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

$payment = null;
$errorMessage = '';

if (isset($_GET['id'])) {
    $paymentId = DbSecurity::validateInput($_GET['id'], 'int');
    
    if (!$paymentId) {
        $errorMessage = "Invalid payment ID.";
    } else {
        $query = "SELECT ap.*, br.name AS agency_branch_name, ma.name AS main_account_name, u.name AS created_by_name
                  FROM agency_payments ap
                  LEFT JOIN branches br ON br.id = ap.agency_branch_id
                  LEFT JOIN main_account ma ON ma.id = ap.main_account_id
                  LEFT JOIN users u ON u.id = ap.created_by
                  WHERE ap.id = ? AND ap.tenant_id = ? AND ap.branch_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$paymentId, $tenant_id, $branch_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            $errorMessage = "Payment not found.";
        }
    }
} else {
    $errorMessage = "Payment ID is required.";
}

try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

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
    <title>Payment #<?php echo isset($payment['id']) ? h($payment['id']) : 'Not Found'; ?> — Receipt</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink:       #1a1a2e;
            --ink-mid:   #4a4a6a;
            --ink-light: #9090aa;
            --rule:      #e2e2ec;
            --surface:   #f7f7fb;
            --accent:    #2563eb;
            --accent-bg: #eff6ff;
            --debit:     #dc2626;
            --credit:    #16a34a;
            --paper:     #ffffff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
            padding: 32px 20px 60px;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            max-width: 760px;
            margin: 0 auto 20px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity .15s;
        }
        .btn:hover { opacity: .82; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-ghost   { background: var(--rule); color: var(--ink-mid); }

        .receipt {
            max-width: 760px;
            margin: 0 auto;
            background: var(--paper);
            border-radius: 12px;
            box-shadow: 0 2px 24px rgba(30,30,80,.08);
            overflow: hidden;
        }

        .receipt-header {
            background: var(--ink);
            color: #fff;
            padding: 32px 40px 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
        }
        .header-brand img {
            height: auto;
            max-height: 48px;
            max-width: 180px;
            width: auto;
            margin-bottom: 10px;
            display: block;
            object-fit: contain;
        }
        .header-brand h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            font-weight: 400;
            letter-spacing: .02em;
            line-height: 1.2;
        }
        .header-brand .branch-name {
            font-size: 12px;
            font-weight: 500;
            color: rgba(255,255,255,.55);
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .header-brand .contact {
            margin-top: 8px;
            font-size: 12px;
            color: rgba(255,255,255,.45);
            line-height: 1.6;
        }
        .header-badge {
            text-align: right;
            flex-shrink: 0;
        }
        .header-badge .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: rgba(255,255,255,.45);
            margin-bottom: 4px;
        }
        .header-badge .doc-title {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            font-weight: 400;
            color: #fff;
            line-height: 1;
        }
        .header-badge .doc-id {
            font-family: 'DM Mono', monospace;
            font-size: 14px;
            color: rgba(255,255,255,.6);
            margin-top: 5px;
        }

        .receipt-body { padding: 36px 40px; }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: var(--ink-light);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--rule);
        }

        .kv-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin-bottom: 32px;
            border: 1px solid var(--rule);
            border-radius: 8px;
            overflow: hidden;
        }
        .kv-row {
            display: contents;
        }
        .kv-row .kv-key,
        .kv-row .kv-val {
            padding: 11px 16px;
            border-bottom: 1px solid var(--rule);
        }
        .kv-row:last-child .kv-key,
        .kv-row:last-child .kv-val {
            border-bottom: none;
        }
        .kv-key {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-mid);
            background: var(--surface);
            border-right: 1px solid var(--rule);
        }
        .kv-val {
            font-size: 13px;
            color: var(--ink);
            background: var(--paper);
        }
        .kv-val strong { font-weight: 600; }

        .amount-strip {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .amount-strip .as-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #15803d;
        }
        .amount-strip .as-value {
            font-family: 'DM Mono', monospace;
            font-size: 22px;
            font-weight: 500;
            color: var(--credit);
        }

        .receipt-footer {
            border-top: 1px solid var(--rule);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
        }
        .receipt-footer .generated {
            font-size: 11px;
            color: var(--ink-light);
        }
        .receipt-footer .tagline {
            font-size: 11px;
            font-style: italic;
            color: var(--ink-light);
        }

        .error-card {
            max-width: 500px;
            margin: 80px auto;
            padding: 32px;
            background: var(--paper);
            border-radius: 12px;
            border-left: 4px solid var(--debit);
            box-shadow: 0 2px 20px rgba(0,0,0,.07);
            font-size: 14px;
            color: var(--debit);
        }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<?php if (!empty($errorMessage)): ?>
    <div class="error-card">
        <strong>Error:</strong> <?php echo h($errorMessage); ?>
    </div>
