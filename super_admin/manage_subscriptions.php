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
        // Fetch the subscription's tenant before updating (needed for status sync)
        $tenant_id = null;
        $stmt = $pdo->prepare("SELECT tenant_id FROM tenant_subscriptions WHERE id = ?");
        $stmt->execute([$subscription_id]);
        $sub_row = $stmt->fetch();
        if ($sub_row) {
            $tenant_id = intval($sub_row['tenant_id']);
        }

        // Update subscription
        $stmt = $pdo->prepare("
            UPDATE tenant_subscriptions
            SET plan_id = ?, status = ?, billing_cycle = ?, amount = ?, currency = ?,
                payment_method = ?, next_billing_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$plan_id, $status, $billing_cycle, $amount, $currency, $payment_method, $next_billing_date, $subscription_id]);

        // Sync tenant status with the subscription status
        $tenant_synced = false;
        if ($tenant_id) {
            if ($status === 'active') {
                // Promote trial/expired tenant to an active subscription
                $stmt = $pdo->prepare("
                    UPDATE tenants
                    SET status = 'active',
                        payment_status = 'current',
                        payment_warning_sent = 0,
                        trial_days = 0,
                        trial_end_date = NULL,
                        payment_due_date = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$next_billing_date, $tenant_id]);
                $tenant_synced = true;
            } elseif ($status === 'expired') {
                // Mirror the trial-expiry behaviour used by the cron
                $stmt = $pdo->prepare("UPDATE tenants SET payment_status = 'overdue', updated_at = NOW() WHERE id = ? AND status = 'trial'");
                $stmt->execute([$tenant_id]);
                $tenant_synced = true;
            }
        }

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_subscription', 'subscription', ?, ?, ?, NOW())");
        $details = json_encode([
            'subscription_id' => $subscription_id,
            'plan_id' => $plan_id,
            'status' => $status,
            'billing_cycle' => $billing_cycle,
            'tenant_synced' => $tenant_synced,
            'tenant_status' => $tenant_synced ? ($status === 'active' ? 'active' : 'overdue') : null
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
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><?= __('manage_subscriptions') ?>
                            </h5>
                            <p class="page-desc"><?php echo __('manage_active_subscriptions'); ?></p>
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
                                
                                <!-- Toolbar -->
                                <div class="sa-toolbar">
                                    <form method="GET" action="manage_subscriptions.php" class="sa-toolbar-form">
                                        <div class="sa-toolbar-group">
                                            <span class="sa-toolbar-label">Search</span>
                                            <div class="sa-search-box">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search by tenant..." value="<?= htmlspecialchars($search_query) ?>">
                                            </div>
                                        </div>
                                        <div class="sa-toolbar-group">
                                            <span class="sa-toolbar-label">Status</span>
                                            <select class="sa-filter-select" name="status">
                                                <option value="">All Statuses</option>
                                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="sa-btn sa-btn-primary" style="align-self:flex-end;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Filter
                                        </button>
                                        <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                        <a href="manage_subscriptions.php" class="sa-btn sa-btn-ghost" style="align-self:flex-end;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Reset
                                        </a>
                                        <?php endif; ?>
                                    </form>
                                </div>

                                <!-- Section Header -->
                                <div class="sa-section-header">
                                    <div>
                                        <h2>Subscriptions Overview</h2>
                                        <p><?= $total_items ?> subscriptions</p>
                                    </div>
                                    <button type="button" class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createSubscriptionModal">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Subscription
                                    </button>
                                </div>

                                <!-- Data Table -->
                                <?php if (!empty($subscriptions)): ?>
                                <div class="sa-table-wrap">
                                    <table class="sa-table">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Plan</th>
                                                <th>Amount</th>
                                                <th>Cycle</th>
                                                <th>Status</th>
                                                <th>Start Date</th>
                                                <th>Next Billing</th>
                                                <th class="sa-th-actions">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subscriptions as $sub):
                                                $initial = strtoupper(substr($sub['tenant_name'], 0, 1));
                                            ?>
                                            <tr>
                                                <td class="sa-td-tenant">
                                                    <div class="sa-avatar" style="background:<?= match($sub['status']) {
                                                        'active' => '#10b981',
                                                        'pending' => '#f59e0b',
                                                        'expired' => '#ef4444',
                                                        default => '#6b7280'
                                                    } ?>"><?= $initial ?></div>
                                                    <div class="sa-tenant-meta">
                                                        <div class="sa-tenant-name"><?= htmlspecialchars($sub['tenant_name']) ?></div>
                                                    </div>
                                                </td>
                                                <td><span class="sa-plan-badge"><?= htmlspecialchars($sub['plan_name'] ?? 'N/A') ?></span></td>
                                                <td style="font-weight:600;white-space:nowrap;"><?= number_format($sub['amount'], 2) ?> <?= htmlspecialchars($sub['currency']) ?></td>
                                                <td style="text-transform:capitalize;"><?= htmlspecialchars($sub['billing_cycle']) ?></td>
                                                <td><span class="pill <?= $sub['status'] === 'active' ? 'pill-green' : ($sub['status'] === 'expired' ? 'pill-red' : 'pill-amber') ?>"><?= htmlspecialchars($sub['status']) ?></span></td>
                                                <td class="sa-td-date"><?= date('M d, Y', strtotime($sub['start_date'])) ?></td>
                                                <td class="sa-td-date"><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : '<span class="sa-na">—</span>' ?></td>
                                                <td class="sa-td-actions">
                                                    <button type="button" class="sa-icon-btn edit-subscription-btn"
                                                            data-subscription-id="<?= $sub['id'] ?>"
                                                            data-tenant-id="<?= $sub['tenant_id'] ?>"
                                                            data-toggle="modal"
                                                            data-target="#editSubscriptionModal" title="Edit">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="sa-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                    <div class="sa-empty-title">No Subscriptions Found</div>
                                    <div class="sa-empty-desc"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'Get started by creating a new subscription.' ?></div>
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

