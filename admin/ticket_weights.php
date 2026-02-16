<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

// Database connection
require_once('../includes/db.php');

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " AND (
        t.passenger_name LIKE ? OR
        t.pnr LIKE ? OR
        t.phone LIKE ? OR
        c.name LIKE ? OR
        t.airline LIKE ? OR
        t.origin LIKE ? OR
        t.destination LIKE ?
    )";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM ticket_weights tw 
              LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
              LEFT JOIN clients c ON t.sold_to = c.id
              WHERE tw.tenant_id = ? AND tw.branch_id = ?" . $search_condition;
$countParams = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $countParams = array_merge($countParams, array_fill(0, 7, $search_param));
}
$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Query to fetch ticket weights with related information
$weightsQuery = "
    SELECT
        tw.*,
        t.passenger_name,
        t.pnr,
        t.phone,
        t.airline,
        t.origin,
        t.destination,
        t.departure_date,
        t.currency,
        s.name AS supplier_name,
        c.name AS sold_to_name
    FROM
        ticket_weights tw
    LEFT JOIN
        ticket_bookings t ON tw.ticket_id = t.id
    LEFT JOIN
        suppliers s ON t.supplier = s.id
    LEFT JOIN
        clients c ON t.sold_to = c.id
    WHERE
        tw.tenant_id = ? AND tw.branch_id = ?" . $search_condition . "
    ORDER BY
        tw.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($weightsQuery);
$params = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $params = array_merge($params, array_fill(0, 7, $search_param));
}
$params[] = $items_per_page;
$params[] = $offset;
$stmt->execute($params);

// Initialize the array to hold weight details
$weights = [];

$weightsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($weightsResult && count($weightsResult) > 0) {
    $weights = $weightsResult;
}

