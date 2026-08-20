<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_permission('finance.additional_payments');

// Handle direct form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    include 'includes/update_additional_payment_base.php';
    exit();
}

// Database connection
require_once('../includes/db.php');

// Load input validation helper
require_once '../includes/InputValidator.php';

// Fetch main accounts for dropdown
$mainAccountsQuery = "SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($mainAccountsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$mainAccounts = $stmt->fetchAll();

// Fetch suppliers for dropdown
$suppliersQuery = "SELECT * FROM suppliers WHERE status = 'active' AND supplier_type = 'external' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($suppliersQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$suppliers = $stmt->fetchAll();

// Fetch clients for dropdown
$clientsQuery = "SELECT * FROM clients WHERE status = 'active' AND client_type = 'regular' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($clientsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clients = $stmt->fetchAll();

// Pagination settings
$items_per_page = 10;
$current_page = InputValidator::getInt($_GET['page'] ?? '', 1, 1, 9999);
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_query = InputValidator::getString($_GET['search'] ?? '', 100);
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " AND (
        ap.payment_type LIKE ? OR
        ap.description LIKE ? OR
        ma.name LIKE ? OR
        s.name LIKE ? OR
        c.name LIKE ?
    )";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM additional_payments ap
              LEFT JOIN users u ON ap.created_by = u.id
              LEFT JOIN main_account ma ON ap.main_account_id = ma.id
              LEFT JOIN suppliers s ON ap.supplier_id = s.id
              LEFT JOIN clients c ON ap.client_id = c.id
              WHERE ap.tenant_id = ? AND ap.branch_id = ?" . $search_condition;
$countParams = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $countParams = array_merge($countParams, array_fill(0, 5, $search_param));
}
$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Get all additional payments with pagination
$paymentsQuery = "SELECT ap.*, u.name as created_by_name, ma.name as main_account_name,
                  s.name as supplier_name, s.id as supplier_id,
                  c.name as client_name, c.id as client_id, c.client_type as client_type
                  FROM additional_payments ap
                  LEFT JOIN users u ON ap.created_by = u.id
                  LEFT JOIN main_account ma ON ap.main_account_id = ma.id
                  LEFT JOIN suppliers s ON ap.supplier_id = s.id
                  LEFT JOIN clients c ON ap.client_id = c.id
                  WHERE ap.tenant_id = ? AND ap.branch_id = ?" . $search_condition . "
                  ORDER BY ap.created_at DESC
                  LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($paymentsQuery);
$params = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $params = array_merge($params, array_fill(0, 5, $search_param));
}
$params[] = $items_per_page;
$params[] = $offset;
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<style>
/* ── Design tokens ── */
:root {
    --ap-bg:           #f4f6f9;
    --ap-surface:      #ffffff;
    --ap-border:       #e8eaed;
    --ap-border-soft:  #f0f2f5;
    --ap-text:         #111827;
    --ap-text-2:       #6b7280;
    --ap-text-muted:   #9ca3af;
    --ap-accent:       #2563eb;
    --ap-accent-soft:  #eff6ff;
    --ap-accent-mid:   #bfdbfe;
    --ap-green:        #059669;
    --ap-green-soft:   #ecfdf5;
    --ap-amber:        #d97706;
    --ap-amber-soft:   #fffbeb;
    --ap-red:          #dc2626;
    --ap-red-soft:     #fef2f2;
    --ap-sky:          #0284c7;
    --ap-sky-soft:     #f0f9ff;
    --ap-radius:       12px;
    --ap-radius-sm:    8px;
    --ap-shadow:       0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --ap-shadow-hover: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
    --ap-mono:         'Courier New', monospace;
}

/* ── Cards container ── */
.ap-cards-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 4px 0;
}

/* ── Individual card ── */
.ap-pcard {
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: var(--ap-radius);
    box-shadow: var(--ap-shadow);
    overflow: hidden;
    transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    animation: apCardIn 0.25s ease both;
}

.ap-pcard:hover {
    box-shadow: var(--ap-shadow-hover);
    border-color: var(--ap-accent-mid);
    transform: translateY(-1px);
}

@keyframes apCardIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

