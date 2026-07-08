<?php
// cashier/live_orders.php
declare(strict_types=1);
session_start();

// Enforce Cashier or Admin access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

// Live/table ordering only exists at Main Branch (a lounge feature); Second
// Branch is a plain walk-in shop and never gets this page. Admins (no
// $_SESSION['branch_id']) always pass through.
$mainBranchId = (int)($pdo->query("SELECT id FROM branches WHERE is_main = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
if (isset($_SESSION['branch_id']) && (int)$_SESSION['branch_id'] !== $mainBranchId) {
    header("Location: index.php");
    exit();
}

// ============================================================================
// 1. AJAX API HANDLER
// ============================================================================
if (isset($_GET['ajax'])) {
    ob_clean(); 
    header('Content-Type: application/json');
    
    // A. Update order status
    if ($_GET['ajax'] === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $order_id = (int)($data['order_id'] ?? 0);
        $new_status = $data['status'] ?? '';
        
        if ($order_id > 0 && in_array($new_status, ['preparing', 'served', 'completed'])) {
            try {
                $stmt = $pdo->prepare("UPDATE qr_orders SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $order_id])) {
                    echo json_encode(['success' => true]);
                    exit();
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit();
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit();
    }

    // B. Delete Order
    if ($_GET['ajax'] === 'delete_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $order_id = (int)($data['order_id'] ?? 0);
        
        if ($order_id > 0) {
            try {
                $pdo->prepare("DELETE FROM qr_order_items WHERE order_id = ?")->execute([$order_id]);
                $pdo->prepare("DELETE FROM qr_orders WHERE id = ?")->execute([$order_id]);
                
                echo json_encode(['success' => true]);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit();
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit();
    }

    // C. Fetch all active orders
    if ($_GET['ajax'] === 'fetch') {
        $orders = [];
        try {
            $query = "SELECT o.id, o.table_number, o.total_amount, o.payment_method, o.status, o.created_at,
                             i.product_name, i.quantity
                      FROM qr_orders o
                      LEFT JOIN qr_order_items i ON o.id = i.order_id
                      WHERE o.status IN ('pending', 'preparing', 'served') AND o.branch_id = :bid
                      ORDER BY o.created_at ASC";

            $stmt = $pdo->prepare($query);
            $stmt->execute(['bid' => $mainBranchId]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $oid = $row['id'];
                if (!isset($orders[$oid])) {
                    $orders[$oid] = [
                        'id' => $oid,
                        'table' => $row['table_number'],
                        'total' => $row['total_amount'],
                        'method' => strtoupper($row['payment_method']),
                        'status' => $row['status'],
                        'time' => date('H:i', strtotime($row['created_at'])),
                        'items' => []
                    ];
                }
                if ($row['product_name']) {
                    $orders[$oid]['items'][] = $row['quantity'] . 'x ' . $row['product_name'];
                }
            }
            echo json_encode(['success' => true, 'orders' => array_values($orders)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

?>
<?php
$staff_area = 'cashier';
$page_title = 'Live Orders';
require '../includes/staff_header.php';
?>
    <style>
        .ticket { display: flex; flex-direction: column; background: var(--kami-surface-2); border: 1px solid var(--kami-border); border-top: 4px solid var(--kami-accent); border-radius: 16px; padding: 20px; grid-column: span 4; transition: transform 0.2s; position: relative; }
        
        /* Dynamic Ticket Border Colors & Backgrounds */
        .ticket.preparing { border-top-color: var(--kami-info, #0A84FF); }
        .ticket.served { border-top-color: var(--kami-success, #34d399); background: rgba(0,0,0,0.3); }

        .ticket-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 16px; margin-bottom: 16px; }
        
        .table-info-block { display: flex; flex-direction: column; gap: 4px; }
        .table-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--kami-text-muted); font-weight: 800; }
        .table-id { font-family: 'Space Grotesk', sans-serif; font-size: 28px; font-weight: 800; color: #fff; line-height: 1; }
        
        .time-badge { font-size: 12px; color: var(--kami-text-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.05); padding: 6px 12px; border-radius: 100px; }

        /* Dynamic Status Pills */
        .status-pill { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; padding: 4px 10px; border-radius: 100px; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 12px; align-self: flex-start; }
        .status-pill.pending { background: rgba(255, 176, 0, 0.15); color: #FFB000; border: 1px solid rgba(255, 176, 0, 0.3); }
        .status-pill.preparing { background: rgba(10, 132, 255, 0.15); color: #0A84FF; border: 1px solid rgba(10, 132, 255, 0.3); }
        .status-pill.served { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }

        .ticket-items { list-style: none; padding: 0; margin: 0 0 20px 0; flex: 1; display: flex; flex-direction: column; gap: 12px; }
        .ticket-items li { display: flex; align-items: flex-start; gap: 12px; }
        .qty-pill { background: rgba(255, 255, 255, 0.1); color: var(--kami-accent, #FFB000); padding: 4px 8px; border-radius: 6px; font-size: 13px; font-weight: 800; white-space: nowrap; }
        .item-text { font-size: 16px; font-weight: 600; color: rgba(255,255,255,0.9); line-height: 1.4; }
        
        .totals { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 16px; }
        .payment-method { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800; padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.05); color: var(--kami-text-muted); border: 1px solid rgba(255,255,255,0.1); }
        .payment-method.momo { background: rgba(56, 189, 248, 0.1); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }
        .total-price { font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 800; color: #fff; }
        
        .action-row { display: flex; gap: 8px; margin-top: 20px; width: 100%; }
        
        .btn-delete { flex-shrink: 0; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; border-radius: 12px; width: 54px; display: flex; align-items: center; justify-content: center; font-size: 22px; cursor: pointer; transition: 0.2s; }
        .btn-delete:hover { background: #ef4444; color: #fff; }
        
        .btn-action-main { flex: 1; border: none; border-radius: 12px; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; padding: 16px 12px; transition: 0.2s; }
        
        .btn-accept { background: var(--kami-accent); color: #000; }
        .btn-accept:hover { filter: brightness(1.1); transform: translateY(-1px); }
        
        .btn-serve { background: var(--kami-info, #0A84FF); color: #fff; }
        .btn-serve:hover { filter: brightness(1.1); transform: translateY(-1px); }

        .btn-paid { background: var(--kami-success, #34d399); color: #000; }
        .btn-paid:hover { filter: brightness(1.1); transform: translateY(-1px); }

        .btn-disabled { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); border: 1px solid rgba(255,255,255,0.1); cursor: not-allowed; }

        @media (max-width: 1400px) { .ticket { grid-column: span 6; } }
        @media (max-width: 900px) { .ticket { grid-column: span 12; } }
        .empty-state { grid-column: span 12; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 100px 20px; background: rgba(0,0,0,0.2); border: 2px dashed rgba(255,255,255,0.1); border-radius: 24px; text-align: center; }
        .empty-state-icon { width: 80px; height: 80px; background: rgba(255,255,255,0.05); color: var(--kami-text-muted); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 24px; }
    </style>

        <h1 class="kami-page-title">Live Sommelier Feed</h1>
        <p class="kami-page-sub">Real-time lounge requests and table service telemetry.</p>

        <div class="bento-grid animate-fade-in" id="orderFeed">
            <!-- Injected by JS -->
        </div>

    <script>
        const feed = document.getElementById('orderFeed');

        async function fetchOrdersUI() {
            try {
                const response = await fetch('live_orders.php?ajax=fetch');
                const data = await response.json();
                if (!data.success) return;
                renderTickets(data.orders);
            } catch (err) {
                console.error("UI Polling error:", err);
            }
        }

        function renderTickets(orders) {
            if (orders.length === 0) {
                feed.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="ph-fill ph-martini"></i></div>
                        <h3 style="font-family: 'Space Grotesk'; font-size: 24px; color: #fff;">All lounges served & paid</h3>
                        <p style="color: #888;">Waiting for clients to submit new orders.</p>
                    </div>`;
                return;
            }

            let html = '';
            orders.forEach(order => {
                let itemsHtml = '';
                order.items.forEach(itemStr => {
                    const match = itemStr.match(/^(\d+)x\s+(.+)$/);
                    if(match) {
                        itemsHtml += `<li><span class="qty-pill">${match[1]}x</span> <span class="item-text">${match[2]}</span></li>`;
                    } else {
                        itemsHtml += `<li><span class="qty-pill">1x</span> <span class="item-text">${itemStr}</span></li>`;
                    }
                });

                const badgeClass = order.method === 'MOMO' ? 'momo' : '';
                
                // Set the visual state of the ticket based on status
                let ticketClass = '';
                let statusBadge = '';
                let actionBtn = '';

                if (order.status === 'pending') {
                    statusBadge = `<span class="status-pill pending"><i class="ph-bold ph-warning-circle"></i> New Order</span>`;
                    actionBtn = `
                        <button class="btn-action-main btn-accept" onclick="updateOrderStatus(${order.id}, 'preparing')">
                            <i class="ph-bold ph-cooking-pot"></i> Accept & Prepare
                        </button>`;
                } else if (order.status === 'preparing') {
                    ticketClass = 'preparing';
                    statusBadge = `<span class="status-pill preparing"><i class="ph-bold ph-cooking-pot"></i> Preparing</span>`;
                    actionBtn = `
                        <button class="btn-action-main btn-serve" onclick="updateOrderStatus(${order.id}, 'served')">
                            <i class="ph-bold ph-tray"></i> Serve to Table
                        </button>`;
                } else if (order.status === 'served') {
                    ticketClass = 'served';
                    statusBadge = `<span class="status-pill served"><i class="ph-bold ph-currency-dollar"></i> Awaiting Payment</span>`;
                    actionBtn = `
                        <button class="btn-action-main btn-disabled" disabled>
                            <i class="ph-bold ph-check"></i> Served
                        </button>
                        <button class="btn-action-main btn-paid" onclick="updateOrderStatus(${order.id}, 'completed')">
                            <i class="ph-bold ph-currency-dollar"></i> Confirm Paid
                        </button>`;
                }
                
                let deleteBtn = `
                    <button class="btn-delete" onclick="deleteOrder(${order.id})" title="Cancel Order">
                        <i class="ph-bold ph-trash"></i>
                    </button>
                `;
                
                html += `
                <div class="ticket ${ticketClass}">
                    ${statusBadge}
                    <div class="ticket-header">
                        <div class="table-info-block">
                            <div class="table-label">Lounge / Table</div>
                            <div class="table-id">${order.table}</div>
                        </div>
                        <div class="time-badge"><i class="ph-bold ph-clock"></i> ${order.time}</div>
                    </div>
                    <ul class="ticket-items">${itemsHtml}</ul>
                    <div class="ticket-footer">
                        <div class="totals">
                            <span class="payment-method ${badgeClass}">${order.method}</span>
                            <span class="total-price">$${parseFloat(order.total).toFixed(2)}</span>
                        </div>
                        <div class="action-row">
                            ${deleteBtn}
                            ${actionBtn}
                        </div>
                    </div>
                </div>`;
            });

            feed.innerHTML = html;
        }

        async function updateOrderStatus(orderId, newStatus) {
            try {
                const response = await fetch('live_orders.php?ajax=update_status', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId, status: newStatus })
                });
                const data = await response.json();
                if (data.success) {
                    if (window.triggerDynamicIsland) {
                        let msg = '';
                        if (newStatus === 'preparing') msg = 'Order accepted for prep.';
                        if (newStatus === 'served') msg = 'Order marked as served. Awaiting payment.';
                        if (newStatus === 'completed') msg = 'Payment confirmed. Ticket cleared.';
                        window.triggerDynamicIsland('Status Updated', msg, 'info');
                    }
                    fetchOrdersUI(); 
                }
            } catch (err) { console.error(err); }
        }

        async function deleteOrder(orderId) {
            if (!confirm("Are you sure you want to completely delete this order? This cannot be undone.")) return;
            try {
                const response = await fetch('live_orders.php?ajax=delete_order', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: orderId })
                });
                const data = await response.json();
                if (data.success) {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Order Deleted', 'The request has been removed.', 'error');
                    fetchOrdersUI(); 
                }
            } catch (err) { console.error(err); }
        }

        fetchOrdersUI();
        setInterval(fetchOrdersUI, 3000);
    </script>
<?php require '../includes/staff_footer.php'; ?>