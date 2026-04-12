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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─────────────────────────────────────────────── */
:root {
  --bg:       #f8fafc;
  --surface:  #ffffff;
  --surface2: #f1f5f9;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --accent:   #4099ff;
  --accent2:  #2ed8b6;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --blue:     #3b82f6;
  --purple:   #8b5cf6;
  --radius:   14px;
}

/* ─── RESET / BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────── */
.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { 
    padding: 24px 28px; 
    display: flex; 
    flex-direction: column; 
    gap: 24px; 
}

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius); 
  overflow: hidden;
  transition: all .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 24px;
}
.sa-card:last-child { margin-bottom: 0; }
.sa-card:hover { 
    border-left-color: var(--accent2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sa-card-hdr {
  padding: 16px 24px; 
  border-bottom: 1px solid var(--border);
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(46,216,182,0.02));
}
.sa-card-hdr h3 { 
    font-size: .95rem; 
    font-weight: 600; 
    color: var(--text);
    display: flex;
    align-items: center;
    letter-spacing: -0.01em;
}
.sa-card-body { 
    padding: 24px; 
}

/* Card colors */
.sa-card:nth-child(1) { border-left-color: #6366f1; }
.sa-card:nth-child(2) { border-left-color: #10b981; }

/* ─── BUTTON ─────────────────────────────────────────────── */
.sa-btn {
  font-size: .8rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 8px 16px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: var(--accent); }
.sa-btn-sm {
  padding: 6px 12px;
  font-size: .75rem;
}

/* ─── BADGE ─────────────────────────────────────────────── */
.badge-num {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 6px; border-radius: 20px;
  font-size: .7rem; font-weight: 700;
  background: rgba(108,99,255,.2); color: var(--accent);
  font-family: 'JetBrains Mono', monospace;
}

/* ─── FORM STYLES ────────────────────────────────────────── */
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    align-items: end;
}

.form-group {
    position: relative;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all .15s ease;
    background: var(--surface2);
    color: var(--text);
    font-family: 'Sora', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.15);
    background: var(--surface);
}

/* ─── USER CARD ──────────────────────────────────────────── */
.user-entry {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--muted);
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: all .2s;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.user-entry:last-child { margin-bottom: 0; }
.user-entry:hover {
    border-left-color: var(--accent);
    background: rgba(108,99,255,.02);
}
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 200px;
}
.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.user-details h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 2px;
}
.user-details p {
    font-size: 0.75rem;
    color: var(--muted);
    margin: 0;
}
.user-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.user-role {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.user-role.super_admin { background: rgba(139,92,246,.15); color: var(--purple); }
.user-role.tenant_super_admin { background: rgba(59,130,246,.15); color: var(--blue); }
.user-role.admin { background: rgba(245,158,11,.15); color: var(--amber); }
.user-role.sales_agent { background: rgba(16,185,129,.15); color: var(--green); }
.user-role.other { background: rgba(107,114,128,.15); color: var(--muted); }
.user-tenant {
    font-size: 0.75rem;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 4px;
}
.user-date {
    font-size: 0.75rem;
    color: var(--muted);
    font-family: 'JetBrains Mono', monospace;
}
.user-actions {
    display: flex;
    gap: 8px;
}
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s;
    color: var(--muted);
}
.action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
}
.action-btn.delete:hover {
    border-color: var(--red);
    color: var(--red);
    background: rgba(239,68,68,.05);
}

/* ─── PAGINATION ─────────────────────────────────────────── */
.pagination-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}
.pagination {
    display: flex;
    gap: 6px;
    list-style: none;
    flex-wrap: wrap;
    justify-content: center;
}
.page-item { display: flex; }
.page-link {
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all .15s;
}
.page-link:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
}
.page-item.active .page-link {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-color: var(--accent);
    color: white;
}
.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.pagination-info {
    font-size: 0.75rem;
    color: var(--muted);
}

