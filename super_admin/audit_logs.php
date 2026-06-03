<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'super_admin' AND tenant_id IS NULL");
$stmt->execute();
$super_admins = $stmt->fetchAll();

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

$stmt = $pdo->prepare("SELECT DISTINCT action FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE role = 'super_admin' AND tenant_id IS NULL)");
$stmt->execute();
$actions = $stmt->fetchAll();
?>
<?php include '../includes/header_super_admin.php'; ?>
<style>
:root {
    --brand: #4099ff;
    --brand2: #2ed8b6;
    --bg: #f0f2f5;
    --surface: #fff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
    --radius: 12px;
    --grad: linear-gradient(135deg, var(--brand), var(--brand2));
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
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

.sa-table-wrap {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    -webkit-overflow-scrolling: touch;
}
.sa-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;
}
.sa-toolbar h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.sa-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: #fff; text-decoration: none; transition: opacity .15s;
}
.sa-btn:hover { opacity: .85; }
.sa-table { width: 100%; border-collapse: collapse; }
.sa-table th {
    text-align: left; padding: 12px 20px; font-size: .75rem;
    font-weight: 600; color: var(--muted); text-transform: uppercase;
    letter-spacing: .04em; background: var(--bg); border-bottom: 1px solid var(--border);
}
.sa-table td {
    padding: 12px 20px; font-size: .85rem;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: #f8fafc; }

.filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; align-items: end; }
@media (max-width: 768px) { .filter-grid { grid-template-columns: 1fr; } }
.sa-form-control {
    width: 100%; padding: 8px 12px; border: 1px solid var(--border);
    border-radius: 8px; font-size: .85rem; font-family: inherit;
    background: var(--surface); color: var(--text);
}
.sa-form-control:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64,153,255,.15); }
.sa-form-group { }
.sa-form-label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .8rem; color: var(--text); }

/* ─── USER AVATAR CELL ─── */
.user-cell { display: flex; align-items: center; gap: 8px; }
.user-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 600; font-size: .7rem; flex-shrink: 0;
}
.user-name { font-weight: 500; }

