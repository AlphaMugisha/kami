<?php
/* Shared OZONE boutique navigation. Pages in subdirectories set $base = '../'
   before including this file so links resolve from the site root.
   Pages may set $nav_active ('menu'|'features'|'about'|'contact') to highlight
   the current link. */
$base = $base ?? '';
$nav_active = $nav_active ?? '';
$act = fn(string $key): string => $nav_active === $key ? 'class="active"' : '';
?>
<nav id="navbar">
    <div class="nav-links">
        <a href="<?= $base ?>menu/index.php" <?= $act('menu') ?>>Menu</a>
        <a href="<?= $base ?>features.php" <?= $act('features') ?>>Experience</a>
    </div>

    <a href="<?= $base ?>index.php" class="brand-logo">OZONE</a>

    <div class="nav-links secondary">
        <a href="<?= $base ?>about.php" <?= $act('about') ?>>Heritage</a>
        <a href="<?= $base ?>contact.php" <?= $act('contact') ?>>Contact</a>
        <a href="<?= $base ?>login.php">Staff</a>
        <a href="<?= $base ?>cart.php" class="cart-icon">
            <i class="ph ph-shopping-bag" style="font-size: 18px;"></i>
        </a>
    </div>

    <button class="nav-burger" id="navBurger" aria-label="Open menu" onclick="toggleNavDrawer()">
        <i class="ph-bold ph-list"></i>
    </button>
</nav>

<div class="nav-drawer-overlay" id="navDrawerOverlay" onclick="toggleNavDrawer()"></div>
<div class="nav-drawer" id="navDrawer">
    <div class="nav-drawer-head">
        <span class="brand-logo">OZONE</span>
        <button class="btn-close" onclick="toggleNavDrawer()" aria-label="Close menu"><i class="ph ph-x"></i></button>
    </div>
    <div class="nav-drawer-links">
        <a href="<?= $base ?>about.php" <?= $act('about') ?>>Heritage</a>
        <a href="<?= $base ?>contact.php" <?= $act('contact') ?>>Contact</a>
        <a href="<?= $base ?>login.php">Staff Terminal</a>
        <a href="<?= $base ?>cart.php" class="cart-icon"><i class="ph ph-shopping-bag" style="font-size: 18px;"></i> Cart</a>
    </div>
</div>
