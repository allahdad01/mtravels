<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../includes/db.php';
require_once '../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$action = $_GET['action'] ?? 'print';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build date conditions
$date_conditions = '';
$params = [$tenant_id, $branch_id];

if ($start_date) {
    $date_conditions .= " AND ca.advance_date >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $date_conditions .= " AND ca.advance_date <= ?";
    $params[] = $end_date;
}

// Fetch all advances with payments
$sql = "SELECT ca.*, 
        (SELECT COALESCE(SUM(cap.amount), 0) FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'incoming') as total_incoming,
        (SELECT COALESCE(SUM(cap.amount), 0) FROM customer_advance_payments cap WHERE cap.advance_id = ca.id AND cap.type = 'outgoing') as total_outgoing
        FROM customer_advances ca 
        WHERE ca.tenant_id = ? AND ca.branch_id = ? 
        $date_conditions
        ORDER BY ca.advance_date DESC, ca.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_advanced = 0;
$total_incoming = 0;
$total_outgoing = 0;

foreach ($advances as &$a) {
    $total_advanced += $a['amount'];
    $total_incoming += $a['total_incoming'];
    $total_outgoing += $a['total_outgoing'];
    $a['pending'] = $a['amount'] - $a['total_incoming'];
}
unset($a);

// Fetch payments with same date filter
$payment_params = [$tenant_id, $branch_id];
$payment_date_conditions = '';
if ($start_date) {
    $payment_date_conditions .= " AND cap.payment_date >= ?";
    $payment_params[] = $start_date;
}
if ($end_date) {
    $payment_date_conditions .= " AND cap.payment_date <= ?";
    $payment_params[] = $end_date;
}

$pay_sql = "SELECT cap.*, ca.customer_name, ca.supplier_name, ma.name as main_account_name
            FROM customer_advance_payments cap
            JOIN customer_advances ca ON cap.advance_id = ca.id
            LEFT JOIN main_account ma ON cap.main_account_id = ma.id
            WHERE ca.tenant_id = ? AND ca.branch_id = ?
            $payment_date_conditions
            ORDER BY cap.payment_date DESC, cap.id DESC";

$pay_stmt = $pdo->prepare($pay_sql);
$pay_stmt->execute($payment_params);
$payments = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_payments_in = 0;
$total_payments_out = 0;
foreach ($payments as $p) {
    if ($p['type'] === 'incoming') $total_payments_in += $p['amount'];
    else $total_payments_out += $p['amount'];
}

$site_name = 'MTravels';
$date_label = ($start_date && $end_date) ? "$start_date to $end_date" : 'All Dates';

if ($action === 'print') {
    print_report($advances, $payments, $total_advanced, $total_incoming, $total_outgoing, $total_payments_in, $total_payments_out, $date_label, $site_name);
} elseif ($action === 'excel') {
    export_excel($advances, $payments, $total_advanced, $total_incoming, $total_outgoing, $total_payments_in, $total_payments_out, $date_label);
}

