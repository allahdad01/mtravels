<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
include '../api/ticket_refund/refund_ticket_handler.php';

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

// Generate cache-busting version
$version = '?v=' . time();
?>

<?php include '../includes/header.php'; ?>
<!-- DataTables CSS removed - using server-side PHP filtering -->
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

/* Refund Ticket Card Styles */
.refund-card-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.refund-card {
    display: grid;
    grid-template-columns: 1fr 140px;
    border-radius: 10px;
    overflow: hidden;
    background: #e8edf2;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.refund-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.refund-card-main {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    padding: 18px 22px;
    position: relative;
}

.refund-card-main::after {
    content: '';
    position: absolute;
    right: 0;
    top: 10%;
    height: 80%;
    border-right: 2px dashed rgba(255,255,255,0.5);
}

.refund-card-main.status-paid {
    background: #8db87a;
}

.refund-card-main.status-partial {
    background: #d4a574;
}

.refund-card-main.status-unpaid {
    background: #e07a7a;
}

.refund-card-main.status-neutral {
    background: #6b8fb3;
}

.refund-card-left {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.refund-card-title {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.5px;
    line-height: 1;
}

.refund-card-id {
    font-size: 11px;
    color: rgba(255,255,255,0.85);
    font-weight: 500;
}

.refund-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 16px;
    color: rgba(255,255,255,0.9);
    font-size: 12px;
    margin-top: 8px;
}

.refund-card-detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.refund-card-detail-label {
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    opacity: 0.8;
    min-width: fit-content;
}

.refund-card-right {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.refund-card-price-box {
    background: #fff;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 28px;
    font-weight: 700;
    color: #2d3f52;
    letter-spacing: -0.5px;
    text-align: center;
}

.refund-card-price-meta {
    display: flex;
    gap: 4px;
    align-items: center;
    font-size: 9px;
    color: rgba(255,255,255,0.75);
}

.refund-card-meta-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(255,255,255,0.7);
}

.refund-card-stub {
    background: #e2e8ed;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12px 8px;
    gap: 6px;
}

.refund-card-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.refund-card-action-btn {
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

.refund-card-action-btn:hover {
    background: #2e7dd9;
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .refund-card {
        grid-template-columns: 1fr;
    }
    
    .refund-card-main {
        grid-template-columns: 1fr;
    }
    
    .refund-card-main::after {
        display: none;
    }
    
    .refund-card-stub {
        padding: 8px 12px;
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
                                    <h5 class="m-b-10"><?= __('refund_tickets') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:"><?= __('refund_tickets') ?></a></li>
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
                                <!-- [ Ticket Table ] start -->
                                <div class="card" style="width: 100%;">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                         <h4 class="mb-0">Refunded Tickets</h4>
                                         <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRefundTicketModal">
                                             <i class="feather icon-plus mr-2"></i><?= __('add_refund_ticket') ?>
                                         </button>
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
                                                 <a href="refund_ticket.php" class="btn btn-secondary ml-2">
                                                     <i class="feather icon-x"></i> Clear
                                                 </a>
                                             <?php endif; ?>
                                         </form>
                                     </div>
                                    <div class="card-body p-4">
                                        <!-- Pagination Info -->
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                </small>
                                            </div>
                                        </div>
                                         <div class="refund-card-container" id="ticketTable">
                                                    <?php foreach ($tickets as $index => $ticket): ?>
                                                        <?php
                                                        $soldTo = $ticket['sold_to_name'];
                                                        $isAgencyClient = false;

                                                        $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                                                        $clientStmt->bindParam(1, $soldTo, PDO::PARAM_STR);
                                                        $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                        $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                        $clientStmt->execute();
                                                        $clientResult = $clientStmt->fetchAll();
                                                        if (count($clientResult) > 0) {
                                                            $isAgencyClient = ($clientResult[0]['client_type'] === 'agency');
                                                        }
                                                        ?>
                                                    <?php
                                                    // Determine payment status
                                                    $paymentStatus = 'neutral';
                                                    $totalPaidInBase = 0;
                                                    $baseCurrency = $ticket['currency'];
                                                    $soldAmount = floatval($ticket['refund_to_passenger']);
                                                    $ticketId = $ticket['id'];
                                                    
                                                    if ($isAgencyClient) {
                                                        // Query transactions from database
                                                        $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                                            transaction_of = 'ticket_refund'
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
                                                    <div class="refund-card">
                                                    <div class="refund-card-main status-<?= $paymentStatus ?>">
                                                        <div class="refund-card-left">
                                                            <div>
                                                                <div class="refund-card-title">REFUND</div>
                                                                <div class="refund-card-id"><?= htmlspecialchars($ticket['pnr']) ?></div>
                                                            </div>
                                                            <div class="refund-card-details">
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Passenger:</span>
                                                                    <span><?= htmlspecialchars($ticket['title']) ?> <?= htmlspecialchars($ticket['passenger_name']) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Sold To:</span>
                                                                    <span><?= htmlspecialchars($ticket['sold_to_name']) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Route:</span>
                                                                    <span><?= htmlspecialchars($ticket['origin']) ?> → <?= htmlspecialchars($ticket['destination']) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Airline:</span>
                                                                    <span><?= htmlspecialchars($ticket['airline']) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Base:</span>
                                                                    <span><?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['base'], 2) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Sold:</span>
                                                                    <span><?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['sold'], 2) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Supplier Penalty:</span>
                                                                    <span><?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'], 2) ?></span>
                                                                </div>
                                                                <div class="refund-card-detail-item">
                                                                    <span class="refund-card-detail-label">Service Penalty:</span>
                                                                    <span><?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['service_penalty'], 2) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="refund-card-right">
                                                            <div class="refund-card-price-box"><?= number_format($ticket['refund_to_passenger'], 2) ?></div>
                                                            <div class="refund-card-price-meta">
                                                                <div class="refund-card-meta-dot"></div>
                                                                <div class="refund-card-meta-dot"></div>
                                                                <div class="refund-card-meta-dot"></div>
                                                                <span><?= htmlspecialchars($baseCurrency) ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="refund-card-stub">
                                                        <div class="refund-card-actions" style="display: flex; flex-direction: column; gap: 6px;">
                                                            <?php if ($isAgencyClient && $canEdit): ?>
                                                            <button class="refund-card-action-btn" onclick="manageTransactions(<?= $ticket['id'] ?>)" title="<?= __('manage_payments') ?>" style="width: 100%;">
                                                                <i class="fas fa-dollar-sign"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <button class="refund-card-action-btn" onclick="printRefundAgreement(<?= $ticket['id'] ?>)" title="<?= __('print_refund_agreement') ?>" style="width: 100%;">
                                                                <i class="feather icon-file"></i>
                                                            </button>
                                                            <?php if ($canEdit): ?>
                                                            <button class="refund-card-action-btn" onclick="deleteTicket(<?= $ticket['id'] ?>)" title="<?= __('delete') ?>" style="width: 100%;">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                    </div>
                                        <!-- Pagination Controls -->
                                        <div class="row mt-4">
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
                                <!-- [ Ticket Table ] end -->
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fetch clients for the multi-ticket invoice modal
$clients = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, name FROM clients 
         WHERE tenant_id = ? AND branch_id = ? 
         ORDER BY name ASC"
    );
    $stmt->execute([$tenant_id, $branch_id]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching clients: " . $e->getMessage());
}
?>

