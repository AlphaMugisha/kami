<?php
/* ================================================================
   OZONE · receive_transfer.php
   ----------------------------------------------------------------
   AJAX endpoint: confirm a pending cross-branch refill actually
   arrived. Turns it into real, sellable stock at the destination
   location. Callable by admins (any pending transfer) and cashiers
   (only ones destined for their own branch).
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

$is_admin    = ($_SESSION['role'] ?? '') === 'admin';
$user_id     = (int)$_SESSION['user_id'];
$transfer_id = filter_input(INPUT_POST, 'transfer_id', FILTER_VALIDATE_INT);

if (!$transfer_id) {
    echo json_encode(['success' => false, 'message' => 'Please choose which refill to confirm.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name AS product_name, tl.branch_id AS to_branch_id, tl.name AS to_name
          FROM stock_transfers t
          JOIN products p ON t.product_id = p.id
          JOIN stock_locations tl ON t.to_location_id = tl.id
         WHERE t.id = :id
    ");
    $stmt->execute(['id' => $transfer_id]);
    $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($transfer === false) {
        throw new RuntimeException('That refill does not exist.');
    }
    if ($transfer['status'] !== 'pending') {
        throw new RuntimeException('That refill was already received.');
    }

    if (!$is_admin) {
        $cashier_branch_id = (int)($_SESSION['branch_id'] ?? 0);
        if (!$cashier_branch_id || (int)$transfer['to_branch_id'] !== $cashier_branch_id) {
            throw new RuntimeException('You can only confirm refills sent to your own branch.');
        }
    }

    $pdo->beginTransaction();
    receive_transfer($pdo, $transfer_id, $user_id);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Confirmed: {$transfer['quantity']} x {$transfer['product_name']} now in stock at {$transfer['to_name']}.",
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Could not confirm receipt: ' . $e->getMessage()]);
}
