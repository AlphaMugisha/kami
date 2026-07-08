<?php
/* ================================================================
   OZONE · transfer_stock.php
   ----------------------------------------------------------------
   AJAX endpoint: move stock between two named locations — same
   branch, or across branches (an admin "refilling" Second Branch's
   shop floor from Main Branch's warehouse, since products are one
   shared catalog now). Used from the transfer modal on
   admin/locations.php, admin/inventory.php and cashier/inventory.php.

   Permissions:
     - Admins  : any location -> any location.
     - Cashiers: only between locations that both belong to their own
                 branch (moving stock IN from another branch is an
                 admin-only "refill" decision).
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

$product_id       = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$from_location_id = filter_input(INPUT_POST, 'from_location_id', FILTER_VALIDATE_INT);
$to_location_id   = filter_input(INPUT_POST, 'to_location_id', FILTER_VALIDATE_INT);
$quantity         = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$notes            = trim((string)($_POST['notes'] ?? ''));

if (!$product_id || !$from_location_id || !$to_location_id || !$quantity || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please choose a product, both locations, and a positive quantity.']);
    exit();
}
if ($from_location_id === $to_location_id) {
    echo json_encode(['success' => false, 'message' => 'Source and destination must be different locations.']);
    exit();
}

try {
    // Guard: product must exist in the shared catalog.
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $product_name = $stmt->fetchColumn();
    if ($product_name === false) {
        throw new RuntimeException('That product does not exist.');
    }

    // Guard: both locations must exist.
    $stmt = $pdo->prepare("SELECT id, name, branch_id, is_warehouse, is_arrival FROM stock_locations WHERE id IN (:from_loc, :to_loc)");
    $stmt->execute(['from_loc' => $from_location_id, 'to_loc' => $to_location_id]);
    $found_by_id = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $found_by_id[(int)$l['id']] = $l;
    }
    if (!isset($found_by_id[$from_location_id]) || !isset($found_by_id[$to_location_id])) {
        throw new RuntimeException('One of those locations does not exist.');
    }

    // Permission: cashiers may only shuffle stock within their own branch's
    // own locations — and never touch the warehouse (Big Stock); moving stock
    // IN from another branch (a "refill") or out of the warehouse is an
    // admin-only decision, made from admin/locations.php.
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

    $pdo->beginTransaction();
    $result = transfer_stock($pdo, $product_id, $from_location_id, $to_location_id, $quantity, $user_id, $notes ?: null);
    $pdo->commit();

    $from_name = $found_by_id[$from_location_id]['name'];
    $to_name   = $found_by_id[$to_location_id]['name'];

    $message = $result['status'] === 'pending'
        ? "Sent {$quantity} x {$product_name}: {$from_name} -> {$to_name}. It'll be sellable once they mark it received."
        : "Moved {$quantity} x {$product_name}: {$from_name} -> {$to_name}.";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'status'  => $result['status'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Transfer failed: ' . $e->getMessage()]);
}
