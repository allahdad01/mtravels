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
    session_unset(); session_destroy();
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
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

$count_query = "SELECT COUNT(*) as total FROM custom_plan_requests WHERE 1=1";
$filter_params = [];

if (!empty($status_filter)) {
    $count_query .= " AND status = ?";
    $filter_params[] = $status_filter;
}
if (!empty($search_query)) {
    $count_query .= " AND (contact_name LIKE ? OR contact_email LIKE ? OR contact_phone LIKE ? OR agency_name LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

$query = "SELECT * FROM custom_plan_requests WHERE 1=1";
if (!empty($status_filter)) {
    $query .= " AND status = ?";
}
if (!empty($search_query)) {
    $query .= " AND (contact_name LIKE ? OR contact_email LIKE ? OR contact_phone LIKE ? OR agency_name LIKE ?)";
}
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$requests = $stmt->fetchAll();

// Get counts for each status
$status_counts = [];
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM custom_plan_requests GROUP BY status");
$stmt->execute();
while ($row = $stmt->fetch()) {
    $status_counts[$row['status']] = $row['count'];
}

require_once '../includes/header_super_admin.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                Custom Plan Requests
                            </h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Review and manage custom plan requests from potential agencies</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="dashboard.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($_GET['success'])): ?>
                                <div class="sa-alert sa-alert-success">
                                    <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                    <div class="sa-alert-content">
                                        <?php
                                        switch ($_GET['success']) {
                                            case 'request_updated': echo 'Request updated successfully.'; break;
                                            case 'request_converted': echo 'Request converted to tenant successfully.'; break;
                                            case 'request_deleted': echo 'Request deleted successfully.'; break;
                                            default: echo 'Operation completed successfully.';
                                        }
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

                                <!-- Search & Filter -->
                                <div class="sa-card" style="margin-bottom: 20px;">
                                    <div class="sa-card-body">
                                        <form method="GET" action="manage_custom_plan_requests.php" class="sa-search-filter">
                                            <div class="sa-search-group" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search by name, email, phone or agency..." value="<?= htmlspecialchars($search_query) ?>" style="flex:1;min-width:200px;">
                                                <select name="status" class="sa-search-input" style="width:auto;min-width:140px;">
                                                    <option value="">All Statuses</option>
                                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                    <option value="negotiating" <?php echo $status_filter === 'negotiating' ? 'selected' : ''; ?>>Negotiating</option>
                                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    <option value="converted" <?php echo $status_filter === 'converted' ? 'selected' : ''; ?>>Converted</option>
                                                </select>
                                                <button type="submit" class="sa-btn sa-btn-primary">Filter</button>
                                                <?php if (!empty($search_query) || !empty($status_filter)): ?>
                                                <a href="manage_custom_plan_requests.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2>All Requests</h2>
                                        <p style="margin: 4px 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> requests</p>
                                    </div>
                                </div>

                                <!-- Status Stats -->
                                <div class="sa-stat-grid" style="margin-bottom:20px;">
                                    <?php
                                    $statuses = [
                                        'pending' => ['Pending', 'var(--amber)', 'rgba(245,158,11,0.12)'],
                                        'contacted' => ['Contacted', 'var(--blue)', 'rgba(59,130,246,0.12)'],
                                        'negotiating' => ['Negotiating', 'var(--primary)', 'rgba(64,153,255,0.12)'],
                                        'approved' => ['Approved', 'var(--green)', 'rgba(16,185,129,0.12)'],
                                        'rejected' => ['Rejected', 'var(--red)', 'rgba(239,68,68,0.12)'],
                                        'converted' => ['Converted', 'var(--green)', 'rgba(16,185,129,0.12)'],
                                    ];
                                    foreach ($statuses as $key => $info):
                                        $count = $status_counts[$key] ?? 0;
                                    ?>
                                    <div class="sa-stat" style="cursor:pointer;<?= $status_filter === $key ? 'border-color:' . $info[1] . ';box-shadow:0 0 0 2px ' . $info[2] . ';' : '' ?>"
                                         onclick="window.location='?status=<?= $key ?>'">
                                        <div class="sa-stat-top">
                                            <div>
                                                <div class="sa-stat-val" style="color:<?= $info[1] ?>"><?= $count ?></div>
                                                <div class="sa-stat-name"><?= $info[0] ?></div>
                                            </div>
                                            <div class="sa-stat-icon" style="background:<?= $info[2] ?>;color:<?= $info[1] ?>;">
                                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Data Table -->
                                <?php if (!empty($requests)): ?>
                                <div class="sa-table-wrap">
                                    <table class="sa-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Contact</th>
                                                <th>Agency</th>
                                                <th>Features</th>
                                                <th>Users</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th class="sa-th-actions">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $req):
                                                $features = json_decode($req['selected_features'], true) ?? [];
                                            ?>
                                            <tr>
                                                <td style="font-weight:600;">#<?= $req['id'] ?></td>
                                                <td>
                                                    <div style="font-weight:600;"><?= htmlspecialchars($req['contact_name']) ?></div>
                                                    <div style="font-size:0.75rem;color:var(--muted);">
                                                        <?= htmlspecialchars($req['contact_email']) ?> &middot; <?= htmlspecialchars($req['contact_phone']) ?>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($req['agency_name'] ?: '—') ?></td>
                                                <td>
                                                    <span class="pill pill-blue"><?= count($features) ?> features</span>
                                                </td>
                                                <td><?= $req['max_users'] ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = match($req['status']) {
                                                        'pending' => 'pill-amber',
                                                        'contacted' => 'pill-blue',
                                                        'negotiating' => 'pill-blue',
                                                        'approved' => 'pill-green',
                                                        'rejected' => 'pill-red',
                                                        'converted' => 'pill-green',
                                                        default => 'pill-gray'
                                                    };
                                                    ?>
                                                    <span class="pill <?= $status_class ?>"><?= htmlspecialchars($req['status']) ?></span>
                                                </td>
                                                <td style="font-size:0.8rem;color:var(--muted);"><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
                                                <td class="sa-td-actions">
                                                    <a href="view_custom_plan_request.php?id=<?= $req['id'] ?>" class="sa-icon-btn" title="View Details">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="sa-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                    <div class="sa-empty-title">No Custom Plan Requests</div>
                                    <div class="sa-empty-desc"><?= !empty($search_query) || !empty($status_filter) ? 'Try adjusting your filters.' : 'When agencies request custom plans, they will appear here.' ?></div>
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
                                    <a href="?page=1<?= $query_string ?>" class="sa-page-btn" title="First"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg></a>
                                    <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="sa-page-btn" title="Previous"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
                                    <?php endif; ?>
                                    <?php if ($start_page > 1): ?><span class="sa-page-ellipsis">...</span><?php endif; ?>
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?page=<?= $i ?><?= $query_string ?>" class="sa-page-btn <?= $i === $current_page ? 'sa-page-active' : '' ?>"><?= $i ?></a>
                                    <?php endfor; ?>
                                    <?php if ($end_page < $total_pages): ?><span class="sa-page-ellipsis">...</span><?php endif; ?>
                                    <?php if ($current_page < $total_pages): ?>
                                    <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="sa-page-btn" title="Next"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
                                    <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="sa-page-btn" title="Last"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg></a>
                                    <?php endif; ?>
                                    <span class="sa-page-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
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
.sa-alert {
    display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
    border-radius: var(--radius); border: 1px solid var(--border);
    margin-bottom: 16px; animation: slideIn 0.3s ease-out;
}
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.sa-alert-icon { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sa-alert-content { flex: 1; font-size: 0.85rem; }
.sa-alert-close { background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; transition: color 0.2s; flex-shrink: 0; display: flex; }
.sa-alert-success { background: #d1fae5; border-color: var(--green); color: #065f46; }
.sa-alert-danger { background: #fee2e2; border-color: var(--red); color: #7f1d1d; }
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
.sa-search-input { padding: 9px 12px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 0.8rem; }
.sa-search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-glow); }
.sa-shdr { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 14px; }
.sa-shdr h2 { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); font-weight: 700; margin: 0; }
.sa-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
.sa-stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 18px; transition: all 0.2s; }
.sa-stat:hover { transform: translateY(-2px); border-color: rgba(64,153,255,0.3); box-shadow: 0 4px 16px var(--primary-glow); }
.sa-stat-top { display: flex; align-items: flex-start; justify-content: space-between; }
.sa-stat-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sa-stat-icon svg { width: 16px; height: 16px; }
.sa-stat-val { font-size: 1.3rem; font-weight: 700; letter-spacing: -0.03em; }
.sa-stat-name { font-size: 0.7rem; color: var(--muted); margin-top: 2px; font-weight: 500; }
.pill {
    font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
}
.pill-green { background: rgba(16,185,129,0.12); color: var(--green); }
.pill-red { background: rgba(239,68,68,0.12); color: var(--red); }
.pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-blue { background: rgba(59,130,246,0.12); color: var(--blue); }
.pill-gray { background: rgba(107,114,128,0.12); color: var(--muted); }
.sa-table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table thead { background: var(--surface2); }
.sa-table th { padding: 12px 16px; text-align: left; font-weight: 600; color: var(--muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); white-space: nowrap; }
.sa-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: rgba(64,153,255,0.03); }
.sa-th-actions { text-align: right; width: 70px; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent;
    background: var(--surface2); color: var(--muted); cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.sa-icon-btn:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
.sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
.sa-empty-desc { font-size: 0.85rem; color: var(--muted); }
.sa-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 20px; padding: 14px; flex-wrap: wrap; }
.sa-page-btn { min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-decoration: none; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 500; transition: all 0.2s; cursor: pointer; }
.sa-page-btn:hover:not(.sa-page-active) { background: rgba(64,153,255,0.06); border-color: var(--primary); color: var(--primary); }
.sa-page-active { background: var(--grad); border-color: transparent; color: #fff; }
.sa-page-ellipsis { color: var(--muted); font-size: 0.8rem; padding: 0 4px; }
.sa-page-info { font-size: 0.75rem; color: var(--muted); margin-left: 10px; }
</style>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
