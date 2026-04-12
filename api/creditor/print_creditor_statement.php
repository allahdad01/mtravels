<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Include language helper
require_once '../../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency', 'logo' => null];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency', 'logo' => null];
}

// Fetch branch data
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

// Validate the creditor ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid creditor ID");
}

$creditor_id = intval($_GET['id']);

// Fetch creditor details
$stmt = $pdo->prepare("SELECT * FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$creditor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$creditor) {
    die("Creditor not found");
}

// Fetch creditor transactions
$stmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
$stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For creditors: debit = payment made to creditor, credit = amount owed added
$total_paid = 0;
$initial_balance = $creditor['balance']; // start from stored balance

foreach ($transactions as $transaction) {
    if ($transaction['transaction_type'] == 'debit') {
        $total_paid += $transaction['amount'];
    } else {
        $initial_balance += $transaction['amount'];
    }
}

function getCurrencySymbol($currency) {
    switch ($currency) {
        case 'USD':    return '$';
        case 'EUR':    return '€';
        case 'AFS':    return '؋';
        case 'DARHAM': return 'د.إ';
        default:       return '';
    }
}

$currency_symbol = getCurrencySymbol($creditor['currency']);
$is_fully_paid   = ($creditor['balance'] == 0);
$statement_ref   = 'CR-' . strtoupper(substr(md5($creditor_id . date('Y')), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($settings['agency_name']) ?> — Creditor Statement</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue:        #1A5276;
            --blue-dk:     #154360;
            --blue-lt:     #EAF2F8;
            --ink:         #1A1A2E;
            --ink-mid:     #4A4A6A;
            --ink-muted:   #8E8EA8;
            --border:      #E8E8F0;
            --surface:     #F7F7FB;
            --white:       #FFFFFF;
            --green:       #1A7F5A;
            --green-lt:    #E6F4EF;
            --orange:      #CA6F1E;
            --orange-lt:   #FEF5E7;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
            padding: 40px 20px 60px;
        }

        /* ── Page ── */
        .page {
            max-width: 820px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,.04), 0 20px 60px rgba(0,0,0,.08);
            position: relative;
        }

        /* ── Top bar ── */
        .top-bar { background: var(--blue); height: 6px; }

        /* ── PAID watermark ── */
        .paid-stamp {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-family: 'Playfair Display', serif;
            font-size: 110px; font-weight: 700;
            color: rgba(26, 127, 90, 0.08);
            border: 10px solid rgba(26, 127, 90, 0.08);
            padding: 10px 30px; border-radius: 12px;
            z-index: 0; pointer-events: none;
            display: <?php echo $is_fully_paid ? 'block' : 'none'; ?>;
            white-space: nowrap;
        }

        .page > *:not(.paid-stamp) { position: relative; z-index: 1; }

        /* ── Header ── */
        .header {
            padding: 36px 48px 28px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            gap: 20px; flex-wrap: wrap;
        }

        .header-left { display: flex; align-items: center; gap: 18px; }

        .logo-wrap img {
            max-width: 56px; max-height: 56px;
            object-fit: contain; border-radius: 8px;
        }

        .logo-placeholder {
            width: 56px; height: 56px;
            background: var(--blue-lt);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 22px; color: var(--blue); font-weight: 700;
        }

        .agency-name {
            font-family: 'Playfair Display', serif;
            font-size: 20px; color: var(--ink); line-height: 1.2;
        }

        .branch-name { font-size: 13px; color: var(--ink-muted); margin-top: 3px; }

        .header-right { text-align: right; }

        .doc-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px; color: var(--blue);
        }

        .doc-meta { margin-top: 8px; display: flex; flex-direction: column; gap: 3px; align-items: flex-end; }
        .doc-meta span { font-size: 12.5px; color: var(--ink-muted); }
        .doc-meta strong { color: var(--ink-mid); font-weight: 500; }

        /* ── Creditor info strip ── */
        .creditor-strip {
            padding: 24px 48px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
        }

        .info-cell .label {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--ink-muted); margin-bottom: 5px;
        }

        .info-cell .value {
            font-size: 14.5px; font-weight: 500;
            color: var(--ink); word-break: break-word;
        }

        /* ── Summary cards ── */
        .summary-section {
            padding: 28px 48px;
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }

        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px 20px;
            position: relative; overflow: hidden;
        }

        .summary-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: var(--border);
        }

        .summary-card.card-balance::before { background: var(--orange); }
        .summary-card.card-paid::before    { background: var(--green); }
        .summary-card.card-status::before  { background: var(--ink); }
        .summary-card.card-initial::before { background: var(--blue); }

        .summary-card .card-label {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--ink-muted); margin-bottom: 10px;
        }

        .summary-card .card-value {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 700; color: var(--ink); line-height: 1;
        }

        .summary-card.card-balance .card-value { color: var(--orange); }
        .summary-card.card-paid    .card-value { color: var(--green); }
        .summary-card.card-initial .card-value { color: var(--blue); }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 13px; font-weight: 600;
        }

        .status-badge.outstanding { background: var(--orange-lt); color: var(--orange); }
        .status-badge.fully-paid  { background: var(--green-lt);  color: var(--green); }

        .status-badge::before {
            content: ''; width: 7px; height: 7px;
            border-radius: 50%; background: currentColor;
        }

        /* ── Timeline ── */
        .timeline-section { padding: 28px 48px 36px; }

        .section-heading {
            font-family: 'Playfair Display', serif;
            font-size: 16px; color: var(--ink);
            margin-bottom: 12px; padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .timeline { position: relative; margin-top: 20px; }

        .timeline::before {
            content: ''; position: absolute;
            left: 18px; top: 0; bottom: 0;
            width: 2px; background: var(--border);
        }

        .timeline-item {
            display: flex; gap: 24px;
            margin-bottom: 6px; position: relative;
        }

        .timeline-item:last-child { margin-bottom: 0; }

        .tl-dot {
            flex-shrink: 0; width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            position: relative; z-index: 1;
            border: 2px solid var(--white);
        }

        .tl-dot.payment {
            background: var(--green-lt);
            box-shadow: 0 0 0 2px var(--green);
        }

        .tl-dot.credit-add {
            background: var(--orange-lt);
            box-shadow: 0 0 0 2px var(--orange);
        }

        .tl-dot svg { width: 16px; height: 16px; }
        .tl-dot.payment    svg { color: var(--green); }
        .tl-dot.credit-add svg { color: var(--orange); }

        .tl-card {
            flex: 1;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 20px;
            display: flex; align-items: center;
            justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
            margin-bottom: 10px;
            transition: box-shadow .15s;
        }

        .tl-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.06); }

        .tl-left { flex: 1; min-width: 120px; }

        .tl-date {
            font-size: 12px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .05em;
            color: var(--ink-muted); margin-bottom: 4px;
        }

        .tl-desc { font-size: 14.5px; font-weight: 500; color: var(--ink); }

        .tl-ref { font-size: 12px; color: var(--ink-muted); margin-top: 2px; }

        .tl-right { text-align: right; }

        .tl-type {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px;
        }

        .tl-type.payment    { color: var(--green); }
        .tl-type.credit-add { color: var(--orange); }

        .tl-amount {
            font-family: 'Playfair Display', serif;
            font-size: 17px; font-weight: 700;
        }

        .tl-amount.payment    { color: var(--green); }
        .tl-amount.credit-add { color: var(--orange); }

        .no-transactions {
            text-align: center; padding: 48px 20px;
            color: var(--ink-muted); font-size: 14px;
        }

        /* ── Footer ── */
        .footer {
            padding: 20px 48px;
            background: var(--surface);
            border-top: 1px solid var(--border);
            display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 10px;
        }

        .footer-left { font-size: 12.5px; color: var(--ink-muted); line-height: 1.6; }
        .footer-right { font-size: 12px; color: var(--ink-muted); text-align: right; }

        /* ── Action bar ── */
        .action-bar {
            max-width: 820px; margin: 24px auto 0;
            display: flex; justify-content: flex-end; padding: 0 4px;
        }

        .btn-print {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 26px;
            background: var(--blue); color: #fff;
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer;
            letter-spacing: .02em;
            transition: background .15s, transform .1s;
        }

        .btn-print:hover { background: var(--blue-dk); transform: translateY(-1px); }
        .btn-print svg { width: 16px; height: 16px; }

        /* ── Print ── */
        @media print {
            body { background: #fff; padding: 0; }
            .page { box-shadow: none; border-radius: 0; }
            .action-bar { display: none; }
            .top-bar, .summary-card::before,
            .tl-dot, .tl-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .paid-stamp { display: <?php echo $is_fully_paid ? 'block' : 'none'; ?>; }
            @page { size: A4; margin: 10mm; }
        }

        @media (max-width: 600px) {
            .header, .creditor-strip, .summary-section,
            .timeline-section, .footer { padding-left: 22px; padding-right: 22px; }
        }
    </style>
</head>
<body>

<div class="page">

    <?php if ($is_fully_paid): ?>
    <div class="paid-stamp">PAID</div>
    <?php endif; ?>

    <div class="top-bar"></div>

    <!-- ── Header ── -->
    <div class="header">
        <div class="header-left">
            <div class="logo-wrap">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="Logo">
                <?php else: ?>
                    <div class="logo-placeholder"><?= strtoupper(substr($settings['agency_name'], 0, 1)) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="agency-name"><?= htmlspecialchars($settings['agency_name']) ?></div>
                <?php if (!empty($branch['name'])): ?>
                    <div class="branch-name"><?= htmlspecialchars($branch['name']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-right">
            <div class="doc-title">Creditor Statement</div>
            <div class="doc-meta">
                <span>Ref: <strong><?= $statement_ref ?></strong></span>
                <span>Date: <strong><?= date('F d, Y') ?></strong></span>
            </div>
        </div>
    </div>

    <!-- ── Creditor Info ── -->
    <div class="creditor-strip">
        <div class="info-cell">
            <div class="label">Creditor Name</div>
            <div class="value"><?= htmlspecialchars($creditor['name']) ?></div>
        </div>
        <div class="info-cell">
            <div class="label">Email</div>
            <div class="value"><?= htmlspecialchars($creditor['email'] ?: 'N/A') ?></div>
        </div>
        <div class="info-cell">
            <div class="label">Phone</div>
            <div class="value"><?= htmlspecialchars($creditor['phone'] ?: 'N/A') ?></div>
        </div>
        <div class="info-cell">
            <div class="label">Address</div>
            <div class="value"><?= htmlspecialchars($creditor['address'] ?: 'N/A') ?></div>
        </div>
    </div>

    <!-- ── Summary ── -->
    <div class="summary-section">
        <div class="summary-card card-initial">
            <div class="card-label">Initial Balance</div>
            <div class="card-value"><?= $currency_symbol ?>&nbsp;<?= number_format($initial_balance, 2) ?></div>
        </div>
        <div class="summary-card card-paid">
            <div class="card-label">Amount Paid</div>
            <div class="card-value"><?= $currency_symbol ?>&nbsp;<?= number_format($total_paid, 2) ?></div>
        </div>
        <div class="summary-card card-balance">
            <div class="card-label">Remaining Balance</div>
            <div class="card-value"><?= $currency_symbol ?>&nbsp;<?= number_format($creditor['balance'], 2) ?></div>
        </div>
        <div class="summary-card card-status">
            <div class="card-label">Status</div>
            <div style="margin-top: 6px;">
                <?php if ($is_fully_paid): ?>
                    <span class="status-badge fully-paid">Fully Paid</span>
                <?php else: ?>
                    <span class="status-badge outstanding">Outstanding</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Transaction Timeline ── -->
    <div class="timeline-section">
        <div class="section-heading">Payment History</div>

        <?php if (count($transactions) > 0): ?>
        <div class="timeline">
            <?php foreach ($transactions as $transaction):
                $is_payment = $transaction['transaction_type'] === 'debit';
                $dot_class  = $is_payment ? 'payment' : 'credit-add';
            ?>
            <div class="timeline-item">
                <div class="tl-dot <?= $dot_class ?>">
                    <?php if ($is_payment): ?>
                    <!-- payment made: arrow down (outgoing) -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
                    </svg>
                    <?php else: ?>
                    <!-- credit added: arrow up (incoming obligation) -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
                    </svg>
                    <?php endif; ?>
                </div>

                <div class="tl-card">
                    <div class="tl-left">
                        <div class="tl-date"><?= date('D, M d Y', strtotime($transaction['payment_date'])) ?></div>
                        <div class="tl-desc"><?= htmlspecialchars($transaction['description']) ?></div>
                        <?php if (!empty($transaction['reference_number'])): ?>
                        <div class="tl-ref">Ref # <?= htmlspecialchars($transaction['reference_number']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tl-right">
                        <div class="tl-type <?= $dot_class ?>"><?= $is_payment ? 'Payment' : 'Credit Added' ?></div>
                        <div class="tl-amount <?= $dot_class ?>"><?= $currency_symbol ?> <?= number_format($transaction['amount'], 2) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-transactions">
            <svg style="width:40px;height:40px;color:var(--border);margin-bottom:10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
            <p>No transaction records found</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Footer ── -->
    <div class="footer">
        <div class="footer-left">
            <?php if (!empty($branch['address'])): ?>
            <div><?= htmlspecialchars($branch['address']) ?></div>
            <?php endif; ?>
            <?php if (!empty($branch['phone'])): ?>
            <div><?= htmlspecialchars($branch['phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="footer-right">
            <div>Generated automatically on <?= date('M d, Y \a\t H:i') ?></div>
            <div>For inquiries, please contact your branch.</div>
        </div>
    </div>
</div>

<!-- ── Action Bar ── -->
<div class="action-bar">
    <button class="btn-print" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print Statement
    </button>
</div>

</body>
</html>