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
    error_log("Database Error: " . $e->getMessage());
    $user = [];
}

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings");
    $settingStmt->execute();
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
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

    <!-- Header / sidebar / RTL styles -->
    <link rel="stylesheet" href="../assets/css/header-styles.css">
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

            <!-- Language switcher -->
            <div class="language-selector" style="padding:5px 15px;text-align:center;">
                <select onchange="window.location.href='../language_switcher.php?lang='+this.value"
                        style="background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:4px;padding:2px 5px;font-size:11px;cursor:pointer;">
                    <option value="en" <?= get_current_lang() === 'en' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">EN</option>
                    <option value="fa" <?= get_current_lang() === 'fa' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">دری</option>
                    <option value="ps" <?= get_current_lang() === 'ps' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">پښتو</option>
                </select>
            </div>

            <a class="mobile-menu" id="mobile-collapse" href="javascript:"><span></span><span></span><span></span></a>
        </div>
        <!-- /brand -->

        <!-- Sidebar menu -->
        <div class="navbar-content scroll-div" style="padding-bottom:100px;">
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

                <li data-username="manage_sales_agents" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_sales_agents.php' ? 'active' : ''; ?>">
                    <a href="manage_sales_agents.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-briefcase"></i></span>
                        <span class="pcoded-mtext">Sales Agents</span>
                    </a>
                </li>

                <li data-username="manage_salary_payments" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_salary_payments.php' ? 'active' : ''; ?>">
                    <a href="manage_salary_payments.php" class="nav-link">
                        <span class="pcoded-micon"><i class="fas fa-money-bill-wave"></i></span>
                        <span class="pcoded-mtext">Salary Payments</span>
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

                <li data-username="file_browser" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'file_browser.php' ? 'active' : ''; ?>">
                    <a href="file_browser.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-folder"></i></span>
                        <span class="pcoded-mtext">File Browser</span>
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
                    <a href="javascript:void(0)" class="nav-link"
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
</nav>
<!-- [ navigation menu ] end -->

<!-- ── Floating Tasks Widget ──────────────────────────────────── -->
<?php include_once __DIR__ . '/floating_tasks.php'; ?>

<!-- ── Scripts ────────────────────────────────────────────────── -->
<script>
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

    // ── Session timeout ───────────────────────────────────────────
    var remainingTime     = <?= (int) $remaining_time ?>;
    var SESSION_TIMEOUT   = <?= (int) $session_timeout ?>;
    var lastActivityTime  = Date.now();
    var warningShown5Min  = false;
    var warningShown1Min  = false;
    var warningTimeout    = null; // non-blocking toast placeholder

    function showSessionWarning(message) {
        // Use a non-blocking toast/banner instead of alert()
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

    // Re-validate with server whenever the tab becomes visible after a pause
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            var away = (Date.now() - lastActivityTime) / 1000;
            if (away > 30) checkServerSession();
            lastActivityTime = Date.now();
        }
    });

    // Countdown tick
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

    // Debounced activity reset (fires at most once per 10 s to avoid flooding)
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
