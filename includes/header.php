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
include '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? and tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
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
        JOIN plans p ON ts.plan_id = p.name
        WHERE ts.tenant_id = ? AND ts.status = 'active'
        ORDER BY ts.start_date DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $allowed_features = json_decode($row['features'], true) ?? [];
        
        // Debug: Log the features for troubleshooting
        error_log("Tenant ID: " . $tenant_id);
        error_log("Features JSON: " . $row['features']);
        error_log("Parsed Features: " . print_r($allowed_features, true));
    } else {
        // Debug: Log if no subscription found
        error_log("No active subscription found for tenant: " . $tenant_id);
        
        // Check if tenant exists in tenant_subscriptions
        $debug_query = "SELECT * FROM tenant_subscriptions WHERE tenant_id = ?";
        $debug_stmt = $conn->prepare($debug_query);
        $debug_stmt->bind_param('i', $tenant_id);
        $debug_stmt->execute();
        $debug_result = $debug_stmt->get_result();
        
        if ($debug_result->num_rows === 0) {
            error_log("No subscriptions found for tenant: " . $tenant_id);
        } else {
            error_log("Found subscriptions but none active for tenant: " . $tenant_id);
            while ($debug_row = $debug_result->fetch_assoc()) {
                error_log("Subscription: " . print_r($debug_row, true));
            }
        }
        $debug_stmt->close();
    }
    $stmt->close();
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
    $hasIt = in_array($feature, $allowed_features);
    // Debug: Log feature checks
    error_log("Checking feature '$feature': " . ($hasIt ? 'ALLOWED' : 'DENIED'));
    return $hasIt;
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

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;

// Debug output for development (remove in production)
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Tenant ID: " . $tenant_id . "\n";
    echo "Allowed Features: " . print_r($allowed_features, true) . "\n";
    echo "</pre>";
    exit();
}
?>



