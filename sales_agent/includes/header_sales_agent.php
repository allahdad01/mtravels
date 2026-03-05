<?php
/**
 * header_sales_agent.php
 * Sales Agent header / navigation partial.
 *
 * Usage (at the top of every sales agent protected page):
 *   require_once('header_sales_agent.php');
 *
 * This file:
 *   1. Bootstraps auth, DB, features, settings via auth_check_sales_agent.php
 *   2. Verifies sales_agent role and linked sales_agent record
 *   3. Outputs the <html>, <head>, pre-loader, mobile button, and sidebar nav
 *   4. Renders the floating chat widget if the feature is enabled
 *   5. Includes the floating tasks widget
 *   6. Handles session timeout with warnings
 */

require_once(__DIR__ . '/../../includes/auth_check_sales_agent.php');
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= h($settings['agency_name'] ?? 'Sales Agent') ?> - Sales Portal</title>

    <!-- Favicon -->
    <link rel="icon" href="../uploads/logo/<?= h($settings['logo'] ?? '') ?>" type="image/x-icon">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Header / sidebar / RTL styles (external file – browser-cached) -->
    <link rel="stylesheet" href="../assets/css/header-styles.css">

    <!-- Sales Agent specific styles (external file) -->
    <link rel="stylesheet" href="../assets/css/sales-agent-styles.css">
</head>

<!-- [ Pre-loader ] start -->
<div class="loader-bg">
    <div class="loader-track">
        <div class="loader-fill"></div>
    </div>
</div>
<!-- [ Pre-loader ] End -->

<body>

<!-- Mobile Floating Hamburger Button -->
<div class="mobile-menu-float">
    <a class="mobile-menu" id="mobile-collapse" href="javascript:"><span></span></a>
</div>

<!-- [ navigation menu ] start -->
<nav class="pcoded-navbar sales-agent-navbar">
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
        <div class="navbar-content scroll-div" style="padding-top:20px;padding-bottom:100px;">
            <ul class="nav pcoded-inner-navbar">

                <?php include __DIR__ . '/nav_items_sales_agent.php'; ?>

            </ul>
        </div>
        <!-- /sidebar menu -->

        <!-- Sticky user profile strip at the bottom of the sidebar -->
        <div class="navbar-brand user-profile-section"
             style="position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1);background:#4099ff;z-index:10;">
            <div style="padding:8px 15px;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                    <a href="profile.php" style="text-decoration:none;flex-shrink:0;">
                        <img class="rounded-circle"
                             style="width:28px;height:28px;cursor:pointer;transition:opacity 0.3s ease;"
                             onmouseover="this.style.opacity='0.8'"
                             onmouseout="this.style.opacity='1'"
                             src="<?= $imagePath ?>"
                             alt="user-avatar">
                    </a>
                    <div style="flex:1;min-width:0;overflow:hidden;">
                        <div style="color:#fff;font-size:11px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">
                            <?= h($user['name'] ?? 'Sales Agent') ?>
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
                    <a href="../logout.php" class="nav-link"
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
<?php include_once '../includes/floating_tasks.php'; ?>

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
        // Replace the body of this function with your preferred toast library call.
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
