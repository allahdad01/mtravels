<?php
/**
 * Manage User Add-ons - Super Admin Interface
 * 
 * Allows super admins to approve/reject user addon requests.
 */

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
    error_log("Unauthorized access attempt to manage_user_addons.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';
require_once '../includes/UserAddonManager.php';

$addon_manager = new UserAddonManager($pdo);

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
                 
                 $result = $addon_manager->approveUserRequest($request_id, $user_id, $approval_notes);
                 
                 if ($result['success']) {
                     $success = 'user_addon_approved';
                 } else {
                     $error = $result['message'];
                 }
             } elseif ($_POST['action'] === 'reject') {
                 $request_id = intval($_POST['request_id']);
                 $reason = $_POST['rejection_reason'] ?? '';
                 
                 $result = $addon_manager->rejectUserRequest($request_id, $user_id, $reason);
                 
                 if ($result['success']) {
                     $success = 'user_addon_rejected';
                 } else {
                     $error = $result['message'];
                 }
             } elseif ($_POST['action'] === 'suspend') {
                 $addon_id = intval($_POST['addon_id']);
                 $result = $addon_manager->suspendUserAddon($addon_id);
                 
                 if ($result['success']) {
                     $success = 'user_addon_suspended';
                 } else {
                     $error = $result['message'];
                 }
             } elseif ($_POST['action'] === 'reactivate') {
                 $addon_id = intval($_POST['addon_id']);
                 $result = $addon_manager->reactivateUserAddon($addon_id);
                 
                 if ($result['success']) {
                     $success = 'user_addon_reactivated';
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
$active_addons = $addon_manager->getAllUserAddons();

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
                            <div class="page-header-content">
                                <h5 class="page-title mb-0">
                                    <i class="feather icon-layers mr-2"></i>User Add-on Requests
                                </h5>
                                <p class="page-subtitle mb-0 mt-2">
                                    Manage user add-on requests and active add-ons for your tenants
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <a href="user_addon_payments.php" class="btn btn-header-primary">
                                    <i class="feather icon-credit-card mr-1"></i>View Payments
                                </a>
                            </div>
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
                                    <div class="sa-alert-icon">✓</div>
                                    <div class="sa-alert-content">
                                        <?php
                                        $msg = '';
                                        switch ($success) {
                                            case 'user_addon_approved':
                                                $msg = 'User add-on request approved successfully';
                                                break;
                                            case 'user_addon_rejected':
                                                $msg = 'User add-on request rejected';
                                                break;
                                            case 'user_addon_suspended':
                                                $msg = 'User add-on suspended successfully';
                                                break;
                                            case 'user_addon_reactivated':
                                                $msg = 'User add-on reactivated successfully';
                                                break;
                                            default:
                                                $msg = 'Operation completed successfully';
                                        }
                                        echo $msg;
                                        ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($error)): ?>
                                <div class="sa-alert sa-alert-danger">
                                    <div class="sa-alert-icon">⚠</div>
                                    <div class="sa-alert-content">
                                        <?= htmlspecialchars($error) ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Search and Filter Bar -->
                                <div class="sa-card" style="margin-bottom: 20px;">
                                    <div class="sa-card-body">
                                        <form method="GET" action="manage_user_addons.php" class="sa-search-filter">
                                            <div class="sa-search-group">
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search tenant or plan..." value="<?= htmlspecialchars($search_query) ?>">
                                                <select class="sa-search-input" name="status" style="flex: 0 0 auto;">
                                                    <option value="">All Status</option>
                                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Suspended</option>
                                                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                                <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                                <a href="manage_user_addons.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- User Add-ons Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2>User Add-ons Overview</h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> requests</p>
                                    </div>
                                </div>

                                <div class="card">
                                     <div class="card-body">
                                         <!-- User Add-ons List -->
                                         <?php if (empty($display_items)): ?>
                                          <div class="sa-card">
                                              <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                                  <div style="font-size: 2rem; margin-bottom: 12px;">👤</div>
                                                  <div style="font-weight: 600; margin-bottom: 4px;">No User Add-ons Found</div>
                                                  <div style="font-size: 0.8rem;"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'No user add-on requests at this time.' ?></div>
                                              </div>
                                          </div>
                                          <?php else: ?>
                                         <div class="sa-row-list">
                                             <?php foreach ($display_items as $item):
                                                 $status_color = match($item['status']) {
                                                     'pending' => 'pill-amber',
                                                     'approved', 'active' => 'pill-green',
                                                     'inactive' => 'pill-red',
                                                     'rejected', 'cancelled' => 'pill-muted',
                                                     default => 'pill-muted'
                                                 };
                                                 $status_display = ($item['status'] === 'inactive') ? 'Suspended' : ucfirst($item['status']);
                                             ?>
                                             <div class="sa-row-card">
                                                 <div class="src-header">
                                                     <div class="src-title">
                                                         <h3><?= htmlspecialchars($item['tenant_name']) ?></h3>
                                                         <p class="src-subtitle"><?= htmlspecialchars($item['plan_name'] ?? 'N/A') ?></p>
                                                     </div>
                                                     <span class="pill <?= $status_color ?>"><?= $status_display ?></span>
                                                 </div>
                                                 
                                                 <div class="src-content">
                                                     <div class="src-col">
                                                         <span class="src-label">Additional Users</span>
                                                         <span class="src-value">+<?= intval($item['additional_users'] ?? $item['requested_additional_users'] ?? 0) ?></span>
                                                     </div>
                                                     <div class="src-col">
                                                         <span class="src-label">Price per User</span>
                                                         <span class="src-value"><?= htmlspecialchars($item['currency']) ?> <?= isset($item['addon_price_per_user']) ? number_format($item['addon_price_per_user'], 2) : '-' ?></span>
                                                     </div>
                                                     <div class="src-col">
                                                         <span class="src-label">Total Cost</span>
                                                         <span class="src-value"><?= htmlspecialchars($item['currency']) ?> <?= isset($item['total_addon_cost']) ? number_format($item['total_addon_cost'], 2) : number_format($item['estimated_monthly_cost'] ?? 0, 2) ?></span>
                                                     </div>
                                                     <div class="src-col">
                                                         <span class="src-label">Created</span>
                                                         <span class="src-value"><?= date('M d, Y', strtotime($item['created_date'] ?? $item['requested_at'])) ?></span>
                                                     </div>
                                                 </div>

                                                 <div class="src-actions">
                                                     <?php if ($item['status'] === 'pending'): ?>
                                                         <button class="sa-btn sa-btn-small sa-btn-primary approve-btn" 
                                                                 data-request-id="<?= $item['id'] ?>"
                                                                 data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>"
                                                                 data-users="<?= intval($item['requested_additional_users']) ?>"
                                                                 data-cost="<?= number_format($item['estimated_monthly_cost'], 2) ?>">Approve</button>
                                                         <button class="sa-btn sa-btn-small sa-btn-danger reject-btn"
                                                                 data-request-id="<?= $item['id'] ?>"
                                                                 data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">Reject</button>
                                                     <?php elseif ($item['status'] === 'active'): ?>
                                                         <button class="sa-btn sa-btn-small sa-btn-ghost suspend-btn"
                                                                 data-addon-id="<?= $item['id'] ?>"
                                                                 data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">Suspend</button>
                                                     <?php elseif ($item['status'] === 'inactive'): ?>
                                                         <button class="sa-btn sa-btn-small sa-btn-primary reactivate-btn"
                                                                 data-addon-id="<?= $item['id'] ?>"
                                                                 data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">Reactivate</button>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                             <?php endforeach; ?>
                                         </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <div class="sa-pagination">
                                            <a href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="sa-pagination-item <?= $current_page === 1 ? 'disabled' : '' ?>">‹</a>
                                            <?php 
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            if ($start_page > 1): ?>
                                            <a href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="sa-pagination-item">1</a>
                                            <?php if ($start_page > 2): ?>
                                            <span class="sa-pagination-ellipsis">...</span>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <a href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="sa-pagination-item <?= $i === $current_page ? 'active' : '' ?>"><?= $i ?></a>
                                            <?php endfor; ?>
                                            <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                            <span class="sa-pagination-ellipsis">...</span>
                                            <?php endif; ?>
                                            <a href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="sa-pagination-item"><?= $total_pages ?></a>
                                            <?php endif; ?>
                                            <a href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="sa-pagination-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">›</a>
                                            <span class="sa-pagination-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                                        </div>
                                        <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveModalLabel">Approve User Add-on Request</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="approveForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" id="approve_request_id">
                    
                    <div class="alert alert-info">
                        <p><strong>Tenant:</strong> <span id="approve_tenant_name"></span></p>
                        <p><strong>Additional Users:</strong> <span id="approve_users"></span></p>
                        <p><strong>Estimated Monthly Cost:</strong> <span id="approve_cost"></span></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="approval_notes">Approval Notes (Optional)</label>
                        <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="approveForm" class="btn btn-success">
                    <i class="feather icon-check mr-1"></i> Approve Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered" role="document">
         <div class="modal-content">
             <div class="modal-header bg-danger text-white">
                 <h5 class="modal-title" id="rejectModalLabel">Reject User Add-on Request</h5>
                 <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                 </button>
             </div>
             <div class="modal-body">
                 <form id="rejectForm" method="POST">
                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                     <input type="hidden" name="action" value="reject">
                     <input type="hidden" name="request_id" id="reject_request_id">
                     
                     <div class="alert alert-warning">
                         <p><strong>Tenant:</strong> <span id="reject_tenant_name"></span></p>
                     </div>
                     
                     <div class="form-group">
                         <label for="rejection_reason">Reason for Rejection *</label>
                         <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide a reason for rejecting this request..."></textarea>
                     </div>
                 </form>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                 <button type="submit" form="rejectForm" class="btn btn-danger">
                     <i class="feather icon-x mr-1"></i> Reject Request
                 </button>
             </div>
         </div>
     </div>
 </div>

<!-- Suspend Modal -->
<div class="modal fade" id="suspendModal" tabindex="-1" role="dialog" aria-labelledby="suspendModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered" role="document">
         <div class="modal-content">
             <div class="modal-header bg-warning text-dark">
                 <h5 class="modal-title" id="suspendModalLabel">Suspend User Add-on</h5>
                 <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                 </button>
             </div>
             <div class="modal-body">
                 <form id="suspendForm" method="POST">
                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                     <input type="hidden" name="action" value="suspend">
                     <input type="hidden" name="addon_id" id="suspend_addon_id">
                     
                     <div class="alert alert-warning">
                         <p><strong>Tenant:</strong> <span id="suspend_tenant_name"></span></p>
                         <p class="text-muted"><small>Suspending this add-on will temporarily disable the additional users for this tenant.</small></p>
                     </div>
                 </form>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                 <button type="submit" form="suspendForm" class="btn btn-warning">
                     <i class="feather icon-pause mr-1"></i> Suspend Add-on
                 </button>
             </div>
         </div>
     </div>
 </div>

<!-- Reactivate Modal -->
<div class="modal fade" id="reactivateModal" tabindex="-1" role="dialog" aria-labelledby="reactivateModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered" role="document">
         <div class="modal-content">
             <div class="modal-header bg-info text-white">
                 <h5 class="modal-title" id="reactivateModalLabel">Reactivate User Add-on</h5>
                 <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                     <span aria-hidden="true">&times;</span>
                 </button>
             </div>
             <div class="modal-body">
                 <form id="reactivateForm" method="POST">
                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                     <input type="hidden" name="action" value="reactivate">
                     <input type="hidden" name="addon_id" id="reactivate_addon_id">
                     
                     <div class="alert alert-info">
                         <p><strong>Tenant:</strong> <span id="reactivate_tenant_name"></span></p>
                         <p class="text-muted"><small>Reactivating this add-on will restore the additional users for this tenant.</small></p>
                     </div>
                 </form>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                 <button type="submit" form="reactivateForm" class="btn btn-info">
                     <i class="feather icon-play mr-1"></i> Reactivate Add-on
                 </button>
             </div>
         </div>
     </div>
 </div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Approve button click handler
document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const requestId = this.getAttribute('data-request-id');
        const tenantName = this.getAttribute('data-tenant-name');
        const users = this.getAttribute('data-users');
        const cost = this.getAttribute('data-cost');
        
        document.getElementById('approve_request_id').value = requestId;
        document.getElementById('approve_tenant_name').textContent = tenantName;
        document.getElementById('approve_users').textContent = users;
        document.getElementById('approve_cost').textContent = cost;
        
        $('#approveModal').modal('show');
    });
});

