<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$initials = '';
$fullName = htmlspecialchars($_SESSION['full_name'] ?? 'Admin');
$roleName = htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Admin'));
$nameParts = explode(' ', $_SESSION['full_name'] ?? 'A');
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>

<style>
  .sidebar {
    display: flex;
    flex-direction: column;
    height: 100vh; /* Lock to exact viewport height */
    overflow: hidden; /* Prevent the sidebar itself from scrolling */
  }
  
  .sidebar .brand {
    flex-shrink: 0; /* Pin to top */
  }

  .sidebar .nav-menu {
    flex: 1; /* Take up all remaining space in the middle */
    overflow-y: auto; /* Allow internal scrolling if zoomed in */
    padding-bottom: 20px;
    
    /* Hide scrollbar for a clean UI */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none;  /* IE and Edge */
  }
  
  .sidebar .nav-menu::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
  }

  .sidebar .user-profile {
    flex-shrink: 0; /* Pin to bottom, prevent squishing */
    margin-top: auto; /* Push to absolute bottom */
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
    // Sync legacy variables for backward compatibility if any
    s.setProperty('--accent-primary', c.primary);
    s.setProperty('--accent-gold', c.primary);
  }
  if (localStorage.getItem('kami_theme_mode') === 'light') document.documentElement.classList.add('light-mode');
  if (localStorage.getItem('kami_reduce_motion') === 'true') document.documentElement.classList.add('reduce-motion');
  if (localStorage.getItem('kami_high_contrast') === 'true') document.documentElement.classList.add('high-contrast');
})();
</script>

<aside class="sidebar">
  <div class="brand">
    <div class="brand-icon"><i class="ph-fill ph-martini"></i></div>
    <span class="brand-name">OZONE</span>
    <span class="brand-badge">Admin</span>
  </div>

  <nav class="nav-menu">
    <span class="nav-section">Main</span>
    <a href="index.php" class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
      <i class="ph-fill ph-squares-four"></i>
      <span>Dashboard</span>
    </a>

    <span class="nav-section">Management & Audits</span>
    <a href="inventory.php" class="nav-link <?= ($currentPage === 'inventory.php') ? 'active' : '' ?>">
      <i class="ph-fill ph-package"></i>
      <span>Inventory</span>
    </a>
    <a href="reports.php" class="nav-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
      <i class="ph-fill ph-archive"></i>
      <span>Audit Reports</span>
    </a>
    <a href="history.php" class="nav-link <?= ($currentPage === 'history.php') ? 'active' : '' ?>">
      <i class="ph-fill ph-books"></i>
      <span>Global Shift History</span>
    </a>
    <a href="users.php" class="nav-link <?= ($currentPage === 'users.php') ? 'active' : '' ?>">
      <i class="ph-fill ph-users-three"></i>
      <span>Users</span>
    </a>

    <span class="nav-section">System</span>
    <a href="settings.php" class="nav-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>">
      <i class="ph-fill ph-gear-six"></i>
      <span>Settings</span>
    </a>
  </nav>

  <div class="user-profile">
    <div class="user-avatar"><?= $initials ?></div>
    <div class="user-info">
      <p class="user-name"><?= $fullName ?></p>
      <span class="user-role"><?= $roleName ?></span>
    </div>
    <a href="../logout.php" class="logout-btn" title="Sign out">
      <i class="ph-bold ph-sign-out"></i>
    </a>
  </div>
</aside>

<script src="../assets/js/ozone.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Alerts route to the luxury toast stack (window.triggerDynamicIsland
    // is provided by ozone.js as a back-compat shim -> ozoneToast).

    // Session-based greeting notification
    if (!sessionStorage.getItem('greeted')) {
        setTimeout(() => {
            window.triggerDynamicIsland(
                'Operational Connection', 
                'Welcome back. Secure link active for <?= $fullName ?>.', 
                'success'
            );
            sessionStorage.setItem('greeted', 'true');
        }, 1000);
    }
});
</script>