<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
enforce_auth();

include '../../includes/db.php';

$agencyBranchId = isset($_GET['branch_id']) ? DbSecurity::validateInput($_GET['branch_id'], 'int') : 0;
$agencyName = trim($_GET['agency_name'] ?? '');
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (!$agencyBranchId && empty($agencyName)) {
    die("Branch ID or agency name is required.");
}

// Agency display name
if ($agencyBranchId) {
    $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->execute([$agencyBranchId, $tenant_id]);
    $agencyBranch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} else {
    $agencyBranch = ['name' => $agencyName];
}

// Settlements
$sql = "SELECT aes.*, e.date AS expense_date, e.description AS expense_description, e.amount AS expense_amount,
        ec.name AS category_name, esc.name AS sub_category_name
        FROM agency_expense_settlements aes
        JOIN expenses e ON e.id = aes.expense_id
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN expense_categories esc ON e.sub_category_id = esc.id
        WHERE aes.tenant_id = ? AND aes.branch_id = ?";
$params = [$tenant_id, $branch_id];

if ($agencyBranchId) {
    $sql .= " AND aes.agency_branch_id = ?";
    $params[] = $agencyBranchId;
} else {
    $sql .= " AND aes.agency_branch_id IS NULL AND aes.agency_name = ?";
    $params[] = $agencyName;
}

if ($startDate && $endDate) {
    $sql .= " AND e.date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}
$sql .= " ORDER BY e.date DESC, aes.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$settlements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payments
$paySql = "SELECT ap.*, ma.name AS main_account_name
    FROM agency_payments ap
    LEFT JOIN main_account ma ON ma.id = ap.main_account_id
    WHERE ap.tenant_id = ? AND ap.branch_id = ?";
$payParams = [$tenant_id, $branch_id];

if ($agencyBranchId) {
    $paySql .= " AND ap.agency_branch_id = ?";
    $payParams[] = $agencyBranchId;
} else {
    $paySql .= " AND ap.agency_branch_id IS NULL AND ap.agency_name = ?";
    $payParams[] = $agencyName;
}

$paySql .= " ORDER BY ap.payment_date DESC";
$payStmt = $pdo->prepare($paySql);
$payStmt->execute($payParams);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// Per-currency totals
$expenseByCurrency = [];
$remainingByCurrency = [];
$paidByCurrency = [];
$settledByCurrency = [];
$allCurrencies = ['USD','AFS','EUR','DARHAM','SAR'];

foreach ($settlements as $s) {
    $cur = $s['currency'];
    if (!isset($expenseByCurrency[$cur])) $expenseByCurrency[$cur] = 0;
    if (!isset($remainingByCurrency[$cur])) $remainingByCurrency[$cur] = 0;
    $expenseByCurrency[$cur] += (float) $s['expense_amount'];
    $remainingByCurrency[$cur] += (float) $s['amount_owed'];
    if ($s['status'] === 'settled') {
        if (!isset($settledByCurrency[$cur])) $settledByCurrency[$cur] = 0;
        $settledByCurrency[$cur]++;
    }
}

foreach ($payments as $p) {
    $cur = $p['currency'];
    if (!isset($paidByCurrency[$cur])) $paidByCurrency[$cur] = 0;
    $paidByCurrency[$cur] += (float) $p['amount'];
}

$settled = $totalExpenses - $totalRemaining;

// Settings & branch info
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) $settings = ['agency_name' => 'Travel Agency'];
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

