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
// Include CSRF protection
require_once('../includes/CsrfProtection.php');
require_once('../includes/CommunicationAddonManager.php');
// Generate or get CSRF token for this session
$csrf_token = CsrfProtection::getToken();
// Include language system
require_once('../includes/language_helpers.php');
$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}
 // Define h() function if not already defined
 if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
// Database connection
require_once('../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? and tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

$allowed_features = [];

if ($tenant_id) {
     try {
         $query = "
                 SELECT p.features
                 FROM tenant_subscriptions ts
                 JOIN plans p ON ts.plan_id = p.id
                 WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trial')
                 ORDER BY ts.start_date DESC
                 LIMIT 1
             ";
         $stmt = $pdo->prepare($query);
         $stmt->execute([$tenant_id]);
         $row = $stmt->fetch(PDO::FETCH_ASSOC);
         
         if ($row) {
             $allowed_features = json_decode($row['features'], true) ?? [];
         }
     } catch (PDOException $e) {
         // Database error - fallback to empty features
     }
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
    // Settings error - continue with defaults
    }

// Get the user ID from the session
$user_id = $_SESSION["user_id"];

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;

// Calculate remaining session time (30 minutes = 1800 seconds)
$session_timeout = 1800; // 30 minutes in seconds
$remaining_time = isset($_SESSION['login_time']) ? $session_timeout - (time() - $_SESSION['login_time']) : $session_timeout;
$remaining_time = max(0, $remaining_time); // Ensure non-negative

$communicationAddonManager = new CommunicationAddonManager($pdo, $tenant_id);
$has_whatsapp_addon = $communicationAddonManager->hasActiveAddon($tenant_id, 'whatsapp');
$has_smtp_addon = $communicationAddonManager->hasActiveAddon($tenant_id, 'smtp');

?>


<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>

    <style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>

    <title><?= htmlspecialchars($settings['agency_name']) ?> - Owner Panel</title>

    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />


    <!-- Favicon icon -->
    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- jQuery (required for DateRangePicker) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
        <!-- Chart.js for data visualization -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
    
        <!-- Date Range Picker -->
        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    
        <!-- Additional libraries for advanced features -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

        <style>
    /* Enhanced Sidebar Styles */
    .pcoded-navbar {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        box-shadow: 2px 0 15px rgba(0,0,0,0.15) !important;
    }

    /* Fix logo display when sidebar is collapsed */
    .pcoded-navbar.navbar-collapsed .header-logo img {
        transform: rotateY(0deg) !important;
        -webkit-transform: rotateY(0deg) !important;
    }

    .pcoded-navbar .navbar-brand {
        background: rgba(255,255,255,0.1) !important;
        border-radius: 8px !important;
        margin: 10px !important;
    }

    /* White text and icons for better contrast */
    .pcoded-navbar li a,
    .pcoded-navbar .pcoded-mtext {
        color: #ffffff !important;
    }

    .pcoded-navbar .pcoded-micon,
    .pcoded-navbar i.feather,
    .pcoded-navbar i.fas {
        color: #ffffff !important;
    }

    .pcoded-navbar .navbar-brand .b-title {
        color: #ffffff !important;
    }

    .pcoded-navbar .pcoded-menu-caption label {
        color: rgba(255,255,255,0.8) !important;
    }

    .pcoded-navbar li a {
        transition: all 0.3s ease !important;
        border-radius: 6px !important;
        margin: 2px 8px !important;
    }

    .pcoded-navbar li a:hover {
        background: rgba(255,255,255,0.15) !important;
        transform: translateX(5px) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
        color: #ffffff !important;
    }

    .pcoded-navbar li a:hover .pcoded-mtext,
    .pcoded-navbar li a:hover .pcoded-micon,
    .pcoded-navbar li a:hover i.feather,
    .pcoded-navbar li a:hover i.fas {
        color: #ffffff !important;
    }

    /* Active menu items */
    .pcoded-navbar li.active a,
    .pcoded-navbar li.pcoded-trigger a {
        color: #ffffff !important;
        background: rgba(255,255,255,0.2) !important;
    }

    .pcoded-navbar li.active a .pcoded-mtext,
    .pcoded-navbar li.active a .pcoded-micon,
    .pcoded-navbar li.active i.feather,
    .pcoded-navbar li.active i.fas,
    .pcoded-navbar li.pcoded-trigger a .pcoded-mtext,
    .pcoded-navbar li.pcoded-trigger a .pcoded-micon,
    .pcoded-navbar li.pcoded-trigger i.feather,
    .pcoded-navbar li.pcoded-trigger i.fas {
        color: #ffffff !important;
    }

    /* Submenu styling */
    .pcoded-navbar .pcoded-submenu {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        border: none !important;
    }

    .pcoded-navbar .pcoded-submenu li a {
        color: #ffffff !important;
        background: transparent !important;
    }

    .pcoded-navbar .pcoded-submenu li a:hover {
        background: rgba(255,255,255,0.15) !important;
        color: #ffffff !important;
    }

    .pcoded-navbar .pcoded-submenu li a .pcoded-mtext,
    .pcoded-navbar .pcoded-submenu li a .pcoded-micon,
    .pcoded-navbar .pcoded-submenu li a i.feather,
    .pcoded-navbar .pcoded-submenu li a i.fas {
        color: #ffffff !important;
    }

    .pcoded-navbar .pcoded-submenu li.active a,
    .pcoded-navbar .pcoded-submenu li.pcoded-trigger a {
        background: rgba(255,255,255,0.2) !important;
        color: #ffffff !important;
    }

    .pcoded-navbar .pcoded-micon {
        transition: transform 0.3s ease !important;
    }

    .pcoded-navbar li a:hover .pcoded-micon {
        transform: scale(1.1) !important;
    }

    /* Enhanced Header Styles */
    .pcoded-header {
        background: #ffffff !important;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1) !important;
        border-bottom: 1px solid #e5e7eb !important;
    }

    .pcoded-header .navbar-nav li {
        margin: 0 5px !important;
    }

    .pcoded-header .navbar-nav li a {
        transition: all 0.3s ease !important;
        border-radius: 6px !important;
        padding: 8px 12px !important;
    }

    .pcoded-header .navbar-nav li a:hover {
        background: rgba(79, 70, 229, 0.1) !important;
    }


    .pcoded-header .dropdown-menu {
        border-radius: 8px !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
        border: none !important;
    }

    .pcoded-header .dropdown-item {
        transition: background 0.3s ease !important;
    }

    .pcoded-header .dropdown-item:hover {
        background: rgba(79, 70, 229, 0.1) !important;
    }

    /* Global Button Styling with Gradient */
    .btn-primary,
    .btn-success,
    .btn-info,
    .btn-warning,
    .btn-danger,
    .btn-secondary,
    .btn-light,
    .btn-dark,
    .btn-outline-primary,
    .btn-outline-success,
    .btn-outline-info,
    .btn-outline-warning,
    .btn-outline-danger,
    .btn-outline-secondary,
    .btn-outline-light,
    .btn-outline-dark,
    button[type="submit"],
    button[type="button"],
    input[type="submit"],
    input[type="button"],
    .btn {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        border: none !important;
        color: #ffffff !important;
        transition: all 0.3s ease !important;
    }

    .btn-primary:hover,
    .btn-success:hover,
    .btn-info:hover,
    .btn-warning:hover,
    .btn-danger:hover,
    .btn-secondary:hover,
    .btn-light:hover,
    .btn-dark:hover,
    .btn-outline-primary:hover,
    .btn-outline-success:hover,
    .btn-outline-info:hover,
    .btn-outline-warning:hover,
    .btn-outline-danger:hover,
    .btn-outline-secondary:hover,
    .btn-outline-light:hover,
    .btn-outline-dark:hover,
    button[type="submit"]:hover,
    button[type="button"]:hover,
    input[type="submit"]:hover,
    input[type="button"]:hover,
    .btn:hover {
        background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%) !important;
        color: #ffffff !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3) !important;
    }

    .btn-primary:focus,
    .btn-success:focus,
    .btn-info:focus,
    .btn-warning:focus,
    .btn-danger:focus,
    .btn-secondary:focus,
    .btn-light:focus,
    .btn-dark:focus,
    .btn-outline-primary:focus,
    .btn-outline-success:focus,
    .btn-outline-info:focus,
    .btn-outline-warning:focus,
    .btn-outline-danger:focus,
    .btn-outline-secondary:focus,
    .btn-outline-light:focus,
    .btn-outline-dark:focus,
    button[type="submit"]:focus,
    button[type="button"]:focus,
    input[type="submit"]:focus,
    input[type="button"]:focus,
    .btn:focus {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25) !important;
    }

    /* Specific button variations */
    .btn-outline-primary,
    .btn-outline-success,
    .btn-outline-info,
    .btn-outline-warning,
    .btn-outline-danger,
    .btn-outline-secondary,
    .btn-outline-light,
    .btn-outline-dark {
        border-color: #4099ff !important;
        color: #4099ff !important;
    }

    .btn-outline-primary:hover,
    .btn-outline-success:hover,
    .btn-outline-info:hover,
    .btn-outline-warning:hover,
    .btn-outline-danger:hover,
    .btn-outline-secondary:hover,
    .btn-outline-light:hover,
    .btn-outline-dark:hover {
        border-color: #4099ff !important;
    }

    /* Button groups and dropdowns */
    .btn-group .btn,
    .dropdown-menu .btn {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        color: #ffffff !important;
    }

    /* Modal buttons */
    .modal-footer .btn,
    .modal-header .btn {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        color: #ffffff !important;
    }

    /* Form buttons */
    .form-group .btn,
    input[type="submit"],
    input[type="button"] {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        color: #ffffff !important;
    }

    /* Special cases */
    .btn-close,
    .close {
        color: #ffffff !important;
        opacity: 0.8 !important;
    }

    .btn-close:hover,
    .close:hover {
        color: #ffffff !important;
        opacity: 1 !important;
    }
    /* ============================================
   COMPLETE RTL LAYOUT FIXES - UPDATED VERSION
   ============================================ */

    /* RTL Base Layout */
    html[dir="rtl"] body {
        direction: rtl;
        text-align: right;
    }

    /* ============================================
    RTL SIDEBAR - COMPLETE FIX
    ============================================ */

    /* RTL Sidebar Positioning */
    html[dir="rtl"] .pcoded-navbar {
        right: 0;
        left: auto;
        overflow-x: hidden;
    }

    html[dir="rtl"] .pcoded-navbar.navbar-collapsed {
        right: 0;
        left: auto;
    }

    /* RTL Main Content Area */
    html[dir="rtl"] .pcoded-main-container {
        margin-right: 264px;
        margin-left: 0;
    }

    html[dir="rtl"] .pcoded-wrapper.navbar-collapsed .pcoded-main-container {
        margin-right: 70px;
        margin-left: 0;
    }

    /* RTL Header */
    html[dir="rtl"] .pcoded-header {
        right: 264px;
        left: 0;
    }

    html[dir="rtl"] .pcoded-wrapper.navbar-collapsed .pcoded-header {
        right: 70px;
        left: 0;
    }

    /* ============================================
    FIX FOR 40PX GAP - MENU ITEMS PADDING & SPACING
    ============================================ */

    /* Remove default padding and reset for RTL menu items */
    html[dir="rtl"] .pcoded-navbar li a {
        padding-right: 15px !important;
        padding-left: 0 !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        box-sizing: border-box !important;
    }

    /* Fix the menu icon container width */
    html[dir="rtl"] .pcoded-navbar .pcoded-micon {
        width: 20px !important;
        min-width: 20px !important;
        max-width: 20px !important;
        margin-left: 10px !important;
        margin-right: 0 !important;
        padding: 0 !important;
        order: 2 !important;
        flex: 0 0 auto !important;
    }

    /* Ensure text takes remaining space without overflow */
    html[dir="rtl"] .pcoded-navbar .pcoded-mtext {
        flex: 1 1 auto !important;
        padding: 5px !important;
        margin: 0 20px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        text-align: right !important;
        order: 1 !important;
    }

    /* Fix hover effect direction */
    html[dir="rtl"] .pcoded-navbar li a:hover {
        transform: translateX(-3px) !important;
    }

    /* ============================================
    SUBMENU FIXES
    ============================================ */

    /* RTL Submenu Container */
    html[dir="rtl"] .pcoded-navbar .pcoded-submenu {
        padding-right: 0 !important;
        padding-left: 0 !important;
    }

    /* Fix submenu items padding */
    html[dir="rtl"] .pcoded-navbar .pcoded-submenu li a {
        padding-right: 40px !important;
        padding-left: 0 !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
    }

    /* Submenu icon specific fix */
    html[dir="rtl"] .pcoded-navbar .pcoded-submenu a i.feather,
    html[dir="rtl"] .pcoded-navbar .pcoded-submenu a i.fas,
    html[dir="rtl"] .pcoded-navbar .pcoded-submenu a i.fa {
        width: 20px !important;
        min-width: 20px !important;
        text-align: center !important;
        margin-left: 10px !important;
        margin-right: 0 !important;
        order: 2 !important;
        flex: 0 0 auto !important;
    }

    /* Force submenu text to left */
    html[dir="rtl"] .pcoded-navbar .pcoded-submenu li a > *:not(i) {
        order: 1 !important;
        flex: 1 !important;
        text-align: right !important;
    }

    /* ============================================
    ACTIVE STATE FIXES
    ============================================ */

    /* Fix for active menu items in RTL */
    html[dir="rtl"] .pcoded-navbar li.active > a,
    html[dir="rtl"] .pcoded-navbar li.pcoded-trigger > a {
        background: rgba(255,255,255,0.2) !important;
        border-right: 3px solid rgba(255,255,255,0.5) !important;
        border-left: none !important;
        padding-right: 12px !important; /* Compensate for border */
    }

    /* ============================================
    NAVBAR CONTENT & STRUCTURE
    ============================================ */

    /* Fix for navbar content container */
    html[dir="rtl"] .pcoded-navbar .navbar-content {
        direction: rtl;
        padding: 0 !important;
    }

    /* Fix for inner navbar */
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar {
        direction: rtl;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Ensure full width usage */
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar > li {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* Remove any extra spacing from list items */
    html[dir="rtl"] .pcoded-navbar ul {
        padding: 0 !important;
        margin: 0 !important;
    }

    html[dir="rtl"] .pcoded-navbar li {
        list-style: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* ============================================
    NAV-LINK SPECIFIC FIXES
    ============================================ */

    /* Remove any extra spacing from nav-link */
    html[dir="rtl"] .pcoded-navbar .nav-link {
        padding: 12px 15px 12px 0 !important;
        width: 100% !important;
        gap: 10px !important;
        display: flex !important;
        flex-direction: row !important;
        justify-content: flex-start !important;
    }

    /* Fix icon alignment without extra space */
    html[dir="rtl"] .pcoded-navbar .nav-link .pcoded-micon {
        order: 2 !important;
        flex: 0 0 auto !important;
    }

    html[dir="rtl"] .pcoded-navbar .nav-link .pcoded-mtext {
        order: 1 !important;
        flex: 1 !important;
        text-align: right !important;
    }

    html[dir="rtl"] .pcoded-navbar .nav-link .pcoded-micon i {
        display: block !important;
        width: 20px !important;
        text-align: center !important;
    }

    /* ============================================
    MENU CAPTION & DROPDOWN ARROW
    ============================================ */

    /* RTL Menu Caption */
    html[dir="rtl"] .pcoded-navbar .pcoded-menu-caption {
        text-align: right;
        padding-right: 15px !important;
        padding-left: 0 !important;
        margin: 0 !important;
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-menu-caption label {
        text-align: right;
        display: block;
    }

    /* RTL Has Menu Arrow */
    html[dir="rtl"] .pcoded-navbar .pcoded-hasmenu > a::after {
        left: 10px !important;
        right: auto !important;
        position: absolute !important;
        transform: rotate(180deg);
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-hasmenu.pcoded-trigger > a::after {
        transform: rotate(90deg);
    }

    /* ============================================
    COLLAPSED STATE
    ============================================ */

    /* Collapsed state - ensure proper alignment */
    html[dir="rtl"] .pcoded-navbar.navbar-collapsed li a {
        padding-right: 0 !important;
        padding-left: 0 !important;
        justify-content: center !important;
    }

    /* Fix for collapsed state */
    html[dir="rtl"] .pcoded-navbar.navbar-collapsed .pcoded-mtext {
        display: none;
    }

    /* ============================================
    NAVBAR BRAND
    ============================================ */

    /* RTL Navbar Brand */
    html[dir="rtl"] .pcoded-navbar .navbar-brand .b-bg {
        margin-left: 10px;
        margin-right: 0;
    }

    html[dir="rtl"] .pcoded-navbar .navbar-brand {
        text-align: right;
        display: flex;
        flex-direction: row-reverse;
        align-items: center;
    }

    /* ============================================
    SCROLLBAR
    ============================================ */

    /* RTL Scrollbar */
    html[dir="rtl"] .pcoded-navbar .navbar-content::-webkit-scrollbar {
        width: 6px;
    }

    html[dir="rtl"] .pcoded-navbar .navbar-content::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    /* ============================================
    MOBILE & RESPONSIVE
    ============================================ */

    /* RTL Mobile Menu */
    html[dir="rtl"] .mobile-menu {
        left: 0;
        right: auto;
    }

    /* ============================================
    GENERAL RTL ELEMENTS
    ============================================ */

    /* RTL Dropdown Menus */
    html[dir="rtl"] .dropdown-menu-right {
        right: auto !important;
        left: 0 !important;
    }

    html[dir="rtl"] .dropdown-menu {
        text-align: right;
    }

    /* RTL Card Headers */
    html[dir="rtl"] .card-header .card-header-right {
        left: 20px;
        right: auto;
    }

    /* RTL Form Elements */
    html[dir="rtl"] .form-control {
        text-align: right;
    }

    html[dir="rtl"] .input-group-append {
        margin-left: 0;
        margin-right: -1px;
    }

    html[dir="rtl"] .input-group-prepend {
        margin-right: 0;
        margin-left: -1px;
    }

    /* RTL Search Results */
    html[dir="rtl"] .search-results-dropdown {
        left: auto;
        right: 0;
    }

    html[dir="rtl"] .search-result-item i {
        margin-left: 10px;
        margin-right: 0;
    }

    /* RTL Breadcrumbs */
    html[dir="rtl"] .breadcrumb-item + .breadcrumb-item::before {
        padding-right: 0;
        padding-left: 0.5rem;
    }

    html[dir="rtl"] .breadcrumb-item + .breadcrumb-item {
        padding-right: 0.5rem;
        padding-left: 0;
    }

    /* RTL DataTables */
    html[dir="rtl"] .dataTables_wrapper .dataTables_filter {
        text-align: left;
    }

    html[dir="rtl"] .dataTables_wrapper .dataTables_length {
        text-align: right;
    }

    html[dir="rtl"] .dataTables_wrapper .dataTables_info {
        text-align: right;
    }

    html[dir="rtl"] .dataTables_wrapper .dataTables_paginate {
        text-align: left;
    }

    /* RTL Buttons */
    html[dir="rtl"] .btn-group > .btn:not(:last-child) {
        margin-left: -1px;
        margin-right: 0;
    }

    html[dir="rtl"] .btn-group > .btn:not(:first-child) {
        margin-right: -1px;
        margin-left: 0;
    }

    /* RTL Alerts */
    html[dir="rtl"] .alert-dismissible .close {
        left: 0;
        right: auto;
    }

    /* RTL Modals */
    html[dir="rtl"] .modal-header .close {
        margin: -1rem auto -1rem -1rem;
    }

    html[dir="rtl"] .modal-footer > * {
        margin-left: 0;
        margin-right: 0.25rem;
    }

    /* RTL Navigation Pills/Tabs */
    html[dir="rtl"] .nav-pills .nav-link,
    html[dir="rtl"] .nav-tabs .nav-link {
        text-align: right;
    }

    /* RTL List Groups */
    html[dir="rtl"] .list-group-item {
        text-align: right;
    }

    /* RTL Progress Bars */
    html[dir="rtl"] .progress-bar {
        right: 0;
        left: auto;
    }

    /* RTL Badges */
    html[dir="rtl"] .badge {
        margin-left: 0.5rem;
        margin-right: 0;
    }

    /* ============================================
    FLOATING CHAT WIDGET
    ============================================ */

    /* Floating Chat Widget - Base Styles */
    .alq-chat-fab {
        position: fixed;
        bottom: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 24px rgba(0,0,0,0.18);
        cursor: pointer;
        z-index: 2147483000;
        transition: transform .15s ease-in-out, box-shadow .15s ease-in-out, background .15s ease-in-out;
    }

    /* LTR positioning */
    html[dir="ltr"] .alq-chat-fab {
        right: 20px;
    }

    html[dir="ltr"] .alq-chat-panel {
        right: 20px;
    }

    html[dir="ltr"] .alq-chat-fab .unread-badge {
        right: -8px;
    }

    /* RTL positioning */
    html[dir="rtl"] .alq-chat-fab {
        left: 20px;
        right: auto;
    }

    html[dir="rtl"] .alq-chat-fab .unread-badge {
        left: -8px;
        right: auto;
    }

    html[dir="rtl"] .alq-chat-panel {
        left: 20px;
        right: auto;
    }

    .alq-chat-fab:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 10px 28px rgba(0,0,0,0.22); 
        background: #1d4ed8; 
    }

    .alq-chat-fab i { font-size: 22px; }

    .alq-chat-fab .unread-badge {
        position: absolute;
        top: -8px;
        background: #ef4444;
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: 600;
        min-width: 18px;
        text-align: center;
        display: none;
        z-index: 1;
    }

    .alq-chat-fab .unread-badge.show { display: block; }

    .alq-chat-panel {
        position: fixed;
        bottom: 86px;
        width: 400px;
        max-width: calc(100% - 24px);
        height: 70vh;
        max-height: 720px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.24);
        overflow: hidden;
        display: none;
        z-index: 2147483000;
    }

    .alq-chat-panel.open { display: block; }

    .alq-chat-panel__header {
        height: 48px;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 12px;
        font-weight: 600;
    }

    .alq-chat-panel__actions { display: flex; gap: 6px; }

    .alq-chat-btn { 
        background: transparent; 
        border: 0; 
        color: #fff; 
        width: 32px; 
        height: 32px; 
        border-radius: 6px; 
        cursor: pointer; 
    }

    .alq-chat-btn:hover { background: rgba(255,255,255,0.12); }

    .alq-chat-iframe { width: 100%; height: calc(100% - 48px); border: 0; }

    @media (max-width: 575.98px) {
        .alq-chat-panel { 
            width: calc(100% - 20px); 
            height: 80vh; 
            bottom: 76px; 
        }
        
        html[dir="ltr"] .alq-chat-panel {
            right: 10px;
        }
        
        html[dir="ltr"] .alq-chat-fab {
            right: 12px;
        }
        
        html[dir="rtl"] .alq-chat-panel {
            left: 10px;
            right: auto;
        }
        
        html[dir="rtl"] .alq-chat-fab {
            left: 12px;
            right: auto;
        }
    }

/* ============================================
MOBILE SIDEBAR - CLEAN LAYOUT FIX
============================================ */

@media (max-width: 991.98px) {
 /* Ensure main content takes full width on mobile (sidebar is overlay) */
 .pcoded-main-container {
     margin-right: 0 !important;
     margin-left: 0 !important;
 }

 /* Mobile floating hamburger button */
 .mobile-menu-float {
        position: fixed;
        top: 20px;
        left: 20px;
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1050;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        padding: 0;
    }

    .mobile-menu-float:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(0,0,0,0.4);
    }

    .mobile-menu-float .mobile-menu {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 0;
        width: 24px;
        height: 18px;
        position: relative;
    }

    .mobile-menu-float .mobile-menu span {
        width: 100%;
        margin-top: 5px;
        height: 2px;
        background: #ffffff;
        border-radius: 2px;
        display: block;
        transition: all 0.3s ease;
        position: absolute;
        left: 0;
    }

    .mobile-menu-float .mobile-menu span:nth-child(1) { 
        top: 0;
    }
    
    .mobile-menu-float .mobile-menu span:nth-child(2) { 
        top: 50%; 
        transform: translateY(-50%);
    }
    
    .mobile-menu-float .mobile-menu span:nth-child(3) { 
        bottom: 0;
    }

    /* Animated hamburger when menu is open */
    .mobile-menu-float.active .mobile-menu span:nth-child(1) {
        top: 50%;
        transform: translateY(-50%) rotate(45deg);
    }

    .mobile-menu-float.active .mobile-menu span:nth-child(2) {
        opacity: 0;
    }

    .mobile-menu-float.active .mobile-menu span:nth-child(3) {
        bottom: 50%;
        transform: translateY(50%) rotate(-45deg);
    }

    /* RTL support for mobile button */
    html[dir="rtl"] .mobile-menu-float {
        left: auto;
        right: 20px;
    }

    /* Hide the desktop navbar toggle */
    .pcoded-navbar .navbar-brand .mobile-menu {
        display: none !important;
    }

    /* Hide the original navbar on mobile by default */
    .pcoded-navbar {
        display: none;
    }

    /* Show sidebar as full-width overlay on mobile */
    .pcoded-navbar.mobile-overlay {
        display: block !important;
        position: fixed;
        top: 0;
        left: 0;
        width: 280px !important;
        max-width: 85vw;
        height: 100vh;
        z-index: 1040;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .pcoded-navbar.mobile-overlay.open {
        transform: translateX(0);
    }

    /* RTL support for mobile sidebar */
    html[dir="rtl"] .pcoded-navbar.mobile-overlay {
        left: auto;
        right: 0;
        transform: translateX(100%);
    }

    html[dir="rtl"] .pcoded-navbar.mobile-overlay.open {
        transform: translateX(0);
    }

    /* CRITICAL: Fix menu item layout */
    .pcoded-navbar.mobile-overlay li a {
        padding: 12px 15px !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 12px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Reset all icon and text positioning */
    .pcoded-navbar.mobile-overlay .pcoded-micon {
        width: 24px !important;
        min-width: 24px !important;
        max-width: 24px !important;
        height: 24px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        order: 1 !important;
        margin: 0 !important;
        padding: 0 !important;
        position: relative !important;
    }

    .pcoded-navbar.mobile-overlay .pcoded-micon i {
        font-size: 18px !important;
        line-height: 1 !important;
        display: block !important;
    }

    .pcoded-navbar.mobile-overlay .pcoded-mtext {
        flex: 1 !important;
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
        white-space: nowrap !important;
        order: 2 !important;
        margin: 0 !important;
        padding: 0 !important;
        position: relative !important;
        text-align: left !important;
    }

    /* RTL text alignment */
    html[dir="rtl"] .pcoded-navbar.mobile-overlay .pcoded-mtext {
        text-align: right !important;
    }

    /* Fix for submenu items */
    .pcoded-navbar.mobile-overlay .pcoded-submenu li a {
        padding-left: 45px !important;
    }

    html[dir="rtl"] .pcoded-navbar.mobile-overlay .pcoded-submenu li a {
        padding-left: 15px !important;
        padding-right: 45px !important;
    }

    /* Ensure submenu icons and text don't overlap */
    .pcoded-navbar.mobile-overlay .pcoded-submenu .pcoded-micon {
        width: 20px !important;
        min-width: 20px !important;
        max-width: 20px !important;
    }

    .pcoded-navbar.mobile-overlay .pcoded-submenu .pcoded-mtext {
        display: block !important;
        opacity: 1 !important;
    }

    /* Fix for has-menu arrow */
    .pcoded-navbar.mobile-overlay .pcoded-hasmenu > a::after {
        position: absolute !important;
        right: 15px !important;
        left: auto !important;
    }

    html[dir="rtl"] .pcoded-navbar.mobile-overlay .pcoded-hasmenu > a::after {
        right: auto !important;
        left: 15px !important;
    }

    /* Remove any transform on icons */
    .pcoded-navbar.mobile-overlay .pcoded-micon,
    .pcoded-navbar.mobile-overlay .pcoded-mtext {
        transform: none !important;
    }

    /* Ensure logo section is visible */
    .pcoded-navbar.mobile-overlay .navbar-brand.header-logo {
        display: flex !important;
        padding: 15px !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 10px !important;
    }

    .pcoded-navbar.mobile-overlay .navbar-brand.header-logo .b-title {
        display: inline-block !important;
        opacity: 1 !important;
    }

    /* Language selector */
    .pcoded-navbar.mobile-overlay .language-selector {
        display: block !important;
        padding: 10px 15px !important;
        text-align: center !important;
    }

    /* Menu caption */
    .pcoded-navbar.mobile-overlay .pcoded-menu-caption {
        display: block !important;
        padding: 10px 15px !important;
        margin-top: 10px !important;
    }

    .pcoded-navbar.mobile-overlay .pcoded-menu-caption label {
        display: block !important;
        opacity: 1 !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }

    /* User profile section at bottom */
    .pcoded-navbar.mobile-overlay .navbar-brand.user-profile-section {
        display: block !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 280px !important;
        max-width: 85vw !important;
        border-top: 1px solid rgba(255,255,255,0.1) !important;
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        z-index: 10 !important;
    }

    html[dir="rtl"] .pcoded-navbar.mobile-overlay .navbar-brand.user-profile-section {
        left: auto !important;
        right: 0 !important;
    }

    /* Navbar content padding for user profile */
    .pcoded-navbar.mobile-overlay .navbar-content {
        padding-bottom: 100px !important;
    }

    /* Mobile overlay background */
    .mobile-menu-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1030;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .mobile-menu-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* Force remove collapsed state on mobile */
    .pcoded-navbar.mobile-overlay.navbar-collapsed {
        width: 280px !important;
        max-width: 85vw !important;
    }

    .pcoded-navbar.mobile-overlay.navbar-collapsed .pcoded-mtext {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }

    .pcoded-navbar.mobile-overlay.navbar-collapsed .pcoded-micon {
        margin: 0 !important;
    }
}

/* Tablet adjustments */
@media (min-width: 576px) and (max-width: 991.98px) {
    .pcoded-navbar.mobile-overlay {
        width: 300px !important;
    }
    
    .pcoded-navbar.mobile-overlay .navbar-brand.user-profile-section {
        width: 300px !important;
    }
}

    /* ============================================
    RTL UTILITY CLASSES
    ============================================ */

    /* Additional RTL Utility Classes */
    html[dir="rtl"] .mr-auto,
    html[dir="rtl"] .mx-auto {
        margin-right: 0 !important;
        margin-left: auto !important;
    }

    html[dir="rtl"] .ml-auto,
    html[dir="rtl"] .mx-auto {
        margin-left: 0 !important;
        margin-right: auto !important;
    }

    html[dir="rtl"] .pr-0 { padding-right: 0 !important; padding-left: 0 !important; }
    html[dir="rtl"] .pl-0 { padding-left: 0 !important; padding-right: 0 !important; }
    html[dir="rtl"] .pr-1 { padding-right: 0.25rem !important; padding-left: 0.25rem !important; }
    html[dir="rtl"] .pl-1 { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
    html[dir="rtl"] .pr-2 { padding-right: 0.5rem !important; padding-left: 0.5rem !important; }
    html[dir="rtl"] .pl-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
    html[dir="rtl"] .pr-3 { padding-right: 1rem !important; padding-left: 1rem !important; }
    html[dir="rtl"] .pl-3 { padding-left: 1rem !important; padding-right: 1rem !important; }
    html[dir="rtl"] .pr-4 { padding-right: 1.5rem !important; padding-left: 1.5rem !important; }
    html[dir="rtl"] .pl-4 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
    html[dir="rtl"] .pr-5 { padding-right: 3rem !important; padding-left: 3rem !important; }
    html[dir="rtl"] .pl-5 { padding-left: 3rem !important; padding-right: 3rem !important; }

    html[dir="rtl"] .mr-0 { margin-right: 0 !important; margin-left: 0 !important; }
    html[dir="rtl"] .ml-0 { margin-left: 0 !important; margin-right: 0 !important; }
    html[dir="rtl"] .mr-1 { margin-right: 0.25rem !important; margin-left: 0.25rem !important; }
    html[dir="rtl"] .ml-1 { margin-left: 0.25rem !important; margin-right: 0.25rem !important; }
    html[dir="rtl"] .mr-2 { margin-right: 0.5rem !important; margin-left: 0.5rem !important; }
    html[dir="rtl"] .ml-2 { margin-left: 0.5rem !important; margin-right: 0.5rem !important; }
    html[dir="rtl"] .mr-3 { margin-right: 1rem !important; margin-left: 1rem !important; }
    html[dir="rtl"] .ml-3 { margin-left: 1rem !important; margin-right: 1rem !important; }
    html[dir="rtl"] .mr-4 { margin-right: 1.5rem !important; margin-left: 1.5rem !important; }
    html[dir="rtl"] .ml-4 { margin-left: 1.5rem !important; margin-right: 1.5rem !important; }
    html[dir="rtl"] .mr-5 { margin-right: 3rem !important; margin-left: 3rem !important; }
    html[dir="rtl"] .ml-5 { margin-left: 3rem !important; margin-right: 3rem !important; }

    html[dir="rtl"] .text-left { text-align: right !important; }
    html[dir="rtl"] .text-right { text-align: left !important; }

    html[dir="rtl"] .float-left { float: right !important; }
    html[dir="rtl"] .float-right { float: left !important; }

    /* Ensure content doesn't push beyond boundaries */
    html[dir="rtl"] .pcoded-navbar * {
        box-sizing: border-box !important;
    }

    /* ============================================
    MODERN HEADER & SIDEBAR TEMPLATE
    ============================================ */
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

    .app-header__search {
        flex: 1;
        max-width: 400px;
        position: relative;
    }

    .app-header__search i {
        position: absolute;
        left: .85rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--app-text-secondary);
        pointer-events: none;
    }

    .app-header__search input {
        width: 100%;
        height: 38px;
        padding: 0 1rem 0 2.5rem;
        border: 1.5px solid var(--app-border);
        border-radius: 999px;
        background: var(--app-bg-page);
        font-size: .875rem;
        color: var(--app-text-primary);
        outline: none;
        transition: border-color var(--app-transition), box-shadow var(--app-transition);
    }

    .app-header__search input:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 3px rgba(64,153,255,.15);
        background: #fff;
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
    }

    .app-header__avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

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

    .pcoded-navbar .pcoded-menu-caption {
        padding: 1rem .625rem .375rem !important;
        margin: 0 !important;
    }

    .pcoded-navbar .pcoded-menu-caption label {
        font-size: .67rem !important;
        font-weight: 600 !important;
        letter-spacing: .1em !important;
        text-transform: uppercase !important;
        color: rgba(168,184,204,.45) !important;
        margin: 0 !important;
    }

    .pcoded-navbar li a.nav-link {
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
        box-shadow: none !important;
        transform: none !important;
        transition: background var(--app-transition), color var(--app-transition), box-shadow var(--app-transition) !important;
    }

    .pcoded-navbar li a.nav-link:hover {
        background: rgba(255,255,255,.07) !important;
        color: #fff !important;
        transform: none !important;
        box-shadow: none !important;
    }

    .pcoded-navbar li.active > a.nav-link {
        background: var(--app-gradient) !important;
        color: #fff !important;
        box-shadow: 0 4px 14px rgba(64,153,255,.35) !important;
    }

    .pcoded-navbar .pcoded-micon {
        width: 22px !important;
        min-width: 22px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: inherit !important;
        margin: 0 !important;
        transform: none !important;
    }

    .pcoded-navbar .pcoded-micon i,
    .pcoded-navbar .pcoded-mtext {
        color: inherit !important;
    }

    .pcoded-navbar .pcoded-mtext {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin: 0 !important;
        padding: 0 !important;
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

    body.sidebar-collapsed .pcoded-navbar {
        width: var(--app-sidebar-collapsed-width) !important;
    }

    body.sidebar-collapsed .pcoded-main-container {
        margin-left: var(--app-sidebar-collapsed-width) !important;
    }

    body.sidebar-collapsed .pcoded-navbar .b-title,
    body.sidebar-collapsed .pcoded-navbar .language-selector,
    body.sidebar-collapsed .pcoded-navbar .pcoded-menu-caption label,
    body.sidebar-collapsed .pcoded-navbar .pcoded-mtext,
    body.sidebar-collapsed .pcoded-navbar .user-profile-section div div:not(:first-child),
    body.sidebar-collapsed .pcoded-navbar .user-profile-section a.nav-link {
        display: none !important;
    }

    body.sidebar-collapsed .pcoded-navbar .navbar-brand.header-logo,
    body.sidebar-collapsed .pcoded-navbar li a.nav-link {
        justify-content: center !important;
        padding-left: .5rem !important;
        padding-right: .5rem !important;
    }

    html[dir="rtl"] .app-header__search i {
        left: auto;
        right: .85rem;
    }

    html[dir="rtl"] .app-header__search input {
        padding: 0 2.5rem 0 1rem;
    }

    html[dir="rtl"] .app-header__actions {
        margin-left: 0;
        margin-right: auto;
    }

    html[dir="rtl"] .pcoded-navbar {
        right: 0 !important;
        left: auto !important;
    }

    html[dir="rtl"] .pcoded-main-container {
        margin-right: var(--app-sidebar-width) !important;
        margin-left: 0 !important;
    }

    html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container {
        margin-right: var(--app-sidebar-collapsed-width) !important;
        margin-left: 0 !important;
    }

    html[dir="rtl"] .pcoded-navbar .language-selector {
        margin-left: 0;
        margin-right: auto;
    }

    @media (min-width: 1024px) {
        .app-header {
            left: var(--app-sidebar-width);
            transition: left var(--app-transition), right var(--app-transition);
        }

        .app-header__brand {
            display: none !important;
        }

        body.sidebar-collapsed .app-header {
            left: var(--app-sidebar-collapsed-width);
        }

        body.sidebar-collapsed .pcoded-navbar:hover {
            width: var(--app-sidebar-width) !important;
        }

        body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo,
        body.sidebar-collapsed .pcoded-navbar:hover li a.nav-link {
            justify-content: flex-start !important;
            padding-left: .625rem !important;
            padding-right: .625rem !important;
        }

        body.sidebar-collapsed .pcoded-navbar:hover .b-title,
        body.sidebar-collapsed .pcoded-navbar:hover .language-selector,
        body.sidebar-collapsed .pcoded-navbar:hover .pcoded-menu-caption label,
        body.sidebar-collapsed .pcoded-navbar:hover .pcoded-mtext {
            display: inline-block !important;
        }

        body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section div div:not(:first-child) {
            display: block !important;
        }

        body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section a.nav-link {
            display: flex !important;
        }

        html[dir="rtl"] .app-header {
            left: 0;
            right: var(--app-sidebar-width);
        }

        html[dir="rtl"] body.sidebar-collapsed .app-header {
            right: var(--app-sidebar-collapsed-width);
        }

        html[dir="rtl"] body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo,
        html[dir="rtl"] body.sidebar-collapsed .pcoded-navbar:hover li a.nav-link {
            justify-content: flex-start !important;
        }
    }

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
        .app-header__search {
            display: none;
        }

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

    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        display: flex !important;
        align-items: center !important;
        gap: .625rem !important;
        width: 100% !important;
        min-width: 0 !important;
        padding: .62rem .625rem !important;
        box-sizing: border-box !important;
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
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        flex-direction: row-reverse !important;
    }

    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext {
        text-align: right !important;
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

    <?php
    // Check if tenant is in trial mode and show banner
    if (isset($tenant_id) && $tenant_id) {
        try {
            $trial_stmt = $pdo->prepare("SELECT status, trial_days, trial_end_date FROM tenants WHERE id = ?");
            $trial_stmt->execute([$tenant_id]);
            $trial_info = $trial_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($trial_info && $trial_info['status'] === 'trial' && !empty($trial_info['trial_end_date'])) {
                $trial_end = $trial_info['trial_end_date'];
                $today = date('Y-m-d');
                $days_left = max(0, (int)((strtotime($trial_end) - strtotime($today)) / 86400));
                $is_expired = $trial_end < $today;
                $urgency_class = $is_expired ? 'trial-banner-expired' : ($days_left <= 3 ? 'trial-banner-urgent' : 'trial-banner-active');
    ?>
    <!-- Trial Period Banner -->
    <div class="trial-banner <?= $urgency_class ?>" id="trialBanner">
        <div class="trial-banner-content">
            <div class="trial-banner-icon">
                <i class="feather icon-clock"></i>
            </div>
            <div class="trial-banner-text">
                <?php if ($is_expired): ?>
                    <strong>Trial Expired!</strong> Your trial period ended on <?= date('M d, Y', strtotime($trial_end)) ?>. Please activate your subscription to continue using all features.
                <?php else: ?>
                    <strong>Trial Period:</strong> You have <strong><?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?></strong> remaining in your free trial. Trial ends on <strong><?= date('M d, Y', strtotime($trial_end)) ?></strong>.
                <?php endif; ?>
            </div>
            <button class="trial-banner-close" onclick="closeTrialBanner();">&times;</button>
        </div>
    </div>
    <script>
    function closeTrialBanner() {
        var banner = document.getElementById('trialBanner');
        if (banner) {
            banner.style.display = 'none';
        }
        document.body.classList.remove('has-trial-banner');
        sessionStorage.setItem('trialBannerClosed', 'true');
    }

    if (sessionStorage.getItem('trialBannerClosed') === 'true') {
        closeTrialBanner();
    } else {
        document.body.classList.add('has-trial-banner');
    }
    </script>
    <style>
    .trial-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 9999;
        padding: 0;
        font-family: 'Inter', system-ui, sans-serif;
    }
    .trial-banner-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 20px;
        font-size: 0.9rem;
        color: #fff;
    }
    .trial-banner-active .trial-banner-content {
        background: linear-gradient(135deg, #3b82f6, #2ed8b6);
    }
    .trial-banner-urgent .trial-banner-content {
        background: linear-gradient(135deg, #f59e0b, #ef4444);
        animation: trial-pulse 2s ease-in-out infinite;
    }
    .trial-banner-expired .trial-banner-content {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    .trial-banner-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .trial-banner-text {
        flex: 1;
        text-align: center;
    }
    .trial-banner-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: #fff;
        font-size: 1.3rem;
        cursor: pointer;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        line-height: 1;
    }
    .trial-banner-close:hover {
        background: rgba(255,255,255,0.4);
    }
    @keyframes trial-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.85; }
    }
    body.has-trial-banner .app-header {
        top: 44px !important;
    }
    body.has-trial-banner .pcoded-navbar {
        top: 44px !important;
        height: calc(100vh - 44px) !important;
    }
    body.has-trial-banner .pcoded-main-container {
        padding-top: calc(var(--app-header-height) + 44px) !important;
    }
    </style>
    <?php
            }
        } catch (Exception $e) {
            // Silently ignore
        }
    }
    ?>

    <div class="app-shell-overlay" id="appShellOverlay"></div>

    <header class="app-header" role="banner">
        <button class="app-header__toggle" id="mobile-collapse" type="button" aria-label="Toggle sidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <a href="dashboard.php" class="app-header__brand">
            <span class="app-header__brand-logo">
                <img src="../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="<?= htmlspecialchars($settings['agency_name']) ?>">
            </span>
            <span class="app-header__brand-name"><?= htmlspecialchars($settings['agency_name']) ?></span>
        </a>

        <div class="app-header__search">
            <i class="feather icon-search"></i>
            <input type="search" id="m-search" placeholder="Search..." aria-label="Search">
        </div>

        <div class="app-header__actions">
            <?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
            <a class="app-header__icon-btn" href="../chat.php" aria-label="Messages">
                <i class="feather icon-message-circle"></i>
                <span class="app-header__badge"></span>
            </a>
            <?php endif; ?>
            <a class="app-header__icon-btn" href="tenant_settings.php" aria-label="Settings">
                <i class="feather icon-settings"></i>
            </a>
            <a class="app-header__avatar" href="profile.php" aria-label="Profile">
                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($user['name'] ?? 'User') ?>">
            </a>
        </div>
    </header>

<!-- [ navigation menu ] start -->
<aside class="pcoded-navbar" id="sidebar" role="navigation" aria-label="Main navigation">
    <div class="navbar-wrapper">
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;" src="../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
                </div>
                <span class="b-title"><?= htmlspecialchars($settings['agency_name']) ?></span>
            </a>
            <div class="language-selector" style="padding: 5px 15px; text-align: center;">
                <select onchange="window.location.href='../language_switcher.php?lang='+this.value" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: #ffffff; border: none; border-radius: 4px; padding: 2px 5px; font-size: 11px; cursor: pointer;">
                    <option value="en" <?= get_current_lang() == 'en' ? 'selected' : '' ?> style="background: #4099ff; color: #ffffff;">EN</option>
                    <option value="fa" <?= get_current_lang() == 'fa' ? 'selected' : '' ?> style="background: #4099ff; color: #ffffff;">دری</option>
                    <option value="ps" <?= get_current_lang() == 'ps' ? 'selected' : '' ?> style="background: #4099ff; color: #ffffff;">پښتو</option>
                </select>
            </div>
        </div>
        <div class="navbar-content scroll-div" style="padding-bottom: 100px;">
            <ul class="nav pcoded-inner-navbar">
                <li class="nav-item pcoded-menu-caption">
                    <label>Owner Panel</label>
                </li>

                <li data-username="dashboard" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="dashboard.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext">Dashboard</span>
                    </a>
                </li>
                <li data-username="subscription_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'subscription_payments.php' ? 'active' : ''; ?>">
                    <a href="subscription_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
                        <span class="pcoded-mtext"><?= __('subscription_payments') ?></span>
                    </a>
                </li>
                <?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
                <li data-username="chat" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">
                    <a href="../chat.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-message-circle"></i></span>
                        <span class="pcoded-mtext">Chat</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-item pcoded-menu-caption">
                    <label>Branch Management</label>
                </li>

                <li data-username="branches" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'branches.php' ? 'active' : ''; ?>">
                    <a href="branches.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-code-branch"></i>
                        </span>
                        <span class="pcoded-mtext">Branches</span>
                    </a>
                </li>
                <li data-username="request_branch_addon" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'request_branch_addon.php' ? 'active' : ''; ?>">
                    <a href="request_branch_addon.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-package"></i></span>
                        <span class="pcoded-mtext">Request Branch</span>
                    </a>
                </li>
                <li data-username="users" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <a href="users.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-plus"></i></span>
                        <span class="pcoded-mtext">Users</span>
                    </a>
                </li>
                <li data-username="request_user_addon" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'request_user_addon.php' ? 'active' : ''; ?>">
                    <a href="request_user_addon.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-package"></i></span>
                        <span class="pcoded-mtext">Request Users</span>
                    </a>
                </li>
                <li data-username="request_communication_addon" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'request_communication_addon.php' ? 'active' : ''; ?>">
                    <a href="request_communication_addon.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-message-square"></i></span>
                        <span class="pcoded-mtext">Request Communication</span>
                    </a>
                </li>
                <?php if (hasFeature('attendance', $allowed_features)): ?>
                <li data-username="branch_attendance" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'branch_attendance.php' ? 'active' : ''; ?>">
                    <a href="branch_attendance.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Branch Attendance</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php
                $ticketPages = ['ticket_bookings.php', 'ticket_reservations.php', 'refunded_tickets.php', 'date_change_tickets.php', 'ticket_weights.php'];
                $isTicketActive = in_array(basename($_SERVER['PHP_SELF']), $ticketPages);
                $showTickets = hasFeature('ticket_bookings', $allowed_features) ||
                              hasFeature('ticket_reservations', $allowed_features) ||
                              hasFeature('refunded_tickets', $allowed_features) ||
                              hasFeature('date_change_tickets', $allowed_features) ||
                              hasFeature('ticket_weights', $allowed_features);
                ?>
                <?php if ($showTickets): ?>
                <li class="nav-item pcoded-menu-caption">
                    <label>Business Operations</label>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('ticket_bookings', $allowed_features)): ?>
                <li data-username="ticket_bookings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ticket_bookings.php' ? 'active' : ''; ?>">
                    <a href="ticket_bookings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-navigation"></i></span>
                        <span class="pcoded-mtext">Ticket Bookings</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('ticket_reservations', $allowed_features)): ?>
                <li data-username="ticket_reservations" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ticket_reservations.php' ? 'active' : ''; ?>">
                    <a href="ticket_reservations.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-clock"></i></span>
                        <span class="pcoded-mtext">Reservations</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('refunded_tickets', $allowed_features)): ?>
                <li data-username="refunded_tickets" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'refunded_tickets.php' ? 'active' : ''; ?>">
                    <a href="refunded_tickets.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-refresh-ccw"></i></span>
                        <span class="pcoded-mtext">Refunded Tickets</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('date_change_tickets', $allowed_features)): ?>
                <li data-username="date_change_tickets" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'date_change_tickets.php' ? 'active' : ''; ?>">
                    <a href="date_change_tickets.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Date Changes</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('ticket_weights', $allowed_features)): ?>
                <li data-username="ticket_weights" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ticket_weights.php' ? 'active' : ''; ?>">
                    <a href="ticket_weights.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-package"></i></span>
                        <span class="pcoded-mtext">Ticket Weights</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('hotel_bookings', $allowed_features)): ?>
                <li data-username="hotel_bookings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'hotel_bookings.php' ? 'active' : ''; ?>">
                    <a href="hotel_bookings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext">Hotel Bookings</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('visa_applications', $allowed_features)): ?>
                <li data-username="visa_applications" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'visa_applications.php' ? 'active' : ''; ?>">
                    <a href="visa_applications.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file-text"></i></span>
                        <span class="pcoded-mtext">Visa Applications</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('umrah_bookings', $allowed_features)): ?>
                <li data-username="umrah_bookings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'umrah_bookings.php' ? 'active' : ''; ?>">
                    <a href="umrah_bookings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-map-pin"></i></span>
                        <span class="pcoded-mtext">Umrah Bookings</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php
                $financePages = ['main_accounts.php', 'suppliers.php', 'clients.php', 'expenses.php', 'additional_payments.php', 'salary_management.php', 'sarafi.php', 'debtors.php', 'creditors.php'];
                $isFinanceActive = in_array(basename($_SERVER['PHP_SELF']), $financePages);
                $showFinance = hasFeature('financial_statements', $allowed_features) ||
                              hasFeature('debtors', $allowed_features) ||
                              hasFeature('creditors', $allowed_features) ||
                              hasFeature('sarafi', $allowed_features) ||
                              hasFeature('salary', $allowed_features) ||
                              hasFeature('additional_payments', $allowed_features) ||
                              hasFeature('expense_management', $allowed_features);
                ?>
                <?php if ($showFinance): ?>
                <li class="nav-item pcoded-menu-caption">
                    <label>Financial Management</label>
                </li>
                <?php endif; ?>

                <li data-username="main_accounts" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'main_accounts.php' ? 'active' : ''; ?>">
                    <a href="main_accounts.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
                        <span class="pcoded-mtext">Main Accounts</span>
                    </a>
                </li>

                <li data-username="suppliers" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : ''; ?>">
                    <a href="suppliers.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-truck"></i>
                        </span>
                        <span class="pcoded-mtext">Suppliers</span>
                    </a>
                </li>

                <li data-username="clients" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>">
                    <a href="clients.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext">Clients</span>
                    </a>
                </li>

                <?php if (hasFeature('expense_management', $allowed_features)): ?>
                <li data-username="expenses" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>">
                    <a href="expenses.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
                        <span class="pcoded-mtext">Expenses</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('additional_payments', $allowed_features)): ?>
                <li data-username="additional_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'additional_payments.php' ? 'active' : ''; ?>">
                    <a href="additional_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-plus-circle"></i></span>
                        <span class="pcoded-mtext">Additional Payments</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('salary', $allowed_features)): ?>
                <li data-username="salary_management" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'salary_management.php' ? 'active' : ''; ?>">
                    <a href="salary_management.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-check"></i></span>
                        <span class="pcoded-mtext">Salary Management</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('sarafi', $allowed_features)): ?>
                <li data-username="sarafi" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sarafi.php' ? 'active' : ''; ?>">
                    <a href="sarafi.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-repeat"></i></span>
                        <span class="pcoded-mtext">Sarafi (Money Exchange)</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('debtors', $allowed_features)): ?>
                <li data-username="debtors" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'debtors.php' ? 'active' : ''; ?>">
                    <a href="debtors.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-minus"></i></span>
                        <span class="pcoded-mtext">Debtors</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('creditors', $allowed_features)): ?>
                <li data-username="creditors" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'creditors.php' ? 'active' : ''; ?>">
                    <a href="creditors.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-plus"></i></span>
                        <span class="pcoded-mtext">Creditors</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasFeature('financial_statements', $allowed_features)): ?>
                <li class="nav-item pcoded-menu-caption">
                    <label>Reports & Analytics</label>
                </li>

                <li data-username="reports" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <a href="reports.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
                        <span class="pcoded-mtext">Reports</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- ── Settings ────────────────────────────────────────────────────── -->
                <li class="nav-item pcoded-menu-caption">
                    <label><?= __('settings') ?></label>
                </li>
                <li data-username="tenant_settings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tenant_settings.php' ? 'active' : ''; ?>">
                    <a href="tenant_settings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-settings"></i></span>
                        <span class="pcoded-mtext">Agency Settings</span>
                    </a>
                </li>
                <li data-username="manage_templates" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_templates.php' ? 'active' : ''; ?>">
                    <a href="manage_templates.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-edit"></i></span>
                        <span class="pcoded-mtext">Umrah Tazmin Template</span>
                    </a>
                </li>

                <!-- ── Communication ────────────────────────────────────────────────── -->
                <?php if ($has_whatsapp_addon || $has_smtp_addon): ?>
                <li class="nav-item pcoded-menu-caption">
                    <label><?= __('communication') ?></label>
                </li>
                <?php endif; ?>

                <?php if ($has_whatsapp_addon): ?>
                <li data-username="whatsapp_settings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'whatsapp_settings.php' ? 'active' : ''; ?>">
                    <a href="whatsapp_settings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-message-circle"></i></span>
                        <span class="pcoded-mtext">WhatsApp Settings</span>
                    </a>
                </li>
                <li data-username="whatsapp_analytics" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'whatsapp_analytics.php' ? 'active' : ''; ?>">
                    <a href="whatsapp_analytics.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-bar-chart-2"></i></span>
                        <span class="pcoded-mtext">WhatsApp Analytics</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($has_smtp_addon): ?>
                <li data-username="email_analytics" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'email_analytics.php' ? 'active' : ''; ?>">
                    <a href="email_analytics.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-mail"></i></span>
                        <span class="pcoded-mtext"><?= __('email_analytics') ?></span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- ── Security & Monitoring ────────────────────────────────────────── -->
                <li class="nav-item pcoded-menu-caption">
                    <label><?= __('security_monitoring') ?></label>
                </li>

                <!-- ── 2FA ────────────────────────────────────────────────────────────── -->
                <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'totp_setup.php' ? 'active' : ''; ?>">
                    <a href="../totp_setup.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-shield"></i></span>
                        <span class="pcoded-mtext"><?= __('2fa') ?></span>
                    </a>
                </li>

                <li data-username="activity_logs" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : ''; ?>">
                    <a href="activity_logs.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-activity"></i></span>
                        <span class="pcoded-mtext">Activity Logs</span>
                    </a>
                </li>

                <!-- ── Tutorials ──────────────────────────────────────────────────────── -->
                <li class="nav-item pcoded-menu-caption">
                    <label>Support</label>
                </li>
                <li data-username="tutorials" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'tutorial.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>">
                    <a href="../admin/tutorial.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-book"></i></span>
                        <span class="pcoded-mtext">Tutorials</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="navbar-brand user-profile-section" style="position: absolute; bottom: 0; width: 100%; border-top: 1px solid rgba(255,255,255,0.1); background: #4099ff; z-index: 10;">
            <div style="padding: 8px 15px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
                    <a href="profile.php" style="text-decoration: none; flex-shrink: 0;">
                        <img class="rounded-circle" style="width:28px; height:28px; cursor: pointer; transition: opacity 0.3s ease;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'" src="<?= $imagePath ?>" alt="user-avatar">
                    </a>
                    <div style="flex: 1; min-width: 0; overflow: hidden;">
                        <div style="color: #ffffff; font-size: 11px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                        <div style="color: rgba(255,255,255,0.7); font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    </div>
                </div>
                <div style="display: flex; gap: 1px; flex-shrink: 0;">
                    <a href="profile.php" class="nav-link" style="padding: 4px; border-radius: 3px; color: #ffffff; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='transparent'">
                        <i class="feather icon-person" style="font-size: 12px;"></i>
                    </a>
                    <a href="logout.php" class="nav-link" style="padding: 4px; border-radius: 3px; color: #ffffff; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='transparent'">
                        <i class="feather icon-log-out" style="font-size: 12px;"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>
