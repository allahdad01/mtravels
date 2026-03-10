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
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';
include '../api/ticket_date_change/date_change_handler.php';

// Check if user is admin or finance
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
<style>
/* Date Change Card Styles */
.date-change-card-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.date-change-card {
    display: grid;
    grid-template-columns: 1fr 140px;
    border-radius: 10px;
    overflow: hidden;
    background: #e8edf2;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.date-change-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.date-change-card-main {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    padding: 18px 22px;
    position: relative;
}

.date-change-card-main::after {
    content: '';
    position: absolute;
    right: 0;
    top: 10%;
    height: 80%;
    border-right: 2px dashed rgba(255,255,255,0.5);
}

.date-change-card-main.status-paid {
    background: #8db87a;
}

.date-change-card-main.status-partial {
    background: #d4a574;
}

.date-change-card-main.status-unpaid {
    background: #e07a7a;
}

.date-change-card-main.status-neutral {
    background: #6b8fb3;
}

.date-change-card-left {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.date-change-card-title {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.5px;
    line-height: 1;
}

.date-change-card-id {
    font-size: 11px;
    color: rgba(255,255,255,0.85);
    font-weight: 500;
}

.date-change-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 16px;
    color: rgba(255,255,255,0.9);
    font-size: 12px;
    margin-top: 8px;
}

.date-change-card-detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.date-change-card-detail-label {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    opacity: 0.8;
    min-width: fit-content;
}

.date-change-card-right {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.date-change-card-price-box {
    background: #fff;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 28px;
    font-weight: 700;
    color: #2d3f52;
    letter-spacing: -0.5px;
    text-align: center;
}

.date-change-card-price-meta {
    display: flex;
    gap: 4px;
    align-items: center;
    font-size: 9px;
    color: rgba(255,255,255,0.75);
}

.date-change-card-meta-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(255,255,255,0.7);
}

.date-change-card-stub {
    background: #e2e8ed;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12px 8px;
    gap: 6px;
}

