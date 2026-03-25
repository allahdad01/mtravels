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
        error_log("User not found: " . $_SESSION['user_id']); 
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
        WHERE ts.tenant_id = ? AND ts.status = 'active'
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

// Temporary fix: If no features found, assign default features for testing
if (empty($allowed_features)) {
    error_log("No features found, using default feature set");
    $allowed_features = [
        "ticket_bookings",
        "ticket_reservations", 
        "refunded_tickets",
        "date_change_tickets",
        "ticket_weights",
        "hotel_bookings",
        "hotel_refunds",
        "visa_applications",
        "visa_refunds",
        "visa_transactions", 
        "inter_tenant_chat",
        "umrah_bookings",
        "umrah_refunds",
        "debtors",
        "creditors",
        "sarafi",
        "salary",
        "additional_payments",
        "jv_payments",
        "manage_maktobs",
        "assets",
        "financial_statements",
        "expense_management"
    ];
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

$profilePic = !empty($user['image']) ? htmlspecialchars($user['image']) : 'default-avatar.jpg';
$imagePath = "../assets/images/client/" . $profilePic;

// Generate CSRF token if not already in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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

    <title><?= htmlspecialchars($settings['agency_name']) ?></title>
  
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
</style>

</head>
  
  <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- Mobile Floating Hamburger Button -->
    <div class="mobile-menu-float">
        <a class="mobile-menu" id="mobile-collapse" href="javascript:"><span></span></a>
    </div>

<!-- [ navigation menu ] start -->
<nav class="pcoded-navbar">
    <div class="navbar-wrapper">
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;" src="../Uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
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
            <a class="mobile-menu" id="mobile-collapse" href="javascript:"><span></span><span></span><span></span></a>
        </div>
        <div class="navbar-content scroll-div" style="padding-bottom: 100px;">
            <ul class="nav pcoded-inner-navbar">
                <li class="nav-item pcoded-menu-caption">
                    <label><?= __('navigation') ?></label>
                </li>
                <li data-username="dashboard" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
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
                <li data-username="ticket_management" class="nav-item pcoded-hasmenu <?php echo $isTicketActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Ticket Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php if (hasFeature('ticket_bookings', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>">
                            <a href="ticket.php"><?= __('book_tickets') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('refunded_tickets', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'refund_ticket.php' ? 'active' : ''; ?>">
                            <a href="refund_ticket.php"><?= __('refund_tickets') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('date_change_tickets', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'date_change.php' ? 'active' : ''; ?>">
                            <a href="date_change.php"><?= __('date_changed_tickets') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('ticket_weights', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket_weights.php' ? 'active' : ''; ?>">
                            <a href="ticket_weights.php"><?= __('ticket_weights') ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('ticket_reservations', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket_reserve.php' ? 'active' : ''; ?>">
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
                <li data-username="hotel_management" class="nav-item pcoded-hasmenu <?php echo $isHotelActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext">Hotel Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php if (hasFeature('hotel_bookings', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'hotel.php' ? 'active' : ''; ?>">
                            <a href="hotel.php">Hotel Bookings</a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('hotel_refunds', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'hotel_refund.php' ? 'active' : ''; ?>">
                            <a href="hotel_refunds.php">Hotel Refund</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('umrah_bookings', $allowed_features)): ?>
                <li data-username="umrah" class="nav-item pcoded-hasmenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['umrah.php', 'umrah_services.php', 'umrah_refunds.php', 'umrah_date_changes.php']) ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-map"></i></span>
                        <span class="pcoded-mtext"><?= __('umrah_management') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'umrah.php' ? 'active' : ''; ?>">
                            <a href="umrah.php"><?= __('umrah_bookings') ?></a>
                        </li>
    
                        <?php if (hasFeature('umrah_refunds', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'umrah_refunds.php' ? 'active' : ''; ?>">
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
                <li data-username="visa_management" class="nav-item pcoded-hasmenu <?php echo $isVisaActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-globe"></i></span>
                        <span class="pcoded-mtext">Visa Management</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php if (hasFeature('visa_applications', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'visa.php' ? 'active' : ''; ?>">
                            <a href="visa.php">Visa Bookings</a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasFeature('visa_refunds', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'visa_refunds.php' ? 'active' : ''; ?>">
                            <a href="visa_refunds.php">Visa Refund</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <li data-username="report" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>">
                    <a href="report.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
                        <span class="pcoded-mtext"><?= __('reports') ?></span>
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
</nav>
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
// Make CSRF token available to JavaScript
window.csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

document.addEventListener('DOMContentLoaded', function () {
    // ── Mobile sidebar ────────────────────────────────────────────
    var mobileFloat = document.querySelector('.mobile-menu-float');
    var mobileToggle = document.getElementById('mobile-collapse');

    function openSidebar() {
        var navbar  = document.querySelector('.pcoded-navbar');
        var overlay = document.querySelector('.mobile-menu-overlay');
        if (!navbar) return;
        navbar.classList.add('mobile-overlay', 'open');
        mobileFloat && mobileFloat.classList.add('active');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'mobile-menu-overlay';
            overlay.addEventListener('click', closeSidebar);
            document.body.appendChild(overlay);
        }
        overlay.classList.add('show');
    }

    function closeSidebar() {
        var navbar  = document.querySelector('.pcoded-navbar');
        var overlay = document.querySelector('.mobile-menu-overlay');
        if (!navbar) return;
        navbar.classList.remove('open');
        mobileFloat && mobileFloat.classList.remove('active');
        overlay && overlay.classList.remove('show');
    }

    function toggleSidebar(e) {
        e.preventDefault();
        e.stopPropagation();
        if (window.innerWidth >= 992) return; // desktop – let the theme handle it
        var navbar = document.querySelector('.pcoded-navbar');
        navbar && navbar.classList.contains('open') ? closeSidebar() : openSidebar();
    }

    mobileFloat  && mobileFloat.addEventListener('click', toggleSidebar);
    mobileToggle && mobileToggle.addEventListener('click', toggleSidebar);
});
</script>

<?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
<script>
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
</script>
<?php endif; ?>
