<?php
require_once 'security.php';
require_once '../includes/language_helpers.php';
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt: " . ($_SESSION['user_id'] ?? 'unknown') . " IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

include '../includes/db.php';

$umrahId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$umrahData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

if (!$umrahId) {
    $error = "No Umrah booking ID provided";
} else {
    $umrahQuery = "SELECT ub.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, f.head_of_family AS family_name
        FROM umrah_bookings ub
        LEFT JOIN clients c ON ub.sold_to = c.id
        LEFT JOIN families f ON ub.family_id = f.family_id
        WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?";
    $stmt = $pdo->prepare($umrahQuery);
    $stmt->execute([$umrahId, $tenant_id, $branch_id]);
    $umrahData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$umrahData) {
        $error = "Umrah booking not found";
    } else {
        $supplierServicesQuery = "SELECT ubs.id AS service_id, ubs.service_type, s.id AS supplier_id, s.name AS supplier_name,
            s.email AS supplier_email, s.phone AS supplier_phone, ubs.base_price, ubs.sold_price, ubs.profit, ubs.currency
            FROM umrah_booking_services ubs
            LEFT JOIN suppliers s ON ubs.supplier_id = s.id
            WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ?
            ORDER BY ubs.service_type";
        $stmt = $pdo->prepare($supplierServicesQuery);
        $stmt->execute([$umrahId, $tenant_id, $branch_id]);
        $umrahData['supplier_services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $clientTransQuery = "SELECT 'Client' AS transaction_type, ct.id, ct.type, ct.amount, ct.currency, ct.description, ct.transaction_of, ct.created_at AS transaction_date
            FROM client_transactions ct WHERE ct.reference_id = ? AND ct.transaction_of = 'umrah' AND ct.tenant_id = ? AND ct.branch_id = ? ORDER BY ct.created_at DESC";
        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$umrahId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $supplierTransQuery = "SELECT 'Supplier' AS transaction_type, st.id, st.transaction_type as type, st.amount, null as currency, st.remarks as description, st.transaction_of, st.transaction_date
            FROM supplier_transactions st WHERE st.reference_id = ? AND st.transaction_of = 'umrah' AND st.tenant_id = ? AND st.branch_id = ? ORDER BY st.transaction_date DESC";
        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$umrahId, $tenant_id, $branch_id]);
        $supplierTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mainAccountTransQuery = "SELECT 'Main Account' AS transaction_type, mat.id, mat.type, mat.amount, mat.currency, mat.description, mat.transaction_of, mat.created_at AS transaction_date
            FROM main_account_transactions mat WHERE mat.reference_id = ? AND mat.transaction_of = 'umrah' AND mat.tenant_id = ? AND mat.branch_id = ? ORDER BY mat.created_at DESC";
        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$umrahId, $tenant_id, $branch_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Umrah Booking · Detail View</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   TOKENS — Corporate SaaS Design System
═══════════════════════════════════════════════ */
:root {
  /* Neutrals */
  --ink-900: #0b0f1a;
  --ink-800: #141927;
  --ink-700: #1d2435;
  --ink-600: #2b3349;
  --ink-400: #5a6785;
  --ink-300: #8494b0;
  --ink-200: #c4cedf;
  --ink-100: #e8edf5;
  --ink-50:  #f4f6fa;
  --white:   #ffffff;

  /* Brand — slate blue accent */
  --brand:       #2563eb;
  --brand-light: #3b82f6;
  --brand-dim:   rgba(37,99,235,.10);
  --brand-glow:  rgba(37,99,235,.20);

  /* Status */
  --green:  #10b981;
  --green-dim: rgba(16,185,129,.10);
  --red:    #ef4444;
  --red-dim:   rgba(239,68,68,.10);
  --amber:  #f59e0b;
  --amber-dim: rgba(245,158,11,.10);
  --purple: #8b5cf6;
  --purple-dim: rgba(139,92,246,.10);

  /* Surface */
  --surface:      #ffffff;
  --surface-2:    #f8fafc;
  --border:       #e2e8f4;
  --border-focus: var(--brand);

  /* Type */
  --font-display: 'Sora', sans-serif;
  --font-body:    'DM Sans', sans-serif;
  --font-mono:    'DM Mono', monospace;

  /* Radius */
  --r-xs: 4px;
  --r-sm: 6px;
  --r:    10px;
  --r-lg: 14px;
  --r-xl: 20px;

  /* Shadow */
  --sh-xs:  0 1px 3px rgba(11,15,26,.07);
  --sh-sm:  0 2px 8px rgba(11,15,26,.08);
  --sh:     0 4px 16px rgba(11,15,26,.09);
  --sh-lg:  0 8px 32px rgba(11,15,26,.10);
  --sh-brand: 0 4px 20px rgba(37,99,235,.25);

  --transition: .18s cubic-bezier(.4,0,.2,1);
}

/* ═══════════════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; }
body {
  font-family: var(--font-body);
  background: var(--ink-50);
  color: var(--ink-800);
  line-height: 1.6;
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; border: none; background: none; }

/* ═══════════════════════════════════════════════
   PAGE SHELL
═══════════════════════════════════════════════ */
.page {
  max-width: 1280px;
  margin: 0 auto;
  padding: 28px 24px 60px;
}

/* ═══════════════════════════════════════════════
   TOP NAV BAR
═══════════════════════════════════════════════ */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 12px;
  flex-wrap: wrap;
}
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: .8rem;
  color: var(--ink-400);
}
.breadcrumb span { color: var(--ink-300); }
.breadcrumb a { color: var(--ink-400); transition: color var(--transition); }
.breadcrumb a:hover { color: var(--brand); }
.breadcrumb .current { color: var(--ink-700); font-weight: 600; }

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: .8rem;
  font-weight: 600;
  color: var(--ink-500);
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  padding: 7px 14px;
  box-shadow: var(--sh-xs);
  transition: all var(--transition);
  color: var(--ink-600);
}
.btn-back:hover { border-color: var(--brand); color: var(--brand); box-shadow: var(--sh-brand); }
.btn-back i { font-size: .75rem; }

