<?php
session_start();
require_once '../includes/db.php';

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
    error_log("Unauthorized access attempt to manage_users.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch tenants for filter and create user form
$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$tenants = $stmt->fetchAll();

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$role = $_GET['role'] ?? '';

// Build base query for counting total items
$count_query = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
$filter_params = [];
$filter_types = '';

if ($tenant_id) {
    $count_query .= " AND u.tenant_id = ?";
    $filter_params[] = $tenant_id;
    $filter_types .= 'i';
}
if ($role) {
    $count_query .= " AND u.role = ?";
    $filter_params[] = $role;
    $filter_types .= 's';
}
if (!empty($search_query)) {
    $count_query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_types .= 'ss';
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Build main query with pagination
$query = "SELECT u.id, u.name, u.email, u.role, u.tenant_id, u.created_at, t.name as tenant_name 
          FROM users u 
          LEFT JOIN tenants t ON u.tenant_id = t.id 
          WHERE 1=1";

if ($tenant_id) {
    $query .= " AND u.tenant_id = ?";
}
if ($role) {
    $query .= " AND u.role = ?";
}
if (!empty($search_query)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
}
$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$users = $stmt->fetchAll();

// Fetch distinct roles for filter
$stmt = $pdo->prepare("SELECT DISTINCT role FROM users");
$stmt->execute();
$roles = $stmt->fetchAll();
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
                                    <h5 class="m-b-10"><?= __('manage_users') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('users') ?></a></li>
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
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?= __('users_list') ?></h5>
                                        <button class="btn btn-primary float-right" data-toggle="modal" data-target="#createUserModal">
                                            <i class="feather icon-plus mr-1"></i><?= __('create_user') ?>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                         <form method="GET" action="manage_users.php" class="mb-3">
                                             <div class="row">
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label for="search"><?= __('search') ?></label>
                                                         <input type="text" class="form-control" id="search" name="search" placeholder="Name or email..." value="<?= htmlspecialchars($search_query) ?>">
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label for="tenant_id"><?= __('tenant') ?></label>
                                                         <select class="form-control" id="tenant_id" name="tenant_id">
                                                             <option value=""><?= __('all_tenants') ?></option>
                                                             <?php foreach ($tenants as $tenant): ?>
                                                             <option value="<?= $tenant['id'] ?>" <?= $tenant_id == $tenant['id'] ? 'selected' : '' ?>>
                                                                 <?= htmlspecialchars($tenant['name']) ?>
                                                             </option>
                                                             <?php endforeach; ?>
                                                         </select>
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label for="role"><?= __('role') ?></label>
                                                         <select class="form-control" id="role" name="role">
                                                             <option value=""><?= __('all_roles') ?></option>
                                                             <?php foreach ($roles as $r): ?>
                                                             <option value="<?= htmlspecialchars($r['role']) ?>" <?= $role == $r['role'] ? 'selected' : '' ?>>
                                                                 <?= htmlspecialchars($r['role']) ?>
                                                             </option>
                                                             <?php endforeach; ?>
                                                         </select>
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label>&nbsp;</label>
                                                         <button type="submit" class="btn btn-primary btn-block"><?= __('filter') ?></button>
                                                     </div>
                                                 </div>
                                             </div>
                                         </form>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= __('name') ?></th>
                                                        <th><?= __('email') ?></th>
                                                        <th><?= __('role') ?></th>
                                                        <th><?= __('tenant') ?></th>
                                                        <th><?= __('created_at') ?></th>
                                                        <th><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td><?= htmlspecialchars($user['role']) ?></td>
                                                        <td><?= $user['tenant_name'] ? htmlspecialchars($user['tenant_name']) : 'N/A' ?></td>
                                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                        <td>
                                                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                                                <i class="feather icon-edit"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-danger delete-user" data-id="<?= $user['id'] ?>">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($users)): ?>
                                                    <tr><td colspan="6" class="text-center"><?= __('no_users_found') ?></td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>">Previous</a>
                                            </li>
                                            <?php 
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            if ($start_page > 1): ?>
                                            <li class="page-item">
                                            <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>">1</a>
                                            </li>
                                            <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>"><?= $i ?></a>
                                            </li>
                                            <?php endfor; ?>
                                            <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                            <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>"><?= $total_pages ?></a>
                                            </li>
                                            <?php endif; ?>
                                            <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>">Next</a>
                                            </li>
                                        </ul>
                                        </nav>
                                        <div class="text-center text-muted small mt-2">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($users) ?> of <?= $total_items ?> users
                                        </div>
                                        <?php endif; ?>
                                        </div>
                                        </div>
                            </div>
                        </div>

                       <!-- Create User Modal -->
                        <div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="createUserModalLabel"><?= __('create_new_user') ?></h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="createUserForm" method="POST" action="create_user.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            
                                            <div class="form-group">
                                                <label for="name"><?= __('name') ?></label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="email"><?= __('email') ?></label>
                                                <input type="email" class="form-control" id="email" name="email" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="password"><?= __('password') ?></label>
                                                <input type="password" class="form-control" id="password" name="password" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="role"><?= __('role') ?></label>
                                                <select class="form-control" id="role" name="role" required>
                                                    <option value="super_admin">Super Admin</option>
                                                    <option value="tenant_super_admin">Tenant Super Admin</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="tenant_id"><?= __('tenant') ?></label>
                                                <select class="form-control" id="tenant_id" name="tenant_id">
                                                    <option value=""><?= __('none') ?></option>
                                                    <?php foreach ($tenants as $tenant): ?>
                                                    <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                        <button type="submit" form="createUserForm" class="btn btn-primary"><?= __('create') ?></button>
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
document.querySelectorAll('.delete-user').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('<?= __('confirm_delete_user') ?>')) {
            const userId = this.getAttribute('data-id');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_user.php';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});
</script>
</body>
</html>
