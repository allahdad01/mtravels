<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include the security module
require_once('security.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication for this page
enforce_auth();

require_permission('finance.jv');
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Get all clients
$clientsStmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name");
$clientsStmt->execute([$tenant_id, $branch_id]);
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all suppliers
$suppliersStmt = $pdo->prepare("SELECT id, name, balance, currency FROM suppliers WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name");
$suppliersStmt->execute([$tenant_id, $branch_id]);
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination settings
$items_per_page = 10;
$current_page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset         = ($current_page - 1) * $items_per_page;

// Search
$search_query     = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
if (!empty($search_query)) {
    $search_condition = " AND (jp.jv_name LIKE ? OR c.name LIKE ? OR s.name LIKE ? OR jp.receipt LIKE ?)";
}

// Total count
$countParams = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $sp = '%' . $search_query . '%';
    $countParams = array_merge($countParams, array_fill(0, 4, $sp));
}
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM jv_payments jp LEFT JOIN clients c ON jp.client_id = c.id LEFT JOIN suppliers s ON jp.supplier_id = s.id WHERE jp.tenant_id = ? AND jp.branch_id = ?" . $search_condition);
    $countStmt->execute($countParams);
    $total_records = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages   = ceil($total_records / $items_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages   = 1;
}

// Fetch paginated payments
$csPayments = [];
try {
    $params = [$tenant_id, $branch_id];
    if (!empty($search_query)) {
        $sp     = '%' . $search_query . '%';
        $params = array_merge($params, array_fill(0, 4, $sp));
    }
    $params[] = $items_per_page;
    $params[] = $offset;
    $csStmt = $pdo->prepare("SELECT jp.*, c.name as client_name, s.name as supplier_name FROM jv_payments jp LEFT JOIN clients c ON jp.client_id = c.id LEFT JOIN suppliers s ON jp.supplier_id = s.id WHERE jp.tenant_id = ? AND jp.branch_id = ?" . $search_condition . " ORDER BY jp.created_at DESC LIMIT ? OFFSET ?");
    $csStmt->execute($params);
    $csPayments = $csStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching client-supplier payments: " . $e->getMessage());
}

$search_param_str = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
?>
<?php include '../includes/header.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ─── Design tokens ────────────────────────────────────── */
:root {
  --bg:          #fafaf9;
  --surface:     #ffffff;
  --border:      #e5e5e3;
  --border-soft: #f0f0ee;
  --text-primary:#1a1a18;
  --text-muted:  #8c8c87;
  --text-dim:    #b5b5b0;
  --accent:      #1a1a18;
  --accent-soft: #f4f4f2;
  --green:       #16a34a;
  --green-bg:    #f0fdf4;
  --amber:       #b45309;
  --amber-bg:    #fffbeb;
  --blue:        #1d4ed8;
  --blue-bg:     #eff6ff;
  --red:         #dc2626;
  --red-bg:      #fef2f2;
  --radius:      6px;
  --radius-lg:   10px;
  --shadow:      0 1px 4px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.04);
  --font-sans:   'DM Sans', sans-serif;
  --font-mono:   'Geist Mono', monospace;
  --t:           150ms cubic-bezier(0.16, 1, 0.3, 1);
}

/* Scope everything under .jvp- to avoid fighting pcoded styles */
.jvp-wrap {
  font-family: var(--font-sans);
  color: var(--text-primary);
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  padding: 28px;
}

/* ─── Page Header ───────────────────────────────────────── */
.jvp-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}
.jvp-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--text-muted);
  margin-bottom: 5px;
  font-family: var(--font-mono);
  letter-spacing: .02em;
}
.jvp-breadcrumb svg { opacity: .4; }
.jvp-title {
  font-size: 21px;
  font-weight: 600;
  color: var(--text-primary);
  letter-spacing: -.4px;
  line-height: 1.2;
  margin: 0;
}
.jvp-subtitle {
  font-size: 13px;
  color: var(--text-muted);
  margin-top: 3px;
}
.jvp-head-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  margin-top: 2px;
  flex-wrap: wrap;
}

/* ─── Stat chips ────────────────────────────────────────── */
.jvp-stat-chips {
  display: flex;
  align-items: center;
  gap: 2px;
  background: var(--border-soft);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 3px;
}
.jvp-stat-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 5px;
  font-size: 12px;
  color: var(--text-muted);
  white-space: nowrap;
  transition: background var(--t);
}
.jvp-stat-chip:hover { background: var(--surface); }
.jvp-stat-chip strong { color: var(--text-primary); font-weight: 600; font-size: 13px; }
.jvp-stat-sep { width: 1px; height: 18px; background: var(--border); }

