<?php
/**
 * Super Admin: Manage Communication Add-ons
 * Approve/reject pending WhatsApp/SMTP addon requests and control active addons.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';
require_once '../includes/CommunicationAddonManager.php';

$addon_manager = new CommunicationAddonManager($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        $user_id = intval($_SESSION['user_id']);
        if ($_POST['action'] === 'approve') {
            $request_id = intval($_POST['request_id'] ?? 0);
            $approval_notes = trim($_POST['approval_notes'] ?? '');
            $result = $addon_manager->approveRequest($request_id, $user_id, $approval_notes);
            if (!empty($result['success'])) {
                $success = 'Communication add-on approved.';
            } else {
                $error = $result['message'] ?? 'Approval failed.';
            }
        } elseif ($_POST['action'] === 'reject') {
            $request_id = intval($_POST['request_id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? '');
            $result = $addon_manager->rejectRequest($request_id, $user_id, $reason);
            if (!empty($result['success'])) {
                $success = 'Communication add-on request rejected.';
            } else {
                $error = $result['message'] ?? 'Reject failed.';
            }
        } elseif ($_POST['action'] === 'suspend') {
            $addon_id = intval($_POST['addon_id'] ?? 0);
            $result = $addon_manager->suspendAddon($addon_id);
            if (!empty($result['success'])) {
                $success = 'Communication add-on suspended.';
            } else {
                $error = $result['message'] ?? 'Suspend failed.';
            }
        } elseif ($_POST['action'] === 'reactivate') {
            $addon_id = intval($_POST['addon_id'] ?? 0);
            $result = $addon_manager->reactivateAddon($addon_id);
            if (!empty($result['success'])) {
                $success = 'Communication add-on reactivated.';
            } else {
                $error = $result['message'] ?? 'Reactivation failed.';
            }
        }
    }
}

$pending_requests = $addon_manager->getPendingAddonRequests();
$all_addons = $addon_manager->getAllAddons();

include '../includes/header_super_admin.php';
?>

<style>
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root { --muted: #999; --red: #ef4444; --amber: #f59e0b; --blue: #4099ff; --grad-start: #4099ff; --grad-end: #2ed8b6; --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); --radius: 10px; }

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card { background: var(--grad) !important; color: #fff; border: none !important; margin-bottom: 24px; padding: 22px 28px !important; box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px; position: relative; overflow: hidden; }
.page-header.card::after { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%); pointer-events: none; }
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; border-radius: var(--radius); border: 1px solid #e0e0e0; margin-bottom: 16px; }
.sa-alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.sa-alert-danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.sa-alert-icon { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sa-alert-icon svg { width: 20px; height: 20px; }
.sa-alert-content { flex: 1; align-self: center; }
.sa-alert-close { flex-shrink: 0; background: none; border: none; cursor: pointer; color: inherit; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
.sa-alert-close:hover { opacity: 0.7; }

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn { padding: 0.6rem 1.2rem; border-radius: 8px; border: none; font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; }
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(64,153,255,0.3); color: #fff; }
.sa-btn-success { background: #10b981; color: white; }
.sa-btn-success:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); color: #fff; }
.sa-btn-danger { background: #fee2e2; color: var(--red); border: 1px solid #fecaca; }
.sa-btn-danger:hover { background: #fecaca; color: var(--red); }
.sa-btn-warning { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
.sa-btn-warning:hover { background: #fde68a; color: #d97706; }
.sa-btn-ghost { background: #f0f0f0; color: #333; border: 1px solid #e0e0e0; }
.sa-btn-ghost:hover { background: #e8e8e8; border-color: #d0d0d0; }
.sa-btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }

/* ─── DATA TABLE ──────────────────────────────────────────── */
.sa-table-wrap { background: white; border: 1px solid #e0e0e0; border-radius: 10px; overflow-x: auto; margin-bottom: 20px; }
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table thead th { text-align: left; padding: 14px 16px; font-size: 0.65rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.06em; background: #fafafa; border-bottom: 1px solid #e0e0e0; white-space: nowrap; }
.sa-table tbody tr { transition: background 0.15s; }
.sa-table tbody tr:hover { background: #f8faff; }
.sa-table tbody td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.sa-table tbody tr:last-child td { border-bottom: none; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-icon-btn { width: 32px; height: 32px; border: 1px solid #e0e0e0; border-radius: 8px; background: white; color: #999; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; vertical-align: middle; margin-left: 4px; }
.sa-icon-btn:hover { background: #f5f5f5; border-color: #ccc; color: #333; }
.sa-icon-btn-success { color: #10b981; border-color: rgba(16,185,129,0.2); }
.sa-icon-btn-success:hover { background: rgba(16,185,129,0.1); border-color: #10b981; }
.sa-icon-btn-danger { color: #ef4444; border-color: rgba(239,68,68,0.2); }
.sa-icon-btn-danger:hover { background: rgba(239,68,68,0.1); border-color: #ef4444; }
.sa-icon-btn-warning { color: #f59e0b; border-color: rgba(245,158,11,0.2); }
.sa-icon-btn-warning:hover { background: rgba(245,158,11,0.1); border-color: #f59e0b; }

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.sa-section-header h5 { font-size: 1rem; font-weight: 700; margin: 0; color: #333; }

/* ─── EMPTY STATE ─────────────────────────────────────────── */
.sa-empty { text-align: center; padding: 36px 20px; background: white; border: 1px solid #e0e0e0; border-radius: 10px; color: #ccc; margin-bottom: 20px; }
.sa-empty-title { font-weight: 600; color: #999; margin-top: 8px; font-size: 0.95rem; }

/* ─── PILLS ───────────────────────────────────────────────── */
.pill { font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; display: inline-block; }
.pill-green { background: rgba(16,185,129,0.12); color: #10b981; }
.pill-amber { background: rgba(245,158,11,0.12); color: #f59e0b; }
.pill-gray { background: #f5f5f5; color: #999; }

/* ─── MODALS ──────────────────────────────────────────────── */
.sa-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
.sa-modal-wrap { background: white; border-radius: 14px; width: 480px; max-width: 94vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.sa-modal-header { display: flex; align-items: center; gap: 12px; padding: 20px 24px 0; }
.sa-modal-header h3 { flex: 1; font-size: 1.1rem; font-weight: 700; margin: 0; color: #333; }
.sa-modal-close { width: 32px; height: 32px; border: none; background: #f5f5f5; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #999; transition: all 0.2s; }
.sa-modal-close:hover { background: #e0e0e0; color: #333; }
.sa-modal-body { padding: 16px 24px 0; }
.sa-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 24px 20px; }

/* ─── FORM ELEMENTS ───────────────────────────────────────── */
.sa-field { margin-bottom: 16px; }
.sa-field-label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 6px; }
.sa-textarea { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ced4da; border-radius: 8px; font-size: 0.85rem; font-family: inherit; resize: vertical; transition: border-color 0.15s; box-sizing: border-box; }
.sa-textarea:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25); }
.sa-info-box { background: #f8faff; border: 1px solid #e8f0fe; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
.sa-info-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.85rem; }
.sa-info-row span { color: #999; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ breadcrumb ] start -->
                        <div class="page-header card">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Communication Add-ons
                                    </h5>
                                    <p class="page-desc">Approve requests and manage active WhatsApp/SMTP add-ons</p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="manage_communication_addon_pricing.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Manage Pricing
                                    </a>
                                </div>
                            </div>
                        </div>
                        <!-- [ breadcrumb ] end -->
                        <?php if (!empty($success)): ?>
                        <div class="sa-alert sa-alert-success">
                            <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                            <div class="sa-alert-content"><?= htmlspecialchars($success) ?></div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                        <div class="sa-alert sa-alert-danger">
                            <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                            <div class="sa-alert-content"><?= htmlspecialchars($error) ?></div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                        <?php endif; ?>

                        <!-- Pending Requests Section -->
                        <div class="sa-section-header">
                            <h5>Pending Communication Add-on Requests</h5>
                        </div>
                        <?php if (empty($pending_requests)): ?>
                        <div class="sa-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            <div class="sa-empty-title">No pending requests</div>
                        </div>
                        <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Type</th>
                                        <th>Plan</th>
                                        <th>Billing</th>
                                        <th>Estimated Cost</th>
                                        <th>Requested At</th>
                                        <th class="sa-th-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['tenant_name'] ?? 'N/A') ?></strong></td>
                                        <td><?= ucfirst(htmlspecialchars($item['addon_type'])) ?></td>
                                        <td><?= htmlspecialchars($item['plan_name'] ?? 'N/A') ?></td>
                                        <td><?= ucfirst(htmlspecialchars($item['billing_cycle'])) ?></td>
                                        <td style="font-weight:600;"><?= htmlspecialchars($item['currency']) . ' ' . number_format(floatval($item['estimated_monthly_cost']), 2) ?></td>
                                        <td class="sa-td-date"><?= htmlspecialchars($item['created_at']) ?></td>
                                        <td class="sa-td-actions">
                                            <button type="button" class="sa-icon-btn sa-icon-btn-success approve-btn"
                                                    data-request-id="<?= intval($item['id']) ?>"
                                                    data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>"
                                                    data-type="<?= ucfirst(htmlspecialchars($item['addon_type'])) ?>"
                                                    data-cost="<?= htmlspecialchars($item['currency']) . ' ' . number_format(floatval($item['estimated_monthly_cost']), 2) ?>"
                                                    data-plan="<?= htmlspecialchars($item['plan_name'] ?? 'N/A') ?>" title="Approve">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            </button>
                                            <button type="button" class="sa-icon-btn sa-icon-btn-danger reject-btn"
                                                    data-request-id="<?= intval($item['id']) ?>"
                                                    data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>" title="Reject">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- All Add-ons Section -->
                        <div class="sa-section-header">
                            <h5>All Communication Add-ons</h5>
                        </div>
                        <?php if (empty($all_addons)): ?>
                        <div class="sa-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <div class="sa-empty-title">No communication add-ons found</div>
                        </div>
                        <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Type</th>
                                        <th>Billing</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th class="sa-th-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_addons as $addon):
                                        $status_color = ($addon['status'] ?? '') === 'active' ? 'pill-green' : 'pill-gray';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($addon['tenant_name'] ?? 'N/A') ?></strong></td>
                                        <td><?= ucfirst(htmlspecialchars($addon['addon_type'])) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($addon['billing_cycle'])) ?></td>
                                        <td style="font-weight:600;"><?= htmlspecialchars($addon['currency']) . ' ' . number_format(floatval($addon['addon_price']), 2) ?></td>
                                        <td><span class="pill <?= $status_color ?>"><?= htmlspecialchars(ucfirst($addon['status'])) ?></span></td>
                                        <td class="sa-td-date"><?= htmlspecialchars($addon['created_at']) ?></td>
                                        <td class="sa-td-actions">
                                            <?php if (($addon['status'] ?? '') === 'active'): ?>
                                            <button type="button" class="sa-icon-btn sa-icon-btn-warning suspend-btn"
                                                    data-addon-id="<?= intval($addon['id']) ?>"
                                                    data-tenant-name="<?= htmlspecialchars($addon['tenant_name']) ?>" title="Suspend">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="sa-icon-btn reactivate-btn"
                                                    data-addon-id="<?= intval($addon['id']) ?>"
                                                    data-tenant-name="<?= htmlspecialchars($addon['tenant_name']) ?>" title="Reactivate">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-modal-wrap">
        <div class="sa-modal-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <h3>Approve Communication Add-on</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approve_request_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="approve_tenant_name"></strong></div>
                    <div class="sa-info-row"><span>Type:</span> <strong id="approve_type"></strong></div>
                    <div class="sa-info-row"><span>Plan:</span> <strong id="approve_plan"></strong></div>
                    <div class="sa-info-row"><span>Estimated Cost:</span> <strong id="approve_cost"></strong></div>
                </div>
                <div class="sa-field">
                    <label class="sa-field-label">Approval Notes (Optional)</label>
                    <textarea class="sa-textarea" name="approval_notes" rows="3" placeholder="Add any notes about this approval..."></textarea>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Approve
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-modal-wrap">
        <div class="sa-modal-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <h3>Reject Communication Add-on</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="reject_request_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="reject_tenant_name"></strong></div>
                </div>
                <div class="sa-field">
                    <label class="sa-field-label">Reason for Rejection <span style="color:#ef4444;">*</span></label>
                    <textarea class="sa-textarea" name="rejection_reason" rows="4" required placeholder="Please provide a reason for rejecting..."></textarea>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Suspend Modal -->
<div id="suspendModal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-modal-wrap">
        <div class="sa-modal-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h3>Suspend Communication Add-on</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="addon_id" id="suspend_addon_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="suspend_tenant_name"></strong></div>
                </div>
                <p style="margin:16px 0 0;font-size:0.85rem;color:var(--muted);">Suspending this add-on will temporarily disable it for the tenant.</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Suspend
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reactivate Modal -->
<div id="reactivateModal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-modal-wrap">
        <div class="sa-modal-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4099ff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
            <h3>Reactivate Communication Add-on</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="reactivate">
                <input type="hidden" name="addon_id" id="reactivate_addon_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="reactivate_tenant_name"></strong></div>
                </div>
                <p style="margin:16px 0 0;font-size:0.85rem;color:var(--muted);">Reactivating this add-on will restore it for the tenant.</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg> Reactivate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function showModal(id) { document.getElementById(id).style.display = 'flex'; }

document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('approve_request_id').value = this.getAttribute('data-request-id');
        document.getElementById('approve_tenant_name').textContent = this.getAttribute('data-tenant-name');
        document.getElementById('approve_type').textContent = this.getAttribute('data-type');
        document.getElementById('approve_plan').textContent = this.getAttribute('data-plan');
        document.getElementById('approve_cost').textContent = this.getAttribute('data-cost');
        showModal('approveModal');
    });
});

document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('reject_request_id').value = this.getAttribute('data-request-id');
        document.getElementById('reject_tenant_name').textContent = this.getAttribute('data-tenant-name');
        showModal('rejectModal');
    });
});

document.querySelectorAll('.suspend-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('suspend_addon_id').value = this.getAttribute('data-addon-id');
        document.getElementById('suspend_tenant_name').textContent = this.getAttribute('data-tenant-name');
        showModal('suspendModal');
    });
});

document.querySelectorAll('.reactivate-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('reactivate_addon_id').value = this.getAttribute('data-addon-id');
        document.getElementById('reactivate_tenant_name').textContent = this.getAttribute('data-tenant-name');
        showModal('reactivateModal');
    });
});
</script>
