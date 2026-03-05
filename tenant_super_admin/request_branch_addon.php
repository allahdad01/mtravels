<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is logged in and has a tenant
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header to initialize database connection
require_once 'header.php';
require_once '../includes/BranchAddonManager.php';

$tenant_id = $_SESSION['tenant_id'];
$addon_manager = new BranchAddonManager($pdo, $tenant_id);

// Get pricing configuration (without currency)
$pricing = $addon_manager->getAddonPricing($tenant_id);

// Get tenant plan info
$plan_info = $addon_manager->getTenantPlanInfo($tenant_id);
if (!$plan_info) {
    $error = 'You do not have an active subscription. Please contact support.';
} else {
    $current_branches = $addon_manager->getCurrentBranchCount($tenant_id);
    $additional_branches = $addon_manager->getTotalAdditionalBranches($tenant_id);
    $max_allowed = $addon_manager->getMaxAllowedBranches($tenant_id);
    $can_add = $addon_manager->canAddMoreBranches($tenant_id);
    
    // Get existing requests
    $pending_requests = $addon_manager->getTenantAddonRequests($tenant_id, 'pending');
    $approved_addons = $addon_manager->getActiveBranchAddons($tenant_id);
    $addon_payment_history = $addon_manager->getAddonPaymentHistory($tenant_id);
    
    // Pagination and search for payment history
    $items_per_page = 10;
    $current_page = intval($_GET['page'] ?? 1);
    $search_query = $_GET['search'] ?? '';
    
    // Filter payment history based on search
    $filtered_history = $addon_payment_history;
    if (!empty($search_query)) {
        $search_lower = strtolower($search_query);
        $filtered_history = array_filter($addon_payment_history, function($payment) use ($search_lower) {
            return 
                strpos(strtolower($payment['additional_branches']), $search_lower) !== false ||
                strpos(strtolower($payment['amount']), $search_lower) !== false ||
                strpos(strtolower($payment['currency']), $search_lower) !== false ||
                strpos(strtolower($payment['status']), $search_lower) !== false ||
                strpos(strtolower(date('M d, Y', strtotime($payment['payment_date'] ?? ''))), $search_lower) !== false;
        });
    }
    
    // Calculate pagination
    $total_items = count($filtered_history);
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    $paginated_history = array_slice(array_values($filtered_history), $offset, $items_per_page);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_branches') {
    // Verify CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $form_error = 'Invalid CSRF token';
    } else {
        $num_branches = intval($_POST['num_branches'] ?? 0);
        $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
        
        if ($num_branches <= 0) {
            $form_error = 'Please enter a valid number of branches';
        } else {
            $result = $addon_manager->requestAdditionalBranches($tenant_id, $num_branches, $billing_cycle);
            
            if ($result['success']) {
                $form_success = 'Your branch add-on request has been submitted successfully. Our team will review it shortly.';
                // Refresh data
                $pending_requests = $addon_manager->getTenantAddonRequests($tenant_id, 'pending');
            } else {
                $form_error = $result['message'];
            }
        }
    }
}
?>

