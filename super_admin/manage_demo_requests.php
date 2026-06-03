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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_demo_requests.php?error=invalid_csrf');
        exit();
    }

    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['status'];

    $valid_statuses = ['pending', 'contacted', 'scheduled', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        header('Location: manage_demo_requests.php?error=invalid_status');
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE demo_requests SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $request_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_demo_request_status', 'demo_request', ?, ?, ?, NOW())");
        $details = json_encode(['new_status' => $new_status]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $request_id, $details, $ip_address]);
        header('Location: manage_demo_requests.php?success=status_updated');
        exit();
    } catch (Exception $e) {
        header('Location: manage_demo_requests.php?error=update_failed');
        exit();
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_demo_requests.php?error=invalid_csrf');
        exit();
    }

    $request_id = intval($_POST['request_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM demo_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'delete_demo_request', 'demo_request', ?, ?, ?, NOW())");
        $details = json_encode(['action' => 'deleted']);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $request_id, $details, $ip_address]);
        header('Location: manage_demo_requests.php?success=request_deleted');
        exit();
    } catch (Exception $e) {
        header('Location: manage_demo_requests.php?error=delete_failed');
        exit();
    }
}

// Pagination and filters
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Count total
$count_query = "SELECT COUNT(*) as total FROM demo_requests WHERE 1=1";
$filter_params = [];
if ($status_filter) {
    $count_query .= " AND status = ?";
    $filter_params[] = $status_filter;
}
if ($search) {
    $count_query .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
    $search_param = "%$search%";
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
}
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated requests
$query = "SELECT * FROM demo_requests WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$demo_requests = $stmt->fetchAll();

// Get status counts for summary
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM demo_requests GROUP BY status");
$stmt->execute();
$status_counts = $stmt->fetchAll();
$status_summary = array_column($status_counts, 'count', 'status');
$total_requests = array_sum($status_summary);
?>

