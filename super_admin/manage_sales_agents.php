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

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$province = $_GET['province'] ?? '';
$status = $_GET['status'] ?? '';

// Build base query for counting total items
$count_query = "SELECT COUNT(*) as total FROM sales_agents WHERE 1=1";
$filter_params = [];
$filter_types = '';

if ($province) {
    $count_query .= " AND province = ?";
    $filter_params[] = $province;
    $filter_types .= 's';
}
if ($status) {
    $count_query .= " AND status = ?";
    $filter_params[] = $status;
    $filter_types .= 's';
}
if (!empty($search_query)) {
    $count_query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_types .= 'sss';
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Build main query with pagination
$query = "SELECT sa.id, sa.name, sa.email, sa.phone, sa.province, sa.region, 
                 sa.commission_rate, sa.salary_type, sa.status, sa.created_at,
                 u.email as user_email
          FROM sales_agents sa
          LEFT JOIN users u ON sa.user_id = u.id
          WHERE 1=1";

if ($province) {
    $query .= " AND sa.province = ?";
}
if ($status) {
    $query .= " AND sa.status = ?";
}
if (!empty($search_query)) {
    $query .= " AND (sa.name LIKE ? OR sa.email LIKE ? OR sa.phone LIKE ?)";
}
$query .= " ORDER BY sa.created_at DESC LIMIT ? OFFSET ?";
$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$sales_agents = $stmt->fetchAll();

// Fetch distinct provinces for filter
$stmt = $pdo->prepare("SELECT DISTINCT province FROM sales_agents ORDER BY province ASC");
$stmt->execute();
$provinces = $stmt->fetchAll();

// Get status counts for stats
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM sales_agents GROUP BY status");
$stmt->execute();
$agent_status_counts = [];
foreach ($stmt->fetchAll() as $row) {
    $agent_status_counts[$row['status']] = (int)$row['count'];
}
$agent_active = $agent_status_counts['active'] ?? 0;
$agent_inactive = $agent_status_counts['inactive'] ?? 0;
$agent_suspended = $agent_status_counts['suspended'] ?? 0;

// Helper: generate initials from name
function get_initials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if (!empty($p)) $initials .= strtoupper($p[0]);
    }
    return substr($initials, 0, 2);
}

