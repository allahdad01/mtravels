<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];

// Database connection
require_once('../includes/db.php');
include '../includes/conn.php';

// Get the user ID from the session
$user_id = $_SESSION["user_id"];

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
        s.name LIKE ? OR 
        c.name LIKE ?
    )";

    // Add 7 params for search
    $like = "%$search%";
    $params = array_fill(0, 7, $like);
    $types .= str_repeat("s", 7);
}

// Main tickets query
$ticketsQuery = "
   SELECT 
    tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline, 
    tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.sold, tb.price, 
    tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status, 
    tb.trip_type, tb.return_date, tb.return_origin, tb.return_destination,
    s.name as supplier_name,
    c.name as sold_to_name,
    ma.name as paid_to_name,
    
    rt.supplier_penalty AS refund_supplier_penalty,
    rt.service_penalty AS refund_service_penalty,
    rt.refund_to_passenger,
    rt.status AS refund_status,
    rt.remarks AS refund_remarks,
    dct.departure_date AS date_change_departure_date,
    dct.currency AS date_change_currency,
    dct.supplier_penalty AS date_change_supplier_penalty,
    dct.service_penalty AS date_change_service_penalty,
    dct.status AS date_change_status,
    dct.remarks AS date_change_remarks,
    
    u.name as created_by
FROM 
    ticket_reservations tb
LEFT JOIN refunded_tickets rt ON tb.id = rt.ticket_id
LEFT JOIN date_change_tickets dct ON tb.id = dct.ticket_id
LEFT JOIN suppliers s ON tb.supplier = s.id
LEFT JOIN clients c ON tb.sold_to = c.id
LEFT JOIN main_account ma ON tb.paid_to = ma.id
LEFT JOIN users u ON tb.created_by = u.id
WHERE tb.tenant_id = ?
$searchCondition
ORDER BY tb.id DESC
LIMIT ? OFFSET ?
";

// Add pagination params
$params[] = $recordsPerPage;
$params[] = $offset;
$types   .= "ii";

// Prepare & execute
$stmt = $conn->prepare($ticketsQuery);
$stmt->bind_param($types, $tenant_id, ...$params);
$stmt->execute();
$ticketsResult = $stmt->get_result();

// ---------------- Count Query ---------------- //
$countQuery = "
    SELECT COUNT(*) as total 
    FROM ticket_reservations tb
    LEFT JOIN suppliers s ON tb.supplier = s.id
    LEFT JOIN clients c ON tb.sold_to = c.id
    WHERE tb.tenant_id = ?
    $searchCondition
";

