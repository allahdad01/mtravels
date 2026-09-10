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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Payment Receipt #<?= (int)$txn['id'] ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    body {
        margin: 0;
        background: #f4f7fb;
        font-family: Arial, Helvetica, sans-serif;
        color: #172746;
        font-size: 12px;
    }

    .receipt {
        width: 100%;
        max-width: 700px;
        margin: 15px auto;
        padding: 20px 24px 15px;
        background: #f8fbff;
        border: 1px solid #a9c9f7;
        border-top: 4px solid #2f80ed;
        border-radius: 10px;
        position: relative;
    }

    .header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 0 12px;
        border-bottom: 1px solid #d9e2ef;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .logo {
        width: 55px;
        height: 55px;
        border: 2px solid #2f80ed;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
        color: #0e3674;
        overflow: hidden;
        flex-shrink: 0;
    }

    .logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .company h1,
    .receipt-title h2 {
        margin: 0;
        color: #0d3473;
        font-weight: 800;
        letter-spacing: .5px;
    }

    .company h1 {
        font-size: 18px;
    }

    .company p {
        margin: 4px 0 0;
        color: #687793;
        font-size: 10px;
    }

    .receipt-title {
        text-align: right;
    }

    .receipt-title h2 {
        font-size: 20px;
    }

    .receipt-title p {
        margin: 4px 0 0;
        color: #2f80ed;
        font-size: 10px;
        font-weight: 800;
    }

    .meta {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 16px;
        margin: 14px 0;
        align-items: end;
    }

    .label {
        color: #66738a;
        font-size: 9px;
        font-weight: 800;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .value {
        font-size: 12px;
        font-weight: 800;
        color: #172746;
    }

    .paid, .received {
        height: 28px;
        border: 1px solid #a8dfc7;
        border-radius: 14px;
        background: #e8f7f0;
        display: inline-flex;
        align-items: center;
        padding: 2px 12px 2px 4px;
        color: #11864f;
        font-size: 11px;
        font-weight: 800;
    }

    .paid-icon {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #118b52;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
        font-size: 11px;
    }

    .main-row {
        display: grid;
        grid-template-columns: 1.8fr 1fr;
        gap: 16px;
        margin-top: 10px;
    }

    .client-box {
        background: white;
        border: 1px solid #cddcf0;
        border-left: 8px solid #2f80ed;
        border-radius: 0 8px 8px 0;
        padding: 12px 16px;
    }

    .client-name {
        font-size: 13px;
        font-weight: 800;
        color: #172746;
        margin-top: 4px;
    }

    .amount-box {
        background: #103879;
        border-radius: 8px;
        color: white;
        padding: 12px 16px;
    }

    .amount-box .label {
        color: #9fc5f5;
        margin-bottom: 3px;
    }

    .amount {
        text-align: right;
        font-size: 18px;
        font-weight: 800;
    }

    .description {
        margin-top: 16px;
    }

    .description-text {
        font-size: 12px;
        color: #172746;
        margin-top: 6px;
    }

    .ticket-info {
        margin-top: 12px;
        background: #f0f5ff;
        border: 1px solid #c9dcf7;
        border-radius: 8px;
        padding: 10px 14px;
    }

    .ticket-info .label {
        margin-bottom: 8px;
        font-size: 10px;
    }

    .ticket-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
    }

    .ticket-item .ticket-label {
        color: #66738a;
        font-size: 8px;
        font-weight: 800;
        margin-bottom: 2px;
        text-transform: uppercase;
    }

    .ticket-item .ticket-value {
        color: #172746;
        font-size: 10px;
        font-weight: 800;
    }

    .confirmation {
        margin-top: 16px;
        height: 1px;
        background: #d6e1ef;
        position: relative;
    }

    .confirmation-dot {
        position: absolute;
        left: 6px;
        top: -6px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #2f80ed;
    }

    .confirmation-text {
        position: absolute;
        left: 28px;
        top: -1px;
        background: #f8fbff;
        padding-right: 8px;
        color: #697892;
        font-size: 9px;
    }

    .footer {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 30px;
        margin-top: 24px;
        align-items: end;
    }

    .signature-line {
        height: 1px;
        background: #b8c7da;
        margin-bottom: 6px;
    }

    .signature-title {
        color: #66738a;
        font-size: 9px;
        font-weight: 800;
    }

    .signature-img {
        max-height: 45px;
        max-width: 100%;
        display: block;
        margin: 0 auto 5px;
    }

    .status {
        background: #eaf2ff;
        border: 1px solid #c9dcf7;
        border-radius: 8px;
        padding: 10px 14px;
    }

    .status-value {
        color: #0d4d9e;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .5px;
        margin-top: 4px;
    }

    .thank-you {
        margin-top: 6px;
        color: #66738a;
        font-size: 9px;
    }

    .no-print {
        position: fixed;
        top: 10px;
        right: 10px;
        z-index: 1000;
    }

    @media print {
        body { background: white; font-size: 11px; }
        .no-print { display: none !important; }
        .receipt {
            margin: 0;
            max-width: none;
            border-radius: 0;
            padding: 16px 20px 12px;
        }
    }
