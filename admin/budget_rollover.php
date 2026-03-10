<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'security.php';
enforce_auth();

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$tenant_id   = $_SESSION['tenant_id'];
$branch_id   = $_SESSION['branch_id'];

require_once('../includes/db.php');
require_once 'includes/db_security.php';
require_once 'includes/logger.php';

$currentMonth  = date('m');
$currentYear   = date('Y');
$previousMonth = date('m', strtotime('-1 month'));
$previousYear  = date('Y', strtotime('-1 month'));

$manualRollover = false;
if (isset($_POST['rollover_month']) && isset($_POST['rollover_year'])) {
    $previousMonth  = $_POST['rollover_month'];
    $previousYear   = $_POST['rollover_year'];
    $manualRollover = true;
}

$previousMonthStart = $previousYear . '-' . $previousMonth . '-01';
$previousMonthEnd   = date('Y-m-t', strtotime($previousMonthStart));
$currentMonthDate   = date('Y-m-d');

$response = ['success' => false, 'message' => '', 'rollovers' => []];

// ── Process rollover ──────────────────────────────────────────────────────────
if (isset($_POST['process_rollover'])) {
    $pdo->beginTransaction();
    try {
        $user_id    = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $pdo->prepare("
            SELECT * FROM budget_allocations
            WHERE allocation_date BETWEEN ? AND ?
              AND remaining_amount > 0
              AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$previousMonthStart, $previousMonthEnd, $tenant_id, $branch_id]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allocations)) {
            $response['message'] = "No remaining budget found from " . date('F Y', strtotime($previousMonthStart)) . " to roll over.";
        } else {
            foreach ($allocations as $allocation) {
                $description = "Rollover from " . date('F Y', strtotime($previousMonthStart)) . " - " . $allocation['description'];

                $stmt = $pdo->prepare("
                    INSERT INTO budget_allocations
                        (main_account_id, category_id, allocated_amount, remaining_amount, currency, allocation_date, description, tenant_id, branch_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $allocation['main_account_id'],
                    $allocation['category_id'],
                    $allocation['remaining_amount'],
                    $allocation['remaining_amount'],
                    $allocation['currency'],
                    $currentMonthDate,
                    $description,
                    $tenant_id,
                    $branch_id,
                    $user_id
                ]);

                $newAllocationId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    UPDATE budget_allocations
                    SET remaining_amount = 0,
                        description = CONCAT(description, ' (Rolled over to allocation #$newAllocationId)')
                    WHERE id = ?
                ");
                $stmt->execute([$allocation['id']]);

                $response['rollovers'][] = [
                    'from_id'     => $allocation['id'],
                    'to_id'       => $newAllocationId,
                    'amount'      => $allocation['remaining_amount'],
                    'currency'    => $allocation['currency'],
                    'category_id' => $allocation['category_id']
                ];

                $activityStmt = $pdo->prepare("
                    INSERT INTO activity_log
                        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id, created_at)
                    VALUES (?, 'budget_rollover', 'budget_allocations', ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $activityStmt->execute([
                    $user_id,
                    $newAllocationId,
                    json_encode(['id' => $allocation['id'], 'remaining_amount' => $allocation['remaining_amount']]),
                    json_encode(['id' => $newAllocationId, 'allocated_amount' => $allocation['remaining_amount'], 'remaining_amount' => $allocation['remaining_amount'], 'description' => $description]),
                    $ip_address,
                    $user_agent,
                    $tenant_id,
                    $branch_id
                ]);
            }

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = "Successfully rolled over " . count($response['rollovers']) .
                " budget allocations from " . date('F Y', strtotime($previousMonthStart)) . " to " . date('F Y') . ".";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = "Error: " . $e->getMessage();
    }
}

