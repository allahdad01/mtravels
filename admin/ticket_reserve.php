<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../includes/db.php');

// Get the user ID from the session
$user_id = $_SESSION["user_id"];

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 10; // Number of records per page
$offset = ($page - 1) * $recordsPerPage;

// ---------------- Tickets Query ---------------- //
$searchCondition = '';
$params = [];
$types  = "i"; // first param = tenant_id (integer)

if ($search) {
    $searchCondition = "AND (
        tb.passenger_name LIKE ? OR 
        tb.pnr LIKE ? OR 
        tb.airline LIKE ? OR 
        tb.origin LIKE ? OR 
        tb.destination LIKE ? OR 
        tb.supplier LIKE ? OR 
        c.name LIKE ?
    )";

    // Add 7 params for search
    $like = "%$search%";
    $params = array_fill(0, 7, $like);
    $types .= str_repeat("s", 7);
}

// Store search params count before adding pagination
$searchParamCount = count($params);

// Main tickets query
$ticketsQuery = "
   SELECT 
    tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline, 
    tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.sold, tb.price, 
    tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status, 
    tb.trip_type, tb.return_date, tb.return_origin, tb.return_destination,
    tb.supplier as supplier_name,
    c.name as sold_to_name,
    ma.name as paid_to_name,
    u.name as created_by
FROM 
    ticket_reservations tb
LEFT JOIN clients c ON tb.sold_to = c.id
LEFT JOIN main_account ma ON tb.paid_to = ma.id
LEFT JOIN users u ON tb.created_by = u.id
WHERE tb.tenant_id = ? AND tb.branch_id = ?
$searchCondition
ORDER BY tb.id DESC
LIMIT ? OFFSET ?
";

// Prepare & execute
$stmt = $pdo->prepare($ticketsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);

// Bind search parameters if any
$paramIndex = 3;
for ($i = 0; $i < $searchParamCount; $i++) {
    $stmt->bindParam($paramIndex++, $params[$i], PDO::PARAM_STR);
}

// Bind pagination parameters after search params
$stmt->bindParam($paramIndex++, $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam($paramIndex++, $offset, PDO::PARAM_INT);

$stmt->execute();
$ticketsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------- Count Query ---------------- //
$countQuery = "
    SELECT COUNT(*) as total
    FROM ticket_reservations tb
    LEFT JOIN clients c ON tb.sold_to = c.id
    WHERE tb.tenant_id = ? AND tb.branch_id = ?
    $searchCondition
";

$stmtCount = $pdo->prepare($countQuery);
$stmtCount->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtCount->bindParam(2, $branch_id, PDO::PARAM_INT);

// Bind search parameters for count query
$countParamIndex = 3;
for ($i = 0; $i < $searchParamCount; $i++) {
    $stmtCount->bindParam($countParamIndex++, $params[$i], PDO::PARAM_STR);
}
$stmtCount->execute();
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// ---------------- Process Tickets ---------------- //
$tickets = [];
if ($ticketsResult) {
    foreach ($ticketsResult as $row) {
        $ticket_id = $row['id'];
        if (!isset($tickets[$ticket_id])) {
            $tickets[$ticket_id] = [
                'ticket' => [
                    'id' => $row['id'],
                    'supplier_name' => $row['supplier_name'],
                    'sold_to' => $row['sold_to_name'],
                    'paid_to' => $row['paid_to_name'],
                    'title' => $row['title'],
                    'passenger_name' => $row['passenger_name'],
                    'pnr' => $row['pnr'],
                    'airline' => $row['airline'],
                    'origin' => $row['origin'],
                    'destination' => $row['destination'],
                    'issue_date' => $row['issue_date'],
                    'departure_date' => $row['departure_date'],
                    'sold' => $row['sold'],
                    'price' => $row['price'],
                    'profit' => $row['profit'],
                    'gender' => $row['gender'],
                    'currency' => $row['currency'],
                    'phone' => $row['phone'],
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'trip_type' => $row['trip_type'],
                    'return_date' => $row['return_date'],
                    'return_origin' => $row['return_origin'],
                    'return_destination' => $row['return_destination'],
                    'created_by' => $row['created_by']
                ]
            ];
        }
    }
} else {
    $tickets = [];
}

// ---------------- Suppliers ---------------- //
$suppliersQuery = "SELECT id, name FROM suppliers WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";
$stmtSup = $pdo->prepare($suppliersQuery);
$stmtSup->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtSup->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmtSup->execute();
$suppliers = $stmtSup->fetchAll(PDO::FETCH_ASSOC);

// Create an associative array of supplier id to supplier name
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}
?>



    <?php include '../includes/header.php'; ?>
    <!-- Add Bootstrap-select CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
    
    <!-- Existing CSS links -->
    <link rel="stylesheet" href="../css/ticket/ticket_styles.css">
    <link rel="stylesheet" href="../css/ticket/ticket-components.css">
    <link rel="stylesheet" href="../css/general/modal-styles.css">
    <link rel="stylesheet" href="../css/ticket/ticket-form.css">

    <style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}