/* ═══════════════════════════════════════════════
   PAGE HEADER ROW
═══════════════════════════════════════════════ */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.page-title-block {}
.page-title-block .booking-id {
  font-family: var(--font-mono);
  font-size: .72rem;
  color: var(--brand);
  background: var(--brand-dim);
  border: 1px solid rgba(37,99,235,.15);
  border-radius: var(--r-xs);
  padding: 2px 8px;
  display: inline-block;
  margin-bottom: 8px;
  letter-spacing: .04em;
  font-weight: 500;
}
.page-title-block h1 {
  font-family: var(--font-display);
  font-size: 1.55rem;
  font-weight: 700;
  color: var(--ink-900);
  letter-spacing: -.02em;
  line-height: 1.2;
}
.page-title-block .subtitle {
  font-size: .82rem;
  color: var(--ink-400);
  margin-top: 5px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.dot-sep { width: 3px; height: 3px; border-radius: 50%; background: var(--ink-200); display: inline-block; }

.status-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: .73rem;
  font-weight: 700;
  letter-spacing: .03em;
  text-transform: uppercase;
}
.status-chip.active   { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(16,185,129,.2); }
.status-chip.pending  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,.2); }
.status-chip.overdue  { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(239,68,68,.2); }
.status-chip i { font-size: .62rem; }

.header-actions { display: flex; align-items: center; gap: 8px; }
.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: var(--r-sm);
  font-size: .8rem;
  font-weight: 600;
  transition: all var(--transition);
  white-space: nowrap;
}
.btn-action.ghost {
  color: var(--ink-600);
  background: var(--white);
  border: 1px solid var(--border);
  box-shadow: var(--sh-xs);
}
.btn-action.ghost:hover { border-color: var(--brand); color: var(--brand); }
.btn-action.primary {
  color: var(--white);
  background: var(--brand);
  border: 1px solid var(--brand);
  box-shadow: var(--sh-brand);
}
.btn-action.primary:hover { background: var(--brand-light); box-shadow: 0 6px 24px rgba(37,99,235,.32); }

/* ═══════════════════════════════════════════════
   KPI STRIP (horizontal, 4 cols)
═══════════════════════════════════════════════ */
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  overflow: hidden;
  box-shadow: var(--sh-sm);
  margin-bottom: 24px;
}
@media(max-width:800px){ .kpi-strip { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px){ .kpi-strip { grid-template-columns: 1fr; } }

.kpi-cell {
  background: var(--white);
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
  transition: background var(--transition);
}
.kpi-cell:hover { background: var(--surface-2); }
.kpi-cell::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  opacity: 0;
  transition: opacity var(--transition);
}
.kpi-cell:hover::before { opacity: 1; }
.kpi-cell.blue::before  { background: var(--brand); }
.kpi-cell.green::before { background: var(--green); }
.kpi-cell.red::before   { background: var(--red); }
.kpi-cell.amber::before { background: var(--amber); }

.kpi-label {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--ink-400);
}
.kpi-value {
  font-family: var(--font-display);
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--ink-900);
  letter-spacing: -.02em;
  line-height: 1;
}
.kpi-value.green { color: var(--green); }
.kpi-value.red   { color: var(--red); }
.kpi-value.blue  { color: var(--brand); }
.kpi-meta {
  font-size: .72rem;
  color: var(--ink-300);
  display: flex;
  align-items: center;
  gap: 4px;
}
.kpi-meta .up   { color: var(--green); font-weight: 600; }
.kpi-meta .down { color: var(--red);   font-weight: 600; }

/* ═══════════════════════════════════════════════
   MAIN GRID (left 60% / right 40%)
═══════════════════════════════════════════════ */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 20px;
  align-items: start;
}
@media(max-width:1024px){ .main-grid { grid-template-columns: 1fr; } }

.left-col { display: flex; flex-direction: column; gap: 20px; }
.right-col { display: flex; flex-direction: column; gap: 20px; }

/* ═══════════════════════════════════════════════
   CARD
═══════════════════════════════════════════════ */
.card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  box-shadow: var(--sh-sm);
  overflow: hidden;
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--surface-2);
  gap: 10px;
}
.card-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.card-icon {
  width: 32px;
  height: 32px;
  border-radius: var(--r-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .78rem;
  flex-shrink: 0;
}
.card-icon.blue   { background: var(--brand-dim);  color: var(--brand); }
.card-icon.green  { background: var(--green-dim);  color: var(--green); }
.card-icon.amber  { background: var(--amber-dim);  color: var(--amber); }
.card-icon.purple { background: var(--purple-dim); color: var(--purple); }

.card-title {
  font-family: var(--font-display);
  font-size: .85rem;
  font-weight: 700;
  color: var(--ink-800);
  letter-spacing: -.01em;
}
.card-badge {
  font-size: .68rem;
  font-weight: 700;
  color: var(--ink-400);
  background: var(--ink-100);
  padding: 2px 8px;
  border-radius: 10px;
}
.card-body { padding: 20px; }
.card-body.flush { padding: 0; }

/* ═══════════════════════════════════════════════
   INFO TABLE (label / value rows)
═══════════════════════════════════════════════ */
.info-table { width: 100%; }
.info-table tr { border-bottom: 1px solid var(--border); }
.info-table tr:last-child { border-bottom: none; }
.info-table td {
  padding: 11px 0;
  font-size: .83rem;
  vertical-align: top;
}
.info-table td:first-child {
  width: 140px;
  font-weight: 600;
  color: var(--ink-400);
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding-right: 16px;
  white-space: nowrap;
  padding-top: 13px;
}
.info-table td:last-child {
  color: var(--ink-700);
  font-weight: 500;
}

/* two-col info grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 32px;
}
@media(max-width:640px){ .info-grid { grid-template-columns: 1fr; } }

/* ═══════════════════════════════════════════════
   SUPPLIER TABLE
═══════════════════════════════════════════════ */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--ink-400);
  padding: 10px 16px;
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
  text-align: left;
  white-space: nowrap;
}
.data-table tbody td {
  padding: 12px 16px;
  font-size: .83rem;
  color: var(--ink-700);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: var(--surface-2); }
.data-table .num {
  font-family: var(--font-mono);
  font-size: .8rem;
  font-weight: 500;
}
.data-table .profit-pos { color: var(--green); font-weight: 700; }
.data-table .profit-neg { color: var(--red);   font-weight: 700; }