.ap-cards-list .ap-pcard:nth-child(1)  { animation-delay: 0ms; }
.ap-cards-list .ap-pcard:nth-child(2)  { animation-delay: 50ms; }
.ap-cards-list .ap-pcard:nth-child(3)  { animation-delay: 100ms; }
.ap-cards-list .ap-pcard:nth-child(4)  { animation-delay: 150ms; }
.ap-cards-list .ap-pcard:nth-child(5)  { animation-delay: 200ms; }
.ap-cards-list .ap-pcard:nth-child(6)  { animation-delay: 250ms; }
.ap-cards-list .ap-pcard:nth-child(n+7){ animation-delay: 300ms; }

/* ── Card top row ── */
.ap-pcard-top {
    display: grid;
    grid-template-columns: 160px 1fr 200px 185px 145px 110px;
    align-items: stretch;
}

/* ── Shared cell base ── */
.ap-cell {
    padding: 18px 20px;
    border-right: 1px solid var(--ap-border-soft);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
}

.ap-cell:last-child { border-right: none; }

/* ── Field labels & values ── */
.ap-field-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ap-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}

.ap-field-label i {
    font-size: 0.72rem;
    color: var(--ap-accent);
}

.ap-field-value {
    font-size: 0.875rem;
    color: var(--ap-text);
    line-height: 1.5;
}

/* ── Type cell ── */
.ap-cell-type {
    gap: 8px;
}

.ap-type-value {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--ap-text);
    line-height: 1.3;
}

/* ── Status badges ── */
.ap-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.3px;
    width: fit-content;
}

.ap-status-paid     { background: var(--ap-green-soft); color: var(--ap-green); }
.ap-status-partial  { background: var(--ap-amber-soft); color: var(--ap-amber); }
.ap-status-unpaid   { background: var(--ap-red-soft);   color: var(--ap-red); }
.ap-status-overpaid { background: var(--ap-sky-soft);   color: var(--ap-sky); }
.ap-status-neutral  { background: #e8eff6; color: #6b8fb3; }

/* ── Financial cell ── */
.ap-fin-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 10px;
    padding: 3px 0;
}

.ap-fin-row + .ap-fin-row {
    border-top: 1px solid var(--ap-border-soft);
    margin-top: 2px;
    padding-top: 4px;
}

.ap-fin-label {
    font-size: 0.72rem;
    color: var(--ap-text-muted);
    flex-shrink: 0;
}

.ap-fin-value {
    font-family: var(--ap-mono);
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--ap-text);
    text-align: right;
}

.ap-fin-value.ap-accent-val { color: var(--ap-accent); font-weight: 600; }
.ap-fin-value.ap-green-val  { color: var(--ap-green); }

/* ── Accounts cell ── */
.ap-acct-item {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.ap-acct-label {
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--ap-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.ap-acct-value {
    font-size: 0.82rem;
    color: var(--ap-text-2);
    font-weight: 500;
}

.ap-acct-value.ap-na { color: var(--ap-text-muted); font-style: italic; }

/* ── Meta cell ── */
.ap-meta-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--ap-text);
}

.ap-meta-date {
    font-size: 0.75rem;
    color: var(--ap-text-muted);
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── Actions cell ── */
.ap-cell-actions {
    gap: 6px;
    align-items: stretch;
    padding: 16px 14px;
}

.ap-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 7px;
    font-size: 0.775rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s ease;
    white-space: nowrap;
    font-family: inherit;
}

.ap-action-btn.ap-btn-txn {
    background: var(--ap-sky-soft);
    color: var(--ap-sky);
    border-color: #bae6fd;
}
.ap-action-btn.ap-btn-txn:hover {
    background: var(--ap-sky);
    color: #fff;
    border-color: var(--ap-sky);
    box-shadow: 0 2px 8px rgba(2,132,199,0.25);
}

.ap-action-btn.ap-btn-edit {
    background: var(--ap-accent-soft);
    color: var(--ap-accent);
    border-color: var(--ap-accent-mid);
}
.ap-action-btn.ap-btn-edit:hover {
    background: var(--ap-accent);
    color: #fff;
    border-color: var(--ap-accent);
    box-shadow: 0 2px 8px rgba(37,99,235,0.25);
}