<!-- [ navigation menu ] end -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const toggleBtn = document.getElementById('mobile-collapse');
    const overlay = document.getElementById('appShellOverlay');

    function isDesktop() {
        return window.innerWidth >= 1024;
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        if (overlay) {
            overlay.classList.remove('active');
        }
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isDesktop()) {
                body.classList.toggle('sidebar-collapsed');
                closeSidebar();
                return;
            }

            const open = body.classList.toggle('sidebar-open');
            if (overlay) {
                overlay.classList.toggle('active', open);
            }
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (isDesktop()) {
            closeSidebar();
        }
    });
});
</script>


<?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
<!-- Floating Chat Widget -->
<style>
    .alq-chat-fab {
        position: fixed;
        bottom: 20px;
        <?php echo is_rtl() ? 'left' : 'right'; ?>: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 24px rgba(0,0,0,0.18);
        cursor: pointer;
        z-index: 2147483000;
        transition: transform .15s ease-in-out, box-shadow .15s ease-in-out, background .15s ease-in-out;
    }
    .alq-chat-fab:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,0.22); background: #1d4ed8; }
    .alq-chat-fab i { font-size: 22px; }
    .alq-chat-fab .unread-badge {
        position: absolute;
        top: -8px;
        <?php echo is_rtl() ? 'left' : 'right'; ?>: -8px;
        background: #ef4444;
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: 600;
        min-width: 18px;
        text-align: center;
        display: none;
        z-index: 1;
    }
    .alq-chat-fab .unread-badge.show { display: block; }
    .alq-chat-panel {
        position: fixed;
        bottom: 86px;
        <?php echo is_rtl() ? 'left' : 'right'; ?>: 20px;
        width: 400px;
        max-width: calc(100% - 24px);
        height: 70vh;
        max-height: 720px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.24);
        overflow: hidden;
        display: none;
        z-index: 2147483000;
    }
    .alq-chat-panel.open { display: block; }
    .alq-chat-panel__header {
        height: 48px;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 12px;
        font-weight: 600;
    }
    .alq-chat-panel__actions { display: flex; gap: 6px; }
    .alq-chat-btn { background: transparent; border: 0; color: #fff; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; }
    .alq-chat-btn:hover { background: rgba(255,255,255,0.12); }
    .alq-chat-iframe { width: 100%; height: calc(100% - 48px); border: 0; }
    @media (max-width: 575.98px) {
        .alq-chat-panel { width: calc(100% - 20px); height: 80vh; <?php echo is_rtl() ? 'left' : 'right'; ?>: 10px; bottom: 76px; }
        .alq-chat-fab { bottom: 12px; <?php echo is_rtl() ? 'left' : 'right'; ?>: 12px; }
    }
</style>

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
    <iframe id="alqChatFrame" class="alq-chat-iframe" src="../chat.php?embed=1" loading="lazy" referrerpolicy="no-referrer"></iframe>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Session timeout functionality
    let remainingTime = <?php echo $remaining_time; ?>; // Get remaining time from PHP

    function updateSessionTimer() {
        if (remainingTime <= 0) {
            // Auto logout when time expires
            window.location.href = 'logout.php';
            return;
        }

        // Show warning 5 minutes before timeout
        if (remainingTime === 300) { // 5 minutes = 300 seconds
            alert('Your session will expire in 5 minutes. Please save your work.');
        }

        // Show warning 1 minute before timeout
        if (remainingTime === 60) { // 1 minute = 60 seconds
            alert('Your session will expire in 1 minute. Please save your work.');
        }

        remainingTime--;
    }

    // Update timer every second
    setInterval(updateSessionTimer, 1000);

    // Reset timer on user activity
    let activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    activityEvents.forEach(function(event) {
        document.addEventListener(event, function() {
            // Reset remaining time to full session timeout
            remainingTime = <?php echo $session_timeout; ?>;
        }, true);
    });
});
</script>
<script>
(function() {
    var fab = document.getElementById('alqChatFab');
    var panel = document.getElementById('alqChatPanel');
    var closeBtn = document.getElementById('alqChatClose');
    var openFull = document.getElementById('alqChatOpenFull');
    var unreadBadge = document.getElementById('alqChatUnreadBadge');
    var currentUnreadCount = 0;

    if (!fab || !panel) return;

    function togglePanel(forceOpen) {
        var isOpen = panel.classList.contains('open');
        if (forceOpen === true || !isOpen) {
            panel.classList.add('open');
            panel.setAttribute('aria-hidden', 'false');
            // Mark messages as seen when opening chat
            if (currentUnreadCount > 0) {
                markMessagesAsSeen();
            }
        } else {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
        }
    }

    function updateUnreadBadge(count) {
        currentUnreadCount = count;
        if (count > 0) {
            unreadBadge.textContent = count > 99 ? '99+' : count;
            unreadBadge.classList.add('show');
        } else {
            unreadBadge.classList.remove('show');
        }
    }

    function fetchUnreadCount() {
        fetch('../api/unread_count.php', { credentials: 'include' })
            .then(response => response.json())
            .then(data => {
                if (data.total_unread !== undefined) {
                    updateUnreadBadge(data.total_unread);
                }
            })
            .catch(error => console.error('Error fetching unread count:', error));
    }

    function markMessagesAsSeen() {
        // This will be called when the chat panel is opened
        // The iframe will handle marking messages as seen
        var iframe = document.getElementById('alqChatFrame');
        if (iframe && iframe.contentWindow) {
            // Send message to iframe to mark messages as seen
            iframe.contentWindow.postMessage({ type: 'markAsSeen' }, '*');
        }
    }

    // Initial fetch
    fetchUnreadCount();

    // Poll for updates every 30 seconds
    setInterval(fetchUnreadCount, 30000);

    // Listen for messages from the chat iframe
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'unreadCountUpdate') {
            updateUnreadBadge(event.data.count);
        }
    });

    fab.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); togglePanel(); });
    closeBtn && closeBtn.addEventListener('click', function(e) { e.preventDefault(); togglePanel(false); });
    openFull && openFull.addEventListener('click', function(e) { e.preventDefault(); window.location.href = '../chat.php'; });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && panel.classList.contains('open')) togglePanel(false); });
})();
</script>
<?php endif; ?>
