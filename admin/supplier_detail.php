<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Start session if not already started

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';

// Initialize variables
$supplierId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$supplierData = null;
$transactions = [];
$error = null;

// Check if ID is provided
if (!$supplierId) {
    $error = "No supplier ID provided";
} else {
    // Get supplier details
    $supplierQuery = "SELECT id, name, contact_person, supplier_type, phone, email, address, currency, balance, created_at, updated_at FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";

    $stmt = $pdo->prepare($supplierQuery);
    $stmt->execute([$supplierId, $tenant_id, $branch_id]);
    $supplierData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplierData) {
        $error = "Supplier not found";
    } else {
        // Get transactions related to this supplier
        $transactionsQuery = "SELECT
                st.id,
                st.supplier_id,
                st.amount,
                st.transaction_type,
                st.remarks AS description,
                st.reference_id,
                st.transaction_of,
                st.transaction_date

            FROM supplier_transactions st
            WHERE st.supplier_id = ? AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.transaction_date DESC";

        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$supplierId, $tenant_id, $branch_id]);
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
                            <h5 class="m-b-10"><?= __('supplier_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('supplier_details') ?></a></li>
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
                    <!-- Supplier Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-briefcase mr-2"></i>
                                <?= __('supplier_information') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('name') ?></th>
                                                <td><?php echo isset($supplierData['name']) ? htmlspecialchars($supplierData['name']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('contact_person') ?></th>
                                                <td><?php echo isset($supplierData['contact_person']) ? htmlspecialchars($supplierData['contact_person']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('supplier_type') ?></th>
                                                <td><?php echo isset($supplierData['supplier_type']) ? htmlspecialchars($supplierData['supplier_type']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('email') ?></th>
                                                <td><?php echo isset($supplierData['email']) ? htmlspecialchars($supplierData['email']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('phone') ?></th>
                                                <td><?php echo isset($supplierData['phone']) ? htmlspecialchars($supplierData['phone']) : '—'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('address') ?></th>
                                                <td><?php echo isset($supplierData['address']) ? htmlspecialchars($supplierData['address']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('balance') ?></th>
                                                <td class="<?php echo (isset($supplierData['balance']) && $supplierData['balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <strong>
                                                        <?php 
                                                        if (isset($supplierData['currency']) && isset($supplierData['balance'])) {
                                                            echo htmlspecialchars($supplierData['currency']) . ' ' . 
                                                                htmlspecialchars($supplierData['balance']);
                                                        } else {
                                                            echo '—';
                                                        }
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('created_at') ?></th>
                                                <td><?php echo isset($supplierData['created_at']) ? date('Y-m-d H:i', strtotime($supplierData['created_at'])) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('updated_at') ?></th>
                                                <td><?php echo isset($supplierData['updated_at']) ? date('Y-m-d H:i', strtotime($supplierData['updated_at'])) : '—'; ?></td>
                                            </tr>
                                        </table>
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
                                                <span class="badge-<?php 
                                                    echo (isset($transaction['transaction_type']) && strtolower($transaction['transaction_type']) == 'credit') ? 'success' : 'info'; 
                                                ?>">
                                                    <?php echo isset($transaction['transaction_type']) ? ucfirst(strtolower($transaction['transaction_type'])) : '—'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="<?php echo (isset($transaction['transaction_type']) && strtolower($transaction['transaction_type']) == 'credit') ? 'text-success' : 'text-danger'; ?>">
                                                    <?php 
                                                    if (isset($transaction['amount'])) {
                                                        echo isset($supplierData['currency']) ? htmlspecialchars($supplierData['currency']) . ' ' : '';
                                                        echo htmlspecialchars($transaction['amount']);
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
                            <div class="alert alert-info"><?= __('no_transactions_found_for_this_supplier') ?></div>
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