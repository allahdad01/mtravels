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
$visaId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$visaData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

// Check if ID is provided
if (!$visaId) {
    $error = "No visa application ID provided";
} else {
    // Get visa application details with related info
    $visaQuery = "SELECT
            va.*,
            c.name AS client_name,
            c.email AS client_email,
            c.phone AS client_phone,
            s.name AS supplier_name,
            s.email AS supplier_email,
            s.phone AS supplier_phone
        FROM visa_applications va
        LEFT JOIN clients c ON va.sold_to = c.id
        LEFT JOIN suppliers s ON va.supplier = s.id
        WHERE va.id = ? AND va.tenant_id = ? AND va.branch_id = ? AND (c.id IS NULL OR c.branch_id = ?) AND (s.id IS NULL OR s.branch_id = ?)";

    $stmt = $pdo->prepare($visaQuery);
    $stmt->execute([$visaId, $tenant_id, $branch_id, $branch_id, $branch_id]);
    $visaData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visaData) {
        $error = "Visa application not found";
    } else {
        // Get main account transactions related to this visa
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
            WHERE mat.reference_id = ? AND mat.transaction_of = 'visa_sale' AND mat.tenant_id = ? AND mat.branch_id = ?
            ORDER BY mat.created_at DESC";

        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$visaId, $tenant_id, $branch_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get client transactions related to this visa
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
            WHERE ct.reference_id = ? AND ct.transaction_of = 'visa_sale' AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.created_at DESC";

        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$visaId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get supplier transactions related to this visa
        $supplierTransQuery = "SELECT
                'Supplier' AS transaction_type,
                st.id,
                st.transaction_type AS type,
                st.amount,
                st.remarks AS description,
                st.transaction_of,
                st.transaction_date
            FROM supplier_transactions st
            WHERE st.reference_id = ? AND st.transaction_of = 'visa_sale' AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.transaction_date DESC";

        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$visaId, $tenant_id, $branch_id]);
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
                            <h5 class="m-b-10"><?= __('visa_application_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('visa_application_details') ?></a></li>
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
                    <!-- Visa Application Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-file-text mr-2"></i>
                                <?= __('visa_application_information') ?>
                                <span class="float-right">
                                    <span class="badge badge-<?php 
                                        if ($visaData['status'] == 'Approved') echo 'success';
                                        elseif ($visaData['status'] == 'Processing') echo 'warning';
                                        elseif ($visaData['status'] == 'Rejected') echo 'danger';
                                        elseif ($visaData['status'] == 'Pending') echo 'info';
                                        else echo 'secondary';
                                    ?>">
                                        <?php echo h($visaData['status']); ?>
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
                                                <th style="width: 40%"><?= __('applicant_name') ?></th>
                                                <td><?php echo isset($visaData['applicant_name']) && $visaData['applicant_name'] !== null ? htmlspecialchars($visaData['applicant_name']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('passport_number') ?></th>
                                                <td><?php echo isset($visaData['passport_number']) && $visaData['passport_number'] !== null ? htmlspecialchars($visaData['passport_number']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('phone') ?></th>
                                                <td><?php echo isset($visaData['phone']) && $visaData['phone'] !== null ? htmlspecialchars($visaData['phone']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('country') ?></th>
                                                <td><?php echo isset($visaData['country']) && $visaData['country'] !== null ? htmlspecialchars($visaData['country']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('visa_type') ?></th>
                                                <td><?php echo isset($visaData['visa_type']) && $visaData['visa_type'] !== null ? htmlspecialchars($visaData['visa_type']) : '—'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('sold_amount') ?></th>
                                                <td>
                                                    <strong>
                                                        <?php echo isset($visaData['currency']) && isset($visaData['sold']) ? 
                                                            htmlspecialchars($visaData['currency']) . ' ' . htmlspecialchars($visaData['sold']) : '—'; ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('base_amount') ?></th>
                                                <td><?php echo isset($visaData['currency']) && isset($visaData['base']) ? 
                                                    htmlspecialchars($visaData['currency']) . ' ' . htmlspecialchars($visaData['base']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('profit') ?></th>
                                                <td class="<?php echo (isset($visaData['profit']) && $visaData['profit'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                                    <strong>
                                                        <?php 
                                                        if (isset($visaData['currency']) && isset($visaData['profit'])) {
                                                            echo htmlspecialchars($visaData['currency']) . ' ' . 
                                                                htmlspecialchars($visaData['profit']);
                                                        } else {
                                                            echo '—';
                                                        }
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('applied_date') ?></th>
                                                <td>
                                                    <?php 
                                                    if (!empty($visaData['applied_date'])) {
                                                        echo date('Y-m-d', strtotime($visaData['applied_date']));
                                                    } else {
                                                        echo '<span class="text-muted">Not Available</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('issued_date') ?></th>
                                                <td>
                                                    <?php 
                                                    if (!empty($visaData['issued_date'])) {
                                                        echo date('Y-m-d', strtotime($visaData['issued_date']));
                                                    } else {
                                                        echo '<span class="text-muted">Not Issued Yet</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('status') ?></th>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        if ($visaData['status'] == 'Approved') echo 'success';
                                                        elseif ($visaData['status'] == 'Processing') echo 'warning';
                                                        elseif ($visaData['status'] == 'Rejected') echo 'danger';
                                                        elseif ($visaData['status'] == 'Pending') echo 'info';
                                                        else echo 'secondary';
                                                    ?>">
                                                        <?php echo isset($visaData['status']) ? htmlspecialchars($visaData['status']) : '—'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('created_at') ?></th>
                                                <td><?php echo isset($visaData['created_at']) ? date('Y-m-d H:i', strtotime($visaData['created_at'])) : '—'; ?></td>
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
                                            <?php if (isset($visaData['client_id']) && !empty($visaData['client_id'])): ?>
                                                <p><strong><?= __('name') ?>:</strong> <?php echo isset($visaData['client_name']) ? htmlspecialchars($visaData['client_name']) : '—'; ?></p>
                                                <p><strong><?= __('email') ?>:</strong> <?php echo isset($visaData['client_email']) ? htmlspecialchars($visaData['client_email']) : '—'; ?></p>
                                                <p><strong><?= __('phone') ?>:</strong> <?php echo isset($visaData['client_phone']) ? htmlspecialchars($visaData['client_phone']) : '—'; ?></p>
                                                <a href="client_detail.php?id=<?php echo h($visaData['client_id']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-external-link mr-1"></i> <?= __('view_client_details') ?>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted"><?= __('no_client_associated_with_this_application') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-briefcase mr-2"></i><?= __('supplier_information') ?></h6>
                                            <?php if (isset($visaData['supplier_id']) && !empty($visaData['supplier_id'])): ?>
                                                <p><strong><?= __('name') ?>:</strong> <?php echo isset($visaData['supplier_name']) ? htmlspecialchars($visaData['supplier_name']) : '—'; ?></p>
                                                <p><strong><?= __('email') ?>:</strong> <?php echo isset($visaData['supplier_email']) ? htmlspecialchars($visaData['supplier_email']) : '—'; ?></p>
                                                <p><strong><?= __('phone') ?>:</strong> <?php echo isset($visaData['supplier_phone']) ? htmlspecialchars($visaData['supplier_phone']) : '—'; ?></p>
                                                <a href="supplier_detail.php?id=<?php echo h($visaData['supplier_id']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-external-link mr-1"></i> <?= __('view_supplier_details') ?>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted"><?= __('no_supplier_associated_with_this_application') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($visaData['remarks'])): ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-info mr-2"></i><?= __('remarks') ?></h6>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($visaData['remarks'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
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
                                                        <span class="badge badge-info">Visa</span>
                                                    </td>
                                                    <td><?php echo isset($transaction['type']) ? ucfirst(strtolower($transaction['type'])) : '—'; ?></td>
                                                    <td>
                                                        <span class="<?php echo (isset($transaction['type']) && strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php 
                                                            if (isset($transaction['currency']) && isset($transaction['amount'])) {
                                                                echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']);
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_client_transactions_found_for_this_application') ?></div>
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
                                                        <span class="badge badge-info">Visa</span>
                                                    </td>
                                                    <td><?php echo isset($transaction['type']) ? ucfirst(strtolower($transaction['type'])) : '—'; ?></td>
                                                    <td>
                                                        <span class="<?php echo (isset($transaction['type']) && strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php 
                                                            if (isset($transaction['currency']) && isset($transaction['amount'])) {
                                                                echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']);
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_supplier_transactions_found_for_this_application') ?></div>
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
                                                        <span class="badge badge-info">Visa</span>
                                                    </td>
                                                    <td><?php echo isset($transaction['type']) ? ucfirst(strtolower($transaction['type'])) : '—'; ?></td>
                                                    <td>
                                                        <span class="<?php echo (isset($transaction['type']) && strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php 
                                                            if (isset($transaction['currency']) && isset($transaction['amount'])) {
                                                                echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']);
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_main_account_transactions_found_for_this_application') ?></div>
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
                                                        <span class="badge badge-info">Visa</span>
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
                                                    <td><?php echo isset($transaction['type']) ? ucfirst(strtolower($transaction['type'])) : '—'; ?></td>
                                                    <td>
                                                        <span class="<?php echo (isset($transaction['type']) && strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php 
                                                            if (isset($transaction['currency']) && isset($transaction['amount'])) {
                                                                echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']);
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_transactions_found_for_this_application') ?></div>
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


<script>
    $(document).ready(function() {
        // Manual initialization of Bootstrap tabs
        $('#transactionTab a').click(function(e) {
            e.preventDefault();
            $(this).tab('show');
        });
    });
</script>

<?php include '../includes/admin_footer.php'; ?>