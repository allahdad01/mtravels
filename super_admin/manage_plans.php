<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
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

// ── CURRENCY HELPER ───────────────────────────────────────────────────────────
function getCurrencySymbol($currency) {
    $symbols = [
        'USD' => '$',
        'AFN' => '؋',
        'AFS' => '؋',  // Legacy support
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'JPY' => '¥',
        'CNY' => '¥',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NZD' => 'NZ$',
    ];
    return $symbols[strtoupper($currency)] ?? '$';
}

// Get default currency from platform settings
$defaultCurrency = 'AFN';
try {
    $stmt = $pdo->prepare("SELECT `value` FROM platform_settings WHERE `key` = 'default_currency'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result && !empty($result['value'])) {
        $defaultCurrency = $result['value'];
    }
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
}

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

// Count total items
$count_query = "SELECT COUNT(*) as total FROM plans WHERE 1=1";
$filter_params = [];

if (!empty($search_query)) {
    $count_query .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated plans
$query = "SELECT name, description, features, price, currency, max_users, max_branches, trial_days, status, created_at FROM plans WHERE 1=1";

if (!empty($search_query)) {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
}
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$plans = $stmt->fetchAll();
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
                            <h5 class="mb-0"><i class="feather icon-package mr-2"></i><?= __('manage_plans') ?></h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_subscription_plans'); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
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
                                <?php if (isset($_GET['success'])): ?>
                                <div class="sa-alert sa-alert-success">
                                    <div class="sa-alert-icon">✓</div>
                                    <div class="sa-alert-content">
                                        <?php
                                        switch ($_GET['success']) {
                                            case 'plan_created':
                                                echo __('plan_created_successfully');
                                                break;
                                            case 'plan_updated':
                                                echo __('plan_updated_successfully');
                                                break;
                                            default:
                                                echo __('operation_completed_successfully');
                                        }
                                        ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($_GET['error'])): ?>
                                <div class="sa-alert sa-alert-danger">
                                    <div class="sa-alert-icon">⚠</div>
                                    <div class="sa-alert-content">
                                        <?= htmlspecialchars($_GET['error']) ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                                <?php endif; ?>

                                <!-- Search and Filter Bar -->
                                <div class="sa-card" style="margin-bottom: 20px;">
                                    <div class="sa-card-body">
                                        <form method="GET" action="manage_plans.php" class="sa-search-filter">
                                            <div class="sa-search-group">
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search plans by name or description..." value="<?= htmlspecialchars($search_query) ?>">
                                                <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                                <?php if (!empty($search_query)): ?>
                                                <a href="manage_plans.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Plans Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2>Plans Overview</h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> plans</p>
                                    </div>
                                    <button class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createPlanModal">
                                        <span style="margin-right: 6px;">+</span>Create Plan
                                    </button>
                                </div>

                                <!-- Stats Cards -->
                                <div class="sa-stat-grid">
                                    <div class="sa-stat">
                                        <div class="sa-stat-top">
                                            <div>
                                                <div class="sa-stat-val"><?= $total_items ?></div>
                                                <div class="sa-stat-name">Total Plans</div>
                                            </div>
                                            <div class="sa-stat-icon si-blue">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m0 10v10l8 4"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sa-stat">
                                        <div class="sa-stat-top">
                                            <div>
                                                <div class="sa-stat-val" style="color:var(--green)"><?= count(array_filter($plans, fn($p) => $p['status'] === 'active')) ?></div>
                                                <div class="sa-stat-name">Active Plans</div>
                                            </div>
                                            <div class="sa-stat-icon si-green">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sa-stat">
                                        <div class="sa-stat-top">
                                            <div>
                                                <div class="sa-stat-val"><?php echo number_format(array_sum(array_column($plans, 'max_users'))); ?></div>
                                                <div class="sa-stat-name">Total Max Users</div>
                                            </div>
                                            <div class="sa-stat-icon si-blue">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0H9m6 0H9m6 0H9M4 12h16"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sa-stat">
                                        <div class="sa-stat-top">
                                            <div>
                                                <div class="sa-stat-val"><?= getCurrencySymbol($defaultCurrency) ?><?php echo number_format(array_sum(array_column($plans, 'price')), 0); ?></div>
                                                <div class="sa-stat-name">Total Value</div>
                                            </div>
                                            <div class="sa-stat-icon si-green">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Plans Grid -->
                                <?php if (!empty($plans)): ?>
                                <div class="sa-3col">
                                    <?php foreach ($plans as $plan):
                                        $features = json_decode($plan['features'], true);
                                        $status_pill = $plan['status'] === 'active' ? 'pill-green' : 'pill-amber';
                                    ?>
                                    <div class="plan-card">
                                        <div class="pc-header">
                                            <div class="pc-title">
                                                <h3><?= htmlspecialchars($plan['name']) ?></h3>
                                            </div>
                                            <span class="pill <?= $status_pill ?>"><?= htmlspecialchars($plan['status']) ?></span>
                                        </div>
                                        
                                        <div class="pc-body">
                                             <div class="pc-price">
                                                 <span class="price-value"><?= getCurrencySymbol($plan['currency'] ?? $defaultCurrency) ?><?= number_format($plan['price'], 2) ?></span>
                                                 <span class="price-label">per month</span>
                                             </div>
                                            <p class="pc-description"><?= htmlspecialchars(substr($plan['description'], 0, 80)) ?>...</p>
                                            <div class="pc-info">
                                                <div class="pc-info-row">
                                                    <span>Max Users:</span>
                                                    <strong><?= htmlspecialchars($plan['max_users']) ?></strong>
                                                </div>
                                                <div class="pc-info-row">
                                                    <span>Max Branches:</span>
                                                    <strong><?= htmlspecialchars($plan['max_branches'] ?? 0) ?></strong>
                                                </div>
                                                <div class="pc-info-row">
                                                    <span>Trial Days:</span>
                                                    <strong><?= htmlspecialchars($plan['trial_days']) ?></strong>
                                                </div>
                                            </div>
                                            <div class="pc-features">
                                                <div class="features-label">Features:</div>
                                                <div class="features-list">
                                                    <?php
                                                    if (is_array($features) && !empty($features)) {
                                                        foreach (array_slice($features, 0, 2) as $feature) {
                                                            echo '<span class="feature-tag">' . htmlspecialchars($feature) . '</span>';
                                                        }
                                                        if (count($features) > 2) {
                                                            echo '<button class="feature-tag more view-all-features" data-plan="' . htmlspecialchars($plan['name']) . '" data-features=\'' . htmlspecialchars(json_encode($features)) . '\' onclick="viewAllFeatures(this)">+' . (count($features) - 2) . ' more</button>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pc-actions">
                                            <button class="sa-btn sa-btn-small sa-btn-primary" data-toggle="modal" data-target="#editPlanModal" onclick="editPlan('<?= htmlspecialchars($plan['name']) ?>')">Edit</button>
                                            <button class="sa-btn sa-btn-small sa-btn-danger delete-plan" data-name="<?= htmlspecialchars($plan['name']) ?>">Delete</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="sa-card">
                                    <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                        <div style="font-size: 2rem; margin-bottom: 12px;">📦</div>
                                        <div style="font-weight: 600; margin-bottom: 4px;">No Plans Found</div>
                                        <div style="font-size: 0.8rem;"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'Get started by creating a new plan.' ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="sa-pagination">
                                    <?php 
                                    $query_string = '';
                                    if (!empty($search_query)) $query_string .= '&search=' . urlencode($search_query);
                                    
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
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View All Features Modal -->
<div class="modal fade" id="viewFeaturesModal" tabindex="-1" role="dialog" aria-labelledby="viewFeaturesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewFeaturesModalLabel">
                    <i class="feather icon-list"></i> <span id="planNameTitle">Plan</span> Features
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="featuresList" class="features-modal-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Plan Modal -->
<div class="modal fade" id="createPlanModal" tabindex="-1" role="dialog" aria-labelledby="createPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPlanModalLabel">
                    <i class="feather icon-plus"></i> Create New Plan
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createPlanForm" method="POST" action="create_plan.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="planName">Plan Name</label>
                                <input type="text" class="form-control" id="planName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="price">Price</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value="USD">USD ($)</option>
                                    <option value="AFN">AFN (؋)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="INR">INR (₹)</option>
                                    <option value="JPY">JPY (¥)</option>
                                    <option value="CNY">CNY (¥)</option>
                                    <option value="AUD">AUD (A$)</option>
                                    <option value="CAD">CAD (C$)</option>
                                    <option value="CHF">CHF</option>
                                    <option value="SEK">SEK (kr)</option>
                                    <option value="NZD">NZD (NZ$)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="max_users">Max Users</label>
                                <input type="number" min="0" class="form-control" id="max_users" name="max_users" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="max_branches">Max Branches</label>
                                <input type="number" min="0" class="form-control" id="max_branches" name="max_branches" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="trial_days">Trial Days</label>
                                <input type="number" min="0" class="form-control" id="trial_days" name="trial_days" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="features">Features (JSON Array)</label>
                        <textarea class="form-control" id="features" name="features" rows="4" placeholder='["feature1","feature2","feature3"]' required></textarea>
                        <small class="form-text text-muted">Enter features as a JSON array, e.g., ["feature1", "feature2"]</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="createPlanForm" class="btn btn-primary">Create Plan</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1" role="dialog" aria-labelledby="editPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPlanModalLabel">
                    <i class="feather icon-edit-2"></i> Edit Plan
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editPlanForm" method="POST" action="update_plan.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="original_name" id="original_name" value="">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPlanName">Plan Name</label>
                                <input type="text" class="form-control" id="editPlanName" name="name" required readonly>
                                <small class="form-text text-muted">Plan name cannot be changed after creation.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="editPrice">Price</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="editPrice" name="price" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="editCurrency">Currency</label>
                                <select class="form-control" id="editCurrency" name="currency" required>
                                    <option value="USD">USD ($)</option>
                                    <option value="AFN">AFN (؋)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="INR">INR (₹)</option>
                                    <option value="JPY">JPY (¥)</option>
                                    <option value="CNY">CNY (¥)</option>
                                    <option value="AUD">AUD (A$)</option>
                                    <option value="CAD">CAD (C$)</option>
                                    <option value="CHF">CHF</option>
                                    <option value="SEK">SEK (kr)</option>
                                    <option value="NZD">NZD (NZ$)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="editMaxUsers">Max Users</label>
                                <input type="number" min="0" class="form-control" id="editMaxUsers" name="max_users" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="editMaxBranches">Max Branches</label>
                                <input type="number" min="0" class="form-control" id="editMaxBranches" name="max_branches" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="editTrialDays">Trial Days</label>
                                <input type="number" min="0" class="form-control" id="editTrialDays" name="trial_days" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="editStatus">Status</label>
                                <select class="form-control" id="editStatus" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editFeatures">Features (JSON Array)</label>
                        <textarea class="form-control" id="editFeatures" name="features" rows="4" placeholder='["feature1","feature2","feature3"]' required></textarea>
                        <small class="form-text text-muted">Enter features as a JSON array, e.g., ["feature1", "feature2"]</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="editPlanForm" class="btn btn-primary">Update Plan</button>
            </div>
        </div>
    </div>
</div>

<!-- Features Modal -->
<div class="modal fade" id="featuresModal" tabindex="-1" role="dialog" aria-labelledby="featuresModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="featuresModalLabel">
                    <i class="feather icon-list"></i> All Features - <span id="planName"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="featuresList" class="features-modal-list">
                    <!-- Features will be populated here -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ─── CSS VARIABLES (Matching header-styles.css) ─────────────── */
:root {
    --grad-start: #4099ff;
    --grad-end: #2ed8b6;
    --grad: linear-gradient(135deg, var(--grad-start) 0%, var(--grad-end) 100%);
    
    --bg: #f8fafc;
    --surface: #ffffff;
    --surface2: #f3f4f6;
    --text: #1f2937;
    --muted: #6b7280;
    --border: #e5e7eb;
    --radius: 10px;
    
    --green: #10b981;
    --red: #ef4444;
    --amber: #f59e0b;
    --blue: #3b82f6;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card {
    background: var(--grad) !important;
    color: #ffffff;
    border: none !important;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(64,153,255,0.18), 0 2px 6px rgba(0,0,0,0.08);
    border-radius: var(--radius);
    position: relative;
    overflow: hidden;
}

.page-header.card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.06) 50%, transparent 60%);
    pointer-events: none;
}

