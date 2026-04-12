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
    // Calculate P&L data by currency
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

    // Organize by currency
    $revenue_totals = [];
    $expense_totals = [];
    
    foreach ($revenue_by_currency as $row) {
        $revenue_totals[$row['currency']] = floatval($row['total']);
    }
    
    foreach ($expense_by_currency as $row) {
        $expense_totals[$row['currency']] = floatval($row['total']);
    }
} else {
    // For stored report, parse currency data
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

// Prepare chart data separated by currency
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
    
    // Add period if not already added
    if (!in_array($period, $chart_data_by_currency[$currency]['periods'])) {
        $chart_data_by_currency[$currency]['periods'][] = $period;
    }
    
    $idx = array_search($period, $chart_data_by_currency[$currency]['periods']);
    $chart_data_by_currency[$currency]['revenues'][$idx] = floatval($trend['revenue']);
    $chart_data_by_currency[$currency]['expenses'][$idx] = floatval($trend['expenses']);
    $chart_data_by_currency[$currency]['profits'][$idx] = floatval($trend['revenue']) - floatval($trend['expenses']);
}

// Format periods for display
foreach ($chart_data_by_currency as $currency => &$data) {
    $data['periods'] = array_map(function($p) { return date('M Y', strtotime($p)); }, $data['periods']);
}

// Top revenue tenants with currency
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

