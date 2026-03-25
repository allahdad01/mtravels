<?php
/**
 * Client Additional Payments Page
 * Displays all additional payments for the client
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/language_helpers.php';
require_once '../includes/session_check.php';

$tenant_id = $_SESSION['tenant_id'];
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];
$lang = init_language();

if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Fetch settings
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Fetch total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM additional_payments 
    WHERE client_id = ? AND tenant_id = ?
");
$countStmt->execute([$client_id, $tenant_id]);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total / $per_page);

// Fetch payments
$payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM additional_payments 
        WHERE client_id = ? AND tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Additional payments fetch error: " . $e->getMessage());
    $payments = [];
}

// Calculate summary
$summary = [
    'total_amount' => 0,
    'usd' => 0,
    'afs' => 0
];

foreach ($payments as $payment) {
    $summary['total_amount'] += floatval($payment['sold_amount'] ?? 0);
    if ($payment['currency'] === 'USD') {
        $summary['usd'] += floatval($payment['sold_amount'] ?? 0);
    } else {
        $summary['afs'] += floatval($payment['sold_amount'] ?? 0);
    }
}
?>

<?php include '../includes/header_client.php'; ?>

<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> - Additional Payments</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    
    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .card { border: none; border-radius: 8px; box-shadow: 0 2px 12px rgba(64,153,255,0.08); }
        .card-header { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important; color: white !important; }
        .card-header h6 { color: white; margin: 0; }
        .table { margin-bottom: 0; }
        .table th { background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #dee2e6; }
        .table td { vertical-align: middle; padding: 12px; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .pagination { margin-top: 20px; }
        .pagination .page-link { color: #4099ff; }
        .pagination .page-link:hover { background: #e3f2fd; }
        .pagination .page-item.active .page-link { background: #4099ff; border-color: #4099ff; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .summary-card { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .summary-item { display: flex; justify-content: space-between; align-items: center; margin: 10px 0; }
        .summary-item strong { font-size: 18px; }
    </style>
</head>
<body>
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10"><i class="feather icon-credit-card"></i> Additional Payments</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <?php if (!empty($payments)): ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-item">
                            <span><i class="feather icon-credit-card"></i> Total Payments</span>
                            <strong><?= $total ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-item">
                            <span><i class="feather icon-dollar-sign"></i> USD Total</span>
                            <strong>$<?= number_format($summary['usd'], 2) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-item">
                            <span><i class="feather icon-credit-card"></i> AFS Total</span>
                            <strong><?= number_format($summary['afs'], 2) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-item">
                            <span><i class="feather icon-trending-up"></i> Grand Total</span>
                            <strong>$<?= number_format($summary['usd'], 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h6>Payment History</h6>
                            <span class="badge badge-light float-right">Total: <?= $total ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payments)): ?>
                                <div class="alert alert-info" role="alert">
                                    <i class="feather icon-info"></i> No additional payments found.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Payment ID</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Currency</th>
                                                <th>Status</th>
                                                <th>Method</th>
                                                <th>Description</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($payment['id'] ?? '-') ?></strong></td>
                                                <td><?= htmlspecialchars(ucfirst($payment['payment_type'] ?? '-')) ?></td>
                                                <td>
                                                    <strong style="color: #22c55e; font-size: 16px;">
                                                        <?php if ($payment['currency'] === 'USD'): ?>
                                                            $<?= number_format($payment['sold_amount'] ?? 0, 2) ?>
                                                        <?php else: ?>
                                                            <?= number_format($payment['sold_amount'] ?? 0, 2) ?>
                                                        <?php endif; ?>
                                                    </strong>
                                                </td>
                                                <td><?= htmlspecialchars($payment['currency'] ?? 'USD') ?></td>
                                                <td>
                                                    <span class="badge status-completed">
                                                        <?= htmlspecialchars($payment['status'] ?? 'Completed') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($payment['payment_method'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars(substr($payment['description'] ?? '-', 0, 30)) ?></td>
                                                <td><?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" title="View Details">
                                                        <i class="feather icon-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1">First</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?>">Last</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
