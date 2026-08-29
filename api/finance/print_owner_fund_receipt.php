<?php
/**
 * Print Owner Fund Receipt
 * Printable voucher for money paid from admin to owner.
 */
session_start();

require_once __DIR__ . '/../../includes/permissions.php';
require_permission('finance.owner_funds');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';
require_once '../../includes/language_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { die('Invalid transaction ID'); }

// Fetch transaction with owner and admin names
$stmt = $pdo->prepare("SELECT mat.*, 
    CASE 
        WHEN mat.reference_id IS NOT NULL THEN u_owner.name
        WHEN mat.description LIKE '[Owner: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(mat.description, '] ', 1), '[Owner: ', -1)
        ELSE '—'
    END AS owner_name,
    CASE 
        WHEN mat.description LIKE '[Owner: %' THEN TRIM(SUBSTRING(mat.description, LOCATE('] ', mat.description) + 2))
        ELSE mat.description
    END AS purpose,
    u_admin.name AS admin_name,
    ma.name AS account_name
FROM main_account_transactions mat
LEFT JOIN users u_owner ON u_owner.id = mat.reference_id
LEFT JOIN users u_admin ON u_admin.id = mat.created_by
LEFT JOIN main_account ma ON ma.id = mat.main_account_id
WHERE mat.id = ? AND mat.tenant_id = ? AND mat.branch_id = ? AND mat.transaction_of = 'owner_fund'");
$stmt->execute([$id, $tenant_id, $branch_id]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$txn) { die('Payment not found'); }

// Company settings
$settings = ['agency_name' => 'Travel Agency', 'logo' => '', 'address' => '', 'phone' => ''];
try {
    $st = $pdo->prepare("SELECT agency_name, logo, address, phone FROM settings WHERE tenant_id = ?");
    $st->execute([$tenant_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $settings = array_merge($settings, $row);
} catch (Exception $e) { /* defaults */ }

// Branch info
$branch = null;
try {
    $st = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $st->execute([$branch_id, $tenant_id]);
    $branch = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

$hasOwnerSig = !empty($txn['owner_signature']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Payment Receipt #<?= (int)$txn['id'] ?></title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10px; margin: 0 !important; padding: 0 !important; }
            .receipt-container { max-width: 100% !important; margin: 0 !important; padding: 0 5px !important; box-shadow: none !important; border: none !important; border-radius: 0 !important; page-break-inside: avoid; }
            @page { size: A4; margin: 5mm; }
        }
        body { background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; font-size: 11px; }
        .receipt-container {
            max-width: 700px; margin: 15px auto; padding: 12px 18px;
            border: 1px solid #ddd; border-radius: 6px; background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .receipt-header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 8px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .agency-name { font-size: 14px; font-weight: bold; color: #333; flex: 1; }
        .receipt-title { font-size: 16px; font-weight: bold; color: #2563eb; text-align: center; flex: 2; }
        .company-logo { flex: 1; text-align: right; }
        .company-logo img { max-height: 45px; max-width: 80px; }
        .receipt-details { margin-bottom: 8px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 3px; padding: 2px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; color: #555; font-size: 11px; }
        .detail-value { color: #333; text-align: right; font-size: 11px; }
        .amount-section { background: #eff6ff; padding: 10px; border-radius: 6px; margin: 10px 0; text-align: center; border: 1px solid #bfdbfe; }
        .amount-label { font-size: 11px; color: #64748b; margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .amount-value { font-size: 22px; font-weight: 800; color: #1d4ed8; }
        .purpose-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin: 10px 0; }
        .purpose-label { font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 3px; }
        .purpose-text { font-size: 11px; color: #333; line-height: 1.4; }
        .signature-section { display: flex; justify-content: space-between; margin-top: 20px; gap: 30px; }
        .signature-box { flex: 1; text-align: center; }
        .signature-label { font-size: 10px; color: #64748b; margin-bottom: 5px; font-weight: 600; text-transform: uppercase; }
        .signature-line { border-bottom: 1px solid #333; height: 35px; margin-top: 5px; }
        .signature-img { max-height: 45px; max-width: 100%; display: block; margin: 0 auto 5px; }
        .signed-name { font-size: 11px; font-weight: 600; color: #333; }
        .signed-date { font-size: 9px; color: #999; }
        .footer-note { text-align: center; font-size: 9px; color: #999; margin-top: 12px; padding-top: 8px; border-top: 1px solid #eee; }
        .print-btn { position: fixed; top: 15px; right: 15px; z-index: 1000; display: flex; gap: 6px; }
        .receipt-id { font-size: 10px; color: #999; margin-top: 2px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="receipt-container">
        <div class="no-print print-btn">
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Close</button>
        </div>

        <!-- Header -->
        <div class="receipt-header">
            <div class="header-row">
                <div class="agency-name">
                    <?= htmlspecialchars($settings['agency_name']) ?>
                    <?php if (!empty($branch['name'])): ?>
                        <br><small style="color:#64748b;"><?= htmlspecialchars($branch['name']) ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="receipt-title">Owner Payment Receipt</div>
                    <div class="receipt-id">Payment #<?= (int)$txn['id'] ?></div>
                </div>
                <div class="company-logo">
                    <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" alt="Logo">
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="receipt-details">
            <div class="detail-row">
                <span class="detail-label">Date & Time</span>
                <span class="detail-value"><?= date('M d, Y H:i', strtotime($txn['created_at'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Paid By (Admin)</span>
                <span class="detail-value"><?= htmlspecialchars($txn['admin_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Received By (Owner)</span>
                <span class="detail-value"><?= htmlspecialchars($txn['owner_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">From Account</span>
                <span class="detail-value"><?= htmlspecialchars($txn['account_name'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Currency</span>
                <span class="detail-value"><?= htmlspecialchars($txn['currency']) ?></span>
            </div>
            <?php if (!empty($txn['receipt'])): ?>
            <div class="detail-row">
                <span class="detail-label">Receipt #</span>
                <span class="detail-value"><?= htmlspecialchars($txn['receipt']) ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Account Balance After</span>
                <span class="detail-value"><?= number_format((float)$txn['balance'], 2) ?> <?= htmlspecialchars($txn['currency']) ?></span>
            </div>
        </div>

        <!-- Amount -->
        <div class="amount-section">
            <div class="amount-label">Amount Paid</div>
            <div class="amount-value"><?= htmlspecialchars($txn['currency']) ?> <?= number_format((float)$txn['amount'], 2) ?></div>
        </div>

        <!-- Purpose -->
        <div class="purpose-box">
            <div class="purpose-label">Purpose / Reason</div>
            <div class="purpose-text"><?= nl2br(htmlspecialchars($txn['purpose'])) ?></div>
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Admin Signature (Paid By)</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Owner Signature (Received By)</div>
                <?php if ($hasOwnerSig): ?>
                    <img class="signature-img" src="<?= htmlspecialchars($txn['owner_signature']) ?>" alt="Owner signature">
                <?php else: ?>
                    <div class="signature-line"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            This is a system-generated receipt for the payment recorded in the financial ledger.<br>
            <?php if (!empty($branch)): ?>
                <?= htmlspecialchars($branch['phone'] ?? '') ?>
                <?php if (!empty($branch['address'])): ?> (<?= htmlspecialchars($branch['address']) ?>)<?php endif; ?>
            <?php else: ?>
                <?= htmlspecialchars($settings['address'] ?? '') ?>
                <?php if (!empty($settings['phone'])): ?> | <?= htmlspecialchars($settings['phone']) ?><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../assets/js/vendor-all.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