.page-header.card h5 {
    color: #ffffff !important;
    margin: 0;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.page-header.card .row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    position: relative;
    z-index: 2;
}

.page-header.card .col-md-6:last-child {
    text-align: right;
    margin-left: auto;
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
    gap: 12px;
    padding: 14px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    margin-bottom: 16px;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.sa-alert-icon {
    font-size: 1.1rem;
    font-weight: 700;
    flex-shrink: 0;
    width: 24px;
    text-align: center;
}

.sa-alert-content {
    flex: 1;
    font-size: 0.85rem;
}

.sa-alert-close {
    background: none;
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    color: var(--muted);
    padding: 0;
    transition: color 0.2s;
    flex-shrink: 0;
}

.sa-alert-close:hover {
    color: var(--text);
}

.sa-alert-success {
    background: #d1fae5;
    border-color: var(--green);
    color: #065f46;
}

.sa-alert-danger {
    background: #fee2e2;
    border-color: var(--red);
    color: #7f1d1d;
}

/* ─── CARDS ───────────────────────────────────────────────── */
.sa-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.sa-card:hover {
    border-color: rgba(64,153,255,0.25);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64,153,255,0.18);
}

.sa-card-body {
    padding: 18px 20px;
}

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s;
    text-decoration: none;
    white-space: nowrap;
}

