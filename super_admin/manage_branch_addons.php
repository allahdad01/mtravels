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
                <div class="page-header card" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white;">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-header-content">
                                <h5 class="page-title mb-0" style="color: white;">
                                    <i class="feather icon-layers mr-2"></i>Branch Add-on Requests
                                </h5>
                                <p class="page-subtitle mb-0 mt-2" style="color: white; opacity: 0.9;">
                                    Manage branch add-on requests and active add-ons for your tenants
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <a href="branch_addon_payments.php" class="btn btn-header-primary">
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
                                        <form method="GET" action="manage_branch_addons.php" class="sa-search-filter">
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
                                                <a href="manage_branch_addons.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Branch Add-ons Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2>Branch Add-ons Overview</h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> requests</p>
                                    </div>
                                </div>

                                <div class="card">
                                     <div class="card-body">
                                         <!-- Branch Add-ons List -->
                                         <?php if (empty($display_items)): ?>
                                         <div class="sa-card">
                                             <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                                 <div style="font-size: 2rem; margin-bottom: 12px;">📦</div>
                                                 <div style="font-weight: 600; margin-bottom: 4px;">No Branch Add-ons Found</div>
                                                 <div style="font-size: 0.8rem;"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'No branch add-on requests at this time.' ?></div>
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
                                                         <span class="src-label">Additional Branches</span>
                                                         <span class="src-value">+<?= intval($item['additional_branches'] ?? $item['requested_additional_branches'] ?? 0) ?></span>
                                                     </div>
                                                     <div class="src-col">
                                                         <span class="src-label">Price per Branch</span>
                                                         <span class="src-value"><?= htmlspecialchars($item['currency']) ?> <?= isset($item['addon_price_per_branch']) ? number_format($item['addon_price_per_branch'], 2) : '-' ?></span>
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
                                                             data-branches="<?= intval($item['requested_additional_branches']) ?>"
                                                             data-cost="<?= number_format($item['estimated_monthly_cost'], 2) ?>">
                                                         <i class="feather icon-check"></i> Approve
                                                     </button>
                                                     <button class="sa-btn sa-btn-small sa-btn-danger reject-btn"
                                                             data-request-id="<?= $item['id'] ?>"
                                                             data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">
                                                         <i class="feather icon-x"></i> Reject
                                                     </button>
                                                     <?php elseif ($item['status'] === 'active'): ?>
                                                     <button class="sa-btn sa-btn-small sa-btn-ghost suspend-btn"
                                                             data-addon-id="<?= $item['id'] ?>"
                                                             data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">
                                                         <i class="feather icon-pause"></i> Suspend
                                                     </button>
                                                     <?php elseif ($item['status'] === 'inactive'): ?>
                                                     <button class="sa-btn sa-btn-small sa-btn-primary reactivate-btn"
                                                             data-addon-id="<?= $item['id'] ?>"
                                                             data-tenant-name="<?= htmlspecialchars($item['tenant_name']) ?>">
                                                         <i class="feather icon-play"></i> Reactivate
                                                     </button>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                             <?php endforeach; ?>
                                         </div>
                                        
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
                                        <a href="?page=1<?= $query_string ?>" class="sa-pagination-item">First</a>
                                        <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="sa-pagination-item">← Prev</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($start_page > 1): ?>
                                        <span class="sa-pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <a href="?page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $current_page ? 'active' : '' ?>">
                                        <?= $i ?>
                                        </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($end_page < $total_pages): ?>
                                        <span class="sa-pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                        <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="sa-pagination-item">Next →</a>
                                        <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="sa-pagination-item">Last</a>
                                        <?php endif; ?>
                                        
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

/* ─── PAGE HEADER ────────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 2rem 2.5rem;
    border: none;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header.card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card .row {
    position: relative;
    z-index: 1;
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
    text-decoration: none;
    white-space: nowrap;
}

.btn-header-primary:hover {
    background: rgba(255,255,255,0.25) !important;
    border-color: rgba(255,255,255,0.70) !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
    color: #ffffff !important;
}

.btn-header-primary:active {
    transform: translateY(0);
}

.page-header.card .btn {
    background: rgba(255,255,255,0.10) !important;
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.30) !important;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.22) !important;
    border-color: rgba(255,255,255,0.50) !important;
    transform: translateY(-1px);
}

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

/* ─── CARDS ───────────────────────────────────────────────── */
.sa-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.sa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.sa-card-body {
    padding: 1.5rem;
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
    display: inline-block;
    font-size: 0.9rem;
}

.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.sa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.sa-btn-danger {
    background: #fee2e2;
    color: var(--red);
    border: 1px solid #fecaca;
}

.sa-btn-danger:hover {
    background: #fecaca;
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

.sa-btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
}

/* ─── SEARCH & FILTER ─────────────────────────────────────── */
.sa-search-filter {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.sa-search-group {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
    min-width: 300px;
}

.sa-search-input {
    flex: 1;
    min-width: 150px;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.sa-search-input:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-shdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.sa-shdr h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.sa-shdr p {
    margin: 4px 0 0 0;
    font-size: 0.75rem;
    color: var(--muted);
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

.sa-pagination-item:hover:not(.active) {
    background: rgba(64, 153, 255, 0.1);
    border-color: #4099ff;
    color: #4099ff;
}

.sa-pagination-item.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-color: #4099ff;
    color: white;
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
