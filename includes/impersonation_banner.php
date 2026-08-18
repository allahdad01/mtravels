<?php
/**
 * impersonation_banner.php
 * Fixed top notice bar shown while a super admin is acting as another user.
 * Include right after <body> in the shared headers.
 */
if (!empty($_SESSION['impersonating']) && !empty($_SESSION['impersonator_user_id'])) {
    $imp_target_name = htmlspecialchars($_SESSION['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
    $imp_target_role = htmlspecialchars($_SESSION['role'] ?? 'user', ENT_QUOTES, 'UTF-8');
    $imp_admin_name = htmlspecialchars($_SESSION['impersonator_name'] ?? 'Super Admin', ENT_QUOTES, 'UTF-8');
    $imp_script_path = dirname($_SERVER['SCRIPT_NAME']);
    $imp_base = rtrim(dirname($imp_script_path), '/');
    $imp_exit_url = htmlspecialchars($imp_base . '/super_admin/stop_impersonation.php', ENT_QUOTES, 'UTF-8');
    ?>
<div id="impersonationBanner" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:#7c2d12;color:#fff;font-family:'Inter',system-ui,sans-serif;font-size:13px;line-height:1.4;box-shadow:0 2px 12px rgba(0,0,0,0.35);">
    <div style="max-width:1200px;margin:0 auto;padding:8px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="background:#fbbf24;color:#7c2d12;font-weight:700;padding:2px 8px;border-radius:4px;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;flex-shrink:0;">Impersonating</span>
            <span>You are acting as <strong><?= $imp_target_name ?></strong> (<?= $imp_target_role ?>) &middot; logged in by <strong><?= $imp_admin_name ?></strong></span>
        </div>
        <a href="<?= $imp_exit_url ?>" style="background:#fff;color:#7c2d12;font-weight:700;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:12px;display:inline-block;flex-shrink:0;">&larr; Return to Super Admin</a>
    </div>
</div>
<script>
(function () {
    document.body.classList.add('has-impersonation-banner');
})();
</script>
<style>
body.has-impersonation-banner .app-header { top: 48px; }
body.has-impersonation-banner .pcoded-header { top: 48px; }
</style>
    <?php
}