<?php
/**
 * Payments Journal — unified payment register for the agency.
 * Phase 1: read-only unified view + action hub (deep links into existing flows).
 * Roles: admin, finance.
 */

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

require_permission('finance.payments');
require_once '../includes/db.php';

/* ── Default date range: current month ── */
$today       = date('Y-m-d');
$firstOfMonth = date('Y-m-01');
$from_date   = isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date']) ? $_GET['from_date'] : $firstOfMonth;
$to_date     = isset($_GET['to_date'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])   ? $_GET['to_date']   : $today;
$sel_module  = isset($_GET['module'])  ? trim($_GET['module'])  : '';
$sel_currency= isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : '';
$sel_ledger  = isset($_GET['ledger'])  ? trim($_GET['ledger'])  : '';
$search_q    = isset($_GET['search'])  ? trim($_GET['search'])  : '';

/* ── Module list for the filter dropdown ── */
$module_list = [
    'ticket_sale' => 'Ticket Sale', 'ticket_refund' => 'Ticket Refund',
    'ticket_reserve' => 'Ticket Reserve', 'date_change' => 'Date Change',
    'weight' => 'Ticket Weight', 'visa_sale' => 'Visa Sale',
    'visa_refund' => 'Visa Refund', 'hotel' => 'Hotel',
    'hotel_refund' => 'Hotel Refund', 'umrah' => 'Umrah',
    'umrah_transaction' => 'Umrah Payment', 'umrah_refund' => 'Umrah Refund',
    'additional_payment' => 'Additional Payment', 'jv_payment' => 'JV Payment',
    'fund' => 'Fund', 'client_fund' => 'Client Fund',
    'supplier_fund' => 'Supplier Fund', 'supplier_fund_withdrawal' => 'Supplier Fund Withdrawal',
    'withdraw_fund' => 'Withdraw Fund', 'transfer' => 'Transfer',
    'expense' => 'Expense', 'debtor' => 'Debtor', 'creditor' => 'Creditor',
    'salary_payment' => 'Salary Payment', 'deposit_sarafi' => 'Sarafi Deposit',
    'hawala_sarafi' => 'Sarafi Hawala', 'withdrawal_sarafi' => 'Sarafi Withdrawal',
    'budget_allocation' => 'Budget Allocation', 'global_budget_allocation' => 'Global Budget Allocation',
];
?>
<?php include '../includes/header.php'; ?>
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
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
  --teal:        #0d9488;
  --teal-bg:     #f0fdfa;
  --amber:       #b45309;
  --amber-bg:    #fffbeb;
  --blue:        #1d4ed8;
  --blue-bg:     #eff6ff;
  --red:         #dc2626;
  --red-bg:      #fef2f2;
  --violet:      #7c3aed;
  --violet-bg:   #f5f3ff;
  --radius:      6px;
  --radius-lg:   10px;
  --shadow:      0 1px 4px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.04);
  --font-sans:   'DM Sans', sans-serif;
  --font-mono:   'Geist Mono', monospace;
  --t:           150ms cubic-bezier(0.16, 1, 0.3, 1);
}

.pjl-wrap {
  font-family: var(--font-sans);
  color: var(--text-primary);
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  padding: 28px;
}

/* ─── Page header ──────────────────────────────────────── */
.pjl-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}
.pjl-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--text-muted);
  margin-bottom: 5px;
  font-family: var(--font-mono);
  letter-spacing: .02em;
}
.pjl-title {
  font-size: 21px;
  font-weight: 600;
  letter-spacing: -.4px;
  line-height: 1.2;
  margin: 0;
}
.pjl-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
.pjl-head-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-top: 2px; flex-wrap: wrap; }

/* ─── Buttons ──────────────────────────────────────────── */
.pjl-btn {
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
.pjl-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.pjl-btn-primary { background: var(--text-primary) !important; color: #fff !important; border-color: var(--text-primary) !important; }
.pjl-btn-primary:hover { background: #2d2d2b !important; color: #fff !important; }
.pjl-btn-ghost { background: transparent !important; color: var(--text-muted) !important; border-color: var(--border) !important; }
.pjl-btn-ghost:hover { background: var(--accent-soft) !important; color: var(--text-primary) !important; border-color: #d0d0cd !important; }
.pjl-btn-icon {
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
.pjl-btn-icon:hover { background: var(--accent-soft); color: var(--text-primary); border-color: var(--border); }

/* ─── KPI strip ────────────────────────────────────────── */
.pjl-kpi-strip {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 10px;
  margin-bottom: 18px;
}
.pjl-kpi {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow);
  padding: 13px 16px;
}
.pjl-kpi .pjl-kpi-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text-dim);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.pjl-kpi .pjl-kpi-value {
  font-family: var(--font-mono);
  font-size: 17px;
  font-weight: 500;
  letter-spacing: -.3px;
}
.pjl-kpi .pjl-kpi-sub { font-size: 11px; color: var(--text-dim); margin-top: 2px; }
.pjl-kpi.in .pjl-kpi-value { color: var(--green); }
.pjl-kpi.out .pjl-kpi-value { color: var(--red); }
.pjl-kpi.net .pjl-kpi-value { color: var(--blue); }
.pjl-kpi-cur {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.pjl-kpi-cur-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  margin-bottom: 6px;
}
.pjl-kpi-cur-name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.pjl-kpi-cur-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-family: var(--font-mono);
  font-size: 12.5px;
}
.pjl-kpi-cur-row .in  { color: var(--green); }
.pjl-kpi-cur-row .out { color: var(--red); }
.pjl-kpi-cur-net {
  font-family: var(--font-mono);
  font-size: 12.5px;
  font-weight: 600;
  margin-top: 5px;
  padding-top: 5px;
  border-top: 1px dashed var(--border);
  color: var(--blue);
}
.pjl-kpi-cur-net.pos { color: var(--green); }
.pjl-kpi-cur-net.neg { color: var(--red); }

/* ─── Toolbar / filters ────────────────────────────────── */
.pjl-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  flex-wrap: wrap;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 10px 12px;
  box-shadow: var(--shadow);
}
.pjl-field {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.pjl-field label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--text-dim);
}
.pjl-field input,
.pjl-field select {
  padding: 6px 9px;
  font-family: var(--font-sans);
  font-size: 13px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--text-primary);
  transition: border var(--t), box-shadow var(--t);
  min-height: 32px;
}
.pjl-field input:focus,
.pjl-field select:focus { outline: none; border-color: #aaa; box-shadow: 0 0 0 3px rgba(26,26,24,.06); }
.pjl-search-wrap { position: relative; flex: 1; min-width: 220px; }
.pjl-search-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-dim); pointer-events: none; }
.pjl-search-wrap input {
  width: 100%;
  padding: 6px 10px 6px 32px;
  font-family: var(--font-sans);
  font-size: 13px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--text-primary);
}
.pjl-search-wrap input:focus { outline: none; border-color: #aaa; box-shadow: 0 0 0 3px rgba(26,26,24,.06); }
.pjl-presets { display: flex; align-items: center; gap: 4px; }
.pjl-preset {
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 500;
  border-radius: 5px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-muted);
  cursor: pointer;
  transition: all var(--t);
}
.pjl-preset:hover { background: var(--accent-soft); color: var(--text-primary); }
.pjl-preset.active { background: var(--text-primary); color: #fff; border-color: var(--text-primary); }

/* ─── Ledger tabs ──────────────────────────────────────── */
.pjl-ledger-tabs {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 14px 0 12px;
  padding: 4px;
  background: var(--border-soft);
  border: 1px solid var(--border);
  border-radius: 9px;
  width: fit-content;
  max-width: 100%;
  overflow-x: auto;
}
.pjl-ltab {
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 600;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  white-space: nowrap;
  transition: all var(--t);
}
.pjl-ltab:hover { color: var(--text-primary); }
.pjl-ltab.active {
  background: var(--surface);
  color: var(--text-primary);
  border-color: var(--border);
  box-shadow: 0 1px 2px rgba(0,0,0,.06);
}
.pjl-ltab .cnt { font-weight: 400; opacity: .65; margin-left: 4px; }
.pjl-ltab.active .cnt { opacity: .55; }
.pjl-entity-select {
  margin-left: 4px;
  padding: 6px 10px;
  font-size: 12.5px;
  font-weight: 500;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-primary);
  cursor: pointer;
  max-width: 220px;
  min-width: 150px;
}
.pjl-entity-select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

/* ─── Table ────────────────────────────────────────────── */
.pjl-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.pjl-table-wrap { overflow-x: auto; }
.pjl-table { width: 100%; border-collapse: collapse; font-family: var(--font-sans); }
.pjl-table thead tr { background: var(--border-soft); border-bottom: 1px solid var(--border); }
.pjl-table th {
  padding: 9px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  white-space: nowrap;
}
.pjl-table th.right, .pjl-table td.right { text-align: right; }
.pjl-table th.center, .pjl-table td.center { text-align: center; }
.pjl-table tbody tr { border-bottom: 1px solid var(--border-soft); transition: background var(--t); }
.pjl-table tbody tr:last-child { border-bottom: none; }
.pjl-table tbody tr:hover { background: var(--border-soft); }
.pjl-table td { padding: 10px 12px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; }

