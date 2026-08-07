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
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Include database connection
include '../../includes/db.php';

// Initialize variables
$expense = null;
$transactions = [];
$errorMessage = '';

// Get expense ID from URL
if (isset($_GET['id'])) {
    $expenseId = DbSecurity::validateInput($_GET['id'], 'int');
    
    if (!$expenseId) {
        $errorMessage = "Invalid expense ID.";
    } else {
        $query = "SELECT e.*, ec.name as category_name, esc.name as sub_category_name, ma.name as account_name, mat.receipt as receipt_number
                  FROM expenses e
                  LEFT JOIN expense_categories ec ON e.category_id = ec.id
                  LEFT JOIN expense_categories esc ON e.sub_category_id = esc.id
                  LEFT JOIN main_account ma ON e.main_account_id = ma.id
                  LEFT JOIN main_account_transactions mat ON e.id = mat.reference_id AND mat.transaction_of = 'expense'
                  WHERE e.id = ? AND e.tenant_id = ? AND e.branch_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$expenseId, $tenant_id, $branch_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            $errorMessage = "Expense not found.";
        } else {
            $transactionQuery = "SELECT 
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.created_at AS transaction_date
                FROM main_account_transactions mat
                WHERE mat.reference_id = ? AND mat.transaction_of = 'expense' AND mat.tenant_id = ? AND mat.branch_id = ?";
            $stmt = $pdo->prepare($transactionQuery);
            $stmt->execute([$expenseId, $tenant_id, $branch_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $errorMessage = "Expense ID is required.";
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
    <title>Expense #<?php echo isset($expense['id']) ? h($expense['id']) : 'Not Found'; ?> — Receipt</title>
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

        /* ── Toolbar (screen only) ─────────────────────────── */
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

        /* ── Receipt card ──────────────────────────────────── */
        .receipt {
            max-width: 760px;
            margin: 0 auto;
            background: var(--paper);
            border-radius: 12px;
            box-shadow: 0 2px 24px rgba(30,30,80,.08);
            overflow: hidden;
        }

        /* ── Header band ───────────────────────────────────── */
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

        /* ── Body padding ──────────────────────────────────── */
        .receipt-body { padding: 36px 40px; }

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
        .kv-val.amount-display {
            font-family: 'DM Mono', monospace;
            font-size: 17px;
            font-weight: 500;
            color: var(--debit);
        }
        .kv-val strong { font-weight: 600; }

        /* ── Amount highlight strip ────────────────────────── */
        .amount-strip {
            background: #fef2f2;
            border: 1px solid #fecaca;
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
            color: #b91c1c;
        }
        .amount-strip .as-value {
            font-family: 'DM Mono', monospace;
            font-size: 22px;
            font-weight: 500;
            color: var(--debit);
        }

        /* ── Transaction table ─────────────────────────────── */
        .tx-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 32px;
        }
        .tx-table thead tr {
            background: var(--surface);
        }
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
        .tx-table td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--rule);
            color: var(--ink);
        }
        .tx-table tbody tr:last-child td { border-bottom: none; }
        .tx-table tbody tr:hover { background: var(--surface); }
        .tx-table .mono { font-family: 'DM Mono', monospace; }
        .tx-table .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-debit  { background: #fef2f2; color: var(--debit); }
        .badge-credit { background: #f0fdf4; color: var(--credit); }

        /* ── Receipt image ─────────────────────────────────── */
        .receipt-image-wrap {
            display: inline-block;
        }
        .receipt-image-wrap img {
            width: auto;
            height: auto;
            max-width: 180px;
            max-height: 180px;
            border-radius: 6px;
            border: 1px solid var(--rule);
            display: block;
            object-fit: contain;
        }
        .receipt-image-wrap small {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            color: var(--ink-light);
            font-family: 'DM Mono', monospace;
        }

        /* ── Footer ────────────────────────────────────────── */
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

        /* ── Error state ───────────────────────────────────── */
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

        /* ── Print ─────────────────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            .tx-table tbody tr:hover { background: transparent; }
        }
    </style>
</head>
<body>

<?php if (!empty($errorMessage)): ?>
    <div class="error-card">
        <strong>Error:</strong> <?php echo h($errorMessage); ?>
    </div>
<?php else: ?>

    <!-- Toolbar (screen only) -->
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

        <!-- Header -->
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
                    <?php if (!empty($branch['email'])): ?>
                        &nbsp;·&nbsp; <?php echo h($branch['email']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-badge">
                <div class="label">Document</div>
                <div class="doc-title">Expense</div>
                <div class="doc-id"># <?php echo h($expense['id']); ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="receipt-body">

            <!-- Amount strip -->
            <div class="amount-strip">
                <span class="as-label">Total Amount</span>
                <span class="as-value"><?php echo h($expense['currency']); ?> <?php echo h(number_format($expense['amount'], 2)); ?></span>
            </div>

            <!-- Expense details -->
            <div class="section-label">Expense Details</div>
            <div class="kv-grid">
                <div class="kv-row">
                    <div class="kv-key">Expense ID</div>
                    <div class="kv-val"><strong><?php echo h($expense['id']); ?></strong></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key">Category</div>
                    <div class="kv-val"><?php echo h($expense['category_name']); ?></div>
                </div>
                <?php if (!empty($expense['sub_category_name'])): ?>
                <div class="kv-row">
                    <div class="kv-key">Sub-Category</div>
                    <div class="kv-val"><?php echo h($expense['sub_category_name']); ?></div>
                </div>
                <?php endif; ?>
                <div class="kv-row">
                    <div class="kv-key">Date</div>
                    <div class="kv-val"><?php echo h(date('F d, Y', strtotime($expense['date']))); ?></div>
                </div>
                <div class="kv-row">
                    <div class="kv-key">Account</div>
                    <div class="kv-val"><?php echo h($expense['account_name']); ?></div>
                </div>
                <?php if (!empty($expense['receipt_number'])): ?>
                <div class="kv-row">
                    <div class="kv-key">Receipt No.</div>
                    <div class="kv-val"><?php echo h($expense['receipt_number']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (isset($expense['allocation_id']) && $expense['allocation_id']): ?>
                <div class="kv-row">
                    <div class="kv-key">Budget Allocation</div>
                    <div class="kv-val">Allocation #<?php echo h($expense['allocation_id']); ?></div>
                </div>
                <?php endif; ?>
                <div class="kv-row">
                    <div class="kv-key">Recorded On</div>
                    <div class="kv-val"><?php echo h(date('F d, Y · H:i', strtotime($expense['created_at']))); ?></div>
                </div>
                <?php if (!empty($expense['description'])): ?>
                <div class="kv-row">
                    <div class="kv-key">Description</div>
                    <div class="kv-val"><?php echo h($expense['description']); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Receipt image -->
            <?php if (!empty($expense['receipt_file'])): ?>
                <div class="section-label">Attached Receipt</div>
                <div class="receipt-image-wrap" style="margin-bottom:32px;">
                    <img src="../../uploads/expense_receipt/<?php echo h($expense['receipt_file']); ?>" alt="Receipt">
                    <small><?php echo h($expense['receipt_file']); ?></small>
                </div>
            <?php endif; ?>

            <!-- Transactions -->
            <?php if (!empty($transactions)): ?>
                <div class="section-label">Transaction History</div>
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="mono"><?php echo h($tx['id']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($tx['type']) === 'debit' ? 'debit' : 'credit'; ?>">
                                    <?php echo h($tx['type']); ?>
                                </span>
                            </td>
                            <td class="mono"><?php echo h($tx['currency']); ?> <?php echo h(number_format($tx['amount'], 2)); ?></td>
                            <td><?php echo h($tx['description']); ?></td>
                            <td class="mono"><?php echo h(date('Y-m-d H:i', strtotime($tx['transaction_date']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div><!-- /receipt-body -->

        <!-- Footer -->
        <div class="receipt-footer">
            <span class="generated">Generated on <?php echo date('F d, Y · H:i:s'); ?></span>
            <span class="tagline">Thank you for your business</span>
        </div>

    </div><!-- /receipt -->

<?php endif; ?>

<script>
    window.onload = function() {
        setTimeout(function() { window.print(); }, 600);
    };
</script>
</body>
</html>