.ap-action-btn.ap-btn-delete {
    background: var(--ap-red-soft);
    color: var(--ap-red);
    border-color: #fecaca;
}
.ap-action-btn.ap-btn-delete:hover {
    background: var(--ap-red);
    color: #fff;
    border-color: var(--ap-red);
    box-shadow: 0 2px 8px rgba(220,38,38,0.25);
}

/* Txn count badge */
.ap-txn-badge {
    background: var(--ap-sky);
    color: #fff;
    border-radius: 10px;
    padding: 1px 6px;
    font-size: 0.66rem;
    font-weight: 700;
    line-height: 1.4;
}

/* ── Progress strip ── */
.ap-progress-strip {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 20px;
    background: var(--ap-bg);
    border-top: 1px solid var(--ap-border-soft);
}

.ap-prog-label {
    font-size: 0.67rem;
    font-weight: 700;
    color: var(--ap-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    flex-shrink: 0;
    width: 110px; /* aligns with type cell */
}

.ap-prog-track {
    flex: 1;
    height: 4px;
    background: var(--ap-border);
    border-radius: 4px;
    overflow: hidden;
}

.ap-prog-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s cubic-bezier(.4,0,.2,1);
    background: var(--ap-accent);
}
.ap-prog-fill.ap-fill-full { background: var(--ap-green); }
.ap-prog-fill.ap-fill-over { background: var(--ap-sky); }
.ap-prog-fill.ap-fill-none { background: var(--ap-border); }

.ap-prog-paid {
    font-size: 0.72rem;
    color: var(--ap-text-muted);
    flex-shrink: 0;
    font-family: var(--ap-mono);
}

.ap-prog-pct {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ap-text-2);
    flex-shrink: 0;
    min-width: 36px;
    text-align: right;
    font-family: var(--ap-mono);
}

/* ── Empty state ── */
.ap-empty-state {
    text-align: center;
    padding: 72px 24px;
    background: var(--ap-surface);
    border: 1px dashed var(--ap-border);
    border-radius: var(--ap-radius);
    margin: 8px 0;
}

.ap-empty-state .ap-empty-icon {
    width: 60px;
    height: 60px;
    background: var(--ap-bg);
    border-radius: 50%;
    display: grid;
    place-items: center;
    margin: 0 auto 16px;
    font-size: 1.4rem;
    color: var(--ap-text-muted);
}

.ap-empty-state h5 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--ap-text-2);
    margin-bottom: 6px;
}

.ap-empty-state p {
    font-size: 0.875rem;
    color: var(--ap-text-muted);
    margin: 0;
}

/* ── Page header styling ── */
.page-header {
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: var(--ap-radius);
    box-shadow: var(--ap-shadow);
    padding: 24px;
    margin-bottom: 24px;
}

.page-header h5 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--ap-text);
    margin: 0;
    display: flex;
    align-items: center;
}

.page-header h5 i {
    font-size: 1.3rem;
    color: var(--ap-accent);
    margin-right: 10px;
}

.page-header p {
    font-size: 0.875rem;
    color: var(--ap-text-2);
    margin: 0;
}

.page-header .row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.page-header .col-md-6:last-child {
    text-align: right;
}

/* ── Card header styling ── */
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    border-bottom: 1px solid var(--ap-border);
    background: var(--ap-bg);
}

.card-header h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--ap-text);
}

.card-header .btn {
    flex-shrink: 0;
    margin-left: auto;
}

/* ── Responsive breakpoints ── */
@media (max-width: 1300px) {
    .ap-pcard-top { grid-template-columns: 150px 1fr 190px 170px 110px; }
    .ap-cell-accounts { display: none; }
}

@media (max-width: 1050px) {
    .ap-pcard-top { grid-template-columns: 140px 1fr 180px 110px; }
    .ap-cell-financial { display: none; }
}

