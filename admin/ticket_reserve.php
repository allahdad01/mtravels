<?php
require_once 'security.php';
require_once '../includes/language_helpers.php';
enforce_auth();

$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once('../includes/db.php');
$user_id = $_SESSION['user_id'];
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$page            = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage  = 10;
$offset          = ($page - 1) * $recordsPerPage;

$searchCondition  = '';
$params           = [];
$searchParamCount = 0;

if ($search) {
    $searchCondition = "AND (
        tb.passenger_name LIKE ? OR tb.pnr LIKE ? OR tb.airline LIKE ? OR
        tb.origin LIKE ? OR tb.destination LIKE ? OR tb.supplier LIKE ? OR c.name LIKE ?
    )";
    $like             = "%$search%";
    $params           = array_fill(0, 7, $like);
    $searchParamCount = 7;
}

$ticketsQuery = "
    SELECT tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline,
           tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.sold, tb.price,
           tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status,
           tb.trip_type, tb.return_date, tb.return_origin, tb.return_destination,
           tb.supplier as supplier_name, c.name as sold_to_name,
           ma.name as paid_to_name, u.name as created_by
    FROM ticket_reservations tb
    LEFT JOIN clients c ON tb.sold_to = c.id
    LEFT JOIN main_account ma ON tb.paid_to = ma.id
    LEFT JOIN users u ON tb.created_by = u.id
    WHERE tb.tenant_id = ? AND tb.branch_id = ?
    $searchCondition
    ORDER BY tb.id DESC LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($ticketsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$pi = 3;
for ($i = 0; $i < $searchParamCount; $i++) $stmt->bindParam($pi++, $params[$i], PDO::PARAM_STR);
$stmt->bindParam($pi++, $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam($pi++, $offset, PDO::PARAM_INT);
$stmt->execute();
$ticketsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) as total FROM ticket_reservations tb
               LEFT JOIN clients c ON tb.sold_to = c.id
               WHERE tb.tenant_id = ? AND tb.branch_id = ? $searchCondition";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtCount->bindParam(2, $branch_id, PDO::PARAM_INT);
$ci = 3;
for ($i = 0; $i < $searchParamCount; $i++) $stmtCount->bindParam($ci++, $params[$i], PDO::PARAM_STR);
$stmtCount->execute();
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages   = ceil($totalRecords / $recordsPerPage);

$tickets = [];
foreach ($ticketsResult as $row) {
    $tid = $row['id'];
    if (!isset($tickets[$tid])) {
        $tickets[$tid] = ['ticket' => [
            'id' => $row['id'], 'supplier_name' => $row['supplier_name'],
            'sold_to' => $row['sold_to_name'], 'paid_to' => $row['paid_to_name'],
            'title' => $row['title'], 'passenger_name' => $row['passenger_name'],
            'pnr' => $row['pnr'], 'airline' => $row['airline'],
            'origin' => $row['origin'], 'destination' => $row['destination'],
            'issue_date' => $row['issue_date'], 'departure_date' => $row['departure_date'],
            'sold' => $row['sold'], 'price' => $row['price'], 'profit' => $row['profit'],
            'gender' => $row['gender'], 'currency' => $row['currency'], 'phone' => $row['phone'],
            'description' => $row['description'], 'status' => $row['status'],
            'trip_type' => $row['trip_type'], 'return_date' => $row['return_date'],
            'return_origin' => $row['return_origin'], 'return_destination' => $row['return_destination'],
            'created_by' => $row['created_by'],
        ]];
    }
}

$suppliersQuery = "SELECT id, name FROM suppliers WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";
$stmtSup = $pdo->prepare($suppliersQuery);
$stmtSup->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtSup->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmtSup->execute();
$suppliers      = $stmtSup->fetchAll(PDO::FETCH_ASSOC);
$supplier_names = array_column($suppliers, 'name', 'id');
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
<link rel="stylesheet" href="../css/ticket/ticket_styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-components.css">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-form.css">

<style>
/* ─── Reset & Base ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ─── Page Shell ───────────────────────────────────────── */
.pg-root { background: #f0f2f5; min-height: 100vh; display: flex; flex-direction: column; }

/* ─── Page Header ──────────────────────────────────────── */
.pg-header { background: #ffffff; border-bottom: 1px solid #e3e6eb; padding: 0 28px; }

.pg-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 0 0;
}

.pg-title-group { display: flex; align-items: center; gap: 12px; }

.pg-title-icon {
    width: 34px; height: 34px;
    background: #185FA5;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.pg-title-icon svg { width: 16px; height: 16px; }

.pg-title-text h1 { font-size: 19px; font-weight: 600; color: #1a2332; margin: 0; line-height: 1.2; }

.pg-breadcrumb {
    display: flex; align-items: center; gap: 5px;
    margin-top: 3px; font-size: 12px; color: #8a94a6;
    list-style: none; padding: 0; margin-bottom: 0;
}

.pg-breadcrumb li a { color: #5a6478; text-decoration: none; }
.pg-breadcrumb li a:hover { color: #185FA5; }
.pg-breadcrumb li + li::before { content: '›'; margin-right: 5px; opacity: 0.5; }

/* ─── Tab Nav ──────────────────────────────────────────── */
.pg-tabs { display: flex; gap: 0; margin-top: 14px; }

.pg-tab {
    padding: 11px 20px; font-size: 13px; font-weight: 500;
    color: #7a8499; cursor: pointer;
    border-bottom: 2px solid transparent;
    text-decoration: none; white-space: nowrap;
    transition: color .15s, border-color .15s;
}

.pg-tab:hover { color: #1a2332; }
.pg-tab.active { color: #185FA5; border-bottom-color: #185FA5; }

/* ─── Page Body ────────────────────────────────────────── */
.pg-body { padding: 24px 28px; flex: 1; }

/* ─── KPI Strip ────────────────────────────────────────── */
.kpi-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }

.kpi-card {
    background: #ffffff; border: 1px solid #e3e6eb;
    border-radius: 10px; padding: 16px 18px 14px;
    position: relative; overflow: hidden;
}

.kpi-accent { position: absolute; top: 0; left: 0; width: 3px; height: 100%; border-radius: 10px 0 0 10px; }

.kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #8a94a6; margin-bottom: 8px; }
.kpi-value { font-size: 26px; font-weight: 600; color: #1a2332; line-height: 1; }
.kpi-sub { font-size: 11px; color: #8a94a6; margin-top: 5px; display: flex; align-items: center; gap: 5px; }

.kpi-badge { display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.kpi-badge.green { background: #e8f4df; color: #3b6d11; }
.kpi-badge.amber { background: #fef3e2; color: #854f0b; }
.kpi-badge.blue  { background: #e6f1fb; color: #185FA5; }

/* ─── Toolbar ──────────────────────────────────────────── */
.pg-toolbar {
    background: #ffffff; border: 1px solid #e3e6eb;
    border-radius: 10px; padding: 12px 16px;
    display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
}

.toolbar-search {
    flex: 1; display: flex; align-items: center; gap: 9px;
    background: #f5f6f8; border: 1px solid #e3e6eb;
    border-radius: 7px; padding: 8px 13px; transition: border-color .15s;
}

.toolbar-search:focus-within { border-color: #185FA5; background: #fff; }
.toolbar-search svg { flex-shrink: 0; color: #9aa1b0; width: 14px; height: 14px; }
.toolbar-search input { border: none; background: transparent; font-size: 13px; color: #1a2332; outline: none; width: 100%; }
.toolbar-search input::placeholder { color: #9aa1b0; }

.toolbar-divider { width: 1px; height: 28px; background: #e3e6eb; flex-shrink: 0; }

.btn-ghost {
    display: inline-flex; align-items: center; gap: 6px;
    background: transparent; color: #5a6478; border: 1px solid #d8dce5;
    padding: 8px 13px; border-radius: 7px; font-size: 13px; font-weight: 500;
    cursor: pointer; white-space: nowrap; text-decoration: none;
    transition: background .15s, color .15s;
}

.btn-ghost:hover { background: #f5f6f8; color: #1a2332; text-decoration: none; }

.btn-primary-corp {
    display: inline-flex; align-items: center; gap: 7px;
    background: #185FA5; color: #ffffff; border: none;
    padding: 9px 17px; border-radius: 7px; font-size: 13px; font-weight: 600;
    cursor: pointer; white-space: nowrap; transition: background .15s;
}

.btn-primary-corp:hover { background: #0C447C; color: #fff; }

/* ─── Section Header ───────────────────────────────────── */
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #8a94a6; }
.section-count { font-size: 12px; color: #8a94a6; }

/* ─── Ticket Reserve Cards (preserved + updated) ──────── */
.ticket-card-container { display: flex; flex-direction: column; gap: 10px; }

.ticket-card {
    display: grid; grid-template-columns: 1fr 128px;
    border-radius: 10px; overflow: hidden;
    border: 1px solid #dde1e9;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06); transition: box-shadow .2s;
}

.ticket-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.1); }

.ticket-card-main {
    display: grid; grid-template-columns: 1fr auto;
    align-items: center; padding: 16px 20px; position: relative;
}

.ticket-card-main::after {
    content: ''; position: absolute; right: 0; top: 12%; height: 76%;
    border-right: 2px dashed rgba(255,255,255,0.4);
}

.ticket-card-main.status-paid    { background: #7aaa62; }
.ticket-card-main.status-partial { background: #d4a574; }
.ticket-card-main.status-unpaid  { background: #e07a7a; }
.ticket-card-main.status-neutral { background: #6b8fb3; }

.ticket-card-left { display: flex; flex-direction: column; gap: 9px; }

.ticket-card-header { display: flex; gap: 12px; align-items: flex-start; }
.ticket-card-status-dots { display: flex; gap: 5px; align-items: center; padding-top: 2px; }
.ticket-card-dot { width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,0.6); }
.ticket-card-dot.primary { width: 9px; height: 9px; background: rgba(255,255,255,0.9); }

.ticket-card-title { font-size: 16px; font-weight: 700; color: #fff; letter-spacing: 0.4px; line-height: 1; display: flex; align-items: center; gap: 8px; }
.ticket-card-title span { display: block; width: 20px; height: 2px; background: rgba(255,255,255,0.65); }
.ticket-card-id { font-size: 11px; color: rgba(255,255,255,0.82); font-weight: 500; }

.ticket-card-details { display: grid; grid-template-columns: 1fr 1fr; gap: 7px 14px; color: rgba(255,255,255,0.92); font-size: 12px; margin-top: 4px; }
.ticket-card-detail-item { display: flex; align-items: center; gap: 5px; }
.ticket-card-detail-label { font-weight: 600; font-size: 10px; text-transform: uppercase; opacity: 0.75; min-width: fit-content; }

.ticket-card-right { display: flex; flex-direction: column; gap: 6px; align-items: center; }

.ticket-card-price-box { background: #fff; border-radius: 7px; padding: 8px 12px; font-size: 24px; font-weight: 700; color: #1e2d3d; letter-spacing: -0.5px; text-align: center; min-width: 80px; }

.ticket-card-price-meta { display: flex; gap: 3px; align-items: center; font-size: 9px; color: rgba(255,255,255,0.7); }
.ticket-card-meta-dot { width: 4px; height: 4px; border-radius: 50%; background: rgba(255,255,255,0.55); }

.ticket-card-stub { background: #eaecf0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 8px; gap: 4px; }
.ticket-card-actions { display: flex; flex-direction: column; gap: 5px; width: 100%; }

.ticket-card-action-btn {
    background: #185FA5; border: none; color: #fff;
    padding: 8px 12px; border-radius: 5px; font-size: 12px;
    cursor: pointer; transition: background .15s;
    display: flex; align-items: center; justify-content: center; width: 100%;
}

.ticket-card-action-btn:hover { background: #0C447C; }

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
    background: #ffffff; border: 1px solid #e3e6eb;
    border-radius: 10px; padding: 12px 18px;
    display: flex; align-items: center; justify-content: space-between; margin-top: 16px;
}

.pg-list-footer .info { font-size: 12px; color: #8a94a6; }
.pagination { margin-bottom: 0; }

.pagination .page-link { border-color: #e3e6eb; color: #5a6478; font-size: 13px; padding: 6px 11px; }
.pagination .page-item.active .page-link { background-color: #185FA5; border-color: #185FA5; color: #fff; }
.pagination .page-link:hover { background: #f5f6f8; color: #1a2332; }

/* ─── Toast ────────────────────────────────────────────── */
.toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px; }

.toast {
    position: relative; background-color: #fff; border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.15); margin-bottom: 10px;
    overflow: hidden; opacity: 0; transform: translateX(40px);
    transition: all 0.3s ease; border-left: 4px solid transparent; padding: 15px;
}

.toast-showing  { opacity: 1; transform: translateX(0); }
.toast-removing { opacity: 0; transform: translateY(-20px); }
.toast-success  { border-left-color: #10b981; }
.toast-error    { border-left-color: #ef4444; }
.toast-warning  { border-left-color: #f59e0b; }
.toast-info     { border-left-color: #3b82f6; }
.toast-title    { display: flex; align-items: center; font-weight: 600; margin-bottom: 5px; }
.toast-message  { word-break: break-word; line-height: 1.5; color: #64748b; }
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
                                    <rect x="1" y="4" width="14" height="9" rx="2" stroke="white" stroke-width="1.4"/>
                                    <path d="M5 4V3a3 3 0 016 0v1" stroke="white" stroke-width="1.3" stroke-linecap="round"/>
                                    <circle cx="8" cy="8.5" r="1.5" fill="white"/>
                                </svg>
                            </div>
                            <div class="pg-title-text">
                                <h1><?= __('ticket_reservations') ?></h1>
                                <ul class="pg-breadcrumb">
                                    <li><a href="dashboard.php"><i class="feather icon-home" style="font-size:11px"></i></a></li>
                                    <li><?= __('ticket_reservations') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="pg-tabs">
                        <a class="pg-tab active" href="?status=all<?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
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
                                placeholder="<?= __('search_tickets') ?>"
                                value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <?php if (!empty($search)): ?>
                        <a href="ticket_reserve.php" class="btn-ghost">
                            <i class="feather icon-x" style="font-size:13px"></i>
                            <?= __('clear') ?? 'Clear' ?>
                        </a>
                        <?php endif; ?>

                        <div class="toolbar-divider"></div>

                        <button type="submit" class="btn-ghost">
                            <i class="feather icon-search" style="font-size:13px"></i>
                            <?= __('search') ?? 'Search' ?>
                        </button>

                        <button type="button" class="btn-primary-corp" data-toggle="modal" data-target="#bookTicketModal">
                            <i class="feather icon-plus-circle" style="font-size:13px"></i>
                            <?= __('reserve_ticket') ?>
                        </button>
                    </div>
                    </form>

                    <!-- Section Header -->
                    <div class="section-head">
                        <div class="section-label"><?= __('results') ?? 'Results' ?></div>
                        <div class="section-count">
                            <?php
                            $startRecord = ($page - 1) * $recordsPerPage + 1;
                            $endRecord   = min($startRecord + $recordsPerPage - 1, $totalRecords);
                            echo __('showing') . ' ' . $startRecord . ' ' . __('to') . ' ' . $endRecord . ' ' . __('of') . ' ' . $totalRecords . ' ' . (__('entries') ?? 'entries');
                            ?>
                        </div>
                    </div>

                    <!-- Reservation Cards -->
                    <div class="ticket-card-container" id="ticketTable">
                        <?php foreach ($tickets as $ticket):
                            $isAgencyClient = false;
                            $paymentStatus  = 'neutral';

                            $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                            $clientStmt->bindParam(1, $ticket['ticket']['sold_to'], PDO::PARAM_STR);
                            $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                            $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                            $clientStmt->execute();
                            $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                            if ($clientRow) {
                                $isAgencyClient = ($clientRow['client_type'] === 'agency');
                            }

                            if ($isAgencyClient) {
                                $baseCurrency    = $ticket['ticket']['currency'];
                                $soldAmount      = floatval($ticket['ticket']['sold']);
                                $totalPaidInBase = 0.0;
                                $ticketId        = $ticket['ticket']['id'];

                                $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'ticket_reserve' AND reference_id = ?");
                                $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
                                $transactionStmt->execute();
                                $transactionQuery = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                if ($transactionQuery) {
                                    foreach ($transactionQuery as $transaction) {
                                        $amount            = floatval($transaction['amount']);
                                        $transCurrency     = $transaction['currency'];
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
                                        <div class="ticket-card-title">RESERVE <span></span></div>
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
                                            <span><?= htmlspecialchars($ticket['ticket']['departure_date']) ?></span>
                                        </div>
                                        <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('return') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['return_date']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="ticket-card-detail-item">
                                            <span class="ticket-card-detail-label"><?= __('phone') ?>:</span>
                                            <span><?= htmlspecialchars($ticket['ticket']['phone']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ticket-card-right">
                                    <div class="ticket-card-price-box"><?= number_format($ticket['ticket']['sold'], 2) ?></div>
                                    <div class="ticket-card-price-meta">
                                        <div class="ticket-card-meta-dot"></div>
                                        <div class="ticket-card-meta-dot"></div>
                                        <div class="ticket-card-meta-dot"></div>
                                        <span><?= htmlspecialchars($ticket['ticket']['currency']) ?></span>
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
                            <?= __('showing') ?> <?= $startRecord ?> <?= __('to') ?> <?= $endRecord ?> <?= __('of') ?> <?= $totalRecords ?> <?= __('entries') ?? 'entries' ?>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php $sp = !empty($search) ? '&search='.urlencode($search) : ''; ?>

                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= max(1, $page - 1) . $sp ?>">
                                        <i class="feather icon-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage   = min($totalPages, $page + 2);
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1'.$sp.'">1</a></li>';
                                    if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                }
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    echo '<li class="page-item '.($page == $i ? 'active' : '').'">
                                        <a class="page-link" href="?page='.$i.$sp.'">'.$i.'</a>
                                    </li>';
                                }
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?page='.$totalPages.$sp.'">'.$totalPages.'</a></li>';
                                }
                                ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= min($totalPages, $page + 1) . $sp ?>">
                                        <i class="feather icon-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>

                </div><!-- /.pg-body -->
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container"></div>
<!-- Add a floating action button for launching the multi-ticket invoice modal -->
<div class="pg-fab" style="<?php echo is_rtl() ? 'left:20px' : 'right:20px' ?>; margin-bottom: 5px;">
    <button type="button" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>
<?php include '../includes/admin_footer.php'; ?>
<?php include '../modals/ticket_reserve/ticket_details.php'; ?>
<?php include '../modals/ticket_reserve/multi_ticket_modal.php'; ?>
<?php include '../modals/ticket_reserve/transaction_modal.php'; ?>
<?php include '../modals/ticket_reserve/book_ticket_modal.php'; ?>
<?php include '../modals/ticket_reserve/edit_ticket_modal.php'; ?>

<!-- Required JS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
<script src="../js/ticket_reserve/view_details.js"></script>
<script src="../js/ticket_reserve/bookings.js"></script>
<script src="../js/ticket_reserve/data/airlines.js"></script>
<script src="../js/ticket_reserve/airline-select.js"></script>
<script src="../js/ticket_reserve/transaction_manager.js"></script>
<script src="../js/ticket_reserve/edit_ticket_reserve.js"></script>
<script src="../js/ticket_reserve/invoice.js"></script>

<script>
const toastConfig = { duration: 4000, animationDuration: 300, maxToasts: 3 };
let activeToasts = [];

function showToast(message, type = 'success', options = {}) {
    const config = { ...toastConfig, ...options };
    const toast  = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icons = { success: 'check-circle', error: 'alert-circle', warning: 'alert-triangle', info: 'info' };
    toast.innerHTML = `
        <div class="toast-title"><i class="feather icon-${icons[type] || 'info'} mr-2"></i>${type.charAt(0).toUpperCase() + type.slice(1)}</div>
        <div class="toast-message">${message}</div>
    `;
    if (activeToasts.length >= toastConfig.maxToasts) {
        const old = activeToasts.shift();
        if (old && old.parentNode) { old.classList.add('toast-removing'); setTimeout(() => old.remove(), config.animationDuration); }
    }
    document.querySelector('.toast-container').appendChild(toast);
    activeToasts.push(toast);
    requestAnimationFrame(() => toast.classList.add('toast-showing'));
    setTimeout(() => {
        toast.classList.add('toast-removing');
        setTimeout(() => { toast.remove(); activeToasts = activeToasts.filter(t => t !== toast); }, config.animationDuration);
    }, config.duration);
    return toast;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alert-success').forEach(a => { showToast(a.textContent.trim(), 'success'); a.remove(); });
    document.querySelectorAll('.alert-danger').forEach(a  => { showToast(a.textContent.trim(), 'error');   a.remove(); });
    document.querySelectorAll('.alert-warning').forEach(a => { showToast(a.textContent.trim(), 'warning'); a.remove(); });
});

window.oldAlert = window.alert;
window.alert = function(message) { showToast(message, 'info'); };
</script>

</body>
</html>