<?php
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: customers.php');
    exit();
}

$customer_id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active' AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id,   PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id,   PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if (count($result) === 0) {
    header('Location: customers.php');
    exit();
}
$customer = $result[0];

$wallet_stmt = $pdo->prepare("SELECT * FROM customer_wallets WHERE customer_id = ? AND tenant_id = ? AND branch_id = ?");
$wallet_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
$wallet_stmt->bindParam(2, $tenant_id,   PDO::PARAM_INT);
$wallet_stmt->bindParam(3, $branch_id,   PDO::PARAM_INT);
$wallet_stmt->execute();
$wallets = $wallet_stmt->fetchAll();

$transactions_stmt = $pdo->prepare("SELECT st.* FROM sarafi_transactions st WHERE st.customer_id = ? AND st.tenant_id = ? AND st.branch_id = ? ORDER BY st.created_at ASC");
$transactions_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
$transactions_stmt->bindParam(2, $tenant_id,   PDO::PARAM_INT);
$transactions_stmt->bindParam(3, $branch_id,   PDO::PARAM_INT);
$transactions_stmt->execute();
$transactions = $transactions_stmt->fetchAll();

try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Default Name'];
} catch (PDOException $e) {
    $settings = ['agency_name' => 'Default Name'];
}

$today = date('Y-m-d');

// Pre-compute running balances per currency
$running = [];   // [currency => running_balance]
$tx_rows = [];   // enriched rows with running balance snapshot
foreach ($transactions as $tx) {
    $cur = $tx['currency'];
    if (!isset($running[$cur])) $running[$cur] = 0;
    if ($tx['type'] === 'deposit') {
        $running[$cur] += $tx['amount'];
    } elseif (in_array($tx['type'], ['withdrawal', 'hawala_send'])) {
        $running[$cur] -= $tx['amount'];
    }
    $tx['running_balance'] = $running[$cur];
    $tx_rows[] = $tx;
}

// Total deposits / withdrawals
$total_deposits = 0; $total_withdrawals = 0;
foreach ($transactions as $tx) {
    if ($tx['type'] === 'deposit') $total_deposits += $tx['amount'];
    if (in_array($tx['type'], ['withdrawal','hawala_send'])) $total_withdrawals += $tx['amount'];
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['agency_name']) ?> — <?= __('customer_statement') ?></title>
<link rel="icon" href="../Uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" type="image/x-icon">
<link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ════════════════════════════════════════════════
   SCREEN STYLES — dark navy/teal theme
════════════════════════════════════════════════ */
:root {
  --grad-start:        #4099ff;
  --grad-end:          #2ed8b6;
  --grad:              linear-gradient(135deg, var(--grad-start) 0%, var(--grad-end) 100%);
  --white:             #ffffff;
  --surface:           #f8fafc;
  --border:            #e5e7eb;
  --slate-400: #94a3b8;
  --slate-300: #cbd5e1;
  --mono: 'IBM Plex Mono', monospace;
  --display: 'Syne', sans-serif;
  --body: 'DM Sans', sans-serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--body);
  background: var(--surface);
  color: #1f2937;
  min-height: 100vh;
  font-size: 14px;
  line-height: 1.5;
}

/* ── Floating toolbar ── */
.toolbar {
  position: fixed;
  top: 20px; right: 24px;
  display: flex; gap: 10px;
  z-index: 100;
}
.toolbar-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px; border-radius: 8px;
  font-family: var(--body); font-size: 13px; font-weight: 600;
  cursor: pointer; border: none; transition: all 0.18s;
  text-decoration: none;
}
.toolbar-btn.print {
  background: var(--grad);
  color: #fff; box-shadow: 0 4px 12px rgba(64,153,255,0.18);
}
.toolbar-btn.print:hover { box-shadow: 0 8px 28px rgba(64,153,255,0.22); transform: translateY(-1px); }
.toolbar-btn.back {
  background: var(--white); color: #4099ff;
  border: 1px solid var(--border);
}
.toolbar-btn.back:hover { background: #f3f4f6; color: #2ed8b6; }

/* ── Statement wrapper ── */
.statement-wrap {
  max-width: 900px;
  margin: 0 auto;
  padding: 32px 24px 64px;
}

/* ── Document card ── */
.doc-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(64,153,255,0.18), 0 2px 6px rgba(0,0,0,0.08);
}

