<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Load input validation helper
require_once '../includes/InputValidator.php';

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
            m.name AS main_account_name,
            u.name AS created_by_name
            FROM additional_payments ap
            LEFT JOIN users u ON ap.created_by = u.id
            LEFT JOIN main_account m ON ap.main_account_id = m.id
        WHERE ap.id = ? AND ap.tenant_id = ? AND ap.branch_id = ?";

    $stmt = $pdo->prepare($paymentQuery);
    $stmt->execute([$paymentId, $tenant_id, $branch_id]);
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
            WHERE ct.reference_id = ? AND ct.transaction_of = 'additional_payment' AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.created_at DESC";

        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$paymentId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get supplier transactions related to this payment
        $supplierTransQuery = "SELECT
                'Supplier' AS transaction_type,
                st.id,
                st.transaction_type AS type,
                st.amount,
                st.remarks AS description,
                st.transaction_of,
                st.transaction_date
            FROM supplier_transactions st
            WHERE st.reference_id = ? AND st.transaction_of = 'additional_payment' AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.transaction_date DESC";

        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$paymentId, $tenant_id, $branch_id]);
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
            WHERE mat.reference_id = ? AND mat.transaction_of = 'additional_payment' AND mat.tenant_id = ? AND mat.branch_id = ?
            ORDER BY mat.created_at DESC";

        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$paymentId, $tenant_id, $branch_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug main account transactions
        if (empty($mainAccountTransactions)) {
            // Try searching more broadly
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
                WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
                ORDER BY mat.created_at DESC";

            $stmt = $pdo->prepare($mainAccountTransQuery);
            $stmt->execute([$paymentId, $tenant_id, $branch_id]);
            $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Include the header
include '../includes/header.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="feather icon-credit-card mr-2"></i><?php echo __('additional_payment_details'); ?></h5>
                </div>
                <div class="col-md-6 text-end">
                    <a href="search.php" class="btn btn-outline-secondary btn-sm">
                        <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_search'); ?>
                    </a>
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
                                    <span class="t <?php
                                        if ($paymentData['payment_type'] == 'Income') echo 'badge-success';
                                        elseif ($paymentData['payment_type'] == 'Expense') echo 'badge-danger';
                                        else echo 'badge-warning';
                                    ?>">
                                        <?php echo h($paymentData['payment_type']); ?>
                                    </span>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('description') ?></th>
                                                <td><?php echo htmlspecialchars($paymentData['description']); ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('payment_type') ?></th>
                                                <td>
                                                    <span class="t <?php
                                                        if ($paymentData['payment_type'] == 'Income') echo 'badge-success';
                                                        elseif ($paymentData['payment_type'] == 'Expense') echo 'badge-danger';
                                                        else echo 'badge-warning';
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
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('profit') ?></th>
                                                <td class="<?php echo ($paymentData['profit'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                                    <strong><?php echo htmlspecialchars($paymentData['currency']) . ' ' . htmlspecialchars($paymentData['profit']); ?></strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('main_account') ?></th>
                                                <td>
                                                    <?php if (!empty($paymentData['main_account_id'])): ?>
                                                        <?php echo htmlspecialchars($paymentData['main_account_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?= __('not_specified') ?></span>
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
                            </div>

                            <!-- Client & Supplier Information -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-user mr-2"></i><?= __('client_information') ?></h6>
                                            <p class="text-muted"><?= __('no_client_associated_with_this_payment') ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-briefcase mr-2"></i><?= __('supplier_information') ?></h6>
                                            <p class="text-muted"><?= __('no_supplier_associated_with_this_payment') ?></p>
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
                                                        <span class="t badge-info"><?= __('additional_payment') ?></span>
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
                                                        <span class="t badge-info"><?= __('additional_payment') ?></span>
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
                                                        <span class="t badge-info"><?= __('additional_payment') ?></span>
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
                                                        <span class="t badge-info"><?= __('additional_payment') ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="t <?php
                                                            if ($transaction['transaction_type'] == 'Client') echo 'badge-primary';
                                                            elseif ($transaction['transaction_type'] == 'Supplier') echo 'badge-warning';
                                                            elseif ($transaction['transaction_type'] == 'Main Account') echo 'badge-info';
                                                            else echo 'badge-secondary';
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
                        </div>
                    </div>
                    
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Design tokens (inherited from additional_payments.php) ── */
:root {
    --ap-bg:           #f4f6f9;
    --ap-surface:      #ffffff;
    --ap-border:       #e8eaed;
    --ap-border-soft:  #f0f2f5;
    --ap-text:         #111827;
    --ap-text-2:       #6b7280;
    --ap-text-muted:   #9ca3af;
    --ap-accent:       #2563eb;
    --ap-accent-soft:  #eff6ff;
    --ap-accent-mid:   #bfdbfe;
    --ap-green:        #059669;
    --ap-green-soft:   #ecfdf5;
    --ap-amber:        #d97706;
    --ap-amber-soft:   #fffbeb;
    --ap-red:          #dc2626;
    --ap-red-soft:     #fef2f2;
    --ap-sky:          #0284c7;
    --ap-sky-soft:     #f0f9ff;
    --ap-radius:       12px;
    --ap-radius-sm:    8px;
    --ap-shadow:       0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --ap-shadow-hover: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
    --ap-mono:         'Courier New', monospace;
}

/* ── Page header ── */
.page-header.card {
    background: var(--ap-accent);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: var(--ap-shadow-hover);
    border-radius: var(--ap-radius);
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

/* ── Cards ── */
.card {
    background: var(--ap-surface);
    border: 1px solid var(--ap-border);
    border-radius: var(--ap-radius);
    box-shadow: var(--ap-shadow);
    transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.card:hover {
    box-shadow: var(--ap-shadow-hover);
    border-color: var(--ap-accent-mid);
    transform: translateY(-1px);
}

.card-header {
    background: var(--ap-accent);
    color: white;
    border-radius: var(--ap-radius) var(--ap-radius) 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    color: white;
}

/* ── Tables ── */
.table-responsive {
    border-radius: var(--ap-radius-sm);
}

.table {
    margin-bottom: 0;
    color: var(--ap-text);
}

.table thead th {
    background-color: var(--ap-accent-soft);
    border-bottom: 2px solid var(--ap-accent-mid);
    font-weight: 600;
    color: var(--ap-accent);
    padding: 1rem;
}

.table tbody tr:hover {
    background-color: var(--ap-border-soft);
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-color: var(--ap-border);
}

.table-bordered {
    border-color: var(--ap-border);
}

.table-bordered th, .table-bordered td {
    border-color: var(--ap-border);
}

/* ── Status badges ── */
.t {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-primary {
    background-color: var(--ap-accent);
    color: white;
}

.badge-success {
    background-color: var(--ap-green);
    color: white;
}

.badge-danger {
    background-color: var(--ap-red);
    color: white;
}

.badge-warning {
    background-color: var(--ap-amber);
    color: #212529;
}

.badge-info {
    background-color: var(--ap-sky);
    color: white;
}

.badge-secondary {
    background-color: var(--ap-text-muted);
    color: white;
}

/* ── Forms ── */
.form-control {
    border-radius: var(--ap-radius-sm);
    border: 1px solid var(--ap-border);
    color: var(--ap-text);
    background: var(--ap-surface);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}

.form-control:focus {
    border-color: var(--ap-accent);
    box-shadow: 0 0 0 0.2rem var(--ap-accent-soft);
    color: var(--ap-text);
}

/* ── Buttons ── */
.btn-primary {
    background: var(--ap-accent);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: var(--ap-accent);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-secondary {
    background: var(--ap-text-muted);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    color: white;
}

.btn-secondary:hover {
    background: var(--ap-text-2);
    transform: translateY(-1px);
}

.btn-outline-secondary {
    border: 1px solid var(--ap-border);
    color: var(--ap-text);
    background: var(--ap-surface);
}

.btn-outline-secondary:hover {
    background: var(--ap-border-soft);
    border-color: var(--ap-accent);
    color: var(--ap-accent);
}

/* ── Alerts ── */
.alert {
    border-radius: var(--ap-radius-sm);
    border: none;
    padding: 1rem 1.5rem;
}

.alert-info {
    background: var(--ap-sky-soft);
    color: var(--ap-sky);
}

.alert-success {
    background: var(--ap-green-soft);
    color: var(--ap-green);
}

.alert-danger {
    background: var(--ap-red-soft);
    color: var(--ap-red);
}

.alert-warning {
    background: var(--ap-amber-soft);
    color: var(--ap-amber);
}

/* ── Field labels & values ── */
.ap-field-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ap-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ap-field-value {
    font-size: 0.875rem;
    color: var(--ap-text);
    line-height: 1.5;
}

/* ── Text utilities ── */
.text-muted {
    color: var(--ap-text-muted) !important;
}

.text-success {
    color: var(--ap-green) !important;
}

.text-danger {
    color: var(--ap-red) !important;
}

.text-end {
    text-align: right;
}

/* ── Sizing ── */
.h2 {
    font-size: 2.5rem;
}

.h4 {
    font-size: 1.5rem;
}

.h5 {
    font-size: 1.25rem;
}

.h6 {
    font-size: 1rem;
}
</style>

                        <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>



<script>
    $(document).ready(function() {
        // Manual initialization of Bootstrap tabs
        $('#transactionTab a').click(function(e) {
            e.preventDefault();
            $(this).tab('show');
        });
    });
</script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 