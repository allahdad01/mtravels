<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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
    error_log("Unauthorized access attempt to manage_branch_addons.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
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
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">Branch Add-on Requests</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Branch Add-ons</a></li>
                                </ul>
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
                                <?php if (isset($success)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
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
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card">
                                     <div class="card-header">
                                         <h5>Branch Add-ons Management <span class="badge badge-info"><?= $total_items ?> total</span></h5>
                                     </div>
                                     <div class="card-body table-border-style">
                                         <div class="mb-3">
                                             <form method="GET" action="manage_branch_addons.php" class="form-inline">
                                                 <input type="text" class="form-control mr-2" name="search" placeholder="Search tenant or plan..." value="<?= htmlspecialchars($search_query) ?>" style="width: 250px;">
                                                 <select class="form-control mr-2" name="status" style="width: 120px;">
                                                     <option value="">All Status</option>
                                                     <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                     <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                     <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                     <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Suspended</option>
                                                     <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                     <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                 </select>
                                                 <button type="submit" class="btn btn-primary mr-2">Search</button>
                                                 <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                                 <a href="manage_branch_addons.php" class="btn btn-secondary">Clear</a>
                                                 <?php endif; ?>
                                             </form>
                                         </div>
                                         <?php if (empty($display_items)): ?>
                                         <div class="alert alert-info">
                                             No branch add-ons found.
                                         </div>
                                         <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Tenant</th>
                                                        <th>Plan</th>
                                                        <th>Additional Branches</th>
                                                        <th>Price per Branch</th>
                                                        <th>Total Cost</th>
                                                        <th>Currency</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($display_items as $item): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($item['tenant_name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($item['plan_name'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <span class="badge badge-success">
                                                                +<?= intval($item['additional_branches'] ?? $item['requested_additional_branches'] ?? 0) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= isset($item['addon_price_per_branch']) ? number_format($item['addon_price_per_branch'], 2) : '-' ?></td>
                                                        <td><?= isset($item['total_addon_cost']) ? number_format($item['total_addon_cost'], 2) : number_format($item['estimated_monthly_cost'] ?? 0, 2) ?></td>
                                                        <td><?= htmlspecialchars($item['currency']) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php 
                                                                echo match($item['status']) {
                                                                    'pending' => 'warning',
                                                                    'approved', 'active' => 'success',
                                                                    'inactive' => 'danger',
                                                                    'rejected', 'cancelled' => 'secondary',
                                                                    default => 'light'
                                                                };
                                                            ?>">
                                                                <?= ($item['status'] === 'inactive') ? 'Suspended' : ucfirst($item['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M d, Y', strtotime($item['created_date'] ?? $item['requested_at'])) ?></td>
                                                        <td>
                                                            <?php if ($item['status'] === 'pending'): ?>
                                                            <button class="btn btn-sm btn-success approve-btn" 
                                                                    data-request-id="<?= $item['id'] ?>"
                                                                    data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>"
                                                                    data-branches="<?= intval($item['requested_additional_branches']) ?>"
                                                                    data-cost="<?= number_format($item['estimated_monthly_cost'], 2) ?>">
                                                                <i class="feather icon-check"></i> Approve
                                                            </button>
                                                            <button class="btn btn-sm btn-danger reject-btn"
                                                                    data-request-id="<?= $item['id'] ?>"
                                                                    data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">
                                                                <i class="feather icon-x"></i> Reject
                                                            </button>
                                                            <?php elseif ($item['status'] === 'active'): ?>
                                                            <button class="btn btn-sm btn-warning suspend-btn"
                                                                    data-addon-id="<?= $item['id'] ?>"
                                                                    data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">
                                                                <i class="feather icon-pause"></i> Suspend
                                                            </button>
                                                            <?php elseif ($item['status'] === 'inactive'): ?>
                                                            <button class="btn btn-sm btn-info reactivate-btn"
                                                                    data-addon-id="<?= $item['id'] ?>"
                                                                    data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">
                                                                <i class="feather icon-play"></i> Reactivate
                                                            </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">Previous</a>
                                            </li>
                                            <?php 
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            if ($start_page > 1): ?>
                                            <li class="page-item">
                                            <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">1</a>
                                            </li>
                                            <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>"><?= $i ?></a>
                                            </li>
                                            <?php endfor; ?>
                                            <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                            <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>"><?= $total_pages ?></a>
                                            </li>
                                            <?php endif; ?>
                                            <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">Next</a>
                                            </li>
                                        </ul>
                                        </nav>
                                        <div class="text-center text-muted small mt-2">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($pending_requests) ?> of <?= $total_items ?> requests
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
                <h5 class="modal-title" id="approveModalLabel">Approve Branch Add-on Request</h5>
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
                        <p><strong>Additional Branches:</strong> <span id="approve_branches"></span></p>
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
                 <h5 class="modal-title" id="rejectModalLabel">Reject Branch Add-on Request</h5>
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
                 <h5 class="modal-title" id="suspendModalLabel">Suspend Branch Add-on</h5>
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
                         <p class="text-muted"><small>Suspending this add-on will temporarily disable the additional branches for this tenant.</small></p>
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
                 <h5 class="modal-title" id="reactivateModalLabel">Reactivate Branch Add-on</h5>
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
                         <p class="text-muted"><small>Reactivating this add-on will restore the additional branches for this tenant.</small></p>
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
        const branches = this.getAttribute('data-branches');
        const cost = this.getAttribute('data-cost');
        
        document.getElementById('approve_request_id').value = requestId;
        document.getElementById('approve_tenant_name').textContent = tenantName;
        document.getElementById('approve_branches').textContent = branches;
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
</body>
</html>