.tag {
  display: inline-block;
  padding: 3px 9px;
  border-radius: var(--r-xs);
  font-size: .68rem;
  font-weight: 700;
  text-transform: capitalize;
  letter-spacing: .02em;
  border: 1px solid;
}
.tag.flight  { background: rgba(37,99,235,.07);  color: var(--brand);  border-color: rgba(37,99,235,.15); }
.tag.hotel   { background: rgba(139,92,246,.07); color: var(--purple); border-color: rgba(139,92,246,.15); }
.tag.visa    { background: rgba(16,185,129,.07); color: var(--green);  border-color: rgba(16,185,129,.15); }
.tag.transport { background: rgba(245,158,11,.07); color: var(--amber); border-color: rgba(245,158,11,.15); }
.tag.default { background: var(--ink-100); color: var(--ink-600); border-color: var(--border); }

.link-cell { color: var(--brand); font-weight: 600; font-size: .82rem; }
.link-cell:hover { text-decoration: underline; }
.link-cell i { font-size: .7rem; margin-right: 3px; opacity: .7; }

/* ═══════════════════════════════════════════════
   CLIENT CARD (right sidebar)
═══════════════════════════════════════════════ */
.client-card-inner { padding: 20px; }
.client-avatar-row {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
}
.avatar-circle {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand) 0%, #818cf8 100%);
  color: var(--white);
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 3px 12px rgba(37,99,235,.28);
}
.client-name-block .name {
  font-weight: 700;
  font-size: .92rem;
  color: var(--ink-800);
  line-height: 1.2;
}
.client-name-block .email {
  font-size: .75rem;
  color: var(--ink-400);
  margin-top: 2px;
}

.client-detail-rows { margin-bottom: 16px; }
.cdr {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
  font-size: .8rem;
}
.cdr:last-child { border-bottom: none; }
.cdr-icon {
  width: 26px;
  height: 26px;
  border-radius: var(--r-xs);
  background: var(--surface-2);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .65rem;
  color: var(--ink-400);
  flex-shrink: 0;
}
.cdr-label { color: var(--ink-400); font-weight: 500; width: 60px; flex-shrink: 0; }
.cdr-val   { color: var(--ink-700); font-weight: 600; }

.btn-full {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
  padding: 9px 16px;
  border-radius: var(--r-sm);
  font-size: .8rem;
  font-weight: 700;
  color: var(--brand);
  background: var(--brand-dim);
  border: 1px solid rgba(37,99,235,.18);
  transition: all var(--transition);
}
.btn-full:hover { background: var(--brand); color: var(--white); box-shadow: var(--sh-brand); }

/* ═══════════════════════════════════════════════
   TRAVEL TIMELINE (right sidebar)
═══════════════════════════════════════════════ */
.travel-timeline { padding: 16px 20px; display: flex; flex-direction: column; gap: 0; }
.tt-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding-bottom: 16px;
  position: relative;
}
.tt-item:last-child { padding-bottom: 0; }
.tt-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 15px;
  top: 30px;
  bottom: 0;
  width: 1px;
  background: var(--border);
  border-left: 1px dashed var(--border);
}
.tt-dot {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--white);
  border: 2px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .65rem;
  color: var(--ink-400);
  flex-shrink: 0;
  position: relative;
  z-index: 1;
  transition: all var(--transition);
}
.tt-dot.active {
  border-color: var(--brand);
  color: var(--brand);
  background: var(--brand-dim);
  box-shadow: 0 0 0 3px var(--brand-glow);
}
.tt-content .tt-label {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--ink-400);
  margin-bottom: 2px;
}
.tt-content .tt-date {
  font-size: .88rem;
  font-weight: 700;
  color: var(--ink-800);
}
.tt-content .tt-sub {
  font-size: .73rem;
  color: var(--ink-300);
  margin-top: 1px;
}

/* ═══════════════════════════════════════════════
   TRANSACTION SECTION
═══════════════════════════════════════════════ */
.tx-tabs {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 3px;
  background: var(--ink-50);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  margin-bottom: 18px;
  width: fit-content;
}
.tx-tab {
  padding: 6px 14px;
  border-radius: calc(var(--r-sm) - 2px);
  font-size: .77rem;
  font-weight: 700;
  color: var(--ink-400);
  transition: all var(--transition);
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 5px;
}
.tx-tab .cnt {
  font-size: .65rem;
  background: var(--ink-100);
  color: var(--ink-400);
  padding: 1px 5px;
  border-radius: 6px;
}
.tx-tab.active {
  background: var(--white);
  color: var(--brand);
  box-shadow: var(--sh-xs);
}
.tx-tab.active .cnt { background: var(--brand-dim); color: var(--brand); }
.tx-tab:hover:not(.active) { color: var(--ink-700); }

.tx-pane { display: none; }
.tx-pane.active { display: block; animation: fadeUp .18s both; }
@keyframes fadeUp { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }

/* Transaction row */
.tx-list { display: flex; flex-direction: column; gap: 6px; }
.tx-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  transition: all var(--transition);
}
.tx-row:hover { background: var(--white); box-shadow: var(--sh-xs); border-color: var(--ink-200); }
.tx-type-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.tx-type-dot.cr { background: var(--green); box-shadow: 0 0 0 3px var(--green-dim); }
.tx-type-dot.db { background: var(--red);   box-shadow: 0 0 0 3px var(--red-dim); }

.tx-party {
  padding: 2px 7px;
  border-radius: var(--r-xs);
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  white-space: nowrap;
}
.tx-party.cl { background: var(--brand-dim);  color: var(--brand); }
.tx-party.sp { background: var(--amber-dim);  color: #b45309; }
.tx-party.ma { background: var(--green-dim);  color: var(--green); }

.tx-body { flex: 1; min-width: 0; }
.tx-type-label { font-size: .82rem; font-weight: 600; color: var(--ink-700); text-transform: capitalize; }
.tx-desc { font-size: .72rem; color: var(--ink-300); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; }

.tx-right { text-align: right; flex-shrink: 0; }
.tx-amount {
  font-family: var(--font-mono);
  font-size: .88rem;
  font-weight: 700;
}
.tx-amount.cr { color: var(--green); }
.tx-amount.db { color: var(--red); }
.tx-date { font-size: .7rem; color: var(--ink-300); margin-top: 2px; }

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 32px 20px;
  gap: 8px;
}
.empty-state i { font-size: 1.6rem; color: var(--ink-200); }
.empty-state p { font-size: .82rem; color: var(--ink-300); }

