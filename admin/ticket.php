<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once('../includes/db.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}
include '../api/ticket/ticket_handler.php';

$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">

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
    border-top: none;
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
    transition: background .15s, color .15s;
}

.btn-ghost:hover {
    background: #f5f6f8;
    color: #1a2332;
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

/* ─── Ticket Cards (preserved) ─────────────────────────── */
.ticket-card-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ticket-card {
    display: grid;
    grid-template-columns: 1fr 128px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #dde1e9;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    transition: box-shadow .2s;
}

.ticket-card:hover {
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
}

.ticket-card-main {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    padding: 16px 20px;
    position: relative;
}

.ticket-card-main::after {
    content: '';
    position: absolute;
    right: 0;
    top: 12%;
    height: 76%;
    border-right: 2px dashed rgba(255,255,255,0.4);
}

.ticket-card-main.status-paid    { background: #7aaa62; }
.ticket-card-main.status-partial { background: #d4a574; }
.ticket-card-main.status-unpaid  { background: #e07a7a; }
.ticket-card-main.status-neutral { background: #6b8fb3; }

.ticket-card-left {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.ticket-card-header {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.ticket-card-status-dots {
    display: flex;
    gap: 5px;
    align-items: center;
    padding-top: 2px;
}

.ticket-card-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: rgba(255,255,255,0.6);
}

.ticket-card-dot.primary {
    width: 9px;
    height: 9px;
    background: rgba(255,255,255,0.9);
}

.ticket-card-title {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.4px;
    line-height: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ticket-card-title span {
    display: block;
    width: 20px;
    height: 2px;
    background: rgba(255,255,255,0.65);
}

.ticket-card-id {
    font-size: 11px;
    color: rgba(255,255,255,0.82);
    font-weight: 500;
}

.ticket-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px 14px;
    color: rgba(255,255,255,0.92);
    font-size: 12px;
    margin-top: 4px;
}

.ticket-card-detail-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.ticket-card-detail-label {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    opacity: 0.75;
    min-width: fit-content;
}

.ticket-card-right {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: center;
}

.ticket-card-price-box {
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

.ticket-card-price-meta {
    display: flex;
    gap: 3px;
    align-items: center;
    font-size: 9px;
    color: rgba(255,255,255,0.7);
}

.ticket-card-meta-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(255,255,255,0.55);
}

.ticket-card-stub {
    background: #eaecf0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 8px;
    gap: 4px;
}

.ticket-card-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 100%;
}

.ticket-card-action-btn {
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

.ticket-card-action-btn:hover {
    background: #0C447C;
}

@media (max-width: 768px) {
    .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pg-body { padding: 16px; }
    .pg-header { padding: 0 16px; }
    .ticket-card { grid-template-columns: 1fr; }
    .ticket-card-main::after { display: none; }
    .ticket-card-stub { padding: 8px 12px; }
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

/* ─── Toast (unchanged) ────────────────────────────────── */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 350px;
}

.toast {
    position: relative;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    margin-bottom: 10px;
    overflow: hidden;
    opacity: 0;
    transform: translateX(40px);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    padding: 15px;
}

.toast-showing  { opacity: 1; transform: translateX(0); }
.toast-removing { opacity: 0; transform: translateY(-20px); }
.toast-success  { border-left-color: #10b981; }
.toast-error    { border-left-color: #ef4444; }
.toast-warning  { border-left-color: #f59e0b; }
.toast-info     { border-left-color: #3b82f6; }

.toast-title {
    display: flex;
    align-items: center;
    font-weight: 600;
    margin-bottom: 5px;
}

.toast-message {
    word-break: break-word;
    line-height: 1.5;
    color: #64748b;
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

/* ─── Bootstrap-select dropdown text fix ───────────────── */
.bootstrap-select .dropdown-menu .inner .dropdown-item {
    color: #212529 !important;
}
.bootstrap-select .dropdown-menu .inner .dropdown-item:hover,
.bootstrap-select .dropdown-menu .inner .dropdown-item:focus {
    color: #16181b !important;
    background-color: #f8f9fa !important;
}
.bootstrap-select .dropdown-menu .inner .dropdown-item.active,
.bootstrap-select .dropdown-menu .inner .dropdown-item:active {
    color: #fff !important;
    background-color: #007bff !important;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container pg-root">
    <div class="pcoded-wrapper">
        <div class="pcoded-content" style="background:transparent;padding:0">
            <div class="pcoded-inner-content" style="padding:0">

                <div class="toast-container"></div>

                <!-- ── Page Header ── -->
                <div class="pg-header">
                    <div class="pg-header-top">
                        <div class="pg-title-group">
                            <div class="pg-title-icon">
                                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="1" y="3" width="14" height="10" rx="2" stroke="white" stroke-width="1.4"/>
                                    <path d="M5 3V2M11 3V2M5 13v1M11 13v1" stroke="white" stroke-width="1.4" stroke-linecap="round"/>
                                    <path d="M1 7h14" stroke="white" stroke-width="1.2"/>
                                </svg>
                            </div>
                            <div class="pg-title-text">
                                <h1><?= __('ticket') ?></h1>
                                <ul class="pg-breadcrumb">
                                    <li><a href="dashboard.php"><i class="feather icon-home" style="font-size:11px"></i></a></li>
                                    <li><?= __('ticket') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="pg-tabs">
                         <a class="pg-tab active" href="?status=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"><?= __('all_tickets') ?? 'All tickets' ?></a>
                         <a class="pg-tab" href="?status=booked<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"><?= __('booked') ?? 'Booked' ?></a>
                         <a class="pg-tab" href="?status=date_changed<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"><?= __('date_changed') ?? 'Date Changed' ?></a>
                         <a class="pg-tab" href="?status=refunded<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"><?= __('refunded') ?? 'Refunded' ?></a>
                     </div>
                </div>

                <!-- ── Page Body ── -->
                <div class="pg-body">

                    <!-- Toolbar -->
                    <div class="pg-toolbar">
                        <div class="toolbar-search">
                            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M10.5 10.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="text" id="pnrFilter"
                                placeholder="<?= __('search_by_pnr_passenger_name_or_airline') ?>"
                                value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <?php if (!empty($search)): ?>
                        <a href="ticket.php" class="btn-ghost">
                            <i class="feather icon-x" style="font-size:13px"></i>
                            <?= __('clear') ?>
                        </a>
                        <?php endif; ?>

                        <div class="toolbar-divider"></div>

                        <button class="btn-ghost" id="searchBtn">
                            <i class="feather icon-search" style="font-size:13px"></i>
                            <?= __('search') ?>
                        </button>

                        <button class="btn-primary-corp" data-toggle="modal" data-target="#bookTicketModal">
                            <i class="feather icon-plus-circle" style="font-size:13px"></i>
                            <?= __('book_ticket') ?>
                        </button>
                    </div>

                    <!-- Section Header -->
                    <div class="section-head">
                        <div class="section-label"><?= __('results') ?? 'Results' ?></div>
                        <div class="section-count">
                            <?= __('showing') ?> <?= min(($page - 1) * $results_per_page + 1, $totalTickets) ?>
                            <?= __('to') ?> <?= min($page * $results_per_page, $totalTickets) ?>
                            <?= __('of') ?> <?= $totalTickets ?> <?= __('tickets') ?>
                        </div>
                    </div>

                    <!-- Ticket Cards -->
                    <div class="ticket-card-container" id="ticketTable">
                        <?php foreach ($tickets as $ticket):
                            $isAgencyClient = false;
                            $soldTo = $ticket['ticket']['sold_to'];
                            $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                            $clientStmt->bindParam(1, $soldTo, PDO::PARAM_STR);
                            $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                            $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                            $clientStmt->execute();
                            $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                            if ($clientRow) {
                                $isAgencyClient = ($clientRow['client_type'] === 'agency');
                            }

                            $paymentStatus = 'neutral';
                            $totalPaidInBase = 0;
                            $baseCurrency = $ticket['ticket']['currency'];
                            $soldAmount = floatval($ticket['ticket']['sold']);
                            $ticketId = $ticket['ticket']['id'];

                            if ($isAgencyClient) {
                                $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                    transaction_of = 'ticket_sale'
                                    AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
                                $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
                                $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                $transactionStmt->execute();
                                $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                if ($transactions && count($transactions) > 0) {
                                    foreach ($transactions as $transaction) {
                                        $amount = floatval($transaction['amount']);
                                        $transCurrency = $transaction['currency'];
                                        $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0
                                            ? floatval($transaction['exchange_rate']) : 1.0;

                                        if ($transCurrency === $baseCurrency) {
                                            $convertedAmount = $amount;
                                        } else {
                                            if ($baseCurrency === 'AFS') {
                                                $convertedAmount = $amount * $transExchangeRate;
                                            } else {
                                                $convertedAmount = $amount / $transExchangeRate;
                                            }
                                        }
                                        $totalPaidInBase += $convertedAmount;
                                    }
                                }

                                if ($totalPaidInBase <= 0)               $paymentStatus = 'unpaid';
                                elseif ($totalPaidInBase < $soldAmount)  $paymentStatus = 'partial';
                                else                                      $paymentStatus = 'paid';
                            }
                        ?>
                        <div class="ticket-card">
                            <div class="ticket-card-main status-<?= $paymentStatus ?>">
                                <div class="ticket-card-left">
                                    <div class="ticket-card-header">
                                        <div class="ticket-card-status-dots">
                                            <div class="ticket-card-dot primary"></div>
                                            <div class="ticket-card-dot"></div>
                                            <div class="ticket-card-dot"></div>
                                            <div class="ticket-card-dot"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="ticket-card-title">
                                            TICKET <span></span>
                                        </div>
                                        <div class="ticket-card-id"><?= htmlspecialchars($ticket['ticket']['pnr']) ?></div>
                                    </div>
                                    <div class="ticket-card-details">
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('sold_to') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['sold_to']) ?></span>
                                        </div>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('passenger') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['title']) ?> <?= htmlspecialchars($ticket['ticket']['passenger_name']) ?></span>
                                        </div>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('route') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['origin']) ?> → <?= htmlspecialchars($ticket['ticket']['destination']) ?></span>
                                        </div>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('airline') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['airline']) ?></span>
                                        </div>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('issue_date') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['issue_date']) ?></span>
                                        </div>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('departure') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['departure_date']) ?><?php if (!empty($ticket['ticket']['departure_time'])): ?> @ <?= htmlspecialchars($ticket['ticket']['departure_time']) ?><?php endif; ?></span>
                                        </div>
                                        <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('return') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['return_date']) ?><?php if (!empty($ticket['ticket']['return_departure_time'])): ?> @ <?= htmlspecialchars($ticket['ticket']['return_departure_time']) ?><?php endif; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($ticket['refund_data']): ?>
                                        <div class="ticket-card-detail-item" style="color:#ffcdd2">
                                            <span class="ticket-card-detail-label"><?= __('refunded') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['refund_data']['currency']) ?> <?= number_format($ticket['refund_data']['refund_to_passenger'], 2) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($ticket['date_change_data']): ?>
                                        <div class="ticket-card-detail-item" style="color:#fff9c4">
                                            <span class="ticket-card-detail-label"><?= __('date_change') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['date_change_data']['currency']) ?> <?= number_format($ticket['date_change_data']['supplier_penalty'] + $ticket['date_change_data']['service_penalty'], 2) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($ticket['ticket']['weight_count'] > 0): ?>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('weight') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['weight_count']) ?> items, <?= number_format($ticket['ticket']['total_weight'], 2) ?> kg</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ticket-card-right">
                                    <div class="ticket-card-price-box"><?= number_format($ticket['ticket']['sold'], 2) ?></div>
                                    <div class="ticket-card-price-meta">
                                        <div class="ticket-card-meta-dot"></div>
                                        <div class="ticket-card-meta-dot"></div>
                                        <div class="ticket-card-meta-dot"></div>
                                        <span><?= htmlspecialchars($baseCurrency) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="ticket-card-stub">
                                <div class="ticket-card-actions">
                                    <button class="ticket-card-action-btn view-details"
                                            data-ticket='<?= json_encode($ticket) ?>'
                                            title="<?= __('view_details') ?>">
                                        <i class="feather icon-eye"></i>
                                    </button>
                                    <?php if ($canEdit): ?>
                                    <button class="ticket-card-action-btn"
                                            onclick="editTicket(<?= $ticket['ticket']['id'] ?>)"
                                            title="<?= __('edit') ?>">
                                        <i class="feather icon-edit-2"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($isAgencyClient && $canEdit): ?>
                                    <button class="ticket-card-action-btn"
                                            onclick="manageTransactions(<?= $ticket['ticket']['id'] ?>)"
                                            title="<?= __('manage_transactions') ?>">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($canEdit): ?>
                                    <button class="ticket-card-action-btn"
                                            onclick="deleteTicket(<?= $ticket['ticket']['id'] ?>)"
                                            title="<?= __('delete') ?>">
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
                            <?= __('showing') ?> <?= min(($page - 1) * $results_per_page + 1, $totalTickets) ?>
                            <?= __('to') ?> <?= min($page * $results_per_page, $totalTickets) ?>
                            <?= __('of') ?> <?= $totalTickets ?> <?= __('tickets') ?>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                            <i class="feather icon-chevrons-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                            <i class="feather icon-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page   = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search='.urlencode($search) : '') . '">1</a></li>';
                                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . (!empty($search) ? '&search='.urlencode($search) : '') . '">' . $i . '</a>
                                    </li>';
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search) ? '&search='.urlencode($search) : '') . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                            <i class="feather icon-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
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

