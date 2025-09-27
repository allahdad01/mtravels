<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a tenant
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id']) || $_SESSION['role'] === 'super_admin') {
    error_log("Unauthorized access attempt to tenant billing: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/conn.php';

// Fetch tenant data
$tenant_id = $_SESSION['tenant_id'];
$stmt = $conn->prepare("SELECT name FROM tenants WHERE id = ? AND status != 'deleted'");
$stmt->bind_param('i', $tenant_id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tenant) {
    header('Location: ../login.php');
    exit();
}

// Fetch current subscription
$stmt = $conn->prepare("SELECT ts.id, ts.plan_id, ts.status, ts.start_date, ts.end_date, p.name as plan_name, p.price 
                        FROM tenant_subscriptions ts 
                        JOIN plans p ON ts.plan_id = p.id 
                        WHERE ts.tenant_id = ? AND ts.status = 'active' 
                        ORDER BY ts.end_date DESC LIMIT 1");
$stmt->bind_param('i', $tenant_id);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch payment history
$stmt = $conn->prepare("SELECT p.id, p.amount, p.payment_method, p.payment_status, p.transaction_id, p.payment_date 
                        FROM tenant_payments p 
                        WHERE p.tenant_id = ? 
                        ORDER BY p.payment_date DESC");
$stmt->bind_param('i', $tenant_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle payment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $subscription_id = (int)$_POST['subscription_id'];
    $transaction_id = 'TXN' . uniqid(); // Mock transaction ID
    $payment_status = 'completed'; // Mock status; integrate with payment gateway for real status

    $stmt = $conn->prepare("INSERT INTO tenant_payments (tenant_id, subscription_id, amount, payment_method, payment_status, transaction_id, payment_date) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('iidsds', $tenant_id, $subscription_id, $amount, $payment_method, $payment_status, $transaction_id);
    if ($stmt->execute()) {
        // Extend subscription
        $months = 1; // Default to 1 month; adjust based on plan or input
        $stmt_extend = $conn->prepare("UPDATE tenant_subscriptions SET end_date = DATE_ADD(end_date, INTERVAL ? MONTH) WHERE id = ?");
        $stmt_extend->bind_param('ii', $months, $subscription_id);
        $stmt_extend->execute();
        $stmt_extend->close();

        // Log to audit_logs
        $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'create_payment', 'payment', ?, ?)");
        $details = "Created payment of $amount via $payment_method";
        $stmt_log->bind_param('iis', $_SESSION['user_id'], $stmt->insert_id, $details);
        $stmt_log->execute();
        $stmt_log->close();

        header('Location: tenant_billing.php?success=Payment created and subscription extended');
    } else {
        header('Location: tenant_billing.php?error=Failed to create payment');
    }
    $stmt->close();
    exit();
}
?>

<?php include '../includes/header.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container dark:bg-gray-900">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10 text-2xl font-semibold text-gray-800 dark:text-gray-100"><?= __('tenant_billing') ?></h5>
                                </div>
                                <ul class="breadcrumb flex space-x-2 text-sm text-gray-500 dark:text-gray-400">
                                    <li class="breadcrumb-item"><a href="tenant_dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('billing') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <!-- Alerts -->
                        <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success bg-green-100 text-green-800 p-4 rounded-lg mb-4"><?= htmlspecialchars($_GET['success']) ?></div>
                        <?php elseif (isset($_GET['error'])): ?>
                        <div class="alert alert-danger bg-red-100 text-red-800 p-4 rounded-lg mb-4"><?= htmlspecialchars($_GET['error']) ?></div>
                        <?php endif; ?>

                        <!-- Subscription Info -->
                        <div class="row mb-6">
                            <div class="col-md-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><?= __('current_subscription') ?></h3>
                                    <?php if ($subscription): ?>
                                    <div class="mt-4">
                                        <p><strong><?= __('plan') ?>:</strong> <?= htmlspecialchars($subscription['plan_name']) ?></p>
                                        <p><strong><?= __('status') ?>:</strong> <span class="badge <?= $subscription['status'] === 'active' ? 'bg-green-500' : 'bg-red-500' ?> text-white px-2 py-1 rounded"><?= ucfirst($subscription['status']) ?></span></p>
                                        <p><strong><?= __('start_date') ?>:</strong> <?= date('M d, Y', strtotime($subscription['start_date'])) ?></p>
                                        <p><strong><?= __('end_date') ?>:</strong> <?= date('M d, Y', strtotime($subscription['end_date'])) ?></p>
                                        <p><strong><?= __('price') ?>:</strong> $<?= number_format($subscription['price'], 2) ?></p>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-gray-600 dark:text-gray-300"><?= __('no_active_subscription') ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Create Payment Form -->
                        <?php if ($subscription): ?>
                        <div class="row mb-6">
                            <div class="col-md-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><?= __('make_payment') ?></h3>
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="relative">
                                                <input type="number" step="0.01" class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" name="amount" value="<?= $subscription['price'] ?>" required>
                                                <label class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('amount') ?></label>
                                            </div>
                                            <div class="relative">
                                                <select class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" name="payment_method" required>
                                                    <option value="credit_card"><?= __('credit_card') ?></option>
                                                    <option value="paypal"><?= __('paypal') ?></option>
                                                    <option value="bank_transfer"><?= __('bank_transfer') ?></option>
                                                </select>
                                                <label class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('payment_method') ?></label>
                                            </div>
                                        </div>
                                        <button type="submit" name="create_payment" class="btn btn-primary mt-4 flex items-center">
                                            <i class="feather icon-dollar-sign mr-2"></i><?= __('submit_payment') ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Payment History -->
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('payment_history') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-bordered dark:text-gray-100">
                                                <thead class="bg-gray-100 dark:bg-gray-700">
                                                    <tr>
                                                        <th><?= __('amount') ?></th>
                                                        <th><?= __('payment_method') ?></th>
                                                        <th><?= __('status') ?></th>
                                                        <th><?= __('transaction_id') ?></th>
                                                        <th><?= __('payment_date') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($payments)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-gray-600 dark:text-gray-300"><?= __('no_payments_found') ?></td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($payments as $payment): ?>
                                                    <tr>
                                                        <td>$<?= number_format($payment['amount'], 2) ?></td>
                                                        <td><?= ucfirst($payment['payment_method']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $payment['payment_status'] === 'completed' ? 'bg-green-500' : ($payment['payment_status'] === 'pending' ? 'bg-yellow-500' : 'bg-red-500') ?> text-white px-2 py-1 rounded">
                                                                <?= ucfirst($payment['payment_status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></td>
                                                        <td><?= date('M d, Y H:i A', strtotime($payment['payment_date'])) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>