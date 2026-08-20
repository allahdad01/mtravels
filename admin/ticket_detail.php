<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_permission('tickets.view');
include '../includes/db.php';

$ticketId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$ticketData = null;
$refundData = null;
$dateChangeData = null;
$weightData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

if (!$ticketId) {
    $error = "No ticket ID provided";
} else {
    $ticketQuery = "SELECT
            tb.*,
            c.name AS client_name,
            c.email AS client_email,
            c.phone AS client_phone,
            s.name AS supplier_name,
            s.email AS supplier_email,
            s.phone AS supplier_phone,
            ma.name AS paid_to_name
        FROM ticket_bookings tb
        LEFT JOIN clients c ON tb.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        LEFT JOIN suppliers s ON tb.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN main_account ma ON tb.paid_to = ma.id AND ma.tenant_id = ? AND ma.branch_id = ?
        WHERE tb.id = ? AND tb.tenant_id = ? AND tb.branch_id = ?";

    $stmt = $pdo->prepare($ticketQuery);
    $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $ticketId, $tenant_id, $branch_id]);
    $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticketData) {
        $error = "Ticket not found";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM refunded_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $refundData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM date_change_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $dateChangeData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM ticket_weights WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $weightData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT 'Main Account' AS transaction_type, mat.id, mat.type, mat.amount, mat.currency, mat.description, mat.transaction_of, mat.created_at AS transaction_date FROM main_account_transactions mat WHERE (mat.reference_id = ? AND mat.tenant_id = ? AND mat.branch_id = ? AND (mat.transaction_of = 'ticket_sale' OR mat.transaction_of = 'ticket_refund' OR mat.transaction_of = 'date_change' OR mat.transaction_of = 'weight')) ORDER BY mat.created_at DESC");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT 'Client' AS transaction_type, ct.id, ct.type, ct.amount, ct.currency, ct.description, ct.transaction_of, ct.created_at AS transaction_date FROM client_transactions ct WHERE (ct.reference_id = ? AND ct.tenant_id = ? AND ct.branch_id = ? AND (ct.transaction_of = 'ticket_sale' OR ct.transaction_of = 'ticket_refund' OR ct.transaction_of = 'date_change' OR ct.transaction_of = 'weight')) ORDER BY ct.created_at DESC");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT 'Supplier' AS transaction_type, st.id, st.transaction_type AS type, st.amount, st.remarks AS description, st.transaction_of, st.transaction_date FROM supplier_transactions st WHERE (st.reference_id = ? AND st.tenant_id = ? AND st.branch_id = ? AND (st.transaction_of = 'ticket_sale' OR st.transaction_of = 'ticket_refund' OR st.transaction_of = 'date_change' OR st.transaction_of = 'weight')) ORDER BY st.transaction_date DESC");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $supplierTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
    --primary:   #4099ff;
    --teal:      #2ed8b6;
    --purple:    #667eea;
    --purple2:   #764ba2;
    --danger:    #ff5370;
    --warning:   #ffb64d;
    --success:   #2ed8b6;
    --info:      #4099ff;
    --dark:      #1a1f2e;
    --surface:   #ffffff;
    --surface2:  #f4f6fb;
    --border:    #e8ecf4;
    --text:      #2d3748;
    --muted:     #8492a6;
    --grad-main: linear-gradient(135deg, var(--primary) 0%, var(--teal) 100%);
    --grad-purple: linear-gradient(135deg, var(--purple) 0%, var(--purple2) 100%);
    --shadow-sm: 0 1px 4px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
    --shadow-lg: 0 8px 32px rgba(0,0,0,0.13);
    --radius:    12px;
    --radius-sm: 8px;
}

*, *::before, *::after { box-sizing: border-box; }

body { font-family: 'DM Sans', sans-serif; background: var(--surface2); color: var(--text); }

