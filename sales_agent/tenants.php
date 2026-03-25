<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a sales agent
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'sales_agent') {
    header('Location: ../login.php');
    exit();
}

// Get sales agent info
$stmt = $pdo->prepare("SELECT id FROM sales_agents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: ../login.php');
    exit();
}

$agent_id = $agent['id'];

// Pagination
$items_per_page = 10;
$current_page   = intval($_GET['page'] ?? 1);
$search_query   = $_GET['search'] ?? '';
$status_filter  = $_GET['status'] ?? '';

// Count total tenants
$count_query  = "SELECT COUNT(*) as total FROM sales_agent_tenants sat
                 JOIN tenants t ON sat.tenant_id = t.id
                 WHERE sat.sales_agent_id = ?";
$filter_params = [$agent_id];

if (!empty($search_query)) {
    $escaped_search = str_replace(['%', '_'], ['\%', '\_'], $search_query);
    $count_query .= " AND t.name LIKE ? ESCAPE '\\'";
    $filter_params[] = "%{$escaped_search}%";
}
if (!empty($status_filter)) {
    $count_query .= " AND sat.status = ?";
    $filter_params[] = $status_filter;
}

$stmt        = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, max(1, $total_pages)));
$offset      = ($current_page - 1) * $items_per_page;

// Fetch tenants
$query        = "SELECT sat.id, t.id as tenant_id, t.name, t.plan, t.status as tenant_status,
                        sat.status, sat.subscription_start_date, sat.subscription_end_date, sat.commission_earned
                 FROM sales_agent_tenants sat
                 JOIN tenants t ON sat.tenant_id = t.id
                 WHERE sat.sales_agent_id = ?";
$query_params = [$agent_id];

if (!empty($search_query)) {
    $escaped_search = str_replace(['%', '_'], ['\%', '\_'], $search_query);
    $query .= " AND t.name LIKE ? ESCAPE '\\'";
    $query_params[] = "%{$escaped_search}%";
}
if (!empty($status_filter)) {
    $query .= " AND sat.status = ?";
    $query_params[] = $status_filter;
}

$query .= " ORDER BY sat.created_at DESC LIMIT ? OFFSET ?";
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt    = $pdo->prepare($query);
$stmt->execute($query_params);
$tenants = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_agent_tenants WHERE sales_agent_id = ? AND status = 'active'");
$stmt->execute([$agent_id]);
$active_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_agent_tenants WHERE sales_agent_id = ?");
$stmt->execute([$agent_id]);
$total_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_earned), 0) as total FROM sales_agent_tenants WHERE sales_agent_id = ?");
$stmt->execute([$agent_id]);
$total_commission = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COALESCE(AVG(commission_earned), 0) as avg FROM sales_agent_tenants WHERE sales_agent_id = ? AND status = 'active'");
$stmt->execute([$agent_id]);
$avg_commission = $stmt->fetch()['avg'];

// Build pagination URL helper
function paginateUrl(int $page, string $search, string $status): string {
    $params = ['page' => $page];
    if ($search !== '') $params['search'] = $search;
    if ($status !== '') $params['status'] = $status;
    return 'tenants.php?' . http_build_query($params);
}
?>
<?php include 'includes/header_sales_agent.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sora:wght@400;600;700&display=swap" rel="stylesheet">

<style>
/* ─────────────────────────────────────────
   CSS VARIABLES  (matches dashboard.php)
───────────────────────────────────────── */
:root {
  --teal:       #2ed8b6;
  --blue:       #4099ff;
  --grad:       linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  --grad-rev:   linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
  --grad-soft:  linear-gradient(135deg, rgba(64,153,255,0.07) 0%, rgba(46,216,182,0.07) 100%);

  --bg:         #F0F4F8;
  --bg-2:       #E8EDF3;
  --surface:    #FFFFFF;
  --surface-2:  #F7F9FC;

  --teal-dim:   rgba(46,216,182,0.12);
  --blue-dim:   rgba(64,153,255,0.12);
  --amber-dim:  rgba(245,158,11,0.10);
  --amber:      #F59E0B;
  --green:      #10B981;
  --green-dim:  rgba(16,185,129,0.10);

  --border:     rgba(0,0,0,0.07);
  --border-2:   rgba(0,0,0,0.04);

  --text-1:     #0F172A;
  --text-2:     #475569;
  --text-3:     #94A3B8;

  --radius:     14px;
  --radius-sm:  9px;
  --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow:     0 4px 16px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.04);
  --shadow-md:  0 8px 30px rgba(64,153,255,0.10), 0 2px 8px rgba(0,0,0,0.05);
}