/* Ticket Card Styles - Similar to ticket.php */
.ticket-card-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ticket-card {
    display: grid;
    grid-template-columns: 1fr 140px;
    border-radius: 10px;
    overflow: hidden;
    background: #e8edf2;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.ticket-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.ticket-card-main {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    padding: 18px 22px;
    position: relative;
}

.ticket-card-main::after {
    content: '';
    position: absolute;
    right: 0;
    top: 10%;
    height: 80%;
    border-right: 2px dashed rgba(255,255,255,0.5);
}

.ticket-card-main.status-paid {
    background: #8db87a;
}

.ticket-card-main.status-partial {
    background: #d4a574;
}

.ticket-card-main.status-unpaid {
    background: #e07a7a;
}

.ticket-card-main.status-neutral {
    background: #6b8fb3;
}

.ticket-card-left {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ticket-card-header {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.ticket-card-status-dots {
    display: flex;
    gap: 6px;
    align-items: center;
    padding-top: 2px;
}

.ticket-card-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,0.7);
}

.ticket-card-dot.primary {
    width: 10px;
    height: 10px;
    background: rgba(255,255,255,0.9);
}

.ticket-card-title {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.5px;
    line-height: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ticket-card-title span {
    display: block;
    width: 24px;
    height: 2px;
    background: rgba(255,255,255,0.8);
}

.ticket-card-id {
    font-size: 11px;
    color: rgba(255,255,255,0.85);
    font-weight: 500;
}

.ticket-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 16px;
    color: rgba(255,255,255,0.9);
    font-size: 12px;
    margin-top: 8px;
}

.ticket-card-detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ticket-card-detail-label {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    opacity: 0.8;
    min-width: fit-content;
}

.ticket-card-right {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ticket-card-price-box {
    background: #fff;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 28px;
    font-weight: 700;
    color: #2d3f52;
    letter-spacing: -0.5px;
    text-align: center;
}

.ticket-card-price-meta {
    display: flex;
    gap: 4px;
    align-items: center;
    font-size: 9px;
    color: rgba(255,255,255,0.75);
}

.ticket-card-meta-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(255,255,255,0.7);
}

.ticket-card-stub {
    background: #e2e8ed;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12px 8px;
    gap: 6px;
}

.ticket-card-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.ticket-card-action-btn {
    background: #4099ff;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.ticket-card-action-btn:hover {
    background: #2979d0;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(64, 153, 255, 0.3);
}

