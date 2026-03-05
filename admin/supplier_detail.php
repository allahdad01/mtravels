<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

require_once '../includes/InputValidator.php';
include '../includes/db.php';

$supplierId = InputValidator::getInt($_GET['id'] ?? '', 0, 1);
$supplierData = null;
$transactions = [];
$error = null;

if (!$supplierId) {
    $error = "No supplier ID provided";
} else {
    $supplierQuery = "SELECT id, name, contact_person, supplier_type, phone, email, address, currency, balance, created_at, updated_at FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($supplierQuery);
    $stmt->execute([$supplierId, $tenant_id, $branch_id]);
    $supplierData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplierData) {
        $error = "Supplier not found";
    } else {
        $transactionsQuery = "SELECT
                st.id,
                st.supplier_id,
                st.amount,
                st.transaction_type,
                st.remarks AS description,
                st.reference_id,
                st.transaction_of,
                st.transaction_date
            FROM supplier_transactions st
            WHERE st.supplier_id = ? AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.transaction_date DESC";

        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$supplierId, $tenant_id, $branch_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Compute KPI stats
$totalCredit = 0;
$totalDebit = 0;
foreach ($transactions as $t) {
    if (strtolower($t['transaction_type']) === 'credit') {
        $totalCredit += $t['amount'];
    } else {
        $totalDebit += $t['amount'];
    }
}

include '../includes/header.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --primary:       #1a56db;
        --primary-light: #e8f0fe;
        --primary-soft:  #dbeafe;
        --success:       #0d9488;
        --success-light: #d1fae5;
        --danger:        #dc2626;
        --danger-light:  #fee2e2;
        --warning:       #d97706;
        --warning-light: #fef3c7;
        --neutral-50:    #f9fafb;
        --neutral-100:   #f3f4f6;
        --neutral-200:   #e5e7eb;
        --neutral-400:   #9ca3af;
        --neutral-600:   #4b5563;
        --neutral-700:   #374151;
        --neutral-900:   #111827;
        --surface:       #ffffff;
        --radius-sm:     6px;
        --radius-md:     10px;
        --radius-lg:     14px;
        --shadow-sm:     0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
        --shadow-md:     0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.05);
        --shadow-lg:     0 10px 30px rgba(0,0,0,.10);
    }

    * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }

    /* ── Page wrapper ── */
    .sd-wrap {
        padding: 24px;
        background: var(--neutral-50);
        min-height: 100vh;
    }

    /* ── Top bar ── */
    .sd-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
    }
    .sd-topbar-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .sd-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--neutral-600);
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 999px;
        border: 1.5px solid var(--neutral-200);
        background: var(--surface);
        transition: all .2s;
    }
    .sd-back-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }
    .sd-page-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--neutral-900);
        margin: 0;
        letter-spacing: -0.3px;
    }
    .sd-page-sub {
        font-size: 0.8rem;
        color: var(--neutral-400);
        font-weight: 400;
        display: block;
        margin-top: 1px;
    }

    /* ── KPI row ── */
    .sd-kpi-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .sd-kpi {
        background: var(--surface);
        border-radius: var(--radius-lg);
        padding: 20px 22px;
        box-shadow: var(--shadow-sm);
        border: 1.5px solid var(--neutral-100);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: box-shadow .2s;
    }
    .sd-kpi:hover { box-shadow: var(--shadow-md); }
    .sd-kpi-icon {
        width: 46px; height: 46px;
        border-radius: var(--radius-md);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .sd-kpi-icon.blue   { background: var(--primary-light); color: var(--primary); }
    .sd-kpi-icon.green  { background: var(--success-light); color: var(--success); }
    .sd-kpi-icon.red    { background: var(--danger-light);  color: var(--danger); }
    .sd-kpi-icon.amber  { background: var(--warning-light); color: var(--warning); }
    .sd-kpi-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--neutral-400);
        text-transform: uppercase;
        letter-spacing: .6px;
        margin: 0 0 4px;
    }
    .sd-kpi-value {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--neutral-900);
        margin: 0;
        font-family: 'DM Mono', monospace;
        letter-spacing: -0.5px;
    }
    .sd-kpi-value.danger { color: var(--danger); }
    .sd-kpi-value.success { color: var(--success); }

    /* ── Cards ── */
    .sd-card {
        background: var(--surface);
        border-radius: var(--radius-lg);
        border: 1.5px solid var(--neutral-100);
        box-shadow: var(--shadow-sm);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .sd-card-head {
        padding: 16px 22px;
        border-bottom: 1.5px solid var(--neutral-100);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--neutral-50);
    }
    .sd-card-title {
        font-size: 0.925rem;
        font-weight: 600;
        color: var(--neutral-700);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sd-card-title i { color: var(--primary); font-size: 1rem; }
    .sd-card-body { padding: 22px; }

    /* ── Supplier profile layout ── */
    .sd-profile {
        display: flex;
        gap: 28px;
        align-items: flex-start;
    }
    .sd-avatar {
        width: 80px; height: 80px;
        border-radius: var(--radius-lg);
        background: var(--primary-light);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem;
        color: var(--primary);
        flex-shrink: 0;
        font-weight: 700;
        letter-spacing: -1px;
        border: 2px solid var(--primary-soft);
    }
    .sd-profile-info { flex: 1; }
    .sd-supplier-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--neutral-900);
        margin: 0 0 4px;
        letter-spacing: -0.4px;
    }
    .sd-supplier-type {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary);
        background: var(--primary-light);
        padding: 3px 10px;
        border-radius: 999px;
        margin-bottom: 16px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }
    .sd-meta-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px 24px;
    }
    .sd-meta-item label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--neutral-400);
        text-transform: uppercase;
        letter-spacing: .6px;
        display: block;
        margin-bottom: 3px;
    }
    .sd-meta-item span {
        font-size: 0.88rem;
        color: var(--neutral-700);
        font-weight: 500;
    }
    .sd-meta-item span.muted { color: var(--neutral-400); font-style: italic; }

    /* ── Divider ── */
    .sd-divider {
        width: 1.5px;
        background: var(--neutral-100);
        align-self: stretch;
        margin: 0 4px;
        flex-shrink: 0;
    }

    /* ── Balance panel ── */
    .sd-balance-panel {
        width: 200px;
        flex-shrink: 0;
        text-align: center;
        padding: 20px;
        border-radius: var(--radius-md);
        border: 1.5px solid var(--neutral-100);
    }
    .sd-balance-panel.owed   { background: var(--danger-light);  border-color: #fca5a5; }
    .sd-balance-panel.clear  { background: var(--success-light); border-color: #6ee7b7; }
    .sd-balance-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--neutral-400);
        text-transform: uppercase;
        letter-spacing: .6px;
        margin-bottom: 6px;
    }
    .sd-balance-value {
        font-size: 1.6rem;
        font-weight: 700;
        font-family: 'DM Mono', monospace;
        letter-spacing: -1px;
        margin: 0;
    }
    .sd-balance-value.owed  { color: var(--danger); }
    .sd-balance-value.clear { color: var(--success); }
    .sd-balance-status {
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 4px;
    }
    .sd-balance-status.owed  { color: var(--danger); }
    .sd-balance-status.clear { color: var(--success); }

    /* ── Transactions filter bar ── */
    .sd-filter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .sd-search-box {
        position: relative;
        flex: 1;
    }
    .sd-search-box input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border: 1.5px solid var(--neutral-200);
        border-radius: var(--radius-md);
        font-size: 0.85rem;
        color: var(--neutral-700);
        outline: none;
        transition: border-color .2s;
        font-family: 'DM Sans', sans-serif;
    }
    .sd-search-box input:focus { border-color: var(--primary); }
    .sd-search-box i {
        position: absolute;
        left: 11px; top: 50%;
        transform: translateY(-50%);
        color: var(--neutral-400);
        font-size: 0.875rem;
    }
    .sd-filter-pill {
        padding: 8px 16px;
        border-radius: 999px;
        border: 1.5px solid var(--neutral-200);
        background: var(--surface);
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--neutral-600);
        cursor: pointer;
        transition: all .2s;
        white-space: nowrap;
    }
    .sd-filter-pill:hover,
    .sd-filter-pill.active { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    .sd-filter-pill.all.active   { border-color: var(--neutral-600); color: var(--neutral-700); background: var(--neutral-100); }
    .sd-filter-pill.debit.active { border-color: var(--danger); color: var(--danger); background: var(--danger-light); }
    .sd-filter-pill.credit.active{ border-color: var(--success); color: var(--success); background: var(--success-light); }

    /* ── Table ── */
    .sd-table-wrap {
        overflow-x: auto;
        max-height: 500px;
        overflow-y: auto;
        border-radius: var(--radius-md);
        border: 1.5px solid var(--neutral-100);
        margin-top: 16px;
    }
    .sd-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }
    .sd-table thead {
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .sd-table thead th {
        background: var(--neutral-100);
        color: var(--neutral-600);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: .6px;
        padding: 11px 14px;
        border-bottom: 1.5px solid var(--neutral-200);
        white-space: nowrap;
    }
    .sd-table tbody tr {
        border-bottom: 1px solid var(--neutral-100);
        transition: background .15s;
    }
    .sd-table tbody tr:last-child { border-bottom: none; }
    .sd-table tbody tr:hover { background: var(--neutral-50); }
    .sd-table tbody td {
        padding: 12px 14px;
        vertical-align: middle;
        color: var(--neutral-700);
    }
    .sd-table td.date-col {
        font-family: 'DM Mono', monospace;
        font-size: 0.8rem;
        color: var(--neutral-400);
        white-space: nowrap;
    }
    .sd-table td.amount-col {
        font-family: 'DM Mono', monospace;
        font-weight: 600;
        white-space: nowrap;
    }
    .sd-table td.amount-col.credit { color: var(--success); }
    .sd-table td.amount-col.debit  { color: var(--danger); }

    /* ── Type badge ── */
    .sd-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.73rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .5px;
    }
    .sd-badge::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        display: block;
    }
    .sd-badge.credit { background: var(--success-light); color: var(--success); }
    .sd-badge.credit::before { background: var(--success); }
    .sd-badge.debit  { background: var(--danger-light); color: var(--danger); }
    .sd-badge.debit::before  { background: var(--danger); }

    /* ── Ref link ── */
    .sd-ref-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.83rem;
        padding: 3px 8px;
        border-radius: var(--radius-sm);
        background: var(--primary-light);
        transition: background .15s;
    }
    .sd-ref-link:hover { background: var(--primary-soft); color: var(--primary); }

    /* ── Empty state ── */
    .sd-empty {
        text-align: center;
        padding: 56px 24px;
        color: var(--neutral-400);
    }
    .sd-empty i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: .4; }
    .sd-empty p { font-size: 0.9rem; margin: 0; }

    /* ── Error state ── */
    .sd-error {
        background: var(--danger-light);
        border: 1.5px solid #fca5a5;
        border-radius: var(--radius-lg);
        padding: 24px 28px;
        color: var(--danger);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .sd-error i { font-size: 1.5rem; flex-shrink: 0; }
    .sd-error-msg { font-weight: 600; font-size: 0.95rem; }

    /* ── Responsive ── */
    @media (max-width: 992px) {
        .sd-kpi-row { grid-template-columns: repeat(2, 1fr); }
        .sd-profile  { flex-direction: column; }
        .sd-divider  { display: none; }
        .sd-balance-panel { width: 100%; }
        .sd-meta-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 576px) {
        .sd-kpi-row { grid-template-columns: 1fr; }
        .sd-meta-grid { grid-template-columns: 1fr; }
        .sd-filter-bar { flex-wrap: wrap; }
    }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-content">
    <div class="sd-wrap">

      <?php if ($error): ?>
        <!-- ── Error ── -->
        <div class="sd-error">
            <i class="feather icon-alert-circle"></i>
            <div>
                <div class="sd-error-msg"><?= h($error) ?></div>
                <a href="search.php" class="sd-back-btn" style="margin-top:10px;display:inline-flex;">
                    <i class="feather icon-arrow-left"></i> <?= __('back_to_search') ?>
                </a>
            </div>
        </div>

      <?php else: ?>
        <!-- ── Top bar ── -->
        <div class="sd-topbar">
            <div class="sd-topbar-left">
                <a href="search.php" class="sd-back-btn">
                    <i class="feather icon-arrow-left"></i> <?= __('back_to_search') ?>
                </a>
                <div>
                    <h1 class="sd-page-title"><?= __('supplier_details') ?></h1>
                    <span class="sd-page-sub"><?= __('supplier_information') ?> &amp; <?= __('transaction_history') ?></span>
                </div>
            </div>
        </div>

        <!-- ── KPI Row ── -->
        <?php
            $currency  = htmlspecialchars($supplierData['currency'] ?? '');
            $balance   = $supplierData['balance'] ?? 0;
            $isOwed    = $balance > 0;
        ?>
        <div class="sd-kpi-row">
            <div class="sd-kpi">
                <div class="sd-kpi-icon blue"><i class="feather icon-activity"></i></div>
                <div>
                    <p class="sd-kpi-label"><?= __('total_transactions') ?></p>
                    <p class="sd-kpi-value"><?= count($transactions) ?></p>
                </div>
            </div>
            <div class="sd-kpi">
                <div class="sd-kpi-icon green"><i class="feather icon-arrow-down-circle"></i></div>
                <div>
                    <p class="sd-kpi-label"><?= __('total_paid') ?></p>
                    <p class="sd-kpi-value success"><?= $currency ?> <?= number_format($totalCredit, 2) ?></p>
                </div>
            </div>
            <div class="sd-kpi">
                <div class="sd-kpi-icon red"><i class="feather icon-arrow-up-circle"></i></div>
                <div>
                    <p class="sd-kpi-label"><?= __('total_debit') ?></p>
                    <p class="sd-kpi-value danger"><?= $currency ?> <?= number_format($totalDebit, 2) ?></p>
                </div>
            </div>
            <div class="sd-kpi">
                <div class="sd-kpi-icon amber"><i class="feather icon-dollar-sign"></i></div>
                <div>
                    <p class="sd-kpi-label"><?= __('balance') ?></p>
                    <p class="sd-kpi-value <?= $isOwed ? 'danger' : 'success' ?>">
                        <?= $currency ?> <?= number_format(abs($balance), 2) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Supplier Profile Card ── -->
        <div class="sd-card">
            <div class="sd-card-head">
                <h5 class="sd-card-title">
                    <i class="feather icon-briefcase"></i>
                    <?= __('supplier_information') ?>
                </h5>
            </div>
            <div class="sd-card-body">
                <div class="sd-profile">
                    <!-- Avatar -->
                    <div class="sd-avatar">
                        <?= strtoupper(mb_substr($supplierData['name'] ?? '?', 0, 2)) ?>
                    </div>

                    <!-- Info block -->
                    <div class="sd-profile-info">
                        <h2 class="sd-supplier-name"><?= htmlspecialchars($supplierData['name'] ?? '—') ?></h2>
                        <?php if (!empty($supplierData['supplier_type'])): ?>
                            <span class="sd-supplier-type"><?= htmlspecialchars($supplierData['supplier_type']) ?></span>
                        <?php endif; ?>

                        <div class="sd-meta-grid">
                            <div class="sd-meta-item">
                                <label><?= __('contact_person') ?></label>
                                <span <?= empty($supplierData['contact_person']) ? 'class="muted"' : '' ?>>
                                    <?= !empty($supplierData['contact_person']) ? htmlspecialchars($supplierData['contact_person']) : '—' ?>
                                </span>
                            </div>
                            <div class="sd-meta-item">
                                <label><?= __('email') ?></label>
                                <span <?= empty($supplierData['email']) ? 'class="muted"' : '' ?>>
                                    <?= !empty($supplierData['email']) ? htmlspecialchars($supplierData['email']) : '—' ?>
                                </span>
                            </div>
                            <div class="sd-meta-item">
                                <label><?= __('phone') ?></label>
                                <span <?= empty($supplierData['phone']) ? 'class="muted"' : '' ?>>
                                    <?= !empty($supplierData['phone']) ? htmlspecialchars($supplierData['phone']) : '—' ?>
                                </span>
                            </div>
                            <div class="sd-meta-item">
                                <label><?= __('address') ?></label>
                                <span <?= empty($supplierData['address']) ? 'class="muted"' : '' ?>>
                                    <?= !empty($supplierData['address']) ? htmlspecialchars($supplierData['address']) : '—' ?>
                                </span>
                            </div>
                            <div class="sd-meta-item">
                                <label><?= __('created_at') ?></label>
                                <span><?= !empty($supplierData['created_at']) ? date('d M Y', strtotime($supplierData['created_at'])) : '—' ?></span>
                            </div>
                            <div class="sd-meta-item">
                                <label><?= __('updated_at') ?></label>
                                <span><?= !empty($supplierData['updated_at']) ? date('d M Y', strtotime($supplierData['updated_at'])) : '—' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="sd-divider"></div>

                    <!-- Balance panel -->
                    <div class="sd-balance-panel <?= $isOwed ? 'owed' : 'clear' ?>">
                        <div class="sd-balance-label"><?= __('outstanding_balance') ?></div>
                        <p class="sd-balance-value <?= $isOwed ? 'owed' : 'clear' ?>">
                            <?= $currency ?> <?= number_format(abs($balance), 2) ?>
                        </p>
                        <div class="sd-balance-status <?= $isOwed ? 'owed' : 'clear' ?>">
                            <?= $isOwed ? '⚠ Amount Owed' : '✓ Settled' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Transactions Card ── -->
        <div class="sd-card">
            <div class="sd-card-head">
                <h5 class="sd-card-title">
                    <i class="feather icon-list"></i>
                    <?= __('transaction_history') ?>
                    <span style="background:var(--primary-light);color:var(--primary);font-size:0.72rem;padding:2px 8px;border-radius:999px;font-weight:600;margin-left:6px;">
                        <?= count($transactions) ?>
                    </span>
                </h5>
                <!-- Filter bar -->
                <?php if (!empty($transactions)): ?>
                <div class="sd-filter-bar">
                    <div class="sd-search-box">
                        <i class="feather icon-search"></i>
                        <input type="text" id="txSearch" placeholder="Search transactions…">
                    </div>
                    <button class="sd-filter-pill all active" data-filter="all">All</button>
                    <button class="sd-filter-pill credit" data-filter="credit">Credit</button>
                    <button class="sd-filter-pill debit"  data-filter="debit">Debit</button>
                </div>
                <?php endif; ?>
            </div>

            <div class="sd-card-body" style="padding: 0 22px 22px;">
                <?php if (!empty($transactions)): ?>
                <div class="sd-table-wrap">
                    <table class="sd-table" id="txTable">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('related_to') ?></th>
                                <th><?= __('description') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx):
                                $txType = strtolower($tx['transaction_type'] ?? '');
                                $isCredit = $txType === 'credit';
                                $refId = htmlspecialchars($tx['reference_id'] ?? '');
                                $txOf  = $tx['transaction_of'] ?? '';
                            ?>
                            <tr data-type="<?= $txType ?>">
                                <td class="date-col">
                                    <?= !empty($tx['transaction_date']) ? date('d M Y', strtotime($tx['transaction_date'])) : '—' ?>
                                </td>
                                <td>
                                    <span class="sd-badge <?= $isCredit ? 'credit' : 'debit' ?>">
                                        <?= ucfirst($txType ?: '—') ?>
                                    </span>
                                </td>
                                <td class="amount-col <?= $isCredit ? 'credit' : 'debit' ?>">
                                    <?= $isCredit ? '+' : '−' ?> <?= $currency ?> <?= number_format($tx['amount'] ?? 0, 2) ?>
                                </td>
                                <td>
                                    <?php if (!empty($txOf) && !empty($refId)):
                                        $label = ucfirst(str_replace('_', ' ', $txOf));
                                        switch ($txOf) {
                                            case 'ticket':
                                                echo "<a class='sd-ref-link' href='ticket_detail.php?id={$refId}'><i class='feather icon-tag'></i>{$label} #{$refId}</a>";
                                                break;
                                            case 'visa': case 'visa_sale':
                                                echo "<a class='sd-ref-link' href='visa_detail.php?id={$refId}'><i class='feather icon-credit-card'></i>{$label} #{$refId}</a>";
                                                break;
                                            case 'hotel': case 'hotel_booking':
                                                echo "<a class='sd-ref-link' href='hotel_detail.php?id={$refId}'><i class='feather icon-home'></i>{$label} #{$refId}</a>";
                                                break;
                                            default:
                                                echo "<span class='sd-ref-link' style='cursor:default;background:var(--neutral-100);color:var(--neutral-600)'>{$label} #{$refId}</span>";
                                        }
                                    else: echo '<span style="color:var(--neutral-400)">—</span>';
                                    endif; ?>
                                </td>
                                <td style="color:var(--neutral-600); max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($tx['description'] ?? '') ?>">
                                    <?= !empty($tx['description']) ? htmlspecialchars($tx['description']) : '<span style="color:var(--neutral-400)">—</span>' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="sd-empty">
                    <i class="feather icon-inbox"></i>
                    <p><?= __('no_transactions_found_for_this_supplier') ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

      <?php endif; ?>
    </div>
  </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
(function () {
    const searchInput = document.getElementById('txSearch');
    const filterBtns  = document.querySelectorAll('.sd-filter-pill');
    const rows        = document.querySelectorAll('#txTable tbody tr');

    let activeFilter = 'all';
    let searchTerm   = '';

    function applyFilters() {
        rows.forEach(row => {
            const type    = row.dataset.type || '';
            const text    = row.textContent.toLowerCase();
            const typeMatch   = activeFilter === 'all' || type === activeFilter;
            const searchMatch = !searchTerm || text.includes(searchTerm);
            row.style.display = (typeMatch && searchMatch) ? '' : 'none';
        });
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', e => {
            searchTerm = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }
})();
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>