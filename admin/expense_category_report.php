<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define h() function for HTML escaping if not already defined
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Include database connection
include '../includes/db.php';

// Language helpers for translations and RTL detection
require_once '../includes/language_helpers.php';

// Initialize variables
$category = null;
$parentName = '';
$expenses = [];
$errorMessage = '';

// Get category ID from URL
if (isset($_GET['category_id'])) {
    $categoryId = DbSecurity::validateInput($_GET['category_id'], 'int');

    if (!$categoryId) {
        $errorMessage = "Invalid category ID.";
    } else {
        $query = "SELECT id, name, parent_id FROM expense_categories WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$categoryId, $tenant_id, $branch_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            $errorMessage = "Category not found.";
        } else {
            if (!empty($category['parent_id'])) {
                $parentStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $parentStmt->execute([$category['parent_id'], $tenant_id, $branch_id]);
                $parentName = $parentStmt->fetchColumn() ?: '';
            }

            // Current month's expenses for this category
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');

            // Top-level category: direct expenses plus children's expenses
            // (sub-category expenses keep the parent's category_id).
            // Sub-category: expenses tagged with its sub_category_id.
            if (!empty($category['parent_id'])) {
                $expenseQuery = "SELECT e.*, ec.name as category_name, esc.name as sub_category_name, ma.name as account_name
                                FROM expenses e
                                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                                LEFT JOIN expense_categories esc ON e.sub_category_id = esc.id
                                LEFT JOIN main_account ma ON e.main_account_id = ma.id
                                WHERE e.sub_category_id = ? AND e.date BETWEEN ? AND ? AND e.tenant_id = ? AND e.branch_id = ?
                                ORDER BY e.date DESC";
                $expenseParams = [$categoryId, $startDate, $endDate, $tenant_id, $branch_id];
            } else {
                $expenseQuery = "SELECT e.*, ec.name as category_name, esc.name as sub_category_name, ma.name as account_name
                                FROM expenses e
                                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                                LEFT JOIN expense_categories esc ON e.sub_category_id = esc.id
                                LEFT JOIN main_account ma ON e.main_account_id = ma.id
                                WHERE e.category_id = ? AND e.date BETWEEN ? AND ? AND e.tenant_id = ? AND e.branch_id = ?
                                ORDER BY e.date DESC";
                $expenseParams = [$categoryId, $startDate, $endDate, $tenant_id, $branch_id];
            }
            $expenseStmt = $pdo->prepare($expenseQuery);
            $expenseStmt->execute($expenseParams);
            $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $errorMessage = "Category ID is required.";
}

// Settings (agency name / logo)
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

// Branch info
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

// Totals per currency
$currencyTotals = [];
foreach ($expenses as $exp) {
    $cur = $exp['currency'] ?? 'USD';
    $currencyTotals[$cur] = ($currencyTotals[$cur] ?? 0) + $exp['amount'];
}

$isRtl = is_rtl();
$dir = $isRtl ? 'rtl' : 'ltr';
$hasSubColumn = empty($category['parent_id']);
?>
<!DOCTYPE html>
<html lang="en" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($category['name'] ?? 'Expense Report') ?> — <?= __('expense_report') ?></title>
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

        /* ── Toolbar (screen only) ─────────────────────────── */
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            max-width: 860px;
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

        /* ── Report card ───────────────────────────────────── */
        .report {
            max-width: 860px;
            margin: 0 auto;
            background: var(--paper);
            border-radius: 12px;
            box-shadow: 0 2px 24px rgba(30,30,80,.08);
            overflow: hidden;
        }

        /* ── Header band ───────────────────────────────────── */
        .report-header {
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
        html[dir="ltr"] .header-badge { text-align: right; }
        html[dir="rtl"] .header-badge { text-align: left; }
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
            line-height: 1.1;
        }
        .header-badge .doc-id {
            font-family: 'DM Mono', monospace;
            font-size: 14px;
            color: rgba(255,255,255,.6);
            margin-top: 6px;
        }

        /* ── Body padding ──────────────────────────────────── */
        .report-body { padding: 36px 40px; }

        /* ── Section label ─────────────────────────────────── */
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

        /* ── Key-value grid ────────────────────────────────── */
        .kv-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin-bottom: 32px;
            border: 1px solid var(--rule);
            border-radius: 8px;
            overflow: hidden;
        }
        .kv-row { display: contents; }
        .kv-row .kv-key,
        .kv-row .kv-val {
            padding: 11px 16px;
            border-bottom: 1px solid var(--rule);
        }
        .kv-row:last-child .kv-key,
        .kv-row:last-child .kv-val { border-bottom: none; }
        .kv-key {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-mid);
            background: var(--surface);
            border-right: 1px solid var(--rule);
        }
        html[dir="ltr"] .kv-key { border-right: 1px solid var(--rule); border-left: none; }
        html[dir="rtl"] .kv-key { border-left: 1px solid var(--rule); border-right: none; }
        .kv-val {
            font-size: 13px;
            color: var(--ink);
            background: var(--paper);
        }
        .kv-val strong { font-weight: 600; }

        /* ── Expense table ─────────────────────────────────── */
        .tx-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 32px;
        }
        .tx-table thead tr { background: var(--surface); }
        .tx-table th {
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--ink-light);
            border-bottom: 2px solid var(--rule);
        }
        html[dir="rtl"] .tx-table th { text-align: right; }
        .tx-table td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--rule);
            color: var(--ink);
        }
        .tx-table tbody tr:last-child td { border-bottom: none; }
        .tx-table tbody tr:hover { background: var(--surface); }
        .tx-table .mono { font-family: 'DM Mono', monospace; }
        .tx-table .num { text-align: right; font-family: 'DM Mono', monospace; }
        html[dir="rtl"] .tx-table .num { text-align: left; }
        .tx-table .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-sub { background: var(--accent-bg); color: var(--accent); }

        /* ── Totals ────────────────────────────────────────── */
        .totals {
            background: var(--surface);
            border: 1px solid var(--rule);
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .totals .tt-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--ink-light);
        }
        .totals .tt-values {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .totals .tt-value {
            font-family: 'DM Mono', monospace;
            font-size: 16px;
            font-weight: 500;
            color: var(--ink);
        }
        .totals .tt-value small { font-size: 11px; color: var(--ink-light); font-weight: 400; }

        /* ── Empty state ───────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--ink-light);
            font-size: 13px;
        }

        /* ── Footer ────────────────────────────────────────── */
        .report-footer {
            border-top: 1px solid var(--rule);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
        }
        .report-footer .generated { font-size: 11px; color: var(--ink-light); }

        /* ── Error state ───────────────────────────────────── */
        .error-card {
            max-width: 500px;
            margin: 80px auto;
            padding: 32px;
            background: var(--paper);
            border-radius: 12px;
            border-left: 4px solid #dc2626;
            box-shadow: 0 2px 20px rgba(0,0,0,.07);
            font-size: 14px;
            color: #dc2626;
        }

        /* ── Print ─────────────────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .report {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            .tx-table tbody tr:hover { background: transparent; }
            .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .tx-table thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .totals { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<?php if (!empty($errorMessage)): ?>
    <div class="error-card">
        <strong>Error:</strong> <?= h($errorMessage) ?>
    </div>
<?php else: ?>

    <!-- Toolbar (screen only) -->
    <div class="toolbar">
        <button class="btn btn-ghost" onclick="window.close()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            <?= __('close') ?>
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <?= __('print') ?>
        </button>
    </div>

    <div class="report">

        <!-- Header -->
        <div class="report-header">
            <div class="header-brand">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="../uploads/logo/<?= h($settings['logo']) ?>" alt="Logo">
                <?php endif; ?>
                <h1><?= h($settings['agency_name']) ?></h1>
                <?php if (!empty($branch['name'])): ?>
                    <div class="branch-name"><?= h($branch['name']) ?></div>
                <?php endif; ?>
                <div class="contact">
                    <?php if (!empty($branch['address'])): ?>
                        <?= h($branch['address']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($branch['phone'])): ?>
                        <?= h($branch['phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($branch['email'])): ?>
                        &nbsp;·&nbsp; <?= h($branch['email']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-badge">
                <div class="label"><?= __('expense_report') ?></div>
                <div class="doc-title">
                    <?= h($category['name']) ?>
                </div>
                <div class="doc-id">
                    <?php if ($parentName !== ''): ?>
                        <?= h($parentName) ?> › <?= h($category['name']) ?>
                    <?php else: ?>
                        #<?= h($category['id']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="report-body">

            <!-- Category info -->
            <div class="section-label"><?= __('expense_report') ?></div>
            <div class="kv-grid">
                <div class="kv-row">
                    <div class="kv-key"><?= __('date') ?></div>
                    <div class="kv-val"><strong><?= h(date('F d, Y', strtotime($startDate))) ?> — <?= h(date('F d, Y', strtotime($endDate))) ?></strong></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key"><?= __('entries') ?></div>
                    <div class="kv-val"><strong><?= count($expenses) ?></strong></div>
                </div>
                <?php if ($parentName !== ''): ?>
                <div class="kv-row">
                    <div class="kv-key"><?= __('parent_category') ?></div>
                    <div class="kv-val"><?= h($parentName) ?></div>
                </div>
                <?php endif; ?>
                <div class="kv-row">
                    <div class="kv-key"><?= __('currency') ?></div>
                    <div class="kv-val"><?= h(implode(' · ', array_keys($currencyTotals))) ?: '—' ?></div>
                </div>
            </div>

            <!-- Expenses table -->
            <?php if ($expenses): ?>
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('date') ?></th>
                        <?php if ($hasSubColumn): ?>
                        <th><?= __('sub_category') ?></th>
                        <?php endif; ?>
                        <th><?= __('description') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('currency') ?></th>
                        <th><?= __('account') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $index => $expense): ?>
                    <tr>
                        <td class="mono"><?= $index + 1 ?></td>
                        <td class="mono"><?= h(date('Y-m-d', strtotime($expense['date']))) ?></td>
                        <?php if ($hasSubColumn): ?>
                        <td>
                            <?php if (!empty($expense['sub_category_name'])): ?>
                                <span class="badge badge-sub"><?= h($expense['sub_category_name']) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?= h($expense['description']) ?></td>
                        <td class="num"><?= h(number_format($expense['amount'], 2)) ?></td>
                        <td class="mono"><?= h($expense['currency'] ?? 'USD') ?></td>
                        <td><?= h($expense['account_name'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals">
                <span class="tt-label"><?= __('total') ?></span>
                <div class="tt-values">
                    <?php foreach ($currencyTotals as $cur => $amt): ?>
                        <span class="tt-value"><?= h($cur) ?> <?= h(number_format($amt, 2)) ?> <small><?= h($cur) ?></small></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <?= __('no_expenses_found') ?>
            </div>
            <?php endif; ?>

        </div><!-- /report-body -->

        <!-- Footer -->
        <div class="report-footer">
            <span class="generated"><?= __('generated_on') ?> <?= date('F d, Y · H:i:s') ?></span>
        </div>

    </div><!-- /report -->

<?php endif; ?>

</body>
</html>
