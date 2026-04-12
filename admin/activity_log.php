<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Load input validation helper
require_once '../includes/InputValidator.php';

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Pagination settings
$records_per_page = 50;
$page = InputValidator::getInt($_GET['page'] ?? '', 1, 1, 9999);
$offset = ($page - 1) * $records_per_page;

// Handle activity log filtering
$date_from = InputValidator::getDate($_GET['date_from'] ?? '', 'Y-m-d', date('Y-m-d', strtotime('-30 days')));
$date_to   = InputValidator::getDate($_GET['date_to'] ?? '', 'Y-m-d', date('Y-m-d'));
$user_id   = InputValidator::getInt($_GET['user_id'] ?? '', 0, 0);
$action    = InputValidator::getString($_GET['action'] ?? '', 50);
$table_name = InputValidator::getString($_GET['table_name'] ?? '', 50);

// Get all users for filter dropdown
$users_stmt = $pdo->prepare("SELECT id, name FROM users WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
$users_stmt->execute([$tenant_id, $branch_id]);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count query
$count_query = "SELECT COUNT(*) as total FROM activity_log a WHERE a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND a.tenant_id = ?";
$count_params = [$date_from, $date_to, $tenant_id];
if ($user_id > 0) { $count_query .= " AND a.user_id = ?"; $count_params[] = $user_id; }
if (!empty($action)) { $count_query .= " AND a.action = ?"; $count_params[] = $action; }
if (!empty($table_name)) { $count_query .= " AND a.table_name = ?"; $count_params[] = $table_name; }

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Main data query
$query = "SELECT a.*, u.name as user_name FROM activity_log a LEFT JOIN users u ON a.user_id = u.id WHERE a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND a.tenant_id = ? AND a.branch_id = ?";
$params = [$date_from, $date_to, $tenant_id, $branch_id];
if ($user_id > 0) { $query .= " AND a.user_id = ?"; $params[] = $user_id; }
if (!empty($action)) { $query .= " AND a.action = ?"; $params[] = $action; }
if (!empty($table_name)) { $query .= " AND a.table_name = ?"; $params[] = $table_name; }
$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dropdowns
$actions_stmt = $pdo->prepare("SELECT DISTINCT action FROM activity_log WHERE tenant_id = ? AND branch_id = ? ORDER BY action");
$actions_stmt->execute([$tenant_id, $branch_id]);
$actions = array_column($actions_stmt->fetchAll(PDO::FETCH_ASSOC), 'action');

$tables_stmt = $pdo->prepare("SELECT DISTINCT table_name FROM activity_log WHERE tenant_id = ? AND branch_id = ? ORDER BY table_name");
$tables_stmt->execute([$tenant_id, $branch_id]);
$tables = array_column($tables_stmt->fetchAll(PDO::FETCH_ASSOC), 'table_name');

// Handle single log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    $log_id = $_POST['log_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_log WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$log_id, $tenant_id, $branch_id]);
        $_SESSION['success_message'] = "Log entry deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting log entry: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Handle bulk log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $delete_before_date = $_POST['delete_before_date'];
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_log WHERE created_at < ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$delete_before_date, $tenant_id, $branch_id]);
        $affected_rows = $stmt->rowCount();
        $_SESSION['success_message'] = "$affected_rows log entries deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting log entries: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Fetch current user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id, $branch_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { session_destroy(); header('Location: ../login.php'); exit(); }
} catch (PDOException $e) {
    $user = null;
}
?>

<?php include '../includes/header.php'; ?>

<style>
/* ── Corporate / Enterprise Design System ── */
:root {
    --color-bg:           #f0f2f5;
    --color-surface:      #ffffff;
    --color-border:       #d9dde3;
    --color-border-light: #eaecef;
    --color-text-primary: #1a1f2e;
    --color-text-secondary: #5a6272;
    --color-text-muted:   #9aa1b0;
    --color-accent:       #2563eb;
    --color-accent-hover: #1d4ed8;
    --color-accent-light: #eff6ff;
    --color-danger:       #dc2626;
    --color-danger-light: #fef2f2;
    --color-success:      #16a34a;
    --color-success-light:#f0fdf4;
    --color-warning:      #d97706;
    --color-warning-light:#fffbeb;
    --color-info:         #0891b2;
    --color-info-light:   #ecfeff;
    --radius-sm:          4px;
    --radius-md:          6px;
    --radius-lg:          8px;
    --shadow-sm:          0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md:          0 4px 6px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);
    --font-mono:          'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
}

