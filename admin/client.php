<?php
require_once 'security.php';
require_once '../includes/db.php';

$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
     header('Location: ../login.php');
    exit();
}

include '../includes/header.php';
?>
<!-- Fonts & icons (remove if already in header.php) -->
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --ink: #0D0F12;
  --ink-2: #3A3F4A;
  --ink-3: #6C737F;
  --ink-4: #9CA3AF;
  --line: #E8EAED;
  --line-2: #F1F3F5;
  --surface: #FFFFFF;
  --surface-2: #F8F9FA;
  --surface-3: #F1F3F5;
  --blue: #2563EB;
  --blue-soft: #EFF4FF;
  --green: #059669;
  --green-soft: #ECFDF5;
  --amber: #D97706;
  --amber-soft: #FFFBEB;
  --rose: #E11D48;
  --rose-soft: #FFF1F3;
  --violet: #7C3AED;
  --violet-soft: #F5F3FF;
  --radius: 10px;
  --radius-lg: 16px;
  --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
  --shadow-sm: 0 1px 4px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.04);
  --shadow-md: 0 2px 8px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.06);
  --t: all 0.18s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: var(--surface-2);
  font-family: 'Sora', sans-serif;
  color: var(--ink);
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
}

/* ─── Layout ─── */
.shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 32px 24px;
}

/* ─── Page Header ─── */
.page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 32px;
  gap: 16px;
}

.page-head-left { display: flex; flex-direction: column; gap: 4px; }

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink-4);
  font-weight: 500;
  letter-spacing: 0.02em;
  margin-bottom: 6px;
}

.breadcrumb span { cursor: pointer; transition: var(--t); }
.breadcrumb span:hover { color: var(--blue); }
.breadcrumb i { font-size: 10px; }

.page-head h1 {
  font-size: 26px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.page-head-sub {
  font-size: 13.5px;
  color: var(--ink-3);
  font-weight: 400;
  margin-top: 4px;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--green-soft);
  color: var(--green);
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 99px;
  letter-spacing: 0.04em;
  margin-left: 10px;
  vertical-align: middle;
}

.live-badge::before {
  content: '';
  width: 6px;
  height: 6px;
  background: var(--green);
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.8); }
}

/* ─── Add Client Button ─── */
.btn-add {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 20px;
  background: var(--ink);
  color: white;
  border: none;
  border-radius: var(--radius);
  font-family: 'Sora', sans-serif;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--t);
  white-space: nowrap;
  letter-spacing: -0.01em;
}

.btn-add:hover {
  background: var(--blue);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37,99,235,0.3);
}

.btn-add i { font-size: 12px; }

/* ─── Stats Grid ─── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 28px;
}

.stat-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: 22px 24px;
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  position: relative;
  overflow: hidden;
  transition: var(--t);
  cursor: default;
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  opacity: 0;
  transition: var(--t);
}

.stat-card.blue::after  { background: var(--blue); }
.stat-card.green::after { background: var(--green); }
.stat-card.violet::after{ background: var(--violet); }
.stat-card.amber::after { background: var(--amber); }

.stat-card:hover { box-shadow: var(--shadow-sm); transform: translateY(-2px); }
.stat-card:hover::after { opacity: 1; }

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.stat-icon {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}

.stat-icon.blue   { background: var(--blue-soft);   color: var(--blue); }
.stat-icon.green  { background: var(--green-soft);  color: var(--green); }
.stat-icon.violet { background: var(--violet-soft); color: var(--violet); }
.stat-icon.amber  { background: var(--amber-soft);  color: var(--amber); }

.stat-change {
  font-size: 11.5px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 99px;
}

.stat-change.up   { background: var(--green-soft); color: var(--green); }
.stat-change.down { background: var(--rose-soft);  color: var(--rose); }

.stat-val {
  font-size: 30px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.03em;
  line-height: 1;
  margin-bottom: 5px;
  font-family: 'JetBrains Mono', monospace;
}

.stat-lbl {
  font-size: 12px;
  color: var(--ink-3);
  font-weight: 500;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

/* Sparkline placeholder */
.sparkline {
  margin-top: 14px;
  height: 32px;
  display: flex;
  align-items: flex-end;
  gap: 3px;
}

.spark-bar {
  flex: 1;
  border-radius: 3px 3px 0 0;
  opacity: 0.3;
  transition: var(--t);
}

.stat-card:hover .spark-bar { opacity: 0.6; }
.stat-card.blue  .spark-bar { background: var(--blue); }
.stat-card.green .spark-bar { background: var(--green); }
.stat-card.violet .spark-bar { background: var(--violet); }
.stat-card.amber  .spark-bar { background: var(--amber); }

/* ─── Main Card ─── */
.main-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}