<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <?php if (is_rtl()): ?>
    <style>
        /* === CRITICAL RTL SIDEBAR FIXES === */
        /* Force right positioning for the entire sidebar */
        .pcoded-navbar {
            right: 0 !important; 
            left: auto !important; 
            direction: rtl !important;
            text-align: right !important;
        }
        
        /* Force all menu items to display icons on right and text on left */
        .pcoded-navbar li a,
        .pcoded-navbar .nav-item a,
        .pcoded-navbar .pcoded-inner-navbar li a {
            display: flex !important;
            flex-direction: row !important; /* This is key - normal direction but with right alignment */
            justify-content: flex-start !important;
            align-items: center !important;
            text-align: right !important;
            width: 100% !important;
        }
        
        /* Force icon positions to the right of text */
        .pcoded-navbar .pcoded-micon,
        .pcoded-navbar i.feather,
        .pcoded-navbar i.fas {
            float: right !important;
            margin-left: 0 !important; 
            margin-right: 12px !important; /* Icon on right side of text */
            order: 2 !important; /* Icon comes second */
        }
        
        /* Text alignment */
        .pcoded-navbar .pcoded-mtext {
            float: left !important;
            text-align: right !important;
            order: 1 !important; /* Text comes first */
        }
        
        /* Menu captions */
        .pcoded-navbar .pcoded-menu-caption {
            text-align: right !important;
            padding-right: 20px !important;
        }
        
        /* Submenu positioning */
        .pcoded-navbar .pcoded-submenu {
            padding-right: 40px !important;
            padding-left: 0 !important;
        }
        
        /* Arrow positioning for dropdown menus */
        .pcoded-navbar li.pcoded-hasmenu > a:after {
            position: absolute !important;
            left: 20px !important;
            right: auto !important;
        }
        
        /* Adjust spacing for header and main container */
        .pcoded-header {
            margin-right: 264px !important;
            margin-left: 0 !important;
        }
        .pcoded-main-container {
            margin-right: 264px !important;
            margin-left: 0 !important;
        }

        /* === RTL HEADER DROPDOWN FIXES === */
        /* Header dropdown positioning */
        .pcoded-header .dropdown .dropdown-menu {
            left: 0 !important;
            right: auto !important;
            text-align: right !important;
            transform-origin: top left !important;
        }

        /* Fix dropdown arrow indicator */
        .pcoded-header .dropdown .dropdown-menu:before {
            right: auto !important;
            left: 10px !important;
        }

        /* Dropdown items alignment */
        .pcoded-header .dropdown .dropdown-menu .dropdown-item {
            text-align: right !important;
            direction: rtl !important;
        }

        /* Profile dropdown specific fixes */
        .pcoded-header .dropdown .profile-notification {
            left: 0 !important;
            right: auto !important;
        }

        .pcoded-header .dropdown .profile-notification .pro-head {
            display: flex !important;
            flex-direction: row-reverse !important;
            text-align: right !important;
        }

        .pcoded-header .dropdown .profile-notification .pro-body li {
            text-align: right !important;
        }

        .pcoded-header .dropdown .profile-notification .pro-body li a {
            display: flex !important;
            flex-direction: row-reverse !important;
            text-align: right !important;
        }

        .pcoded-header .dropdown .profile-notification .pro-body li a i {
            margin-right: 0 !important;
            margin-left: 10px !important;
        }

        /* Language dropdown specific fixes */
        .pcoded-header .dropdown .icon.feather.icon-globe + .dropdown-menu {
            min-width: 160px !important;
        }

        /* Mobile specific dropdown fixes */
        @media (max-width: 991px) {
            .pcoded-header .dropdown .dropdown-menu {
                position: absolute !important;
                left: 0 !important;
                right: auto !important;
            }
        }

        /* Hide dropdown arrows */
        html[dir="rtl"] .pcoded-header .dropdown-toggle::after,
        body.rtl .pcoded-header .dropdown-toggle::after,
        html[dir="rtl"] .pcoded-header .dropdown .dropdown-toggle::after,
        body.rtl .pcoded-header .dropdown .dropdown-toggle::after {
            display: none !important;
        }
    </style>
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
    <script>
        // Apply RTL styles immediately and after DOM load
        (function() {
            function forceRTL() {
                // Target the sidebar
                var navbar = document.querySelector('.pcoded-navbar');
                if (!navbar) return;
                
                // Set fundamental RTL properties
                navbar.style.right = '0';
                navbar.style.left = 'auto';
                navbar.style.direction = 'rtl';
                navbar.style.textAlign = 'right';
                
                // Force direction on all menu items
                var menuItems = navbar.querySelectorAll('.nav-item a, .pcoded-inner-navbar li a');
                menuItems.forEach(function(item) {
                    // Style flex container
                    item.style.display = 'flex';
                    item.style.flexDirection = 'row'; // Normal direction, we'll use order to control placement
                    item.style.justifyContent = 'flex-start';
                    item.style.alignItems = 'center';
                    item.style.textAlign = 'right';
                    item.style.width = '100%';
                    
                    // Position icons on the right side
                    var icon = item.querySelector('.pcoded-micon, i.feather, i.fas');
                    if (icon) {
                        icon.style.float = 'right';
                        icon.style.marginLeft = '0';
                        icon.style.marginRight = '12px';
                        icon.style.order = '2'; // Icon comes second
                    }
                    
                    // Position text
                    var text = item.querySelector('.pcoded-mtext');
                    if (text) {
                        text.style.float = 'left';
                        text.style.textAlign = 'right';
                        text.style.order = '1'; // Text comes first
                    }
                });
                
                // Style submenu padding
                var submenus = navbar.querySelectorAll('.pcoded-submenu');
                submenus.forEach(function(submenu) {
                    submenu.style.paddingRight = '40px';
                    submenu.style.paddingLeft = '0';
                });

                // Fix header dropdowns
                var headerDropdowns = document.querySelectorAll('.pcoded-header .dropdown');
                headerDropdowns.forEach(function(dropdown) {
                    var menu = dropdown.querySelector('.dropdown-menu');
                    if (menu) {
                        // Set RTL positioning
                        menu.style.left = '0';
                        menu.style.right = 'auto';
                        menu.style.textAlign = 'right';
                        
                        // Fix dropdown item alignment
                        var items = menu.querySelectorAll('.dropdown-item');
                        items.forEach(function(item) {
                            item.style.textAlign = 'right';
                        });
                    }
                });
            }
            
            // Apply immediately
            forceRTL();
            
            // Also apply after DOM loaded and a short delay
            document.addEventListener('DOMContentLoaded', forceRTL);
            setTimeout(forceRTL, 500);
        })();
    </script>
    <?php endif; ?>
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
        <!-- DataTables CSS -->
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
    <!-- RTL support -->
    <?php if (is_rtl()): ?>
    <link rel="stylesheet" href="../assets/css/force-rtl.css">
    <link rel="stylesheet" href="../assets/css/rtl-reset.css">
    <link rel="stylesheet" href="../assets/css/rtl.css">
    <script src="../assets/js/rtl-extreme-fix.js"></script>
    <script src="../assets/js/rtl-fix.js"></script>
    <script src="../assets/js/rtl-header-fix.js"></script>
    <!-- Force dropdowns to work properly in RTL mode -->
    <style>
        /* Critical header dropdown fixes */
        html[dir="rtl"] .dropdown-menu.show,
        body.rtl .dropdown-menu.show {
            display: block !important;
            visibility: visible !important;
        }
        
        /* Bootstrap RTL patch for dropdowns */
        html[dir="rtl"] .dropdown-menu,
        body.rtl .dropdown-menu {
            position: absolute !important;
            float: left !important;
            text-align: right !important;
            left: 0 !important;
            right: auto !important;
        }
        
        /* Force dropdowns to be properly positioned */
        @media (min-width: 992px) {
            html[dir="rtl"] .pcoded-header .ml-auto .dropdown-menu,
            body.rtl .pcoded-header .ml-auto .dropdown-menu {
                position: absolute !important;
                left: 0 !important;
                right: auto !important;
            }
        }
    
