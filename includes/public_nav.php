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
</nav>
