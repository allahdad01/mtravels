<?php
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once 'security.php';
require_once '../includes/db.php';

enforce_auth();
require_permission('operations.clients');

require_once '../includes/InputValidator.php';

// Initialize variables
$clientId = InputValidator::getInt($_GET['id'] ?? '', 0, 1);
$clientData = null;
$transactions = [];
$error = null;

if (!$clientId) {
    $error = "No client ID provided";
} else {
    $clientQuery = "SELECT id, image, name, email, phone, usd_balance, afs_balance, address, created_at, updated_at, client_type, status FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($clientQuery);
    $stmt->execute([$clientId, $tenant_id, $branch_id]);
    $clientData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clientData) {
        $error = "Client not found";
    } else {
        $transactionsQuery = "SELECT
                ct.id, ct.client_id, ct.amount, ct.currency, ct.type,
                ct.description, ct.reference_id, ct.transaction_of,
                ct.created_at AS transaction_date
            FROM client_transactions ct
            WHERE ct.client_id = ? AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.created_at DESC";
        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$clientId, $tenant_id, $branch_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Helper: initials from name
function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= strtoupper(mb_substr($p, 0, 1));
    }
    return $initials ?: '??';
}

// Booking count helper — returns int
function bookingCount($pdo, $table, $field, $value, $tenant_id, $branch_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $field = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$value, $tenant_id, $branch_id]);
    return (int)$stmt->fetchColumn();
}

// Transaction-type count helper
function txnCount($pdo, $clientId, $transaction_of, $tenant_id, $branch_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$clientId, $transaction_of, $tenant_id, $branch_id]);
    return (int)$stmt->fetchColumn();
}

if (!$error) {
    // Pre-fetch all counts
    $cnt = [
        'tickets'          => bookingCount($pdo, 'ticket_bookings',  'sold_to', $clientId, $tenant_id, $branch_id),
        'visas'            => bookingCount($pdo, 'visa_applications', 'sold_to', $clientId, $tenant_id, $branch_id),
        'hotels'           => bookingCount($pdo, 'hotel_bookings',    'sold_to', $clientId, $tenant_id, $branch_id),
        'umrah'            => txnCount($pdo, $clientId, 'umrah',            $tenant_id, $branch_id),
        'ticket_refund'    => txnCount($pdo, $clientId, 'ticket_refund',    $tenant_id, $branch_id),
        'visa_refund'      => txnCount($pdo, $clientId, 'visa_refund',      $tenant_id, $branch_id),
        'hotel_refund'     => txnCount($pdo, $clientId, 'hotel_refund',     $tenant_id, $branch_id),
        'umrah_refund'     => txnCount($pdo, $clientId, 'umrah_refund',     $tenant_id, $branch_id),
        'date_change'      => txnCount($pdo, $clientId, 'date_change',      $tenant_id, $branch_id),
        'additional'       => txnCount($pdo, $clientId, 'additional_payment',$tenant_id, $branch_id),
        'reserve'          => txnCount($pdo, $clientId, 'ticket_reserve',   $tenant_id, $branch_id),
        'fund'             => txnCount($pdo, $clientId, 'fund',             $tenant_id, $branch_id),
        'jv'               => txnCount($pdo, $clientId, 'jv_payment',       $tenant_id, $branch_id),
    ];

    // Financial totals by currency
    $stmt = $pdo->prepare("SELECT currency, SUM(amount) as total FROM client_transactions WHERE client_id = ? AND type = 'credit' AND tenant_id = ? AND branch_id = ? GROUP BY currency");
    $stmt->execute([$clientId, $tenant_id, $branch_id]);
    $creditByCurrency = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $creditByCurrency[$row['currency']] = (float)($row['total'] ?: 0);
    }

    $stmt = $pdo->prepare("SELECT currency, SUM(amount) as total FROM client_transactions WHERE client_id = ? AND type = 'debit' AND tenant_id = ? AND branch_id = ? GROUP BY currency");
    $stmt->execute([$clientId, $tenant_id, $branch_id]);
    $debitByCurrency = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $debitByCurrency[$row['currency']] = (float)($row['total'] ?: 0);
    }

    // Use actual client balances as net balance
    $usdNetBalance = (float)$clientData['usd_balance'];
    $afsNetBalance = (float)$clientData['afs_balance'];

    // Totals for all currencies (for backward compatibility in display)
    $totalCredit = (float)(($creditByCurrency['USD'] ?? 0) + ($creditByCurrency['AFS'] ?? 0));
    $totalDebit = (float)(($debitByCurrency['USD'] ?? 0) + ($debitByCurrency['AFS'] ?? 0));
}