/* ─────────────────────────────────────────
   BASE
───────────────────────────────────────── */
.sa-page {
  background: var(--bg);
  padding: 28px 24px 48px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--text-1);
  min-height: 100vh;
}

/* ─────────────────────────────────────────
   UTILITIES
───────────────────────────────────────── */
.grad-text {
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.sec-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--text-3);
  margin-bottom: 14px;
}

/* ─────────────────────────────────────────
   PAGE HEADER BAR
───────────────────────────────────────── */
.page-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  gap: 16px;
  flex-wrap: wrap;
}
.page-topbar-left {}
.page-eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 5px;
}
.page-title {
  font-family: 'Sora', sans-serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--text-1);
  line-height: 1.2;
}
.page-sub {
  font-size: 12px;
  color: var(--text-3);
  margin-top: 3px;
}
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--grad);
  color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 700;
  border: none;
  border-radius: var(--radius-sm);
  padding: 11px 20px;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(64,153,255,0.25);
  transition: opacity 0.15s, transform 0.15s;
  cursor: pointer;
}
.btn-primary:hover { opacity: 0.88; transform: translateY(-1px); color: #fff; text-decoration: none; }
.btn-primary svg { width: 13px; height: 13px; }

/* ─────────────────────────────────────────
   METRIC CARDS
───────────────────────────────────────── */
.metric-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 28px;
}
@media (max-width: 992px) { .metric-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 576px)  { .metric-grid { grid-template-columns: 1fr; } }

.mcard {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px 18px 16px;
  box-shadow: var(--shadow-sm);
  position: relative;
  overflow: hidden;
  transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
}
.mcard:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(64,153,255,0.20); }
.mcard::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--grad);
  opacity: 0;
  transition: opacity 0.2s;
}
.mcard:hover::before { opacity: 1; }

.mcard-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
}
.mcard-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
}
.mcard-icon svg { width: 17px; height: 17px; }
.mcard-value {
  font-family: 'Sora', sans-serif;
  font-size: 24px; font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.03em;
  line-height: 1;
  margin-bottom: 5px;
}
.mcard-label { font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 2px; }
.mcard-sub   { font-size: 10px; color: var(--text-3); }

/* ─────────────────────────────────────────
   MAIN PANEL
───────────────────────────────────────── */
.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 13px;
  border-bottom: 1px solid var(--border-2);
  background: var(--surface-2);
  flex-wrap: wrap;
  gap: 10px;
}
.panel-title { font-size: 13px; font-weight: 700; color: var(--text-1); }
.panel-count {
  font-size: 11px; font-weight: 600;
  color: var(--text-3);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 3px 10px;
}

/* ─────────────────────────────────────────
   SEARCH & FILTER BAR
───────────────────────────────────────── */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border-2);
  background: var(--surface-2);
  flex-wrap: wrap;
}
.filter-input {
  flex: 1;
  min-width: 180px;
  height: 36px;
  padding: 0 12px 0 36px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 500;
  color: var(--text-1);
  background: var(--surface);
  transition: border-color 0.15s, box-shadow 0.15s;
  outline: none;
}
.filter-input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(64,153,255,0.10);
}
.filter-input::placeholder { color: var(--text-3); }
.filter-input-wrap {
  flex: 1; min-width: 180px;
  position: relative;
}
.filter-input-wrap svg {
  position: absolute;
  left: 10px; top: 50%;
  transform: translateY(-50%);
  width: 15px; height: 15px;
  color: var(--text-3);
  pointer-events: none;
}
.filter-select {
  height: 36px;
  padding: 0 32px 0 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-1);
  background: var(--surface);
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 14px;
  cursor: pointer;
  outline: none;
  transition: border-color 0.15s;
}
.filter-select:focus { border-color: var(--blue); }
.btn-search {
  height: 36px;
  padding: 0 18px;
  background: var(--grad);
  color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 700;
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 0.15s;
}
.btn-search:hover { opacity: 0.88; }
.btn-reset {
  height: 36px;
  padding: 0 14px;
  background: var(--surface);
  color: var(--text-2);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: background 0.15s, border-color 0.15s;
}
.btn-reset:hover { background: var(--bg); border-color: rgba(0,0,0,0.12); color: var(--text-1); text-decoration: none; }

