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
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../includes/db.php');

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
    <link rel="stylesheet" href="../css/ticket/ticket_reserve_datatables.css">

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
                                                        <?php
                                                        $isAgencyClient = false;
                                                        $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                                                        $clientStmt->bindParam(1, $ticket['ticket']['sold_to'], PDO::PARAM_STR);
                                                        $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                        $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                        $clientStmt->execute();
                                                        $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($clientRow) {
                                                            $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                        }
                                                        ?>
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
                                                                        <?php if ($isAgencyClient): ?>
                                                                        <button class="dropdown-item" onclick="manageTransactions(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="fas fa-dollar-sign text-success mr-2"></i> <?= __('manage_transactions') ?>
                                                                        </button>
                                                                        <?php endif; ?>
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
                                                                $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE tenant_id = ? AND branch_id = ? AND name = ?");
                                                                $clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                                                $clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                                                $clientStmt->bindParam(3, $ticket['ticket']['sold_to'], PDO::PARAM_STR);
                                                                $clientStmt->execute();
                                                                $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                                if ($clientRow) {
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
                                                                            <?= htmlspecialchars($ticket['ticket']['return_date'] ?? '') ?>
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
    