function print_report($advances, $payments, $total_advanced, $total_incoming, $total_outgoing, $total_payments_in, $total_payments_out, $date_label, $site_name) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Umrah Hawala Report - <?php echo $date_label; ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 13px; color: #333; padding: 20px; }
    .report-header { text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #2563eb; }
    .report-header h1 { font-size: 22px; color: #2563eb; margin-bottom: 4px; }
    .report-header h2 { font-size: 15px; color: #666; font-weight: normal; }
    .report-header .date-range { font-size: 12px; color: #999; margin-top: 4px; }
    .summary-boxes { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .summary-box { flex: 1; min-width: 150px; background: #f8f9fa; border-radius: 8px; padding: 12px; border-left: 3px solid #ccc; }
    .summary-box.blue { border-left-color: #2563eb; }
    .summary-box.green { border-left-color: #16a34a; }
    .summary-box.amber { border-left-color: #d97706; }
    .summary-box.red { border-left-color: #dc2626; }
    .summary-box .label { font-size: 11px; text-transform: uppercase; color: #666; font-weight: 600; }
    .summary-box .value { font-size: 20px; font-weight: 700; margin-top: 2px; }
    h3 { font-size: 14px; margin: 18px 0 8px; color: #2563eb; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th { background: #f4f6fa; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: #666; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e9ecef; }
    td { padding: 7px 10px; border-bottom: 1px solid #eee; }
    tr:hover { background: #f9fafb; }
    .pos { color: #16a34a; font-weight: 600; }
    .neg { color: #dc2626; font-weight: 600; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .badge-pending { background: #fffbeb; color: #b45309; }
    .badge-paid_by_agency { background: #eff6ff; color: #1d4ed8; }
    .badge-completed { background: #f0fdf4; color: #15803d; }
    .footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; font-size: 11px; color: #999; }
    @media print { body { padding: 10px; } .no-print { display: none; } }
</style>
</head>
<body>
<div class="report-header">
    <h1>Umrah Hawala Report</h1>
    <h2><?php echo $site_name; ?></h2>
    <div class="date-range">Date Range: <?php echo $date_label; ?></div>
</div>

<div class="summary-boxes">
    <div class="summary-box amber">
        <div class="label">Total Advanced</div>
        <div class="value"><?php echo number_format($total_advanced, 2); ?></div>
    </div>
    <div class="summary-box green">
        <div class="label">Total Incoming (Customer Pays)</div>
        <div class="value"><?php echo number_format($total_payments_in, 2); ?></div>
    </div>
    <div class="summary-box red">
        <div class="label">Total Outgoing (Pay Supplier)</div>
        <div class="value"><?php echo number_format($total_payments_out, 2); ?></div>
    </div>
    <div class="summary-box blue">
        <div class="label">Net Pending</div>
        <div class="value"><?php echo number_format($total_advanced - $total_payments_in, 2); ?></div>
    </div>
</div>

<h3>Umrah Hawala Records (<?php echo count($advances); ?>)</h3>
<table>
    <thead>
        <tr><th>Date</th><th>Customer</th><th>Phone</th><th>Supplier</th><th>Amount</th><th>Currency</th><th>Incoming</th><th>Outgoing</th><th>Pending</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php if (empty($advances)): ?>
        <tr><td colspan="10" style="text-align:center;color:#999;padding:20px;">No records found</td></tr>
    <?php else: foreach ($advances as $a): ?>
        <tr>
            <td><?php echo date('d M Y', strtotime($a['advance_date'])); ?></td>
            <td><strong><?php echo htmlspecialchars($a['customer_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($a['customer_phone'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($a['supplier_name']); ?></td>
            <td><strong><?php echo number_format($a['amount'], 2); ?></strong></td>
            <td><?php echo $a['currency']; ?></td>
            <td class="pos">+<?php echo number_format($a['total_incoming'], 2); ?></td>
            <td class="neg">-<?php echo number_format($a['total_outgoing'], 2); ?></td>
            <td class="<?php echo $a['pending'] > 0 ? 'neg' : ''; ?>"><?php echo number_format($a['pending'], 2); ?></td>
            <td><span class="badge badge-<?php echo $a['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($a['status'])); ?></span></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if (!empty($payments)): ?>
<h3>Payment History (<?php echo count($payments); ?>)</h3>
<table>
    <thead>
        <tr><th>Date</th><th>Customer</th><th>Supplier</th><th>Type</th><th>Amount</th><th>Currency</th><th>Rate</th><th>Converted</th><th>Account</th><th>Description</th></tr>
    </thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
        <tr>
            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
            <td><?php echo htmlspecialchars($p['customer_name']); ?></td>
            <td><?php echo htmlspecialchars($p['supplier_name']); ?></td>
            <td><span class="<?php echo $p['type'] === 'incoming' ? 'pos' : 'neg'; ?>"><?php echo ucfirst($p['type']); ?></span></td>
            <td class="<?php echo $p['type'] === 'incoming' ? 'pos' : 'neg'; ?>"><?php echo $p['type'] === 'incoming' ? '+' : '-'; ?><?php echo number_format($p['amount'], 2); ?></td>
            <td><?php echo $p['currency']; ?></td>
            <td><?php echo ($p['exchange_rate'] && $p['exchange_rate'] != 1) ? number_format($p['exchange_rate'], 6) : '—'; ?></td>
            <td><?php echo ($p['converted_amount'] && $p['converted_amount'] != $p['amount']) ? number_format($p['converted_amount'], 2) : '—'; ?></td>
            <td><?php echo htmlspecialchars($p['main_account_name'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($p['description'] ?: '—'); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="footer">Generated on <?php echo date('d M Y h:i A'); ?> | <?php echo $site_name; ?></div>
<button class="no-print" onclick="window.print();" style="position:fixed;top:20px;right:20px;padding:10px 20px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;">🖨 Print</button>
</body>
</html>
<?php
}

function export_excel($advances, $payments, $total_advanced, $total_incoming, $total_outgoing, $total_payments_in, $total_payments_out, $date_label) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Umrah_Hawala_Report_' . date('Y-m-d') . '.xls"');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<h1>Umrah Hawala Report</h1>';
    echo '<p>Date Range: ' . $date_label . ' | Generated: ' . date('d M Y h:i A') . '</p>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr style="background:#4472C4;color:#fff;"><th colspan="10" style="font-size:14px;">Summary</th></tr>';
    echo '<tr><td><b>Total Advanced</b></td><td>' . number_format($total_advanced, 2) . '</td>';
    echo '<td><b>Total Incoming</b></td><td>' . number_format($total_payments_in, 2) . '</td>';
    echo '<td><b>Total Outgoing</b></td><td>' . number_format($total_payments_out, 2) . '</td>';
    echo '<td><b>Net Pending</b></td><td>' . number_format($total_advanced - $total_payments_in, 2) . '</td>';
    echo '<td></td><td></td></tr>';
    echo '</table>';

    echo '<br><table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr style="background:#4472C4;color:#fff;"><th colspan="10">Umrah Hawala Records (' . count($advances) . ')</th></tr>';
    echo '<tr style="background:#D6E4F0;"><th>Date</th><th>Customer</th><th>Phone</th><th>Supplier</th><th>Amount</th><th>Currency</th><th>Incoming</th><th>Outgoing</th><th>Pending</th><th>Status</th></tr>';
    foreach ($advances as $a) {
        $pending = $a['amount'] - $a['total_incoming'];
        echo '<tr>';
        echo '<td>' . date('d M Y', strtotime($a['advance_date'])) . '</td>';
        echo '<td>' . htmlspecialchars($a['customer_name']) . '</td>';
        echo '<td>' . htmlspecialchars($a['customer_phone'] ?: '—') . '</td>';
        echo '<td>' . htmlspecialchars($a['supplier_name']) . '</td>';
        echo '<td>' . number_format($a['amount'], 2) . '</td>';
        echo '<td>' . $a['currency'] . '</td>';
        echo '<td>+' . number_format($a['total_incoming'], 2) . '</td>';
        echo '<td>-' . number_format($a['total_outgoing'], 2) . '</td>';
        echo '<td>' . number_format($pending, 2) . '</td>';
        echo '<td>' . str_replace('_', ' ', ucfirst($a['status'])) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    if (!empty($payments)) {
        echo '<br><table border="1" cellpadding="5" cellspacing="0">';
        echo '<tr style="background:#4472C4;color:#fff;"><th colspan="10">Payment History (' . count($payments) . ')</th></tr>';
        echo '<tr style="background:#D6E4F0;"><th>Date</th><th>Customer</th><th>Supplier</th><th>Type</th><th>Amount</th><th>Currency</th><th>Rate</th><th>Converted</th><th>Account</th><th>Description</th></tr>';
        foreach ($payments as $p) {
            echo '<tr>';
            echo '<td>' . date('d M Y', strtotime($p['payment_date'])) . '</td>';
            echo '<td>' . htmlspecialchars($p['customer_name']) . '</td>';
            echo '<td>' . htmlspecialchars($p['supplier_name']) . '</td>';
            echo '<td>' . ucfirst($p['type']) . '</td>';
            echo '<td>' . ($p['type'] === 'incoming' ? '+' : '-') . number_format($p['amount'], 2) . '</td>';
            echo '<td>' . $p['currency'] . '</td>';
            echo '<td>' . ($p['exchange_rate'] && $p['exchange_rate'] != 1) ? number_format($p['exchange_rate'], 6) : '—' . '</td>';
            echo '<td>' . ($p['converted_amount'] && $p['converted_amount'] != $p['amount']) ? number_format($p['converted_amount'], 2) : '—' . '</td>';
            echo '<td>' . htmlspecialchars($p['main_account_name'] ?: '—') . '</td>';
            echo '<td>' . htmlspecialchars($p['description'] ?: '—') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo '</body></html>';
}