<!-- [ Main Content ] start -->
<style>
/* Custom enhancements for better layout and style */
.page-header-title h5 {
    color: #007bff;
    font-weight: 600;
}
.card {
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-2px);
}
.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
}
.card-header h5 {
    margin: 0;
    font-weight: 600;
}
.badge-primary, .badge-success, .badge-info, .badge-warning, .badge-danger {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
}
.table-responsive {
    border-radius: 10px;
    
}
.table {
    margin-bottom: 0;
}
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}
.table tbody tr:hover {
    background-color: #f1f3f4;
}
.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}
.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}
.alert {
    border-radius: 10px;
    border: none;
}
.pagination .page-link {
    border-radius: 50%;
    margin: 0 2px;
    border: 1px solid #dee2e6;
    color: #007bff;
}
.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}
.card.bg-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}
#estimatedCost {
    color: #28a745;
    font-weight: bold;
}
</style>
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
                                    <h5 class="m-b-10"><i class="feather icon-git-branch mr-2"></i>Request Additional Branches</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Branch Management</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php else: ?>
                                
                                <!-- Current Status Card -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-bar-chart-2 mr-2"></i>Your Current Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span><i class="feather icon-package mr-2"></i><strong>Plan:</strong></span>
                                                    <span class="badge badge-primary badge-pill px-3 py-2"><?= htmlspecialchars($plan_info['name']) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span><i class="feather icon-git-branch mr-2"></i><strong>Included Branches:</strong></span>
                                                    <span class="text-primary font-weight-bold"><?= intval($plan_info['max_branches']) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><i class="feather icon-users mr-2"></i><strong>Current Branches:</strong></span>
                                                    <span class="text-info font-weight-bold"><?= intval($current_branches) ?></span>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span><i class="feather icon-plus-circle mr-2"></i><strong>Additional Branches Purchased:</strong></span>
                                                    <span class="badge badge-success badge-pill px-3 py-2"><?= intval($additional_branches) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span><i class="feather icon-maximize mr-2"></i><strong>Max Allowed:</strong></span>
                                                    <span class="badge badge-info badge-pill px-3 py-2"><?= intval($max_allowed) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><i class="feather icon-check-circle mr-2"></i><strong>Available Slots:</strong></span>
                                                    <span class="badge badge-<?= ($max_allowed - $current_branches) > 0 ? 'success' : 'danger' ?> badge-pill px-3 py-2">
                                                        <?= intval($max_allowed - $current_branches) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (isset($form_success)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($form_success) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($form_error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($form_error) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Request Form -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-plus-square mr-2"></i>Request Additional Branches</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!$can_add): ?>
                                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                                            <i class="feather icon-info mr-2"></i>You have reached the maximum number of branches you can create. Please contact support to discuss plan upgrades.
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <?php else: ?>
                                        <form method="POST" id="requestForm">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="request_branches">

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="num_branches"><i class="feather icon-hash mr-2"></i><strong>Number of Additional Branches</strong></label>
                                                        <input type="number" class="form-control form-control-lg" id="num_branches" name="num_branches"
                                                               min="1" max="<?= intval($max_allowed - $current_branches) ?>" value="1" required
                                                               onchange="updateCost()">
                                                        <small class="form-text text-muted">
                                                            <i class="feather icon-info mr-1"></i>Available: <?= intval($max_allowed - $current_branches) ?> branches
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="billing_cycle"><i class="feather icon-calendar mr-2"></i><strong>Billing Cycle</strong></label>
                                                        <select class="form-control form-control-lg" id="billing_cycle" name="billing_cycle" onchange="updateCost()">
                                                            <option value="monthly">Monthly (<?= number_format($pricing['monthly'], 2) ?> <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?>/branch)</option>
                                                            <option value="quarterly">Quarterly (<?= number_format($pricing['quarterly'], 2) ?> <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?>/branch)</option>
                                                            <option value="yearly">Yearly (<?= number_format($pricing['yearly'], 2) ?> <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?>/branch)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="card bg-light border-0 shadow-sm">
                                                        <div class="card-body">
                                                            <h6 class="text-primary"><i class="feather icon-dollar-sign mr-2"></i><strong>Estimated Cost</strong></h6>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span id="costBreakdown" class="text-muted">1 branch × <?= number_format($pricing['monthly'], 2) ?> <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> = <?= number_format($pricing['monthly'], 2) ?> <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?>/month</span>
                                                                <h5 class="mb-0"><span id="estimatedCost" class="text-success font-weight-bold"><?= number_format($pricing['monthly'], 2) ?> <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?></span></h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group mt-4">
                                                <button type="submit" class="btn btn-primary btn-lg btn-block">
                                                    <i class="feather icon-send mr-2"></i> Submit Request
                                                </button>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Pending Requests -->
                                <?php if (!empty($pending_requests)): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-clock mr-2"></i>Pending Requests <span class="badge badge-warning badge-pill ml-2"><?= count($pending_requests) ?></span></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><i class="feather icon-git-branch mr-1"></i>Branches Requested</th>
                                                        <th><i class="feather icon-dollar-sign mr-1"></i>Estimated Cost</th>
                                                        <th><i class="feather icon-alert-circle mr-1"></i>Status</th>
                                                        <th><i class="feather icon-calendar mr-1"></i>Requested On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pending_requests as $req): ?>
                                                    <tr>
                                                        <td class="font-weight-bold text-primary">+<?= intval($req['requested_additional_branches']) ?></td>
                                                        <td class="text-success font-weight-bold"><?= number_format($req['estimated_monthly_cost'], 2) ?> <?= htmlspecialchars($req['currency']) ?></td>
                                                        <td><span class="badge badge-warning badge-pill px-3 py-1">Pending Review</span></td>
                                                        <td class="text-muted"><?= date('M d, Y', strtotime($req['requested_at'])) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Active Add-ons -->
                                <?php if (!empty($approved_addons)): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-check-circle mr-2"></i>Active Branch Add-ons</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><i class="feather icon-plus mr-1"></i>Additional Branches</th>
                                                        <th><i class="feather icon-tag mr-1"></i>Price/Branch</th>
                                                        <th><i class="feather icon-credit-card mr-1"></i>Total Cost</th>
                                                        <th><i class="feather icon-repeat mr-1"></i>Billing Cycle</th>
                                                        <th><i class="feather icon-activity mr-1"></i>Status</th>
                                                        <th><i class="feather icon-refresh-cw mr-1"></i>Next Renewal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($approved_addons as $addon): ?>
                                                    <tr>
                                                        <td class="font-weight-bold text-info"><?= intval($addon['additional_branches']) ?></td>
                                                        <td class="text-muted"><?= number_format($addon['addon_price_per_branch'], 2) ?> <?= htmlspecialchars($addon['currency']) ?></td>
                                                        <td class="text-success font-weight-bold"><?= number_format($addon['total_addon_cost'], 2) ?> <?= htmlspecialchars($addon['currency']) ?></td>
                                                        <td class="text-primary"><?= ucfirst($addon['billing_cycle']) ?></td>
                                                        <td><span class="badge badge-success badge-pill px-3 py-1"><?= ucfirst($addon['status']) ?></span></td>
                                                        <td class="text-muted"><?= $addon['next_renewal_date'] ? date('M d, Y', strtotime($addon['next_renewal_date'])) : '-' ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Payment History -->
                                <?php if (!empty($addon_payment_history)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h5><i class="feather icon-history mr-2"></i>Add-on Payment History <span class="badge badge-info badge-pill ml-2"><?= count($addon_payment_history) ?></span></h5>
                                            </div>
                                            <div class="col-md-6">
                                                <form method="GET" class="form-inline float-right" id="searchForm">
                                                    <div class="input-group">
                                                        <input type="text" name="search" class="form-control" placeholder="Search payments..." value="<?= htmlspecialchars($search_query) ?>" style="width: 250px;">
                                                        <div class="input-group-append">
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="feather icon-search"></i>
                                                            </button>
                                                            <?php if (!empty($search_query)): ?>
                                                            <a href="request_branch_addon.php" class="btn btn-outline-secondary ml-1">
                                                                <i class="feather icon-x"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($paginated_history)): ?>
                                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                                            <i class="feather icon-info mr-2"></i>
                                            <?php if (!empty($search_query)): ?>
                                            No results found for "<strong><?= htmlspecialchars($search_query) ?></strong>"
                                            <?php else: ?>
                                            No payment history available yet.
                                            <?php endif; ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><i class="feather icon-git-branch mr-1"></i>Branches</th>
                                                        <th><i class="feather icon-dollar-sign mr-1"></i>Amount</th>
                                                        <th><i class="feather icon-calendar mr-1"></i>Period</th>
                                                        <th><i class="feather icon-check-circle mr-1"></i>Status</th>
                                                        <th><i class="feather icon-clock mr-1"></i>Payment Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($paginated_history as $payment): ?>
                                                    <tr>
                                                        <td class="font-weight-bold text-info"><?= intval($payment['additional_branches']) ?></td>
                                                        <td class="text-success font-weight-bold"><?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                                        <td class="text-muted small"><?= date('M d, Y', strtotime($payment['period_start'])) ?> - <?= date('M d, Y', strtotime($payment['period_end'])) ?></td>
                                                        <td><span class="badge badge-<?= $payment['status'] === 'completed' ? 'success' : 'warning' ?> badge-pill px-3 py-1"><?= ucfirst($payment['status']) ?></span></td>
                                                        <td class="text-muted"><?= $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-' ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Payment history pagination" class="mt-4">
                                            <ul class="pagination pagination-lg justify-content-center">
                                                <!-- Previous Button -->
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="request_branch_addon.php?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" aria-label="Previous">
                                                        <span aria-hidden="true"><i class="feather icon-chevron-left"></i></span>
                                                    </a>
                                                </li>

                                                <!-- Page Numbers -->
                                                <?php
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);

                                                if ($start_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="request_branch_addon.php?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <?php endif; ?>

                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="request_branch_addon.php?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                                <?php endfor; ?>

                                                <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="request_branch_addon.php?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                                </li>
                                                <?php endif; ?>

                                                <!-- Next Button -->
                                                <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="request_branch_addon.php?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" aria-label="Next">
                                                        <span aria-hidden="true"><i class="feather icon-chevron-right"></i></span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                        <div class="text-center mt-3 text-muted">
                                            <small>Page <strong><?= $current_page ?></strong> of <strong><?= $total_pages ?></strong> | Showing <strong><?= count($paginated_history) ?></strong> of <strong><?= $total_items ?></strong> records</small>
                                        </div>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php endif; ?>
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

<script>
// Dynamic pricing from database
const pricingData = {
    monthly: <?= $pricing['monthly'] ?>,
    quarterly: <?= $pricing['quarterly'] ?>,
    yearly: <?= $pricing['yearly'] ?>,
    currency: '<?= isset($plan_info['currency']) ? htmlspecialchars($plan_info['currency']) : 'USD' ?>'
};

function updateCost() {
    const numBranches = parseInt(document.getElementById('num_branches').value) || 1;
    const billingCycle = document.getElementById('billing_cycle').value;
    const currency = pricingData.currency;
    
    let pricePerBranch = pricingData[billingCycle] || pricingData.monthly;
    let costText = '';
    const totalCost = numBranches * pricePerBranch;
    
    switch(billingCycle) {
        case 'quarterly':
            costText = `${numBranches} branch${numBranches > 1 ? 'es' : ''} × ${pricingData.quarterly.toFixed(2)} ${currency} (3 months) = ${totalCost.toFixed(2)} ${currency}`;
            break;
        case 'yearly':
            costText = `${numBranches} branch${numBranches > 1 ? 'es' : ''} × ${pricingData.yearly.toFixed(2)} ${currency} (12 months) = ${totalCost.toFixed(2)} ${currency}`;
            break;
        default:
            costText = `${numBranches} branch${numBranches > 1 ? 'es' : ''} × ${pricingData.monthly.toFixed(2)} ${currency} = ${totalCost.toFixed(2)} ${currency}/month`;
    }
    
    document.getElementById('costBreakdown').textContent = costText;
    document.getElementById('estimatedCost').textContent = `${totalCost.toFixed(2)} ${currency}`;
}

// Initialize cost on page load
document.addEventListener('DOMContentLoaded', updateCost);
</script>
</body>
</html>