/* ── Page Layout ── */
.al-page { background: var(--color-bg); min-height: 100vh; padding: 0; }

/* ── Top Bar ── */
.al-topbar {
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: var(--shadow-sm);
}
.al-topbar-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.al-topbar-icon {
    width: 32px; height: 32px;
    background: var(--color-accent);
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 15px;
    flex-shrink: 0;
}
.al-topbar-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0;
    line-height: 1;
}
.al-topbar-subtitle {
    font-size: 12px;
    color: var(--color-text-muted);
    margin: 0;
    margin-top: 2px;
}
.al-topbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Buttons ── */
.al-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 500;
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    line-height: 1.4;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    text-decoration: none;
    white-space: nowrap;
}
.al-btn-primary {
    background: var(--color-accent);
    color: #fff;
    border-color: var(--color-accent);
}
.al-btn-primary:hover { background: var(--color-accent-hover); border-color: var(--color-accent-hover); color: #fff; }
.al-btn-ghost {
    background: transparent;
    color: var(--color-text-secondary);
    border-color: var(--color-border);
}
.al-btn-ghost:hover { background: var(--color-bg); color: var(--color-text-primary); }
.al-btn-danger {
    background: var(--color-danger);
    color: #fff;
    border-color: var(--color-danger);
}
.al-btn-danger:hover { background: #b91c1c; border-color: #b91c1c; color: #fff; }
.al-btn-danger-ghost {
    background: transparent;
    color: var(--color-danger);
    border-color: #fca5a5;
}
.al-btn-danger-ghost:hover { background: var(--color-danger-light); }
.al-btn-sm {
    padding: 4px 9px;
    font-size: 12px;
    gap: 4px;
}
.al-btn-icon {
    padding: 5px 8px;
    min-width: 0;
}

/* ── Alerts ── */
.al-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 14px;
    border-radius: var(--radius-md);
    font-size: 13px;
    margin: 16px 24px 0;
    border: 1px solid;
}
.al-alert-success { background: var(--color-success-light); color: #14532d; border-color: #bbf7d0; }
.al-alert-danger   { background: var(--color-danger-light);  color: #7f1d1d; border-color: #fecaca; }

/* ── Filter Bar (collapsible) ── */
.al-filter-bar {
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    padding: 0 24px;
    overflow: hidden;
    transition: max-height 0.25s ease, padding 0.25s ease;
    max-height: 0;
}
.al-filter-bar.open {
    max-height: 120px;
    padding: 14px 24px;
}
.al-filter-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}
.al-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 120px;
    max-width: 200px;
}
.al-filter-group label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
    margin: 0;
}
.al-filter-group .form-control {
    height: 32px;
    font-size: 13px;
    padding: 4px 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    background: var(--color-surface);
    box-shadow: none;
    transition: border-color 0.15s;
}
.al-filter-group .form-control:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 2px rgba(37,99,235,0.12);
    outline: none;
}
.al-filter-actions {
    display: flex;
    gap: 6px;
    padding-bottom: 0;
    flex-shrink: 0;
}

/* ── Table Toolbar ── */
.al-table-toolbar {
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border-light);
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.al-table-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.al-table-meta-count {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
}
.al-table-meta-page {
    font-size: 12px;
    color: var(--color-text-muted);
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 20px;
    padding: 2px 10px;
}
.al-search-wrap {
    position: relative;
    width: 220px;
}
.al-search-wrap input {
    width: 100%;
    height: 30px;
    font-size: 12px;
    padding: 4px 10px 4px 30px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg);
    color: var(--color-text-primary);
    transition: border-color 0.15s, background 0.15s;
}
.al-search-wrap input:focus {
    outline: none;
    border-color: var(--color-accent);
    background: var(--color-surface);
    box-shadow: 0 0 0 2px rgba(37,99,235,0.12);
}
.al-search-icon {
    position: absolute;
    left: 9px; top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 12px;
    pointer-events: none;
}

