<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize variables
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$clientData = null;
$transactions = [];
$error = null;

// Check if ID is provided
if (!$clientId) {
    $error = "No client ID provided";
} else {
    // Get client details
    $clientQuery = "SELECT id, image, name, email, phone, usd_balance, afs_balance, address, created_at, updated_at, client_type FROM clients WHERE id = ? AND tenant_id = ?";
        
    $stmt = $pdo->prepare($clientQuery);
    $stmt->execute([$clientId, $tenant_id]);
    $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$clientData) {
        $error = "Client not found";
    } else {
        // Get transactions related to this client
        $transactionsQuery = "SELECT 
                ct.id,
                ct.client_id,
                ct.amount,
                ct.currency,
                ct.type,
                ct.description,
                ct.reference_id,
                ct.transaction_of,
                ct.created_at AS transaction_date
            FROM client_transactions ct
            WHERE ct.client_id = ? 
            AND ct.tenant_id = ?
            ORDER BY ct.created_at DESC";
            
        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$clientId, $tenant_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Include the header
include '../includes/header.php';
?>
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
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('client_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('client_details') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <a href="search.php" class="btn btn-primary"><?= __('back_to_search') ?></a>
                <?php else: ?>
                    <!-- Client Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-user mr-2"></i>
                                <?= __('client_information') ?>
                                <span class="float-right">
                                    <span class="badge badge-<?php 
                                        if (isset($clientData['status']) && $clientData['status'] == 'Active') echo 'success';
                                        elseif (isset($clientData['status']) && $clientData['status'] == 'Inactive') echo 'danger';
                                        else echo 'warning';
                                    ?>">
                                        <?php echo isset($clientData['status']) ? htmlspecialchars($clientData['status']) : 'Unknown'; ?>
                                    </span>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($clientData['image'])): ?>
                                <div class="col-md-12 mb-4 text-center">
                                    <img src="../uploads/clients/<?php echo htmlspecialchars($clientData['image']); ?>" 
                                        alt="Client Profile" class="img-fluid rounded" style="max-height: 200px;">
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('name') ?></th>
                                                <td><?php echo isset($clientData['name']) ? htmlspecialchars($clientData['name']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('email') ?></th>
                                                <td><?php echo isset($clientData['email']) ? htmlspecialchars($clientData['email']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('phone') ?></th>
                                                <td><?php echo isset($clientData['phone']) ? htmlspecialchars($clientData['phone']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('address') ?></th>
                                                <td><?php echo isset($clientData['address']) ? htmlspecialchars($clientData['address']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('client_type') ?></th>
                                                <td><?php echo isset($clientData['client_type']) ? htmlspecialchars($clientData['client_type']) : '—'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th><?= __('usd_balance') ?></th>
                                                <td class="<?php echo (isset($clientData['usd_balance']) && $clientData['usd_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <strong>
                                                        <?php echo isset($clientData['usd_balance']) ? 'USD ' . htmlspecialchars($clientData['usd_balance']) : '—'; ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('afs_balance') ?></th>
                                                <td class="<?php echo (isset($clientData['afs_balance']) && $clientData['afs_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <strong>
                                                        <?php echo isset($clientData['afs_balance']) ? 'AFS ' . htmlspecialchars($clientData['afs_balance']) : '—'; ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('created_at') ?></th>
                                                <td><?php echo isset($clientData['created_at']) ? date('Y-m-d H:i', strtotime($clientData['created_at'])) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('updated_at') ?></th>
                                                <td><?php echo isset($clientData['updated_at']) ? date('Y-m-d H:i', strtotime($clientData['updated_at'])) : '—'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bookings Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="feather icon-calendar mr-2"></i><?= __('booking_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <!-- Main Bookings Section -->
                            <h6 class="text-muted mb-3"><i class="feather icon-bookmark mr-2"></i><?= __('main_bookings') ?></h6>
                            <div class="row mb-4">
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-primary text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-1">
                                                <i class="feather icon-tag mr-1" style="font-size: 1.25rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    // Get ticket count
                                                    $countQuery = "SELECT COUNT(*) FROM ticket_bookings WHERE sold_to = ?";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('tickets') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-success text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-file-text mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    // Get visa count
                                                    $countQuery = "SELECT COUNT(*) FROM visa_applications WHERE sold_to = ?";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('visas') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-info text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-home mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    // Get hotel count
                                                    $countQuery = "SELECT COUNT(*) FROM hotel_bookings WHERE sold_to = ?";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('hotels') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-warning text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-star mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    // Get umrah count
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'umrah'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('umrah') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Refunds Section -->
                            <h6 class="text-muted mb-3"><i class="feather icon-refresh-ccw mr-2"></i><?= __('refunds') ?></h6>
                            <div class="row mb-4">
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-danger text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-tag mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'ticket_refund'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('refund_tickets') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-danger text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-file-text mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'visa_refund'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('refund_visas') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-danger text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-home mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'hotel_refund'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('refund_hotels') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-danger text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-star mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'umrah_refund'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('refund_umrah') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Other Transactions Section -->
                            <h6 class="text-muted mb-3"><i class="feather icon-activity mr-2"></i><?= __('other_transactions') ?></h6>
                            <div class="row mb-4">
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-secondary text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-calendar mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'date_change'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('date_changes') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-info text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-plus-circle mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'additional_payment'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('additional_payments') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-primary text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-clock mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'ticket_reserve'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('ticket_reserve') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-success text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-repeat mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'fund'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('fund_transfer') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Summary Section -->
                            <h6 class="text-muted mb-3"><i class="feather icon-dollar-sign mr-2"></i><?= __('financial_summary') ?></h6>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-success text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-arrow-up-circle mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    // Get total credit
                                                    $creditQuery = "SELECT SUM(amount) FROM client_transactions WHERE client_id = ? AND type = 'credit'";
                                                    $stmt = $pdo->prepare($creditQuery);
                                                    $stmt->execute([$clientId]);
                                                    $totalCredit = $stmt->fetchColumn() ?: 0;
                                                    echo number_format($totalCredit, 2);
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('total_credit') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-danger text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-arrow-down-circle mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    // Get total debit
                                                    $debitQuery = "SELECT SUM(amount) FROM client_transactions WHERE client_id = ? AND type = 'debit'";
                                                    $stmt = $pdo->prepare($debitQuery);
                                                    $stmt->execute([$clientId]);
                                                    $totalDebit = $stmt->fetchColumn() ?: 0;
                                                    echo number_format($totalDebit, 2);
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('total_debit') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-dark text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-credit-card mr-2 text-white" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0 text-white">
                                                    <?php
                                                    // Calculate balance
                                                    $balance = $totalCredit - $totalDebit;
                                                    echo number_format($balance, 2);
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0 text-white"><?= __('current_balance') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card bg-warning text-white shadow-sm rounded-lg">
                                        <div class="card-body text-center p-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="feather icon-file mr-2" style="font-size: 1.5rem;"></i>
                                                <h2 class="mb-0">
                                                    <?php
                                                    $countQuery = "SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND transaction_of = 'jv_payment'";
                                                    $stmt = $pdo->prepare($countQuery);
                                                    $stmt->execute([$clientId]);
                                                    echo h($stmt->fetchColumn());
                                                    ?>
                                                </h2>
                                            </div>
                                            <p class="mb-0"><?= __('jv_payment') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-activity mr-2"></i><?= __('transaction_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($transactions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= __('date') ?></th>
                                            <th><?= __('type') ?></th>
                                            <th><?= __('amount') ?></th>
                                            <th><?= __('related_to') ?></th>
                                            <th><?= __('description') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo (isset($transaction['type']) && strtolower($transaction['type']) == 'credit') ? 'success' : 'info'; 
                                                ?>">
                                                    <?php echo isset($transaction['type']) ? ucfirst(strtolower($transaction['type'])) : '—'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="<?php echo (isset($transaction['type']) && strtolower($transaction['type']) == 'credit') ? 'text-success' : 'text-danger'; ?>">
                                                    <?php 
                                                    if (isset($transaction['currency']) && isset($transaction['amount'])) {
                                                        echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']);
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if (isset($transaction['transaction_of']) && !empty($transaction['transaction_of'])) {
                                                    $transactionType = htmlspecialchars(ucfirst($transaction['transaction_of']));
                                                    $refId = isset($transaction['reference_id']) ? htmlspecialchars($transaction['reference_id']) : '';
                                                    
                                                    switch ($transaction['transaction_of']) {
                                                        case 'ticket':
                                                            echo "<a href='ticket_detail.php?id={$refId}'>{$transactionType} #{$refId}</a>";
                                                            break;
                                                        case 'visa':
                                                        case 'visa_sale':
                                                            echo "<a href='visa_detail.php?id={$refId}'>{$transactionType} #{$refId}</a>";
                                                            break;
                                                        case 'hotel':
                                                        case 'hotel_booking':
                                                            echo "<a href='hotel_detail.php?id={$refId}'>{$transactionType} #{$refId}</a>";
                                                            break;
                                                        default:
                                                            echo h($transactionType) . ($refId ? " #{$refId}" : '');
                                                    }
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info"><?= __('no_transactions_found_for_this_client') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <a href="search.php" class="btn btn-secondary">
                                <i class="feather icon-arrow-left mr-1"></i> <?= __('back_to_search') ?>
                            </a>
                        </div>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include the footer
// include '../includes/footer.php';
?> 

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

        /* Card Styles */
        .card-body {
            padding: 1rem !important;
        }
        
        .card .text-center h2 {
            font-size: 1.5rem;
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .card .text-center p {
            font-size: 0.875rem;
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .card .d-flex i {
            font-size: 1.25rem !important;
            margin-right: 0.5rem !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .card .text-center h2 {
                font-size: 1.25rem;
            }
            
            .card .text-center p {
                font-size: 0.75rem;
            }
            
            .card .d-flex i {
                font-size: 1rem !important;
            }
        }
        
        @media (max-width: 768px) {
            .col-md-3 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .card-body {
                padding: 0.75rem !important;
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
                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Listen for form submission (using submit event)
                                document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    const newPassword = document.getElementById('newPassword').value;
                                    const confirmPassword = document.getElementById('confirmPassword').value;
                                    const currentPassword = document.getElementById('currentPassword').value;

                                    // If any password field is filled, all password fields must be filled
                                    if (newPassword || confirmPassword || currentPassword) {
                                        if (!currentPassword) {
                                                alert('<?= __('please_enter_your_current_password') ?>');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('<?= __('please_enter_a_new_password') ?>');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('<?= __('please_confirm_your_new_password') ?>');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('<?= __('new_passwords_do_not_match') ?>');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('<?= __('new_password_must_be_at_least_6_characters_long') ?>');
                                            return;
                                        }
                                    }
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('update_client_profile.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            // Clear password fields
                                            document.getElementById('currentPassword').value = '';
                                            document.getElementById('newPassword').value = '';
                                            document.getElementById('confirmPassword').value = '';
                                            location.reload();
                                        } else {
                                            alert(data.message || '<?= __('failed_to_update_profile') ?>');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                            alert('<?= __('an_error_occurred_while_updating_the_profile') ?>');
                                    });
                                });
                            });
                            </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 