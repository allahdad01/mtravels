<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
require_once('../includes/session_check.php');
// Include language system
require_once('../includes/language_helpers.php');
$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Database connection
require_once('../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'] ?? null;

// Fetch user data with proper error handling
try {

        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$_SESSION['user_id'], $tenant_id, $branch_id]);

    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

$allowed_features = [];

if ($tenant_id) {
    $query = "
        SELECT p.features
        FROM tenant_subscriptions ts
        JOIN plans p ON ts.plan_id = p.id
        WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trial')
        ORDER BY ts.start_date DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $allowed_features = json_decode($row['features'], true) ?? [];
    }
} else {
    error_log("Tenant ID is empty or null");
}


// Helper function to check if a feature is allowed
function hasFeature($feature, $allowed_features) {
    return in_array($feature, $allowed_features);
}

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
}

// Get the user ID from the session
$user_id = $_SESSION["user_id"];

$profilePic = !empty($user['image']) ? htmlspecialchars($user['image']) : '';
$imagePath = ($profilePic && file_exists('../assets/images/client/' . $profilePic)) ? '../assets/images/client/' . $profilePic : '';

// Generate CSRF token if not already in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// HTML escaping helper
if (!function_exists('h')) {
    function h(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ── Current page for active-state detection ──
$currentPage = basename($_SERVER['PHP_SELF']);

/**
 * Returns 'active' when the current page is in the given list.
 */
if (!function_exists('navActive')) {
    function navActive(string ...$pages): string {
        global $currentPage;
        return in_array($currentPage, $pages, true) ? 'active' : '';
    }
}

/**
 * Returns 'active pcoded-trigger' when the current page is in the given list.
 */
if (!function_exists('navTrigger')) {
    function navTrigger(string ...$pages): string {
        global $currentPage;
        return in_array($currentPage, $pages, true) ? 'active pcoded-trigger' : '';
    }
}

?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="<?= h($csrf_token) ?>">

    <title><?= h($settings['agency_name'] ?? 'Dashboard') ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../uploads/logo/<?= h($settings['logo'] ?? '') ?>" type="image/x-icon">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Header / sidebar styles -->
    <link rel="stylesheet" href="../assets/css/header-styles.css">

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

    body { background: var(--app-bg-page) !important; overflow-x: hidden; }

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
    .app-shell-overlay.active { display: block; opacity: 1; }

    .app-header {
        position: fixed;
        top: 0;
        left: 0;
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
    .app-header__toggle:hover { background: var(--app-gradient-soft); }
    .app-header__toggle span {
        display: block;
        width: 20px;
        height: 2px;
        background: var(--app-text-secondary);
        border-radius: 2px;
        transition: var(--app-transition);
    }
    body.sidebar-open .app-header__toggle span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    body.sidebar-open .app-header__toggle span:nth-child(2) { opacity: 0; }
    body.sidebar-open .app-header__toggle span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    .app-header__brand {
        display: flex;
        align-items: center;
        gap: .625rem;
        min-width: 0;
        flex-shrink: 0;
        color: var(--app-text-primary);
    }
    .app-header__brand-logo,
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
    .app-header__brand-logo img,
    .pcoded-navbar .b-bg img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }
    .app-header__brand-name,
    .pcoded-navbar .b-title {
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        background: var(--app-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .app-header__spacer { flex: 1; min-width: 1rem; }

    .app-header__actions { margin-left: auto; display: flex; align-items: center; gap: .625rem; }
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
    }
    .app-header__icon-btn:hover { background: var(--app-gradient-soft); color: #4099ff; }
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
        flex-shrink: 0;
    }
    .app-header__avatar img { width: 100%; height: 100%; object-fit: cover; }

    /* ── Help menu ── */
    .app-header__help { position: relative; }
    .app-help-menu {
        position: absolute; top: calc(100% + 8px); right: 0; width: 360px; max-height: 420px;
        background: #fff; border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.15);
        display: none; flex-direction: column; overflow: hidden; z-index: 1100;
    }
    .app-help-menu.open { display: flex; }
    .app-help-menu__head {
        padding: 14px 16px; font-weight: 600; font-size: .9rem; color: var(--app-text-primary);
        border-bottom: 1px solid var(--app-border); display: flex; align-items: center;
    }
    .app-help-menu__subhead {
        padding: 8px 16px; font-size: .8rem; color: var(--app-text-secondary);
        background: #f8f9fa; border-bottom: 1px solid var(--app-border);
    }
    .app-help-menu__list {
        flex: 1; overflow-y: auto; padding: 8px;
    }
    .app-help-menu__empty {
        text-align: center; padding: 24px 16px; color: var(--app-text-secondary); font-size: .85rem;
    }
    .app-help-menu__item {
        display: flex; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: pointer;
        transition: background .15s; border: 1px solid transparent; margin-bottom: 4px;
    }
    .app-help-menu__item:hover { background: var(--app-gradient-soft); border-color: #4099ff; }
    .app-help-menu__item-icon {
        width: 40px; height: 40px; border-radius: 8px; background: var(--app-gradient-soft);
        display: flex; align-items: center; justify-content: center; color: #4099ff; flex-shrink: 0;
    }
    .app-help-menu__item-body { flex: 1; min-width: 0; }
    .app-help-menu__item-title { font-weight: 600; font-size: .85rem; color: var(--app-text-primary); margin-bottom: 2px; }
    .app-help-menu__item-desc { font-size: .78rem; color: var(--app-text-secondary); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .app-help-menu__item-chapters { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .app-help-menu__chapter {
        font-size: .7rem; padding: 2px 8px; border-radius: 4px; background: #e8f0fe; color: #1967d2;
        font-family: monospace; white-space: nowrap;
    }
    .app-help-menu__item-play {
        font-size: .75rem; color: #4099ff; display: flex; align-items: center; gap: 4px; margin-top: 4px; font-weight: 500;
    }
    .app-help-menu__foot {
        padding: 10px 16px; border-top: 1px solid var(--app-border); text-align: center;
    }
    .app-help-menu__foot a { font-size: .82rem; color: #4099ff; font-weight: 500; text-decoration: none; }
    .app-help-menu__foot a:hover { text-decoration: underline; }

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
    .pcoded-navbar .navbar-wrapper { display: flex; flex-direction: column; width: 100%; height: 100%; position: relative; z-index: 1; }
    .pcoded-navbar .navbar-brand.header-logo {
        height: var(--app-header-height);
        display: flex !important;
        align-items: center !important;
        padding: 0 1.5rem !important;
        margin: 0 !important;
        gap: .625rem !important;
        border-bottom: 1px solid rgba(255,255,255,.06) !important;
        background: transparent !important;
        border-radius: 0 !important;
        flex-shrink: 0;
    }
    .pcoded-navbar .b-brand { display: flex !important; align-items: center !important; gap: .625rem !important; min-width: 0; }
    .pcoded-navbar .language-selector { margin-left: auto; padding: 0 !important; }
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

    .pcoded-navbar .pcoded-inner-navbar,
    .pcoded-navbar .pcoded-inner-navbar > li { width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .pcoded-navbar .pcoded-menu-caption { padding: 1rem .625rem .375rem !important; margin: 0 !important; }
    .pcoded-navbar .pcoded-menu-caption label {
        font-size: .67rem !important;
        font-weight: 600 !important;
        letter-spacing: .1em !important;
        text-transform: uppercase !important;
        color: rgba(168,184,204,.45) !important;
        margin: 0 !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        display: flex !important;
        align-items: center !important;
        gap: .625rem !important;
        width: 100% !important;
        min-width: 0 !important;
        padding: .62rem .625rem !important;
        margin: .125rem 0 !important;
        box-sizing: border-box !important;
        color: var(--app-text-sidebar) !important;
        background: transparent !important;
        transform: none !important;
        box-shadow: none !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link:hover {
        background: rgba(255,255,255,.07) !important;
        color: #fff !important;
        padding: .62rem .625rem !important;
        transform: none !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li.active > a.nav-link {
        background: var(--app-gradient) !important;
        color: #fff !important;
        box-shadow: 0 4px 14px rgba(64,153,255,.35) !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-micon,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-micon,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-micon {
        position: static !important;
        order: 0 !important;
        flex: 0 0 22px !important;
        width: 22px !important;
        min-width: 22px !important;
        max-width: 22px !important;
        height: 22px !important;
        margin: 0 !important;
        padding: 0 !important;
        transform: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: inherit !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext {
        position: static !important;
        order: 0 !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        color: inherit !important;
    }

    .pcoded-navbar .user-profile-section {
        position: absolute !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: .75rem !important;
        border-top: 1px solid rgba(255,255,255,.06) !important;
        background: var(--app-bg-sidebar) !important;
        border-radius: 0 !important;
    }
    .pcoded-main-container {
        margin-left: var(--app-sidebar-width) !important;
        margin-right: 0 !important;
        padding-top: var(--app-header-height) !important;
        min-height: 100vh;
        transition: margin-left var(--app-transition), margin-right var(--app-transition) !important;
    }

    body.sidebar-collapsed .pcoded-navbar { width: var(--app-sidebar-collapsed-width) !important; }
    body.sidebar-collapsed .pcoded-main-container { margin-left: var(--app-sidebar-collapsed-width) !important; }
    body.sidebar-collapsed .pcoded-navbar .b-title,
    body.sidebar-collapsed .pcoded-navbar .language-selector,
    body.sidebar-collapsed .pcoded-navbar .pcoded-menu-caption label,
    body.sidebar-collapsed .pcoded-navbar .pcoded-mtext,
    body.sidebar-collapsed .pcoded-navbar .user-profile-section div div:not(:first-child),
    body.sidebar-collapsed .pcoded-navbar .user-profile-section a.nav-link { display: none !important; }
    body.sidebar-collapsed .pcoded-navbar .navbar-brand.header-logo,
    body.sidebar-collapsed .pcoded-navbar li a.nav-link {
        justify-content: center !important;
        padding-left: .5rem !important;
        padding-right: .5rem !important;
    }

    /* ── RTL ── */
    html[dir="rtl"] .app-header__actions { margin-left: 0; margin-right: auto; }
    html[dir="rtl"] .app-header__notification-count { right: auto; left: -5px; }
    html[dir="rtl"] .pcoded-navbar { right: 0 !important; left: auto !important; }
    html[dir="rtl"] .pcoded-main-container { margin-right: var(--app-sidebar-width) !important; margin-left: 0 !important; }
    html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container { margin-right: var(--app-sidebar-collapsed-width) !important; margin-left: 0 !important; }
    html[dir="rtl"] .pcoded-navbar .language-selector { margin-left: 0; margin-right: auto; }
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link { flex-direction: row-reverse !important; }
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext { text-align: right !important; }
    html[dir="rtl"] .app-notification-menu { right: auto; left: 0; }
    html[dir="rtl"] .app-header__help .app-help-menu { right: auto; left: 0; }

    @media (min-width: 1024px) {
        .app-header {
            left: var(--app-sidebar-width);
            transition: left var(--app-transition), right var(--app-transition);
        }
        .app-header__brand { display: none !important; }
        body.sidebar-collapsed .app-header { left: var(--app-sidebar-collapsed-width); }
        body.sidebar-collapsed .pcoded-navbar:hover { width: var(--app-sidebar-width) !important; }
        body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo,
        body.sidebar-collapsed .pcoded-navbar:hover li a.nav-link {
            justify-content: flex-start !important;
            padding-left: .625rem !important;
            padding-right: .625rem !important;
        }
        body.sidebar-collapsed .pcoded-navbar:hover .b-title,
        body.sidebar-collapsed .pcoded-navbar:hover .language-selector,
        body.sidebar-collapsed .pcoded-navbar:hover .pcoded-menu-caption label,
        body.sidebar-collapsed .pcoded-navbar:hover .pcoded-mtext { display: inline-block !important; }
        body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section div div:not(:first-child) { display: block !important; }
        body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section a.nav-link { display: flex !important; }
        html[dir="rtl"] .app-header { left: 0; right: var(--app-sidebar-width); }
        html[dir="rtl"] body.sidebar-collapsed .app-header { right: var(--app-sidebar-collapsed-width); }
    }

    @media (max-width: 1023px) {
        .app-header { left: 0; right: 0; padding: 0 1rem; }
        .app-header__brand-name { max-width: 180px; }
        .pcoded-navbar {
            transform: translateX(-100%) !important;
            width: var(--app-sidebar-width) !important;
            max-width: 85vw;
        }
        body.sidebar-open .pcoded-navbar { transform: translateX(0) !important; }
        html[dir="rtl"] .pcoded-navbar { transform: translateX(100%) !important; }
        html[dir="rtl"] body.sidebar-open .pcoded-navbar { transform: translateX(0) !important; }
        .pcoded-main-container,
        html[dir="rtl"] .pcoded-main-container,
        body.sidebar-collapsed .pcoded-main-container,
        html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        .mobile-menu-float { display: none !important; }
    }

    @media (max-width: 639px) {
        .app-header__search { display: none; }
        .app-header__brand-name { max-width: 130px; }
        .app-header__actions { gap: .25rem; }
        .app-header__icon-btn { width: 34px; height: 34px; }
    }

    /* ── Video modal ── */
    .help-video-modal {
        position: fixed; z-index: 12000; left: 0; top: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); align-items: center; justify-content: center;
    }
    .help-video-modal.show { display: flex !important; }
    .help-video-modal-content {
        position: relative; background: #000; width: 90%; max-width: 900px; border-radius: 8px;
        max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
    }
    .help-video-modal-header {
        background: #1a1a2e; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between;
    }
    .help-video-modal-title { color: #fff; font-weight: 600; font-size: .9rem; }
    .help-video-modal-close { color: #fff; font-size: 24px; cursor: pointer; line-height: 1; }
    .help-video-modal-close:hover { color: #ff5370; }
    .help-video-container { position: relative; width: 100%; aspect-ratio: 16 / 9; flex-shrink: 0; }
    .help-video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
    .help-video-chapters { background: #1a1a2e; padding: 12px 16px; border-top: 1px solid #333; overflow-y: auto; flex-shrink: 1; min-height: 0; }
    .help-video-chapters-title { color: #aaa; font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; flex-shrink: 0; }
    .help-video-chapters-list { display: flex; flex-wrap: wrap; gap: 6px; }
    .help-video-chapter-item {
        display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); border-radius: 5px;
        padding: 5px 10px; cursor: pointer; transition: all .2s; border: 1px solid transparent;
    }
    .help-video-chapter-item:hover { background: rgba(70,128,255,0.2); border-color: #4680ff; }
    .help-video-chapter-time { font-size: .75rem; font-weight: 700; color: #4680ff; font-family: monospace; }
    .help-video-chapter-label { font-size: .8rem; color: #e0e0e0; }

    /* ── Trial banner compatibility ── */
    body.has-trial-banner .app-header {
        top: 48px;
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

<div class="app-shell-overlay" id="appShellOverlay"></div>

<header class="app-header" role="banner">
    <button class="app-header__toggle" id="mobile-collapse" type="button" aria-label="Toggle sidebar" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

    <a href="dashboard.php" class="app-header__brand">
        <span class="app-header__brand-logo">
            <img src="../uploads/logo/<?= h($settings['logo'] ?? '') ?>" alt="<?= h($settings['agency_name'] ?? '') ?>">
        </span>
        <span class="app-header__brand-name"><?= h($settings['agency_name'] ?? '') ?></span>
    </a>

    <div class="app-header__spacer"></div>

    <div class="app-header__actions">
        <div class="app-header__help" id="pageHelpContainer">
            <button class="app-header__icon-btn" type="button" id="pageHelpToggle" aria-label="Page tutorials" aria-expanded="false">
                <i class="feather icon-help-circle"></i>
            </button>
            <div class="app-help-menu" id="pageHelpMenu" aria-hidden="true">
                <div class="app-help-menu__head">
                    <i class="feather icon-book mr-2"></i>
                    <span><?= h(__('tutorials')) ?></span>
                </div>
                <div class="app-help-menu__subhead" id="pageHelpSubhead">Loading...</div>
                <div class="app-help-menu__list" id="pageHelpList">
                    <div class="app-help-menu__empty">No tutorials for this page</div>
                </div>
                <div class="app-help-menu__foot">
                    <a href="tutorial.php"><?= h(__('view_all_tutorials')) ?> <i class="feather icon-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <a class="app-header__avatar" href="profile.php" aria-label="Profile">
            <?php if ($imagePath): ?>
            <img src="<?= $imagePath ?>" alt="<?= h($user['name'] ?? 'User') ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <span class="avatar-initials" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;"><?= strtoupper(substr(h($user['name'] ?? 'U'), 0, 1)) ?></span>
            <?php else: ?>
            <span class="avatar-initials" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;"><?= strtoupper(substr(h($user['name'] ?? 'U'), 0, 1)) ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>

<!-- [ navigation menu ] start -->
<aside class="pcoded-navbar" id="sidebar" role="navigation" aria-label="Main navigation">
    <div class="navbar-wrapper">

        <!-- Brand / logo -->
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;"
                         src="../uploads/logo/<?= h($settings['logo'] ?? '') ?>"
                         alt="<?= h($settings['agency_name'] ?? '') ?>">
                </div>
                <span class="b-title"><?= h($settings['agency_name'] ?? '') ?></span>
            </a>

            <!-- Language switcher -->
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

                <li class="nav-item pcoded-menu-caption">
                    <label><?= __('navigation') ?></label>
                </li>

                <li data-username="dashboard" class="nav-item <?= navActive('dashboard.php') ?>">
                    <a href="dashboard.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext"><?= __('dashboard') ?></span>
                    </a>
                </li>

                <li class="nav-item pcoded-menu-caption">
                    <label><?= __('pages') ?></label>
                </li>

                <?php
                $ticketPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'ticket_reserve.php', 'ticket_weights.php'];
                $isTicketActive = in_array(basename($_SERVER['PHP_SELF']), $ticketPages);
                $showTickets = hasFeature('ticket_bookings', $allowed_features) ||
                              hasFeature('ticket_reservations', $allowed_features) ||
                              hasFeature('refunded_tickets', $allowed_features) ||
                              hasFeature('date_change_tickets', $allowed_features) ||
                              hasFeature('ticket_weights', $allowed_features);
                ?>
                <?php if ($showTickets): ?>
                <li data-username="ticket_management" class="nav-item pcoded-hasmenu <?= $isTicketActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Ticket Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php if (hasFeature('ticket_bookings', $allowed_features)): ?>
                        <li class="<?= navActive('ticket.php') ?>">
                            <a href="ticket.php"><?= __('book_tickets') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('refunded_tickets', $allowed_features)): ?>
                        <li class="<?= navActive('refund_ticket.php') ?>">
                            <a href="refund_ticket.php"><?= __('refund_tickets') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('date_change_tickets', $allowed_features)): ?>
                        <li class="<?= navActive('date_change.php') ?>">
                            <a href="date_change.php"><?= __('date_changed_tickets') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('ticket_weights', $allowed_features)): ?>
                        <li class="<?= navActive('ticket_weights.php') ?>">
                            <a href="ticket_weights.php"><?= __('ticket_weights') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('ticket_reservations', $allowed_features)): ?>
                        <li class="<?= navActive('ticket_reserve.php') ?>">
                            <a href="ticket_reserve.php"><?= __('ticket_reservations') ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php
                $hotelPages = ['hotel.php', 'hotel_refunds.php'];
                $isHotelActive = in_array(basename($_SERVER['PHP_SELF']), $hotelPages);
                $showHotel = hasFeature('hotel_bookings', $allowed_features) || hasFeature('hotel_refunds', $allowed_features);
                ?>
                <?php if ($showHotel): ?>
                <li data-username="hotel_management" class="nav-item pcoded-hasmenu <?= $isHotelActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext">Hotel Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php if (hasFeature('hotel_bookings', $allowed_features)): ?>
                        <li class="<?= navActive('hotel.php') ?>">
                            <a href="hotel.php">Hotel Bookings</a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('hotel_refunds', $allowed_features)): ?>
                        <li class="<?= navActive('hotel_refunds.php') ?>">
                            <a href="hotel_refunds.php">Hotel Refund</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('umrah_bookings', $allowed_features)): ?>
                <li data-username="umrah" class="nav-item pcoded-hasmenu <?= navTrigger('umrah.php', 'umrah_refunds.php') ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-map"></i></span>
                        <span class="pcoded-mtext"><?= __('umrah_management') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="<?= navActive('umrah.php') ?>">
                            <a href="umrah.php"><?= __('umrah_bookings') ?></a>
                        </li>
                        <?php if (hasFeature('umrah_refunds', $allowed_features)): ?>
                        <li class="<?= navActive('umrah_refunds.php') ?>">
                            <a href="umrah_refunds.php"><?= __('umrah_refunds') ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php
                $visaPages = ['visa.php', 'visa_refunds.php'];
                $isVisaActive = in_array(basename($_SERVER['PHP_SELF']), $visaPages);
                $showVisa = hasFeature('visa_applications', $allowed_features) || hasFeature('visa_refunds', $allowed_features);
                ?>
                <?php if ($showVisa): ?>
                <li data-username="visa_management" class="nav-item pcoded-hasmenu <?= $isVisaActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-globe"></i></span>
                        <span class="pcoded-mtext">Visa Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php if (hasFeature('visa_applications', $allowed_features)): ?>
                        <li class="<?= navActive('visa.php') ?>">
                            <a href="visa.php">Visa Bookings</a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('visa_refunds', $allowed_features)): ?>
                        <li class="<?= navActive('visa_refunds.php') ?>">
                            <a href="visa_refunds.php">Visa Refund</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <li data-username="additional_payments" class="nav-item <?= navActive('additional_payments.php', 'additional_payments_detail.php') ?>">
                    <a href="additional_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-plus-circle"></i></span>
                        <span class="pcoded-mtext"><?= __('additional_payments') ?></span>
                    </a>
                </li>

                <li data-username="report" class="nav-item <?= navActive('report.php') ?>">
                    <a href="report.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
                        <span class="pcoded-mtext"><?= __('reports') ?></span>
                    </a>
                </li>

            </ul>
        </div>
        <!-- /sidebar menu -->

        <!-- Sticky user profile strip at the bottom of the sidebar -->
        <div class="navbar-brand user-profile-section"
             style="position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1);background:#4099ff;z-index:10;">
            <div style="padding:8px 15px;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                    <a href="profile.php" style="text-decoration:none;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.2);">
                        <?php if ($imagePath): ?>
                        <img class="rounded-circle"
                             style="width:28px;height:28px;cursor:pointer;transition:opacity 0.3s ease;object-fit:cover;"
                             onmouseover="this.style.opacity='0.8'"
                             onmouseout="this.style.opacity='1'"
                             src="<?= $imagePath ?>"
                             alt="user-avatar"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="sidebar-avatar-initials" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;line-height:1;"><?= strtoupper(substr(h($user['name'] ?? 'U'), 0, 1)) ?></span>
                        <?php else: ?>
                        <span class="sidebar-avatar-initials" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;line-height:1;"><?= strtoupper(substr(h($user['name'] ?? 'U'), 0, 1)) ?></span>
                        <?php endif; ?>
                    </a>
                    <div style="flex:1;min-width:0;overflow:hidden;">
                        <div style="color:#fff;font-size:11px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">
                            <?= h($user['name'] ?? 'User') ?>
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
                       onmouseout="this.style.background='transparent'">
                        <i class="feather icon-person" style="font-size:12px;"></i>
                    </a>
                    <a href="logout.php" class="nav-link"
                       style="padding:4px;border-radius:3px;color:#fff;transition:all 0.3s ease;"
                       onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                       onmouseout="this.style.background='transparent'">
                        <i class="feather icon-log-out" style="font-size:12px;"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /user profile strip -->

    </div>
</aside>
<!-- [ navigation menu ] end -->

<?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
<!-- ── Floating Chat Widget ─────────────────────────────────── -->
<div id="alqChatFab" class="alq-chat-fab" title="Chat">
    <i class="feather icon-message-circle"></i>
    <span class="unread-badge" id="alqChatUnreadBadge">0</span>
    <span class="sr-only">Open chat</span>
</div>
<div id="alqChatPanel" class="alq-chat-panel" aria-hidden="true">
    <div class="alq-chat-panel__header">
        <span>Chat</span>
        <div class="alq-chat-panel__actions">
            <button id="alqChatOpenFull" class="alq-chat-btn" title="Open full page">
                <i class="feather icon-external-link"></i>
            </button>
            <button id="alqChatClose" class="alq-chat-btn" title="Close">
                <i class="feather icon-x"></i>
            </button>
        </div>
    </div>
    <iframe id="alqChatFrame" class="alq-chat-iframe"
            src="../chat.php?embed=1" loading="lazy" referrerpolicy="no-referrer"></iframe>
</div>
<?php endif; ?>

<!-- ── Floating Tasks Widget ──────────────────────────────────── -->
<?php include_once 'floating_tasks.php'; ?>

<!-- ── Scripts ────────────────────────────────────────────────── -->
<script>
var csrfToken = '<?= h($csrf_token) ?>';

document.addEventListener('DOMContentLoaded', function () {
    var shellToggle = document.getElementById('mobile-collapse');
    var shellOverlay = document.getElementById('appShellOverlay');

    function isModernShellDesktop() {
        return window.innerWidth >= 1024;
    }

    function closeModernShellSidebar() {
        document.body.classList.remove('sidebar-open');
        shellOverlay && shellOverlay.classList.remove('active');
        shellToggle && shellToggle.setAttribute('aria-expanded', 'false');
    }

    if (shellToggle) {
        shellToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            if (isModernShellDesktop()) {
                document.body.classList.toggle('sidebar-collapsed');
                closeModernShellSidebar();
                return;
            }

            var open = document.body.classList.toggle('sidebar-open');
            shellOverlay && shellOverlay.classList.toggle('active', open);
            shellToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    shellOverlay && shellOverlay.addEventListener('click', closeModernShellSidebar);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModernShellSidebar();
    });
    window.addEventListener('resize', function() {
        if (isModernShellDesktop()) closeModernShellSidebar();
    });

    // ── Page Help / Tutorial button ─────────────────────────
    var helpToggle = document.getElementById('pageHelpToggle');
    var helpMenu = document.getElementById('pageHelpMenu');
    var helpList = document.getElementById('pageHelpList');
    var helpSubhead = document.getElementById('pageHelpSubhead');
    var currentPage = window.location.pathname.split('/').pop();

    function loadPageTutorials() {
        if (!helpList || !helpSubhead) return;
        helpSubhead.textContent = 'Loading tutorials for ' + currentPage + '...';
        helpList.innerHTML = '<div class="app-help-menu__empty"><i class="feather icon-loader" style="animation:spin 1s linear infinite;display:inline-block;"></i> Loading...</div>';

        fetch('../api/tutorials/list.php?page=' + encodeURIComponent(currentPage))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.tutorials || !data.tutorials.length) {
                    helpSubhead.textContent = currentPage;
                    helpList.innerHTML = '<div class="app-help-menu__empty"><i class="feather icon-book-open" style="display:block;font-size:2rem;margin-bottom:8px;"></i>No tutorials for this page</div>';
                    return;
                }
                helpSubhead.textContent = data.tutorials.length + ' tutorial' + (data.tutorials.length > 1 ? 's' : '') + ' for ' + currentPage;
                helpList.innerHTML = data.tutorials.map(function(t) {
                    var chapters = [];
                    try { chapters = JSON.parse(t.chapters || '[]'); } catch(e) {}
                    var chapterHtml = chapters.length ? '<div class="app-help-menu__item-chapters">'
                        + chapters.map(function(c) { return '<span class="app-help-menu__chapter">' + esc(c.time) + ' ' + esc(c.label) + '</span>'; }).join('')
                        + '</div>' : '';
                    return '<div class="app-help-menu__item" data-tutorial="' + encodeURIComponent(JSON.stringify(t)) + '">'
                        + '<div class="app-help-menu__item-icon"><i class="feather icon-play-circle"></i></div>'
                        + '<div class="app-help-menu__item-body">'
                        + '<div class="app-help-menu__item-title">' + esc(t.title) + '</div>'
                        + '<div class="app-help-menu__item-desc">' + esc(t.description || '') + '</div>'
                        + chapterHtml
                        + '<div class="app-help-menu__item-play"><i class="feather icon-play"></i> Watch tutorial</div>'
                        + '</div></div>';
                }).join('');
            })
            .catch(function() {
                helpSubhead.textContent = currentPage;
                helpList.innerHTML = '<div class="app-help-menu__empty">Failed to load tutorials</div>';
            });
    }

    function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    if (helpToggle && helpMenu) {
        helpToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = helpMenu.classList.toggle('open');
            helpMenu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            helpToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) loadPageTutorials();
        });

        helpList.addEventListener('click', function(e) {
            var item = e.target.closest('.app-help-menu__item');
            if (item) {
                var tutorial = JSON.parse(decodeURIComponent(item.getAttribute('data-tutorial')));
                openTutorialVideo(tutorial);
            }
        });

        document.addEventListener('click', function(e) {
            if (helpMenu && !helpMenu.contains(e.target) && helpToggle && !helpToggle.contains(e.target)) {
                helpMenu.classList.remove('open');
                helpMenu.setAttribute('aria-hidden', 'true');
                helpToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && helpMenu) {
                helpMenu.classList.remove('open');
                helpMenu.setAttribute('aria-hidden', 'true');
                if (helpToggle) helpToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
    // ── End Help button ─────────────────────────────────────
});

// ── Chat widget JS ─────────────────────────────────────────────
<?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
(function () {
    var fab        = document.getElementById('alqChatFab');
    var panel      = document.getElementById('alqChatPanel');
    var closeBtn   = document.getElementById('alqChatClose');
    var openFull   = document.getElementById('alqChatOpenFull');
    var badge      = document.getElementById('alqChatUnreadBadge');
    var unreadCount = 0;

    if (!fab || !panel) return;

    function togglePanel(forceOpen) {
        var isOpen = panel.classList.contains('open');
        if (forceOpen === true || !isOpen) {
            panel.classList.add('open');
            panel.setAttribute('aria-hidden', 'false');
            if (unreadCount > 0) markAsSeen();
        } else {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
        }
    }

    function updateBadge(count) {
        unreadCount = count;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
    }

    function fetchUnreadCount() {
        fetch('../api/unread_count.php', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.total_unread !== undefined) updateBadge(d.total_unread); })
            .catch(function (e) { console.error('Unread count error:', e); });
    }

    function markAsSeen() {
        var frame = document.getElementById('alqChatFrame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({ type: 'markAsSeen' }, '*');
        }
    }

    fetchUnreadCount();
    setInterval(fetchUnreadCount, 30000);

    window.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'unreadCountUpdate') updateBadge(e.data.count);
    });

    fab.addEventListener('click',     function (e) { e.preventDefault(); e.stopPropagation(); togglePanel(); });
    closeBtn && closeBtn.addEventListener('click', function (e) { e.preventDefault(); togglePanel(false); });
    openFull && openFull.addEventListener('click', function (e) { e.preventDefault(); window.location.href = '../chat.php'; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('open')) togglePanel(false); });
}());
<?php endif; ?>
</script>

<!-- ── Tutorial Video Modal ────────────────────────────────── -->
<div id="helpVideoModal" class="help-video-modal" style="display:none;">
    <div class="help-video-modal-content">
        <div class="help-video-modal-header">
            <span class="help-video-modal-title" id="helpVideoTitle">Tutorial</span>
            <span class="help-video-modal-close" onclick="closeHelpVideo()">&times;</span>
        </div>
        <div class="help-video-container">
            <iframe id="helpVideoPlayer" src="" allow="autoplay; fullscreen; picture-in-picture"></iframe>
        </div>
        <div class="help-video-chapters" id="helpVideoChapters" style="display:none;">
            <div class="help-video-chapters-title">Chapters</div>
            <div class="help-video-chapters-list" id="helpVideoChaptersList"></div>
        </div>
    </div>
</div>

<script>
function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

var helpCurrentVideoId = null;
var helpCurrentVideoType = null;
var helpCurrentChapters = [];
var helpYtPlayer = null;
var helpVimeoPlayer = null;
var helpYtApiLoaded = false;
var helpVimeoApiLoaded = false;

function openTutorialVideo(tutorial) {
    var type = tutorial.video_type || 'vimeo';
    var id = tutorial.video_id || '';
    if (!id) return;

    document.getElementById('helpVideoTitle').textContent = tutorial.title || 'Tutorial';
    helpCurrentVideoId = id;
    helpCurrentVideoType = type;
    helpYtPlayer = null;
    helpVimeoPlayer = null;
    try { helpCurrentChapters = JSON.parse(tutorial.chapters || '[]'); } catch(e) { helpCurrentChapters = []; }

    var url = type === 'youtube'
        ? 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0&enablejsapi=1'
        : 'https://player.vimeo.com/video/' + id + '?autoplay=1';

    document.getElementById('helpVideoPlayer').src = url;
    document.getElementById('helpVideoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    renderHelpChapters();
    loadHelpPlayerApi();
}

function loadHelpPlayerApi() {
    var iframe = document.getElementById('helpVideoPlayer');
    if (helpCurrentVideoType === 'youtube') {
        if (!helpYtApiLoaded) {
            helpYtApiLoaded = true;
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var first = document.getElementsByTagName('script')[0];
            first.parentNode.insertBefore(tag, first);
        }
        var checkYt = setInterval(function() {
            if (typeof YT !== 'undefined' && YT.loaded) {
                clearInterval(checkYt);
                if (!helpYtPlayer) {
                    try { helpYtPlayer = new YT.Player('helpVideoPlayer', {}); } catch(e) {}
                }
            }
        }, 500);
    } else if (helpCurrentVideoType === 'vimeo') {
        if (!helpVimeoApiLoaded) {
            helpVimeoApiLoaded = true;
            var tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            var first = document.getElementsByTagName('script')[0];
            first.parentNode.insertBefore(tag, first);
        }
        var checkVimeo = setInterval(function() {
            if (typeof Vimeo !== 'undefined' && Vimeo.Player) {
                clearInterval(checkVimeo);
                if (!helpVimeoPlayer) {
                    try { helpVimeoPlayer = new Vimeo.Player(iframe); } catch(e) {}
                }
            }
        }, 500);
    }
}

function renderHelpChapters() {
    var container = document.getElementById('helpVideoChapters');
    var list = document.getElementById('helpVideoChaptersList');
    if (!helpCurrentChapters.length) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'block';
    list.innerHTML = helpCurrentChapters.map(function(ch, i) {
        return '<div class="help-video-chapter-item" onclick="seekHelpVideo(' + i + ')">'
            + '<span class="help-video-chapter-time">' + esc(ch.time) + '</span>'
            + '<span class="help-video-chapter-label">' + esc(ch.label) + '</span>'
            + '</div>';
    }).join('');
}

function seekHelpVideo(index) {
    var ch = helpCurrentChapters[index];
    if (!ch) return;
    var seconds = parseInt(ch.seconds, 10) || 0;
    if (helpCurrentVideoType === 'youtube' && helpYtPlayer && typeof helpYtPlayer.seekTo === 'function') {
        helpYtPlayer.seekTo(seconds, true);
    } else if (helpCurrentVideoType === 'vimeo' && helpVimeoPlayer && typeof helpVimeoPlayer.setCurrentTime === 'function') {
        helpVimeoPlayer.setCurrentTime(seconds).catch(function() {});
    }
}

function closeHelpVideo() {
    document.getElementById('helpVideoModal').classList.remove('show');
    document.getElementById('helpVideoPlayer').src = '';
    document.body.style.overflow = 'auto';
    helpCurrentChapters = [];
    helpYtPlayer = null;
    helpVimeoPlayer = null;
}

document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('helpVideoModal')) closeHelpVideo();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('helpVideoModal').classList.contains('show')) closeHelpVideo();
});
</script>

