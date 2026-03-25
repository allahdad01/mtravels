<?php
/**
 * Client Visa Applications Page
 * Displays all visa applications for the client
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/language_helpers.php';
require_once '../includes/session_check.php';

$tenant_id = $_SESSION['tenant_id'];
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];
$lang = init_language();

if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Fetch settings
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
}

// Pagination
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset   = ($page - 1) * $per_page;

// Fetch summary stats
try {
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*)                                                            AS total_applications,
            SUM(sold)                                                           AS total_spent,
            AVG(sold)                                                           AS avg_cost,
            SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END)       AS approved,
            SUM(CASE WHEN LOWER(status) = 'pending'  THEN 1 ELSE 0 END)       AS pending,
            SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END)       AS rejected,
            SUM(CASE WHEN LOWER(status) = 'issued'   THEN 1 ELSE 0 END)       AS issued
        FROM visa_applications
        WHERE sold_to = ? AND tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_applications' => 0,
        'total_spent'        => 0,
        'avg_cost'           => 0,
        'approved'           => 0,
        'pending'            => 0,
        'rejected'           => 0,
        'issued'             => 0,
    ];
}

$total       = (int)($stats['total_applications'] ?? 0);
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;

// Fetch visa applications
$visas = [];
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM visa_applications
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $visas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Visas fetch error: " . $e->getMessage());
    $visas = [];
}

// Helper: status badge class
function statusBadgeClass(string $status): string {
    return match(strtolower(trim($status))) {
        'approved'   => 'badge-approved',
        'issued'     => 'badge-issued',
        'pending'    => 'badge-pending',
        'rejected'   => 'badge-rejected',
        'processing' => 'badge-processing',
        default      => 'badge-default',
    };
}

// Helper: visa type icon
function visaTypeIcon(string $type): string {
    $type = strtolower(trim($type));
    if (str_contains($type, 'tourist'))   return 'icon-sun';
    if (str_contains($type, 'business'))  return 'icon-briefcase';
    if (str_contains($type, 'student'))   return 'icon-book';
    if (str_contains($type, 'transit'))   return 'icon-repeat';
    if (str_contains($type, 'work'))      return 'icon-tool';
    if (str_contains($type, 'umrah'))     return 'icon-map-pin';
    if (str_contains($type, 'hajj'))      return 'icon-map-pin';
    return 'icon-credit-card';
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Visa Applications</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* ─── Root Variables ──────────────────────────────── */
        :root {
            --primary:      #4099ff;
            --accent:       #2ed8b6;
            --visa:         #6366f1;
            --visa-dk:      #4338ca;
            --visa-bg:      #f5f3ff;
            --visa-lt:      #ede9fe;
            --visa-chip:    #7c3aed;
            --success:      #28a745;
            --warning:      #f0ad4e;
            --danger:       #dc3545;
            --info:         #0ea5e9;
            --teal:         #2ed8b6;
            --bg-page:      #f4f7fa;
            --bg-card:      #ffffff;
            --border:       #e9ecef;
            --text:         #2d3748;
            --text-light:   #718096;
            --radius-lg:    14px;
            --radius-md:    10px;
            --radius-sm:    6px;
            --shadow-sm:    0 2px 8px rgba(0,0,0,0.06);
            --shadow-md:    0 4px 20px rgba(0,0,0,0.09);
            --transition:   all 0.22s ease;
        }

        body { background: var(--bg-page); color: var(--text); }

        /* ─── Page Header ─────────────────────────────────── */
        .page-header-title h5 {
            font-size: 1.25rem; font-weight: 700;
            color: var(--text);
            display: flex; align-items: center; gap: 8px;
        }
        .page-header-title h5 .header-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--visa), var(--visa-chip));
            border-radius: var(--radius-sm);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem;
        }

        /* ─── Summary Cards ───────────────────────────────── */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .summary-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }

        .summary-card .sc-icon {
            width: 48px; height: 48px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .sc-icon.indigo { background: var(--visa-lt);  color: var(--visa-chip); }
        .sc-icon.teal   { background: #e0faf5;         color: #1aaa8a; }
        .sc-icon.green  { background: #eafaf1;         color: var(--success); }
        .sc-icon.yellow { background: #fff8e1;         color: #d68910; }
        .sc-icon.red    { background: #fdecea;         color: var(--danger); }
        .sc-icon.blue   { background: #e0f2fe;         color: #0369a1; }
        .sc-icon.purple { background: #f3eeff;         color: #7c3aed; }

        .summary-card .sc-info .sc-value {
            font-size: 1.4rem; font-weight: 700;
            line-height: 1.2; color: var(--text);
        }
        .summary-card .sc-info .sc-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 2px; font-weight: 500;
        }

        /* ─── Status Pipeline Bar ─────────────────────────── */
        .pipeline-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            padding: 20px 24px;
            margin-bottom: 22px;
        }
        .pipeline-title {
            font-size: 0.8rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light); margin-bottom: 14px;
        }
        .pipeline-bar {
            display: flex; height: 10px;
            border-radius: 20px; overflow: hidden;
            background: #f1f3f5;
            gap: 2px;
        }
        .pipeline-bar .seg {
            height: 100%; border-radius: 0;
            transition: var(--transition);
        }
        .pipeline-bar .seg:first-child { border-radius: 20px 0 0 20px; }
        .pipeline-bar .seg:last-child  { border-radius: 0 20px 20px 0; }
        .pipeline-bar .seg.approved    { background: var(--success); }
        .pipeline-bar .seg.issued      { background: var(--info); }
        .pipeline-bar .seg.pending     { background: var(--warning); }
        .pipeline-bar .seg.rejected    { background: var(--danger); }

        .pipeline-legend {
            display: flex; flex-wrap: wrap; gap: 16px;
            margin-top: 12px;
        }
        .legend-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.8rem; color: var(--text-light);
        }
        .legend-dot {
            width: 10px; height: 10px;
            border-radius: 50%; flex-shrink: 0;
        }
        .legend-dot.approved { background: var(--success); }
        .legend-dot.issued   { background: var(--info); }
        .legend-dot.pending  { background: var(--warning); }
        .legend-dot.rejected { background: var(--danger); }
        .legend-item strong  { color: var(--text); }

        /* ─── Notice Banner ───────────────────────────────── */
        .visa-notice {
            display: flex; align-items: center; gap: 12px;
            background: var(--visa-bg);
            border: 1px solid var(--visa-lt);
            border-left: 4px solid var(--visa-chip);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.87rem;
            color: var(--visa-dk);
        }
        .visa-notice i { font-size: 1.1rem; flex-shrink: 0; color: var(--visa-chip); }

        /* ─── Main Card ───────────────────────────────────── */
        .visas-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .visas-card-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .visas-card-header .vch-left { display: flex; align-items: center; gap: 12px; }
        .visas-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .visas-card-header .count-chip {
            background: var(--visa-lt); color: var(--visa-chip);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .visas-table { width: 100%; border-collapse: collapse; }
        .visas-table thead tr { border-bottom: 2px solid var(--border); }
        .visas-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .visas-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .visas-table tbody tr:last-child { border-bottom: none; }
        .visas-table tbody tr:hover { background: var(--visa-bg); }
        .visas-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* Application ID chip */
        .app-id-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: var(--visa-lt); color: var(--visa-chip);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* Applicant cell */
        .applicant-name { font-weight: 600; color: var(--text); font-size: 0.9rem; }

        /* Visa type cell */
        .visa-type-cell {
            display: flex; align-items: center; gap: 8px;
        }
        .visa-type-icon {
            width: 30px; height: 30px;
            border-radius: var(--radius-sm);
            background: var(--visa-lt); color: var(--visa-chip);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; flex-shrink: 0;
        }
        .visa-type-label { font-size: 0.88rem; font-weight: 600; color: var(--text); }

        /* Country chip */
        .country-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #e0f2fe; color: #0369a1;
            padding: 4px 10px; border-radius: var(--radius-sm);
            font-size: 0.82rem; font-weight: 600;
        }
        .country-chip i { font-size: 0.78rem; }

        /* Cost chip */
        .cost-chip {
            display: inline-flex; align-items: center; gap: 4px;
            background: #eafaf1; color: #1e7e34;
            padding: 5px 12px; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.92rem;
        }
        .cost-chip .currency { font-size: 0.75rem; font-weight: 500; opacity: 0.85; }

        /* ─── Status Badges ───────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .status-badge .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        .badge-approved   { background: #eafaf1; color: #1e7e34; }
        .badge-approved   .dot { background: var(--success); }
        .badge-issued     { background: #e0f2fe; color: #0369a1; }
        .badge-issued     .dot { background: var(--info); }
        .badge-pending    { background: #fff8e1; color: #856404; }
        .badge-pending    .dot { background: var(--warning); }
        .badge-rejected   { background: #fdecea; color: #b02a37; }
        .badge-rejected   .dot { background: var(--danger); }
        .badge-processing { background: var(--visa-lt); color: var(--visa-chip); }
        .badge-processing .dot { background: var(--visa-chip); }
        .badge-default    { background: #f1f3f5; color: #495057; }
        .badge-default    .dot { background: #adb5bd; }

        /* Date */
        .date-main { font-weight: 500; font-size: 0.88rem; }
        .date-time { font-size: 0.76rem; color: var(--text-light); margin-top: 2px; }

        /* Action Button */
        .btn-view {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #fff; color: var(--primary);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.95rem; cursor: pointer;
            transition: var(--transition); text-decoration: none;
        }
        .btn-view:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: scale(1.07); }

        /* ─── Empty State ─────────────────────────────────── */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .es-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--visa-bg), #e0f2fe);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--visa-chip);
        }
        .empty-state h6 { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .empty-state p  { font-size: 0.88rem; color: var(--text-light); max-width: 320px; margin: 0 auto; }

        /* ─── Pagination ──────────────────────────────────── */
        .pagination-wrap {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 18px 24px;
            border-top: 1px solid var(--border);
        }
        .pagination-info { font-size: 0.82rem; color: var(--text-light); }
        .pagination-info strong { color: var(--text); }

        .pagination { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; }
        .pagination .page-item .page-link {
            display: flex; align-items: center; justify-content: center;
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #fff; color: var(--text);
            font-size: 0.85rem; font-weight: 500;
            text-decoration: none; transition: var(--transition);
        }
        .pagination .page-item .page-link:hover { background: var(--visa-lt); color: var(--visa-chip); border-color: #c4b5fd; }
        .pagination .page-item.active .page-link { background: var(--visa-chip); color: #fff; border-color: var(--visa-chip); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .pipeline-legend { gap: 10px; }
            .visas-card-header { flex-direction: column; align-items: flex-start; }
            .pagination-wrap { justify-content: center; }
            .pagination-info { width: 100%; text-align: center; }
        }
        @media (max-width: 480px) {
            .summary-cards { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="pcoded-main-container">
    <div class="pcoded-content">

        <!-- ── Page Header ────────────────────────────────── -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5>
                                <span class="header-icon"><i class="feather icon-globe"></i></span>
                                Visa Applications
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="summary-cards">

            <div class="summary-card">
                <div class="sc-icon indigo"><i class="feather icon-globe"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format($total) ?></div>
                    <div class="sc-label">Total Applications</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon teal"><i class="feather icon-dollar-sign"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['total_spent'] ?? 0), 2) ?></div>
                    <div class="sc-label">Total Spent</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon green"><i class="feather icon-check-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['approved'] ?? 0)) ?></div>
                    <div class="sc-label">Approved</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon blue"><i class="feather icon-credit-card"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['issued'] ?? 0)) ?></div>
                    <div class="sc-label">Issued</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon yellow"><i class="feather icon-clock"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['pending'] ?? 0)) ?></div>
                    <div class="sc-label">Pending</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon red"><i class="feather icon-x-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['rejected'] ?? 0)) ?></div>
                    <div class="sc-label">Rejected</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon purple"><i class="feather icon-bar-chart-2"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['avg_cost'] ?? 0), 2) ?></div>
                    <div class="sc-label">Avg. Cost</div>
                </div>
            </div>

        </div>

        <!-- ── Status Pipeline Bar ────────────────────────── -->
        <?php if ($total > 0):
            $approved = (int)($stats['approved'] ?? 0);
            $issued   = (int)($stats['issued']   ?? 0);
            $pending  = (int)($stats['pending']  ?? 0);
            $rejected = (int)($stats['rejected'] ?? 0);
            $safeTotal = max($total, 1);
        ?>
        <div class="pipeline-card">
            <div class="pipeline-title">Application Status Distribution</div>
            <div class="pipeline-bar">
                <?php if ($approved > 0): ?>
                    <div class="seg approved" style="width:<?= round(($approved / $safeTotal) * 100) ?>%;" title="Approved: <?= $approved ?>"></div>
                <?php endif; ?>
                <?php if ($issued > 0): ?>
                    <div class="seg issued" style="width:<?= round(($issued / $safeTotal) * 100) ?>%;" title="Issued: <?= $issued ?>"></div>
                <?php endif; ?>
                <?php if ($pending > 0): ?>
                    <div class="seg pending" style="width:<?= round(($pending / $safeTotal) * 100) ?>%;" title="Pending: <?= $pending ?>"></div>
                <?php endif; ?>
                <?php if ($rejected > 0): ?>
                    <div class="seg rejected" style="width:<?= round(($rejected / $safeTotal) * 100) ?>%;" title="Rejected: <?= $rejected ?>"></div>
                <?php endif; ?>
            </div>
            <div class="pipeline-legend">
                <div class="legend-item">
                    <span class="legend-dot approved"></span>
                    Approved <strong><?= $approved ?></strong>
                </div>
                <div class="legend-item">
                    <span class="legend-dot issued"></span>
                    Issued <strong><?= $issued ?></strong>
                </div>
                <div class="legend-item">
                    <span class="legend-dot pending"></span>
                    Pending <strong><?= $pending ?></strong>
                </div>
                <div class="legend-item">
                    <span class="legend-dot rejected"></span>
                    Rejected <strong><?= $rejected ?></strong>
                </div>
            </div>
        </div>

        <!-- ── Notice Banner ──────────────────────────────── -->
        <div class="visa-notice">
            <i class="feather icon-info"></i>
            <span>Visa processing times vary by destination country and application type. Ensure all submitted documents are valid. Contact your agent immediately if your application status changes unexpectedly.</span>
        </div>
        <?php endif; ?>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="visas-card">

            <!-- Card Header -->
            <div class="visas-card-header">
                <div class="vch-left">
                    <h6><i class="feather icon-list" style="color:var(--visa-chip);margin-right:6px;"></i>Application Records</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($visas)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-globe"></i></div>
                    <h6>No Visa Applications Found</h6>
                    <p>You have no visa applications on record. Contact your agent to initiate a new visa application.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="visas-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>App. ID</th>
                                <th>Applicant</th>
                                <th>Visa Type</th>
                                <th class="hide-mobile">Destination</th>
                                <th class="hide-mobile">Cost</th>
                                <th>Status</th>
                                <th class="hide-mobile">Applied</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visas as $index => $visa):
                                $rowNum    = $offset + $index + 1;
                                $status    = $visa['status'] ?? 'pending';
                                $badgeClass = statusBadgeClass($status);
                                $createdAt  = strtotime($visa['created_at']);
                                $visaType   = $visa['visa_type'] ?? '';
                                $typeIcon   = visaTypeIcon($visaType);
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- Application ID -->
                                <td>
                                    <span class="app-id-chip">#<?= htmlspecialchars((string)($visa['id'] ?? '—')) ?></span>
                                </td>

                                <!-- Applicant -->
                                <td>
                                    <div class="applicant-name"><?= htmlspecialchars($visa['applicant_name'] ?? '—') ?></div>
                                </td>

                                <!-- Visa Type -->
                                <td>
                                    <div class="visa-type-cell">
                                        <div class="visa-type-icon">
                                            <i class="feather <?= $typeIcon ?>"></i>
                                        </div>
                                        <span class="visa-type-label"><?= htmlspecialchars($visaType ?: '—') ?></span>
                                    </div>
                                </td>

                                <!-- Destination -->
                                <td class="hide-mobile">
                                    <?php if (!empty($visa['country'])): ?>
                                        <span class="country-chip">
                                            <i class="feather icon-map-pin"></i>
                                            <?= htmlspecialchars($visa['country']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Cost -->
                                <td class="hide-mobile">
                                    <span class="cost-chip">
                                        <span class="currency">USD</span>
                                        <?= number_format((float)($visa['sold'] ?? 0), 2) ?>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <span class="dot"></span>
                                        <?= htmlspecialchars(ucfirst(strtolower($status))) ?>
                                    </span>
                                </td>

                                <!-- Applied Date -->
                                <td class="hide-mobile">
                                    <?php if ($createdAt): ?>
                                        <div class="date-main"><?= date('d M Y', $createdAt) ?></div>
                                        <div class="date-time"><?= date('h:i A', $createdAt) ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td style="text-align:center;">
                                    <a href="visa_details.php?booking_id=<?= urlencode($visa['id']) ?>"
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
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="pagination-info">
                        Showing <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?></strong> of <strong><?= number_format($total) ?></strong> applications
                    </div>

                    <ul class="pagination">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=1" title="First"><i class="feather icon-chevrons-left"></i></a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" title="Previous"><i class="feather icon-chevron-left"></i></a>
                        </li>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" title="Next"><i class="feather icon-chevron-right"></i></a>
                        </li>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $total_pages ?>" title="Last"><i class="feather icon-chevrons-right"></i></a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <!-- end .visas-card -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>