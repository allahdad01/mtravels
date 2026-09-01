<?php
/**
 * header_super_admin.php
 * Main header / navigation partial for super admin.
 *
 * Usage (at the top of every protected super admin page):
 *   require_once('header_super_admin.php');
 *
 * This file:
 *   1. Bootstraps auth, DB, language, settings
 *   2. Outputs the <html>, <head>, pre-loader, mobile button, and sidebar nav
 *   3. Includes the floating tasks widget
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include language system
require_once(__DIR__ . '/language_helpers.php');
$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Database connection
require_once(__DIR__ . '/db.php');

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = [];
}

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings");
    $settingStmt->execute();
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $settings = [
        'platform_name' => 'Platform',
        'platform_description' => 'Platform Description',
        'platform_logo' => 'default-logo.png',
        'platform_favicon' => 'default-favicon.ico'
    ];
}

// Get remaining session time
$session_timeout = isset($_SESSION['timeout']) ? (int)$_SESSION['timeout'] : 1800; // 30 min default
$session_start_time = isset($_SESSION['start_time']) ? (int)$_SESSION['start_time'] : time();
$remaining_time = max(0, $session_timeout - (time() - $session_start_time));

// Get user profile picture
$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;

// Check if features are available (for chat widget)
$allowed_features = isset($_SESSION['allowed_features']) ? $_SESSION['allowed_features'] : [];

function hasFeature($feature, $features) {
    return in_array($feature, $features, true);
}

if (!function_exists('h')) {
    function h(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= h($settings['platform_name'] ?? 'Super Admin Dashboard') ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../uploads/logo/<?= h($settings['platform_logo'] ?? '') ?>" type="image/x-icon">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    :root {
        --app-gradient: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        --app-gradient-soft: linear-gradient(135deg, rgba(64,153,255,.12) 0%, rgba(46,216,182,.12) 100%);
        --app-header-height: 64px;
        --app-sidebar-width: 260px;
        --app-sidebar-collapsed-width: 68px;
        --app-bg-page: #f0f4f8;
        --app-bg-sidebar: #0f1b2d;
        --app-bg-header: #ffffff;
        --app-text-primary: #1a2332;
        --app-text-secondary: #6b7a90;
        --app-text-sidebar: #a8b8cc;
        --app-border: #e4eaf2;
        --app-shadow-sm: 0 1px 4px rgba(0,0,0,.06);
        --app-shadow-lg: 0 8px 32px rgba(64,153,255,.18);
        --app-radius-sm: 6px;
        --app-transition: .28s cubic-bezier(.4,0,.2,1);
    }

    body {
        background: var(--app-bg-page) !important;
        overflow-x: hidden;
    }

    /* ── App Shell Overlay ── */
    .app-shell-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(10,20,40,.45);
        backdrop-filter: blur(2px);
        z-index: 1030;
        opacity: 0;
        transition: opacity var(--app-transition);
    }
    .app-shell-overlay.active {
        display: block;
        opacity: 1;
    }

    /* ── Header ── */
    .app-header {
        position: fixed;
        top: 0;
        left: var(--app-sidebar-width);
        right: 0;
        height: var(--app-header-height);
        background: var(--app-bg-header);
        border-bottom: 1px solid var(--app-border);
        box-shadow: var(--app-shadow-sm);
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0 1.5rem;
        z-index: 1020;
        transition: left var(--app-transition);
    }

    body.sidebar-collapsed .app-header {
        left: var(--app-sidebar-collapsed-width) !important;
    }

    .app-header__toggle {
        width: 38px;
        height: 38px;
        border-radius: var(--app-radius-sm);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        background: transparent;
        border: 0;
        cursor: pointer;
        transition: background var(--app-transition);
        flex-shrink: 0;
    }

    .app-header__toggle:hover {
        background: var(--app-gradient-soft);
    }

    .app-header__toggle span {
        display: block;
        width: 20px;
        height: 2px;
        background: var(--app-text-secondary);
        border-radius: 2px;
        transition: var(--app-transition);
    }

    body.sidebar-open .app-header__toggle span:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }

    body.sidebar-open .app-header__toggle span:nth-child(2) {
        opacity: 0;
    }

    body.sidebar-open .app-header__toggle span:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    .app-header__brand {
        display: flex;
        align-items: center;
        gap: .625rem;
        min-width: 0;
        flex-shrink: 0;
        color: var(--app-text-primary);
        text-decoration: none;
    }

    .app-header__brand-logo {
        width: 36px;
        height: 36px;
        border-radius: var(--app-radius-sm);
        background: var(--app-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--app-shadow-lg);
        overflow: hidden;
        flex-shrink: 0;
    }

    .app-header__brand-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .app-header__brand-name {
        font-size: 1.05rem;
        font-weight: 700;
        background: var(--app-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .app-header__actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: .625rem;
    }

    .app-header__icon-btn {
        width: 38px;
        height: 38px;
        border-radius: var(--app-radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--app-text-secondary);
        background: transparent;
        border: 0;
        position: relative;
        transition: background var(--app-transition), color var(--app-transition);
    }

    .app-header__icon-btn:hover {
        background: var(--app-gradient-soft);
        color: #4099ff;
    }

    .app-header__badge {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 8px;
        height: 8px;
        background: #ff4d6d;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    .app-header__avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--app-gradient);
        box-shadow: var(--app-shadow-lg);
        color: #fff;
        font-weight: 700;
        flex-shrink: 0;
        cursor: pointer;
    }

    .app-header__avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* ── Sidebar ── */
    .pcoded-navbar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: var(--app-sidebar-width) !important;
        height: 100vh !important;
        background: var(--app-bg-sidebar) !important;
        z-index: 1040 !important;
        display: flex !important;
        flex-direction: column !important;
        transform: translateX(0) !important;
        transition: transform var(--app-transition), width var(--app-transition) !important;
        overflow: hidden !important;
        box-shadow: none !important;
    }

    body.sidebar-collapsed .pcoded-navbar {
        width: var(--app-sidebar-collapsed-width) !important;
    }

    body.sidebar-collapsed .pcoded-navbar .pcoded-mtext,
    body.sidebar-collapsed .pcoded-navbar .language-selector,
    body.sidebar-collapsed .pcoded-navbar .pcoded-menu-caption label,
    body.sidebar-collapsed .pcoded-navbar .user-profile-section div div:not(:first-child),
    body.sidebar-collapsed .pcoded-navbar .user-profile-section a.nav-link {
        display: none !important;
    }

    body.sidebar-collapsed .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        justify-content: center !important;
        padding: .62rem 0 !important;
    }

    body.sidebar-collapsed .pcoded-navbar .navbar-brand.header-logo {
        justify-content: center !important;
    }

    body.sidebar-collapsed .pcoded-navbar .navbar-brand.header-logo .b-title {
        display: none !important;
    }

    body.sidebar-collapsed .pcoded-navbar:hover {
        width: var(--app-sidebar-width) !important;
    }

    body.sidebar-collapsed .pcoded-navbar:hover .pcoded-mtext,
    body.sidebar-collapsed .pcoded-navbar:hover .language-selector,
    body.sidebar-collapsed .pcoded-navbar:hover .pcoded-menu-caption label,
    body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section div div:not(:first-child),
    body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section a.nav-link {
        display: inline-block !important;
    }

    body.sidebar-collapsed .pcoded-navbar:hover .pcoded-inner-navbar li > a.nav-link {
        justify-content: flex-start !important;
        padding: .62rem .625rem !important;
    }

    body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo {
        justify-content: flex-start !important;
    }

    body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo .b-title {
        display: inline-block !important;
    }

    .pcoded-navbar .navbar-wrapper {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
    }

    .pcoded-navbar .navbar-brand.header-logo {
        height: var(--app-header-height);
        display: flex !important;
        align-items: center !important;
        padding: 0 1.5rem !important;
        margin: 0 !important;
        gap: .625rem !important;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: transparent !important;
        border-radius: 0 !important;
        flex-shrink: 0;
    }

    .pcoded-navbar .b-brand {
        display: flex !important;
        align-items: center !important;
        gap: .625rem !important;
        min-width: 0;
    }

    .pcoded-navbar .b-bg {
        width: 36px !important;
        height: 36px !important;
        border-radius: var(--app-radius-sm) !important;
        background: var(--app-gradient) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: var(--app-shadow-lg) !important;
        overflow: hidden;
        flex-shrink: 0;
    }

    .pcoded-navbar .b-bg img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }

    .pcoded-navbar .b-title {
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        color: #fff !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pcoded-navbar .language-selector {
        margin-left: auto;
        padding: 0 !important;
    }

    .pcoded-navbar .language-selector select {
        height: 28px;
        border-radius: 999px !important;
        padding: 0 8px !important;
        font-size: 11px !important;
    }

    .pcoded-navbar .navbar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem .625rem 6.5rem !important;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,.12) transparent;
    }

    .pcoded-navbar .navbar-content::-webkit-scrollbar {
        width: 4px;
    }

    .pcoded-navbar .navbar-content::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,.12);
        border-radius: 4px;
    }

    .pcoded-navbar .pcoded-inner-navbar,
    .pcoded-navbar .pcoded-inner-navbar > li {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li.pcoded-menu-caption {
        padding: 1rem .625rem .375rem !important;
        margin: 0 !important;
        font-size: .67rem !important;
        font-weight: 600 !important;
        color: rgba(168,184,204,.45) !important;
        text-transform: uppercase !important;
        letter-spacing: .1em !important;
    }

    .pcoded-navbar .pcoded-menu-caption label {
        font-size: .67rem !important;
        font-weight: 600 !important;
        letter-spacing: .1em !important;
        text-transform: uppercase !important;
        color: rgba(168,184,204,.45) !important;
        margin: 0 !important;
        display: inline-block !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        display: flex !important;
        align-items: center !important;
        gap: .625rem !important;
        padding: .62rem .625rem !important;
        border-radius: var(--app-radius-sm) !important;
        margin: .125rem 0 !important;
        color: var(--app-text-sidebar) !important;
        background: transparent !important;
        font-size: .875rem !important;
        font-weight: 500 !important;
        white-space: nowrap !important;
        transition: background var(--app-transition), color var(--app-transition) !important;
        text-decoration: none !important;
        position: relative;
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link:hover {
        background: rgba(255,255,255,.07) !important;
        color: #fff !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li.active > a.nav-link {
        background: var(--app-gradient) !important;
        color: #fff !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li.active > a.nav-link .pcoded-micon i {
        color: #fff !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a > .pcoded-micon {
        flex: 0 0 22px !important;
        width: 22px !important;
        min-width: 22px !important;
        max-width: 22px !important;
        height: 22px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        margin: 0 !important;
        font-size: 18px !important;
        position: static !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a > .pcoded-micon i {
        font-size: 18px !important;
        color: var(--app-text-sidebar) !important;
        transition: color var(--app-transition) !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link:hover .pcoded-micon i {
        color: #fff !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a > .pcoded-micon + .pcoded-mtext {
        position: static !important;
        top: auto !important;
        flex: 1 1 auto !important;
        font-size: .875rem !important;
        font-weight: 500 !important;
        color: var(--app-text-sidebar) !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link:hover .pcoded-mtext {
        color: #fff !important;
    }

    /* ── Submenu ── */
    .pcoded-navbar .pcoded-submenu {
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        list-style: none !important;
    }

    .pcoded-navbar .pcoded-submenu li a {
        padding-left: 2.5rem !important;
    }

    .pcoded-navbar .pcoded-inner-navbar li.pcoded-hasmenu .pcoded-submenu li > a:before {
        display: none !important;
    }

    .pcoded-navbar .pcoded-hasmenu {
        position: relative;
    }

    .pcoded-navbar .pcoded-hasmenu > a::after {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        border: 4px solid transparent;
        border-top-color: var(--app-text-sidebar);
        transition: transform var(--app-transition);
    }

    .pcoded-navbar .pcoded-hasmenu.pcoded-trigger > a::after {
        transform: translateY(-50%) rotate(180deg);
    }

    /* ── User profile in sidebar ── */
    .pcoded-navbar .user-profile-section {
        position: absolute;
        bottom: 0;
        width: 100%;
        border-top: 1px solid rgba(255,255,255,.1);
        background: rgba(0,0,0,.2) !important;
        z-index: 10;
    }

    /* ── Main content area ── */
    .pcoded-main-container {
        margin-left: var(--app-sidebar-width) !important;
        padding-top: var(--app-header-height);
        min-height: 100vh;
        transition: margin-left var(--app-transition);
    }

    body.sidebar-collapsed .pcoded-main-container {
        margin-left: var(--app-sidebar-collapsed-width) !important;
    }

    /* ── RTL ── */
    html[dir="rtl"] .app-header {
        left: 0 !important;
        right: var(--app-sidebar-width) !important;
    }

    html[dir="rtl"] body.sidebar-collapsed .app-header {
        right: var(--app-sidebar-collapsed-width) !important;
    }

    html[dir="rtl"] .pcoded-navbar {
        left: auto !important;
        right: 0 !important;
    }

    html[dir="rtl"] .pcoded-main-container {
        margin-left: 0 !important;
        margin-right: var(--app-sidebar-width) !important;
    }

    html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container {
        margin-right: var(--app-sidebar-collapsed-width) !important;
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        flex-direction: row-reverse !important;
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext {
        text-align: right !important;
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-hasmenu > a::after {
        right: auto !important;
        left: 1rem !important;
    }

    html[dir="rtl"] body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo,
    html[dir="rtl"] body.sidebar-collapsed .pcoded-navbar:hover .pcoded-inner-navbar li > a.nav-link {
        justify-content: flex-start !important;
    }

    /* ── Mobile ── */
    @media (max-width: 1023px) {
        .app-header {
            left: 0;
            right: 0;
            padding: 0 1rem;
        }

        .app-header__brand-name {
            max-width: 180px;
        }

        .pcoded-navbar {
            transform: translateX(-100%) !important;
            width: var(--app-sidebar-width) !important;
            max-width: 85vw;
        }

        body.sidebar-open .pcoded-navbar {
            transform: translateX(0) !important;
        }

        html[dir="rtl"] .pcoded-navbar {
            transform: translateX(100%) !important;
        }

        html[dir="rtl"] body.sidebar-open .pcoded-navbar {
            transform: translateX(0) !important;
        }

        .pcoded-main-container,
        html[dir="rtl"] .pcoded-main-container,
        body.sidebar-collapsed .pcoded-main-container,
        html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .mobile-menu-float {
            display: none !important;
        }
    }

    @media (max-width: 639px) {
        .app-header__brand-name {
            max-width: 130px;
        }

        .app-header__actions {
            gap: .25rem;
        }

        .app-header__icon-btn {
            width: 34px;
            height: 34px;
        }
    }
    </style>
</head>
<body>

<!-- [ Pre-loader ] start -->
<div class="loader-bg">
    <div class="loader-track">
        <div class="loader-fill"></div>
    </div>
</div>
<!-- [ Pre-loader ] End -->

<!-- App Shell Overlay (mobile) -->
<div class="app-shell-overlay" id="appShellOverlay"></div>

<!-- [ Header ] start -->
<header class="app-header" role="banner">
    <button class="app-header__toggle" id="mobile-collapse" type="button" aria-label="Toggle sidebar" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

    <div class="app-header__actions">
        <a class="app-header__icon-btn" href="platform_settings.php" aria-label="Settings">
            <i class="feather icon-settings"></i>
        </a>
        <a class="app-header__avatar" href="dashboard.php" aria-label="Profile">
            <img src="<?= $imagePath ?>" alt="<?= h($user['name'] ?? 'Admin') ?>">
        </a>
    </div>
</header>
<!-- [ Header ] end -->

<!-- [ navigation menu ] start -->
<aside class="pcoded-navbar" id="sidebar" role="navigation" aria-label="Main navigation">
    <div class="navbar-wrapper">

        <!-- Brand / logo -->
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;"
                         src="../uploads/logo/<?= h($settings['platform_logo'] ?? '') ?>"
                         alt="<?= h($settings['platform_name'] ?? '') ?>">
                </div>
                <span class="b-title"><?= h($settings['platform_name'] ?? '') ?></span>
            </a>
            <div class="language-selector" style="padding:5px 15px;text-align:center;">
                <select onchange="window.location.href='../language_switcher.php?lang='+this.value"
                        style="background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:4px;padding:2px 5px;font-size:11px;cursor:pointer;">
                    <option value="en" <?= get_current_lang() === 'en' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">EN</option>
                    <option value="fa" <?= get_current_lang() === 'fa' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">دری</option>
                    <option value="ps" <?= get_current_lang() === 'ps' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">پښتو</option>
                </select>
            </div>
        </div>
        <!-- /brand -->

        <!-- Sidebar menu -->
        <div class="navbar-content scroll-div" style="padding-bottom:100px;">
            <ul class="nav pcoded-inner-navbar">

                <!-- Navigation -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Navigation</label>
                </li>
                <li data-username="dashboard" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="dashboard.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext"><?= __('dashboard') ?></span>
                    </a>
                </li>

                <!-- Business Operations -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Business Operations</label>
                </li>
                <li data-username="manage_tenants" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_tenants.php' ? 'active' : ''; ?>">
                    <a href="manage_tenants.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext">Manage Tenants</span>
                    </a>
                </li>
                <li data-username="manage_sales_agents" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_sales_agents.php' ? 'active' : ''; ?>">
                    <a href="manage_sales_agents.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-briefcase"></i></span>
                        <span class="pcoded-mtext">Sales Agents</span>
                    </a>
                </li>
                <li data-username="manage_demo_requests" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_demo_requests.php' ? 'active' : ''; ?>">
                    <a href="manage_demo_requests.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Demo Requests</span>
                    </a>
                </li>
                <li data-username="support_tickets" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['support_tickets_manage.php', 'support_ticket_view.php']) ? 'active' : ''; ?>">
                    <a href="support_tickets_manage.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-headphones"></i></span>
                        <span class="pcoded-mtext">Support Tickets</span>
                    </a>
                </li>

                <!-- Subscription Management -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Subscription Management</label>
                </li>
                <li data-username="manage_plans" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_plans.php' ? 'active' : ''; ?>">
                    <a href="manage_plans.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-package"></i></span>
                        <span class="pcoded-mtext">Plans</span>
                    </a>
                </li>
                <li data-username="manage_custom_plan_requests" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['manage_custom_plan_requests.php', 'view_custom_plan_request.php', 'convert_custom_plan_request.php']) ? 'active' : ''; ?>">
                    <a href="manage_custom_plan_requests.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-plus-circle"></i></span>
                        <span class="pcoded-mtext">Custom Plan Requests</span>
                    </a>
                </li>
                <li data-username="manage_subscriptions" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_subscriptions.php' ? 'active' : ''; ?>">
                    <a href="manage_subscriptions.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
                        <span class="pcoded-mtext">Subscriptions</span>
                    </a>
                </li>
                <li data-username="manage_branch_addons" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_branch_addons.php' ? 'active' : ''; ?>">
                    <a href="manage_branch_addons.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-gift"></i></span>
                        <span class="pcoded-mtext">Branch Add-ons</span>
                    </a>
                </li>
                <li data-username="manage_user_addons" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_user_addons.php' ? 'active' : ''; ?>">
                    <a href="manage_user_addons.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-user-plus"></i></span>
                        <span class="pcoded-mtext">User Add-ons</span>
                    </a>
                </li>
                <li data-username="manage_communication_addons" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_communication_addons.php' ? 'active' : ''; ?>">
                    <a href="manage_communication_addons.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-comment-dots"></i></span>
                        <span class="pcoded-mtext">Communication Add-ons</span>
                    </a>
                </li>

                <!-- Pricing Management -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Pricing Management</label>
                </li>
                <li data-username="manage_tenant_addon_pricing" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_tenant_addon_pricing.php' ? 'active' : ''; ?>">
                    <a href="manage_tenant_addon_pricing.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-tag"></i></span>
                        <span class="pcoded-mtext">Branch Addon Pricing</span>
                    </a>
                </li>
                <li data-username="manage_user_addon_pricing" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_user_addon_pricing.php' ? 'active' : ''; ?>">
                    <a href="manage_user_addon_pricing.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-users"></i></span>
                        <span class="pcoded-mtext">User Addon Pricing</span>
                    </a>
                </li>
                <li data-username="manage_communication_addon_pricing" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_communication_addon_pricing.php' ? 'active' : ''; ?>">
                    <a href="manage_communication_addon_pricing.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-tags"></i></span>
                        <span class="pcoded-mtext">Communication Addon Pricing</span>
                    </a>
                </li>

                <!-- Financial Management -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Financial Management</label>
                </li>
                <li data-username="subscription_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'subscription_payments.php' ? 'active' : ''; ?>">
                    <a href="subscription_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
                        <span class="pcoded-mtext">Subscription Payments</span>
                    </a>
                </li>
                <li data-username="manage_salary_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_salary_payments.php' ? 'active' : ''; ?>">
                    <a href="manage_salary_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-money-bill-wave"></i></span>
                        <span class="pcoded-mtext">Salary Payments</span>
                    </a>
                </li>
                <li data-username="expense_management" class="nav-item pcoded-hasmenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['system_expenses.php', 'system_expense_categories.php', 'system_revenue.php', 'profit_loss_dashboard.php']) ? 'active' : ''; ?>">
                    <a href="javascript:void(0);" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
                        <span class="pcoded-mtext">Expense Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'system_expenses.php' ? 'active' : ''; ?>">
                            <a href="system_expenses.php" class="nav-link">
                                <span class="pcoded-mtext">Expenses</span>
                            </a>
                        </li>
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profit_loss_dashboard.php' ? 'active' : ''; ?>">
                            <a href="profit_loss_dashboard.php" class="nav-link">
                                <span class="pcoded-mtext">Profit &amp; Loss</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Content & Marketing -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Content &amp; Marketing</label>
                </li>
                <li data-username="manage_testimonials" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_testimonials.php' ? 'active' : ''; ?>">
                    <a href="manage_testimonials.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-star"></i></span>
                        <span class="pcoded-mtext">Testimonials</span>
                    </a>
                </li>
                <li data-username="manage_blog_posts" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_blog_posts.php' ? 'active' : ''; ?>">
                    <a href="manage_blog_posts.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-edit"></i></span>
                        <span class="pcoded-mtext">Posts</span>
                    </a>
                </li>

                <!-- Administration -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Administration</label>
                </li>
                <li data-username="manage_users" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                    <a href="manage_users.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-check"></i></span>
                        <span class="pcoded-mtext">Users</span>
                    </a>
                </li>
                <li data-username="platform_settings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'platform_settings.php' ? 'active' : ''; ?>">
                    <a href="platform_settings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-settings"></i></span>
                        <span class="pcoded-mtext">Platform Settings</span>
                    </a>
                </li>
                <li data-username="name_dictionary" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'name_dictionary.php' ? 'active' : ''; ?>">
                    <a href="name_dictionary.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-language"></i></span>
                        <span class="pcoded-mtext">Name Dictionary</span>
                    </a>
                </li>
                <li data-username="audit_logs" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
                    <a href="audit_logs.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-activity"></i></span>
                        <span class="pcoded-mtext">Audit Logs</span>
                    </a>
                </li>
                <li data-username="ssl_monitoring" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ssl_monitoring.php' ? 'active' : ''; ?>">
                    <a href="ssl_monitoring.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-shield-alt"></i></span>
                        <span class="pcoded-mtext">SSL Monitoring</span>
                    </a>
                </li>
                <li data-username="backup_management" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['backup_management.php', 'backup_settings.php']) ? 'active' : ''; ?>">
                    <a href="backup_management.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-save"></i></span>
                        <span class="pcoded-mtext">Backups</span>
                    </a>
                    <ul class="nav-item" style="padding-left:20px;list-style:none;margin:0;">
                        <li><a href="backup_management.php" style="display:block;padding:6px 12px;font-size:.82rem;color:<?= basename($_SERVER['PHP_SELF']) == 'backup_management.php' ? '#4099ff' : 'rgba(255,255,255,.6)' ?>;">Manage Backups</a></li>
                        <li><a href="backup_settings.php" style="display:block;padding:6px 12px;font-size:.82rem;color:<?= basename($_SERVER['PHP_SELF']) == 'backup_settings.php' ? '#4099ff' : 'rgba(255,255,255,.6)' ?>;">Auto Backup Settings</a></li>
                    </ul>
                </li>
                <li data-username="db_migrate" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'db_migrate.php' ? 'active' : ''; ?>">
                    <a href="db_migrate.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-database"></i></span>
                        <span class="pcoded-mtext">DB Migration</span>
                    </a>
                </li>
                <li data-username="fix_client_balances" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'fix_client_balances.php' ? 'active' : ''; ?>">
                    <a href="fix_client_balances.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-balance-scale"></i></span>
                        <span class="pcoded-mtext">Fix Account Balances</span>
                    </a>
                </li>
                <li data-username="file_browser" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'file_browser.php' ? 'active' : ''; ?>">
                    <a href="file_browser.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-folder"></i></span>
                        <span class="pcoded-mtext">File Browser</span>
                    </a>
                </li>
                <li data-username="manage_tutorials" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_tutorials.php' ? 'active' : ''; ?>">
                    <a href="manage_tutorials.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-book"></i></span>
                        <span class="pcoded-mtext">Manage Tutorials</span>
                    </a>
                </li>
                <li data-username="tenant_activity" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tenant_activity.php' ? 'active' : ''; ?>">
                    <a href="tenant_activity.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-bar-chart-2"></i></span>
                        <span class="pcoded-mtext">Tenant Activity</span>
                    </a>
                </li>
                <li data-username="manage_email_tracking" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_email_tracking.php' ? 'active' : ''; ?>">
                    <a href="manage_email_tracking.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-mail"></i></span>
                        <span class="pcoded-mtext">Email Tracking</span>
                    </a>
                </li>

            </ul>
        </div>
        <!-- /sidebar menu -->

        <!-- Sticky user profile strip at the bottom of the sidebar -->
        <div class="navbar-brand user-profile-section">
            <div style="padding:8px 15px;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                    <a href="javascript:void(0)" style="text-decoration:none;flex-shrink:0;">
                        <img class="rounded-circle"
                             style="width:28px;height:28px;cursor:pointer;transition:opacity 0.3s ease;"
                             onmouseover="this.style.opacity='0.8'"
                             onmouseout="this.style.opacity='1'"
                             src="<?= $imagePath ?>"
                             alt="user-avatar">
                    </a>
                    <div style="flex:1;min-width:0;overflow:hidden;">
                        <div style="color:#fff;font-size:11px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">
                            <?= h($user['name'] ?? 'Super Admin') ?>
                        </div>
                        <div style="color:rgba(255,255,255,0.7);font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">
                            <?= h($user['email'] ?? '') ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:1px;flex-shrink:0;">
                    <a href="profile.php" class="nav-link"
                       style="padding:4px;border-radius:3px;color:#fff;transition:all 0.3s ease;"
                       onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                       onmouseout="this.style.background='transparent'"
                       title="My Profile">
                        <i class="feather icon-user" style="font-size:12px;"></i>
                    </a>
                    <a href="logout.php" class="nav-link"
                       style="padding:4px;border-radius:3px;color:#fff;transition:all 0.3s ease;"
                       onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                       onmouseout="this.style.background='transparent'"
                       title="Logout">
                        <i class="feather icon-log-out" style="font-size:12px;"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /user profile strip -->

    </div>
</aside>
<!-- [ navigation menu ] end -->

<!-- ── Floating Tasks Widget ──────────────────────────────────── -->
<?php include_once __DIR__ . '/floating_tasks.php'; ?>

<!-- ── Scripts ────────────────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Modern sidebar toggle ──
    const body = document.body;
    const toggleBtn = document.getElementById('mobile-collapse');
    const overlay = document.getElementById('appShellOverlay');

    function isDesktop() {
        return window.innerWidth >= 1024;
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        if (overlay) overlay.classList.remove('active');
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isDesktop()) {
                body.classList.toggle('sidebar-collapsed');
                closeSidebar();
                return;
            }
            const open = body.classList.toggle('sidebar-open');
            if (overlay) overlay.classList.toggle('active', open);
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });

    window.addEventListener('resize', function () {
        if (isDesktop()) closeSidebar();
    });

    // ── Session timeout ──
    var remainingTime     = <?= (int) $remaining_time ?>;
    var SESSION_TIMEOUT   = <?= (int) $session_timeout ?>;
    var lastActivityTime  = Date.now();
    var warningShown5Min  = false;
    var warningShown1Min  = false;
    var warningTimeout    = null;

    function showSessionWarning(message) {
        var banner = document.getElementById('session-warning-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'session-warning-banner';
            banner.style.cssText = [
                'position:fixed;top:0;left:0;right:0;z-index:9999;',
                'background:#f59e0b;color:#1c1917;text-align:center;',
                'padding:10px 20px;font-weight:600;font-size:14px;',
                'box-shadow:0 2px 8px rgba(0,0,0,0.2);'
            ].join('');
            document.body.appendChild(banner);
        }
        banner.textContent = message;
        banner.style.display = 'block';
        clearTimeout(warningTimeout);
        warningTimeout = setTimeout(function () {
            if (banner) banner.style.display = 'none';
        }, 8000);
    }

    function hideBanner() {
        var banner = document.getElementById('session-warning-banner');
        if (banner) banner.style.display = 'none';
        clearTimeout(warningTimeout);
    }

    function checkServerSession() {
        fetch('../api/session_check.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (res.status === 401 || res.status === 403) {
                window.location.href = '../login.php?timeout=1';
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (data && !data.authenticated) {
                window.location.href = '../login.php?timeout=1';
            }
        })
        .catch(function (err) { console.error('Session check error:', err); });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            var away = (Date.now() - lastActivityTime) / 1000;
            if (away > 30) checkServerSession();
            lastActivityTime = Date.now();
        }
    });

    setInterval(function () {
        if (remainingTime <= 0) {
            window.location.href = '../logout.php';
            return;
        }
        if (remainingTime <= 300 && !warningShown5Min) {
            showSessionWarning('Your session will expire in 5 minutes. Please save your work.');
            warningShown5Min = true;
        }
        if (remainingTime <= 60 && !warningShown1Min) {
            showSessionWarning('Your session will expire in 1 minute. Please save your work.');
            warningShown1Min = true;
        }
        remainingTime--;
    }, 1000);

    var activityDebounce;
    ['mousedown', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        document.addEventListener(ev, function () {
            clearTimeout(activityDebounce);
            activityDebounce = setTimeout(function () {
                lastActivityTime  = Date.now();
                remainingTime     = SESSION_TIMEOUT;
                warningShown5Min  = false;
                warningShown1Min  = false;
                hideBanner();
            }, 10000);
        }, { passive: true, capture: true });
    });
});
</script>
