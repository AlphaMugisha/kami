<?php
/* ================================================================
   OZONE · Shared staff shell — HEADER
   ----------------------------------------------------------------
   Renders <html><head>, the fixed 180px sidebar (separate nav sets
   for admin vs cashier), the fixed topbar, and opens .kami-content.
   Close the page with includes/staff_footer.php.

   The calling page must have already run session_start() + auth, and
   may set (before requiring this file):
     $staff_area   'admin' | 'cashier'   (auto-detected from path if unset)
     $page_title   string  -> <title> and topbar context
     $page_styles  string  -> extra <style> CSS injected into <head>
     $active_page  string  -> override active nav (defaults to current file)
   ================================================================ */

$staff_area = $staff_area ?? (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'admin' : 'cashier');
$page_title = $page_title ?? 'Ozone';
$active_page = $active_page ?? basename($_SERVER['PHP_SELF']);

$fullName = $_SESSION['full_name'] ?? ($staff_area === 'admin' ? 'Admin' : 'Cashier');
$roleName = ucfirst($_SESSION['role'] ?? $staff_area);
$nameParts = preg_split('/\s+/', trim($fullName)) ?: ['A'];
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

/* Per-area sidebar nav: [section label => [ [file, icon, label, dot?], ... ] ] */
if ($staff_area === 'admin') {
    $brandBadge = 'Admin';
    $navGroups = [
        'Main' => [
            ['index.php', 'ph-squares-four', 'Dashboard'],
        ],
        'Management & Audits' => [
            ['inventory.php', 'ph-package', 'Inventory'],
            ['restock.php', 'ph-shopping-cart-simple', 'Restock'],
            ['reports.php', 'ph-archive', 'Audit Reports'],
            ['history.php', 'ph-books', 'Shift History'],
            ['users.php', 'ph-users-three', 'Users'],
        ],
        'System' => [
            ['settings.php', 'ph-gear-six', 'Settings'],
        ],
    ];
} else {
    $brandBadge = 'POS';
    $navGroups = [
        'Overview' => [
            ['dashboard.php', 'ph-squares-four', 'My Shift'],
        ],
        'Checkout' => [
            ['index.php', 'ph-shopping-bag', 'Sales Register'],
            ['live_orders.php', 'ph-bell-ringing', 'Live Orders', true],
        ],
        'Management' => [
            ['inventory.php', 'ph-package', 'Inventory'],
        ],
        'Shift Tools' => [
            ['timeclock.php', 'ph-clock', 'Time Clock'],
            ['zreport.php', 'ph-receipt', 'Daily Z-Report'],
            ['history.php', 'ph-clock-counter-clockwise', 'Shift History'],
        ],
        'Account' => [
            ['qr_builder.php', 'ph-qr-code', 'QR Generator'],
            ['settings.php', 'ph-lock-key', 'Security Settings'],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ozone <?= htmlspecialchars(ucfirst($staff_area)) ?> | <?= htmlspecialchars($page_title) ?></title>
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
  /* Pre-paint: apply saved accent theme + light/contrast/motion (no flash) */
  (function () {
    var themes = {
      gold:    { primary: '#E0B85C', hover: '#F0CE7E', bg: 'rgba(224, 184, 92, 0.12)', border: 'rgba(224, 184, 92, 0.32)' },
      blue:    { primary: '#5EB3FF', hover: '#8AC9FF', bg: 'rgba(94, 179, 255, 0.12)',  border: 'rgba(94, 179, 255, 0.3)' },
      purple:  { primary: '#BF5AF2', hover: '#D08DFF', bg: 'rgba(191, 90, 242, 0.12)',  border: 'rgba(191, 90, 242, 0.3)' },
      emerald: { primary: '#4ADE80', hover: '#63E6BE', bg: 'rgba(74, 222, 128, 0.12)',  border: 'rgba(74, 222, 128, 0.3)' }
    };
    var c = themes[localStorage.getItem('kami_theme') || 'gold'];
    if (c) {
      var s = document.documentElement.style;
      s.setProperty('--kami-accent', c.primary);
      s.setProperty('--kami-accent-hover', c.hover);
      s.setProperty('--kami-accent-bg', c.bg);
      s.setProperty('--kami-accent-border', c.border);
      s.setProperty('--kami-accent-grad', 'linear-gradient(135deg, ' + c.hover + ' 0%, ' + c.primary + ' 100%)');
      s.setProperty('--accent-gold', c.primary);
    }
    if (localStorage.getItem('kami_theme_mode') === 'light') document.documentElement.classList.add('light-mode');
    if (localStorage.getItem('kami_reduce_motion') === 'true') document.documentElement.classList.add('reduce-motion');
    if (localStorage.getItem('kami_high_contrast') === 'true') document.documentElement.classList.add('high-contrast');
  })();
  </script>
  <link rel="stylesheet" href="../assets/css/kami.css">
  <?php if (!empty($page_styles)) echo $page_styles; ?>
</head>
<body class="kami-layout">

  <div class="kami-backdrop"></div>

  <aside class="kami-sidebar">
    <div class="kami-brand">
      <div class="kami-brand-icon"><i class="ph-fill ph-martini"></i></div>
      <span class="kami-brand-name">OZONE</span>
    </div>

    <nav class="kami-nav">
      <?php foreach ($navGroups as $section => $links): ?>
        <span class="nav-section-label"><?= htmlspecialchars($section) ?></span>
        <?php foreach ($links as $lnk):
          $isActive = ($active_page === $lnk[0]) ? 'active' : '';
          $hasDot = !empty($lnk[3]); ?>
          <a href="<?= $lnk[0] ?>" class="nav-link <?= $isActive ?>">
            <i class="ph-fill <?= $lnk[1] ?>"></i>
            <span><?= htmlspecialchars($lnk[2]) ?></span>
            <?php if ($hasDot): ?><span class="nav-dot"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="kami-side-user">
      <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="user-info">
        <p class="user-name"><?= htmlspecialchars($fullName) ?></p>
        <span class="user-role"><?= htmlspecialchars($roleName) ?></span>
      </div>
    </div>
  </aside>

  <div class="kami-main">
    <header class="kami-topbar">
      <button class="kami-mobile-toggle" aria-label="Open menu"><i class="ph ph-list"></i></button>
      <div class="topbar-actions">
        <button class="icon-btn" aria-label="Search" onclick="ozoneToast('Search', 'Global search is coming soon.', 'info')"><i class="ph ph-magnifying-glass"></i></button>
        <button class="icon-btn" aria-label="Notifications" onclick="ozoneToast('Notifications', 'You are all caught up.', 'success')"><i class="ph ph-bell"></i></button>
        <a class="icon-btn" href="settings.php" aria-label="Settings"><i class="ph ph-gear-six"></i></a>
        <button class="icon-btn" id="kamiThemeToggle" aria-label="Toggle light mode" onclick="kamiToggleTheme()"><i class="ph ph-sun"></i></button>
        <a class="topbar-avatar" href="settings.php" title="<?= htmlspecialchars($fullName) ?>"><?= htmlspecialchars($initials) ?></a>
      </div>
    </header>

    <main class="kami-content">
