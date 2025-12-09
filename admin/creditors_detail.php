<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

// Initialize variables
$creditorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$creditorData = null;
$transactions = [];
$error = null;

// Check if ID is provided
if (!$creditorId) {
    $error = "No creditor ID provided";
} else {
    // Get creditor details
    $creditorQuery = "SELECT * FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($creditorQuery);
    $stmt->execute([$creditorId, $tenant_id, $branch_id]);
    $creditorData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$creditorData) {
        $error = "Creditor not found";
    } else {
        // Get transactions related to this creditor
        $transactionsQuery = "SELECT
                'Creditor Payment' AS transaction_type,
                ct.id,
                ct.creditor_id,
                ct.amount,
                ct.currency,
                ct.transaction_type,
                ct.description,
                ct.payment_date,
                ct.reference_number,
                ct.created_at
            FROM creditor_transactions ct
            WHERE ct.creditor_id = ?
            AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.payment_date DESC";

        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$creditorId, $tenant_id, $branch_id]);
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
                            <h5 class="m-b-10">Creditor Details</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php">Search</a></li>
                            <li class="breadcrumb-item"><a href="javascript:">Creditor Details</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <a href="search.php" class="btn btn-primary">Back to Search</a>
                <?php else: ?>
                    <!-- Creditor Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-user mr-2"></i>
                                Creditor Information
                                <span class="float-right">
                                    <span class="badge badge-<?php 
                                        if ($creditorData['status'] == 'Active') echo 'success';
                                        elseif ($creditorData['status'] == 'Inactive') echo 'danger';
                                        else echo 'warning';
                                    ?>">
                                        <?php echo h($creditorData['status']); ?>
                                    </span>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Name</th>
                                            <td><?php echo htmlspecialchars($creditorData['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td><?php echo htmlspecialchars($creditorData['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td><?php echo htmlspecialchars($creditorData['phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Address</th>
                                            <td><?php echo htmlspecialchars($creditorData['address']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Current Balance</th>
                                            <td class="<?php echo ($creditorData['balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                <strong>
                                                    <?php echo htmlspecialchars($creditorData['currency']) . ' ' . htmlspecialchars($creditorData['balance']); ?>
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Currency</th>
                                            <td><?php echo htmlspecialchars($creditorData['currency']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    if ($creditorData['status'] == 'Active') echo 'success';
                                                    elseif ($creditorData['status'] == 'Inactive') echo 'danger';
                                                    else echo 'warning';
                                                ?>">
                                                    <?php echo h($creditorData['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Created At</th>
                                            <td><?php echo date('Y-m-d H:i', strtotime($creditorData['created_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-activity mr-2"></i>Transaction History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($transactions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Payment Method</th>
                                            <th>Reference #</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($transaction['payment_date'])); ?></td>
                                            <td>
                                                <span class="<?php echo ($transaction['amount'] < 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars(abs($transaction['amount'])); ?>
                                                    <?php echo ($transaction['amount'] < 0) ? '(Debt Increase)' : '(Payment)'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo isset($transaction['transaction_type']) ? htmlspecialchars($transaction['transaction_type']) : '—'; ?></td>
                                            <td><?php echo isset($transaction['reference_number']) ? htmlspecialchars($transaction['reference_number']) : '—'; ?></td>
                                            <td><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">No transactions found for this creditor.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <a href="search.php" class="btn btn-secondary">
                                <i class="feather icon-arrow-left mr-1"></i> Back to Search
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