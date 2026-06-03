<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';
require_once '../includes/BranchAddonManager.php';

$addon_manager = new BranchAddonManager($pdo);

// Handle approval action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (isset($_POST['action'])) {
         // Verify CSRF token
         if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
             $error = 'Invalid CSRF token';
         } else {
             $user_id = $_SESSION['user_id'];
             
             if ($_POST['action'] === 'approve') {
                 $request_id = intval($_POST['request_id']);
                 $approval_notes = $_POST['approval_notes'] ?? '';
                 
                 $result = $addon_manager->approveBranchRequest($request_id, $user_id, $approval_notes);
                 
                 if ($result['success']) {
                     $success = 'branch_addon_approved';
                 } else {
                     $error = $result['message'];
                 }
             } elseif ($_POST['action'] === 'reject') {
                 $request_id = intval($_POST['request_id']);
                 $reason = $_POST['rejection_reason'] ?? '';
                 
                 $result = $addon_manager->rejectBranchRequest($request_id, $user_id, $reason);
                 
                 if ($result['success']) {
                     $success = 'branch_addon_rejected';
                 } else {
                     $error = $result['message'];
                 }
             } elseif ($_POST['action'] === 'suspend') {
                 $addon_id = intval($_POST['addon_id']);
                 $result = $addon_manager->suspendBranchAddon($addon_id);
                 
                 if ($result['success']) {
                     $success = 'branch_addon_suspended';
                 } else {
                     $error = $result['message'];
                 }
             } elseif ($_POST['action'] === 'reactivate') {
                 $addon_id = intval($_POST['addon_id']);
                 $result = $addon_manager->reactivateBranchAddon($addon_id);
                 
                 if ($result['success']) {
                     $success = 'branch_addon_reactivated';
                 } else {
                     $error = $result['message'];
                 }
             }
         }
     }
}

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get both pending requests and active addons
$pending_requests = $addon_manager->getPendingAddonRequests();
$active_addons = $addon_manager->getAllBranchAddons();

// Merge and combine for display
$all_items = array_merge($pending_requests, $active_addons);

// Apply search filter
$filtered_items = $all_items;
if (!empty($search_query)) {
     $search_lower = strtolower($search_query);
     $filtered_items = array_filter($all_items, function($item) use ($search_lower) {
         return strpos(strtolower($item['tenant_name'] ?? ''), $search_lower) !== false ||
                strpos(strtolower($item['plan_name'] ?? ''), $search_lower) !== false;
     });
}

// Apply status filter
if (!empty($status_filter)) {
     $filtered_items = array_filter($filtered_items, function($item) use ($status_filter) {
         return ($item['status'] ?? '') === $status_filter;
     });
}

