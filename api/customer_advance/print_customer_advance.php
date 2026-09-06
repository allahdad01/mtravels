<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
require_once '../../includes/db.php';
enforce_auth();
require_permission('finance.expenses');

$tenant_id = (int) $_SESSION['tenant_id'];
$branch_id = (int) $_SESSION['branch_id'];

$customerName = trim($_GET['customer_name'] ?? '');
if (empty($customerName)) {
    die('Customer name is required');
}

// Fetch advances
$stmt = $pdo->prepare("SELECT * FROM customer_advances
    WHERE tenant_id = ? AND branch_id = ? AND customer_name = ?
    ORDER BY advance_date DESC");
$stmt->execute([$tenant_id, $branch_id, $customerName]);
$advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payments
$payStmt = $pdo->prepare("SELECT cap.*, ma.name AS main_account_name
    FROM customer_advance_payments cap
    JOIN customer_advances ca ON ca.id = cap.advance_id
    LEFT JOIN main_account ma ON ma.id = cap.main_account_id
    WHERE cap.tenant_id = ? AND cap.branch_id = ? AND ca.customer_name = ?
    ORDER BY cap.payment_date DESC");
$payStmt->execute([$tenant_id, $branch_id, $customerName]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch agency info
$agencyStmt = $pdo->prepare("SELECT name, phone, email, address FROM settings WHERE tenant_id = ? AND branch_id = ? LIMIT 1");
$agencyStmt->execute([$tenant_id, $branch_id]);
$agency = $agencyStmt->fetch(PDO::FETCH_ASSOC);

$totalAdvance = 0;
$totalIncoming = 0;
$totalOutgoing = 0;
foreach ($advances as $a) $totalAdvance += (float) $a['amount'];
foreach ($payments as $p) {
    if ($p['type'] === 'incoming') $totalIncoming += (float) $p['amount'];
    else $totalOutgoing += (float) $p['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umrah Hawala Statement - <?= htmlspecialchars($customerName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; font-size: 13px; color: #0f172a; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; }
        .agency-name { font-size: 20px; font-weight: 700; }
        .agency-info { font-size: 12px; color: #64748b; margin-top: 4px; }
        .title { font-size: 16px; font-weight: 600; text-align: right; }
        .subtitle { font-size: 12px; color: #64748b; text-align: right; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 2rem; }
        .summary-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
        .summary-card .label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .summary-card .value { font-size: 18px; font-weight: 700; margin-top: 4px; }
        .summary-card .value.green { color: #16a34a; }
        .summary-card .value.red { color: #dc2626; }
        .summary-card .value.blue { color: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        th { background: #f4f6fa; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #64748b; padding: 8px 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        td { padding: 8px 12px; border-bottom: 1px solid #eef1f5; font-size: 12.5px; }
        tr:last-child td { border-bottom: none; }
        .pos { color: #16a34a; font-weight: 600; }
        .neg { color: #dc2626; font-weight: 600; }
        .section-title { font-size: 14px; font-weight: 700; margin: 1.5rem 0 0.75rem; display: flex; align-items: center; gap: 6px; }
        .footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; display: flex; justify-content: space-between; }
        @media print { body { padding: 1rem; } .no-print { display: none; } }
        .print-btn { position: fixed; top: 1rem; right: 1rem; background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .print-btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print</button>

    <div class="header">
        <div>
            <div class="agency-name"><?= htmlspecialchars($agency['name'] ?? 'Travel Agency') ?></div>
            <div class="agency-info">
                <?php if (!empty($agency['phone'])) echo htmlspecialchars($agency['phone']) . '<br>'; ?>
                <?php if (!empty($agency['email'])) echo htmlspecialchars($agency['email']) . '<br>'; ?>
                <?php if (!empty($agency['address'])) echo htmlspecialchars($agency['address']); ?>
            </div>
        </div>
        <div>
            <div class="title">Umrah Hawala Statement</div>
            <div class="subtitle">Customer: <?= htmlspecialchars($customerName) ?></div>
            <div class="subtitle">Generated: <?= date('M d, Y h:i A') ?></div>
        </div>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Total Umrah Hawala</div>
            <div class="value blue"><?= number_format($totalAdvance, 2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Paid by Agency</div>
            <div class="value"><?= number_format($totalOutgoing, 2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Received from Customer</div>
            <div class="value green"><?= number_format($totalIncoming, 2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Outstanding Balance</div>
            <div class="value <?= ($totalAdvance - $totalIncoming) > 0 ? 'red' : 'green' ?>"><?= number_format($totalAdvance - $totalIncoming, 2) ?></div>
        </div>
    </div>

    <div class="section-title">Umrah Hawala</div>
    <table>
        <thead>
            <tr><th>Date</th><th>Supplier</th><th>Amount</th><th>Currency</th><th>Reason</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php if (empty($advances)): ?>
            <tr><td colspan="6" style="text-align:center;color:#94a3b8;">No umrah hawala records found</td></tr>
            <?php else: foreach ($advances as $a): ?>
            <tr>
                <td><?= date('M d, Y', strtotime($a['advance_date'])) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($a['supplier_name']) ?></td>
                <td style="font-weight:600;"><?= number_format($a['amount'], 2) ?></td>
                <td><?= htmlspecialchars($a['currency']) ?></td>
                <td><?= htmlspecialchars($a['reason'] ?: '—') ?></td>
                <td style="text-transform:capitalize;"><?= str_replace('_', ' ', $a['status']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="section-title">Payment History</div>
    <table>
        <thead>
            <tr><th>Date</th><th>Type</th><th>Amount</th><th>Currency</th><th>Account</th><th>Description</th></tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
            <tr><td colspan="6" style="text-align:center;color:#94a3b8;">No payments recorded</td></tr>
            <?php else: foreach ($payments as $p): ?>
            <tr>
                <td><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                <td style="font-weight:600;"><?= $p['type'] === 'incoming' ? '<span style="color:#16a34a;">Incoming</span>' : '<span style="color:#dc2626;">Outgoing</span>' ?></td>
                <td class="<?= $p['type'] === 'incoming' ? 'pos' : 'neg' ?>"><?= $p['type'] === 'incoming' ? '+' : '-' ?><?= number_format($p['amount'], 2) ?></td>
                <td><?= htmlspecialchars($p['currency']) ?></td>
                <td><?= htmlspecialchars($p['main_account_name'] ?: '—') ?></td>
                <td><?= htmlspecialchars($p['description'] ?: '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <span>Generated by <?= htmlspecialchars($_SESSION['user_name'] ?? 'System') ?></span>
        <span>Page 1 of 1</span>
    </div>
</body>
</html>
