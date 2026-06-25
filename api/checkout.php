<?php
declare(strict_types=1);
session_start();
require_once '../config/db.php';
require_once '../includes/stock_functions.php';

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Read the JSON data sent from Javascript
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Empty payload received.']);
    exit();
}

$cashier_id = $_SESSION['user_id'];
$subtotal = 0;
$updated_stock_data = [];

try {
    // Start a secure database transaction
    $pdo->beginTransaction();

    // 1. Calculate totals securely on the backend (Never trust frontend prices)
    foreach ($data['items'] as $item) {
        // Fetch actual price and stock from DB to prevent hacking
        $stmt = $pdo->prepare("SELECT price, stock FROM products WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $item['id']]);
        $db_product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$db_product) {
            throw new Exception("Product ID " . $item['id'] . " not found in database.");
        }
        if ($db_product['stock'] < $item['qty']) {
            throw new Exception("Insufficient stock for product ID " . $item['id'] . ".");
        }

        $subtotal += ($db_product['price'] * $item['qty']);
    }

    $tax = $subtotal * 0.18; // 18% VAT
    $total = $subtotal + $tax;

    // 2. Insert the main receipt into `sales`
    $stmt = $pdo->prepare("INSERT INTO sales (cashier_id, subtotal, tax, total) VALUES (:cashier, :sub, :tax, :tot)");
    $stmt->execute([
        'cashier' => $cashier_id,
        'sub' => $subtotal,
        'tax' => $tax,
        'tot' => $total
    ]);
    
    $sale_id = $pdo->lastInsertId();

    // 3. Loop again to insert line items and deduct stock
    foreach ($data['items'] as $item) {
        // FIFO: pull this quantity from the oldest batches first, capturing the
        // source batch + weighted-average cost so each sale line is profit-traceable.
        $fifo = deduct_stock_fifo((int)$item['id'], (int)$item['qty'], $pdo);

        // Log the line item with batch traceability.
        $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, batch_id, buying_price) VALUES (:sid, :pid, :qty, :price, :batch, :buy)");
        $stmt->execute([
            'sid'   => $sale_id,
            'pid'   => $item['id'],
            'qty'   => $item['qty'],
            'price' => $item['price'], // We can log the price it was sold at
            'batch' => $fifo['batch_id'],
            'buy'   => $fifo['avg_buying_price'],
        ]);

        // Deduct inventory (running cache the POS reads on every sale)
        $stmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
        $stmt->execute([
            'qty' => $item['qty'],
            'id' => $item['id']
        ]);

        // Fetch the new stock to send back to the UI
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = :id");
        $stmt->execute(['id' => $item['id']]);
        $new_stock = $stmt->fetchColumn();

        $updated_stock_data[] = [
            'id' => $item['id'],
            'new_stock' => $new_stock
        ];
    }

    // Commit the transaction - Everything succeeded!
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'transaction_id' => $sale_id,
        'updated_stock' => $updated_stock_data
    ]);

} catch (Exception $e) {
    // If anything fails, rollback the entire transaction
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>