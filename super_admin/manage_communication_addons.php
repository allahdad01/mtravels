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
    .ca-wrap { padding: 20px; }
    .ca-head { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
    .ca-head h4 { margin: 0; font-weight: 700; color: #fff; }
    .ca-head p { margin: 6px 0 0; opacity: .9; font-size: 13px; }
    .ca-alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; font-weight: 600; }
    .ca-card { background: #fff; border: 1px solid #e8edf5; border-radius: 12px; box-shadow: 0 2px 12px rgba(64,153,255,.08); margin-bottom: 16px; }
    .ca-card-head { padding: 14px 16px; border-bottom: 1px solid #e8edf5; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .ca-card-head h5 { margin: 0; font-size: 15px; font-weight: 700; }
    .ca-card-body { padding: 14px 16px; }
    .ca-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .ca-table thead th { background: #f4f7fe; color: #6b7a99; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; padding: 10px; border-bottom: 1px solid #e8edf5; }
    .ca-table tbody td { padding: 10px; border-bottom: 1px solid #eef2f8; vertical-align: top; }
    .ca-table tbody tr:last-child td { border-bottom: none; }
    .ca-inline-form { display: inline-flex; align-items: center; gap: 6px; margin-right: 6px; margin-bottom: 6px; }
    .ca-inline-form input[type="text"] { width: 145px; }
    .ca-badge { display: inline-flex; align-items: center; border-radius: 16px; padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .ca-badge.pending { background: rgba(245,158,11,.12); color: #92400e; }
    .ca-badge.active { background: rgba(34,197,94,.12); color: #166534; }
    .ca-badge.inactive { background: rgba(107,122,153,.12); color: #475569; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="ca-wrap">
                            <div class="ca-head">
                                <h4><i class="feather icon-message-circle mr-1"></i>Communication Add-ons</h4>
                                <p>Approve requests and manage active WhatsApp/SMTP add-ons</p>
                            </div>
                            <?php if (!empty($success)): ?>
                            <div class="alert alert-success ca-alert"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($error)): ?>
                            <div class="alert alert-danger ca-alert"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                        <div class="ca-card">
                            <div class="ca-card-head">
                                <h5>Pending Communication Add-on Requests</h5>
                                <div>
                                    <a href="manage_communication_addon_pricing.php" class="btn btn-sm btn-primary">
                                        <i class="feather icon-dollar-sign mr-1"></i>Manage Pricing
                                    </a>
                                </div>
                            </div>
                            <div class="ca-card-body">
                                <?php if (empty($pending_requests)): ?>
                                <p class="text-muted mb-0">No pending requests.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="ca-table">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Type</th>
                                                <th>Plan</th>
                                                <th>Billing</th>
                                                <th>Estimated Cost</th>
                                                <th>Requested At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_requests as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['tenant_name'] ?? 'N/A') ?></td>
                                                <td><?= ucfirst(htmlspecialchars($item['addon_type'])) ?></td>
                                                <td><?= htmlspecialchars($item['plan_name'] ?? 'N/A') ?></td>
                                                <td><?= ucfirst(htmlspecialchars($item['billing_cycle'])) ?></td>
                                                <td><?= htmlspecialchars($item['currency']) . ' ' . number_format(floatval($item['estimated_monthly_cost']), 2) ?></td>
                                                <td><?= htmlspecialchars($item['created_at']) ?></td>
                                                <td>
                                                    <form method="POST" class="ca-inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="request_id" value="<?= intval($item['id']) ?>">
                                                        <input type="text" class="form-control form-control-sm mb-1" name="approval_notes" placeholder="Approval notes">
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form method="POST" class="ca-inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="request_id" value="<?= intval($item['id']) ?>">
                                                        <input type="text" class="form-control form-control-sm mb-1" name="rejection_reason" placeholder="Rejection reason">
                                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ca-card">
                            <div class="ca-card-head">
                                <h5>All Communication Add-ons</h5>
                            </div>
                            <div class="ca-card-body">
                                <?php if (empty($all_addons)): ?>
                                <p class="text-muted mb-0">No communication add-ons found.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="ca-table">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Type</th>
                                                <th>Billing</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_addons as $addon): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($addon['tenant_name'] ?? 'N/A') ?></td>
                                                <td><?= ucfirst(htmlspecialchars($addon['addon_type'])) ?></td>
                                                <td><?= ucfirst(htmlspecialchars($addon['billing_cycle'])) ?></td>
                                                <td><?= htmlspecialchars($addon['currency']) . ' ' . number_format(floatval($addon['addon_price']), 2) ?></td>
                                                <td>
                                                    <?php if (($addon['status'] ?? '') === 'active'): ?>
                                                    <span class="ca-badge active">Active</span>
                                                    <?php else: ?>
                                                    <span class="ca-badge inactive"><?= htmlspecialchars(ucfirst($addon['status'])) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($addon['created_at']) ?></td>
                                                <td>
                                                    <?php if (($addon['status'] ?? '') === 'active'): ?>
                                                    <form method="POST" class="ca-inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="suspend">
                                                        <input type="hidden" name="addon_id" value="<?= intval($addon['id']) ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm">Suspend</button>
                                                    </form>
                                                    <?php else: ?>
                                                    <form method="POST" class="ca-inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="reactivate">
                                                        <input type="hidden" name="addon_id" value="<?= intval($addon['id']) ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">Reactivate</button>
                                                    </form>
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
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