<!-- Create Subscription Modal -->
<div class="modal fade" id="createSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="createSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title" id="createSubscriptionModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg><?= __('create_subscription') ?>
                    </h5>
                    <p class="sa-modal-subtitle">Set up a new subscription for a tenant</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-modal-body">
                <form method="POST" action="create_subscription.php" id="createSubscriptionForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <!-- Tenant & Plan Section -->
                    <div class="sa-form-section">
                        <h6 class="sa-form-section-title">Tenant & Plan</h6>
                        <div class="sa-form-grid-2">
                            <div class="sa-form-group">
                                <label for="tenant_id" class="sa-form-label">
                                    <?= __('tenant') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="tenant_id" name="tenant_id" required>
                                    <option value=""><?= __('select_tenant') ?></option>
                                    <?php 
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
                            
                            <div class="sa-form-group">
                                <label for="create_plan_id" class="sa-form-label">
                                    <?= __('plan') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="create_plan_id" name="plan_id" required>
                                    <option value=""><?= __('select_plan') ?></option>
                                    <?php foreach ($plans as $plan): ?>
                                    <option value="<?= htmlspecialchars($plan['id']) ?>" 
                                            data-price="<?= htmlspecialchars($plan['price']) ?>">
                                        <?= htmlspecialchars($plan['name']) ?> (<?= number_format($plan['price'], 2) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subscription Details Section -->
                    <div class="sa-form-section">
                        <h6 class="sa-form-section-title">Subscription Details</h6>
                        <div class="sa-form-grid-2">
                            <div class="sa-form-group">
                                <label for="create_status" class="sa-form-label">
                                    <?= __('status') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="create_status" name="status" required>
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="pending"><?= __('pending') ?></option>
                                    <option value="expired"><?= __('expired') ?></option>
                                    <option value="cancelled"><?= __('cancelled') ?></option>
                                </select>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="create_billing_cycle" class="sa-form-label">
                                    <?= __('billing_cycle') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="create_billing_cycle" name="billing_cycle" required>
                                    <option value="monthly"><?= __('monthly') ?></option>
                                    <option value="quarterly"><?= __('quarterly') ?></option>
                                    <option value="yearly"><?= __('yearly') ?></option>
                                </select>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="create_amount" class="sa-form-label">
                                    <?= __('amount') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <input type="number" step="0.01" class="sa-form-input" id="create_amount" name="amount" placeholder="0.00" required>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="create_currency" class="sa-form-label">
                                    <?= __('currency') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <input type="text" class="sa-form-input" id="create_currency" name="currency" value="USD" placeholder="USD" required>
                                <p class="sa-form-hint">3-letter currency code (e.g., USD, EUR)</p>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="create_start_date" class="sa-form-label">
                                    <?= __('start_date') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <input type="date" class="sa-form-input" id="create_start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="create_next_billing_date" class="sa-form-label"><?= __('next_billing_date') ?></label>
                                <input type="date" class="sa-form-input" id="create_next_billing_date" name="next_billing_date">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Section -->
                    <div class="sa-form-section">
                        <h6 class="sa-form-section-title">Payment Information</h6>
                        <div class="sa-form-grid-2">
                            <div class="sa-form-group">
                                <label for="create_payment_method" class="sa-form-label"><?= __('payment_method') ?></label>
                                <input type="text" class="sa-form-input" id="create_payment_method" name="payment_method" placeholder="e.g., Credit Card, Bank Transfer">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="submit" form="createSubscriptionForm" class="sa-btn sa-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <?= __('create') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Subscription Modal -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="editSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title" id="editSubscriptionModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><?= __('edit_subscription') ?>
                    </h5>
                    <p class="sa-modal-subtitle">Update subscription details and billing information</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-modal-body">
                <div id="editSubscriptionLoader" class="sa-edit-loader">
                    <div class="sa-spinner"></div>
                    <p>Loading subscription data...</p>
                </div>
                <form method="POST" action="manage_subscriptions.php" id="editSubscriptionForm" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="subscription_id" id="edit_subscription_id">
                    
                    <!-- Tenant & Plan Section -->
                    <div class="sa-form-section">
                        <h6 class="sa-form-section-title">Tenant & Plan</h6>
                        <div class="sa-form-grid-2">
                            <div class="sa-form-group">
                                <label for="edit_tenant_name" class="sa-form-label"><?= __('tenant') ?></label>
                                <input type="text" class="sa-form-input" id="edit_tenant_name" readonly>
                                <p class="sa-form-hint">Tenant is read-only for security</p>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="edit_plan_id" class="sa-form-label">
                                    <?= __('plan') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="edit_plan_id" name="plan_id" required>
                                    <?php foreach ($plans as $plan): ?>
                                    <option value="<?= htmlspecialchars($plan['id']) ?>" 
                                            data-price="<?= htmlspecialchars($plan['price']) ?>">
                                        <?= htmlspecialchars($plan['name']) ?> (<?= number_format($plan['price'], 2) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subscription Details Section -->
                    <div class="sa-form-section">
                        <h6 class="sa-form-section-title">Subscription Details</h6>
                        <div class="sa-form-grid-2">
                            <div class="sa-form-group">
                                <label for="edit_status" class="sa-form-label">
                                    <?= __('status') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="edit_status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="pending">Pending</option>
                                    <option value="expired">Expired</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="edit_billing_cycle" class="sa-form-label">
                                    <?= __('billing_cycle') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <select class="sa-form-input sa-form-select" id="edit_billing_cycle" name="billing_cycle" required>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="edit_amount" class="sa-form-label">
                                    <?= __('amount') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <input type="number" step="0.01" class="sa-form-input" id="edit_amount" name="amount" placeholder="0.00" required>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="edit_currency" class="sa-form-label">
                                    <?= __('currency') ?>
                                    <span class="sa-required">*</span>
                                </label>
                                <input type="text" class="sa-form-input" id="edit_currency" name="currency" placeholder="USD" required>
                                <p class="sa-form-hint">3-letter currency code</p>
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="edit_payment_method" class="sa-form-label"><?= __('payment_method') ?></label>
                                <input type="text" class="sa-form-input" id="edit_payment_method" name="payment_method" placeholder="e.g., Credit Card">
                            </div>
                            
                            <div class="sa-form-group">
                                <label for="edit_next_billing_date" class="sa-form-label"><?= __('next_billing_date') ?></label>
                                <input type="date" class="sa-form-input" id="edit_next_billing_date" name="next_billing_date">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="submit" form="editSubscriptionForm" class="sa-btn sa-btn-primary" id="saveEditSubscription">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <?= __('save_changes') ?>
                </button>
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
    background: linear-gradient(135deg, #4099ff 0%, #2673cc 50%, #2ed8b6 100%) !important;
    color: #fff; border: none !important; margin-bottom: 24px;
    padding: 22px 28px !important; box-shadow: 0 4px 20px rgba(64,153,255,0.3);
    border-radius: 12px; position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }
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
.sa-toolbar {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 14px 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sa-toolbar-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.sa-toolbar-group { display: flex; flex-direction: column; gap: 4px; }
.sa-toolbar-label { font-size: 0.7rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.sa-search-box {
    display: flex; align-items: center;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; padding: 0 12px; min-width: 200px; flex: 1; max-width: 300px;
}
.sa-search-icon { flex-shrink: 0; color: var(--muted); }
.sa-search-box .sa-search-input {
    border: none; background: transparent; padding: 8px 10px; font-size: 0.85rem;
    color: var(--text); flex: 1; outline: none; min-width: 0;
}
.sa-search-box .sa-search-input::placeholder { color: var(--muted); }
.sa-filter-select {
    padding: 8px 12px; background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-size: 0.82rem; outline: none; cursor: pointer;
    min-width: 140px;
}
.sa-filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
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
.sa-section-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--border);
}
.sa-section-header h2 { font-size: 1.15rem; font-weight: 600; margin: 0; color: var(--text); }
.sa-section-header p { margin: 2px 0 0 0; font-size: 0.75rem; color: var(--muted); }
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
.sa-th-actions { text-align: right; width: 80px; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent;
    background: var(--surface2); color: var(--muted); cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.sa-icon-btn:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.sa-td-tenant { display: flex; align-items: center; gap: 12px; }
.sa-tenant-meta { display: flex; flex-direction: column; gap: 2px; }
.sa-tenant-name { font-weight: 600; color: var(--text); font-size: 0.9rem; }
.sa-plan-badge {
    display: inline-block; padding: 3px 10px; border-radius: 6px;
    background: rgba(64,153,255,0.08); color: var(--primary); font-size: 0.78rem; font-weight: 500;
}
.sa-td-date { color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
.sa-na { color: var(--muted); font-size: 0.8rem; }
.pill {
    font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
}
.pill-green { background: rgba(16,185,129,0.12); color: var(--green); }
.pill-red { background: rgba(239,68,68,0.12); color: var(--red); }
.pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-blue { background: rgba(59,130,246,0.12); color: var(--blue); }
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
.sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
.sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
.sa-empty-desc { font-size: 0.85rem; color: var(--muted); }
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
.sa-form-input:disabled { background: var(--surface2); color: var(--muted); cursor: not-allowed; }
.sa-form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 32px; }
.sa-form-textarea { resize: vertical; min-height: 80px; }
.sa-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
@media (max-width: 768px) { .sa-form-grid-2 { grid-template-columns: 1fr; } }
.sa-form-section { margin-bottom: 24px; }
.sa-form-section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); margin: 0 0 14px 0; padding-bottom: 10px; border-bottom: 2px solid var(--border); }
.sa-form-hint { font-size: 0.75rem; color: var(--muted); margin: 4px 0 0; }
.sa-required { color: var(--red); font-weight: 700; }
.sa-edit-loader { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; min-height: 200px; }
.sa-spinner { width: 40px; height: 40px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.sa-edit-loader p { color: var(--muted); font-size: 0.9rem; margin: 0; }
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