// Reject button click handler
document.querySelectorAll('.reject-btn').forEach(btn => {
     btn.addEventListener('click', function() {
         const requestId = this.getAttribute('data-request-id');
         const tenantName = this.getAttribute('data-tenant-name');
         
         document.getElementById('reject_request_id').value = requestId;
         document.getElementById('reject_tenant_name').textContent = tenantName;
         
         $('#rejectModal').modal('show');
     });
});

// Suspend button click handler
document.querySelectorAll('.suspend-btn').forEach(btn => {
     btn.addEventListener('click', function() {
         const addonId = this.getAttribute('data-addon-id');
         const tenantName = this.getAttribute('data-tenant-name');
         
         document.getElementById('suspend_addon_id').value = addonId;
         document.getElementById('suspend_tenant_name').textContent = tenantName;
         
         $('#suspendModal').modal('show');
     });
});

// Reactivate button click handler
document.querySelectorAll('.reactivate-btn').forEach(btn => {
     btn.addEventListener('click', function() {
         const addonId = this.getAttribute('data-addon-id');
         const tenantName = this.getAttribute('data-tenant-name');
         
         document.getElementById('reactivate_addon_id').value = addonId;
         document.getElementById('reactivate_tenant_name').textContent = tenantName;
         
         $('#reactivateModal').modal('show');
     });
});
 
 // Clear forms on modal close
 $('#approveModal, #rejectModal, #suspendModal, #reactivateModal').on('hidden.bs.modal', function() {
     $(this).find('form')[0].reset();
 });