<?php include '../modals/ticket_refund/refund_ticket_modal.php'; ?>
<?php include '../modals/ticket_refund/transaction_modal.php'; ?>
<?php include '../modals/ticket_refund/edit_transaction_modal.php'; ?>
<?php include '../modals/ticket_refund/multi_ticket.php'; ?>






                                  <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>

                                    <!-- DataTables removed - using server-side PHP filtering -->
                                

<style>
    /* Enhanced Card Styles */
    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        transform: translateY(-5px);
    }

    /* Responsive Table Improvements */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Ticket Table Styles */
    #refundTicketTable thead {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    #refundTicketTable th {
        font-weight: 600;
        text-transform: uppercase;
        color: #6c757d;
        padding: 12px 15px;
    }

    #refundTicketTable td {
        vertical-align: middle;
        padding: 12px 15px;
    }

    /* Status Indicator Styles */
    .status-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .status-indicator.status-paid {
        background-color: #28a745;
    }

    .status-indicator.status-partial {
        background-color: #ffc107;
    }

    .status-indicator.status-unpaid {
        background-color: #dc3545;
    }

    /* Avatar Styles */
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }

    .avatar.bg-light-primary {
        background-color: rgba(59, 125, 221, 0.2);
        color: #3b7ddd;
    }

    /* Dropdown Menu Styles */
    .dropdown-menu {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: none;
    }

    .dropdown-item {
        transition: background-color 0.2s ease;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    /* Floating Action Button */
    #floatingActionButton .btn {
        border-radius: 50%;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    #floatingActionButton .btn:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .card-body {
            padding: 15px;
        }

        .table-responsive {
            font-size: 0.9rem;
        }
    }

</style>

<!-- SweetAlert2 CSS and JS -->
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Initialize translations for JavaScript -->
<script>
window.translations = {
    search: "<?= __('search') ?>",
    show: "<?= __('show') ?>",
    entries: "<?= __('entries') ?>",
    showing: "<?= __('showing') ?>",
    to: "<?= __('to') ?>",
    of: "<?= __('of') ?>",
    filtered_from: "<?= __('filtered_from') ?>",
    total_entries: "<?= __('total_entries') ?>",
    first: "<?= __('first') ?>",
    last: "<?= __('last') ?>",
    next: "<?= __('next') ?>",
    previous: "<?= __('previous') ?>",
    ticket_id_is_missing: "<?= __('ticket_id_is_missing') ?>",
    error: "<?= __('error') ?>",
    failed_to_generate_agreement: "<?= __('failed_to_generate_agreement') ?>",
    error_generating_agreement: "<?= __('error_generating_agreement') ?>",
    are_you_sure_you_want_to_delete_this_ticket: "<?= __('are_you_sure_you_want_to_delete_this_ticket') ?>"
};
</script>

<!-- Custom JS -->
<script src="../js/ticket_refund/multi_ticket.js<?= $version ?>"></script>
<script src="../js/ticket_refund/search.js<?= $version ?>"></script>
<script src="../js/ticket_refund/transaction_manager.js<?= $version ?>"></script>
<!-- DataTable removed -->
<script src="../js/ticket_refund/document_actions.js<?= $version ?>"></script>
<script src="../js/ticket_refund/select.js<?= $version ?>"></script>
<script src="../js/ticket_refund/main.js<?= $version ?>"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>