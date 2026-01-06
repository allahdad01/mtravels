<?php
/**
 * Branch Addon Payments Management
 * Super Admin interface for recording and viewing branch addon payments
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/BranchAddonManager.php';

$addon_manager = new BranchAddonManager($pdo);

// Helper function to get currency symbol
function getAddonCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AFN' => '؋',
        'AED' => 'د.إ',
        'INR' => '₹',
        'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

// Handle form submission for recording payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $addon_id = intval($_POST['addon_id']);
    $amount = floatval($_POST['amount']);
    $currency = $_POST['currency'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'] ?? '';
    $transaction_id = $_POST['transaction_id'] ?? '';
    $receipt_number = $_POST['receipt_number'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $processed_by = $_SESSION['user_id'];
    
    try {
        $result = $addon_manager->recordAddonPayment($addon_id, 0, $amount, $currency, $payment_method, $transaction_id);
        
        if ($result['success']) {
            // Update addon next renewal date
            $stmt = $pdo->prepare("UPDATE branch_addons SET next_renewal_date = DATE_ADD(?, INTERVAL 1 MONTH) WHERE id = ?");
            $stmt->execute([$payment_date, $addon_id]);
            
            header('Location: branch_addon_payments.php?success=1');
            exit();
        } else {
            $error_message = $result['message'];
        }
    } catch (Exception $e) {
        $error_message = "Error recording payment: " . $e->getMessage();
    }
}

// Get all active branch addons
$active_addons = $addon_manager->getAllBranchAddons('active');

// Get payment history for each addon
$addon_payments = [];
foreach ($active_addons as $addon) {
    $stmt = $pdo->prepare("
        SELECT bap.*, t.name as tenant_name, ba.additional_branches
        FROM branch_addon_payments bap
        JOIN branch_addons ba ON bap.addon_id = ba.id
        JOIN tenants t ON ba.tenant_id = t.id
        WHERE bap.addon_id = ?
        ORDER BY bap.payment_date DESC
    ");
    $stmt->execute([$addon['id']]);
    $addon_payments[$addon['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../includes/header_super_admin.php';
?>

<style>
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}
.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
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
                                    <h5 class="m-b-10">Branch Addon Payments</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="manage_branch_addons.php">Branch Addons</a></li>
                                    <li class="breadcrumb-item">Payments</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        
                        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="feather icon-check-circle"></i> Payment recorded successfully!
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="feather icon-alert-circle"></i> <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>

                        <!-- Record Payment Button -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#recordPaymentModal">
                                    <i class="feather icon-plus-circle mr-2"></i>Record Branch Addon Payment
                                </button>
                            </div>
                        </div>

                        <!-- Active Addons with Payments -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card shadow-lg border-0">
                                    <div class="card-header">
                                        <h4 class="mb-0"><i class="feather icon-credit-card mr-2"></i>Active Branch Addons with Payment History</h4>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php if (empty($active_addons)): ?>
                                        <div class="text-center py-4">
                                            <i class="feather icon-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                            <p class="text-muted">No active branch addons found</p>
                                        </div>
                                        <?php else: ?>
                                        <?php foreach ($active_addons as $addon): ?>
                                        <?php 
                                        $symbol = getAddonCurrencySymbol($addon['currency'] ?? 'USD');
                                        $payments = $addon_payments[$addon['id']] ?? [];
                                        $total_paid = array_sum(array_column($payments, 'amount'));
                                        ?>
                                        <div class="addon-section border-bottom">
                                            <div class="p-3 bg-light d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($addon['tenant_name']) ?></strong>
                                                    <span class="badge badge-success ml-2">+<?= intval($addon['additional_branches']) ?> branches</span>
                                                    <span class="text-muted ml-2">| <?= $symbol . number_format($addon['total_addon_cost'], 2) ?>/month</span>
                                                </div>
                                                <div>
                                                    <span class="text-muted">Total Paid: <?= $symbol . number_format($total_paid, 2) ?></span>
                                                </div>
                                            </div>
                                            <?php if (empty($payments)): ?>
                                            <div class="p-3 text-center text-muted">
                                                <small>No payments recorded yet</small>
                                            </div>
                                            <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Amount</th>
                                                            <th>Method</th>
                                                            <th>Transaction ID</th>
                                                            <th>Receipt</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payments as $payment): ?>
                                                        <tr>
                                                            <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                                            <td><?= $symbol . number_format($payment['amount'], 2) ?></td>
                                                            <td><?= htmlspecialchars($payment['payment_method'] ?: '-') ?></td>
                                                            <td><?= htmlspecialchars($payment['transaction_id'] ?: '-') ?></td>
                                                            <td><?= htmlspecialchars($payment['receipt_number'] ?: '-') ?></td>
                                                            <td>
                                                                <span class="badge badge-<?= $payment['status'] === 'completed' ? 'success' : 'warning' ?>">
                                                                    <?= ucfirst($payment['status']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" role="dialog" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="recordPaymentModalLabel">
                    <i class="feather icon-credit-card mr-2"></i>Record Branch Addon Payment
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">

                    <div class="form-group">
                        <label for="addon_id">Branch Addon *</label>
                        <select class="form-control" id="addon_id" name="addon_id" required>
                            <option value="">Select Branch Addon</option>
                            <?php foreach ($active_addons as $addon): ?>
                            <?php $symbol = getAddonCurrencySymbol($addon['currency'] ?? 'USD'); ?>
                            <option value="<?= $addon['id'] ?>" data-currency="<?= htmlspecialchars($addon['currency'] ?? 'USD') ?>" data-amount="<?= $addon['total_addon_cost'] ?>">
                                <?= htmlspecialchars($addon['tenant_name']) ?> - +<?= intval($addon['additional_branches']) ?> branches (<?= $symbol . number_format($addon['total_addon_cost'], 2) ?>/month)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount *</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="amountSymbol">$</span>
                            </div>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="currency">Currency *</label>
                        <select class="form-control" id="currency" name="currency" required>
                            <option value="USD">USD ($)</option>
                            <option value="AFN">AFN (؋)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                            <option value="AED">AED (د.إ)</option>
                            <option value="INR">INR (₹)</option>
                            <option value="PKR">PKR (₨)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="">Select Method</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="transaction_id">Transaction ID</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="receipt_number">Receipt Number</label>
                            <input type="text" class="form-control" id="receipt_number" name="receipt_number" placeholder="Enter receipt number">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Additional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check-circle mr-1"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
const currencySymbols = {
    'USD': '$',
    'EUR': '€',
    'GBP': '£',
    'JPY': '¥',
    'AFN': '؋',
    'AED': 'د.إ',
    'INR': '₹',
    'PKR': '₨',
};

$(document).ready(function() {
    $('#addon_id').on('change', function() {
        const option = $(this).find('option:selected');
        const currency = option.data('currency') || 'USD';
        const amount = option.data('amount') || '';
        const symbol = currencySymbols[currency] || currency;
        
        $('#currency').val(currency);
        $('#amount').val(amount);
        $('#amountSymbol').text(symbol);
    });
    
    $('#currency').on('change', function() {
        const currency = $(this).val();
        const symbol = currencySymbols[currency] || currency;
        $('#amountSymbol').text(symbol);
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
