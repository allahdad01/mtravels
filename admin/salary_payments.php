<?php
// Initialize the session
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

// Include config file
require_once "../includes/db.php";

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$currency_filter = isset($_GET['currency']) ? $_GET['currency'] : 'all';

// Build query - only for current user
$query = "
    SELECT sp.*, u.name as employee_name, ma.name as account_name
    FROM salary_payments sp
    JOIN users u ON sp.user_id = u.id
    JOIN main_account ma ON sp.main_account_id = ma.id
    WHERE sp.tenant_id = ? AND sp.branch_id = ? AND sp.user_id = ?
";

$params = [$tenant_id, $branch_id, $user_id];

if (!empty($search)) {
    $query .= " AND (sp.receipt LIKE ? OR sp.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($month_filter)) {
    $query .= " AND DATE_FORMAT(sp.payment_for_month, '%Y-%m') = ?";
    $params[] = $month_filter;
}

if ($type_filter !== 'all') {
    $query .= " AND sp.payment_type = ?";
    $params[] = $type_filter;
}

if ($currency_filter !== 'all') {
    $query .= " AND sp.currency = ?";
    $params[] = $currency_filter;
}

$query .= " ORDER BY sp.payment_date DESC, sp.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_usd = 0;
$total_afs = 0;
foreach ($payments as $payment) {
    if ($payment['currency'] === 'USD') {
        $total_usd += $payment['amount'];
    } else {
        $total_afs += $payment['amount'];
    }
}

// Get unique months for filter (only months where current user has payments)
$months_query = "SELECT DISTINCT DATE_FORMAT(payment_for_month, '%Y-%m') as month_value,
                 DATE_FORMAT(payment_for_month, '%M %Y') as month_label
                 FROM salary_payments 
                 WHERE tenant_id = ? AND branch_id = ? AND user_id = ?
                 ORDER BY month_value DESC";
$stmt = $pdo->prepare($months_query);
$stmt->execute([$tenant_id, $branch_id, $user_id]);
$months = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header first (which loads language helpers), then set page title
include '../includes/header.php';
$page_title = __('salary_payments');
?>

<style>
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
    }

    .stats-card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .stats-card h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
    }

    .stats-card p {
        margin: 5px 0 0 0;
        opacity: 0.9;
    }

    .stats-card.usd {
        background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
    }

    .stats-card.afs {
        background: linear-gradient(135deg, #ffb64d 0%, #ff5370 100%);
    }

    .stats-card.total {
        background: linear-gradient(135deg, #4099ff 0%, #73b4ff 100%);
    }

    .payment-type-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .payment-type-regular {
        background-color: #2ed8b6;
        color: white;
    }

    .payment-type-bonus {
        background-color: #4099ff;
        color: white;
    }

    .payment-type-advance {
        background-color: #ffb64d;
        color: white;
    }

    .payment-type-other {
        background-color: #6c757d;
        color: white;
    }

    .description-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .receipt-text {
        font-family: monospace;
        font-size: 12px;
        color: #6c757d;
    }

    .filter-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .export-buttons .btn {
        margin-left: 5px;
    }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <!-- Page Header -->
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <i class="feather icon-dollar-sign mr-2"></i><?= __('my_salary_payments') ?>
                                        </h5>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="print_payroll.php?user_id=<?= $user_id ?>" class="btn btn-light btn-sm" target="_blank">
                                            <i class="feather icon-printer mr-1"></i><?= __('print_my_payroll') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats Cards -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="stats-card usd">
                                        <h3><?= number_format($total_usd, 2) ?> USD</h3>
                                        <p><i class="feather icon-dollar-sign mr-1"></i><?= __('total_usd_payments') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stats-card afs">
                                        <h3><?= number_format($total_afs, 2) ?> AFS</h3>
                                        <p><i class="feather icon-dollar-sign mr-1"></i><?= __('total_afs_payments') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stats-card total">
                                        <h3><?= count($payments) ?></h3>
                                        <p><i class="feather icon-list mr-1"></i><?= __('total_payments') ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="filter-card">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label"><?= __('search') ?></label>
                                        <input type="text" class="form-control" name="search"
                                               placeholder="<?= __('search_by_receipt_or_description') ?>"
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label"><?= __('month') ?></label>
                                        <select class="form-control" name="month">
                                            <option value=""><?= __('all_months') ?></option>
                                            <?php foreach ($months as $m): ?>
                                                <option value="<?= $m['month_value'] ?>" <?= $month_filter == $m['month_value'] ? 'selected' : '' ?>>
                                                    <?= $m['month_label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label"><?= __('payment_type') ?></label>
                                        <select class="form-control" name="type">
                                            <option value="all"><?= __('all_types') ?></option>
                                            <option value="regular" <?= $type_filter == 'regular' ? 'selected' : '' ?>><?= __('regular_salary') ?></option>
                                            <option value="bonus" <?= $type_filter == 'bonus' ? 'selected' : '' ?>><?= __('bonus') ?></option>
                                            <option value="advance" <?= $type_filter == 'advance' ? 'selected' : '' ?>><?= __('advance') ?></option>
                                            <option value="other" <?= $type_filter == 'other' ? 'selected' : '' ?>><?= __('other') ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="feather icon-search"></i>
                                        </button>
                                    </div>
                                </form>
                                <?php if (!empty($search) || !empty($month_filter) || $type_filter !== 'all'): ?>
                                    <div class="mt-2">
                                        <a href="salary_payments.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-refresh-cw mr-1"></i><?= __('clear_filters') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Payments Table -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="feather icon-list mr-2"></i><?= __('my_payment_history') ?> (<?= count($payments) ?>)
                                    </h5>
                                </div>
                                <div class="card-body table-border-style">
                                    <div class="table-responsive">
                                        <table id="salary-payments-table" class="table table-hover">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th><?= __('id') ?></th>
                                                    <th><?= __('account') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('payment_date') ?></th>
                                                    <th><?= __('for_month') ?></th>
                                                    <th><?= __('receipt') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($payments) > 0): ?>
                                                    <?php foreach ($payments as $payment): ?>
                                                        <tr>
                                                            <td><?= $payment['id'] ?></td>
                                                            <td><?= htmlspecialchars($payment['account_name']) ?></td>
                                                            <td>
                                                                <strong><?= number_format($payment['amount'], 2) ?></strong>
                                                                <span class="badge badge-light"><?= $payment['currency'] ?></span>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $type_class = 'payment-type-' . $payment['payment_type'];
                                                                $type_label = ucfirst($payment['payment_type']);
                                                                ?>
                                                                <span class="payment-type-badge <?= $type_class ?>">
                                                                    <?= __($payment['payment_type']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                                            <td><?= date('F Y', strtotime($payment['payment_for_month'])) ?></td>
                                                            <td>
                                                                <span class="receipt-text"><?= $payment['receipt'] ?></span>
                                                            </td>
                                                            <td class="description-cell" title="<?= htmlspecialchars($payment['description']) ?>">
                                                                <?= htmlspecialchars($payment['description']) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center py-4">
                                                            <i class="feather icon-inbox text-muted" style="font-size: 48px;"></i>
                                                            <p class="mt-2 text-muted"><?= __('no_payments_found') ?></p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<!-- DataTables removed - using server-side PHP filtering instead -->

</body>
</html>