/* ─── Buttons ───────────────────────────────────────────── */
.jvp-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 500;
  border-radius: var(--radius);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all var(--t);
  text-decoration: none !important;
  white-space: nowrap;
  line-height: 1;
  background: none;
}
.jvp-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.jvp-btn-primary { background: var(--text-primary) !important; color: #fff !important; border-color: var(--text-primary) !important; }
.jvp-btn-primary:hover { background: #2d2d2b !important; color: #fff !important; }
.jvp-btn-primary:active { background: #111110 !important; transform: translateY(1px); }
.jvp-btn-ghost { background: transparent !important; color: var(--text-muted) !important; border-color: var(--border) !important; }
.jvp-btn-ghost:hover { background: var(--accent-soft) !important; color: var(--text-primary) !important; border-color: #d0d0cd !important; }
.jvp-btn-danger { background: var(--red) !important; color: #fff !important; border-color: var(--red) !important; }
.jvp-btn-danger:hover { background: #b91c1c !important; color: #fff !important; }

.jvp-btn-icon {
  padding: 6px;
  border-radius: var(--radius);
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-muted);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all var(--t);
  line-height: 0;
}
.jvp-btn-icon:hover { background: var(--accent-soft); color: var(--text-primary); border-color: var(--border); }
.jvp-btn-icon.danger:hover { background: var(--red-bg); color: var(--red); border-color: #fca5a5; }

/* ─── Alerts ────────────────────────────────────────────── */
.jvp-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 14px;
  border-radius: var(--radius);
  margin-bottom: 16px;
  font-size: 13px;
  animation: jvpSlideDown 300ms ease both;
}
.jvp-alert.success { background: var(--green-bg); border: 1px solid #bbf7d0; color: #166534; }
.jvp-alert.error   { background: var(--red-bg);   border: 1px solid #fca5a5;  color: #7f1d1d; }
.jvp-alert.info    { background: var(--blue-bg);   border: 1px solid #bfdbfe;  color: #1e3a8a; }
.jvp-alert .dismiss { margin-left: auto; flex-shrink: 0; background: none; border: none; cursor: pointer; opacity: .6; line-height: 0; color: inherit; }
.jvp-alert .dismiss:hover { opacity: 1; }
@keyframes jvpSlideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

/* ─── Toolbar ───────────────────────────────────────────── */
.jvp-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.jvp-search-wrap { position: relative; flex: 1; max-width: 360px; }
.jvp-search-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-dim); pointer-events: none; }
.jvp-search-input {
  width: 100%;
  padding: 7px 10px 7px 34px;
  font-family: var(--font-sans);
  font-size: 13px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--text-primary);
  transition: border var(--t), box-shadow var(--t);
}
.jvp-search-input::placeholder { color: var(--text-dim); }
.jvp-search-input:focus { outline: none; border-color: #aaa; box-shadow: 0 0 0 3px rgba(26,26,24,.06); }
.jvp-toolbar-right { display: flex; align-items: center; gap: 6px; margin-left: auto; }

/* ─── Table card ────────────────────────────────────────── */
.jvp-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.jvp-table-wrap { overflow-x: auto; }
.jvp-table {
  width: 100%;
  border-collapse: collapse;
  font-family: var(--font-sans);
}
.jvp-table thead tr { background: var(--border-soft); border-bottom: 1px solid var(--border); }
.jvp-table th {
  padding: 9px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  white-space: nowrap;
}
.jvp-table th.right, .jvp-table td.right { text-align: right; }
.jvp-table th.center, .jvp-table td.center { text-align: center; }
.jvp-table tbody tr { border-bottom: 1px solid var(--border-soft); transition: background var(--t); }
.jvp-table tbody tr:last-child { border-bottom: none; }
.jvp-table tbody tr:hover { background: var(--border-soft); }
.jvp-table td { padding: 11px 14px; font-size: 13px; vertical-align: middle; color: var(--text-primary); }

/* Cell helpers */
.jvp-date-main { font-size: 13px; font-weight: 500; }
.jvp-date-sub  { font-size: 11px; color: var(--text-dim); font-family: var(--font-mono); margin-top: 1px; }
.jvp-jv-label  { display: inline-flex; align-items: center; font-family: var(--font-mono); font-size: 12px; font-weight: 500; background: var(--accent-soft); color: var(--text-primary); border: 1px solid var(--border); padding: 3px 8px; border-radius: 4px; }
.jvp-party-name { font-size: 13px; font-weight: 500; }
.jvp-party-role { font-size: 11px; color: var(--text-dim); }
.jvp-amount     { font-family: var(--font-mono); font-size: 13px; font-weight: 500; }
.jvp-currency-badge { display: inline-block; font-family: var(--font-mono); font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 3px; letter-spacing: .04em; }
  .jvp-currency-badge.usd { background: var(--blue-bg); color: var(--blue); border: 1px solid #bfdbfe; }
  .jvp-currency-badge.afs { background: var(--amber-bg); color: var(--amber); border: 1px solid #fde68a; }
  .jvp-currency-badge.eur { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
  .jvp-currency-badge.darham { background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; }
  .jvp-currency-badge.sar { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.jvp-receipt-code { font-family: var(--font-mono); font-size: 12px; color: var(--text-muted); background: var(--border-soft); padding: 2px 7px; border-radius: 3px; border: 1px solid var(--border); }

/* Row actions — fade in on hover */
.jvp-row-actions { display: flex; align-items: center; gap: 4px; opacity: 0; transition: opacity var(--t); }
.jvp-table tbody tr:hover .jvp-row-actions { opacity: 1; }

/* ─── Table footer / pagination ─────────────────────────── */
.jvp-table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  background: var(--border-soft);
  flex-wrap: wrap;
  gap: 10px;
}
.jvp-table-count { font-size: 12px; color: var(--text-muted); }
.jvp-table-count strong { color: var(--text-primary); }
.jvp-pagination { display: flex; align-items: center; gap: 3px; }
.jvp-page-btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 30px; height: 30px; padding: 0 6px;
  border-radius: var(--radius); border: 1px solid transparent;
  font-size: 12.5px; font-family: var(--font-sans); font-weight: 500;
  cursor: pointer; color: var(--text-muted); background: transparent;
  transition: all var(--t); text-decoration: none;
}
.jvp-page-btn:hover { background: var(--surface); border-color: var(--border); color: var(--text-primary); }
.jvp-page-btn.active { background: var(--text-primary); color: #fff; border-color: var(--text-primary); }
.jvp-page-btn.disabled, .jvp-page-btn[disabled] { opacity: .35; cursor: default; pointer-events: none; }
.jvp-page-ellipsis { color: var(--text-dim); font-size: 13px; padding: 0 2px; }

/* ─── Empty state ───────────────────────────────────────── */
.jvp-empty {
  padding: 56px 24px;
  text-align: center;
}
.jvp-empty-icon {
  width: 44px; height: 44px;
  background: var(--border-soft); border: 1px solid var(--border); border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 14px; color: var(--text-dim);
}
.jvp-empty-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.jvp-empty-sub   { font-size: 13px; color: var(--text-muted); }

/* ─── Modals ────────────────────────────────────────────── */
.jvp-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.3); backdrop-filter: blur(2px);
  z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}
.jvp-modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,.15), 0 0 0 1px rgba(0,0,0,.06);
  width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto;
  animation: jvpModalUp 200ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.jvp-modal.sm { max-width: 420px; }
@keyframes jvpModalUp { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.jvp-modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 22px 16px; border-bottom: 1px solid var(--border);
}
.jvp-modal-head h2 { font-size: 15px; font-weight: 600; letter-spacing: -.2px; margin: 0; }
.jvp-modal-head p  { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.jvp-modal-body { padding: 22px; }
.jvp-modal-foot {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 8px; padding: 14px 22px;
  border-top: 1px solid var(--border); background: var(--border-soft);
}

/* ─── Form ──────────────────────────────────────────────── */
.jvp-form-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.jvp-form-full   { grid-template-columns: 1fr; }
.jvp-form-three  { grid-template-columns: 1fr 1fr 1fr; }
.jvp-field {}
.jvp-field label {
  display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px;
}
.jvp-field input,
.jvp-field select,
.jvp-field textarea {
  width: 100%; padding: 8px 11px;
  font-family: var(--font-sans); font-size: 13.5px;
  border: 1px solid var(--border); border-radius: var(--radius);
  background: var(--surface); color: var(--text-primary);
  transition: border var(--t), box-shadow var(--t);
}
.jvp-field input:focus,
.jvp-field select:focus,
.jvp-field textarea:focus { outline: none; border-color: #aaa; box-shadow: 0 0 0 3px rgba(26,26,24,.06); }
.jvp-field input::placeholder { color: var(--text-dim); }
.jvp-field textarea { resize: vertical; min-height: 62px; }
.jvp-field-hint { font-size: 11.5px; color: var(--text-dim); margin-top: 4px; }
.jvp-section-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--text-dim);
  margin: 18px 0 12px; padding-bottom: 7px;
  border-bottom: 1px solid var(--border-soft);
}

/* ─── View modal detail cells ───────────────────────────── */
.jvp-detail-hero {
  background: var(--green-bg); border: 1px solid #bbf7d0;
  border-radius: 8px; padding: 18px 20px; margin-bottom: 20px;
  display: flex; align-items: center; justify-content: space-between;
}
.jvp-detail-hero .label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #15803d; margin-bottom: 4px; }
.jvp-detail-hero .amount { font-size: 26px; font-weight: 600; letter-spacing: -1px; color: #166534; font-family: var(--font-mono); }
.jvp-party-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.jvp-party-box { border: 1px solid var(--border); border-radius: 8px; padding: 14px; }
.jvp-party-box .box-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-dim); margin-bottom: 6px; }
.jvp-party-box .box-name  { font-size: 14px; font-weight: 600; }
.jvp-party-box .box-sub   { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.jvp-detail-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; font-size: 13px; }
.jvp-detail-cell .dc-label { font-size: 11px; color: var(--text-dim); margin-bottom: 3px; }
.jvp-detail-cell .dc-value { font-weight: 500; }

/* ─── Toast ─────────────────────────────────────────────── */
.jvp-toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
.jvp-toast {
  display: flex; align-items: center; gap: 10px;
  background: var(--text-primary); color: #fff;
  padding: 11px 16px; border-radius: 8px; font-size: 13px;
  box-shadow: 0 4px 20px rgba(0,0,0,.25);
  font-family: var(--font-sans);
  animation: jvpToastIn 250ms cubic-bezier(0.16,1,0.3,1) both;
}
.jvp-toast.success { background: #15803d; }
.jvp-toast.error   { background: var(--red); }
@keyframes jvpToastIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ─── Responsive ────────────────────────────────────────── */
@media (max-width: 768px) {
  .jvp-wrap { padding: 16px; }
  .jvp-page-head { flex-direction: column; }
  .jvp-stat-chips { flex-wrap: wrap; }
  .jvp-form-grid, .jvp-form-three { grid-template-columns: 1fr; }
  .jvp-party-grid, .jvp-detail-grid { grid-template-columns: 1fr; }
  .jvp-table-footer { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
        <div class="main-body">
          <div class="page-wrapper">
            <div class="jvp-wrap">

              <!-- ── Flash messages ─────────────────────────── -->
              <?php if ($success_message): ?>
              <div class="jvp-alert success" id="jvpAlertSuccess">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php echo htmlspecialchars($success_message); ?>
                <button class="dismiss" onclick="this.closest('.jvp-alert').remove()">
                  <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M10 3L3 10M3 3l7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
              </div>
              <?php endif; ?>
              <?php if ($error_message): ?>
              <div class="jvp-alert error" id="jvpAlertError">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?php echo htmlspecialchars($error_message); ?>
                <button class="dismiss" onclick="this.closest('.jvp-alert').remove()">
                  <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M10 3L3 10M3 3l7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
              </div>
              <?php endif; ?>

              <!-- ── Page Header ────────────────────────────── -->
              <div class="jvp-page-head">
                <div>
                  <div class="jvp-breadcrumb">
                    <span><?php echo __('finance'); ?></span>
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php echo __('jv_payments_management'); ?></span>
                  </div>
                  <h1 class="jvp-title"><?php echo __('jv_payments_management'); ?></h1>
                  <p class="jvp-subtitle"><?php echo __('client_to_supplier_payment_management'); ?></p>
                </div>

                <div class="jvp-head-right">
                  <!-- Inline stat chips -->
                  <div class="jvp-stat-chips">
                    <div class="jvp-stat-chip">
                      <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M2 8h8M2 12h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      <strong><?php echo $total_records; ?></strong> <?php echo __('total_payments'); ?>
                    </div>
                    <div class="jvp-stat-sep"></div>
                    <div class="jvp-stat-chip">
                      <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="7" r="3.5" stroke="currentColor" stroke-width="1.5"/><path d="M2.5 14c0-2.5 2.5-4 5.5-4s5.5 1.5 5.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      <strong><?php echo count($clients); ?></strong> <?php echo __('active_clients'); ?>
                    </div>
                    <div class="jvp-stat-sep"></div>
                    <div class="jvp-stat-chip">
                      <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M5 3V2M11 3V2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                      <strong><?php echo count($suppliers); ?></strong> <?php echo __('active_suppliers'); ?>
                    </div>
                  </div>

                  <button class="jvp-btn jvp-btn-ghost" onclick="window.location.reload()">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13.5 8a5.5 5.5 0 1 1-1.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 2l2.5 2.5L10 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Refresh
                  </button>

                  <button class="jvp-btn jvp-btn-primary" onclick="jvpOpenModal('addModal')">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <?php echo __('add_new_payment'); ?>
                  </button>
                </div>
              </div>

              <!-- ── Toolbar / Search ───────────────────────── -->
              <div class="jvp-toolbar">
                <form method="GET" style="display:flex;align-items:center;gap:8px;flex:1;flex-wrap:wrap">
                  <div class="jvp-search-wrap">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <input type="text" name="search" class="jvp-search-input"
                           placeholder="<?php echo __('search_by_jv_name_client_supplier_receipt'); ?>…"
                           value="<?php echo htmlspecialchars($search_query); ?>">
                  </div>
                  <button type="submit" class="jvp-btn jvp-btn-ghost">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <?php echo __('search'); ?>
                  </button>
                  <?php if (!empty($search_query)): ?>
                  <a href="jv_payments.php" class="jvp-btn jvp-btn-ghost">
                    <svg width="12" height="12" viewBox="0 0 13 13" fill="none"><path d="M10 3L3 10M3 3l7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    Clear
                  </a>
                  <?php endif; ?>
                </form>
              </div>

              <!-- ── Table ──────────────────────────────────── -->
              <div class="jvp-table-card">
                <div class="jvp-table-wrap">
                  <table class="jvp-table">
                    <thead>
                      <tr>
                        <th><?php echo __('date'); ?></th>
                        <th><?php echo __('jv_name'); ?></th>
                        <th><?php echo __('client'); ?></th>
                        <th><?php echo __('supplier'); ?></th>
                        <th class="right"><?php echo __('amount'); ?></th>
                        <th class="center"><?php echo __('currency'); ?></th>
                        <th><?php echo __('receipt'); ?></th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($csPayments)): ?>
                      <tr>
                        <td colspan="8">
                          <div class="jvp-empty">
                            <div class="jvp-empty-icon">
                              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="4" width="14" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </div>
                            <div class="jvp-empty-title">No payments found</div>
                            <div class="jvp-empty-sub">
                              <?php if (!empty($search_query)): ?>
                                No results for "<?php echo htmlspecialchars($search_query); ?>"
                              <?php else: ?>
                                <?php echo __('add_new_payment'); ?> to get started
                              <?php endif; ?>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <?php else: foreach ($csPayments as $payment):
                        $currClass = strtolower($payment['currency']);
                      ?>
                      <tr>
                        <td>
                          <div class="jvp-date-main"><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></div>
                          <div class="jvp-date-sub"><?php echo date('H:i', strtotime($payment['created_at'])); ?></div>
                        </td>
                        <td><span class="jvp-jv-label"><?php echo htmlspecialchars($payment['jv_name']); ?></span></td>
                        <td>
                          <div class="jvp-party-name"><?php echo htmlspecialchars($payment['client_name'] ?? '—'); ?></div>
                          <div class="jvp-party-role"><?php echo __('client'); ?></div>
                        </td>
                        <td>
                          <div class="jvp-party-name"><?php echo htmlspecialchars($payment['supplier_name'] ?? '—'); ?></div>
                          <div class="jvp-party-role"><?php echo __('supplier'); ?></div>
                        </td>
                        <td class="right"><span class="jvp-amount"><?php echo number_format($payment['total_amount'], 2); ?></span></td>
                        <td class="center"><span class="jvp-currency-badge <?php echo $currClass; ?>"><?php echo htmlspecialchars($payment['currency']); ?></span></td>
                        <td><span class="jvp-receipt-code"><?php echo htmlspecialchars($payment['receipt']); ?></span></td>
                        <td>
                          <div class="jvp-row-actions">
                            <!-- View -->
                             <button class="jvp-btn-icon view-cs-btn"
                                     data-id="<?php echo $payment['id']; ?>"
                                     title="<?php echo __('view'); ?>">
                               <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><ellipse cx="8" cy="8" rx="6" ry="4" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8" r="1.5" fill="currentColor"/></svg>
                             </button>
                             <!-- Edit -->
                             <button class="jvp-btn-icon edit-cs-btn"
                                     data-id="<?php echo $payment['id']; ?>"
                                     title="<?php echo __('edit'); ?>">
                               <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M11 2.5l2.5 2.5L5 13.5H2.5V11L11 2.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                             </button>
                             <!-- Delete -->
                             <button class="jvp-btn-icon danger delete-cs-btn"
                                    data-id="<?php echo $payment['id']; ?>"
                                    title="<?php echo __('delete'); ?>">
                              <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 5h10M6 5V3.5h4V5M6 8v4M10 8v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="3" y="5" width="10" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination footer -->
                <div class="jvp-table-footer">
                  <p class="jvp-table-count">
                    <?php
                    $from = $total_records > 0 ? $offset + 1 : 0;
                    $to   = min($offset + $items_per_page, $total_records);
                    ?>
                    Showing <strong><?php echo $from; ?>–<?php echo $to; ?></strong> of <strong><?php echo $total_records; ?></strong> payments
                  </p>
                  <div class="jvp-pagination">
                    <!-- Prev -->
                    <?php if ($current_page > 1): ?>
                      <a href="?page=1<?php echo $search_param_str; ?>" class="jvp-page-btn">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M9 2L5 6l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M7 2L3 6l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </a>
                      <a href="?page=<?php echo $current_page - 1; ?><?php echo $search_param_str; ?>" class="jvp-page-btn">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M8 2L4 6l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </a>
                    <?php else: ?>
                      <span class="jvp-page-btn disabled"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M8 2L4 6l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <?php endif; ?>

                    <!-- Page numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page   = min($total_pages, $current_page + 2);
                    if ($start_page > 1) echo '<span class="jvp-page-ellipsis">…</span>';
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                      <a href="?page=<?php echo $i; ?><?php echo $search_param_str; ?>"
                         class="jvp-page-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                      </a>
                    <?php endfor;
                    if ($end_page < $total_pages) echo '<span class="jvp-page-ellipsis">…</span>';
                    ?>

                    <!-- Next -->
                    <?php if ($current_page < $total_pages): ?>
                      <a href="?page=<?php echo $current_page + 1; ?><?php echo $search_param_str; ?>" class="jvp-page-btn">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </a>
                      <a href="?page=<?php echo $total_pages; ?><?php echo $search_param_str; ?>" class="jvp-page-btn">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 2l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M5 2l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </a>
                    <?php else: ?>
                      <span class="jvp-page-btn disabled"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div><!-- /table-card -->

            </div><!-- /jvp-wrap -->
          </div>
        </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     ADD PAYMENT MODAL
════════════════════════════════════════════════════════ -->
<div class="jvp-backdrop" id="addModal" style="display:none" onclick="jvpBackdropClick(event,'addModal')">
  <div class="jvp-modal">
    <div class="jvp-modal-head">
      <div>
        <h2><?php echo __('add_client_to_supplier_payment'); ?></h2>
        <p><?php echo __('create_a_direct_payment_between_client_and_supplier'); ?></p>
      </div>
      <button class="jvp-btn-icon" onclick="jvpCloseModal('addModal')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="POST" action="process_client_supplier_jv.php" id="clientSupplierForm">
      <div class="jvp-modal-body">

        <div class="jvp-form-grid jvp-form-full" style="grid-template-columns:1fr">
          <div class="jvp-field">
            <label><?php echo __('jv_name'); ?></label>
            <input type="text" name="jv_name" value="Client-Supplier Payment" required placeholder="Payment name">
          </div>
        </div>

        <div class="jvp-section-title"><?php echo __('transaction_parties'); ?></div>
        <div class="jvp-form-grid">
          <div class="jvp-field">
            <label><?php echo __('client'); ?></label>
            <select name="client_id" id="client_id" required>
              <option value=""><?php echo __('select_client'); ?></option>
              <?php foreach ($clients as $client): ?>
              <option value="<?php echo $client['id']; ?>"
                      data-usd-balance="<?php echo $client['usd_balance']; ?>"
                      data-afs-balance="<?php echo $client['afs_balance']; ?>">
                <?php echo htmlspecialchars($client['name']); ?>
                (USD: <?php echo number_format($client['usd_balance'], 0); ?> / AFS: <?php echo number_format($client['afs_balance'], 0); ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="jvp-field">
            <label><?php echo __('supplier'); ?></label>
            <select name="supplier_id" id="supplier_id" required>
              <option value=""><?php echo __('select_supplier'); ?></option>
              <?php foreach ($suppliers as $supplier): ?>
              <option value="<?php echo $supplier['id']; ?>"
                      data-currency="<?php echo $supplier['currency']; ?>"
                      data-balance="<?php echo $supplier['balance']; ?>">
                <?php echo htmlspecialchars($supplier['name']); ?>
                (<?php echo number_format($supplier['balance'], 0); ?> <?php echo $supplier['currency']; ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="jvp-section-title"><?php echo __('amount_currency'); ?></div>
        <div class="jvp-form-grid jvp-form-three" style="grid-template-columns:1fr 1fr 1fr">
          <div class="jvp-field">
            <label>Client Balance</label>
            <select name="balance_currency" id="balance_currency">
              <option value="USD">USD – US Dollar</option>
              <option value="AFS">AFS – Afghani</option>
            </select>
            <p class="jvp-field-hint">Which client balance to credit</p>
          </div>
          <div class="jvp-field">
            <label><?php echo __('currency'); ?></label>
            <select name="currency" id="currency">
              <option value="USD">USD – US Dollar</option>
              <option value="AFS">AFS – Afghani</option>
              <option value="EUR">EUR – Euro</option>
              <option value="DARHAM">DARHAM – UAE Dirham</option>
              <option value="SAR">SAR – Saudi Riyal</option>
            </select>
            <p class="jvp-field-hint">What the client pays in</p>
          </div>
          <div class="jvp-field">
            <label><?php echo __('amount'); ?></label>
            <input type="number" step="0.01" name="total_amount" id="total_amount" required placeholder="0.00">
            <p class="jvp-field-hint" id="addAmountHint">in USD</p>
          </div>
        </div>
        <div class="jvp-form-grid jvp-form-three" style="grid-template-columns:1fr 1fr 1fr">
          <div class="jvp-field" id="exchangeRateField" style="display:none">
            <label>Exchange Rate (client)</label>
            <input type="number" step="0.00001" name="exchange_rate" id="exchange_rate" placeholder="1.00000">
            <p class="jvp-field-hint" id="exchangeRateHint">1 USD = X AFS, enter X</p>
          </div>
          <div class="jvp-field" id="supplierRateField" style="display:none">
            <label>Exchange Rate (supplier)</label>
            <input type="number" step="0.00001" name="supplier_rate" id="supplier_rate" placeholder="1.00000">
            <p class="jvp-field-hint" id="supplierRateHint">1 USD = X AFS, enter X</p>
          </div>
        </div>
        <div id="addConversionPreview" style="background:var(--blue-bg);border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px;font-size:13px;color:#1e3a8a;line-height:1.7;display:none"></div>

        <div class="jvp-section-title"><?php echo __('additional_details'); ?></div>
        <div class="jvp-form-grid">
          <div class="jvp-field">
            <label><?php echo __('receipt_number'); ?></label>
            <input type="text" name="receipt" id="receipt" required placeholder="RCP-XXXXX">
          </div>
          <div class="jvp-field">
            <label><?php echo __('remarks'); ?></label>
            <textarea name="remarks" id="remarks" placeholder="<?php echo __('optional_notes'); ?>…"></textarea>
          </div>
        </div>

      </div>
      <div class="jvp-modal-foot">
        <button type="button" class="jvp-btn jvp-btn-ghost" onclick="jvpCloseModal('addModal')"><?php echo __('cancel'); ?></button>
        <button type="submit" class="jvp-btn jvp-btn-primary" id="addSubmitBtn">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13 4L6.5 11 3 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <?php echo __('process_payment'); ?>
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     VIEW MODAL
════════════════════════════════════════════════════════ -->
<div class="jvp-backdrop" id="viewModal" style="display:none" onclick="jvpBackdropClick(event,'viewModal')">
  <div class="jvp-modal">
    <div class="jvp-modal-head">
      <div>
        <h2 id="viewModalTitle"><?php echo __('payment_details'); ?></h2>
        <p id="viewModalSubtitle" style="font-family:var(--font-mono);font-size:11px"></p>
      </div>
      <button class="jvp-btn-icon" onclick="jvpCloseModal('viewModal')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <div class="jvp-modal-body" id="viewModalBody">
      <div style="text-align:center;padding:32px 0;color:var(--text-dim)">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;display:inline-block"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="40" stroke-dashoffset="10"/></svg>
        <p style="margin-top:10px;font-size:13px"><?php echo __('loading_details'); ?>…</p>
      </div>
    </div>
    <div class="jvp-modal-foot">
      <button class="jvp-btn jvp-btn-ghost" onclick="jvpCloseModal('viewModal')"><?php echo __('close'); ?></button>
      <button class="jvp-btn jvp-btn-ghost" id="viewEditBtn" style="display:none">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M11 2.5l2.5 2.5L5 13.5H2.5V11L11 2.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        <?php echo __('edit'); ?>
      </button>
    </div>
  </div>
</div>





<!-- ═══════════════════════════════════════════════════════
     DELETE MODAL
════════════════════════════════════════════════════════ -->
<div class="jvp-backdrop" id="deleteModal" style="display:none" onclick="jvpBackdropClick(event,'deleteModal')">
  <div class="jvp-modal sm">
    <div class="jvp-modal-head">
      <div>
        <h2><?php echo __('delete_client_supplier_payment'); ?></h2>
        <p><?php echo __('this_action_cannot_be_undone'); ?></p>
      </div>
      <button class="jvp-btn-icon" onclick="jvpCloseModal('deleteModal')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="POST" action="process_client_supplier_jv_delete.php" id="deleteForm">
      <div class="jvp-modal-body">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_cs_id">

        <div id="deletePreview" style="background:var(--red-bg);border:1px solid #fca5a5;border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:#7f1d1d">
          <!-- Filled dynamically -->
        </div>

        <p style="font-size:13px;color:var(--text-muted);line-height:1.6">
          <?php echo __('this_action_will'); ?>:
          <strong style="color:var(--text-primary);display:block;margin-top:6px">· <?php echo __('return_funds_to_the_client_account'); ?></strong>
          <strong style="color:var(--text-primary);display:block">· <?php echo __('deduct_the_amount_from_the_supplier_balance'); ?></strong>
          <strong style="color:var(--text-primary);display:block">· <?php echo __('delete_all_associated_transaction_records'); ?></strong>
        </p>
      </div>
      <div class="jvp-modal-foot">
        <button type="button" class="jvp-btn jvp-btn-ghost" onclick="jvpCloseModal('deleteModal')"><?php echo __('cancel'); ?></button>
        <button type="submit" class="jvp-btn jvp-btn-danger" id="deleteSubmitBtn">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3 5h10M6 5V3.5h4V5M6 8v4M10 8v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="3" y="5" width="10" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
          <?php echo __('delete'); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT MODAL
════════════════════════════════════════════════════════ -->
<div class="jvp-backdrop" id="editModal" style="display:none" onclick="jvpBackdropClick(event,'editModal')">
  <div class="jvp-modal">
    <div class="jvp-modal-head">
      <div>
        <h2><?php echo __('edit_client_supplier_payment'); ?></h2>
        <p><?php echo __('update_payment'); ?></p>
      </div>
      <button class="jvp-btn-icon" onclick="jvpCloseModal('editModal')">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="POST" action="update_jv_payment.php" id="editForm">
      <input type="hidden" name="id" id="edit_id">
      <div class="jvp-modal-body">
        <div class="jvp-form-grid jvp-form-full" style="grid-template-columns:1fr">
          <div class="jvp-field">
            <label><?php echo __('jv_name'); ?></label>
            <input type="text" name="jv_name" id="edit_jv_name" required>
          </div>
        </div>

        <div class="jvp-section-title"><?php echo __('transaction_parties'); ?></div>
        <div class="jvp-form-grid">
          <div class="jvp-field">
            <label><?php echo __('client'); ?></label>
            <input type="text" id="edit_client_name" class="jvp-party-name" readonly style="background:#e9ecef;">
            <input type="hidden" name="client_id" id="edit_client_id">
          </div>
          <div class="jvp-field">
            <label><?php echo __('supplier'); ?></label>
            <input type="text" id="edit_supplier_name" class="jvp-party-name" readonly style="background:#e9ecef;">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <input type="hidden" name="supplier_currency" id="edit_supplier_currency">
          </div>
        </div>

        <div class="jvp-section-title"><?php echo __('amount_currency'); ?></div>
        <div class="jvp-form-grid jvp-form-three" style="grid-template-columns:1fr 1fr 1fr">
          <div class="jvp-field">
            <label>Client Balance</label>
            <select name="balance_currency" id="edit_balance_currency">
              <option value="USD">USD – US Dollar</option>
              <option value="AFS">AFS – Afghani</option>
            </select>
          </div>
          <div class="jvp-field">
            <label><?php echo __('currency'); ?></label>
            <select name="currency" id="edit_currency">
              <option value="USD">USD – US Dollar</option>
              <option value="AFS">AFS – Afghani</option>
              <option value="EUR">EUR – Euro</option>
              <option value="DARHAM">DARHAM – UAE Dirham</option>
              <option value="SAR">SAR – Saudi Riyal</option>
            </select>
          </div>
          <div class="jvp-field">
            <label><?php echo __('amount'); ?></label>
            <input type="number" step="0.01" name="total_amount" id="edit_total_amount" required placeholder="0.00">
          </div>
        </div>
        <div class="jvp-form-grid jvp-form-three" style="grid-template-columns:1fr 1fr 1fr">
          <div class="jvp-field" id="editExchangeRateField" style="display:none">
            <label>Exchange Rate (client)</label>
            <input type="number" step="0.00001" name="exchange_rate" id="edit_exchange_rate" placeholder="1.00000">
            <p class="jvp-field-hint" id="editExchangeRateHint">1 USD = X AFS, enter X</p>
          </div>
          <div class="jvp-field" id="editSupplierRateField" style="display:none">
            <label>Exchange Rate (supplier)</label>
            <input type="number" step="0.00001" name="supplier_rate" id="edit_supplier_rate" placeholder="1.00000">
            <p class="jvp-field-hint" id="editSupplierRateHint">1 USD = X AFS, enter X</p>
          </div>
        </div>

        <div class="jvp-section-title"><?php echo __('additional_details'); ?></div>
        <div class="jvp-form-grid">
          <div class="jvp-field">
            <label><?php echo __('receipt_number'); ?></label>
            <input type="text" name="receipt" id="edit_receipt" required placeholder="RCP-XXXXX">
          </div>
          <div class="jvp-field">
            <label><?php echo __('remarks'); ?></label>
            <textarea name="remarks" id="edit_remarks" placeholder="<?php echo __('optional_notes'); ?>…"></textarea>
          </div>
        </div>

        <div class="jvp-section-title">Balance Impact Preview</div>
        <div id="editBalancePreview" style="background:var(--blue-bg);border:1px solid #bfdbfe;border-radius:8px;padding:14px 16px;font-size:13px;color:#1e3a8a;line-height:1.8">
          <div><strong><?php echo __('client'); ?>:</strong> <span id="edit_client_balance_preview">—</span></div>
          <div><strong><?php echo __('supplier'); ?>:</strong> <span id="edit_supplier_balance_preview">—</span></div>
        </div>
      </div>
      <div class="jvp-modal-foot">
        <button type="button" class="jvp-btn jvp-btn-ghost" onclick="jvpCloseModal('editModal')"><?php echo __('cancel'); ?></button>
        <button type="submit" class="jvp-btn jvp-btn-primary" id="editSubmitBtn">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13 4L6.5 11 3 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <?php echo __('update_payment'); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Toast container -->
<div class="jvp-toast-wrap" id="jvpToastWrap"></div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- Required JS (kept from original) -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
/* ─── Modal helpers ──────────────────────────────────────── */
function jvpOpenModal(id) {
  document.getElementById(id).style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function jvpCloseModal(id) {
  document.getElementById(id).style.display = 'none';
  document.body.style.overflow = '';
}
function jvpBackdropClick(e, id) {
  if (e.target === document.getElementById(id)) jvpCloseModal(id);
}
document.addEventListener('keydown', e => {
   if (e.key === 'Escape') {
     ['addModal','viewModal','deleteModal','editModal'].forEach(id => {
       const el = document.getElementById(id);
       if (el && el.style.display !== 'none') jvpCloseModal(id);
     });
   }
 });

/* ─── Toast ──────────────────────────────────────────────── */
function jvpToast(msg, type = 'success') {
  const wrap = document.getElementById('jvpToastWrap');
  const t    = document.createElement('div');
  t.className = 'jvp-toast ' + type;
  const icons = {
    success: '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="white" stroke-width="1.5"/><path d="M5 8l2 2 4-4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    error:   '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="white" stroke-width="1.5"/><path d="M11 5L5 11M5 5l6 6" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg>'
  };
  t.innerHTML = (icons[type] || '') + msg;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 300ms'; setTimeout(() => t.remove(), 300); }, 2800);
}

/* ─── Button protection ──────────────────────────────────── */
function jvpProtectBtn(btn, text = 'Processing…') {
  if (!btn || btn.disabled) return;
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="animation:spin 1s linear infinite"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-dasharray="24" stroke-dashoffset="6"/></svg> ${text}`;
  setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 4000);
}

/* ─── View payment ───────────────────────────────────────── */
document.querySelectorAll('.view-cs-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id = this.dataset.id;
    jvpOpenModal('viewModal');
    document.getElementById('viewModalSubtitle').textContent = '';
    document.getElementById('viewEditBtn').style.display = 'none';
    document.getElementById('viewModalBody').innerHTML = `
      <div style="text-align:center;padding:32px 0;color:var(--text-dim)">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;display:inline-block"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="40" stroke-dashoffset="10"/></svg>
        <p style="margin-top:10px;font-size:13px">Loading…</p>
      </div>`;

    $.ajax({
      url: 'get_jv_payment.php',
      type: 'GET',
      data: { id: id, type: 'client_supplier' },
      dataType: 'json',
      success: function (res) {
        if (!res.success) {
          document.getElementById('viewModalBody').innerHTML =
            `<div class="jvp-alert error" style="margin:0"><span>${res.message}</span></div>`;
          return;
        }
        const p = res.payment;
        const createdDate = new Date(p.created_at);
        const fmtDate  = createdDate.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
        const fmtTime  = createdDate.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
        const fmtAmt   = parseFloat(p.total_amount).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 });
        const currClass = (p.currency || '').toLowerCase();

        document.getElementById('viewModalSubtitle').textContent = p.jv_name;

        document.getElementById('viewModalBody').innerHTML = `
          <div class="jvp-detail-hero">
            <div>
              <div class="label"><?php echo __('total_amount'); ?></div>
              <div class="amount">${fmtAmt}</div>
            </div>
            <span class="jvp-currency-badge ${currClass}">${$('<div>').text(p.currency).html()}</span>
          </div>
          <div class="jvp-party-grid">
            <div class="jvp-party-box">
              <div class="box-label"><?php echo __('client'); ?></div>
              <div class="box-name">${$('<div>').text(p.client_name || '—').html()}</div>
              <div class="box-sub"><?php echo __('paid_from'); ?></div>
            </div>
            <div class="jvp-party-box">
              <div class="box-label"><?php echo __('supplier'); ?></div>
              <div class="box-name">${$('<div>').text(p.supplier_name || '—').html()}</div>
              <div class="box-sub"><?php echo __('paid_to'); ?></div>
            </div>
          </div>
          <div class="jvp-detail-grid">
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('client'); ?> Balance</div><div class="dc-value">${$('<div>').text(p.balance_currency || p.currency).html()}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('currency'); ?> (paid)</div><div class="dc-value">${$('<div>').text(p.currency).html()}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('exchange_rate'); ?> (client)</div><div class="dc-value">${p.exchange_rate > 0 ? p.exchange_rate : '—'}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('exchange_rate'); ?> (supplier)</div><div class="dc-value">${p.supplier_rate > 0 ? p.supplier_rate : '—'}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('receipt'); ?></div><span class="jvp-receipt-code">${$('<div>').text(p.receipt || '—').html()}</span></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('created_by'); ?></div><div class="dc-value">${$('<div>').text(p.created_by_name || 'System').html()}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('date'); ?></div><div class="dc-value">${fmtDate}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('time'); ?></div><div class="dc-value" style="font-family:var(--font-mono)">${fmtTime}</div></div>
            <div class="jvp-detail-cell"><div class="dc-label"><?php echo __('remarks'); ?></div><div class="dc-value" style="color:var(--text-muted);font-style:italic">${p.remarks ? $('<div>').text(p.remarks).html() : '—'}</div></div>
          </div>`;

        // Store payment data for edit modal and show edit button
        window._currentPayment = p;
        const editBtn = document.getElementById('viewEditBtn');
        editBtn.style.display = 'inline-flex';
      },
      error: function () {
        document.getElementById('viewModalBody').innerHTML =
          `<div class="jvp-alert error" style="margin:0"><?php echo __('failed_to_load_details'); ?></div>`;
      }
    });
  });
});

/* ─── Edit payment ───────────────────────────────────────── */
document.getElementById('viewEditBtn').addEventListener('click', function () {
  const p = window._currentPayment;
  if (!p) return;

  document.getElementById('edit_id').value = p.id;
  document.getElementById('edit_jv_name').value = p.jv_name;
  document.getElementById('edit_client_name').value = p.client_name || '';
  document.getElementById('edit_client_id').value = p.client_id || '';
  document.getElementById('edit_supplier_name').value = p.supplier_name || '';
  document.getElementById('edit_supplier_id').value = p.supplier_id || '';
  document.getElementById('edit_total_amount').value = p.total_amount;
  document.getElementById('edit_receipt').value = p.receipt || '';
  document.getElementById('edit_remarks').value = p.remarks || '';

  populateEditCurrency(p);

  jvpCloseModal('viewModal');
  jvpOpenModal('editModal');
});

/* ─── Edit payment from row ──────────────────────────────── */
document.querySelectorAll('.edit-cs-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id = this.dataset.id;
    $.ajax({
      url: 'get_jv_payment.php',
      type: 'GET',
      data: { id: id, type: 'client_supplier' },
      dataType: 'json',
      success: function (res) {
        if (!res.success) {
          jvpToast(res.message, 'error');
          return;
        }
        const p = res.payment;
        window._currentPayment = p;

        document.getElementById('edit_id').value = p.id;
        document.getElementById('edit_jv_name').value = p.jv_name;
        document.getElementById('edit_client_name').value = p.client_name || '';
        document.getElementById('edit_client_id').value = p.client_id || '';
        document.getElementById('edit_supplier_name').value = p.supplier_name || '';
        document.getElementById('edit_supplier_id').value = p.supplier_id || '';
        document.getElementById('edit_total_amount').value = p.total_amount;
        document.getElementById('edit_receipt').value = p.receipt || '';
        document.getElementById('edit_remarks').value = p.remarks || '';

        populateEditCurrency(p);

        jvpOpenModal('editModal');
      },
      error: function () {
        jvpToast('<?php echo __('failed_to_load_details'); ?>', 'error');
      }
    });
  });
});

/* ─── Delete payment ─────────────────────────────────────── */
document.querySelectorAll('.delete-cs-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const id = this.dataset.id;
    // Try to get info from the table row
    const row    = this.closest('tr');
    const jvName = row?.querySelector('.jvp-jv-label')?.textContent?.trim() || 'Payment #' + id;
    const amount = row?.querySelector('.jvp-amount')?.textContent?.trim() || '';
    const curr   = row?.querySelector('.jvp-currency-badge')?.textContent?.trim() || '';
    const client = row?.querySelectorAll('.jvp-party-name')[0]?.textContent?.trim() || '';
    const supp   = row?.querySelectorAll('.jvp-party-name')[1]?.textContent?.trim() || '';

    document.getElementById('delete_cs_id').value = id;
    document.getElementById('deletePreview').innerHTML =
      `<strong style="display:block;margin-bottom:4px">${jvName} — ${amount} ${curr}</strong>${client} → ${supp}`;

    jvpOpenModal('deleteModal');
  });
});

/* ─── Multi-currency logic (fund-client style) ───────────── */
const CUR_INFO = {
  USD:    { sym: '$',   name: 'USD' },
  AFS:    { sym: '؋',   name: 'AFS' },
  EUR:    { sym: '€',   name: 'EUR' },
  DARHAM: { sym: 'د.إ', name: 'AED' },
  SAR:    { sym: '﷼',   name: 'SAR' },
};
const DIVIDE_PAIRS = ['AFS->AED','AFS->EUR','AFS->USD','AED->EUR','AED->USD','EUR->USD','AFS->SAR','SAR->USD','SAR->EUR'];
function curName(c) { return (CUR_INFO[c] || { name: c }).name; }

// Client credit: rate is "1 <balance> = X <payment>" (same convention as fund client)
function computeClientCredit(balance, payment, amount, rate) {
  if (balance === payment) return amount;
  if (balance === 'USD') return amount / rate;   // 1 USD = X <payment>
  if (payment === 'USD') return amount * rate;   // 1 USD = X AFS
  return amount / rate;                          // 1 AFS = X <payment>
}

// Supplier credit: rate is "1 <payment> = X <supplier currency>"
function computeSupplierCredit(payment, supplierCurr, amount, rate) {
  if (payment === supplierCurr) return amount;
  const pf = payment === 'DARHAM' ? 'AED' : payment;
  const pt = supplierCurr === 'DARHAM' ? 'AED' : supplierCurr;
  return DIVIDE_PAIRS.includes(pf + '->' + pt) ? amount / rate : amount * rate;
}

/* ─── Add modal ──────────────────────────────────────────── */
function updateAddCurrencyUI() {
  const balance  = document.getElementById('balance_currency').value;
  const payment  = document.getElementById('currency').value;
  const supplierOpt = document.getElementById('supplier_id').selectedOptions[0];
  const supplierCurr = supplierOpt ? supplierOpt.dataset.currency : '';

  const rateField1 = document.getElementById('exchangeRateField');
  const rate1 = document.getElementById('exchange_rate');
  const hint1 = document.getElementById('exchangeRateHint');
  const rateField2 = document.getElementById('supplierRateField');
  const rate2 = document.getElementById('supplier_rate');
  const hint2 = document.getElementById('supplierRateHint');

  if (balance !== payment) {
    const base   = (balance === 'AFS' && payment === 'USD') ? 'USD' : balance;
    const target = (balance === 'AFS' && payment === 'USD') ? 'AFS' : payment;
    rateField1.style.display = 'block';
    rate1.required = true;
    hint1.textContent = '1 ' + curName(base) + ' = X ' + curName(target) + ', enter X';
  } else {
    rateField1.style.display = 'none';
    rate1.required = false;
    rate1.value = '';
  }

  if (supplierCurr && payment !== supplierCurr) {
    rateField2.style.display = 'block';
    rate2.required = true;
    hint2.textContent = '1 ' + curName(payment) + ' = X ' + curName(supplierCurr) + ', enter X';
  } else {
    rateField2.style.display = 'none';
    rate2.required = false;
    rate2.value = '';
  }

  document.getElementById('addAmountHint').textContent = 'in ' + curName(payment);
  updateAddConversionPreview();
}

function updateAddConversionPreview() {
  const balance  = document.getElementById('balance_currency').value;
  const payment  = document.getElementById('currency').value;
  const supplierOpt = document.getElementById('supplier_id').selectedOptions[0];
  const supplierCurr = supplierOpt ? supplierOpt.dataset.currency : '';
  const amount = parseFloat(document.getElementById('total_amount').value) || 0;
  const r1 = parseFloat(document.getElementById('exchange_rate').value) || 0;
  const r2 = parseFloat(document.getElementById('supplier_rate').value) || 0;
  const prev = document.getElementById('addConversionPreview');

  if (!amount) { prev.style.display = 'none'; return; }

  const needR1 = balance !== payment;
  const needR2 = !!supplierCurr && payment !== supplierCurr;
  if ((needR1 && !r1) || (needR2 && !r2)) {
    prev.style.display = 'block';
    prev.textContent = 'Enter ' + [needR1 ? 'client' : null, needR2 ? 'supplier' : null].filter(Boolean).join(' and ') + ' exchange rate to preview balances.';
    return;
  }

  const credit  = computeClientCredit(balance, payment, amount, r1 || 1);
  const lines = ['<strong>Client:</strong> +' + credit.toFixed(2) + ' ' + curName(balance) + ' credited to ' + curName(balance) + ' balance'];
  if (supplierCurr) {
    const suppAmt = computeSupplierCredit(payment, supplierCurr, amount, r2 || 1);
    lines.push('<strong>Supplier:</strong> +' + suppAmt.toFixed(2) + ' ' + curName(supplierCurr) + ' received');
  } else {
    lines.push('<strong>Supplier:</strong> select a supplier to preview');
  }
  prev.style.display = 'block';
  prev.innerHTML = '<div>' + lines.join('</div><div>') + '</div>';
}

document.getElementById('balance_currency').addEventListener('change', updateAddCurrencyUI);
document.getElementById('currency').addEventListener('change', updateAddCurrencyUI);
document.getElementById('supplier_id').addEventListener('change', updateAddCurrencyUI);
document.getElementById('exchange_rate').addEventListener('input', updateAddConversionPreview);
document.getElementById('supplier_rate').addEventListener('input', updateAddConversionPreview);
document.getElementById('total_amount').addEventListener('input', updateAddConversionPreview);
document.addEventListener('DOMContentLoaded', updateAddCurrencyUI);

/* ─── Edit modal ─────────────────────────────────────────── */
function populateEditCurrency(p) {
  document.getElementById('edit_balance_currency').value = p.balance_currency || p.currency;
  document.getElementById('edit_currency').value = p.currency;
  document.getElementById('edit_exchange_rate').value = p.exchange_rate > 0 ? p.exchange_rate : '';
  document.getElementById('edit_supplier_rate').value = (p.supplier_rate > 0) ? p.supplier_rate : (p.exchange_rate > 0 ? p.exchange_rate : '');

  const supplierOpt = document.querySelector('#supplier_id option[value="' + p.supplier_id + '"]');
  const supplierCurrency = supplierOpt ? supplierOpt.dataset.currency : '';
  document.getElementById('edit_supplier_currency').value = supplierCurrency;

  updateEditCurrencyUI();
  updateEditBalancePreview();
}

function updateEditCurrencyUI() {
  const balance  = document.getElementById('edit_balance_currency').value;
  const payment  = document.getElementById('edit_currency').value;
  const supplierCurr = document.getElementById('edit_supplier_currency').value;

  const rateField1 = document.getElementById('editExchangeRateField');
  const rate1 = document.getElementById('edit_exchange_rate');
  const hint1 = document.getElementById('editExchangeRateHint');
  const rateField2 = document.getElementById('editSupplierRateField');
  const rate2 = document.getElementById('edit_supplier_rate');
  const hint2 = document.getElementById('editSupplierRateHint');

  if (balance !== payment) {
    const base   = (balance === 'AFS' && payment === 'USD') ? 'USD' : balance;
    const target = (balance === 'AFS' && payment === 'USD') ? 'AFS' : payment;
    rateField1.style.display = 'block';
    rate1.required = true;
    hint1.textContent = '1 ' + curName(base) + ' = X ' + curName(target) + ', enter X';
  } else {
    rateField1.style.display = 'none';
    rate1.required = false;
  }

  if (supplierCurr && payment !== supplierCurr) {
    rateField2.style.display = 'block';
    rate2.required = true;
    hint2.textContent = '1 ' + curName(payment) + ' = X ' + curName(supplierCurr) + ', enter X';
  } else {
    rateField2.style.display = 'none';
    rate2.required = false;
  }
}

document.getElementById('edit_total_amount').addEventListener('input', updateEditBalancePreview);
document.getElementById('edit_exchange_rate').addEventListener('input', updateEditBalancePreview);
document.getElementById('edit_supplier_rate').addEventListener('input', updateEditBalancePreview);
document.getElementById('edit_balance_currency').addEventListener('change', function () { updateEditCurrencyUI(); updateEditBalancePreview(); });
document.getElementById('edit_currency').addEventListener('change', function () { updateEditCurrencyUI(); updateEditBalancePreview(); });

function updateEditBalancePreview() {
  const p = window._currentPayment;
  if (!p) return;
  const newAmt     = parseFloat(document.getElementById('edit_total_amount').value) || 0;
  const newBalance = document.getElementById('edit_balance_currency').value;
  const newPayment = document.getElementById('edit_currency').value;
  const rate1 = parseFloat(document.getElementById('edit_exchange_rate').value) || 0;
  const rate2 = parseFloat(document.getElementById('edit_supplier_rate').value) || 0;
  const supplierCurrency = document.getElementById('edit_supplier_currency').value;

  const oldBalance = p.balance_currency || p.currency;
  const oldRate1 = parseFloat(p.exchange_rate) || 0;
  const oldRate2 = (p.supplier_rate > 0) ? parseFloat(p.supplier_rate) : (parseFloat(p.exchange_rate) || 0);

  const oldClientCredit = computeClientCredit(oldBalance, p.currency, parseFloat(p.total_amount), oldRate1 || 1);
  const newClientCredit = computeClientCredit(newBalance, newPayment, newAmt, rate1 || 1);
  const oldSupplierAmt  = computeSupplierCredit(p.currency, supplierCurrency, parseFloat(p.total_amount), oldRate2 || 1);
  const newSupplierAmt  = computeSupplierCredit(newPayment, supplierCurrency, newAmt, rate2 || 1);

  const clientDiff = newClientCredit - oldClientCredit;
  const supplierDiff = newSupplierAmt - oldSupplierAmt;

  const sign = d => (d > 0 ? '+' : '') + d.toFixed(2);
  document.getElementById('edit_client_balance_preview').textContent =
    (clientDiff !== 0 ? sign(clientDiff) + ' ' + newBalance + ' (was ' + oldBalance + ')' : 'No change');
  document.getElementById('edit_supplier_balance_preview').textContent =
    (supplierDiff !== 0 ? sign(supplierDiff) + ' ' + (supplierCurrency || newPayment) : 'No change');
}

/* ─── Form validation ────────────────────────────────────── */
document.getElementById('clientSupplierForm').addEventListener('submit', function (e) {
  const clientId     = document.getElementById('client_id').value;
  const supplierId   = document.getElementById('supplier_id').value;
  const amount       = parseFloat(document.getElementById('total_amount').value);
  const balanceCurr  = document.getElementById('balance_currency').value;
  const currency     = document.getElementById('currency').value;
  const rate1        = parseFloat(document.getElementById('exchange_rate').value);
  const rate2        = parseFloat(document.getElementById('supplier_rate').value);
  const supplierOpt  = document.getElementById('supplier_id').selectedOptions[0];
  const supplierCurr = supplierOpt?.dataset?.currency;

  if (!clientId || !supplierId) {
    e.preventDefault();
    jvpToast('<?php echo __('please_select_both_client_and_supplier'); ?>', 'error');
    return;
  }
  if (isNaN(amount) || amount <= 0) {
    e.preventDefault();
    jvpToast('<?php echo __('please_enter_a_valid_amount_greater_than_zero'); ?>', 'error');
    return;
  }
  if (balanceCurr !== currency && (isNaN(rate1) || rate1 <= 0)) {
    e.preventDefault();
    jvpToast('<?php echo __('please_enter_a_valid_exchange_rate_for_currency_conversion'); ?>', 'error');
    return;
  }
  if (supplierCurr && supplierCurr !== currency && (isNaN(rate2) || rate2 <= 0)) {
    e.preventDefault();
    jvpToast('<?php echo __('please_enter_a_valid_exchange_rate_for_currency_conversion'); ?>', 'error');
    return;
  }
  jvpProtectBtn(document.getElementById('addSubmitBtn'), 'Processing…');
});

document.getElementById('deleteForm').addEventListener('submit', function () {
  jvpProtectBtn(document.getElementById('deleteSubmitBtn'), 'Deleting…');
});

document.getElementById('editForm').addEventListener('submit', function (e) {
  const amount = parseFloat(document.getElementById('edit_total_amount').value);
  const balanceCurr = document.getElementById('edit_balance_currency').value;
  const currency = document.getElementById('edit_currency').value;
  const rate1 = parseFloat(document.getElementById('edit_exchange_rate').value);
  const rate2 = parseFloat(document.getElementById('edit_supplier_rate').value);
  const supplierCurrency = document.getElementById('edit_supplier_currency').value;

  if (isNaN(amount) || amount <= 0) {
    e.preventDefault();
    jvpToast('<?php echo __('please_enter_a_valid_amount_greater_than_zero'); ?>', 'error');
    return;
  }
  if (balanceCurr !== currency && (isNaN(rate1) || rate1 <= 0)) {
    e.preventDefault();
    jvpToast('<?php echo __('please_enter_a_valid_exchange_rate_for_currency_conversion'); ?>', 'error');
    return;
  }
  if (supplierCurrency && supplierCurrency !== currency && (isNaN(rate2) || rate2 <= 0)) {
    e.preventDefault();
    jvpToast('<?php echo __('please_enter_a_valid_exchange_rate_for_currency_conversion'); ?>', 'error');
    return;
  }
  jvpProtectBtn(document.getElementById('editSubmitBtn'), 'Updating…');
});
</script>

</body>
</html>