.pjl-date-main { font-size: 12.5px; font-weight: 500; }
.pjl-date-sub { font-size: 11px; color: var(--text-dim); font-family: var(--font-mono); margin-top: 1px; }

.pjl-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 20px;
  letter-spacing: .03em;
  text-transform: uppercase;
}
.pjl-badge.credit { background: var(--green-bg); color: var(--green); border: 1px solid #bbf7d0; }
.pjl-badge.debit  { background: var(--red-bg);   color: var(--red);   border: 1px solid #fca5a5; }

.pjl-amount { font-family: var(--font-mono); font-size: 13px; font-weight: 500; }
.pjl-amount.credit { color: var(--green); }
.pjl-amount.debit  { color: var(--red); }
.pjl-base { font-family: var(--font-mono); font-size: 11.5px; color: var(--text-dim); margin-top: 1px; }

.pjl-currency-badge {
  display: inline-block;
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 3px;
  letter-spacing: .04em;
}
.pjl-currency-badge.usd { background: var(--blue-bg); color: var(--blue); border: 1px solid #bfdbfe; }
.pjl-currency-badge.afs { background: var(--amber-bg); color: var(--amber); border: 1px solid #fde68a; }
.pjl-currency-badge.eur { background: var(--violet-bg); color: var(--violet); border: 1px solid #ddd6fe; }
.pjl-currency-badge.darham { background: var(--green-bg); color: var(--green); border: 1px solid #bbf7d0; }
.pjl-currency-badge.sar { background: var(--teal-bg); color: var(--teal); border: 1px solid #99f6e4; }

.pjl-ledger-tag {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  padding: 2px 7px;
  border-radius: 4px;
  background: var(--accent-soft);
  color: var(--text-muted);
  border: 1px solid var(--border);
}
.pjl-ledger-tag.client { background: var(--blue-bg); color: var(--blue); border-color: #bfdbfe; }
.pjl-ledger-tag.supplier { background: var(--amber-bg); color: var(--amber); border-color: #fde68a; }
.pjl-ledger-tag.jv { background: var(--violet-bg); color: var(--violet); border-color: #ddd6fe; }

.pjl-name { font-size: 13px; font-weight: 500; }
.pjl-name-sub { font-size: 11px; color: var(--text-dim); }
.pjl-module { font-size: 12px; font-weight: 500; color: var(--text-primary); }
.pjl-subcat {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 600;
  padding: 1px 8px;
  border-radius: 20px;
  margin-top: 3px;
  background: var(--blue-bg);
  color: var(--blue);
  border: 1px solid #bfdbfe;
}
.pjl-receipt-code {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--text-muted);
  background: var(--border-soft);
  padding: 2px 7px;
  border-radius: 3px;
  border: 1px solid var(--border);
}
.pjl-drill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--blue);
  text-decoration: none !important;
}
.pjl-drill:hover { text-decoration: underline !important; }
.pjl-desc { font-size: 12px; color: var(--text-muted); max-width: 320px; white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; line-height: 1.45; }

/* ─── Table footer / pagination ────────────────────────── */
.pjl-table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  background: var(--border-soft);
  flex-wrap: wrap;
  gap: 10px;
}
.pjl-table-count { font-size: 12px; color: var(--text-muted); }
.pjl-table-count strong { color: var(--text-primary); }
.pjl-pagination { display: flex; align-items: center; gap: 3px; }
.pjl-page-btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 30px; height: 30px; padding: 0 6px;
  border-radius: var(--radius); border: 1px solid transparent;
  font-size: 12.5px; font-family: var(--font-sans); font-weight: 500;
  cursor: pointer; color: var(--text-muted); background: transparent;
  transition: all var(--t); text-decoration: none;
}
.pjl-page-btn:hover { background: var(--surface); border-color: var(--border); color: var(--text-primary); }
.pjl-page-btn.active { background: var(--text-primary); color: #fff; border-color: var(--text-primary); }
.pjl-page-btn.disabled, .pjl-page-btn[disabled] { opacity: .35; cursor: default; pointer-events: none; }
.pjl-page-ellipsis { color: var(--text-dim); font-size: 13px; padding: 0 2px; }

/* ─── Empty / loading ──────────────────────────────────── */
.pjl-empty { padding: 56px 24px; text-align: center; }
.pjl-empty-icon {
  width: 44px; height: 44px;
  background: var(--border-soft); border: 1px solid var(--border); border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 14px; color: var(--text-dim);
}
.pjl-empty-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.pjl-empty-sub { font-size: 13px; color: var(--text-muted); }
.pjl-loading-row td { text-align: center; padding: 44px 0; color: var(--text-dim); }

/* ─── Modals ───────────────────────────────────────────── */
.pjl-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.3); backdrop-filter: blur(2px);
  z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}
.pjl-modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,.15), 0 0 0 1px rgba(0,0,0,.06);
  width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto;
  animation: pjlModalUp 200ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.pjl-modal-lg { max-width: 720px; }
@keyframes pjlModalUp { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.pjl-modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 22px 16px; border-bottom: 1px solid var(--border);
}
.pjl-modal-head h2 { font-size: 15px; font-weight: 600; letter-spacing: -.2px; margin: 0; }
.pjl-modal-head p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.pjl-modal-body { padding: 20px 22px; }
.pjl-modal-foot {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 8px; padding: 14px 22px;
  border-top: 1px solid var(--border); background: var(--border-soft);
}

/* ─── Action hub ───────────────────────────────────────── */
.pjl-section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--text-dim);
  margin: 0 0 10px;
}
.pjl-hub-btn {
  width: 100%;
  text-align: left;
  font-family: var(--font-sans);
  cursor: pointer;
}
.pjl-hub-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.pjl-hub-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  text-decoration: none !important;
  color: var(--text-primary);
  transition: all var(--t);
}
.pjl-hub-card:hover { border-color: #c8c8c4; box-shadow: var(--shadow); transform: translateY(-1px); }
.pjl-hub-card strong { display: block; font-size: 13px; font-weight: 600; }
.pjl-hub-card small { display: block; font-size: 11.5px; color: var(--text-muted); margin-top: 2px; line-height: 1.45; }
.pjl-hub-icon {
  width: 30px; height: 30px;
  flex-shrink: 0;
  border-radius: 7px;
  display: inline-flex; align-items: center; justify-content: center;
}
.pjl-hub-icon.ticket     { background: var(--blue-bg);    color: var(--blue); }
.pjl-hub-icon.visa       { background: var(--violet-bg);  color: var(--violet); }
.pjl-hub-icon.umrah      { background: var(--green-bg);   color: var(--green); }
.pjl-hub-icon.hotel      { background: var(--amber-bg);   color: var(--amber); }
.pjl-hub-icon.additional { background: var(--green-bg);   color: var(--green); }
.pjl-hub-icon.jv         { background: var(--violet-bg);  color: var(--violet); }
.pjl-hub-icon.fund       { background: var(--blue-bg);    color: var(--blue); }
.pjl-hub-icon.transfer   { background: var(--amber-bg);   color: var(--amber); }
.pjl-hub-icon.expense    { background: var(--red-bg);     color: var(--red); }
.pjl-hub-icon.salary     { background: var(--green-bg);   color: var(--green); }
.pjl-hub-icon.sarafi     { background: var(--violet-bg);  color: var(--violet); }
.pjl-hub-icon.refund     { background: var(--amber-bg);   color: var(--amber); }

/* ─── Picker (step 2) ──────────────────────────────────── */
.pjl-picker-search { margin-bottom: 12px; }
.pjl-picker-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 300px;
  overflow-y: auto;
  margin-bottom: 14px;
  padding-right: 2px;
}
.pjl-picker-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 13px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  cursor: pointer;
  transition: all var(--t);
  width: 100%;
  text-align: left;
  font-family: var(--font-sans);
}
.pjl-picker-item:hover { border-color: #c8c8c4; background: var(--accent-soft); }
.pjl-picker-item.selected { border-color: var(--text-primary); background: var(--accent-soft); box-shadow: 0 0 0 1px var(--text-primary); }
.pjl-picker-item strong { display: block; font-size: 13px; font-weight: 600; }
.pjl-picker-item small { display: block; font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
.pjl-picker-item .pjl-picker-amt { font-family: var(--font-mono); font-size: 12.5px; color: var(--text-primary); white-space: nowrap; }
.pjl-picker-item .pjl-picker-amt small { font-family: var(--font-mono); color: var(--text-dim); display: block; text-align: right; }
.pjl-picker-empty { text-align: center; color: var(--text-dim); font-size: 13px; padding: 28px 0; }
.pjl-picker-hint { font-size: 11.5px; color: var(--text-dim); text-align: center; padding: 6px 0 2px; }

/* Payment form (step 2) */
.pjl-form { border-top: 1px solid var(--border); padding-top: 16px; margin-top: 4px; }
.pjl-form .pjl-form-title {
  font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
  color: var(--text-muted); margin: 0 0 12px; display: flex; align-items: center; gap: 8px;
}
.pjl-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.pjl-form-grid .pjl-field.full { grid-column: 1 / -1; }
.pjl-form-grid .pjl-field.third { grid-column: span 1; }
.pjl-form-grid input,
.pjl-form-grid select,
.pjl-form-grid textarea {
  width: 100%;
  padding: 7px 10px;
  font-family: var(--font-sans);
  font-size: 13px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--text-primary);
  min-height: 34px;
}
.pjl-form-grid textarea { resize: vertical; min-height: 58px; }
.pjl-form-grid input:focus,
.pjl-form-grid select:focus,
.pjl-form-grid textarea:focus { outline: none; border-color: #aaa; box-shadow: 0 0 0 3px rgba(26,26,24,.06); }
.pjl-form-grid select[disabled] { background: var(--border-soft); color: var(--text-muted); cursor: not-allowed; }
@media (max-width: 640px) { .pjl-form-grid { grid-template-columns: 1fr; } .pjl-form-grid .pjl-field.full { grid-column: 1; } }

/* ─── Toast ────────────────────────────────────────────── */
.pjl-toast-wrap { position: fixed; top: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
.pjl-toast {
  display: flex; align-items: center; gap: 10px;
  background: var(--text-primary); color: #fff;
  padding: 11px 16px; border-radius: 8px; font-size: 13px;
  box-shadow: 0 4px 20px rgba(0,0,0,.25);
  font-family: var(--font-sans);
  animation: pjlToastIn 250ms cubic-bezier(0.16,1,0.3,1) both;
}
.pjl-toast.error { background: var(--red); }
@keyframes pjlToastIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

/* ─── Responsive ───────────────────────────────────────── */
@media (max-width: 1200px) {
  .pjl-kpi-strip { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 900px) {
  .pjl-kpi-strip { grid-template-columns: repeat(2, 1fr); }
  .pjl-hub-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .pjl-wrap { padding: 16px; }
  .pjl-page-head { flex-direction: column; }
  .pjl-kpi-strip { grid-template-columns: 1fr; }
  .pjl-hub-grid { grid-template-columns: 1fr; }
  .pjl-table-footer { flex-direction: column; align-items: flex-start; }
}
</style>

<style id="pjlPrintStyle">
@media print {
  @page { size: A4 landscape; margin: 12mm; }
  body * { visibility: hidden !important; }
  .pjl-table-card, .pjl-table-card * { visibility: visible !important; }
  .pjl-table-card {
    position: fixed; inset: 0; width: 100%; height: auto;
    margin: 0; padding: 0; border: none; box-shadow: none; border-radius: 0;
    overflow: visible;
  }
  .pjl-table-wrap { overflow: visible; }
  .pjl-table { font-size: 11px; }
  .pjl-table th, .pjl-table td { padding: 5px 6px; white-space: nowrap; }
  .pjl-table-footer { display: none !important; }
  .pjl-badge { font-size: 9px; padding: 1px 6px; }
  .pjl-amount { font-size: 11px; }
  .pjl-currency-badge { font-size: 9px; padding: 1px 5px; }
  .pjl-ledger-tag { font-size: 9px; }
  .pjl-desc { max-width: 200px; font-size: 10px; white-space: normal; word-wrap: break-word; overflow-wrap: break-word; }
}
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
      <div class="page-wrapper">
        <div class="pjl-wrap">

          <!-- ── Page Header ────────────────────────────── -->
          <div class="pjl-page-head">
            <div>
              <div class="pjl-breadcrumb">
                <span><?php echo __('finance_accounting'); ?></span>
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><?php echo __('payments_journal'); ?></span>
              </div>
              <h1 class="pjl-title"><?php echo __('payments_journal'); ?></h1>
              <p class="pjl-subtitle"><?php echo __('payments_journal_subtitle'); ?></p>
            </div>
            <div class="pjl-head-right">
              <button class="pjl-btn pjl-btn-ghost" onclick="window.location.reload()">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13.5 8a5.5 5.5 0 1 1-1.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 2l2.5 2.5L10 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php echo __('refresh'); ?>
              </button>
              <button class="pjl-btn pjl-btn-ghost" id="pjlExportBtn">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 10V3M5.5 5.5L8 3l2.5 2.5M3.5 10.5v2h9v-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php echo __('export_csv'); ?>
              </button>
              <button class="pjl-btn pjl-btn-ghost" id="pjlPrintBtn">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M4 6V2h8v4M4 12H2.5A1.5 1.5 0 0 1 1 10.5v-3A1.5 1.5 0 0 1 2.5 6h11A1.5 1.5 0 0 1 15 7.5v3a1.5 1.5 0 0 1-1.5 1.5H12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="10" width="8" height="4" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>
                <?php echo __('print'); ?>
              </button>
              <button class="pjl-btn pjl-btn-primary" onclick="pjlOpenModal('pjlRecordModal')">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <?php echo __('record_payment'); ?>
              </button>
            </div>
          </div>

          <!-- ── KPI strip (per currency) ─────────────────── -->
          <div class="pjl-kpi-strip" id="pjlKpiStrip">
            <div class="pjl-kpi">
              <div class="pjl-kpi-label"><?php echo __('entries'); ?></div>
              <div class="pjl-kpi-value" id="pjlKpiCount">—</div>
              <div class="pjl-kpi-sub" id="pjlKpiCountSub"></div>
            </div>
          </div>

          <!-- ── Filter toolbar ─────────────────────────── -->
          <form class="pjl-toolbar" id="pjlFilterForm" method="GET">
            <div class="pjl-field">
              <label><?php echo __('from'); ?></label>
              <input type="date" name="from_date" id="pjlFromDate" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>
            <div class="pjl-field">
              <label><?php echo __('to'); ?></label>
              <input type="date" name="to_date" id="pjlToDate" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <div class="pjl-field">
              <label><?php echo __('module'); ?></label>
              <select name="module" id="pjlModule">
                <option value=""><?php echo __('all_types'); ?></option>
                <?php foreach ($module_list as $code => $label): ?>
                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $sel_module === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="pjl-field">
              <label><?php echo __('currency'); ?></label>
              <select name="currency" id="pjlCurrency">
                <option value=""><?php echo __('all_currencies'); ?></option>
                <?php foreach (['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'] as $c): ?>
                <option value="<?php echo $c; ?>" <?php echo $sel_currency === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="pjl-field">
              <label><?php echo __('ledger'); ?></label>
              <select name="ledger" id="pjlLedger">
                <option value="main_account" <?php echo $sel_ledger === '' || $sel_ledger === 'main_account' ? 'selected' : ''; ?>><?php echo __('all_main_accounts'); ?></option>
                <option value="client" <?php echo $sel_ledger === 'client' ? 'selected' : ''; ?>><?php echo __('all_clients'); ?></option>
                <option value="supplier" <?php echo $sel_ledger === 'supplier' ? 'selected' : ''; ?>><?php echo __('all_suppliers'); ?></option>
                <option value="jv" <?php echo $sel_ledger === 'jv' ? 'selected' : ''; ?>><?php echo __('jv_payments'); ?></option>
              </select>
            </div>
            <div class="pjl-search-wrap">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              <input type="text" name="search" id="pjlSearch" placeholder="<?php echo __('search_journal_placeholder'); ?>…" value="<?php echo htmlspecialchars($search_q); ?>">
            </div>
            <button type="submit" class="pjl-btn pjl-btn-ghost" style="margin-top:17px">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11 11l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              <?php echo __('apply_filters'); ?>
            </button>
            <button type="button" class="pjl-btn pjl-btn-ghost" id="pjlClearBtn" style="margin-top:17px">
              <?php echo __('clear'); ?>
            </button>
            <div class="pjl-presets" style="margin-left:auto;margin-top:17px">
              <button type="button" class="pjl-preset" data-preset="today"><?php echo __('today'); ?></button>
              <button type="button" class="pjl-preset" data-preset="week"><?php echo __('this_week'); ?></button>
              <button type="button" class="pjl-preset active" data-preset="month"><?php echo __('this_month'); ?></button>
              <button type="button" class="pjl-preset" data-preset="all"><?php echo __('all_time'); ?></button>
            </div>
          </form>

          <!-- ── Ledger tabs ─────────────────────────────── -->
          <div class="pjl-ledger-tabs" id="pjlLedgerTabs" role="tablist">
            <button type="button" class="pjl-ltab <?php echo ($sel_ledger === 'main_account' || $sel_ledger === '') ? 'active' : ''; ?>" data-ledger="main_account" data-all-label="<?php echo __('all_main_accounts'); ?>"><?php echo __('main_account'); ?></button>
            <button type="button" class="pjl-ltab <?php echo $sel_ledger === 'client' ? 'active' : ''; ?>" data-ledger="client" data-all-label="<?php echo __('all_clients'); ?>"><?php echo __('client'); ?></button>
            <button type="button" class="pjl-ltab <?php echo $sel_ledger === 'supplier' ? 'active' : ''; ?>" data-ledger="supplier" data-all-label="<?php echo __('all_suppliers'); ?>"><?php echo __('supplier'); ?></button>
            <button type="button" class="pjl-ltab <?php echo $sel_ledger === 'jv' ? 'active' : ''; ?>" data-ledger="jv"><?php echo __('jv_payments'); ?></button>
            <select class="pjl-entity-select" id="pjlEntity" name="entity">
              <option value=""><?php echo $sel_ledger === 'main_account' || $sel_ledger === '' ? __('all_main_accounts') : ($sel_ledger === 'client' ? __('all_clients') : __('all_suppliers')); ?></option>
            </select>
          </div>

          <!-- ── Table ──────────────────────────────────── -->
          <div class="pjl-table-card">
            <div class="pjl-table-wrap">
              <table class="pjl-table">
                <thead>
                  <tr>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('type'); ?></th>
                    <th class="right"><?php echo __('amount'); ?></th>
                    <th class="center"><?php echo __('currency'); ?></th>
                    <th><?php echo __('ledger'); ?></th>
                    <th><?php echo __('account_party'); ?></th>
                    <th><?php echo __('module'); ?></th>
                    <th><?php echo __('receipt'); ?></th>
                    <th><?php echo __('reference'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th class="right"><?php echo __('balance'); ?></th>
                  </tr>
                </thead>
                <tbody id="pjlTbody">
                  <tr class="pjl-loading-row"><td colspan="11"><?php echo __('loading_entries'); ?>…</td></tr>
                </tbody>
              </table>
            </div>

            <div class="pjl-table-footer">
              <p class="pjl-table-count" id="pjlCount">
                <?php echo __('loading'); ?>…
              </p>
              <div class="pjl-pagination" id="pjlPagination"></div>
            </div>
          </div>

        </div><!-- /pjl-wrap -->
      </div>
    </div>
  </div>
</div>

<!-- ── Record Payment (Action Hub) modal ────────────────── -->
<?php include '../modals/journal/record_payment_modal.php'; ?>

<!-- Mount point for the reused per-module transaction modals -->
<div id="pjlModuleMount"></div>

<!-- Toast container -->
<div class="pjl-toast-wrap" id="pjlToastWrap"></div>

<style>@keyframes spin { to { transform: rotate(360deg); } }</style>

<!-- Required JS (kept from original template) -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<!-- Journal logic -->
<script>
const PJL = {
  baseUrl: '../api/journal/get_journal_entries.php',
  page: 1,
  perPage: 25,
};
const PJL_CSRF = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

/* ─── Modal helpers ─────────────────────────────────────── */
function pjlOpenModal(id) {
  if (id === 'pjlRecordModal') {
    PJL_FLOW.module = null;
    document.getElementById('pjlPickerView').style.display = 'none';
    document.getElementById('pjlPickerHead').style.display = 'none';
    document.getElementById('pjlPickerFoot').style.display = 'none';
    document.getElementById('pjlHubView').style.display = 'block';
    document.getElementById('pjlHubHead').style.display = 'block';
    document.getElementById('pjlHubFoot').style.display = 'flex';
  }
  document.getElementById(id).style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function pjlCloseModal(id) {
  document.getElementById(id).style.display = 'none';
  document.body.style.overflow = '';
}
function pjlBackdropClick(e, id) {
  if (e.target === document.getElementById(id)) pjlCloseModal(id);
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const el = document.getElementById('pjlRecordModal');
    if (el && el.style.display !== 'none') pjlCloseModal('pjlRecordModal');
  }
});

/* ─── Toast ─────────────────────────────────────────────── */
function pjlToast(msg, type) {
  const wrap = document.getElementById('pjlToastWrap');
  const t = document.createElement('div');
  t.className = 'pjl-toast' + (type === 'error' ? ' error' : '');
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 300ms'; setTimeout(() => t.remove(), 300); }, 2800);
}

/* ─── Escape HTML ───────────────────────────────────────── */
function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function fmtNum(n, digits) {
  if (n === null || n === undefined || isNaN(n)) return '—';
  return Number(n).toLocaleString(undefined, { minimumFractionDigits: digits || 2, maximumFractionDigits: digits || 2 });
}
function fmtDate(dt) {
  const d = new Date(dt.replace(' ', 'T'));
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
function fmtTime(dt) {
  const d = new Date(dt.replace(' ', 'T'));
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

/* ─── Record Payment flow ───────────────────────────────── */
const PJL_I18N = {
  select_entity: <?php echo json_encode(__('select_entity')); ?>,
  type_to_search: <?php echo json_encode(__('type_to_search')); ?>,
  no_entities_found: <?php echo json_encode(__('no_entities_found')); ?>,
};

const PJL_FLOW = {
  module: null,
  qTimer: null,
};

/* Real module modals — opened in place on this page.
   - picker   : entity-picker type shown before opening (null = open directly)
   - url      : api/journal/modal_render.php -> renders the module's real modal
   - selector : the modal element id inside the rendered HTML
   - scripts  : JS files the module's transaction manager needs
   - open     : snippet run after the scripts execute; E = picked entity (or null) */
const PJL_MODAL = {
  ticket: {
    picker: 'ticket',
    url: '../api/journal/modal_render.php?modal=ticket',
    selector: '#transactionsModal',
    scripts: ['../js/ticket/toast.js', '../js/ticket/transaction-manager.js?v=' + Date.now()],
    open: 'manageTransactions(E ? E.id : null);',
  },
  visa: {
    picker: 'visa',
    url: '../api/journal/modal_render.php?modal=visa',
    selector: '#transactionModal',
    scripts: ['../js/visa/toast.js', '../js/visa/transaction_manager.js?v=' + Date.now()],
    open: 'openTransactionTab(E.id, E.meta.amount, E.meta.currency);',
  },
  umrah: {
    picker: 'umrah',
    url: '../api/journal/modal_render.php?modal=umrah',
    selector: '#transactionModal',
    scripts: ['../js/ticket/toast.js', '../js/umrah/transaction_manager.js?v=' + Date.now()],
    open: 'window.csrfToken = CSRF; openTransactionTab(E.id, E.meta.amount);',
  },
  umrah_refund: {
    picker: 'umrah_refund',
    url: '../api/journal/modal_render.php?modal=umrah_refund',
    selector: '#refundTransactionModal',
    scripts: ['../js/umrah_refund/transaction_manager.js', '../js/umrah_refund/umrah_management.js'],
    open: 'processRefundTransaction(E.id);',
  },
  hotel: {
    picker: 'hotel',
    url: '../api/journal/modal_render.php?modal=hotel',
    selector: '#transactionsModal',
    scripts: ['../js/hotel/init.js', '../js/hotel/toast.js', '../js/hotel/transactions.js?v=' + Date.now()],
    open: 'manageTransactions(E.id);',
  },
  additional_payment: {
    picker: 'additional_payment',
    url: '../api/journal/modal_render.php?modal=additional_payment',
    selector: '#addTransactionModal',
    scripts: ['../js/additional_payments/transactions.js?v=' + Date.now(), '../js/additional_payments/main.js'],
    open: 'var e = E, now = new Date(), pad = function(n){ return String(n).padStart(2,"0"); };' +
      '$("#transaction_payment_id").val(e.id);' +
      '$("#transaction_payment_type").val(e.meta.payment_type);' +
      '$("#original_payment_currency").val(e.meta.currency);' +
      '$("#transaction_currency").val(e.meta.currency);' +
      '$("#transaction_main_account_id").val(e.meta.main_account_id);' +
      '$("#trans-payment-type").text(e.meta.payment_type);' +
      '$("#trans-description").text(e.meta.description || "");' +
      '$("#trans-account").text(e.meta.account_name || "");' +
      '$("#totalAmount").text(e.meta.currency + " " + Number(e.meta.amount || 0).toFixed(2));' +
      '$("#remainingAmount").text(e.meta.currency + " " + Number(e.meta.amount || 0).toFixed(2));' +
      '$("#payment_date").val(now.getFullYear() + "-" + pad(now.getMonth() + 1) + "-" + pad(now.getDate()));' +
      '$("#payment_time").val(pad(now.getHours()) + ":" + pad(now.getMinutes()) + ":" + pad(now.getSeconds()));' +
      '$("#exchange_rate").val(""); $("#exchange_rate_group").hide();' +
      '$("#addTransactionModal").modal("show");' +
      'transactionManager.loadTransactionHistory(e.id);',
  },
  fund_client: {
    picker: 'client',
    url: '../api/journal/modal_render.php?modal=fund_client',
    selector: '#partialPaymentModal',
    scripts: [],
    open: 'openPaymentModal(E.id, E.label, E.meta.usd, E.meta.afs);',
  },
  fund_supplier: {
    picker: 'supplier',
    url: '../api/journal/modal_render.php?modal=fund_supplier',
    selector: '#fundSupplierModal',
    scripts: ['../js/accounts/toast-notifications.js?v=' + Date.now(), '../js/accounts/account-funding.js?v=' + Date.now()],
    open: 'setupFundingModal(E.id, E.label, E.meta.currency);',
  },
  withdraw_main: {
    picker: 'main_account',
    url: '../api/journal/modal_render.php?modal=withdraw_main',
    selector: '#withdrawMainModal',
    scripts: ['../js/accounts/toast-notifications.js?v=' + Date.now(), '../js/accounts/main-account-withdrawal.js?v=' + Date.now()],
    open: 'setupMainWithdrawModal(E.id, E.label);',
  },
  withdraw_supplier: {
    picker: 'supplier',
    url: '../api/journal/modal_render.php?modal=withdraw_supplier',
    selector: '#withdrawSupplierModal',
    scripts: ['../js/accounts/toast-notifications.js?v=' + Date.now(), '../js/accounts/account-withdrawal.js?v=' + Date.now()],
    open: 'setupWithdrawModal(E.id, E.label, E.meta.currency);',
  },
  withdraw_client: {
    picker: 'client',
    url: '../api/journal/modal_render.php?modal=withdraw_client',
    selector: '#clientWithdrawModal',
    scripts: ['../js/accounts/toast-notifications.js?v=' + Date.now()],
    open: 'openClientWithdrawModal(E.id, E.label, E.meta.usd, E.meta.afs);',
  },
  transfer: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=transfer',
    selector: '#transferModal',
    scripts: ['../js/accounts/toast-notifications.js?v=' + Date.now(), '../js/accounts/account-funding.js?v=' + Date.now()],
    open: '$("#transferModal").modal("show");',
  },
  expense: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=expense',
    selector: '#expenseModal',
    scripts: ['../js/expense/event_handlers.js'],
    open: '$("#expenseModal").modal("show");',
  },
  jv_payment: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=jv_payment',
    selector: '#jvAddModal',
    scripts: [],
    open: 'pjlOpenModal("jvAddModal");',
  },
  ticket_date_change: {
    picker: 'ticket_date_change',
    url: '../api/journal/modal_render.php?modal=ticket_date_change',
    selector: '#transactionsModal',
    scripts: ['../js/ticket_date_change/transaction-manager.js?v=' + Date.now()],
    open: 'manageTransactions(E.id);',
  },
  ticket_refund: {
    picker: 'ticket_refund',
    url: '../api/journal/modal_render.php?modal=ticket_refund',
    selector: '#transactionsModal',
    scripts: ['../js/ticket_refund/transaction_manager.js?v=' + Date.now()],
    open: 'manageTransactions(E.id);',
  },
  ticket_weight: {
    picker: 'ticket_weight',
    url: '../api/journal/modal_render.php?modal=ticket_weight',
    selector: '#transactionsModal',
    scripts: ['../js/ticket/toast.js', '../js/ticket_weight/transaction_manager.js?v=' + Date.now()],
    open: 'manageTransactions(E.id);',
  },
  ticket_reserve: {
    picker: 'ticket_reserve',
    url: '../api/journal/modal_render.php?modal=ticket_reserve',
    selector: '#transactionsModal',
    scripts: ['../js/ticket/toast.js', '../js/ticket_reserve/transaction_manager.js?v=' + Date.now()],
    open: 'manageTransactions(E.id);',
  },
  hotel_refund: {
    picker: 'hotel_refund',
    url: '../api/journal/modal_render.php?modal=hotel_refund',
    selector: '#refundTransactionModal',
    scripts: ['../js/hotel_refund/transaction_manager.js', '../js/hotel_refund/hotel_management.js'],
    open: 'processRefundTransaction(E.id);',
  },
  visa_refund: {
    picker: 'visa_refund',
    url: '../api/journal/modal_render.php?modal=visa_refund',
    selector: '#refundTransactionModal',
    scripts: ['../js/visa/toast.js', '../js/visa_refund/transaction_manager.js'],
    open: 'processRefundTransaction(E.id);',
  },
  sarafi_deposit: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=sarafi_deposit',
    selector: '#sarafiDepositModal',
    scripts: ['../js/sarafi/sarafi_modal.js?v=' + Date.now()],
    open: '$("#sarafiDepositModal").modal("show");',
  },
  sarafi_withdraw: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=sarafi_withdraw',
    selector: '#sarafiWithdrawModal',
    scripts: ['../js/sarafi/sarafi_modal.js?v=' + Date.now()],
    open: '$("#sarafiWithdrawModal").modal("show");',
  },
  sarafi_hawala: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=sarafi_hawala',
    selector: '#sarafiHawalaModal',
    scripts: ['../js/sarafi/sarafi_modal.js?v=' + Date.now()],
    open: '$("#sarafiHawalaModal").modal("show");',
  },
  sarafi_exchange: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=sarafi_exchange',
    selector: '#sarafiExchangeModal',
    scripts: ['../js/sarafi/sarafi_modal.js?v=' + Date.now()],
    open: '$("#sarafiExchangeModal").modal("show");',
  },
  salary_regular: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=salary_regular',
    selector: '#salaryModal',
    scripts: ['../js/salary/salary_modal.js?v=' + Date.now()],
    open: '$("#salaryModal").modal("show");',
  },
  salary_advance: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=salary_advance',
    selector: '#salaryAdvanceModal',
    scripts: ['../js/salary/salary_modal.js?v=' + Date.now()],
    open: '$("#salaryAdvanceModal").modal("show");',
  },
  salary_bonus: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=salary_bonus',
    selector: '#salaryBonusModal',
    scripts: ['../js/salary/salary_modal.js?v=' + Date.now()],
    open: '$("#salaryBonusModal").modal("show");',
  },
  salary_deduction: {
    picker: null,
    url: '../api/journal/modal_render.php?modal=salary_deduction',
    selector: '#salaryDeductionModal',
    scripts: ['../js/salary/salary_modal.js?v=' + Date.now()],
    open: '$("#salaryDeductionModal").modal("show");',
  },
};

