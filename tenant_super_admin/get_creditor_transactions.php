<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/db.php';
// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

// Get creditor ID from request
$creditor_id = isset($_GET['creditor_id']) ? (int)$_GET['creditor_id'] : 0;

if (!$creditor_id) {
    echo '<div class="alert alert-danger">Invalid creditor ID</div>';
    exit();
}

// Verify creditor belongs to this tenant
$creditor_check = $pdo->prepare("SELECT c.id, c.name, c.currency FROM creditors c WHERE c.id = ? AND c.tenant_id = ?");
$creditor_check->execute([$creditor_id, $tenant_id]);
$creditor = $creditor_check->fetch(PDO::FETCH_ASSOC);

if (!$creditor) {
    echo '<div class="alert alert-danger">Creditor not found</div>';
    exit();
}

function currency_symbol($currency) {
    $symbols = [
        'USD'    => '$',
        'AFS'    => '؋',
        'EUR'    => '€',
        'DARHAM' => 'د.إ',
    ];
    return $symbols[strtoupper($currency ?? '')] ?? '';
}

$sym = currency_symbol($creditor['currency']);

// Get transactions for this creditor
$query = "SELECT ct.*
          FROM creditor_transactions ct
          WHERE ct.creditor_id = ? AND ct.tenant_id = ?
          ORDER BY ct.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$creditor_id, $tenant_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate running balance
$running_balance = 0;
foreach ($transactions as &$transaction) {
    if ($transaction['transaction_type'] === 'debit') {
        $running_balance += $transaction['amount'];
    } else {
        $running_balance -= $transaction['amount'];
    }
    $transaction['running_balance'] = $running_balance;
}
unset($transaction); // Break reference

// Get summary
$summary_query = "SELECT
    COUNT(*) as total_transactions,
    COUNT(CASE WHEN transaction_type = 'debit' THEN 1 END) as debit_count,
    COUNT(CASE WHEN transaction_type = 'credit' THEN 1 END) as credit_count,
    SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debits,
    SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credits
FROM creditor_transactions
WHERE creditor_id = ? AND tenant_id = ?";

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$creditor_id, $tenant_id]);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
.transaction-history {
    max-height: 600px;
    overflow-y: auto;
}

.transaction-item {
    border-left: 4px solid;
    margin-bottom: 15px;
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.transaction-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.transaction-item.debit {
    border-left-color: #28a745;
    background: linear-gradient(135deg, #f8fff8 0%, #f0f8f0 100%);
}

.transaction-item.credit {
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #fff8f8 0%, #f8f0f0 100%);
}

.transaction-amount {
    font-size: 18px;
    font-weight: bold;
}

.transaction-amount.debit {
    color: #28a745;
}

.transaction-amount.credit {
    color: #dc3545;
}

.transaction-meta {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

.balance-summary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.balance-summary h5 {
    margin-bottom: 15px;
    font-weight: 600;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.summary-item {
    text-align: center;
}

.summary-item .value {
    font-size: 24px;
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

.summary-item .label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<!-- Transaction Summary -->
<div class="balance-summary">
    <h5><i class="feather icon-bar-chart-2 mr-2"></i>Transaction Summary - <?= htmlspecialchars($creditor['name']) ?></h5>
    <div class="summary-grid">
        <div class="summary-item">
            <span class="value"><?= number_format($summary['total_transactions'] ?? 0) ?></span>
            <span class="label">Total Transactions</span>
        </div>
        <div class="summary-item">
            <span class="value"><?= number_format($summary['debit_count'] ?? 0) ?></span>
            <span class="label">Debit Transactions</span>
        </div>
        <div class="summary-item">
            <span class="value"><?= number_format($summary['credit_count'] ?? 0) ?></span>
            <span class="label">Credit Transactions</span>
        </div>
        <div class="summary-item">
            <span class="value"><?= $sym ?><?= number_format(($summary['total_debits'] ?? 0) - ($summary['total_credits'] ?? 0), 2) ?></span>
            <span class="label">Current Balance</span>
        </div>
    </div>
</div>

<!-- Transaction History -->
<div class="transaction-history">
    <?php if (empty($transactions)): ?>
        <div class="text-center py-5">
            <i class="feather icon-inbox" style="font-size: 48px; color: #6c757d;"></i>
            <h5 class="mt-3 text-muted">No Transactions Found</h5>
            <p class="text-muted">This creditor has no transaction history yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($transactions as $transaction): ?>
            <div class="transaction-item <?= $transaction['transaction_type'] ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="transaction-details">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-<?= $transaction['transaction_type'] === 'debit' ? 'success' : 'danger' ?> mr-2">
                                <i class="feather icon-<?= $transaction['transaction_type'] === 'debit' ? 'plus' : 'minus' ?> mr-1"></i>
                                <?= ucfirst($transaction['transaction_type']) ?>
                            </span>
                            <small class="text-muted">
                                <?= date('M d, Y', strtotime($transaction['created_at'])) ?> at <?= date('H:i', strtotime($transaction['created_at'])) ?>
                            </small>
                        </div>

                        <div class="transaction-description mb-2">
                            <strong><?= htmlspecialchars($transaction['description'] ?? 'No description') ?></strong>
                        </div>

                        <div class="transaction-meta">
                            <?php if (!empty($transaction['reference_number'])): ?>
                                <span class="ml-3">Ref: <?= htmlspecialchars($transaction['reference_number']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="transaction-amounts text-right">
                        <div class="transaction-amount <?= $transaction['transaction_type'] ?>">
                            <?= $transaction['transaction_type'] === 'debit' ? '+' : '-' ?><?= $sym ?><?= number_format($transaction['amount'], 2) ?>
                        </div>
                        <div class="transaction-balance text-muted">
                            Balance: <?= $sym ?><?= number_format($transaction['running_balance'], 2) ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($transaction['remarks'])): ?>
                    <div class="transaction-remarks mt-2 pt-2 border-top">
                        <small class="text-muted">
                            <i class="feather icon-message-square mr-1"></i>
                            <?= htmlspecialchars($transaction['remarks']) ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($transactions)): ?>
<div class="mt-3 text-center">
    <small class="text-muted">
        Showing <?= count($transactions) ?> transactions
    </small>
</div>
<?php endif; ?>