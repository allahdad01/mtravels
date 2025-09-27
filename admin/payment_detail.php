<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
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
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$paymentData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

// Check if ID is provided
if (!$paymentId) {
    $error = "No payment ID provided";
} else {
    // Get additional payment details with related info
    $paymentQuery = "SELECT 
            ap.*,
            c.name AS client_name,
            c.email AS client_email,
            c.phone AS client_phone,
            s.name AS supplier_name,
            s.email AS supplier_email,
            s.phone AS supplier_phone,
            u.name AS created_by_name
        FROM additional_payments ap
        LEFT JOIN clients c ON ap.client_id = c.id
        LEFT JOIN suppliers s ON ap.supplier_id = s.id
        LEFT JOIN users u ON ap.created_by = u.id
        WHERE ap.id = ? AND ap.tenant_id = ?";
        
    $stmt = $pdo->prepare($paymentQuery);
    $stmt->execute([$paymentId, $tenant_id]);
    $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paymentData) {
        $error = "Payment not found";
    } else {
        // Get client transactions related to this payment
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
            WHERE ct.reference_id = ? AND ct.transaction_of = 'additional_payment' AND ct.tenant_id = ?
            ORDER BY ct.created_at DESC";
            
        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$paymentId, $tenant_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get supplier transactions related to this payment
        $supplierTransQuery = "SELECT 
                'Supplier' AS transaction_type,
                st.id,
                st.type,
                st.amount,
                st.currency,
                st.description,
                st.transaction_of,
                st.created_at AS transaction_date
            FROM supplier_transactions st
            WHERE st.reference_id = ? AND st.transaction_of = 'additional_payment' AND st.tenant_id = ?
            ORDER BY st.created_at DESC";
            
        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$paymentId, $tenant_id]);
        $supplierTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get main account transactions related to this payment
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
            WHERE mat.reference_id = ? AND mat.transaction_of = 'additional_payment' AND mat.tenant_id = ?
            ORDER BY mat.created_at DESC";
            
        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$paymentId, $tenant_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <h5 class="m-b-10"><?= __('additional_payment_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('payment_details') ?></a></li>
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
                    <!-- Payment Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-credit-card mr-2"></i>
                                <?= __('payment_information') ?>
                                <span class="float-right">
                                    <span class="badge badge-<?php 
                                        if ($paymentData['payment_type'] == 'Income') echo 'success';
                                        elseif ($paymentData['payment_type'] == 'Expense') echo 'danger';
                                        else echo 'warning';
                                    ?>">
                                        <?php echo h($paymentData['payment_type']); ?>
                                    </span>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th><?= __('description') ?></th>
                                            <td><?php echo htmlspecialchars($paymentData['description']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('payment_type') ?></th>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    if ($paymentData['payment_type'] == 'Income') echo 'success';
                                                    elseif ($paymentData['payment_type'] == 'Expense') echo 'danger';
                                                    else echo 'warning';
                                                ?>">
                                                    <?php echo h($paymentData['payment_type']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('base_amount') ?></th>
                                            <td><?php echo htmlspecialchars($paymentData['currency']) . ' ' . htmlspecialchars($paymentData['base_amount']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('sold_amount') ?></th>
                                            <td><strong><?php echo htmlspecialchars($paymentData['currency']) . ' ' . htmlspecialchars($paymentData['sold_amount']); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th><?= __('profit') ?></th>
                                            <td class="<?php echo ($paymentData['profit'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                                <strong><?php echo htmlspecialchars($paymentData['currency']) . ' ' . htmlspecialchars($paymentData['profit']); ?></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('main_account') ?></th>
                                            <td>
                                                <?php if (!empty($paymentData['main_account_id'])): ?>
                                                    ID: <?php echo htmlspecialchars($paymentData['main_account_id']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not specified</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('created_by') ?></th>
                                            <td><?php echo htmlspecialchars($paymentData['created_by_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('created_at') ?></th>
                                            <td><?php echo date('Y-m-d H:i', strtotime($paymentData['created_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Client & Supplier Information -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-user mr-2"></i><?= __('client_information') ?></h6>
                                            <?php if (!empty($paymentData['client_id'])): ?>
                                                <p><strong><?= __('name') ?>:</strong> <?php echo htmlspecialchars($paymentData['client_name']); ?></p>
                                                <p><strong><?= __('email') ?>:</strong> <?php echo htmlspecialchars($paymentData['client_email']); ?></p>
                                                <p><strong><?= __('phone') ?>:</strong> <?php echo htmlspecialchars($paymentData['client_phone']); ?></p>
                                                <a href="client_detail.php?id=<?php echo h($paymentData['client_id']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-external-link mr-1"></i> <?= __('view_client_details') ?>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted"><?= __('no_client_associated_with_this_payment') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-briefcase mr-2"></i><?= __('supplier_information') ?></h6>
                                            <?php if (!empty($paymentData['supplier_id'])): ?>
                                                <p><strong><?= __('name') ?>:</strong> <?php echo htmlspecialchars($paymentData['supplier_name']); ?></p>
                                                <p><strong><?= __('email') ?>:</strong> <?php echo htmlspecialchars($paymentData['supplier_email']); ?></p>
                                                <p><strong><?= __('phone') ?>:</strong> <?php echo htmlspecialchars($paymentData['supplier_phone']); ?></p>
                                                <a href="supplier_detail.php?id=<?php echo h($paymentData['supplier_id']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-external-link mr-1"></i> <?= __('view_supplier_details') ?>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted"><?= __('no_supplier_associated_with_this_payment') ?></p>
                                            <?php endif; ?>
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
                            <ul class="nav nav-tabs mb-3" id="transactionTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="client-tab" data-toggle="tab" href="#client" role="tab"><?= __('client_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="supplier-tab" data-toggle="tab" href="#supplier" role="tab"><?= __('supplier_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="main-account-tab" data-toggle="tab" href="#main-account" role="tab"><?= __('main_account_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="all-tab" data-toggle="tab" href="#all" role="tab"><?= __('all_transactions') ?></a>
                                </li>
                            </ul>
                            <div class="tab-content" id="transactionTabContent">
                                <!-- Client Transactions -->
                                <div class="tab-pane fade show active" id="client" role="tabpanel">
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
                                                        <span class="badge badge-info"><?= __('additional_payment') ?></span>
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
                                    <div class="alert alert-info"><?= __('no_client_transactions_found_for_this_payment') ?></div>
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
                                                        <span class="badge badge-info"><?= __('additional_payment') ?></span>
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
                                    <div class="alert alert-info"><?= __('no_supplier_transactions_found_for_this_payment') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Main Account Transactions -->
                                <div class="tab-pane fade" id="main-account" role="tabpanel">
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
                                                        <span class="badge badge-info"><?= __('additional_payment') ?></span>
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
                                    <div class="alert alert-info"><?= __('no_main_account_transactions_found_for_this_payment') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- All Transactions -->
                                <div class="tab-pane fade" id="all" role="tabpanel">
                                    <?php if (!empty($clientTransactions) || !empty($supplierTransactions) || !empty($mainAccountTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('party') ?></th>
                                                    <th><?= __('transaction') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $allTransactions = array_merge($clientTransactions, $supplierTransactions, $mainAccountTransactions);
                                                
                                                // Sort by date, most recent first
                                                usort($allTransactions, function($a, $b) {
                                                    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
                                                });
                                                
                                                foreach ($allTransactions as $transaction): 
                                                ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?= __('additional_payment') ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            if ($transaction['transaction_type'] == 'Client') echo 'primary';
                                                            elseif ($transaction['transaction_type'] == 'Supplier') echo 'warning';
                                                            elseif ($transaction['transaction_type'] == 'Main Account') echo 'info';
                                                            else echo 'secondary';
                                                        ?>">
                                                            <?php echo h($transaction['transaction_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo isset($transaction['currency']) ? htmlspecialchars($transaction['currency']) . ' ' : ''; ?>
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
                                    <div class="alert alert-info"><?= __('no_transactions_found_for_this_payment') ?></div>
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
                            
                            <a href="edit_payment.php?id=<?php echo h($paymentId); ?>" class="btn btn-info">
                                <i class="feather icon-edit mr-1"></i> <?= __('edit_payment') ?>
                            </a>
                            
                            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
                                <i class="feather icon-trash-2 mr-1"></i> <?= __('delete_payment') ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Delete Confirmation Modal -->
                    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= __('confirm_delete') ?></h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p><?= __('are_you_sure_you_want_to_delete_this_payment') ?></p>
                                    <p><strong><?= __('type') ?>:</strong> <?php echo htmlspecialchars($paymentData['payment_type']); ?></p>
                                    <p><strong><?= __('description') ?>:</strong> <?php echo htmlspecialchars($paymentData['description']); ?></p>
                                    <p><strong><?= __('amount') ?>:</strong> <?php echo htmlspecialchars($paymentData['currency']) . ' ' . htmlspecialchars($paymentData['sold_amount']); ?></p>
                                </div>
                                <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                    <a href="delete_payment.php?id=<?php echo h($paymentId); ?>" class="btn btn-danger"><?= __('delete') ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include the footer
include '../includes/admin_footer.php';
?> 