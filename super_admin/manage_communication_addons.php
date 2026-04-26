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

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h5>Pending Communication Add-on Requests</h5>
                                <div class="mt-2">
                                    <a href="manage_communication_addon_pricing.php" class="btn btn-sm btn-primary">
                                        <i class="feather icon-dollar-sign mr-1"></i>Manage Pricing
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_requests)): ?>
                                <p class="text-muted mb-0">No pending requests.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
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
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="request_id" value="<?= intval($item['id']) ?>">
                                                        <input type="text" class="form-control form-control-sm mb-1" name="approval_notes" placeholder="Approval notes">
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
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

                        <div class="card">
                            <div class="card-header">
                                <h5>All Communication Add-ons</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($all_addons)): ?>
                                <p class="text-muted mb-0">No communication add-ons found.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
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
                                                    <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                    <span class="badge badge-secondary"><?= htmlspecialchars(ucfirst($addon['status'])) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($addon['created_at']) ?></td>
                                                <td>
                                                    <?php if (($addon['status'] ?? '') === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="suspend">
                                                        <input type="hidden" name="addon_id" value="<?= intval($addon['id']) ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm">Suspend</button>
                                                    </form>
                                                    <?php else: ?>
                                                    <form method="POST" class="d-inline">
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
