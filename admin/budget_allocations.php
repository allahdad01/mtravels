<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once 'security.php';
enforce_auth();
require_once '../includes/InputValidator.php';

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

require_once('../includes/db.php');

$currentMonth  = date('m');
$currentYear   = date('Y');
$selectedMonth = InputValidator::getMonth($_GET['month'] ?? '', (int)$currentMonth);
$selectedYear  = InputValidator::getYear($_GET['year']  ?? '', (int)$currentYear);

$startDate = $selectedYear . '-' . $selectedMonth . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

// Main accounts
$stmt = $pdo->prepare("SELECT * FROM main_account WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
$stmt->execute([$tenant_id, $branch_id]);
$mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categories
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
$stmt->execute([$tenant_id, $branch_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Allocations with join
$stmt = $pdo->prepare("
    SELECT ba.*, ma.name as account_name, ec.name as category_name, ec.parent_id as category_parent_id, esc.name as sub_category_name
    FROM budget_allocations ba
    JOIN main_account ma ON ba.main_account_id = ma.id
    JOIN expense_categories ec ON ba.category_id = ec.id
    LEFT JOIN expense_categories esc ON ba.sub_category_id = esc.id
    WHERE ba.allocation_date BETWEEN ? AND ? AND ba.tenant_id = ? AND ba.branch_id = ?
    ORDER BY ba.allocation_date DESC
");
$stmt->execute([$startDate, $endDate, $tenant_id, $branch_id]);
$allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Category name lookup (for rendering parent names)
$catNameById = [];
foreach ($categories as $c) {
    $catNameById[$c['id']] = $c['name'];
}

// Categories that actually have an allocation in the selected period,
// with all their sub-categories (for the add-expense modal)
$allocCats = [];
foreach ($allocations as $a) {
    $cid = $a['category_id'];
    if (!isset($allocCats[$cid])) {
        $allocCats[$cid] = [
            'id'        => $cid,
            'name'      => $a['category_name'],
            'parent_id' => $a['category_parent_id'],
            'subs'      => [],
        ];
    }
}

// Attach all sub-categories of each allocated category
foreach ($categories as $c) {
    if (!empty($c['parent_id']) && isset($allocCats[$c['parent_id']])) {
        $allocCats[$c['parent_id']]['subs'][$c['id']] = $c['name'];
    }
}

// Summary totals per currency
$totals    = [];
$available = [];
foreach ($allocations as $a) {
    $c = $a['currency'];
    $totals[$c]    = ($totals[$c]    ?? 0) + $a['allocated_amount'];
    $available[$c] = ($available[$c] ?? 0) + $a['remaining_amount'];
}

// Pending rollover check
$prevMonth      = date('m', strtotime('-1 month'));
$prevYear       = date('Y', strtotime('-1 month'));
$prevStart      = $prevYear  . '-' . $prevMonth . '-01';
$prevEnd        = date('Y-m-t', strtotime($prevStart));
$stmt           = $pdo->prepare("SELECT COUNT(*) FROM budget_allocations WHERE allocation_date BETWEEN ? AND ? AND remaining_amount > 0 AND tenant_id = ? AND branch_id = ?");
$stmt->execute([$prevStart, $prevEnd, $tenant_id, $branch_id]);
$pendingCount   = $stmt->fetchColumn();
?>

<style>
/* ── Design tokens ─────────────────────────────────────────────────────────── */
:root {
    --brand-a    : #4099ff;
    --brand-b    : #2ed8b6;
    --surface    : #f0f4fa;
    --card-bg    : #ffffff;
    --border     : #e2e9f3;
    --text       : #1a2233;
    --muted      : #7a8aa0;
    --radius-lg  : 14px;
    --radius-md  : 10px;
    --radius-sm  : 6px;
    --shadow-sm  : 0 2px 8px rgba(64,153,255,.08);
    --shadow-md  : 0 4px 18px rgba(64,153,255,.14);
    --shadow-lg  : 0 8px 32px rgba(64,153,255,.20);

    /* currency accent colors */
    --clr-usd    : #16a34a;
    --clr-afs    : #dc2626;
    --clr-eur    : #2563eb;
    --clr-darham : #ea580c;
}

body { background: var(--surface); }

/* ── Page header ─────────────────────────────────────────────────────────── */
.ba-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 28px;
}
.ba-title {
    font-size: 1.45rem;
    font-weight: 700;
    letter-spacing: -.3px;
    color: var(--text);
    margin: 0;
}
.ba-title span {
    background: linear-gradient(90deg, var(--brand-a), var(--brand-b));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.ba-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* ── Filter bar ──────────────────────────────────────────────────────────── */
.ba-filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--card-bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    padding: 6px 10px;
    box-shadow: var(--shadow-sm);
}
.ba-filter-bar select {
    border: none;
    background: transparent;
    font-size: .875rem;
    color: var(--text);
    padding: 4px 6px;
    outline: none;
    cursor: pointer;
}
.ba-filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--brand-a), var(--brand-b));
    border: none;
    border-radius: var(--radius-sm);
    color: #fff;
    cursor: pointer;
    transition: opacity .2s;
    flex-shrink: 0;
}
.ba-filter-btn:hover { opacity: .85; }

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn-brand {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: linear-gradient(135deg, var(--brand-a), var(--brand-b));
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    padding: 9px 18px;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(64,153,255,.32);
    transition: opacity .2s, transform .15s;
    text-decoration: none;
}
.btn-brand:hover { opacity: .9; transform: translateY(-1px); color: #fff; text-decoration: none; }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: var(--card-bg);
    color: var(--text);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    padding: 8px 16px;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
    text-decoration: none;
}
.btn-ghost:hover { border-color: var(--brand-a); box-shadow: var(--shadow-sm); color: var(--text); text-decoration: none; }

