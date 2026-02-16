<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/db.php';
include '../includes/conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;

// Validate account belongs to tenant
$account_check = $pdo->prepare("SELECT id, name FROM main_account WHERE id = ? AND tenant_id = ?");
$account_check->execute([$account_id, $tenant_id]);
$account = $account_check->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo '<div class="alert alert-danger">Account not found or access denied</div>';
    exit();
}

// Get transactions with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 20;
$offset = ($page - 1) * $results_per_page;

// Get transactions
$query = "SELECT
    mat.*
FROM main_account_transactions mat
WHERE mat.main_account_id = ? AND mat.tenant_id = ?
LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$account_id, $tenant_id, $results_per_page, $offset]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM main_account_transactions WHERE main_account_id = ? AND tenant_id = ?";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute([$account_id, $tenant_id]);
$total_transactions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_transactions / $results_per_page);
?>

<div class="table-responsive">
    <table class="table table-lg table-hover">
        <thead class="thead-light">
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Description</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    <i class="feather icon-inbox"></i> No transactions found for this account
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td>
                    <?= htmlspecialchars($transaction['created_at']) ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $transaction['type'] === 'credit' ? 'success' : 'danger' ?>">
                            <i class="feather icon-<?= $transaction['type'] === 'credit' ? 'plus' : 'minus' ?> mr-1"></i>
                            <?= ucfirst($transaction['type']) ?>
                        </span>
                    </td>
                    <td>
                        <strong class="text-<?= $transaction['type'] === 'credit' ? 'success' : 'danger' ?>">
                            <?= $transaction['type'] === 'credit' ? '+' : '-' ?>
                            <?= number_format($transaction['amount'], 2) ?>
                        </strong>
                    </td>
                    <td>
                        <span class="badge badge-light">
                            <?= htmlspecialchars($transaction['currency']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="transaction-description">
                            <div class="description-main">
                                <?= htmlspecialchars($transaction['description']) ?>
                            </div>
                            <?php if ($transaction['transaction_of']): ?>
                            <div class="description-type text-muted small">
                                Type: <?= ucwords(str_replace('_', ' ', $transaction['transaction_of'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($transaction['reference_id']): ?>
                        <span class="badge badge-info">
                            #<?= $transaction['reference_id'] ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>

                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-center mt-3">
    <nav aria-label="Transaction pagination">
        <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadTransactions(<?= $account_id ?>, 1)">
                        <i class="feather icon-chevrons-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadTransactions(<?= $account_id ?>, <?= $page - 1 ?>)">
                        <i class="feather icon-chevron-left"></i>
                    </a>
                </li>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(' . $account_id . ', 1)">1</a></li>';
                if ($start_page > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                    <a class="page-link" href="#" onclick="loadTransactions(' . $account_id . ', ' . $i . ')">' . $i . '</a>
                </li>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(' . $account_id . ', ' . $total_pages . ')">' . $total_pages . '</a></li>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadTransactions(<?= $account_id ?>, <?= $page + 1 ?>)">
                        <i class="feather icon-chevron-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadTransactions(<?= $account_id ?>, <?= $total_pages ?>)">
                        <i class="feather icon-chevrons-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>

<script>
// Function to load transactions (called from parent window)
function loadTransactions(accountId, page) {
    const modal = document.getElementById('transactionsModal');
    const content = document.getElementById('transactionsContent');

    content.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Loading transactions...</p></div>';

    fetch('get_account_transactions.php?account_id=' + accountId + '&page=' + page)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading transactions: ' + error.message + '</div>';
        });
}
</script>

<style>
.transaction-date .date-main {
    font-weight: 600;
    font-size: 0.875rem;
}

.transaction-date .date-time {
    font-size: 0.75rem;
}

.transaction-description .description-main {
    font-weight: 500;
    margin-bottom: 2px;
}

.transaction-description .description-type {
    font-size: 0.75rem;
}

.table-sm th,
.table-sm td {
    padding: 0.5rem 0.75rem;
}
</style>