/* ─── EMPTY STATE ───────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.5;
}
.empty-state-text {
    font-size: 0.9rem;
}

/* ─── SCROLLBAR ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 10px; }

/* ─── PCODED LAYOUT INTEGRATION ──────────────────────────── */
body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .sa-content { padding: 16px; }
    .filter-grid { grid-template-columns: 1fr; }
    .user-entry { flex-direction: column; align-items: flex-start; }
    .user-info { width: 100%; }
    .user-meta { width: 100%; justify-content: flex-start; }
    .user-actions { width: 100%; justify-content: flex-end; }
    .pagination { gap: 4px; }
    .page-link { padding: 6px 10px; font-size: 0.75rem; }
}
</style>

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
                                    <h5 class="m-b-10"><?= __(' manage_users') ?></h5>
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

                <div class="sa-wrap">
                    <div class="sa-content">

                        <!-- Filters Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-filter" style="margin-right:8px"></i>Filters</h3>
                                <button class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createUserModal">
                                    <i class="feather icon-plus"></i>Create User
                                </button>
                            </div>
                            <div class="sa-card-body">
                                <form method="GET" action="manage_users.php">
                                    <div class="filter-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="search"><?= __('search') ?></label>
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Name or email..." value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="tenant_id"><?= __('tenant') ?></label>
                                            <select class="form-control" id="tenant_id" name="tenant_id">
                                                <option value=""><?= __('all_tenants') ?></option>
                                                <?php foreach ($tenants as $tenant): ?>
                                                <option value="<?= $tenant['id'] ?>" <?= $tenant_id == $tenant['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($tenant['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="role"><?= __('role') ?></label>
                                            <select class="form-control" id="role" name="role">
                                                <option value=""><?= __('all_roles') ?></option>
                                                <?php foreach ($roles as $r): ?>
                                                <option value="<?= htmlspecialchars($r['role']) ?>" <?= $role == $r['role'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($r['role']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="sa-btn sa-btn-primary" style="width:100%; justify-content:center;">
                                                <i class="feather icon-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Users List Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-users" style="margin-right:8px"></i>Users List</h3>
                                <span class="badge-num"><?= $total_items ?> total</span>
                            </div>
                            <div class="sa-card-body">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): 
                                        $initial = strtoupper(substr($user['name'], 0, 1));
                                        $roleClass = 'other';
                                        if ($user['role'] === 'super_admin') $roleClass = 'super_admin';
                                        elseif ($user['role'] === 'tenant_super_admin') $roleClass = 'tenant_super_admin';
                                        elseif ($user['role'] === 'admin') $roleClass = 'admin';
                                        elseif ($user['role'] === 'sales_agent') $roleClass = 'sales_agent';
                                    ?>
                                    <div class="user-entry">
                                        <div class="user-info">
                                            <div class="user-avatar"><?= $initial ?></div>
                                            <div class="user-details">
                                                <h4><?= htmlspecialchars($user['name']) ?></h4>
                                                <p><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                        </div>
                                        <div class="user-meta">
                                            <span class="user-role <?= $roleClass ?>"><?= htmlspecialchars($user['role']) ?></span>
                                            <span class="user-tenant">
                                                <i class="feather icon-building"></i>
                                                <?= $user['tenant_name'] ? htmlspecialchars($user['tenant_name']) : 'N/A' ?>
                                            </span>
                                            <span class="user-date">
                                                <i class="feather icon-calendar"></i>
                                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="user-actions">
                                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="action-btn" title="Edit">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                            <button class="action-btn delete delete-user" data-id="<?= $user['id'] ?>" title="Delete">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="feather icon-users"></i></div>
                                        <div class="empty-state-text"><?= __('no_users_found') ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrap">
                                    <ul class="pagination">
                                        <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>">
                                                <i class="feather icon-chevron-left"></i>
                                            </a>
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
                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($tenant_id) ? '&tenant_id=' . urlencode($tenant_id) : '' ?><?= !empty($role) ? '&role=' . urlencode($role) : '' ?>">
                                                <i class="feather icon-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="pagination-info">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($users) ?> of <?= $total_items ?> users
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /sa-content -->
                </div><!-- /sa-wrap -->
            </div><!-- /.pcoded-inner-content -->
        </div><!-- /.pcoded-content -->
    </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
            <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #2ed8b6);border:none;padding:20px 24px">
                <h5 class="modal-title" id="createUserModalLabel" style="color:white;font-weight:600">
                    <i class="feather icon-user-plus" style="margin-right:8px"></i>Create New User
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity:0.8">
                    <span style="color:white">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:24px">
                <form id="createUserForm" method="POST" action="create_user.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="name"><?= __('name') ?></label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="Enter full name">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="email"><?= __('email') ?></label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="Enter email address">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="password"><?= __('password') ?></label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="role"><?= __('role') ?></label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="super_admin">Super Admin</option>
                            <option value="tenant_super_admin">Tenant Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="tenant_id"><?= __('tenant') ?></label>
                        <select class="form-control" id="tenant_id" name="tenant_id">
                            <option value=""><?= __('none') ?></option>
                            <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 24px">
                <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                <button type="submit" form="createUserForm" class="sa-btn sa-btn-primary">Create User</button>
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