</script>

<style>
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
    --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --radius: 10px;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.2);
    padding: 24px;
    margin-bottom: 24px;
}

.page-header-content {
    padding: 0.5rem 0;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    line-height: 1.2;
}

.page-title i {
    font-size: 2rem;
    margin-right: 0.75rem;
    opacity: 0.95;
}

.page-subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.btn-header-primary {
    background: rgba(255,255,255,0.15) !important;
    color: #ffffff !important;
    border: 1.5px solid rgba(255,255,255,0.40) !important;
    border-radius: 6px;
    padding: 0.65rem 1.25rem !important;
    font-size: 0.9rem !important;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-header-primary:hover {
    background: rgba(255,255,255,0.25) !important;
    border-color: rgba(255,255,255,0.60) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
    border-radius: 8px;
    border-left: 4px solid;
    background: #f5f5f5;
}

.sa-alert-success {
    border-left-color: #10b981;
    background: rgba(16, 185, 129, 0.08);
}

.sa-alert-danger {
    border-left-color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
}

.sa-alert-icon {
    flex-shrink: 0;
    font-weight: 700;
    font-size: 1rem;
}

.sa-alert-success .sa-alert-icon {
    color: #10b981;
}

.sa-alert-danger .sa-alert-icon {
    color: #ef4444;
}

.sa-alert-content {
    flex: 1;
    color: #333;
    font-size: 0.9rem;
}

.sa-alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #999;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
}