</style>
<?php endif; ?>

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

.pcoded-header .main-search {
    border-radius: 25px !important;
    overflow: hidden !important;
    background: #f3f4f6 !important;
    position: relative !important;
}

.pcoded-header .main-search input {
    border: none !important;
    background: transparent !important;
}

.search-results-dropdown {
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    right: 0 !important;
    background: white !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
    max-height: 300px !important;
    overflow-y: auto !important;
    z-index: 1000 !important;
    margin-top: 5px !important;
}

.search-result-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 10px 15px !important;
    cursor: pointer !important;
    transition: background 0.2s ease !important;
    border-bottom: 1px solid #f3f4f6 !important;
}

.search-result-item:hover {
    background: #f8fafc !important;
}

.search-result-item:last-child {
    border-bottom: none !important;
}

.search-result-item i {
    color: #4099ff !important;
    font-size: 16px !important;
    width: 16px !important;
}

.search-result-title {
    flex: 1 !important;
    font-weight: 500 !important;
    color: #1f2937 !important;
}

.search-result-path {
    font-size: 12px !important;
    color: #6b7280 !important;
}

.search-no-results {
    padding: 20px 15px !important;
    text-align: center !important;
    color: #9ca3af !important;
    font-size: 14px !important;
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
</style>


</head>
  
  <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

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
            <a class="mobile-menu" id="mobile-collapse" href="javascript:"><span></span></a>
        </div>
        <div class="navbar-content scroll-div">
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
                <?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
                <li data-username="chat" class="nav-item pcoded-hasmenu <?php echo (basename($_SERVER['PHP_SELF']) == 'chat.php' || basename($_SERVER['PHP_SELF']) == 'send_messages.php' || basename($_SERVER['PHP_SELF']) == 'chat_settings.php' || basename($_SERVER['PHP_SELF']) == 'tenant_peering.php') ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-message-circle"></i></span>
                        <span class="pcoded-mtext"><?= __('chat') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>">
                            <a href="../chat.php">
                                <i class="feather icon-users"></i> <?= __('chat') ?>
                            </a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'send_messages.php' ? 'active' : ''; ?>">
                            <a href="send_messages.php">
                                <i class="feather icon-message-circle"></i> <?= __('send_messages') ?>
                            </a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'chat_settings.php' ? 'active' : ''; ?>">
                            <a href="chat_settings.php">
                                <i class="feather icon-settings"></i> Chat Settings
                            </a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'tenant_peering.php' ? 'active' : ''; ?>">
                            <a href="tenant_peering.php">
                                <i class="feather icon-users"></i> Tenant Peering
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Header Search Functionality -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('m-search');
                    const searchResults = document.getElementById('search-results');
                    const searchResultsList = document.getElementById('search-results-list');
                    const searchClose = document.querySelector('.search-close');
                
                    let menuItems = [];
                    let searchTimeout;
                
                    // Collect all menu items on page load
                    function collectMenuItems() {
                        menuItems = [];
                
                        // Get main navigation items
                        document.querySelectorAll('.pcoded-inner-navbar li a').forEach(link => {
                            const text = link.textContent.trim();
                            const href = link.getAttribute('href');
                            const icon = link.querySelector('i') ? link.querySelector('i').className : 'feather icon-circle';
                
                            if (text && href && href !== 'javascript:') {
                                menuItems.push({
                                    title: text,
                                    url: href,
                                    icon: icon,
                                    path: 'Main Menu'
                                });
                            }
                        });
                
                        // Get submenu items
                        document.querySelectorAll('.pcoded-submenu li a').forEach(link => {
                            const text = link.textContent.trim();
                            const href = link.getAttribute('href');
                
                            if (text && href && href !== 'javascript:') {
                                // Find parent menu item
                                let parentText = 'Menu';
                                const submenu = link.closest('.pcoded-submenu');
                                if (submenu) {
                                    const parentLink = submenu.previousElementSibling;
                                    if (parentLink && parentLink.querySelector('.pcoded-mtext')) {
                                        parentText = parentLink.querySelector('.pcoded-mtext').textContent.trim();
                                    }
                                }
                
                                menuItems.push({
                                    title: text,
                                    url: href,
                                    icon: 'feather icon-arrow-right',
                                    path: parentText
                                });
                            }
                        });
                    }
                
                    // Perform search
                    function performSearch(query) {
                        if (!query.trim()) {
                            searchResults.style.display = 'none';
                            return;
                        }
                
                        const results = menuItems.filter(item =>
                            item.title.toLowerCase().includes(query.toLowerCase()) ||
                            item.path.toLowerCase().includes(query.toLowerCase())
                        );
                
                        renderSearchResults(results);
                    }
                
                    // Render search results
                    function renderSearchResults(results) {
                        if (results.length === 0) {
                            searchResultsList.innerHTML = '<div class="search-no-results">No menu items found</div>';
                            searchResults.style.display = 'block';
                            return;
                        }
                
                        const html = results.map(result => `
                            <div class="search-result-item" data-url="${result.url}">
                                <i class="${result.icon}"></i>
                                <div>
                                    <div class="search-result-title">${highlightMatch(result.title, searchInput.value)}</div>
                                    <div class="search-result-path">${result.path}</div>
                                </div>
                            </div>
                        `).join('');
                
                        searchResultsList.innerHTML = html;
                        searchResults.style.display = 'block';
                
                        // Add click handlers
                        document.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const url = this.dataset.url;
                                if (url && url !== '#') {
                                    window.location.href = url;
                                }
                                searchResults.style.display = 'none';
                                searchInput.value = '';
                            });
                        });
                    }
                
                    // Highlight matching text
                    function highlightMatch(text, query) {
                        if (!query) return text;
                        const regex = new RegExp(`(${query})`, 'gi');
                        return text.replace(regex, '<mark>$1</mark>');
                    }
                
                    // Event listeners
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        const query = this.value.trim();
                
                        if (query.length > 0) {
                            searchTimeout = setTimeout(() => performSearch(query), 300);
                        } else {
                            searchResults.style.display = 'none';
                        }
                    });
                
                    searchInput.addEventListener('focus', function() {
                        if (this.value.trim().length > 0) {
                            performSearch(this.value.trim());
                        }
                    });
                
                    searchInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            searchResults.style.display = 'none';
                            this.blur();
                        } else if (e.key === 'Enter') {
                            // Navigate to first result
                            const firstResult = document.querySelector('.search-result-item');
                            if (firstResult) {
                                firstResult.click();
                            }
                        }
                    });
                
                    // Close search when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.main-search')) {
                            searchResults.style.display = 'none';
                        }
                    });
                
                    // Clear search
                    if (searchClose) {
                        searchClose.addEventListener('click', function() {
                            searchInput.value = '';
                            searchResults.style.display = 'none';
                            searchInput.focus();
                        });
                    }
                
                    // Initialize menu collection
                    collectMenuItems();
                
                    // Re-collect menu items when sidebar changes (for dynamic menus)
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList') {
                                collectMenuItems();
                            }
                        });
                    });
                
                    const sidebar = document.querySelector('.pcoded-inner-navbar');
                    if (sidebar) {
                        observer.observe(sidebar, { childList: true, subtree: true });
                    }
                });
                </script>
                
                <li data-username="accounts" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'accounts.php' ? 'active' : ''; ?>">
                    <a href="accounts.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-briefcase"></i></span>
                        <span class="pcoded-mtext"><?= __('accounts') ?></span>
                    </a>
                </li>
                <li data-username="subscription_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'subscription_payments.php' ? 'active' : ''; ?>">
                    <a href="subscription_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
                        <span class="pcoded-mtext"><?= __('subscription_payments') ?></span>
                    </a>
                </li>
                <?php if (hasFeature('debtors', $allowed_features)): ?>
                <li data-username="debtors" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'debtors.php' ? 'active' : ''; ?>">
                    <a href="debtors.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('debtors') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('creditors', $allowed_features)): ?>
                <li data-username="creditors" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'creditors.php' ? 'active' : ''; ?>">
                    <a href="creditors.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('creditors') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('sarafi', $allowed_features)): ?>
                <li data-username="sarafi" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sarafi.php' ? 'active' : ''; ?>">
                    <a href="sarafi.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
                        <span class="pcoded-mtext"><?= __('sarafi') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('salary', $allowed_features)): ?>
                <li data-username="salary" class="nav-item pcoded-hasmenu <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'salary') !== false) ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
                        <span class="pcoded-mtext"><?= __('salary_management') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'salary_management.php' ? 'active' : ''; ?>">
                            <a href="salary_management.php"><?= __('employee_salaries') ?></a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'salary_payment.php' ? 'active' : ''; ?>">
                            <a href="salary_payment.php"><?= __('salary_payment') ?></a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <li data-username="users" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <a href="users.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-plus"></i></span>
                        <span class="pcoded-mtext"><?= __('users') ?></span>
                    </a>
                </li>
               
                <?php 
                $bookingPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'hotel.php', 'ticket_reserve.php', 'ticket_weights.php'];
                $isBookingActive = in_array(basename($_SERVER['PHP_SELF']), $bookingPages);
                $showBookings = hasFeature('ticket_bookings', $allowed_features) || 
                                hasFeature('ticket_reservations', $allowed_features) || 
                                hasFeature('refunded_tickets', $allowed_features) || 
                                hasFeature('date_change_tickets', $allowed_features) || 
                                hasFeature('ticket_weights', $allowed_features) || 
                                hasFeature('hotel_bookings', $allowed_features);
                ?>
                <?php if ($showBookings): ?>
                <li data-username="bookings" class="nav-item pcoded-hasmenu <?php echo $isBookingActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext"><?= __('bookings') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
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
                        <li data-username="tickets" class="nav-item pcoded-hasmenu <?php echo $isTicketActive ? 'active pcoded-trigger' : ''; ?>">
                            <a href="javascript:" class="nav-link">
                                <span class="pcoded-mtext"><?= __('ticket') ?></span>
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
                        <?php if (hasFeature('hotel_bookings', $allowed_features)): ?>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'hotel.php' ? 'active' : ''; ?>">
                            <a href="hotel.php"><?= __('hotel') ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('umrah_bookings', $allowed_features)): ?>
                <li data-username="umrah" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'umrah.php' ? 'active' : ''; ?>">
                    <a href="umrah.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-map"></i></span>
                        <span class="pcoded-mtext"><?= __('umrah_management') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('visa_applications', $allowed_features)): ?>
                <li data-username="visa" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'visa.php' ? 'active' : ''; ?>">
                    <a href="visa.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-globe"></i></span>
                        <span class="pcoded-mtext"><?= __('visa') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('additional_payments', $allowed_features)): ?>
                <li data-username="additional_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'additional_payments.php' ? 'active' : ''; ?>">
                    <a href="additional_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('additional_payments') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('jv_payments', $allowed_features)): ?>
                <li data-username="jv_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'jv_payments.php' ? 'active' : ''; ?>">
                    <a href="jv_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('jv_payments') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('manage_maktobs', $allowed_features)): ?>
                <li data-username="manage_maktobs" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_maktobs.php' ? 'active' : ''; ?>">
                    <a href="manage_maktobs.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-file-alt"></i></span>
                        <span class="pcoded-mtext"><?= __('manage_letters') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasFeature('assets', $allowed_features)): ?>
                <li data-username="assets" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'assets.php' ? 'active' : ''; ?>">
                    <a href="assets.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('assets') ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <li data-username="supplier" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'supplier.php' ? 'active' : ''; ?>">
                    <a href="supplier.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('supplier') ?></span>
                    </a>
                </li>
                <li data-username="client" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'client.php' ? 'active' : ''; ?>">
                    <a href="client.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user"></i></span>
                        <span class="pcoded-mtext"><?= __('client') ?></span>
                    </a>
                </li>
               
                <li data-username="expense" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'expense_management.php' ? 'active' : ''; ?>">
                    <a href="expense_management.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
                        <span class="pcoded-mtext"><?= __('expense_management') ?></span>
                    </a>
                </li>
                <li data-username="report" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>">
                    <a href="report.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
                        <span class="pcoded-mtext"><?= __('reports') ?></span>
                    </a>
                </li>

                <li data-username="settings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <a href="settings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-settings"></i></span>
                        <span class="pcoded-mtext"><?= __('settings') ?></span>
                    </a>
                </li>
                <li data-username="2fa" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'totp.php' ? 'active' : ''; ?>">
                    <a href="../totp_setup.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-shield"></i></span>
                        <span class="pcoded-mtext"><?= __('2fa') ?></span>
                    </a>
                </li>
                <li data-username="search" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : ''; ?>">
                    <a href="search.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-search"></i></span>
                        <span class="pcoded-mtext"><?= __('search') ?></span>
                    </a>
                </li>
                <li data-username="tutorials" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tutorials.php' ? 'active' : ''; ?>">
                    <a href="tutorials.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-graduation-cap"></i></span>
                        <span class="pcoded-mtext"><?= __('tutorials') ?></span>
                    </a>
                </li>

                <li data-username="activity_log" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'activity_log.php' ? 'active' : ''; ?>">
                    <a href="activity_log.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-activity"></i></span>
                        <span class="pcoded-mtext"><?= __('activity_log') ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- [ navigation menu ] end -->


