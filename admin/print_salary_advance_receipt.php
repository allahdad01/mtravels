<?php
// Initialize the session
session_start();

require_once __DIR__ . '/../includes/permissions.php';
require_permission('hr.salary');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include config file
require_once "../includes/db.php";
require_once "../includes/language_helpers.php";

// Check if advance_id is passed
if (!isset($_GET["advance_id"]) || empty(trim($_GET["advance_id"]))) {
    echo "Invalid advance ID";
    exit();
}

$advance_id = trim($_GET["advance_id"]);

// Get advance details
$sql = "SELECT sa.*, u.name as employee_name, u.email, sm.base_salary,
               ma.name as account_name
        FROM salary_advances sa
        JOIN users u ON sa.user_id = u.id
        JOIN salary_management sm ON u.id = sm.user_id
        JOIN main_account ma ON sa.main_account_id = ma.id
        WHERE sa.id = ? AND sa.tenant_id = ? AND sa.branch_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $advance_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$advance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$advance) {
    echo "Advance not found";
    exit();
}

// Get company/tenant info
$company_sql = "SELECT name FROM tenants WHERE id = ?";
$company_stmt = $pdo->prepare($company_sql);
$company_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$company_stmt->execute();
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

// Get settings for logo
$settings_sql = "SELECT logo, agency_name FROM settings WHERE tenant_id = ?";
$settings_stmt = $pdo->prepare($settings_sql);
$settings_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$settings_stmt->execute();
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

// If no settings found, create a default one
if (!$settings) {
    $settings = ['logo' => '', 'agency_name' => $company['name']];
}