// ── Categories ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
$stmt->execute([$tenant_id, $branch_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoriesById = array_column($categories, 'name', 'id');

// ── Pending preview (always load for selected month) ─────────────────────────
$stmt = $pdo->prepare("
    SELECT ba.*, ec.name AS category_name
    FROM budget_allocations ba
    LEFT JOIN expense_categories ec ON ec.id = ba.category_id AND ec.tenant_id = ba.tenant_id
    WHERE ba.allocation_date BETWEEN ? AND ?
      AND ba.remaining_amount > 0
      AND ba.tenant_id = ? AND ba.branch_id = ?
    ORDER BY ba.remaining_amount DESC
");
$stmt->execute([$previousMonthStart, $previousMonthEnd, $tenant_id, $branch_id]);
$previewAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pendingCount = count($previewAllocations);

$totalByCurrency = [];
foreach ($previewAllocations as $a) {
    $totalByCurrency[$a['currency']] = ($totalByCurrency[$a['currency']] ?? 0) + $a['remaining_amount'];
}

// ── Auto-rollover already done this month? ────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM activity_log
    WHERE action = 'budget_rollover' AND created_at >= ? AND tenant_id = ? AND branch_id = ?
");
$stmt->execute([date('Y-m-01'), $tenant_id, $branch_id]);
$autoRolloverDone = $stmt->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Rollover</title>
    <!-- your existing asset links go here -->
    <link rel="stylesheet" href="../assets/css/style.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.css">

    <style>
        /* ── Design tokens ── */
        :root {
            --brand-start : #4099ff;
            --brand-end   : #2ed8b6;
            --brand-mid   : #38b8d8;
            --surface     : #f4f7fb;
            --card-bg     : #ffffff;
            --border      : #e4eaf3;
            --text-main   : #1a2233;
            --text-muted  : #7a8aa0;
            --success-bg  : #eafaf4;
            --success-fg  : #1a7a52;
            --warning-bg  : #fff8ec;
            --warning-fg  : #9a6200;
            --danger-bg   : #fef2f2;
            --danger-fg   : #b91c1c;
            --radius-lg   : 14px;
            --radius-md   : 10px;
            --shadow-card : 0 2px 12px rgba(64,153,255,.10);
            --shadow-lift : 0 6px 24px rgba(64,153,255,.18);
        }

        /* ── Layout ── */
        body { background: var(--surface); }

        .br-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }
        .br-page-title {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -.3px;
        }
        .br-page-title span {
            background: linear-gradient(90deg, var(--brand-start), var(--brand-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* ── Cards ── */
        .br-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            margin-bottom: 24px;
            transition: box-shadow .2s;
        }
        .br-card:hover { box-shadow: var(--shadow-lift); }

        .br-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--brand-start) 0%, var(--brand-end) 100%);
            border-bottom: none;
        }
        .br-card-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            letter-spacing: -.1px;
        }

        .br-card-body { padding: 24px; }

        /* ── Stat chips ── */
        .br-stats {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .br-stat {
            flex: 1;
            min-width: 160px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            box-shadow: var(--shadow-card);
        }
        .br-stat-label {
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .br-stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.1;
        }
        .br-stat-value.accent {
            background: linear-gradient(90deg, var(--brand-start), var(--brand-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* ── Alert banners ── */
        .br-alert {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        .br-alert-icon { flex-shrink: 0; margin-top: 1px; }
        .br-alert-body { flex: 1; }
        .br-alert-title { font-weight: 600; font-size: .9rem; margin-bottom: 4px; }
        .br-alert-text  { font-size: .85rem; margin: 0; }

        .br-alert.success { background: var(--success-bg); border-color: #a7f3d0; color: var(--success-fg); }
        .br-alert.warning { background: var(--warning-bg); border-color: #fde68a; color: var(--warning-fg); }
        .br-alert.danger  { background: var(--danger-bg);  border-color: #fecaca; color: var(--danger-fg); }
        .br-alert.info    { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }

        /* ── Form controls ── */
        .br-form-group { margin-bottom: 18px; }
        .br-form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .br-select-row { display: flex; gap: 12px; }
        .br-select-row select { flex: 1; }

        select.form-control, .br-select {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            font-size: .9rem;
            color: var(--text-main);
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            -webkit-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%237a8aa0' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }
        select.form-control:focus, .br-select:focus {
            border-color: var(--brand-start);
            box-shadow: 0 0 0 3px rgba(64,153,255,.15);
            outline: none;
        }

        /* ── Buttons ── */
        .btn-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--brand-start), var(--brand-end));
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            padding: 10px 20px;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 3px 10px rgba(64,153,255,.35);
        }
        .btn-brand:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 5px 16px rgba(64,153,255,.4); }
        .btn-brand:active { transform: translateY(0); }

        .btn-outline-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1.5px solid rgba(255,255,255,.4);
            border-radius: var(--radius-md);
            padding: 8px 16px;
            font-size: .825rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-outline-back:hover { background: rgba(255,255,255,.25); color: #fff; text-decoration: none; }

        /* ── Preview table ── */
        .br-table-wrap { overflow-x: auto; }
        .br-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .875rem;
        }
        .br-table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--text-muted);
            font-weight: 700;
            padding: 10px 16px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }
        .br-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }
        .br-table tbody tr:hover { background: #f8fbff; }
        .br-table tbody tr:last-child { border-bottom: none; }
        .br-table td {
            padding: 13px 16px;
            color: var(--text-main);
            vertical-align: middle;
        }
        .br-table td.muted { color: var(--text-muted); font-size: .8rem; }

        .br-amount-pill {
            display: inline-block;
            background: linear-gradient(90deg, #e0f0ff, #d0f7ef);
            color: var(--brand-start);
            font-weight: 700;
            font-size: .85rem;
            padding: 4px 12px;
            border-radius: 50px;
        }
        .br-amount-pill.rolled {
            background: linear-gradient(90deg, #d1fae5, #a7f3d0);
            color: var(--success-fg);
        }

        .br-badge {
            display: inline-block;
            font-size: .72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 50px;
            letter-spacing: .3px;
        }
        .br-badge-cat {
            background: #eff6ff;
            color: #1d4ed8;
        }

        /* ── Empty state ── */
        .br-empty {
            text-align: center;
            padding: 56px 24px;
            color: var(--text-muted);
        }
        .br-empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #e0f0ff, #d0f7ef);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .br-empty-icon svg { color: var(--brand-start); }
        .br-empty h6 { font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 6px; }
        .br-empty p  { font-size: .875rem; margin: 0; }

        /* ── Confirmation modal ── */
        .br-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10,20,50,.45);
            backdrop-filter: blur(3px);
            z-index: 1050;
            align-items: center;
            justify-content: center;
        }
        .br-modal-backdrop.open { display: flex; }
        .br-modal {
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            width: 100%;
            max-width: 460px;
            padding: 32px;
            animation: modalIn .22s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes modalIn {
            from { transform: scale(.92) translateY(10px); opacity: 0; }
            to   { transform: scale(1)  translateY(0);     opacity: 1; }
        }
        .br-modal-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fff8ec, #fde68a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .br-modal h5 {
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .br-modal p {
            text-align: center;
            font-size: .875rem;
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        .br-modal-summary {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: .875rem;
        }
        .br-modal-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            color: var(--text-main);
        }
        .br-modal-summary-row .label { color: var(--text-muted); }
        .br-modal-actions {
            display: flex;
            gap: 12px;
        }
        .btn-cancel {
            flex: 1;
            padding: 10px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--text-muted);
            font-weight: 600;
            font-size: .875rem;
            cursor: pointer;
            transition: border-color .2s, color .2s;
        }
        .btn-cancel:hover { border-color: #aaa; color: var(--text-main); }
        .btn-confirm {
            flex: 2;
            padding: 10px;
            border: none;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--brand-start), var(--brand-end));
            color: #fff;
            font-weight: 600;
            font-size: .875rem;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(64,153,255,.35);
            transition: opacity .2s;
        }
        .btn-confirm:hover { opacity: .9; }

        /* ── Results highlight ── */
        .br-result-row td:first-child { font-family: monospace; font-size: .8rem; color: var(--text-muted); }

        /* ── Divider ── */
        .br-divider { border: none; border-top: 1px solid var(--border); margin: 0; }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- Page header -->
                        <div class="br-page-header">
                            <h1 class="br-page-title">Budget <span>Rollover</span></h1>
                            <a href="budget_allocations.php" style="text-decoration:none;">
                                <button style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-md);padding:9px 18px;font-size:.85rem;font-weight:600;color:var(--text-main);cursor:pointer;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='var(--shadow-lift)'" onmouseout="this.style.boxShadow='none'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                    Back to Budget Allocations
                                </button>
                            </a>
                        </div>

                        <!-- Toast-style alerts -->
                        <?php if ($response['success']): ?>
                        <div class="br-alert success">
                            <div class="br-alert-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="br-alert-body">
                                <div class="br-alert-title">Rollover Successful</div>
                                <p class="br-alert-text"><?= htmlspecialchars($response['message']) ?></p>
                            </div>
                        </div>
                        <?php elseif (!empty($response['message'])): ?>
                        <div class="br-alert danger">
                            <div class="br-alert-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            </div>
                            <div class="br-alert-body">
                                <div class="br-alert-title">Rollover Failed</div>
                                <p class="br-alert-text"><?= htmlspecialchars($response['message']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($autoRolloverDone && empty($response['rollovers'])): ?>
                        <div class="br-alert info">
                            <div class="br-alert-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </div>
                            <div class="br-alert-body">
                                <div class="br-alert-title">Already Processed</div>
                                <p class="br-alert-text">A rollover has already been completed for <?= date('F Y') ?>. Use the manual selector below to roll over from a different period.</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Stats row -->
                        <div class="br-stats">
                            <div class="br-stat">
                                <div class="br-stat-label">Pending Allocations</div>
                                <div class="br-stat-value accent"><?= $pendingCount ?></div>
                            </div>
                            <?php 
                            $totalByCurrency = [];
                            if (!empty($allocations)) {
                                foreach ($allocations as $allocation) {
                                    $cur = $allocation['currency'];
                                    if (!isset($totalByCurrency[$cur])) {
                                        $totalByCurrency[$cur] = 0;
                                    }
                                    $totalByCurrency[$cur] += $allocation['remaining_amount'];
                                }
                            }
                            ?>
                            <?php foreach ($totalByCurrency as $cur => $tot): ?>
                            <div class="br-stat">
                                <div class="br-stat-label">Total to Roll Over (<?= htmlspecialchars($cur) ?>)</div>
                                <div class="br-stat-value accent"><?= number_format($tot, 2) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <div class="br-stat">
                                <div class="br-stat-label">Rolling Into</div>
                                <div class="br-stat-value" style="font-size:1.1rem;font-weight:700;"><?= date('F Y') ?></div>
                            </div>
                        </div>

                        <!-- Two-column: selector + preview -->
                        <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start;" class="br-grid">
                            
                            <!-- Left: month selector -->
                            <div class="br-card">
                                <div class="br-card-header">
                                    <h5>Select Period</h5>
                                </div>
                                <div class="br-card-body">
                                    <p style="font-size:.875rem;color:var(--text-muted);margin-bottom:20px;">
                                        Choose which month's remaining budget to preview and roll forward into <strong><?= date('F Y') ?></strong>.
                                    </p>
                                    <form method="get" id="previewForm">
                                        <div class="br-form-group">
                                            <label>Month</label>
                                            <select name="rollover_month" class="form-control" onchange="this.form.submit()">
                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?= sprintf('%02d', $m) ?>" <?= $previousMonth == sprintf('%02d', $m) ? 'selected' : '' ?>>
                                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="br-form-group">
                                            <label>Year</label>
                                            <select name="rollover_year" class="form-control" onchange="this.form.submit()">
                                                <?php for ($y = $currentYear - 2; $y <= $currentYear; $y++): ?>
                                                    <option value="<?= $y ?>" <?= $previousYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </form>

                                    <hr class="br-divider" style="margin:20px 0;">

                                    <?php if ($pendingCount > 0): ?>
                                    <button type="button" class="btn-brand" style="width:100%;justify-content:center;" onclick="openConfirm()">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                        Roll Over <?= $pendingCount ?> Allocation<?= $pendingCount !== 1 ? 's' : '' ?>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-brand" style="width:100%;justify-content:center;opacity:.5;cursor:not-allowed;" disabled>
                                        Nothing to Roll Over
                                    </button>
                                    <?php endif; ?>

                                    <ul style="list-style:none;padding:0;margin:20px 0 0;display:flex;flex-direction:column;gap:8px;">
                                        <?php foreach ([
                                            'Remaining funds carried forward',
                                            'Category & account assignments preserved',
                                            'Previous allocations zeroed & tagged',
                                            'All actions logged in activity trail'
                                        ] as $tip): ?>
                                        <li style="display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--text-muted);">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2ed8b6" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            <?= $tip ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- Right: preview table -->
                            <div class="br-card">
                                <div class="br-card-header">
                                    <h5>
                                        Preview — <?= date('F Y', strtotime($previousMonthStart)) ?>
                                        <?php if ($pendingCount > 0): ?>
                                        <span style="background:rgba(255,255,255,.25);font-size:.75rem;padding:2px 10px;border-radius:50px;margin-left:8px;"><?= $pendingCount ?> item<?= $pendingCount !== 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                    </h5>
                                </div>
                                <div class="br-card-body" style="padding:0;">

                                    <?php if (empty($previewAllocations)): ?>
                                    <div class="br-empty">
                                        <div class="br-empty-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                        </div>
                                        <h6>No remaining budget</h6>
                                        <p>There are no allocations with a remaining balance for <?= date('F Y', strtotime($previousMonthStart)) ?>.<br>Try selecting a different period.</p>
                                    </div>

                                    <?php elseif (!empty($response['rollovers'])): ?>
                                    <!-- Post-rollover results view -->
                                    <div class="br-table-wrap">
                                        <table class="br-table">
                                            <thead>
                                                <tr>
                                                    <th>From → To</th>
                                                    <th>Category</th>
                                                    <th>Amount Rolled Over</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($response['rollovers'] as $r): ?>
                                                <tr class="br-result-row">
                                                    <td>#<?= $r['from_id'] ?> → #<?= $r['to_id'] ?></td>
                                                    <td><span class="br-badge br-badge-cat"><?= htmlspecialchars($categoriesById[$r['category_id']] ?? 'Unknown') ?></span></td>
                                                    <td><span class="br-amount-pill rolled"><?= number_format($r['amount'], 2) ?> <?= htmlspecialchars($r['currency']) ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php else: ?>
                                    <!-- Preview (before rollover) -->
                                    <div class="br-table-wrap">
                                        <table class="br-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Description</th>
                                                    <th>Category</th>
                                                    <th>Allocated</th>
                                                    <th>Remaining</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($previewAllocations as $a): ?>
                                                <tr>
                                                    <td class="muted"><?= $a['id'] ?></td>
                                                    <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($a['description']) ?>">
                                                        <?= htmlspecialchars($a['description']) ?>
                                                    </td>
                                                    <td><span class="br-badge br-badge-cat"><?= htmlspecialchars($a['category_name'] ?? 'Unknown') ?></span></td>
                                                    <td class="muted"><?= number_format($a['allocated_amount'], 2) ?> <?= htmlspecialchars($a['currency']) ?></td>
                                                    <td><span class="br-amount-pill"><?= number_format($a['remaining_amount'], 2) ?> <?= htmlspecialchars($a['currency']) ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                    </div><!-- /page-wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Confirmation Modal ──────────────────────────────────────────────────── -->
<div class="br-modal-backdrop" id="confirmModal" onclick="if(event.target===this)closeConfirm()">
    <div class="br-modal">
        <div class="br-modal-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <h5>Confirm Budget Rollover</h5>
        <p>This will move all remaining balances from <strong><?= date('F Y', strtotime($previousMonthStart)) ?></strong> into new allocations for <strong><?= date('F Y') ?></strong>. This action cannot be undone.</p>

        <div class="br-modal-summary">
            <div class="br-modal-summary-row">
                <span class="label">Allocations affected</span>
                <strong><?= $pendingCount ?></strong>
            </div>
            <?php foreach ($totalByurrency ?? $totalByCurrency ?? [] as $cur => $tot): ?>
            <div class="br-modal-summary-row">
                <span class="label">Total (<?= htmlspecialchars($cur) ?>)</span>
                <strong><?= number_format($tot, 2) ?> <?= htmlspecialchars($cur) ?></strong>
            </div>
            <?php endforeach; ?>
            <div class="br-modal-summary-row">
                <span class="label">From</span>
                <strong><?= date('F Y', strtotime($previousMonthStart)) ?></strong>
            </div>
            <div class="br-modal-summary-row">
                <span class="label">Into</span>
                <strong><?= date('F Y') ?></strong>
            </div>
        </div>

        <form method="post" id="rolloverForm">
            <input type="hidden" name="process_rollover" value="1">
            <input type="hidden" name="rollover_month" value="<?= $previousMonth ?>">
            <input type="hidden" name="rollover_year"  value="<?= $previousYear ?>">
            <div class="br-modal-actions">
                <button type="button" class="btn-cancel" onclick="closeConfirm()">Cancel</button>
                <button type="submit" class="btn-confirm">
                    Yes, Roll Over Now
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Responsive grid fix for smaller screens -->
<style>
@media (max-width: 860px) {
    .br-grid { grid-template-columns: 1fr !important; }
}
</style>

<script>
function openConfirm()  { document.getElementById('confirmModal').classList.add('open');    document.body.style.overflow = 'hidden'; }
function closeConfirm() { document.getElementById('confirmModal').classList.remove('open'); document.body.style.overflow = '';       }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });
</script>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

</body>
</html>