<!-- [ Header ] start -->
<header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
        <a class="mobile-menu" id="mobile-collapse1" href="javascript:"><span></span></a>
        <a href="dashboard.php" class="b-brand">
            <div class="b-bg">
                <img class="rounded-circle" style="width:40px;" src="../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
            </div>
            <span class="b-title"><?= htmlspecialchars($settings['agency_name']) ?></span>
        </a>
    </div>
    <a class="mobile-menu" id="mobile-header" href="javascript:">
        <i class="feather icon-more-horizontal"></i>
    </a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li><a href="javascript:" class="full-screen" onclick="javascript:toggleFullScreen()"><i class="feather icon-maximize"></i></a></li>
            <li class="nav-item">
                <div class="main-search">
                    <div class="input-group">
                        <input type="text" id="m-search" class="form-control" placeholder="Search menu...">
                        <a href="javascript:" class="input-group-append search-close">
                            <i class="feather icon-x input-group-text"></i>
                        </a>
                        <span class="input-group-append search-btn btn btn-primary">
                            <i class="feather icon-search input-group-text"></i>
                        </span>
                    </div>
                    <div id="search-results" class="search-results-dropdown" style="display: none;">
                        <div id="search-results-list"></div>
                    </div>
                </div>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li>
                <div class="dropdown">
                    <a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="icon feather icon-globe"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a href="../language_switcher.php?lang=en" class="dropdown-item <?= get_current_lang() == 'en' ? 'active' : '' ?>">
                            English
                        </a>
                        <a href="../language_switcher.php?lang=fa" class="dropdown-item <?= get_current_lang() == 'fa' ? 'active' : '' ?>">
                            دری
                        </a>
                        <a href="../language_switcher.php?lang=ps" class="dropdown-item <?= get_current_lang() == 'ps' ? 'active' : '' ?>">
                            پښتو
                        </a>
                    </div>
                </div>
            </li>
            <li>
                <div class="dropdown drp-user">
                    <a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="icon feather icon-settings"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right profile-notification">
                        <div class="pro-head">
                            <img src="<?= $imagePath ?>" class="img-radius" alt="User-Profile-Image">
                            <span><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest'; ?></span>
                            <span class="text-muted"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User'; ?></span>
                            <a href="logout.php" class="dud-logout" title="Logout">
                                <i class="feather icon-log-out"></i>
                            </a>
                        </div>
                        <ul class="pro-body">
                            <li>
                                <a href="javascript:void(0)" class="dropdown-item" data-toggle="modal" data-target="#profileModal">
                                    <i class="feather icon-user"></i> <?= __('profile') ?>
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0)" class="dropdown-item" data-toggle="modal" data-target="#settingsModal">
                                    <i class="feather icon-settings"></i> <?= __('settings') ?>
                                </a>
                            </li>
                            <li>
                                <a href="logout.php" class="dropdown-item">
                                    <i class="feather icon-log-out"></i> <?= __('logout') ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</header>
