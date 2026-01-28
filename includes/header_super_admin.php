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

// Include language system
require_once('../includes/language_helpers.php');
$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Database connection
require_once('../includes/db.php');

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
}


// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings");
    $settingStmt->execute();
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = [
        'platform_name' => 'Construct360',
        'platform_description' => 'Comprehensive Construction Management Platform',
        'platform_logo' => 'public/uploads/logos/default-logo.png',
        'platform_favicon' => 'public/uploads/logos/default-favicon.ico'
    ];
}

// Get the user ID from the session
$user_id = $_SESSION["user_id"];

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;
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
    <title><?= htmlspecialchars($settings['platform_name']) ?></title>
  
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />


    <!-- Favicon icon -->
    <link rel="icon" href="../assets/images/log.png" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">
        <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- Favicon -->
    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" type="image/x-icon">

    <!-- Page-specific CSS -->
    <?php if (basename($_SERVER['PHP_SELF']) === 'manage_plans.php'): ?>
    <link rel="stylesheet" href="../css/super_admin/manage_plans.css">
    <?php endif; ?>

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

           html[dir="ltr"] & {
               right: 10px;
           }

           html[dir="ltr"] .alq-chat-fab {
               right: 12px;
           }

           html[dir="rtl"] & {
               left: 10px;
               right: auto;
           }

           html[dir="rtl"] .alq-chat-fab {
               left: 12px;
               right: auto;
           }
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

<!-- [ navigation menu ] start -->
<nav class="pcoded-navbar">
    <div class="navbar-wrapper">
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;" src="../uploads/logo/<?= htmlspecialchars($settings['platform_logo']) ?>" alt="activity-user">
                </div>
                <span class="b-title"><?= htmlspecialchars($settings['platform_name']) ?></span>
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
                    <label><?= __('super_admin') ?></label>
                </li>
                <li data-username="manage_tenants" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_tenants.php' ? 'active' : ''; ?>">
                    <a href="manage_tenants.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
                        <span class="pcoded-mtext"><?= __('manage_tenants') ?></span>
                    </a>
                </li>
                <li data-username="manage_plans" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_plans.php' ? 'active' : ''; ?>">
                    <a href="manage_plans.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-package"></i></span>
                        <span class="pcoded-mtext"><?= __('manage_plans') ?></span>
                    </a>
                </li>
                <li data-username="manage_subscriptions" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_subscriptions.php' ? 'active' : ''; ?>">
                    <a href="manage_subscriptions.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
                        <span class="pcoded-mtext"><?= __('manage_subscriptions') ?></span>
                    </a>
                </li>
                <li data-username="manage_branch_addons" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_branch_addons.php' ? 'active' : ''; ?>">
                    <a href="manage_branch_addons.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-gift"></i></span>
                        <span class="pcoded-mtext"><?= __('manage_branch_addons') ?></span>
                    </a>
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
                <li data-username="manage_user_addons" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_user_addons.php' ? 'active' : ''; ?>">
                    <a href="manage_user_addons.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-user-plus"></i></span>
                        <span class="pcoded-mtext">User Addons</span>
                    </a>
                </li>
                <li data-username="subscription_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'subscription_payments.php' ? 'active' : ''; ?>">
                    <a href="subscription_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
                        <span class="pcoded-mtext">Subscription Payments</span>
                    </a>
                </li>
                <li data-username="support_tickets" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'support_tickets_manage.php' || basename($_SERVER['PHP_SELF']) == 'support_ticket_manage.php' ? 'active' : ''; ?>">
                    <a href="support_tickets_manage.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-headphones"></i></span>
                        <span class="pcoded-mtext">Support Tickets</span>
                    </a>
                </li>
                <li data-username="expense_management" class="nav-item pcoded-hasmenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['system_expenses.php', 'system_expense_categories.php', 'system_revenue.php', 'profit_loss_dashboard.php']) ? 'active' : ''; ?>">
                    <a href="javascript:void(0);" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
                        <span class="pcoded-mtext"><?= __('expense_management') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'system_expenses.php' ? 'active' : ''; ?>">
                            <a href="system_expenses.php" class="nav-link">
                                <span class="pcoded-mtext"><?= __('system_expenses') ?></span>
                            </a>
                        </li>
                        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profit_loss_dashboard.php' ? 'active' : ''; ?>">
                            <a href="profit_loss_dashboard.php" class="nav-link">
                                <span class="pcoded-mtext"><?= __('profit_loss_dashboard') ?></span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li data-username="manage_demo_requests" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_demo_requests.php' ? 'active' : ''; ?>">
                    <a href="manage_demo_requests.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Demo Requests</span>
                    </a>
                </li>
                <li data-username="platform_settings" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'platform_settings.php' ? 'active' : ''; ?>">
                    <a href="platform_settings.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-settings"></i></span>
                        <span class="pcoded-mtext"><?= __('platform_settings') ?></span>
                    </a>
                </li>
             
                <li data-username="audit_logs" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
                    <a href="audit_logs.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-activity"></i></span>
                        <span class="pcoded-mtext"><?= __('audit_logs') ?></span>
                    </a>
                </li>
                <li data-username="manage_users" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                    <a href="manage_users.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-user-check"></i></span>
                        <span class="pcoded-mtext"><?= __('manage_users') ?></span>
                    </a>
                </li>
                <li data-username="manage_testimonials" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_testimonials.php' ? 'active' : ''; ?>">
                    <a href="manage_testimonials.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-star"></i></span>
                        <span class="pcoded-mtext">Manage Testimonials</span>
                    </a>
                </li>
                <li data-username="manage_blog_posts" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_blog_posts.php' ? 'active' : ''; ?>">
                    <a href="manage_blog_posts.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-edit"></i></span>
                        <span class="pcoded-mtext">Manage Posts</span>
                    </a>
                </li>
                <li data-username="backup_management" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'backup_management.php' ? 'active' : ''; ?>">
                    <a href="backup_management.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-save"></i></span>
                        <span class="pcoded-mtext">Manage Backup</span>
                    </a>
                </li>
                <li data-username="ssl_monitoring" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ssl_monitoring.php' ? 'active' : ''; ?>">
                    <a href="ssl_monitoring.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-shield-alt"></i></span>
                        <span class="pcoded-mtext">SSL Monitoring</span>
                    </a>
                </li>
                <li data-username="support_tickets_admin" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'support_tickets_list.php' || basename($_SERVER['PHP_SELF']) == 'support_ticket_view.php' ? 'active' : ''; ?>">
                    <a href="support_tickets_list.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-help-circle"></i></span>
                        <span class="pcoded-mtext">Support Tickets</span>
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
                <img class="rounded-circle" style="width:40px;" src="../uploads/logo/<?= htmlspecialchars($settings['platform_logo']) ?>" alt="activity-user">
            </div>
            <span class="b-title"><?= htmlspecialchars($settings['platform_name']) ?></span>
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
                        <input type="text" id="m-search" class="form-control" placeholder="Search . . .">
                        <a href="javascript:" class="input-group-append search-close">
                            <i class="feather icon-x input-group-text"></i>
                        </a>
                        <span class="input-group-append search-btn btn btn-primary">
                            <i class="feather icon-search input-group-text"></i>
                        </span>
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
                                <a href="../logout.php" class="dropdown-item">
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