.sa-btn-primary {
    background: var(--grad);
    color: white;
}

.sa-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64,153,255,0.3);
}

.sa-btn-ghost {
    background: var(--surface2);
    color: var(--muted);
    border: 1px solid var(--border);
}

.sa-btn-ghost:hover {
    background: rgba(64,153,255,0.1);
    border-color: var(--grad-start);
    color: var(--grad-start);
}

.sa-btn-danger {
    background: #fee2e2;
    color: var(--red);
    border: 1px solid #fecaca;
}

.sa-btn-danger:hover {
    background: #fecaca;
    border-color: var(--red);
}

.sa-btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
}

/* ─── SEARCH & FILTER ─────────────────────────────────────── */
.sa-search-filter {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.sa-search-group {
    display: flex;
    gap: 10px;
    flex: 1;
    flex-wrap: wrap;
}

.sa-search-input {
    flex: 1;
    min-width: 200px;
    padding: 9px 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 0.8rem;
}

.sa-search-input:focus {
    outline: none;
    border-color: var(--grad-start);
    box-shadow: 0 0 0 2px rgba(64,153,255,0.2);
}

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-shdr {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 14px;
}

.sa-shdr h2 {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted);
    font-weight: 700;
    margin: 0;
}

/* ─── STAT CARDS ─────────────────────────────────────────── */
.sa-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.sa-stat {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 22px;
    position: relative;
    overflow: hidden;
    transition: all 0.2s;
}

