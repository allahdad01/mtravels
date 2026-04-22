<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include header which provides $settings, $user, and other globals
require_once('../includes/header.php');

// Database connection
require_once('../includes/db.php');


// Get tenant_id and branch_id from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

// Month and year filter
$currentMonth = date('m');
$currentYear = date('Y');

$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear  = isset($_GET['year'])  ? $_GET['year']  : $currentYear;

$startDate = $selectedYear . '-' . $selectedMonth . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

// Rollover logic
$currentDate  = date('Y-m-d');
$selectedDate = $selectedYear . '-' . $selectedMonth . '-01';

if (strtotime($selectedDate) >= strtotime(date('Y-m-01'))) {
    $previousMonth = date('m', strtotime('-1 month', strtotime($selectedDate)));
    $previousYear  = date('Y', strtotime('-1 month', strtotime($selectedDate)));
    $previousStart = $previousYear . '-' . $previousMonth . '-01';
    $previousEnd   = date('Y-m-t', strtotime($previousStart));

    $rolloverCheckStmt = $pdo->prepare("
        SELECT COUNT(*) FROM global_budget_allocations
        WHERE allocation_date >= ? AND description LIKE '%Rollover%'
        AND tenant_id = ? AND branch_id = ?
    ");
    $rolloverCheckStmt->execute([$startDate, $tenant_id, $branch_id]);
    $rolloverDone = $rolloverCheckStmt->fetchColumn();

    if ($rolloverDone == 0) {
        $remainingStmt = $pdo->prepare("
            SELECT main_account_id, SUM(remaining_amount) as total_remaining, currency
            FROM global_budget_allocations
            WHERE allocation_date BETWEEN ? AND ? AND remaining_amount > 0
            AND tenant_id = ? AND branch_id = ?
            GROUP BY main_account_id, currency
        ");
        $remainingStmt->execute([$previousStart, $previousEnd, $tenant_id, $branch_id]);
        $remainingAmounts = $remainingStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($remainingAmounts)) {
            $pdo->beginTransaction();
            try {
                foreach ($remainingAmounts as $remaining) {
                    if ($remaining['total_remaining'] > 0) {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO global_budget_allocations
                            (main_account_id, allocated_amount, remaining_amount, currency, allocation_date, description, tenant_id, branch_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $description = "Rollover from " . date('F Y', strtotime($previousStart));
                        $insertStmt->execute([
                            $remaining['main_account_id'],
                            $remaining['total_remaining'],
                            $remaining['total_remaining'],
                            $remaining['currency'],
                            $startDate,
                            $description,
                            $tenant_id,
                            $branch_id
                        ]);

                        $updateStmt = $pdo->prepare("
                            UPDATE global_budget_allocations
                            SET remaining_amount = 0,
                                description = CONCAT(description, ' (Rolled over)')
                            WHERE allocation_date BETWEEN ? AND ?
                              AND main_account_id = ?
                              AND currency = ?
                              AND remaining_amount > 0
                              AND tenant_id = ?
                              AND branch_id = ?
                        ");
                        $updateStmt->execute([$previousStart, $previousEnd, $remaining['main_account_id'], $remaining['currency'], $tenant_id, $branch_id]);
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
}

// Fetch main accounts
$stmt = $pdo->prepare("SELECT * FROM main_account WHERE tenant_id = ? AND branch_id = ?");
$stmt->execute([$tenant_id, $branch_id]);
$mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch global allocations
$stmt = $pdo->prepare("
    SELECT * FROM global_budget_allocations
    WHERE allocation_date BETWEEN ? AND ?
    AND tenant_id = ? AND branch_id = ?
    ORDER BY allocation_date DESC
");
$stmt->execute([$startDate, $endDate, $tenant_id, $branch_id]);
$globalAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories
$categoriesStmt = $pdo->prepare("SELECT * FROM expense_categories WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
$categoriesStmt->execute([$tenant_id, $branch_id]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expenses per category (only from valid global allocations)
$stmt = $pdo->prepare("
    SELECT ec.id, ec.name, SUM(e.amount) as total_used, e.currency
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    INNER JOIN global_budget_allocations ga ON e.global_allocation_id = ga.id AND ga.tenant_id = ? AND ga.branch_id = ?
    WHERE e.date BETWEEN ? AND ? AND e.global_allocation_id IS NOT NULL
    AND e.tenant_id = ? AND e.branch_id = ?
    GROUP BY ec.id, ec.name, e.currency
    ORDER BY ec.name
");
$stmt->execute([$tenant_id, $branch_id, $startDate, $endDate, $tenant_id, $branch_id]);
$categoryUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build totals per currency
$totals = [];
foreach ($globalAllocations as $alloc) {
    $curr = $alloc['currency'];
    if (!isset($totals[$curr])) $totals[$curr] = ['allocated' => 0, 'remaining' => 0];
    $totals[$curr]['allocated']  += $alloc['allocated_amount'];
    $totals[$curr]['remaining']  += $alloc['remaining_amount'];
}

// Get unique currencies from current allocations for the add expense modal
$stmt = $pdo->prepare("
    SELECT DISTINCT currency FROM global_budget_allocations
    WHERE tenant_id = ? AND branch_id = ?
    ORDER BY currency ASC
");
$stmt->execute([$tenant_id, $branch_id]);
$allocatedCurrencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
$availableCurrencies = array_column($allocatedCurrencies, 'currency');

?>

    <style>
        /* ── Reset & base ────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        /* ── Page wrapper ───────────────────────── */
        .gba-page { padding: 20px 24px; }

        /* ── Top bar ───────────────────────────── */
        .gba-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }
        .gba-topbar h4 {
            font-size: 17px;
            font-weight: 500;
            margin: 0;
            color: #1a1a1a;
        }
        .gba-topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Month/year pill */
        .gba-month-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            color: #444;
        }
        .gba-month-pill select {
            border: none;
            background: transparent;
            font-size: 13px;
            color: #444;
            cursor: pointer;
            outline: none;
            padding: 0;
        }
        .gba-month-pill .btn-filter {
            border: none;
            background: transparent;
            padding: 0 2px;
            cursor: pointer;
            color: #888;
            font-size: 13px;
        }
        .gba-month-pill .btn-filter:hover { color: #333; }

        /* Action buttons */
        .gba-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            transition: opacity .15s, background .15s;
            white-space: nowrap;
        }
        .gba-btn:hover { opacity: .88; }
        .gba-btn-primary { background: #1a1a1a; color: #fff; }
        .gba-btn-success { background: #1a7a4a; color: #fff; }

        /* ── Period banner ──────────────────────── */
        .gba-period-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #eef5fb;
            border: 1px solid #c6ddf0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #185FA5;
            margin-bottom: 20px;
        }

        /* ── Summary cards ──────────────────────── */
        .gba-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        @media (max-width: 767px) {
            .gba-summary-grid { grid-template-columns: 1fr; }
        }
        .gba-summary-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 16px 18px;
        }
        .gba-summary-card .sc-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .gba-summary-card .sc-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .gba-summary-card .sc-value {
            font-size: 22px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .gba-summary-card .sc-sub {
            font-size: 12px;
            color: #aaa;
            margin-top: 4px;
        }

        /* ── Section label ──────────────────────── */
        .gba-section-label {
            font-size: 11px;
            font-weight: 600;
            color: #aaa;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        /* ── Allocation cards grid ──────────────── */
        .gba-alloc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }
        .gba-alloc-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 16px 18px;
            transition: border-color .15s;
        }
        .gba-alloc-card:hover { border-color: #bbb; }

        .gba-alloc-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .gba-alloc-meta { font-size: 12px; color: #999; }
        .gba-alloc-account { font-size: 12px; color: #888; margin-top: 3px; }
        .gba-alloc-amount { text-align: right; }
        .gba-alloc-amount .amount-val {
            font-size: 20px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .gba-alloc-amount .amount-curr {
            font-size: 12px;
            color: #aaa;
            margin-left: 3px;
        }

        .gba-alloc-desc {
            font-size: 13px;
            color: #999;
            margin-bottom: 12px;
            min-height: 18px;
            word-break: break-word;
        }
        .gba-alloc-desc.is-rollover {
            color: #185FA5;
            font-size: 12px;
        }

        /* Progress bar */
        .gba-progress-bar-bg {
            height: 5px;
            border-radius: 3px;
            background: #f0f0f0;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .gba-progress-bar-fill {
            height: 5px;
            border-radius: 3px;
            transition: width .4s;
        }
        .gba-fill-green  { background: #1a7a4a; }
        .gba-fill-amber  { background: #BA7517; }
        .gba-fill-red    { background: #A32D2D; }

        .gba-progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #aaa;
        }

        /* Action buttons inside card */
        .gba-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        .gba-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            background: transparent;
            cursor: pointer;
            color: #555;
            transition: background .12s, color .12s;
        }
        .gba-action-btn:hover { background: #f5f5f5; color: #1a1a1a; }
        .gba-action-btn.danger { color: #A32D2D; border-color: #f0c5c5; }
        .gba-action-btn.danger:hover { background: #fdf0f0; }
        .gba-action-btn:disabled { opacity: .38; cursor: not-allowed; pointer-events: none; }

        /* ── Category usage table ───────────────── */
        .gba-usage-card {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 28px;
        }
        .gba-usage-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .gba-usage-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .gba-usage-table th {
            padding: 9px 18px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid #f0f0f0;
            background: #fafafa;
        }
        .gba-usage-table td {
            padding: 11px 18px;
            border-bottom: 1px solid #f5f5f5;
            color: #333;
        }
        .gba-usage-table tr:last-child td { border-bottom: none; }
        .gba-cat-pill {
            display: inline-flex;
            align-items: center;
            background: #f5f5f5;
            border: 1px solid #eee;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 12px;
            color: #444;
        }
        .gba-mini-bar-wrap { display: flex; align-items: center; gap: 10px; }
        .gba-mini-bar-bg {
            flex: 1;
            height: 5px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }
        .gba-mini-bar-fill { height: 5px; background: #185FA5; border-radius: 3px; }
        .gba-pct-label { font-size: 12px; color: #aaa; min-width: 36px; text-align: right; }

        /* ── Empty state ───────────────────────── */
        .gba-empty {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 48px 24px;
            text-align: center;
            margin-bottom: 28px;
        }
        .gba-empty i { font-size: 36px; color: #ccc; }
        .gba-empty h6 { margin: 12px 0 4px; font-size: 15px; color: #555; }
        .gba-empty p { font-size: 13px; color: #aaa; margin: 0 0 16px; }

        /* ── Modal tweaks ───────────────────────── */
        .modal-content { border: none; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.14); }
        .modal-header { border-bottom: 1px solid #f0f0f0; padding: 16px 20px; }
        .modal-footer { border-top: 1px solid #f0f0f0; padding: 12px 20px; }
        .modal-title { font-size: 15px; font-weight: 500; }
        .form-control { border-radius: 7px; border: 1px solid #e0e0e0; font-size: 13px; }
        .form-control:focus { border-color: #888; box-shadow: none; }

        @media (max-width: 575px) {
            .gba-topbar { flex-direction: column; align-items: flex-start; }
            .gba-topbar-actions { width: 100%; }
            .gba-btn { flex: 1; justify-content: center; }
        }
    </style>


<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
                        <div class="gba-page">

                            <!-- Top bar -->
                            <div class="gba-topbar">
                                <h4>Global budget allocation</h4>
                                <div class="gba-topbar-actions">
                                    <!-- Month/year filter -->
                                    <form method="get" id="filterForm">
                                        <div class="gba-month-pill">
                                            <i class="feather icon-calendar" style="font-size:13px;color:#888;"></i>
                                            <select name="month" id="monthFilter">
                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?= sprintf('%02d', $m) ?>" <?= $selectedMonth == sprintf('%02d', $m) ? 'selected' : '' ?>>
                                                        <?= date('F', mktime(0,0,0,$m,1)) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <select name="year" id="yearFilter" style="margin-left:4px;">
                                                <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                                                    <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <button type="submit" class="btn-filter" title="Apply filter">
                                                <i class="feather icon-filter"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <button type="button" class="gba-btn gba-btn-success" data-toggle="modal" data-target="#addExpenseModal">
                                        <i class="feather icon-plus-circle"></i> Add expense
                                    </button>
                                    <button type="button" class="gba-btn gba-btn-primary" data-toggle="modal" data-target="#globalAllocationModal">
                                        <i class="feather icon-plus"></i> New allocation
                                    </button>
                                </div>
                            </div>

                            <!-- Period banner -->
                            <div class="gba-period-bar">
                                <i class="feather icon-calendar"></i>
                                Showing allocations for <strong style="margin-left:4px;"><?= date('F Y', strtotime($startDate)) ?></strong>
                            </div>

                            <!-- Summary cards (one row per currency) -->
                            <?php foreach ($totals as $curr => $data):
                                $used         = $data['allocated'] - $data['remaining'];
                                $usagePct     = $data['allocated'] > 0 ? round(($used / $data['allocated']) * 100) : 0;
                                $remainingPct = 100 - $usagePct;
                            ?>
                            <div class="gba-summary-grid">
                                <div class="gba-summary-card">
                                    <div class="sc-label"><span class="sc-dot" style="background:#185FA5;"></span>Total allocated (<?= $curr ?>)</div>
                                    <div class="sc-value"><?= number_format($data['allocated'], 2) ?></div>
                                    <div class="sc-sub">Remaining: <?= number_format($data['remaining'], 2) ?></div>
                                </div>
                                <div class="gba-summary-card">
                                    <div class="sc-label"><span class="sc-dot" style="background:#1a7a4a;"></span>Available funds (<?= $curr ?>)</div>
                                    <div class="sc-value"><?= number_format($data['remaining'], 2) ?></div>
                                    <div class="sc-sub"><?= $remainingPct ?>% of total</div>
                                </div>
                                <div class="gba-summary-card">
                                    <div class="sc-label"><span class="sc-dot" style="background:#A32D2D;"></span>Used funds (<?= $curr ?>)</div>
                                    <div class="sc-value"><?= number_format($used, 2) ?></div>
                                    <div class="sc-sub"><?= $usagePct ?>% usage rate</div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Allocation cards -->
                            <p class="gba-section-label">Allocations</p>

                            <?php if (count($globalAllocations) > 0): ?>
                            <div class="gba-alloc-grid">
                                <?php foreach ($globalAllocations as $allocation):
                                    $usedAmount    = $allocation['allocated_amount'] - $allocation['remaining_amount'];
                                    $usedPct       = $allocation['allocated_amount'] > 0
                                        ? round(($usedAmount / $allocation['allocated_amount']) * 100) : 0;
                                    $fillClass     = $usedPct < 50 ? 'gba-fill-green' : ($usedPct < 75 ? 'gba-fill-amber' : 'gba-fill-red');
                                    $isRollover    = stripos($allocation['description'], 'Rollover') !== false;
                                ?>
                                <div class="gba-alloc-card">
                                    <div class="gba-alloc-top">
                                        <div>
                                            <div class="gba-alloc-meta"><?= date('d M Y', strtotime($allocation['allocation_date'])) ?></div>
                                            <div class="gba-alloc-account">Global budget</div>
                                        </div>
                                        <div class="gba-alloc-amount">
                                            <span class="amount-val"><?= number_format($allocation['allocated_amount'], 2) ?></span>
                                            <span class="amount-curr"><?= $allocation['currency'] ?></span>
                                        </div>
                                    </div>

                                    <div class="gba-alloc-desc <?= $isRollover ? 'is-rollover' : '' ?>">
                                        <?= $isRollover ? '↩ ' : '' ?><?= htmlspecialchars($allocation['description']) ?>
                                    </div>

                                    <div class="gba-progress-bar-bg">
                                        <div class="gba-progress-bar-fill <?= $fillClass ?>" style="width:<?= $usedPct ?>%;"></div>
                                    </div>
                                    <div class="gba-progress-labels">
                                        <span>Used: <?= number_format($usedAmount, 2) ?> <?= $allocation['currency'] ?></span>
                                        <span>Available: <?= number_format($allocation['remaining_amount'], 2) ?></span>
                                    </div>

                                    <div class="gba-card-actions">
                                        <button class="gba-action-btn fund-global-allocation"
                                                data-id="<?= $allocation['id'] ?>"
                                                data-currency="<?= $allocation['currency'] ?>">
                                            <i class="feather icon-plus-circle"></i> Fund
                                        </button>
                                        <button class="gba-action-btn view-category-usage"
                                                data-id="<?= $allocation['id'] ?>">
                                            <i class="feather icon-pie-chart"></i> Category usage
                                        </button>
                                        <button class="gba-action-btn view-global-expenses"
                                                data-id="<?= $allocation['id'] ?>">
                                            <i class="feather icon-file-text"></i> Expenses
                                        </button>
                                        <button class="gba-action-btn view-funds"
                                                data-id="<?= $allocation['id'] ?>"
                                                data-currency="<?= $allocation['currency'] ?>">
                                            <i class="feather icon-dollar-sign"></i> Transactions
                                        </button>
                                        <button class="gba-action-btn danger delete-global-allocation"
                                                data-id="<?= $allocation['id'] ?>"
                                                <?= ($usedAmount > 0) ? 'disabled' : '' ?>>
                                            <i class="feather icon-trash-2"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php else: ?>
                            <div class="gba-empty">
                                <i class="feather icon-inbox"></i>
                                <h6>No allocations found</h6>
                                <p>No global budget allocations for the selected month.</p>
                                <a href="global_budget_allocation.php" class="gba-btn gba-btn-primary" style="display:inline-flex;">
                                    <i class="feather icon-refresh-cw"></i> Show all
                                </a>
                            </div>
                            <?php endif; ?>

                            <!-- Category usage table -->
                            <?php if (count($categoryUsage) > 0): ?>
                            <p class="gba-section-label">Category usage</p>
                            <div class="gba-usage-card">
                                <div class="gba-usage-header">Spending by category — <?= date('F Y', strtotime($startDate)) ?></div>
                                <table class="gba-usage-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Total used</th>
                                            <th>Currency</th>
                                            <th style="width:220px;">% of allocation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categoryUsage as $usage):
                                            $allocatedForCurr = $totals[$usage['currency']]['allocated'] ?? 0;
                                            $pct = $allocatedForCurr > 0
                                                ? round(($usage['total_used'] / $allocatedForCurr) * 100, 2) : 0;
                                        ?>
                                        <tr>
                                            <td><span class="gba-cat-pill"><?= htmlspecialchars($usage['name']) ?></span></td>
                                            <td><?= number_format($usage['total_used'], 2) ?></td>
                                            <td><?= $usage['currency'] ?></td>
                                            <td>
                                                <div class="gba-mini-bar-wrap">
                                                    <div class="gba-mini-bar-bg">
                                                        <div class="gba-mini-bar-fill" style="width:<?= min($pct, 100) ?>%;"></div>
                                                    </div>
                                                    <span class="gba-pct-label"><?= $pct ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                        </div><!-- .gba-page -->
                    
        </div>
    </div>
</div>
<!-- End Main Content -->


<!-- ══ MODALS ══════════════════════════════════════════════════════ -->

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add expense from global allocation</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="addExpenseForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Expense category</label>
                        <select class="form-control" id="expenseCategory" name="expenseCategory" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-control" id="expenseDate" name="expenseDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="expenseDescription" name="expenseDescription" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" step="0.01" class="form-control" id="expenseAmount" name="expenseAmount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                 <label>Currency</label>
                                 <select class="form-control" id="expenseCurrency" name="expenseCurrency" required>
                                     <?php if (count($availableCurrencies) === 0): ?>
                                         <option value="">No currencies allocated</option>
                                     <?php elseif (count($availableCurrencies) === 1): ?>
                                         <option value="<?= htmlspecialchars($availableCurrencies[0]) ?>" selected><?= htmlspecialchars($availableCurrencies[0]) ?></option>
                                     <?php else: ?>
                                         <option value="">Select currency</option>
                                         <?php foreach ($availableCurrencies as $curr): ?>
                                             <option value="<?= htmlspecialchars($curr) ?>"><?= htmlspecialchars($curr) ?></option>
                                         <?php endforeach; ?>
                                     <?php endif; ?>
                                 </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="gba-btn gba-btn-success">Add expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Global Allocation Modal -->
<div class="modal fade" id="globalAllocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create global budget allocation</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="globalAllocationForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Main account</label>
                        <select class="form-control" id="mainAccountId" name="mainAccountId" required>
                            <option value="">Select account</option>
                            <?php foreach ($mainAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total amount</label>
                                <input type="number" step="0.01" class="form-control" id="totalAmount" name="totalAmount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Currency</label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value="">Select currency</option>
                                    <option value="USD">USD</option>
                                    <option value="AFS">AFS</option>
                                    <option value="EUR">EUR</option>
                                    <option value="DARHAM">AED</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Allocation date</label>
                        <input type="date" class="form-control" id="allocationDate" name="allocationDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="gba-btn gba-btn-primary">Create allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Fund Modal -->
<div class="modal fade" id="fundGlobalAllocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add funds to allocation</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="fundGlobalAllocationForm">
                <input type="hidden" id="fundGlobalAllocationId" name="fundGlobalAllocationId">
                <div class="modal-body">
                    <div class="alert alert-info" style="font-size:13px;">
                        Adding funds increases both the total and remaining allocation amounts.
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Additional amount</label>
                                <input type="number" step="0.01" class="form-control" id="additionalGlobalAmount" name="additionalGlobalAmount" required min="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Currency</label>
                                <input type="text" class="form-control" id="fundGlobalCurrency" name="fundGlobalCurrency" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Note <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="fundGlobalNote" name="fundGlobalNote" rows="2" placeholder="Reason for adding funds"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="gba-btn gba-btn-success">Add funds</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Category Usage Modal -->
<div class="modal fade" id="categoryUsageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Category usage details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="gba-usage-table" style="border:1px solid #f0f0f0;border-radius:8px;overflow:hidden;">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total used</th>
                                <th>Currency</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody id="categoryUsageTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Global Expenses Modal -->
<div class="modal fade" id="globalExpensesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Expenses from global allocation</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label style="font-size:13px;">Filter by category</label>
                    <select id="categoryFilter" class="form-control" style="max-width:240px;">
                        <option value="">All categories</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="gba-usage-table" style="border:1px solid #f0f0f0;border-radius:8px;overflow:hidden;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Currency</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="globalExpensesTableBody"></tbody>
                    </table>
                </div>
                <div id="no-global-expenses-message" class="text-center py-4" style="display:none;">
                    <i class="feather icon-inbox text-muted" style="font-size:32px;"></i>
                    <p class="mt-2 mb-0" style="font-size:13px;color:#aaa;">No expenses found for this allocation</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Fund Transactions Modal -->
<div class="modal fade" id="viewFundsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fund transactions</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="gba-summary-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:20px;">
                    <div class="gba-summary-card">
                        <div class="sc-label">Account</div>
                        <div class="sc-value" id="funds-allocation-account" style="font-size:15px;"></div>
                        <div class="sc-sub" id="funds-allocation-date"></div>
                    </div>
                    <div class="gba-summary-card">
                        <div class="sc-label">Allocated / Remaining</div>
                        <div class="sc-value" id="funds-allocation-amount" style="font-size:15px;"></div>
                        <div class="sc-sub" id="funds-allocation-remaining"></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="gba-usage-table" style="border:1px solid #f0f0f0;border-radius:8px;overflow:hidden;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="funds-table-body"></tbody>
                    </table>
                </div>
                <div id="no-funds-message" class="text-center py-4" style="display:none;">
                    <i class="feather icon-inbox text-muted" style="font-size:32px;"></i>
                    <p class="mt-2 mb-0" style="font-size:13px;color:#aaa;">No fund transactions found</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
$(document).ready(function () {

    // Auto-submit filter on change
    $('#monthFilter, #yearFilter').on('change', function () {
        $('#filterForm').submit();
    });

    /* ── Helpers ─────────────────────────────── */
    function btnLoading($btn, text) {
        $btn.data('orig', $btn.html()).prop('disabled', true).html('<i class="feather icon-loader"></i> ' + text);
    }
    function btnReset($btn) {
        $btn.prop('disabled', false).html($btn.data('orig'));
    }

    /* ── Add expense ─────────────────────────── */
    $('#addExpenseForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        btnLoading($btn, 'Adding...');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: {
                action: 'add_expense_global',
                categoryId:           $('#expenseCategory').val(),
                date:                 $('#expenseDate').val(),
                description:          $('#expenseDescription').val(),
                amount:               $('#expenseAmount').val(),
                currency:             $('#expenseCurrency').val()
            },
            success: function (r) {
                btnReset($btn);
                r.success ? (alert(r.message), location.reload()) : alert('Error: ' + r.message);
            },
            error: function () { btnReset($btn); alert('An error occurred while adding the expense.'); }
        });
    });

    /* ── Create allocation ───────────────────── */
    $('#globalAllocationForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        btnLoading($btn, 'Creating...');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: {
                action: 'create_global_allocation',
                main_account_id: $('#mainAccountId').val(),
                total_amount:    $('#totalAmount').val(),
                currency:        $('#currency').val(),
                date:            $('#allocationDate').val(),
                description:     $('#description').val()
            },
            success: function (r) {
                btnReset($btn);
                r.success ? (alert(r.message), location.reload()) : alert('Error: ' + r.message);
            },
            error: function () { btnReset($btn); alert('An error occurred while creating the allocation.'); }
        });
    });

    /* ── Fund allocation ─────────────────────── */
    $(document).on('click', '.fund-global-allocation', function () {
        $('#fundGlobalAllocationId').val($(this).data('id'));
        $('#fundGlobalCurrency').val($(this).data('currency'));
        $('#fundGlobalAllocationModal').modal('show');
    });

    $('#fundGlobalAllocationForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        btnLoading($btn, 'Adding...');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: {
                action:        'add_funds_global',
                allocation_id: $('#fundGlobalAllocationId').val(),
                amount:        $('#additionalGlobalAmount').val(),
                note:          $('#fundGlobalNote').val()
            },
            success: function (r) {
                btnReset($btn);
                r.success ? (alert(r.message), location.reload()) : alert('Error: ' + r.message);
            },
            error: function () { btnReset($btn); alert('An error occurred while adding funds.'); }
        });
    });

    /* ── Delete allocation ───────────────────── */
    $(document).on('click', '.delete-global-allocation', function () {
        if (!confirm('Are you sure you want to delete this allocation? Only unused allocations can be deleted. If this allocation already has expenses, delete those expenses first. Any remaining funds will be returned to the main account.')) return;
        var $btn = $(this), id = $btn.data('id');
        btnLoading($btn, 'Deleting...');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: { action: 'delete_global_allocation', allocation_id: id },
            success: function (r) {
                btnReset($btn);
                r.success ? (alert(r.message), location.reload()) : alert('Error: ' + r.message);
            },
            error: function () { btnReset($btn); alert('An error occurred while deleting the allocation.'); }
        });
    });

    /* ── View category usage ─────────────────── */
    $(document).on('click', '.view-category-usage', function () {
        var tbody = $('#categoryUsageTableBody').empty();
        <?php foreach ($categoryUsage as $usage):
            $allocatedForCurr = $totals[$usage['currency']]['allocated'] ?? 0;
            $pct = $allocatedForCurr > 0 ? round(($usage['total_used'] / $allocatedForCurr) * 100, 2) : 0;
        ?>
        tbody.append(`
            <tr>
                <td><span class="gba-cat-pill"><?= htmlspecialchars($usage['name']) ?></span></td>
                <td><?= number_format($usage['total_used'], 2) ?></td>
                <td><?= $usage['currency'] ?></td>
                <td>
                    <div class="gba-mini-bar-wrap">
                        <div class="gba-mini-bar-bg"><div class="gba-mini-bar-fill" style="width:<?= min($pct,100) ?>%;"></div></div>
                        <span class="gba-pct-label"><?= $pct ?>%</span>
                    </div>
                </td>
            </tr>`);
        <?php endforeach; ?>
        $('#categoryUsageModal').modal('show');
    });

    /* ── View expenses ───────────────────────── */
    var allExpenses = [];

    $(document).on('click', '.view-global-expenses', function () {
        var $btn = $(this), id = $btn.data('id');
        btnLoading($btn, 'Loading...');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: { action: 'get_global_expenses', allocation_id: id },
            success: function (r) {
                btnReset($btn);
                if (!r.success) { alert('Error: ' + r.message); return; }
                allExpenses = r.expenses || [];
                allExpenses.sort((a, b) => new Date(b.date) - new Date(a.date));
                var cats = [...new Set(allExpenses.map(e => e.category_name).filter(Boolean))];
                var $cf = $('#categoryFilter').empty().append('<option value="">All categories</option>');
                cats.forEach(c => $cf.append(`<option value="${c}">${c}</option>`));
                renderExpenses('');
                $('#globalExpensesModal').modal('show');
            },
            error: function () { btnReset($btn); alert('An error occurred while fetching expenses.'); }
        });
    });

    $(document).on('change', '#categoryFilter', function () { renderExpenses($(this).val()); });

    function renderExpenses(cat) {
        var $tbody = $('#globalExpensesTableBody').empty();
        var rows = cat ? allExpenses.filter(e => e.category_name === cat) : allExpenses;
        if (rows.length) {
            rows.forEach(function (e) {
                $tbody.append(`
                    <tr>
                        <td>${new Date(e.date).toLocaleDateString()}</td>
                        <td><span class="gba-cat-pill">${e.category_name || 'N/A'}</span></td>
                        <td style="max-width:160px;word-break:break-word;">${e.description}</td>
                        <td>${e.amount}</td>
                        <td>${e.currency}</td>
                        <td>
                            <button class="gba-action-btn danger delete-global-expense" data-id="${e.id}">
                                <i class="feather icon-trash-2"></i>
                            </button>
                        </td>
                    </tr>`);
            });
            $tbody.show(); $('#no-global-expenses-message').hide();
        } else {
            $tbody.hide(); $('#no-global-expenses-message').show();
        }
    }

    /* ── Delete expense ──────────────────────── */
    $(document).on('click', '.delete-global-expense', function () {
        if (!confirm('Delete this expense?')) return;
        var $btn = $(this), id = $btn.data('id');
        btnLoading($btn, '');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: { action: 'delete_global_expense', expense_id: id },
            success: function (r) {
                btnReset($btn);
                r.success ? (alert(r.message), location.reload()) : alert('Error: ' + r.message);
            },
            error: function () { btnReset($btn); alert('An error occurred.'); }
        });
    });

    /* ── View fund transactions ──────────────── */
    $(document).on('click', '.view-funds', function () {
        var $btn = $(this), id = $btn.data('id');
        btnLoading($btn, 'Loading...');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: { action: 'get_fund_transactions', allocation_id: id },
            success: function (r) {
                btnReset($btn);
                if (!r.success) { alert('Error: ' + r.message); return; }
                var a = r.allocation, txns = r.transactions;
                $('#funds-allocation-account').text(a.account_name);
                $('#funds-allocation-date').text(new Date(a.allocation_date).toLocaleDateString());
                $('#funds-allocation-amount').text(parseFloat(a.allocated_amount).toLocaleString(undefined,{minimumFractionDigits:2}) + ' ' + a.currency);
                $('#funds-allocation-remaining').text('Remaining: ' + parseFloat(a.remaining_amount).toLocaleString(undefined,{minimumFractionDigits:2}) + ' ' + a.currency);

                var $tbody = $('#funds-table-body').empty();
                if (txns.length) {
                    txns.forEach(function (t) {
                        var isDebit = t.type === 'debit';
                        $tbody.append(`
                            <tr>
                                <td>${t.created_at ? new Date(t.created_at).toLocaleDateString() : 'N/A'}</td>
                                <td style="max-width:220px;word-break:break-word;">${t.description}</td>
                                <td>${t.amount} ${t.currency}</td>
                                <td style="color:${isDebit ? '#A32D2D' : '#1a7a4a'};">
                                    <i class="feather icon-arrow-${isDebit ? 'down' : 'up'}"></i>
                                    ${isDebit ? 'Debit' : 'Credit'}
                                </td>
                                <td>
                                    <button class="gba-action-btn danger delete-fund-transaction"
                                            data-id="${t.id}" data-allocation-id="${id}">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </td>
                            </tr>`);
                    });
                    $tbody.show(); $('#no-funds-message').hide();
                } else {
                    $tbody.hide(); $('#no-funds-message').show();
                }
                $('#viewFundsModal').modal('show');
            },
            error: function () { btnReset($btn); alert('An error occurred while fetching transactions.'); }
        });
    });

    /* ── Delete fund transaction ─────────────── */
    $(document).on('click', '.delete-fund-transaction', function () {
        if (!confirm('Delete this transaction? This may affect the allocation balance.')) return;
        var $btn = $(this), tid = $btn.data('id'), aid = $btn.data('allocation-id');
        btnLoading($btn, '');
        $.ajax({
            url: 'global_allocation_actions.php', type: 'POST', dataType: 'json',
            data: { action: 'delete_fund_transaction', transaction_id: tid, allocation_id: aid },
            success: function (r) {
                btnReset($btn);
                if (r.success) {
                    alert(r.message);
                    $('.view-funds[data-id="' + aid + '"]').trigger('click');
                } else {
                    alert('Error: ' + r.message);
                }
            },
            error: function () { btnReset($btn); alert('An error occurred.'); }
        });
    });

});
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
