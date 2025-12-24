<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include database connection
include '../includes/db.php';

// Initialize variables
$debtorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$debtorData = null;
$transactions = [];
$error = null;

// Check if ID is provided
if (!$debtorId) {
    $error = "No debtor ID provided";
} else {
    // Get debtor details
    $debtorQuery = "SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    
        $stmt = $pdo->prepare($debtorQuery);
        $stmt->execute([$debtorId, $tenant_id, $branch_id]);
    $debtorData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$debtorData) {
        $error = "Debtor not found";
    } else {
        // Get transactions related to this debtor
        $transactionsQuery = "SELECT
                        dt.id,
                        dt.debtor_id,
                        dt.amount,
                        dt.currency,
                        dt.transaction_type,
                        dt.description,
                        dt.reference_number,
                        dt.payment_date AS transaction_date,
                        dt.created_at
                    FROM debtor_transactions dt
                    WHERE dt.debtor_id = ? AND dt.tenant_id = ? AND dt.branch_id = ?
                    ORDER BY dt.payment_date DESC";
        
                $stmt = $pdo->prepare($transactionsQuery);
                $stmt->execute([$debtorId, $tenant_id, $branch_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Include the header
include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/debtors/styles.css">
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('debtor_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('debtor_details') ?></a></li>
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
                    <!-- Debtor Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-user mr-2"></i>
                                <?= __('debtor_information') ?>
                                <span class="float-right">
                                    <span class="badge-<?php 
                                        if (isset($debtorData['status']) && $debtorData['status'] == 'Active') echo 'success';
                                        elseif (isset($debtorData['status']) && $debtorData['status'] == 'Inactive') echo 'danger';
                                        else echo 'warning';
                                    ?>">
                                        <?php echo isset($debtorData['status']) ? htmlspecialchars($debtorData['status']) : 'Unknown'; ?>
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
                                                <th style="width: 40%"><?= __('name') ?></th>
                                                <td><?php echo isset($debtorData['name']) ? htmlspecialchars($debtorData['name']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('email') ?></th>
                                                <td><?php echo isset($debtorData['email']) ? htmlspecialchars($debtorData['email']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('phone') ?></th>
                                                <td><?php echo isset($debtorData['phone']) ? htmlspecialchars($debtorData['phone']) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('address') ?></th>
                                                <td><?php echo isset($debtorData['address']) ? htmlspecialchars($debtorData['address']) : '—'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th style="width: 40%"><?= __('balance') ?></th>
                                                <td class="<?php echo (isset($debtorData['balance']) && $debtorData['balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <strong>
                                                        <?php 
                                                        if (isset($debtorData['currency']) && isset($debtorData['balance'])) {
                                                            echo htmlspecialchars($debtorData['currency']) . ' ' . 
                                                                htmlspecialchars($debtorData['balance']);
                                                        } else {
                                                            echo '—';
                                                        }
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('status') ?></th>
                                                <td>
                                                    <span class="badge-<?php 
                                                        if (isset($debtorData['status']) && $debtorData['status'] == 'Active') echo 'success';
                                                        elseif (isset($debtorData['status']) && $debtorData['status'] == 'Inactive') echo 'danger';
                                                        else echo 'warning';
                                                    ?>">
                                                        <?php echo isset($debtorData['status']) ? htmlspecialchars($debtorData['status']) : 'Unknown'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?= __('created_at') ?></th>
                                                <td><?php echo isset($debtorData['created_at']) ? date('Y-m-d H:i', strtotime($debtorData['created_at'])) : '—'; ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= __('updated_at') ?></th>
                                                <td><?php echo isset($debtorData['updated_at']) ? date('Y-m-d H:i', strtotime($debtorData['updated_at'])) : '—'; ?></td>
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
                                            <th><?= __('description') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Debug output if no transactions
                                        if (empty($transactions) && isset($_SESSION['user_id']) && $_SESSION['user_role'] == 'admin'): 
                                        ?>
                                        <tr>
                                            <td colspan="4">
                                                <div class="alert alert-warning">
                                                    <p><strong><?= __('debug_info_admin_only') ?>:</strong></p>
                                                    <p><?= __('debtor_id') ?>: <?php echo h($debtorId); ?></p>
                                                    <p><?= __('debtor_name') ?>: <?php echo htmlspecialchars($debtorData['name']); ?></p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                            <td>
                                                <span class="badge-<?php 
                                                    echo (isset($transaction['transaction_type']) && strtolower($transaction['transaction_type']) == 'payment') ? 'success' : 'info'; 
                                                ?>">
                                                    <?php echo isset($transaction['transaction_type']) ? ucfirst(strtolower($transaction['transaction_type'])) : '—'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="<?php echo (isset($transaction['transaction_type']) && strtolower($transaction['transaction_type']) == 'payment') ? 'text-success' : 'text-danger'; ?>">
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
                                                if (isset($transaction['description']) && !empty($transaction['description'])) {
                                                    echo htmlspecialchars($transaction['description']);
                                                } elseif (isset($transaction['reference_number']) && !empty($transaction['reference_number'])) {
                                                    echo 'Ref: ' . htmlspecialchars($transaction['reference_number']);
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info"><?= __('no_transactions_found_for_this_debtor') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <a href="search.php" class="btn btn-secondary">
                                <i class="feather icon-arrow-left mr-1"></i> <?= __('back_to_search') ?>
                            </a>
                            
                                <a href="add_payment.php?debtor_id=<?php echo h($debtorId); ?>" class="btn btn-success">
                                <i class="feather icon-dollar-sign mr-1"></i> <?= __('record_payment') ?>
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


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