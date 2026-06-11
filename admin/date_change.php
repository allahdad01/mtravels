<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'security.php';
require_once '../includes/language_helpers.php';
enforce_auth();

$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';
include '../api/ticket_date_change/date_change_handler.php';

$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.css">
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js"></script>
<link rel="stylesheet" href="../css/ticket/ticket_styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-components.css">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-form.css">
<link rel="stylesheet" href="../css/ticket/datechange-css.css">

<?php include '../includes/header.php'; ?>

<style>
/* ─── Reset & Base ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ─── Page Shell ───────────────────────────────────────── */
.pg-root {
    background: #f0f2f5;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ─── Page Header ──────────────────────────────────────── */
.pg-header {
    background: #ffffff;
    border-bottom: 1px solid #e3e6eb;
    padding: 0 28px;
}

.pg-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 0 0;
}

.pg-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pg-title-icon {
    width: 34px;
    height: 34px;
    background: #185FA5;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pg-title-icon svg {
    width: 16px;
    height: 16px;
}

.pg-title-text h1 {
    font-size: 19px;
    font-weight: 600;
    color: #1a2332;
    margin: 0;
    line-height: 1.2;
}

.pg-breadcrumb {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 3px;
    font-size: 12px;
    color: #8a94a6;
    list-style: none;
    padding: 0;
    margin-bottom: 0;
}

.pg-breadcrumb li a {
    color: #5a6478;
    text-decoration: none;
}

.pg-breadcrumb li a:hover {
    color: #185FA5;
}

.pg-breadcrumb li + li::before {
    content: '›';
    margin-right: 5px;
    opacity: 0.5;
}

/* ─── Tab Nav ──────────────────────────────────────────── */
.pg-tabs {
    display: flex;
    gap: 0;
    margin-top: 14px;
}

.pg-tab {
    padding: 11px 20px;
    font-size: 13px;
    font-weight: 500;
    color: #7a8499;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    text-decoration: none;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
}

.pg-tab:hover {
    color: #1a2332;
}

.pg-tab.active {
    color: #185FA5;
    border-bottom-color: #185FA5;
}

/* ─── Page Body ────────────────────────────────────────── */
.pg-body {
    padding: 24px 28px;
    flex: 1;
}

/* ─── KPI Strip ────────────────────────────────────────── */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.kpi-card {
    background: #ffffff;
    border: 1px solid #e3e6eb;
    border-radius: 10px;
    padding: 16px 18px 14px;
    position: relative;
    overflow: hidden;
}

.kpi-accent {
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    height: 100%;
    border-radius: 10px 0 0 10px;
}

.kpi-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #8a94a6;
    margin-bottom: 8px;
}

.kpi-value {
    font-size: 26px;
    font-weight: 600;
    color: #1a2332;
    line-height: 1;
}