include '../includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════ */
:root {
  --bg:       #F4F5F7;
  --surface:  #FFFFFF;
  --surface2: #F8F9FB;

  --ink:  #111827;
  --ink2: #374151;
  --ink3: #6B7280;
  --ink4: #9CA3AF;
  --line: #E5E7EB;
  --line2:#F3F4F6;

  --blue:        #2563EB;
  --blue-light:  #EFF6FF;
  --blue-mid:    #BFDBFE;
  --green:       #10B981;
  --green-light: #ECFDF5;
  --rose:        #F43F5E;
  --rose-light:  #FFF1F2;
  --amber:       #F59E0B;
  --amber-light: #FFFBEB;
  --violet:      #8B5CF6;
  --violet-light:#F5F3FF;
  --teal:        #14B8A6;
  --teal-light:  #F0FDFA;

  --r:    12px;
  --r-lg: 16px;
  --shadow-xs: 0 1px 3px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.04);
  --shadow-sm: 0 4px 6px -1px rgba(0,0,0,.07), 0 2px 4px -1px rgba(0,0,0,.04);
  --shadow-md: 0 10px 15px -3px rgba(0,0,0,.07), 0 4px 6px -2px rgba(0,0,0,.04);
  --t: all .18s ease;
}

/* ═══════════════════════════════════════════
   RESET OVERRIDES (kill Bootstrap noise)
═══════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }

body {
  font-family: 'DM Sans', sans-serif !important;
  background: var(--bg) !important;
  color: var(--ink);
  -webkit-font-smoothing: antialiased;
}

/* Nuke Bootstrap card styles inside our shell */
.cd-shell .card,
.cd-shell .card-header,
.cd-shell .card-body {
  all: unset;
  display: block;
}

.cd-shell .table { all: unset; }
.cd-shell .row   { all: unset; }

/* ═══════════════════════════════════════════
   SHELL
═══════════════════════════════════════════ */
.cd-shell {
  max-width: 1340px;
  margin: 0 auto;
  padding: 36px 28px 60px;
}

/* ═══════════════════════════════════════════
   TOPBAR
═══════════════════════════════════════════ */
.cd-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  gap: 16px;
  flex-wrap: wrap;
}

.cd-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--ink4);
  font-weight: 500;
}
.cd-breadcrumb a { color: var(--ink3); text-decoration: none; transition: color .15s; }
.cd-breadcrumb a:hover { color: var(--blue); }
.cd-breadcrumb .sep { color: var(--line); font-size: 10px; }

.cd-topbar-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

/* ═══════════════════════════════════════════
   BUTTONS
═══════════════════════════════════════════ */
.cd-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  border-radius: var(--r);
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: var(--t);
  text-decoration: none;
  white-space: nowrap;
  line-height: 1;
}
.cd-btn i { font-size: 11px; }

.cd-btn-ghost {
  background: var(--surface);
  color: var(--ink2);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
}
.cd-btn-ghost:hover { background: var(--surface2); border-color: var(--ink3); color: var(--ink); }

.cd-btn-primary {
  background: var(--blue);
  color: #fff;
  box-shadow: 0 4px 12px rgba(37,99,235,.25);
}
.cd-btn-primary:hover {
  background: #1d4ed8;
  box-shadow: 0 6px 16px rgba(37,99,235,.35);
  transform: translateY(-1px);
  color: #fff;
  text-decoration: none;
}

.cd-btn-danger {
  background: var(--rose-light);
  color: var(--rose);
}
.cd-btn-danger:hover {
  background: var(--rose);
  color: #fff;
  transform: translateY(-1px);
}

/* ═══════════════════════════════════════════
   PAGE TITLE ROW
═══════════════════════════════════════════ */
.cd-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}

.cd-title-row h1 {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -.03em;
  color: var(--ink);
  margin: 0;
  line-height: 1;
}

.cd-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 11px;
  border-radius: 99px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: .02em;
}
.cd-status-pill.active   { background: var(--green-light); color: var(--green); }
.cd-status-pill.inactive { background: var(--rose-light);  color: var(--rose); }
.cd-status-pill.pending  { background: var(--amber-light); color: var(--amber); }
.cd-status-pill.active::before {
  content:''; display:inline-block; width:6px; height:6px;
  border-radius:50%; background:var(--green); animation: cd-blink 2s infinite;
}
@keyframes cd-blink { 0%,100%{opacity:1} 50%{opacity:.35} }

/* ═══════════════════════════════════════════
   LAYOUT: SIDEBAR + MAIN
═══════════════════════════════════════════ */
.cd-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 24px;
  align-items: start;
}

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.cd-sidebar { display: flex; flex-direction: column; gap: 18px; }

/* Profile card */
.cd-profile-card {
  background: var(--surface);
  border-radius: var(--r-lg);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
  animation: cd-fadeUp .4s ease both;
}

.cd-profile-header {
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #3b82f6 100%);
  padding: 28px 24px 52px;
  text-align: center;
  position: relative;
}
.cd-profile-header::after {
  content: '';
  position: absolute;
  bottom: -1px; left: 0; right: 0;
  height: 38px;
  background: var(--surface);
  border-radius: 50% 50% 0 0 / 38px 38px 0 0;
}

.cd-avatar {
  width: 80px; height: 80px;
  border-radius: 50%;
  border: 3px solid rgba(255,255,255,.35);
  background: rgba(255,255,255,.18);
  backdrop-filter: blur(8px);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  font-weight: 700;
  color: #fff;
  font-family: 'DM Mono', monospace;
  overflow: hidden;
}
.cd-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }

.cd-profile-body {
  padding: 16px 22px 22px;
  text-align: center;
}
.cd-client-name {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.02em;
  margin: 0 0 5px;
}
.cd-type-tag {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 700;
  background: var(--blue-light);
  color: var(--blue);
  padding: 3px 10px;
  border-radius: 99px;
  letter-spacing: .05em;
  text-transform: uppercase;
  margin-bottom: 18px;
}

.cd-contact { display: flex; flex-direction: column; gap: 8px; text-align: left; }

.cd-contact-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 11px;
  background: var(--bg);
  border-radius: 9px;
}
.cd-contact-icon {
  width: 28px; height: 28px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px;
  flex-shrink: 0;
}
.cd-contact-icon.blue   { background: var(--blue-light);   color: var(--blue); }
.cd-contact-icon.green  { background: var(--green-light);  color: var(--green); }
.cd-contact-icon.violet { background: var(--violet-light); color: var(--violet); }
.cd-contact-label { font-size: 10px; color: var(--ink4); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 1px; }
.cd-contact-text  { font-size: 12.5px; color: var(--ink2); font-weight: 500; word-break: break-all; }

/* Balance card */
.cd-balance-card {
  background: var(--surface);
  border-radius: var(--r-lg);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  padding: 18px 20px;
  animation: cd-fadeUp .4s ease .06s both;
}

.cd-card-subtitle {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--ink4);
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 7px;
}
.cd-card-subtitle i { color: var(--blue); font-size: 10.5px; }

.cd-balance-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 0;
  border-bottom: 1px solid var(--line2);
}
.cd-balance-row:first-of-type { padding-top: 0; }
.cd-balance-row:last-of-type  { border-bottom: none; padding-bottom: 0; }

.cd-balance-left { display: flex; align-items: center; gap: 9px; }
.cd-currency-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  font-family: 'DM Mono', monospace;
  flex-shrink: 0;
}
.cd-currency-icon.usd { background: var(--blue-light);  color: var(--blue); }
.cd-currency-icon.afs { background: var(--green-light); color: var(--green); }
.cd-balance-lbl { font-size: 12px; color: var(--ink3); font-weight: 500; }
.cd-balance-amt {
  font-size: 14.5px;
  font-weight: 700;
  font-family: 'DM Mono', monospace;
}
.cd-balance-amt.owed  { color: var(--rose); }
.cd-balance-amt.clear { color: var(--green); }

/* Meta card */
.cd-meta-card {
  background: var(--surface);
  border-radius: var(--r-lg);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  padding: 18px 20px;
  animation: cd-fadeUp .4s ease .12s both;
}
.cd-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 9px 0;
  border-bottom: 1px solid var(--line2);
  gap: 10px;
}
.cd-meta-row:first-of-type { padding-top: 0; }
.cd-meta-row:last-of-type  { border-bottom: none; padding-bottom: 0; }
.cd-meta-key { font-size: 11.5px; color: var(--ink4); font-weight: 500; white-space: nowrap; }
.cd-meta-val { font-size: 12.5px; color: var(--ink2); font-weight: 500; text-align: right; }

/* ═══════════════════════════════════════════
   SECTION CARDS (main column)
═══════════════════════════════════════════ */
.cd-main { display: flex; flex-direction: column; gap: 22px; }

.cd-section {
  background: var(--surface);
  border-radius: var(--r-lg);
  border: 1px solid var(--line);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
  transition: box-shadow .2s;
}
.cd-section:hover { box-shadow: var(--shadow-sm); }

.cd-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 17px 22px;
  border-bottom: 1px solid var(--line);
}
.cd-section-head-left { display: flex; align-items: center; gap: 10px; }

.cd-section-icon {
  width: 32px; height: 32px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
}
.cd-section-icon.blue   { background: var(--blue-light);   color: var(--blue); }
.cd-section-icon.violet { background: var(--violet-light); color: var(--violet); }

.cd-section-title { font-size: 14px; font-weight: 700; color: var(--ink); letter-spacing: -.01em; }
.cd-section-sub   { font-size: 11.5px; color: var(--ink4); font-weight: 400; margin-top: 1px; }

.cd-section-body { padding: 20px 22px; }

/* ═══════════════════════════════════════════
   STAT GROUPS
═══════════════════════════════════════════ */
.cd-stat-group { margin-bottom: 20px; }
.cd-stat-group:last-child { margin-bottom: 0; }

.cd-stat-group-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--ink4);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-bottom: 8px;
  border-bottom: 1px dashed var(--line);
}
.cd-stat-group-label i { font-size: 10px; color: var(--blue); }

.cd-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.cd-stat-mini {
  background: var(--surface2);
  border: 1px solid var(--line);
  border-radius: var(--r);
  padding: 14px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 3px;
  transition: var(--t);
  position: relative;
  overflow: hidden;
}
.cd-stat-mini::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2.5px;
  border-radius: 0 0 4px 4px;
}
.cd-stat-mini:hover { background: var(--surface); box-shadow: var(--shadow-sm); transform: translateY(-2px); }