/* ── Table ── */
.al-table-wrap {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin: 16px 24px;
}
.al-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.al-table thead th {
    background: #f8f9fb;
    border-bottom: 1px solid var(--color-border);
    padding: 9px 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    white-space: nowrap;
    text-align: left;
}
.al-table thead th:first-child { padding-left: 16px; }
.al-table thead th:last-child { padding-right: 16px; text-align: right; }
.al-table tbody tr { border-bottom: 1px solid var(--color-border-light); }
.al-table tbody tr:last-child { border-bottom: none; }
.al-table tbody tr:hover { background: #f5f7fa; }
.al-table tbody td {
    padding: 9px 12px;
    color: var(--color-text-primary);
    vertical-align: middle;
}
.al-table tbody td:first-child { padding-left: 16px; }
.al-table tbody td:last-child { padding-right: 16px; text-align: right; }

/* ── Datetime cell ── */
.al-datetime {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--color-text-secondary);
    white-space: nowrap;
}

/* ── User cell ── */
.al-user {
    display: flex;
    align-items: center;
    gap: 7px;
}
.al-user-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--color-accent-light);
    color: var(--color-accent);
    font-size: 10px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
}
.al-user-name {
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--color-text-primary);
    font-weight: 500;
}

/* ── Action Badges ── */
.al-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 4px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    white-space: nowrap;
}
.al-badge-login    { background: var(--color-success-light); color: var(--color-success); }
.al-badge-logout   { background: var(--color-warning-light); color: var(--color-warning); }
.al-badge-create,
.al-badge-insert   { background: var(--color-accent-light);  color: var(--color-accent); }
.al-badge-update   { background: var(--color-info-light);    color: var(--color-info); }
.al-badge-delete   { background: var(--color-danger-light);  color: var(--color-danger); }
.al-badge-default  { background: #f1f3f6;                   color: var(--color-text-secondary); }

/* ── Table / IP mono ── */
.al-mono {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--color-text-secondary);
}
.al-record-id { color: var(--color-text-muted); font-size: 12px; font-family: var(--font-mono); }

/* ── Row actions ── */
.al-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }

/* ── Empty state ── */
.al-empty {
    text-align: center;
    padding: 60px 24px;
}
.al-empty-icon { font-size: 2.5rem; color: var(--color-text-muted); margin-bottom: 12px; }
.al-empty-title { font-size: 15px; font-weight: 600; color: var(--color-text-secondary); margin: 0 0 6px; }
.al-empty-sub { font-size: 13px; color: var(--color-text-muted); margin: 0; }

/* ── Pagination ── */
.al-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 12px 16px;
    border-top: 1px solid var(--color-border-light);
    background: #fafbfc;
}
.al-page-item a, .al-page-item span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    color: var(--color-text-secondary);
    text-decoration: none;
    background: var(--color-surface);
    transition: all 0.15s;
}
.al-page-item a:hover { background: var(--color-bg); border-color: var(--color-accent); color: var(--color-accent); }
.al-page-item.active span { background: var(--color-accent); color: #fff; border-color: var(--color-accent); font-weight: 600; }

/* ── Shared Modals ── */
.al-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.45);
    z-index: 1040;
    backdrop-filter: blur(2px);
}
.al-modal-backdrop.show { display: flex; align-items: center; justify-content: center; }
.al-modal {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    width: 100%;
    max-width: 600px;
    margin: 24px;
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.al-modal-lg { max-width: 800px; }
.al-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid var(--color-border);
    background: #fafbfc;
    flex-shrink: 0;
}
.al-modal-header h5 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--color-text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.al-modal-close {
    background: none;
    border: none;
    color: var(--color-text-muted);
    font-size: 18px;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    line-height: 1;
}
.al-modal-close:hover { background: var(--color-bg); color: var(--color-text-primary); }
.al-modal-body {
    padding: 20px;
    overflow-y: auto;
}
.al-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid var(--color-border);
    background: #fafbfc;
    flex-shrink: 0;
}