function pjlFlowLabel(key) {
  return PJL_I18N[key] || key;
}

/* ── Step 1: hub → step 2 picker / direct open ── */
function pjlStartFlow(module) {
  const cfg = PJL_MODAL[module];
  if (!cfg) return;
  PJL_FLOW.module = module;

  if (!cfg.picker) {
    pjlOpenRealModal(module, null);
    return;
  }

  document.getElementById('pjlHubView').style.display = 'none';
  document.getElementById('pjlHubHead').style.display = 'none';
  document.getElementById('pjlHubFoot').style.display = 'none';
  document.getElementById('pjlPickerView').style.display = 'block';
  document.getElementById('pjlPickerHead').style.display = 'block';
  document.getElementById('pjlPickerFoot').style.display = 'flex';

  document.getElementById('pjlPickerTitle').textContent = pjlFlowLabel('select_entity');
  document.getElementById('pjlPickerSubtitle').textContent = pjlFlowLabel('type_to_search');
  document.getElementById('pjlPickerQ').value = '';
  pjlLoadEntities('');
  document.getElementById('pjlPickerQ').focus();
}

function pjlBackToHub() {
  PJL_FLOW.module = null;
  document.getElementById('pjlPickerView').style.display = 'none';
  document.getElementById('pjlPickerHead').style.display = 'none';
  document.getElementById('pjlPickerFoot').style.display = 'none';
  document.getElementById('pjlHubView').style.display = 'block';
  document.getElementById('pjlHubHead').style.display = 'block';
  document.getElementById('pjlHubFoot').style.display = 'flex';
}