/* ── Document header ── */
.doc-header {
  padding: 28px 32px 24px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  position: relative;
  overflow: hidden;
}
.doc-header::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--grad);
}
.agency-block {
  display: flex;
  flex-direction: column;
  gap: 0;
  flex: 1;
  text-align: left;
}
.agency-block img {
  display: none;
}
.agency-name {
  font-family: var(--display);
  font-weight: 800;
  font-size: 20px;
  letter-spacing: -0.4px;
  color: #0d2040;
  margin: 0;
}
.agency-meta {
  font-size: 11px;
  color: #6b7280;
  line-height: 1.6;
  margin-top: 4px;
}
.header-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 0 0 auto;
}
.header-center img {
  height: 56px;
  display: block;
}
.statement-label-block { 
  text-align: right;
  flex: 1;
}
.statement-label {
  font-family: var(--display);
  font-weight: 700;
  font-size: 16px;
  color: #4099ff;
  letter-spacing: -0.2px;
  margin-bottom: 4px;
}
.statement-meta {
  font-family: var(--mono);
  font-size: 10px;
  color: #6b7280;
  line-height: 1.7;
}

/* ── Section ── */
.doc-section {
  padding: 22px 32px;
  border-bottom: 1px solid var(--border);
}
.doc-section:last-child { border-bottom: none; }
.section-label {
  font-family: var(--mono);
  font-size: 9px;
  letter-spacing: 1.3px;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 14px;
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

/* ── Customer info grid ── */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.info-block {}
.info-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 7px;
  font-size: 13px;
}
.info-key {
  font-family: var(--mono);
  font-size: 10px;
  color: #6b7280;
  white-space: nowrap;
  min-width: 100px;
}
.info-val {
  color: #0d2040;
  font-weight: 500;
}

