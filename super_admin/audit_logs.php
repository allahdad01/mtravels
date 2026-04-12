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

// Pagination and search
$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

// Fetch super admins for filter
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'super_admin' AND tenant_id IS NULL");
$stmt->execute();
$super_admins = $stmt->fetchAll();

// Build base query with filters
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$base_where = "WHERE u.role = 'super_admin' AND u.tenant_id IS NULL";
$filter_params = [];

if ($user_id) {
    $base_where .= " AND al.user_id = ?";
    $filter_params[] = $user_id;
}
if ($action) {
    $base_where .= " AND al.action = ?";
    $filter_params[] = $action;
}
if (!empty($search_query)) {
    $base_where .= " AND (al.details LIKE ? OR al.entity_type LIKE ? OR u.name LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

// Count total items
$count_query = "
    SELECT COUNT(*) as total 
    FROM audit_logs al 
    JOIN users u ON al.user_id = u.id 
    {$base_where}
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated audit logs
$query = "
    SELECT al.user_id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.created_at, u.name as user_name 
    FROM audit_logs al 
    JOIN users u ON al.user_id = u.id 
    {$base_where}
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";
$params = $filter_params;
$params[] = $items_per_page;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll();

// Fetch distinct actions for filter
$stmt = $pdo->prepare("SELECT DISTINCT action FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE role = 'super_admin' AND tenant_id IS NULL)");
$stmt->execute();
$actions = $stmt->fetchAll();
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

/* ─── TOP BAR ────────────────────────────────────────────── */
.sa-topbar {
  padding: 16px 28px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 50;
}
.sa-topbar-left h1 { font-size: 1.05rem; font-weight: 600; letter-spacing: -.02em; }
.sa-topbar-left p  { font-size: .75rem; color: var(--muted); margin-top: 2px; }
.sa-topbar-right   { display: flex; align-items: center; gap: 10px; }
.sa-avatar {
  width: 34px; height: 34px; border-radius: 50%; overflow: hidden;
  border: 2px solid var(--accent); cursor: pointer; flex-shrink: 0;
}
.sa-avatar img { width: 100%; height: 100%; object-fit: cover; }

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

/* Card color variants */
.sa-card:nth-child(1) { border-left-color: #6366f1; }
.sa-card:nth-child(2) { border-left-color: #10b981; }

/* ─── FORM STYLES ────────────────────────────────────────── */
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

/* ─── BADGE ─────────────────────────────────────────────── */
.badge-num {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 6px; border-radius: 20px;
  font-size: .7rem; font-weight: 700;
  background: rgba(108,99,255,.2); color: var(--accent);
  font-family: 'JetBrains Mono', monospace;
}

/* ─── AUDIT LOG ENTRIES ─────────────────────────────────── */
.log-entry {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--muted);
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: all .2s;
}
.log-entry:last-child { margin-bottom: 0; }
.log-entry:hover {
    border-left-color: var(--accent);
    background: rgba(108,99,255,.02);
}
.log-entry-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}
.log-user {
    display: flex;
    align-items: center;
    gap: 8px;
}
.log-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.75rem;
}
.log-name {
    font-weight: 600;
    font-size: 0.85rem;
}
.log-action {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.log-action.create { background: rgba(16,185,129,.15); color: var(--green); }
.log-action.update { background: rgba(59,130,246,.15); color: var(--blue); }
.log-action.delete { background: rgba(239,68,68,.15); color: var(--red); }
.log-action.other { background: rgba(139,92,246,.15); color: var(--purple); }
.log-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.75rem;
    color: var(--muted);
}
.log-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.log-details {
    margin-top: 10px;
    padding: 10px 12px;
    background: var(--surface2);
    border-radius: 6px;
    font-size: 0.8rem;
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
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
.page-item {
    display: flex;
}
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
    .log-entry-header { flex-direction: column; align-items: flex-start; }
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
                                    <h5 class="m-b-10"><?= __('audit_logs') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('audit_logs') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="sa-wrap">
                    <div class="sa-content">

                        <!-- Filter Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-filter" style="margin-right:8px"></i>Filters</h3>
                                <span class="badge-num"><?= $total_items ?> total</span>
                            </div>
                            <div class="sa-card-body">
                                <form method="GET" action="audit_logs.php">
                                    <div class="filter-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="user_id"><?= __('super_admin') ?></label>
                                            <select class="form-control" id="user_id" name="user_id">
                                                <option value=""><?= __('all_users') ?></option>
                                                <?php foreach ($super_admins as $admin): ?>
                                                <option value="<?= $admin['id'] ?>" <?= $user_id == $admin['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($admin['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="action"><?= __('action') ?></label>
                                            <select class="form-control" id="action" name="action">
                                                <option value=""><?= __('all_actions') ?></option>
                                                <?php foreach ($actions as $act): ?>
                                                <option value="<?= htmlspecialchars($act['action']) ?>" <?= $action == $act['action'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($act['action']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="search">Search</label>
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Details, entity type, user..." value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="sa-btn" style="background: linear-gradient(135deg, var(--accent), var(--accent2)); color: white; width: 100%; justify-content: center;">
                                                <i class="feather icon-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Audit Logs Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-activity" style="margin-right:8px"></i>Audit Log Entries</h3>
                            </div>
                            <div class="sa-card-body">
                                <?php if (!empty($audit_logs)): ?>
                                    <?php foreach ($audit_logs as $log): 
                                        $actionClass = 'other';
                                        if (stripos($log['action'], 'create') !== false) $actionClass = 'create';
                                        elseif (stripos($log['action'], 'update') !== false) $actionClass = 'update';
                                        elseif (stripos($log['action'], 'delete') !== false) $actionClass = 'delete';
                                        
                                        $initial = strtoupper(substr($log['user_name'], 0, 1));
                                    ?>
                                    <div class="log-entry">
                                        <div class="log-entry-header">
                                            <div class="log-user">
                                                <div class="log-avatar"><?= $initial ?></div>
                                                <span class="log-name"><?= htmlspecialchars($log['user_name']) ?></span>
                                            </div>
                                            <span class="log-action <?= $actionClass ?>"><?= htmlspecialchars($log['action']) ?></span>
                                        </div>
                                        <div class="log-meta">
                                            <div class="log-meta-item">
                                                <i class="feather icon-database"></i>
                                                <span><?= htmlspecialchars($log['entity_type']) ?></span>
                                            </div>
                                            <div class="log-meta-item">
                                                <i class="feather icon-hash"></i>
                                                <span>ID: <?= htmlspecialchars($log['entity_id']) ?></span>
                                            </div>
                                            <div class="log-meta-item">
                                                <i class="feather icon-map-pin"></i>
                                                <span><?= htmlspecialchars($log['ip_address']) ?></span>
                                            </div>
                                            <div class="log-meta-item">
                                                <i class="feather icon-clock"></i>
                                                <span><?= date('M d, Y H:i A', strtotime($log['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <?php if (!empty($log['details'])): ?>
                                        <div class="log-details">
                                            <?= htmlspecialchars($log['details']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="feather icon-inbox"></i></div>
                                        <div class="empty-state-text"><?= __('no_audit_logs_found') ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrap">
                                    <ul class="pagination">
                                        <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <i class="feather icon-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php 
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <i class="feather icon-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="pagination-info">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($audit_logs) ?> of <?= $total_items ?> logs
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