<!-- [ Header ] end -->

<?php if (is_rtl()): ?>
<!-- Critical RTL Mobile Sidebar CSS -->
<style>
/* Mobile overrides */
@media (max-width: 991px) {
    /* Basic positioning */
    html[dir="rtl"] .pcoded-navbar,
    body.rtl .pcoded-navbar {
        right: -100% !important;
        left: auto !important;
        transform: translateX(0) !important;
        transition: all 0.3s ease-in-out !important;
        position: fixed !important;
        height: 100% !important;
        z-index: 1030 !important;
        display: none !important;
    }

    /* When menu is open */
    html[dir="rtl"] .pcoded-navbar.mob-open,
    body.rtl .pcoded-navbar.mob-open {
        right: 0 !important;
        left: auto !important;
        display: block !important;
    }

    /* Header and container */
    html[dir="rtl"] .pcoded-header,
    body.rtl .pcoded-header,
    html[dir="rtl"] .pcoded-main-container,
    body.rtl .pcoded-main-container {
        margin-right: 0 !important;
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    /* Overlay */
    .mobile-menu-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        display: none;
    }
    
    /* Show overlay */
    html[dir="rtl"] .pcoded-navbar.mob-open + .mobile-menu-overlay,
    body.rtl .pcoded-navbar.mob-open + .mobile-menu-overlay {
        display: block !important;
    }
}
</style>
<script>
// Fix mobile sidebar for RTL
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle functionality
    var mobileToggles = document.querySelectorAll('.mobile-menu, #mobile-collapse1, #mobile-header');
    mobileToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var sidebar = document.querySelector('.pcoded-navbar');
            if (!sidebar) return;
            
            if (sidebar.classList.contains('mob-open')) {
                sidebar.classList.remove('mob-open');
                sidebar.style.right = '-100%';
                sidebar.style.display = 'none';
            } else {
                sidebar.classList.add('mob-open');
                sidebar.style.right = '0';
                sidebar.style.display = 'block';
            }
            
            // Manage overlay
            var overlay = document.querySelector('.mobile-menu-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'mobile-menu-overlay';
                document.body.appendChild(overlay);
                
                overlay.addEventListener('click', function() {
                    if (sidebar) {
                        sidebar.classList.remove('mob-open');
                        sidebar.style.right = '-100%';
                        sidebar.style.display = 'none';
                        overlay.style.display = 'none';
                    }
                });
            }
            
            overlay.style.display = sidebar.classList.contains('mob-open') ? 'block' : 'none';
        });
    });
});
</script>

