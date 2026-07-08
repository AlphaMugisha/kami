<?php
/* Manual verification script for the Shop Arrivals rework.
   Runs the whole scenario inside one transaction and ROLLS BACK at the
   end, so it never leaves any trace in the real data. Run with:
     php scripts/test_shop_arrivals_flow.php
*/
declare(strict_types=1);
chdir(__DIR__ . '/..');
require_once 'config/db.php';
require_once 'includes/stock_functions.php';

function assertTrue(bool $cond, string $msg): void {
    echo ($cond ? "OK   " : "FAIL ") . $msg . "\n";
    if (!$cond) throw new RuntimeException("Assertion failed: $msg");
}

$pdo->beginTransaction();
try {
    $ADMIN_USER = (int)$pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
    $CASHIER_USER = (int)$pdo->query("SELECT id FROM users WHERE role='cashier' LIMIT 1")->fetchColumn() ?: $ADMIN_USER;

    // Locations
    $loc = [];
    foreach ($pdo->query("SELECT id, branch_id, name FROM stock_locations")->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $loc[(int)$l['branch_id']][$l['name']] = (int)$l['id'];
    }
    echo "Locations: " . json_encode($loc) . "\n";
    assertTrue(isset($loc[1]['Big Stock'], $loc[1]['Hanging'], $loc[1]['Fridge'], $loc[1]['Shop Arrivals']), 'Main Branch has all 4 locations');
    assertTrue(isset($loc[2]['Hanging'], $loc[2]['Fridge'], $loc[2]['Shop Arrivals']), 'Second Branch has all 3 locations');

    // Test product
    $stmt = $pdo->prepare("INSERT INTO products (branch_id, shared, sku, name, category, price, buying_price, selling_price, stock) VALUES (1, 1, :sku, :name, 'Imported', 10, 5, 10, 0)");
    $stmt->execute(['sku' => 'TESTSKU-SA2', 'name' => 'Test Shop Arrivals Product']);
    $pid = (int)$pdo->lastInsertId();
    echo "Created test product #$pid\n";

    /* ================= SECOND BRANCH FLOW ================= */
    // 1. Restock into warehouse (Big Stock)
    log_restock($pdo, 1, $pid, 20, 5.00, 10.00, null, 'test restock', $ADMIN_USER, $loc[1]['Big Stock']);
    $stock = (int)$pdo->query("SELECT stock FROM products WHERE id=$pid")->fetchColumn();
    assertTrue($stock === 20, "Product stock is 20 after restock (got $stock)");

    // 2. Admin sends Big Stock -> Second Branch (cross-branch): must go to Shop Arrivals
    $xfer = transfer_stock($pdo, $pid, $loc[1]['Big Stock'], $loc[2]['Shop Arrivals'], 12, $ADMIN_USER, 'refill to second branch');
    assertTrue($xfer['status'] === 'pending', 'Cross-branch transfer to Shop Arrivals is pending');

    // 2b. Admin attempting to send Big Stock -> Second Branch Hanging directly must be rejected
    $rejected = false;
    try {
        transfer_stock($pdo, $pid, $loc[1]['Big Stock'], $loc[2]['Hanging'], 1, $ADMIN_USER, 'bad refill');
    } catch (RuntimeException $e) {
        $rejected = true;
    }
    assertTrue($rejected, 'Cross-branch transfer straight to Hanging (not Shop Arrivals) is rejected');

    // 3. Cashier marks received -> lands in Second Branch's Shop Arrivals
    receive_transfer($pdo, $xfer['transfer_id'], $CASHIER_USER);
    $arrivalsQty = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[2]['Shop Arrivals']} AND product_id=$pid")->fetchColumn();
    assertTrue($arrivalsQty === 12, "12 units now sit in Second Branch Shop Arrivals (got $arrivalsQty)");

    // 4. Cashier classifies: 6 to Fridge, 6 to Hanging (same branch, instant)
    transfer_stock($pdo, $pid, $loc[2]['Shop Arrivals'], $loc[2]['Fridge'], 6, $CASHIER_USER, 'classify');
    transfer_stock($pdo, $pid, $loc[2]['Shop Arrivals'], $loc[2]['Hanging'], 6, $CASHIER_USER, 'classify');

    $fridgeQty = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[2]['Fridge']} AND product_id=$pid")->fetchColumn();
    $hangingQty = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[2]['Hanging']} AND product_id=$pid")->fetchColumn();
    $arrivalsQtyAfter = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[2]['Shop Arrivals']} AND product_id=$pid")->fetchColumn();
    assertTrue($fridgeQty === 6, "Fridge has 6 units (got $fridgeQty)");
    assertTrue($hangingQty === 6, "Hanging has 6 units (got $hangingQty)");
    assertTrue($arrivalsQtyAfter === 0, "Shop Arrivals now empty (got $arrivalsQtyAfter)");

    // 5. Sell 4 from Fridge via FIFO (simulating the Sale page)
    $fifo = deduct_stock_fifo($pid, 4, $pdo, 2, $loc[2]['Fridge']);
    $fridgeQtyAfterSale = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[2]['Fridge']} AND product_id=$pid")->fetchColumn();
    assertTrue($fridgeQtyAfterSale === 2, "Fridge has 2 units left after selling 4 (got $fridgeQtyAfterSale)");
    assertTrue($hangingQty === 6, 'Hanging untouched by a Fridge-only sale');

    // 6. Insert an actual sale row + sale_item + decrement products.stock (mirrors checkout.php)
    $stmt = $pdo->prepare("INSERT INTO sales (cashier_id, branch_id, subtotal, tax, total) VALUES (:c,2,40,7.2,47.2)");
    $stmt->execute(['c' => $CASHIER_USER]);
    $saleId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, batch_id, buying_price, location_id) VALUES (:sid,:pid,4,10,:batch,:buy,:loc)");
    $stmt->execute(['sid' => $saleId, 'pid' => $pid, 'batch' => $fifo['batch_id'], 'buy' => $fifo['avg_buying_price'], 'loc' => $loc[2]['Fridge']]);
    $pdo->exec("UPDATE products SET stock = stock - 4 WHERE id = $pid");

    /* ================= DAILY REPORT SANITY ================= */
    // The daily report counts every `received` transfer into the branch that
    // day: the cross-branch refill itself, plus the two same-branch classify
    // moves (Arrivals -> Fridge, Arrivals -> Hanging) — all 3 are legitimate.
    $refillsReceivedToday = (int)$pdo->query("
        SELECT COUNT(*) FROM stock_transfers t JOIN stock_locations tl ON t.to_location_id = tl.id
         WHERE tl.branch_id = 2 AND t.status='received' AND DATE(t.received_at) = CURDATE() AND t.product_id = $pid
    ")->fetchColumn();
    assertTrue($refillsReceivedToday === 3, "Daily report sees 3 received transfers today for Second Branch: refill + 2 classify moves (got $refillsReceivedToday)");

    $salesToday = (float)$pdo->query("SELECT SUM(total) FROM sales WHERE branch_id=2 AND cashier_id=$CASHIER_USER AND DATE(created_at)=CURDATE() AND id=$saleId")->fetchColumn();
    assertTrue(abs($salesToday - 47.2) < 0.001, "Sale of 47.20 recorded for today (got $salesToday)");

    /* ================= MAIN BRANCH INTERNAL FLOW ================= */
    log_restock($pdo, 1, $pid, 10, 5.00, 10.00, null, 'main restock', $ADMIN_USER, $loc[1]['Big Stock']);

    // Same-branch move Big Stock -> Shop Arrivals: instant (received)
    $mainXfer = transfer_stock($pdo, $pid, $loc[1]['Big Stock'], $loc[1]['Shop Arrivals'], 10, $ADMIN_USER, 'main holding step');
    assertTrue($mainXfer['status'] === 'received', 'Main Branch Big Stock -> Shop Arrivals lands instantly (same branch)');

    $mainArrivalsQty = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[1]['Shop Arrivals']} AND product_id=$pid")->fetchColumn();
    assertTrue($mainArrivalsQty === 10, "Main Branch Shop Arrivals has 10 units (got $mainArrivalsQty)");

    // Classify 4 to Hanging, 6 to Fridge
    transfer_stock($pdo, $pid, $loc[1]['Shop Arrivals'], $loc[1]['Hanging'], 4, $ADMIN_USER, 'classify main');
    transfer_stock($pdo, $pid, $loc[1]['Shop Arrivals'], $loc[1]['Fridge'], 6, $ADMIN_USER, 'classify main');

    $mainHangingQty = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[1]['Hanging']} AND product_id=$pid")->fetchColumn();
    $mainFridgeQty = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[1]['Fridge']} AND product_id=$pid")->fetchColumn();
    $mainArrivalsAfter = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[1]['Shop Arrivals']} AND product_id=$pid")->fetchColumn();
    assertTrue($mainHangingQty === 4, "Main Hanging has 4 units (got $mainHangingQty)");
    assertTrue($mainFridgeQty === 6, "Main Fridge has 6 units (got $mainFridgeQty)");
    assertTrue($mainArrivalsAfter === 0, "Main Shop Arrivals empty after classification (got $mainArrivalsAfter)");

    // Sell from Main Hanging
    deduct_stock_fifo($pid, 2, $pdo, 1, $loc[1]['Hanging']);
    $mainHangingAfterSale = (int)$pdo->query("SELECT COALESCE(SUM(quantity_remaining),0) FROM stock_batches WHERE location_id = {$loc[1]['Hanging']} AND product_id=$pid")->fetchColumn();
    assertTrue($mainHangingAfterSale === 2, "Main Hanging has 2 left after selling 2 (got $mainHangingAfterSale)");

    echo "\nALL ASSERTIONS PASSED\n";
} finally {
    $pdo->rollBack();
    echo "Transaction rolled back — no test data persisted.\n";
}