/* ── Detail Grid ── */
.al-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    overflow: hidden;
    margin-bottom: 16px;
    font-size: 13px;
}
.al-detail-row {
    display: contents;
}
.al-detail-label {
    background: #f8f9fb;
    border-bottom: 1px solid var(--color-border-light);
    border-right: 1px solid var(--color-border-light);
    padding: 8px 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
}
.al-detail-value {
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border-light);
    padding: 8px 12px;
    color: var(--color-text-primary);
    font-size: 13px;
    word-break: break-word;
}
.al-detail-label:nth-last-child(-n+2), 
.al-detail-value:last-child { border-bottom: none; }

/* ── JSON Diff ── */
.al-diff-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.al-diff-panel h6 {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    margin: 0 0 6px;
}
.al-diff-panel pre {
    background: #0d1117;
    color: #e6edf3;
    border-radius: var(--radius-md);
    padding: 12px;
    font-family: var(--font-mono);
    font-size: 12px;
    margin: 0;
    overflow: auto;
    max-height: 260px;
    border: 1px solid var(--color-border);
}

/* ── Delete confirm ── */
.al-delete-warning {
    display: flex;
    gap: 10px;
    background: var(--color-danger-light);
    border: 1px solid #fecaca;
    border-radius: var(--radius-md);
    padding: 12px 14px;
    font-size: 13px;
    color: #7f1d1d;
    margin-bottom: 4px;
}
.al-delete-warning-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.al-confirm-text { font-size: 14px; color: var(--color-text-primary); margin-bottom: 12px; }

/* ── Filter toggle button state ── */
.al-filter-toggle.active {
    background: var(--color-accent-light);
    color: var(--color-accent);
    border-color: #bfdbfe;
}