/* ── Page header ── */
.td-page-header {
    background: var(--grad-main);
    border-radius: var(--radius);
    padding: 22px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 20px rgba(64,153,255,.30);
    animation: fadeDown .45s ease both;
}
.td-page-header h5 { color: #fff; font-weight: 700; font-size: 1.15rem; margin: 0; }
.td-page-header p  { color: rgba(255,255,255,.80); font-size: .82rem; margin: 4px 0 0; }
.td-back-btn {
    background: rgba(255,255,255,.18);
    color: #fff;
    border: 1px solid rgba(255,255,255,.30);
    border-radius: 50px;
    padding: 8px 20px;
    font-size: .82rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background .2s, transform .2s;
}
.td-back-btn:hover { background: rgba(255,255,255,.30); transform: translateY(-1px); color: #fff; text-decoration: none; }

/* ── Hero status strip ── */
.hero-strip {
    background: var(--dark);
    border-radius: var(--radius);
    padding: 24px 32px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
    animation: fadeDown .5s .05s ease both;
}
.hero-strip::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(64,153,255,.12) 0%, rgba(46,216,182,.08) 100%);
    pointer-events: none;
}
.hero-route {
    display: flex;
    align-items: center;
    gap: 14px;
}
.hero-city {
    text-align: center;
}
.hero-city-code {
    font-family: 'DM Mono', monospace;
    font-size: 2rem;
    font-weight: 500;
    color: #fff;
    line-height: 1;
    letter-spacing: 2px;
}
.hero-city-name {
    font-size: .72rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 3px;
}
.hero-plane {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex: 1;
    min-width: 80px;
}
.hero-plane-line {
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, rgba(64,153,255,.4), rgba(46,216,182,.4));
    position: relative;
}
.hero-plane-line::after {
    content: '✈';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--teal);
    font-size: 1rem;
    background: var(--dark);
    padding: 0 6px;
}
.hero-airline {
    font-size: .72rem;
    color: var(--muted);
    font-family: 'DM Mono', monospace;
}
.hero-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}
.hero-pnr {
    font-family: 'DM Mono', monospace;
    font-size: 1.05rem;
    color: var(--teal);
    letter-spacing: 2px;
}
.hero-departure {
    font-size: .80rem;
    color: var(--muted);
}
.hero-departure span { color: #fff; font-weight: 600; }
.hero-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
}
.hs-paid    { background: rgba(46,216,182,.15); color: var(--teal); border: 1px solid rgba(46,216,182,.3); }
.hs-borrowed { background: rgba(255,182,77,.15); color: var(--warning); border: 1px solid rgba(255,182,77,.3); }
.hs-changed { background: rgba(64,153,255,.15); color: var(--primary); border: 1px solid rgba(64,153,255,.3); }
.hs-refunded { background: rgba(255,83,112,.15); color: var(--danger); border: 1px solid rgba(255,83,112,.3); }
.hs-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: pulse-dot 1.8s ease infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.7)} }

/* ── Financial summary cards ── */
.fin-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
    animation: fadeUp .5s .10s ease both;
}
@media(max-width:768px){ .fin-row { grid-template-columns: repeat(2,1fr); } }
.fin-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    transition: transform .2s, box-shadow .2s;
    position: relative;
    overflow: hidden;
}
.fin-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.fin-card.fc-base::before   { background: var(--purple); }
.fin-card.fc-sell::before   { background: var(--grad-main); }
.fin-card.fc-profit::before { background: linear-gradient(90deg,var(--teal),var(--success)); }
.fin-card.fc-receipt::before{ background: linear-gradient(90deg,var(--warning),#ff9f43); }
.fin-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.fin-label {
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.fin-label i { font-size: .85rem; }
.fin-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text);
    font-family: 'DM Mono', monospace;
}
.fin-value.profit-val { color: var(--teal); }
.fin-value.receipt-val { font-size: 1rem; color: var(--muted); }

/* ── Two-column info layout ── */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
    animation: fadeUp .5s .15s ease both;
}
@media(max-width:768px){ .info-grid { grid-template-columns: 1fr; } }