/* Colour variants */
.cs-blue::before   { background: var(--blue); }
.cs-green::before  { background: var(--green); }
.cs-violet::before { background: var(--violet); }
.cs-amber::before  { background: var(--amber); }
.cs-rose::before   { background: var(--rose); }
.cs-teal::before   { background: var(--teal); }

.cd-stat-mini-icon {
  width: 26px; height: 26px;
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px;
  margin-bottom: 3px;
}
.cs-blue   .cd-stat-mini-icon { background: var(--blue-light);   color: var(--blue); }
.cs-green  .cd-stat-mini-icon { background: var(--green-light);  color: var(--green); }
.cs-violet .cd-stat-mini-icon { background: var(--violet-light); color: var(--violet); }
.cs-amber  .cd-stat-mini-icon { background: var(--amber-light);  color: var(--amber); }
.cs-rose   .cd-stat-mini-icon { background: var(--rose-light);   color: var(--rose); }
.cs-teal   .cd-stat-mini-icon { background: var(--teal-light);   color: var(--teal); }

.cd-stat-mini-val {
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  font-family: 'DM Mono', monospace;
  letter-spacing: -.02em;
  line-height: 1;
}
.cd-stat-mini-lbl {
  font-size: 10px;
  color: var(--ink4);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
}

/* ═══════════════════════════════════════════
   FINANCIAL STRIP
═══════════════════════════════════════════ */
.cd-fin-strip {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr auto;
  gap: 1px;
  background: var(--line);
  border-top: 1px solid var(--line);
}
.cd-fin-cell {
  background: var(--surface);
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.cd-fin-cell:last-child {
  background: var(--surface2);
  align-items: center;
  justify-content: center;
  padding: 18px 24px;
}
.cd-fin-label { font-size: 10.5px; color: var(--ink4); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.cd-fin-val   { font-size: 19px; font-weight: 700; font-family: 'DM Mono', monospace; letter-spacing: -.02em; line-height: 1.1; }
.cd-fin-val.credit  { color: var(--green); }
.cd-fin-val.debit   { color: var(--rose); }
.cd-fin-val.balance { color: var(--ink); }
.cd-fin-sub { font-size: 11px; color: var(--ink4); }
.cd-jv-num { font-size: 24px; font-weight: 700; font-family: 'DM Mono', monospace; color: var(--amber); line-height: 1; }
.cd-jv-lbl { font-size: 10.5px; color: var(--ink4); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-top: 4px; }

/* ═══════════════════════════════════════════
   TRANSACTION TABLE
═══════════════════════════════════════════ */
.cd-txn-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 20px;
  border-bottom: 1px solid var(--line);
  gap: 14px;
  flex-wrap: wrap;
}
.cd-search {
  display: flex;
  align-items: center;
  gap: 7px;
  background: var(--bg);
  border: 1px solid var(--line);
  border-radius: 9px;
  padding: 7px 13px;
  width: 240px;
  transition: var(--t);
}
.cd-search:focus-within {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px var(--blue-light);
  background: var(--surface);
}
.cd-search i { font-size: 11.5px; color: var(--ink4); flex-shrink: 0; }
.cd-search input {
  border: none; background: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px; color: var(--ink);
  outline: none; width: 100%;
}
.cd-search input::placeholder { color: var(--ink4); }

.cd-filter-tabs {
  display: flex;
  gap: 3px;
  background: var(--bg);
  padding: 3px;
  border-radius: 9px;
}
.cd-filter-tab {
  padding: 6px 13px;
  border-radius: 7px;
  font-size: 12px; font-weight: 600;
  color: var(--ink3);
  cursor: pointer;
  border: none; background: none;
  font-family: 'DM Sans', sans-serif;
  transition: var(--t);
}
.cd-filter-tab:hover { color: var(--ink); }
.cd-filter-tab.active { background: var(--surface); color: var(--ink); box-shadow: var(--shadow-xs); }

.cd-txn-wrap { overflow-x: auto; }

.cd-table {
  width: 100%;
  border-collapse: collapse;
}
.cd-table thead tr { background: var(--surface2); }
.cd-table th {
  padding: 11px 16px;
  font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--ink4);
  text-align: left;
  border-bottom: 1px solid var(--line);
  white-space: nowrap;
}
.cd-table td {
  padding: 12px 16px;
  font-size: 13px;
  color: var(--ink2);
  border-bottom: 1px solid var(--line2);
  vertical-align: middle;
}
.cd-table tbody tr:hover td { background: var(--surface2); }
.cd-table tbody tr:last-child td { border-bottom: none; }

.cd-txn-date {
  font-family: 'DM Mono', monospace;
  font-size: 12px; color: var(--ink3);
}
.cd-txn-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: 11px; font-weight: 600;
}
.cd-txn-badge.credit { background: var(--green-light); color: var(--green); }
.cd-txn-badge.debit  { background: var(--rose-light);  color: var(--rose); }
.cd-txn-badge i { font-size: 8px; }

.cd-txn-amt { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 600; }
.cd-txn-amt.credit { color: var(--green); }
.cd-txn-amt.debit  { color: var(--rose); }