</style>
<!-- pcoded wrapper so header.php layout works -->
<div class="pcoded-main-container">
<div class="pcoded-wrapper">
<div class="al-page">

    <!-- ── Top Bar ── -->
    <div class="al-topbar">
        <div class="al-topbar-left">
            <div class="al-topbar-icon"><i class="feather icon-activity"></i></div>
            <div>
                <div class="al-topbar-title"><?= __('activity_log') ?></div>
                <div class="al-topbar-subtitle"><?= __('audit trail and user actions') ?></div>
            </div>
        </div>
        <div class="al-topbar-right">
            <button id="filterToggleBtn" class="al-btn al-btn-ghost al-filter-toggle" onclick="toggleFilters()">
                <i class="feather icon-filter"></i> <?= __('filter_logs') ?>
            </button>
            <?php if (!empty($logs)): ?>
            <button class="al-btn al-btn-danger-ghost" onclick="openBulkDeleteModal()">
                <i class="feather icon-trash-2"></i> <?= __('bulk_delete') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="al-alert al-alert-success"><i class="feather icon-check-circle"></i> <?= h($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="al-alert al-alert-danger"><i class="feather icon-alert-circle"></i> <?= h($error_message) ?></div>
    <?php endif; ?>

    <!-- ── Collapsible Filter Bar ── -->
    <div class="al-filter-bar" id="filterBar">
        <form method="GET" action="activity_log.php">
            <div class="al-filter-row">
                <div class="al-filter-group">
                    <label><?= __('date_from') ?></label>
                    <input type="date" class="form-control" name="date_from" value="<?= h($date_from) ?>">
                </div>
                <div class="al-filter-group">
                    <label><?= __('date_to') ?></label>
                    <input type="date" class="form-control" name="date_to" value="<?= h($date_to) ?>">
                </div>
                <div class="al-filter-group">
                    <label><?= __('user') ?></label>
                    <select class="form-control" name="user_id">
                        <option value="0"><?= __('all_users') ?></option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= h($u['id']) ?>" <?= $user_id == $u['id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="al-filter-group">
                    <label><?= __('action') ?></label>
                    <select class="form-control" name="action">
                        <option value=""><?= __('all_actions') ?></option>
                        <?php foreach ($actions as $act): ?>
                            <option value="<?= h($act) ?>" <?= $action == $act ? 'selected' : '' ?>><?= h(ucfirst($act)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="al-filter-group">
                    <label><?= __('table') ?></label>
                    <select class="form-control" name="table_name">
                        <option value=""><?= __('all_tables') ?></option>
                        <?php foreach ($tables as $tbl): ?>
                            <option value="<?= h($tbl) ?>" <?= $table_name == $tbl ? 'selected' : '' ?>><?= h($tbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="al-filter-actions">
                    <button type="submit" class="al-btn al-btn-primary"><i class="feather icon-search"></i> <?= __('apply_filters') ?></button>
                    <a href="activity_log.php" class="al-btn al-btn-ghost"><i class="feather icon-x"></i> <?= __('reset') ?></a>
                </div>
            </div>
        </form>
    </div>

    <!-- ── Table Card ── -->
    <div class="al-table-wrap">
        <!-- Toolbar -->
        <div class="al-table-toolbar">
            <div class="al-table-meta">
                <span class="al-table-meta-count"><?= number_format($total_records) ?> <?= __('entries') ?></span>
                <?php if ($total_pages > 1): ?>
                    <span class="al-table-meta-page"><?= __('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                <?php endif; ?>
            </div>
            <div class="al-search-wrap">
                <i class="feather icon-search al-search-icon"></i>
                <input type="text" id="logSearch" placeholder="<?= __('search_logs') ?>…">
            </div>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <table class="al-table" id="logsTable">
                <thead>
                    <tr>
                        <th><?= __('date_time') ?></th>
                        <th><?= __('user') ?></th>
                        <th><?= __('action') ?></th>
                        <th><?= __('table') ?></th>
                        <th><?= __('record_id') ?></th>
                        <th><?= __('ip_address') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $badge = match($log['action']) {
                            'login'  => 'login',
                            'logout' => 'logout',
                            'create' => 'create',
                            'insert' => 'insert',
                            'update' => 'update',
                            'delete' => 'delete',
                            default  => 'default'
                        };
                        $initials = strtoupper(substr($log['user_name'] ?? '?', 0, 2));
                    ?>
                    <tr class="log-row"
                        data-id="<?= h($log['id']) ?>"
                        data-datetime="<?= h(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?>"
                        data-user="<?= h($log['user_name']) ?>"
                        data-action="<?= h($log['action']) ?>"
                        data-action-badge="<?= $badge ?>"
                        data-table="<?= h($log['table_name']) ?>"
                        data-record="<?= h($log['record_id']) ?>"
                        data-ip="<?= h($log['ip_address']) ?>"
                        data-ua="<?= h($log['user_agent']) ?>"
                        data-old='<?= h($log['old_values'] ?? '') ?>'
                        data-new='<?= h($log['new_values'] ?? '') ?>'>
                        <td><span class="al-datetime"><?= h(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?></span></td>
                        <td>
                            <div class="al-user">
                                <div class="al-user-avatar"><?= h($initials) ?></div>
                                <span class="al-user-name" title="<?= h($log['user_name']) ?>"><?= h($log['user_name']) ?></span>
                            </div>
                        </td>
                        <td><span class="al-badge al-badge-<?= $badge ?>"><?= h(ucfirst($log['action'])) ?></span></td>
                        <td><span class="al-mono"><?= h($log['table_name']) ?></span></td>
                        <td><span class="al-record-id"><?= h($log['record_id']) ?></span></td>
                        <td><span class="al-mono"><?= h($log['ip_address']) ?></span></td>
                        <td>
                            <div class="al-row-actions">
                                <button class="al-btn al-btn-ghost al-btn-sm al-btn-icon" title="<?= __('view') ?>" onclick="openViewModal(this.closest('tr'))">
                                    <i class="feather icon-eye"></i>
                                </button>
                                <button class="al-btn al-btn-danger-ghost al-btn-sm al-btn-icon" title="<?= __('delete') ?>" onclick="openDeleteModal(this.closest('tr'))">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="al-empty">
                                <div class="al-empty-icon"><i class="feather icon-inbox"></i></div>
                                <p class="al-empty-title"><?= __('no_log_entries_found') ?></p>
                                <p class="al-empty-sub"><?= __('try_adjusting_your_filters_or_check_back_later') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1):
            $base_params = $_GET; unset($base_params['page']);
            $qs = !empty($base_params) ? '&' . http_build_query($base_params) : '';
        ?>
        <div class="al-pagination">
            <?php if ($page > 1): ?>
                <span class="al-page-item"><a href="?page=1<?= $qs ?>">&laquo;&laquo;</a></span>
                <span class="al-page-item"><a href="?page=<?= $page-1 ?><?= $qs ?>">&laquo;</a></span>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
                <span class="al-page-item <?= $i==$page?'active':'' ?>">
                    <?= $i==$page ? "<span>$i</span>" : "<a href='?page=$i$qs'>$i</a>" ?>
                </span>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <span class="al-page-item"><a href="?page=<?= $page+1 ?><?= $qs ?>">&raquo;</a></span>
                <span class="al-page-item"><a href="?page=<?= $total_pages ?><?= $qs ?>">&raquo;&raquo;</a></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.al-page -->
</div></div>

<!-- ══════════════════════════════════════════
     SHARED MODAL: View Log Detail
══════════════════════════════════════════ -->
<div class="al-modal-backdrop" id="viewModalBackdrop" onclick="closeViewModal(event)">
    <div class="al-modal al-modal-lg" onclick="event.stopPropagation()">
        <div class="al-modal-header">
            <h5><i class="feather icon-activity"></i> <?= __('log_details') ?></h5>
            <button class="al-modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="al-modal-body">
            <div class="al-detail-grid" id="viewDetailGrid"></div>
            <div class="al-diff-grid" id="viewDiffGrid"></div>
        </div>
        <div class="al-modal-footer">
            <button class="al-btn al-btn-ghost" onclick="closeViewModal()"><?= __('close') ?></button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     SHARED MODAL: Delete Log Entry
══════════════════════════════════════════ -->
<div class="al-modal-backdrop" id="deleteModalBackdrop" onclick="closeDeleteModal(event)">
    <div class="al-modal" onclick="event.stopPropagation()">
        <div class="al-modal-header">
            <h5><i class="feather icon-trash-2"></i> <?= __('confirm_deletion') ?></h5>
            <button class="al-modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="log_id" id="deleteLogId">
            <div class="al-modal-body">
                <p class="al-confirm-text"><?= __('are_you_sure_you_want_to_delete_this_log_entry') ?></p>
                <div class="al-delete-warning">
                    <span class="al-delete-warning-icon"><i class="feather icon-alert-triangle"></i></span>
                    <span><?= __('this_action_cannot_be_undone') ?></span>
                </div>
            </div>
            <div class="al-modal-footer">
                <button type="button" class="al-btn al-btn-ghost" onclick="closeDeleteModal()"><?= __('cancel') ?></button>
                <button type="submit" name="delete_log" class="al-btn al-btn-danger"><?= __('delete') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     SHARED MODAL: Bulk Delete
══════════════════════════════════════════ -->
<div class="al-modal-backdrop" id="bulkDeleteBackdrop" onclick="closeBulkDeleteModal(event)">
    <div class="al-modal" onclick="event.stopPropagation()">
        <div class="al-modal-header">
            <h5><i class="feather icon-trash-2"></i> <?= __('bulk_delete_log_entries') ?></h5>
            <button class="al-modal-close" onclick="closeBulkDeleteModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="al-modal-body">
                <div class="al-delete-warning" style="margin-bottom:16px;">
                    <span class="al-delete-warning-icon"><i class="feather icon-alert-triangle"></i></span>
                    <span><?= __('warning_this_action_will_permanently_delete_all_log_entries_before_the_selected_date') ?></span>
                </div>
                <div class="al-filter-group" style="max-width:100%;">
                    <label><?= __('delete_logs_before_date') ?> *</label>
                    <input type="date" class="form-control" name="delete_before_date" required style="height:36px;font-size:13px;">
                </div>
            </div>
            <div class="al-modal-footer">
                <button type="button" class="al-btn al-btn-ghost" onclick="closeBulkDeleteModal()"><?= __('cancel') ?></button>
                <button type="submit" name="bulk_delete" class="al-btn al-btn-danger"><?= __('delete_logs') ?></button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// ── Filter toggle ──────────────────────────────────────
function toggleFilters() {
    const bar = document.getElementById('filterBar');
    const btn = document.getElementById('filterToggleBtn');
    bar.classList.toggle('open');
    btn.classList.toggle('active');
}

// Auto-open filters if any are active
(function() {
    const params = new URLSearchParams(window.location.search);
    const filterKeys = ['user_id','action','table_name'];
    // Also check if dates differ from defaults
    const hasFilter = filterKeys.some(k => params.has(k) && params.get(k) !== '' && params.get(k) !== '0');
    if (hasFilter) toggleFilters();
})();

// ── Live search ────────────────────────────────────────
document.getElementById('logSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.log-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ── View Modal ─────────────────────────────────────────
function openViewModal(row) {
    const d = row.dataset;

    // Build detail grid
    const fields = [
        ['<?= __('date_time') ?>',  d.datetime],
        ['<?= __('user') ?>',       d.user],
        ['<?= __('action') ?>',     `<span class="al-badge al-badge-${d.actionBadge}">${capitalise(d.action)}</span>`],
        ['<?= __('table') ?>',      `<span class="al-mono">${esc(d.table)}</span>`],
        ['<?= __('record_id') ?>', `<span class="al-record-id">${esc(d.record)}</span>`],
        ['<?= __('ip_address') ?>', `<span class="al-mono">${esc(d.ip)}</span>`],
        ['<?= __('user_agent') ?>',  esc(d.ua)],
    ];
    const grid = document.getElementById('viewDetailGrid');
    grid.innerHTML = fields.map(([label, val]) =>
        `<div class="al-detail-label">${label}</div><div class="al-detail-value">${val}</div>`
    ).join('');

    // Build diff panels
    const oldVal = tryParseJSON(d.old);
    const newVal = tryParseJSON(d.new);
    const diffGrid = document.getElementById('viewDiffGrid');
    diffGrid.innerHTML = `
        <div class="al-diff-panel">
            <h6><?= __('old_values') ?></h6>
            <pre>${oldVal ? esc(JSON.stringify(oldVal, null, 2)) : '<em style="color:#8b949e"><?= __('no_data') ?></em>'}</pre>
        </div>
        <div class="al-diff-panel">
            <h6><?= __('new_values') ?></h6>
            <pre>${newVal ? esc(JSON.stringify(newVal, null, 2)) : '<em style="color:#8b949e"><?= __('no_data') ?></em>'}</pre>
        </div>
    `;

    document.getElementById('viewModalBackdrop').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeViewModal(e) {
    if (e && e.target !== document.getElementById('viewModalBackdrop')) return;
    document.getElementById('viewModalBackdrop').classList.remove('show');
    document.body.style.overflow = '';
}

// ── Delete Modal ───────────────────────────────────────
function openDeleteModal(row) {
    document.getElementById('deleteLogId').value = row.dataset.id;
    document.getElementById('deleteModalBackdrop').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal(e) {
    if (e && e.target !== document.getElementById('deleteModalBackdrop')) return;
    document.getElementById('deleteModalBackdrop').classList.remove('show');
    document.body.style.overflow = '';
}

// ── Bulk Delete Modal ──────────────────────────────────
function openBulkDeleteModal() {
    document.getElementById('bulkDeleteBackdrop').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeBulkDeleteModal(e) {
    if (e && e.target !== document.getElementById('bulkDeleteBackdrop')) return;
    document.getElementById('bulkDeleteBackdrop').classList.remove('show');
    document.body.style.overflow = '';
}

// ── Keyboard: Escape closes any open modal ─────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeViewModal();
    closeDeleteModal();
    closeBulkDeleteModal();
    document.body.style.overflow = '';
});

// ── Helpers ────────────────────────────────────────────
function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function capitalise(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function tryParseJSON(str) {
    if (!str) return null;
    try { return JSON.parse(str); } catch { return null; }
}
</script>
</body>
</html>