.kpi-sub {
    font-size: 11px;
    color: #8a94a6;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.kpi-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.kpi-badge.green  { background: #e8f4df; color: #3b6d11; }
.kpi-badge.amber  { background: #fef3e2; color: #854f0b; }
.kpi-badge.blue   { background: #e6f1fb; color: #185FA5; }

/* ─── Toolbar ──────────────────────────────────────────── */
.pg-toolbar {
    background: #ffffff;
    border: 1px solid #e3e6eb;
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.toolbar-search {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 9px;
    background: #f5f6f8;
    border: 1px solid #e3e6eb;
    border-radius: 7px;
    padding: 8px 13px;
    transition: border-color .15s;
}

.toolbar-search:focus-within {
    border-color: #185FA5;
    background: #fff;
}

.toolbar-search svg {
    flex-shrink: 0;
    color: #9aa1b0;
    width: 14px;
    height: 14px;
}

.toolbar-search input {
    border: none;
    background: transparent;
    font-size: 13px;
    color: #1a2332;
    outline: none;
    width: 100%;
}

.toolbar-search input::placeholder {
    color: #9aa1b0;
}

.toolbar-divider {
    width: 1px;
    height: 28px;
    background: #e3e6eb;
    flex-shrink: 0;
}

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    color: #5a6478;
    border: 1px solid #d8dce5;
    padding: 8px 13px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    transition: background .15s, color .15s;
}

.btn-ghost:hover {
    background: #f5f6f8;
    color: #1a2332;
    text-decoration: none;
}

.btn-primary-corp {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #185FA5;
    color: #ffffff;
    border: none;
    padding: 9px 17px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s;
}

.btn-primary-corp:hover {
    background: #0C447C;
    color: #fff;
}

/* ─── Section Header ───────────────────────────────────── */
.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #8a94a6;
}

.section-count {
    font-size: 12px;
    color: #8a94a6;
}

/* ─── Date Change Cards (preserved) ───────────────────── */
.date-change-card-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.date-change-card {
    display: grid;
    grid-template-columns: 1fr 128px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #dde1e9;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    transition: box-shadow .2s;
}

.date-change-card:hover {
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
}

.date-change-card-main {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    padding: 16px 20px;
    position: relative;
}

.date-change-card-main::after {
    content: '';
    position: absolute;
    right: 0;
    top: 12%;
    height: 76%;
    border-right: 2px dashed rgba(255,255,255,0.4);
}

.date-change-card-main.status-paid    { background: #7aaa62; }
.date-change-card-main.status-partial { background: #d4a574; }
.date-change-card-main.status-unpaid  { background: #e07a7a; }
.date-change-card-main.status-neutral { background: #6b8fb3; }

.date-change-card-left {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.date-change-card-title {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.4px;
    line-height: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.date-change-card-title span {
    display: block;
    width: 20px;
    height: 2px;
    background: rgba(255,255,255,0.65);
}

.date-change-card-id {
    font-size: 11px;
    color: rgba(255,255,255,0.82);
    font-weight: 500;
}

.date-change-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px 14px;
    color: rgba(255,255,255,0.92);
    font-size: 12px;
    margin-top: 4px;
}

.date-change-card-detail-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.date-change-card-detail-label {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    opacity: 0.75;
    min-width: fit-content;
}

.date-change-card-right {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: center;
}

.date-change-card-price-box {
    background: #fff;
    border-radius: 7px;
    padding: 8px 12px;
    font-size: 24px;
    font-weight: 700;
    color: #1e2d3d;
    letter-spacing: -0.5px;
    text-align: center;
    min-width: 80px;
}

.date-change-card-price-meta {
    display: flex;
    gap: 3px;
    align-items: center;
    font-size: 9px;
    color: rgba(255,255,255,0.7);
}

.date-change-card-meta-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(255,255,255,0.55);
}

.date-change-card-stub {
    background: #eaecf0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 8px;
    gap: 4px;
}

.date-change-card-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 100%;
}

.date-change-card-action-btn {
    background: #185FA5;
    border: none;
    color: #fff;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
    transition: background .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.date-change-card-action-btn:hover {
    background: #0C447C;
}

@media (max-width: 768px) {
    .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pg-body { padding: 16px; }
    .pg-header { padding: 0 16px; }
    .date-change-card { grid-template-columns: 1fr; }
    .date-change-card-main::after { display: none; }
    .date-change-card-stub { padding: 8px 12px; }
}

/* ─── Footer / Pagination ──────────────────────────────── */
.pg-list-footer {
    background: #ffffff;
    border: 1px solid #e3e6eb;
    border-radius: 10px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
}

.pg-list-footer .info {
    font-size: 12px;
    color: #8a94a6;
}

.pagination {
    margin-bottom: 0;
}

.pagination .page-link {
    border-color: #e3e6eb;
    color: #5a6478;
    font-size: 13px;
    padding: 6px 11px;
}

.pagination .page-item.active .page-link {
    background-color: #185FA5;
    border-color: #185FA5;
    color: #fff;
}

.pagination .page-link:hover {
    background: #f5f6f8;
    color: #1a2332;
}
/* ─── FAB ──────────────────────────────────────────────── */
.pg-fab {
    position: fixed;
    bottom: 80px;
    z-index: 1050;
}

.pg-fab button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #185FA5;
    border: none;
    color: #fff;
    font-size: 25px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(24,95,165,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
}

.pg-fab button:hover {
    background: #0C447C;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container pg-root">
    <div class="pcoded-wrapper">
        <div class="pcoded-content" style="background:transparent;padding:0">
            <div class="pcoded-inner-content" style="padding:0">

                <!-- ── Page Header ── -->
                <div class="pg-header">
                    <div class="pg-header-top">
                        <div class="pg-title-group">
                            <div class="pg-title-icon">
                                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="white" stroke-width="1.4"/>
                                    <path d="M2 6h12" stroke="white" stroke-width="1.2"/>
                                    <path d="M5 1v2M11 1v2" stroke="white" stroke-width="1.4" stroke-linecap="round"/>
                                    <path d="M5.5 9.5l1.5 1.5 3-3" stroke="white" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="pg-title-text">
                                <h1><?= __('date_change_management') ?></h1>
                                <ul class="pg-breadcrumb">
                                    <li><a href="dashboard.php"><i class="feather icon-home" style="font-size:11px"></i></a></li>
                                    <li><?= __('date_change_management') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="pg-tabs">
                        <a class="pg-tab active" href="?status=all<?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>">
                            <?= __('all') ?? 'All' ?>
                        </a>
                    </div>
                </div>

                <!-- ── Page Body ── -->
                <div class="pg-body">

                    <!-- Toolbar -->
                    <form method="GET">
                    <div class="pg-toolbar">
                        <div class="toolbar-search">
                            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M10.5 10.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="text" name="search"
                                placeholder="<?= __('search_placeholder') ?? 'Search by passenger name, PNR, phone, airline, city…' ?>"
                                value="<?= htmlspecialchars($search_query) ?>">
                        </div>

                        <?php if (!empty($search_query)): ?>
                        <a href="date_change.php" class="btn-ghost">
                            <i class="feather icon-x" style="font-size:13px"></i>
                            <?= __('clear') ?? 'Clear' ?>
                        </a>
                        <?php endif; ?>

                        <div class="toolbar-divider"></div>

                        <button type="submit" class="btn-ghost">
                            <i class="feather icon-search" style="font-size:13px"></i>
                            <?= __('search') ?? 'Search' ?>
                        </button>

                        <button type="button" class="btn-primary-corp" data-toggle="modal" data-target="#addDateChangeModal">
                            <i class="feather icon-plus-circle" style="font-size:13px"></i>
                            <?= __('add_date_change') ?>
                        </button>
                    </div>
                    </form>

                    <!-- Section Header -->
                    <div class="section-head">
                        <div class="section-label"><?= __('results') ?? 'Results' ?></div>
                        <div class="section-count">
                            <?= __('showing') ?? 'Showing' ?>
                            <?= $offset + 1 ?> <?= __('to') ?? 'to' ?>
                            <?= min($offset + $items_per_page, $total_records) ?>
                            <?= __('of') ?? 'of' ?> <?= $total_records ?>
                            <?= __('entries') ?? 'entries' ?>
                        </div>
                    </div>

                    <!-- Date Change Cards -->
                    <div class="date-change-card-container" id="ticketTable">
                        <?php foreach ($tickets as $ticket):
                            $paymentStatus  = 'neutral';
                            $totalPaidInBase = 0;
                            $baseCurrency   = $ticket['currency'];
                            $soldAmount     = floatval($ticket['supplier_penalty'] + $ticket['service_penalty']);
                            $ticketId       = $ticket['id'];
                            $soldTo         = $ticket['sold_to_name'];
                            $isAgencyClient = false;

                            $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE tenant_id = ? AND branch_id = ? AND name = ?");
                            $clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                            $clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                            $clientStmt->bindParam(3, $soldTo, PDO::PARAM_STR);
                            $clientStmt->execute();
                            $clientResult = $clientStmt->fetchAll();
                            if (count($clientResult) > 0) {
                                $isAgencyClient = ($clientResult[0]['client_type'] === 'agency');
                            }

                            if ($isAgencyClient) {
                                $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                    transaction_of = 'date_change' AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
                                $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
                                $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                $transactionStmt->execute();
                                $transactions = $transactionStmt->fetchAll();

                                if ($transactions && count($transactions) > 0) {
                                    foreach ($transactions as $transaction) {
                                        $amount           = floatval($transaction['amount']);
                                        $transCurrency    = $transaction['currency'];
                                        $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0
                                            ? floatval($transaction['exchange_rate']) : 1.0;

                                        if ($transCurrency === $baseCurrency) {
                                            $convertedAmount = $amount;
                                        } elseif ($baseCurrency === 'AFS') {
                                            $convertedAmount = $amount * $transExchangeRate;
                                        } else {
                                            $convertedAmount = $amount / $transExchangeRate;
                                        }
                                        $totalPaidInBase += $convertedAmount;
                                    }
                                }

                                if ($totalPaidInBase <= 0)              $paymentStatus = 'unpaid';
                                elseif ($totalPaidInBase < $soldAmount) $paymentStatus = 'partial';
                                else                                     $paymentStatus = 'paid';
                            }
                        ?>
                        <div class="date-change-card">
                            <div class="date-change-card-main status-<?= $paymentStatus ?>">
                                <div class="date-change-card-left">
                                    <div>
                                        <div class="date-change-card-title">
                                            DATE CHANGE <span></span>
                                        </div>
                                        <div class="date-change-card-id"><?= htmlspecialchars($ticket['pnr']) ?></div>
                                    </div>
                                    <div class="date-change-card-details">
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('passenger') ?? 'Passenger' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['title']) ?> <?= htmlspecialchars($ticket['passenger_name']) ?></span>
                                        </div>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('sold_to') ?? 'Sold To' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['sold_to_name']) ?></span>
                                        </div>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('route') ?? 'Route' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['origin']) ?> → <?= htmlspecialchars($ticket['destination']) ?></span>
                                        </div>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('phone') ?? 'Phone' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['phone'] ?? '') ?></span>
                                        </div>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('old_departure') ?? 'Old Departure' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['old_departure_date'] ?? '') ?></span>
                                        </div>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('new_departure') ?? 'New Departure' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['departure_date'] ?? '') ?></span>
                                        </div>
                                        <?php if (!empty($ticket['old_return_date'])): ?>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('old_return') ?? 'Old Return' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['old_return_date']) ?></span>
                                        </div>
                                        <div class="date-change-card-detail-item">
                                            <span class="date-change-card-detail-label"><?= __('new_return') ?? 'New Return' ?>:</span>
                                            <span><?= htmlspecialchars($ticket['return_date'] ?? 'N/A') ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="date-change-card-right">
                                    <div class="date-change-card-price-box">
                                        <?= number_format($ticket['supplier_penalty'] + $ticket['service_penalty'], 2) ?>
                                    </div>
                                    <div class="date-change-card-price-meta">
                                        <div class="date-change-card-meta-dot"></div>
                                        <div class="date-change-card-meta-dot"></div>
                                        <div class="date-change-card-meta-dot"></div>
                                        <span><?= htmlspecialchars($baseCurrency) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="date-change-card-stub">
                                <div class="date-change-card-actions">
                                    <?php if ($isAgencyClient && $canEdit): ?>
                                    <button class="date-change-card-action-btn"
                                            onclick="manageTransactions(<?= $ticket['id'] ?>)"
                                            title="<?= __('manage_transactions') ?>">
                                        <i class="fa fa-credit-card"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="date-change-card-action-btn"
                                            onclick="printAgreement(<?= $ticket['id'] ?>)"
                                            title="<?= __('print_agreement') ?>">
                                        <i class="feather icon-printer"></i>
                                    </button>
                                    <?php if ($canEdit): ?>
                                    <button class="date-change-card-action-btn"
                                            onclick="editDateChange(<?= $ticket['id'] ?>)"
                                            title="<?= __('edit') ?>"
                                            data-supplier-penalty="<?= $ticket['supplier_penalty'] ?>"
                                            data-service-penalty="<?= $ticket['service_penalty'] ?>"
                                            data-remarks="<?= htmlspecialchars($ticket['remarks'] ?? '') ?>">
                                        <i class="feather icon-edit"></i>
                                    </button>
                                    <button class="date-change-card-action-btn"
                                            onclick="deleteTicket(<?= $ticket['id'] ?>)"
                                            title="<?= __('delete_ticket') ?>">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="pg-list-footer">
                        <div class="info">
                            <?= __('showing') ?? 'Showing' ?>
                            <?= $offset + 1 ?> <?= __('to') ?? 'to' ?>
                            <?= min($offset + $items_per_page, $total_records) ?>
                            <?= __('of') ?? 'of' ?> <?= $total_records ?>
                            <?= __('entries') ?? 'entries' ?>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php $sp = !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?>

                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?= $sp ?>">
                                            <i class="feather icon-chevrons-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $current_page - 1 ?><?= $sp ?>">
                                            <i class="feather icon-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page   = min($total_pages, $current_page + 2);
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1'.$sp.'">1</a></li>';
                                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                }
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item '.($i == $current_page ? 'active' : '').'">
                                        <a class="page-link" href="?page='.$i.$sp.'">'.$i.'</a>
                                    </li>';
                                }
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.$sp.'">'.$total_pages.'</a></li>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $current_page + 1 ?><?= $sp ?>">
                                            <i class="feather icon-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= $sp ?>">
                                            <i class="feather icon-chevrons-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>

                </div><!-- /.pg-body -->
            </div>
        </div>
    </div>
