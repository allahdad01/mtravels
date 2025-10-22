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


                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 