<!-- RTL Mobile Sidebar Fix Script -->
<script>
// Fix mobile sidebar toggle in RTL mode
document.addEventListener('DOMContentLoaded', function() {
    if (document.documentElement.dir === 'rtl' || document.body.classList.contains('rtl')) {
        console.log('RTL Mobile sidebar fix loaded');
        
        // Get all toggle buttons
        var toggleButtons = document.querySelectorAll('.mobile-menu, #mobile-collapse1, #mobile-collapse, #mobile-header');
        
        // Replace each button with a clone to remove existing event listeners
        toggleButtons.forEach(function(button) {
            var newButton = button.cloneNode(true);
            if (button.parentNode) {
                button.parentNode.replaceChild(newButton, button);
            }
            
            // Add our custom event listener
            newButton.addEventListener('click', function(e) {
                console.log('Mobile toggle clicked in RTL mode');
                e.preventDefault();
                e.stopPropagation();
                
                var sidebar = document.querySelector('.pcoded-navbar');
                if (!sidebar) return;
                
                // Toggle sidebar
                if (sidebar.classList.contains('mob-open')) {
                    // Close sidebar
                    sidebar.classList.remove('mob-open');
                    sidebar.style.right = '-100%';
                    sidebar.style.display = 'none';
                } else {
                    // Open sidebar
                    sidebar.classList.add('mob-open');
                    sidebar.style.right = '0';
                    sidebar.style.display = 'block';
                }
                
                // Handle overlay
                var overlay = document.querySelector('.mobile-menu-overlay');
                if (!overlay) {
                    // Create overlay if it doesn't exist
                    overlay = document.createElement('div');
                    overlay.className = 'mobile-menu-overlay';
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.right = '0';
                    overlay.style.bottom = '0';
                    overlay.style.background = 'rgba(0,0,0,0.5)';
                    overlay.style.zIndex = '999';
                    
                    // Close sidebar when overlay is clicked
                    overlay.addEventListener('click', function() {
                        if (sidebar) {
                            sidebar.classList.remove('mob-open');
                            sidebar.style.right = '-100%';
                            sidebar.style.display = 'none';
                        }
                        this.style.display = 'none';
                    });
                    
                    document.body.appendChild(overlay);
                }
                
                // Show/hide overlay based on sidebar state
                overlay.style.display = sidebar.classList.contains('mob-open') ? 'block' : 'none';
            });
        });
        
        // Set initial sidebar state
        var sidebar = document.querySelector('.pcoded-navbar');
        if (sidebar && window.innerWidth <= 991) {
            if (!sidebar.classList.contains('mob-open')) {
                sidebar.style.right = '-100%';
                sidebar.style.left = 'auto';
            }
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            var sidebar = document.querySelector('.pcoded-navbar');
            if (!sidebar) return;
            
            if (window.innerWidth <= 991) {
                // Mobile view
                if (!sidebar.classList.contains('mob-open')) {
                    sidebar.style.right = '-100%';
                    sidebar.style.display = 'none';
                }
            } else {
                // Desktop view
                sidebar.style.right = '0';
                sidebar.style.display = 'block';
            }
        });
        
        // Fix dropdown menus in header for RTL
        console.log('Fixing header dropdowns for RTL');
        
        // Apply RTL fixes to header dropdown menus
        var headerDropdowns = document.querySelectorAll('.pcoded-header .dropdown');
        headerDropdowns.forEach(function(dropdown) {
            // Fix dropdown-menu positioning
            var menu = dropdown.querySelector('.dropdown-menu');
            if (menu) {
                menu.style.right = 'auto';
                menu.style.left = '0';
                menu.style.textAlign = 'right';
                
                // Set proper dropdown position when toggled
                var toggle = dropdown.querySelector('.dropdown-toggle');
                if (toggle) {
                    toggle.addEventListener('click', function() {
                        setTimeout(function() {
                            if (menu.classList.contains('show')) {
                                // Get the toggle button's position
                                var toggleRect = toggle.getBoundingClientRect();
                                
                                // Set menu position to match toggle width
                                menu.style.left = '0';
                                menu.style.right = 'auto';
                                
                                // Ensure menu stays within screen bounds
                                var menuRect = menu.getBoundingClientRect();
                                if (menuRect.left < 0) {
                                    menu.style.left = '0';
                                }
                            }
                        }, 0);
                    });
                }
                
                // Fix dropdown items alignment
                var items = menu.querySelectorAll('.dropdown-item');
                items.forEach(function(item) {
                    item.style.textAlign = 'right';
                    item.style.direction = 'rtl';
                });
            }
        });
    }
});
</script>

