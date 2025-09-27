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
include '../includes/conn.php';

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log the error
        error_log("User not found: " . $_SESSION['user_id']);
        
        // For debugging
        echo "<!-- Debug: User ID = " . $_SESSION['user_id'] . " -->";
        echo "<!-- Debug: SQL = SELECT * FROM users WHERE id = " . $_SESSION['user_id'] . " -->";
        
        // Redirect to login if user not found
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // For debugging - remove in production
    echo "<!-- Debug: User Data = " . json_encode($user) . " -->";

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // For debugging
    echo "<!-- Debug: Database Error = " . $e->getMessage() . " -->";
    
    $user = null;
}

// Verify the data is being fetched correctly
if ($user) {
    // Log successful fetch
    error_log("User data fetched successfully for ID: " . $_SESSION['user_id']);
} else {
    // Log fetch failure
    error_log("Failed to fetch user data for ID: " . $_SESSION['user_id']);
}

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

// Get the user ID from the session
$user_id = $_SESSION["user_id"];

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;
?>



<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <title><?= htmlspecialchars($settings['agency_name']) ?></title>
  
    <!-- Meta -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="<?= htmlspecialchars($settings['description'] ?? '') ?>" />
    <meta name="keywords" content="<?= htmlspecialchars($settings['keywords'] ?? '') ?>"/>
    <meta name="author" content="<?= htmlspecialchars($settings['author'] ?? 'CodedThemes') ?>"/>

    <!-- Favicon icon -->
    <link rel="icon" href="../assets/images/log.png" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">
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
                    <img class="rounded-circle" style="width:40px;" src="../uploads/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
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
                      
                <?php 
                $bookingPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'hotel.php', 'ticket_reserve.php', 'ticket_weights.php'];
                $isBookingActive = in_array(basename($_SERVER['PHP_SELF']), $bookingPages);
                ?>
                <li data-username="bookings" class="nav-item pcoded-hasmenu <?php echo $isBookingActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext"><?= __('bookings') ?></span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php 
                        $ticketPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'ticket_reserve.php', 'ticket_weights.php'];
                        $isTicketActive = in_array(basename($_SERVER['PHP_SELF']), $ticketPages);
                        ?>
                        <li data-username="tickets" class="nav-item pcoded-hasmenu <?php echo $isTicketActive ? 'active pcoded-trigger' : ''; ?>">
                            <a href="javascript:" class="nav-link">
                                <span class="pcoded-mtext"><?= __('ticket') ?></span>
                            </a>
                            <ul class="pcoded-submenu">
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>">
                                    <a href="ticket.php" class=""><?= __('book_tickets') ?></a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'refund_ticket.php' ? 'active' : ''; ?>">
                                    <a href="refund_ticket.php" class=""><?= __('refund_tickets') ?></a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'date_change.php' ? 'active' : ''; ?>">
                                    <a href="date_change.php" class=""><?= __('date_changed_tickets') ?></a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket_reserve.php' ? 'active' : ''; ?>">
                                    <a href="ticket_reserve.php" class=""><?= __('ticket_reservations') ?></a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket_weights.php' ? 'active' : ''; ?>">
                                    <a href="ticket_weights.php" class=""><?= __('ticket_weights') ?></a>
                                </li>
                            </ul>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'hotel.php' ? 'active' : ''; ?>">
                                <a href="hotel.php" class=""><?= __('hotel') ?></a>
                        </li>
                    </ul>
                </li>
                <li data-username="umrah" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'umrah.php' ? 'active' : ''; ?>">
                    <a href="umrah.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-map"></i></span>
                        <span class="pcoded-mtext"><?= __('umrah_management') ?></span>
                    </a>
                </li>
                <li data-username="visa" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'visa.php' ? 'active' : ''; ?>">
                    <a href="visa.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-globe"></i></span>
                        <span class="pcoded-mtext"><?= __('visa') ?></span>
                    </a>
                </li>
               
                <li data-username="report" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>">
                    <a href="report.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
                        <span class="pcoded-mtext"><?= __('reports') ?></span>
                    </a>
                </li>
               
                <li data-username="2fa" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'totp.php' ? 'active' : ''; ?>">
                    <a href="../totp_setup.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-shield"></i></span>
                        <span class="pcoded-mtext"><?= __('2fa') ?></span>
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
                <img class="rounded-circle" style="width:40px;" src="../uploads/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
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