.info-panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    overflow: hidden;
}
.info-panel-header {
    padding: 14px 20px;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-panel-header.ph-flight { background: var(--grad-main); color: #fff; }
.info-panel-header.ph-party  { background: var(--grad-purple); color: #fff; }
.info-panel-body { padding: 8px 0; }
.info-row {
    display: flex;
    align-items: center;
    padding: 11px 20px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
    transition: background .15s;
}
.info-row:last-child { border-bottom: none; }
.info-row:hover { background: var(--surface2); }
.info-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .85rem;
}
.ii-blue   { background: rgba(64,153,255,.12); color: var(--primary); }
.ii-teal   { background: rgba(46,216,182,.12); color: var(--teal); }
.ii-purple { background: rgba(102,126,234,.12); color: var(--purple); }
.ii-warn   { background: rgba(255,182,77,.12); color: var(--warning); }
.info-content { flex: 1; min-width: 0; }
.info-content-label { font-size: .70rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .8px; }
.info-content-value { font-size: .88rem; font-weight: 600; color: var(--text); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.info-action { flex-shrink: 0; }
.info-action a {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--surface2);
    color: var(--muted);
    border: 1px solid var(--border);
    font-size: .75rem;
    transition: all .2s;
    text-decoration: none;
}
.info-action a:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

.gender-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-left: 6px;
    background: rgba(64,153,255,.12);
    color: var(--primary);
}

/* ── Description block ── */
.desc-block {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    border-left: 4px solid var(--primary);
    padding: 16px 20px;
    margin-bottom: 20px;
    animation: fadeUp .5s .18s ease both;
}
.desc-block-label { font-size: .70rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.desc-block-text  { font-size: .88rem; color: var(--text); line-height: 1.6; }

/* ── Accordion for Refund / Date Change / Weight ── */
.extra-accordion {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
    animation: fadeUp .5s .20s ease both;
}
.acc-item {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.acc-trigger {
    width: 100%;
    background: none;
    border: none;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    text-align: left;
    transition: background .15s;
}
.acc-trigger:hover { background: var(--surface2); }
.acc-trigger-icon {
    width: 36px; height: 36px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}
.ai-danger  { background: rgba(255,83,112,.12); color: var(--danger); }
.ai-primary { background: rgba(64,153,255,.12); color: var(--primary); }
.ai-info    { background: rgba(46,216,182,.12); color: var(--teal); }
.acc-trigger-title {
    flex: 1;
    font-size: .90rem;
    font-weight: 700;
    color: var(--text);
}
.acc-trigger-subtitle { font-size: .75rem; color: var(--muted); font-weight: 400; margin-top: 1px; }
.acc-chevron {
    font-size: .8rem;
    color: var(--muted);
    transition: transform .25s;
    flex-shrink: 0;
}
.acc-item.open .acc-chevron { transform: rotate(180deg); }
.acc-body {
    display: none;
    padding: 0 20px 20px;
    border-top: 1px solid var(--border);
}
.acc-item.open .acc-body { display: block; }
.acc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    margin-top: 16px;
}
@media(max-width:600px){ .acc-grid { grid-template-columns: 1fr; } }
.acc-field { padding: 10px 0; border-bottom: 1px solid var(--border); }
.acc-field:nth-last-child(-n+2) { border-bottom: none; }
.acc-field-label { font-size: .68rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .8px; }
.acc-field-value { font-size: .88rem; font-weight: 600; color: var(--text); margin-top: 3px; }
.acc-remarks {
    margin-top: 14px;
    background: var(--surface2);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    font-size: .83rem;
    color: var(--text);
    border-left: 3px solid var(--primary);
}
.acc-remarks strong { color: var(--muted); font-size: .70rem; text-transform: uppercase; letter-spacing: .8px; display: block; margin-bottom: 4px; }

/* Status pill reuse */
.spill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 50px;
    font-size: .70rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
}
.spill-success { background: rgba(46,216,182,.12); color: var(--teal); border:1px solid rgba(46,216,182,.25); }
.spill-warning { background: rgba(255,182,77,.12); color: var(--warning); border:1px solid rgba(255,182,77,.25); }
.spill-danger  { background: rgba(255,83,112,.12); color: var(--danger); border:1px solid rgba(255,83,112,.25); }
.spill-primary { background: rgba(64,153,255,.12); color: var(--primary); border:1px solid rgba(64,153,255,.25); }

/* ── Timeline transactions ── */
.trans-card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    overflow: hidden;
    animation: fadeUp .5s .25s ease both;
}
.trans-card-header {
    padding: 16px 24px;
    background: var(--grad-purple);
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: .88rem;
    font-weight: 700;
}
.trans-filter-bar {
    padding: 14px 20px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
}
.tf-btn {
    padding: 5px 14px;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 600;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    cursor: pointer;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.tf-btn.active, .tf-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.tf-btn.tf-client.active  { background: var(--teal); border-color: var(--teal); }
.tf-btn.tf-supplier.active{ background: var(--purple); border-color: var(--purple); }
.tf-count {
    background: rgba(255,255,255,.25);
    border-radius: 50px;
    padding: 1px 6px;
    font-size: .65rem;
}
.tf-btn:not(.active) .tf-count { background: var(--surface2); color: var(--muted); }

.timeline {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.tl-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--muted);
    font-size: .85rem;
}
.tl-empty i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .4; }