.sa-stat::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--grad-start);
    opacity: 0;
    transition: opacity 0.2s;
}

.sa-stat:hover::after {
    opacity: 1;
}

.sa-stat:hover {
    transform: translateY(-2px);
    border-color: rgba(64,153,255,0.3);
}

.sa-stat-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}

.sa-stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sa-stat-icon svg {
    width: 17px;
    height: 17px;
}

.si-green {
    background: rgba(16,185,129,0.12);
    color: var(--green);
}

.si-red {
    background: rgba(239,68,68,0.12);
    color: var(--red);
}

.si-blue {
    background: rgba(59,130,246,0.12);
    color: var(--blue);
}

.sa-stat-val {
    font-size: 1.45rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    font-family: 'JetBrains Mono', monospace;
}

.sa-stat-name {
    font-size: 0.72rem;
    color: var(--muted);
    margin-top: 2px;
    font-weight: 500;
}

/* ─── GRID LAYOUTS ────────────────────────────────────────── */
.sa-3col {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
}

@media (max-width: 1200px) {
    .sa-3col {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .sa-3col {
        grid-template-columns: 1fr;
    }
}

/* ─── PLAN CARD ───────────────────────────────────────────── */
.plan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: all 0.2s;
}

.plan-card:hover {
    border-color: rgba(64,153,255,0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(64,153,255,0.15);
}

.pc-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.pc-title h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
    color: var(--text);
}

.pc-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.pc-price {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.price-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--green);
}

.price-label {
    font-size: 0.75rem;
    color: var(--muted);
}

.pc-description {
    margin: 0;
    font-size: 0.8rem;
    color: var(--muted);
}

.pc-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.pc-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
}

.pc-info-row span {
    color: var(--muted);
}

.pc-info-row strong {
    color: var(--text);
}

.pc-features {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.features-label {
    font-size: 0.72rem;
    color: var(--muted);
    font-weight: 600;
}

.features-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.feature-tag {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 12px;
    background: var(--surface2);
    color: var(--muted);
    font-family: 'Courier New', monospace;
}

.feature-tag.more {
    cursor: pointer;
    background: rgba(64,153,255,0.12);
    color: var(--grad-start);
}

.pc-actions {
    display: flex;
    gap: 6px;
}

.pc-actions .sa-btn {
    flex: 1;
    justify-content: center;
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
    color: var(--green);
}

.pill-red {
    background: rgba(239,68,68,0.12);
    color: var(--red);
}

.pill-amber {
    background: rgba(245,158,11,0.12);
    color: var(--amber);
}

.pill-blue {
    background: rgba(59,130,246,0.12);
    color: var(--blue);
}

.pill-muted {
    background: var(--surface2);
    color: var(--muted);
}

/* ─── PAGINATION ──────────────────────────────────────────── */
.sa-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding: 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    flex-wrap: wrap;
}