async function pjlLoadEntities(q) {
  const cfg = PJL_MODAL[PJL_FLOW.module];
  const list = document.getElementById('pjlPickerList');
  if (!cfg || !cfg.picker) return;
  list.innerHTML = '<div class="pjl-picker-hint">' + pjlFlowLabel('type_to_search') + '…</div>';
  try {
    const res = await fetch('../api/journal/entity_picker.php?type=' + encodeURIComponent(cfg.picker) + '&q=' + encodeURIComponent(q || ''), { credentials: 'include' });
    const data = await res.json();
    if (!data.success) { list.innerHTML = '<div class="pjl-picker-empty">' + esc(data.message || 'Error') + '</div>'; return; }
    if (!data.items.length) { list.innerHTML = '<div class="pjl-picker-empty">' + pjlFlowLabel('no_entities_found') + '</div>'; return; }
    list.innerHTML = data.items.map(item => {
      const amt = item.meta && item.meta.amount !== null && item.meta.amount !== undefined
        ? '<div class="pjl-picker-amt">' + fmtNum(item.meta.amount) + ' <small>' + esc(item.meta.currency || '') + '</small></div>' : '';
      return `<button type="button" class="pjl-picker-item" data-id="${item.id}">
        <span><strong>${esc(item.label)}</strong><small>${esc(item.sublabel || '')}</small></span>
        ${amt}
      </button>`;
    }).join('');
    list.querySelectorAll('.pjl-picker-item').forEach(btn => {
      btn.addEventListener('click', () => {
        const item = data.items.find(i => String(i.id) === btn.dataset.id);
        pjlOpenRealModal(PJL_FLOW.module, item);
      });
    });
  } catch (e) {
    list.innerHTML = '<div class="pjl-picker-empty">' + esc(e.message) + '</div>';
  }
}