/* ─────────────────────────────────────────
   TENANT TABLE ROWS
───────────────────────────────────────── */
.tenant-list { padding: 4px 0; }

.tenant-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 13px 20px;
  transition: background 0.12s;
  cursor: default;
}
.tenant-row:hover { background: var(--surface-2); }
.tenant-row + .tenant-row { border-top: 1px solid var(--border-2); }

.tenant-avatar {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: var(--grad-soft);
  border: 1px solid rgba(64,153,255,0.15);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Sora', sans-serif;
  font-size: 11px; font-weight: 700;
  color: var(--blue);
  flex-shrink: 0;
  letter-spacing: 0.03em;
}

.tenant-body { flex: 1; min-width: 0; }
.tenant-name {
  font-size: 13px; font-weight: 700;
  color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-bottom: 2px;
}
.tenant-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.tenant-meta-item {
  font-size: 10px; font-weight: 500;
  color: var(--text-3);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.tenant-meta-item svg { width: 11px; height: 11px; }
.meta-sep { color: var(--border); font-size: 12px; }

.tenant-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}
.tenant-amount {
  font-family: 'Sora', sans-serif;
  font-size: 14px; font-weight: 700;
  color: var(--text-1);
  font-variant-numeric: tabular-nums;
}
.tenant-amount-label {
  font-size: 9px; font-weight: 600;
  color: var(--text-3);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

/* status pills */
.pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 9px; font-weight: 800;
  letter-spacing: 0.07em; text-transform: uppercase;
  padding: 3px 9px; border-radius: 20px;
}
.pill .pdot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
.pill-active   { background: var(--teal-dim);  color: #0d9373; }
.pill-active .pdot  { background: var(--teal); }
.pill-inactive { background: var(--bg-2, #E8EDF3); color: var(--text-3); }
.pill-inactive .pdot { background: var(--text-3); }

/* plan badge */
.plan-badge {
  display: inline-block;
  font-size: 9px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  padding: 2px 8px; border-radius: 6px;
  background: var(--blue-dim);
  color: #1d6fd8;
}

/* ─────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 56px 24px;
  text-align: center;
}
.empty-icon {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: var(--grad-soft);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
}
.empty-icon svg { width: 26px; height: 26px; }
.empty-title { font-size: 14px; font-weight: 700; color: var(--text-1); margin-bottom: 5px; }
.empty-sub   { font-size: 12px; color: var(--text-3); }

/* ─────────────────────────────────────────
   PAGINATION
───────────────────────────────────────── */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 20px;
  border-top: 1px solid var(--border-2);
  background: var(--surface-2);
  flex-wrap: wrap;
  gap: 10px;
}
.pagination-info {
  font-size: 11px; font-weight: 600;
  color: var(--text-3);
}
.pagination-links {
  display: flex;
  align-items: center;
  gap: 5px;
}
.pg-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px; height: 32px;
  padding: 0 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px; font-weight: 600;
  border: 1px solid var(--border);
  border-radius: 8px;
  text-decoration: none;
  background: var(--surface);
  color: var(--text-2);
  transition: background 0.12s, border-color 0.12s, color 0.12s;
  white-space: nowrap;
}
.pg-btn:hover  { background: var(--bg); border-color: rgba(0,0,0,0.12); color: var(--text-1); text-decoration: none; }
.pg-btn.active {
  background: var(--grad);
  border-color: transparent;
  color: #fff;
  box-shadow: 0 2px 8px rgba(64,153,255,0.25);
}
.pg-btn.active:hover { opacity: 0.88; }
.pg-btn.disabled { opacity: 0.4; pointer-events: none; }
.pg-ellipsis {
  font-size: 12px; font-weight: 600;
  color: var(--text-3);
  padding: 0 4px;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <div class="main-body">
          <div class="page-wrapper sa-page">

            <!-- ── PAGE TOPBAR ── -->
            <div class="page-topbar">
              <div class="page-topbar-left">
                <div class="page-eyebrow">Tenant Management</div>
                <div class="page-title">Managed Travel Agencies</div>
                <div class="page-sub"><?= $total_items ?> tenant<?= $total_items !== 1 ? 's' : '' ?> in your portfolio</div>
              </div>
              <a href="create_tenant_subscription.php" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                New Tenant
              </a>
            </div>

            <!-- ── METRIC CARDS ── -->
            <div class="sec-label">Overview</div>
            <div class="metric-grid">

              <!-- Active Tenants -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:linear-gradient(135deg,rgba(64,153,255,0.09),rgba(46,216,182,0.09));">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#gi1)" stroke-width="2">
                      <defs><linearGradient id="gi1" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                  </div>
                  <span style="font-size:9px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:2px 8px;border-radius:20px;background:var(--teal-dim);color:#0d9373;">Live</span>
                </div>
                <div class="mcard-value grad-text"><?= $active_count ?></div>
                <div class="mcard-label">Active Tenants</div>
                <div class="mcard-sub">Currently managed</div>
              </div>

              <!-- Total Tenants -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:var(--blue-dim);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#4099ff" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                  </div>
                </div>
                <div class="mcard-value" style="color:var(--blue);"><?= $total_count ?></div>
                <div class="mcard-label">Total Tenants</div>
                <div class="mcard-sub">All-time portfolio</div>
              </div>

              <!-- Total Commission -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:var(--teal-dim);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#2ed8b6" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                </div>
                <div class="mcard-value" style="color:var(--teal);">$<?= number_format($total_commission, 2) ?></div>
                <div class="mcard-label">Total Commission</div>
                <div class="mcard-sub">From all tenants</div>
              </div>

              <!-- Avg Commission -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:var(--amber-dim);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#F59E0B" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                    </svg>
                  </div>
                </div>
                <div class="mcard-value" style="color:var(--amber);">$<?= number_format($avg_commission, 2) ?></div>
                <div class="mcard-label">Avg per Tenant</div>
                <div class="mcard-sub">Active tenants only</div>
              </div>

            </div>

            <!-- ── TENANTS PANEL ── -->
            <div class="sec-label">Your Tenants</div>
            <div class="panel">

              <!-- Panel header -->
              <div class="panel-head">
                <span class="panel-title">Managed Travel Agencies</span>
                <span class="panel-count">
                  <?php if (!empty($search_query) || !empty($status_filter)): ?>
                    <?= $total_items ?> result<?= $total_items !== 1 ? 's' : '' ?> found
                  <?php else: ?>
                    <?= $total_items ?> total
                  <?php endif; ?>
                </span>
              </div>

              <!-- Filter bar -->
              <div class="filter-bar">
                <form method="GET" action="tenants.php" style="display:contents;">
                  <div class="filter-input-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/></svg>
                    <input
                      type="text"
                      name="search"
                      class="filter-input"
                      placeholder="Search by agency name…"
                      value="<?= htmlspecialchars($search_query) ?>"
                    >
                  </div>
                  <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active"   <?= $status_filter === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                  <button type="submit" class="btn-search">Search</button>
                  <?php if (!empty($search_query) || !empty($status_filter)): ?>
                    <a href="tenants.php" class="btn-reset">Clear</a>
                  <?php endif; ?>
                </form>
              </div>

              <!-- Tenant list -->
              <?php if (!empty($tenants)): ?>
                <div class="tenant-list">
                  <?php foreach ($tenants as $t): ?>
                    <div class="tenant-row">

                      <!-- Avatar -->
                      <div class="tenant-avatar">
                        <?= strtoupper(substr($t['name'], 0, 2)) ?>
                      </div>

                      <!-- Body -->
                      <div class="tenant-body">
                        <div class="tenant-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="tenant-meta">
                          <!-- Plan -->
                          <span class="plan-badge"><?= ucfirst($t['plan'] ?? 'Standard') ?></span>
                          <span class="meta-sep">·</span>
                          <!-- Since -->
                          <span class="tenant-meta-item">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Since <?= date('M Y', strtotime($t['subscription_start_date'])) ?>
                          </span>
                          <?php if (!empty($t['subscription_end_date'])): ?>
                            <span class="meta-sep">·</span>
                            <span class="tenant-meta-item">
                              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                              Ends <?= date('M Y', strtotime($t['subscription_end_date'])) ?>
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Right: amount + status -->
                      <div class="tenant-right">
                        <div>
                          <div class="tenant-amount">$<?= number_format($t['commission_earned'], 2) ?></div>
                          <div class="tenant-amount-label">Commission</div>
                        </div>
                        <span class="pill pill-<?= $t['status'] === 'active' ? 'active' : 'inactive' ?>">
                          <span class="pdot"></span>
                          <?= ucfirst($t['status']) ?>
                        </span>
                      </div>

                    </div>
                  <?php endforeach; ?>
                </div>

              <?php else: ?>
                <div class="empty-state">
                  <div class="empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#gi2)" stroke-width="1.5">
                      <defs><linearGradient id="gi2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                  </div>
                  <?php if (!empty($search_query) || !empty($status_filter)): ?>
                    <div class="empty-title">No tenants match your filters</div>
                    <div class="empty-sub">Try adjusting your search or clearing the filters.</div>
                  <?php else: ?>
                    <div class="empty-title">No tenants yet</div>
                    <div class="empty-sub">Start by adding your first tenant to your portfolio.</div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Pagination -->
              <?php if ($total_pages > 1): ?>
                <?php
                  $start_item = $offset + 1;
                  $end_item   = min($offset + $items_per_page, $total_items);
                ?>
                <div class="pagination-bar">
                  <span class="pagination-info">
                    Showing <?= $start_item ?>–<?= $end_item ?> of <?= $total_items ?> tenant<?= $total_items !== 1 ? 's' : '' ?>
                  </span>
                  <div class="pagination-links">

                    <!-- Previous -->
                    <a href="<?= $current_page > 1 ? htmlspecialchars(paginateUrl($current_page - 1, $search_query, $status_filter)) : '#' ?>"
                       class="pg-btn <?= $current_page <= 1 ? 'disabled' : '' ?>">
                      ← Prev
                    </a>

                    <?php
                      // Smart page window: always show first, last, and ±2 around current
                      $window = 2;
                      $pages_shown = [];
                      for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i === 1 || $i === $total_pages || abs($i - $current_page) <= $window) {
                          $pages_shown[] = $i;
                        }
                      }
                      $prev_p = null;
                      foreach ($pages_shown as $p):
                        if ($prev_p !== null && $p - $prev_p > 1):
                    ?>
                          <span class="pg-ellipsis">…</span>
                    <?php   endif; ?>
                          <a href="<?= htmlspecialchars(paginateUrl($p, $search_query, $status_filter)) ?>"
                             class="pg-btn <?= $p === $current_page ? 'active' : '' ?>">
                            <?= $p ?>
                          </a>
                    <?php
                        $prev_p = $p;
                      endforeach;
                    ?>

                    <!-- Next -->
                    <a href="<?= $current_page < $total_pages ? htmlspecialchars(paginateUrl($current_page + 1, $search_query, $status_filter)) : '#' ?>"
                       class="pg-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                      Next →
                    </a>

                  </div>
                </div>
              <?php endif; ?>

            </div><!-- /.panel -->

          </div><!-- /.sa-page -->
        </div><!-- /.main-body -->
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>