/* ── Alert banner ────────────────────────────────────────────────────────── */
.ba-alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 14px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 22px;
    font-size: .875rem;
    font-weight: 500;
}
.ba-alert.warning { background: #fff8ec; border: 1px solid #fde68a; color: #92400e; }
.ba-alert.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.ba-alert-left { display: flex; align-items: center; gap: 10px; }

/* ── Stat chips ──────────────────────────────────────────────────────────── */
.ba-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.ba-stat {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px 22px;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    transition: box-shadow .2s, transform .2s;
}
.ba-stat:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.ba-stat::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--brand-a), var(--brand-b));
}
.ba-stat-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    margin-bottom: 8px;
}
.ba-stat-value {
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1;
    margin-bottom: 4px;
}
.ba-stat-sub {
    font-size: .78rem;
    color: var(--muted);
}
.ba-stat-icon {
    position: absolute;
    top: 16px; right: 18px;
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.ba-stat-icon.blue  { background: #dbeafe; color: var(--brand-a); }
.ba-stat-icon.green { background: #dcfce7; color: #16a34a; }
.ba-stat-icon.red   { background: #fee2e2; color: #dc2626; }

/* ── Allocation cards ────────────────────────────────────────────────────── */
.ba-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}
.ba-alloc-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s, transform .2s;
    overflow: hidden;
}
.ba-alloc-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-3px); }

.ba-alloc-top {
    padding: 20px 20px 16px;
    flex: 1;
}
.ba-alloc-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}
.ba-alloc-category {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1.2;
}
.ba-date-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .72rem;
    background: var(--surface);
    color: var(--muted);
    border: 1px solid var(--border);
    border-radius: 50px;
    padding: 3px 10px;
    white-space: nowrap;
    flex-shrink: 0;
}

.ba-alloc-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 16px;
}
.ba-account-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .78rem;
    font-weight: 600;
    background: #dbeafe;
    color: #1d4ed8;
    border-radius: 50px;
    padding: 4px 12px;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ba-amount {
    font-size: 1.15rem;
    font-weight: 700;
    white-space: nowrap;
}
.ba-amount small { font-size: .72rem; font-weight: 600; opacity: .7; }

.ba-amount.usd    { color: var(--clr-usd); }
.ba-amount.afs    { color: var(--clr-afs); }
.ba-amount.eur    { color: var(--clr-eur); }
.ba-amount.darham { color: var(--clr-darham); }