<?php else: ?>

    <div class="toolbar no-print">
        <button class="btn btn-ghost" onclick="window.close()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            Close
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Receipt
        </button>
    </div>

    <div class="receipt">

        <div class="receipt-header">
            <div class="header-brand">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="../../uploads/logo/<?php echo h($settings['logo']); ?>" alt="Logo">
                <?php endif; ?>
                <h1><?php echo h($settings['agency_name']); ?></h1>
                <?php if (!empty($branch['name'])): ?>
                    <div class="branch-name"><?php echo h($branch['name']); ?></div>
                <?php endif; ?>
                <div class="contact">
                    <?php if (!empty($branch['address'])): ?>
                        <?php echo h($branch['address']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($branch['phone'])): ?>
                        <?php echo h($branch['phone']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-badge">
                <div class="label">Document</div>
                <div class="doc-title">Payment Receipt</div>
                <div class="doc-id"># <?php echo h($payment['id']); ?></div>
            </div>
        </div>

        <div class="receipt-body">

            <div class="amount-strip">
                <span class="as-label">Amount Received</span>
                <span class="as-value"><?php echo h($payment['currency']); ?> <?php echo h(number_format($payment['amount'], 2)); ?></span>
            </div>

            <div class="section-label">Payment Details</div>
            <div class="kv-grid">
                <div class="kv-row">
                    <div class="kv-key">Payment ID</div>
                    <div class="kv-val"><strong>#<?php echo h($payment['id']); ?></strong></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key">Payment Date</div>
                    <div class="kv-val"><?php echo h(date('F d, Y', strtotime($payment['payment_date']))); ?></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key">From Branch</div>
                    <div class="kv-val"><?php echo h($payment['agency_branch_name']); ?></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key">Currency</div>
                    <div class="kv-val"><?php echo h($payment['currency']); ?></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key">Main Account</div>
                    <div class="kv-val"><?php echo h($payment['main_account_name'] ?? '—'); ?></div>
                </div>
                <?php if ($payment['exchange_rate']): ?>
                <div class="kv-row">
                    <div class="kv-key">Exchange Rate</div>
                    <div class="kv-val"><?php echo h($payment['exchange_rate']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($payment['reference_number'])): ?>
                <div class="kv-row">
                    <div class="kv-key">Reference No.</div>
                    <div class="kv-val"><?php echo h($payment['reference_number']); ?></div>
                </div>
                <?php endif; ?>
                <div class="kv-row">
                    <div class="kv-key">Recorded By</div>
                    <div class="kv-val"><?php echo h($payment['created_by_name'] ?? '—'); ?></div>
                </div>
                <?php if (!empty($payment['description'])): ?>
                <div class="kv-row">
                    <div class="kv-key">Description</div>
                    <div class="kv-val"><?php echo h($payment['description']); ?></div>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="receipt-footer">
            <span class="generated">Generated on <?php echo date('F d, Y · H:i:s'); ?></span>
            <span class="tagline">Agency Settlement Payment Receipt</span>
        </div>

    </div>

<?php endif; ?>

<script>
    window.onload = function() {
        setTimeout(function() { window.print(); }, 600);
    };
</script>
</body>
</html>