// Pagination
$total_items = count($filtered_items);
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;
$display_items = array_slice(array_values($filtered_items), $offset, $items_per_page);
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Branch Add-on Requests
                            </h5>
                            <p class="page-desc">Manage branch add-on requests and active add-ons for your tenants</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="branch_addon_payments.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>View Payments
                            </a>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <div class="col-xl-12">
                                <!-- Success/Error Alerts -->
                                <?php if (isset($success)): ?>
                                <div class="sa-alert sa-alert-success">
                                    <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                    <div class="sa-alert-content">
                                        <?php
                                        $msg = '';
                                        switch ($success) {
                                            case 'branch_addon_approved':
                                                $msg = 'Branch add-on request approved successfully';
                                                break;
                                            case 'branch_addon_rejected':
                                                $msg = 'Branch add-on request rejected';
                                                break;
                                            case 'branch_addon_suspended':
                                                $msg = 'Branch add-on suspended successfully';
                                                break;
                                            case 'branch_addon_reactivated':
                                                $msg = 'Branch add-on reactivated successfully';
                                                break;
                                            default:
                                                $msg = 'Operation completed successfully';
                                        }
                                        echo $msg;
                                        ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($error)): ?>
                                <div class="sa-alert sa-alert-danger">
                                    <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                    <div class="sa-alert-content">
                                        <?= htmlspecialchars($error) ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Toolbar -->
                                <div class="sa-toolbar">
                                    <form method="GET" action="manage_branch_addons.php" class="sa-toolbar-form">
                                        <div class="sa-toolbar-group">
                                            <span class="sa-toolbar-label">Search</span>
                                            <div class="sa-search-box">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search tenant or plan..." value="<?= htmlspecialchars($search_query) ?>">
                                            </div>
                                        </div>
                                        <div class="sa-toolbar-group">
                                            <span class="sa-toolbar-label">Status</span>
                                            <select class="sa-filter-select" name="status">
                                                <option value="">All Status</option>
                                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Suspended</option>
                                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="sa-btn sa-btn-primary" style="align-self:flex-end;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Filter
                                        </button>
                                        <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                        <a href="manage_branch_addons.php" class="sa-btn sa-btn-ghost" style="align-self:flex-end;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Reset
                                        </a>
                                        <?php endif; ?>
                                    </form>
                                </div>

                                <!-- Section Header -->
                                <div class="sa-section-header">
                                    <div>
                                        <h2>Branch Add-ons Overview</h2>
                                        <p><?= $total_items ?> requests</p>
                                    </div>
                                </div>

                                <!-- Data Table -->
                                <?php if (!empty($display_items)): ?>
                                <div class="sa-table-wrap">
                                    <table class="sa-table">
                                        <thead>
                                            <tr>
                                                <th>Tenant / Plan</th>
                                                <th>Additional Branches</th>
                                                <th>Price / Branch</th>
                                                <th>Total Cost</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th class="sa-th-actions">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($display_items as $item):
                                                $initial = strtoupper(substr($item['tenant_name'], 0, 1));
                                                $status_color = match($item['status']) {
                                                    'pending' => 'pill-amber',
                                                    'approved', 'active' => 'pill-green',
                                                    'inactive' => 'pill-red',
                                                    default => 'pill-gray'
                                                };
                                                $status_display = ($item['status'] === 'inactive') ? 'Suspended' : ucfirst($item['status']);
                                            ?>
                                            <tr>
                                                <td class="sa-td-tenant">
                                                    <div class="sa-avatar" style="background:<?= match($item['status']) {
                                                        'active', 'approved' => '#10b981',
                                                        'pending' => '#f59e0b',
                                                        'inactive', 'rejected', 'cancelled' => '#6b7280',
                                                        default => '#6b7280'
                                                    } ?>"><?= $initial ?></div>
                                                    <div class="sa-tenant-meta">
                                                        <div class="sa-tenant-name"><?= htmlspecialchars($item['tenant_name']) ?></div>
                                                        <div class="sa-tenant-id"><?= htmlspecialchars($item['plan_name'] ?? 'N/A') ?></div>
                                                    </div>
                                                </td>
                                                <td style="font-weight:600;">+<?= intval($item['additional_branches'] ?? $item['requested_additional_branches'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars($item['currency']) ?> <?= isset($item['addon_price_per_branch']) ? number_format($item['addon_price_per_branch'], 2) : '-' ?></td>
                                                <td style="font-weight:600;"><?= htmlspecialchars($item['currency']) ?> <?= isset($item['total_addon_cost']) ? number_format($item['total_addon_cost'], 2) : number_format($item['estimated_monthly_cost'] ?? 0, 2) ?></td>
                                                <td><span class="pill <?= $status_color ?>"><?= $status_display ?></span></td>
                                                <td class="sa-td-date"><?= date('M d, Y', strtotime($item['created_date'] ?? $item['requested_at'])) ?></td>
                                                <td class="sa-td-actions">
                                                    <?php if ($item['status'] === 'pending'): ?>
                                                    <button type="button" class="sa-icon-btn sa-icon-btn-success approve-btn"
                                                            data-request-id="<?= $item['id'] ?>"
                                                            data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>"
                                                            data-branches="<?= intval($item['requested_additional_branches']) ?>"
                                                            data-cost="<?= number_format($item['estimated_monthly_cost'], 2) ?>" title="Approve">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </button>
                                                    <button type="button" class="sa-icon-btn sa-icon-btn-danger reject-btn"
                                                            data-request-id="<?= $item['id'] ?>"
                                                            data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>" title="Reject">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </button>
                                                    <?php elseif ($item['status'] === 'active'): ?>
                                                    <button type="button" class="sa-icon-btn suspend-btn"
                                                            data-addon-id="<?= $item['id'] ?>"
                                                            data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>" title="Suspend">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                                    </button>
                                                    <?php elseif ($item['status'] === 'inactive'): ?>
                                                    <button type="button" class="sa-icon-btn reactivate-btn"
                                                            data-addon-id="<?= $item['id'] ?>"
                                                            data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>" title="Reactivate">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="sa-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                    <div class="sa-empty-title">No Branch Add-ons Found</div>
                                    <div class="sa-empty-desc"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'No branch add-on requests at this time.' ?></div>
                                </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="sa-pagination">
                                <?php 
                                $query_string = '';
                                if (!empty($search_query)) $query_string .= '&search=' . urlencode($search_query);
                                if (!empty($status_filter)) $query_string .= '&status=' . urlencode($status_filter);
                                
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                ?>
                                
                                <?php if ($current_page > 1): ?>
                                <a href="?page=1<?= $query_string ?>" class="sa-page-btn" title="First page"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg></a>
                                <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="sa-page-btn" title="Previous"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
                                <?php endif; ?>
                                
                                <?php if ($start_page > 1): ?>
                                <span class="sa-page-ellipsis">...</span>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?= $i ?><?= $query_string ?>" class="sa-page-btn <?= $i === $current_page ? 'sa-page-active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                <span class="sa-page-ellipsis">...</span>
                                <?php endif; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="sa-page-btn" title="Next"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
                                <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="sa-page-btn" title="Last page"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg></a>
                                <?php endif; ?>
                                
                                <span class="sa-page-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
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
            <h3>Approve Branch Add-on Request</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="approveForm" method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approve_request_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="approve_tenant_name"></strong></div>
                    <div class="sa-info-row"><span>Additional Branches:</span> <strong id="approve_branches"></strong></div>
                    <div class="sa-info-row"><span>Estimated Monthly Cost:</span> <strong id="approve_cost"></strong></div>
                </div>
                <div class="sa-field">
                    <label class="sa-field-label">Approval Notes (Optional)</label>
                    <textarea class="sa-textarea" id="approval_notes" name="approval_notes" rows="3" placeholder="Add any notes about this approval..."></textarea>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Approve Request
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
            <h3>Reject Branch Add-on Request</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="rejectForm" method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="reject_request_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="reject_tenant_name"></strong></div>
                </div>
                <div class="sa-field">
                    <label class="sa-field-label">Reason for Rejection <span style="color:#ef4444;">*</span></label>
                    <textarea class="sa-textarea" id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide a reason for rejecting this request..."></textarea>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject Request
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
            <h3>Suspend Branch Add-on</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="suspendForm" method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="addon_id" id="suspend_addon_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="suspend_tenant_name"></strong></div>
                </div>
                <p style="margin:16px 0 0;font-size:0.85rem;color:var(--muted);">Suspending this add-on will temporarily disable the additional branches for this tenant.</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Suspend Add-on
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
            <h3>Reactivate Branch Add-on</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="reactivateForm" method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="reactivate">
                <input type="hidden" name="addon_id" id="reactivate_addon_id">
                <div class="sa-info-box">
                    <div class="sa-info-row"><span>Tenant:</span> <strong id="reactivate_tenant_name"></strong></div>
                </div>
                <p style="margin:16px 0 0;font-size:0.85rem;color:var(--muted);">Reactivating this add-on will restore the additional branches for this tenant.</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="sa-btn sa-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg> Reactivate Add-on
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
    --blue: #4099ff;
    --amber: #ffc107;
    --grad-start: #4099ff;
    --grad-end: #2ed8b6;
    --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --radius: 10px;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2673cc 50%, #2ed8b6 100%) !important;
    color: #fff; border: none !important; margin-bottom: 24px;
    padding: 22px 28px !important; box-shadow: 0 4px 20px rgba(64,153,255,0.3);
    border-radius: 12px; position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    border: none;
    margin-bottom: 1.5rem;
}

.sa-alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.sa-alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.sa-alert-icon {
    flex-shrink: 0;
    font-weight: bold;
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.sa-alert-content {
    flex: 1;
    align-self: center;
}

.sa-alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: inherit;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
}

.sa-alert-close:hover {
    opacity: 0.7;
}

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.sa-btn-primary {
    background: var(--grad);
    color: white;
}

.sa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.sa-btn-success {
    background: #10b981;
    color: white;
}
.sa-btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}