<?php include '../includes/header_super_admin.php'; ?>

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
    --purple: #8b5cf6;
}
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
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .btn {
    background: rgba(255,255,255,0.12) !important; color: #fff;
    border: 1px solid rgba(255,255,255,0.25) !important; border-radius: 8px;
    padding: 7px 16px; font-size: 0.8rem; font-weight: 500;
    transition: all 0.2s; position: relative; z-index: 1;
    backdrop-filter: blur(4px); text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
}
.page-header.card .btn:hover { background: rgba(255,255,255,0.2) !important; border-color: rgba(255,255,255,0.4) !important; transform: translateY(-1px); }
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
.sa-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 20px; }
@media (max-width: 992px) { .sa-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 576px) { .sa-stats { grid-template-columns: repeat(2, 1fr); } }
.sa-stat-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 14px 14px; display: flex; align-items: center; gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: box-shadow 0.2s, transform 0.2s;
}
.sa-stat-card:hover { box-shadow: 0 4px 16px var(--primary-glow); transform: translateY(-2px); }
.sa-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sa-stat-icon svg { width: 20px; height: 20px; }
.sa-stat-blue { background: rgba(64,153,255,0.1); color: var(--primary); }
.sa-stat-amber { background: rgba(245,158,11,0.1); color: var(--amber); }
.sa-stat-purple { background: rgba(139,92,246,0.1); color: var(--purple); }
.sa-stat-green { background: rgba(16,185,129,0.1); color: var(--green); }
.sa-stat-red { background: rgba(239,68,68,0.1); color: var(--red); }
.sa-stat-gray { background: rgba(107,114,128,0.1); color: var(--muted); }
.sa-stat-body { display: flex; flex-direction: column; gap: 1px; }
.sa-stat-value { font-size: 1.3rem; font-weight: 700; color: var(--text); line-height: 1.2; }
.sa-stat-label { font-size: 0.68rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
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
.sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
.sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
.sa-empty-desc { font-size: 0.85rem; color: var(--muted); }
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
.sa-th-actions { text-align: right; width: 120px; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-td-actions .sa-icon-btn { margin-left: 4px; }
.sa-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent;
    background: var(--surface2); color: var(--muted); cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.sa-icon-btn:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-icon-btn-danger:hover { background: rgba(239,68,68,0.08); border-color: var(--red); color: var(--red); }
.sa-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.sa-td-tenant { display: flex; align-items: center; gap: 12px; }
.sa-tenant-meta { display: flex; flex-direction: column; gap: 2px; }
.sa-tenant-name { font-weight: 600; color: var(--text); font-size: 0.9rem; }
.sa-tenant-id { font-size: 0.75rem; color: var(--muted); }
.sa-td-date { color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
.sa-na { color: var(--muted); font-size: 0.8rem; }
.pill {
    font-size: 0.62rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 4px;
}
.pill-pending { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-contacted { background: rgba(59,130,246,0.12); color: var(--blue); }
.pill-scheduled { background: rgba(139,92,246,0.12); color: var(--purple); }
.pill-completed { background: rgba(16,185,129,0.12); color: var(--green); }
.pill-cancelled { background: rgba(239,68,68,0.12); color: var(--red); }
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
.sa-alert-warning { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #92400e; display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 8px; }
.sa-alert-warning svg { color: var(--amber); flex-shrink: 0; margin-top: 1px; }
.sa-alert-warning strong { display: block; margin-bottom: 4px; }
.sa-alert-warning p { margin: 0; font-size: 0.85rem; }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">

                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><?php echo __('demo_requests'); ?>
                                        </h5>
                                        <p class="page-desc"><?php echo __('manage_demo_requests'); ?></p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span style="background:rgba(255,255,255,0.12);padding:8px 16px;border-radius:8px;display:inline-flex;align-items:center;gap:8px;backdrop-filter:blur(4px);position:relative;z-index:1;font-size:0.85rem;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                            <strong style="font-size:1.1rem;"><?php echo $total_requests; ?></strong> <?php echo __('total_requests'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($_GET['success'])): ?>
                            <div class="sa-alert sa-alert-success" id="successAlert">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                <div class="sa-alert-content">
                                    <?php
                                    switch ($_GET['success']) {
                                        case 'status_updated': echo 'Demo request status updated successfully!'; break;
                                        case 'request_deleted': echo 'Demo request deleted successfully!'; break;
                                        default: echo 'Operation completed successfully!';
                                    }
                                    ?>
                                </div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.remove();"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <?php endif; ?>

                            <?php if (isset($_GET['error'])): ?>
                            <div class="sa-alert sa-alert-danger" id="errorAlert">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <div class="sa-alert-content">
                                    <?php
                                    switch ($_GET['error']) {
                                        case 'invalid_csrf': echo 'Security validation failed. Please try again.'; break;
                                        case 'invalid_status': echo 'Invalid status selected.'; break;
                                        case 'update_failed': echo 'Failed to update request status.'; break;
                                        case 'delete_failed': echo 'Failed to delete request.'; break;
                                        default: echo 'An error occurred. Please try again.';
                                    }
                                    ?>
                                </div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.remove();"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <?php endif; ?>

                            <!-- Status Summary -->
                            <div class="sa-stats">
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-amber"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $status_summary['pending'] ?? 0; ?></span><span class="sa-stat-label">Pending</span></div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-blue"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $status_summary['contacted'] ?? 0; ?></span><span class="sa-stat-label">Contacted</span></div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-purple"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $status_summary['scheduled'] ?? 0; ?></span><span class="sa-stat-label">Scheduled</span></div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-green"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $status_summary['completed'] ?? 0; ?></span><span class="sa-stat-label">Completed</span></div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-red"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
                                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $status_summary['cancelled'] ?? 0; ?></span><span class="sa-stat-label">Cancelled</span></div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-gray"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $total_requests; ?></span><span class="sa-stat-label">Total</span></div>
                                </div>
                            </div>

                            <!-- Toolbar -->
                            <div class="sa-toolbar">
                                <form method="GET" action="manage_demo_requests.php" class="sa-toolbar-form">
                                    <div class="sa-toolbar-group">
                                        <span class="sa-toolbar-label">Search</span>
                                        <div class="sa-search-box">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <input type="text" class="sa-search-input" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, or company">
                                        </div>
                                    </div>
                                    <div class="sa-toolbar-group">
                                        <span class="sa-toolbar-label">Status</span>
                                        <select class="sa-filter-select" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="sa-btn sa-btn-primary" style="align-self:flex-end;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Filter
                                    </button>
                                    <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <a href="manage_demo_requests.php" class="sa-btn sa-btn-ghost" style="align-self:flex-end;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Reset
                                    </a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <!-- Data Table -->
                            <?php if (!empty($demo_requests)): ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th>Name / Company</th>
                                            <th>Contact</th>
                                            <th>Schedule</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th class="sa-th-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($demo_requests as $request):
                                            $initial = strtoupper(substr($request['name'], 0, 1));
                                        ?>
                                        <tr>
                                            <td class="sa-td-tenant">
                                                <div class="sa-avatar" style="background:<?= match($request['status']) {
                                                    'completed' => '#10b981',
                                                    'pending' => '#f59e0b',
                                                    'contacted' => '#3b82f6',
                                                    'scheduled' => '#8b5cf6',
                                                    default => '#6b7280'
                                                } ?>"><?= $initial ?></div>
                                                <div class="sa-tenant-meta">
                                                    <div class="sa-tenant-name"><?= htmlspecialchars($request['name']) ?></div>
                                                    <div class="sa-tenant-id"><?= htmlspecialchars($request['company']) ?><?= $request['company_size'] ? ' (' . htmlspecialchars($request['company_size']) . ')' : '' ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight:500;"><?= htmlspecialchars($request['email']) ?></div>
                                                <?php if ($request['phone']): ?>
                                                <div style="font-size:0.78rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($request['phone']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($request['preferred_date']): ?>
                                                <span style="font-size:0.82rem;"><?= date('M d, Y', strtotime($request['preferred_date'])) ?></span>
                                                <?php if ($request['preferred_time']): ?>
                                                <span style="font-size:0.78rem;color:var(--muted);display:block;"><?= date('H:i', strtotime($request['preferred_time'])) ?></span>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                <span class="sa-na">No preference</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="pill <?= getStatusPillClass($request['status']) ?>"><?= ucfirst(htmlspecialchars($request['status'])) ?></span></td>
                                            <td class="sa-td-date"><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                            <td class="sa-td-actions">
                                                <button type="button" class="sa-icon-btn" onclick="viewRequestDetails(<?= $request['id'] ?>)" title="View">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                <button type="button" class="sa-icon-btn" onclick="updateStatus(<?= $request['id'] ?>, '<?= $request['status'] ?>')" title="Update Status">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <button type="button" class="sa-icon-btn sa-icon-btn-danger" onclick="deleteRequest(<?= $request['id'] ?>)" title="Delete">
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                <div class="sa-empty-title">No demo requests found</div>
                                <div class="sa-empty-desc">Try adjusting your search or filter criteria.</div>
                            </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="sa-pagination">
                                <?php
                                $q = '';
                                if (!empty($search)) $q .= '&search=' . urlencode($search);
                                if (!empty($status_filter)) $q .= '&status=' . urlencode($status_filter);
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                ?>
                                <?php if ($current_page > 1): ?>
                                <a href="?page=1<?php echo $q; ?>" class="sa-page-btn" title="First page"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg></a>
                                <a href="?page=<?php echo $current_page - 1 . $q; ?>" class="sa-page-btn" title="Previous"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
                                <?php endif; ?>
                                <?php if ($start_page > 1): ?><span class="sa-page-ellipsis">...</span><?php endif; ?>
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i . $q; ?>" class="sa-page-btn <?php echo $i === $current_page ? 'sa-page-active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <?php if ($end_page < $total_pages): ?><span class="sa-page-ellipsis">...</span><?php endif; ?>
                                <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1 . $q; ?>" class="sa-page-btn" title="Next"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
                                <a href="?page=<?php echo $total_pages . $q; ?>" class="sa-page-btn" title="Last page"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg></a>
                                <?php endif; ?>
                                <span class="sa-page-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Details Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header" style="background:linear-gradient(135deg,rgba(64,153,255,0.06),rgba(46,216,182,0.06));">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Demo Request Details
                    </h5>
                    <p class="sa-modal-subtitle">View complete request information</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-modal-body" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header" style="background:linear-gradient(135deg,rgba(245,158,11,0.08),rgba(251,191,36,0.08));">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--amber);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Update Request Status
                    </h5>
                    <p class="sa-modal-subtitle">Change the status of this request</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="request_id" id="updateRequestId">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label class="sa-form-label">New Status</label>
                        <select class="sa-form-input" id="statusSelect" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="contacted">Contacted</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Request Modal -->
<div class="modal fade" id="deleteRequestModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sa-modal-content">
            <div class="sa-modal-header" style="background:linear-gradient(135deg,rgba(239,68,68,0.08),rgba(248,113,113,0.08));">
                <div class="sa-modal-title-group">
                    <h5 class="sa-modal-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--red);"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete Demo Request
                    </h5>
                    <p class="sa-modal-subtitle">This action cannot be undone</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="delete_request" value="1">
                <input type="hidden" name="request_id" id="deleteRequestId">
                <div class="sa-modal-body">
                    <div class="sa-alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div>
                            <strong>Warning!</strong>
                            <p>Are you sure you want to delete this demo request? This action cannot be undone.</p>
                        </div>
                    </div>
                    <div id="deleteRequestInfo"></div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary" style="background:var(--red);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function viewRequestDetails(requestId) {
    fetch(`get_demo_request_details.php?id=${requestId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('requestDetailsContent').innerHTML = data;
            $('#viewRequestModal').modal('show');
        })
        .catch(error => {
            console.error('Error loading request details:', error);
            alert('Error loading request details. Please try again.');
        });
}

function updateStatus(requestId, currentStatus) {
    document.getElementById('updateRequestId').value = requestId;
    document.getElementById('statusSelect').value = currentStatus;
    $('#updateStatusModal').modal('show');
}

function deleteRequest(requestId) {
    fetch(`get_demo_request_details.php?id=${requestId}&basic=1`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('deleteRequestId').value = requestId;
            document.getElementById('deleteRequestInfo').innerHTML = data;
            $('#deleteRequestModal').modal('show');
        })
        .catch(error => {
            console.error('Error loading request info:', error);
            document.getElementById('deleteRequestId').value = requestId;
            document.getElementById('deleteRequestInfo').innerHTML = '<p class="text-muted">Unable to load request details.</p>';
            $('#deleteRequestModal').modal('show');
        });
}
</script>

<?php include '../includes/admin_footer.php'; ?>

<?php
function getStatusPillClass($status) {
    $classes = [
        'pending' => 'pill-pending',
        'contacted' => 'pill-contacted',
        'scheduled' => 'pill-scheduled',
        'completed' => 'pill-completed',
        'cancelled' => 'pill-cancelled'
    ];
    return $classes[$status] ?? 'pill-pending';
}
?>
