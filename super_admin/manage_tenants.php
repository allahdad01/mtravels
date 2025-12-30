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
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

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
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10"><?= __('manage_tenants') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('tenants') ?></a></li>
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
                                <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
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
                                        <h5><?= __('tenants_list') ?></h5>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#createTenantModal">
                                            <i class="feather icon-plus mr-1"></i><?= __('create_tenant') ?>
                                        </button>
                                    </div>
                                    <div class="card-body table-border-style">
                                         <div class="mb-3">
                                             <form method="GET" action="manage_tenants.php" class="form-inline">
                                                 <input type="text" class="form-control mr-2" name="search" placeholder="Search tenants..." value="<?= htmlspecialchars($search_query) ?>" style="width: 200px;">
                                                 <select class="form-control mr-2" name="status" style="width: 120px;">
                                                     <option value="">All Status</option>
                                                     <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                                     <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                     <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                 </select>
                                                 <button type="submit" class="btn btn-primary mr-2">Search</button>
                                                 <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                                 <a href="manage_tenants.php" class="btn btn-secondary">Clear</a>
                                                 <?php endif; ?>
                                             </form>
                                         </div>
                                         <div class="table-responsive">
                                             <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= __('name') ?></th>
                                                        <th><?= __('subdomain') ?></th>
                                                        <th><?= __('identifier') ?></th>
                                                        <th><?= __('plan') ?></th>
                                                        <th><?= __('status') ?></th>
                                                        <th><?= __('billing_email') ?></th>
                                                        <th><?= __('created_at') ?></th>
                                                        <th><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($tenants as $tenant): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($tenant['name']) ?></td>
                                                        <td><?= htmlspecialchars($tenant['subdomain']) ?></td>
                                                        <td><?= htmlspecialchars($tenant['identifier']) ?></td>
                                                        <td><?= htmlspecialchars($tenant['plan']) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $tenant['status'] === 'active' ? 'success' : ($tenant['status'] === 'suspended' ? 'danger' : 'warning') ?>">
                                                                <?= htmlspecialchars($tenant['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($tenant['billing_email']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($tenant['created_at'])) ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary edit-tenant-btn" 
                                                                    data-tenant-id="<?= $tenant['id'] ?>" 
                                                                    data-toggle="modal" 
                                                                    data-target="#editTenantModal">
                                                                <i class="feather icon-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger delete-tenant" data-id="<?= $tenant['id'] ?>">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($tenants)): ?>
                                                    <tr><td colspan="8" class="text-center"><?= __('no_tenants_found') ?></td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                                </table>
                                                </div>
                                                
                                                <!-- Pagination -->
                                                <?php if ($total_pages > 1): ?>
                                                <nav aria-label="Page navigation" class="mt-3">
                                                <ul class="pagination justify-content-center">
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">Previous</a>
                                                </li>
                                                <?php 
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);
                                                if ($start_page > 1): ?>
                                                <li class="page-item">
                                                <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>"><?= $i ?></a>
                                                </li>
                                                <?php endfor; ?>
                                                <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>"><?= $total_pages ?></a>
                                                </li>
                                                <?php endif; ?>
                                                <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>">Next</a>
                                                </li>
                                                </ul>
                                                </nav>
                                                <div class="text-center text-muted small mt-2">
                                                Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($tenants) ?> of <?= $total_items ?> tenants
                                                </div>
                                                <?php endif; ?>
                                                </div>
                                                </div>
                                                </div>
                                                </div>

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
