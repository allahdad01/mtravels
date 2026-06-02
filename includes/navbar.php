<?php
/**
 * Shared Navigation Component
 * Used across all landing pages for consistency
 * 
 * Requires: $platform_settings to be defined
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Role-to-dashboard routing (must match php_login.php redirect logic)
$dashboardRoutes = [
    'super_admin'         => 'super_admin/dashboard.php',
    'tenant_super_admin'  => 'tenant_super_admin/dashboard.php',
    'sales_agent'         => 'sales_agent/dashboard.php',
    'client'              => 'client/dashboard.php',
    'user'                => 'user/dashboard.php',
];
// These roles all map to the admin dashboard
$adminRoleGroup = ['admin', 'sales', 'finance', 'umrah', 'staff'];
$isLoggedIn = isset($_SESSION['user_id']);
$dashboardUrl = null;
if ($isLoggedIn) {
    $role = $_SESSION['role'] ?? '';
    $dashboardUrl = $dashboardRoutes[$role]
        ?? (in_array($role, $adminRoleGroup) ? 'admin/dashboard.php' : 'user/dashboard.php');
}

// Define default navigation links
$nav_links = isset($nav_links) ? $nav_links : [
    ['href' => 'index.php', 'label' => 'Home'],
    ['href' => 'features.php', 'label' => 'Features'],
    ['href' => 'pricing.php', 'label' => 'Pricing'],
    ['href' => 'about.php', 'label' => 'About'],
    ['href' => 'index.php#contact', 'label' => 'Contact']
];
?><!-- Page Preloader -->
<div id="page-loader" class="page-loader">
    <div class="loader-card">
        <img src="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" alt="<?= htmlspecialchars(getSetting($platform_settings, 'platform_name') ?? 'MTravels') ?>" class="loader-logo">
        <div class="loader-dots">
            <span class="loader-dot"></span>
            <span class="loader-dot"></span>
            <span class="loader-dot"></span>
        </div>
    </div>
</div>
<script>
(function(){var d=document.getElementById('page-loader');if(d){window.addEventListener('load',function(){setTimeout(function(){d.classList.add('loader-hidden');setTimeout(function(){d.style.display='none'},350)},300)})}})();
</script>
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
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="nav-login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.3s;">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.3s;">Login</a>
                    <?php endif; ?>
                    <a href="book-demo.php" class="btn btn-primary">
                        <span>Book a Demo</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