/* Progress bar */
.ba-progress-wrap { margin-bottom: 12px; }
.ba-progress-labels {
    display: flex;
    justify-content: space-between;
    font-size: .72rem;
    color: var(--muted);
    margin-bottom: 5px;
}
.ba-progress-track {
    height: 7px;
    background: var(--surface);
    border-radius: 50px;
    overflow: hidden;
}
.ba-progress-fill {
    height: 100%;
    border-radius: 50px;
    transition: width .6s ease;
}
.ba-progress-fill.low  { background: linear-gradient(90deg, #34d399, #10b981); }
.ba-progress-fill.mid  { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.ba-progress-fill.high { background: linear-gradient(90deg, #f87171, #ef4444); }

.ba-desc {
    font-size: .8rem;
    color: var(--muted);
    line-height: 1.4;
    margin-bottom: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Card footer actions */
.ba-alloc-footer {
    display: flex;
    border-top: 1px solid var(--border);
    background: #fafcff;
}
.ba-alloc-footer .ba-action {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 10px 4px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: .68rem;
    font-weight: 600;
    color: var(--muted);
    border-right: 1px solid var(--border);
    transition: background .15s, color .15s;
    text-decoration: none;
}
.ba-alloc-footer .ba-action:last-child { border-right: none; }
.ba-alloc-footer .ba-action:hover { background: #f0f6ff; color: var(--brand-a); }
.ba-alloc-footer .ba-action.danger:hover { background: #fff5f5; color: #dc2626; }
.ba-alloc-footer .ba-action:disabled { opacity: .4; cursor: not-allowed; }
.ba-alloc-footer .ba-action:disabled:hover { background: transparent; color: var(--muted); }
.ba-alloc-footer .ba-action svg { flex-shrink: 0; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.ba-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 72px 24px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}
.ba-empty-icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0f0ff, #d0f7ef);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px;
}
.ba-empty h5 { font-size: 1.05rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
.ba-empty p  { font-size: .875rem; color: var(--muted); margin-bottom: 20px; }

/* ── Section label ───────────────────────────────────────────────────────── */
.ba-section-label {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ba-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── Custom badges for fund transaction type ────────────────────────────── */
.ba-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.ba-badge.debit {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.ba-badge.credit {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}
.ba-badge.sub {
    background: #e3efff;
    color: #1d4ed8;
    border: 1px solid #bcd9f7;
    text-transform: none;
    font-weight: 600;
    letter-spacing: 0;
}

/* ── Action buttons in fund table ──────────────────────────────────────── */
.ba-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    background: transparent;
    cursor: pointer;
    color: #555;
    font-size: 0;
    transition: background .12s, color .12s;
}
.ba-action-btn:hover { background: #f5f5f5; color: #1a1a1a; }
.ba-action-btn.danger { color: #A32D2D; border-color: #f0c5c5; }
.ba-action-btn.danger:hover { background: #fdf0f0; }
.ba-action-btn i { font-size: 14px; }

@media (max-width: 640px) {
    .ba-header { flex-direction: column; align-items: stretch; }
    .ba-header-actions { justify-content: flex-end; }
    .ba-cards-grid { grid-template-columns: 1fr; }
}
</style>


<?php include '../includes/header.php'; ?>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="main-body">
          <div class="page-wrapper">

            <!-- ── Page header ── -->
            <div class="ba-header">
                <h1 class="ba-title">Budget <span>Allocations</span></h1>
                <div class="ba-header-actions">

                    <!-- Month / year filter -->
                    <form method="get" class="ba-filter-bar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <select name="month" id="monthFilter">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= sprintf('%02d', $m) ?>" <?= $selectedMonth == sprintf('%02d', $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" id="yearFilter">
                            <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="ba-filter-btn" title="Apply filter">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        </button>
                    </form>

                    <a href="budget_rollover.php" class="btn-ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        <?= __('budget_rollover') ?>
                        <?php if ($pendingCount > 0): ?>
                        <span style="background:#f59e0b;color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:50px;"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>

                    <button type="button" class="btn-brand" style="background:linear-gradient(135deg,#2ed8b6,#4099ff);" data-toggle="modal" data-target="#addAllocationExpenseModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        <?= __('add_expense') ?>
                    </button>
                    <button type="button" class="btn-brand" data-toggle="modal" data-target="#allocationModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?= __('new_allocation') ?>
                    </button>
                </div>
            </div>

            <!-- ── Pending rollover alert ── -->
            <?php if ($pendingCount > 0): ?>
            <div class="ba-alert warning">
                <div class="ba-alert-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong><?= __('attention') ?>:</strong> <?= $pendingCount ?> <?= __('budget_allocations_from') ?> <?= date('F Y', strtotime($prevStart)) ?> <?= __('with_remaining_funds') ?></span>
                </div>
                <a href="budget_rollover.php" class="btn-brand" style="font-size:.8rem;padding:7px 14px;">
                    <?= __('process_rollover') ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- ── Viewing period notice ── -->
            <div class="ba-alert info" style="margin-bottom:24px;">
                <div class="ba-alert-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= __('showing_budget_allocations_for') ?>: <strong style="margin-left:4px;"><?= date('F Y', strtotime($startDate)) ?></strong>
                </div>
            </div>

            <!-- ── Summary stats ── -->
            <div class="ba-stats">
                <?php foreach (['Total' => $totals, 'Available' => $available] as $label => $map):
                    $isAvail = $label === 'Available';
                    $iconClass = $isAvail ? 'green' : 'blue';
                    foreach ($map as $cur => $amt):
                        $used = ($totals[$cur] ?? 0) - ($available[$cur] ?? 0);
                ?>
                <?php endforeach; endforeach; ?>

                <?php foreach ($totals as $cur => $amt): ?>
                <div class="ba-stat">
                    <div class="ba-stat-icon blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="ba-stat-label"><?= __('total_allocations') ?> (<?= htmlspecialchars($cur) ?>)</div>
                    <div class="ba-stat-value"><?= number_format($amt, 2) ?></div>
                    <div class="ba-stat-sub"><?= htmlspecialchars($cur) ?></div>
                </div>
                <?php endforeach; ?>

                <?php foreach ($available as $cur => $amt): ?>
                <div class="ba-stat">
                    <div class="ba-stat-icon green">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="ba-stat-label"><?= __('available_funds') ?> (<?= htmlspecialchars($cur) ?>)</div>
                    <div class="ba-stat-value"><?= number_format($amt, 2) ?></div>
                    <div class="ba-stat-sub"><?= htmlspecialchars($cur) ?></div>
                </div>
                <?php endforeach; ?>

                <?php foreach ($totals as $cur => $total):
                    $used = $total - ($available[$cur] ?? 0); ?>
                <div class="ba-stat">
                    <div class="ba-stat-icon red">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                    <div class="ba-stat-label"><?= __('used_funds') ?> (<?= htmlspecialchars($cur) ?>)</div>
                    <div class="ba-stat-value"><?= number_format($used, 2) ?></div>
                    <div class="ba-stat-sub"><?= htmlspecialchars($cur) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Allocation cards ── -->
            <div class="ba-section-label">
                <?= count($allocations) ?> allocation<?= count($allocations) !== 1 ? 's' : '' ?> found
            </div>

            <div class="ba-cards-grid">
                <?php if (empty($allocations)): ?>
                <div class="ba-empty">
                    <div class="ba-empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--brand-a)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                    <h5><?= __('no_budget_allocations_found') ?></h5>
                    <p><?= __('no_budget_allocations_found_for_selected_month') ?></p>
                    <a href="budget_allocations.php" class="btn-ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.34"/></svg>
                        <?= __('show_all_allocations') ?>
                    </a>
                </div>

                <?php else: foreach ($allocations as $alloc):
                    $usedAmt     = $alloc['allocated_amount'] - $alloc['remaining_amount'];
                    $usedPct     = $alloc['allocated_amount'] > 0 ? round(($usedAmt / $alloc['allocated_amount']) * 100) : 0;
                    $fillClass   = $usedPct < 50 ? 'low' : ($usedPct < 75 ? 'mid' : 'high');
                    $amtClass    = strtolower($alloc['currency']);
                    $hasActivity = $usedAmt > 0;
                ?>
                <div class="ba-alloc-card">
                    <div class="ba-alloc-top">

                        <div class="ba-alloc-head">
                            <div class="ba-alloc-category">
                                <?= htmlspecialchars($alloc['category_name']) ?>
                                <?php if (!empty($alloc['sub_category_name'])): ?>
                                    <div style="font-size:.78rem;font-weight:600;color:var(--brand-a);margin-top:3px;"><?= htmlspecialchars($alloc['sub_category_name']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="ba-date-chip">
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?= date('d M Y', strtotime($alloc['allocation_date'])) ?>
                            </div>
                        </div>

                        <div class="ba-alloc-meta">
                            <div class="ba-account-chip">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                <?= htmlspecialchars($alloc['account_name']) ?>
                            </div>
                            <div class="ba-amount <?= $amtClass ?>">
                                <?= number_format($alloc['allocated_amount'], 2) ?>
                                <small><?= htmlspecialchars($alloc['currency']) ?></small>
                            </div>
                        </div>

                        <div class="ba-progress-wrap">
                            <div class="ba-progress-labels">
                                <span>Used: <?= number_format($usedAmt, 2) ?> <?= htmlspecialchars($alloc['currency']) ?></span>
                                <span><?= $usedPct ?>%</span>
                                <span>Left: <?= number_format($alloc['remaining_amount'], 2) ?> <?= htmlspecialchars($alloc['currency']) ?></span>
                            </div>
                            <div class="ba-progress-track">
                                <div class="ba-progress-fill <?= $fillClass ?>" style="width:<?= $usedPct ?>%"></div>
                            </div>
                        </div>

                        <?php if (!empty($alloc['description'])): ?>
                        <p class="ba-desc"><?= htmlspecialchars($alloc['description']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Footer actions -->
                    <div class="ba-alloc-footer">
                        <button class="ba-action fund-allocation"
                                data-id="<?= $alloc['id'] ?>"
                                data-currency="<?= $alloc['currency'] ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            Fund
                        </button>
                        <button class="ba-action view-funds"
                                data-id="<?= $alloc['id'] ?>"
                                data-currency="<?= $alloc['currency'] ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            Funds
                        </button>
                        <button class="ba-action view-expenses"
                                data-id="<?= $alloc['id'] ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Expenses
                        </button>
                        <button class="ba-action danger delete-allocation"
                                data-id="<?= $alloc['id'] ?>"
                                <?= $hasActivity ? 'disabled title="Cannot delete: allocation has activity"' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

          </div><!-- /page-wrapper -->
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../modals/allocation/allocation_modal.php'; ?>
<?php include '../modals/allocation/expense_modal.php'; ?>
<?php include '../modals/allocation/fund_modal.php'; ?>
<?php include '../modals/allocation/view_fund_modal.php'; ?>

<?php
// Get unique currencies from current allocations
$allocCurrenciesStmt = $pdo->prepare("SELECT DISTINCT currency FROM budget_allocations WHERE tenant_id = ? AND branch_id = ? ORDER BY currency");
$allocCurrenciesStmt->execute([$tenant_id, $branch_id]);
$allocCurrencies = $allocCurrenciesStmt->fetchAll(PDO::FETCH_ASSOC);
$availableAllocCurrencies = array_column($allocCurrencies, 'currency');
?>

<!-- Top-level Add Expense Modal -->
<div class="modal fade" id="addAllocationExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_expense_from_budget_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="addAllocationExpenseForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('expense_category') ?></label>
                        <select class="form-control" id="topExpenseCategory" name="categoryId" required>
                            <?php if (empty($allocCats)): ?>
                                <option value=""><?= __('no_budget_allocations_found_for_selected_month') ?></option>
                            <?php else: ?>
                                <option value=""><?= __('select_category') ?></option>
                                <?php foreach ($allocCats as $cat): ?>
                                    <?php if (!empty($cat['parent_id'])): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars(($catNameById[$cat['parent_id']] ?? '') . ' — ' . $cat['name']) ?></option>
                                    <?php else: ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php foreach ($cat['subs'] as $sid => $sname): ?>
                                            <option value="<?= $cat['id'] ?>" data-sub="<?= $sid ?>"><?= htmlspecialchars($cat['name'] . ' — ' . $sname) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('date') ?></label>
                        <input type="date" class="form-control" id="topExpenseDate" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('description') ?></label>
                        <textarea class="form-control" id="topExpenseDescription" name="description" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('amount') ?></label>
                                <input type="number" step="0.01" class="form-control" id="topExpenseAmount" name="amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="topExpenseCurrency" name="currency" required>
                                    <?php if (count($availableAllocCurrencies) === 0): ?>
                                        <option value=""><?= __('no_currencies_available') ?></option>
                                    <?php elseif (count($availableAllocCurrencies) === 1): ?>
                                        <option value="<?= htmlspecialchars($availableAllocCurrencies[0]) ?>" selected><?= htmlspecialchars($availableAllocCurrencies[0]) ?></option>
                                    <?php else: ?>
                                        <option value=""><?= __('select_currency') ?></option>
                                        <?php foreach ($availableAllocCurrencies as $curr): ?>
                                            <option value="<?= htmlspecialchars($curr) ?>"><?= htmlspecialchars($curr) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-success"><?= __('add_expense') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/allocation/allocation_management.js"></script>
<script src="../js/allocation/allocation_event_handlers.js"></script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>