/* ═══════════════════════════════════════════════
   DOCUMENT CARDS
═══════════════════════════════════════════════ */
.doc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.doc-item {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  transition: all var(--transition);
  cursor: pointer;
  user-select: none;
}
.doc-item:hover {
  border-color: var(--brand);
  box-shadow: 0 4px 16px var(--brand-glow);
  transform: translateY(-2px);
}
.doc-item:active {
  transform: translateY(0);
}

/* Document Preview Modal */
.doc-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(11,15,26,.85);
  backdrop-filter: blur(4px);
  z-index: 9999;
  animation: fadeIn .18s both;
}
.doc-modal.active {
  display: flex;
  align-items: center;
  justify-content: center;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.doc-modal-content {
  background: var(--white);
  border-radius: var(--r-xl);
  max-width: 90vw;
  max-height: 90vh;
  overflow: auto;
  position: relative;
  box-shadow: var(--sh-lg);
  animation: slideUp .25s cubic-bezier(.4,0,.2,1) both;
}
@keyframes slideUp { from { transform: translateY(40px); opacity: 0; } to { transform: none; opacity: 1; } }

.doc-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--surface-2);
  position: sticky;
  top: 0;
}

.doc-modal-title {
  font-weight: 700;
  font-size: .9rem;
  color: var(--ink-800);
  margin: 0;
}

.doc-modal-close {
  width: 32px;
  height: 32px;
  border-radius: var(--r-sm);
  border: none;
  background: transparent;
  color: var(--ink-400);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition);
  font-size: 1.2rem;
}
.doc-modal-close:hover {
  background: var(--brand-dim);
  color: var(--brand);
}

.doc-modal-body {
  padding: 20px;
  text-align: center;
}

.doc-modal-body img {
  max-width: 100%;
  max-height: 70vh;
  border-radius: var(--r);
  box-shadow: var(--sh);
}

.doc-modal-body iframe {
  width: 100%;
  height: 70vh;
  border: none;
  border-radius: var(--r);
}

/* Print Styles */
@media print {
  body {
    background: var(--white);
  }
  .page {
    max-width: 100%;
    padding: 0;
  }
  .topbar,
  .header-actions,
  .tx-tabs,
  .doc-grid,
  .bulk-actions-bar,
  .pagination,
  .btn-action,
  .btn-back,
  .btn-full {
    display: none !important;
  }
  .card {
    page-break-inside: avoid;
    margin-bottom: 20px;
    box-shadow: none;
    border: 1px solid #ddd;
  }
  .main-grid {
    grid-template-columns: 1fr !important;
  }
  .info-table td {
    padding: 8px 0;
  }
  .kpi-strip {
    grid-template-columns: repeat(4, 1fr) !important;
  }
  .tx-pane {
    display: block !important;
  }
  .travel-timeline {
    padding: 0;
  }
}
.doc-thumb {
  height: 110px;
  background: var(--ink-100);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.doc-thumb img { max-width: 100%; max-height: 100%; object-fit: cover; }
.doc-thumb i { font-size: 2rem; color: var(--ink-200); }
.doc-foot {
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.doc-foot-label {
  font-size: .75rem;
  font-weight: 700;
  color: var(--ink-700);
}
.doc-foot-icon {
  width: 22px;
  height: 22px;
  border-radius: var(--r-xs);
  background: var(--brand-dim);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .62rem;
  color: var(--brand);
}

/* ═══════════════════════════════════════════════
   PILL BADGES
═══════════════════════════════════════════════ */
.pill-yes { color: var(--green); font-weight: 700; font-size: .82rem; }
.pill-no  { color: var(--red);   font-weight: 700; font-size: .82rem; }

/* ═══════════════════════════════════════════════
   SECTION DIVIDER LABEL
═══════════════════════════════════════════════ */
.section-label {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--ink-400);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-label::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* ═══════════════════════════════════════════════
   STAGGER ANIMATION
═══════════════════════════════════════════════ */
@keyframes slideIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: none; }
}
.anim { animation: slideIn .35s both; }
.anim:nth-child(1) { animation-delay: .04s }
.anim:nth-child(2) { animation-delay: .09s }
.anim:nth-child(3) { animation-delay: .14s }
.anim:nth-child(4) { animation-delay: .19s }
.anim:nth-child(5) { animation-delay: .24s }
.anim:nth-child(6) { animation-delay: .29s }
</style>
</head>
<body>
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
<div class="page">

  <!-- TOP NAV -->
  <div class="topbar anim">
    <nav class="breadcrumb">
      <a href="dashboard.php">Dashboard</a>
      <span>/</span>
      <a href="umrah_search.php">Umrah</a>
      <span>/</span>
      <span class="current">Booking Detail</span>
    </nav>
    <a href="umrah_search.php" class="btn-back">
      <i class="fa fa-arrow-left"></i> Back to Search
    </a>
  </div>

<?php if ($error): ?>
  <div class="card anim" style="background: var(--red-dim); border-color: var(--red); margin-bottom: 20px;">
    <div class="card-body" style="text-align: center; color: var(--red); font-weight: 600;">
      <i class="fa fa-exclamation-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($error) ?>
    </div>
  </div>
