<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

require_once('../includes/db.php');

include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/general/modal-styles.css">

<!-- Fonts & icons (remove if already in header.php) -->
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ─── Design System (shared with client page) ─────────────────────────────── */
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
  --teal: #0D9488;
  --teal-soft: #F0FDFA;
  --radius: 10px;
  --radius-lg: 16px;
  --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
  --shadow-sm: 0 1px 4px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.04);
  --shadow-md: 0 2px 8px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.06);
  --t: all 0.18s ease;
}

*, *::before, *::after { box-sizing: border-box; }

body {
  background: var(--surface-2);
  font-family: 'Sora', sans-serif;
  color: var(--ink);
  -webkit-font-smoothing: antialiased;
}

/* ─── Layout shell ────────────────────────────────────────────────────────── */
.supplier-shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 32px 24px;
}

/* ─── Page Header ─────────────────────────────────────────────────────────── */
.page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 32px;
  gap: 16px;
}

.page-head-left { display: flex; flex-direction: column; gap: 4px; }

.breadcrumb-nav {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink-4);
  font-weight: 500;
  letter-spacing: 0.02em;
  margin-bottom: 6px;
  list-style: none;
  padding: 0;
}

.breadcrumb-nav a { color: var(--ink-4); text-decoration: none; transition: var(--t); }
.breadcrumb-nav a:hover { color: var(--blue); }
.breadcrumb-nav i { font-size: 10px; }

.page-head h1 {
  font-size: 26px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.02em;
  line-height: 1.1;
  margin: 0;
}

.page-head-sub {
  font-size: 13.5px;
  color: var(--ink-3);
  font-weight: 400;
  margin-top: 4px;
}

/* ─── Add button ──────────────────────────────────────────────────────────── */
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
  text-decoration: none;
}

.btn-add:hover {
  background: var(--blue);
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37,99,235,0.3);
}

.btn-add i { font-size: 12px; }

/* ─── Stats Grid ──────────────────────────────────────────────────────────── */
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
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  opacity: 0;
  transition: var(--t);
}

.stat-card.blue::after   { background: var(--blue); }
.stat-card.green::after  { background: var(--green); }
.stat-card.violet::after { background: var(--violet); }
.stat-card.amber::after  { background: var(--amber); }

.stat-card:hover { box-shadow: var(--shadow-sm); transform: translateY(-2px); }
.stat-card:hover::after { opacity: 1; }

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.stat-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; flex-shrink: 0;
}

.stat-icon.blue   { background: var(--blue-soft);   color: var(--blue); }
.stat-icon.green  { background: var(--green-soft);  color: var(--green); }
.stat-icon.violet { background: var(--violet-soft); color: var(--violet); }
.stat-icon.amber  { background: var(--amber-soft);  color: var(--amber); }

.stat-change {
  font-size: 11.5px; font-weight: 600;
  padding: 3px 8px; border-radius: 99px;
}

.stat-change.up   { background: var(--green-soft); color: var(--green); }
.stat-change.down { background: var(--rose-soft);  color: var(--rose); }

.stat-val {
  font-size: 30px; font-weight: 700;
  color: var(--ink); letter-spacing: -0.03em;
  line-height: 1; margin-bottom: 5px;
  font-family: 'JetBrains Mono', monospace;
}

.stat-lbl {
  font-size: 12px; color: var(--ink-3);
  font-weight: 500; letter-spacing: 0.02em;
  text-transform: uppercase;
}

.sparkline {
  margin-top: 14px; height: 32px;
  display: flex; align-items: flex-end; gap: 3px;
}

.spark-bar {
  flex: 1; border-radius: 3px 3px 0 0;
  opacity: 0.3; transition: var(--t);
}

.stat-card:hover .spark-bar { opacity: 0.6; }
.stat-card.blue   .spark-bar { background: var(--blue); }
.stat-card.green  .spark-bar { background: var(--green); }
.stat-card.violet .spark-bar { background: var(--violet); }
.stat-card.amber  .spark-bar { background: var(--amber); }

/* ─── Main card ───────────────────────────────────────────────────────────── */
.main-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}

/* ─── Toolbar ─────────────────────────────────────────────────────────────── */
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
.search-box { position: relative; flex: 1; max-width: 340px; }

.search-box i {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%);
  color: var(--ink-4); font-size: 13px; pointer-events: none;
}