.ticket-card-action-btn i {
    font-size: 12px;
}
</style>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('ticket_reservations') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('ticket_reservations') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="mb-3 text-right d-flex justify-content-between align-items-center">
                                         <form class="form-inline flex-grow-1 mr-3" method="get">
                                             <div class="input-group w-100">
                                                 <input type="search" 
                                                        class="form-control" 
                                                        placeholder="<?= __('search_tickets') ?>" 
                                                        name="search" 
                                                        value="<?= htmlspecialchars($search) ?>"
                                                        aria-label="Search tickets">
                                                 <div class="input-group-append">
                                                     <button class="btn btn-primary" type="submit">
                                                         <i class="feather icon-search"></i>
                                                     </button>
                                                     <?php if (!empty($search)): ?>
                                                         <a href="ticket_reserve.php" class="btn btn-secondary">
                                                             <i class="feather icon-x"></i>
                                                         </a>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                         </form>
                                         <button class="btn btn-primary" data-toggle="modal" data-target="#bookTicketModal"><?= __('reserve_ticket') ?></button>
                                    </div>
                                    <div class="card">
                                        <!-- Card body with ticket cards -->
                                        <div class="ticket-card-container">
                                            <?php 
                                            $counter = ($page - 1) * $recordsPerPage + 1; // Start counter based on page
                                            foreach ($tickets as $ticket): ?>
                                                <?php
                                                $isAgencyClient = false;
                                                $paymentStatus = 'neutral';
                                                $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                                                $clientStmt->bindParam(1, $ticket['ticket']['sold_to'], PDO::PARAM_STR);
                                                $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                $clientStmt->execute();
                                                $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                if ($clientRow) {
                                                    $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                }
                                                
                                                // Calculate payment status for agency clients
                                                if ($isAgencyClient) {
                                                    $baseCurrency = $ticket['ticket']['currency'];
                                                    $soldAmount = floatval($ticket['ticket']['sold']);
                                                    $totalPaidInBase = 0.0;
                                                    $ticketId = $ticket['ticket']['id'];

                                                    $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                                        transaction_of = 'ticket_reserve'
                                                        AND reference_id = ?");
                                                    $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
                                                    $transactionStmt->execute();
                                                    $transactionQuery = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                                    if ($transactionQuery && count($transactionQuery) > 0) {
                                                        foreach ($transactionQuery as $transaction) {
                                                            $amount = floatval($transaction['amount']);
                                                            $transCurrency = $transaction['currency'];
                                                            $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 ? floatval($transaction['exchange_rate']) : 1.0;

                                                            $convertedAmount = 0.0;

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

                                                    if ($totalPaidInBase <= 0) {
                                                        $paymentStatus = 'unpaid';
                                                    } elseif ($totalPaidInBase < $soldAmount) {
                                                        $paymentStatus = 'partial';
                                                    } else {
                                                        $paymentStatus = 'paid';
                                                    }
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
                                                                    RESERVE
                                                                    <span></span>
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
                                                        <div class="ticket-card-actions" style="display: flex; flex-direction: column; gap: 6px;">
                                                            <button class="ticket-card-action-btn view-details" data-ticket='<?= json_encode($ticket) ?>' title="<?= __('view_details') ?>" style="width: 100%;">
                                                                <i class="feather icon-eye"></i>
                                                            </button>
                                                            <?php if ($canEdit): ?>
                                                            <button class="ticket-card-action-btn" onclick="editTicket(<?= $ticket['ticket']['id'] ?>)" title="<?= __('edit') ?>" style="width: 100%;">
                                                                <i class="feather icon-edit-2"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <?php if ($isAgencyClient && $canEdit): ?>
                                                            <button class="ticket-card-action-btn" onclick="manageTransactions(<?= $ticket['ticket']['id'] ?>)" title="<?= __('manage_transactions') ?>" style="width: 100%;">
                                                                <i class="fas fa-dollar-sign"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <?php if ($canEdit): ?>
                                                            <button class="ticket-card-action-btn" onclick="deleteTicket(<?= $ticket['ticket']['id'] ?>)" title="<?= __('delete') ?>" style="width: 100%;">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Pagination Controls -->
                                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                                             <div class="pagination-info">
                                                 <?php 
                                                 $startRecord = ($page - 1) * $recordsPerPage + 1;
                                                 $endRecord = min($startRecord + $recordsPerPage - 1, $totalRecords);
                                                 echo "Showing " . $startRecord . " to " . $endRecord . " of " . $totalRecords . " entries"; 
                                                 ?>
                                             </div>
                                            <nav aria-label="Ticket reservations pagination">
                                                <ul class="pagination mb-0">
                                                    <?php 
                                                    // Prepare search parameter for pagination links
                                                    $searchParam = !empty($search) ? '&search=' . urlencode($search) : '';
                                                    ?>
                                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?page=<?= max(1, $page - 1) . $searchParam ?>" aria-label="<?= __('previous') ?>">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                    <?php 
                                                    // Show up to 5 page numbers around current page
                                                    $startPage = max(1, $page - 2);
                                                    $endPage = min($totalPages, $page + 2);
                                                    
                                                    // Always show first page if not in range
                                                    if ($startPage > 1) {
                                                        echo '<li class="page-item ' . ($page == 1 ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=1' . $searchParam . '">1</a>
                                                              </li>';
                                                        if ($startPage > 2) {
                                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                        }
                                                    }
                                                    
                                                    // Page numbers
                                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=' . $i . $searchParam . '">' . $i . '</a>
                                                              </li>';
                                                    }
                                                    
                                                    // Always show last page if not in range
                                                    if ($endPage < $totalPages) {
                                                        if ($endPage < $totalPages - 1) {
                                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                        }
                                                        echo '<li class="page-item ' . ($page == $totalPages ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=' . $totalPages . $searchParam . '">' . $totalPages . '</a>
                                                              </li>';
                                                    }
                                                    ?>
                                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?page=<?= min($totalPages, $page + 1) . $searchParam ?>" aria-label="<?= __('next') ?>">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Toast Container -->
<div class="toast-container"></div>

<style>
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
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        margin-bottom: 10px;
        overflow: hidden;
        opacity: 0;
        transform: translateX(40px);
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        padding: 15px;
    }

    .toast-showing {
        opacity: 1;
        transform: translateX(0);
    }

    .toast-removing {
        opacity: 0;
        transform: translateY(-20px);
    }

    .toast-success {
        border-left-color: #10b981;
    }

    .toast-error {
        border-left-color: #ef4444;
    }

    .toast-warning {
        border-left-color: #f59e0b;
    }

    .toast-info {
        border-left-color: #3b82f6;
    }

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
</style>


<?php include '../modals/ticket_reserve/ticket_details.php'; ?>    
<?php include '../modals/ticket_reserve/transaction_modal.php'; ?>
    <?php include '../modals/ticket_reserve/book_ticket_modal.php'; ?>
    <?php include '../modals/ticket_reserve/edit_ticket_modal.php'; ?>

    <?php include '../modals/ticket_reserve/multi_ticket_modal.php'; ?>  
  

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
      

<style>
            /* Modal Styles */
            .modal-content {
                border-radius: 0.5rem;
            }

            .modal-header {
                padding: 1.25rem;
            }

            .nav-pills .nav-link {
                border-radius: 0.25rem;
                transition: all 0.3s;
                color: #6c757d;
            }

            .nav-pills .nav-link.active {
                background-color: #4099ff;
                color: white;
            }

            .nav-pills .nav-link:hover:not(.active) {
                background-color: #e9ecef;
            }

            .card {
                transition: transform 0.2s;
            }


            .badge-pill {
                padding: 0.5em 1em;
            }

            /* Responsive Adjustments */
            @media (max-width: 768px) {
                .modal-dialog {
                    margin: 0.5rem;
                }
                
                .nav-pills {
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    padding: 1rem;
                }
                
                .nav-pills .nav-link {
                    white-space: nowrap;
                }
            }
            
</style>


                      
                             





                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>
                                    <!-- view ticket details -->
                                    <script src="../js/ticket_reserve/view_details.js"></script>
                                    <script src="../js/ticket_reserve/bookings.js"></script>
                                    <script src="../js/ticket_reserve/data/airlines.js"></script>
                                    <script src="../js/ticket_reserve/airline-select.js"></script>
                                    <script src="../js/ticket_reserve/transaction_manager.js"></script>
                                    <!-- Include toast notification system -->
                                    <script>
                                    // Toast notification system
                                    const toastConfig = {
                                        duration: 4000,      // Display duration in ms
                                        animationDuration: 300,  // Animation duration in ms
                                        maxToasts: 3        // Maximum number of toasts to show at once
                                    };

                                    // Collection to track active toasts
                                    let activeToasts = [];

                                    /**
                                     * Show a toast notification
                                     * @param {string} message - The message to display
                                     * @param {string} type - Type of toast (success, error, warning, info)
                                     * @param {object} options - Optional configuration overrides
                                     */
                                    function showToast(message, type = 'success', options = {}) {
                                        const config = { ...toastConfig, ...options };

                                        // Create the toast element
                                        const toast = document.createElement('div');
                                        toast.className = `toast toast-${type}`;

                                        // Set icon based on type
                                        let icon = 'check-circle';
                                        switch(type) {
                                            case 'error':
                                                icon = 'alert-circle';
                                                break;
                                            case 'warning':
                                                icon = 'alert-triangle';
                                                break;
                                            case 'info':
                                                icon = 'info';
                                                break;
                                        }

                                        // Set toast content
                                        toast.innerHTML = `
                                            <div class="toast-title">
                                                <i class="feather icon-${icon} mr-2"></i>
                                                ${type.charAt(0).toUpperCase() + type.slice(1)}
                                            </div>
                                            <div class="toast-message">${message}</div>
                                        `;

                                        // Manage toast collection
                                        if (activeToasts.length >= toastConfig.maxToasts) {
                                            const oldestToast = activeToasts.shift();
                                            if (oldestToast && oldestToast.parentNode) {
                                                oldestToast.classList.add('toast-removing');
                                                setTimeout(() => oldestToast.remove(), config.animationDuration);
                                            }
                                        }

                                        // Add toast to container
                                        const container = document.querySelector('.toast-container');
                                        container.appendChild(toast);
                                        activeToasts.push(toast);

                                        // Trigger animation
                                        requestAnimationFrame(() => toast.classList.add('toast-showing'));

                                        // Auto dismiss
                                        setTimeout(() => {
                                            toast.classList.add('toast-removing');
                                            setTimeout(() => {
                                                toast.remove();
                                                activeToasts = activeToasts.filter(t => t !== toast);
                                            }, config.animationDuration);
                                        }, config.duration);

                                        return toast;
                                    }

                                    // Convert all alerts to toasts
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Success alerts
                                        document.querySelectorAll('.alert-success').forEach(alert => {
                                            const message = alert.textContent.trim();
                                            showToast(message, 'success');
                                            alert.remove();
                                        });

                                        // Error alerts
                                        document.querySelectorAll('.alert-danger').forEach(alert => {
                                            const message = alert.textContent.trim();
                                            showToast(message, 'error');
                                            alert.remove();
                                        });

                                        // Warning alerts
                                        document.querySelectorAll('.alert-warning').forEach(alert => {
                                            const message = alert.textContent.trim();
                                            showToast(message, 'warning');
                                            alert.remove();
                                        });
                                    });

                                    // Replace all existing alert() calls with toast notifications
                                    window.oldAlert = window.alert;
                                    window.alert = function(message) {
                                        showToast(message, 'info');
                                    };
                                    </script>
                                    <script src="../js/ticket_reserve/edit_ticket_reserve.js"></script>
                                    <!-- Add script for multiple ticket invoice functionality -->
                                    <script src="../js/ticket_reserve/invoice.js"></script>
                                    



<style>
    
    .position-fixed .btn-lg {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }

    .position-fixed .btn-lg:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.3);
    }

    .position-fixed .btn-lg i {
        font-size: 24px;
    }
</style>




</body>
</html>

    <!-- Add Bootstrap-select JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
    
    
    <style>
        /* Existing styles... */

        /* Pagination Styles */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .pagination .page-item {
            margin: 0 2px;
        }

        .pagination .page-link {
            color: #4099ff;
            background-color: #fff;
            border: 1px solid #dee2e6;
            padding: 0.5rem 0.75rem;
            line-height: 1.25;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination .page-item.active .page-link {
            background-color: #4099ff;
            border-color: #4099ff;
            color: white;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .pagination .page-link:hover:not(.disabled) {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 0.875rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 576px) {
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }

            .pagination .page-item {
                margin: 2px;
            }

            .pagination-info {
                text-align: center;
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
    