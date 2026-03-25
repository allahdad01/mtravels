<?php
/**
 * Client Umrah Refunds Page
 * Displays all umrah refunds for the client
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

// State flags
$table_exists = true;
$total        = 0;
$total_pages  = 1;
$refunds      = [];
$stats        = [
    'total_refunds'    => 0,
    'total_refunded'   => 0,
    'avg_refund'       => 0,
    'largest_refund'   => 0,
    'completed_count'  => 0,
    'pending_count'    => 0,
];

// Fetch summary stats
try {
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(ur.id)                                                        AS total_refunds,
            SUM(ur.refund_amount)                                               AS total_refunded,
            AVG(ur.refund_amount)                                               AS avg_refund,
            MAX(ur.refund_amount)                                               AS largest_refund,
            SUM(CASE WHEN ur.processed = 1 THEN 1 ELSE 0 END)                  AS completed_count,
            SUM(CASE WHEN ur.processed = 0 THEN 1 ELSE 0 END)                  AS pending_count
        FROM umrah_refunds ur
        JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
        WHERE ub.sold_to = ? AND ur.tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $stats;
    $total = (int)($stats['total_refunds'] ?? 0);
    $total_pages = $total > 0 ? ceil($total / $per_page) : 1;
} catch (PDOException $e) {
    $table_exists = false;
}

// Fetch refunds
if ($table_exists) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                ur.id,
                ur.booking_id,
                ur.refund_amount,
                ur.reason,
                ur.processed,
                ur.refund_type,
                ur.created_at,
                CONCAT(ub.name, ' b. ', ub.fname) AS passenger_name,
                ub.flight_date
            FROM umrah_refunds ur
            JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
            WHERE ub.sold_to = ? AND ur.tenant_id = ?
            ORDER BY ur.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
        $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Umrah refunds fetch error: " . $e->getMessage());
        $table_exists = false;
        $refunds = [];
    }
}

// Helper: status badge class
function statusBadgeClass(string $status): string {
    return match(strtolower(trim($status))) {
        'completed', 'approved', 'processed' => 'badge-completed',
        'pending'                             => 'badge-pending',
        'rejected', 'cancelled'              => 'badge-rejected',
        default                              => 'badge-default',
    };
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Umrah Refunds</title>
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
            --umrah:        #b45309;
            --umrah-dk:     #92400e;
            --umrah-bg:     #fffbeb;
            --umrah-lt:     #fef3c7;
            --umrah-chip:   #d97706;
            --umrah-gold:   #f59e0b;
            --refund:       #ef4444;
            --refund-bg:    #fef2f2;
            --refund-lt:    #fee2e2;
            --success:      #28a745;
            --warning:      #f0ad4e;
            --danger:       #dc3545;
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
            background: linear-gradient(135deg, var(--refund), var(--umrah-chip));
            border-radius: var(--radius-sm);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem;
        }

        /* ─── Summary Cards ───────────────────────────────── */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        .sc-icon.red    { background: var(--refund-lt);  color: var(--refund); }
        .sc-icon.gold   { background: var(--umrah-lt);   color: var(--umrah-chip); }
        .sc-icon.green  { background: #eafaf1;           color: var(--success); }
        .sc-icon.yellow { background: #fff8e1;           color: #d68910; }
        .sc-icon.purple { background: #f3eeff;           color: #7c3aed; }
        .sc-icon.teal   { background: #e0faf5;           color: #1aaa8a; }

        .summary-card .sc-info .sc-value {
            font-size: 1.4rem; font-weight: 700;
            line-height: 1.2; color: var(--text);
        }
        .summary-card .sc-info .sc-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 2px; font-weight: 500;
        }

        /* ─── Split Card ──────────────────────────────────── */
        .split-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            padding: 20px 22px;
            margin-bottom: 22px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            overflow: hidden;
        }
        .split-card .split-side {
            display: flex; align-items: center; gap: 14px;
            padding: 4px 0;
        }
        .split-card .split-side:first-child {
            border-right: 1px solid var(--border);
            padding-right: 24px;
        }
        .split-card .split-side:last-child { padding-left: 24px; }

        .split-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .split-icon.completed { background: #eafaf1; color: var(--success); }
        .split-icon.pending   { background: #fff8e1; color: #d68910; }

        .split-info .split-value { font-size: 1.3rem; font-weight: 700; color: var(--text); }
        .split-info .split-label { font-size: 0.78rem; color: var(--text-light); font-weight: 500; margin-top: 2px; }
        .split-info .split-sub   { font-size: 0.76rem; color: var(--text-light); margin-top: 1px; }

        /* ─── Notice Banner ───────────────────────────────── */
        .umrah-refund-notice {
            display: flex; align-items: center; gap: 12px;
            background: var(--umrah-bg);
            border: 1px solid var(--umrah-lt);
            border-left: 4px solid var(--umrah-gold);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.87rem;
            color: var(--umrah-dk);
        }
        .umrah-refund-notice i { font-size: 1.1rem; flex-shrink: 0; color: var(--umrah-chip); }

        /* ─── Unavailable State ───────────────────────────── */
        .unavailable-state {
            text-align: center; padding: 60px 20px;
        }
        .unavailable-state .ua-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--umrah-bg), var(--refund-bg));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--umrah-chip);
        }
        .unavailable-state h6 { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .unavailable-state p  { font-size: 0.88rem; color: var(--text-light); max-width: 320px; margin: 0 auto; }

        /* ─── Main Card ───────────────────────────────────── */
        .refunds-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .refunds-card-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .refunds-card-header .rch-left { display: flex; align-items: center; gap: 12px; }
        .refunds-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .refunds-card-header .count-chip {
            background: var(--refund-lt); color: var(--refund);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .refunds-table { width: 100%; border-collapse: collapse; }
        .refunds-table thead tr { border-bottom: 2px solid var(--border); }
        .refunds-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .refunds-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .refunds-table tbody tr:last-child { border-bottom: none; }
        .refunds-table tbody tr:hover { background: var(--umrah-bg); }
        .refunds-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* Booking ID chip */
        .booking-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: var(--umrah-lt); color: var(--umrah-chip);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* Pilgrim cell */
        .pilgrim-name    { font-weight: 600; color: var(--text); font-size: 0.9rem; }
        .pilgrim-flight  { font-size: 0.78rem; color: var(--text-light); margin-top: 3px;
                           display: flex; align-items: center; gap: 5px; }

        /* Refund amount */
        .refund-amount {
            display: inline-flex; align-items: baseline; gap: 4px;
            color: var(--refund); font-weight: 700; font-size: 1rem;
        }
        .refund-amount .sym { font-size: 0.8rem; font-weight: 600; opacity: 0.85; }

        /* Reason text */
        .reason-text {
            font-size: 0.82rem; color: var(--text-light);
            background: #f8f9fa;
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block;
            max-width: 200px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ─── Status Badges ───────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .status-badge .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        .badge-completed { background: #eafaf1; color: #1e7e34; }
        .badge-completed .dot { background: var(--success); }
        .badge-pending   { background: #fff8e1; color: #856404; }
        .badge-pending   .dot { background: var(--warning); }
        .badge-rejected  { background: #fdecea; color: #b02a37; }
        .badge-rejected  .dot { background: var(--danger); }
        .badge-default   { background: #f1f3f5; color: #495057; }
        .badge-default   .dot { background: #adb5bd; }

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
            background: linear-gradient(135deg, var(--umrah-bg), var(--refund-bg));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--umrah-chip);
        }
        .empty-state h6 { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .empty-state p  { font-size: 0.88rem; color: var(--text-light); max-width: 320px; margin: 0 auto; }
        .empty-state .es-good {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 16px; font-size: 0.84rem;
            background: #eafaf1; color: #1e7e34;
            padding: 6px 16px; border-radius: 20px; font-weight: 600;
        }

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
        .pagination .page-item .page-link:hover { background: var(--umrah-lt); color: var(--umrah-chip); border-color: #fcd34d; }
        .pagination .page-item.active .page-link { background: var(--umrah-chip); color: #fff; border-color: var(--umrah-chip); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .split-card { grid-template-columns: 1fr; gap: 16px; }
            .split-card .split-side:first-child {
                border-right: none; border-bottom: 1px solid var(--border);
                padding-right: 0; padding-bottom: 16px;
            }
            .split-card .split-side:last-child { padding-left: 0; }
            .refunds-card-header { flex-direction: column; align-items: flex-start; }
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
                                <span class="header-icon"><i class="feather icon-rotate-ccw"></i></span>
                                Umrah Refunds
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$table_exists): ?>
        <!-- ── Feature Unavailable ────────────────────────── -->
        <div class="refunds-card">
            <div class="unavailable-state">
                <div class="ua-icon"><i class="feather icon-tool"></i></div>
                <h6>Feature Not Yet Available</h6>
                <p>The Umrah Refunds module is not currently configured. Please contact your agent for refund-related enquiries.</p>
            </div>
        </div>

        <?php else: ?>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="summary-cards">

            <div class="summary-card">
                <div class="sc-icon red"><i class="feather icon-rotate-ccw"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format($total) ?></div>
                    <div class="sc-label">Total Refunds</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon gold"><i class="feather icon-dollar-sign"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['total_refunded'] ?? 0), 2) ?></div>
                    <div class="sc-label">Total Refunded</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon green"><i class="feather icon-check-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['completed_count'] ?? 0)) ?></div>
                    <div class="sc-label">Completed</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon yellow"><i class="feather icon-clock"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['pending_count'] ?? 0)) ?></div>
                    <div class="sc-label">Pending</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon purple"><i class="feather icon-bar-chart-2"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['avg_refund'] ?? 0), 2) ?></div>
                    <div class="sc-label">Average Refund</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon teal"><i class="feather icon-arrow-up-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['largest_refund'] ?? 0), 2) ?></div>
                    <div class="sc-label">Largest Refund</div>
                </div>
            </div>

        </div>

        <!-- ── Completed vs Pending Split Card ────────────── -->
        <?php if ($total > 0): ?>
        <div class="split-card">
            <div class="split-side">
                <div class="split-icon completed"><i class="feather icon-check-circle"></i></div>
                <div class="split-info">
                    <div class="split-value"><?= number_format((int)($stats['completed_count'] ?? 0)) ?></div>
                    <div class="split-label">Completed Refunds</div>
                    <div class="split-sub">Successfully returned to client</div>
                </div>
            </div>
            <div class="split-side">
                <div class="split-icon pending"><i class="feather icon-clock"></i></div>
                <div class="split-info">
                    <div class="split-value"><?= number_format((int)($stats['pending_count'] ?? 0)) ?></div>
                    <div class="split-label">Pending Refunds</div>
                    <div class="split-sub">Awaiting processing or approval</div>
                </div>
            </div>
        </div>

        <!-- ── Notice Banner ──────────────────────────────── -->
        <div class="umrah-refund-notice">
            <i class="feather icon-heart"></i>
            <span>Umrah refunds are subject to the package cancellation policy agreed upon at the time of booking. Processing may take 7–14 business days. Please contact your agent for any questions regarding your refund status.</span>
        </div>
        <?php endif; ?>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="refunds-card">

            <!-- Card Header -->
            <div class="refunds-card-header">
                <div class="rch-left">
                    <h6><i class="feather icon-list" style="color:var(--refund);margin-right:6px;"></i>Refund Records</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($refunds)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-rotate-ccw"></i></div>
                    <h6>No Umrah Refunds Found</h6>
                    <p>You have no Umrah refund records. May your pilgrimage journey remain complete and uninterrupted.</p>
                    <div class="es-good">
                        <i class="feather icon-check-circle"></i> All Umrah bookings intact
                    </div>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="refunds-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Booking ID</th>
                                <th>Pilgrim</th>
                                <th>Refund Amount</th>
                                <th>Status</th>
                                <th class="hide-mobile">Reason</th>
                                <th class="hide-mobile">Date</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refunds as $index => $refund):
                                $rowNum    = $offset + $index + 1;
                                $status    = ($refund['processed'] ?? 0) ? 'completed' : 'pending';
                                $badgeClass = statusBadgeClass($status);
                                $createdAt  = strtotime($refund['created_at']);
                                $reason     = trim($refund['reason'] ?? '');
                                $flightDate = $refund['flight_date'] ?? '';
                                $flightFmt  = $flightDate ? date('d M Y', strtotime($flightDate)) : '';
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- Booking ID -->
                                <td>
                                    <span class="booking-chip"><?= htmlspecialchars($refund['booking_id'] ?? '—') ?></span>
                                </td>

                                <!-- Pilgrim + flight date -->
                                <td>
                                    <div class="pilgrim-name"><?= htmlspecialchars($refund['passenger_name'] ?? '—') ?></div>
                                    <?php if ($flightFmt): ?>
                                        <div class="pilgrim-flight">
                                            <i class="feather icon-send" style="font-size:0.75rem; color:var(--umrah-chip);"></i>
                                            <span><?= $flightFmt ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Refund Amount -->
                                <td>
                                    <span class="refund-amount">
                                        <span class="sym">$</span>
                                        <?= number_format((float)($refund['refund_amount'] ?? 0), 2) ?>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <span class="dot"></span>
                                        <?= htmlspecialchars(ucfirst(strtolower($status))) ?>
                                    </span>
                                </td>

                                <!-- Reason -->
                                <td class="hide-mobile">
                                    <?php if (!empty($reason)): ?>
                                        <span class="reason-text" title="<?= htmlspecialchars($reason) ?>">
                                            <?= htmlspecialchars($reason) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light); font-size:0.82rem;">No reason provided</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date -->
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
                                    <a href="umrah_refund_detail.php?id=<?= urlencode($refund['id']) ?>"
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
                        Showing <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?></strong> of <strong><?= number_format($total) ?></strong> refunds
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
        <!-- end .refunds-card -->

        <?php endif; // end table_exists ?>

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>