// Get branch info
$branch_sql = "SELECT name, address FROM branches WHERE id = ? AND tenant_id = ?";
$branch_stmt = $pdo->prepare($branch_sql);
$branch_stmt->bindParam(1, $branch_id, PDO::PARAM_INT);
$branch_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$branch_stmt->execute();
$branch = $branch_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('salary_advance_receipt') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink:         #2c3e50;
            --ink-soft:    #4099ff;
            --ink-muted:   #95a5a6;
            --accent:      #2ed8b6;
            --primary:     #4099ff;
            --rule:        #ecf0f1;
            --page-bg:     #f8f9fa;
            --white:       #ffffff;
            --stamp-green: #1a7a4a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--page-bg);
            padding: 20px 10px;
            color: var(--ink);
        }

        /* ── Toolbar ── */
        .toolbar {
            max-width: 760px;
            margin: 0 auto 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            padding: 9px 22px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
            letter-spacing: .02em;
        }
        .btn:active { transform: scale(.97); }
        .btn-print  { background: var(--primary); color: var(--white); }
        .btn-print:hover { background: var(--ink-soft); }
        .btn-close  { background: var(--white); color: var(--ink); border: 1.5px solid var(--rule); }
        .btn-close:hover { border-color: var(--primary); }

        /* ── Paper ── */
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,.06), 0 12px 40px rgba(0,0,0,.09);
            animation: rise .45s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header Band ── */
        .receipt-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            padding: 32px 40px;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 20px;
        }
        .company-name {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--white);
            letter-spacing: .01em;
            line-height: 1.2;
        }
        .company-meta {
            margin-top: 6px;
            font-size: 12px;
            color: rgba(255,255,255,.55);
            line-height: 1.7;
            font-weight: 300;
        }
        .logo-wrap img {
            max-height: 70px;
            max-width: 140px;
            object-fit: contain;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,.1));
        }

        /* ── Title Bar ── */
        .receipt-title-bar {
            background: var(--ink);
            padding: 13px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .receipt-title {
            font-family: 'DM Serif Display', serif;
            font-size: 15px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--white);
        }
        .receipt-no {
            font-size: 12px;
            font-weight: 500;
            color: rgba(255,255,255,.8);
            letter-spacing: .06em;
        }

        /* ── Body ── */
        .receipt-body { padding: 36px 40px; }

        .section { margin-bottom: 30px; }
        .section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 10px;
        }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            border: 1.5px solid var(--rule);
            border-radius: 6px;
            overflow: hidden;
        }
        .info-grid .row { display: contents; }
        .info-grid .row > * {
            padding: 11px 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--rule);
        }
        .info-grid .row:last-child > * { border-bottom: none; }
        .info-grid .row > *:first-child {
            background: #f7f7fb;
            font-weight: 500;
            color: var(--ink-soft);
            border-right: 1.5px solid var(--rule);
        }
        .info-grid .row > *:last-child { color: var(--ink); }

        /* ── Amount Block ── */
        .amount-block {
            background: var(--ink);
            border-radius: 8px;
            padding: 24px 28px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: end;
            gap: 10px;
        }
        .amount-label-main {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255,255,255,.5);
            margin-bottom: 6px;
        }
        .amount-figure {
            font-family: 'DM Serif Display', serif;
            font-size: 38px;
            color: var(--white);
            letter-spacing: -.01em;
            line-height: 1;
        }
        .amount-currency {
            font-size: 18px;
            opacity: .7;
            margin-right: 4px;
            vertical-align: super;
            font-family: 'DM Sans', sans-serif;
            font-weight: 300;
        }
        .amount-meta { text-align: right; }
        .amount-meta-label { font-size: 11px; color: rgba(255,255,255,.45); margin-bottom: 4px; }
        .amount-meta-value { font-size: 13px; color: rgba(255,255,255,.75); font-weight: 500; }

        /* ── Divider ── */
        .divider { border: none; border-top: 1.5px solid var(--rule); margin: 28px 0; }

        /* ── Notes ── */
        .notes-block {
            border: 1.5px solid var(--rule);
            border-left: 4px solid var(--ink-soft);
            border-radius: 0 6px 6px 0;
            padding: 16px 20px;
            margin-bottom: 30px;
        }
        .notes-title {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--ink-soft);
            margin-bottom: 10px;
        }
        .notes-list { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .notes-list li {
            font-size: 12px;
            color: var(--ink-soft);
            padding-left: 16px;
            position: relative;
            line-height: 1.2;
        }
        .notes-list li::before { content: '—'; position: absolute; left: 0; color: var(--ink-muted); font-size: 11px; }

        /* ── Signatures ── */
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 10px;
        }
        .sig-box {
            padding-top: 48px;
            border-top: 1.5px solid var(--ink);
            position: relative;
        }
        .sig-box::before {
            content: '';
            position: absolute;
            top: -1px; left: 0;
            width: 32px; height: 3px;
            background: var(--accent);
        }
        .sig-name { font-size: 12px; font-weight: 600; color: var(--ink); letter-spacing: .04em; }
        .sig-date { margin-top: 5px; font-size: 11px; color: var(--ink-muted); }

        /* ── Footer ── */
        .receipt-footer {
            background: #f7f7fb;
            border-top: 1.5px solid var(--rule);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .footer-left { font-size: 11px; color: var(--ink-muted); line-height: 1.6; }
        .footer-stamp {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--stamp-green);
            border: 2px solid var(--stamp-green);
            padding: 5px 14px;
            border-radius: 3px;
            opacity: .75;
            transform: rotate(-2deg);
            display: inline-block;
            white-space: nowrap;
        }

        /* ── Print ── */
        @media print {
            @page { margin: 0; size: A4; }
            body { background: white; padding: 0; margin: 0; }
            .toolbar { display: none; }
            .receipt { box-shadow: none; border-radius: 0; animation: none; max-width: 100%; margin: 0; }
            .receipt-body { padding: 24px 30px; }
            .receipt-header { padding: 24px 30px; }
            .receipt-title-bar { padding: 10px 30px; }
            .receipt-footer { padding: 12px 30px; }
            .receipt-header,
            .receipt-title-bar,
            .amount-block,
            .info-grid .row > *:first-child,
            .receipt-footer {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .section, .signature-row, .notes-block { page-break-inside: avoid; }
        }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .receipt-header { padding: 24px 22px; grid-template-columns: 1fr; }
            .receipt-title-bar { padding: 11px 22px; flex-direction: column; align-items: flex-start; gap: 4px; }
            .receipt-body { padding: 24px 22px; }
            .receipt-footer { padding: 14px 22px; flex-direction: column; align-items: flex-start; }
            .info-grid { grid-template-columns: 140px 1fr; }
            .amount-block { grid-template-columns: 1fr; }
            .amount-meta { text-align: left; }
            .amount-figure { font-size: 30px; }
            .signature-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Toolbar -->
    <div class="toolbar">
        <button class="btn btn-close" onclick="window.close()"><?= __('close') ?></button>
        <button class="btn btn-print" onclick="window.print()">&#128438; <?= __('print') ?></button>
    </div>

    <div class="receipt">

        <!-- Header Band -->
        <div class="receipt-header">
            <div>
                <div class="company-name"><?php echo htmlspecialchars($company['name'] ?? 'Company'); ?></div>
                <div class="company-meta">
                    <?php if ($branch): ?>
                        <?php echo htmlspecialchars($branch['name']); ?>
                        <?php if ($branch['address']): ?>
                            &nbsp;·&nbsp;<?php echo htmlspecialchars($branch['address']); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($settings['logo'])): ?>
                <div class="logo-wrap">
                    <img src="../uploads/logo/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Company Logo">
                </div>
            <?php endif; ?>
        </div>

        <!-- Title Bar -->
        <div class="receipt-title-bar">
            <span class="receipt-title"><?= __('salary_advance_receipt') ?></span>
            <span class="receipt-no"># <?php echo htmlspecialchars($advance['receipt']); ?></span>
        </div>

        <!-- Body -->
        <div class="receipt-body">

            <!-- Amount Block -->
            <div class="amount-block">
                <div>
                    <div class="amount-label-main"><?= __('total_amount') ?></div>
                    <div class="amount-figure">
                        <span class="amount-currency"><?php echo htmlspecialchars($advance['currency']); ?></span><?php echo number_format($advance['amount'], 2); ?>
                    </div>
                </div>
                <div class="amount-meta">
                    <div class="amount-meta-label"><?= __('advance_date') ?></div>
                    <div class="amount-meta-value"><?php echo date('d M Y', strtotime($advance['advance_date'])); ?></div>
                </div>
            </div>



            <!-- Employee Information -->
            <div class="section">
                <div class="section-label"><?= __('employee_information') ?></div>
                <div class="info-grid">
                    <div class="row">
                        <div><?= __('employee_name') ?></div>
                        <div><?php echo htmlspecialchars($advance['employee_name']); ?></div>
                    </div>
                    <div class="row">
                        <div><?= __('email') ?></div>
                        <div><?php echo htmlspecialchars($advance['email']); ?></div>
                    </div>
                    <div class="row">
                        <div><?= __('base_salary') ?></div>
                        <div><?php echo number_format($advance['base_salary'], 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Advance Details -->
            <div class="section">
                <div class="section-label"><?= __('advance_details') ?></div>
                <div class="info-grid">
                    <div class="row">
                        <div><?= __('currency') ?></div>
                        <div><?php echo htmlspecialchars($advance['currency']); ?></div>
                    </div>
                    <div class="row">
                        <div><?= __('advance_amount') ?></div>
                        <div><?php echo htmlspecialchars($advance['currency']); ?> <?php echo number_format($advance['amount'], 2); ?></div>
                    </div>
                    <div class="row">
                        <div><?= __('receipt_no') ?></div>
                        <div><?php echo htmlspecialchars($advance['receipt']); ?></div>
                    </div>
                    <div class="row">
                        <div><?= __('advance_date') ?></div>
                        <div><?php echo date('Y-m-d', strtotime($advance['advance_date'])); ?></div>
                    </div>
                    <?php if ($advance['description']): ?>
                    <div class="row">
                        <div><?= __('description') ?></div>
                        <div><?php echo htmlspecialchars($advance['description']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes -->
            <div class="notes-block">
                <div class="notes-title"><?= __('important_notes') ?></div>
                <ul class="notes-list">
                    <li><?= __('this_advance_will_be_recovered_from_future_salary') ?></li>
                    <li><?= __('employee_must_sign_below_to_acknowledge_receipt') ?></li>
                    <li><?= __('please_keep_a_copy_for_your_records') ?></li>
                </ul>
            </div>

            <!-- Signature Section -->
            <div class="signature-row">
                <div class="sig-box">
                    <div class="sig-name"><?= __('employee_signature') ?></div>
                    <div class="sig-date"><?= __('date') ?>: _______________</div>
                </div>
                <div class="sig-box">
                    <div class="sig-name"><?= __('approver_signature') ?></div>
                    <div class="sig-date"><?= __('date') ?>: _______________</div>
                </div>
            </div>

        </div><!-- /receipt-body -->

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="footer-left">
                <p><?= __('this_is_a_computer_generated_document_and_is_valid_without_signature') ?></p>
                <p><?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            <div class="footer-stamp"><?= __('approved') ?></div>
        </div>

    </div><!-- /receipt -->

</body>
</html>