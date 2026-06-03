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
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m0 10v10l8 4"/></svg><?= __('manage_plans') ?>
                            </h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_subscription_plans'); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="dashboard.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg><?php echo __('back_to_dashboard'); ?>
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
                                    <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
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
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($_GET['error'])): ?>
                                <div class="sa-alert sa-alert-danger">
                                    <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                    <div class="sa-alert-content">
                                        <?= htmlspecialchars($_GET['error']) ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
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

                                <!-- Data Table -->
                                <?php if (!empty($plans)): ?>
                                <div class="sa-table-wrap">
                                    <table class="sa-table">
                                        <thead>
                                            <tr>
                                                <th>Plan Name</th>
                                                <th>Price</th>
                                                <th>Users</th>
                                                <th>Branches</th>
                                                <th>Trial</th>
                                                <th>Status</th>
                                                <th>Features</th>
                                                <th class="sa-th-actions">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($plans as $plan):
                                                $features = json_decode($plan['features'], true);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600;"><?= htmlspecialchars($plan['name']) ?></div>
                                                    <div style="font-size:0.75rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars(substr($plan['description'], 0, 60)) ?>...</div>
                                                </td>
                                                <td style="font-weight:600;color:var(--green);white-space:nowrap;"><?= getCurrencySymbol($plan['currency'] ?? $defaultCurrency) ?><?= number_format($plan['price'], 2) ?></td>
                                                <td><?= htmlspecialchars($plan['max_users']) ?></td>
                                                <td><?= htmlspecialchars($plan['max_branches'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars($plan['trial_days']) ?>d</td>
                                                <td><span class="pill <?= $plan['status'] === 'active' ? 'pill-green' : 'pill-amber' ?>"><?= htmlspecialchars($plan['status']) ?></span></td>
                                                <td>
                                                    <?php if (is_array($features) && !empty($features)): ?>
                                                    <div style="display:flex;flex-wrap:wrap;gap:3px;">
                                                        <?php foreach (array_slice($features, 0, 2) as $feature): ?>
                                                        <span style="font-size:0.7rem;padding:2px 6px;border-radius:4px;background:var(--surface2);color:var(--muted);"><?= htmlspecialchars($feature) ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($features) > 2): ?>
                                                        <button type="button" class="view-all-features" style="font-size:0.7rem;padding:2px 6px;border-radius:4px;background:rgba(64,153,255,0.1);color:var(--primary);border:none;cursor:pointer;" data-plan="<?= htmlspecialchars($plan['name']) ?>" data-features='<?= htmlspecialchars(json_encode($features)) ?>' onclick="viewAllFeatures(this)">+<?= count($features) - 2 ?> more</button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php else: ?>
                                                    <span class="sa-na">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="sa-td-actions">
                                                    <button type="button" class="sa-icon-btn" data-toggle="modal" data-target="#editPlanModal" onclick="editPlan('<?= htmlspecialchars($plan['name']) ?>')" title="Edit">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                    <button type="button" class="sa-icon-btn sa-icon-btn-danger delete-plan" data-name="<?= htmlspecialchars($plan['name']) ?>" title="Delete">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="sa-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m0 10v10l8 4"/></svg>
                                    <div class="sa-empty-title">No Plans Found</div>
                                    <div class="sa-empty-desc"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'Get started by creating a new plan.' ?></div>
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
</div>

<!-- View All Features Modal -->
<div class="modal fade" id="viewFeaturesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m0 10v10l8 4"/></svg> <span id="planNameTitle">Plan</span> Features
                    </h5>
                    <p class="sa-modal-subtitle">All features included in this plan</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-modal-body">
                <div id="featuresList" class="features-modal-list"></div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Plan Modal -->
<div class="modal fade" id="createPlanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title" id="createPlanModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> Create New Plan
                    </h5>
                    <p class="sa-modal-subtitle">Set up a new subscription plan</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form id="createPlanForm" method="POST" action="create_plan.php">
                <div class="sa-modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="sa-form-grid-4">
                        <div class="sa-form-group">
                            <label for="planName" class="sa-form-label">Plan Name <span style="color:var(--red)">*</span></label>
                            <input type="text" class="sa-form-input" id="planName" name="name" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="price" class="sa-form-label">Price</label>
                            <input type="number" step="0.01" min="0" class="sa-form-input" id="price" name="price" value="0.00">
                        </div>
                        <div class="sa-form-group">
                            <label for="currency" class="sa-form-label">Currency <span style="color:var(--red)">*</span></label>
                            <select class="sa-form-input sa-form-select" id="currency" name="currency" required>
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
                        <div class="sa-form-group">
                            <label for="status" class="sa-form-label">Status</label>
                            <select class="sa-form-input sa-form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="sa-form-group">
                        <label for="description" class="sa-form-label">Description <span style="color:var(--red)">*</span></label>
                        <textarea class="sa-form-input sa-form-textarea" id="description" name="description" rows="3" required></textarea>
                    </div>

                    <div class="sa-form-grid-4">
                        <div class="sa-form-group">
                            <label for="max_users" class="sa-form-label">Max Users</label>
                            <input type="number" min="0" class="sa-form-input" id="max_users" name="max_users" value="0">
                        </div>
                        <div class="sa-form-group">
                            <label for="max_branches" class="sa-form-label">Max Branches</label>
                            <input type="number" min="0" class="sa-form-input" id="max_branches" name="max_branches" value="0">
                        </div>
                        <div class="sa-form-group">
                            <label for="trial_days" class="sa-form-label">Trial Days</label>
                            <input type="number" min="0" class="sa-form-input" id="trial_days" name="trial_days" value="0">
                        </div>
                    </div>

                    <div class="sa-form-group">
                        <label for="features" class="sa-form-label">Features (JSON Array) <span style="color:var(--red)">*</span></label>
                        <textarea class="sa-form-input sa-form-textarea" id="features" name="features" rows="4" placeholder='["feature1","feature2","feature3"]' required></textarea>
                        <p style="font-size:0.75rem;color:var(--muted);margin:4px 0 0;">Enter features as a JSON array, e.g., ["feature1", "feature2"]</p>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Create Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title" id="editPlanModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Plan
                    </h5>
                    <p class="sa-modal-subtitle">Update plan configuration</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form id="editPlanForm" method="POST" action="update_plan.php">
                <div class="sa-modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="original_name" id="original_name" value="">

                    <div class="sa-form-grid-4">
                        <div class="sa-form-group">
                            <label for="editPlanName" class="sa-form-label">Plan Name</label>
                            <input type="text" class="sa-form-input" id="editPlanName" name="name" required readonly>
                            <p style="font-size:0.75rem;color:var(--muted);margin:4px 0 0;">Plan name cannot be changed after creation.</p>
                        </div>
                        <div class="sa-form-group">
                            <label for="editPrice" class="sa-form-label">Price <span style="color:var(--red)">*</span></label>
                            <input type="number" step="0.01" min="0" class="sa-form-input" id="editPrice" name="price" value="0.00" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="editCurrency" class="sa-form-label">Currency <span style="color:var(--red)">*</span></label>
                            <select class="sa-form-input sa-form-select" id="editCurrency" name="currency" required>
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
                        <div class="sa-form-group">
                            <label for="editStatus" class="sa-form-label">Status <span style="color:var(--red)">*</span></label>
                            <select class="sa-form-input sa-form-select" id="editStatus" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="sa-form-group">
                        <label for="editDescription" class="sa-form-label">Description <span style="color:var(--red)">*</span></label>
                        <textarea class="sa-form-input sa-form-textarea" id="editDescription" name="description" rows="3" required></textarea>
                    </div>

                    <div class="sa-form-grid-4">
                        <div class="sa-form-group">
                            <label for="editMaxUsers" class="sa-form-label">Max Users <span style="color:var(--red)">*</span></label>
                            <input type="number" min="0" class="sa-form-input" id="editMaxUsers" name="max_users" value="0" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="editMaxBranches" class="sa-form-label">Max Branches <span style="color:var(--red)">*</span></label>
                            <input type="number" min="0" class="sa-form-input" id="editMaxBranches" name="max_branches" value="0" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="editTrialDays" class="sa-form-label">Trial Days <span style="color:var(--red)">*</span></label>
                            <input type="number" min="0" class="sa-form-input" id="editTrialDays" name="trial_days" value="0" required>
                        </div>
                    </div>

                    <div class="sa-form-group">
                        <label for="editFeatures" class="sa-form-label">Features (JSON Array) <span style="color:var(--red)">*</span></label>
                        <textarea class="sa-form-input sa-form-textarea" id="editFeatures" name="features" rows="4" placeholder='["feature1","feature2","feature3"]' required></textarea>
                        <p style="font-size:0.75rem;color:var(--muted);margin:4px 0 0;">Enter features as a JSON array, e.g., ["feature1", "feature2"]</p>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Update Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Features Modal -->
<div class="modal fade" id="featuresModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m0 10v10l8 4"/></svg> All Features - <span id="planName"></span>
                    </h5>
                    <p class="sa-modal-subtitle">All features included in this plan</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-modal-body">
                <div id="featuresList" class="features-modal-list"></div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #4099ff;
    --primary-dark: #2673cc;
    --primary-glow: rgba(64,153,255,0.2);
    --secondary: #2ed8b6;
    --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --bg: #f0f8ff;
    --surface: #ffffff;
    --surface2: #f3f8ff;
    --text: #1a2332;
    --muted: #6b7280;
    --border: #e2e8f0;
    --radius: 10px;
    --green: #10b981;
    --red: #ef4444;
    --amber: #f59e0b;
    --blue: #3b82f6;
}
.page-header.card {
    background: var(--grad) !important; color: #fff; border: none !important;
    margin-bottom: 20px; padding: 22px 28px !important;
    box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
    position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-header.card .sa-btn:hover { background: rgba(255,255,255,0.2) !important; border-color: rgba(255,255,255,0.4) !important; transform: translateY(-1px); }
.sa-alert {
    display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
    border-radius: var(--radius); border: 1px solid var(--border);
    margin-bottom: 16px; animation: slideIn 0.3s ease-out;
}
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.sa-alert-icon { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sa-alert-icon svg { width: 20px; height: 20px; }
.sa-alert-content { flex: 1; font-size: 0.85rem; }
.sa-alert-close { background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; transition: color 0.2s; flex-shrink: 0; display: flex; }
.sa-alert-close:hover { color: var(--text); }
.sa-alert-success { background: #d1fae5; border-color: var(--green); color: #065f46; }
.sa-alert-success .sa-alert-icon svg { color: var(--green); }
.sa-alert-danger { background: #fee2e2; border-color: var(--red); color: #7f1d1d; }
.sa-alert-danger .sa-alert-icon svg { color: var(--red); }
.sa-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 0.82rem; font-weight: 600; transition: all 0.2s;
    text-decoration: none; white-space: nowrap;
}
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 14px var(--primary-glow); }
.sa-btn-ghost { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
.sa-btn-ghost:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-btn-sm { padding: 6px 12px; font-size: 0.75rem; }
.sa-search-filter { display: flex; gap: 10px; align-items: flex-end; }
.sa-search-group { display: flex; gap: 10px; flex: 1; flex-wrap: wrap; }
.sa-search-input { flex: 1; min-width: 200px; padding: 9px 12px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 0.8rem; }
.sa-search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-glow); }
.sa-shdr { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 14px; }
.sa-shdr h2 { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); font-weight: 700; margin: 0; }
.sa-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
.sa-stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; position: relative; overflow: hidden; transition: all 0.2s; }
.sa-stat:hover { transform: translateY(-2px); border-color: rgba(64,153,255,0.3); box-shadow: 0 4px 16px var(--primary-glow); }
.sa-stat-top { display: flex; align-items: flex-start; justify-content: space-between; }
.sa-stat-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sa-stat-icon svg { width: 17px; height: 17px; }
.si-green { background: rgba(16,185,129,0.12); color: var(--green); }
.si-blue { background: rgba(64,153,255,0.12); color: var(--primary); }
.si-red { background: rgba(239,68,68,0.12); color: var(--red); }
.sa-stat-val { font-size: 1.4rem; font-weight: 700; letter-spacing: -0.03em; }
.sa-stat-name { font-size: 0.72rem; color: var(--muted); margin-top: 2px; font-weight: 500; }
.pill {
    font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
}
.pill-green { background: rgba(16,185,129,0.12); color: var(--green); }
.pill-red { background: rgba(239,68,68,0.12); color: var(--red); }
.pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-blue { background: rgba(59,130,246,0.12); color: var(--blue); }
.pill-gray { background: rgba(107,114,128,0.12); color: var(--muted); }
.sa-table-wrap {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table thead { background: var(--surface2); }
.sa-table th { padding: 12px 16px; text-align: left; font-weight: 600; color: var(--muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); white-space: nowrap; }
.sa-table td { padding: 14px 16px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: rgba(64,153,255,0.03); }
.sa-th-actions { text-align: right; width: 90px; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-td-actions .sa-icon-btn { margin-left: 4px; }
.sa-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent;
    background: var(--surface2); color: var(--muted); cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.sa-icon-btn:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-icon-btn-danger:hover { background: rgba(239,68,68,0.08); border-color: var(--red); color: var(--red); }
.sa-na { color: var(--muted); font-size: 0.8rem; }
.sa-pagination {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    margin-top: 20px; padding: 14px; flex-wrap: wrap;
}
.sa-page-btn {
    min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px;
    border: 1px solid var(--border); background: var(--surface);
    color: var(--text); text-decoration: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 500; transition: all 0.2s; cursor: pointer;
}
.sa-page-btn:hover:not(.sa-page-active) { background: rgba(64,153,255,0.06); border-color: var(--primary); color: var(--primary); }
.sa-page-active { background: var(--grad); border-color: transparent; color: #fff; }
.sa-page-ellipsis { color: var(--muted); font-size: 0.8rem; padding: 0 4px; }
.sa-page-info { font-size: 0.75rem; color: var(--muted); margin-left: 10px; }
.sa-modal-content { border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 20px 60px rgba(0,0,0,0.15); background: var(--surface); }
.sa-modal-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 20px 24px; border-bottom: 1px solid var(--border);
}
.sa-modal-title-group { flex: 1; }
.sa-modal-title { font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text); display: flex; align-items: center; gap: 8px; }
.sa-modal-subtitle { font-size: 0.8rem; color: var(--muted); margin: 4px 0 0; }
.sa-modal-close { background: none; border: none; color: var(--muted); cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; margin-left: 16px; }
.sa-modal-close:hover { color: var(--text); }
.sa-modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
.sa-modal-footer { display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid var(--border); background: var(--surface2); }
.sa-form-group { display: flex; flex-direction: column; margin-bottom: 16px; }
.sa-form-label { font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text); }
.sa-form-input { padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); font-size: 0.95rem; transition: all 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.sa-form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.sa-form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 32px; }
.sa-form-textarea { resize: vertical; min-height: 80px; }
.sa-form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
@media (max-width: 768px) { .sa-form-grid-4 { grid-template-columns: 1fr; } }
.sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
.sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
.sa-empty-desc { font-size: 0.85rem; color: var(--muted); }
.features-modal-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.feature-modal-item { padding: 12px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; color: var(--text); display: flex; align-items: center; justify-content: center; text-align: center; }
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

});
</script>
</body>
</html>