.date-change-card-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.date-change-card-action-btn {
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

.date-change-card-action-btn:hover {
    background: #2e7dd9;
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .date-change-card {
        grid-template-columns: 1fr;
    }
    
    .date-change-card-main {
        grid-template-columns: 1fr;
    }
    
    .date-change-card-main::after {
        display: none;
    }
    
    .date-change-card-stub {
        padding: 8px 12px;
    }
}
</style>
        <?php 
        include '../includes/header.php';
        // DataTables CSS removed
        ?>
        <!-- [ Main Content ] start -->
            <div class="pcoded-main-container">
                <div class="pcoded-wrapper">
                    <div class="pcoded-content">
                        <div class="pcoded-inner-content">
                            <div class="main-body">
                                <div class="page-wrapper">
                                    <!-- [ Main Content ] start -->
                                    <div class="row">
                                        <div class="col-sm-12">
                                             <!-- Search and Actions Section -->
                                             <div class="card-header mb-3">
                                                 <div class="card-body">
                                                     <div class="row align-items-center">
                                                         <div class="col-md-8">
                                                             <h3><?= __('date_change_management') ?></h3>
                                                         </div>
                                                         <div class="col-md-4 text-right">
                                                             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDateChangeModal">
                                                                 <i class="feather icon-plus mr-2"></i><?= __('add_date_change') ?>
                                                             </button>
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>

                                             <!-- Search Bar -->
                                             <div class="card mb-3">
                                                 <div class="card-body">
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
                                                             <a href="date_change.php" class="btn btn-secondary ml-2">
                                                                 <i class="feather icon-x"></i> Clear
                                                             </a>
                                                         <?php endif; ?>
                                                     </form>
                                                 </div>
                                             </div>

                                    <!-- [ Cards ] start -->
                                    <div class="card">                              
                                         <div class="card-body">
                                             <!-- Pagination Info -->
                                             <div class="row mb-3">
                                                 <div class="col-md-6">
                                                     <small class="text-muted">
                                                         Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                     </small>
                                                 </div>
                                             </div>
                                             <div class="date-change-card-container" id="ticketTable">
                                                 <?php foreach ($tickets as $ticket): ?>
                                                 <?php
                                                     // Determine payment status
                                                     $paymentStatus = 'neutral';
                                                     $totalPaidInBase = 0;
                                                     $baseCurrency = $ticket['currency'];
                                                     $soldAmount = floatval($ticket['supplier_penalty'] + $ticket['service_penalty']);
                                                     $ticketId = $ticket['id'];
                                                     
                                                     // Get client type from clients table
                                                     $soldTo = $ticket['sold_to_name'];
                                                     $isAgencyClient = false;
                                                     
                                                     $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE tenant_id = ? AND branch_id = ? AND name = ?");
                                                     $clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                                     $clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                                     $clientStmt->bindParam(3, $soldTo, PDO::PARAM_STR);
                                                     $clientStmt->execute();
                                                     $clientResult = $clientStmt->fetchAll();
                                                     if (count($clientResult) > 0) {
                                                         $clientRow = $clientResult[0];
                                                         $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                     }
                                                     
                                                     if ($isAgencyClient) {
                                                         // Query transactions from database
                                                         $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                                             transaction_of = 'date_change'
                                                             AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
                                                         $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
                                                         $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                         $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                         $transactionStmt->execute();
                                                         $transactions = $transactionStmt->fetchAll();
                                                         
                                                         if ($transactions && count($transactions) > 0) {
                                                             foreach ($transactions as $transaction) {
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
                                                 <div class="date-change-card">
                                                     <div class="date-change-card-main status-<?= $paymentStatus ?>">
                                                         <div class="date-change-card-left">
                                                             <div>
                                                                 <div class="date-change-card-title">DATE CHANGE</div>
                                                                 <div class="date-change-card-id"><?= htmlspecialchars($ticket['pnr']) ?></div>
                                                             </div>
                                                             <div class="date-change-card-details">
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">Passenger:</span>
                                                                     <span><?= htmlspecialchars($ticket['title']) ?> <?= htmlspecialchars($ticket['passenger_name']) ?></span>
                                                                 </div>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">Sold To:</span>
                                                                     <span><?= htmlspecialchars($ticket['sold_to_name']) ?></span>
                                                                 </div>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">Route:</span>
                                                                     <span><?= htmlspecialchars($ticket['origin']) ?> → <?= htmlspecialchars($ticket['destination']) ?></span>
                                                                 </div>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">Phone:</span>
                                                                     <span><?= htmlspecialchars($ticket['phone'] ?? '') ?></span>
                                                                 </div>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">Old Departure:</span>
                                                                     <span><?= htmlspecialchars($ticket['old_departure_date'] ?? '') ?></span>
                                                                 </div>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">New Departure:</span>
                                                                     <span><?= htmlspecialchars($ticket['departure_date'] ?? '') ?></span>
                                                                 </div>
                                                                 <?php if (!empty($ticket['old_return_date'])): ?>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">Old Return:</span>
                                                                     <span><?= htmlspecialchars($ticket['old_return_date']) ?></span>
                                                                 </div>
                                                                 <div class="date-change-card-detail-item">
                                                                     <span class="date-change-card-detail-label">New Return:</span>
                                                                     <span><?= htmlspecialchars($ticket['return_date'] ?? 'N/A') ?></span>
                                                                 </div>
                                                                 <?php endif; ?>
                                                             </div>
                                                         </div>
                                                         <div class="date-change-card-right">
                                                             <div class="date-change-card-price-box"><?= number_format($ticket['supplier_penalty'] + $ticket['service_penalty'], 2) ?></div>
                                                             <div class="date-change-card-price-meta">
                                                                 <div class="date-change-card-meta-dot"></div>
                                                                 <div class="date-change-card-meta-dot"></div>
                                                                 <div class="date-change-card-meta-dot"></div>
                                                                 <span><?= htmlspecialchars($baseCurrency) ?></span>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <div class="date-change-card-stub">
                                                         <div class="date-change-card-actions" style="display: flex; flex-direction: column; gap: 6px;">
                                                             <?php if ($isAgencyClient && $canEdit): ?>
                                                             <button class="date-change-card-action-btn" onclick="manageTransactions(<?= $ticket['id'] ?>)" title="<?= __('manage_transactions') ?>" style="width: 100%;">
                                                                 <i class="fa fa-credit-card"></i>
                                                             </button>
                                                             <?php endif; ?>
                                                             <button class="date-change-card-action-btn" onclick="printAgreement(<?= $ticket['id'] ?>)" title="<?= __('print_agreement') ?>" style="width: 100%;">
                                                                 <i class="feather icon-printer"></i>
                                                             </button>
                                                             <?php if ($canEdit): ?>
                                                             <button class="date-change-card-action-btn" onclick="deleteTicket(<?= $ticket['id'] ?>)" title="<?= __('delete_ticket') ?>" style="width: 100%;">
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

    <?php include '../modals/ticket_date_change/multi_ticket.php'; ?>
    <?php include '../modals/ticket_date_change/transaction_modal.php'; ?>
    <?php include '../modals/ticket_date_change/edit_transaction.php'; ?>
    <?php include '../modals/ticket_date_change/add_date_change.php'; ?>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
    


    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables removed - using server-side PHP filtering -->
    <script src="../js/ticket_date_change/addDateChange.js"></script>
    <script src="../js/ticket_date_change/deleteDateChange.js"></script>
    <script src="../js/ticket_date_change/transaction-manager.js"></script>
    <script src="../js/ticket_date_change/multiTicket.js"></script>

</body>
</html>