include '../includes/header_super_admin.php'; ?>

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
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
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
.sa-stat-amber { background: rgba(245,158,11,0.1); color: var(--amber); }
.sa-stat-red { background: rgba(239,68,68,0.1); color: var(--red); }
.sa-stat-body { display: flex; flex-direction: column; gap: 2px; }
.sa-stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text); line-height: 1.2; }
.sa-stat-label { font-size: 0.78rem; color: var(--muted); font-weight: 500; }
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
    font-size: 0.75rem; font-weight: 700; color: #fff; flex-shrink: 0;
}
.sa-tenant-meta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sa-tenant-name { font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sa-tenant-id { font-size: 0.72rem; color: var(--muted); }
.sa-td-email { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); }
.sa-td-date { white-space: nowrap; color: var(--muted); font-size: 0.8rem; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-plan-badge {
    display: inline-block; padding: 3px 10px; border-radius: 6px;
    background: rgba(64,153,255,0.1); color: var(--primary);
    font-size: 0.78rem; font-weight: 600;
}
.sa-pct { font-weight: 700; }
.sa-location { font-size: 0.82rem; color: var(--text); }
.sa-region { color: var(--muted); font-size: 0.78rem; }
.sa-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    border: 1px solid var(--border); background: var(--surface);
    color: var(--muted); cursor: pointer; transition: all 0.2s;
    text-decoration: none; margin-left: 4px;
}
.sa-icon-btn:hover { border-color: var(--primary); color: var(--primary); background: rgba(64,153,255,0.06); }
.sa-icon-btn-danger:hover { border-color: var(--red); color: var(--red); background: rgba(239,68,68,0.06); }
.pill {
    font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 4px;
}
.pill svg { width: 12px; height: 12px; }
.pill-green { background: rgba(34,211,160,0.12); color: var(--green); }
.pill-red { background: rgba(244,63,94,0.12); color: var(--red); }
.pill-gray { background: rgba(107,114,128,0.12); color: var(--muted); }
.pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-blue { background: rgba(56,189,248,0.12); color: var(--blue); }
.sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
.sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
.sa-empty-desc { font-size: 0.85rem; color: var(--muted); }
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
                                        <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Sales Agents</h5>
                                        <p class="page-desc">Manage and monitor your sales agents</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#createSalesAgentModal">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Create Agent
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($_GET['error'])): ?>
                            <div class="sa-alert sa-alert-danger" id="errorAlert">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <div class="sa-alert-content"><strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?></div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.remove();"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <script>document.getElementById('errorAlert')?.scrollIntoView({ behavior: 'smooth', block: 'start' });</script>
                            <?php endif; ?>

                            <?php if (!empty($_GET['success'])): ?>
                            <div class="sa-alert sa-alert-success" id="successAlert">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                <div class="sa-alert-content"><strong>Success:</strong> <?= htmlspecialchars($_GET['success']) ?></div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.remove();"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <script>
                                document.getElementById('successAlert')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                setTimeout(() => { const a = document.getElementById('successAlert'); if(a) { a.style.transition = 'opacity 0.3s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 300); } }, 5000);
                            </script>
                            <?php endif; ?>

                            <!-- Stats Cards -->
                            <div class="sa-stats">
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $total_items ?></span>
                                        <span class="sa-stat-label">Total Agents</span>
                                    </div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-green"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $agent_active ?></span>
                                        <span class="sa-stat-label">Active</span>
                                    </div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-amber"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $agent_inactive ?></span>
                                        <span class="sa-stat-label">Inactive</span>
                                    </div>
                                </div>
                                <div class="sa-stat-card">
                                    <div class="sa-stat-icon sa-stat-red"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
                                    <div class="sa-stat-body">
                                        <span class="sa-stat-value"><?= $agent_suspended ?></span>
                                        <span class="sa-stat-label">Suspended</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Toolbar -->
                            <div class="sa-toolbar">
                                <form method="GET" action="manage_sales_agents.php" class="sa-toolbar-form">
                                    <div class="sa-toolbar-left">
                                        <div class="sa-search-box">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <input type="text" class="sa-search-input" name="search" placeholder="Search name, email or phone..." value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <select class="sa-filter-select" name="province">
                                            <option value="">All Provinces</option>
                                            <?php foreach ($provinces as $p): ?>
                                            <option value="<?= $p['province'] ?>" <?= $province == $p['province'] ? 'selected' : '' ?>><?= htmlspecialchars($p['province']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="sa-filter-select" name="status">
                                            <option value="">All Status</option>
                                            <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            <option value="suspended" <?= $status == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                        </select>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">Search</button>
                                        <?php if (!empty($search_query) || !empty($province) || !empty($status)): ?>
                                        <a href="manage_sales_agents.php" class="sa-btn sa-btn-ghost sa-btn-sm">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Data Table -->
                            <?php if (empty($sales_agents)): ?>
                            <div class="sa-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <div class="sa-empty-title">No Sales Agents Found</div>
                                <div class="sa-empty-desc"><?= !empty($search_query) || !empty($province) || !empty($status) ? 'Try adjusting your search filters.' : 'No sales agents available yet.' ?></div>
                            </div>
                            <?php else: ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th>Phone</th>
                                            <th>Location</th>
                                            <th>Commission</th>
                                            <th>Salary Type</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th class="sa-th-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales_agents as $agent):
                                            $initials = get_initials($agent['name']);
                                            $status_class = match($agent['status']) {
                                                'active' => 'pill-green',
                                                'inactive' => 'pill-gray',
                                                default => 'pill-red'
                                            };
                                            $avatar_color = match($agent['status']) {
                                                'active' => '#10b981',
                                                'inactive' => '#f59e0b',
                                                default => '#ef4444'
                                            };
                                        ?>
                                        <tr class="sa-row">
                                            <td class="sa-td-tenant">
                                                <div class="sa-avatar" style="background:<?= $avatar_color ?>"><?= $initials ?></div>
                                                <div class="sa-tenant-meta">
                                                    <div class="sa-tenant-name"><?= htmlspecialchars($agent['name']) ?></div>
                                                    <div class="sa-tenant-id"><?= htmlspecialchars($agent['email']) ?></div>
                                                </div>
                                            </td>
                                            <td class="sa-td-email"><?= htmlspecialchars($agent['phone'] ?? '—') ?></td>
                                            <td>
                                                <span class="sa-location">
                                                    <?= htmlspecialchars($agent['province']) ?>
                                                    <?= !empty($agent['region']) ? '<span class="sa-region">• ' . htmlspecialchars($agent['region']) . '</span>' : '' ?>
                                                </span>
                                            </td>
                                            <td><span class="sa-plan-badge sa-pct"><?= $agent['commission_rate'] ?>%</span></td>
                                            <td><?= htmlspecialchars(ucfirst($agent['salary_type'])) ?></td>
                                            <td><span class="pill <?= $status_class ?>"><?= htmlspecialchars(ucfirst($agent['status'])) ?></span></td>
                                            <td class="sa-td-date"><?= date('M d, Y', strtotime($agent['created_at'])) ?></td>
                                            <td class="sa-td-actions">
                                                <a href="edit_sales_agent.php?id=<?= $agent['id'] ?>" class="sa-icon-btn" title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </a>
                                                <a href="view_sales_agent.php?id=<?= $agent['id'] ?>" class="sa-icon-btn" title="View">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                                <button class="sa-icon-btn sa-icon-btn-danger delete-agent" data-id="<?= $agent['id'] ?>" title="Delete">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="sa-pagination">
                                <?php 
                                $query_string = '';
                                if (!empty($search_query)) $query_string .= '&search=' . urlencode($search_query);
                                if (!empty($province)) $query_string .= '&province=' . urlencode($province);
                                if (!empty($status)) $query_string .= '&status=' . urlencode($status);
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

                        <!-- Create Sales Agent Modal -->
                        <div class="modal fade" id="createSalesAgentModal" tabindex="-1" role="dialog" aria-labelledby="createSalesAgentModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content sa-modal-content">
                                    <div class="sa-modal-header">
                                        <div class="sa-modal-title-group">
                                            <h5 class="sa-modal-title" id="createSalesAgentModalLabel">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Create New Sales Agent
                                            </h5>
                                            <p class="sa-modal-subtitle">Register a new sales agent with account credentials</p>
                                        </div>
                                        <button type="button" class="sa-modal-close" data-dismiss="modal" aria-label="Close">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                    <div class="sa-modal-body">
                                        <form id="createSalesAgentForm" method="POST" action="create_sales_agent.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            
                                            <div class="sa-form-section">
                                                <h6 class="sa-form-section-title">Personal Information</h6>
                                                <div class="sa-form-grid-2">
                                                    <div class="sa-form-group">
                                                        <label for="name" class="sa-form-label">Name <span class="sa-required">*</span></label>
                                                        <input type="text" class="sa-form-input" id="name" name="name" required placeholder="Enter full name">
                                                    </div>
                                                    <div class="sa-form-group">
                                                        <label for="email" class="sa-form-label">Email <span class="sa-required">*</span></label>
                                                        <input type="email" class="sa-form-input" id="email" name="email" required placeholder="Enter email address">
                                                    </div>
                                                    <div class="sa-form-group">
                                                        <label for="phone" class="sa-form-label">Phone</label>
                                                        <input type="tel" class="sa-form-input" id="phone" name="phone" placeholder="Enter phone number">
                                                    </div>
                                                    <div class="sa-form-group">
                                                        <label for="password" class="sa-form-label">Password <span class="sa-required">*</span></label>
                                                        <input type="password" class="sa-form-input" id="password" name="password" required placeholder="Enter password">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="sa-form-section">
                                                <h6 class="sa-form-section-title">Location & Commission</h6>
                                                <div class="sa-form-grid-2">
                                                    <div class="sa-form-group">
                                                        <label for="province" class="sa-form-label">Province <span class="sa-required">*</span></label>
                                                        <input type="text" class="sa-form-input" id="province" name="province" required placeholder="Enter province">
                                                    </div>
                                                    <div class="sa-form-group">
                                                        <label for="region" class="sa-form-label">Region</label>
                                                        <input type="text" class="sa-form-input" id="region" name="region" placeholder="Enter region">
                                                    </div>
                                                    <div class="sa-form-group">
                                                        <label for="commission_rate" class="sa-form-label">Commission Rate (%) <span class="sa-required">*</span></label>
                                                        <input type="number" class="sa-form-input" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100" value="10.00" required>
                                                    </div>
                                                    <div class="sa-form-group">
                                                        <label for="salary_type" class="sa-form-label">Salary Type <span class="sa-required">*</span></label>
                                                        <select class="sa-form-input sa-form-select" id="salary_type" name="salary_type" required>
                                                            <option value="commission">Commission Only</option>
                                                            <option value="salary">Salary Only</option>
                                                            <option value="hybrid">Salary + Commission</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="sa-form-group" id="baseSalaryGroup" style="display: none;">
                                                    <label for="base_salary" class="sa-form-label">Base Salary</label>
                                                    <input type="number" class="sa-form-input" id="base_salary" name="base_salary" step="0.01" min="0" placeholder="Enter base salary">
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="sa-modal-footer">
                                        <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                                        <button type="submit" form="createSalesAgentForm" class="sa-btn sa-btn-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Agent
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
document.getElementById('salary_type')?.addEventListener('change', function() {
    const baseSalaryGroup = document.getElementById('baseSalaryGroup');
    if (this.value === 'salary' || this.value === 'hybrid') {
        baseSalaryGroup.style.display = 'block';
        document.getElementById('base_salary').required = true;
    } else {
        baseSalaryGroup.style.display = 'none';
        document.getElementById('base_salary').required = false;
    }
});

document.querySelectorAll('.delete-agent').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Are you sure you want to delete this sales agent?')) {
            const agentId = this.getAttribute('data-id');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_sales_agent.php';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="agent_id" value="${agentId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});
</script>


</body>
</html>
