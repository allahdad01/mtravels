<?php
/**
 * Request User Add-on - Tenant Interface
 * 
 * Allows tenants to request additional user slots beyond their plan limits.
 */

require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../includes/UserAddonManager.php';
require_once '../admin/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Initialize UserAddonManager
$userAddonManager = new UserAddonManager($pdo, $tenant_id);

// Get tenant usage stats
$usageStats = $userAddonManager->getUsageStats();
$plan = $usageStats['plan'];

// Get pricing configuration
$addonPricing = $userAddonManager->getAddonPricing();

// Get currency from plan
$currency = $plan['currency'] ?? 'USD';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_addon'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = __('invalid_csrf_token');
    } else {
        $num_users = intval($_POST['num_users'] ?? 0);
        $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
        
        if ($num_users <= 0) {
            $error = __('invalid_number_of_users');
        } elseif ($num_users > 100) {
            $error = __('max_users_per_request_exceeded');
        } else {
            $result = $userAddonManager->requestAdditionalUsers($tenant_id, $num_users, $billing_cycle);
            
            if ($result['success']) {
                $success = sprintf(__('user_addon_request_submitted'), $num_users, $result['estimated_cost'], $result['currency']);
                // Refresh stats
                $usageStats = $userAddonManager->getUsageStats();
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get tenant's pending requests and active addons
$all_pending_requests = $userAddonManager->getTenantAddonRequests($tenant_id, 'pending');
$all_active_addons = $userAddonManager->getActiveUserAddons($tenant_id);

// Pagination for pending requests (5 per page)
$pending_items_per_page = 5;
$pending_current_page = intval($_GET['pending_page'] ?? 1);
$pending_total_items = count($all_pending_requests);
$pending_total_pages = ceil($pending_total_items / $pending_items_per_page);
$pending_current_page = max(1, min($pending_current_page, $pending_total_pages));
$pending_offset = ($pending_current_page - 1) * $pending_items_per_page;
$pending_requests = array_slice($all_pending_requests, $pending_offset, $pending_items_per_page);

// Pagination for active addons (5 per page)
$addon_items_per_page = 5;
$addon_current_page = intval($_GET['addon_page'] ?? 1);
$addon_total_items = count($all_active_addons);
$addon_total_pages = ceil($addon_total_items / $addon_items_per_page);
$addon_current_page = max(1, min($addon_current_page, $addon_total_pages));
$addon_offset = ($addon_current_page - 1) * $addon_items_per_page;
$active_addons = array_slice($all_active_addons, $addon_offset, $addon_items_per_page);

$page_title = __('request_more_users');
include 'header.php';
?>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-users mr-2"></i><?php echo __('request_more_users'); ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('request_additional_user_slots'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="users.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_add_employee'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Current Usage Card -->
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-bar-chart-2 mr-2"></i><?php echo __('current_usage'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center mb-4">
                                                    <div class="h2 font-weight-bold text-primary">
                                                        <i class="feather icon-users mr-2"></i><?php echo $usageStats['current_users']; ?>
                                                        <span class="text-muted h4">/ <?php echo $usageStats['max_users']; ?></span>
                                                    </div>
                                                    <p class="text-muted mb-0"><?php echo __('users_used'); ?></p>
                                                </div>

                                                <div class="progress mb-4" style="height: 30px; border-radius: 15px;">
                                                    <div class="progress-bar <?php echo $usageStats['usage_percentage'] >= 90 ? 'bg-danger' : ($usageStats['usage_percentage'] >= 75 ? 'bg-warning' : 'bg-success'); ?>"
                                                         role="progressbar"
                                                         style="width: <?php echo min(100, $usageStats['usage_percentage']); ?>%; border-radius: 15px;">
                                                        <span class="font-weight-bold"><?php echo $usageStats['usage_percentage']; ?>%</span>
                                                    </div>
                                                </div>

                                                <hr class="my-4">

                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <div class="h4 mb-1 font-weight-bold text-info"><?php echo $usageStats['base_users']; ?></div>
                                                        <small class="text-muted"><i class="feather icon-home mr-1"></i><?php echo __('base_users'); ?></small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="h4 mb-1 font-weight-bold text-success">+<?php echo $usageStats['additional_users']; ?></div>
                                                        <small class="text-muted"><i class="feather icon-plus-circle mr-1"></i><?php echo __('addon_users'); ?></small>
                                                    </div>
                                                </div>

                                                <hr class="my-4">

                                                <div class="text-center">
                                                    <span class="badge badge-info badge-pill px-3 py-2 h6">
                                                        <i class="feather icon-package mr-1"></i><?php echo htmlspecialchars($plan['name'] ?? __('no_plan')); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Pricing Information -->
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h5><i class="feather icon-dollar-sign mr-2"></i><?php echo __('addon_pricing'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless">
                                                    <tbody>
                                                        <tr>
                                                            <td class="py-3"><i class="feather icon-calendar mr-2 text-primary"></i><?php echo __('monthly'); ?></td>
                                                            <td class="text-right py-3 font-weight-bold text-success h5"><?php echo number_format($addonPricing['monthly'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="py-3"><i class="feather icon-calendar mr-2 text-warning"></i><?php echo __('quarterly'); ?></td>
                                                            <td class="text-right py-3 font-weight-bold text-success h5"><?php echo number_format($addonPricing['quarterly'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="py-3"><i class="feather icon-calendar mr-2 text-info"></i><?php echo __('yearly'); ?></td>
                                                            <td class="text-right py-3 font-weight-bold text-success h5"><?php echo number_format($addonPricing['yearly'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <div class="alert alert-light border mt-3">
                                                    <p class="text-muted mb-0 text-center">
                                                        <i class="feather icon-info mr-2"></i><?php echo __('per_user_per_billing_cycle'); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Request Form -->
                                    <div class="col-md-8">
                                        <?php if (isset($error)): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <?php echo $error; ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($success)): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php echo $success; ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <?php endif; ?>

                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-user-plus mr-2"></i><?php echo __('request_additional_users'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="request_addon" value="1">

                                                    <div class="form-group">
                                                        <label for="num_users"><i class="feather icon-hash mr-2"></i><?php echo __('number_of_additional_users'); ?> <span class="text-danger">*</span></label>
                                                        <input type="number" class="form-control form-control-lg" id="num_users" name="num_users"
                                                               min="1" max="100" value="1" required
                                                               onchange="updateEstimatedCost()">
                                                        <small class="form-text text-muted">
                                                            <i class="feather icon-info mr-1"></i><?php echo __('max_100_users_per_request'); ?>
                                                        </small>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="billing_cycle"><i class="feather icon-repeat mr-2"></i><?php echo __('billing_cycle'); ?> <span class="text-danger">*</span></label>
                                                        <select class="form-control form-control-lg" id="billing_cycle" name="billing_cycle" required onchange="updateEstimatedCost()">
                                                            <option value="monthly"><?php echo __('monthly'); ?> - <?php echo number_format($addonPricing['monthly'], 2); ?> <?php echo htmlspecialchars($currency); ?>/<?php echo __('user'); ?></option>
                                                            <option value="quarterly"><?php echo __('quarterly'); ?> - <?php echo number_format($addonPricing['quarterly'], 2); ?> <?php echo htmlspecialchars($currency); ?>/<?php echo __('user'); ?></option>
                                                            <option value="yearly"><?php echo __('yearly'); ?> - <?php echo number_format($addonPricing['yearly'], 2); ?> <?php echo htmlspecialchars($currency); ?>/<?php echo __('user'); ?></option>
                                                        </select>
                                                    </div>

                                                    <div class="alert alert-info border-0 shadow-sm">
                                                        <h6 class="text-primary mb-3"><i class="feather icon-calculator mr-2"></i><?php echo __('estimated_cost'); ?></h6>
                                                        <div class="h3 mb-2 font-weight-bold">
                                                            <span id="estimated_cost"><?php echo number_format($addonPricing['monthly'], 2); ?></span> <?php echo htmlspecialchars($currency); ?>
                                                            <span class="text-muted h5" id="cost_period">/ <?php echo __('month'); ?></span>
                                                        </div>
                                                        <small class="text-muted" id="cost_breakdown">
                                                            1 user × <?php echo number_format($addonPricing['monthly'], 2); ?> <?php echo htmlspecialchars($currency); ?> = <?php echo number_format($addonPricing['monthly'], 2); ?> <?php echo htmlspecialchars($currency); ?>/<?php echo __('month'); ?>
                                                        </small>
                                                    </div>

                                                    <div class="form-group mb-0 mt-4">
                                                        <button type="submit" class="btn btn-primary btn-lg mr-3">
                                                            <i class="feather icon-send mr-2"></i><?php echo __('submit_request'); ?>
                                                        </button>
                                                        <a href="add_employee.php" class="btn btn-secondary btn-lg">
                                                            <i class="feather icon-x mr-2"></i><?php echo __('cancel'); ?>
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Pending Requests -->
                                         <?php if (!empty($all_pending_requests)): ?>
                                         <div class="card mt-3">
                                             <div class="card-header">
                                                 <h5><i class="feather icon-clock mr-2"></i><?php echo __('pending_requests'); ?> <span class="badge badge-warning badge-pill ml-2"><?php echo $pending_total_items; ?></span></h5>
                                             </div>
                                             <div class="card-body table-responsive">
                                                 <table class="table table-hover">
                                                     <thead>
                                                         <tr>
                                                             <th><i class="feather icon-user-plus mr-1"></i><?php echo __('requested_users'); ?></th>
                                                             <th><i class="feather icon-dollar-sign mr-1"></i><?php echo __('estimated_cost'); ?></th>
                                                             <th><i class="feather icon-calendar mr-1"></i><?php echo __('requested_at'); ?></th>
                                                             <th><i class="feather icon-alert-circle mr-1"></i><?php echo __('status'); ?></th>
                                                         </tr>
                                                     </thead>
                                                     <tbody>
                                                         <?php foreach ($pending_requests as $request): ?>
                                                         <tr>
                                                             <td>
                                                                 <span class="badge badge-success badge-pill px-3 py-2 font-weight-bold">
                                                                     +<?php echo intval($request['requested_additional_users']); ?>
                                                                 </span>
                                                             </td>
                                                             <td class="text-success font-weight-bold h6">
                                                                 <?php echo number_format($request['estimated_monthly_cost'], 2); ?> <?php echo htmlspecialchars($currency); ?>
                                                             </td>
                                                             <td class="text-muted"><?php echo date('M d, Y H:i', strtotime($request['requested_at'])); ?></td>
                                                             <td>
                                                                 <span class="badge badge-warning badge-pill px-3 py-1">
                                                                     <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                                                 </span>
                                                             </td>
                                                         </tr>
                                                         <?php endforeach; ?>
                                                     </tbody>
                                                 </table>
                                             </div>

                                             <!-- Pagination for Pending Requests -->
                                             <?php if ($pending_total_pages > 1): ?>
                                             <nav aria-label="Pending requests pagination" class="mt-2 mb-0">
                                             <ul class="pagination justify-content-center mb-0" style="padding: 1rem;">
                                             <li class="page-item <?= $pending_current_page === 1 ? 'disabled' : '' ?>">
                                             <a class="page-link" href="request_user_addon.php?pending_page=<?= $pending_current_page - 1 ?>">
                                                 <i class="feather icon-chevron-left"></i> Prev
                                             </a>
                                             </li>
                                             <?php 
                                             $p_start = max(1, $pending_current_page - 2);
                                             $p_end = min($pending_total_pages, $pending_current_page + 2);
                                             if ($p_start > 1): ?>
                                             <li class="page-item"><a class="page-link" href="request_user_addon.php?pending_page=1">1</a></li>
                                             <?php if ($p_start > 2): ?>
                                             <li class="page-item disabled"><span class="page-link">...</span></li>
                                             <?php endif; ?>
                                             <?php endif; ?>
                                             <?php for ($i = $p_start; $i <= $p_end; $i++): ?>
                                             <li class="page-item <?= $i === $pending_current_page ? 'active' : '' ?>">
                                             <a class="page-link" href="request_user_addon.php?pending_page=<?= $i ?>"><?= $i ?></a>
                                             </li>
                                             <?php endfor; ?>
                                             <?php if ($p_end < $pending_total_pages): ?>
                                             <?php if ($p_end < $pending_total_pages - 1): ?>
                                             <li class="page-item disabled"><span class="page-link">...</span></li>
                                             <?php endif; ?>
                                             <li class="page-item"><a class="page-link" href="request_user_addon.php?pending_page=<?= $pending_total_pages ?>"><?= $pending_total_pages ?></a></li>
                                             <?php endif; ?>
                                             <li class="page-item <?= $pending_current_page === $pending_total_pages ? 'disabled' : '' ?>">
                                             <a class="page-link" href="request_user_addon.php?pending_page=<?= $pending_current_page + 1 ?>">
                                                 Next <i class="feather icon-chevron-right"></i>
                                             </a>
                                             </li>
                                             </ul>
                                             </nav>
                                             <div class="text-center text-muted small" style="padding: 0 1rem 1rem 1rem;">
                                             Page <?= $pending_current_page ?> of <?= $pending_total_pages ?>
                                             </div>
                                             <?php endif; ?>
                                         </div>
                                         <?php endif; ?>

                                        <!-- Active Add-ons -->
                                         <?php if (!empty($all_active_addons)): ?>
                                         <div class="card mt-3">
                                             <div class="card-header">
                                                 <h5><i class="feather icon-check-circle mr-2"></i><?php echo __('active_user_addons'); ?> <span class="badge badge-success badge-pill ml-2"><?php echo $addon_total_items; ?></span></h5>
                                             </div>
                                             <div class="card-body table-responsive">
                                                 <table class="table table-hover">
                                                     <thead>
                                                         <tr>
                                                             <th><i class="feather icon-users mr-1"></i><?php echo __('additional_users'); ?></th>
                                                             <th><i class="feather icon-tag mr-1"></i><?php echo __('cost_per_user'); ?></th>
                                                             <th><i class="feather icon-credit-card mr-1"></i><?php echo __('total_cost'); ?></th>
                                                             <th><i class="feather icon-repeat mr-1"></i><?php echo __('billing_cycle'); ?></th>
                                                             <th><i class="feather icon-refresh-cw mr-1"></i><?php echo __('renewal_date'); ?></th>
                                                         </tr>
                                                     </thead>
                                                     <tbody>
                                                         <?php foreach ($active_addons as $addon): ?>
                                                         <tr>
                                                             <td>
                                                                 <span class="badge badge-success badge-pill px-3 py-2 font-weight-bold">
                                                                     +<?php echo intval($addon['additional_users']); ?>
                                                                 </span>
                                                             </td>
                                                             <td class="text-muted font-weight-bold"><?php echo number_format($addon['addon_price_per_user'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                                                             <td class="text-success font-weight-bold h6"><?php echo number_format($addon['total_addon_cost'], 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                                                             <td class="text-primary font-weight-bold"><?php echo ucfirst(htmlspecialchars($addon['billing_cycle'])); ?></td>
                                                             <td class="text-muted">
                                                                 <?php echo $addon['next_renewal_date'] ? date('M d, Y', strtotime($addon['next_renewal_date'])) : '<span class="text-danger">-</span>'; ?>
                                                             </td>
                                                         </tr>
                                                         <?php endforeach; ?>
                                                     </tbody>
                                                 </table>
                                             </div>

                                             <!-- Pagination for Active Add-ons -->
                                             <?php if ($addon_total_pages > 1): ?>
                                             <nav aria-label="Active addons pagination" class="mt-2 mb-0">
                                             <ul class="pagination justify-content-center mb-0" style="padding: 1rem;">
                                             <li class="page-item <?= $addon_current_page === 1 ? 'disabled' : '' ?>">
                                             <a class="page-link" href="request_user_addon.php?addon_page=<?= $addon_current_page - 1 ?>">
                                                 <i class="feather icon-chevron-left"></i> Prev
                                             </a>
                                             </li>
                                             <?php 
                                             $a_start = max(1, $addon_current_page - 2);
                                             $a_end = min($addon_total_pages, $addon_current_page + 2);
                                             if ($a_start > 1): ?>
                                             <li class="page-item"><a class="page-link" href="request_user_addon.php?addon_page=1">1</a></li>
                                             <?php if ($a_start > 2): ?>
                                             <li class="page-item disabled"><span class="page-link">...</span></li>
                                             <?php endif; ?>
                                             <?php endif; ?>
                                             <?php for ($i = $a_start; $i <= $a_end; $i++): ?>
                                             <li class="page-item <?= $i === $addon_current_page ? 'active' : '' ?>">
                                             <a class="page-link" href="request_user_addon.php?addon_page=<?= $i ?>"><?= $i ?></a>
                                             </li>
                                             <?php endfor; ?>
                                             <?php if ($a_end < $addon_total_pages): ?>
                                             <?php if ($a_end < $addon_total_pages - 1): ?>
                                             <li class="page-item disabled"><span class="page-link">...</span></li>
                                             <?php endif; ?>
                                             <li class="page-item"><a class="page-link" href="request_user_addon.php?addon_page=<?= $addon_total_pages ?>"><?= $addon_total_pages ?></a></li>
                                             <?php endif; ?>
                                             <li class="page-item <?= $addon_current_page === $addon_total_pages ? 'disabled' : '' ?>">
                                             <a class="page-link" href="request_user_addon.php?addon_page=<?= $addon_current_page + 1 ?>">
                                                 Next <i class="feather icon-chevron-right"></i>
                                             </a>
                                             </li>
                                             </ul>
                                             </nav>
                                             <div class="text-center text-muted small" style="padding: 0 1rem 1rem 1rem;">
                                             Page <?= $addon_current_page ?> of <?= $addon_total_pages ?>
                                             </div>
                                             <?php endif; ?>
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

    <style>
    /* Enhanced custom styles for better layout and design */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .progress {
        border-radius: 15px;
        
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }

    .progress-bar {
        transition: width 0.6s ease;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
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
        padding: 1rem;
    }

    .table tbody tr:hover {
        background-color: #f1f3f4;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        padding: 0.75rem;
    }

    .form-control:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    #estimated_cost {
        color: #28a745;
        font-weight: bold;
    }

    .h2 {
        font-size: 2.5rem;
    }

    .h4 {
        font-size: 1.5rem;
    }

    .h5 {
        font-size: 1.25rem;
    }

    .h6 {
        font-size: 1rem;
    }
    </style>

    <script>
        // Pricing per billing cycle
        const pricing = {
            monthly: <?php echo $addonPricing['monthly']; ?>,
            quarterly: <?php echo $addonPricing['quarterly']; ?>,
            yearly: <?php echo $addonPricing['yearly']; ?>
        };
        
        const currency = '<?php echo htmlspecialchars($currency); ?>';
        
        const billingCycleLabels = {
            monthly: '<?php echo __("month"); ?>',
            quarterly: '<?php echo __("quarter"); ?>',
            yearly: '<?php echo __("year"); ?>'
        };
        
        function updateEstimatedCost() {
            const numUsers = parseInt(document.getElementById('num_users').value) || 1;
            const billingCycle = document.getElementById('billing_cycle').value;
            const pricePerUser = pricing[billingCycle];
            const totalCost = numUsers * pricePerUser;
            
            document.getElementById('estimated_cost').textContent = totalCost.toFixed(2);
            document.getElementById('cost_period').textContent = '/ ' + billingCycleLabels[billingCycle];
            document.getElementById('cost_breakdown').textContent =
                numUsers + ' user' + (numUsers > 1 ? 's' : '') + ' × ' + pricePerUser.toFixed(2) + ' ' + currency +
                ' = ' + totalCost.toFixed(2) + ' ' + currency + '/' + billingCycleLabels[billingCycle];
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateEstimatedCost();
        });
    </script>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
