<?php
$userName = $_SESSION['full_name'] ?? 'Cashier';
$userRole = ucfirst($_SESSION['role'] ?? 'Cashier');
$current_page = basename($_SERVER['PHP_SELF']);

$nameParts = explode(' ', $userName);
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>

<style>
  /* 1. Fix the Sidebar Overlapping */
  .sidebar {
    display: flex;
    flex-direction: column;
    height: 100vh; 
    overflow: hidden;
    position: relative;
  }
  .sidebar .brand { flex-shrink: 0; }
  .sidebar .nav-menu {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 20px;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .sidebar .nav-menu::-webkit-scrollbar { display: none; }
  
  /* Force the profile to stop absolute floating */
  .sidebar .user-profile {
    position: relative !important;
    bottom: auto !important;
    margin-top: auto !important;
    flex-shrink: 0;
  }

</style>

<script>
(function() {
  var themes = {
    gold:    { primary: '#E0B85C', hover: '#F0CE7E', bg: 'rgba(224, 184, 92, 0.12)', border: 'rgba(224, 184, 92, 0.32)' },
    blue:    { primary: '#5EB3FF', hover: '#8AC9FF', bg: 'rgba(94, 179, 255, 0.12)',  border: 'rgba(94, 179, 255, 0.3)' },
    purple:  { primary: '#BF5AF2', hover: '#D08DFF', bg: 'rgba(191, 90, 242, 0.12)',  border: 'rgba(191, 90, 242, 0.3)' },
    emerald: { primary: '#4ADE80', hover: '#63E6BE', bg: 'rgba(74, 222, 128, 0.12)',  border: 'rgba(74, 222, 128, 0.3)' }
  };
  var t = localStorage.getItem('kami_theme') || 'gold';
  var c = themes[t];
  if (c) {
    var s = document.documentElement.style;
    s.setProperty('--kami-accent', c.primary);
    s.setProperty('--kami-accent-hover', c.hover);
    s.setProperty('--kami-accent-bg', c.bg);
    s.setProperty('--kami-accent-border', c.border);
    s.setProperty('--kami-accent-grad', 'linear-gradient(135deg, ' + c.hover + ' 0%, ' + c.primary + ' 100%)');
    s.setProperty('--accent-primary', c.primary);
  }
})();
</script>

<audio id="global-order-chime" preload="auto">
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
</audio>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="ph-fill ph-martini"></i></div>
        <span class="brand-name">OZONE</span>
        <span class="brand-badge">POS</span>
    </div>
    
    <nav class="nav-menu">
        <span class="nav-section">Checkout</span>
        <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="ph-fill ph-shopping-bag"></i> Sales Register
        </a>
        <a href="live_orders.php" class="nav-link <?= $current_page == 'live_orders.php' ? 'active' : '' ?>">
            <i class="ph-fill ph-bell-ringing"></i> 
            <span>Live Orders</span>
            <span style="margin-left: auto; width: 8px; height: 8px; background: var(--kami-success); border-radius: 50%; box-shadow: 0 0 8px var(--kami-success); animation: pulse 2s infinite;"></span>
        </a>
        <span class="nav-section">Management</span>
        <a href="inventory.php" class="nav-link <?= $current_page == 'inventory.php' ? 'active' : '' ?>">
            <i class="ph ph-package"></i> Inventory Control
        </a>
        
        <span class="nav-section">Shift Tools</span>
        <a href="timeclock.php" class="nav-link <?= $current_page == 'timeclock.php' ? 'active' : '' ?>">
            <i class="ph-fill ph-clock"></i> Time Clock
        </a>
        <a href="zreport.php" class="nav-link <?= $current_page == 'zreport.php' ? 'active' : '' ?>">
            <i class="ph-fill ph-receipt"></i> Daily Z-Report
        </a>
        <a href="history.php" class="nav-link <?= $current_page == 'history.php' ? 'active' : '' ?>">
            <i class="ph-fill ph-clock-counter-clockwise"></i> Shift History
        </a>
        
        <span class="nav-section">Account</span>
        <a href="qr_builder.php" class="nav-link <?= ($current_page === 'qr_builder.php') ? 'active' : '' ?>">
            <i class="ph-fill ph-qr-code"></i>
            <span>QR Generator</span>
        </a>
        <a href="settings.php" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>">
            <i class="ph-fill ph-lock-key"></i> Security Settings
        </a>
    </nav>

    <div class="user-profile">
        <div class="user-avatar"><?= $initials ?></div>
        <div class="user-info">
            <p class="user-name"><?= htmlspecialchars($userName) ?></p>
            <span class="user-role"><?= htmlspecialchars($userRole) ?></span>
        </div>
        <a href="../logout.php" class="logout-btn" title="End Shift & Logout">
            <i class="ph-bold ph-sign-out"></i>
        </a>
    </div>
</aside>

<script src="../assets/js/ozone.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Alerts route to the luxury toast stack (window.triggerDynamicIsland
    // is provided by ozone.js as a back-compat shim -> ozoneToast).

    // ==========================================
    // GLOBAL BACKGROUND ORDER MONITOR
    // ==========================================
    async function checkGlobalOrders() {
        try {
            const response = await fetch('live_orders.php?ajax=fetch');
            if (!response.ok) return;
            const data = await response.json();
            
            if (!data.success || data.orders.length === 0) return;

            const latestOrderId = Math.max(...data.orders.map(o => parseInt(o.id)));
            const savedOrderId = parseInt(localStorage.getItem('kami_last_seen_order') || 0);

            if (latestOrderId > savedOrderId) {
                localStorage.setItem('kami_last_seen_order', latestOrderId);
                
                if (savedOrderId !== 0) {
                    const chime = document.getElementById('global-order-chime');
                    if (chime) chime.play().catch(e => console.log('Audio blocked pending user interaction.'));
                    
                    const newestOrder = data.orders.find(o => parseInt(o.id) === latestOrderId);
                    if (window.triggerDynamicIsland) {
                        window.triggerDynamicIsland('New VIP Request', `Lounge/Table ${newestOrder.table} requires service.`, 'success');
                    }
                }
            }
        } catch (err) {
            console.error("Global monitor error:", err);
        }
    }

    setInterval(checkGlobalOrders, 4000);
    checkGlobalOrders(); 
});
</script>