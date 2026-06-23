<?php
// menu/process_order.php
declare(strict_types=1);

// Ensure the server responds with JSON so our JavaScript frontend understands it
header('Content-Type: application/json');

// Pull in your database connection 
// (Assuming your connection variable is $pdo and lives in config/db.php)
require_once '../config/db.php'; 

// 1. Catch the JSON payload sent by the frontend
$jsonPayload = file_get_contents('php://input');
$data = json_decode($jsonPayload, true);

// Security Check: Did we actually get valid data with items?
if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty order payload.']);
    exit();
}

// 2. Extract the high-level order details
$table_number = $data['table'] ?? 'Unknown';
$total_amount = $data['total'] ?? 0.00;
$payment_method = $data['method'] ?? 'momo';
$items = $data['items'];

// 3. Start a Database Transaction
// This is an enterprise standard: If the main order saves, but the line items fail, 
// it rolls everything back so you don't get corrupted, half-finished orders.
$pdo->beginTransaction();

try {
    // Step A: Insert the high-level order into `qr_orders`
    $stmt = $pdo->prepare("INSERT INTO qr_orders (table_number, total_amount, payment_method, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$table_number, $total_amount, $payment_method]);
    
    // Grab the ID of the order we just created so we can link the items to it
    $order_id = $pdo->lastInsertId();

    // Step B: Loop through the cart and insert each bottle into `qr_order_items`
    $stmt_items = $pdo->prepare("INSERT INTO qr_order_items (order_id, product_name, quantity, price) VALUES (?, ?, ?, ?)");
    
    foreach ($items as $item) {
        $name = $item['name'];
        $qty = $item['quantity'];
        $price = $item['price'];
        
        $stmt_items->execute([$order_id, $name, $qty, $price]);
    }

    // Step C: If we made it here with no errors, officially commit the save!
    $pdo->commit();
    
    // Tell the frontend JavaScript that it was a success
    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
    // If anything fails, undo the database changes and return an error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>