<?php elseif ($umrahData): ?>

  <!-- PAGE HEADER -->
  <div class="page-header anim">
    <div class="page-title-block">
      <div class="booking-id">UMR-<?= str_pad($umrahData['booking_id'], 6, '0', STR_PAD_LEFT) ?></div>
      <h1><?= htmlspecialchars($umrahData['name']) ?></h1>
      <div class="subtitle">
        <span>Passport: <strong style="color:var(--ink-600)"><?= htmlspecialchars($umrahData['passport_number'] ?? 'N/A') ?></strong></span>
        <span class="dot-sep"></span>
        <span>Duration: <strong style="color:var(--ink-600)"><?= htmlspecialchars($umrahData['duration'] ?? 'N/A') ?></strong></span>
        <span class="dot-sep"></span>
        <span>Room: <strong style="color:var(--ink-600)"><?= htmlspecialchars($umrahData['room_type'] ?? 'N/A') ?></strong></span>
        <span class="dot-sep"></span>
        <span class="status-chip <?= strtolower($umrahData['status'] ?? 'pending') ?>"><i class="fa fa-circle"></i> <?= ucfirst($umrahData['status'] ?? 'Pending') ?></span>
      </div>
    </div>
    <div class="header-actions">
      <button class="btn-action primary" onclick="printBookingDetails()"><i class="fa fa-print"></i> Print Booking</button>
    </div>
  </div>

  <!-- KPI STRIP -->
  <div class="kpi-strip anim">
    <div class="kpi-cell blue">
      <div class="kpi-label">Sold Price</div>
      <div class="kpi-value blue"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['sold_price'] ?? 0, 0) ?></div>
      <div class="kpi-meta">Total booking value</div>
    </div>
    <div class="kpi-cell green">
      <div class="kpi-label">Amount Paid</div>
      <div class="kpi-value green"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['paid'] ?? 0, 0) ?></div>
      <div class="kpi-meta"><span class="up">▲ <?= $umrahData['sold_price'] > 0 ? number_format(($umrahData['paid'] / $umrahData['sold_price']) * 100, 1) : 0 ?>%</span>&nbsp;collected</div>
    </div>
    <div class="kpi-cell red">
      <div class="kpi-label">Amount Due</div>
      <div class="kpi-value red"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format(($umrahData['due'] ?? 0), 0) ?></div>
      <div class="kpi-meta"><span class="down">Outstanding balance</span></div>
    </div>
    <div class="kpi-cell amber">
      <div class="kpi-label">Net Profit</div>
      <div class="kpi-value" style="color:var(--amber)"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['profit'] ?? 0, 0) ?></div>
      <div class="kpi-meta">Margin: <span class="up"><?= $umrahData['sold_price'] > 0 ? number_format(($umrahData['profit'] / $umrahData['sold_price']) * 100, 1) : 0 ?>%</span></div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="main-grid">

    <!-- ══ LEFT COLUMN ══ -->
    <div class="left-col">

      <!-- Booking Details -->
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon blue"><i class="fa fa-clipboard-list"></i></div>
            <span class="card-title">Booking Information</span>
          </div>
          <span class="card-badge">Booking #<?= str_pad($umrahData['booking_id'], 6, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="card-body">
          <div class="info-grid">
            <table class="info-table">
              <tr>
                <td>Pilgrim</td>
                <td><?= htmlspecialchars($umrahData['name']) ?></td>
              </tr>
              <tr>
                <td>Passport</td>
                <td style="font-family:var(--font-mono);font-size:.8rem;"><?= htmlspecialchars($umrahData['passport_number'] ?? 'N/A') ?></td>
              </tr>
              <tr>
                <td>Date of Birth</td>
                <td><?= !empty($umrahData['dob']) ? date('d M Y', strtotime($umrahData['dob'])) : 'N/A' ?></td>
              </tr>
              <tr>
                <td>Family</td>
                <td><?= htmlspecialchars($umrahData['family_name'] ?? 'N/A') ?></td>
              </tr>
              <tr>
                <td>Room Type</td>
                <td><?= htmlspecialchars($umrahData['room_type'] ?? 'N/A') ?></td>
              </tr>
            </table>
            <table class="info-table">
              <tr>
                <td>Flight Date</td>
                <td><?= !empty($umrahData['flight_date']) ? date('d M Y', strtotime($umrahData['flight_date'])) : 'N/A' ?></td>
              </tr>
              <tr>
                <td>Return Date</td>
                <td><?= !empty($umrahData['return_date']) ? date('d M Y', strtotime($umrahData['return_date'])) : 'N/A' ?></td>
              </tr>
              <tr>
                <td>Entry Date</td>
                <td><?= !empty($umrahData['entry_date']) ? date('d M Y', strtotime($umrahData['entry_date'])) : 'N/A' ?></td>
              </tr>
              <tr>
                <td>Cost Price</td>
                <td style="font-family:var(--font-mono);font-size:.8rem;"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['price'] ?? 0, 2) ?></td>
              </tr>
              <tr>
                <td>Bank Payment</td>
                <td>
                  <?php if (!empty($umrahData['received_bank_payment'])): ?>
                  <span class="pill-yes">✓ Received</span>
                  <span style="font-family:var(--font-mono);font-size:.73rem;color:var(--ink-400);margin-left:6px;">#<?= htmlspecialchars($umrahData['bank_receipt_number'] ?? $umrahData['booking_id']) ?></span>
                  <?php else: ?>
                  <span class="pill-no">✗ Pending</span>
                  <?php endif; ?>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Supplier Services -->
      <?php if (!empty($umrahData['supplier_services'])): ?>
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon amber"><i class="fa fa-building"></i></div>
            <span class="card-title">Supplier Services</span>
          </div>
          <span class="card-badge"><?= count($umrahData['supplier_services']) ?> services</span>
        </div>
        <div class="card-body flush">
          <table class="data-table">
            <thead>
              <tr>
                <th>Service</th>
                <th>Supplier</th>
                <th>Cost</th>
                <th>Sold</th>
                <th>Profit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($umrahData['supplier_services'] as $svc): 
                $profit = ($svc['sold_price'] ?? 0) - ($svc['base_price'] ?? 0);
              ?>
              <tr>
                <td><span class="tag <?= strtolower($svc['service_type'] ?? 'default') ?>"><?= htmlspecialchars($svc['service_type']) ?></span></td>
                <td><a href="#" class="link-cell"><i class="fa fa-external-link-alt"></i><?= htmlspecialchars($svc['supplier_name'] ?? 'N/A') ?></a></td>
                <td class="num"><?= htmlspecialchars($svc['currency'] ?? 'USD') ?> <?= number_format($svc['base_price'] ?? 0, 0) ?></td>
                <td class="num"><?= htmlspecialchars($svc['currency'] ?? 'USD') ?> <?= number_format($svc['sold_price'] ?? 0, 0) ?></td>
                <td class="num <?= $profit >= 0 ? 'profit-pos' : 'profit-neg' ?>"><?= $profit >= 0 ? '+' : '-' ?><?= htmlspecialchars($svc['currency'] ?? 'USD') ?> <?= number_format(abs($profit), 0) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Transaction History -->
      <?php 
      $allTx = array_merge($clientTransactions, $supplierTransactions, $mainAccountTransactions);
      usort($allTx, fn($a, $b) => strtotime($b['transaction_date'] ?? 0) - strtotime($a['transaction_date'] ?? 0));
      $totalTx = count($allTx);
      ?>
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon green"><i class="fa fa-receipt"></i></div>
            <span class="card-title">Transaction History</span>
          </div>
          <span class="card-badge"><?= $totalTx ?> total</span>
        </div>
        <div class="card-body">
          <!-- Tabs -->
          <div class="tx-tabs">
            <button class="tx-tab active" onclick="switchTab('tx-all',this)">All <span class="cnt"><?= $totalTx ?></span></button>
            <button class="tx-tab" onclick="switchTab('tx-client',this)">Client <span class="cnt"><?= count($clientTransactions) ?></span></button>
            <button class="tx-tab" onclick="switchTab('tx-supplier',this)">Supplier <span class="cnt"><?= count($supplierTransactions) ?></span></button>
            <button class="tx-tab" onclick="switchTab('tx-main',this)">Main Acct <span class="cnt"><?= count($mainAccountTransactions) ?></span></button>
          </div>

          <!-- All -->
          <div class="tx-pane active" id="tx-all">
            <?php if (empty($allTx)): ?>
              <div class="empty-state"><i class="fa fa-inbox"></i><p>No transactions found.</p></div>
            <?php else: ?>
            <div class="tx-list">
              <?php foreach ($allTx as $t): 
                $isDebit = strtolower($t['type'] ?? '') === 'debit';
                $sign = $isDebit ? '−' : '+';
                $cls  = $isDebit ? 'db' : 'cr';
                $cur  = isset($t['currency']) && $t['currency'] ? htmlspecialchars($t['currency']) . ' ' : '';
                $party = $t['transaction_type'] ?? 'Unknown';
                $bc = $party === 'Client' ? 'cl' : ($party === 'Supplier' ? 'sp' : 'ma');
                $desc = htmlspecialchars($t['description'] ?? '');
                $type = ucfirst(strtolower(htmlspecialchars($t['type'] ?? '')));
                $date = date('d M Y', strtotime($t['transaction_date'] ?? now()));
                $amt  = number_format($t['amount'] ?? 0, 2);
              ?>
              <div class="tx-row">
                <div class="tx-type-dot <?= $cls ?>"></div>
                <span class="tx-party <?= $bc ?>"><?= htmlspecialchars($party) ?></span>
                <div class="tx-body">
                  <div class="tx-type-label"><?= $type ?></div>
                  <?php if ($desc): ?><div class="tx-desc"><?= $desc ?></div><?php endif; ?>
                </div>
                <div class="tx-right">
                  <div class="tx-amount <?= $cls ?>"><?= $sign . $cur . $amt ?></div>
                  <div class="tx-date"><?= $date ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Client only -->
          <div class="tx-pane" id="tx-client">
            <?php if (empty($clientTransactions)): ?>
              <div class="empty-state"><i class="fa fa-inbox"></i><p>No client transactions.</p></div>
            <?php else: ?>
            <div class="tx-list">
              <?php foreach ($clientTransactions as $t): 
                $isDebit = strtolower($t['type'] ?? '') === 'debit';
                $sign = $isDebit ? '−' : '+';
                $cls  = $isDebit ? 'db' : 'cr';
                $cur  = isset($t['currency']) && $t['currency'] ? htmlspecialchars($t['currency']) . ' ' : '';
                $desc = htmlspecialchars($t['description'] ?? '');
                $type = ucfirst(strtolower(htmlspecialchars($t['type'] ?? '')));
                $date = date('d M Y', strtotime($t['transaction_date'] ?? now()));
                $amt  = number_format($t['amount'] ?? 0, 2);
              ?>
              <div class="tx-row">
                <div class="tx-type-dot <?= $cls ?>"></div>
                <span class="tx-party cl">Client</span>
                <div class="tx-body"><div class="tx-type-label"><?= $type ?></div><?php if ($desc): ?><div class="tx-desc"><?= $desc ?></div><?php endif; ?></div>
                <div class="tx-right"><div class="tx-amount <?= $cls ?>"><?= $sign . $cur . $amt ?></div><div class="tx-date"><?= $date ?></div></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Supplier only -->
          <div class="tx-pane" id="tx-supplier">
            <?php if (empty($supplierTransactions)): ?>
              <div class="empty-state"><i class="fa fa-inbox"></i><p>No supplier transactions.</p></div>
            <?php else: ?>
            <div class="tx-list">
              <?php foreach ($supplierTransactions as $t): 
                $isDebit = strtolower($t['type'] ?? '') === 'debit';
                $sign = $isDebit ? '−' : '+';
                $cls  = $isDebit ? 'db' : 'cr';
                $desc = htmlspecialchars($t['description'] ?? '');
                $type = ucfirst(strtolower(htmlspecialchars($t['type'] ?? '')));
                $date = date('d M Y', strtotime($t['transaction_date'] ?? now()));
                $amt  = number_format($t['amount'] ?? 0, 2);
              ?>
              <div class="tx-row">
                <div class="tx-type-dot <?= $cls ?>"></div>
                <span class="tx-party sp">Supplier</span>
                <div class="tx-body"><div class="tx-type-label"><?= $type ?></div><?php if ($desc): ?><div class="tx-desc"><?= $desc ?></div><?php endif; ?></div>
                <div class="tx-right"><div class="tx-amount <?= $cls ?>"><?= $sign . $amt ?></div><div class="tx-date"><?= $date ?></div></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Main Account -->
          <div class="tx-pane" id="tx-main">
            <?php if (empty($mainAccountTransactions)): ?>
              <div class="empty-state"><i class="fa fa-inbox"></i><p>No main account transactions.</p></div>
            <?php else: ?>
            <div class="tx-list">
              <?php foreach ($mainAccountTransactions as $t): 
                $isDebit = strtolower($t['type'] ?? '') === 'debit';
                $sign = $isDebit ? '−' : '+';
                $cls  = $isDebit ? 'db' : 'cr';
                $cur  = isset($t['currency']) && $t['currency'] ? htmlspecialchars($t['currency']) . ' ' : '';
                $desc = htmlspecialchars($t['description'] ?? '');
                $type = ucfirst(strtolower(htmlspecialchars($t['type'] ?? '')));
                $date = date('d M Y', strtotime($t['transaction_date'] ?? now()));
                $amt  = number_format($t['amount'] ?? 0, 2);
              ?>
              <div class="tx-row">
                <div class="tx-type-dot <?= $cls ?>"></div>
                <span class="tx-party ma">Main Acct</span>
                <div class="tx-body"><div class="tx-type-label"><?= $type ?></div><?php if ($desc): ?><div class="tx-desc"><?= $desc ?></div><?php endif; ?></div>
                <div class="tx-right"><div class="tx-amount <?= $cls ?>"><?= $sign . $cur . $amt ?></div><div class="tx-date"><?= $date ?></div></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- Documents -->
      <?php
      $basePath = '/almoqadas/mtravels';
      $docPhoto = !empty($umrahData['photo_path']) ? $basePath . $umrahData['photo_path'] : '';
      $docPassport = !empty($umrahData['passport_path']) ? $basePath . $umrahData['passport_path'] : '';
      $docVisa = !empty($umrahData['visa_path']) ? $basePath . $umrahData['visa_path'] : '';
      $hasDocs = !empty($docPhoto) || !empty($docPassport) || !empty($docVisa);
      $docCount = ($docPhoto ? 1 : 0) + ($docPassport ? 1 : 0) + ($docVisa ? 1 : 0);
      ?>
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon purple"><i class="fa fa-file-alt"></i></div>
            <span class="card-title">Uploaded Documents</span>
          </div>
          <span class="card-badge"><?= $docCount ?> files</span>
        </div>
        <div class="card-body">
          <?php if ($hasDocs): ?>
          <div class="doc-grid">
            <?php if (!empty($docPhoto)): ?>
            <div class="doc-item" onclick="openDocPreview('<?= htmlspecialchars($docPhoto) ?>', 'Photo')">
              <div class="doc-thumb" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe);">
                <i class="fa fa-user-circle" style="color:#818cf8;font-size:2.8rem;"></i>
              </div>
              <div class="doc-foot">
                <span class="doc-foot-label">Photo</span>
                <div class="doc-foot-icon"><i class="fa fa-eye"></i></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($docPassport)): ?>
            <div class="doc-item" onclick="openDocPreview('<?= htmlspecialchars($docPassport) ?>', 'Passport')">
              <div class="doc-thumb" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);">
                <i class="fa fa-book-open" style="color:#34d399;font-size:2.8rem;"></i>
              </div>
              <div class="doc-foot">
                <span class="doc-foot-label">Passport</span>
                <div class="doc-foot-icon"><i class="fa fa-eye"></i></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($docVisa)): ?>
            <div class="doc-item" onclick="openDocPreview('<?= htmlspecialchars($docVisa) ?>', 'Visa')">
              <div class="doc-thumb" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                <i class="fa fa-file-pdf" style="color:#f59e0b;font-size:2.8rem;"></i>
              </div>
              <div class="doc-foot">
                <span class="doc-foot-label">Visa</span>
                <div class="doc-foot-icon"><i class="fa fa-eye"></i></div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="empty-state"><i class="fa fa-file-upload"></i><p>No documents uploaded</p></div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /left -->

    <!-- ══ RIGHT COLUMN ══ -->
    <div class="right-col">

      <!-- Client Card -->
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon blue"><i class="fa fa-user"></i></div>
            <span class="card-title">Booking Agent</span>
          </div>
        </div>
        <div class="client-card-inner">
          <div class="client-avatar-row">
            <div class="avatar-circle"><?= strtoupper(substr($umrahData['client_name'] ?? 'N/A', 0, 2)) ?></div>
            <div class="client-name-block">
              <div class="name"><?= htmlspecialchars($umrahData['client_name'] ?? 'N/A') ?></div>
              <div class="email"><?= htmlspecialchars($umrahData['client_email'] ?? 'N/A') ?></div>
            </div>
          </div>
          <div class="client-detail-rows">
            <div class="cdr">
              <div class="cdr-icon"><i class="fa fa-phone"></i></div>
              <span class="cdr-label">Phone</span>
              <span class="cdr-val"><?= htmlspecialchars($umrahData['client_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="cdr">
              <div class="cdr-icon"><i class="fa fa-users"></i></div>
              <span class="cdr-label">Family</span>
              <span class="cdr-val"><?= htmlspecialchars($umrahData['family_name'] ?? 'N/A') ?></span>
            </div>
            <div class="cdr">
              <div class="cdr-icon"><i class="fa fa-tag"></i></div>
              <span class="cdr-label">Member Type</span>
              <span class="cdr-val"><?= htmlspecialchars($umrahData['relation'] ?? 'N/A') ?></span>
            </div>
          </div>
          <a href="client_detail.php?id=<?= htmlspecialchars($umrahData['sold_to'] ?? '') ?>" class="btn-full">
            <i class="fa fa-external-link-alt"></i> View Client Profile
          </a>
        </div>
      </div>

      <!-- Travel Timeline -->
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon amber"><i class="fa fa-route"></i></div>
            <span class="card-title">Travel Timeline</span>
          </div>
        </div>
        <div class="travel-timeline">
          <div class="tt-item">
            <div class="tt-dot active"><i class="fa fa-plane-departure"></i></div>
            <div class="tt-content">
              <div class="tt-label">Departure</div>
              <div class="tt-date"><?= !empty($umrahData['flight_date']) ? date('d F Y', strtotime($umrahData['flight_date'])) : 'N/A' ?></div>
              <div class="tt-sub">Jeddah International Airport</div>
            </div>
          </div>
          <div class="tt-item">
            <div class="tt-dot"><i class="fa fa-hotel"></i></div>
            <div class="tt-content">
              <div class="tt-label">Check-in</div>
              <div class="tt-date"><?= !empty($umrahData['entry_date']) ? date('d F Y', strtotime($umrahData['entry_date'])) : 'N/A' ?></div>
              <div class="tt-sub"><?= htmlspecialchars($umrahData['room_type'] ?? 'N/A') ?></div>
            </div>
          </div>
          <div class="tt-item">
            <div class="tt-dot"><i class="fa fa-mosque"></i></div>
            <div class="tt-content">
              <div class="tt-label">Umrah Period</div>
              <div class="tt-date"><?= !empty($umrahData['flight_date']) && !empty($umrahData['return_date']) ? date('d M', strtotime($umrahData['flight_date'])) . ' – ' . date('d M Y', strtotime($umrahData['return_date'])) : 'N/A' ?></div>
              <div class="tt-sub"><?= htmlspecialchars($umrahData['duration'] ?? '0') ?> pilgrimage</div>
            </div>
          </div>
          <div class="tt-item">
            <div class="tt-dot"><i class="fa fa-plane-arrival"></i></div>
            <div class="tt-content">
              <div class="tt-label">Return</div>
              <div class="tt-date"><?= !empty($umrahData['return_date']) ? date('d F Y', strtotime($umrahData['return_date'])) : 'N/A' ?></div>
              <div class="tt-sub">Back to origin</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Progress -->
      <div class="card anim">
        <div class="card-head">
          <div class="card-head-left">
            <div class="card-icon green"><i class="fa fa-wallet"></i></div>
            <span class="card-title">Payment Summary</span>
          </div>
        </div>
        <div class="card-body">
          <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
              <span style="font-size:.75rem;font-weight:700;color:var(--ink-400);text-transform:uppercase;letter-spacing:.06em;">Collection Progress</span>
              <span style="font-size:.8rem;font-weight:700;color:var(--green);font-family:var(--font-mono);"><?= $umrahData['sold_price'] > 0 ? number_format(($umrahData['paid'] / $umrahData['sold_price']) * 100, 1) : 0 ?>%</span>
            </div>
            <div style="height:6px;background:var(--ink-100);border-radius:3px;overflow:hidden;">
              <div style="width:<?= $umrahData['sold_price'] > 0 ? number_format(($umrahData['paid'] / $umrahData['sold_price']) * 100, 1) : 0 ?>%;height:100%;background:linear-gradient(90deg,var(--brand),var(--green));border-radius:3px;transition:width .6s cubic-bezier(.4,0,.2,1);"></div>
            </div>
          </div>
          <table class="info-table">
            <tr>
              <td>Total Price</td>
              <td style="font-family:var(--font-mono);font-size:.8rem;font-weight:700;"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['sold_price'] ?? 0, 2) ?></td>
            </tr>
            <tr>
              <td>Paid</td>
              <td style="font-family:var(--font-mono);font-size:.8rem;font-weight:700;color:var(--green);"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['paid'] ?? 0, 2) ?></td>
            </tr>
            <tr>
              <td>Outstanding</td>
              <td style="font-family:var(--font-mono);font-size:.8rem;font-weight:700;color:var(--red);"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format(($umrahData['due'] ?? 0), 2) ?></td>
            </tr>
            <tr>
              <td>Net Profit</td>
              <td style="font-family:var(--font-mono);font-size:.8rem;font-weight:700;color:var(--amber);"><?= htmlspecialchars($umrahData['currency'] ?? 'USD') ?> <?= number_format($umrahData['profit'] ?? 0, 2) ?></td>
            </tr>
          </table>
        </div>
      </div>

    </div><!-- /right -->
  </div><!-- /main-grid -->

