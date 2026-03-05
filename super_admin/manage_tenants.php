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
    error_log("Unauthorized access attempt to manage_tenants.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
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
    
    $stmt = $pdo->prepare("SELECT id, name, subdomain, identifier, status, plan, billing_email, created_at FROM tenants WHERE id = ? AND status != 'deleted'");
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
    $subdomain = trim($_POST['subdomain'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $billing_email = trim($_POST['billing_email'] ?? '');

    $errors = [];

    // Validate input
    if (empty($name) || empty($subdomain) || empty($identifier) || empty($plan) || empty($status) || empty($billing_email)) {
        $errors[] = "All required fields must be filled.";
    }
    if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }
    if (!in_array($status, ['active', 'inactive', 'suspended'])) {
        $errors[] = "Invalid status.";
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $subdomain)) {
        $errors[] = "Invalid subdomain format.";
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $identifier)) {
        $errors[] = "Invalid identifier format.";
    }

    // Check for duplicate subdomain (excluding current tenant)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE subdomain = ? AND id != ? AND status != 'deleted'");
    $stmt->execute([$subdomain, $tenant_id]);
    if ($stmt->fetch()['count'] > 0) {
        $errors[] = "Subdomain already exists.";
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
    if (empty($errors)) {
        // Update tenant
        $stmt = $pdo->prepare("
            UPDATE tenants 
            SET name = ?, subdomain = ?, identifier = ?, plan = ?, status = ?, 
                billing_email = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $subdomain, $identifier, $plan, $status, $billing_email, $tenant_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                                VALUES (?, 'update_tenant', 'tenant', ?, ?, ?, NOW())");
        $details = json_encode([
            'tenant_id' => $tenant_id,
            'name' => $name,
            'subdomain' => $subdomain,
            'status' => $status
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
    $count_query .= " AND (name LIKE ? OR subdomain LIKE ? OR identifier LIKE ? OR billing_email LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
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
$query = "SELECT id, name, subdomain, identifier, status, plan, billing_email, created_at FROM tenants WHERE status != 'deleted'";

if (!empty($search_query)) {
    $query .= " AND (name LIKE ? OR subdomain LIKE ? OR identifier LIKE ? OR billing_email LIKE ?)";
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
$stmt = $pdo->prepare("SELECT name FROM plans WHERE status = 'active'");
$stmt->execute();
$plans = $stmt->fetchAll();
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
                                        <h5 class="mb-0"><i class="feather icon-users mr-2"></i><?php echo __('manage_tenants'); ?></h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage and create tenants for the system</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i>Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12">
                                <?php if (isset($_GET['success'])): ?>
                                <div class="sa-alert sa-alert-success">
                                    <div class="sa-alert-icon">✓</div>
                                    <div class="sa-alert-content">
                                        <?php 
                                        $success_message = '';
                                        switch ($_GET['success']) {
                                            case 'tenant_created':
                                                $success_message = __('tenant_created_successfully');
                                                break;
                                            case 'tenant_updated':
                                                $success_message = __('tenant_updated_successfully');
                                                break;
                                            case 'tenant_deleted':
                                                $success_message = __('tenant_deleted_successfully');
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
                                        <form method="GET" action="manage_tenants.php" class="sa-search-filter">
                                            <div class="sa-search-group">
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search tenants by name, subdomain, or email..." value="<?= htmlspecialchars($search_query) ?>">
                                                <select class="sa-filter-select" name="status">
                                                    <option value="">All Status</option>
                                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                </select>
                                                <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                                <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                                <a href="manage_tenants.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Tenants Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2><?= __('tenants_list') ?></h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> tenants</p>
                                    </div>
                                    <button class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createTenantModal">
                                        <span style="margin-right: 6px;">+</span><?= __('create_tenant') ?>
                                    </button>
                                </div>

                                <!-- Tenants Grid -->
                                <?php if (!empty($tenants)): ?>
                                <div class="sa-3col">
                                    <?php foreach ($tenants as $tenant):
                                        $status_pill = match($tenant['status']) {
                                            'active' => 'pill-green',
                                            'suspended' => 'pill-red',
                                            default => 'pill-amber'
                                        };
                                        $status_icon = match($tenant['status']) {
                                            'active' => '●',
                                            'suspended' => '⊘',
                                            default => '○'
                                        };
                                    ?>
                                    <div class="tenant-card">
                                        <div class="tc-header">
                                            <div class="tc-title">
                                                <h3><?= htmlspecialchars($tenant['name']) ?></h3>
                                                <div class="tc-subdomain"><?= htmlspecialchars($tenant['subdomain']) ?>.mtravels</div>
                                            </div>
                                            <span class="pill <?= $status_pill ?>"><?= htmlspecialchars($tenant['status']) ?></span>
                                        </div>
                                        
                                        <div class="tc-body">
                                            <div class="tc-info-row">
                                                <span class="tc-label">Plan</span>
                                                <span class="tc-value"><?= htmlspecialchars($tenant['plan']) ?></span>
                                            </div>
                                            <div class="tc-info-row">
                                                <span class="tc-label">ID</span>
                                                <span class="tc-value" style="font-family: 'Courier New', monospace; font-size: 0.75rem;"><?= htmlspecialchars($tenant['identifier']) ?></span>
                                            </div>
                                            <div class="tc-info-row">
                                                <span class="tc-label">Email</span>
                                                <span class="tc-value" style="font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($tenant['billing_email']) ?></span>
                                            </div>
                                            <div class="tc-info-row">
                                                <span class="tc-label">Created</span>
                                                <span class="tc-value" style="font-size: 0.8rem;"><?= date('M d, Y', strtotime($tenant['created_at'])) ?></span>
                                            </div>
                                        </div>

                                        <div class="tc-actions">
                                            <button type="button" class="sa-btn sa-btn-small sa-btn-primary edit-tenant-btn"
                                                    data-tenant-id="<?= $tenant['id'] ?>"
                                                    data-toggle="modal"
                                                    data-target="#editTenantModal"
                                                    title="Edit">
                                                Edit
                                            </button>
                                            <a href="generate_agreement.php?id=<?= $tenant['id'] ?>" class="sa-btn sa-btn-small sa-btn-ghost" target="_blank" title="Agreement">
                                                Agreement
                                            </a>
                                            <button class="sa-btn sa-btn-small sa-btn-danger delete-tenant" data-id="<?= $tenant['id'] ?>" title="Delete">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="sa-card">
                                    <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                        <div style="font-size: 2rem; margin-bottom: 12px;">○</div>
                                        <div style="font-weight: 600; margin-bottom: 4px;">No Tenants Found</div>
                                        <div style="font-size: 0.8rem;"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'Get started by creating a new tenant.' ?></div>
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

                                            <!-- Create Tenant Modal -->
                        <div class="modal fade" id="createTenantModal" tabindex="-1" role="dialog" aria-labelledby="createTenantModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="createTenantModalLabel"><?= __('create_new_tenant') ?></h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="createTenantForm" method="POST" action="create_tenant.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            
                                            <div class="form-group">
                                                <label for="tenantName"><?= __('tenant_name') ?></label>
                                                <input type="text" class="form-control" id="tenantName" name="name" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="subdomain"><?= __('subdomain') ?></label>
                                                <input type="text" class="form-control" id="subdomain" name="subdomain" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="identifier"><?= __('identifier') ?></label>
                                                <input type="text" class="form-control" id="identifier" name="identifier" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="plan"><?= __('plan') ?></label>
                                                <select class="form-control" id="plan" name="plan" required>
                                                    <?php foreach ($plans as $plan): ?>
                                                    <option value="<?= htmlspecialchars($plan['name']) ?>"><?= htmlspecialchars($plan['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="billingEmail"><?= __('billing_email') ?></label>
                                                <input type="email" class="form-control" id="billingEmail" name="billing_email" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="agencyName"><?= __('agency_name') ?></label>
                                                <input type="text" class="form-control" id="agencyName" name="agency_name" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="title"><?= __('title') ?></label>
                                                <input type="text" class="form-control" id="title" name="title" value="Travel Agency">
                                            </div>

                                            <div class="form-group">
                                                <label for="phone"><?= __('phone') ?></label>
                                                <input type="text" class="form-control" id="phone" name="phone">
                                            </div>

                                            <div class="form-group">
                                                <label for="address"><?= __('address') ?></label>
                                                <textarea class="form-control" id="address" name="address"></textarea>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                        <button type="submit" form="createTenantForm" class="btn btn-primary"><?= __('create') ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Tenant Modal -->
                        <div class="modal fade" id="editTenantModal" tabindex="-1" role="dialog" aria-labelledby="editTenantModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="editTenantModalLabel"><?= __('edit_tenant') ?></h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="editTenantLoader" class="text-center" style="display: none;">
                                            <div class="spinner-border" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                        </div>
                                        <form method="POST" action="manage_tenants.php" id="editTenantForm" style="display: none;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="update_tenant">
                                            <input type="hidden" name="tenant_id" id="edit_tenant_id">
                                            
                                            <div class="form-group">
                                                <label for="edit_tenant_name"><?= __('tenant_name') ?></label>
                                                <input type="text" class="form-control" id="edit_tenant_name" name="name" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="edit_subdomain"><?= __('subdomain') ?></label>
                                                <input type="text" class="form-control" id="edit_subdomain" name="subdomain" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="edit_identifier"><?= __('identifier') ?></label>
                                                <input type="text" class="form-control" id="edit_identifier" name="identifier" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="edit_plan"><?= __('plan') ?></label>
                                                <select class="form-control" id="edit_plan" name="plan" required>
                                                    <?php foreach ($plans as $plan): ?>
                                                    <option value="<?= htmlspecialchars($plan['name']) ?>"><?= htmlspecialchars($plan['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="edit_status"><?= __('status') ?></label>
                                                <select class="form-control" id="edit_status" name="status" required>
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                    <option value="suspended">Suspended</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="edit_billing_email"><?= __('billing_email') ?></label>
                                                <input type="email" class="form-control" id="edit_billing_email" name="billing_email" required>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                        <button type="submit" form="editTenantForm" class="btn btn-primary" id="saveEditTenant"><?= __('save_changes') ?></button>
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

    .sa-card-hdr {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .sa-card-hdr h3 {
        font-size: 0.85rem;
        font-weight: 600;
        margin: 0;
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
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(108,99,255,0.2);
    }

    .sa-filter-select {
        padding: 9px 12px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-size: 0.8rem;
    }

    .sa-filter-select:focus {
        outline: none;
        border-color: var(--accent);
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

    /* ─── TENANT CARD ─────────────────────────────────────────── */
    .tenant-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 18px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        transition: all 0.2s;
    }

    .tenant-card:hover {
        border-color: rgba(64,153,255,0.3);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(64,153,255,0.15);
    }

    .tc-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .tc-title h3 {
        font-size: 0.9rem;
        font-weight: 600;
        margin: 0 0 4px 0;
        color: var(--text);
        word-break: break-word;
    }

    .tc-subdomain {
        font-size: 0.72rem;
        color: var(--muted);
        font-family: 'Courier New', monospace;
    }

    .tc-body {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 10px 0;
        border-top: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
    }

    .tc-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
    }

    .tc-label {
        color: var(--muted);
        font-weight: 500;
        flex-shrink: 0;
    }

    .tc-value {
        color: var(--text);
        font-weight: 500;
        text-align: right;
        flex: 1;
        margin-left: 10px;
    }

    .tc-actions {
        display: flex;
        gap: 6px;
    }

    .tc-actions .sa-btn {
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
        background: rgba(34,211,160,0.12);
        color: var(--green);
    }

    .pill-red {
        background: rgba(244,63,94,0.12);
        color: var(--red);
    }

    .pill-amber {
        background: rgba(245,158,11,0.12);
        color: var(--amber);
    }

    .pill-blue {
        background: rgba(56,189,248,0.12);
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
        background: rgba(108,99,255,0.1);
        border-color: var(--accent);
        color: var(--accent);
    }

    .sa-pagination-item.active {
        background: linear-gradient(135deg, var(--accent), var(--accent2));
        border-color: var(--accent);
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
// Handle edit tenant button click
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
            $('#edit_subdomain').val(data.subdomain);
            $('#edit_identifier').val(data.identifier);
            $('#edit_plan').val(data.plan);
            $('#edit_status').val(data.status);
            $('#edit_billing_email').val(data.billing_email);
            
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
</script>
</body>
</html>
