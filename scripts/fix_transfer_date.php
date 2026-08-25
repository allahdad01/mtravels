<?php
/**
 * Fix date of specific transfer transactions in main_account_transactions
 * Changes created_at from 2026-08-25 to 2026-08-20
 *
 * Usage:
 *   php scripts/fix_transfer_date.php          (dry run - shows what will change)
 *   php scripts/fix_transfer_date.php --run     (actually executes the update)
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run via CLI.\n");
}

$dryRun = !isset($argv[1]) || $argv[1] !== '--run';

require_once __DIR__ . '/../includes/db.php';

echo "=== Transfer Date Fix Script ===" . PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN (no changes)" : "LIVE RUN") . PHP_EOL;
echo PHP_EOL;

// Find the two transactions: SAR 43725 and USD 11660, created on 2026-08-25
$stmt = $pdo->prepare("
    SELECT id, tenant_id, main_account_id, type, amount, currency, description, 
           created_at, transaction_of, balance
    FROM main_account_transactions
    WHERE DATE(created_at) = '2026-08-25'
      AND transaction_of = 'transfer'
      AND amount IN (43725.000, 11660.000)
      AND currency IN ('SAR', 'USD')
    ORDER BY id
");
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($transactions)) {
    echo "No matching transactions found. Exiting." . PHP_EOL;
    exit(1);
}

echo "Found " . count($transactions) . " transaction(s):" . PHP_EOL;
echo str_repeat('-', 100) . PHP_EOL;

foreach ($transactions as $t) {
    echo "ID:          {$t['id']}" . PHP_EOL;
    echo "Tenant:      {$t['tenant_id']}" . PHP_EOL;
    echo "Account ID:  {$t['main_account_id']}" . PHP_EOL;
    echo "Type:        {$t['type']}" . PHP_EOL;
    echo "Amount:      {$t['amount']} {$t['currency']}" . PHP_EOL;
    echo "Balance:     {$t['balance']}" . PHP_EOL;
    echo "Description: " . substr($t['description'], 0, 80) . "..." . PHP_EOL;
    echo "Current Date:{$t['created_at']}" . PHP_EOL;
    echo "New Date:    2026-08-20 (same time)" . PHP_EOL;
    echo str_repeat('-', 100) . PHP_EOL;
}

if ($dryRun) {
    echo PHP_EOL . "DRY RUN complete. No changes made." . PHP_EOL;
    echo "To apply changes, run: php scripts/fix_transfer_date.php --run" . PHP_EOL;
    exit(0);
}

// Actually update
echo PHP_EOL . "Applying changes..." . PHP_EOL;

$pdo->beginTransaction();

try {
    $updateStmt = $pdo->prepare("
        UPDATE main_account_transactions
        SET created_at = STR_TO_DATE(CONCAT('2026-08-20 ', TIME(created_at)), '%Y-%m-%d %H:%i:%s')
        WHERE id = ?
    ");

    foreach ($transactions as $t) {
        $updateStmt->execute([$t['id']]);
        echo "Updated ID {$t['id']} ({$t['amount']} {$t['currency']})" . PHP_EOL;
    }

    $pdo->commit();
    echo PHP_EOL . "Done. " . count($transactions) . " transaction(s) updated successfully." . PHP_EOL;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Changes rolled back. No data was modified." . PHP_EOL;
    exit(1);
}
