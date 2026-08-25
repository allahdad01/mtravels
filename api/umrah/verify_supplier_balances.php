<?php
/**
 * Verify supplier balances match sum of transactions.
 *
 * Usage:
 *   php verify_supplier_balances.php --tenant 28 --branch 21
 */

$targetTenant = null;
$targetBranch = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--tenant' && isset($argv[$i + 1])) $targetTenant = (int)$argv[$i + 1];
    if ($argv[$i] === '--branch' && isset($argv[$i + 1])) $targetBranch = (int)$argv[$i + 1];
}

if (!$targetTenant || !$targetBranch) {
    fwrite(STDERR, "Usage: php verify_supplier_balances.php --tenant <id> --branch <id>\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/db.php';

$suppliers = [112, 113, 114];

foreach ($suppliers as $supId) {
    // Get master balance
    $balStmt = $pdo->prepare("SELECT name, balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $balStmt->execute([$supId, $targetTenant, $targetBranch]);
    $sup = $balStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sup) { echo "Supplier #{$supId} not found\n"; continue; }

    // Get last transaction's running balance
    $lastStmt = $pdo->prepare("
        SELECT balance, amount, transaction_type FROM supplier_transactions
        WHERE supplier_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $lastStmt->execute([$supId, $targetTenant, $targetBranch]);
    $last = $lastStmt->fetch(PDO::FETCH_ASSOC);

    // Count transactions
    $cntStmt = $pdo->prepare("
        SELECT COUNT(*) as cnt, SUM(CASE WHEN transaction_type = 'Debit' THEN amount ELSE 0 END) as total_debit,
               SUM(CASE WHEN transaction_type = 'Credit' THEN amount ELSE 0 END) as total_credit
        FROM supplier_transactions
        WHERE supplier_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
    ");
    $cntStmt->execute([$supId, $targetTenant, $targetBranch]);
    $stats = $cntStmt->fetch(PDO::FETCH_ASSOC);

    $txnBalance = $last ? (float)$last['balance'] : 0;
    $masterBalance = (float)$sup['balance'];
    $match = abs($txnBalance - $masterBalance) < 0.01 ? 'OK' : 'MISMATCH';

    echo "Supplier #{$supId} ({$sup['name']}):\n";
    echo "  Master balance: " . number_format($masterBalance, 3) . "\n";
    echo "  Last txn balance: " . number_format($txnBalance, 3) . "\n";
    echo "  Transactions: {$stats['cnt']} | Debits: " . number_format($stats['total_debit'], 3) . " | Credits: " . number_format($stats['total_credit'], 3) . "\n";
    echo "  Status: {$match}\n\n";
}