</style>
</head>

<body>
<div class="receipt">

    <div class="no-print">
        <button onclick="window.print()" style="background:#2f80ed;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;">
            &#128424; Print
        </button>
        <button onclick="window.close()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;margin-left:8px;">
            &#10005; Close
        </button>
    </div>

    <header class="header">
        <div class="brand">
            <div class="logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <?= strtoupper(substr($settings['agency_name'] ?? 'TA', 0, 2)); ?>
                <?php endif; ?>
            </div>

            <div class="company">
                <h1><?= htmlspecialchars($settings['agency_name'] ?? 'Travel Agency'); ?></h1>
                <p>
                    <?php if (!empty($branch['name'])): ?>
                        <?= htmlspecialchars($branch['name']); ?> &nbsp;&bull;&nbsp;
                    <?php endif; ?>
                    <?= htmlspecialchars($branch['address'] ?? $settings['address'] ?? ''); ?>
                    <?php if (!empty($branch['phone']) || !empty($settings['phone'])): ?>
                        &nbsp;&bull;&nbsp; <?= htmlspecialchars($branch['phone'] ?? $settings['phone'] ?? ''); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="receipt-title">
            <h2>OWNER PAYMENT RECEIPT</h2>
            <p>PAYMENT #<?= (int)$txn['id'] ?></p>
        </div>
    </header>

    <section class="meta">
        <div>
            <div class="label">DATE</div>
            <div class="value"><?= date('M d, Y', strtotime($txn['created_at'])); ?></div>
        </div>

        <div>
            <div class="label">TIME</div>
            <div class="value"><?= date('h:i A', strtotime($txn['created_at'])); ?></div>
        </div>

        <div>
            <div class="label">PAID BY (ADMIN)</div>
            <div class="value"><?= htmlspecialchars($txn['admin_name'] ?? '—'); ?></div>
        </div>

        <div>
            <div class="label">RECEIVED BY (OWNER)</div>
            <div class="value"><?= htmlspecialchars($txn['owner_name'] ?? '—'); ?></div>
        </div>
    </section>

    <section class="main-row">
        <div class="client-box">
            <div class="label">FROM ACCOUNT</div>
            <div class="client-name">
                <?= htmlspecialchars($txn['account_name'] ?? '—'); ?>
            </div>
        </div>

        <div class="amount-box">
            <div class="label">AMOUNT PAID</div>
            <div class="amount">
                <?= number_format((float)$txn['amount'], 0); ?> <?= htmlspecialchars($txn['currency']); ?>
            </div>
        </div>
    </section>

    <section class="description">
        <div class="label">PURPOSE / REASON</div>
        <div class="description-text">
            <?= nl2br(htmlspecialchars($txn['purpose'])); ?>
        </div>

        <div class="ticket-info">
            <div class="label">ADDITIONAL DETAILS</div>
            <div class="ticket-grid">
                <div class="ticket-item">
                    <div class="ticket-label">CURRENCY</div>
                    <div class="ticket-value"><?= htmlspecialchars($txn['currency']); ?></div>
                </div>
                <?php if (!empty($txn['receipt'])): ?>
                <div class="ticket-item">
                    <div class="ticket-label">RECEIPT #</div>
                    <div class="ticket-value"><?= htmlspecialchars($txn['receipt']); ?></div>
                </div>
                <?php endif; ?>
                <div class="ticket-item">
                    <div class="ticket-label">ACCOUNT BALANCE AFTER</div>
                    <div class="ticket-value"><?= number_format((float)$txn['balance'], 2); ?> <?= htmlspecialchars($txn['currency']); ?></div>
                </div>
            </div>
        </div>

        <div class="confirmation">
            <span class="confirmation-dot"></span>
            <span class="confirmation-text">
                This is a system-generated receipt for the payment recorded in the financial ledger.
            </span>
        </div>
    </section>

    <footer class="footer">
        <div>
            <div class="signature-line"></div>
            <div class="signature-title">ADMIN SIGNATURE (PAID BY)</div>
            <div class="thank-you"><?= htmlspecialchars($settings['agency_name'] ?? 'Travel Agency'); ?></div>
        </div>

        <div>
            <div class="signature-line"></div>
            <div class="signature-title">OWNER SIGNATURE (RECEIVED BY)</div>
            <?php if ($hasOwnerSig): ?>
                <img class="signature-img" src="<?= htmlspecialchars($txn['owner_signature']); ?>" alt="Owner signature">
            <?php endif; ?>
        </div>

        <div class="status">
            <div class="label">DOCUMENT STATUS</div>
            <div class="status-value">PAYMENT CONFIRMED</div>
        </div>
    </footer>

</div>
</body>
</html>