// Available periods for dropdown
$periods = [];
for ($i = 0; $i < 12; $i++) {
    $d = new DateTime();
    $d->modify("-$i month");
    $periods[] = $d->format('Y-m');
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Breadcrumb -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">Profit & Loss Analysis</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Financial</a></li>
                                    <li class="breadcrumb-item active"><a href="#!">P&L Dashboard</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- Period Selector -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="mb-3">Select Period</h6>
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <form method="GET" class="form-inline">
                                                    <select name="period" class="form-control" onchange="this.form.submit()">
                                                        <?php foreach ($periods as $p): ?>
                                                        <option value="<?= $p ?>" <?= $p === $report_period ? 'selected' : '' ?>>
                                                            <?= date('F Y', strtotime($p . '-01')) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            </div>
                                            <div class="col-md-8 text-right">
                                                <p class="text-muted mb-0">
                                                    <strong><?= date('F d, Y', strtotime($start_date)) ?></strong> to 
                                                    <strong><?= date('F d, Y', strtotime($end_date)) ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main KPIs - Currency Aware -->
                         <div class="row mb-4">
                             <?php
                             // Get all unique currencies from both revenue and expenses
                             $all_currencies = array_unique(array_merge(array_keys($revenue_totals), array_keys($expense_totals)));
                             
                             foreach ($all_currencies as $currency) {
                                 $rev = $revenue_totals[$currency] ?? 0;
                                 $exp = $expense_totals[$currency] ?? 0;
                                 $profit = $rev - $exp;
                                 $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
                                 $symbol = $currency === 'AFS' ? '؋' : '$';
                             ?>
                             <div class="col-lg-6 col-xl-4 mb-4">
                                 <div class="card shadow-sm h-100" style="border-top: 4px solid #4099ff;">
                                     <div class="card-body pt-4 pb-4">
                                         <div class="text-center mb-4">
                                             <h5 class="font-weight-bold text-primary mb-0"><?= htmlspecialchars($currency) ?></h5>
                                         </div>
                                         
                                         <div class="row text-center">
                                             <div class="col-6 mb-4">
                                                 <div class="p-3 bg-light rounded" style="background-color: #f0f8ff !important;">
                                                     <small class="text-muted d-block mb-2">Revenue</small>
                                                     <h5 class="text-success font-weight-bold mb-0">+<?= $symbol . number_format($rev, 2) ?></h5>
                                                 </div>
                                             </div>
                                             <div class="col-6 mb-4">
                                                 <div class="p-3 bg-light rounded" style="background-color: #fff5f5 !important;">
                                                     <small class="text-muted d-block mb-2">Expenses</small>
                                                     <h5 class="text-danger font-weight-bold mb-0">-<?= $symbol . number_format($exp, 2) ?></h5>
                                                 </div>
                                             </div>
                                         </div>
                                         
                                         <hr class="my-3">
                                         
                                         <div class="row text-center">
                                             <div class="col-6">
                                                 <small class="text-muted d-block mb-2">Profit</small>
                                                 <h5 class="font-weight-bold mb-0" style="color: <?= $profit >= 0 ? '#17a2b8' : '#ffc107' ?>;">
                                                     <?= $symbol . number_format($profit, 2) ?>
                                                 </h5>
                                             </div>
                                             <div class="col-6">
                                                 <small class="text-muted d-block mb-2">Margin</small>
                                                 <h5 class="font-weight-bold mb-0" style="color: #6c757d;">
                                                     <?= number_format($margin, 1) ?>%
                                                 </h5>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <?php } ?>
                             <?php if (empty($all_currencies)) { ?>
                             <div class="col-12">
                                 <div class="alert alert-info">No financial data available for this period</div>
                             </div>
                             <?php } ?>
                         </div>

                        <!-- Charts Row 1 -->
                         <div class="row mb-4">
                             <!-- Revenue vs Expenses Chart by Currency -->
                             <div class="col-xl-8">
                                 <div class="card">
                                     <div class="card-header">
                                         <h5>Revenue vs Expenses (Last 12 Months)</h5>
                                     </div>
                                     <div class="card-body">
                                         <?php if (empty($chart_data_by_currency)): ?>
                                         <div class="alert alert-info">No trend data available</div>
                                         <?php else: ?>
                                         <?php foreach ($chart_data_by_currency as $currency => $data): ?>
                                         <div class="mb-4">
                                             <h6 class="font-weight-bold mb-2">Currency: <?= htmlspecialchars($currency) ?></h6>
                                             <canvas id="trendChart_<?= htmlspecialchars($currency) ?>" height="80"></canvas>
                                         </div>
                                         <?php endforeach; ?>
                                         <?php endif; ?>
                                     </div>
                                 </div>
                             </div>

                            <!-- Profit Summary - All Currencies -->
                             <div class="col-xl-4">
                                 <div class="card">
                                     <div class="card-header">
                                         <h5>Period Summary by Currency</h5>
                                     </div>
                                     <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                         <?php foreach ($all_currencies as $currency) {
                                             $rev = $revenue_totals[$currency] ?? 0;
                                             $exp = $expense_totals[$currency] ?? 0;
                                             $profit = $rev - $exp;
                                             $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
                                             $symbol = $currency === 'AFS' ? '؋' : '$';
                                         ?>
                                         <div class="mb-4 pb-3" style="border-bottom: 1px solid #e9ecef;">
                                             <h6 class="font-weight-bold mb-2"><?= htmlspecialchars($currency) ?></h6>
                                             <div class="mb-2">
                                                 <small class="text-muted">Revenue</small>
                                                 <h6 class="text-success">+<?= $symbol . number_format($rev, 2) ?></h6>
                                             </div>
                                             <div class="mb-2">
                                                 <small class="text-muted">Expenses</small>
                                                 <h6 class="text-danger">-<?= $symbol . number_format($exp, 2) ?></h6>
                                             </div>
                                             <div class="mb-2">
                                                 <small class="text-muted">Profit</small>
                                                 <h6 class="text-info"><?= $symbol . number_format($profit, 2) ?></h6>
                                             </div>
                                             <div class="progress" style="height: 6px;">
                                                 <div class="progress-bar bg-success" style="width: <?= $margin ?>%"></div>
                                             </div>
                                             <small class="text-muted d-block mt-1">Margin: <?= number_format($margin, 1) ?>%</small>
                                         </div>
                                         <?php } ?>
                                         <?php if (empty($all_currencies)) { ?>
                                         <div class="alert alert-sm alert-info">No data</div>
                                         <?php } ?>
                                     </div>
                                 </div>
                             </div>
                        </div>

                        <!-- Charts Row 2 -->
                        <div class="row mb-4">
                            <!-- Revenue by Type -->
                            <div class="col-xl-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Revenue by Type</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="revenueTypeChart" height="200"></canvas>
                                        <div class="mt-3">
                                             <?php 
                                             // Group revenue by currency
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
                                             <div class="mb-3 pb-2" style="border-bottom: 1px solid #e9ecef;">
                                                 <small class="font-weight-bold text-muted"><?= htmlspecialchars($curr) ?></small>
                                                 <?php foreach ($types as $type): ?>
                                                 <div class="d-flex justify-content-between mb-1">
                                                     <small><?= ucfirst($type['revenue_type']) ?></small>
                                                     <small><strong><?= $symbol . number_format($type['total'], 2) ?></strong> (<?= $type['count'] ?> txn)</small>
                                                 </div>
                                                 <?php endforeach; ?>
                                             </div>
                                             <?php endforeach; ?>
                                             <?php if (empty($revenue_types)): ?>
                                             <small class="text-muted">No revenue data</small>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                </div>
                            </div>

                            <!-- Top Revenue Tenants -->
                            <div class="col-xl-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Top Revenue Tenants</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        // Group tenants by currency
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
                                        <div class="mb-4">
                                            <h6 class="font-weight-bold mb-2">Currency: <?= htmlspecialchars($curr) ?></h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach (array_slice($tenants, 0, 5) as $tenant): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($tenant['name']) ?></td>
                                                            <td class="text-right"><strong><?= $symbol . number_format($tenant['total'], 2) ?></strong></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($tenants_list)): ?>
                                        <div class="text-center text-muted">No revenue data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expense Breakdown -->
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Expense Breakdown by Category</h5>
                                    </div>
                                    <div class="card-body">
                                         <?php 
                                         // Group expenses by currency
                                         $expenses_by_currency = [];
                                         foreach ($expense_categories as $cat) {
                                             $curr = $cat['currency'];
                                             if (!isset($expenses_by_currency[$curr])) {
                                                 $expenses_by_currency[$curr] = [];
                                             }
                                             $expenses_by_currency[$curr][] = $cat;
                                         }
                                         ?>
                                         <?php foreach ($expenses_by_currency as $curr => $categories): 
                                             $symbol = $curr === 'AFS' ? '؋' : '$';
                                             // Calculate total for this currency
                                             $curr_total = array_sum(array_column($categories, 'total'));
                                         ?>
                                         <div class="mb-4">
                                             <h6 class="font-weight-bold mb-3">Currency: <?= htmlspecialchars($curr) ?></h6>
                                             <div class="table-responsive">
                                                 <table class="table table-striped table-hover table-sm">
                                                     <thead class="thead-light">
                                                         <tr>
                                                             <th>Category</th>
                                                             <th class="text-right">Amount</th>
                                                             <th class="text-right">Count</th>
                                                             <th class="text-right">% of Total</th>
                                                             <th>Progress</th>
                                                         </tr>
                                                     </thead>
                                                     <tbody>
                                                         <?php foreach ($categories as $cat): 
                                                             $pct = $curr_total > 0 ? ($cat['total'] / $curr_total) * 100 : 0;
                                                         ?>
                                                         <tr>
                                                             <td><?= htmlspecialchars($cat['name'] ?? 'Uncategorized') ?></td>
                                                             <td class="text-right"><strong><?= $symbol . number_format($cat['total'], 2) ?></strong></td>
                                                             <td class="text-right"><?= $cat['count'] ?></td>
                                                             <td class="text-right"><?= number_format($pct, 1) ?>%</td>
                                                             <td>
                                                                 <div class="progress" style="height: 6px;">
                                                                     <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                                                                 </div>
                                                             </td>
                                                         </tr>
                                                         <?php endforeach; ?>
                                                     </tbody>
                                                 </table>
                                             </div>
                                         </div>
                                         <?php endforeach; ?>
                                         <?php if (empty($expense_categories)): ?>
                                         <div class="alert alert-info">No expense data available</div>
                                         <?php endif; ?>
                                     </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Trend Charts by Currency
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
const revenueTypeChart = new Chart(document.getElementById('revenueTypeChart'), {
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
</script>

</body>
</html>
