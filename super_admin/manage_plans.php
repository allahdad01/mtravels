<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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
    error_log("Unauthorized access attempt to manage_plans.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

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
$query = "SELECT name, description, features, price, max_users, trial_days, status, created_at FROM plans WHERE 1=1";

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
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10"><?= __('manage_plans') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('plans') ?></a></li>
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
                                <!-- Success/Error Alerts -->
                                <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
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
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_GET['error']) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Plan Management</h5>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#createPlanModal">
                                            <i class="feather icon-plus mr-1"></i>Create Plan
                                        </button>
                                    </div>

                                    <!-- Stats Cards -->
                                    <div class="stats-cards">
                                        <div class="stat-card">
                                            <div class="stat-icon">
                                                <i class="feather icon-package"></i>
                                            </div>
                                            <div class="stat-value"><?= $total_items ?></div>
                                            <div class="stat-label">Total Plans</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-icon">
                                                <i class="feather icon-check-circle"></i>
                                            </div>
                                            <div class="stat-value"><?= count(array_filter($plans, fn($p) => $p['status'] === 'active')) ?></div>
                                            <div class="stat-label">Active Plans</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-icon">
                                                <i class="feather icon-users"></i>
                                            </div>
                                            <div class="stat-value">
                                                <?php
                                                $total_users = array_sum(array_column($plans, 'max_users'));
                                                echo number_format($total_users);
                                                ?>
                                            </div>
                                            <div class="stat-label">Max Users</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-icon">
                                                <i class="feather icon-dollar-sign"></i>
                                            </div>
                                            <div class="stat-value">
                                                <?php
                                                $total_revenue = array_sum(array_column($plans, 'price'));
                                                echo '$' . number_format($total_revenue, 0);
                                                ?>
                                            </div>
                                            <div class="stat-label">Total Value</div>
                                        </div>
                                    </div>

                                    <div class="card-body table-border-style">
                                        <!-- Search Form -->
                                        <div class="mb-3">
                                            <form method="GET" action="manage_plans.php" class="form-inline">
                                                <input type="text" class="form-control mr-2" name="search" placeholder="Search plans..." value="<?= htmlspecialchars($search_query) ?>" style="width: 250px;">
                                                <button type="submit" class="btn btn-primary mr-2">Search</button>
                                                <?php if (!empty($search_query)): ?>
                                                <a href="manage_plans.php" class="btn btn-secondary">Clear</a>
                                                <?php endif; ?>
                                            </form>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Description</th>
                                                        <th>Features</th>
                                                        <th>Price</th>
                                                        <th>Max Users</th>
                                                        <th>Trial Days</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($plans as $plan): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="description-cell">
                                                                <div class="description-text" title="<?= htmlspecialchars($plan['description']) ?>">
                                                                    <?= htmlspecialchars($plan['description']) ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $features = json_decode($plan['features'], true);
                                                            ?>
                                                            <div class="features-badges" data-features='<?= htmlspecialchars($plan['features']) ?>'>
                                                                <?php
                                                                if (is_array($features) && !empty($features)) {
                                                                    $display_features = array_slice($features, 0, 3); // Show first 3
                                                                    foreach ($display_features as $feature) {
                                                                        echo '<span class="feature-badge">' . htmlspecialchars($feature) . '</span>';
                                                                    }
                                                                    if (count($features) > 3) {
                                                                        echo '<span class="feature-badge feature-badge-more" data-toggle="modal" data-target="#featuresModal" data-plan="' . htmlspecialchars($plan['name']) . '" title="Click to view all features">+' . (count($features) - 3) . ' more</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span class="text-muted">No features</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="price-cell">$<?= number_format($plan['price'], 2) ?></div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-secondary">
                                                                <?= htmlspecialchars($plan['max_users']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-info">
                                                                <?= htmlspecialchars($plan['trial_days']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-<?= $plan['status'] ?>">
                                                                <?= htmlspecialchars($plan['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="date-cell">
                                                                <?= date('M j, Y', strtotime($plan['created_at'])) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="actions-cell">
                                                                <a href="edit_plan.php?name=<?= urlencode($plan['name']) ?>" class="btn btn-sm btn-primary" title="Edit Plan">
                                                                    <i class="feather icon-edit"></i> Edit
                                                                </a>
                                                                <button class="btn btn-sm btn-danger delete-plan ml-1" data-name="<?= htmlspecialchars($plan['name']) ?>" title="Delete Plan">
                                                                    <i class="feather icon-trash-2"></i> Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($plans)): ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center py-5">
                                                            <div class="text-muted">
                                                                <i class="feather icon-package" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                                                                <h5>No Plans Found</h5>
                                                                <p>Create your first subscription plan to get started.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-3">
                                            <ul class="pagination justify-content-center">
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        <i class="feather icon-chevron-left"></i> Previous
                                                    </a>
                                                </li>

                                                <?php
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);
                                                if ($start_page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                                    </li>
                                                    <?php if ($start_page > 2): ?>
                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                    <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                        <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>

                                                <?php if ($end_page < $total_pages): ?>
                                                    <?php if ($end_page < $total_pages - 1): ?>
                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    <?php endif; ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                                    </li>
                                                <?php endif; ?>

                                                <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        Next <i class="feather icon-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                        <div class="text-center text-muted small mt-2">
                                            Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($plans) ?> of <?= $total_items ?> plans
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="max_users">Max Users</label>
                                <input type="number" min="0" class="form-control" id="max_users" name="max_users" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="trial_days">Trial Days</label>
                                <input type="number" min="0" class="form-control" id="trial_days" name="trial_days" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
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