.sa-btn-danger {
    background: #fee2e2;
    color: var(--red);
    border: 1px solid #fecaca;
}

.sa-btn-danger:hover {
    background: #fecaca;
}

.sa-btn-warning {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fde68a;
}
.sa-btn-warning:hover {
    background: #fde68a;
}

.sa-btn-ghost {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #e0e0e0;
}

.sa-btn-ghost:hover {
    background: #e8e8e8;
    border-color: #d0d0d0;
}

/* ─── PILLS ───────────────────────────────────────────────── */
.pill {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.pill-green {
    background: rgba(16,185,129,0.12);
    color: #10b981;
}

.pill-red {
    background: rgba(239,68,68,0.12);
    color: #ef4444;
}

.pill-amber {
    background: rgba(245,158,11,0.12);
    color: #f59e0b;
}

.pill-gray {
    background: #f5f5f5;
    color: #999;
}

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.sa-section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0;
    color: #333;
}

.sa-section-header p {
    margin: 4px 0 0 0;
    font-size: 0.8rem;
    color: var(--muted);
}

/* ─── TOOLBAR ─────────────────────────────────────────────── */
.sa-toolbar {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 20px;
}

.sa-toolbar-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.sa-toolbar-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sa-toolbar-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sa-search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.sa-search-icon {
    position: absolute;
    left: 12px;
    color: #999;
    pointer-events: none;
}

