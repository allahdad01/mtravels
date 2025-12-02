<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize variables
$ticketId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$ticketData = null;
$refundData = null;
$dateChangeData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

// Check if ID is provided
if (!$ticketId) {
    $error = "No ticket ID provided";
} else {
    // Get ticket details with related info
    $ticketQuery = "SELECT
            tb.*,
            c.name AS client_name,
            c.email AS client_email,
            c.phone AS client_phone,
            s.name AS supplier_name,
            s.email AS supplier_email,
            s.phone AS supplier_phone,
            ma.name AS paid_to_name
        FROM ticket_bookings tb
        LEFT JOIN clients c ON tb.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        LEFT JOIN suppliers s ON tb.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN main_account ma ON tb.paid_to = ma.id AND ma.tenant_id = ? AND ma.branch_id = ?
        WHERE tb.id = ? AND tb.tenant_id = ? AND tb.branch_id = ?";

    $stmt = $pdo->prepare($ticketQuery);
    $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $ticketId, $tenant_id, $branch_id]);
    $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticketData) {
        $error = "Ticket not found";
    } else {
        // Get refund information if exists
        $refundQuery = "SELECT * FROM refunded_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1";
        $stmt = $pdo->prepare($refundQuery);
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $refundData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get date change information if exists
        $dateChangeQuery = "SELECT * FROM date_change_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1";
        $stmt = $pdo->prepare($dateChangeQuery);
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $dateChangeData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get main account transactions related to this ticket
        $mainAccountTransQuery = "SELECT
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.created_at AS transaction_date
            FROM main_account_transactions mat
            WHERE (mat.reference_id = ? AND mat.tenant_id = ? AND mat.branch_id = ? AND (mat.transaction_of = 'ticket_sale' OR
                  mat.transaction_of = 'ticket_refund' OR mat.transaction_of = 'date_change'))
            ORDER BY mat.created_at DESC";

        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get client transactions related to this ticket
        $clientTransQuery = "SELECT
                'Client' AS transaction_type,
                ct.id,
                ct.type,
                ct.amount,
                ct.currency,
                ct.description,
                ct.transaction_of,
                ct.created_at AS transaction_date
            FROM client_transactions ct
            WHERE (ct.reference_id = ? AND ct.tenant_id = ? AND ct.branch_id = ? AND (ct.transaction_of = 'ticket_sale' OR
                  ct.transaction_of = 'ticket_refund' OR ct.transaction_of = 'date_change'))
            ORDER BY ct.created_at DESC";

        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get supplier transactions related to this ticket
        $supplierTransQuery = "SELECT
                'Supplier' AS transaction_type,
                st.id,
                st.transaction_type AS type,
                st.amount,
                st.remarks AS description,
                st.transaction_of,
                st.transaction_date
            FROM supplier_transactions st
            WHERE (st.reference_id = ? AND st.tenant_id = ? AND st.branch_id = ? AND (st.transaction_of = 'ticket_sale' OR
                  st.transaction_of = 'ticket_refund' OR st.transaction_of = 'date_change'))
            ORDER BY st.transaction_date DESC";

        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $supplierTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <h5 class="m-b-10"><?= __('ticket_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('ticket_details') ?></a></li>
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
                    <!-- Ticket Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-file-text mr-2"></i>
                                <?= __('ticket_information') ?>
                                <span class="float-right">
                                    <span class="badge badge-<?php 
                                        if ($ticketData['status'] == 'Paid') echo 'success';
                                        elseif ($ticketData['status'] == 'Borrowed') echo 'warning';
                                        elseif ($ticketData['status'] == 'Date Changed') echo 'primary';
                                        elseif ($ticketData['status'] == 'Refunded') echo 'danger';
                                    ?>">
                                        <?php echo h($ticketData['status']); ?>
                                    </span>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th><?= __('passenger_name') ?></th>
                                            <td>
                                                <?php echo htmlspecialchars($ticketData['title'] . ' ' . $ticketData['passenger_name']); ?>
                                                <span class="badge badge-info"><?php echo h($ticketData['gender']); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('contact') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('pnr') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['pnr']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('airline') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['airline']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('route') ?></th>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ticketData['origin']); ?></strong>
                                                <i class="feather icon-arrow-right mx-2"></i>
                                                <strong><?php echo htmlspecialchars($ticketData['destination']); ?></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('issue_date') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($ticketData['issue_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('departure_date') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($ticketData['departure_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th><?= __('client') ?></th>
                                            <td>
                                                <?php echo htmlspecialchars($ticketData['client_name']); ?>
                                                <?php if ($ticketData['sold_to']): ?>
                                                <a href="client_detail.php?id=<?php echo h($ticketData['sold_to']); ?>" class="btn btn-sm btn-outline-primary float-right">
                                                    <i class="feather icon-external-link"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('client_contact') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['client_phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('supplier') ?></th>
                                            <td>
                                                <?php echo htmlspecialchars($ticketData['supplier_name']); ?>
                                                <?php if ($ticketData['supplier']): ?>
                                                    <a href="supplier_detail.php?id=<?php echo h($ticketData['supplier']); ?>" class="btn btn-sm btn-outline-success float-right">
                                                    <i class="feather icon-external-link"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('supplier_contact') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['supplier_phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('paid_to') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['paid_to_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('price_base') ?></th>
                                            <td><?php echo htmlspecialchars($ticketData['currency']) . ' ' . htmlspecialchars($ticketData['price']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('selling_price') ?></th>
                                            <td><strong><?php echo htmlspecialchars($ticketData['currency']) . ' ' . htmlspecialchars($ticketData['sold']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('profit') ?></th>
                                            <td class="text-success">
                                                <?php echo htmlspecialchars($ticketData['currency']) . ' ' . htmlspecialchars($ticketData['profit']); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('receipt_number') ?></th>
                                            <td><?php echo !empty($ticketData['receipt']) ? htmlspecialchars($ticketData['receipt']) : 'N/A'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <?php if (!empty($ticketData['description'])): ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                                <h6 class="card-title"><i class="feather icon-info mr-2"></i><?= __('description') ?></h6>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($ticketData['description'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transaction Cards -->
                    <?php if ($refundData): ?>
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="feather icon-rotate-ccw mr-2"></i><?= __('refund_information') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <th><?= __('refund_date') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($refundData['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('status') ?></th>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($refundData['status'] == 'Refunded') ? 'success' : 
                                                        (($refundData['status'] == 'Pending') ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo h($refundData['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('supplier_penalty') ?></th>
                                            <td><?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['supplier_penalty']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <th><?= __('receipt_number') ?></th>
                                            <td><?php echo !empty($refundData['receipt']) ? htmlspecialchars($refundData['receipt']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('service_penalty') ?></th>
                                            <td><?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['service_penalty']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('refund_amount') ?></th>
                                            <td><strong><?php echo htmlspecialchars($refundData['currency']) . ' ' . htmlspecialchars($refundData['refund_to_passenger']); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <?php if (!empty($refundData['remarks'])): ?>
                                <div class="col-md-12 mt-2">
                                    <div class="alert alert-secondary">
                                        <strong><?= __('remarks') ?>:</strong> <?php echo nl2br(htmlspecialchars($refundData['remarks'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($dateChangeData): ?>
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="feather icon-calendar mr-2"></i><?= __('date_change_information') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <th><?= __('changed_on') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($dateChangeData['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('new_departure_date') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($dateChangeData['departure_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('status') ?></th>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($dateChangeData['status'] == 'Refunded') ? 'success' : 
                                                        (($dateChangeData['status'] == 'Pending') ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo h($dateChangeData['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <th><?= __('receipt_number') ?></th>
                                            <td><?php echo !empty($dateChangeData['receipt']) ? htmlspecialchars($dateChangeData['receipt']) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('supplier_penalty') ?></th>
                                            <td><?php echo htmlspecialchars($dateChangeData['currency']) . ' ' . htmlspecialchars($dateChangeData['supplier_penalty']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('service_penalty') ?></th>
                                            <td><?php echo htmlspecialchars($dateChangeData['currency']) . ' ' . htmlspecialchars($dateChangeData['service_penalty']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <?php if (!empty($dateChangeData['remarks'])): ?>
                                <div class="col-md-12 mt-2">
                                    <div class="alert alert-secondary">
                                        <strong><?= __('remarks') ?>:</strong> <?php echo nl2br(htmlspecialchars($dateChangeData['remarks'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Transactions History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-activity mr-2"></i><?= __('transaction_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-3" id="transactionTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="main-account-tab" data-toggle="tab" href="#main-account" role="tab"><?= __('main_account_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="client-tab" data-toggle="tab" href="#client" role="tab"><?= __('client_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="supplier-tab" data-toggle="tab" href="#supplier" role="tab"><?= __('supplier_transactions') ?></a>
                                </li>
                            </ul>
                            <div class="tab-content" id="transactionTabContent">
                                <!-- Main Account Transactions -->
                                <div class="tab-pane fade show active" id="main-account" role="tabpanel">
                                    <?php if (!empty($mainAccountTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mainAccountTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                        if ($transaction['transaction_of'] == 'ticket_sale') echo 'info';
                                                        elseif ($transaction['transaction_of'] == 'ticket_refund') echo 'warning';
                                                        elseif ($transaction['transaction_of'] == 'date_change') echo 'dark';
                                                        else echo 'secondary';
                                                        ?>">
                                                            <?php 
                                                            if ($transaction['transaction_of'] == 'ticket_sale') echo __('sale');
                                                            elseif ($transaction['transaction_of'] == 'ticket_refund') echo __('refund');
                                                            elseif ($transaction['transaction_of'] == 'date_change') echo __('date_change');
                                                            else echo ucfirst($transaction['transaction_of']);
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_main_account_transactions_found_for_this_ticket') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Client Transactions -->
                                <div class="tab-pane fade" id="client" role="tabpanel">
                                    <?php if (!empty($clientTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($clientTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                        if ($transaction['transaction_of'] == 'ticket_sale') echo 'info';
                                                        elseif ($transaction['transaction_of'] == 'ticket_refund') echo 'warning';
                                                        elseif ($transaction['transaction_of'] == 'date_change') echo 'dark';
                                                        else echo 'secondary';
                                                        ?>">
                                                            <?php 
                                                            if ($transaction['transaction_of'] == 'ticket_sale') echo __('sale');
                                                            elseif ($transaction['transaction_of'] == 'ticket_refund') echo __('refund');
                                                            elseif ($transaction['transaction_of'] == 'date_change') echo __('date_change');
                                                            else echo ucfirst($transaction['transaction_of']);
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_client_transactions_found_for_this_ticket') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Supplier Transactions -->
                                <div class="tab-pane fade" id="supplier" role="tabpanel">
                                    <?php if (!empty($supplierTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($supplierTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                        if ($transaction['transaction_of'] == 'ticket_sale') echo 'info';
                                                        elseif ($transaction['transaction_of'] == 'ticket_refund') echo 'warning';
                                                        elseif ($transaction['transaction_of'] == 'date_change') echo 'dark';
                                                        else echo 'secondary';
                                                        ?>">
                                                            <?php 
                                                            if ($transaction['transaction_of'] == 'ticket_sale') echo __('sale');
                                                            elseif ($transaction['transaction_of'] == 'ticket_refund') echo __('refund');
                                                            elseif ($transaction['transaction_of'] == 'date_change') echo __('date_change');
                                                            else echo ucfirst($transaction['transaction_of']);
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_supplier_transactions_found_for_this_ticket') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
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


<?php
// Include the footer
include '../includes/admin_footer.php';
?> 