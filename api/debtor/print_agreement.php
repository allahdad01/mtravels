<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';

// Validate and sanitize debtor ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid debtor ID');
}
$debtor_id = intval($_GET['id']);

// Fetch debtor information
$stmt = $pdo->prepare("SELECT d.*, m.name as main_account_name FROM debtors d
                        LEFT JOIN main_account m ON d.main_account_id = m.id
                        WHERE d.id = ? AND d.tenant_id = ? AND d.branch_id = ?");
$stmt->bindParam(1, $debtor_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$debtor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$debtor) {
    die('Debtor not found');
}

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

// Fetch current admin user
try {
    $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $userStmt->execute([$_SESSION['user_id'], $tenant_id, $branch_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user = null;
}

// Single reference — computed once
$agreement_ref  = 'DEBT-' . $debtor['id'] . '-' . date('Ymd');
$agreement_date = date('F j, Y');

// Default agreement terms
if (empty($debtor['agreement_terms'])) {
    $debtor['agreement_terms'] =
        "1. The debtor agrees to pay the full amount due by the agreed deadline.\n" .
        "2. Late payments may be subject to additional fees as per company policy.\n" .
        "3. Failure to make scheduled payments may result in legal action.\n" .
        "4. The debtor must provide advance notice for any payment delays.";
}

// Currency symbol helper
function getCurrencySymbol($currency) {
    switch ($currency) {
        case 'USD':    return '$';
        case 'EUR':    return '€';
        case 'AFS':    return '؋';
        case 'DARHAM': return 'د.إ';
        default:       return '';
    }
}
$currency_symbol = getCurrencySymbol($debtor['currency']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debt Agreement — <?= htmlspecialchars($debtor['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --crimson:    #C0392B;
            --crimson-dk: #96281B;
            --crimson-lt: #FDECEA;
            --navy:       #1A237E;
            --ink:        #1A1A2E;
            --ink-mid:    #4A4A6A;
            --ink-muted:  #8E8EA8;
            --border:     #E8E8F0;
            --surface:    #F7F7FB;
            --white:      #FFFFFF;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            padding: 24px 20px 40px;
        }

        /* ── Page — fixed A4 proportions on screen ── */
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,.04), 0 20px 60px rgba(0,0,0,.08);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* ── Watermark ── */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-family: 'Playfair Display', serif;
            font-size: 80px;
            font-weight: 700;
            color: rgba(26, 35, 126, 0.04);
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
            letter-spacing: .08em;
        }

        .page > *:not(.watermark) { position: relative; z-index: 1; }

        /* ── Top bar ── */
        .top-bar { background: var(--crimson); height: 5px; flex-shrink: 0; }

        /* ── Header ── */
        .header {
            padding: 16px 32px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            gap: 16px;
            flex-shrink: 0;
        }

        .header-left { display: flex; align-items: center; gap: 12px; }

        .logo-wrap img {
            max-width: 42px; max-height: 42px;
            object-fit: contain; border-radius: 6px;
        }

        .logo-placeholder {
            width: 42px; height: 42px;
            background: var(--crimson-lt);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 18px; color: var(--crimson); font-weight: 700;
        }

        .agency-name {
            font-family: 'Playfair Display', serif;
            font-size: 16px; color: var(--ink); line-height: 1.2;
        }

        .branch-name { font-size: 11px; color: var(--ink-muted); margin-top: 2px; }

        .header-right { text-align: right; }

        .doc-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px; color: var(--crimson);
        }

        .doc-meta { margin-top: 4px; display: flex; flex-direction: column; gap: 1px; align-items: flex-end; }
        .doc-meta span { font-size: 11px; color: var(--ink-muted); }
        .doc-meta strong { color: var(--ink-mid); font-weight: 500; }

        /* ── Body area (grows to fill page) ── */
        .body-content { flex: 1; display: flex; flex-direction: column; }

        /* ── Two-column body ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid var(--border);
        }

        .col-section {
            padding: 14px 20px 14px 32px;
        }

        .col-section:first-child {
            border-right: 1px solid var(--border);
        }

        .col-section:last-child {
            padding-left: 20px;
            padding-right: 32px;
        }

        /* ── Generic section ── */
        .section {
            padding: 12px 32px;
            border-bottom: 1px solid var(--border);
        }

        .section-heading {
            font-family: 'Playfair Display', serif;
            font-size: 12.5px;
            color: var(--ink);
            margin-bottom: 10px;
            padding-bottom: 7px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .section-heading .heading-icon {
            width: 20px; height: 20px;
            background: var(--crimson-lt);
            border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .section-heading .heading-icon svg { width: 11px; height: 11px; color: var(--crimson); }

        /* ── Detail grid ── */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px 20px;
        }

        .detail-cell .label {
            font-size: 9.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--ink-muted);
            margin-bottom: 3px;
        }

        .detail-cell .value {
            font-size: 12.5px;
            font-weight: 500;
            color: var(--ink);
            word-break: break-word;
        }

        /* ── Debt amount highlight ── */
        .amount-highlight {
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            background: var(--crimson-lt);
            border: 1px solid rgba(192,57,43,.18);
            border-radius: 6px;
            padding: 5px 12px;
            margin-top: 3px;
        }

        .amount-highlight .symbol { font-size: 12px; color: var(--crimson); font-weight: 600; }
        .amount-highlight .number {
            font-family: 'Playfair Display', serif;
            font-size: 20px; font-weight: 700; color: var(--crimson);
        }
        .amount-highlight .currency-code {
            font-size: 11px; color: var(--crimson-dk); font-weight: 500; opacity: .8;
        }

        /* ── Terms box ── */
        .terms-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--navy);
            border-radius: 0 6px 6px 0;
            padding: 10px 14px;
            font-size: 11.5px;
            line-height: 1.75;
            color: var(--ink-mid);
            white-space: pre-line;
        }

        /* ── Legal banner ── */
        .legal-banner {
            background: #F0F0F8;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 10.5px;
            color: var(--ink-muted);
            line-height: 1.5;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .legal-banner svg { width: 13px; height: 13px; flex-shrink: 0; margin-top: 1px; color: var(--navy); }

        /* ── Signature grid ── */
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 10px;
        }

        .sig-box { display: flex; flex-direction: column; }

        .sig-label {
            font-size: 9.5px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--ink-muted); margin-bottom: 4px;
        }

        .sig-name { font-size: 12px; font-weight: 500; color: var(--ink); margin-bottom: 40px; }

        .sig-line {
            border-top: 1.5px solid var(--ink);
            padding-top: 6px;
            font-size: 11px; color: var(--ink-mid);
        }

        .sig-sub { font-size: 10px; color: var(--ink-muted); margin-top: 2px; }

        /* ── Footer ── */
        .footer {
            padding: 10px 32px;
            background: var(--surface);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            flex-shrink: 0;
        }

        .footer-left, .footer-right { font-size: 10px; color: var(--ink-muted); line-height: 1.5; }
        .footer-right { text-align: right; }

        /* ── Action bar (screen only) ── */
        .action-bar {
            width: 210mm;
            margin: 16px auto 0;
            display: flex;
            justify-content: flex-end;
        }

        .btn-print {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px;
            background: var(--crimson); color: #fff;
            border: none; border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 600; cursor: pointer;
            letter-spacing: .02em;
            transition: background .15s, transform .1s;
        }

        .btn-print:hover { background: var(--crimson-dk); transform: translateY(-1px); }
        .btn-print svg { width: 15px; height: 15px; }

        /* ── Print ── */
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .page {
                width: 100%; min-height: 0;
                box-shadow: none; border-radius: 0;
            }
            .action-bar { display: none; }
            .top-bar, .terms-box, .amount-highlight,
            .section-heading .heading-icon,
            .legal-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="watermark"><?= htmlspecialchars($settings['agency_name']) ?></div>
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
            <div class="doc-title">Debt Agreement</div>
            <div class="doc-meta">
                <span>Ref: <strong><?= $agreement_ref ?></strong></span>
                <span>Date: <strong><?= $agreement_date ?></strong></span>
            </div>
        </div>
    </div>

    <div class="body-content">

        <!-- ── Debtor Info + Debt Details side by side ── -->
        <div class="two-col">

            <!-- Left: Debtor Information -->
            <div class="col-section">
                <div class="section-heading">
                    <span class="heading-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    Debtor Information
                </div>
                <div class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-cell">
                        <div class="label">Full Name</div>
                        <div class="value"><?= htmlspecialchars($debtor['name']) ?></div>
                    </div>
                    <?php if (!empty($debtor['email'])): ?>
                    <div class="detail-cell">
                        <div class="label">Email Address</div>
                        <div class="value"><?= htmlspecialchars($debtor['email']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($debtor['phone'])): ?>
                    <div class="detail-cell">
                        <div class="label">Phone Number</div>
                        <div class="value"><?= htmlspecialchars($debtor['phone']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($debtor['address'])): ?>
                    <div class="detail-cell">
                        <div class="label">Address</div>
                        <div class="value"><?= htmlspecialchars($debtor['address']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Debt Details -->
            <div class="col-section">
                <div class="section-heading">
                    <span class="heading-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                        </svg>
                    </span>
                    Debt Details
                </div>
                <div class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-cell">
                        <div class="label">Outstanding Amount</div>
                        <div class="amount-highlight">
                            <span class="symbol"><?= $currency_symbol ?></span>
                            <span class="number"><?= number_format($debtor['balance'], 2) ?></span>
                            <span class="currency-code"><?= htmlspecialchars($debtor['currency']) ?></span>
                        </div>
                    </div>
                    <div class="detail-cell">
                        <div class="label">Linked Account</div>
                        <div class="value"><?= htmlspecialchars($debtor['main_account_name'] ?? 'Not specified') ?></div>
                    </div>
                    <div class="detail-cell">
                        <div class="label">Agreement Reference</div>
                        <div class="value"><?= $agreement_ref ?></div>
                    </div>
                    <div class="detail-cell">
                        <div class="label">Agreement Date</div>
                        <div class="value"><?= $agreement_date ?></div>
                    </div>
                </div>
            </div>

        </div><!-- /two-col -->

        <!-- ── Terms & Conditions ── -->
        <div class="section">
            <div class="section-heading">
                <span class="heading-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </span>
                Terms &amp; Conditions
            </div>
            <div class="terms-box"><?= nl2br(htmlspecialchars($debtor['agreement_terms'])) ?></div>
        </div>

        <!-- ── Signatures ── -->
        <div class="section" style="border-bottom: none;">
            <div class="section-heading">
                <span class="heading-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    </svg>
                </span>
                Signatures
            </div>
            <div class="legal-banner">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>By signing below, both parties confirm they have read, understood, and agree to the terms and conditions stated in this agreement. This document constitutes a legally binding obligation.</span>
            </div>
            <div class="signature-grid">
                <div class="sig-box">
                    <div class="sig-label">Debtor</div>
                    <div class="sig-name"><?= htmlspecialchars($debtor['name']) ?></div>
                    <div class="sig-line">Debtor's Signature</div>
                    <div class="sig-sub">Date: ___________________</div>
                </div>
                <div class="sig-box">
                    <div class="sig-label">Authorized Representative</div>
                    <div class="sig-name"><?= htmlspecialchars($user['name'] ?? $settings['agency_name']) ?></div>
                    <div class="sig-line">Authorized Signature</div>
                    <div class="sig-sub">Date: ___________________</div>
                </div>
            </div>
        </div>

    </div><!-- /body-content -->

    <!-- ── Footer ── -->
    <div class="footer">
        <div class="footer-left">
            <?php if (!empty($branch['address'])): ?>
            <div><?= htmlspecialchars($branch['address']) ?></div>
            <?php endif; ?>
            <?php if (!empty($branch['phone'])): ?>
            <div>Tel: <?= htmlspecialchars($branch['phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="footer-right">
            <div>Generated on <?= date('M d, Y \a\t H:i') ?></div>
            <div>Ref: <?= $agreement_ref ?></div>
        </div>
    </div>
</div>

<!-- ── Action Bar ── -->
<div class="action-bar">
    <button class="btn-print" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print Agreement
    </button>
</div>

</body>
</html>