.sa-search-input {
    padding: 0.6rem 0.75rem 0.6rem 2.2rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.85rem;
    min-width: 240px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.sa-search-input:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.sa-filter-select {
    padding: 0.6rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
    cursor: pointer;
}

.sa-filter-select:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* ─── DATA TABLE ──────────────────────────────────────────── */
.sa-table-wrap {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    overflow-x: auto;
    margin-bottom: 0;
}

.sa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.sa-table thead th {
    text-align: left;
    padding: 14px 16px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    background: #fafafa;
    border-bottom: 1px solid #e0e0e0;
    white-space: nowrap;
}

.sa-table tbody tr {
    transition: background 0.15s;
}

.sa-table tbody tr:hover {
    background: #f8faff;
}

.sa-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}

.sa-table tbody tr:last-child td {
    border-bottom: none;
}

.sa-td-tenant {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sa-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.sa-tenant-meta {
    display: flex;
    flex-direction: column;
}

.sa-tenant-name {
    font-weight: 600;
    color: #333;
}

.sa-tenant-id {
    font-size: 0.75rem;
    color: #999;
    margin-top: 2px;
}

.sa-td-date {
    white-space: nowrap;
    color: #999;
    font-size: 0.8rem;
}

.sa-th-actions {
    width: 80px;
    text-align: right;
}

.sa-td-actions {
    text-align: right;
    white-space: nowrap;
}

.sa-icon-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    color: #999;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    vertical-align: middle;
    margin-left: 4px;
}

.sa-icon-btn:hover {
    background: #f5f5f5;
    border-color: #ccc;
    color: #333;
}