<!-- FAB -->
<div class="pg-fab" style="<?php echo is_rtl() ? 'left:20px' : 'right:20px' ?>; margin-bottom: 5px;">
    <button type="button" id="launchMultiTicketInvoice"
            title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>

<?php include '../includes/admin_footer.php'; ?>
<?php include '../modals/ticket/multi_ticket_modal.php'; ?>
<?php include '../modals/ticket/book_ticket_modal.php'; ?>
<?php include '../modals/ticket/ticket_details.php'; ?>
<?php include '../modals/ticket/ticket_refund_modal.php'; ?>
<?php include '../modals/ticket/ticket_date_change_modal.php'; ?>
<?php include '../modals/ticket/ticket_weight_modal.php'; ?>
<?php include '../modals/ticket/transaction_modal.php'; ?>
<?php include '../modals/ticket/edit_ticket_modal.php'; ?>

<!-- Required JS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
<script src="../js/ticket/profit-calc.js"></script>
<script src="../js/ticket/ticket-details.js"></script>
<script src="../js/ticket/ticket-form.js"></script>
<script src="../js/ticket/supplier-currency.js"></script>
<script src="../js/ticket/delete-ticket.js"></script>
<script src="../js/ticket/weight-management.js"></script>
<script src="../js/ticket/refund-calc.js"></script>
<script src="../js/ticket/search.js"></script>
<script src="../js/ticket/transaction-manager.js<?= '?v=' . time() ?>"></script>
<script src="../js/ticket/trip-type.js"></script>
<script src="../js/ticket/payment-calculation.js"></script>
<script src="../js/ticket/passenger-count.js"></script>
<script src="../js/ticket/supplier-currency-select.js"></script>
<script src="../js/ticket/edit-ticket.js"></script>
<script src="../js/ticket/data/airlines.js"></script>
<script src="../js/ticket/airline-select.js"></script>
<script src="../js/ticket/multi-ticket-invoice.js"></script>
<script src="../js/ticket/pdf-upload-handler.js"></script>
<script src="../js/ticket/passenger_info.js"></script>
<script src="../js/ticket/toast.js"></script>
<script src="../js/ticket/pdf-ticket-extract.js"></script>
<script src="../js/ticket/client-phone-autofill.js"></script>
<script src="../js/ticket/refresh-table.js"></script>

</body>
</html>