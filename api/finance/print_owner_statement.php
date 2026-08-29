<?php
/**
 * print_owner_statement.php
 * Printable owner payment statement showing all transactions for an owner.
 * Params: owner_id, date_from (optional), date_to (optional), csrf_token
 */
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('finance.owner_funds');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';

$ownerId  = (int) ($_GET['owner_id'] ?? 0);
$customName = trim($_GET['custom_name'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

// owner_id=0 with custom_name means custom owner
if ($ownerId <= 0 && $customName === '') { echo 'Invalid owner'; exit; }

// Get owner info (system user or custom)
$owner = ['id' => 0, 'name' => $customName];
if ($ownerId > 0) {
    $ownerStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND tenant_id = ?");
    $ownerStmt->execute([$ownerId, $tenant_id]);
    $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner) { echo 'Owner not found'; exit; }
}

// Get settings
$logoPath = '../../uploads/logo/';
$settings = [];
try {
    $st = $pdo->prepare("SELECT agency_name, logo, address, phone FROM settings WHERE tenant_id = ?");
    $st->execute([$tenant_id]);
    $settings = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

// Get branch
$branch = [];
try {
    $st = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
    $st->execute([$branch_id, $tenant_id]);
    $branch = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

// Build query
$where = ["tenant_id = ?", "branch_id = ?", "transaction_of = 'owner_fund'"];
$params = [$tenant_id, $branch_id];

if ($ownerId > 0) {
    // System user: match by reference_id OR custom name in description
    $where[] = "(reference_id = ? OR description LIKE ?)";
    $params[] = $ownerId;
    $params[] = "%[Owner: {$owner['name']}]%";
} else {
    // Custom name only: match description pattern
    $where[] = "description LIKE ?";
    $params[] = "%[Owner: $customName]%";
}

if ($dateFrom !== '') {
    $where[] = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);
$txnStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE $whereSql ORDER BY created_at ASC");
$txnStmt->execute($params);
$transactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

// Group by currency
$grouped = [];
foreach ($transactions as $t) {
    $grouped[$t['currency']][] = $t;
}
$totals = [];
foreach ($grouped as $cur => $txns) {
    $totals[$cur] = array_sum(array_column($txns, 'amount'));
}

function money($amount) { return number_format((float)$amount, 2); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Statement — <?= h($owner['name']) ?></title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10px; margin: 0 !important; padding: 0 !important; }
            .statement-container { max-width: 100% !important; margin: 0 !important; padding: 0 5px !important; box-shadow: none !important; border: none !important; border-radius: 0 !important; }
            @page { size: A4; margin: 5mm; }
        }
        body { background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; font-size: 11px; }
        .statement-container {
            max-width: 800px; margin: 15px auto; padding: 12px 18px;
            border: 1px solid #ddd; border-radius: 6px; background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stmt-header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 10px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .agency-name { font-size: 14px; font-weight: bold; color: #333; flex: 1; }
        .stmt-title { font-size: 16px; font-weight: bold; color: #2563eb; text-align: center; flex: 2; }
        .company-logo { flex: 1; text-align: right; }
        .company-logo img { max-height: 45px; max-width: 80px; }
        .stmt-meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 11px; color: #555; }
        .stmt-meta strong { color: #333; }
        .stmt-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .stmt-table th { background: #2563eb; color: white; padding: 5px 8px; font-size: 10px; text-transform: uppercase; text-align: left; }
        .stmt-table td { padding: 4px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        .stmt-table tr:nth-child(even) { background: #f9fafb; }
        .stmt-table .text-right { text-align: right; }
        .totals-section { background: #eff6ff; padding: 8px 12px; border-radius: 6px; border: 1px solid #bfdbfe; margin-top: 10px; }
        .totals-title { font-size: 11px; font-weight: 700; color: #1e40af; margin-bottom: 5px; text-transform: uppercase; }
        .total-row { display: flex; justify-content: space-between; font-size: 11px; padding: 2px 0; }
        .total-row .label { color: #555; }
        .total-row .value { font-weight: 700; color: #1d4ed8; }
        .footer-note { text-align: center; font-size: 9px; color: #999; margin-top: 15px; padding-top: 8px; border-top: 1px solid #eee; }
        .print-btn { position: fixed; top: 15px; right: 15px; z-index: 1000; display: flex; gap: 6px; }
        .no-transactions { text-align: center; padding: 30px; color: #999; font-size: 12px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="statement-container">
        <div class="no-print print-btn">
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Close</button>
        </div>

        <!-- Header -->
        <div class="stmt-header">
            <div class="header-row">
                <div class="agency-name">
                    <?= h($settings['agency_name'] ?? '') ?>
                    <?php if (!empty($branch['name'])): ?>
                        <br><small style="color:#64748b;"><?= h($branch['name']) ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="stmt-title">Owner Payment Statement</div>
                </div>
                <div class="company-logo">
                    <img src="<?= $logoPath . h($settings['logo'] ?? '') ?>" alt="Logo">
                </div>
            </div>
        </div>

        <!-- Meta -->
        <div class="stmt-meta">
            <div><strong>Owner:</strong> <?= h($owner['name']) ?></div>
            <div>
                <?php if ($dateFrom && $dateTo): ?>
                    <strong>Period:</strong> <?= h($dateFrom) ?> to <?= h($dateTo) ?>
                <?php elseif ($dateFrom): ?>
                    <strong>From:</strong> <?= h($dateFrom) ?> onwards
                <?php elseif ($dateTo): ?>
                    <strong>Up to:</strong> <?= h($dateTo) ?>
                <?php else: ?>
                    <strong>Period:</strong> All Transactions
                <?php endif; ?>
            </div>
            <div><strong>Date Printed:</strong> <?= date('d M Y H:i') ?></div>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="no-transactions">No transactions found for this owner.</div>
        <?php else: ?>
            <?php foreach ($grouped as $cur => $txns): ?>
                <h6 style="font-size:11px; font-weight:700; color:#1e40af; margin:10px 0 5px; text-transform:uppercase;"><?= h($cur) ?> Transactions</h6>
                <table class="stmt-table">
                    <thead>
                        <tr>
                            <th style="width:15%">Date</th>
                            <th style="width:10%">#</th>
                            <th style="width:15%">Account</th>
                            <th style="width:25%">Purpose</th>
                            <th style="width:15%">Receipt</th>
                            <th class="text-right" style="width:10%">Amount</th>
                            <th class="text-right" style="width:10%">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $runningBalance = 0; ?>
                        <?php foreach ($txns as $t): ?>
                            <?php $runningBalance += (float)$t['amount']; ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                <td><?= (int)$t['id'] ?></td>
                                <td><?= h($t['account_name'] ?? '') ?></td>
                                <td><?= h($t['description'] ?? '') ?></td>
                                <td><?= h($t['receipt'] ?? '—') ?></td>
                                <td class="text-right"><?= money($t['amount']) ?></td>
                                <td class="text-right"><?= money($runningBalance) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Total for this currency -->
                <div class="totals-section">
                    <div class="total-row">
                        <span class="label">Total <?= h($cur) ?>:</span>
                        <span class="value"><?= money($totals[$cur]) ?></span>
                    </div>
                    <div class="total-row">
                        <span class="label">Transactions:</span>
                        <span class="value"><?= count($txns) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="footer-note">
            This is a computer-generated statement. For questions, contact your administrator.
        </div>
    </div>
</div>
</body>
</html>