<?php endif; ?>
</div><!-- /page -->

<!-- Document Preview Modal -->
<div id="docModal" class="doc-modal">
  <div class="doc-modal-content">
    <div class="doc-modal-header">
      <h3 class="doc-modal-title" id="docModalTitle">Document</h3>
      <button class="doc-modal-close" onclick="closeDocPreview()"><i class="fa fa-times"></i></button>
    </div>
    <div class="doc-modal-body" id="docModalBody">
      <!-- Content will be injected here -->
    </div>
  </div>
</div>

         

  </div>
</div>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function switchTab(id, btn) {
  document.querySelectorAll('.tx-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tx-tab').forEach(b => b.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}

function printBookingDetails() {
  // Show all transaction panes before printing
  const allPanes = document.querySelectorAll('.tx-pane');
  const activePanes = document.querySelectorAll('.tx-pane.active');
  allPanes.forEach(pane => pane.style.display = 'block');
  
  // Set document title for print
  const originalTitle = document.title;
  document.title = 'Umrah Booking Detail - ' + new Date().toLocaleDateString();
  
  // Print
  window.print();
  
  // Restore
  setTimeout(() => {
    document.title = originalTitle;
    // Restore original state
    allPanes.forEach(pane => pane.style.display = '');
    if (activePanes.length > 0) {
      activePanes[0].style.display = 'block';
    }
  }, 1000);
}

function openDocPreview(path, title) {
  const modal = document.getElementById('docModal');
  const modalTitle = document.getElementById('docModalTitle');
  const modalBody = document.getElementById('docModalBody');
  
  modalTitle.textContent = title;
  
  // Check file extension
  const ext = path.toLowerCase().split('.').pop();
  const isPdf = ext === 'pdf';
  const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
  
  if (isPdf) {
    modalBody.innerHTML = `<iframe src="${path}" style="width:100%;height:70vh;border:none;"></iframe>`;
  } else if (isImage) {
    modalBody.innerHTML = `<img src="${path}" alt="${title}" style="max-width:100%;max-height:70vh;border-radius:10px;">`;
  } else {
    modalBody.innerHTML = `<div style="padding:40px;color:var(--ink-400);"><i class="fa fa-file" style="font-size:3rem;margin-bottom:16px;display:block;"></i><p>Preview not available for this file type</p><a href="${path}" target="_blank" class="btn-action primary" style="display:inline-block;margin-top:12px;"><i class="fa fa-download"></i> Download File</a></div>`;
  }
  
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDocPreview() {
  const modal = document.getElementById('docModal');
  modal.classList.remove('active');
  document.body.style.overflow = '';
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('docModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeDocPreview();
      }
    });
  }
  
  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeDocPreview();
    }
  });
});
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
