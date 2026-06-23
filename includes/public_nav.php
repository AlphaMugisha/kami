<nav>
    <div class="nav-container">
        <a href="index.php" class="brand">
            <i class="ph-fill ph-hexagon"></i>
            Kami OS
        </a>
        <div class="nav-links">
            <a href="features.php" <?= basename($_SERVER['PHP_SELF']) == 'features.php' ? 'class="active"' : '' ?>>Features</a>
            <a href="about.php" <?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'class="active"' : '' ?>>Company</a>
            <a href="contact.php" <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'class="active"' : '' ?>>Contact</a>
        </div>
        <div class="nav-actions">
            <a href="login.php" class="btn btn-glass">Log in</a>
            <a href="contact.php" class="btn btn-primary" style="margin-left: 12px;">Contact Sales</a>
        </div>
    </div>
</nav>