/* ─── ACTION PILL ─── */
.action-pill {
    display: inline-flex; padding: 3px 10px; border-radius: 20px;
    font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .02em;
}
.action-create { background: #d1fae5; color: #065f46; }
.action-update { background: #dbeafe; color: #1e40af; }
.action-delete { background: #fee2e2; color: #991b1b; }
.action-other { background: #ede9fe; color: #6d28d9; }

/* ─── META ─── */
.meta-icon { display: inline-flex; align-items: center; gap: 4px; color: var(--muted); font-size: .8rem; }
.mono { font-family: 'Courier New', monospace; font-size: .8rem; background: var(--bg); padding: 1px 6px; border-radius: 4px; }

.details-cell {
    max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    color: var(--muted); font-size: .8rem;
}

/* ─── PAGINATION ─── */
.pagination-wrap {
    display: flex; align-items: center; justify-content: center;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border); flex-wrap: wrap;
}
.pag-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px;
    border: 1px solid var(--border); border-radius: 8px;
    background: var(--surface); color: var(--text);
    font-size: .8rem; text-decoration: none; transition: all .15s;
}
.pag-btn:hover { border-color: var(--brand); color: var(--brand); }
.pag-btn.active { background: linear-gradient(135deg, var(--brand), var(--brand2)); border-color: var(--brand2); color: #fff; }
.pag-btn.disabled { opacity: .4; pointer-events: none; }
.pag-info { font-size: .75rem; color: var(--muted); }

.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }

/* ─── BADGE COUNT ─── */
.badge-num {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 8px; border-radius: 20px;
    font-size: .7rem; font-weight: 700;
    background: rgba(64,153,255,.15); color: var(--brand);
}
</style>

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
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                                </svg>
                                Audit Logs
                            </h5>
                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9">Track all super admin activities and system changes</p>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- Filter Card -->
                        <div class="sa-table-wrap" style="margin-bottom:24px">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                    Filters
                                </h3>
                                <span class="badge-num"><?= $total_items ?> total</span>
                            </div>
                            <div style="padding:16px 20px">
                                <form method="GET" action="audit_logs.php">
                                    <div class="filter-grid">
                                        <div class="sa-form-group">
                                            <label class="sa-form-label" for="user_id">Super Admin</label>
                                            <select class="sa-form-control" id="user_id" name="user_id">
                                                <option value="">All Users</option>
                                                <?php foreach ($super_admins as $admin): ?>
                                                <option value="<?= $admin['id'] ?>" <?= $user_id == $admin['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($admin['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="sa-form-group">
                                            <label class="sa-form-label" for="action">Action</label>
                                            <select class="sa-form-control" id="action" name="action">
                                                <option value="">All Actions</option>
                                                <?php foreach ($actions as $act): ?>
                                                <option value="<?= htmlspecialchars($act['action']) ?>" <?= $action == $act['action'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($act['action']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="sa-form-group">
                                            <label class="sa-form-label" for="search">Search</label>
                                            <input type="text" class="sa-form-control" id="search" name="search"
                                                   placeholder="Details, entity, user..."
                                                   value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <div class="sa-form-group">
                                            <label class="sa-form-label">&nbsp;</label>
                                            <button type="submit" class="sa-btn" style="width:100%;justify-content:center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                                Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Audit Logs Table -->
                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    Audit Log Entries
                                </h3>
                                <span class="badge-num"><?= count($audit_logs) ?> showing</span>
                            </div>

                            <?php if (!empty($audit_logs)): ?>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Entity</th>
                                        <th>ID</th>
                                        <th>IP Address</th>
                                        <th>Date & Time</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_logs as $log):
                                        $actionClass = 'other';
                                        if (stripos($log['action'], 'create') !== false) $actionClass = 'create';
                                        elseif (stripos($log['action'], 'update') !== false) $actionClass = 'update';
                                        elseif (stripos($log['action'], 'delete') !== false) $actionClass = 'delete';

                                        $initial = strtoupper(substr($log['user_name'], 0, 1));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar"><?= $initial ?></div>
                                                <span class="user-name"><?= htmlspecialchars($log['user_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="action-pill action-<?= $actionClass ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td><span class="meta-icon"><?= htmlspecialchars($log['entity_type']) ?></span></td>
                                        <td><span class="mono"><?= htmlspecialchars($log['entity_id']) ?></span></td>
                                        <td><span class="meta-icon"><?= htmlspecialchars($log['ip_address']) ?></span></td>
                                        <td style="white-space:nowrap"><?= date('M d, Y H:i A', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <?php if (!empty($log['details'])): ?>
                                            <span class="details-cell" title="<?= htmlspecialchars($log['details']) ?>"><?= htmlspecialchars($log['details']) ?></span>
                                            <?php else: ?>
                                            <span style="color:var(--muted)">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:12px">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <p>No audit logs found</p>
                            </div>
                            <?php endif; ?>

                            <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrap">
                                <a class="pag-btn <?= $current_page === 1 ? 'disabled' : '' ?>"
                                   href="?page=<?= $current_page - 1 ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                </a>
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                if ($start_page > 1): ?>
                                <a class="pag-btn" href="?page=1<?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                <?php if ($start_page > 2): ?>
                                <span class="pag-btn disabled">...</span>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a class="pag-btn <?= $i === $current_page ? 'active' : '' ?>"
                                   href="?page=<?= $i ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pag-btn disabled">...</span>
                                <?php endif; ?>
                                <a class="pag-btn" href="?page=<?= $total_pages ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                <?php endif; ?>
                                <a class="pag-btn <?= $current_page === $total_pages ? 'disabled' : '' ?>"
                                   href="?page=<?= $current_page + 1 ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            </div>
                            <div style="text-align:center;padding:0 20px 12px;font-size:.75rem;color:var(--muted)">
                                Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($audit_logs) ?> of <?= $total_items ?> logs
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
