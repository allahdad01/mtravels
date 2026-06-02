<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers (CSP managed by .htaccess)
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

// Handle AJAX requests for get tenant
if (isset($_GET['action']) && $_GET['action'] === 'get_tenant' && isset($_GET['id'])) {
    $tenant_id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT id, name, identifier, status, plan, trial_days, trial_end_date, billing_email, created_at FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
    if ($tenant) {
        header('Content-Type: application/json');
        echo json_encode($tenant);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Tenant not found']);
    }
    exit();
}

// Handle form submission for updating tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tenant') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_tenants.php?error=invalid_csrf');
        exit();
    }
    
    $tenant_id = intval($_POST['tenant_id']);
    $name = trim($_POST['name'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $billing_email = trim($_POST['billing_email'] ?? '');
    $has_trial = isset($_POST['has_trial']) ? true : false;
    $trial_days = intval($_POST['trial_days'] ?? 0);

    $errors = [];

    // Validate input
    if (empty($name) || empty($identifier) || empty($plan) || empty($status) || empty($billing_email)) {
        $errors[] = "All required fields must be filled.";
    }
    if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }
    if (!in_array($status, ['active', 'inactive', 'suspended', 'trial'])) {
        $errors[] = "Invalid status.";
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $identifier)) {
        $errors[] = "Invalid identifier format.";
    }
    // Check for duplicate identifier (excluding current tenant)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE identifier = ? AND id != ? AND status != 'deleted'");
    $stmt->execute([$identifier, $tenant_id]);
    if ($stmt->fetch()['count'] > 0) {
        $errors[] = "Identifier already exists.";
    }
    // Verify plan exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ? AND status = 'active'");
    $stmt->execute([$plan]);
    if ($stmt->fetch()['count'] == 0) {
        $errors[] = "Invalid or inactive plan selected.";
    }

    // Calculate trial_end_date based on trial settings
    $trial_end_date = null;
    if ($has_trial && $trial_days > 0) {
        $trial_end_date = date('Y-m-d', strtotime("+{$trial_days} days"));
        // If enabling trial, set status to trial
        if ($status === 'active') {
            $status = 'trial';
        }
    }

    if (empty($errors)) {
        // Update tenant
         $stmt = $pdo->prepare("
             UPDATE tenants
             SET name = ?, identifier = ?, plan = ?, status = ?,
                 trial_days = ?, trial_end_date = ?,
                 billing_email = ?, updated_at = NOW()
             WHERE id = ?
         ");
         $stmt->execute([$name, $identifier, $plan, $status, $trial_days, $trial_end_date, $billing_email, $tenant_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_tenant', 'tenant', ?, ?, ?, NOW())");
        $details = json_encode([
            'tenant_id' => $tenant_id,
            'name' => $name,
            'identifier' => $identifier,
            'status' => $status,
            'has_trial' => $has_trial,
            'trial_days' => $trial_days,
            'trial_end_date' => $trial_end_date
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $tenant_id, $details, $ip_address]);
        header('Location: manage_tenants.php?success=tenant_updated');
        exit();
    } else {
        header('Location: manage_tenants.php?error=' . urlencode(implode(', ', $errors)));
        exit();
    }
}

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);

// Validate and sanitize search input to prevent DoS and injection attempts
$raw_search = $_GET['search'] ?? '';
$search_query = !empty($raw_search) ? sanitize_search_input($raw_search, 100) : '';
if ($search_query === null) {
    $search_query = ''; // Reject suspicious input
}

$raw_status = $_GET['status'] ?? '';
$status_filter = !empty($raw_status) ? sanitize_search_input($raw_status, 50) : '';
if ($status_filter === null) {
    $status_filter = ''; // Reject suspicious input
}

// Count total items
$count_query = "SELECT COUNT(*) as total FROM tenants WHERE status != 'deleted'";
$filter_params = [];

if (!empty($search_query)) {
    $count_query .= " AND (name LIKE ? OR identifier LIKE ? OR billing_email LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}
if (!empty($status_filter)) {
    $count_query .= " AND status = ?";
    $filter_params[] = $status_filter;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated tenants
$query = "SELECT id, name, identifier, status, plan, trial_days, trial_end_date, billing_email, created_at FROM tenants WHERE status != 'deleted'";

if (!empty($search_query)) {
    $query .= " AND (name LIKE ? OR identifier LIKE ? OR billing_email LIKE ?)";
}
if (!empty($status_filter)) {
    $query .= " AND status = ?";
}
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$tenants = $stmt->fetchAll();

// Fetch plans for create and edit tenant forms
$stmt = $pdo->prepare("SELECT id, name, price, currency, trial_days FROM plans WHERE status = 'active' ORDER BY name");
$stmt->execute();
$plans = $stmt->fetchAll();

// Get status counts for stats
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tenants WHERE status != 'deleted' GROUP BY status");
$stmt->execute();
$status_counts = [];
foreach ($stmt->fetchAll() as $row) {
    $status_counts[$row['status']] = (int)$row['count'];
}
$active_count = $status_counts['active'] ?? 0;
$trial_count = $status_counts['trial'] ?? 0;
$suspended_count = $status_counts['suspended'] ?? 0;
$inactive_count = $status_counts['inactive'] ?? 0;
?>

<?php include '../includes/header_super_admin.php'; ?>

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
                                        <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><?php echo __('manage_tenants'); ?></h5>
                                        <p class="page-desc">Manage and create tenants for the system</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($_GET['success'])): ?>
                            <div class="sa-alert sa-alert-success">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                <div class="sa-alert-content">
                                    <?php 
                                    $success_message = '';
                                    switch ($_GET['success']) {
                                        case 'tenant_created': $success_message = __('tenant_created_successfully'); break;
                                        case 'tenant_updated': $success_message = __('tenant_updated_successfully'); break;
                                        case 'tenant_deleted': $success_message = __('tenant_deleted_successfully'); break;
                                        default: $success_message = __('operation_completed_successfully');
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
                                <div class="sa-alert-content"><?= htmlspecialchars($_GET['error']) ?></div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <?php endif; ?>

                            <!-- Stats Cards -->
                            <div class="sa-stats">
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $total_items ?></span>
                                        <span class="sa-stat-label">Total Tenants</span>
                                    </div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-green"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $active_count ?></span>
                                        <span class="sa-stat-label">Active</span>
                                    </div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $trial_count ?></span>
                                        <span class="sa-stat-label">On Trial</span>
                                    </div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-red"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $suspended_count + $inactive_count ?></span>
                                        <span class="sa-stat-label">Suspended / Inactive</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Toolbar: Search + Filter + Create -->
                            <div class="sa-toolbar">
                                <form method="GET" action="manage_tenants.php" class="sa-toolbar-form">
                                    <div class="sa-toolbar-left">
                                        <div class="sa-search-box">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <input type="text" class="sa-search-input" name="search" placeholder="Search by name, subdomain, or email..." value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <select class="sa-filter-select" name="status">
                                            <option value="">All Status</option>
                                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="trial" <?= $status_filter === 'trial' ? 'selected' : '' ?>>Trial</option>
                                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                        </select>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">Search</button>
                                        <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                        <a href="manage_tenants.php" class="sa-btn sa-btn-ghost sa-btn-sm">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sa-toolbar-right">
                                        <button type="button" class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createTenantModal">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Create Tenant
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Data Table -->
                            <?php if (!empty($tenants)): ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Plan</th>
                                            <th>Status</th>
                                            <th>Trial</th>
                                            <th>Email</th>
                                            <th>Created</th>
                                            <th class="sa-th-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tenants as $tenant):
                                            $initial = strtoupper(substr($tenant['name'], 0, 1));
                                            $status_pill = match($tenant['status']) {
                                                'active' => 'pill-green',
                                                'trial' => 'pill-blue',
                                                'suspended' => 'pill-red',
                                                default => 'pill-amber'
                                            };
                                            $is_trial = !empty($tenant['trial_days']) && $tenant['trial_days'] > 0;
                                            $trial_expired = $is_trial && !empty($tenant['trial_end_date']) && strtotime($tenant['trial_end_date']) < strtotime('today');
                                            $trial_remaining = $is_trial && !empty($tenant['trial_end_date']) ? max(0, (int)((strtotime($tenant['trial_end_date']) - strtotime('today')) / 86400)) : 0;
                                        ?>
                                        <tr class="sa-row">
                                            <td class="sa-td-tenant">
                                                <div class="sa-avatar" style="background:<?= match($tenant['status']) {
                                                    'active' => '#10b981',
                                                    'trial' => '#3b82f6',
                                                    'suspended' => '#ef4444',
                                                    default => '#f59e0b'
                                                } ?>"><?= $initial ?></div>
                                                <div class="sa-tenant-meta">
                                                    <div class="sa-tenant-name"><?= htmlspecialchars($tenant['name']) ?></div>
                                                    <div class="sa-tenant-id"><?= htmlspecialchars($tenant['identifier']) ?></div>
                                                </div>
                                            </td>
                                            <td><span class="sa-plan-badge"><?= htmlspecialchars($tenant['plan']) ?></span></td>
                                            <td><span class="pill <?= $status_pill ?>"><?= htmlspecialchars($tenant['status']) ?></span></td>
                                            <td>
                                                <?php if ($is_trial): ?>
                                                <span class="sa-trial-info <?= $trial_expired ? 'sa-trial-expired' : '' ?>">
                                                    <?= intval($tenant['trial_days']) ?>d
                                                    <?php if ($trial_expired): ?>(expired)
                                                    <?php elseif (!empty($tenant['trial_end_date'])): ?>(<?= $trial_remaining ?>d left)
                                                    <?php endif; ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="sa-na">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="sa-td-email"><?= htmlspecialchars($tenant['billing_email']) ?></td>
                                            <td class="sa-td-date"><?= date('M d, Y', strtotime($tenant['created_at'])) ?></td>
                                            <td class="sa-td-actions">
                                                <button type="button" class="sa-icon-btn edit-tenant-btn" data-tenant-id="<?= $tenant['id'] ?>" data-toggle="modal" data-target="#editTenantModal" title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <a href="generate_agreement.php?id=<?= $tenant['id'] ?>" class="sa-icon-btn" target="_blank" title="Agreement">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                                </a>
                                                <button class="sa-icon-btn sa-icon-btn-danger delete-tenant" data-id="<?= $tenant['id'] ?>" title="Delete">
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <div class="sa-empty-title">No Tenants Found</div>
                                <div class="sa-empty-desc"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'Get started by creating a new tenant.' ?></div>
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
                                <?php if ($start_page > 1): ?><span class="sa-page-ellipsis">...</span><?php endif; ?>
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?= $i ?><?= $query_string ?>" class="sa-page-btn <?= $i === $current_page ? 'sa-page-active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($end_page < $total_pages): ?><span class="sa-page-ellipsis">...</span><?php endif; ?>
                                <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="sa-page-btn" title="Next"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
                                <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="sa-page-btn" title="Last page"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg></a>
                                <?php endif; ?>
                                <span class="sa-page-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                            </div>
                            <?php endif; ?>

                                            <!-- Create Tenant Modal -->
                                            <div class="modal fade" id="createTenantModal" tabindex="-1" role="dialog" aria-labelledby="createTenantModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                            <div class="modal-content sa-modal-content">
                                            <div class="sa-modal-header">
                                            <div class="sa-modal-title-group">
                                            <h5 class="sa-modal-title" id="createTenantModalLabel">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg><?= __('create_new_tenant') ?>
                                            </h5>
                                            <p class="sa-modal-subtitle">Set up a new tenant account with essential information</p>
                                            </div>
                                            <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                            </div>
                                            <div class="sa-modal-body">
                                            <form id="createTenantForm" method="POST" action="create_tenant.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            
                                            <!-- Tenant Configuration Section -->
                                            <div class="sa-form-section">
                                                <h6 class="sa-form-section-title">Tenant Configuration</h6>
                                                <div class="sa-form-grid-2">
                                                    <div class="sa-form-group">
                                                        <label for="tenantName" class="sa-form-label">
                                                            <?= __('tenant_name') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <input type="text" class="sa-form-input" id="tenantName" name="name" placeholder="e.g., Luxury Travels Inc." required>
                                                        <p class="sa-form-hint">The display name for this tenant</p>
                                                    </div>
                                                    
                                                    <div class="sa-form-group">
                                                        <label for="plan" class="sa-form-label">
                                                            <?= __('plan') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <select class="sa-form-input sa-form-select" id="plan" name="plan" required>
                                                            <option value="">Select a plan</option>
                                                            <?php foreach ($plans as $plan): ?>
                                                            <option value="<?= htmlspecialchars($plan['name']) ?>"
                                                                    data-price="<?= htmlspecialchars($plan['price']) ?>"
                                                                    data-currency="<?= htmlspecialchars($plan['currency']) ?>"
                                                                    data-trial-days="<?= htmlspecialchars($plan['trial_days'] ?? 0) ?>">
                                                                <?= htmlspecialchars($plan['name']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div id="planPriceDisplay" class="sa-plan-price">
                                                            <div class="sa-plan-price-header">Plan Price</div>
                                                            <div class="sa-plan-price-value"><span id="planPrice">-</span> <span id="planCurrency">-</span></div>
                                                            <div class="sa-plan-price-footnote">A subscription will be automatically created for this plan</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Trial Period Section -->
                                                <div class="sa-form-section">
                                                    <h6 class="sa-form-section-title">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Trial Period
                                                    </h6>
                                                    <div class="sa-form-grid-2">
                                                        <div class="sa-form-group">
                                                            <label class="sa-form-label" for="has_trial">
                                                                Enable Trial Period
                                                            </label>
                                                            <div class="sa-toggle-row">
                                                                <label class="sa-toggle-switch">
                                                                    <input type="checkbox" id="has_trial" name="has_trial" value="1">
                                                                    <span class="sa-toggle-slider"></span>
                                                                </label>
                                                                <span id="trialStatusLabel" class="sa-toggle-label">No trial</span>
                                                            </div>
                                                            <p class="sa-form-hint">Start this tenant with a free trial period before paid subscription</p>
                                                        </div>
                                                        <div class="sa-form-group" id="trialDaysGroup" style="display: none;">
                                                            <label for="trial_days" class="sa-form-label">Trial Days</label>
                                                            <div class="sa-trial-row">
                                                                <button type="button" class="sa-trial-btn" onclick="adjustTrialDays(-1)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                                                                <input type="number" class="sa-form-input sa-trial-input" id="trial_days" name="trial_days" min="1" max="365" value="14">
                                                                <button type="button" class="sa-trial-btn" onclick="adjustTrialDays(1)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                                                            </div>
                                                            <p class="sa-form-hint">Plan default: <strong id="planTrialDefault">0</strong> days (editable)</p>
                                                            <div id="trialEndDatePreview">
                                                                <span class="sa-trial-end-text">Trial ends: <span id="trialEndDateText">-</span></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Agency Information Section -->
                                            <div class="sa-form-section">
                                                <h6 class="sa-form-section-title">Agency Information</h6>
                                                <div class="sa-form-grid-2">
                                                    <div class="sa-form-group">
                                                        <label for="agencyName" class="sa-form-label">
                                                            <?= __('agency_name') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <input type="text" class="sa-form-input" id="agencyName" name="agency_name" placeholder="e.g., Luxury Travels Inc." required>
                                                    </div>

                                                    <div class="sa-form-group">
                                                        <label for="title" class="sa-form-label"><?= __('title') ?></label>
                                                        <input type="text" class="sa-form-input" id="title" name="title" placeholder="e.g., Travel Agency" value="Travel Agency">
                                                    </div>

                                                    <div class="sa-form-group">
                                                        <label for="billingEmail" class="sa-form-label">
                                                            <?= __('billing_email') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <input type="email" class="sa-form-input" id="billingEmail" name="billing_email" placeholder="billing@agency.com" required>
                                                    </div>

                                                    <div class="sa-form-group">
                                                        <label for="phone" class="sa-form-label"><?= __('phone') ?></label>
                                                        <input type="text" class="sa-form-input" id="phone" name="phone" placeholder="+1 (555) 123-4567">
                                                    </div>
                                                </div>

                                                <div class="sa-form-group">
                                                    <label for="address" class="sa-form-label"><?= __('address') ?></label>
                                                    <textarea class="sa-form-input sa-form-textarea" id="address" name="address" placeholder="Street address, city, state, country" rows="3"></textarea>
                                                </div>
                                            </div>
                                            </form>
                                            </div>
                                            <div class="sa-modal-footer">
                                            <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal"><?= __('cancel') ?></button>
                                            <button type="submit" form="createTenantForm" class="sa-btn sa-btn-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><polyline points="20 6 9 17 4 12"/></svg><?= __('create') ?>
                                            </button>
                                            </div>
                                            </div>
                                            </div>
                                            </div>

                        <!-- Edit Tenant Modal -->
                        <div class="modal fade" id="editTenantModal" tabindex="-1" role="dialog" aria-labelledby="editTenantModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content sa-modal-content">
                                    <div class="sa-modal-header">
                                        <div class="sa-modal-title-group">
                                            <h5 class="sa-modal-title" id="editTenantModalLabel">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><?= __('edit_tenant') ?>
                                            </h5>
                                            <p class="sa-modal-subtitle">Update tenant configuration and details</p>
                                        </div>
                                        <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                    <div class="sa-modal-body">
                                        <div id="editTenantLoader" class="sa-edit-loader">
                                            <div class="sa-spinner"></div>
                                            <p>Loading tenant data...</p>
                                        </div>
                                        <form method="POST" action="manage_tenants.php" id="editTenantForm" style="display: none;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="update_tenant">
                                            <input type="hidden" name="tenant_id" id="edit_tenant_id">
                                            
                                            <!-- Tenant Configuration Section -->
                                            <div class="sa-form-section">
                                                <h6 class="sa-form-section-title">Tenant Configuration</h6>
                                                <div class="sa-form-grid-2">
                                                    <div class="sa-form-group">
                                                        <label for="edit_tenant_name" class="sa-form-label">
                                                            <?= __('tenant_name') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <input type="text" class="sa-form-input" id="edit_tenant_name" name="name" required>
                                                    </div>
                                                    
                                                    <div class="sa-form-group">
                                                        <label for="edit_plan" class="sa-form-label">
                                                            <?= __('plan') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <select class="sa-form-input sa-form-select" id="edit_plan" name="plan" required>
                                                            <?php foreach ($plans as $plan): ?>
                                                            <option value="<?= htmlspecialchars($plan['name']) ?>"><?= htmlspecialchars($plan['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="sa-form-group">
                                                         <label for="edit_identifier" class="sa-form-label">Identifier</label>
                                                         <input type="hidden" id="edit_identifier" name="identifier">
                                                         <input type="text" class="sa-form-input" id="edit_identifier_display" readonly style="background: var(--surface2); cursor: not-allowed;">
                                                         <p class="sa-form-hint">Auto-generated and cannot be changed</p>
                                                     </div>
                                                    
                                                    <div class="sa-form-group">
                                                        <label for="edit_status" class="sa-form-label">
                                                            <?= __('status') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <select class="sa-form-input sa-form-select" id="edit_status" name="status" required>
                                                            <option value="active">Active</option>
                                                            <option value="trial">Trial</option>
                                                            <option value="inactive">Inactive</option>
                                                            <option value="suspended">Suspended</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="sa-form-group">
                                                        <label for="edit_billing_email" class="sa-form-label">
                                                            <?= __('billing_email') ?>
                                                            <span class="sa-required">*</span>
                                                        </label>
                                                        <input type="email" class="sa-form-input" id="edit_billing_email" name="billing_email" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Trial Period Section -->
                                                <div class="sa-form-section">
                                                    <h6 class="sa-form-section-title">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Trial Period
                                                    </h6>
                                                    <div class="sa-form-grid-2">
                                                        <div class="sa-form-group">
                                                            <label class="sa-form-label" for="edit_has_trial">
                                                                Enable Trial Period
                                                            </label>
                                                            <div class="sa-toggle-row">
                                                                <label class="sa-toggle-switch">
                                                                    <input type="checkbox" id="edit_has_trial" name="has_trial" value="1">
                                                                    <span class="sa-toggle-slider"></span>
                                                                </label>
                                                                <span id="editTrialStatusLabel" class="sa-toggle-label">No trial</span>
                                                            </div>
                                                        </div>
                                                        <div class="sa-form-group" id="editTrialDaysGroup" style="display: none;">
                                                            <label for="edit_trial_days" class="sa-form-label">Trial Days</label>
                                                            <div class="sa-trial-row">
                                                                <button type="button" class="sa-trial-btn" onclick="adjustEditTrialDays(-1)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                                                                <input type="number" class="sa-form-input sa-trial-input" id="edit_trial_days" name="trial_days" min="0" max="365" value="14">
                                                                <button type="button" class="sa-trial-btn" onclick="adjustEditTrialDays(1)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                                                            </div>
                                                            <div id="editTrialEndDatePreview">
                                                                <span class="sa-trial-end-text">Trial ends: <span id="editTrialEndDateText">-</span></span>
                                                            </div>
                                                        <?php if (!empty($tenant['trial_end_date'])): ?>
                                                        <p class="sa-form-hint">Current trial end date: <?= htmlspecialchars($tenant['trial_end_date']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="sa-modal-footer">
                                        <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal"><?= __('cancel') ?></button>
                                        <button type="submit" form="editTenantForm" class="sa-btn sa-btn-primary" id="saveEditTenant">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg><?= __('save_changes') ?>
                                        </button>
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

    <style>
    :root {
        --primary: #4099ff;
        --primary-dark: #2673cc;
        --primary-light: #73b4ff;
        --primary-glow: rgba(64,153,255,0.2);
        --secondary: #2ed8b6;
        --secondary-glow: rgba(46,216,182,0.2);
        --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        --accent: #2ed8b6;
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

    /* ─── PAGE HEADER ─────────────────────────────────────────── */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2673cc 50%, #2ed8b6 100%) !important;
        color: #fff;
        border: none !important;
        margin-bottom: 24px;
        padding: 22px 28px !important;
        box-shadow: 0 4px 20px rgba(64,153,255,0.3);
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    .page-header.card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }
    .page-header.card h5 {
        color: #fff !important;
        margin: 0;
        font-weight: 700;
        font-size: 1.15rem;
        position: relative;
        z-index: 1;
    }
    .page-header.card .btn {
        background: rgba(255,255,255,0.12) !important;
        color: #fff;
        border: 1px solid rgba(255,255,255,0.25) !important;
        border-radius: 8px;
        padding: 7px 16px;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.2s;
        position: relative;
        z-index: 1;
        backdrop-filter: blur(4px);
    }
    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.2) !important;
        border-color: rgba(255,255,255,0.4) !important;
        transform: translateY(-1px);
    }
    .page-header.card .row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        position: relative;
        z-index: 2;
    }
    .page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
    .page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }

    /* ─── ALERTS ──────────────────────────────────────────────── */
    .sa-alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        margin-bottom: 16px;
        animation: slideIn 0.3s ease-out;
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

    /* ─── STATS CARDS ─────────────────────────────────────────── */
    .sa-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
    @media (max-width: 992px) { .sa-stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 576px) { .sa-stats { grid-template-columns: 1fr; } }
    .sa-stat-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .sa-stat-card:hover { box-shadow: 0 4px 16px var(--primary-glow); transform: translateY(-2px); }
    .sa-stat-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .sa-stat-icon svg { width: 22px; height: 22px; }
    .sa-stat-blue { background: rgba(64,153,255,0.1); color: var(--primary); }
    .sa-stat-green { background: rgba(16,185,129,0.1); color: var(--green); }
    .sa-stat-blue { background: rgba(59,130,246,0.1); color: var(--blue); }
    .sa-stat-red { background: rgba(239,68,68,0.1); color: var(--red); }
    .sa-stat-body { display: flex; flex-direction: column; gap: 2px; }
    .sa-stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text); line-height: 1.2; }
    .sa-stat-label { font-size: 0.78rem; color: var(--muted); font-weight: 500; }

    /* ─── TOOLBAR ─────────────────────────────────────────────── */
    .sa-toolbar {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .sa-toolbar-form { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .sa-toolbar-left { display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap; }
    .sa-toolbar-right { flex-shrink: 0; }
    .sa-search-box {
        display: flex; align-items: center;
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 8px; padding: 0 12px; min-width: 220px; flex: 1; max-width: 360px;
    }
    .sa-search-icon { flex-shrink: 0; color: var(--muted); }
    .sa-search-box .sa-search-input {
        border: none; background: transparent; padding: 9px 10px; font-size: 0.85rem;
        color: var(--text); flex: 1; outline: none; min-width: 0;
    }
    .sa-search-box .sa-search-input::placeholder { color: var(--muted); }
    .sa-filter-select {
        padding: 9px 12px; background: var(--surface2); border: 1px solid var(--border);
        border-radius: 8px; color: var(--text); font-size: 0.82rem; outline: none; cursor: pointer;
        min-width: 120px;
    }
    .sa-filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
    .sa-btn-sm { padding: 7px 14px; font-size: 0.8rem; }

    /* ─── BUTTONS ─────────────────────────────────────────────── */
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
    .sa-btn-danger { background: #fee2e2; color: var(--red); border: 1px solid #fecaca; }
    .sa-btn-danger:hover { background: #fecaca; border-color: var(--red); }

    /* ─── DATA TABLE ──────────────────────────────────────────── */
    .sa-table-wrap {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .sa-table { width: 100%; border-collapse: collapse; }
    .sa-table thead { background: var(--surface2); }
    .sa-table th {
        padding: 12px 16px; text-align: left; font-size: 0.72rem;
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        color: var(--muted); border-bottom: 1px solid var(--border); white-space: nowrap;
    }
    .sa-table td { padding: 14px 16px; font-size: 0.85rem; color: var(--text); border-bottom: 1px solid var(--border); }
    .sa-table tbody tr:last-child td { border-bottom: none; }
    .sa-table tbody tr { transition: background 0.15s; }
    .sa-table tbody tr:hover { background: rgba(64,153,255,0.03); }
    .sa-th-actions { text-align: right; width: 120px; }
    .sa-td-tenant { display: flex; align-items: center; gap: 12px; }
    .sa-avatar {
        width: 36px; height: 36px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .sa-tenant-meta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .sa-tenant-name { font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sa-tenant-id { font-size: 0.72rem; color: var(--muted); font-family: 'Courier New', monospace; }
    .sa-plan-badge {
        display: inline-block; padding: 3px 10px; border-radius: 6px;
        background: rgba(64,153,255,0.1); color: var(--primary);
        font-size: 0.78rem; font-weight: 600;
    }
    .sa-td-email { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); }
    .sa-td-date { white-space: nowrap; color: var(--muted); font-size: 0.8rem; }
    .sa-td-actions { text-align: right; white-space: nowrap; }
    .sa-trial-info { font-size: 0.8rem; font-weight: 600; color: var(--blue); }
    .sa-trial-expired { color: var(--red); }
    .sa-na { color: var(--muted); }

    /* ─── ICON BUTTONS ────────────────────────────────────────── */
    .sa-icon-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 8px;
        border: 1px solid var(--border); background: var(--surface);
        color: var(--muted); cursor: pointer; transition: all 0.2s;
        text-decoration: none; margin-left: 4px;
    }
    .sa-icon-btn:hover { border-color: var(--primary); color: var(--primary); background: rgba(64,153,255,0.06); }
    .sa-icon-btn-danger:hover { border-color: var(--red); color: var(--red); background: rgba(239,68,68,0.06); }

    /* ─── PILLS ───────────────────────────────────────────────── */
    .pill {
        font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .pill svg { width: 12px; height: 12px; }
    .pill-green { background: rgba(34,211,160,0.12); color: var(--green); }
    .pill-red { background: rgba(244,63,94,0.12); color: var(--red); }
    .pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
    .pill-blue { background: rgba(56,189,248,0.12); color: var(--blue); }

    /* ─── EMPTY STATE ─────────────────────────────────────────── */
    .sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
    .sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
    .sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
    .sa-empty-desc { font-size: 0.85rem; color: var(--muted); }

    /* ─── PAGINATION ──────────────────────────────────────────── */
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

    /* ─── MODAL STYLES ─────────────────────────────────────────── */
    .sa-modal-content { border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 20px 60px rgba(0,0,0,0.15); background: var(--surface); }
    .sa-modal-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 24px; border-bottom: 1px solid var(--border);
        background: linear-gradient(135deg, rgba(64,153,255,0.06), rgba(46,216,182,0.06));
    }
    .sa-modal-title-group { flex: 1; }
    .sa-modal-title { font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text); display: flex; align-items: center; }
    .sa-modal-subtitle { font-size: 0.85rem; color: var(--muted); margin: 6px 0 0 0; }
    .sa-modal-close { background: none; border: none; color: var(--muted); cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; margin-left: 16px; }
    .sa-modal-close:hover { color: var(--text); }
    .sa-modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
    .sa-modal-footer { display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid var(--border); background: var(--surface2); }

    /* ─── FORM SECTIONS ────────────────────────────────────────── */
    .sa-form-section { margin-bottom: 24px; }
    .sa-form-section:last-child { margin-bottom: 0; }
    .sa-form-section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary-dark); margin: 0 0 16px 0; padding-bottom: 12px; border-bottom: 2px solid var(--border); }
    .sa-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 768px) { .sa-form-grid-2 { grid-template-columns: 1fr; } }
    .sa-form-group { display: flex; flex-direction: column; }
    .sa-form-label { font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text); display: flex; align-items: center; gap: 4px; }
    .sa-required { color: var(--red); font-weight: 700; }
    .sa-form-input { padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); font-size: 0.95rem; transition: all 0.2s; font-family: inherit; }
    .sa-form-input::placeholder { color: var(--muted); }
    .sa-form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
    .sa-form-input:disabled { background: var(--surface2); color: var(--muted); cursor: not-allowed; }
    .sa-form-select {
        appearance: none; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234099ff' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px;
    }
    .sa-form-textarea { resize: vertical; min-height: 80px; }
    .sa-form-hint { font-size: 0.75rem; color: var(--muted); margin: 6px 0 0 0; line-height: 1.4; }
    .sa-subdomain-wrapper { display: flex; align-items: center; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); overflow: hidden; }
    .sa-subdomain-wrapper .sa-form-input { flex: 1; border: none; border-radius: 0; }
    .sa-subdomain-suffix { padding: 10px 12px; background: var(--surface2); color: var(--muted); font-weight: 500; font-size: 0.9rem; border-left: 1px solid var(--border); white-space: nowrap; font-family: 'Courier New', monospace; }

    /* ─── TOGGLE ROW ──────────────────────────────────────────── */
    .sa-toggle-row { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
    .sa-toggle-label { font-size: 0.85rem; color: var(--muted); }
    .sa-trial-row { display: flex; align-items: center; gap: 8px; }
    .sa-trial-input { text-align: center; width: 80px; }
    .sa-trial-end-text { font-size: 0.8rem; color: var(--primary); font-weight: 600; }
    #trialEndDatePreview, #editTrialEndDatePreview { margin-top: 8px; padding: 8px 12px; background: rgba(64,153,255,0.06); border-radius: 6px; display: none; }

    /* ─── PLAN PRICE DISPLAY ──────────────────────────────────── */
    #planPriceDisplay.sa-plan-price { margin-top: 12px; padding: 12px; background: var(--surface2); border-radius: 8px; display: none; }
    .sa-plan-price-header { margin: 0; font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; }
    .sa-plan-price-value { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text); }
    .sa-plan-price-footnote { margin: 6px 0 0 0; font-size: 0.75rem; color: var(--muted); }

    /* ─── IDENTIFIER DISPLAY (edit) ───────────────────────────── */
    #edit_identifier_display { background: var(--surface2); cursor: not-allowed; }
    #editTenantForm { display: none; }

    /* ─── TOGGLE SWITCH ────────────────────────────────────────── */
    .sa-toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .sa-toggle-switch input { opacity: 0; width: 0; height: 0; }
    .sa-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border); transition: 0.3s; border-radius: 24px; }
    .sa-toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
    .sa-toggle-switch input:checked + .sa-toggle-slider { background: var(--grad); }
    .sa-toggle-switch input:checked + .sa-toggle-slider:before { transform: translateX(20px); }

    /* ─── TRIAL ADJUST BUTTONS ─────────────────────────────────── */
    .sa-trial-btn { width: 36px; height: 36px; border: 1px solid var(--border); background: var(--surface); border-radius: 8px; font-size: 1.2rem; font-weight: 600; color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
    .sa-trial-btn:hover { background: var(--grad); color: white; border-color: transparent; }
    .sa-trial-btn:active { transform: scale(0.95); }

    /* ─── LOADER ────────────────────────────────────────────────── */
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
// ─── Trial Days Adjustment (Create Modal) ──────────────────────
function adjustTrialDays(delta) {
    const input = document.getElementById('trial_days');
    let val = parseInt(input.value) || 0;
    val = Math.max(1, Math.min(365, val + delta));
    input.value = val;
    updateTrialEndDate();
}

// ─── Trial Days Adjustment (Edit Modal) ────────────────────────
function adjustEditTrialDays(delta) {
    const input = document.getElementById('edit_trial_days');
    let val = parseInt(input.value) || 0;
    val = Math.max(0, Math.min(365, val + delta));
    input.value = val;
    updateEditTrialEndDate();
}

// ─── Calculate Trial End Date (Create Modal) ───────────────────
function updateTrialEndDate() {
    const days = parseInt(document.getElementById('trial_days').value) || 0;
    const preview = document.getElementById('trialEndDatePreview');
    const text = document.getElementById('trialEndDateText');
    if (days > 0) {
        const endDate = new Date();
        endDate.setDate(endDate.getDate() + days);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        text.textContent = endDate.toLocaleDateString('en-US', options);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// ─── Calculate Trial End Date (Edit Modal) ─────────────────────
function updateEditTrialEndDate() {
    const days = parseInt(document.getElementById('edit_trial_days').value) || 0;
    const preview = document.getElementById('editTrialEndDatePreview');
    const text = document.getElementById('editTrialEndDateText');
    if (days > 0) {
        const endDate = new Date();
        endDate.setDate(endDate.getDate() + days);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        text.textContent = endDate.toLocaleDateString('en-US', options);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// ─── Trial Toggle (Create Modal) ───────────────────────────────
document.getElementById('has_trial').addEventListener('change', function() {
    const group = document.getElementById('trialDaysGroup');
    const label = document.getElementById('trialStatusLabel');
    if (this.checked) {
        group.style.display = 'block';
        label.textContent = 'Trial enabled';
        label.style.color = 'var(--blue)';
        updateTrialEndDate();
    } else {
        group.style.display = 'none';
        label.textContent = 'No trial';
        label.style.color = 'var(--muted)';
    }
});

// ─── Trial Toggle (Edit Modal) ─────────────────────────────────
document.getElementById('edit_has_trial').addEventListener('change', function() {
    const group = document.getElementById('editTrialDaysGroup');
    const label = document.getElementById('editTrialStatusLabel');
    if (this.checked) {
        group.style.display = 'block';
        label.textContent = 'Trial enabled';
        label.style.color = 'var(--blue)';
        updateEditTrialEndDate();
    } else {
        group.style.display = 'none';
        label.textContent = 'No trial';
        label.style.color = 'var(--muted)';
        document.getElementById('edit_trial_days').value = 0;
    }
});

// ─── Trial Days Input Change (Create Modal) ────────────────────
document.getElementById('trial_days').addEventListener('input', updateTrialEndDate);

// ─── Trial Days Input Change (Edit Modal) ──────────────────────
document.getElementById('edit_trial_days').addEventListener('input', updateEditTrialEndDate);

// ─── Handle edit tenant button click ───────────────────────────
$(document).on('click', '.edit-tenant-btn', function() {
    const tenantId = $(this).data('tenant-id');
    
    // Show loader
    $('#editTenantLoader').show();
    $('#editTenantForm').hide();
    $('#saveEditTenant').prop('disabled', true);
    
    // Fetch tenant data
    $.ajax({
        url: 'manage_tenants.php',
        method: 'GET',
        data: {
            action: 'get_tenant',
            id: tenantId
        },
        dataType: 'json',
        success: function(data) {
             // Populate form fields
              $('#edit_tenant_id').val(data.id);
              $('#edit_tenant_name').val(data.name);
              $('#edit_identifier').val(data.identifier);
              $('#edit_identifier_display').val(data.identifier);
              $('#edit_plan').val(data.plan);
              $('#edit_status').val(data.status);
              $('#edit_billing_email').val(data.billing_email);
             
             // Populate trial fields
             const hasTrial = data.trial_days && parseInt(data.trial_days) > 0;
             $('#edit_has_trial').prop('checked', hasTrial);
             $('#edit_trial_days').val(hasTrial ? parseInt(data.trial_days) : 0);
             
             if (hasTrial) {
                 $('#editTrialDaysGroup').show();
                 $('#editTrialStatusLabel').text('Trial enabled');
                 $('#editTrialStatusLabel').css('color', 'var(--blue)');
                 if (data.trial_end_date) {
                     $('#editTrialEndDatePreview').show();
                     const endDate = new Date(data.trial_end_date);
                     const options = { year: 'numeric', month: 'short', day: 'numeric' };
                     $('#editTrialEndDateText').text(endDate.toLocaleDateString('en-US', options));
                 }
             } else {
                 $('#editTrialDaysGroup').hide();
                 $('#editTrialStatusLabel').text('No trial');
                 $('#editTrialStatusLabel').css('color', 'var(--muted)');
                 $('#editTrialEndDatePreview').hide();
             }
            
            // Update modal title
            $('#editTenantModalLabel').text('Edit Tenant - ' + data.name);
            
            // Hide loader and show form
            $('#editTenantLoader').hide();
            $('#editTenantForm').show();
            $('#saveEditTenant').prop('disabled', false);
        },
        error: function(xhr, status, error) {
            console.error('Error fetching tenant data:', error);
            alert('Error loading tenant data. Please try again.');
            $('#editTenantModal').modal('hide');
        }
    });
});

// Reset edit modal when closed
$('#editTenantModal').on('hidden.bs.modal', function () {
    $('#editTenantForm')[0].reset();
    $('#editTenantForm').hide();
    $('#editTenantLoader').hide();
    $('#editTrialDaysGroup').hide();
    $('#editTrialEndDatePreview').hide();
    $('#editTrialStatusLabel').text('No trial');
    $('#editTrialStatusLabel').css('color', 'var(--muted)');
    $('#editTenantModalLabel').text('<?= __('edit_tenant') ?>');
});

// Handle delete tenant
document.querySelectorAll('.delete-tenant').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('<?= __('confirm_delete_tenant') ?>')) {
            const tenantId = this.getAttribute('data-id');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_tenant.php';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="tenant_id" value="${tenantId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Handle plan selection and display price + trial days
document.getElementById('plan').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const priceDisplay = document.getElementById('planPriceDisplay');
    
    if (this.value === '') {
        priceDisplay.style.display = 'none';
    } else {
        const price = selectedOption.getAttribute('data-price');
        const currency = selectedOption.getAttribute('data-currency');
        const trialDays = parseInt(selectedOption.getAttribute('data-trial-days')) || 0;
        
        document.getElementById('planPrice').textContent = parseFloat(price).toFixed(2);
        document.getElementById('planCurrency').textContent = currency + '/month';
        priceDisplay.style.display = 'block';
        
        // Update trial days default from plan
        document.getElementById('planTrialDefault').textContent = trialDays;
        if (document.getElementById('has_trial').checked) {
            document.getElementById('trial_days').value = trialDays > 0 ? trialDays : 14;
            updateTrialEndDate();
        }
    }
});

// Initialize price display if plan is pre-selected
window.addEventListener('load', function() {
    const planSelect = document.getElementById('plan');
    if (planSelect && planSelect.value !== '') {
        const event = new Event('change');
        planSelect.dispatchEvent(event);
    }
});
</script>
</body>
</html>
