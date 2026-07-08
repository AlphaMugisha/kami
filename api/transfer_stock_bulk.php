<?php
/* ================================================================
   OZONE · transfer_stock_bulk.php
   ----------------------------------------------------------------
   AJAX endpoint: move MULTIPLE products from one location to another
   in a single shipment (e.g. an admin refilling Second Branch with
   five different products at once, instead of five separate trips
   through transfer_stock.php). Same permission rules and the same
   transfer_stock() engine underneath — this just loops it over many
   products atomically, all sharing one From/To/notes.

   Body (JSON): {
     from_location_id, to_location_id, notes,
     rows: [{ product_id, quantity }, ...]
   }
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

$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$user_id  = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
    exit();
}

$from_location_id = filter_var($data['from_location_id'] ?? null, FILTER_VALIDATE_INT);
$to_location_id   = filter_var($data['to_location_id'] ?? null, FILTER_VALIDATE_INT);
$notes            = trim((string)($data['notes'] ?? ''));
$rows             = is_array($data['rows'] ?? null) ? $data['rows'] : [];

if (!$from_location_id || !$to_location_id) {
    echo json_encode(['success' => false, 'message' => 'Please choose both locations.']);
    exit();
}
if ($from_location_id === $to_location_id) {
    echo json_encode(['success' => false, 'message' => 'Source and destination must be different locations.']);
    exit();
}
if (empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'Add at least one product to move.']);
    exit();
}

try {
    // Guard: both locations must exist.
    $stmt = $pdo->prepare("SELECT id, name, branch_id, is_warehouse FROM stock_locations WHERE id IN (:from_loc, :to_loc)");
    $stmt->execute(['from_loc' => $from_location_id, 'to_loc' => $to_location_id]);
    $found_by_id = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $found_by_id[(int)$l['id']] = $l;
    }
    if (!isset($found_by_id[$from_location_id]) || !isset($found_by_id[$to_location_id])) {
        throw new RuntimeException('One of those locations does not exist.');
    }

    // Same permission rules as the single-item transfer endpoint.
    if (!$is_admin) {
        $cashier_branch_id = (int)($_SESSION['branch_id'] ?? 0);
        if (!$cashier_branch_id
            || (int)$found_by_id[$from_location_id]['branch_id'] !== $cashier_branch_id
            || (int)$found_by_id[$to_location_id]['branch_id'] !== $cashier_branch_id
        ) {
            throw new RuntimeException('You can only move stock between locations at your own branch.');
        }
        if ($found_by_id[$from_location_id]['is_warehouse'] || $found_by_id[$to_location_id]['is_warehouse']) {
            throw new RuntimeException('Only an admin can move stock in or out of the warehouse.');
        }
    }

    // Validate + normalize every row before touching the database.
    $clean_rows = [];
    $seen_products = [];
    foreach ($rows as $i => $r) {
        $line = $i + 1;
        $product_id = filter_var($r['product_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity   = filter_var($r['quantity'] ?? null, FILTER_VALIDATE_INT);
        if (!$product_id) {
            throw new RuntimeException("Row {$line}: choose a product.");
        }
        if (!$quantity || $quantity <= 0) {
            throw new RuntimeException("Row {$line}: quantity must be greater than zero.");
        }
        if (isset($seen_products[$product_id])) {
            throw new RuntimeException("Row {$line}: this product is already in the list above — combine them into one row.");
        }
        $seen_products[$product_id] = true;
        $clean_rows[] = ['product_id' => $product_id, 'quantity' => $quantity];
    }

    $product_ids = array_column($clean_rows, 'product_id');
    $in = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id IN ($in)");
    $stmt->execute($product_ids);
    $product_names = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $product_names[(int)$p['id']] = $p['name'];
    }
    foreach ($clean_rows as $row) {
        if (!isset($product_names[$row['product_id']])) {
            throw new RuntimeException("Product #{$row['product_id']} does not exist.");
        }
    }

    $clean_notes = $notes !== '' ? $notes : null;

    // Atomic: either the whole shipment moves, or none of it does — a
    // partial refill would leave a confusing half-sent shipment behind.
    $pdo->beginTransaction();
    $moved = [];
    $status = 'received';
    foreach ($clean_rows as $row) {
        $result = transfer_stock($pdo, $row['product_id'], $from_location_id, $to_location_id, $row['quantity'], $user_id, $clean_notes);
        $status = $result['status'];
        $moved[] = "{$row['quantity']} x {$product_names[$row['product_id']]}";
    }
    $pdo->commit();

    $from_name = $found_by_id[$from_location_id]['name'];
    $to_name   = $found_by_id[$to_location_id]['name'];
    $message = $status === 'pending'
        ? "Sent " . count($moved) . " product(s): {$from_name} -> {$to_name}. Sellable once received."
        : "Moved " . count($moved) . " product(s): {$from_name} -> {$to_name}.";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'status'  => $status,
        'moved'   => $moved,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Transfer failed: ' . $e->getMessage()]);
}