.cd-txn-link {
  display: inline-flex; align-items: center; gap: 5px;
  color: var(--blue); text-decoration: none;
  font-size: 12.5px; font-weight: 500;
  transition: color .15s;
}
.cd-txn-link:hover { color: #1d4ed8; text-decoration: underline; }
.cd-txn-link i { font-size: 10px; }
.cd-txn-ref {
  font-family: 'DM Mono', monospace;
  font-size: 11px; color: var(--ink3);
  background: var(--line2);
  padding: 2px 6px; border-radius: 5px;
}
.cd-txn-desc { font-size: 12.5px; color: var(--ink3); }

.cd-empty-row td {
  text-align: center;
  padding: 48px 16px !important;
  color: var(--ink4) !important;
}
.cd-empty-row i { font-size: 28px; margin-bottom: 10px; display: block; opacity: .35; }
.cd-empty-row p { font-size: 13px; margin: 0; }

.cd-txn-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 20px;
  border-top: 1px solid var(--line);
  background: var(--surface2);
  flex-wrap: wrap;
  gap: 10px;
}
.cd-txn-count { font-size: 12px; color: var(--ink4); font-weight: 500; }

.cd-pager { display: flex; gap: 3px; }
.cd-page-btn {
  min-width: 30px; height: 30px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface);
  font-family: 'DM Sans', sans-serif;
  font-size: 12px; font-weight: 600;
  color: var(--ink3);
  cursor: pointer;
  transition: var(--t);
  display: flex; align-items: center; justify-content: center;
  padding: 0 10px;
}
.cd-page-btn:hover { border-color: var(--ink3); color: var(--ink); }
.cd-page-btn.active { background: var(--ink); color: #fff; border-color: var(--ink); }
.cd-page-btn:disabled { opacity: .4; cursor: not-allowed; }

/* ═══════════════════════════════════════════
   ERROR STATE
═══════════════════════════════════════════ */
.cd-error {
  background: var(--rose-light);
  color: var(--rose);
  border-radius: var(--r);
  padding: 16px 20px;
  font-size: 13.5px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

/* ═══════════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════════ */
@keyframes cd-fadeUp {
  from { opacity:0; transform:translateY(14px); }
  to   { opacity:1; transform:translateY(0); }
}
.cd-profile-card  { animation: cd-fadeUp .4s ease both; }
.cd-balance-card  { animation: cd-fadeUp .4s ease .06s both; }
.cd-meta-card     { animation: cd-fadeUp .4s ease .12s both; }
.cd-section:nth-child(1) { animation: cd-fadeUp .4s ease .1s  both; }
.cd-section:nth-child(2) { animation: cd-fadeUp .4s ease .18s both; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 1100px) {
  .cd-layout { grid-template-columns: 260px 1fr; }
  .cd-stat-grid { grid-template-columns: repeat(2, 1fr); }
  .cd-fin-strip { grid-template-columns: 1fr 1fr; }
  .cd-fin-cell:last-child { grid-column: 1 / -1; flex-direction: row; justify-content: center; gap: 20px; }
}
@media (max-width: 860px) {
  .cd-layout { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .cd-shell { padding: 20px 16px 40px; }
  .cd-stat-grid { grid-template-columns: repeat(2, 1fr); }
  .cd-fin-strip { grid-template-columns: 1fr 1fr; }
  .cd-txn-toolbar { flex-direction: column; align-items: stretch; }
  .cd-search { width: 100%; }
  .cd-topbar-actions { width: 100%; }
  .cd-topbar-actions .cd-btn { flex: 1; justify-content: center; }
}
</style>
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">

<!-- ─── PAGE MARKUP ─── -->
<div class="cd-shell">

  <!-- Topbar -->
  <div class="cd-topbar">
    <div class="cd-breadcrumb">
      <a href="#"><?= __('finance') ?></a>
      <span class="sep"><i class="fas fa-chevron-right"></i></span>
      <a href="client.php"><?= __('clients') ?></a>
      <span class="sep"><i class="fas fa-chevron-right"></i></span>
      <span style="color:var(--ink2)"><?= $clientData ? h($clientData['name']) : __('client_detail') ?></span>
    </div>
    <div class="cd-topbar-actions">
      <a href="client.php" class="cd-btn cd-btn-ghost"><i class="fas fa-arrow-left"></i> <?= __('back') ?></a>
      <?php if ($clientData): ?>
      <a href="client_edit.php?id=<?= $clientId ?>" class="cd-btn cd-btn-primary"><i class="fas fa-pen"></i> <?= __('edit_client') ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="cd-error"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
  <?php else: ?>

  <!-- Title Row -->
  <div class="cd-title-row">
    <h1><?= __('client_profile') ?></h1>
    <?php
      $st = strtolower($clientData['status'] ?? '');
      $pillClass = match($st) { 'active' => 'active', 'inactive' => 'inactive', default => 'pending' };
    ?>
    <span class="cd-status-pill <?= $pillClass ?>"><?= h(ucfirst($clientData['status'] ?? 'Unknown')) ?></span>
  </div>

  <!-- Layout -->
  <div class="cd-layout">

    <!-- ─────────────────── SIDEBAR ─────────────────── -->
    <div class="cd-sidebar">

      <!-- Profile Card -->
      <div class="cd-profile-card">
        <div class="cd-profile-header">
          <div class="cd-avatar">
            <?php if (!empty($clientData['image'])): ?>
              <img src="../uploads/clients/<?= h($clientData['image']) ?>" alt="<?= h($clientData['name']) ?>">
            <?php else: ?>
              <?= h(getInitials($clientData['name'])) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="cd-profile-body">
          <div class="cd-client-name"><?= h($clientData['name']) ?></div>
          <div class="cd-type-tag"><?= h($clientData['client_type'] ?? 'Client') ?></div>
          <div class="cd-contact">
            <?php if (!empty($clientData['email'])): ?>
            <div class="cd-contact-row">
              <div class="cd-contact-icon blue"><i class="fas fa-envelope"></i></div>
              <div>
                <span class="cd-contact-label"><?= __('email') ?></span>
                <div class="cd-contact-text"><?= h($clientData['email']) ?></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($clientData['phone'])): ?>
            <div class="cd-contact-row">
              <div class="cd-contact-icon green"><i class="fas fa-phone"></i></div>
              <div>
                <span class="cd-contact-label"><?= __('phone') ?></span>
                <div class="cd-contact-text"><?= h($clientData['phone']) ?></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($clientData['address'])): ?>
            <div class="cd-contact-row">
              <div class="cd-contact-icon violet"><i class="fas fa-map-marker-alt"></i></div>
              <div>
                <span class="cd-contact-label"><?= __('address') ?></span>
                <div class="cd-contact-text"><?= h($clientData['address']) ?></div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Balance Card -->
      <div class="cd-balance-card">
        <div class="cd-card-subtitle"><i class="fas fa-wallet"></i> <?= __('account_balances') ?></div>
        <div class="cd-balance-row">
          <div class="cd-balance-left">
            <div class="cd-currency-icon usd">$</div>
            <div class="cd-balance-lbl">US Dollar</div>
          </div>
          <div class="cd-balance-amt <?= ($clientData['usd_balance'] ?? 0) > 0 ? 'owed' : 'clear' ?>">
            USD <?= number_format($clientData['usd_balance'] ?? 0, 2) ?>
          </div>
        </div>
        <div class="cd-balance-row">
          <div class="cd-balance-left">
            <div class="cd-currency-icon afs">؋</div>
            <div class="cd-balance-lbl">Afghani</div>
          </div>
          <div class="cd-balance-amt <?= ($clientData['afs_balance'] ?? 0) > 0 ? 'owed' : 'clear' ?>">
            AFS <?= number_format($clientData['afs_balance'] ?? 0, 2) ?>
          </div>
        </div>
      </div>

      <!-- Meta Card -->
      <div class="cd-meta-card">
        <div class="cd-card-subtitle"><i class="fas fa-info-circle"></i> <?= __('account_info') ?></div>
        <div class="cd-meta-row">
          <span class="cd-meta-key"><?= __('client_id') ?></span>
          <span class="cd-meta-val" style="font-family:'DM Mono',monospace;font-size:12px">#<?= str_pad($clientData['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="cd-meta-row">
          <span class="cd-meta-key"><?= __('joined') ?></span>
          <span class="cd-meta-val"><?= date('Y-m-d', strtotime($clientData['created_at'])) ?></span>
        </div>
        <div class="cd-meta-row">
          <span class="cd-meta-key"><?= __('last_updated') ?></span>
          <span class="cd-meta-val"><?= date('Y-m-d', strtotime($clientData['updated_at'])) ?></span>
        </div>
        <div class="cd-meta-row">
          <span class="cd-meta-key"><?= __('client_type') ?></span>
          <span class="cd-meta-val"><?= h($clientData['client_type'] ?? '—') ?></span>
        </div>
      </div>

    </div><!-- /cd-sidebar -->

    <!-- ─────────────────── MAIN ─────────────────── -->
    <div class="cd-main">

      <!-- Booking History Section -->
      <div class="cd-section">
        <div class="cd-section-head">
          <div class="cd-section-head-left">
            <div class="cd-section-icon blue"><i class="fas fa-calendar-check"></i></div>
            <div>
              <div class="cd-section-title"><?= __('booking_history') ?></div>
              <div class="cd-section-sub"><?= __('all_service_bookings_linked_to_this_client') ?></div>
            </div>
          </div>
        </div>
        <div class="cd-section-body">

          <!-- Main Bookings -->
          <div class="cd-stat-group">
            <div class="cd-stat-group-label"><i class="fas fa-bookmark"></i> <?= __('main_bookings') ?></div>
            <div class="cd-stat-grid">
              <div class="cd-stat-mini cs-blue">
                <div class="cd-stat-mini-icon"><i class="fas fa-tag"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['tickets'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('tickets') ?></div>
              </div>
              <div class="cd-stat-mini cs-green">
                <div class="cd-stat-mini-icon"><i class="fas fa-file-alt"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['visas'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('visas') ?></div>
              </div>
              <div class="cd-stat-mini cs-violet">
                <div class="cd-stat-mini-icon"><i class="fas fa-hotel"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['hotels'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('hotels') ?></div>
              </div>
              <div class="cd-stat-mini cs-amber">
                <div class="cd-stat-mini-icon"><i class="fas fa-star"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['umrah'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('umrah') ?></div>
              </div>
            </div>
          </div>

          <!-- Refunds -->
          <div class="cd-stat-group">
            <div class="cd-stat-group-label"><i class="fas fa-undo-alt"></i> <?= __('refunds') ?></div>
            <div class="cd-stat-grid">
              <div class="cd-stat-mini cs-rose">
                <div class="cd-stat-mini-icon"><i class="fas fa-tag"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['ticket_refund'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('refund_tickets') ?></div>
              </div>
              <div class="cd-stat-mini cs-rose">
                <div class="cd-stat-mini-icon"><i class="fas fa-file-alt"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['visa_refund'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('refund_visas') ?></div>
              </div>
              <div class="cd-stat-mini cs-rose">
                <div class="cd-stat-mini-icon"><i class="fas fa-hotel"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['hotel_refund'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('refund_hotels') ?></div>
              </div>
              <div class="cd-stat-mini cs-rose">
                <div class="cd-stat-mini-icon"><i class="fas fa-star"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['umrah_refund'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('refund_umrah') ?></div>
              </div>
            </div>
          </div>

          <!-- Other Transactions -->
          <div class="cd-stat-group">
            <div class="cd-stat-group-label"><i class="fas fa-exchange-alt"></i> <?= __('other_transactions') ?></div>
            <div class="cd-stat-grid">
              <div class="cd-stat-mini cs-teal">
                <div class="cd-stat-mini-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['date_change'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('date_changes') ?></div>
              </div>
              <div class="cd-stat-mini cs-violet">
                <div class="cd-stat-mini-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['additional'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('additional_payments') ?></div>
              </div>
              <div class="cd-stat-mini cs-blue">
                <div class="cd-stat-mini-icon"><i class="fas fa-clock"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['reserve'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('ticket_reserve') ?></div>
              </div>
              <div class="cd-stat-mini cs-green">
                <div class="cd-stat-mini-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="cd-stat-mini-val"><?= $cnt['fund'] ?></div>
                <div class="cd-stat-mini-lbl"><?= __('fund_transfer') ?></div>
              </div>
            </div>
          </div>

        </div><!-- /cd-section-body -->

        <!-- Financial Strip -->
         <div class="cd-fin-strip">
           <div class="cd-fin-cell">
             <div class="cd-fin-label"><?= __('total_credit') ?></div>
             <div class="cd-fin-sub" style="margin-bottom:8px"><?= __('all_inbound_payments') ?></div>
             <div class="cd-fin-val credit">USD <?= number_format($creditByCurrency['USD'] ?? 0, 2) ?></div>
             <div class="cd-fin-val credit">AFS <?= number_format($creditByCurrency['AFS'] ?? 0, 2) ?></div>
           </div>
           <div class="cd-fin-cell">
             <div class="cd-fin-label"><?= __('total_debit') ?></div>
             <div class="cd-fin-sub" style="margin-bottom:8px"><?= __('all_outbound_charges') ?></div>
             <div class="cd-fin-val debit">USD <?= number_format($debitByCurrency['USD'] ?? 0, 2) ?></div>
             <div class="cd-fin-val debit">AFS <?= number_format($debitByCurrency['AFS'] ?? 0, 2) ?></div>
           </div>
           <div class="cd-fin-cell">
             <div class="cd-fin-label"><?= __('net_balance') ?></div>
             <div class="cd-fin-sub" style="margin-bottom:8px"><?= __('current_account_balance') ?></div>
             <div class="cd-fin-val balance">USD <?= number_format($usdNetBalance, 2) ?></div>
             <div class="cd-fin-val balance">AFS <?= number_format($afsNetBalance, 2) ?></div>
           </div>
           <div class="cd-fin-cell">
             <div style="text-align:center">
               <div class="cd-jv-num"><?= $cnt['jv'] ?></div>
               <div class="cd-jv-lbl"><?= __('jv_payment') ?></div>
             </div>
           </div>
         </div>

      </div><!-- /booking history section -->

      <!-- Transaction History Section -->
      <div class="cd-section">
        <div class="cd-section-head">
          <div class="cd-section-head-left">
            <div class="cd-section-icon violet"><i class="fas fa-list-ul"></i></div>
            <div>
              <div class="cd-section-title"><?= __('transaction_history') ?></div>
              <div class="cd-section-sub"><?= __('full_ledger_of_all_account_activity') ?></div>
            </div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="cd-txn-toolbar">
          <div class="cd-search">
            <i class="fas fa-search"></i>
            <input type="text" id="cdSearchInput" placeholder="<?= __('search_transactions') ?>…" oninput="cdFilterTxn()">
          </div>
          <div class="cd-filter-tabs" id="cdFilterTabs">
            <button class="cd-filter-tab active" onclick="cdSetFilter('all', this)"><?= __('all') ?></button>
            <button class="cd-filter-tab" onclick="cdSetFilter('credit', this)"><?= __('credit') ?></button>
            <button class="cd-filter-tab" onclick="cdSetFilter('debit', this)"><?= __('debit') ?></button>
          </div>
        </div>

        <div class="cd-txn-wrap">
          <table class="cd-table" id="cdTxnTable">
            <thead>
              <tr>
                <th><?= __('date') ?></th>
                <th><?= __('type') ?></th>
                <th><?= __('amount') ?></th>
                <th><?= __('related_to') ?></th>
                <th><?= __('description') ?></th>
              </tr>
            </thead>
            <tbody id="cdTxnBody">
              <?php if (empty($transactions)): ?>
              <tr class="cd-empty-row">
                <td colspan="5">
                  <i class="fas fa-inbox"></i>
                  <p><?= __('no_transactions_found_for_this_client') ?></p>
                </td>
              </tr>
              <?php else: ?>
              <?php foreach ($transactions as $tx):
                $type    = strtolower($tx['type'] ?? '');
                $txOf    = $tx['transaction_of'] ?? '';
                $refId   = h($tx['reference_id'] ?? '');
                $typeLabel = h(ucfirst($type));
              ?>
              <tr data-type="<?= h($type) ?>">
                <td><span class="cd-txn-date"><?= date('Y-m-d', strtotime($tx['transaction_date'])) ?></span></td>
                <td>
                  <span class="cd-txn-badge <?= $type === 'credit' ? 'credit' : 'debit' ?>">
                    <i class="fas <?= $type === 'credit' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                    <?= $typeLabel ?>
                  </span>
                </td>
                <td>
                  <span class="cd-txn-amt <?= $type === 'credit' ? 'credit' : 'debit' ?>">
                    <?= h($tx['currency'] ?? '') ?> <?= h($tx['amount'] ?? '—') ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($txOf)): ?>
                    <?php switch ($txOf):
                      case 'ticket': ?>
                        <a href="ticket_detail.php?id=<?= $refId ?>" class="cd-txn-link">
                          <i class="fas fa-tag"></i> <?= __('ticket') ?> <span class="cd-txn-ref">#<?= $refId ?></span>
                        </a>
                      <?php break; case 'visa': case 'visa_sale': ?>
                        <a href="visa_detail.php?id=<?= $refId ?>" class="cd-txn-link">
                          <i class="fas fa-file-alt"></i> <?= __('visa') ?> <span class="cd-txn-ref">#<?= $refId ?></span>
                        </a>
                      <?php break; case 'hotel': case 'hotel_booking': ?>
                        <a href="hotel_detail.php?id=<?= $refId ?>" class="cd-txn-link">
                          <i class="fas fa-hotel"></i> <?= __('hotel') ?> <span class="cd-txn-ref">#<?= $refId ?></span>
                        </a>
                      <?php break; default: ?>
                        <span style="font-size:12.5px;color:var(--ink3)">
                          <?= h(ucfirst(str_replace('_', ' ', $txOf))) ?>
                          <?php if ($refId): ?><span class="cd-txn-ref">#<?= $refId ?></span><?php endif; ?>
                        </span>
                    <?php endswitch; ?>
                  <?php else: ?>
                    <span style="color:var(--ink4)">—</span>
                  <?php endif; ?>
                </td>
                <td class="cd-txn-desc"><?= h($tx['description'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="cd-txn-foot">
          <span class="cd-txn-count" id="cdTxnCount">
            <?= __('showing') ?> <?= count($transactions) ?> <?= __('transactions') ?>
          </span>
          <div class="cd-pager">
            <button class="cd-page-btn" disabled><i class="fas fa-chevron-left" style="font-size:9px"></i></button>
            <button class="cd-page-btn active">1</button>
            <button class="cd-page-btn" disabled><i class="fas fa-chevron-right" style="font-size:9px"></i></button>
          </div>
        </div>
      </div><!-- /transaction section -->

    </div><!-- /cd-main -->
  </div><!-- /cd-layout -->
  </div>

  </div>
</div>
  <?php endif; ?>
</div><!-- /cd-shell -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
(function() {
  let cdFilter = 'all';
  const total = <?= count($transactions) ?>;

  window.cdSetFilter = function(type, btn) {
    cdFilter = type;
    document.querySelectorAll('.cd-filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    cdApply();
  };

  window.cdFilterTxn = function() { cdApply(); };

  function cdApply() {
    const q = document.getElementById('cdSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#cdTxnBody tr[data-type]');
    let vis = 0;
    rows.forEach(r => {
      const matchType   = cdFilter === 'all' || r.dataset.type === cdFilter;
      const matchSearch = !q || r.textContent.toLowerCase().includes(q);
      const show = matchType && matchSearch;
      r.style.display = show ? '' : 'none';
      if (show) vis++;
    });
    const countEl = document.getElementById('cdTxnCount');
    if (countEl) countEl.textContent = `Showing ${vis} of ${total} transactions`;
  }
})();
</script>

<?php include '../includes/admin_footer.php'; ?>