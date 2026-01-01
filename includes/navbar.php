<?php
/**
 * Shared Navigation Component
 * Used across all landing pages for consistency
 * 
 * Requires: $platform_settings to be defined
 */

// Define default navigation links
$nav_links = isset($nav_links) ? $nav_links : [
    ['href' => 'index.php', 'label' => 'Home'],
    ['href' => 'index.php#features', 'label' => 'Features'],
    ['href' => 'index.php#pricing', 'label' => 'Pricing'],
    ['href' => 'about.php', 'label' => 'About'],
    ['href' => 'index.php#contact', 'label' => 'Contact']
];
?>
<!-- Navigation -->
<nav class="navbar" id="navbar">
    <div class="container">
        <div class="nav-content">
            <a href="index.php" class="logo">
                <img src="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'logo.png') ?>" alt="Logo" style="height: 40px;">
                <span class="logo-text"><?= htmlspecialchars(getSetting($platform_settings, 'platform_name') ?? 'MTravels') ?></span>
            </a>
            <button class="hamburger" id="hamburger">☰</button>
            <div class="nav-menu">
                <ul class="nav-links">
                    <?php foreach ($nav_links as $link): ?>
                    <li><a href="<?= htmlspecialchars($link['href']) ?>"><?= htmlspecialchars($link['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <div class="nav-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                        <span class="theme-icon">🌙</span>
                    </button>
                    <a href="login.php" class="nav-login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.3s;">Login</a>
                    <a href="book-demo.php" class="btn btn-primary">
                        <span>Book a Demo</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
