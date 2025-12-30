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
require_once '../includes/header.php';
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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
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
                                    <h5 class="m-b-10">Request Additional Branches</h5>
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
                                        <h5>Your Current Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span><strong>Plan:</strong></span>
                                                    <span class="badge-primary"><?= htmlspecialchars($plan_info['name']) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span><strong>Included Branches:</strong></span>
                                                    <span><?= intval($plan_info['max_branches']) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span><strong>Current Branches:</strong></span>
                                                    <span><?= intval($current_branches) ?></span>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span><strong>Additional Branches Purchased:</strong></span>
                                                    <span class="badge-success"><?= intval($additional_branches) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span><strong>Max Allowed:</strong></span>
                                                    <span class="badge-info"><?= intval($max_allowed) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span><strong>Available Slots:</strong></span>
                                                    <span class="badge-<?= ($max_allowed - $current_branches) > 0 ? 'success' : 'danger' ?>">
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
                                        <h5>Request Additional Branches</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!$can_add): ?>
                                        <div class="alert alert-info">
                                            You have reached the maximum number of branches you can create. Please contact support to discuss plan upgrades.
                                        </div>
                                        <?php else: ?>
                                        <form method="POST" id="requestForm">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="request_branches">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="num_branches"><strong>Number of Additional Branches</strong></label>
                                                        <input type="number" class="form-control" id="num_branches" name="num_branches" 
                                                               min="1" max="<?= intval($max_allowed - $current_branches) ?>" value="1" required
                                                               onchange="updateCost()">
                                                        <small class="form-text text-muted">
                                                            Available: <?= intval($max_allowed - $current_branches) ?> branches
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="billing_cycle"><strong>Billing Cycle</strong></label>
                                                        <select class="form-control" id="billing_cycle" name="billing_cycle" onchange="updateCost()">
                                                            <option value="monthly">Monthly (<?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> <?= number_format($pricing['monthly'], 2) ?>/branch)</option>
                                                            <option value="quarterly">Quarterly (<?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> <?= number_format($pricing['quarterly'], 2) ?>/branch)</option>
                                                            <option value="yearly">Yearly (<?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> <?= number_format($pricing['yearly'], 2) ?>/branch)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="card bg-light">
                                                        <div class="card-body">
                                                            <h6><strong>Estimated Cost</strong></h6>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span id="costBreakdown">1 branch × <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> <?= number_format($pricing['monthly'], 2) ?> = <?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> <?= number_format($pricing['monthly'], 2) ?>/month</span>
                                                                <h5><span id="estimatedCost"><?= htmlspecialchars($plan_info['currency'] ?? 'USD') ?> <?= number_format($pricing['monthly'], 2) ?></span></h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group mt-3">
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="feather icon-send mr-1"></i> Submit Request
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
                                        <h5>Pending Requests <span class="badge-warning"><?= count($pending_requests) ?></span></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Branches Requested</th>
                                                        <th>Estimated Cost</th>
                                                        <th>Status</th>
                                                        <th>Requested On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pending_requests as $req): ?>
                                                    <tr>
                                                        <td>+<?= intval($req['requested_additional_branches']) ?></td>
                                                        <td><?= number_format($req['estimated_monthly_cost'], 2) ?> <?= htmlspecialchars($req['currency']) ?></td>
                                                        <td><span class="badge-warning">Pending Review</span></td>
                                                        <td><?= date('M d, Y', strtotime($req['requested_at'])) ?></td>
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
                                        <h5>Active Branch Add-ons</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Additional Branches</th>
                                                        <th>Price/Branch</th>
                                                        <th>Total Cost</th>
                                                        <th>Billing Cycle</th>
                                                        <th>Status</th>
                                                        <th>Next Renewal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($approved_addons as $addon): ?>
                                                    <tr>
                                                        <td><?= intval($addon['additional_branches']) ?></td>
                                                        <td>$<?= number_format($addon['addon_price_per_branch'], 2) ?> <?= htmlspecialchars($addon['currency']) ?></td>
                                                        <td>$<?= number_format($addon['total_addon_cost'], 2) ?></td>
                                                        <td><?= ucfirst($addon['billing_cycle']) ?></td>
                                                        <td><span class="badge-success"><?= ucfirst($addon['status']) ?></span></td>
                                                        <td><?= $addon['next_renewal_date'] ? date('M d, Y', strtotime($addon['next_renewal_date'])) : '-' ?></td>
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
                                                <h5>Add-on Payment History <span class="badge badge-info"><?= count($addon_payment_history) ?></span></h5>
                                            </div>
                                            <div class="col-md-6">
                                                <form method="GET" class="form-inline float-right" id="searchForm">
                                                    <input type="text" name="search" class="form-control form-control-sm mr-2" 
                                                           placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>" style="width: 200px;">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="feather icon-search"></i>
                                                    </button>
                                                    <?php if (!empty($search_query)): ?>
                                                    <a href="request_branch_addon.php" class="btn btn-sm btn-secondary ml-2">
                                                        <i class="feather icon-x"></i> Clear
                                                    </a>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($paginated_history)): ?>
                                        <div class="alert alert-info" role="alert">
                                            <i class="feather icon-info mr-2"></i>
                                            <?php if (!empty($search_query)): ?>
                                            No results found for "<strong><?= htmlspecialchars($search_query) ?></strong>"
                                            <?php else: ?>
                                            No payment history available yet.
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Branches</th>
                                                        <th>Amount</th>
                                                        <th>Period</th>
                                                        <th>Status</th>
                                                        <th>Payment Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($paginated_history as $payment): ?>
                                                    <tr>
                                                        <td><?= intval($payment['additional_branches']) ?></td>
                                                        <td><?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($payment['period_start'])) ?> - <?= date('M d, Y', strtotime($payment['period_end'])) ?></td>
                                                        <td><span class="badge badge-<?= $payment['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($payment['status']) ?></span></td>
                                                        <td><?= $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-' ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Payment history pagination" class="mt-3">
                                            <ul class="pagination justify-content-center mb-0">
                                                <!-- Previous Button -->
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="request_branch_addon.php?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        <i class="feather icon-chevron-left"></i> Previous
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
                                                    <a class="page-link" href="request_branch_addon.php?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        Next <i class="feather icon-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                        <div class="text-center mt-2 text-muted small">
                                            Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($paginated_history) ?> of <?= $total_items ?> records
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
            costText = `${numBranches} branch${numBranches > 1 ? 'es' : ''} × ${currency} ${pricingData.quarterly.toFixed(2)} (3 months) = ${currency} ${totalCost.toFixed(2)}`;
            break;
        case 'yearly':
            costText = `${numBranches} branch${numBranches > 1 ? 'es' : ''} × ${currency} ${pricingData.yearly.toFixed(2)} (12 months) = ${currency} ${totalCost.toFixed(2)}`;
            break;
        default:
            costText = `${numBranches} branch${numBranches > 1 ? 'es' : ''} × ${currency} ${pricingData.monthly.toFixed(2)} = ${currency} ${totalCost.toFixed(2)}/month`;
    }
    
    document.getElementById('costBreakdown').textContent = costText;
    document.getElementById('estimatedCost').textContent = `${currency} ${totalCost.toFixed(2)}`;
}

// Initialize cost on page load
document.addEventListener('DOMContentLoaded', updateCost);
</script>
</body>
</html>