.search-box input {
  width: 100%;
  padding: 9px 14px 9px 38px;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  font-family: 'Sora', sans-serif;
  font-size: 13.5px; color: var(--ink);
  background: var(--surface-2);
  transition: var(--t); outline: none;
}

.search-box input::placeholder { color: var(--ink-4); }
.search-box input:focus {
  border-color: var(--blue); background: var(--surface);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
}

/* Filter pills */
.filter-pills { display: flex; gap: 4px; }

.filter-pill {
  padding: 7px 14px; border-radius: 99px;
  font-size: 12.5px; font-weight: 600; cursor: pointer;
  border: 1px solid var(--line); background: var(--surface);
  color: var(--ink-3); transition: var(--t); letter-spacing: 0.01em;
  font-family: 'Sora', sans-serif;
}

.filter-pill:hover { border-color: var(--ink-3); color: var(--ink); }
.filter-pill.active { background: var(--ink); color: white; border-color: var(--ink); }

/* Tab toggle */
.tab-toggle {
  display: inline-flex;
  background: var(--surface-2);
  border: 1px solid var(--line);
  border-radius: 99px; padding: 3px;
}

.tab-btn {
  padding: 6px 16px; border: none; border-radius: 99px;
  font-family: 'Sora', sans-serif; font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: var(--t);
  background: transparent; color: var(--ink-3); white-space: nowrap;
}

.tab-btn.active {
  background: var(--surface); color: var(--ink);
  box-shadow: var(--shadow-xs);
}

/* ─── Table ───────────────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; }

thead tr { border-bottom: 1px solid var(--line); }

thead th {
  padding: 12px 20px; text-align: left;
  font-size: 11px; font-weight: 700;
  color: var(--ink-4); text-transform: uppercase;
  letter-spacing: 0.07em; white-space: nowrap;
  background: var(--surface-2);
}

thead th.num { text-align: right; }

tbody tr { border-bottom: 1px solid var(--line-2); transition: var(--t); }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }
tbody tr:hover .action-row { opacity: 1; }

td {
  padding: 15px 20px; font-size: 13.5px;
  color: var(--ink); vertical-align: middle;
}

td.num {
  text-align: right;
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px; letter-spacing: -0.01em;
}

/* Supplier cell */
.supplier-cell { display: flex; align-items: center; gap: 12px; }

.avatar {
  width: 36px; height: 36px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; letter-spacing: -0.01em;
  flex-shrink: 0; color: white;
}