try {
    $myBranchStmt = $pdo->prepare("SELECT name, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $myBranchStmt->execute([$branch_id, $tenant_id]);
    $myBranch = $myBranchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $myBranch = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlement Report — <?php echo h($agencyBranch['name'] ?? ''); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #1a1a2e; --ink-mid: #4a4a6a; --ink-light: #9090aa;
            --rule: #e2e2ec; --surface: #f7f7fb; --accent: #2563eb;
            --debit: #dc2626; --credit: #16a34a; --paper: #ffffff;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; font-size: 13px; background: var(--surface); color: var(--ink); min-height: 100vh; padding: 32px 20px 60px; }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; max-width: 900px; margin: 0 auto 20px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border: none; border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; transition: opacity .15s; }
        .btn:hover { opacity: .82; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-ghost { background: var(--rule); color: var(--ink-mid); }
        .receipt { max-width: 900px; margin: 0 auto; background: var(--paper); border-radius: 12px; box-shadow: 0 2px 24px rgba(30,30,80,.08); overflow: hidden; }
        .receipt-header { background: var(--ink); color: #fff; padding: 32px 40px 28px; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .header-brand h1 { font-family: 'DM Serif Display', serif; font-size: 20px; font-weight: 400; }
        .header-brand .branch-name { font-size: 12px; font-weight: 500; color: rgba(255,255,255,.55); margin-top: 3px; text-transform: uppercase; letter-spacing: .08em; }
        .header-brand .contact { margin-top: 8px; font-size: 12px; color: rgba(255,255,255,.45); line-height: 1.6; }
        .header-badge { text-align: right; flex-shrink: 0; }
        .header-badge .label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.45); margin-bottom: 4px; }
        .header-badge .doc-title { font-family: 'DM Serif Display', serif; font-size: 26px; color: #fff; line-height: 1; }
        .header-badge .doc-id { font-family: 'DM Mono', monospace; font-size: 13px; color: rgba(255,255,255,.6); margin-top: 5px; }
        .receipt-body { padding: 36px 40px; }
        .section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; color: var(--ink-light); margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid var(--rule); }
        .kv-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0; margin-bottom: 32px; border: 1px solid var(--rule); border-radius: 8px; overflow: hidden; }
        .kv-row { display: contents; }
        .kv-row .kv-key, .kv-row .kv-val { padding: 11px 16px; border-bottom: 1px solid var(--rule); }
        .kv-row:last-child .kv-key, .kv-row:last-child .kv-val { border-bottom: none; }
        .kv-key { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-mid); background: var(--surface); border-right: 1px solid var(--rule); }
        .kv-val { font-size: 14px; color: var(--ink); background: var(--paper); font-weight: 600; }
        .kv-val.green { color: var(--credit); }
        .kv-val.red { color: var(--debit); }
        table.tx-table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-bottom: 32px; }
        .tx-table thead tr { background: var(--surface); }
        .tx-table th { padding: 10px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--ink-light); border-bottom: 2px solid var(--rule); }
        .tx-table td { padding: 11px 14px; border-bottom: 1px solid var(--rule); color: var(--ink); }
        .tx-table tbody tr:last-child td { border-bottom: none; }
        .tx-table .mono { font-family: 'DM Mono', monospace; }
        .tx-table .badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .badge-settled { background: #f0fdf4; color: var(--credit); }
        .badge-pending { background: #fffbeb; color: #b45309; }
        .badge-partial { background: #eff6ff; color: #1d4ed8; }
        .amount-strip { border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .amount-strip.green { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .amount-strip.red { background: #fef2f2; border: 1px solid #fecaca; }
        .amount-strip.blue { background: #eff6ff; border: 1px solid #bfdbfe; }
        .amount-strip .as-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }
        .amount-strip.green .as-label { color: #15803d; }
        .amount-strip.red .as-label { color: #b91c1c; }
        .amount-strip.blue .as-label { color: #1d4ed8; }
        .amount-strip .as-value { font-family: 'DM Mono', monospace; font-size: 20px; font-weight: 500; }
        .amount-strip.green .as-value { color: var(--credit); }
        .amount-strip.red .as-value { color: var(--debit); }
        .amount-strip.blue .as-value { color: var(--accent); }
        .receipt-footer { border-top: 1px solid var(--rule); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; background: var(--surface); }
        .receipt-footer .generated { font-size: 11px; color: var(--ink-light); }
        .receipt-footer .tagline { font-size: 11px; font-style: italic; color: var(--ink-light); }
        @media print { body { background: #fff; padding: 0; } .toolbar { display: none !important; } .receipt { box-shadow: none; border-radius: 0; max-width: 100%; } }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn btn-ghost" onclick="window.close()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        Close
    </button>
    <button class="btn btn-primary" onclick="window.print()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print Report
    </button>
</div>

<div class="receipt">
    <div class="receipt-header">
        <div class="header-brand">
            <?php if (!empty($settings['logo'])): ?>
                <img src="../../uploads/logo/<?php echo h($settings['logo']); ?>" alt="Logo" style="height:auto;max-height:48px;max-width:180px;margin-bottom:10px;display:block;object-fit:contain;">
            <?php endif; ?>
            <h1><?php echo h($settings['agency_name']); ?></h1>
            <?php if (!empty($myBranch['name'])): ?>
                <div class="branch-name"><?php echo h($myBranch['name']); ?></div>
            <?php endif; ?>
            <div class="contact">
                <?php if (!empty($myBranch['address'])): ?><?php echo h($myBranch['address']); ?><br><?php endif; ?>
                <?php if (!empty($myBranch['phone'])): ?><?php echo h($myBranch['phone']); ?><?php endif; ?>
            </div>
        </div>
        <div class="header-badge">
            <div class="label">Document</div>
            <div class="doc-title">Settlement Report</div>
            <div class="doc-id"><?php echo h($agencyBranch['name'] ?? ''); ?></div>
            <?php if ($startDate && $endDate): ?>
                <div class="doc-id"><?php echo h($startDate); ?> to <?php echo h($endDate); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="receipt-body">

        <!-- Summary Cards -->
        <div class="section-label">Summary</div>
        <table class="tx-table" style="margin-bottom:32px;">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th style="text-align:right;">Expenses</th>
                    <th style="text-align:right;">Paid</th>
                    <th style="text-align:right;">Remaining</th>
                    <th style="text-align:right;">Settled</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCurrencies as $cur): ?>
                <?php if (isset($expenseByCurrency[$cur]) && $expenseByCurrency[$cur] > 0): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo h($cur); ?></td>
                    <td style="text-align:right;" class="mono"><?php echo h(number_format($expenseByCurrency[$cur], 2)); ?></td>
                    <td style="text-align:right;color:var(--credit);" class="mono"><?php echo h(number_format($paidByCurrency[$cur] ?? 0, 2)); ?></td>
                    <td style="text-align:right;color:var(--debit);" class="mono"><?php echo h(number_format($remainingByCurrency[$cur] ?? 0, 2)); ?></td>
                    <td style="text-align:right;" class="mono"><?php echo h(($settledByCurrency[$cur] ?? 0) . ' / ' . count(array_filter($settlements, fn($s) => $s['currency'] === $cur))); ?></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Expense Settlements -->
        <?php if (!empty($settlements)): ?>
        <div class="section-label">Expense Settlements (<?php echo count($settlements); ?>)</div>
        <table class="tx-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th style="text-align:right;">Expense Amt</th>
                    <th style="text-align:right;">Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($settlements as $s): ?>
                <tr>
                    <td class="mono"><?php echo h(date('Y-m-d', strtotime($s['expense_date']))); ?></td>
                    <td><?php echo h($s['expense_description'] ?: '—'); ?></td>
                    <td><?php echo h($s['category_name'] ?: $s['sub_category_name'] ?: '—'); ?></td>
                    <td style="text-align:right;" class="mono"><?php echo h($s['currency'] . ' ' . number_format($s['expense_amount'], 2)); ?></td>
                    <td style="text-align:right;" class="mono" style="color:<?php echo $s['amount_owed'] > 0 ? 'var(--debit)' : 'var(--credit)'; ?>;"><?php echo h(number_format($s['amount_owed'], 2)); ?></td>
                    <td><span class="badge badge-<?php echo h($s['status']); ?>"><?php echo h($s['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="section-label">Expense Settlements</div>
        <p style="color:var(--ink-light);margin-bottom:32px;">No settlements found for this period.</p>
        <?php endif; ?>

        <!-- Payments -->
        <?php if (!empty($payments)): ?>
        <div class="section-label">Payments Received (<?php echo count($payments); ?>)</div>
        <table class="tx-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Account</th>
                    <th>Ref #</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Currency</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="mono"><?php echo h($p['payment_date']); ?></td>
                    <td><?php echo h($p['description'] ?: '—'); ?></td>
                    <td><?php echo h($p['main_account_name'] ?? '—'); ?></td>
                    <td><?php echo h($p['reference_number'] ?: '—'); ?></td>
                    <td style="text-align:right;" class="mono" style="color:var(--credit);">+<?php echo h(number_format($p['amount'], 2)); ?></td>
                    <td><?php echo h($p['currency']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="section-label">Payments Received</div>
        <p style="color:var(--ink-light);margin-bottom:32px;">No payments recorded for this branch.</p>
        <?php endif; ?>

    </div>

    <div class="receipt-footer">
        <span class="generated">Generated on <?php echo date('F d, Y · H:i:s'); ?></span>
        <span class="tagline">Agency Settlement Report</span>
    </div>
</div>

<script>
    window.onload = function() {
        setTimeout(function() { window.print(); }, 600);
    };
</script>
</body>
</html>