$stmtCount = $conn->prepare($countQuery);
$stmtCount->bind_param(substr($types, 0, -2), $tenant_id, ...array_slice($params, 0, -2)); // exclude limit/offset
$stmtCount->execute();
$totalRecords = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// ---------------- Process Tickets ---------------- //
$tickets = [];
if ($ticketsResult) {
    while ($row = $ticketsResult->fetch_assoc()) {
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
    echo "Error: " . $conn->error;
}

// ---------------- Suppliers ---------------- //
$suppliersQuery = "SELECT id, name FROM suppliers WHERE status = 'active' AND tenant_id = ?";
$stmtSup = $conn->prepare($suppliersQuery);
$stmtSup->bind_param("i", $tenant_id);
$stmtSup->execute();
$suppliersResult = $stmtSup->get_result();
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);

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
    <link rel="stylesheet" href="css/ticket_styles.css">
    <link rel="stylesheet" href="css/ticket-components.css">
    <link rel="stylesheet" href="css/modal-styles.css">
    <link rel="stylesheet" href="css/ticket-form.css">
    <link rel="stylesheet" href="css/ticket_reserve_datatables.css">

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
                                        <!-- body -->
                                         <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="reservationTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" width="50">#</th>
                                                            <th width="100"><?= __('action') ?></th>
                                                            <th width="60" class="text-center"><?= __('payment') ?></th>
                                                            <th><?= __('passenger_info') ?></th>
                                                            <th><?= __('flight_details') ?></th>
                                                            <th><?= __('booking_info') ?></th>
                                                            <th class="text-right"><?= __('amount') ?></th>
                                                        </tr>
                                                    </thead>
                                                <tbody id="ticketTable">
                                                    <?php 
                                                    $counter = ($page - 1) * $recordsPerPage + 1; // Start counter based on page
                                                    foreach ($tickets as $ticket): ?>
                                                        <tr>
                                                            <td><?= $counter++ ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-secondary dropdown-toggle" type="button" id="actionDropdown<?= $ticket['ticket']['id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                        <i class="feather icon-more-vertical"></i> <?= __('actions') ?>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionDropdown<?= $ticket['ticket']['id'] ?>">
                                                                        <button class="dropdown-item view-details" data-ticket='<?= htmlspecialchars(json_encode($ticket)) ?>'>
                                                                            <i class="feather icon-eye text-primary mr-2"></i> <?= __('view_details') ?>
                                                                        </button>
                                                                        <button class="dropdown-item" onclick="editTicket(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="feather icon-edit-2 text-warning mr-2"></i> <?= __('edit') ?>
                                                                        </button>
                                                                        <button class="dropdown-item" onclick="manageTransactions(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="fas fa-dollar-sign text-success mr-2"></i> <?= __('manage_transactions') ?>
                                                                        </button>
                                                                        <div class="dropdown-divider"></div>

                                                                        <button class="dropdown-item text-danger" onclick="deleteTicket(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="feather icon-trash-2 mr-2"></i> <?= __('delete') ?>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                // Get client type from clients table
                                                                $soldTo = $ticket['ticket']['sold_to'];
                                                                $isAgencyClient = false; // Default to not agency client

                                                                // Fix: We need to query the clients table using the client name from sold_to
                                                                $clientQuery = $conn->query("SELECT client_type FROM clients WHERE tenant_id = $tenant_id AND name = '".$ticket['ticket']['sold_to']."'");
                                                                if ($clientQuery && $clientQuery->num_rows > 0) {
                                                                    $clientRow = $clientQuery->fetch_assoc();
                                                                    // Only show payment status for agency clients
                                                                    $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                }

                                                                // Only show payment status for agency clients
                                                                if ($isAgencyClient) {
                                                                    // Calculate payment status using transaction-specific exchange rates
                                                                    $baseCurrency = $ticket['ticket']['currency'];
                                                                    $soldAmount = floatval($ticket['ticket']['sold']);
                                                                    $totalPaidInBase = 0.0;

                                                                    // Get ticket ID
                                                                    $ticketId = $ticket['ticket']['id'];

                                                                    // Query transactions from main_account_transactions table
                                                                    $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE
                                                                        transaction_of = 'ticket_reserve'
                                                                        AND reference_id = '$ticketId'");

                                                                    if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                                        while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                            $amount = floatval($transaction['amount']);
                                                                            $transCurrency = $transaction['currency'];
                                                                            $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 ? floatval($transaction['exchange_rate']) : 1.0;

                                                                            $convertedAmount = 0.0;

                                                                            // Conversion logic
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

                                                                    // Status icon based on payment status
                                                                    if ($totalPaidInBase <= 0) {
                                                                        // No transactions
                                                                        echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                                    } elseif ($totalPaidInBase < $soldAmount) {
                                                                        // Partial payment
                                                                        $percentage = round(($totalPaidInBase / $soldAmount) * 100);
                                                                        echo '<i class="fas fa-circle text-warning" style="color: #ffc107 !important;"
                                                                            title="Partial payment: ' . $baseCurrency . ' ' . number_format($totalPaidInBase, 2) . ' / ' . $baseCurrency . ' ' .
                                                                            number_format($soldAmount, 2) . ' (' . $percentage . '%)"></i>';
                                                                    } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                        // Fully paid (with a small tolerance for floating-point comparison)
                                                                        echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                                    } else {
                                                                        // Overpaid
                                                                        echo '<i class="fas fa-circle text-success"
                                                                            title="Fully paid (overpaid by ' . $baseCurrency . ' ' .
                                                                            number_format($totalPaidInBase - $soldAmount, 2) . ')"></i>';
                                                                    }
                                                                } else {
                                                                    // Not an agency client - show neutral icon
                                                                    echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                                }
                                                            ?>
                                                            </td>
                                                            <td>
                                                                <div class="passenger-info">
                                                                    <div class="passenger-info__details">
                                                                        <div class="passenger-info__name">
                                                                            <?= htmlspecialchars($ticket['ticket']['passenger_name']) ?>
                                                                        </div>
                                                                        <div class="passenger-info__pnr">
                                                                            PNR: <?= htmlspecialchars($ticket['ticket']['pnr']) ?>
                                                                            <br>
                                                                            <?= __('phone') ?>: <?= htmlspecialchars($ticket['ticket']['phone']) ?>
                                                                            <br>
                                                                            <?= __('created_by') ?>: <?= htmlspecialchars($ticket['ticket']['created_by']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                
                                                            <td>
                                                                <?php if ($ticket['ticket']['trip_type'] === 'one_way'): ?>
                                                                    <div class="flight-info">
                                                                                <div class="flight-info__segment">
                                                                                    <div class="flight-info__city">
                                                                                        <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                        <?php else: ?>
                                                                    <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                                                    <div class="flight-info">
                                                                        <div class="flight-info__segment">
                                                                            <div class="flight-info__city">
                                                                                <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <?php endif; ?> 
                                                                <?php endif; ?>                                                       
                                                            </td>
                                                            <td>
                                                                <div class="booking-info">
                                                                    <div class="booking-info__details">
                                                                        <div class="booking-info__airline">
                                                                            <?= htmlspecialchars($ticket['ticket']['airline']) ?>
                                                                        </div>
                                                                        <div class="booking-info__issue-date">
                                                                            <?= htmlspecialchars($ticket['ticket']['issue_date']) ?>
                                                                        </div>
                                                                        <div class="booking-info__departure-date">
                                                                            <?= htmlspecialchars($ticket['ticket']['departure_date']) ?>
                                                                        </div>
                                                                        <div class="booking-info__return-date">
                                                                            <?= htmlspecialchars($ticket['ticket']['return_date']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__amount">
                                                                        <?= htmlspecialchars($ticket['ticket']['sold']) ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination Controls -->
                                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                                            <div class="pagination-info">
                                                <?php 
                                                $startRecord = ($page - 1) * $recordsPerPage + 1;
                                                $endRecord = min($startRecord + $recordsPerPage - 1, $totalRecords);
                                                echo sprintf(__('showing_records'), $startRecord, $endRecord, $totalRecords); 
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

<!-- Transaction Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <!-- Hotel Info Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('ticket_booking_details') ?></h6>
                                <p class="mb-1"><strong><?= __('name') ?>:</strong> <span id="trans-guest-name"></span></p>
                                <p class="mb-1"><strong><?= __('pnr') ?>:</strong> <span id="trans-order-id"></span></p>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('total_amount') ?>:</span>
                                        <strong id="totalAmount"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('exchange_rate') ?>:</span>
                                        <strong id="exchangeRateDisplay"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?= __('exchanged_amount') ?>:</span>
                                        <strong id="exchangedAmount"></strong>
                                    </div>
                                    <div id="usdSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_usd') ?>:</span>
                                            <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_usd') ?>:</span>
                                            <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_afs') ?>:</span>
                                            <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_afs') ?>:</span>
                                            <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="eurSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_eur') ?>:</span>
                                            <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_eur') ?>:</span>
                                            <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="aedSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_aed') ?>:</span>
                                            <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_aed') ?>:</span>
                                            <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                            <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('description') ?></th>
                                        <th><?= __('payment') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('exchange_rate') ?></th>
                                        <th class="text-center"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="transactionTableBody">
                                    <!-- Transactions will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

               <!-- Add Transaction Form -->
               <div id="addTransactionForm" class="collapse">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                            </div>
                            <div class="card-body">
                                <form id="hotelTransactionForm">
                                    <input type="hidden" id="booking_id" name="booking_id">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentDate">
                                                    <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                                </label>
                                                <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentTime">
                                                    <i class="feather icon-clock mr-1"></i><?= __('payment_time') ?>
                                                </label>
                                                <input type="time" class="form-control" id="paymentTime" name="payment_time" step="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentAmount">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                                </label>
                                                <input type="number" class="form-control" id="paymentAmount" 
                                                    name="payment_amount" step="0.01" min="0.01" required 
                                                    placeholder="Enter amount">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentCurrency">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                                </label>
                                                <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                                                    <option value=""><?= __('select_currency') ?></option>
                                                    <option value="USD"><?= __('usd') ?></option>
                                                    <option value="AFS"><?= __('afs') ?></option>
                                                    <option value="EUR"><?= __('eur') ?></option>
                                                    <option value="DARHAM"><?= __('darham') ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
   
                                    <div class="form-group" id="exchangeRateField" style="display: none;">
                                        <label for="transactionExchangeRate">
                                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                        </label>
                                        <input type="number" class="form-control" id="transactionExchangeRate"
                                            name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                    </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="paymentDescription">
                                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                        </label>
                                        <textarea class="form-control" id="paymentDescription" 
                                                name="payment_description" rows="2" required
                                                placeholder="Enter payment description"></textarea>
                                    </div>

                                    <div class="text-right mt-3">
                                        <button type="button" class="btn btn-secondary" data-toggle="collapse" 
                                                data-target="#addTransactionForm">
                                            <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

  
  
<!-- Multiple Ticket Invoice Modal -->
<div class="modal fade" id="multiTicketInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_combined_invoice') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="feather icon-info mr-2"></i><?= __('select_multiple_tickets_to_generate_a_combined_invoice') ?>
                </div>
                
                <form id="multiTicketInvoiceForm">
                <div class="form-group">
                        <label for="clientFilter"><?= __('filter_by_client') ?></label>
                        <select class="form-control" id="clientFilter" name="clientFilter">
                            <option value=""><?= __('all_clients') ?></option>
                            <?php
                            // Fetch clients from database
                            $clientQuery = "SELECT DISTINCT c.name FROM clients c 
                                          INNER JOIN ticket_reservations tr ON c.id = tr.sold_to 
                                          WHERE tr.tenant_id = $tenant_id
                                          ORDER BY c.name ASC";
                            $clientResult = $conn->query($clientQuery);
                            
                            if ($clientResult && $clientResult->num_rows > 0) {
                                while ($client = $clientResult->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($client['name']) . '">' . 
                                         htmlspecialchars($client['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="clientForInvoice"><?= __('client') ?></label>
                        
                        <input type="text" class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceComment"><?= __('comments_notes') ?></label>
                        <textarea class="form-control" id="invoiceComment" name="invoiceComment" rows="2"></textarea>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="ticketSelectionTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="selectAllTickets">
                                            <label class="custom-control-label" for="selectAllTickets"></label>
                                        </div>
                                    </th>
                                    <th><?= __('client') ?></th>
                                    <th><?= __('passenger') ?></th>
                                    <th><?= __('pnr') ?></th>
                                    <th><?= __('sector') ?></th>
                                    <th><?= __('flight') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('amount') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ticketsForInvoiceBody">
                                <!-- Tickets will be loaded here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="7" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                    <td id="invoiceTotal" class="font-weight-bold">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="invoiceCurrency"><?= __('currency') ?></label>
                        <select class="form-control" id="invoiceCurrency" name="invoiceCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <button type="button" class="btn btn-primary" id="generateCombinedInvoice">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_invoice') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add a floating action button for launching the multi-ticket invoice modal -->
<div id="floatingActionButton" class="position-fixed" style="bottom: 80px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>
<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
                                   <!-- Ticket details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                        <i class="feather icon-clipboard mr-2"></i><?= __('ticket_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1"><?= __('sold_price') ?></div>
                            <h4 class="mb-0 text-primary" id="sold-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1"><?= __('base_price') ?></div>
                            <h4 class="mb-0 text-info" id="base-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1"><?= __('profit') ?></div>
                            <h4 class="mb-0 text-success" id="profit">-</h4>
                        </div>
                     
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                            <i class="feather icon-info mr-2"></i><?= __('summary') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description" role="tab">
                            <i class="feather icon-file-text mr-2"></i><?= __('description') ?>
                        </a>
                    </li>
                    
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted"><?= __('client_information') ?></h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('passenger_name') ?></span>
                                            <strong id="passenger-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('pnr') ?></span>
                                            <strong id="pnr">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('supplier') ?></span>
                                            <strong id="supplier-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('sold_to') ?></span>
                                            <strong id="sold-to">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted"><?= __('paid_to') ?></span>
                                            <strong id="paid-to">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted"><?= __('additional_details') ?></h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('currency') ?></span>
                                            <strong id="currency">-</strong>
                                        </div>
                                       
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted"><?= __('phone') ?></span>
                                            <strong id="phone">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted"><?= __('gender') ?></span>
                                            <strong id="gender">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Tab -->
                    <div class="tab-pane fade" id="details-description" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p id="description" class="mb-0">-</p>
                            </div>
                        </div>
                    </div>

                   
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('close') ?>
                </button>
                
            </div>
        </div>
    </div>
</div>

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



                                <!-- Book Ticket Modal -->
                                <div class="modal fade" id="bookTicketModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5><?= __('reserve_ticket') ?></h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <form id="bookTicketForm">
                                                <div class="modal-body">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="supplier"><?= __('supplier') ?></label>
                                                            <select class="form-control selectpicker" id="supplier" name="supplier" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <option value=""><?= __('select_supplier') ?></option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                         
                                                        <div class="form-group col-md-3">
                                                            <label for="soldTo"><?= __('sold_to') ?></label>
                                                            <select class="form-control selectpicker" id="soldTo" name="soldTo" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <option value=""><?= __('select_client') ?></option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']}
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="tripType"><?= __('trip_type') ?></label>
                                                            <select class="form-control selectpicker" id="tripType" name="tripType" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <option value="one_way"><?= __('one_way') ?></option>
                                                                <option value="round_trip"><?= __('round_trip') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="title"><?= __('title') ?></label>
                                                            <select class="form-control" id="title" name="title" required>
                                                                <option value="Mr"><?= __('mr') ?></option>
                                                                <option value="Mrs"><?= __('mrs') ?></option>
                                                                <option value="Child"><?= __('child') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="gender"><?= __('gender') ?></label>
                                                            <select class="form-control" id="gender" name="gender" required>
                                                                <option value="Male"><?= __('male') ?></option>
                                                                <option value="Female"><?= __('female') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="passengerName"><?= __('passenger_name') ?></label>
                                                            <input type="text" class="form-control" id="passengerName" name="passengerName" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="pnr"><?= __('pnr') ?></label>
                                                            <input type="text" class="form-control" id="pnr" name="pnr" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="phone"><?= __('phone') ?></label>
                                                            <input type="text" class="form-control" id="phone" name="phone" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="origin"><?= __('from') ?></label>
                                                            <input type="text" class="form-control" id="origin" name="origin" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="destination"><?= __('to') ?></label>
                                                            <input type="text" class="form-control" id="destination" name="destination" required>
                                                        </div>
                                                        <div id="returnJourneyFields" class="form-group col-md-3" style="display: none;">
                                                            <label for="returnDestination"><?= __('return_to') ?></label>
                                                            <input type="text" class="form-control" id="returnDestination" name="returnDestination">
                                                        </div>
                                                        
                                                            <div class="form-group col-md-3">
                                                                <label for="airline">
                                                                    <i class="feather icon-plane mr-1"></i><?= __('airline') ?>
                                                                </label>
                                                                <select class="form-control selectpicker" id="airline" name="airline" required 
                                                                    data-live-search="true" data-style="btn-light">
                                                                    <!-- Airlines will be populated by JavaScript -->
                                                                </select>
                                                            </div>
                                                        </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="issueDate"><?= __('issue_date') ?></label>
                                                            <input type="date" class="form-control" id="issueDate" name="issueDate" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="departureDate"><?= __('departure_date') ?></label>
                                                            <input type="date" class="form-control" id="departureDate" name="departureDate" required>
                                                        </div>
                                                        <div id="returnDateField" class="form-group col-md-3" style="display: none;">
                                                            <label for="returnDate"><?= __('return_date') ?></label>
                                                            <input type="date" class="form-control" id="returnDate" name="returnDate">
                                                        </div>
                                                        <div class="form-group col-md-3" id="baseFieldContainer">
                                                            <label for="base"><?= __('base') ?></label>
                                                            <input type="number" class="form-control" id="base" name="base" step="any" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="sold"><?= __('sold') ?></label>
                                                            <input type="number" class="form-control" id="sold" name="sold" step="any" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="profit"><?= __('profit') ?></label>
                                                            <input type="number" class="form-control" id="pro" name="pro" step="any" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="curr"><?= __('currency') ?></label>
                                                            <input class="form-control" id="curr" name="curr" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="description"><?= __('description') ?></label>
                                                            <input type="text" class="form-control" id="description" name="description" required>
                                                        </div>
                                                    </div>
                                                    
                                                                                   
                                                    <div class="form-row">
                                                        <div class="form-group col-md-4">
                                                            <label for="paidTo"><?= __('paid_to') ?></label>
                                                            <select class="form-control" id="paidTo" name="paidTo" required>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']}
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                    <button type="submit" class="btn btn-primary"><?= __('book') ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                               <!-- Profile Modal -->
   <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>


                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>


                           
                             
                                <!-- Edit ticket tab -->
                                    <div class="modal fade" id="editTicketModal" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div id="editLoader" style="display: none; text-align: center;">
                                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...
                                            </div>

                                            <form id="editTicketForm">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?= __('edit_ticket') ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" id="editTicketId" name="id">
                                                       <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="supplier"><?= __('supplier') ?></label>
                                                            <select class="form-control" id="editSupplier" name="supplier" required readonly>
                                                                <option value=""><?= __('select_supplier') ?></option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editSoldTo"><?= __('sold_to') ?></label>
                                                            <select class="form-control" id="editSoldTo" name="soldTo" required readonly>
                                                                <option value=""><?= __('select_client') ?></option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']}
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editTripType"><?= __('trip_type') ?></label>
                                                            <select class="form-control" id="editTripType" name="tripType" required>
                                                                <option value="one_way"><?= __('one_way') ?></option>
                                                                <option value="round_trip"><?= __('round_trip') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editTitle"><?= __('title') ?></label>
                                                            <select class="form-control" id="editTitle" name="title" required>
                                                                <option value="Mr"><?= __('mr') ?></option>
                                                                <option value="Mrs"><?= __('mrs') ?></option>
                                                                <option value="Child"><?= __('child') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editGender"><?= __('gender') ?></label>
                                                            <select class="form-control" id="editGender" name="gender" required>
                                                                <option value="Male"><?= __('male') ?></option>
                                                                <option value="Female"><?= __('female') ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPassengerName"><?= __('passenger_name') ?></label>
                                                            <input type="text" class="form-control" id="editPassengerName" name="passengerName" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPhone"><?= __('phone') ?></label>
                                                            <input type="text" class="form-control" id="editPhone" name="phone" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPnr"><?= __('pnr') ?></label>
                                                            <input type="text" class="form-control" id="editPnr" name="pnr" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editOrigin"><?= __('from') ?></label>
                                                            <input type="text" class="form-control" id="editOrigin" name="origin" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editDestination"><?= __('to') ?></label>
                                                            <input type="text" class="form-control" id="editDestination" name="destination" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editAirline"><?= __('airline') ?></label>
                                                            <select class="form-control selectpicker" id="editAirline" name="airline" required 
                                                                data-live-search="true" data-style="btn-light">
                                                                <!-- Airline options go here -->
                                                                
                                                            </select>
                                                        </div>
                                                        <div id="editReturnJourneyFields" style="display: none;">
                                                            <div class="form-group col-md-8">
                                                                <label for="editReturnDestination"><?= __('return_to') ?></label>
                                                                <input type="text" class="form-control" id="editReturnDestination" name="returnDestination">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editIssueDate"><?= __('issue_date') ?></label>
                                                            <input type="date" class="form-control" id="editIssueDate" name="issueDate" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editDepartureDate"><?= __('departure_date') ?></label>
                                                            <input type="date" class="form-control" id="editDepartureDate" name="departureDate" required>
                                                        </div>
                                                        <div id="editReturnDateField" class="form-group col-md-3" style="display: none;">
                                                            <label for="editReturnDate"><?= __('return_date') ?></label>
                                                            <input type="date" class="form-control" id="editReturnDate" name="returnDate">
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editBase"><?= __('base') ?></label>
                                                            <input type="number" class="form-control" id="editBase" name="base" step="any" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editSold"><?= __('sold') ?></label>
                                                            <input type="number" class="form-control" id="editSold" name="sold" step="any" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPro"><?= __('profit') ?></label>
                                                            <input type="number" class="form-control" id="editPro" name="pro" step="any" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editCurr"><?= __('currency') ?></label>
                                                            <input class="form-control" id="editCurr" name="curr" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPaidTo"><?= __('paid_to') ?></label>
                                                            <select class="form-control" id="editPaidTo" name="paidTo" required readonly>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']}
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-12">
                                                            <label for="editDescription"><?= __('description') ?></label>
                                                            <input type="text" class="form-control" id="editDescription" name="description" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>





                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>
                                    <!-- view ticket details -->
                                    <script src="js/ticket_reserve/view_details.js"></script>
                                    <script src="js/ticket_reserve/bookings.js"></script>
                                    <script src="js/ticket_reserve/data/airlines.js"></script>
                                    <script src="js/ticket_reserve/airline-select.js"></script>
                                    <script src="js/ticket_reserve/transaction_manager.js"></script>
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
                                    <script src="js/ticket_reserve/edit_ticket_reserve.js"></script>
                                    <!-- Add script for multiple ticket invoice functionality -->
                                    <script src="js/ticket_reserve/invoice.js"></script>
                                    



<style>
    /* Add styles for the floating button */
    #floatingActionButton {
        right: 30px;
    }
    
    /* RTL support - position on left side instead */
    html[dir="rtl"] #floatingActionButton {
        right: auto;
        left: 30px;
    }
    
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
    
    <script>
        $(document).ready(function() {
            // Initialize Bootstrap Select for supplier dropdown
            $('#supplier').selectpicker({
                style: 'btn-light',
                size: 4,
                liveSearch: true
            });

            // Initialize Bootstrap Select for soldTo dropdown
            $('#soldTo').selectpicker({
                style: 'btn-light',
                size: 4,
                liveSearch: true
            });

            // Initialize Bootstrap Select for tripType dropdown
            $('#tripType').selectpicker({
                style: 'btn-light',
                size: 4
            });
        });
    </script>
    
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
    