.sa-alert-close:hover {
    color: #333;
}

/* ─── CARDS ───────────────────────────────────────────────── */
.sa-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

.sa-card-body {
    padding: 16px;
}

/* ─── SEARCH FILTER ───────────────────────────────────────── */
.sa-search-filter {
    display: flex;
}

.sa-search-group {
    display: flex;
    gap: 8px;
    width: 100%;
    flex-wrap: wrap;
}

.sa-search-input {
    padding: 8px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.9rem;
    flex: 1;
    min-width: 150px;
}

.sa-search-input:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
}

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-shdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sa-shdr h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    padding: 8px 12px;
    border: 1px solid;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.sa-btn:hover {
    transform: translateY(-1px);
}

.sa-btn-primary {
    background: #4099ff;
    border-color: #4099ff;
    color: white;
}

.sa-btn-primary:hover {
    background: #3a89ff;
    border-color: #3a89ff;
}

.sa-btn-danger {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

.sa-btn-danger:hover {
    background: #e03c3c;
    border-color: #e03c3c;
}

.sa-btn-ghost {
    background: transparent;
    border-color: #e0e0e0;
    color: #333;
}

.sa-btn-ghost:hover {
    background: #f5f5f5;
    border-color: #999;
}

.sa-btn-small {
    padding: 6px 10px;
    font-size: 0.8rem;
}

/* ─── ROW CARD LIST ───────────────────────────────────────── */
.sa-row-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sa-row-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    transition: all 0.2s ease;
}

.sa-row-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
}

.src-header {
    flex: 0 0 250px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 250px;
}

.src-title h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.src-subtitle {
    font-size: 0.75rem;
    color: #999;
    margin: 4px 0 0 0;
}

.src-content {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
    min-width: 400px;
}

.src-col {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.src-label {
    font-size: 0.7rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
}

.src-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}

.src-actions {
    flex: 0 0 auto;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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

.pill-muted {
    background: #f5f5f5;
    color: #999;
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

.sa-pagination-item {
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
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.sa-pagination-item:hover:not(.active):not(.disabled) {
    background: rgba(64, 153, 255, 0.1);
    border-color: #4099ff;
    color: #4099ff;
}

.sa-pagination-item.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-color: #4099ff;
    color: white;
}

.sa-pagination-item.disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.sa-pagination-ellipsis {
    color: #999;
    font-size: 0.8rem;
}

.sa-pagination-info {
    font-size: 0.8rem;
    color: #999;
    margin-left: auto;
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 1024px) {
    .sa-row-card {
        flex-wrap: wrap;
    }
    
    .src-header {
        flex: 1 1 100%;
        min-width: 100%;
    }
    
    .src-content {
        flex: 1 1 100%;
        min-width: 100%;
        grid-template-columns: repeat(2, 1fr);
    }
    
    .src-actions {
        flex: 1 1 100%;
    }
}

@media (max-width: 768px) {
    .sa-row-card {
        padding: 12px;
        flex-direction: column;
    }
    
    .src-header {
        width: 100%;
    }
    
    .src-content {
        grid-template-columns: 1fr;
    }
}
</style>

</body>
</html>