<!-- Header RTL Fix for Dropdowns -->
<style>
/* RTL header dropdown fixes */
html[dir="rtl"] .pcoded-header .dropdown .dropdown-menu,
body.rtl .pcoded-header .dropdown .dropdown-menu {
    text-align: right !important;
    left: 0 !important;
    right: auto !important;
    transform-origin: top left !important;
}

html[dir="rtl"] .pcoded-header .dropdown .dropdown-menu:before,
body.rtl .pcoded-header .dropdown .dropdown-menu:before {
    left: 10px !important;
    right: auto !important;
}

html[dir="rtl"] .dropdown-menu-right,
body.rtl .dropdown-menu-right {
    left: 0 !important;
    right: auto !important;
}

html[dir="rtl"] .pcoded-header .dropdown .dropdown-menu .dropdown-item,
body.rtl .pcoded-header .dropdown .dropdown-menu .dropdown-item {
    text-align: right !important;
    direction: rtl !important;
}

/* Fix profile dropdown */
html[dir="rtl"] .pcoded-header .dropdown .profile-notification,
body.rtl .pcoded-header .dropdown .profile-notification {
    width: 290px !important;
    left: 0 !important;
    right: auto !important;
}

html[dir="rtl"] .pcoded-header .dropdown .profile-notification .pro-head,
body.rtl .pcoded-header .dropdown .profile-notification .pro-head {
    display: flex !important;
    flex-direction: row-reverse !important;
    text-align: right !important;
}

html[dir="rtl"] .pcoded-header .dropdown .profile-notification .pro-body li,
body.rtl .pcoded-header .dropdown .profile-notification .pro-body li {
    text-align: right !important;
}

html[dir="rtl"] .pcoded-header .dropdown .profile-notification .pro-body li a,
body.rtl .pcoded-header .dropdown .profile-notification .pro-body li a {
    display: flex !important;
    align-items: center !important;
    flex-direction: row-reverse !important;
    text-align: right !important;
}

html[dir="rtl"] .pcoded-header .dropdown .profile-notification .pro-body li a i,
body.rtl .pcoded-header .dropdown .profile-notification .pro-body li a i {
    margin-right: 0 !important;
    margin-left: 10px !important;
}

/* Prevent dropdowns from going offscreen */
@media (min-width: 992px) {
    html[dir="rtl"] .navbar-nav .dropdown-menu,
    body.rtl .navbar-nav .dropdown-menu {
        position: absolute !important;
    }
}

/* RTL language dropdown specific fixes */
html[dir="rtl"] .icon.feather.icon-globe + .dropdown-menu,
body.rtl .icon.feather.icon-globe + .dropdown-menu {
    min-width: 160px !important;
}
</style>
<?php endif; ?>

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
