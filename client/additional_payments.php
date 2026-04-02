<?php
/**
 * Client Additional Payments Page
 */

// ── Session & Auth ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}
// Define h() function for HTML escaping
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
require_once '../includes/db.php';
require_once '../includes/language_helpers.php';
require_once '../includes/session_check.php';

$tenant_id = $_SESSION['tenant_id'];
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];

// ── Language ───────────────────────────────────────────────
init_language();
if (isset($_GET['lang'])) set_language($_GET['lang'], true);

// ── Settings ───────────────────────────────────────────────
try {
    $st = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $st->execute([$tenant_id]);
    $settings = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $settings = [];
}



// ── Filters & Pagination ───────────────────────────────────
$page            = max(1, (int)($_GET['page']     ?? 1));
$filter_currency = strtoupper(trim($_GET['currency'] ?? ''));
$per_page        = 10;

// ── Unfiltered Summary (always full totals) ────────────────
$summary = ['count' => 0, 'usd' => 0.0, 'afs' => 0.0];
try {
    $st = $pdo->prepare("
        SELECT currency, SUM(sold_amount) AS tot, COUNT(*) AS cnt
        FROM additional_payments
        WHERE client_id = ? AND tenant_id = ?
        GROUP BY currency
    ");
    $st->execute([$client_id, $tenant_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $summary['count'] += (int)$row['cnt'];
        if (strtoupper(trim($row['currency'] ?? '')) === 'USD')
            $summary['usd'] += (float)$row['tot'];
        else
            $summary['afs'] += (float)$row['tot'];
    }
} catch (PDOException $e) {
    error_log("Summary error: " . $e->getMessage());
}

// ── Filtered & Paginated Payments ──────────────────────────
$payments    = [];
$total       = 0;
$total_pages = 1;
$offset      = 0;
try {
    $where  = "WHERE client_id = ? AND tenant_id = ?";
    $params = [$client_id, $tenant_id];

    if ($filter_currency !== '') { $where .= " AND currency = ?"; $params[] = $filter_currency; }

    $cs = $pdo->prepare("SELECT COUNT(*) FROM additional_payments $where");
    $cs->execute($params);
    $total       = (int)$cs->fetchColumn();
    $total_pages = $total > 0 ? (int)ceil($total / $per_page) : 1;
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    $fs = $pdo->prepare("
        SELECT id, payment_type, sold_amount, currency,
               description, created_at
        FROM additional_payments $where
        ORDER BY created_at DESC LIMIT ? OFFSET ?
    ");
    $fs->execute(array_merge($params, [$per_page, $offset]));
    $payments = $fs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Payments fetch error: " . $e->getMessage());
}


$agencyName = htmlspecialchars($settings['agency_name'] ?? 'MTravels');
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $agencyName ?> – Additional Payments</title>
    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* ── Variables ────────────────────────────────────────── */
        :root {
            --primary:       #4361ee;
            --primary-dark:  #3a0ca3;
            --success:       #22c55e;
            --warning:       #f59e0b;
            --danger:        #ef4444;
            --info:          #3b82f6;

            --grad-primary:  linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            --grad-success:  linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            --grad-warning:  linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --grad-danger:   linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --grad-info:     linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);

            --bg-page:       #f0f4f8;
            --bg-card:       #ffffff;
            --border:        #e2e8f0;
            --text:          #1e293b;
            --text-muted:    #64748b;

            --radius-xl:  20px;
            --radius-lg:  14px;
            --radius-md:  10px;
            --radius-sm:   7px;

            --shadow-sm:  0 1px 4px rgba(0,0,0,0.06);
            --shadow-md:  0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg:  0 8px 32px rgba(0,0,0,0.12);
            --transition: all 0.22s ease;
        }

        body { background: var(--bg-page); color: var(--text); }

        /* ── Layout wrapper ───────────────────────────────────── */
        .ap-wrap { padding: 24px; }

        /* ── Hero Banner ──────────────────────────────────────── */
        .hero-banner {
            background: var(--grad-primary);
            border-radius: var(--radius-xl);
            padding: 28px 32px;
            margin-bottom: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 8px 32px rgba(67,97,238,0.35);
            position: relative;
            overflow: hidden;
        }
        /* decorative circles */
        .hero-banner::before,
        .hero-banner::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .hero-banner::before {
            width: 220px; height: 220px;
            top: -60px; right: -50px;
            background: rgba(255,255,255,0.06);
        }
        .hero-banner::after {
            width: 130px; height: 130px;
            bottom: -50px; right: 120px;
            background: rgba(255,255,255,0.04);
        }

        .hero-left { position: relative; z-index: 1; }
        .hero-eyebrow {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.82rem;
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            margin-bottom: 8px;
        }
        .live-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 3px rgba(74,222,128,0.25);
            animation: live-pulse 2s infinite;
        }
        @keyframes live-pulse {
            0%,100% { box-shadow: 0 0 0 3px rgba(74,222,128,0.25); }
            50%      { box-shadow: 0 0 0 7px rgba(74,222,128,0); }
        }
        .hero-name {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        .hero-meta {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }
        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.83rem;
            color: rgba(255,255,255,0.78);
        }
        .hero-meta-item i { font-size: 0.82rem; }
        .hero-meta-item strong { color: #fff; font-weight: 700; }
        .hero-divider {
            width: 1px; height: 14px;
            background: rgba(255,255,255,0.25);
        }

        .hero-right {
            display: flex;
            align-items: center;
            gap: 18px;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        .hero-date-box { text-align: right; }
        .hero-date-day {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .hero-date-sub {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.65);
            margin-top: 3px;
        }
        .hero-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        }

        /* ── Summary Grid ─────────────────────────────────────── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 26px;
        }
        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .sum-icon {
            width: 52px; height: 52px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #fff;
            flex-shrink: 0;
        }
        .sum-icon.si-primary { background: var(--grad-primary); }
        .sum-icon.si-success { background: var(--grad-success); }
        .sum-icon.si-warning { background: var(--grad-warning); }
        .sum-icon.si-info    { background: var(--grad-info);    }

        .sum-body {}
        .sum-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.65px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .sum-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        .sum-sub {
            font-size: 0.73rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* ── Panel ────────────────────────────────────────────── */
        .panel {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background: #fafbfc;
            flex-wrap: wrap;
            gap: 10px;
        }
        .panel-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-title h5 {
            font-size: 0.97rem;
            font-weight: 700;
            margin: 0;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .panel-title h5 i { color: var(--primary); }
        .rec-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--grad-primary);
            color: #fff;
        }

        /* ── Filter Bar ───────────────────────────────────────── */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-select {
            height: 35px;
            padding: 0 28px 0 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.81rem;
            color: var(--text);
            background: #fff
              url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2364748b'/%3E%3C/svg%3E")
              no-repeat right 10px center;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: var(--transition);
            outline: none;
        }
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.12);
        }
        .btn-reset {
            height: 35px;
            padding: 0 13px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text-muted);
            font-size: 0.81rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-reset:hover { border-color: var(--danger); color: var(--danger); }

        /* ── Table ────────────────────────────────────────────── */
        .panel-body { padding: 0; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-table thead tr { border-bottom: 2px solid var(--border); }
        .payment-table thead th {
            padding: 11px 16px;
            font-size: 0.69rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-muted);
            background: #fafbfc;
            white-space: nowrap;
            /* sticky header */
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .payment-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.14s;
        }
        .payment-table tbody tr:nth-child(even) { background: #f8fafc; }
        .payment-table tbody tr:hover           { background: #eef2ff; }
        .payment-table tbody tr:last-child      { border-bottom: none; }
        .payment-table tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 0.875rem;
            color: var(--text);
        }

        /* Row number bubble */
        .row-num {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--bg-page);
            border: 1px solid var(--border);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.73rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        /* Type chip */
        .type-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            font-weight: 600;
            background: #eef2ff;
            color: var(--primary);
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }
        .type-chip i { font-size: 0.72rem; }

        /* Amount */
        .amount-cell {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text);
            white-space: nowrap;
        }
        .cur-tag {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--bg-page);
            border: 1px solid var(--border);
            padding: 1px 5px;
            border-radius: 4px;
            margin-left: 4px;
            vertical-align: middle;
        }

        /* Status pill */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-pill .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .s-completed  { background: #dcfce7; color: #166534; }
        .s-completed .dot  { background: #16a34a; }
        .s-processing { background: #fef9c3; color: #854d0e; }
        .s-processing .dot { background: #d97706; }
        .s-failed     { background: #fee2e2; color: #991b1b; }
        .s-failed .dot     { background: #dc2626; }
        .s-pending    { background: #e0f2fe; color: #075985; }
        .s-pending .dot    { background: #0284c7; }

        /* Method */
        .method-cell {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.83rem;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .method-cell i { font-size: 0.78rem; }

        /* Description */
        .desc-cell {
            font-size: 0.83rem;
            color: var(--text-muted);
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Date */
        .date-main { font-size: 0.85rem; font-weight: 600; color: var(--text); white-space: nowrap; }
        .date-time { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }

        /* View button */
        .btn-view {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #fff;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-view:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(67,97,238,0.3);
            transform: translateY(-1px);
        }

        /* ── Empty State ──────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 64px 20px;
        }
        .empty-icon-wrap {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: var(--bg-page);
            border: 2px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            color: var(--text-muted);
        }
        .empty-state h6 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 0.88rem;
            color: var(--text-muted);
            max-width: 300px;
            margin: 0 auto;
            line-height: 1.6;
        }
        .empty-ok {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 18px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #166534;
            background: #dcfce7;
            padding: 7px 16px;
            border-radius: 20px;
        }

        /* ── Pagination ───────────────────────────────────────── */
        .pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            background: #fafbfc;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pagination-info {
            font-size: 0.83rem;
            color: var(--text-muted);
        }
        .pagination-info strong { color: var(--text); }
        .pagination {
            display: flex;
            gap: 5px;
            list-style: none;
            margin: 0; padding: 0;
        }
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px; height: 34px;
            padding: 0 8px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            font-size: 0.82rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }
        .page-link:hover { background: #eef2ff; border-color: var(--primary); color: var(--primary); }
        .page-item.active .page-link {
            background: var(--grad-primary);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 2px 8px rgba(67,97,238,0.3);
        }
        .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 1100px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .hero-banner   { flex-direction: column; align-items: flex-start; padding: 22px 20px; }
            .hero-right    { width: 100%; justify-content: space-between; }
            .panel-header  { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 540px) {
            .summary-grid  { grid-template-columns: 1fr; }
            .hero-meta     { flex-direction: column; align-items: flex-start; gap: 6px; }
            .hero-divider  { display: none; }
            .pagination-wrap { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>

<?php include '../includes/header_client.php'; ?>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="ap-wrap">

            <!-- ────────────────────────────────────────────────
                 Summary Cards  (always visible, show 0 when empty)
            ──────────────────────────────────────────────── -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="sum-icon si-primary">
                        <i class="feather icon-layers"></i>
                    </div>
                    <div class="sum-body">
                        <div class="sum-label">Total Payments</div>
                        <div class="sum-value"><?= number_format($summary['count']) ?></div>
                        <div class="sum-sub">All-time records</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="sum-icon si-success">
                        <i class="feather icon-dollar-sign"></i>
                    </div>
                    <div class="sum-body">
                        <div class="sum-label">USD Total</div>
                        <div class="sum-value">$<?= number_format($summary['usd'], 2) ?></div>
                        <div class="sum-sub">US Dollar payments</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="sum-icon si-warning">
                        <i class="feather icon-trending-up"></i>
                    </div>
                    <div class="sum-body">
                        <div class="sum-label">AFS Total</div>
                        <div class="sum-value">&#x60B;<?= number_format($summary['afs'], 2) ?></div>
                        <div class="sum-sub">Afghan Afghani payments</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="sum-icon si-info">
                        <i class="feather icon-bar-chart-2"></i>
                    </div>
                    <div class="sum-body">
                        <div class="sum-label">Grand Total</div>
                        <div class="sum-value">$<?= number_format($summary['usd'] + $summary['afs'], 2) ?></div>
                        <div class="sum-sub">Combined value</div>
                    </div>
                </div>
            </div>

            <!-- ────────────────────────────────────────────────
                 Payments Panel
            ──────────────────────────────────────────────── -->
            <div class="panel">

                <!-- Panel Header -->
                <div class="panel-header">
                    <div class="panel-title">
                        <h5>
                            <i class="feather icon-credit-card"></i>
                            Additional Payments
                        </h5>
                        <span class="rec-badge"><?= number_format($total) ?> records</span>
                    </div>

                    <!-- Filter Form -->
                    <form class="filter-bar" method="GET" action="">
                         <select name="currency" class="filter-select" onchange="this.form.submit()">
                             <option value="">All Currencies</option>
                             <option value="USD" <?= $filter_currency === 'USD' ? 'selected' : '' ?>>USD</option>
                             <option value="AFS" <?= $filter_currency === 'AFS' ? 'selected' : '' ?>>AFS</option>
                         </select>
                         <?php if ($filter_currency !== ''): ?>
                             <a href="?" class="btn-reset">
                                 <i class="feather icon-x"></i> Reset
                             </a>
                         <?php endif; ?>
                    </form>
                </div><!-- /.panel-header -->

                <!-- Panel Body -->
                <div class="panel-body">

                    <?php if (empty($payments)): ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <div class="empty-icon-wrap">
                                <i class="feather icon-inbox"></i>
                            </div>
                            <h6>No Payments Found</h6>
                            <p>
                                <?php if ($filter_status !== '' || $filter_currency !== ''): ?>
                                    No payments match the selected filters.
                                    Try adjusting or resetting your filters.
                                <?php else: ?>
                                    You haven't made any additional payments yet.
                                <?php endif; ?>
                            </p>
                            <?php if ($filter_status === '' && $filter_currency === ''): ?>
                                <div class="empty-ok">
                                    <i class="feather icon-check-circle"></i>
                                    Account is up to date
                                </div>
                            <?php else: ?>
                                <a href="?" class="btn-reset" style="margin-top:16px; display:inline-flex;">
                                    <i class="feather icon-refresh-cw"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="payment-table">
                                <thead>
                                    <tr>
                                        <th style="width:48px;">#</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Method</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th style="width:52px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $i => $p):
                                        $rowNum   = $offset + $i + 1;
                                        $status   = strtolower(trim($p['status']   ?? 'pending'));
                                        $currency = strtoupper(trim($p['currency'] ?? 'USD'));
                                        $amount   = (float)($p['sold_amount']      ?? 0);

                                        // Currency symbol
                                        $symbol    = ($currency === 'USD') ? '$' : '&#x60B;';
                                        $amountFmt = $symbol . number_format($amount, 2);

                                        // Status CSS class
                                        if      ($status === 'completed')  $sClass = 's-completed';
                                        elseif  ($status === 'processing') $sClass = 's-processing';
                                        elseif  ($status === 'failed')     $sClass = 's-failed';
                                        else                               $sClass = 's-pending';

                                        $dt          = strtotime($p['created_at'] ?? 'now');
                                        $description = $p['description'] ?? '';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="row-num"><?= $rowNum ?></span>
                                        </td>
                                        <td>
                                            <span class="type-chip">
                                                <i class="feather icon-tag"></i>
                                                <?= htmlspecialchars(ucfirst($p['payment_type'] ?? '—')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="amount-cell">
                                                <?= $amountFmt ?>
                                                <span class="cur-tag"><?= $currency ?></span>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= $sClass ?>">
                                                <span class="dot"></span>
                                                <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="method-cell">
                                                <i class="feather icon-credit-card"></i>
                                                <?= htmlspecialchars($p['payment_method'] ?? '—') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="desc-cell"
                                                 title="<?= htmlspecialchars($description) ?>">
                                                <?= htmlspecialchars(mb_substr($description, 0, 50)) ?>
                                                <?= mb_strlen($description) > 50 ? '…' : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-main"><?= date('d M Y', $dt) ?></div>
                                            <div class="date-time"><?= date('H:i', $dt) ?></div>
                                        </td>
                                        <td style="text-align:center;">
                                            <a href="additional_payments_detail.php?id=<?= urlencode($p['id']) ?>"
                                               class="btn-view" title="View Details">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1):
                            // Preserve active filters in pagination links
                            $qp = array_filter(['status' => $filter_status, 'currency' => $filter_currency]);
                            $qs = $qp ? '&' . http_build_query($qp) : '';
                        ?>
                        <div class="pagination-wrap">
                            <div class="pagination-info">
                                Showing
                                <strong><?= number_format($offset + 1) ?>–<?= number_format(min($offset + $per_page, $total)) ?></strong>
                                of <strong><?= number_format($total) ?></strong> payments
                            </div>
                            <ul class="pagination">
                                <!-- First / Prev -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=1<?= $qs ?>">
                                        <i class="feather icon-chevrons-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $qs ?>">
                                        <i class="feather icon-chevron-left"></i>
                                    </a>
                                </li>

                                <!-- Page numbers -->
                                <?php for ($pi = max(1, $page - 2); $pi <= min($total_pages, $page + 2); $pi++): ?>
                                    <li class="page-item <?= $pi === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $pi ?><?= $qs ?>">
                                            <?= $pi ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next / Last -->
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $qs ?>">
                                        <i class="feather icon-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $total_pages ?><?= $qs ?>">
                                        <i class="feather icon-chevrons-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div><!-- /.panel-body -->
            </div><!-- /.panel -->

        </div><!-- /.ap-wrap -->
    </div><!-- /.pcoded-content -->
</div><!-- /.pcoded-main-container -->

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>