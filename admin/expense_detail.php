<?php
// ── auth / db bootstrap (unchanged from original) ──────────────────────────
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once 'security.php';
enforce_auth();
require_permission('finance.expenses');
include '../includes/db.php';

$expense = null; $transactions = []; $errorMessage = '';

if (isset($_GET['id'])) {
    $expenseId = DbSecurity::validateInput($_GET['id'], 'int');
    if (!$expenseId) {
        $errorMessage = "Invalid expense ID.";
    } else {
        $stmt = $pdo->prepare("SELECT e.*, ec.name as category_name, esc.name as sub_category_name, ma.name as account_name,
                  mat.receipt as receipt_number
                  FROM expenses e
                  LEFT JOIN expense_categories ec ON e.category_id=ec.id AND e.tenant_id=ec.tenant_id AND e.branch_id=ec.branch_id
                  LEFT JOIN expense_categories esc ON e.sub_category_id=esc.id AND e.tenant_id=esc.tenant_id AND e.branch_id=esc.branch_id
                  LEFT JOIN main_account ma ON e.main_account_id=ma.id AND e.tenant_id=ma.tenant_id AND e.branch_id=ma.branch_id
                  LEFT JOIN main_account_transactions mat ON e.id=mat.reference_id AND mat.transaction_of='expense' AND mat.tenant_id=e.tenant_id AND mat.branch_id=e.branch_id
                  WHERE e.id=? AND e.tenant_id=? AND e.branch_id=?");
        $stmt->execute([$expenseId, $tenant_id, $branch_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expense) {
            $errorMessage = "Expense not found.";
        } else {
            $stmt2 = $pdo->prepare("SELECT 'Main Account' AS transaction_type,mat.id,mat.type,mat.amount,
                mat.balance,mat.currency,mat.description,mat.transaction_of,mat.receipt,
                mat.created_at AS transaction_date
                FROM main_account_transactions mat
                WHERE mat.reference_id=? AND mat.transaction_of='expense' AND mat.tenant_id=? AND mat.branch_id=?");
            $stmt2->execute([$expenseId, $tenant_id, $branch_id]);
            $transactions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $errorMessage = "Expense ID is required.";
}

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=Mulish:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── CSS Variables ──────────────────────────────────────────── */
:root {
  --bg:        #f4f6fb;
  --surface:   #ffffff;
  --border:    #e8ecf4;
  --accent:    #3d7fff;
  --accent2:   #00c9a7;
  --danger:    #ff4d6d;
  --warn:      #f59e0b;
  --text-h:    #111827;
  --text-b:    #374151;
  --text-m:    #6b7280;
  --text-s:    #9ca3af;
  --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md: 0 4px 16px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.04);
  --shadow-lg: 0 8px 32px rgba(0,0,0,.1), 0 2px 8px rgba(0,0,0,.06);
  --radius:    14px;
  --radius-sm: 8px;
  --font-h:    'Syne', sans-serif;
  --font-b:    'Mulish', sans-serif;
  --font-mono: 'DM Mono', monospace;
}

/* ── Reset / Base ───────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-b); background: var(--bg); color: var(--text-b); }
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }

/* ── Page wrapper ───────────────────────────────────────────── */
.exp-page { padding: 28px 24px 120px; max-width: 1100px; margin: 0 auto; }

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--text-m); margin-bottom:20px; }
.breadcrumb a { color:var(--text-m); }
.breadcrumb a:hover { color:var(--accent); text-decoration:none; }
.breadcrumb .sep { color:var(--border); font-size:16px; }

/* ── Hero header ────────────────────────────────────────────── */
.hero {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-md);
  padding: 28px 32px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 24px;
  position: relative;
  overflow: hidden;
  animation: slideUp .4s ease both;
}
.hero::before {
  content:'';
  position:absolute; top:0; left:0; right:0; height:4px;
  background: linear-gradient(90deg, var(--accent) 0%, var(--accent2) 100%);
}
.hero-icon {
  width:56px; height:56px; border-radius:14px;
  background: linear-gradient(135deg, #eef3ff 0%, #e0f7f3 100%);
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.hero-icon svg { width:26px; height:26px; color:var(--accent); }
.hero-meta { flex:1; }
.hero-meta h1 { font-family:var(--font-h); font-size:22px; font-weight:700; color:var(--text-h); }
.hero-meta .sub { font-size:13px; color:var(--text-m); margin-top:4px; }
.hero-amount {
  text-align:right;
}
.hero-amount .label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--text-s); margin-bottom:4px; }
.hero-amount .value {
  font-family:var(--font-mono); font-size:32px; font-weight:500;
  color:var(--danger); letter-spacing:-.5px;
}

/* ── Two-column layout ──────────────────────────────────────── */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
@media(max-width:720px){ .grid-2{ grid-template-columns:1fr; } }

/* ── Card ───────────────────────────────────────────────────── */
.card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-md);
  overflow: hidden;
  animation: slideUp .4s ease both;
}
.card:nth-child(2){ animation-delay:.06s }
.card:nth-child(3){ animation-delay:.12s }
.card:nth-child(4){ animation-delay:.18s }
.card-head {
  padding: 16px 24px;
  border-bottom: 1px solid var(--border);
  display:flex; align-items:center; gap:10px;
}
.card-head h2 { font-family:var(--font-h); font-size:14px; font-weight:600; color:var(--text-h); flex:1; }
.card-head svg { width:16px; height:16px; color:var(--accent); flex-shrink:0; }
.card-body { padding: 24px; }

