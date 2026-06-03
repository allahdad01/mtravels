<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';

// Get report period from URL or use current month
$report_period = $_GET['period'] ?? date('Y-m');
list($year, $month) = explode('-', $report_period);
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Fetch or create P&L report
$stmt = $pdo->prepare("SELECT * FROM system_profit_loss_reports WHERE report_period = ?");
$stmt->execute([$report_period]);
$report = $stmt->fetch();

if (!$report) {
     $revenue_stmt = $pdo->prepare("
         SELECT currency, SUM(amount) as total FROM system_revenue
         WHERE payment_date BETWEEN ? AND ? AND status = 'completed'
         GROUP BY currency
     ");
     $revenue_stmt->execute([$start_date, $end_date]);
     $revenue_by_currency = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

    $expense_stmt = $pdo->prepare("
        SELECT currency, SUM(amount) as total FROM system_expenses
        WHERE date BETWEEN ? AND ?
        GROUP BY currency
    ");
    $expense_stmt->execute([$start_date, $end_date]);
    $expense_by_currency = $expense_stmt->fetchAll(PDO::FETCH_ASSOC);

    $revenue_totals = [];
    $expense_totals = [];
    
    foreach ($revenue_by_currency as $row) {
        $revenue_totals[$row['currency']] = floatval($row['total']);
    }
    
    foreach ($expense_by_currency as $row) {
        $expense_totals[$row['currency']] = floatval($row['total']);
    }
} else {
    $revenue_totals = json_decode($report['revenue_by_currency'], true) ?? [];
    $expense_totals = json_decode($report['expense_by_currency'], true) ?? [];
}

// Get revenue by type and currency
$revenue_by_type = $pdo->prepare("
    SELECT revenue_type, currency, SUM(amount) as total, COUNT(*) as count
    FROM system_revenue
    WHERE payment_date BETWEEN ? AND ? AND status = 'completed'
    GROUP BY revenue_type, currency
    ORDER BY currency, revenue_type
");
$revenue_by_type->execute([$start_date, $end_date]);
$revenue_types = $revenue_by_type->fetchAll();

// Get expense by category and currency
$expense_by_category = $pdo->prepare("
    SELECT sec.id, sec.name, se.currency, SUM(se.amount) as total, COUNT(se.id) as count
    FROM system_expenses se
    LEFT JOIN system_expense_categories sec ON se.category_id = sec.id
    WHERE se.date BETWEEN ? AND ?
    GROUP BY sec.id, sec.name, se.currency
    ORDER BY se.currency, total DESC
");
$expense_by_category->execute([$start_date, $end_date]);
$expense_categories = $expense_by_category->fetchAll();

// Get monthly P&L trend (last 12 months) by currency
$trend_data = $pdo->prepare("
    SELECT 
        DATE_FORMAT(sr.payment_date, '%Y-%m') as period,
        sr.currency,
        SUM(sr.amount) as revenue,
        COALESCE((SELECT SUM(amount) FROM system_expenses WHERE DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(sr.payment_date, '%Y-%m') AND currency = sr.currency), 0) as expenses
    FROM system_revenue sr
    WHERE sr.status = 'completed'
    AND sr.payment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(sr.payment_date, '%Y-%m'), sr.currency
    ORDER BY sr.currency, period ASC
");
$trend_data->execute();
$monthly_trends = $trend_data->fetchAll();

$chart_data_by_currency = [];

foreach ($monthly_trends as $trend) {
    $period = $trend['period'];
    $currency = $trend['currency'];
    
    if (!isset($chart_data_by_currency[$currency])) {
        $chart_data_by_currency[$currency] = [
            'periods' => [],
            'revenues' => [],
            'expenses' => [],
            'profits' => []
        ];
    }
    
    if (!in_array($period, $chart_data_by_currency[$currency]['periods'])) {
        $chart_data_by_currency[$currency]['periods'][] = $period;
    }
    
    $idx = array_search($period, $chart_data_by_currency[$currency]['periods']);
    $chart_data_by_currency[$currency]['revenues'][$idx] = floatval($trend['revenue']);
    $chart_data_by_currency[$currency]['expenses'][$idx] = floatval($trend['expenses']);
    $chart_data_by_currency[$currency]['profits'][$idx] = floatval($trend['revenue']) - floatval($trend['expenses']);
}

foreach ($chart_data_by_currency as $currency => &$data) {
    $data['periods'] = array_map(function($p) { return date('M Y', strtotime($p)); }, $data['periods']);
}

$top_tenants = $pdo->prepare("
    SELECT t.name, sr.currency, SUM(sr.amount) as total
    FROM system_revenue sr
    LEFT JOIN tenants t ON sr.tenant_id = t.id
    WHERE sr.payment_date BETWEEN ? AND ? AND sr.status = 'completed'
    GROUP BY sr.tenant_id, t.name, sr.currency
    ORDER BY sr.currency, total DESC
");
$top_tenants->execute([$start_date, $end_date]);
$tenants_list = $top_tenants->fetchAll();

$periods = [];
for ($i = 0; $i < 12; $i++) {
    $d = new DateTime();
    $d->modify("-$i month");
    $periods[] = $d->format('Y-m');
}
?>
<?php include '../includes/header_super_admin.php'; ?>
<style>
    :root {
        --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        --muted: #888;
        --surface: #fff;
        --surface2: #f5f6fa;
        --border: #e0e0e0;
        --text: #333;
        --radius: 10px;
        --green: #10b981;
        --red: #ef4444;
        --amber: #f59e0b;
        --blue: #3b82f6;
        --info: #17a2b8;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif; background:#f0f2f5; color:var(--text); }

    /* ─── PAGE HEADER ────────────────────────────────────────── */
    .page-header.card {
        background: var(--grad) !important; color: #fff; border: none !important;
        margin-bottom: 20px; padding: 22px 28px !important;
        box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
        position: relative; overflow: hidden;
    }
    .page-header.card::after {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }
    .page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
    .page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
    .page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }

    /* ─── ALERTS ──────────────────────────────────────────────── */
    .sa-alert {
        display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
        border-radius: var(--radius); border: 1px solid var(--border);
        margin-bottom: 16px; animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .sa-alert-success { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .sa-alert-danger { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
    .sa-alert-info { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }

    /* ─── SECTION LABEL ───────────────────────────────────────── */
    .section-label {
        font-size:0.82rem; font-weight:700; color:var(--muted); text-transform:uppercase;
        letter-spacing:0.05em; margin-bottom:10px;
    }

    /* ─── CARDS ───────────────────────────────────────────────── */
    .sa-card {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
    }
    .sa-card-header {
        padding:16px 20px; border-bottom:1px solid var(--border);
    }
    .sa-card-header h5 {
        font-size:0.95rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;
    }
    .sa-card-body { padding:20px; }

    /* ─── GRID LAYOUTS ────────────────────────────────────────── */
    .sa-grid { display:grid; gap:20px; }
    .sa-grid-kpi { grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); }
    .sa-grid-8-4 { grid-template-columns:2fr 1fr; }
    .sa-grid-half { grid-template-columns:1fr 1fr; }
    @media (max-width:992px) {
        .sa-grid-8-4, .sa-grid-half { grid-template-columns:1fr; }
    }

    /* ─── KPI CARDS ───────────────────────────────────────────── */
    .sa-kpi-card {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        border-top:4px solid var(--blue); padding:20px;
        box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:all 0.2s;
    }
    .sa-kpi-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.1); }
    .sa-kpi-title { font-size:0.85rem; font-weight:700; color:var(--blue); margin-bottom:16px; }
    .sa-kpi-body { margin-bottom:12px; }
    .sa-kpi-row {
        display:flex; justify-content:space-between; align-items:center;
        padding:6px 0; border-bottom:1px solid #f0f0f0;
    }
    .sa-kpi-label { font-size:0.78rem; color:var(--muted); font-weight:500; }
    .sa-kpi-val { font-weight:700; font-size:0.92rem; }
    .sa-kpi-positive { color:var(--green); }
    .sa-kpi-negative { color:var(--red); }
    .sa-kpi-footer {
        display:flex; gap:16px; padding-top:12px; border-top:1px solid var(--border);
    }
    .sa-kpi-stat { flex:1; }
    .sa-kpi-stat-label { display:block; font-size:0.7rem; color:var(--muted); margin-bottom:2px; }
    .sa-kpi-stat-value { font-weight:700; font-size:0.95rem; }

    /* ─── SUMMARY ITEMS ───────────────────────────────────────── */
    .sa-summary-item {
        margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #eee;
    }
    .sa-summary-item:last-child { margin-bottom:0; padding-bottom:0; border-bottom:none; }
    .sa-summary-row {
        display:flex; justify-content:space-between; align-items:center; padding:3px 0;
    }
    .sa-summary-lbl { font-size:0.78rem; color:var(--muted); }
    .sa-summary-val { font-size:0.85rem; font-weight:600; }
    .sa-text-green { color:var(--green); }
    .sa-text-red { color:var(--red); }
    .sa-text-info { color:var(--info); }
    .sa-flex-row {
        display:flex; justify-content:space-between; align-items:center; padding:2px 0;
    }

    /* ─── PROGRESS BARS ───────────────────────────────────────── */
    .sa-bar { height:6px; background:#eee; border-radius:3px; overflow:hidden; }
    .sa-bar-fill { height:100%; border-radius:3px; transition:width 0.5s ease; }
    .sa-bar-green { background:var(--green); }
    .sa-bar-blue { background:var(--grad); }

    /* ─── DATA TABLE ──────────────────────────────────────────── */
    .sa-table-wrap {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        overflow-x:auto;
    }
    .sa-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    .sa-table thead { background:#f8f9fc; }
    .sa-table th {
        padding:10px 14px; text-align:left; font-weight:600; color:#555;
        border-bottom:2px solid var(--border); white-space:nowrap;
    }
    .sa-table td { padding:8px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .sa-table tbody tr:hover { background:#f8f9fc; }
    .sa-table tbody tr:last-child td { border-bottom:none; }

    /* ─── BUTTONS ─────────────────────────────────────────────── */
    .sa-btn {
        display:inline-flex; align-items:center; padding:9px 18px; border-radius:8px;
        font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s;
        border:none; text-decoration:none; gap:4px;
    }
    .sa-btn-sm { padding:6px 12px; font-size:0.8rem; }
    .sa-btn-primary { background:var(--grad); color:#fff; }
    .sa-btn-primary:hover { box-shadow:0 4px 12px rgba(64,153,255,0.35); transform:translateY(-1px); }

    /* ─── FORM ELEMENTS ───────────────────────────────────────── */
    .sa-form-control {
        width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
        font-size:0.85rem; background:var(--surface); color:var(--text); transition:border-color 0.15s;
    }
    .sa-form-control:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    select.sa-form-control { cursor:pointer; }
    </style>
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header card">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                                    Profit & Loss Analysis
                                </h5>
                                <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;">Financial performance dashboard with multi-currency support</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <span style="font-size:0.85rem;opacity:0.85;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <?php echo date('F d, Y', strtotime($start_date)); ?> &ndash; <?php echo date('F d, Y', strtotime($end_date)); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->

                            <!-- Period Selector -->
                            <p class="section-label">Report Period</p>
                            <div class="sa-card" style="margin-bottom:20px;">
                                <div class="sa-card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                                    <form method="GET" style="display:flex;align-items:center;gap:10px;">
                                        <select name="period" class="sa-form-control" onchange="this.form.submit()" style="min-width:180px;">
                                            <?php foreach ($periods as $p): ?>
                                            <option value="<?= $p ?>" <?= $p === $report_period ? 'selected' : '' ?>>
                                                <?= date('F Y', strtotime($p . '-01')) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                            View
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- KPI Cards by Currency -->
                            <?php
                            $all_currencies = array_unique(array_merge(array_keys($revenue_totals), array_keys($expense_totals)));
                            if (!empty($all_currencies)):
                            ?>
                            <p class="section-label">Financial Summary</p>
                            <div class="sa-grid sa-grid-kpi">
                                <?php foreach ($all_currencies as $currency):
                                    $rev = $revenue_totals[$currency] ?? 0;
                                    $exp = $expense_totals[$currency] ?? 0;
                                    $profit = $rev - $exp;
                                    $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
                                    $symbol = $currency === 'AFS' ? '؋' : '$';
                                ?>
                                <div class="sa-kpi-card">
                                    <div class="sa-kpi-title"><?= htmlspecialchars($currency) ?></div>
                                    <div class="sa-kpi-body">
                                        <div class="sa-kpi-row">
                                            <span class="sa-kpi-label">Revenue</span>
                                            <span class="sa-kpi-val sa-kpi-positive">+<?= $symbol . number_format($rev, 2) ?></span>
                                        </div>
                                        <div class="sa-kpi-row">
                                            <span class="sa-kpi-label">Expenses</span>
                                            <span class="sa-kpi-val sa-kpi-negative">-<?= $symbol . number_format($exp, 2) ?></span>
                                        </div>
                                    </div>
                                    <div class="sa-kpi-footer">
                                        <div class="sa-kpi-stat">
                                            <span class="sa-kpi-stat-label">Profit</span>
                                            <span class="sa-kpi-stat-value" style="color:<?= $profit >= 0 ? 'var(--info)' : 'var(--amber)' ?>"><?= $symbol . number_format($profit, 2) ?></span>
                                        </div>
                                        <div class="sa-kpi-stat">
                                            <span class="sa-kpi-stat-label">Margin</span>
                                            <span class="sa-kpi-stat-value"><?= number_format($margin, 1) ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="sa-alert sa-alert-info">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                No financial data available for this period
                            </div>
                            <?php endif; ?>

                            <!-- Charts Row 1 -->
                            <div class="sa-grid sa-grid-8-4" style="margin-bottom:20px;">
                                <!-- Revenue vs Expenses Chart -->
                                <div class="sa-card">
                                    <div class="sa-card-header">
                                        <h5>Revenue vs Expenses (Last 12 Months)</h5>
                                    </div>
                                    <div class="sa-card-body">
                                        <?php if (empty($chart_data_by_currency)): ?>
                                        <div class="sa-alert sa-alert-info">No trend data available</div>
                                        <?php else: ?>
                                        <?php foreach ($chart_data_by_currency as $currency => $data): ?>
                                        <div style="margin-bottom:16px;">
                                            <h6 style="font-weight:600;margin-bottom:8px;">Currency: <?= htmlspecialchars($currency) ?></h6>
                                            <canvas id="trendChart_<?= htmlspecialchars($currency) ?>" height="80"></canvas>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Period Summary -->
                                <div class="sa-card">
                                    <div class="sa-card-header">
                                        <h5>Period Summary by Currency</h5>
                                    </div>
                                    <div class="sa-card-body" style="max-height:400px;overflow-y:auto;">
                                        <?php foreach ($all_currencies as $currency):
                                            $rev = $revenue_totals[$currency] ?? 0;
                                            $exp = $expense_totals[$currency] ?? 0;
                                            $profit = $rev - $exp;
                                            $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
                                            $symbol = $currency === 'AFS' ? '؋' : '$';
                                        ?>
                                        <div class="sa-summary-item">
                                            <h6 style="font-weight:600;margin-bottom:8px;"><?= htmlspecialchars($currency) ?></h6>
                                            <div class="sa-summary-row">
                                                <span class="sa-summary-lbl">Revenue</span>
                                                <span class="sa-summary-val sa-text-green">+<?= $symbol . number_format($rev, 2) ?></span>
                                            </div>
                                            <div class="sa-summary-row">
                                                <span class="sa-summary-lbl">Expenses</span>
                                                <span class="sa-summary-val sa-text-red">-<?= $symbol . number_format($exp, 2) ?></span>
                                            </div>
                                            <div class="sa-summary-row">
                                                <span class="sa-summary-lbl">Profit</span>
                                                <span class="sa-summary-val sa-text-info"><?= $symbol . number_format($profit, 2) ?></span>
                                            </div>
                                            <div class="sa-bar" style="margin:8px 0;">
                                                <div class="sa-bar-fill sa-bar-green" style="width:<?= $margin ?>%"></div>
                                            </div>
                                            <small style="color:var(--muted);display:block;margin-top:2px;">Margin: <?= number_format($margin, 1) ?>%</small>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($all_currencies)): ?>
                                        <div class="sa-alert sa-alert-info">No data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts Row 2 -->
                            <div class="sa-grid sa-grid-half" style="margin-bottom:20px;">
                                <!-- Revenue by Type -->
                                <div class="sa-card">
                                    <div class="sa-card-header">
                                        <h5>Revenue by Type</h5>
                                    </div>
                                    <div class="sa-card-body">
                                        <canvas id="revenueTypeChart" height="200"></canvas>
                                        <div style="margin-top:16px;">
                                            <?php 
                                            $revenue_by_currency_type = [];
                                            foreach ($revenue_types as $type) {
                                                $curr = $type['currency'];
                                                if (!isset($revenue_by_currency_type[$curr])) {
                                                    $revenue_by_currency_type[$curr] = [];
                                                }
                                                $revenue_by_currency_type[$curr][] = $type;
                                            }
                                            foreach ($revenue_by_currency_type as $curr => $types): 
                                                $symbol = $curr === 'AFS' ? '؋' : '$';
                                            ?>
                                            <div class="sa-summary-item">
                                                <small style="font-weight:600;color:var(--muted);display:block;margin-bottom:4px;"><?= htmlspecialchars($curr) ?></small>
                                                <?php foreach ($types as $type): ?>
                                                <div class="sa-flex-row">
                                                    <small><?= ucfirst($type['revenue_type']) ?></small>
                                                    <small><strong><?= $symbol . number_format($type['total'], 2) ?></strong> (<?= $type['count'] ?> txn)</small>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($revenue_types)): ?>
                                            <small style="color:var(--muted);">No revenue data</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Top Revenue Tenants -->
                                <div class="sa-card">
                                    <div class="sa-card-header">
                                        <h5>Top Revenue Tenants</h5>
                                    </div>
                                    <div class="sa-card-body">
                                        <?php 
                                        $tenants_by_currency = [];
                                        foreach ($tenants_list as $tenant) {
                                            $curr = $tenant['currency'];
                                            if (!isset($tenants_by_currency[$curr])) {
                                                $tenants_by_currency[$curr] = [];
                                            }
                                            $tenants_by_currency[$curr][] = $tenant;
                                        }
                                        ?>
                                        <?php foreach ($tenants_by_currency as $curr => $tenants): 
                                            $symbol = $curr === 'AFS' ? '؋' : '$';
                                        ?>
                                        <div style="margin-bottom:16px;">
                                            <h6 style="font-weight:600;margin-bottom:8px;">Currency: <?= htmlspecialchars($curr) ?></h6>
                                            <div class="sa-table-wrap">
                                                <table class="sa-table">
                                                    <tbody>
                                                        <?php foreach (array_slice($tenants, 0, 5) as $tenant): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($tenant['name']) ?></td>
                                                            <td style="text-align:right;"><strong><?= $symbol . number_format($tenant['total'], 2) ?></strong></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($tenants_list)): ?>
                                        <div style="text-align:center;color:var(--muted);padding:20px;">No revenue data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Expense Breakdown -->
                            <p class="section-label">Expense Breakdown by Category</p>
                            <?php 
                            $expenses_by_currency = [];
                            foreach ($expense_categories as $cat) {
                                $curr = $cat['currency'];
                                if (!isset($expenses_by_currency[$curr])) {
                                    $expenses_by_currency[$curr] = [];
                                }
                                $expenses_by_currency[$curr][] = $cat;
                            }
                            ?>
                            <?php if (!empty($expenses_by_currency)): ?>
                            <?php foreach ($expenses_by_currency as $curr => $categories): 
                                $symbol = $curr === 'AFS' ? '؋' : '$';
                                $curr_total = array_sum(array_column($categories, 'total'));
                            ?>
                            <div class="sa-card" style="margin-bottom:16px;">
                                <div class="sa-card-header">
                                    <h5>Currency: <?= htmlspecialchars($curr) ?></h5>
                                </div>
                                <div class="sa-card-body" style="padding:0;">
                                    <div class="sa-table-wrap" style="border:none;border-radius:0;">
                                        <table class="sa-table">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th style="text-align:right;">Amount</th>
                                                    <th style="text-align:right;">Count</th>
                                                    <th style="text-align:right;">% of Total</th>
                                                    <th>Progress</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categories as $cat): 
                                                    $pct = $curr_total > 0 ? ($cat['total'] / $curr_total) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($cat['name'] ?? 'Uncategorized') ?></td>
                                                    <td style="text-align:right;"><strong><?= $symbol . number_format($cat['total'], 2) ?></strong></td>
                                                    <td style="text-align:right;"><?= $cat['count'] ?></td>
                                                    <td style="text-align:right;"><?= number_format($pct, 1) ?>%</td>
                                                    <td style="width:120px;">
                                                        <div class="sa-bar">
                                                            <div class="sa-bar-fill sa-bar-blue" style="width:<?= $pct ?>%"></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="sa-alert sa-alert-info">No expense data available</div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>


<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
    <script>
    const currencySymbols = {
        'AFS': '؋',
        'USD': '$'
    };

    const chartDataByCurrency = <?= json_encode($chart_data_by_currency) ?>;

    Object.keys(chartDataByCurrency).forEach(function(currency) {
        const data = chartDataByCurrency[currency];
        const symbol = currencySymbols[currency] || currency;
        
        const canvasId = 'trendChart_' + currency;
        const element = document.getElementById(canvasId);
        
        if (element) {
            new Chart(element, {
                type: 'line',
                data: {
                    labels: data.periods,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: data.revenues,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#10B981',
                            pointRadius: 4,
                            borderWidth: 2
                        },
                        {
                            label: 'Expenses',
                            data: data.expenses,
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#EF4444',
                            pointRadius: 4,
                            borderWidth: 2
                        },
                        {
                            label: 'Profit',
                            data: data.profits,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: '#3B82F6',
                            pointRadius: 4,
                            borderWidth: 3,
                            borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#333',
                            bodyColor: '#666',
                            borderColor: '#e1e1e1',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += symbol + context.parsed.y.toFixed(2);
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function(value) { return symbol + value.toFixed(0); } }
                        }
                    }
                }
            });
        }
    });

    // Revenue Type Chart
    const revenueTypeCanvas = document.getElementById('revenueTypeChart');
    if (revenueTypeCanvas) {
        new Chart(revenueTypeCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(fn($r) => ucfirst($r['revenue_type']), $revenue_types)) ?>,
                datasets: [{
                    data: <?= json_encode(array_map(fn($r) => floatval($r['total']), $revenue_types)) ?>,
                    backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6', '#EC4899'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: (c) => '$' + c.parsed.toFixed(2) } }
                }
            }
        });
    }
    </script>
<?php include '../includes/admin_footer.php'; ?>