?>


    <?php include '../includes/header.php'; ?>
    <!-- DataTables CSS removed -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="../css/ticket/ticket_styles.css">
    <link rel="stylesheet" href="../css/ticket/ticket-components.css">
    <link rel="stylesheet" href="../css/general/modal-styles.css">
    <link rel="stylesheet" href="../css/ticket/ticket-form.css">
    <link rel="stylesheet" href="../css/ticket/ticket_weight.css">

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- [ Table ] start -->
                                <div class="col-sm-12">
                                    <div class="card">
                                         <div class="card-header">
                                             <div class="row align-items-center">
                                                 <div class="col">
                                                     <h5><?= __('ticket_weights_management') ?></h5>
                                                 </div>
                                                 <div class="col text-right">
                                                      <button type="button" class="btn btn-success" id="generateInvoiceBtn" style="display: none;">
                                                          <i class="feather icon-file-text mr-2"></i>Generate Invoice
                                                      </button>
                                                      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTransactionModal">
                                                          <i class="feather icon-plus mr-2"></i><?= __('add_weight') ?>
                                                      </button>
                                                  </div>
                                             </div>
                                         </div>

                                         <!-- Search Bar -->
                                         <div class="card-body border-bottom pb-3">
                                             <form method="GET" class="form-inline">
                                                 <div class="form-group mb-0 flex-grow-1">
                                                     <input 
                                                         type="text" 
                                                         name="search" 
                                                         class="form-control w-100" 
                                                         placeholder="Search by passenger name, PNR, phone, airline, city..." 
                                                         value="<?= htmlspecialchars($search_query) ?>"
                                                     >
                                                 </div>
                                                 <button type="submit" class="btn btn-info ml-2">
                                                     <i class="feather icon-search"></i> Search
                                                 </button>
                                                 <?php if (!empty($search_query)): ?>
                                                     <a href="ticket_weights.php" class="btn btn-secondary ml-2">
                                                         <i class="feather icon-x"></i> Clear
                                                     </a>
                                                 <?php endif; ?>
                                             </form>
                                         </div>

                                         <div class="card-body p-0">
                                             <!-- Pagination Info -->
                                             <div class="row mb-3 p-3">
                                                 <div class="col-md-6">
                                                     <small class="text-muted">
                                                         Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                     </small>
                                                 </div>
                                             </div>
                                             <div class="table-responsive">
                                                <table class="table table-hover mb-0" id="weightsTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">
                                                                <input type="checkbox" id="selectAllWeights">
                                                            </th>
                                                            <th><?= __('passenger') ?></th>
                                                            <th><?= __('flight_details') ?></th>
                                                            <th><?= __('weight_details') ?></th>
                                                            <th><?= __('financial_details') ?></th>
                                                            <th><?= __('date_added') ?></th>
                                                            <th><?= __('payment_status') ?></th>
                                                            <th class="text-right no-sort"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="ticketTable">
                                                        <?php foreach ($weights as $weight): ?>
                                                        <tr>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="weight-checkbox" value="<?= $weight['id'] ?>">
                                                            </td>
                                                            <td>
                                                                <div class="passenger-info">
                                                                    
                                                                    <div class="passenger-info__details">
                                                                        <div class="passenger-info__name">
                                                                         <?= htmlspecialchars($weight['passenger_name']) ?>
                                                                        </div>
                                                                        <div class="passenger-info__pnr">
                                                                            PNR: <?= htmlspecialchars($weight['pnr']) ?>
                                                                            <br>
                                                                            <?= __('phone') ?>: <?= htmlspecialchars($weight['phone']) ?>
                                                                            <br>
                                                                            <?= __('created_by') ?>: <?= htmlspecialchars($weight['created_by']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="flight-info">
                                                                    <div class="flight-info__segment">
                                                                        <div class="flight-info__city">
                                                                            <?= htmlspecialchars($weight['origin']) ?> - <?= htmlspecialchars($weight['destination']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="weight-info">
                                                                    <div class="weight-info__weight">
                                                                        <?= number_format($weight['weight'], 2) ?> kg
                                                                    </div>
                                                                    <?php if (!empty($weight['remarks'])): ?>
                                                                    <div class="weight-info__remarks">
                                                                        <?= htmlspecialchars($weight['remarks']) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($weight['exchange_rate']) || !empty($weight['market_exchange_rate'])): ?>
                                                                    <div class="weight-info__exchange-rate">
                                                                        <?= __('rate') ?>: <?= number_format($weight['exchange_rate'], 2) ?>
                                                                    </div>
                                                                    <?php if (!empty($weight['market_exchange_rate'])): ?>
                                                                    <div class="weight-info__market-exchange-rate">
                                                                        <?= __('market') ?>: <?= number_format($weight['market_exchange_rate'], 2) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__amount">
                                                                        <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['sold_price'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__base-price">
                                                                        <?= __('base') ?>: <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['base_price'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__profit">
                                                                        <?= __('profit') ?>: <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['profit'], 2) ?>
                                                                    </div>
                                                                    
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?= date('d M Y H:i', strtotime($weight['created_at'])) ?>
                                                                <br>
                                                                <?= __('created_by') ?>: <?= htmlspecialchars($weight['created_by']) ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                // Get client type from clients table
                                                                $soldTo = $weight['sold_to_name'];
                                                                $isAgencyClient = false;

                                                                // Check client type
                                                                $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE tenant_id = ? AND branch_id = ? AND name = ?");
                                                                $clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                                                $clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                                                $clientStmt->bindParam(3, $soldTo, PDO::PARAM_STR);
                                                                $clientStmt->execute();
                                                                $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                                if ($clientRow) {
                                                                    $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                }

                                                                if ($isAgencyClient) {
                                                                    $baseCurrency = $weight['currency']; // Base currency of the weight
                                                                    $soldAmount = floatval($weight['sold_price']);
                                                                    $totalPaidInBase = 0.0;

                                                                    $weightId = $weight['id'];

                                                                    // Fetch transactions
                                                                    $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions
                                                                        WHERE transaction_of = 'weight'
                                                                        AND reference_id = ?
                                                                        AND tenant_id = ? AND branch_id = ?");
                                                                    $transactionStmt->bindParam(1, $weightId, PDO::PARAM_INT);
                                                                    $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                    $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                    $transactionStmt->execute();
                                                                    $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                                                    if ($transactions && count($transactions) > 0) {
                                                                        foreach ($transactions as $transaction) {
                                                                            $amount = floatval($transaction['amount']);
                                                                            $transCurrency = $transaction['currency'];
                                                                            $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 
                                                                                                ? floatval($transaction['exchange_rate']) 
                                                                                                : 1.0;

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

                                                                    // Payment status icon
                                                                    if ($totalPaidInBase <= 0) {
                                                                        echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                                    } elseif ($totalPaidInBase < $soldAmount) {
                                                                        $percentage = round(($totalPaidInBase / $soldAmount) * 100);
                                                                        echo '<i class="fas fa-circle text-warning" style="color: #ffc107 !important;"
                                                                            title="Partial payment: ' . $baseCurrency . ' ' . number_format($totalPaidInBase, 2) . ' / ' . 
                                                                            $baseCurrency . ' ' . number_format($soldAmount, 2) . ' (' . $percentage . '%)"></i>';
                                                                    } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                        echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                                    } else {
                                                                        echo '<i class="fas fa-circle text-success"
                                                                            title="Fully paid (overpaid by ' . $baseCurrency . ' ' . number_format($totalPaidInBase - $soldAmount, 2) . ')"></i>';
                                                                    }
                                                                } else {
                                                                    echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                                }
                                                                ?>
                                                            </td>

                                                            <td class="text-right">
                                                                 <div class="dropdown">
                                                                     <button class="btn btn-icon btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                         <i class="feather icon-more-horizontal"></i>
                                                                     </button>
                                                                     <div class="dropdown-menu dropdown-menu-right">
                                                                         <?php if ($isAgencyClient): ?>
                                                                         <a class="dropdown-item" href="javascript:void(0)" onclick="manageTransactions(<?= $weight['id'] ?>)">
                                                                             <i class="fa fa-credit-card mr-2"></i><?= __('manage_transactions') ?>
                                                                         </a>
                                                                         <?php endif; ?>
                                                                         <a class="dropdown-item" href="javascript:void(0)" onclick="editWeight(<?= $weight['id'] ?>)">
                                                                             <i class="feather icon-edit mr-2"></i><?= __('edit_weight') ?>
                                                                         </a>
                                                                         <div class="dropdown-divider"></div>
                                                                         <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteWeight(<?= $weight['id'] ?>)">
                                                                             <i class="feather icon-trash-2 mr-2"></i><?= __('delete_weight') ?>
                                                                         </a>
                                                                     </div>
                                                                 </div>
                                                             </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                </div>
                                                <!-- Pagination Controls -->
                                                <div class="row mt-4 p-3">
                                                 <div class="col-md-12">
                                                     <nav aria-label="Page navigation">
                                                         <ul class="pagination justify-content-center">
                                                             <?php
                                                             // Helper function to build pagination links with search parameter
                                                             $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                                                             ?>
                                                             <?php if ($current_page > 1): ?>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=1<?= $search_param ?>">First</a>
                                                                 </li>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=<?= $current_page - 1 ?><?= $search_param ?>">Previous</a>
                                                                 </li>
                                                             <?php else: ?>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">First</span>
                                                                 </li>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">Previous</span>
                                                                 </li>
                                                             <?php endif; ?>

                                                             <?php
                                                             // Show page numbers
                                                             $start_page = max(1, $current_page - 2);
                                                             $end_page = min($total_pages, $current_page + 2);

                                                             if ($start_page > 1):
                                                             ?>
                                                                 <li class="page-item disabled"><span class="page-link">...</span></li>
                                                             <?php endif; ?>

                                                             <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                                 <?php if ($i == $current_page): ?>
                                                                     <li class="page-item active"><span class="page-link"><?= $i ?></span></li>
                                                                 <?php else: ?>
                                                                     <li class="page-item"><a class="page-link" href="?page=<?= $i ?><?= $search_param ?>"><?= $i ?></a></li>
                                                                 <?php endif; ?>
                                                             <?php endfor; ?>

                                                             <?php if ($end_page < $total_pages): ?>
                                                                 <li class="page-item disabled"><span class="page-link">...</span></li>
                                                             <?php endif; ?>

                                                             <?php if ($current_page < $total_pages): ?>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=<?= $current_page + 1 ?><?= $search_param ?>">Next</a>
                                                                 </li>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=<?= $total_pages ?><?= $search_param ?>">Last</a>
                                                                 </li>
                                                             <?php else: ?>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">Next</span>
                                                                 </li>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">Last</span>
                                                                 </li>
                                                             <?php endif; ?>
                                                         </ul>
                                                     </nav>
                                                 </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                <!-- [ Table ] end -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <?php include '../modals/ticket_weight/transaction_modal.php'; ?>
    <?php include '../modals/ticket_weight/book_ticket_modal.php'; ?>
    <?php include '../modals/ticket_weight/edit_ticket_modal.php'; ?>
    <?php include '../modals/ticket_weight/multi_ticket_modal.php'; ?>  
  

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
 

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables removed - using server-side PHP filtering -->
    
        <script src="../js/ticket_weight/transaction_manager.js"></script>
        <script src="../js/ticket_weight/weight_manager.js"></script>
        <script src="../js/ticket_weight/button_protection.js"></script>
        <script src="../js/ticket_weight/multi_ticket.js"></script>
    
        <script>


            // Function to show toast
    function showToast(message, type = 'success') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }


    </script>



</body>
</html>