.sa-icon-btn-success {
    color: #10b981;
    border-color: rgba(16,185,129,0.2);
}

.sa-icon-btn-success:hover {
    background: rgba(16,185,129,0.1);
    border-color: #10b981;
}

.sa-icon-btn-danger {
    color: #ef4444;
    border-color: rgba(239,68,68,0.2);
}

.sa-icon-btn-danger:hover {
    background: rgba(239,68,68,0.1);
    border-color: #ef4444;
}

/* ─── EMPTY STATE ─────────────────────────────────────────── */
.sa-empty {
    text-align: center;
    padding: 48px 20px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    color: #ccc;
}

.sa-empty-title {
    font-weight: 600;
    color: #999;
    margin-top: 12px;
    font-size: 1rem;
}

.sa-empty-desc {
    font-size: 0.85rem;
    color: #bbb;
    margin-top: 4px;
}

/* ─── PAGINATION ──────────────────────────────────────────── */
.sa-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding: 14px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    flex-wrap: wrap;
}

.sa-page-btn {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: #f5f5f5;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.sa-page-btn:hover {
    background: rgba(64, 153, 255, 0.1);
    border-color: #4099ff;
    color: #4099ff;
}

.sa-page-active {
    background: var(--grad);
    border-color: #4099ff;
    color: white;
}

.sa-page-active:hover {
    color: white;
}

.sa-page-ellipsis {
    color: #999;
    font-size: 0.9rem;
}

.sa-page-info {
    font-size: 0.8rem;
    color: #999;
    margin-left: auto;
}

/* ─── MODALS ──────────────────────────────────────────────── */
.sa-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.sa-modal-wrap {
    background: white;
    border-radius: 14px;
    width: 480px;
    max-width: 94vw;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.sa-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px 0;
}

.sa-modal-header h3 {
    flex: 1;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
    color: #333;
}

.sa-modal-close {
    width: 32px;
    height: 32px;
    border: none;
    background: #f5f5f5;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    transition: all 0.2s;
}

.sa-modal-close:hover {
    background: #e0e0e0;
    color: #333;
}

.sa-modal-body {
    padding: 16px 24px 0;
}

.sa-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 24px 20px;
}

/* ─── FORM ELEMENTS ───────────────────────────────────────── */
.sa-field {
    margin-bottom: 16px;
}

.sa-field-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 6px;
}

.sa-textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: inherit;
    resize: vertical;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    box-sizing: border-box;
}

.sa-textarea:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.sa-info-box {
    background: #f8faff;
    border: 1px solid #e8f0fe;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}

.sa-info-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.85rem;
}

.sa-info-row span {
    color: #999;
}
</style>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function showModal(id) { document.getElementById(id).style.display = 'block'; }

// Approve button click handler
document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('approve_request_id').value = this.getAttribute('data-request-id');
        document.getElementById('approve_tenant_name').textContent = this.getAttribute('data-tenant-name');
        document.getElementById('approve_branches').textContent = this.getAttribute('data-branches');
        document.getElementById('approve_cost').textContent = this.getAttribute('data-cost');
        showModal('approveModal');
    });
});

// Reject button click handler
document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('reject_request_id').value = this.getAttribute('data-request-id');
        document.getElementById('reject_tenant_name').textContent = this.getAttribute('data-tenant-name');
        showModal('rejectModal');
    });
});

// Suspend button click handler
document.querySelectorAll('.suspend-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('suspend_addon_id').value = this.getAttribute('data-addon-id');
        document.getElementById('suspend_tenant_name').textContent = this.getAttribute('data-tenant-name');
        showModal('suspendModal');
    });
});

// Reactivate button click handler
document.querySelectorAll('.reactivate-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('reactivate_addon_id').value = this.getAttribute('data-addon-id');
        document.getElementById('reactivate_tenant_name').textContent = this.getAttribute('data-tenant-name');
        showModal('reactivateModal');
    });
});
</script>
</body>
</html>