/* ── Balance summary boxes ── */
.balance-grid {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.balance-box {
  background: #f0f9ff;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 18px;
  min-width: 140px;
  position: relative;
  overflow: hidden;
}
.balance-box::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: var(--grad);
}
.balance-cur {
  font-family: var(--mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 6px;
}
.balance-amount {
  font-family: var(--mono);
  font-size: 18px;
  font-weight: 500;
  color: #0d2040;
  letter-spacing: -0.5px;
}

/* ── Summary row (totals) ── */
.summary-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.summary-box {
  flex: 1; min-width: 130px;
  background: #f0f9ff;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 16px;
  text-align: center;
}
.summary-box .s-label { font-family: var(--mono); font-size: 9px; letter-spacing: 1px; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
.summary-box .s-val   { font-family: var(--mono); font-size: 15px; font-weight: 500; color: #0d2040; }
.summary-box.credits .s-val  { color: #0369a1; }
.summary-box.debits  .s-val  { color: #e11d48; }
.summary-box.txcount .s-val  { color: #b45309; }

/* ── Transaction table ── */
.tx-table-wrap { overflow-x: auto; }
.tx-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 650px;
}
.tx-table thead tr { border-bottom: 1px solid var(--border); }
.tx-table thead th {
  padding: 9px 12px;
  font-family: var(--mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: #6b7280;
  text-align: left;
  background: #f9fafb;
  font-weight: 500;
  white-space: nowrap;
}
.tx-table thead th:first-child { padding-left: 20px; }
.tx-table thead th:last-child  { padding-right: 20px; text-align: right; }
.tx-table thead th.num { text-align: right; }

.tx-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
.tx-table tbody tr:last-child { border-bottom: none; }
.tx-table tbody tr:hover { background: #f9fafb; }
.tx-table tbody td {
  padding: 10px 12px;
  vertical-align: middle;
  font-size: 12px;
  color: #374151;
}
.tx-table tbody td:first-child { padding-left: 20px; }
.tx-table tbody td:last-child  { padding-right: 20px; text-align: right; }
.tx-table tbody td.num { text-align: right; font-family: var(--mono); font-size: 12px; }

.td-date { font-family: var(--mono); font-size: 11px; color: #6b7280; white-space: nowrap; }

.type-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 20px;
  font-size: 10px; font-weight: 600; white-space: nowrap;
}
.type-badge.deposit    { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.type-badge.withdrawal { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.type-badge.hawala_send { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
.type-badge.exchange   { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

.credit-amt  { color: #0369a1;  font-family: var(--mono); font-size: 12px; }
.debit-amt   { color: #b91c1c;  font-family: var(--mono); font-size: 12px; }
.balance-amt { color: #0d2040; font-family: var(--mono); font-size: 12px; font-weight: 500; }
.balance-neg { color: #b91c1c; }
.dash { color: #d1d5db; }

/* ── Footer ── */
.doc-footer {
  padding: 18px 32px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-family: var(--mono);
  font-size: 10px;
  color: #6b7280;
  flex-wrap: wrap;
  gap: 8px;
}

/* ════════════════════════════════════════════════
   PRINT STYLES — white, clean, formal
════════════════════════════════════════════════ */
@media print {
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

  body {
    background: #fff !important;
    color: #111 !important;
    font-family: 'DM Sans', 'Segoe UI', Arial, sans-serif;
    font-size: 11pt;
  }

  .toolbar { display: none !important; }

  .statement-wrap { padding: 0; max-width: 100%; }

  .doc-card {
    background: #fff !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
  }

  .doc-header {
    border-bottom: 2px solid #0891b2 !important;
    background: #fff !important;
    padding: 16px 0 14px !important;
  }
  .doc-header::before { background: #0891b2 !important; height: 3px; }

  .agency-name { color: #0d2040 !important; font-size: 18pt !important; }
  .agency-meta { color: #555 !important; }
  .statement-label { color: #0891b2 !important; font-size: 15pt !important; }
  .statement-meta  { color: #555 !important; }

  .doc-section { padding: 14px 0 !important; border-bottom: 1px solid #e5e7eb !important; }
  .section-label { color: #6b7280 !important; }
  .section-label::after { background: #e5e7eb !important; }

  .info-key { color: #6b7280 !important; }
  .info-val { color: #111 !important; }

  .balance-box {
    background: #f8fafc !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 6px !important;
  }
  .balance-box::before { display: block !important; }
  .balance-cur    { color: #6b7280 !important; }
  .balance-amount { color: #0d2040 !important; }

  .summary-box { background: #f8fafc !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; }
  .summary-box .s-label { color: #6b7280 !important; }
  .summary-box.credits .s-val { color: #0891b2 !important; }
  .summary-box.debits  .s-val { color: #e11d48 !important; }
  .summary-box.txcount .s-val { color: #92400e !important; }

  .tx-table thead th {
    background: #f1f5f9 !important;
    color: #475569 !important;
    border-bottom: 1px solid #cbd5e1 !important;
  }
  .tx-table tbody tr { border-bottom: 1px solid #f1f5f9 !important; }
  .tx-table tbody tr:hover { background: transparent !important; }
  .tx-table tbody td { color: #374151 !important; }
  .td-date  { color: #6b7280 !important; }

  .type-badge.deposit    { background: #e0f2fe !important; color: #0369a1 !important; border-color: #bae6fd !important; }
  .type-badge.withdrawal { background: #fee2e2 !important; color: #b91c1c !important; border-color: #fecaca !important; }
  .type-badge.hawala_send { background: #ede9fe !important; color: #5b21b6 !important; border-color: #ddd6fe !important; }
  .type-badge.exchange   { background: #fef3c7 !important; color: #92400e !important; border-color: #fde68a !important; }

  .credit-amt  { color: #0369a1 !important; }
  .debit-amt   { color: #b91c1c !important; }
  .balance-amt { color: #0d2040 !important; }
  .balance-neg { color: #b91c1c !important; }
  .dash        { color: #d1d5db !important; }

  .doc-footer { border-top: 1px solid #e5e7eb !important; color: #6b7280 !important; padding: 12px 0 !important; }
}

@media (max-width: 600px) {
  .doc-header  { flex-direction: column; gap: 16px; padding: 20px 16px; }
  .agency-block { text-align: center; }
  .statement-label-block { text-align: center; }
  .info-grid   { grid-template-columns: 1fr; }
  .statement-wrap { padding: 16px 12px 40px; }
  .doc-section { padding: 16px 16px; }
  .doc-footer  { padding: 14px 16px; }
  .header-center img { height: 44px; }
  .agency-name { font-size: 18px; }
  .statement-label { font-size: 14px; }
}
</style>
</head>
<body>

<!-- Floating toolbar -->
<div class="toolbar no-print">
  <a href="customers.php" class="toolbar-btn back">
    ← <?= __('back') ?>
  </a>
  <button class="toolbar-btn print" onclick="window.print()">
    <i class="fas fa-print"></i>
    <?= __('print') ?>
  </button>
</div>

<div class="statement-wrap">
  <div class="doc-card">

    <!-- ── HEADER ─────────────────────────────────── -->
    <div class="doc-header">
      <div class="agency-block">
        <div class="agency-name"><?= htmlspecialchars($settings['agency_name']) ?></div>
        <div class="agency-meta">
          <?php if (!empty($settings['address'])): ?><?= htmlspecialchars($settings['address']) ?><br><?php endif; ?>
          <?php if (!empty($settings['phone'])): ?><?= htmlspecialchars($settings['phone']) ?><br><?php endif; ?>
          <?php if (!empty($settings['email'])): ?><?= htmlspecialchars($settings['email']) ?><?php endif; ?>
        </div>
      </div>
      <div class="header-center">
        <?php if (!empty($settings['logo'])): ?>
        <img src="../Uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="logo">
        <?php endif; ?>
      </div>
      <div class="statement-label-block">
        <div class="statement-label"><?= __('customer_statement') ?></div>
        <div class="statement-meta">
          <?= __('statement_date') ?>: <?= $today ?><br>
          <?= __('customer_id') ?>: #<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?><br>
          <?= __('generated_on') ?>: <?= date('Y-m-d H:i') ?>
        </div>
      </div>
    </div>

    <!-- ── CUSTOMER INFO ─────────────────────────── -->
    <div class="doc-section">
      <div class="section-label"><?= __('customer_information') ?></div>
      <div class="info-grid">
        <div class="info-block">
          <div class="info-row">
            <span class="info-key"><?= __('customer_name') ?></span>
            <span class="info-val"><?= htmlspecialchars($customer['name']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-key"><?= __('customer_phone') ?></span>
            <span class="info-val"><?= htmlspecialchars($customer['phone']) ?></span>
          </div>
          <?php if (!empty($customer['email'])): ?>
          <div class="info-row">
            <span class="info-key"><?= __('customer_email') ?></span>
            <span class="info-val"><?= htmlspecialchars($customer['email']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($customer['address'])): ?>
          <div class="info-row">
            <span class="info-key"><?= __('customer_address') ?></span>
            <span class="info-val"><?= htmlspecialchars($customer['address']) ?></span>
          </div>
          <?php endif; ?>
        </div>
        <div class="info-block">
          <div class="info-row">
            <span class="info-key"><?= __('customer_id') ?></span>
            <span class="info-val">#<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div class="info-row">
            <span class="info-key"><?= __('customer_since') ?></span>
            <span class="info-val"><?= date('Y-m-d', strtotime($customer['created_at'])) ?></span>
          </div>
          <div class="info-row">
            <span class="info-key"><?= __('total_transactions') ?></span>
            <span class="info-val"><?= count($transactions) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ACCOUNT BALANCES ───────────────────────── -->
    <div class="doc-section">
      <div class="section-label"><?= __('account_balance') ?></div>
      <?php if (count($wallets) > 0): ?>
      <div class="balance-grid">
        <?php foreach ($wallets as $w): ?>
        <div class="balance-box">
          <div class="balance-cur"><?= htmlspecialchars($w['currency']) ?></div>
          <div class="balance-amount"><?= number_format($w['balance'], 2) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p style="color:var(--slate-400);font-size:13px;"><?= __('no_balance') ?></p>
      <?php endif; ?>
    </div>

    <!-- ── SUMMARY ────────────────────────────────── -->
    <div class="doc-section">
      <div class="section-label"><?= __('summary') ?></div>
      <div class="summary-row">
        <div class="summary-box credits">
          <div class="s-label"><?= __('total_credits') ?></div>
          <div class="s-val"><?= number_format($total_deposits, 2) ?></div>
        </div>
        <div class="summary-box debits">
          <div class="s-label"><?= __('total_debits') ?></div>
          <div class="s-val"><?= number_format($total_withdrawals, 2) ?></div>
        </div>
        <div class="summary-box txcount">
          <div class="s-label"><?= __('transactions') ?></div>
          <div class="s-val"><?= count($transactions) ?></div>
        </div>
      </div>
    </div>

    <!-- ── TRANSACTION HISTORY ────────────────────── -->
    <div class="doc-section">
      <div class="section-label"><?= __('recent_transactions') ?></div>

      <?php if (count($tx_rows) > 0): ?>
      <div class="tx-table-wrap">
        <table class="tx-table">
          <thead>
            <tr>
              <th><?= __('date') ?></th>
              <th><?= __('type') ?></th>
              <th><?= __('description') ?></th>
              <th><?= __('reference') ?></th>
              <th class="num"><?= __('debit') ?></th>
              <th class="num"><?= __('credit') ?></th>
              <th class="num"><?= __('balance') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tx_rows as $tx):
              $is_credit     = ($tx['type'] === 'deposit');
              $is_debit      = in_array($tx['type'], ['withdrawal','hawala_send']);
              $bal_negative  = $tx['running_balance'] < 0;
            ?>
            <tr>
              <td><span class="td-date"><?= date('Y-m-d', strtotime($tx['created_at'])) ?></span></td>
              <td><span class="type-badge <?= htmlspecialchars($tx['type']) ?>"><?= __($tx['type']) ?></span></td>
              <td style="max-width:180px;color:var(--slate-400);font-size:12px;">
                <?= $tx['notes'] ? htmlspecialchars($tx['notes']) : '<span class="dash">—</span>' ?>
              </td>
              <td style="font-family:var(--mono);font-size:10px;color:var(--slate-400);">
                <?= $tx['reference_number'] ? htmlspecialchars($tx['reference_number']) : '<span class="dash">—</span>' ?>
              </td>
              <td class="num">
                <?php if ($is_debit): ?>
                  <span class="debit-amt">− <?= number_format($tx['amount'], 2) ?> <small style="font-size:9px;opacity:0.7;"><?= $tx['currency'] ?></small></span>
                <?php else: ?>
                  <span class="dash">—</span>
                <?php endif; ?>
              </td>
              <td class="num">
                <?php if ($is_credit): ?>
                  <span class="credit-amt">+ <?= number_format($tx['amount'], 2) ?> <small style="font-size:9px;opacity:0.7;"><?= $tx['currency'] ?></small></span>
                <?php else: ?>
                  <span class="dash">—</span>
                <?php endif; ?>
              </td>
              <td class="num">
                <span class="balance-amt <?= $bal_negative ? 'balance-neg' : '' ?>">
                  <?= number_format(abs($tx['running_balance']), 2) ?>
                  <?= $bal_negative ? '<small style="font-size:9px;">DR</small>' : '' ?>
                  <small style="font-size:9px;opacity:0.6;"><?= $tx['currency'] ?></small>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p style="color:var(--slate-400);font-size:13px;padding:8px 0;"><?= __('no_recent_transactions') ?></p>
      <?php endif; ?>
    </div>

    <!-- ── FOOTER ─────────────────────────────────── -->
    <div class="doc-footer">
      <span><?= __('statement_disclaimer') ?></span>
      <span><?= __('generated_on') ?>: <?= date('Y-m-d H:i:s') ?></span>
    </div>

  </div><!-- /doc-card -->
</div><!-- /statement-wrap -->

<script src="../assets/js/vendor-all.min.js"></script>
</body>
</html>