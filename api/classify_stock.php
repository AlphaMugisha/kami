<?php
/* ================================================================
   OZONE · classify_stock.php
   ----------------------------------------------------------------
   AJAX endpoint for the cashier's "What we have in the shop" page:
   splits stock sitting in the cashier's own branch "Shop Arrivals"
   holding location out onto the sellable shelves (Hanging / Fridge),
   in one atomic move. A single product can be split across both
   (e.g. 6 to the fridge, 6 hanging).

   Same-branch move, so transfer_stock() lands it instantly — no
   sent/pending/received handshake needed here (that's only for
   cross-branch refills).
   ================================================================ */

declare(strict_types=1);
session_start();
require_once '../config/db.php';
require_once '../includes/stock_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$branch_id = (int)($_SESSION['branch_id'] ?? $_SESSION['admin_branch_id'] ?? 0);

if (!$branch_id) {
    echo json_encode(['success' => false, 'message' => 'No branch selected for this session. Please sign in again.']);
    exit();
}

$product_id   = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$qty_hanging  = filter_input(INPUT_POST, 'qty_hanging', FILTER_VALIDATE_INT) ?: 0;
$qty_fridge   = filter_input(INPUT_POST, 'qty_fridge', FILTER_VALIDATE_INT) ?: 0;

if (!$product_id || $qty_hanging < 0 || $qty_fridge < 0 || ($qty_hanging + $qty_fridge) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Choose a product and at least one positive quantity for Hanging or Fridge.']);
    exit();
}

try {
    // This branch's three fixed locations: the holding area we're classifying
    // OUT of, and the two sellable shelves we're classifying INTO.
    $stmt = $pdo->prepare("SELECT id, name, is_arrival FROM stock_locations WHERE branch_id = :bid AND name IN ('Shop Arrivals', 'Hanging', 'Fridge')");
    $stmt->execute(['bid' => $branch_id]);
    $byName = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $byName[$l['name']] = $l;
    }
    if (!isset($byName['Shop Arrivals']) || !$byName['Shop Arrivals']['is_arrival']) {
        throw new RuntimeException('This branch has no Shop Arrivals location set up.');
    }
    if ($qty_hanging > 0 && !isset($byName['Hanging'])) {
        throw new RuntimeException('This branch has no Hanging location set up.');
    }
    if ($qty_fridge > 0 && !isset($byName['Fridge'])) {
        throw new RuntimeException('This branch has no Fridge location set up.');
    }

    $arrivals_id = (int)$byName['Shop Arrivals']['id'];

    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $product_name = $stmt->fetchColumn();
    if ($product_name === false) {
        throw new RuntimeException('That product does not exist.');
    }

    $pdo->beginTransaction();

    $moved_parts = [];
    if ($qty_hanging > 0) {
        transfer_stock($pdo, $product_id, $arrivals_id, (int)$byName['Hanging']['id'], $qty_hanging, $user_id, 'Classified from Shop Arrivals');
        $moved_parts[] = "{$qty_hanging} to Hanging";
    }
    if ($qty_fridge > 0) {
        transfer_stock($pdo, $product_id, $arrivals_id, (int)$byName['Fridge']['id'], $qty_fridge, $user_id, 'Classified from Shop Arrivals');
        $moved_parts[] = "{$qty_fridge} to Fridge";
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Classified {$product_name}: " . implode(', ', $moved_parts) . '.',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Could not classify stock: ' . $e->getMessage()]);
}