@media (max-width: 768px) {
    .ap-pcard-top {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto;
    }
    .ap-cell { border-right: none; border-bottom: 1px solid var(--ap-border-soft); }
    .ap-cell-type { grid-column: 1 / -1; flex-direction: row; align-items: center; justify-content: space-between; }
    .ap-cell-actions { grid-column: 1 / -1; flex-direction: row; padding: 14px 16px; }
    .ap-action-btn { flex: 1; }
    .ap-prog-label { width: auto; }
    
    .page-header .row {
        flex-direction: column;
        text-align: left;
    }
    
    .page-header .col-md-6:last-child {
        text-align: left;
        margin-top: 16px;
    }
}

@media (max-width: 480px) {
    .ap-pcard-top { grid-template-columns: 1fr; }
    .ap-cell-type { flex-direction: column; align-items: flex-start; }
}
</style>

    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">

    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="main-content">

                                <!-- Page Header -->
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5><i class="feather icon-plus-circle mr-2"></i><?= __('additional_payments') ?></h5>
                                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;"><?= __('manage_additional_payments') ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?= __('back_to_dashboard') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">

                                            <!-- Card header -->
                                            <div class="card-header">
                                                <h5><?= __('payment_list') ?></h5>
<?php if (user_can('finance.edit')): ?>
                                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPaymentModal">
                                                    <i class="feather icon-plus-circle"></i> <?= __('add_payment') ?>
                                                </button>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Search bar -->
                                            <div class="card-body border-bottom pb-3">
                                                <form method="GET" class="form-inline">
                                                    <div class="form-group mb-0 flex-grow-1">
                                                        <input
                                                            type="text"
                                                            name="search"
                                                            class="form-control w-100"
                                                            placeholder="Search by payment type, description, account name..."
                                                            value="<?= htmlspecialchars($search_query) ?>">
                                                    </div>
                                                    <button type="submit" class="btn btn-info ml-2">
                                                        <i class="feather icon-search"></i> Search
                                                    </button>
                                                    <?php if (!empty($search_query)): ?>
                                                        <a href="additional_payments.php" class="btn btn-secondary ml-2">
                                                            <i class="feather icon-x"></i> Clear
                                                        </a>
                                                    <?php endif; ?>
                                                </form>
                                            </div>

                                            <div class="card-body">

                                                <?php if (isset($_SESSION['success'])): ?>
                                                    <div class="alert alert-success"><?= h($_SESSION['success']); unset($_SESSION['success']); ?></div>
                                                <?php endif; ?>

                                                <?php if (isset($_SESSION['error'])): ?>
                                                    <div class="alert alert-danger"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
                                                <?php endif; ?>

                                                <!-- Pagination info -->
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                        </small>
                                                    </div>
                                                </div>

                                                <?php if (empty($payments)): ?>
                                                    <!-- Empty state -->
                                                    <div class="ap-empty-state">
                                                        <div class="ap-empty-icon">
                                                            <i class="feather icon-inbox"></i>
                                                        </div>
                                                        <h5>No Payments Found</h5>
                                                        <p>There are no additional payments to display. Create a new payment to get started.</p>
                                                    </div>

                                                <?php else: ?>
                                                    <!-- ── Payment cards ── -->
                                                    <div class="ap-cards-list">
                                                        <?php foreach ($payments as $payment): ?>
                                                        <?php
                                                            // ── Compute totalPaidInBase ──
                                                            $isAgencyClient    = ($payment['client_type'] ?? '') === 'agency';
                                                            $canAddTransaction = empty($payment['client_id']) || $isAgencyClient;
                                                            $baseCurrency      = $payment['currency'];
                                                            $soldAmount        = floatval($payment['sold_amount']);
                                                            $paymentId         = $payment['id'];
                                                            $totalPaidInBase   = 0.0;

                                                            $transactionStmt = $pdo->prepare("
                                                                SELECT * FROM main_account_transactions
                                                                WHERE transaction_of = 'additional_payment'
                                                                  AND reference_id = ? AND tenant_id = ? AND branch_id = ?
                                                            ");
                                                            $transactionStmt->execute([$paymentId, $tenant_id, $branch_id]);
                                                            $transactions = $transactionStmt->fetchAll();

                                                            $txnCount        = count($transactions);
                                                            $usdToAfs        = 70;
                                                            $usdToEur        = 0.9;
                                                            $usdToDarham     = 3.61;
                                                            $usdToSar        = 3.75;
                                                            $badgeClass      = 'ap-status-neutral';
                                                            $badgeIcon       = 'fas fa-minus-circle';
                                                            $badgeLabel      = 'N/A';
                                                            $paymentProgress = 0;
                                                            $fillClass       = 'ap-fill-none';

                                                            if ($canAddTransaction && $txnCount > 0) {
                                                                foreach ($transactions as $t) {
                                                                    $er = isset($t['exchange_rate']) && $t['exchange_rate'] > 0 ? floatval($t['exchange_rate']) : null;
                                                                    if ($er) {
                                                                        if ($t['currency'] === 'AFS')         $usdToAfs    = $er;
                                                                        elseif ($t['currency'] === 'EUR')     $usdToEur    = $er;
                                                                        elseif ($t['currency'] === 'DARHAM')  $usdToDarham = $er;
                                                                        elseif ($t['currency'] === 'SAR')     $usdToSar    = $er;
                                                                    }
                                                                }
                                                                foreach ($transactions as $t) {
                                                                    $amount    = floatval($t['amount']);
                                                                    $transCur  = $t['currency'];
                                                                    $er        = isset($t['exchange_rate']) && $t['exchange_rate'] > 0 ? floatval($t['exchange_rate']) : null;
                                                                    $converted = $amount;

                                                                    if ($transCur !== $baseCurrency) {
                                                                        $inUsd = $amount;
                                                                        if ($transCur === 'AFS')         $inUsd = $amount / ($er ?? $usdToAfs);
                                                                        elseif ($transCur === 'EUR')     $inUsd = $amount / ($er ?? $usdToEur);
                                                                        elseif ($transCur === 'DARHAM')  $inUsd = $amount / ($er ?? $usdToDarham);
                                                                        elseif ($transCur === 'SAR')     $inUsd = $amount / ($er ?? $usdToSar);

                                                                        if ($baseCurrency === 'USD')         $converted = $inUsd;
                                                                        elseif ($baseCurrency === 'AFS')    $converted = $inUsd * $usdToAfs;
                                                                        elseif ($baseCurrency === 'EUR')    $converted = $inUsd * $usdToEur;
                                                                        elseif ($baseCurrency === 'DARHAM') $converted = $inUsd * $usdToDarham;
                                                                        elseif ($baseCurrency === 'SAR')    $converted = $inUsd * $usdToSar;
                                                                    }
                                                                    $totalPaidInBase += $converted;
                                                                }
                                                            }

                                                            // ── Status badge ──
                                                            if ($canAddTransaction) {
                                                                if ($totalPaidInBase <= 0) {
                                                                    $badgeClass = 'ap-status-unpaid';
                                                                    $badgeIcon  = 'fas fa-times-circle';
                                                                    $badgeLabel = 'Unpaid';
                                                                } elseif ($totalPaidInBase < ($soldAmount - 0.01)) {
                                                                    $badgeClass = 'ap-status-partial';
                                                                    $badgeIcon  = 'fas fa-adjust';
                                                                    $badgeLabel = 'Partial';
                                                                } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                    $badgeClass = 'ap-status-paid';
                                                                    $badgeIcon  = 'fas fa-check-circle';
                                                                    $badgeLabel = 'Paid';
                                                                } else {
                                                                    $badgeClass = 'ap-status-overpaid';
                                                                    $badgeIcon  = 'fas fa-arrow-circle-up';
                                                                    $badgeLabel = 'Overpaid';
                                                                }

                                                                $paymentProgress = $soldAmount > 0 ? min(100, round(($totalPaidInBase / $soldAmount) * 100)) : 0;

                                                                if ($totalPaidInBase <= 0) {
                                                                    $fillClass = 'ap-fill-none';
                                                                } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                    $fillClass = 'ap-fill-full';
                                                                } elseif ($totalPaidInBase > $soldAmount) {
                                                                    $fillClass = 'ap-fill-over';
                                                                } else {
                                                                    $fillClass = '';
                                                                }
                                                            }
                                                        ?>

                                                        <div class="ap-pcard">
                                                            <div class="ap-pcard-top">

                                                                <!-- Type + Status -->
                                                                <div class="ap-cell ap-cell-type">
                                                                    <span class="ap-field-label"><i class="feather icon-tag"></i><?= __('type') ?></span>
                                                                    <span class="ap-type-value"><?= htmlspecialchars($payment['payment_type']) ?></span>
                                                                    <span class="ap-status-badge <?= $badgeClass ?>">
                                                                        <i class="<?= $badgeIcon ?>"></i> <?= $badgeLabel ?>
                                                                    </span>
                                                                </div>

                                                                <!-- Description -->
                                                                <div class="ap-cell ap-cell-desc">
                                                                    <span class="ap-field-label"><i class="feather icon-file-text"></i><?= __('description') ?></span>
                                                                    <span class="ap-field-value"><?= htmlspecialchars($payment['description']) ?></span>
                                                                </div>

                                                                <!-- Financial -->
                                                                <div class="ap-cell ap-cell-financial">
                                                                    <span class="ap-field-label" style="margin-bottom:6px;"><i class="feather icon-dollar-sign"></i><?= __('financial_details') ?></span>
                                                                    <div class="ap-fin-row">
                                                                        <span class="ap-fin-label">Base</span>
                                                                        <span class="ap-fin-value"><?= number_format($payment['base_amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></span>
                                                                    </div>
                                                                    <div class="ap-fin-row">
                                                                        <span class="ap-fin-label">Sold</span>
                                                                        <span class="ap-fin-value ap-accent-val"><?= number_format($payment['sold_amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></span>
                                                                    </div>
                                                                    <div class="ap-fin-row">
                                                                        <span class="ap-fin-label">Profit</span>
                                                                        <span class="ap-fin-value ap-green-val"><?= number_format($payment['profit'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></span>
                                                                    </div>
                                                                </div>

                                                                <!-- Accounts -->
                                                                <div class="ap-cell ap-cell-accounts">
                                                                    <span class="ap-field-label" style="margin-bottom:6px;"><i class="feather icon-home"></i><?= __('accounts') ?></span>
                                                                    <div class="ap-acct-item">
                                                                        <span class="ap-acct-label">Main Account</span>
                                                                        <span class="ap-acct-value"><?= htmlspecialchars($payment['main_account_name']) ?></span>
                                                                    </div>
                                                                    <div class="ap-acct-item" style="margin-top:5px;">
                                                                        <span class="ap-acct-label">Supplier</span>
                                                                        <span class="ap-acct-value <?= empty($payment['supplier_name']) ? 'ap-na' : '' ?>">
                                                                            <?= htmlspecialchars($payment['supplier_name'] ?? 'N/A') ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="ap-acct-item" style="margin-top:5px;">
                                                                        <span class="ap-acct-label">Client</span>
                                                                        <span class="ap-acct-value <?= empty($payment['client_name']) ? 'ap-na' : '' ?>">
                                                                            <?= htmlspecialchars($payment['client_name'] ?? 'N/A') ?>
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <!-- Created by -->
                                                                <div class="ap-cell ap-cell-meta">
                                                                    <span class="ap-field-label"><i class="feather icon-user"></i><?= __('created_by') ?></span>
                                                                    <span class="ap-meta-name"><?= htmlspecialchars($payment['created_by_name']) ?></span>
                                                                    <span class="ap-meta-date">
                                                                        <i class="feather icon-calendar"></i>
                                                                        <?= date('M d, Y H:i', strtotime($payment['created_at'])) ?>
                                                                    </span>
                                                                </div>

                                                                <!-- Actions -->
                                                                <div class="ap-cell ap-cell-actions">
                                                                    <?php if ($canAddTransaction): ?>
                                                                    <button class="ap-action-btn ap-btn-txn add-transaction"
                                                                            data-id="<?= $payment['id'] ?>"
                                                                            data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                            data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                            data-main-account="<?= $payment['main_account_id'] ?>"
                                                                            data-supplier="<?= $payment['supplier_id'] ?>"
                                                                            data-client="<?= $payment['client_id'] ?>"
                                                                            data-receipt="<?= htmlspecialchars($payment['receipt'] ?? '') ?>"
                                                                            data-description="<?= htmlspecialchars($payment['description'] ?? '') ?>"
                                                                            data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                            data-toggle="modal" data-target="#addTransactionModal">
                                                                        <i class="feather icon-credit-card"></i> Add
                                                                        <?php if ($txnCount > 0): ?>
                                                                            <span class="ap-txn-badge"><?= $txnCount ?></span>
                                                                        <?php endif; ?>
                                                                    </button>
                                                                    <?php endif; ?>

                                                                    <?php if (user_can('finance.edit')): ?>
                                                                    <button class="ap-action-btn ap-btn-edit edit-payment"
                                                                            data-id="<?= $payment['id'] ?>"
                                                                            data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                            data-description="<?= htmlspecialchars($payment['description']) ?>"
                                                                            data-base-amount="<?= $payment['base_amount'] ?>"
                                                                            data-profit="<?= $payment['profit'] ?>"
                                                                            data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                            data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                            data-main-account="<?= $payment['main_account_id'] ?>"
                                                                            data-supplier="<?= $payment['supplier_id'] ?>"
                                                                            data-client="<?= $payment['client_id'] ?>"
                                                                            data-receipt="<?= htmlspecialchars($payment['receipt'] ?? '') ?>"
                                                                            data-toggle="modal" data-target="#editPaymentModal">
                                                                        <i class="feather icon-edit"></i> Edit
                                                                    </button>
                                                                    <?php endif; ?>
                                                                    <?php if (user_can('finance.delete')): ?>
                                                                    <button class="ap-action-btn ap-btn-delete delete-payment"
                                                                            data-id="<?= $payment['id'] ?>">
                                                                        <i class="feather icon-trash-2"></i> Delete
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>

                                                            </div><!-- /.ap-pcard-top -->

                                                            <?php if ($canAddTransaction): ?>
                                                            <!-- Progress strip -->
                                                            <div class="ap-progress-strip">
                                                                <span class="ap-prog-label">Progress</span>
                                                                <div class="ap-prog-track">
                                                                    <div class="ap-prog-fill <?= $fillClass ?>" style="width:<?= $paymentProgress ?>%"></div>
                                                                </div>
                                                                <span class="ap-prog-paid">
                                                                    <?= number_format($totalPaidInBase, 2) ?> / <?= number_format($soldAmount, 2) ?> <?= htmlspecialchars($baseCurrency) ?>
                                                                </span>
                                                                <span class="ap-prog-pct"><?= $paymentProgress ?>%</span>
                                                            </div>
                                                            <?php endif; ?>

                                                        </div><!-- /.ap-pcard -->
                                                        <?php endforeach; ?>
                                                    </div><!-- /.ap-cards-list -->

                                                    <!-- Pagination -->
                                                    <?php if ($total_pages > 1): ?>
                                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                                            <small class="text-muted">
                                                                Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                            </small>
                                                            <nav>
                                                                <ul class="pagination pagination-sm mb-0">
                                                                    <?php if ($current_page > 1): ?>
                                                                        <li class="page-item">
                                                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                                                <i class="feather icon-chevron-left"></i>
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                                                                        <li class="page-item <?= $p === $current_page ? 'active' : '' ?>">
                                                                            <a class="page-link" href="?page=<?= $p ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $p ?></a>
                                                                        </li>
                                                                    <?php endfor; ?>
                                                                    <?php if ($current_page < $total_pages): ?>
                                                                        <li class="page-item">
                                                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                                                <i class="feather icon-chevron-right"></i>
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </nav>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php endif; ?>

                                            </div><!-- /.card-body -->
                                        </div><!-- /.card -->
                                    </div>
                                </div>
                            </div><!-- /.main-content -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php include '../modals/additional_payment/add_payment_modal.php'; ?>
    <?php include '../modals/additional_payment/edit_payment_modal.php'; ?>
    <?php include '../modals/additional_payment/add_transaction_modal.php'; ?>
    <?php include '../modals/additional_payment/edit_transaction_modal.php'; ?>

    <!-- Scripts -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../js/additional_payments/transactions.js<?= '?v=' . time() ?>"></script>
    <script src="../js/additional_payments/main.js"></script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