.tl-item {
    display: flex;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
    transition: opacity .2s;
}
.tl-item:last-child { border-bottom: none; }
.tl-item.hidden { display: none; }
.tl-line {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    width: 36px;
}
.tl-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
    border: 2px solid var(--surface);
    box-shadow: 0 0 0 2px currentColor;
}
.tl-dot-main     { color: var(--primary); background: var(--primary); }
.tl-dot-client   { color: var(--teal); background: var(--teal); }
.tl-dot-supplier { color: var(--purple); background: var(--purple); }
.tl-connector {
    flex: 1;
    width: 1px;
    background: var(--border);
    margin-top: 4px;
}
.tl-content { flex: 1; min-width: 0; }
.tl-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.tl-who {
    font-size: .70rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 2px;
}
.tl-who-main     { color: var(--primary); }
.tl-who-client   { color: var(--teal); }
.tl-who-supplier { color: var(--purple); }
.tl-desc { font-size: .82rem; font-weight: 500; color: var(--text); }
.tl-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.tl-date { font-size: .70rem; color: var(--muted); font-family: 'DM Mono', monospace; }
.tl-of-badge {
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 2px 7px;
    border-radius: 50px;
}
.tob-sale     { background: rgba(64,153,255,.10); color: var(--primary); }
.tob-refund   { background: rgba(255,182,77,.10); color: var(--warning); }
.tob-change   { background: rgba(102,126,234,.10); color: var(--purple); }
.tob-weight   { background: rgba(46,216,182,.10); color: var(--teal); }
.tl-amount {
    font-family: 'DM Mono', monospace;
    font-size: .95rem;
    font-weight: 700;
    flex-shrink: 0;
}
.tl-credit { color: var(--teal); }
.tl-debit  { color: var(--danger); }

