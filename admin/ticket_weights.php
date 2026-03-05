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

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

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

    <style>
    /* Weight Card Styles */
    .weight-card-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .weight-card {
        display: grid;
        grid-template-columns: 1fr 140px;
        border-radius: 10px;
        overflow: hidden;
        background: #e8edf2;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .weight-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .weight-card-main {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        padding: 18px 22px;
        position: relative;
    }

    .weight-card-main::after {
        content: '';
        position: absolute;
        right: 0;
        top: 10%;
        height: 80%;
        border-right: 2px dashed rgba(255,255,255,0.5);
    }

    .weight-card-main.status-paid {
        background: #8db87a;
    }

    .weight-card-main.status-partial {
        background: #d4a574;
    }

    .weight-card-main.status-unpaid {
        background: #e07a7a;
    }

    .weight-card-main.status-neutral {
        background: #6b8fb3;
    }

    .weight-card-left {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .weight-card-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.5px;
        line-height: 1;
    }

    .weight-card-id {
        font-size: 11px;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
    }

    .weight-card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 16px;
        color: rgba(255,255,255,0.9);
        font-size: 12px;
        margin-top: 8px;
    }

    .weight-card-detail-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .weight-card-detail-label {
        font-weight: 600;
        font-size: 10px;
        text-transform: uppercase;
        opacity: 0.8;
        min-width: fit-content;
    }

    .weight-card-right {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .weight-card-price-box {
        background: #fff;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 28px;
        font-weight: 700;
        color: #2d3f52;
        letter-spacing: -0.5px;
        text-align: center;
    }

    .weight-card-price-meta {
        display: flex;
        gap: 4px;
        align-items: center;
        font-size: 9px;
        color: rgba(255,255,255,0.75);
    }

    .weight-card-meta-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: rgba(255,255,255,0.7);
    }

    .weight-card-stub {
        background: #e2e8ed;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 12px 8px;
        gap: 6px;
    }

    .weight-card-actions {
        display: flex;
        flex-direction: column;
        gap: 6px;
        width: 100%;
    }

    .weight-card-action-btn {
        background: #4099ff;
        border: none;
        color: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    .weight-card-action-btn:hover {
        background: #2e7dd9;
        transform: scale(1.05);
    }

    @media (max-width: 768px) {
        .weight-card {
            grid-template-columns: 1fr;
        }
        
        .weight-card-main {
            grid-template-columns: 1fr;
        }
        
        .weight-card-main::after {
            display: none;
        }
        
        .weight-card-stub {
            padding: 8px 12px;
        }
    }
    </style>

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

                                         <div class="card-body">
                                              <!-- Pagination Info -->
                                              <div class="row mb-3">
                                                  <div class="col-md-6">
                                                      <small class="text-muted">
                                                          Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                      </small>
                                                  </div>
                                              </div>
                                              <div class="weight-card-container" id="ticketTable">
                                                        <?php foreach ($weights as $weight): ?>
                                                        <?php
                                                        // Determine payment status
                                                        $paymentStatus = 'neutral';
                                                        $totalPaidInBase = 0;
                                                        $baseCurrency = $weight['currency'];
                                                        $soldAmount = floatval($weight['sold_price']);
                                                        $weightId = $weight['id'];
                                                        
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

                                                            if ($totalPaidInBase <= 0) {
                                                                $paymentStatus = 'unpaid';
                                                            } elseif ($totalPaidInBase < $soldAmount) {
                                                                $paymentStatus = 'partial';
                                                            } else {
                                                                $paymentStatus = 'paid';
                                                            }
                                                        }
                                                        ?>
                                                        <div class="weight-card">
                                                            <div class="weight-card-main status-<?= $paymentStatus ?>">
                                                                <div class="weight-card-left">
                                                                    <div>
                                                                        <div class="weight-card-title">WEIGHT</div>
                                                                        <div class="weight-card-id"><?= htmlspecialchars($weight['pnr']) ?></div>
                                                                    </div>
                                                                    <div class="weight-card-details">
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Passenger:</span>
                                                                            <span><?= htmlspecialchars($weight['passenger_name']) ?></span>
                                                                        </div>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Sold To:</span>
                                                                            <span><?= htmlspecialchars($weight['sold_to_name']) ?></span>
                                                                        </div>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Route:</span>
                                                                            <span><?= htmlspecialchars($weight['origin']) ?> → <?= htmlspecialchars($weight['destination']) ?></span>
                                                                        </div>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Weight:</span>
                                                                            <span><?= number_format($weight['weight'], 2) ?> kg</span>
                                                                        </div>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Base Price:</span>
                                                                            <span><?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['base_price'], 2) ?></span>
                                                                        </div>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Profit:</span>
                                                                            <span><?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['profit'], 2) ?></span>
                                                                        </div>
                                                                        <?php if (!empty($weight['remarks'])): ?>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Remarks:</span>
                                                                            <span><?= htmlspecialchars($weight['remarks']) ?></span>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                        <div class="weight-card-detail-item">
                                                                            <span class="weight-card-detail-label">Created:</span>
                                                                            <span><?= date('d M Y H:i', strtotime($weight['created_at'])) ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="weight-card-right">
                                                                    <div class="weight-card-price-box"><?= number_format($weight['sold_price'], 2) ?></div>
                                                                    <div class="weight-card-price-meta">
                                                                        <div class="weight-card-meta-dot"></div>
                                                                        <div class="weight-card-meta-dot"></div>
                                                                        <div class="weight-card-meta-dot"></div>
                                                                        <span><?= htmlspecialchars($baseCurrency) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="weight-card-stub">
                                                                <div class="weight-card-actions" style="display: flex; flex-direction: column; gap: 6px;">
                                                                    <?php if ($isAgencyClient && $canEdit): ?>
                                                                    <button class="weight-card-action-btn" onclick="manageTransactions(<?= $weight['id'] ?>)" title="<?= __('manage_transactions') ?>" style="width: 100%;">
                                                                        <i class="fa fa-credit-card"></i>
                                                                    </button>
                                                                    <?php endif; ?>
                                                                    <?php if ($canEdit): ?>
                                                                    <button class="weight-card-action-btn" onclick="editWeight(<?= $weight['id'] ?>)" title="<?= __('edit_weight') ?>" style="width: 100%;">
                                                                        <i class="feather icon-edit"></i>
                                                                    </button>
                                                                    <button class="weight-card-action-btn" onclick="deleteWeight(<?= $weight['id'] ?>)" title="<?= __('delete_weight') ?>" style="width: 100%;">
                                                                        <i class="feather icon-trash-2"></i>
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
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