.avatar.c1 { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.avatar.c2 { background: linear-gradient(135deg, #8B5CF6, #7C3AED); }
.avatar.c3 { background: linear-gradient(135deg, #10B981, #059669); }
.avatar.c4 { background: linear-gradient(135deg, #F59E0B, #D97706); }
.avatar.c5 { background: linear-gradient(135deg, #EF4444, #DC2626); }
.avatar.c6 { background: linear-gradient(135deg, #0D9488, #0F766E); }

.supplier-info { min-width: 0; }

.supplier-name {
  font-weight: 600; font-size: 13.5px; color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.supplier-id {
  font-size: 11px; color: var(--ink-4);
  font-family: 'JetBrains Mono', monospace; margin-top: 1px;
}

/* Badges */
.badge {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 99px;
  font-size: 11.5px; font-weight: 600; letter-spacing: 0.02em;
}

.badge-internal   { background: var(--blue-soft);   color: var(--blue); }
.badge-external   { background: var(--violet-soft);  color: var(--violet); }
.badge-individual { background: var(--amber-soft);   color: var(--amber); }
.badge-company    { background: var(--teal-soft);    color: var(--teal); }
.badge-active     { background: var(--green-soft);   color: var(--green); }
.badge-inactive   { background: var(--surface-3);    color: var(--ink-4); }
.badge-ticket     { background: var(--blue-soft);   color: var(--blue); }
.badge-visa       { background: var(--violet-soft); color: var(--violet); }
.badge-umrah      { background: var(--amber-soft);  color: var(--amber); }
.badge-hotel      { background: var(--rose-soft);   color: var(--rose); }
.badge-all        { background: var(--teal-soft);    color: var(--teal); }
.badge-all        { background: var(--amber-soft);  color: var(--amber); }

/* Balance cell */
.bal-positive { color: var(--green); font-weight: 600; }
.bal-negative { color: var(--rose);  font-weight: 600; }
.bal-zero     { color: var(--ink-4); }

/* Currency badge */
.currency-chip {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 6px;
  font-size: 11px; font-weight: 700;
  background: var(--surface-3); color: var(--ink-2);
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: 0.04em;
}

/* Address cell */
.address-text {
  font-size: 13px; color: var(--ink-3);
  max-width: 200px; white-space: nowrap;
  overflow: hidden; text-overflow: ellipsis;
}

/* Action buttons */
.actions-cell { text-align: right; }

.action-row {
  display: inline-flex; gap: 4px; align-items: center;
  opacity: 0; transition: var(--t);
}

.act-btn {
  width: 30px; height: 30px;
  border: none; border-radius: 8px;
  cursor: pointer; display: inline-flex;
  align-items: center; justify-content: center;
  font-size: 13px; transition: var(--t);
  background: transparent; color: var(--ink-4);
}

.act-btn:hover        { background: var(--surface-3); color: var(--ink); }
.act-btn.danger:hover { background: var(--rose-soft);  color: var(--rose); }
.act-btn.primary:hover{ background: var(--blue-soft);  color: var(--blue); }

/* ─── Empty state ─────────────────────────────────────────────────────────── */
.empty-state {
  padding: 64px 24px; text-align: center; display: none;
}

.empty-icon {
  width: 56px; height: 56px;
  background: var(--surface-3); border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: var(--ink-4); margin: 0 auto 16px;
}

.empty-state h3 { font-size: 15px; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
.empty-state p  { font-size: 13.5px; color: var(--ink-4); }

/* ─── Table footer ────────────────────────────────────────────────────────── */
.table-foot {
  display: flex; align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  border-top: 1px solid var(--line);
  background: var(--surface-2);
  gap: 12px; flex-wrap: wrap;
}

.foot-count { font-size: 12.5px; color: var(--ink-4); font-weight: 500; }
.foot-count strong { color: var(--ink); font-weight: 600; }

/* Pagination */
.pagination { display: flex; align-items: center; gap: 4px; }

.page-btn {
  min-width: 30px; height: 30px;
  border: 1px solid var(--line); border-radius: 8px;
  background: var(--surface); font-family: 'Sora', sans-serif;
  font-size: 12.5px; font-weight: 500; color: var(--ink-3);
  cursor: pointer; transition: var(--t);
  display: flex; align-items: center; justify-content: center; padding: 0 10px;
}

.page-btn:hover  { border-color: var(--ink-3); color: var(--ink); }
.page-btn.active { background: var(--ink); color: white; border-color: var(--ink); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ─── Toast ───────────────────────────────────────────────────────────────── */
.toast-wrap {
  position: fixed; bottom: 24px; right: 24px;
  background: var(--ink); color: white;
  padding: 12px 18px; border-radius: var(--radius);
  font-size: 13.5px; font-weight: 500;
  box-shadow: var(--shadow-md);
  transform: translateY(80px); opacity: 0;
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  z-index: 1000; display: flex; align-items: center; gap: 10px;
  pointer-events: none;
}

.toast-wrap.show { transform: translateY(0); opacity: 1; }
.toast-wrap i { font-size: 14px; color: #4ADE80; }

/* ─── Animations ──────────────────────────────────────────────────────────── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.stat-card:nth-child(1) { animation: fadeUp 0.4s ease 0.05s both; }
.stat-card:nth-child(2) { animation: fadeUp 0.4s ease 0.10s both; }
.stat-card:nth-child(3) { animation: fadeUp 0.4s ease 0.15s both; }
.stat-card:nth-child(4) { animation: fadeUp 0.4s ease 0.20s both; }
.main-card { animation: fadeUp 0.4s ease 0.25s both; }

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .supplier-shell { padding: 20px 16px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .page-head { flex-direction: column; }
  .btn-add { width: 100%; justify-content: center; }
  .toolbar { padding: 14px 16px; }
  .toolbar-left { flex-direction: column; align-items: stretch; }
  .search-box { max-width: none; }
  .tab-toggle { width: 100%; }
  .tab-btn { flex: 1; text-align: center; }
  .col-address { display: none; }
  td, th { padding: 12px 14px; }
}

@media (max-width: 500px) {
  .stats-grid { grid-template-columns: 1fr; }
  .col-currency { display: none; }
}
</style>

<!-- pcoded wrapper -->
<div class="pcoded-main-container">
        <div class="main-body">
          <div class="page-wrapper">
            <div class="main-content">

<div class="supplier-shell">

  <!-- ─── Page Header ─── -->
  <div class="page-head">
    <div class="page-head-left">
      <nav class="breadcrumb-nav">
        <a href="dashboard.php">Finance</a>
        <i class="fas fa-chevron-right"></i>
        <span style="color: var(--ink-2)"><?php echo __('supplier'); ?></span>
      </nav>
      <h1><?php echo __('supplier'); ?></h1>
      <p class="page-head-sub"><?php echo __('manage_suppliers'); ?></p>
    </div>
    <button class="btn-add" data-toggle="modal" data-target="#addSupplierModal">
      <i class="fas fa-plus"></i>
      <?= __('add_new_supplier') ?>
    </button>
  </div>

  <!-- ─── Stats Grid ─── -->
  <div class="stats-grid">

    <div class="stat-card blue">
      <div class="stat-top">
        <div class="stat-icon blue"><i class="fas fa-truck"></i></div>
        <span class="stat-change up" id="suppliersChangeBadge">↑ 0%</span>
      </div>
      <div class="stat-val" id="statTotal">0</div>
      <div class="stat-lbl"><?= __('total_suppliers') ?? 'Total Suppliers' ?></div>
      <div class="sparkline" id="sparkTotal"></div>
    </div>

    <div class="stat-card green">
      <div class="stat-top">
        <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
        <span class="stat-change up">Active</span>
      </div>
      <div class="stat-val" id="statActive">0</div>
      <div class="stat-lbl"><?= __('active_suppliers') ?? 'Active Suppliers' ?></div>
      <div class="sparkline" id="sparkActive"></div>
    </div>

    <div class="stat-card violet">
      <div class="stat-top">
        <div class="stat-icon violet"><i class="fas fa-globe"></i></div>
        <span class="stat-change up">Foreign</span>
      </div>
      <div class="stat-val" id="statForeign">0</div>
      <div class="stat-lbl"><?= __('foreign_suppliers') ?? 'Foreign Suppliers' ?></div>
      <div class="sparkline" id="sparkForeign"></div>
    </div>

    <div class="stat-card amber">
      <div class="stat-top">
        <div class="stat-icon amber"><i class="fas fa-building"></i></div>
        <span class="stat-change up">Local</span>
      </div>
      <div class="stat-val" id="statLocal">0</div>
      <div class="stat-lbl"><?= __('local_suppliers') ?? 'Local Suppliers' ?></div>
      <div class="sparkline" id="sparkLocal"></div>
    </div>

  </div>

  <!-- ─── Main Card ─── -->
  <div class="main-card">

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="searchSupplier" placeholder="<?= __('search') ?>…">
        </div>
        <div class="filter-pills" id="typeFilterPills">
        <button class="filter-pill active" data-type="">All</button>
        <button class="filter-pill" data-type="internal"><?= __('internal') ?? 'Internal' ?></button>
        <button class="filter-pill" data-type="external"><?= __('external') ?? 'External' ?></button>
        </div>
        <div class="filter-pills" id="categoryFilterPills">
        <button class="filter-pill active" data-category="">All</button>
        <button class="filter-pill" data-category="ticket"><?= __('ticket') ?? 'Ticket' ?></button>
        <button class="filter-pill" data-category="visa"><?= __('visa') ?? 'Visa' ?></button>
        <button class="filter-pill" data-category="umrah"><?= __('umrah') ?? 'Umrah' ?></button>
        <button class="filter-pill" data-category="hotel"><?= __('hotel') ?? 'Hotel' ?></button>
        </div>
      </div>
      <div class="toolbar-right">
        <div class="tab-toggle">
          <button class="tab-btn active" data-tab="active">
            <?= __('active_suppliers') ?> <span id="activeCount" style="font-weight:400;opacity:0.6"></span>
          </button>
          <button class="tab-btn" data-tab="inactive">
            <?= __('inactive_suppliers') ?> <span id="inactiveCount" style="font-weight:400;opacity:0.6"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th><?= __('supplier_info') ?></th>
            <th><?= __('supplier_type') ?></th>
            <th><?= __('category') ?? 'Category' ?></th>
            <th class="num"><?= __('balance') ?></th>
            <th class="col-currency"><?= __('currency') ?></th>
            <th class="col-address"><?= __('address') ?></th>
            <th><?= __('status') ?></th>
            <th class="num"><?= __('actions') ?></th>
          </tr>
        </thead>
        <tbody id="supplierTableBody"></tbody>
      </table>

      <div class="empty-state" id="emptyState">
        <div class="empty-icon"><i class="fas fa-truck-ramp-box"></i></div>
        <h3>No suppliers found</h3>
        <p>Try adjusting your search or filters</p>
      </div>
    </div>

    <!-- Table Footer -->
    <div class="table-foot">
      <div class="foot-count" id="footCount">Showing <strong>0</strong> of <strong>0</strong> suppliers</div>
      <div class="pagination" id="pagination"></div>
    </div>

  </div><!-- /main-card -->

</div><!-- /supplier-shell -->

            </div><!-- /main-content -->
          </div><!-- /page-wrapper -->
        </div><!-- /main-body -->
</div><!-- /pcoded-main-container -->

<!-- Toast -->
<div class="toast-wrap" id="toastWrap">
  <i class="fas fa-check-circle"></i>
  <span id="toastMsg"></span>
</div>

<?php include '../modals/supplier/add_supplier.php'; ?>
<?php include '../modals/supplier/edit_supplier.php'; ?>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ─── Supplier Management — new UI wired to original API ─────────────────── */
(function () {

  // ── State ──
  let suppliers   = [];
  let currentTab  = 'active';
  let currentType = '';
  let currentCategory = '';
  let currentSearch = '';
  let currentPage   = 1;
  const PER_PAGE    = 8;

  // ── Avatar helpers ──
  const AVATAR_CLASSES = ['c1','c2','c3','c4','c5','c6'];

  function avatarClass(name) {
    let h = 0;
    for (const ch of (name || '')) h = (h * 31 + ch.charCodeAt(0)) % AVATAR_CLASSES.length;
    return AVATAR_CLASSES[h];
  }

  function initials(name) {
    const parts = (name || '').trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
      : (name || 'S').slice(0, 2).toUpperCase();
  }

  // ── Formatters ──
  function fmtBalance(raw) {
    const v = parseFloat(raw || 0);
    if (!v) return '<span class="bal-zero">—</span>';
    const cls = v > 0 ? 'bal-positive' : 'bal-negative';
    return `<span class="${cls}">${Math.abs(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</span>`;
  }

  function escHtml(str) {
    return String(str || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  // ── Load from API ──
  function loadSuppliers() {
    setLoading(true);
    fetch('../api/supplier/getSupplier.php')
      .then(r => r.json())
      .then(data => {
        suppliers = data.suppliers.map(s => ({
          ...s,
          balance: parseFloat(s.balance || 0),
        }));
        updateStats();
        buildSparklines();
        render();
      })
      .catch(() => showError('Failed to load suppliers'))
      .finally(() => setLoading(false));
  }

  // ── Stats ──
  function updateStats() {
    const total   = suppliers.length;
    const active  = suppliers.filter(s => s.status === 'active').length;
    const external = suppliers.filter(s => (s.supplier_type || '').toLowerCase() === 'external').length;
    const internal = suppliers.filter(s => (s.supplier_type || '').toLowerCase() === 'internal').length;

    document.getElementById('statTotal').textContent   = total;
    document.getElementById('statActive').textContent  = active;
    document.getElementById('statForeign').textContent = external;
    document.getElementById('statLocal').textContent   = internal;
  }

  // ── Sparklines ──
  function buildSparklines() {
    [
      { id: 'sparkTotal',   heights: [40,55,45,65,50,70,80,75,90,85,95,100] },
      { id: 'sparkActive',  heights: [60,65,70,68,75,80,78,85,82,88,90,95]  },
      { id: 'sparkForeign', heights: [30,40,35,50,45,60,55,70,65,75,72,80]  },
      { id: 'sparkLocal',   heights: [80,75,85,78,82,75,80,72,78,70,75,68]  },
    ].forEach(({ id, heights }) => {
      const el = document.getElementById(id);
      if (el) el.innerHTML = heights.map(h => `<div class="spark-bar" style="height:${h}%"></div>`).join('');
    });
  }

  // ── Filter ──
  function getFiltered() {
    const q = currentSearch.toLowerCase();
    return suppliers.filter(s => {
      if (s.status !== currentTab) return false;
      if (currentType && (s.supplier_type || '').toLowerCase() !== currentType) return false;
      if (currentCategory && (s.category || 'all').toLowerCase() !== currentCategory) return false;
      if (q && !(s.name || '').toLowerCase().includes(q) && !(s.id || '').toString().includes(q)) return false;
      return true;
    });
  }

  // ── Render ──
  function render() {
    const filtered = getFiltered();
    const total    = filtered.length;
    const pages    = Math.ceil(total / PER_PAGE) || 1;
    currentPage    = Math.min(currentPage, pages);

    const slice = filtered.slice((currentPage - 1) * PER_PAGE, currentPage * PER_PAGE);
    const tbody = document.getElementById('supplierTableBody');
    const empty = document.getElementById('emptyState');

    if (!slice.length) {
      tbody.innerHTML = '';
      empty.style.display = 'block';
    } else {
      empty.style.display = 'none';
      tbody.innerHTML = slice.map((s, i) => buildRow(s, (currentPage - 1) * PER_PAGE + i + 1)).join('');
    }

    // Tab counts
    const activeCnt   = suppliers.filter(s => s.status === 'active').length;
    const inactiveCnt = suppliers.filter(s => s.status === 'inactive').length;
    document.getElementById('activeCount').textContent   = `(${activeCnt})`;
    document.getElementById('inactiveCount').textContent = `(${inactiveCnt})`;

    // Footer
    const start = total ? (currentPage - 1) * PER_PAGE + 1 : 0;
    const end   = Math.min(currentPage * PER_PAGE, total);
    document.getElementById('footCount').innerHTML =
      `Showing <strong>${start}–${end}</strong> of <strong>${total}</strong> suppliers`;

    renderPagination(pages);
  }

  function buildRow(s, rowNum) {
    const typeSlug  = (s.supplier_type || '').toLowerCase();
    const typeLabel = s.supplier_type || '—';
    const categorySlug  = (s.category || 'all').toLowerCase();
    const categoryLabel = (s.category || 'all').charAt(0).toUpperCase() + (s.category || 'all').slice(1);
    const currency  = s.currency || 'USD';

    return `
      <tr data-id="${s.id}">
        <td style="color:var(--ink-4);font-family:'JetBrains Mono',monospace;font-size:12px">${rowNum}</td>
        <td>
          <div class="supplier-cell">
            <div class="avatar ${avatarClass(s.name)}">${initials(s.name)}</div>
            <div class="supplier-info">
              <div class="supplier-name">${escHtml(s.name)}</div>
              <div class="supplier-id">#${escHtml(String(s.id))}</div>
            </div>
          </div>
        </td>
        <td><span class="badge-${escHtml(typeSlug)}">${escHtml(typeLabel)}</span></td>
        <td><span class="badge-${escHtml(categorySlug)}">${escHtml(categoryLabel)}</span></td>
        <td class="num">${fmtBalance(s.balance)}</td>
        <td class="col-currency">
          <span class="currency-chip">${escHtml(currency)}</span>
        </td>
        <td class="col-address">
          <span class="address-text" title="${escHtml(s.address || '')}">${escHtml(s.address || '—')}</span>
        </td>
        <td><span class="badge-${s.status}">${s.status.charAt(0).toUpperCase() + s.status.slice(1)}</span></td>
        <td class="actions-cell">
          <div class="action-row">
            <button class="act-btn primary" onclick="editSupplier(${s.id})" title="Edit">
              <i class="fas fa-pen-to-square"></i>
            </button>
            <button class="act-btn danger" onclick="deleteSupplier(${s.id})" title="Delete">
              <i class="fas fa-trash-can"></i>
            </button>
          </div>
        </td>
      </tr>`;
  }

  // ── Pagination ──
  function renderPagination(pages) {
    const pg = document.getElementById('pagination');
    if (pages <= 1) { pg.innerHTML = ''; return; }
    let html = `<button class="page-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}><i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
    for (let i = 1; i <= pages; i++) {
      html += `<button class="page-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" onclick="goPage(${currentPage+1})" ${currentPage===pages?'disabled':''}><i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
    pg.innerHTML = html;
  }

  window.goPage = p => { currentPage = p; render(); };

  // ── Edit supplier ──
  window.editSupplier = function (id) {
    const s = suppliers.find(x => x.id === id);
    if (!s) return;

    // Populate edit modal fields (mirrors original SupplierManagement.editSupplier)
    const setVal = (sel, val) => { const el = document.getElementById(sel); if (el) el.value = val || ''; };
    setVal('editSupplierId',      s.id);
    setVal('editSupplierName',    s.name);
    setVal('editContactPerson',   s.contact_person);
    setVal('editPhone',           s.phone);
    setVal('editEmail',           s.email);
    setVal('editSupplierType',    s.supplier_type);
    setVal('editSupplierCategory', s.category || 'all');
    setVal('editCurrency',        s.currency);
    setVal('editAddress',         s.address);

    $('#editSupplierModal').modal('show');
  };

  // ── Delete supplier ──
  window.deleteSupplier = function (id) {
    const s = suppliers.find(x => x.id === id);
    if (!s) return;

    Swal.fire({
      title: 'Delete Supplier?',
      html: `<span style="color:#6C737F">This will permanently remove <strong>${escHtml(s.name)}</strong>.<br>This action cannot be undone.</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#E11D48',
      cancelButtonColor: '#6C737F',
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
    }).then(result => {
      if (!result.isConfirmed) return;

      fetch('../api/supplier/delete_supplier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) { showToast(`${s.name} removed`); loadSuppliers(); }
          else throw new Error(data.message || 'Failed to delete supplier');
        })
        .catch(err => showError(err.message));
    });
  };

  // ── Add supplier form ──
  const addForm = document.getElementById('addSupplierForm');
  if (addForm) {
    addForm.addEventListener('submit', function (e) {
      e.preventDefault();
      fetch('../api/supplier/add_supplier.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
          $('#addSupplierModal').modal('hide');
          setTimeout(() => {
            if (data.success || data.status === 'success') {
              Swal.fire({ icon: 'success', title: 'Supplier Added', timer: 1500, showConfirmButton: false })
                .then(() => { this.reset(); loadSuppliers(); });
            } else {
              showError(data.message || 'Failed to add supplier');
            }
          }, 300);
        })
        .catch(err => { $('#addSupplierModal').modal('hide'); setTimeout(() => showError(err.message), 300); });
    });
  }

  // ── Edit supplier form ──
   const editForm = document.getElementById('editSupplierForm');
   if (editForm) {
     editForm.addEventListener('submit', function (e) {
       e.preventDefault();
       fetch('../api/supplier/update_supplier.php', {
         method: 'POST',
         body: new FormData(this),
       })
         .then(r => r.json())
         .then(data => {
           if (data.success) {
             $('#editSupplierModal').modal('hide');
             showToast('Supplier updated');
             loadSuppliers();
           } else throw new Error(data.message || 'Failed to update supplier');
         })
         .catch(err => showError(err.message));
     });
   }

  // ── Search + filter ──
  document.getElementById('searchSupplier').addEventListener('input', e => {
    currentSearch = e.target.value; currentPage = 1; render();
  });

  document.querySelectorAll('#typeFilterPills .filter-pill').forEach(pill => {
    pill.addEventListener('click', function () {
      document.querySelectorAll('#typeFilterPills .filter-pill').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      currentType = this.dataset.type; currentPage = 1; render();
    });
  });

  document.querySelectorAll('#categoryFilterPills .filter-pill').forEach(pill => {
    pill.addEventListener('click', function () {
      document.querySelectorAll('#categoryFilterPills .filter-pill').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      currentCategory = this.dataset.category; currentPage = 1; render();
    });
  });

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentTab = this.dataset.tab; currentPage = 1; render();
    });
  });

  // ── Toast ──
  function showToast(msg) {
    const wrap = document.getElementById('toastWrap');
    document.getElementById('toastMsg').textContent = msg;
    wrap.classList.add('show');
    clearTimeout(wrap._t);
    wrap._t = setTimeout(() => wrap.classList.remove('show'), 2600);
  }

  // ── Error ──
  function showError(msg) {
    Swal.fire({ icon: 'error', title: 'Error', text: msg });
  }

  // ── Loading ──
  function setLoading(on) {
    const el = document.querySelector('.loader-bg');
    if (!el) return;
    if (on) { el.style.display = 'block'; el.classList.remove('fade-out'); }
    else { el.classList.add('fade-out'); setTimeout(() => (el.style.display = 'none'), 300); }
  }

  // ── Init ──
  buildSparklines();
  loadSuppliers();

})();
</script>

<?php include '../includes/admin_footer.php'; ?>