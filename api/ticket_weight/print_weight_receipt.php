<?php
// Include security module
require_once '../../admin/security.php';

// Include language helper
require_once '../../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../../includes/db.php');

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transaction_id) {
    die(__('invalid_transaction_id'));
}

// Fetch transaction details with weight information
$query = "
    SELECT
        mat.*,
        tw.id as weight_id,
        tw.weight,
        tw.remarks,
        tb.passenger_name,
        tb.pnr,
        tb.origin,
        tb.destination,
        tb.airline,
        tb.departure_date,
        tb.currency as ticket_currency,
        c.name as client_name,
        s.name as supplier_name
    FROM main_account_transactions mat
    LEFT JOIN ticket_weights tw ON mat.reference_id = tw.id AND mat.transaction_of = 'weight'
    LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id
    LEFT JOIN clients c ON tb.sold_to = c.id
    LEFT JOIN suppliers s ON tb.supplier = s.id
    WHERE mat.id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die(__('transaction_not_found'));
}

// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo __('receipt'); ?> - <?php echo htmlspecialchars($transaction['description']); ?></title>
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
            &#128424; <?php echo __('print'); ?>
        </button>
        <button onclick="window.close()" style="background:#6c757d;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;margin-left:8px;">
            &#10005; <?php echo __('close'); ?>
        </button>
    </div>

    <header class="header">
        <div class="brand">
            <div class="logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <?php echo strtoupper(substr($settings['agency_name'] ?? 'TA', 0, 2)); ?>
                <?php endif; ?>
            </div>

            <div class="company">
                <h1><?php echo htmlspecialchars($settings['agency_name'] ?? 'Travel Agency'); ?></h1>
                <p>
                    <?php if (!empty($branch['name'])): ?>
                        <?php echo htmlspecialchars($branch['name']); ?> &nbsp;&bull;&nbsp;
                    <?php endif; ?>
                    <?php echo htmlspecialchars($branch['address'] ?? $settings['address'] ?? ''); ?>
                    <?php if (!empty($branch['phone']) || !empty($settings['phone'])): ?>
                        &nbsp;&bull;&nbsp; <?php echo htmlspecialchars($branch['phone'] ?? $settings['phone'] ?? ''); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="receipt-title">
            <h2>RECEIPT</h2>
            <p>TICKET WEIGHT</p>
        </div>
    </header>

    <section class="meta">
        <div>
            <div class="label"><?php echo __('receipt_number'); ?></div>
            <div class="value">#<?php echo $transaction['id']; ?></div>
        </div>

        <div>
            <div class="label"><?php echo __('date'); ?></div>
            <div class="value"><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></div>
        </div>

        <div>
            <div class="label"><?php echo __('time'); ?></div>
            <div class="value"><?php echo date('h:i A', strtotime($transaction['created_at'])); ?></div>
        </div>

        <div>
            <div class="label"><?php echo __('payment_type'); ?></div>
            <?php if ($transaction['type'] === 'credit'): ?>
                <div class="received">
                    <span class="paid-icon">&#10003;</span>
                    <?php echo __('received'); ?>
                </div>
            <?php else: ?>
                <div class="paid">
                    <span class="paid-icon">&#10003;</span>
                    <?php echo __('paid'); ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="main-row">
        <div class="client-box">
            <div class="label"><?php echo __('client'); ?> / <?php echo __('account'); ?></div>
            <div class="client-name">
                <?php echo htmlspecialchars($transaction['client_name'] ?? __('walking_customer')); ?>
            </div>
        </div>

        <div class="amount-box">
            <div class="label"><?php echo __('amount'); ?></div>
            <div class="amount">
                <?php echo number_format($transaction['amount'], 0); ?> <?php echo htmlspecialchars($transaction['currency']); ?>
            </div>
        </div>
    </section>

    <section class="description">
        <div class="label"><?php echo __('description'); ?></div>
        <div class="description-text">
            <?php echo htmlspecialchars($transaction['description']); ?>
        </div>

        <?php if (!empty($transaction['weight_id'])): ?>
        <div class="ticket-info">
            <div class="label"><?php echo __('weight_information'); ?></div>
            <div class="ticket-grid">
                <div class="ticket-item">
                    <div class="ticket-label"><?php echo __('passenger_name'); ?></div>
                    <div class="ticket-value"><?php echo htmlspecialchars($transaction['passenger_name']); ?></div>
                </div>
                <div class="ticket-item">
                    <div class="ticket-label"><?php echo __('sector'); ?></div>
                    <div class="ticket-value">
                        <?php echo htmlspecialchars($transaction['origin']); ?> - <?php echo htmlspecialchars($transaction['destination']); ?>
                    </div>
                </div>
                <div class="ticket-item">
                    <div class="ticket-label"><?php echo __('departure_date'); ?></div>
                    <div class="ticket-value"><?php echo htmlspecialchars($transaction['departure_date']); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="confirmation">
            <span class="confirmation-dot"></span>
            <span class="confirmation-text">
                <?php echo __('transaction_recorded'); ?> <?php echo htmlspecialchars($settings['agency_name'] ?? 'Finance'); ?>
            </span>
        </div>
    </section>

    <footer class="footer">
        <div>
            <div class="signature-line"></div>
            <div class="signature-title"><?php echo __('receiver_sign'); ?></div>
        </div>

        <div>
            <div class="signature-line"></div>
            <div class="signature-title"><?php echo __('authorized_sign'); ?></div>
        </div>

        <div class="status">
            <div class="label"><?php echo __('document_status'); ?></div>
            <div class="status-value"><?php echo __('payment_confirmed'); ?></div>
            <div class="thank-you"><?php echo __('thank_you_business'); ?></div>
        </div>
    </footer>

</div>
</body>
</html>