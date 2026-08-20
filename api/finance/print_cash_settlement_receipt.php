<?php
// Printable cash settlement (handover) voucher with the admin's signature.
session_start();

require_once __DIR__ . '/../../includes/permissions.php';
require_permission('finance.cash_settlement');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';
require_once '../../includes/language_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { die('Invalid settlement ID'); }

$stmt = $pdo->prepare("SELECT cs.*, u.name AS user_name, cu.name AS confirmed_name
    FROM cash_settlements cs
    LEFT JOIN users u  ON u.id  = cs.user_id
    LEFT JOIN users cu ON cu.id = cs.confirmed_by
    WHERE cs.id=? AND cs.tenant_id=? AND cs.branch_id=?");
$stmt->execute([$id, $tenant_id, $branch_id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$s) { die('Settlement not found'); }

// Company / settings
$settings = ['agency_name' => 'Travel Agency', 'logo' => '', 'address' => '', 'phone' => ''];
try {
    $st = $pdo->prepare("SELECT agency_name, logo, address, phone FROM settings WHERE tenant_id=?");
    $st->execute([$tenant_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $settings = array_merge($settings, $row);
} catch (Exception $e) { /* defaults */ }

$branch = null;
try {
    $st = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id=? AND tenant_id=?");
    $st->execute([$branch_id, $tenant_id]);
    $branch = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

$confirmed = $s['status'] === 'confirmed';
$hasSig = $confirmed && !empty($s['signature_data']);

// FIFO breakdown: which income items make up this handover.
// Credits are consumed in date order; each confirmed settlement covers the
// next slice of that stream, so this receipt lists exactly the items that
// were handed over (partial items are marked).
$items = [];
$itemsTotal = 0.0;
$breakdownNote = '';

$stmt = $pdo->prepare("SELECT mat.id, mat.description, mat.transaction_of, mat.reference_id, mat.created_at, mat.amount
    FROM main_account_transactions mat
    JOIN main_account ma ON ma.id = mat.main_account_id
    WHERE mat.tenant_id=? AND mat.branch_id=? AND mat.created_by=?
      AND UPPER(mat.currency)=? AND ma.account_type='internal' AND mat.type='credit'
    ORDER BY mat.created_at ASC, mat.id ASC");
$stmt->execute([$tenant_id, $branch_id, $s['user_id'], $s['currency']]);
$credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, amount FROM cash_settlements
    WHERE tenant_id=? AND branch_id=? AND user_id=? AND currency=? AND status='confirmed'
    ORDER BY confirmed_at ASC, id ASC");
$stmt->execute([$tenant_id, $branch_id, $s['user_id'], $s['currency']]);
$settledList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$targetIdx = -1;
$before = 0.0;
foreach ($settledList as $i => $se) {
    if ((int)$se['id'] === (int)$id) { $targetIdx = $i; break; }
    $before += (float)$se['amount'];
}

if ($targetIdx >= 0) {
    $start = $before;
    $end = $before + (float)$s['amount'];
    $running = 0.0;
    foreach ($credits as $c) {
        $amt = (float)$c['amount'];
        $cStart = $running;
        $cEnd = $running + $amt;
        $running = $cEnd;
        if ($cEnd <= $start || $cStart >= $end) continue;
        $covered = min($cEnd, $end) - max($cStart, $start);
        $c['covered'] = $covered;
        $c['partial'] = $covered < $amt - 0.005;
        $items[] = $c;
        $itemsTotal += $covered;
        if ($cEnd >= $end) break;
    }
    if (!$items) $breakdownNote = 'No income items found for this handover.';
} else {
    // Pending / unconfirmed — show the full income list for verification.
    foreach ($credits as $c) {
        $c['covered'] = (float)$c['amount'];
        $c['partial'] = false;
        $items[] = $c;
        $itemsTotal += (float)$c['amount'];
    }
    if ($items) $breakdownNote = 'Not yet confirmed — showing all income items for ' . htmlspecialchars($s['currency']) . '.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Settlement Receipt</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .receipt-container { max-width: 100% !important; margin: 0 !important; }
        }
        body { background: #f4f6f9; }
        .receipt-container {
            max-width: 800px; margin: 20px auto; padding: 20px;
            border: 1px solid #ddd; border-radius: 8px; background: white;
        }
        .receipt-header { border-bottom: 2px solid #4099ff; padding-bottom: 20px; margin-bottom: 10px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .agency-name { font-size: 18px; font-weight: bold; color: #333; flex: 1; }
        .receipt-title { font-size: 24px; font-weight: bold; color: #4099ff; text-align: center; flex: 2; }
        .company-logo { flex: 1; text-align: right; }
        .company-logo img { max-height: 80px; max-width: 120px; }
        .receipt-details { margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding: 5px 0; }
        .detail-label { font-weight: bold; color: #333; }
        .detail-value { color: #666; text-align: right; }
        .receipt-main { display: flex; gap: 24px; align-items: flex-start; }
        .receipt-main .receipt-details { flex: 0 0 235px; min-width: 210px; margin-bottom: 0; }
        .breakdown-col { flex: 1 1 auto; min-width: 0; }
        .breakdown-col .breakdown-section-title { margin-top: 0; }
        @media (max-width: 700px) {
            .receipt-main { flex-direction: column; }
            .receipt-main .receipt-details { flex: 1 1 auto; width: 100%; }
        }
        .amount-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; position: relative; }
        .amount-label { font-size: 16px; color: #666; margin-bottom: 10px; }
        .amount-value { font-size: 28px; font-weight: bold; color: white; background: #4099ff; padding: 10px 20px; border-radius: 5px; display: inline-block; }
        .status-pill { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .status-pill.confirmed { background: #d1fae5; color: #15803d; }
        .status-pill.rejected { background: #fee2e2; color: #b91c1c; }
        .status-pill.pending { background: #fef3c7; color: #b45309; }
        .breakdown-section-title { font-size: 13px; font-weight: bold; color: #333; margin: 20px 0 10px; border-left: 4px solid #4099ff; padding-left: 10px; }
        .breakdown-table { width: 100%; border-collapse: collapse; margin: 10px 0 20px; font-size: 13px; }
        .breakdown-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #666; background: #f8f9fa; border-bottom: 2px solid #4099ff; padding: 8px 10px; }
        .breakdown-table td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        .breakdown-table .num { text-align: right; font-weight: bold; white-space: nowrap; }
        .breakdown-table tr.total-row td { border-top: 2px solid #4099ff; border-bottom: none; font-weight: bold; background: #f0f6ff; }
        .badge-partial { font-size: 10px; font-weight: bold; color: #b45309; background: #fef3c7; border-radius: 10px; padding: 1px 7px; margin-left: 6px; }
        .breakdown-note { font-size: 11px; color: #999; margin: -10px 0 15px; }
        .signature-section { display: flex; justify-content: space-between; margin-top: 40px; gap: 40px; }
        .signature-box { flex: 1; text-align: center; }
        .signature-label { font-size: 12px; color: #666; margin-bottom: 10px; font-weight: bold; }
        .signature-line { border-bottom: 1px solid #333; height: 60px; margin-top: 10px; }
        .signature-img { max-height: 80px; max-width: 100%; display: block; margin: 0 auto 10px; }
        .signed-name { font-size: 13px; font-weight: bold; color: #333; }
        .signed-date { font-size: 11px; color: #999; }
        .footer-note { text-align: center; font-size: 12px; color: #999; margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; }
        .print-btn { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .ledger-note { font-size: 11px; color: #999; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="receipt-container">
        <div class="no-print print-btn">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
            <button onclick="window.close()" class="btn btn-secondary ml-2"><i class="fas fa-times"></i> Close</button>
        </div>

        <div class="receipt-header">
            <div class="header-row">
                <div class="agency-name">
                    <?php echo htmlspecialchars($settings['agency_name']); ?>
                    <?php if (!empty($branch['name'])): ?><br><small><?php echo htmlspecialchars($branch['name']); ?></small><?php endif; ?>
                </div>
                <div class="receipt-title">Cash Settlement Receipt</div>
                <div class="company-logo">
                    <img src="../../uploads/logo/<?php echo htmlspecialchars($settings['logo'] ?? ''); ?>" alt="Company Logo">
                </div>
            </div>
        </div>

        <div class="receipt-main">
            <!-- Column 1: receipt details -->
            <div class="receipt-details">
                <div class="detail-row"><span class="detail-label">Receipt No:</span><span class="detail-value">#<?php echo (int)$s['id']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Submitted By (Finance):</span><span class="detail-value"><?php echo htmlspecialchars($s['user_name']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Submission Date:</span><span class="detail-value"><?php echo date('M d, Y H:i', strtotime($s['created_at'])); ?></span></div>
                <div class="detail-row"><span class="detail-label">Currency:</span><span class="detail-value"><?php echo htmlspecialchars($s['currency']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Note:</span><span class="detail-value"><?php echo htmlspecialchars($s['request_note'] ?? '—'); ?></span></div>
                <div class="detail-row"><span class="detail-label">Status:</span><span class="detail-value"><span class="status-pill <?php echo $s['status']; ?>"><?php echo $s['status']; ?></span></span></div>
                <?php if ($confirmed): ?>
                    <div class="detail-row"><span class="detail-label">Confirmed By (Admin):</span><span class="detail-value"><?php echo htmlspecialchars($s['confirmed_name'] ?? '—'); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Confirmed At:</span><span class="detail-value"><?php echo date('M d, Y H:i', strtotime($s['confirmed_at'])); ?></span></div>
                <?php elseif ($s['status'] === 'rejected'): ?>
                    <div class="detail-row"><span class="detail-label">Reject Reason:</span><span class="detail-value"><?php echo htmlspecialchars($s['reject_reason'] ?? '—'); ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Column 2: income items -->
            <div class="breakdown-col">
                <div class="breakdown-section-title">Cash Breakdown — Items Included in this Handover</div>
                <?php if ($breakdownNote): ?><div class="breakdown-note"><?php echo $breakdownNote; ?></div><?php endif; ?>
                <?php if ($items): ?>
                <table class="breakdown-table">
                    <thead>
                        <tr><th>#</th><th>Date</th><th>Item / Description</th><th>Source</th><th>Ref</th><th class="num">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php $n = 0; foreach ($items as $it): $n++; ?>
                        <tr>
                            <td><?php echo $n; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($it['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($it['description'] ?: '—'); ?><?php if ($it['partial']): ?><span class="badge-partial">partial</span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars(str_replace('_', ' ', $it['transaction_of'])); ?></td>
                            <td><?php echo htmlspecialchars((string) $it['reference_id']); ?></td>
                            <td class="num"><?php echo number_format((float) $it['covered'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="total-row"><td colspan="5">Total handed over</td><td class="num"><?php echo number_format($itemsTotal, 2); ?></td></tr>
                    </tbody>
                </table>
                <?php else: ?>
                <table class="breakdown-table">
                    <tr><td colspan="6" style="color:#999;">No items found for this settlement.</td></tr>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="amount-section">
            <div class="amount-label">Amount Handed Over</div>
            <div class="amount-value"><?php echo htmlspecialchars($s['currency']); ?> <?php echo number_format((float)$s['amount'], 2); ?></div>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Finance Manager Sign</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Admin Authorized Sign</div>
                <?php if ($hasSig): ?>
                    <img class="signature-img" src="<?php echo htmlspecialchars($s['signature_data']); ?>" alt="Admin signature">
                    <div class="signed-name"><?php echo htmlspecialchars($s['confirmed_name']); ?></div>
                    <div class="signed-date"><?php echo date('M d, Y H:i', strtotime($s['signed_at'])); ?></div>
                <?php else: ?>
                    <div class="signature-line"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ledger-note">This is a system-generated receipt. <?php echo $hasSig ? 'The admin signature above confirms the physical handover of cash.' : ''; ?></div>

        <div class="footer-note">
            <?php if (!empty($branch)): ?>
                <?php echo htmlspecialchars($branch['phone'] ?? ''); ?><?php if (!empty($branch['address'])): ?> (<?php echo htmlspecialchars($branch['address']); ?>)<?php endif; ?>
            <?php else: ?>
                <?php echo htmlspecialchars($settings['address'] ?? ''); ?>
                <?php if (!empty($settings['phone'])): ?> | <?php echo htmlspecialchars($settings['phone']); ?><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../assets/js/vendor-all.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