/* ─── Toolbar ─── */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--line);
  gap: 12px;
  flex-wrap: wrap;
}

.toolbar-left  { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.toolbar-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

/* Search */
.search-box {
  position: relative;
  flex: 1;
  max-width: 340px;
}

.search-box i {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--ink-4);
  font-size: 13px;
  pointer-events: none;
}

.search-box input {
  width: 100%;
  padding: 9px 14px 9px 38px;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  font-family: 'Sora', sans-serif;
  font-size: 13.5px;
  color: var(--ink);
  background: var(--surface-2);
  transition: var(--t);
  outline: none;
}

.search-box input::placeholder { color: var(--ink-4); }
.search-box input:focus {
  border-color: var(--blue);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
}

/* Type filter pills */
.filter-pills { display: flex; gap: 4px; }

.filter-pill {
  padding: 7px 14px;
  border-radius: 99px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink-3);
  transition: var(--t);
  letter-spacing: 0.01em;
}

.filter-pill:hover { border-color: var(--ink-3); color: var(--ink); }
.filter-pill.active { background: var(--ink); color: white; border-color: var(--ink); }

/* ─── Toggle Tabs (pill style) ─── */
.tab-toggle {
  display: inline-flex;
  background: var(--surface-2);
  border: 1px solid var(--line);
  border-radius: 99px;
  padding: 3px;
}

.tab-btn {
  padding: 6px 16px;
  border: none;
  border-radius: 99px;
  font-family: 'Sora', sans-serif;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--t);
  background: transparent;
  color: var(--ink-3);
  white-space: nowrap;
}

.tab-btn.active {
  background: var(--surface);
  color: var(--ink);
  box-shadow: var(--shadow-xs);
}

/* ─── Table ─── */
.table-wrap { overflow-x: auto; }

table {
  width: 100%;
  border-collapse: collapse;
}

thead tr { border-bottom: 1px solid var(--line); }

thead th {
  padding: 12px 20px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--ink-4);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  white-space: nowrap;
  background: var(--surface-2);
}

thead th.num { text-align: right; }

tbody tr {
  border-bottom: 1px solid var(--line-2);
  transition: var(--t);
}

tbody tr:last-child { border-bottom: none; }

tbody tr:hover { background: var(--surface-2); }

/* Inline edit on hover */
tbody tr:hover .inline-edit { opacity: 1; }

td {
  padding: 15px 20px;
  font-size: 13.5px;
  color: var(--ink);
  vertical-align: middle;
}

td.num {
  text-align: right;
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px;
  letter-spacing: -0.01em;
}

/* Client cell */
.client-cell { display: flex; align-items: center; gap: 12px; }

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -0.01em;
  flex-shrink: 0;
  color: white;
}

