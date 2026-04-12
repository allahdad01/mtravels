<?php
/**
 * Branch Addon Payments Management
 * Super Admin interface for recording and viewing branch addon payments
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Database connection
require_once '../config.php';
require_once '../includes/db.php';

// Include security module
require_once 'security.php';

// Check if user is a super admin (system administrator, not tenant-based)
enforce_super_admin();

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
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        header('Location: branch_addon_payments.php?error=csrf');
        exit();
    }
    
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
        // Never expose error details to client
        $error_message = "An error occurred while recording the payment. Please try again.";
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
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
    --blue: #4099ff;
    --amber: #ffc107;
}

/* ─── PAGE HEADER ────────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 2rem 2.5rem;
    border: none;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header.card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card .row {
    position: relative;
    z-index: 1;
}

.page-header-content {
    padding: 0.5rem 0;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    line-height: 1.2;
}

.page-title i {
    font-size: 2rem;
    margin-right: 0.75rem;
    opacity: 0.95;
}

.page-subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.10) !important;
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.30) !important;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.22) !important;
    border-color: rgba(255,255,255,0.50) !important;
    transform: translateY(-1px);
}

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    border: none;
    margin-bottom: 1.5rem;
}

.sa-alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.sa-alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.sa-alert-icon {
    flex-shrink: 0;
    font-weight: bold;
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.sa-alert-content {
    flex: 1;
    align-self: center;
}

.sa-alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: inherit;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
}

.sa-alert-close:hover {
    opacity: 0.7;
}

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
}

.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.sa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-shdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.sa-shdr h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.sa-shdr p {
    margin: 4px 0 0 0;
    font-size: 0.75rem;
    color: var(--muted);
}

/* ─── PAYMENT LIST ────────────────────────────────────────── */
.sa-payment-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sa-payment-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.2s ease;
}

.sa-payment-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
}

.spc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.spc-title h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: #333;
}

.spc-subtitle {
    font-size: 0.85rem;
    color: #999;
    margin: 0;
}

.spc-stats {
    display: flex;
    gap: 24px;
}

.stat-item {
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: #4099ff;
}

.spc-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--muted);
}

.spc-empty i {
    font-size: 2rem;
    margin-bottom: 12px;
    display: block;
    opacity: 0.5;
}

.spc-payments {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-item {
    display: grid;
    grid-template-columns: 120px 120px 140px 150px 100px;
    gap: 16px;
    padding: 12px;
    background: #f9f9f9;
    border-radius: 8px;
    align-items: center;
}

.pi-date, .pi-amount, .pi-method, .pi-txn, .pi-status {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pi-label {
    font-size: 0.7rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
}

.pi-value {
    font-size: 0.9rem;
    color: #333;
    font-weight: 500;
}

/* ─── PILLS ───────────────────────────────────────────────── */
.pill {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
    display: inline-block;
}

.pill-green {
    background: rgba(16,185,129,0.12);
    color: #10b981;
}

.pill-amber {
    background: rgba(245,158,11,0.12);
    color: #f59e0b;
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 1024px) {
    .payment-item {
        grid-template-columns: 1fr 1fr;
    }
    
    .spc-stats {
        flex-direction: column;
        gap: 12px;
    }
}

@media (max-width: 768px) {
    .spc-header {
        flex-direction: column;
    }
    
    .payment-item {
        grid-template-columns: 1fr;
    }
    
    .page-header.card {
        padding: 1.5rem;
    }
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-header-content">
                                <h5 class="page-title mb-0">
                                    <i class="feather icon-credit-card mr-2"></i>Branch Addon Payments
                                </h5>
                                <p class="page-subtitle mb-0 mt-2">
                                    Manage and track branch addon payments for all active subscriptions
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <a href="manage_branch_addons.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-arrow-left mr-1"></i>Back to Add-ons
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        
                        <!-- Success/Error Alerts -->
                        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                        <div class="sa-alert sa-alert-success">
                            <div class="sa-alert-icon">✓</div>
                            <div class="sa-alert-content">
                                Payment recorded successfully!
                            </div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="sa-alert sa-alert-danger">
                            <div class="sa-alert-icon">⚠</div>
                            <div class="sa-alert-content">
                                <?= htmlspecialchars($error_message) ?>
                            </div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Section Header -->
                        <div class="sa-shdr" style="margin-bottom: 20px;">
                            <div>
                                <h2>Payment Records</h2>
                                <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Manage and track all branch addon payments</p>
                            </div>
                            <button type="button" class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#recordPaymentModal">
                                <span style="margin-right: 6px;">+</span>Record Payment
                            </button>
                        </div>

                        <!-- Active Addons with Payments -->
                        <div class="sa-payment-list">
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
                                        <div class="sa-payment-card">
                                            <div class="spc-header">
                                                <div class="spc-title">
                                                    <h4><?= htmlspecialchars($addon['tenant_name']) ?></h4>
                                                    <p class="spc-subtitle">+<?= intval($addon['additional_branches']) ?> branches • <?= $symbol . number_format($addon['total_addon_cost'], 2) ?>/month</p>
                                                </div>
                                                <div class="spc-stats">
                                                    <div class="stat-item">
                                                        <span class="stat-label">Total Paid</span>
                                                        <span class="stat-value"><?= $symbol . number_format($total_paid, 2) ?></span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-label">Payments</span>
                                                        <span class="stat-value"><?= count($payments) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (empty($payments)): ?>
                                            <div class="spc-empty">
                                                <i class="feather icon-inbox"></i>
                                                <p>No payments recorded yet</p>
                                            </div>
                                            <?php else: ?>
                                            <div class="spc-payments">
                                                <?php foreach ($payments as $payment): ?>
                                                <div class="payment-item">
                                                    <div class="pi-date">
                                                        <span class="pi-label">Date</span>
                                                        <span class="pi-value"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></span>
                                                    </div>
                                                    <div class="pi-amount">
                                                        <span class="pi-label">Amount</span>
                                                        <span class="pi-value"><?= $symbol . number_format($payment['amount'], 2) ?></span>
                                                    </div>
                                                    <div class="pi-method">
                                                        <span class="pi-label">Method</span>
                                                        <span class="pi-value"><?= htmlspecialchars($payment['payment_method'] ?: '-') ?></span>
                                                    </div>
                                                    <div class="pi-txn">
                                                        <span class="pi-label">Transaction ID</span>
                                                        <span class="pi-value"><?= htmlspecialchars($payment['transaction_id'] ?: '-') ?></span>
                                                    </div>
                                                    <div class="pi-status">
                                                        <span class="pill <?= $payment['status'] === 'completed' ? 'pill-green' : 'pill-amber' ?>">
                                                            <?= ucfirst($payment['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
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