/* ── Animations ── */
@keyframes fadeDown { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp   { from { opacity:0; transform:translateY(14px);  } to { opacity:1; transform:translateY(0); } }

.alert-danger-td {
    background: linear-gradient(135deg,rgba(255,83,112,.08),rgba(255,83,112,.04));
    border: 1px solid rgba(255,83,112,.2);
    border-left: 4px solid var(--danger);
    border-radius: var(--radius);
    padding: 18px 22px;
    color: #c53030;
    font-weight: 500;
}
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="main-body">
          <div class="page-wrapper">
            <div class="main-content">

              <!-- Page Header -->
              <div class="td-page-header">
                <div>
                  <h5><i class="feather icon-file-text" style="margin-right:8px;"></i><?php echo __('ticket_details'); ?></h5>
                  <p><?php echo __('view_ticket_information'); ?></p>
                </div>
                <a href="search.php" class="td-back-btn">
                  <i class="feather icon-arrow-left"></i><?php echo __('back_to_search'); ?>
                </a>
              </div>

              <?php if ($error): ?>
                <div class="alert-danger-td"><i class="feather icon-alert-circle" style="margin-right:8px;"></i><?php echo h($error); ?></div>

              <?php else: ?>

              <!-- Hero Status Strip -->
              <div class="hero-strip">
                <div class="hero-route">
                  <div class="hero-city">
                    <div class="hero-city-code"><?php echo htmlspecialchars($ticketData['origin']); ?></div>
                    <div class="hero-city-name"><?php echo __('origin'); ?></div>
                  </div>
                  <div class="hero-plane" style="min-width:100px;">
                    <div class="hero-plane-line"></div>
                    <div class="hero-airline"><?php echo htmlspecialchars($ticketData['airline']); ?></div>
                  </div>
                  <div class="hero-city">
                    <div class="hero-city-code"><?php echo htmlspecialchars($ticketData['destination']); ?></div>
                    <div class="hero-city-name"><?php echo __('destination'); ?></div>
                  </div>
                </div>

                <div style="display:flex;flex-direction:column;align-items:center;gap:10px;">
                  <?php
                    $statusClass = 'hs-paid';
                    if ($ticketData['status'] == 'Borrowed') $statusClass = 'hs-borrowed';
                    elseif ($ticketData['status'] == 'Date Changed') $statusClass = 'hs-changed';
                    elseif ($ticketData['status'] == 'Refunded') $statusClass = 'hs-refunded';
                  ?>
                  <div class="hero-status-badge <?php echo $statusClass; ?>">
                    <span class="hs-dot"></span>
                    <?php echo h($ticketData['status']); ?>
                  </div>
                  <div style="font-size:.72rem;color:var(--muted);text-align:center;">
                    <?php echo htmlspecialchars($ticketData['title'] . ' ' . $ticketData['passenger_name']); ?>
                  </div>
                </div>

                <div class="hero-meta">
                  <div>
                    <div style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;"><?php echo __('pnr'); ?></div>
                    <div class="hero-pnr"><?php echo htmlspecialchars($ticketData['pnr']); ?></div>
                  </div>
                  <div class="hero-departure">
                    <?php echo __('departure'); ?>: <span><?php echo date('d M Y', strtotime($ticketData['departure_date'])); ?></span>
                  </div>
                  <div class="hero-departure">
                    <?php echo __('issued'); ?>: <span><?php echo date('d M Y', strtotime($ticketData['issue_date'])); ?></span>
                  </div>
                </div>
              </div>

              <!-- Financial Summary Cards -->
              <div class="fin-row">
                <div class="fin-card fc-base">
                  <div class="fin-label"><i class="feather icon-tag"></i><?php echo __('base_price'); ?></div>
                  <div class="fin-value"><?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($ticketData['price'], 2); ?></div>
                </div>
                <div class="fin-card fc-sell">
                  <div class="fin-label"><i class="feather icon-shopping-cart"></i><?php echo __('selling_price'); ?></div>
                  <div class="fin-value"><?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($ticketData['sold'], 2); ?></div>
                </div>
                <div class="fin-card fc-profit">
                  <div class="fin-label"><i class="feather icon-trending-up"></i><?php echo __('profit'); ?></div>
                  <div class="fin-value profit-val"><?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($ticketData['profit'], 2); ?></div>
                </div>
                <div class="fin-card fc-receipt">
                  <div class="fin-label"><i class="feather icon-hash"></i><?php echo __('receipt_number'); ?></div>
                  <div class="fin-value receipt-val"><?php echo !empty($ticketData['receipt']) ? htmlspecialchars($ticketData['receipt']) : '—'; ?></div>
                </div>
              </div>

              <!-- Two-column Info Panels -->
              <div class="info-grid">

                <!-- Flight & Passenger Info -->
                <div class="info-panel">
                  <div class="info-panel-header ph-flight">
                    <i class="feather icon-user"></i>
                    <?php echo __('passenger_flight_info'); ?>
                  </div>
                  <div class="info-panel-body">
                    <div class="info-row">
                      <div class="info-icon ii-blue"><i class="feather icon-user"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('passenger_name'); ?></div>
                        <div class="info-content-value">
                          <?php echo htmlspecialchars($ticketData['title'] . ' ' . $ticketData['passenger_name']); ?>
                          <span class="gender-badge"><?php echo h($ticketData['gender']); ?></span>
                        </div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-teal"><i class="feather icon-phone"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('contact'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['phone']); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-blue"><i class="feather icon-credit-card"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('pnr'); ?></div>
                        <div class="info-content-value" style="font-family:'DM Mono',monospace;letter-spacing:1px;"><?php echo htmlspecialchars($ticketData['pnr']); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-purple"><i class="feather icon-send"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('airline'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['airline']); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-teal"><i class="feather icon-map-pin"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('route'); ?></div>
                        <div class="info-content-value">
                          <?php echo htmlspecialchars($ticketData['origin']); ?>
                          <i class="feather icon-arrow-right" style="margin:0 6px;font-size:.7rem;color:var(--muted);"></i>
                          <?php echo htmlspecialchars($ticketData['destination']); ?>
                        </div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-blue"><i class="feather icon-calendar"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('issue_date'); ?></div>
                        <div class="info-content-value"><?php echo date('d M Y', strtotime($ticketData['issue_date'])); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-warn"><i class="feather icon-clock"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('departure_date'); ?></div>
                        <div class="info-content-value"><?php echo date('d M Y', strtotime($ticketData['departure_date'])); ?></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Party Info -->
                <div class="info-panel">
                  <div class="info-panel-header ph-party">
                    <i class="feather icon-users"></i>
                    <?php echo __('client_supplier_info'); ?>
                  </div>
                  <div class="info-panel-body">
                    <div class="info-row">
                      <div class="info-icon ii-blue"><i class="feather icon-briefcase"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('client'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['client_name']); ?></div>
                      </div>
                      <?php if ($ticketData['sold_to']): ?>
                      <div class="info-action">
                        <a href="client_detail.php?id=<?php echo h($ticketData['sold_to']); ?>" title="View Client">
                          <i class="feather icon-external-link"></i>
                        </a>
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-teal"><i class="feather icon-phone"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('client_contact'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['client_phone']); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-purple"><i class="feather icon-truck"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('supplier'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['supplier_name']); ?></div>
                      </div>
                      <?php if ($ticketData['supplier']): ?>
                      <div class="info-action">
                        <a href="supplier_detail.php?id=<?php echo h($ticketData['supplier']); ?>" title="View Supplier">
                          <i class="feather icon-external-link"></i>
                        </a>
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-teal"><i class="feather icon-phone"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('supplier_contact'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['supplier_phone']); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-warn"><i class="feather icon-dollar-sign"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('paid_to'); ?></div>
                        <div class="info-content-value"><?php echo htmlspecialchars($ticketData['paid_to_name']); ?></div>
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-icon ii-blue"><i class="feather icon-info"></i></div>
                      <div class="info-content">
                        <div class="info-content-label"><?php echo __('status'); ?></div>
                        <div class="info-content-value">
                          <?php
                            $spill = 'spill-primary';
                            if ($ticketData['status'] == 'Paid') $spill = 'spill-success';
                            elseif ($ticketData['status'] == 'Borrowed') $spill = 'spill-warning';
                            elseif ($ticketData['status'] == 'Refunded') $spill = 'spill-danger';
                          ?>
                          <span class="spill <?php echo $spill; ?>"><?php echo h($ticketData['status']); ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Description -->
              <?php if (!empty($ticketData['description'])): ?>
              <div class="desc-block">
                <div class="desc-block-label"><i class="feather icon-align-left" style="margin-right:6px;"></i><?php echo __('description'); ?></div>
                <div class="desc-block-text"><?php echo nl2br(htmlspecialchars($ticketData['description'])); ?></div>
              </div>
              <?php endif; ?>

              <!-- Accordion: Refund / Date Change / Weight -->
              <?php if ($refundData || $dateChangeData || $weightData): ?>
              <div class="extra-accordion">

                <?php if ($refundData): ?>
                <div class="acc-item" id="acc-refund">
                  <button class="acc-trigger" onclick="toggleAcc('acc-refund')">
                    <div class="acc-trigger-icon ai-danger"><i class="feather icon-rotate-ccw"></i></div>
                    <div>
                      <div class="acc-trigger-title"><?php echo __('refund_information'); ?></div>
                      <div class="acc-trigger-subtitle">
                        <?php echo __('refund_amount'); ?>: <?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['refund_to_passenger']); ?>
                        &nbsp;·&nbsp;
                        <span class="spill spill-<?php echo ($refundData['status']=='Refunded')?'success':(($refundData['status']=='Pending')?'warning':'danger'); ?>"><?php echo h($refundData['status']); ?></span>
                      </div>
                    </div>
                    <i class="feather icon-chevron-down acc-chevron"></i>
                  </button>
                  <div class="acc-body">
                    <div class="acc-grid">
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('refund_date'); ?></div>
                        <div class="acc-field-value"><?php echo date('d M Y', strtotime($refundData['created_at'])); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('receipt_number'); ?></div>
                        <div class="acc-field-value"><?php echo !empty($refundData['receipt']) ? htmlspecialchars($refundData['receipt']) : '—'; ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('supplier_penalty'); ?></div>
                        <div class="acc-field-value"><?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['supplier_penalty']); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('service_penalty'); ?></div>
                        <div class="acc-field-value"><?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['service_penalty']); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('refund_amount'); ?></div>
                        <div class="acc-field-value" style="color:var(--teal);font-size:1rem;"><?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['refund_to_passenger']); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('status'); ?></div>
                        <div class="acc-field-value">
                          <span class="spill spill-<?php echo ($refundData['status']=='Refunded')?'success':(($refundData['status']=='Pending')?'warning':'danger'); ?>"><?php echo h($refundData['status']); ?></span>
                        </div>
                      </div>
                    </div>
                    <?php if (!empty($refundData['remarks'])): ?>
                    <div class="acc-remarks"><strong><?php echo __('remarks'); ?></strong><?php echo nl2br(htmlspecialchars($refundData['remarks'])); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>

                <?php if ($dateChangeData): ?>
                <div class="acc-item" id="acc-datechange">
                  <button class="acc-trigger" onclick="toggleAcc('acc-datechange')">
                    <div class="acc-trigger-icon ai-primary"><i class="feather icon-calendar"></i></div>
                    <div>
                      <div class="acc-trigger-title"><?php echo __('date_change_information'); ?></div>
                      <div class="acc-trigger-subtitle">
                        <?php echo __('new_departure_date'); ?>: <?php echo date('d M Y', strtotime($dateChangeData['departure_date'])); ?>
                        &nbsp;·&nbsp;
                        <span class="spill spill-<?php echo ($dateChangeData['status']=='Refunded')?'success':(($dateChangeData['status']=='Pending')?'warning':'danger'); ?>"><?php echo h($dateChangeData['status']); ?></span>
                      </div>
                    </div>
                    <i class="feather icon-chevron-down acc-chevron"></i>
                  </button>
                  <div class="acc-body">
                    <div class="acc-grid">
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('changed_on'); ?></div>
                        <div class="acc-field-value"><?php echo date('d M Y', strtotime($dateChangeData['created_at'])); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('new_departure_date'); ?></div>
                        <div class="acc-field-value"><?php echo date('d M Y', strtotime($dateChangeData['departure_date'])); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('supplier_penalty'); ?></div>
                        <div class="acc-field-value"><?php echo htmlspecialchars($dateChangeData['currency']) . ' ' . htmlspecialchars($dateChangeData['supplier_penalty']); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('service_penalty'); ?></div>
                        <div class="acc-field-value"><?php echo htmlspecialchars($dateChangeData['currency']) . ' ' . htmlspecialchars($dateChangeData['service_penalty']); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('receipt_number'); ?></div>
                        <div class="acc-field-value"><?php echo !empty($dateChangeData['receipt']) ? htmlspecialchars($dateChangeData['receipt']) : '—'; ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('status'); ?></div>
                        <div class="acc-field-value">
                          <span class="spill spill-<?php echo ($dateChangeData['status']=='Refunded')?'success':(($dateChangeData['status']=='Pending')?'warning':'danger'); ?>"><?php echo h($dateChangeData['status']); ?></span>
                        </div>
                      </div>
                    </div>
                    <?php if (!empty($dateChangeData['remarks'])): ?>
                    <div class="acc-remarks"><strong><?php echo __('remarks'); ?></strong><?php echo nl2br(htmlspecialchars($dateChangeData['remarks'])); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>

                <?php if ($weightData): ?>
                <div class="acc-item" id="acc-weight">
                  <button class="acc-trigger" onclick="toggleAcc('acc-weight')">
                    <div class="acc-trigger-icon ai-info"><i class="feather icon-package"></i></div>
                    <div>
                      <div class="acc-trigger-title"><?php echo __('weight_information'); ?></div>
                      <div class="acc-trigger-subtitle">
                        <?php echo number_format($weightData['weight'] ?? 0, 2); ?> kg
                        &nbsp;·&nbsp; <?php echo __('profit'); ?>: <?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($weightData['profit'] ?? 0, 2); ?>
                      </div>
                    </div>
                    <i class="feather icon-chevron-down acc-chevron"></i>
                  </button>
                  <div class="acc-body">
                    <div class="acc-grid">
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('weight'); ?></div>
                        <div class="acc-field-value"><?php echo number_format($weightData['weight'] ?? 0, 2); ?> kg</div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('base_price'); ?></div>
                        <div class="acc-field-value"><?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($weightData['base_price'] ?? 0, 2); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('sold_price'); ?></div>
                        <div class="acc-field-value"><?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($weightData['sold_price'] ?? 0, 2); ?></div>
                      </div>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('profit'); ?></div>
                        <div class="acc-field-value" style="color:var(--teal);"><?php echo htmlspecialchars($ticketData['currency']); ?> <?php echo number_format($weightData['profit'] ?? 0, 2); ?></div>
                      </div>
                      <?php if (!empty($weightData['exchange_rate'])): ?>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('exchange_rate'); ?></div>
                        <div class="acc-field-value"><?php echo number_format($weightData['exchange_rate'], 2); ?></div>
                      </div>
                      <?php endif; ?>
                      <?php if (!empty($weightData['market_exchange_rate'])): ?>
                      <div class="acc-field">
                        <div class="acc-field-label"><?php echo __('market_exchange_rate'); ?></div>
                        <div class="acc-field-value"><?php echo number_format($weightData['market_exchange_rate'], 2); ?></div>
                      </div>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($weightData['remarks'])): ?>
                    <div class="acc-remarks"><strong><?php echo __('remarks'); ?></strong><?php echo nl2br(htmlspecialchars($weightData['remarks'])); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>

              </div>
              <?php endif; ?>

              <!-- Timeline Transactions -->
              <div class="trans-card">
                <div class="trans-card-header">
                  <i class="feather icon-activity"></i>
                  <?php echo __('transaction_history'); ?>
                  <span style="margin-left:auto;font-size:.75rem;opacity:.7;font-weight:400;">
                    <?php echo count($mainAccountTransactions) + count($clientTransactions) + count($supplierTransactions); ?> <?php echo __('entries'); ?>
                  </span>
                </div>

                <!-- Filter bar -->
                <div class="trans-filter-bar">
                  <button class="tf-btn active" onclick="filterTl('all', this)">
                    <i class="feather icon-layers"></i><?php echo __('all'); ?>
                    <span class="tf-count"><?php echo count($mainAccountTransactions) + count($clientTransactions) + count($supplierTransactions); ?></span>
                  </button>
                  <button class="tf-btn" onclick="filterTl('main', this)">
                    <i class="feather icon-home"></i><?php echo __('main_account'); ?>
                    <span class="tf-count"><?php echo count($mainAccountTransactions); ?></span>
                  </button>
                  <button class="tf-btn tf-client" onclick="filterTl('client', this)">
                    <i class="feather icon-user"></i><?php echo __('client'); ?>
                    <span class="tf-count"><?php echo count($clientTransactions); ?></span>
                  </button>
                  <button class="tf-btn tf-supplier" onclick="filterTl('supplier', this)">
                    <i class="feather icon-truck"></i><?php echo __('supplier'); ?>
                    <span class="tf-count"><?php echo count($supplierTransactions); ?></span>
                  </button>
                </div>

                <!-- Timeline items -->
                <div class="timeline" id="tl-container">

                  <?php
                  // Merge all transactions with source tag
                  $allTrans = [];
                  foreach ($mainAccountTransactions as $t) { $t['_src'] = 'main'; $allTrans[] = $t; }
                  foreach ($clientTransactions as $t)      { $t['_src'] = 'client'; $allTrans[] = $t; }
                  foreach ($supplierTransactions as $t)    { $t['_src'] = 'supplier'; $allTrans[] = $t; }
                  // Sort by date desc
                  usort($allTrans, fn($a,$b) => strtotime($b['transaction_date']) - strtotime($a['transaction_date']));
                  ?>

                  <?php if (empty($allTrans)): ?>
                  <div class="tl-empty">
                    <i class="feather icon-inbox"></i>
                    <?php echo __('no_transactions_found_for_this_ticket'); ?>
                  </div>
                  <?php else: ?>
                    <?php foreach ($allTrans as $i => $t):
                      $src = $t['_src'];
                      $isLast = ($i === count($allTrans) - 1);
                      $isDebit = strtolower($t['type']) === 'debit';
                      $of = $t['transaction_of'] ?? '';
                      $ofClass = 'tob-sale';
                      $ofLabel = '';
                      if ($of === 'ticket_sale')   { $ofClass = 'tob-sale';   $ofLabel = __('sale'); }
                      elseif ($of === 'ticket_refund') { $ofClass = 'tob-refund'; $ofLabel = __('refund'); }
                      elseif ($of === 'date_change')   { $ofClass = 'tob-change'; $ofLabel = __('date_change'); }
                      elseif ($of === 'weight')        { $ofClass = 'tob-weight'; $ofLabel = __('weight'); }
                      else { $ofLabel = ucfirst($of); }

                      $srcLabel = ($src === 'main') ? __('main_account') : (($src === 'client') ? __('client') : __('supplier'));
                      $currency = $t['currency'] ?? ($ticketData['currency'] ?? '');
                    ?>
                    <div class="tl-item" data-src="<?php echo $src; ?>">
                      <div class="tl-line">
                        <div class="tl-dot tl-dot-<?php echo $src; ?>"></div>
                        <?php if (!$isLast): ?><div class="tl-connector"></div><?php endif; ?>
                      </div>
                      <div class="tl-content">
                        <div class="tl-header">
                          <div>
                            <div class="tl-who tl-who-<?php echo $src; ?>"><?php echo $srcLabel; ?></div>
                            <div class="tl-desc"><?php echo htmlspecialchars($t['description'] ?? ''); ?></div>
                          </div>
                          <div class="tl-amount <?php echo $isDebit ? 'tl-debit' : 'tl-credit'; ?>">
                            <?php echo $isDebit ? '−' : '+'; ?>
                            <?php echo $currency . ' ' . htmlspecialchars($t['amount']); ?>
                          </div>
                        </div>
                        <div class="tl-meta">
                          <span class="tl-date"><?php echo date('d M Y', strtotime($t['transaction_date'])); ?></span>
                          <?php if ($ofLabel): ?>
                          <span class="tl-of-badge <?php echo $ofClass; ?>"><?php echo $ofLabel; ?></span>
                          <?php endif; ?>
                          <span class="tl-of-badge" style="background:rgba(0,0,0,.05);color:var(--muted);"><?php echo ucfirst(strtolower($t['type'])); ?></span>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php endif; // end no error ?>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function toggleAcc(id) {
    const el = document.getElementById(id);
    el.classList.toggle('open');
}

function filterTl(src, btn) {
    // Update active button
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Filter items
    document.querySelectorAll('.tl-item').forEach(item => {
        if (src === 'all' || item.dataset.src === src) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });
    // Show empty if nothing visible
    const visible = document.querySelectorAll('.tl-item:not(.hidden)').length;
    let empty = document.querySelector('.tl-empty-filter');
    if (visible === 0) {
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'tl-empty tl-empty-filter';
            empty.innerHTML = '<i class="feather icon-inbox"></i>No transactions found for this filter.';
            document.getElementById('tl-container').appendChild(empty);
        }
        empty.style.display = 'block';
    } else if (empty) {
        empty.style.display = 'none';
    }
}
</script>

<?php include '../includes/admin_footer.php'; ?>