.avatar.c1 { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.avatar.c2 { background: linear-gradient(135deg, #8B5CF6, #7C3AED); }
.avatar.c3 { background: linear-gradient(135deg, #10B981, #059669); }
.avatar.c4 { background: linear-gradient(135deg, #F59E0B, #D97706); }
.avatar.c5 { background: linear-gradient(135deg, #EF4444, #DC2626); }
.avatar.c6 { background: linear-gradient(135deg, #EC4899, #DB2777); }

.client-info { min-width: 0; }
.client-name {
  font-weight: 600;
  font-size: 13.5px;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.client-id {
  font-size: 11px;
  color: var(--ink-4);
  font-family: 'JetBrains Mono', monospace;
  margin-top: 1px;
}

/* Badges - Client Type & Status */
.badge, .regular, .agency, .activ, .inactive {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

/* Client Type Badges */
.regular  { background: var(--blue-soft);   color: var(--blue); }
.agency   { background: var(--violet-soft);  color: var(--violet); }

/* Status Badges */
.activ   { background: var(--green-soft);   color: var(--green); }
.inactive { background: var(--surface-3);    color: var(--ink-4); }

/* Legacy badge classes */
.badge-regular  { background: var(--blue-soft);   color: var(--blue); }
.badge-agency   { background: var(--violet-soft);  color: var(--violet); }
.badge-active   { background: var(--green-soft);   color: var(--green); }
.badge-inactive { background: var(--surface-3);    color: var(--ink-4); }

/* Balance cell */
.balance-cell { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.bal-primary { font-weight: 600; font-size: 13.5px; }
.bal-secondary { font-size: 11.5px; color: var(--ink-4); font-family: 'JetBrains Mono', monospace; }

.bal-positive { color: var(--green); }
.bal-negative { color: var(--rose); }
.bal-zero     { color: var(--ink-3); }

/* Contact cell */
.contact-cell { display: flex; flex-direction: column; gap: 2px; }
.contact-email { font-size: 13px; color: var(--ink-2); }
.contact-phone { font-size: 11.5px; color: var(--ink-4); font-family: 'JetBrains Mono', monospace; }

/* ─── Action Menu ─── */
.actions-cell { text-align: right; }

.action-row {
  display: inline-flex;
  gap: 4px;
  align-items: center;
  opacity: 0;
  transition: var(--t);
}

tbody tr:hover .action-row { opacity: 1; }

.act-btn {
  width: 30px;
  height: 30px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  transition: var(--t);
  background: transparent;
  color: var(--ink-4);
}

.act-btn:hover { background: var(--surface-3); color: var(--ink); }
.act-btn.danger:hover { background: var(--rose-soft); color: var(--rose); }
.act-btn:disabled { cursor: not-allowed; }
.act-btn:disabled:hover { background: transparent; color: var(--ink-4); }
.act-btn.primary:hover { background: var(--blue-soft); color: var(--blue); }

/* Inline edit field */
.editable {
  position: relative;
  cursor: pointer;
  padding: 4px 6px;
  border-radius: 6px;
  margin: -4px -6px;
  transition: var(--t);
}

.editable:hover { background: var(--blue-soft); }

.editable input {
  display: none;
  width: 100%;
  border: 1px solid var(--blue);
  border-radius: 6px;
  padding: 4px 8px;
  font-family: 'Sora', sans-serif;
  font-size: 13px;
  outline: none;
  background: white;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.editable.editing span  { display: none; }
.editable.editing input { display: block; }

.edit-hint {
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 9px;
  color: var(--blue);
  opacity: 0;
  pointer-events: none;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.editable:hover .edit-hint { opacity: 1; }

/* ─── Empty State ─── */
.empty-state {
  padding: 64px 24px;
  text-align: center;
  display: none;
}

.empty-icon {
  width: 56px;
  height: 56px;
  background: var(--surface-3);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: var(--ink-4);
  margin: 0 auto 16px;
}

.empty-state h3 {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink-2);
  margin-bottom: 6px;
}

.empty-state p { font-size: 13.5px; color: var(--ink-4); }

/* ─── Table Footer ─── */
.table-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  border-top: 1px solid var(--line);
  background: var(--surface-2);
  gap: 12px;
  flex-wrap: wrap;
}

.foot-count {
  font-size: 12.5px;
  color: var(--ink-4);
  font-weight: 500;
}

.foot-count strong { color: var(--ink); font-weight: 600; }

/* Pagination */
.pagination { display: flex; align-items: center; gap: 4px; }

.page-btn {
  min-width: 30px;
  height: 30px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--surface);
  font-family: 'Sora', sans-serif;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--ink-3);
  cursor: pointer;
  transition: var(--t);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 10px;
}

.page-btn:hover { border-color: var(--ink-3); color: var(--ink); }
.page-btn.active { background: var(--ink); color: white; border-color: var(--ink); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ─── Notification Toast ─── */
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: var(--ink);
  color: white;
  padding: 12px 18px;
  border-radius: var(--radius);
  font-size: 13.5px;
  font-weight: 500;
  box-shadow: var(--shadow-md);
  transform: translateY(80px);
  opacity: 0;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 10px;
  pointer-events: none;
}

.toast.show { transform: translateY(0); opacity: 1; }
.toast i { font-size: 14px; color: #4ADE80; }

/* ─── Responsive ─── */
@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .shell { padding: 20px 16px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .page-head { flex-direction: column; }
  .btn-add { width: 100%; justify-content: center; }
  .toolbar { padding: 14px 16px; }
  .toolbar-left { flex-direction: column; align-items: stretch; }
  .search-box { max-width: none; }
  .tab-toggle { width: 100%; }
  .tab-btn { flex: 1; text-align: center; }
  td, th { padding: 12px 14px; }

  /* Hide less critical columns on mobile */
  .col-email, .col-phone { display: none; }
}

@media (max-width: 500px) {
  .stats-grid { grid-template-columns: 1fr; }
  .stat-val { font-size: 26px; }
}

/* ─── Fade-in animations ─── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.stat-card:nth-child(1) { animation: fadeUp 0.4s ease 0.05s both; }
.stat-card:nth-child(2) { animation: fadeUp 0.4s ease 0.10s both; }
.stat-card:nth-child(3) { animation: fadeUp 0.4s ease 0.15s both; }
.stat-card:nth-child(4) { animation: fadeUp 0.4s ease 0.20s both; }

.main-card { animation: fadeUp 0.4s ease 0.25s both; }

</style>
</head>
<body>

<div class="pcoded-main-container">
        <div class="main-body">
          <div class="page-wrapper">
            <div class="main-content">

<div class="shell">

  <!-- ─── Page Header ─── -->
  <div class="page-head">
    <div class="page-head-left">
      <div class="breadcrumb">
        <span>Finance</span>
        <i class="fas fa-chevron-right"></i>
        <span style="color: var(--ink-2)">Clients</span>
      </div>
      <h1>Client Management <span class="live-badge">Live</span></h1>
      <p class="page-head-sub">Manage accounts, balances and relationships</p>
    </div>
    <button class="btn-add" id="addClientBtn">
      <i class="fas fa-plus"></i>
      Add Client
    </button>
  </div>

  <!-- ─── Stats Grid ─── -->
  <div class="stats-grid">

    <div class="stat-card blue">
      <div class="stat-top">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <span class="stat-change up">↑ 8%</span>
      </div>
      <div class="stat-val" id="statTotal">247</div>
      <div class="stat-lbl">Total Clients</div>
      <div class="sparkline" id="sparkTotal"></div>
    </div>

    <div class="stat-card green">
      <div class="stat-top">
        <div class="stat-icon green"><i class="fas fa-building"></i></div>
        <span class="stat-change up">↑ 3%</span>
      </div>
      <div class="stat-val" id="statAgencies">34</div>
      <div class="stat-lbl">Internal</div>
      <div class="sparkline" id="sparkAgencies"></div>
    </div>

    <div class="stat-card violet">
      <div class="stat-top">
        <div class="stat-icon violet"><i class="fas fa-dollar-sign"></i></div>
        <span class="stat-change up">↑ 12%</span>
      </div>
      <div class="stat-val" id="statUSD">$84.2k</div>
      <div class="stat-lbl">Total USD Balance</div>
      <div class="sparkline" id="sparkUSD"></div>
    </div>

    <div class="stat-card amber">
      <div class="stat-top">
        <div class="stat-icon amber"><i class="fas fa-coins"></i></div>
        <span class="stat-change down">↓ 2%</span>
      </div>
      <div class="stat-val" id="statAFS">1.2M</div>
      <div class="stat-lbl">Total AFS Balance</div>
      <div class="sparkline" id="sparkAFS"></div>
    </div>

  </div>

  <!-- ─── Main Table Card ─── -->
  <div class="main-card">

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search clients, email or phone…">
        </div>
        <div class="filter-pills">
          <button class="filter-pill active" data-type="">All</button>
          <button class="filter-pill" data-type="regular">External</button>
          <button class="filter-pill" data-type="agency">Internal</button>
        </div>
      </div>
      <div class="toolbar-right">
        <div class="tab-toggle">
          <button class="tab-btn active" data-tab="active">Active <span id="activeCount" style="font-weight:400;opacity:0.6"></span></button>
          <button class="tab-btn" data-tab="inactive">Inactive <span id="inactiveCount" style="font-weight:400;opacity:0.6"></span></button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Type</th>
            <th class="col-email">Contact</th>
            <th class="num">USD Balance</th>
            <th class="num">AFS Balance</th>
            <th>Status</th>
            <th class="num">Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody"></tbody>
      </table>
      <div class="empty-state" id="emptyState">
        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
        <h3>No clients found</h3>
        <p>Try adjusting your search or filters</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="table-foot">
      <div class="foot-count" id="footCount">Showing <strong>0</strong> of <strong>0</strong> clients</div>
      <div class="pagination" id="pagination"></div>
    </div>

  </div>
</div>

            </div><!-- /main-content -->
          </div><!-- /page-wrapper -->
        </div><!-- /main-body -->
</div><!-- /pcoded-main-container -->

<?php include '../modals/client/add_client.php'; ?>
<?php include '../modals/client/edit_client.php'; ?>

<!-- Toast -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>


<script>
// PHP → JS translations for client types
const clientTypeTranslations = {
    'regular': '<?= __("regular") ?>',
    'agency':  '<?= __("agency") ?>'
};
</script>

<!-- Vendor scripts (keep order matching original) -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
window.csrfToken = '<?= $_SESSION['csrf_token'] ?>';
<?php
$clientCountSql = $branch_id > 0
    ? 'SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND (branch_id = ? OR branch_id IS NULL)'
    : 'SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND branch_id IS NULL';
$clientCountStmt = $pdo->prepare($clientCountSql);
$clientCountStmt->execute($branch_id > 0 ? [$tenant_id, $branch_id] : [$tenant_id]);
$hasExistingClients = (int)$clientCountStmt->fetchColumn() > 0;
?>
var __hasExistingClients = <?= $hasExistingClients ? 'true' : 'false' ?>;
</script>

<!-- Client management logic -->
<script src="../js/client/client_management.js"></script>

<?php include '../includes/admin_footer.php'; ?>