</div>

<?php include '../modals/ticket_date_change/multi_ticket.php'; ?>
<?php include '../modals/ticket_date_change/transaction_modal.php'; ?>
<?php include '../modals/ticket_date_change/edit_transaction.php'; ?>
<?php include '../modals/ticket_date_change/add_date_change.php'; ?>

<?php include '../includes/admin_footer.php'; ?>

<!-- Required JS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/ticket_date_change/addDateChange.js"></script>
<script src="../js/ticket_date_change/deleteDateChange.js"></script>
<script src="../js/ticket_date_change/transaction-manager.js"></script>
<script src="../js/ticket_date_change/multiTicket.js"></script>

<script>
function editDateChange(id) {
    const btn = event?.target?.closest('button');
    const supplierPenalty = parseFloat(btn?.dataset?.supplierPenalty) || 0;
    const servicePenalty = parseFloat(btn?.dataset?.servicePenalty) || 0;
    const currentRemarks = btn?.dataset?.remarks || '';

    Swal.fire({
        title: 'Edit Date Change Penalties',
        html: `
            <div style="text-align:left">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Supplier Penalty</label>
                <input id="swal-supplier-penalty" class="swal2-input" type="number" step="0.01" min="0" value="${supplierPenalty}" style="width:100%">
                <label style="font-weight:600;font-size:13px;display:block;margin:12px 0 4px">Service Penalty</label>
                <input id="swal-service-penalty" class="swal2-input" type="number" step="0.01" min="0" value="${servicePenalty}" style="width:100%">
                <label style="font-weight:600;font-size:13px;display:block;margin:12px 0 4px">Description</label>
                <textarea id="swal-remarks" class="swal2-textarea" style="width:100%">${currentRemarks}</textarea>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#185FA5',
        preConfirm: () => {
            const sp = parseFloat(document.getElementById('swal-supplier-penalty').value) || 0;
            const sv = parseFloat(document.getElementById('swal-service-penalty').value) || 0;
            const rm = document.getElementById('swal-remarks').value.trim();
            if (sp < 0 || sv < 0) {
                Swal.showValidationMessage('Penalties cannot be negative');
                return false;
            }
            return { supplier_penalty: sp, service_penalty: sv, remarks: rm };
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        const data = result.value;
        const clickedBtn = event?.target?.closest('button') || document.activeElement;
        let originalContent = '';
        if (clickedBtn && clickedBtn.tagName === 'BUTTON') {
            originalContent = clickedBtn.innerHTML;
            clickedBtn.disabled = true;
            clickedBtn.innerHTML = '<i class="feather icon-loader"></i>';
        }

        fetch('../api/ticket_date_change/update_date_change.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, ...data }),
        })
        .then(response => response.json())
        .then(res => {
            if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                clickedBtn.disabled = false;
                clickedBtn.innerHTML = originalContent;
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: res.success ? 'success' : 'error',
                title: res.message || (res.success ? 'Updated successfully' : 'Update failed'),
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            if (res.success) {
                setTimeout(() => location.reload(), 1000);
            }
        })
        .catch(err => {
            if (clickedBtn && clickedBtn.tagName === 'BUTTON' && originalContent) {
                clickedBtn.disabled = false;
                clickedBtn.innerHTML = originalContent;
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'An unexpected error occurred',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        });
    });
}
</script>

</body>
</html>