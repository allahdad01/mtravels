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
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}
include '../api/ticket/ticket_handler.php';

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);
?>

<?php 
include '../includes/header.php';
?>
<!-- Add Bootstrap-select CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
   
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

/* Ticket Card Styles */
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

.ticket-card-action-btn:hover {
    background: #2e7dd9;
    transform: scale(1.05);
}

@media (max-width: 768px) {
.ticket-card {
    grid-template-columns: 1fr;
}

.ticket-card-main {
    grid-template-columns: 1fr;
}

.ticket-card-main::after {
    display: none;
}

.ticket-card-stub {
    padding: 8px 12px;
}

.ticket-card-actions {
    position: relative;
    top: auto;
    right: auto;
    margin-top: 8px;
}
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
                                        <h5 class="m-b-10"><?= __('ticket') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('ticket') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
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
                            <div class="row">
                                <div class="col-sm-12">
                                    <!-- Search and Actions Section -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="search-box">
                                                        <div class="input-group">
                                                            <input type="text" id="pnrFilter" class="form-control" placeholder="<?= __('search_by_pnr_passenger_name_or_airline') ?>" value="<?= htmlspecialchars($search) ?>">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-primary" type="button" id="searchBtn">
                                                                    <i class="feather icon-search"></i> <?= __('search') ?>
                                                                </button>
                                                                <?php if (!empty($search)): ?>
                                                                <a href="ticket.php" class="btn btn-secondary">
                                                                    <i class="feather icon-x"></i> <?= __('clear') ?>
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <button class="btn btn-primary btn-lg shadow-md" data-toggle="modal" data-target="#bookTicketModal">
                                                        <i class="feather icon-plus-circle mr-2"></i><?= __('book_ticket') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tickets Card Section -->
                                     <div class="card">
                                         <div class="card-body">
                                             <div class="ticket-card-container" id="ticketTable">
                                                 <?php 
                                                 $counter = 1;
                                                 foreach ($tickets as $ticket): 
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
                                                     
                                                     // Determine payment status
                                                     $paymentStatus = 'neutral';
                                                     $totalPaidInBase = 0;
                                                     $baseCurrency = $ticket['ticket']['currency'];
                                                     $soldAmount = floatval($ticket['ticket']['sold']);
                                                     $ticketId = $ticket['ticket']['id'];
                                                     
                                                     if ($isAgencyClient) {
                                                         // Query transactions from database
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
                                                                     TICKET
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
                                                                     <span><?= htmlspecialchars($ticket['ticket']['departure_date']) ?><?php if (!empty($ticket['ticket']['departure_time'])): ?> @ <?= htmlspecialchars($ticket['ticket']['departure_time']) ?><?php endif; ?></span>
                                                                 </div>
                                                                 <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                                                 <div class="ticket-card-detail-item">
                                                                     <span class="ticket-card-detail-label"><?= __('return') ?>:</span>
                                                                     <span><?= htmlspecialchars($ticket['ticket']['return_date']) ?><?php if (!empty($ticket['ticket']['return_departure_time'])): ?> @ <?= htmlspecialchars($ticket['ticket']['return_departure_time']) ?><?php endif; ?></span>
                                                                 </div>
                                                                 <?php endif; ?>
                                                                 <?php if ($ticket['refund_data']): ?>
                                                                 <div class="ticket-card-detail-item" style="color: #ff6b6b;">
                                                                     <span class="ticket-card-detail-label"><?= __('refunded') ?>:</span>
                                                                     <span><?= htmlspecialchars($ticket['refund_data']['currency']) ?> <?= number_format($ticket['refund_data']['refund_to_passenger'], 2) ?></span>
                                                                 </div>
                                                                 <?php endif; ?>
                                                                 <?php if ($ticket['date_change_data']): ?>
                                                                 <div class="ticket-card-detail-item" style="color: #ffc107;">
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

                                            <!-- Pagination -->
                                            <div class="card-footer bg-white">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="text-muted">
                                                        <?= __('showing') ?> <?= min(($page - 1) * $results_per_page + 1, $totalTickets) ?> <?= __('to') ?> <?= min($page * $results_per_page, $totalTickets) ?> <?= __('of') ?> <?= $totalTickets ?> <?= __('tickets') ?>
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
                                                            $end_page = min($total_pages, $page + 2);
                                                            
                                                            if ($start_page > 1) {
                                                                echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search='.urlencode($search) : '') . '">1</a></li>';
                                                                if ($start_page > 2) {
                                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                                }
                                                            }
                                                            
                                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                    <a class="page-link" href="?page=' . $i . (!empty($search) ? '&search='.urlencode($search) : '') . '">' . $i . '</a>
                                                                    </li>';
                                                            }
                                                            
                                                            if ($end_page < $total_pages) {
                                                                if ($end_page < $total_pages - 1) {
                                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                                }
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
        <!-- Add a floating action button for launching the multi-ticket invoice modal -->
        <div id="floatingActionButton" class="position-fixed" style="bottom: 80px; <?php echo is_rtl() ? 'left' : 'right'; ?>: 20px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>" style="width: 60px; height: 60px; padding: 0; border-radius: 50%;">
        <i class="feather icon-file-text"></i>
    </button>
</div>
<?php include '../modals/ticket/multi_ticket_modal.php'; ?> 
    <?php include '../modals/ticket/book_ticket_modal.php'; ?>
    <?php include '../modals/ticket/ticket_details.php'; ?>
    <?php include '../modals/ticket/ticket_refund_modal.php'; ?>
    <?php include '../modals/ticket/ticket_date_change_modal.php'; ?>
    <?php include '../modals/ticket/ticket_weight_modal.php'; ?>
    <?php include '../modals/ticket/transaction_modal.php'; ?>

    <?php include '../modals/ticket/edit_ticket_modal.php'; ?>
    



       
<?php include '../includes/admin_footer.php'; ?>



                                    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>
                                    <!-- Add Bootstrap-select JavaScript -->
                                    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
                                    <script src="../js/ticket/profit-calc.js"></script>
                                    <script src="../js/ticket//ticket-details.js"></script>
                                    <script src="../js/ticket//ticket-form.js"></script>
                                    <script src="../js/ticket//supplier-currency.js"></script>
                                    <script src="../js/ticket//delete-ticket.js"></script>
                                    <script src="../js/ticket//weight-management.js"></script>
                                    <script src="../js/ticket/refund-calc.js"></script>
                                    <script src="../js/ticket/search.js"></script>
                                    <script src="../js/ticket/transaction-manager.js"></script>
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