/* ── Open the module's own transaction modal in place ── */
const PJL_srcCache = {};

function pjlFetchText(url) {
  if (PJL_srcCache[url]) return Promise.resolve(PJL_srcCache[url]);
  const busted = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now();
  return fetch(busted, { credentials: 'include', cache: 'no-store' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
    .then(t => { PJL_srcCache[url] = t; return t; });
}

function pjlExtractInlineScripts(html) {
  const re = /<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi;
  let m, out = [];
  while ((m = re.exec(html))) out.push(m[1]);
  return out.join('\n');
}

/* document shim: run DOMContentLoaded / ready callbacks immediately
   (we execute module scripts after the page has already loaded). */
const PJL_DOC_SHIM = [
  'const __realDoc = globalThis.document;',
  'const document = new Proxy(__realDoc, {',
  '  get(t, p) {',
  "    if (p === 'addEventListener') {",
  '      return function(type, fn, opts) {',
  "        if (type === 'DOMContentLoaded' && typeof fn === 'function') {",
  '          try { fn(); } catch (e) {}',
  '          return undefined;',
  '        }',
  '        return t.addEventListener(type, fn, opts);',
  '      };',
  '    }',
  '    const v = t[p];',
  '    return (typeof v === "function") ? v.bind(t) : v;',
  '  }',
  '});',
].join('\n');

function pjlOpenRealModal(module, entity) {
  const cfg = PJL_MODAL[module];
  if (!cfg) return;
  const mount = document.getElementById('pjlModuleMount');

  pjlCloseModal('pjlRecordModal');

  Promise.all([pjlFetchText(cfg.url)].concat(cfg.scripts.map(pjlFetchText)))
    .then(parts => {
      const html = parts[0];
      mount.innerHTML = html;

      /* Clear stale document-delegated handlers for forms that are reused
         across modules (same IDs like #editTransactionForm). A handler bound
         by a previously opened module would otherwise fire on this module's
         form too (e.g. hotel edit also triggering a refund update request). */
      $(document).off('submit', '#editTransactionForm');

      const inline = pjlExtractInlineScripts(html);
      const src = PJL_DOC_SHIM + '\n'
        + inline + '\n'
        + parts.slice(1).join('\n') + '\n'
        + cfg.open.replace('CSRF', JSON.stringify(PJL_CSRF));

      try {
        new Function('E', src)(entity);
      } catch (err) {
        mount.innerHTML = '';
        pjlToast('Failed to open form: ' + err.message, 'error');
        return;
      }

      const $modal = $(cfg.selector);
      if ($modal.length) {
        $modal.off('hidden.bs.modal').on('hidden.bs.modal', () => {
          $modal.data('bs.modal', null);
          pjlCleanupEmbed();
          loadJournal();
        });
      }
    })
    .catch(err => pjlToast('Failed to load form: ' + err.message, 'error'));
}

/* Remove a mounted module modal + its Bootstrap backdrop. Also used by the
   JV modal (custom backdrop) after submit/close. */
function pjlCleanupEmbed() {
  const m = document.getElementById('pjlModuleMount');
  if (m) m.innerHTML = '';
  document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
}

/* ─── Filter helpers ────────────────────────────────────── */
function currentQuery() {
  const f = document.getElementById('pjlFilterForm');
  const qs = new URLSearchParams(new FormData(f));
  const ent = document.getElementById('pjlEntity');
  if (ent && ent.value) qs.set('entity', ent.value);
  return qs.toString();
}
function dateToInput(d) {
  return d.toISOString().slice(0, 10);
}
function applyPreset(preset) {
  const from = document.getElementById('pjlFromDate');
  const to = document.getElementById('pjlToDate');
  const now = new Date();
  document.querySelectorAll('.pjl-preset').forEach(b => b.classList.toggle('active', b.dataset.preset === preset));
  if (preset === 'today') {
    from.value = dateToInput(now);
    to.value = dateToInput(now);
  } else if (preset === 'week') {
    const start = new Date(now); start.setDate(now.getDate() - now.getDay() + (now.getDay() === 0 ? -6 : 1));
    from.value = dateToInput(start);
    to.value = dateToInput(now);
  } else if (preset === 'month') {
    from.value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-01';
    to.value = dateToInput(now);
  } else if (preset === 'all') {
    from.value = '';
    to.value = '';
  }
  PJL.page = 1;
  loadJournal();
}

/* ─── Load & render ─────────────────────────────────────── */
async function loadJournal() {
  const qs = new URLSearchParams(currentQuery());
  qs.set('page', PJL.page);
  qs.set('per_page', PJL.perPage);

  const tbody = document.getElementById('pjlTbody');
  tbody.innerHTML = '<tr class="pjl-loading-row"><td colspan="11">Loading entries…</td></tr>';

  try {
    const res = await fetch(PJL.baseUrl + '?' + qs.toString(), { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load journal');

    renderRows(data.rows);
    renderKpis(data.summary);
    renderPagination(data);

    const from = (data.total - (data.page - 1) * data.per_page);
    const to = Math.min(data.total, data.page * data.per_page);
    document.getElementById('pjlCount').innerHTML =
      'Showing <strong>' + from + '–' + to + '</strong> of <strong>' + fmtNum(data.total, 0) + '</strong> entries';
  } catch (err) {
    tbody.innerHTML = '<tr class="pjl-loading-row"><td colspan="11">' + esc(err.message) + '</td></tr>';
    pjlToast(err.message, 'error');
  }
}

function renderRows(rows) {
  const tbody = document.getElementById('pjlTbody');
  if (!rows.length) {
    tbody.innerHTML = `
      <tr><td colspan="11">
        <div class="pjl-empty">
          <div class="pjl-empty-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="4" width="14" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <div class="pjl-empty-title">No journal entries found</div>
          <div class="pjl-empty-sub">Try adjusting the filters or date range.</div>
        </div>
      </td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const isCredit = r.type === 'credit';
    const currClass = String(r.currency).toLowerCase();
    const baseHtml = r.base_amount !== null
      ? '<div class="pjl-base">≈ ' + fmtNum(r.base_amount) + ' USD</div>'
      : '<div class="pjl-base" style="opacity:.5">—</div>';
    const ledgerTag = r.ledger === 'main_account'
      ? '<span class="pjl-ledger-tag">Main</span>'
      : '<span class="pjl-ledger-tag ' + esc(r.ledger) + '">' + esc(r.ledger === 'jv' ? 'JV' : r.ledger) + '</span>';
    const partyHtml = r.party_name
      ? '<div class="pjl-name">' + esc(r.party_name) + '</div>'
      : '';
    const acctHtml = r.account_name
      ? '<div class="pjl-name-sub">' + esc(r.account_name) + '</div>'
      : '';
    const subcatHtml = r.sub_category_name
      ? '<span class="pjl-subcat">' + esc(r.sub_category_name) + '</span>'
      : '';
    const refHtml = r.drill_url
      ? '<a class="pjl-drill" target="_blank" href="' + esc(r.drill_url) + '">#' + esc(r.reference_id) + ' <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M4 8L8 4M5 4h3v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></a>'
      : (r.reference_id !== null ? '#' + esc(r.reference_id) : '—');
    const receiptHtml = r.receipt ? '<span class="pjl-receipt-code">' + esc(r.receipt) + '</span>' : '—';

    return `<tr>
      <td>
        <div class="pjl-date-main">${fmtDate(r.entry_date)}</div>
        <div class="pjl-date-sub">${fmtTime(r.entry_date)}</div>
      </td>
      <td><span class="pjl-badge ${isCredit ? 'credit' : 'debit'}">${isCredit ? 'Cr' : 'Dr'}</span></td>
      <td class="right">
        <div class="pjl-amount ${isCredit ? 'credit' : 'debit'}">${isCredit ? '+' : '−'}${fmtNum(r.amount)}</div>
        ${baseHtml}
      </td>
      <td class="center"><span class="pjl-currency-badge ${currClass}">${esc(r.currency)}</span></td>
      <td>${ledgerTag}${acctHtml}</td>
      <td>${partyHtml || '<span style="color:var(--text-dim)">—</span>'}</td>
      <td><span class="pjl-module">${esc(r.module_label)}</span>${subcatHtml}</td>
      <td>${receiptHtml}</td>
      <td>${refHtml}</td>
      <td><div class="pjl-desc" title="${esc(r.description)}">${esc(r.description) || '—'}</div></td>
      <td class="right">${r.balance !== null ? '<span class="pjl-amount">' + fmtNum(r.balance) + '</span>' : '—'}</td>
    </tr>`;
  }).join('');
}

const PJL_CURRENCIES = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];

function renderKpis(summary) {
  const byCurr = summary.by_currency || {};
  const strip  = document.getElementById('pjlKpiStrip');
  const count  = document.getElementById('pjlKpiCount');
  const sub    = document.getElementById('pjlKpiCountSub');

  count.textContent = fmtNum(summary.count, 0);
  sub.textContent   = 'filtered entries';

  const cards = PJL_CURRENCIES.map(c => {
    const v   = byCurr[c] || { in: 0, out: 0 };
    const net = v.in - v.out;
    const cls = net > 0 ? 'pos' : (net < 0 ? 'neg' : '');
    return `<div class="pjl-kpi pjl-kpi-cur">
      <div class="pjl-kpi-cur-head">
        <span class="pjl-kpi-cur-name">
          <span class="pjl-currency-badge ${String(c).toLowerCase()}">${esc(c)}</span>
        </span>
      </div>
      <div class="pjl-kpi-cur-row">
        <span class="in">+${fmtNum(v.in)}</span>
        <span class="out">−${fmtNum(v.out)}</span>
      </div>
      <div class="pjl-kpi-cur-net ${cls}">Net ${fmtNum(net)}</div>
    </div>`;
  }).join('');

  document.querySelectorAll('#pjlKpiStrip .pjl-kpi-cur').forEach(el => el.remove());
  count.closest('.pjl-kpi-strip').insertAdjacentHTML('beforeend', cards);
}

function renderPagination(data) {
  const wrap = document.getElementById('pjlPagination');
  const page = data.page, pages = data.total_pages;
  let html = '';

  const pageBtn = (p, label, cls) =>
    `<button class="pjl-page-btn ${cls}" data-page="${p}">${label}</button>`;

  if (page > 1) html += pageBtn(page - 1, '‹', '');
  const start = Math.max(1, page - 2), end = Math.min(pages, page + 2);
  if (start > 1) html += '<span class="pjl-page-ellipsis">…</span>';
  for (let i = start; i <= end; i++) {
    html += pageBtn(i, i, i === page ? 'active' : '');
  }
  if (end < pages) html += '<span class="pjl-page-ellipsis">…</span>';
  if (page < pages) html += pageBtn(page + 1, '›', '');

  wrap.innerHTML = html;
  wrap.querySelectorAll('[data-page]').forEach(b => {
    b.addEventListener('click', () => { PJL.page = parseInt(b.dataset.page, 10); loadJournal(); });
  });
}

/* ─── Events ────────────────────────────────────────────── */
document.getElementById('pjlFilterForm').addEventListener('submit', e => {
  e.preventDefault();
  PJL.page = 1;
  loadJournal();
});
document.getElementById('pjlClearBtn').addEventListener('click', () => {
  document.getElementById('pjlFilterForm').reset();
  document.querySelectorAll('.pjl-preset').forEach(b => b.classList.remove('active'));
  const ent = document.getElementById('pjlEntity');
  if (ent) ent.value = '';
  syncLedgerTabs();
  PJL.page = 1;
  loadJournal();
});
document.querySelectorAll('.pjl-preset').forEach(b => {
  b.addEventListener('click', () => applyPreset(b.dataset.preset));
});
/* ── Ledger tabs ── */
const PJL_ENTITY_TYPES = { 'main_account': 'main_account', 'client': 'client', 'supplier': 'supplier' };

async function loadPjlEntities(ledger) {
  const sel = document.getElementById('pjlEntity');
  if (!PJL_ENTITY_TYPES[ledger]) {
    sel.style.display = 'none';
    return;
  }
  sel.style.display = '';
  const prev = sel.value;
  sel.innerHTML = '<option value="">' + (document.querySelector('#pjlLedgerTabs .pjl-ltab[data-ledger="' + ledger + '"]') || { dataset: {} }).dataset.allLabel + '</option>';
  try {
    const res = await fetch('../api/journal/journal_entities.php?type=' + encodeURIComponent(ledger), { credentials: 'include' });
    const data = await res.json();
    if (data.success) {
      data.items.forEach(it => {
        const o = document.createElement('option');
        o.value = it.id;
        o.textContent = it.name;
        sel.appendChild(o);
      });
    }
  } catch (err) { /* leave just the "All" option */ }
  sel.value = prev;
}

document.getElementById('pjlLedgerTabs').addEventListener('click', e => {
  const btn = e.target.closest('.pjl-ltab');
  if (!btn) return;
  document.getElementById('pjlLedger').value = btn.dataset.ledger;
  syncLedgerTabs();
  const sel = document.getElementById('pjlEntity');
  sel.value = '';
  loadPjlEntities(btn.dataset.ledger);
  PJL.page = 1;
  loadJournal();
});
document.getElementById('pjlEntity').addEventListener('change', () => {
  PJL.page = 1;
  loadJournal();
});
function syncLedgerTabs() {
  const cur = document.getElementById('pjlLedger').value || 'main_account';
  document.querySelectorAll('#pjlLedgerTabs .pjl-ltab').forEach(b => {
    b.classList.toggle('active', b.dataset.ledger === cur);
  });
}
document.getElementById('pjlExportBtn').addEventListener('click', () => {
  const qs = currentQuery();
  window.open('../api/journal/export_journal.php?' + qs, '_blank');
});

/* ── Record Payment hub ── */
document.querySelectorAll('#pjlHubView .pjl-hub-btn').forEach(btn => {
  btn.addEventListener('click', () => pjlStartFlow(btn.dataset.module));
});

/* ── Print ── */
function pjlPrintBuildRows(rows) {
  return rows.map(r => {
    const isCredit = r.type === 'credit';
    const currClass = String(r.currency).toLowerCase();
    const baseHtml = r.base_amount !== null
      ? '<div style="font-family:monospace;font-size:8px;color:#b5b5b0;margin-top:1px;">≈ ' + fmtNum(r.base_amount) + ' USD</div>'
      : '';
    const ledgerTag = r.ledger === 'main_account'
      ? '<span style="font-size:9px;font-weight:700;text-transform:uppercase;padding:1px 5px;border-radius:4px;background:#f4f4f2;color:#8c8c87;border:1px solid #e5e5e3;">Main</span>'
      : '<span style="font-size:9px;font-weight:700;text-transform:uppercase;padding:1px 5px;border-radius:4px;">' + esc(r.ledger === 'jv' ? 'JV' : r.ledger) + '</span>';
    const partyHtml = r.party_name
      ? '<div style="font-size:10px;font-weight:500;">' + esc(r.party_name) + '</div>'
      : '';
    const acctHtml = r.account_name
      ? '<div style="font-size:8px;color:#b5b5b0;">' + esc(r.account_name) + '</div>'
      : '';
    const subcatHtml = r.sub_category_name
      ? ' <span style="font-size:8px;font-weight:600;padding:1px 5px;border-radius:20px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">' + esc(r.sub_category_name) + '</span>'
      : '';
    const refHtml = r.drill_url
      ? '<a href="' + esc(r.drill_url) + '" target="_blank" style="font-size:10px;color:#1d4ed8;text-decoration:none;">#' + esc(r.reference_id) + '</a>'
      : (r.reference_id !== null ? '#' + esc(r.reference_id) : '—');
    const receiptHtml = r.receipt ? '<span style="font-family:monospace;font-size:9px;color:#8c8c87;background:#f0f0ee;padding:1px 5px;border-radius:3px;border:1px solid #e5e5e3;">' + esc(r.receipt) + '</span>' : '—';
    const badgeCls = isCredit ? 'cr' : 'dr';
    const amtCls = isCredit ? 'cr' : 'dr';
    const sign = isCredit ? '+' : '−';

    return '<tr>'
      + '<td><div style="font-weight:500;">' + fmtDate(r.entry_date) + '</div><div style="font-size:8px;color:#b5b5b0;font-family:monospace;">' + fmtTime(r.entry_date) + '</div></td>'
      + '<td><span class="badge ' + badgeCls + '">' + (isCredit ? 'Cr' : 'Dr') + '</span></td>'
      + '<td style="text-align:right;"><span class="amt ' + amtCls + '">' + sign + fmtNum(r.amount) + '</span>' + baseHtml + '</td>'
      + '<td style="text-align:center;"><span class="cur ' + currClass + '">' + esc(r.currency) + '</span></td>'
      + '<td>' + ledgerTag + acctHtml + '</td>'
      + '<td>' + partyHtml + '</td>'
      + '<td><span style="font-size:10px;font-weight:500;">' + esc(r.module_label) + '</span>' + subcatHtml + '</td>'
      + '<td>' + receiptHtml + '</td>'
      + '<td>' + refHtml + '</td>'
      + '<td><div style="font-size:9px;color:#8c8c87;max-width:200px;white-space:normal;word-wrap:break-word;overflow-wrap:break-word;line-height:1.3;" title="' + esc(r.description) + '">' + (esc(r.description) || '—') + '</div></td>'
      + '<td style="text-align:right;">' + (r.balance !== null ? '<span style="font-family:monospace;font-weight:500;font-size:10px;">' + fmtNum(r.balance) + '</span>' : '—') + '</td>'
      + '</tr>';
  }).join('');
}

document.getElementById('pjlPrintBtn').addEventListener('click', async () => {
  const kpiStrip = document.getElementById('pjlKpiStrip');

  /* Build KPI HTML from the visible strip */
  let kpiHtml = '';
  if (kpiStrip) {
    kpiStrip.querySelectorAll('.pjl-kpi').forEach(card => {
      const label = card.querySelector('.pjl-kpi-label');
      const value = card.querySelector('.pjl-kpi-value');
      const sub   = card.querySelector('.pjl-kpi-sub');
      const curH  = card.querySelector('.pjl-kpi-cur-head');
      const curR  = card.querySelectorAll('.pjl-kpi-cur-row');
      const curN  = card.querySelector('.pjl-kpi-cur-net');

      if (curH) {
        const badge = curH.querySelector('.pjl-currency-badge');
        const curName = badge ? badge.textContent.trim() : '';
        const curCls  = badge ? badge.className.replace('pjl-currency-badge','').trim() : '';
        let rows = '';
        curR.forEach(r => {
          const inV  = r.querySelector('.in')  ? r.querySelector('.in').textContent.trim()  : '';
          const outV = r.querySelector('.out') ? r.querySelector('.out').textContent.trim() : '';
          rows += '<div style="display:flex;justify-content:space-between;font-family:monospace;font-size:10px;gap:8px;"><span style="color:#16a34a;">' + esc(inV) + '</span><span style="color:#dc2626;">' + esc(outV) + '</span></div>';
        });
        const netTxt = curN ? curN.textContent.trim() : '';
        const netClr = curN && curN.classList.contains('pos') ? '#16a34a' : (curN && curN.classList.contains('neg') ? '#dc2626' : '#1d4ed8');
        kpiHtml += '<div style="flex:1;min-width:120px;border:1px solid #e5e5e3;border-radius:6px;padding:8px 10px;background:#fff;">'
          + '<div style="display:flex;align-items:center;gap:5px;margin-bottom:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">'
          + '<span class="cur ' + curCls + '">' + esc(curName) + '</span></div>'
          + rows
          + '<div style="font-family:monospace;font-size:10px;font-weight:600;margin-top:4px;padding-top:4px;border-top:1px dashed #e5e5e3;color:' + netClr + ';">' + esc(netTxt) + '</div>'
          + '</div>';
      } else if (label && value) {
        kpiHtml += '<div style="flex:0 0 auto;min-width:100px;border:1px solid #e5e5e3;border-radius:6px;padding:8px 10px;background:#fff;">'
          + '<div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#b5b5b0;margin-bottom:3px;">' + esc(label.textContent.trim()) + '</div>'
          + '<div style="font-family:monospace;font-size:14px;font-weight:500;">' + esc(value.textContent.trim()) + '</div>'
          + (sub ? '<div style="font-size:9px;color:#b5b5b0;margin-top:2px;">' + esc(sub.textContent.trim()) + '</div>' : '')
          + '</div>';
      }
    });
  }

  /* Fetch ALL records from the API */
  const qs = new URLSearchParams(currentQuery());
  qs.set('page', 1);
  qs.set('per_page', 99999);

  let allRows = [];
  let mainAccounts = [];
  try {
    const [res, acctRes] = await Promise.all([
      fetch(PJL.baseUrl + '?' + qs.toString(), { credentials: 'include' }),
      fetch('../api/journal/main_account_balances.php?' + qs.toString(), { credentials: 'include' })
    ]);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load');
    allRows = data.rows || [];
    const acctData = await acctRes.json();
    if (acctData.success) mainAccounts = acctData.accounts || [];
  } catch (err) {
    alert('Failed to load records for printing: ' + err.message);
    return;
  }

  const fromVal = document.getElementById('pjlFromDate').value;
  const toVal   = document.getElementById('pjlToDate').value;
  const dateRange = fromVal && toVal ? fromVal + ' — ' + toVal : (fromVal || toVal || 'All time');

  /* Build main account balance cards HTML */
  let acctHtml = '';
  if (mainAccounts.length) {
    acctHtml = '<div style="margin-bottom:10px;"><div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#b5b5b0;margin-bottom:6px;">Main Account Balances (as of ' + esc(dateRange) + ')</div>';
    acctHtml += '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
    const currSymbols = { usd: '$', afs: '؋', eur: '€', darham: 'AED ', sar: 'SAR ' };
    const currColors = { usd: '#1d4ed8', afs: '#b45309', eur: '#7c3aed', darham: '#16a34a', sar: '#0d9488' };
    const currBgs = { usd: '#eff6ff', afs: '#fffbeb', eur: '#f5f3ff', darham: '#f0fdf4', sar: '#f0fdfa' };
    mainAccounts.forEach(acct => {
      acctHtml += '<div style="flex:1;min-width:160px;border:1px solid #e5e5e3;border-radius:6px;padding:8px 10px;background:#fff;">';
      acctHtml += '<div style="font-size:10px;font-weight:700;margin-bottom:6px;color:#1a1a18;">' + esc(acct.name) + '</div>';
      ['usd','afs','eur','darham','sar'].forEach(c => {
        if (acct.balances[c] !== undefined) {
          const sym = currSymbols[c];
          const clr = currColors[c];
          const bg  = currBgs[c];
          acctHtml += '<div style="display:flex;justify-content:space-between;align-items:center;font-family:monospace;font-size:10px;padding:2px 0;">';
          acctHtml += '<span style="font-weight:700;text-transform:uppercase;font-size:8px;color:#8c8c87;">' + c.toUpperCase() + '</span>';
          acctHtml += '<span style="color:' + clr + ';font-weight:500;">' + sym + fmtNum(acct.balances[c]) + '</span>';
          acctHtml += '</div>';
        }
      });
      acctHtml += '</div>';
    });
    acctHtml += '</div></div>';
  }

  const printWin = window.open('', '_blank');
  printWin.document.write('<!DOCTYPE html><html><head><title>Payments Journal</title><style>');
  printWin.document.write('@page{size:A4 landscape;margin:10mm;}');
  printWin.document.write('body{font-family:DM Sans,sans-serif;font-size:11px;color:#1a1a18;margin:0;padding:0;}');
  printWin.document.write('table{width:100%;border-collapse:collapse;}');
  printWin.document.write('th,td{padding:4px 5px;border:1px solid #e5e5e3;text-align:left;font-size:10px;white-space:nowrap;}');
  printWin.document.write('th{background:#f0f0ee;font-weight:700;text-transform:uppercase;font-size:9px;letter-spacing:.04em;color:#8c8c87;}');
  printWin.document.write('th.desc-col{white-space:normal;max-width:200px;}');
  printWin.document.write('.right{text-align:right;} .center{text-align:center;}');
  printWin.document.write('.badge{display:inline-block;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;}');
  printWin.document.write('.badge.cr{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}');
  printWin.document.write('.badge.dr{background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;}');
  printWin.document.write('.amt{font-family:monospace;font-weight:500;font-size:10px;}');
  printWin.document.write('.amt.cr{color:#16a34a;} .amt.dr{color:#dc2626;}');
  printWin.document.write('.cur{font-family:monospace;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;}');
  printWin.document.write('.cur.usd{background:#eff6ff;color:#1d4ed8;} .cur.afs{background:#fffbeb;color:#b45309;}');
  printWin.document.write('.cur.eur{background:#f5f3ff;color:#7c3aed;} .cur.darham{background:#f0fdf4;color:#16a34a;}');
  printWin.document.write('.cur.sar{background:#f0fdfa;color:#0d9488;}');
  printWin.document.write('</style></head><body>');
  const printDate = new Date().toLocaleString();
  printWin.document.write('<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">');
  printWin.document.write('<h2 style="font-size:14px;margin:0;">Payments Journal</h2>');
  printWin.document.write('<div style="font-size:10px;color:#8c8c87;text-align:right;">');
  printWin.document.write('<div><strong>Period:</strong> ' + esc(dateRange) + '</div>');
  printWin.document.write('<div><strong>Printed:</strong> ' + esc(printDate) + '</div>');
  printWin.document.write('<div><strong>Records:</strong> ' + esc(String(allRows.length)) + '</div>');
  printWin.document.write('</div></div>');
  if (kpiHtml) {
    printWin.document.write('<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">' + kpiHtml + '</div>');
  }
  if (acctHtml) {
    printWin.document.write(acctHtml);
  }
  printWin.document.write('<table><thead><tr>');
  printWin.document.write('<th>Date</th><th>Type</th><th style="text-align:right;">Amount</th><th style="text-align:center;">Currency</th><th>Ledger</th><th>Account/Party</th><th>Module</th><th>Receipt</th><th>Reference</th><th class="desc-col">Description</th><th style="text-align:right;">Balance</th>');
  printWin.document.write('</tr></thead><tbody>');
  printWin.document.write(pjlPrintBuildRows(allRows));
  printWin.document.write('</tbody></table>');
  printWin.document.write('</body></html>');
  printWin.document.close();
  printWin.focus();
  setTimeout(() => { printWin.print(); }, 500);
});

/* ── Picker (step 2) ── */
document.getElementById('pjlPickerQ').addEventListener('input', () => {
  clearTimeout(PJL_FLOW.qTimer);
  const q = document.getElementById('pjlPickerQ').value.trim();
  PJL_FLOW.qTimer = setTimeout(() => pjlLoadEntities(q), 300);
});
document.getElementById('pjlPickerQ').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    pjlLoadEntities(document.getElementById('pjlPickerQ').value.trim());
  }
});
document.getElementById('pjlPickerBack').addEventListener('click', pjlBackToHub);
document.getElementById('pjlPickerCancel').addEventListener('click', pjlBackToHub);

/* ─── Init ──────────────────────────────────────────────── */
const initLedger = document.getElementById('pjlLedger').value;
loadPjlEntities(initLedger).then(() => {
  const ent = document.getElementById('pjlEntity');
  const urlEnt = new URLSearchParams(window.location.search).get('entity');
  if (urlEnt) ent.value = urlEnt;
});
loadJournal();
</script>