/* ── Field rows ─────────────────────────────────────────────── */
.field-list { display:flex; flex-direction:column; gap:16px; }
.field { display:flex; flex-direction:column; gap:4px; }
.field .key {
  font-size:11px; text-transform:uppercase; letter-spacing:.07em;
  color:var(--text-s); font-weight:600;
}
.field .val { font-size:15px; color:var(--text-h); font-weight:500; }
.field .val.mono { font-family:var(--font-mono); font-size:14px; }
.field .val.danger { color:var(--danger); font-family:var(--font-mono); font-size:18px; }
.field .val.muted { color:var(--text-m); font-weight:400; font-size:14px; }

/* ── Badges ─────────────────────────────────────────────────── */
.badge {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:100px; font-size:12px; font-weight:600;
}
.badge-debit  { background:#fff1f3; color:#c9234a; }
.badge-credit { background:#ecfdf5; color:#059669; }
.badge-category { background:#eff6ff; color:#2563eb; }

/* ── Timeline ───────────────────────────────────────────────── */
.timeline { position:relative; padding-left:28px; }
.timeline::before {
  content:''; position:absolute; left:8px; top:8px; bottom:8px;
  width:2px; background: linear-gradient(to bottom, var(--accent), var(--accent2));
  border-radius:2px;
}
.tl-item {
  position:relative; padding:0 0 24px 20px;
  animation: fadeIn .4s ease both;
}
.tl-item:last-child { padding-bottom:0; }
.tl-dot {
  position:absolute; left:-28px; top:4px;
  width:14px; height:14px; border-radius:50%;
  border:2px solid var(--surface);
  box-shadow:0 0 0 2px var(--accent);
  background:var(--accent);
  transition: transform .2s;
}
.tl-item.credit .tl-dot { background:var(--accent2); box-shadow:0 0 0 2px var(--accent2); }
.tl-item:hover .tl-dot { transform:scale(1.3); }
.tl-card {
  background:#fafbff; border:1px solid var(--border);
  border-radius: var(--radius-sm); padding:14px 16px;
  transition: box-shadow .2s, transform .2s;
  cursor:default;
}
.tl-card:hover { box-shadow: var(--shadow-md); transform:translateY(-1px); }
.tl-top { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.tl-top .tl-id { font-family:var(--font-mono); font-size:11px; color:var(--text-s); }
.tl-top .tl-amount { margin-left:auto; font-family:var(--font-mono); font-weight:500; font-size:15px; }
.tl-top .tl-amount.debit  { color:var(--danger); }
.tl-top .tl-amount.credit { color:#059669; }
.tl-desc { font-size:13px; color:var(--text-m); }
.tl-date { font-size:11px; color:var(--text-s); margin-top:6px; }

/* ── Receipt button ─────────────────────────────────────────── */
.btn-receipt {
  display:inline-flex; align-items:center; gap:6px;
  padding:6px 14px; border-radius:var(--radius-sm);
  background:#eff6ff; color:var(--accent); font-size:13px; font-weight:600;
  border:1px solid #bfdbfe; cursor:pointer; transition:.2s;
}
.btn-receipt:hover { background:var(--accent); color:#fff; border-color:var(--accent); }

/* ── Sticky action bar ──────────────────────────────────────── */
.action-bar {
  position:fixed; bottom:0; left:0; right:0; z-index:100;
  background:var(--surface);
  border-top:1px solid var(--border);
  box-shadow:0 -4px 24px rgba(0,0,0,.08);
  padding:14px 24px;
  display:flex; align-items:center; gap:12px; justify-content:flex-end;
}
.btn {
  display:inline-flex; align-items:center; gap:7px;
  padding:9px 20px; border-radius:var(--radius-sm);
  font-family:var(--font-b); font-size:13px; font-weight:600;
  cursor:pointer; border:none; transition:.18s; text-decoration:none !important;
}
.btn-ghost { background:transparent; color:var(--text-m); border:1px solid var(--border); }
.btn-ghost:hover { background:var(--bg); color:var(--text-h); }
.btn-primary { background:var(--accent); color:#fff; }
.btn-primary:hover { background:#2c6cf0; box-shadow:0 4px 14px rgba(61,127,255,.35); }
.btn-print { background:#f3f4f6; color:var(--text-b); border:1px solid var(--border); }
.btn-print:hover { background:#e5e7eb; }
.btn svg { width:15px; height:15px; }

/* ── Receipt modal ──────────────────────────────────────────── */
.modal-overlay {
  display:none; position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,.55); backdrop-filter:blur(4px);
  align-items:center; justify-content:center;
}
.modal-overlay.active { display:flex; }
.modal-box {
  background:var(--surface); border-radius:var(--radius);
  box-shadow:var(--shadow-lg); padding:28px; max-width:640px; width:90%;
  animation: popIn .25s ease both;
}
.modal-box img { width:100%; border-radius:var(--radius-sm); }
.modal-footer { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; }

/* ── Animations ─────────────────────────────────────────────── */
@keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn  { from{opacity:0;transform:translateX(-6px)} to{opacity:1;transform:translateX(0)} }
@keyframes popIn   { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }

/* ── Error state ────────────────────────────────────────────── */
.error-banner {
  background:#fff1f3; border:1px solid #fecdd3; border-radius:var(--radius);
  padding:20px 24px; color:#be123c; font-weight:500; display:flex; gap:10px; align-items:center;
}
</style>
<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
<div class="exp-page">

  <!-- Breadcrumb -->
  <nav class="breadcrumb">
    <a href="index.php">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    </a>
    <span class="sep">/</span>
    <a href="expense_management.php"><?= __('expenses') ?></a>
    <span class="sep">/</span>
    <span><?= __('expense_details') ?></span>
  </nav>

  <?php if (!empty($errorMessage)): ?>
    <div class="error-banner">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($errorMessage); ?>
    </div>

  <?php else: ?>

    <!-- ── Hero ────────────────────────────────────────────────── -->
    <div class="hero">
      <div class="hero-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
      </div>
      <div class="hero-meta">
        <h1><?= __('expense_details') ?> <span style="color:var(--text-m);font-weight:400">#<?php echo htmlspecialchars($expense['id']); ?></span></h1>
        <div class="sub">
          <span class="badge badge-category"><?php echo htmlspecialchars($expense['category_name']); ?></span>
          &nbsp;·&nbsp; <?php echo htmlspecialchars(date('D, d M Y', strtotime($expense['date']))); ?>
        </div>
      </div>
      <div class="hero-amount">
        <div class="label"><?= __('amount') ?></div>
        <div class="value">
          <?php echo htmlspecialchars($expense['currency']); ?>
          <?php echo number_format($expense['amount'], 2); ?>
        </div>
      </div>
    </div>

    <!-- ── Two-column info ──────────────────────────────────────── -->
    <div class="grid-2">

      <!-- Left card: Expense info -->
      <div class="card">
        <div class="card-head">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <h2><?= __('expense_information') ?></h2>
        </div>
        <div class="card-body">
          <div class="field-list">
            <div class="field">
              <span class="key"><?= __('description') ?></span>
              <span class="val"><?php echo htmlspecialchars($expense['description']); ?></span>
            </div>
            <div class="field">
              <span class="key"><?= __('category') ?></span>
              <span class="val"><span class="badge badge-category"><?php echo htmlspecialchars($expense['category_name']); ?></span></span>
            </div>
            <?php if (!empty($expense['sub_category_name'])): ?>
            <div class="field">
              <span class="key"><?= __('sub_category') ?></span>
              <span class="val"><span class="badge badge-category"><?php echo htmlspecialchars($expense['sub_category_name']); ?></span></span>
            </div>
            <?php endif; ?>
            <div class="field">
              <span class="key"><?= __('amount') ?></span>
              <span class="val danger"><?php echo htmlspecialchars($expense['currency']); ?> <?php echo number_format($expense['amount'], 2); ?></span>
            </div>
            <?php if (!empty($expense['receipt_number'])): ?>
            <div class="field">
              <span class="key"><?= __('receipt_number') ?></span>
              <span class="val mono"><?php echo htmlspecialchars($expense['receipt_number']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($expense['receipt_file'])): ?>
            <div class="field">
              <span class="key"><?= __('receipt_file') ?></span>
              <span class="val">
                <button class="btn-receipt" onclick="showReceipt('<?php echo htmlspecialchars($expense['receipt_file']); ?>')">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <?= __('view_receipt') ?>
                </button>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right card: Meta info -->
      <div class="card">
        <div class="card-head">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <h2><?= __('metadata') ?></h2>
        </div>
        <div class="card-body">
          <div class="field-list">
            <div class="field">
              <span class="key"><?= __('date') ?></span>
              <span class="val"><?php echo htmlspecialchars(date('D, d M Y', strtotime($expense['date']))); ?></span>
            </div>
            <div class="field">
              <span class="key"><?= __('account') ?></span>
              <span class="val"><?php echo htmlspecialchars($expense['account_name']); ?></span>
            </div>
            <div class="field">
              <span class="key"><?= __('created_at') ?></span>
              <span class="val muted mono"><?php echo htmlspecialchars(date('d M Y · H:i', strtotime($expense['created_at']))); ?></span>
            </div>
            <?php if (!empty($expense['allocation_id'])): ?>
            <div class="field">
              <span class="key"><?= __('budget_allocation') ?></span>
              <span class="val">
                <a href="budget_allocation_detail.php?id=<?php echo htmlspecialchars($expense['allocation_id']); ?>">
                  <?= __('view_allocation') ?> #<?php echo htmlspecialchars($expense['allocation_id']); ?> →
                </a>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Transaction Timeline ──────────────────────────────────── -->
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <h2><?= __('transaction_history') ?></h2>
        <?php if (!empty($transactions)): ?>
          <span class="badge badge-category" style="margin-left:auto"><?php echo count($transactions); ?> <?= __('entries') ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (empty($transactions)): ?>
          <div style="text-align:center;padding:32px;color:var(--text-s)">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;display:block;margin-inline:auto"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= __('no_transactions_found_for_this_expense') ?>
          </div>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($transactions as $i => $tx):
              $isDebit = strtolower($tx['type']) === 'debit';
              $cls = $isDebit ? 'debit' : 'credit';
              $prefix = $isDebit ? '−' : '+';
            ?>
            <div class="tl-item <?php echo $cls; ?>" style="animation-delay:<?php echo $i * 0.07; ?>s">
              <div class="tl-dot"></div>
              <div class="tl-card">
                <div class="tl-top">
                  <span class="badge badge-<?php echo $cls; ?>">
                    <?php if ($isDebit): ?>
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                    <?php else: ?>
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($tx['type']); ?>
                  </span>
                  <span class="tl-id">#<?php echo htmlspecialchars($tx['id']); ?></span>
                  <span class="tl-amount <?php echo $cls; ?>">
                    <?php echo $prefix . ' ' . htmlspecialchars($tx['currency']) . ' ' . number_format($tx['amount'], 2); ?>
                  </span>
                </div>
                <div class="tl-desc"><?php echo htmlspecialchars($tx['description']); ?></div>
                <div class="tl-date">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?php echo htmlspecialchars(date('d M Y · H:i', strtotime($tx['transaction_date']))); ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>
</div>

<!-- ── Sticky Action Bar ─────────────────────────────────────────── -->
<div class="action-bar">
  <a href="expense_management.php" class="btn btn-ghost">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    <?= __('back_to_expenses') ?>
  </a>
  <?php if (!empty($expense)): ?>
  <a href="../api/expense/print_expense.php?id=<?php echo htmlspecialchars($expenseId); ?>" target="_blank" class="btn btn-print">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    <?= __('print_details') ?>
  </a>
  <?php endif; ?>
</div>

<!-- ── Receipt Modal ─────────────────────────────────────────────── -->
<div class="modal-overlay" id="receiptModal" onclick="if(event.target===this)closeReceipt()">
  <div class="modal-box">
    <h3 style="font-family:var(--font-h);font-size:16px;font-weight:600;color:var(--text-h);margin-bottom:16px"><?= __('receipt') ?></h3>
    <img id="receiptImg" src="" alt="Receipt">
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeReceipt()"><?= __('close') ?></button>
      <a id="receiptDownload" href="" class="btn btn-primary" download>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <?= __('download') ?>
      </a>
    </div>
  </div>
</div>
</div>
</div>
<script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<script>
function showReceipt(file) {
  const url = '../uploads/expense_receipt/' + file;
  document.getElementById('receiptImg').src = url;
  document.getElementById('receiptDownload').href = url;
  document.getElementById('receiptModal').classList.add('active');
}
function closeReceipt() {
  document.getElementById('receiptModal').classList.remove('active');
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeReceipt(); });
</script>

<?php include '../includes/admin_footer.php'; ?>