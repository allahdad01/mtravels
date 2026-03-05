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
    error_log("Unauthorized access attempt to manage_subscriptions.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

// Handle AJAX requests for edit subscription
if (isset($_GET['action']) && $_GET['action'] === 'get_subscription' && isset($_GET['subscription_id'])) {
    $subscription_id = intval($_GET['subscription_id']);

    $stmt = $pdo->prepare("
        SELECT ts.id, ts.tenant_id, ts.plan_id, ts.status, ts.billing_cycle, ts.start_date, ts.end_date,
            ts.amount, ts.currency, ts.payment_method, ts.last_payment_date, ts.next_billing_date,
            ts.transaction_id, COALESCE(t.name, 'Deleted Tenant') as tenant_name, p.name as plan_name
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        WHERE ts.id = ?
    ");
    
    try {
        $stmt->execute([$subscription_id]);
    } catch (PDOException $e) {
        error_log("Database error in get_subscription: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error occurred']);
        exit();
    }

    $subscription = $stmt->fetch();
    if ($subscription) {
        header('Content-Type: application/json');
        echo json_encode($subscription);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Subscription not found']);
    }
    exit();
}

// Handle form submission for updating subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_subscription') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_subscriptions.php?error=invalid_csrf');
        exit();
    }

    $subscription_id = intval($_POST['subscription_id']);
    $plan_id = $_POST['plan_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $billing_cycle = $_POST['billing_cycle'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $currency = $_POST['currency'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $next_billing_date = !empty($_POST['next_billing_date']) ? $_POST['next_billing_date'] : null;

    $errors = [];

    // Validate input
    if (empty($plan_id) || empty($status) || empty($billing_cycle) || empty($amount) || empty($currency)) {
        $errors[] = "All required fields must be filled.";
    }
    
    // Validate amount is numeric
    if (!empty($amount) && (!is_numeric($amount) || floatval($amount) <= 0)) {
        $errors[] = "Subscription amount must be a positive number.";
    }
    
    if (!empty($amount)) {
        $amount = floatval($amount);
    }
    if (!in_array($status, ['active', 'pending', 'expired', 'cancelled'])) {
        $errors[] = "Invalid status.";
    }
    if (!in_array($billing_cycle, ['monthly', 'quarterly', 'yearly'])) {
        $errors[] = "Invalid billing cycle.";
    }
    if (!is_numeric($amount) || $amount < 0) {
        $errors[] = "Invalid amount.";
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $errors[] = "Invalid currency code.";
    }
    if ($next_billing_date && !strtotime($next_billing_date)) {
        $errors[] = "Invalid next billing date.";
    }

    // Verify plan exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM plans WHERE id = ? AND status = 'active'");
    $stmt->execute([$plan_id]);
    if ($stmt->fetch()['count'] == 0) {
        $errors[] = "Invalid or inactive plan selected.";
    }
    if (empty($errors)) {
        // Update subscription
        $stmt = $pdo->prepare("
            UPDATE tenant_subscriptions
            SET plan_id = ?, status = ?, billing_cycle = ?, amount = ?, currency = ?,
                payment_method = ?, next_billing_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$plan_id, $status, $billing_cycle, $amount, $currency, $payment_method, $next_billing_date, $subscription_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_subscription', 'subscription', ?, ?, ?, NOW())");
        $details = json_encode([
            'subscription_id' => $subscription_id,
            'plan_id' => $plan_id,
            'status' => $status,
            'billing_cycle' => $billing_cycle
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $subscription_id, $details, $ip_address]);
        header('Location: manage_subscriptions.php?success=subscription_updated');
        exit();
    } else {
        header('Location: manage_subscriptions.php?error=' . urlencode(implode(', ', $errors)));
        exit();
    }
}

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Count total items
$count_query = "SELECT COUNT(*) as total FROM tenant_subscriptions ts LEFT JOIN tenants t ON ts.tenant_id = t.id WHERE 1=1";
$filter_params = [];

if (!empty($search_query)) {
    $count_query .= " AND (COALESCE(t.name, 'Deleted Tenant') LIKE ? OR t.identifier LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}
if (!empty($status_filter)) {
    $count_query .= " AND ts.status = ?";
    $filter_params[] = $status_filter;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated subscriptions
$query = "
    SELECT ts.id, ts.tenant_id, ts.status, ts.billing_cycle, ts.start_date, ts.end_date,
           ts.amount, ts.currency, ts.payment_method, ts.last_payment_date, ts.next_billing_date,
           ts.transaction_id, COALESCE(t.name, 'Deleted Tenant') as tenant_name, t.status as tenant_status, p.name as plan_name
    FROM tenant_subscriptions ts
    LEFT JOIN tenants t ON ts.tenant_id = t.id
    LEFT JOIN plans p ON ts.plan_id = p.id
    WHERE 1=1";

if (!empty($search_query)) {
    $query .= " AND (COALESCE(t.name, 'Deleted Tenant') LIKE ? OR t.identifier LIKE ?)";
}
if (!empty($status_filter)) {
    $query .= " AND ts.status = ?";
}
$query .= " ORDER BY ts.start_date DESC LIMIT ? OFFSET ?";

$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$subscriptions = $stmt->fetchAll();
// Fetch active plans for the create and edit subscription modals
$stmt = $pdo->prepare("SELECT id, name, description, features, price, max_users, trial_days FROM plans WHERE status = 'active' ORDER BY name");
$stmt->execute();
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
                            <h5 class="mb-0"><i class="feather icon-credit-card mr-2"></i><?= __('manage_subscriptions') ?></h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_active_subscriptions'); ?></p>
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
                                        $success_message = '';
                                        switch ($_GET['success']) {
                                            case 'subscription_created':
                                                $success_message = __('subscription_created_successfully');
                                                break;
                                        case 'subscription_updated':
                                            $success_message = __('subscription_updated_successfully');
                                            break;
                                        default:
                                            $success_message = __('operation_completed_successfully');
                                    }
                                    echo $success_message;
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
                                        <form method="GET" action="manage_subscriptions.php" class="sa-search-filter">
                                            <div class="sa-search-group">
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search subscriptions..." value="<?= htmlspecialchars($search_query) ?>">
                                                <select class="sa-search-input" name="status" style="flex: 0 0 auto;">
                                                    <option value="">All Statuses</option>
                                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                                <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                                <a href="manage_subscriptions.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Subscriptions Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2>Subscriptions Overview</h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> subscriptions</p>
                                    </div>
                                    <button class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createSubscriptionModal">
                                        <span style="margin-right: 6px;">+</span>Create Subscription
                                    </button>
                                </div>

                                <!-- Subscriptions Grid -->
                                <?php if (!empty($subscriptions)): ?>
                                <div class="sa-3col">
                                    <?php foreach ($subscriptions as $sub):
                                        $status_pill = $sub['status'] === 'active' ? 'pill-green' : ($sub['status'] === 'expired' ? 'pill-red' : 'pill-amber');
                                    ?>
                                    <div class="plan-card">
                                        <div class="pc-header">
                                            <div class="pc-title">
                                                <h3><?= htmlspecialchars($sub['tenant_name']) ?></h3>
                                                <p class="pc-subtitle"><?= htmlspecialchars($sub['plan_name'] ?? 'N/A') ?></p>
                                            </div>
                                            <span class="pill <?= $status_pill ?>"><?= htmlspecialchars($sub['status']) ?></span>
                                        </div>
                                        
                                        <div class="pc-body">
                                            <div class="pc-price">
                                                <span class="price-value"><?= number_format($sub['amount'], 2) ?></span>
                                                <span class="price-label"><?= htmlspecialchars($sub['currency']) ?> / <?= htmlspecialchars($sub['billing_cycle']) ?></span>
                                            </div>
                                            <div class="pc-info">
                                                <div class="pc-info-row">
                                                    <span>Start Date:</span>
                                                    <strong><?= date('M d, Y', strtotime($sub['start_date'])) ?></strong>
                                                </div>
                                                <div class="pc-info-row">
                                                    <span>Next Billing:</span>
                                                    <strong><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : '-' ?></strong>
                                                </div>
                                                <div class="pc-info-row">
                                                    <span>Payment Method:</span>
                                                    <strong><?= htmlspecialchars($sub['payment_method'] ?? '-') ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pc-actions">
                                            <button class="sa-btn sa-btn-small sa-btn-primary edit-subscription-btn"
                                                    data-subscription-id="<?= $sub['id'] ?>"
                                                    data-tenant-id="<?= $sub['tenant_id'] ?>"
                                                    data-toggle="modal"
                                                    data-target="#editSubscriptionModal">Edit</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="sa-card">
                                    <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                        <div style="font-size: 2rem; margin-bottom: 12px;">💳</div>
                                        <div style="font-weight: 600; margin-bottom: 4px;">No Subscriptions Found</div>
                                        <div style="font-size: 0.8rem;"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'Get started by creating a new subscription.' ?></div>
                                    </div>
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

<!-- Create Subscription Modal -->
<div class="modal fade" id="createSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="createSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSubscriptionModalLabel"><?= __('create_subscription') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="create_subscription.php" id="createSubscriptionForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="form-group">
                        <label for="tenant_id"><?= __('tenant') ?></label>
                        <select class="form-control" id="tenant_id" name="tenant_id" required>
                            <option value=""><?= __('select_tenant') ?></option>
                            <?php 
                            // Fetch active tenants
                            $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
                            $stmt->execute();
                            $tenants_list = $stmt->fetchAll();
                            foreach ($tenants_list as $tenant): 
                            ?>
                            <option value="<?= htmlspecialchars($tenant['id']) ?>">
                                <?= htmlspecialchars($tenant['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_plan_id"><?= __('plan') ?></label>
                        <select class="form-control" id="create_plan_id" name="plan_id" required>
                            <option value=""><?= __('select_plan') ?></option>
                            <?php foreach ($plans as $plan): ?>
                            <option value="<?= htmlspecialchars($plan['id']) ?>" 
                                    data-price="<?= htmlspecialchars($plan['price']) ?>">
                                <?= htmlspecialchars($plan['name']) ?> - 
                                <?= number_format($plan['price'], 2) ?> - 
                                <?= htmlspecialchars($plan['max_users']) ?> users - 
                                <?= htmlspecialchars($plan['trial_days']) ?> trial days
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_status"><?= __('status') ?></label>
                        <select class="form-control" id="create_status" name="status" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="pending"><?= __('pending') ?></option>
                            <option value="expired"><?= __('expired') ?></option>
                            <option value="cancelled"><?= __('cancelled') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_billing_cycle"><?= __('billing_cycle') ?></label>
                        <select class="form-control" id="create_billing_cycle" name="billing_cycle" required>
                            <option value="monthly"><?= __('monthly') ?></option>
                            <option value="quarterly"><?= __('quarterly') ?></option>
                            <option value="yearly"><?= __('yearly') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_amount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="create_amount" name="amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_currency"><?= __('currency') ?></label>
                        <input type="text" class="form-control" id="create_currency" name="currency" value="USD" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_payment_method"><?= __('payment_method') ?></label>
                        <input type="text" class="form-control" id="create_payment_method" name="payment_method">
                    </div>
                    
                    <div class="form-group">
                        <label for="create_start_date"><?= __('start_date') ?></label>
                        <input type="date" class="form-control" id="create_start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_next_billing_date"><?= __('next_billing_date') ?></label>
                        <input type="date" class="form-control" id="create_next_billing_date" name="next_billing_date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="submit" form="createSubscriptionForm" class="btn btn-primary"><?= __('create') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Subscription Modal -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="editSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubscriptionModalLabel"><?= __('edit_subscription') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="editSubscriptionLoader" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
                <form method="POST" action="manage_subscriptions.php" id="editSubscriptionForm" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="subscription_id" id="edit_subscription_id">
                    
                    <div class="form-group">
                        <label for="edit_tenant_name"><?= __('tenant') ?></label>
                        <input type="text" class="form-control" id="edit_tenant_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_plan_id"><?= __('plan') ?></label>
                        <select class="form-control" id="edit_plan_id" name="plan_id" required>
                            <?php foreach ($plans as $plan): ?>
                            <option value="<?= htmlspecialchars($plan['id']) ?>" 
                                    data-price="<?= htmlspecialchars($plan['price']) ?>">
                                <?= htmlspecialchars($plan['name']) ?> - 
                                <?= number_format($plan['price'], 2) ?> - 
                                <?= htmlspecialchars($plan['max_users']) ?> users - 
                                <?= htmlspecialchars($plan['trial_days']) ?> trial days
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status"><?= __('status') ?></label>
                        <select class="form-control" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_billing_cycle"><?= __('billing_cycle') ?></label>
                        <select class="form-control" id="edit_billing_cycle" name="billing_cycle" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_amount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="edit_amount" name="amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_currency"><?= __('currency') ?></label>
                        <input type="text" class="form-control" id="edit_currency" name="currency" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_payment_method"><?= __('payment_method') ?></label>
                        <input type="text" class="form-control" id="edit_payment_method" name="payment_method">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_next_billing_date"><?= __('next_billing_date') ?></label>
                        <input type="date" class="form-control" id="edit_next_billing_date" name="next_billing_date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="submit" form="editSubscriptionForm" class="btn btn-primary" id="saveEditSubscription"><?= __('save_changes') ?></button>
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

.page-header.card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 1.5rem;
    border: none;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.page-header.card h5 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
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

/* Dashboard Styling Classes */
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

.sa-btn-ghost {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #e0e0e0;
}

.sa-btn-ghost:hover {
    background: #e8e8e8;
    border-color: #d0d0d0;
}

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

.sa-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.sa-stat {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.sa-stat-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.sa-stat-val {
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
}

.sa-stat-name {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.25rem;
}

.sa-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.sa-stat-icon.si-blue {
    background: #e3f2fd;
    color: #4099ff;
}

.sa-stat-icon.si-green {
    background: #e8f5e9;
    color: #28a745;
}

.sa-3col {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

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

.pc-subtitle {
    font-size: 0.75rem;
    color: var(--muted);
    margin: 4px 0 0 0;
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
    font-size: 0.8rem;
    color: var(--muted);
    margin-left: auto;
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
 </style>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Auto-update amount when plan changes in create subscription modal
document.getElementById('create_plan_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('create_amount').value = price;
    }
});

// Auto-update amount when plan changes in edit subscription modal
document.getElementById('edit_plan_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('edit_amount').value = price;
    }
});

// Initialize amount on create modal open
$('#createSubscriptionModal').on('shown.bs.modal', function () {
    const planSelect = document.getElementById('create_plan_id');
    if (planSelect.selectedIndex > 0) {
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        if (price) {
            document.getElementById('create_amount').value = price;
        }
    }
});

// Handle edit subscription button click
$(document).on('click', '.edit-subscription-btn', function() {
    const subscriptionId = $(this).data('subscription-id');
    const tenantId = $(this).data('tenant-id');

    // Show loader
    $('#editSubscriptionLoader').show();
    $('#editSubscriptionForm').hide();
    $('#saveEditSubscription').prop('disabled', true);

    // Fetch subscription data
    $.ajax({
        url: 'manage_subscriptions.php',
        method: 'GET',
        data: {
            action: 'get_subscription',
            subscription_id: subscriptionId
        },
        dataType: 'json',
        success: function(data) {
            if (data.error) {
                console.error('Error fetching subscription data:', data.error);
                alert('Error loading subscription data: ' + data.error);
                $('#editSubscriptionModal').modal('hide');
                return;
            }

            // Populate form fields
            $('#edit_subscription_id').val(data.id);
            $('#edit_tenant_name').val(data.tenant_name);
            $('#edit_plan_id').val(data.plan_id);
            $('#edit_status').val(data.status);
            $('#edit_billing_cycle').val(data.billing_cycle);
            $('#edit_amount').val(data.amount);
            $('#edit_currency').val(data.currency);
            $('#edit_payment_method').val(data.payment_method || '');
            $('#edit_next_billing_date').val(data.next_billing_date || '');

            // Update modal title
            $('#editSubscriptionModalLabel').text('Edit Subscription - ' + data.tenant_name);

            // Hide loader and show form
            $('#editSubscriptionLoader').hide();
            $('#editSubscriptionForm').show();
            $('#saveEditSubscription').prop('disabled', false);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.status, status, error);
            alert('Network error occurred. Please try again.');
            $('#editSubscriptionModal').modal('hide');
        }
    });
});

// Reset edit modal when closed
$('#editSubscriptionModal').on('hidden.bs.modal', function () {
    $('#editSubscriptionForm')[0].reset();
    $('#editSubscriptionForm').hide();
    $('#editSubscriptionLoader').hide();
    $('#editSubscriptionModalLabel').text('<?= __('edit_subscription') ?>');
});
</script>
</body>
</html>