.sa-pagination-item {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface2);
    color: var(--text);
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
    background: rgba(64,153,255,0.1);
    border-color: var(--grad-start);
    color: var(--grad-start);
}

.sa-pagination-item.active {
    background: var(--grad);
    border-color: var(--grad-start);
    color: white;
}

.sa-pagination-ellipsis {
    color: var(--muted);
    font-size: 0.8rem;
}

.sa-pagination-info {
    font-size: 0.75rem;
    color: var(--muted);
    margin-left: 10px;
}

/* ─── FEATURES MODAL ──────────────────────────────────────────── */
.features-modal-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.feature-modal-item {
    padding: 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.feature-modal-item::before {
    content: '✓ ';
    color: var(--green);
    font-weight: 700;
    margin-right: 6px;
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
    overflow: hidden;
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
    overflow: hidden;
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

/* Additional styles for stats cards */
.stats-cards {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    flex: 1;
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2rem;
    color: #4099ff;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
}

/* Feature badges */
.feature-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    font-size: 0.8rem;
    margin: 0.1rem;
    display: inline-block;
}

.feature-badge-more {
    cursor: pointer;
    background: #007bff;
    color: white;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.plan-name {
    font-weight: 600;
    color: #333;
}

.description-cell {
    max-width: 200px;
}

.description-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.price-cell {
    font-weight: 600;
    color: #28a745;
}

.date-cell {
    color: #666;
}

.actions-cell .btn {
    margin-right: 0.5rem;
}

.pagination .page-link {
    border-radius: 5px;
    margin: 0 2px;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border: none;
}
</style>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
// View all features function
function viewAllFeatures(button) {
    const planName = button.getAttribute('data-plan');
    const featuresJson = button.getAttribute('data-features');
    
    try {
        const features = JSON.parse(featuresJson);
        
        // Update modal title
        document.getElementById('planNameTitle').textContent = planName;
        
        // Clear previous content
        const featuresList = document.getElementById('featuresList');
        featuresList.innerHTML = '';
        
        // Add features to modal
        features.forEach(feature => {
            const featureItem = document.createElement('div');
            featureItem.className = 'feature-modal-item';
            featureItem.textContent = feature;
            featuresList.appendChild(featureItem);
        });
        
        // Show modal
        $('#viewFeaturesModal').modal('show');
    } catch (error) {
        console.error('Error parsing features:', error);
        alert('Error loading features');
    }
}

// Edit plan function
function editPlan(planName) {
    // Fetch plan data via AJAX
    fetch('get_plan.php?name=' + encodeURIComponent(planName))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate form fields
                document.getElementById('editPlanName').value = data.plan.name;
                document.getElementById('original_name').value = data.plan.name;
                document.getElementById('editPrice').value = data.plan.price;
                document.getElementById('editCurrency').value = data.plan.currency || 'USD';
                document.getElementById('editDescription').value = data.plan.description;
                document.getElementById('editMaxUsers').value = data.plan.max_users;
                document.getElementById('editMaxBranches').value = data.plan.max_branches || 0;
                document.getElementById('editTrialDays').value = data.plan.trial_days;
                document.getElementById('editStatus').value = data.plan.status;
                document.getElementById('editFeatures').value = data.plan.features;
                
                // Show modal
                $('#editPlanModal').modal('show');
            } else {
                alert('Error loading plan: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading plan data');
        });
}

// JavaScript for Plan Management
document.addEventListener('DOMContentLoaded', () => {
    // Delete plan functionality
    document.querySelectorAll('.delete-plan').forEach(button => {
        button.addEventListener('click', function() {
            const planName = this.getAttribute('data-name');

            if (confirm(`Are you sure you want to delete the plan "${planName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_plan.php';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="plan_name" value="${planName}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Handle features modal
    document.querySelectorAll('.feature-badge-more').forEach(button => {
        button.addEventListener('click', function() {
            const planName = this.getAttribute('data-plan');
            const featuresContainer = this.closest('.features-badges');
            const features = JSON.parse(featuresContainer.getAttribute('data-features') || '[]');

            document.getElementById('planName').textContent = planName;
            const featuresList = document.getElementById('featuresList');
            featuresList.innerHTML = '';

            if (features.length === 0) {
                featuresList.innerHTML = '<div class="text-center text-muted py-4">No features available</div>';
                return;
            }

            features.forEach(feature => {
                const badge = document.createElement('span');
                badge.className = 'feature-badge';
                badge.textContent = feature;
                featuresList.appendChild(badge);
            });
        });
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            const closeBtn = alert.querySelector('.close');
            if (closeBtn) closeBtn.click();
        });
    }, 5000);
});
</script>
</body>
</html>
