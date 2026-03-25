<?php
/**
 * Client Umrah Bookings Page
 * Displays all umrah bookings for the client
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
            COUNT(*)                                                            AS total_bookings,
            SUM(sold_price)                                                     AS total_spent,
            AVG(sold_price)                                                     AS avg_price,
            AVG(duration)                                                       AS avg_duration,
            SUM(CASE WHEN LOWER(status) = 'confirmed'  THEN 1 ELSE 0 END)     AS confirmed,
            SUM(CASE WHEN LOWER(status) = 'pending'    THEN 1 ELSE 0 END)     AS pending,
            SUM(CASE WHEN LOWER(status) = 'completed'  THEN 1 ELSE 0 END)     AS completed
        FROM umrah_bookings
        WHERE sold_to = ? AND tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_bookings' => 0,
        'total_spent'    => 0,
        'avg_price'      => 0,
        'avg_duration'   => 0,
        'confirmed'      => 0,
        'pending'        => 0,
        'completed'      => 0,
    ];
}

$total       = (int)($stats['total_bookings'] ?? 0);
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;

// Fetch umrah bookings
$umrahs = [];
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM umrah_bookings
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $umrahs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Umrah bookings fetch error: " . $e->getMessage());
    $umrahs = [];
}

// Helper: status badge class
function statusBadgeClass(string $status): string {
    return match(strtolower(trim($status))) {
        'confirmed'  => 'badge-confirmed',
        'completed'  => 'badge-completed',
        'cancelled'  => 'badge-cancelled',
        'pending'    => 'badge-pending',
        default      => 'badge-default',
    };
}

// Helper: flight date urgency
function flightUrgency(string $date): string {
    $ts   = strtotime($date);
    if (!$ts) return '';
    $days = (int)(($ts - time()) / 86400);
    if ($days < 0)   return 'past';
    if ($days === 0) return 'today';
    if ($days <= 7)  return 'soon';
    return '';
}

// Helper: duration label
function durationLabel(int $days): string {
    if ($days <= 0) return '—';
    if ($days < 7)  return $days . ' day' . ($days !== 1 ? 's' : '');
    $weeks = round($days / 7);
    return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' (' . $days . 'd)';
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Umrah Bookings</title>
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
            --success:      #28a745;
            --warning:      #f0ad4e;
            --danger:       #dc3545;
            --info:         #0ea5e9;
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
            background: linear-gradient(135deg, var(--umrah-chip), var(--umrah-gold));
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
        .sc-icon.gold   { background: var(--umrah-lt);  color: var(--umrah-chip); }
        .sc-icon.teal   { background: #e0faf5;          color: #1aaa8a; }
        .sc-icon.green  { background: #eafaf1;          color: var(--success); }
        .sc-icon.yellow { background: #fff8e1;          color: #d68910; }
        .sc-icon.blue   { background: #e8f3ff;          color: var(--primary); }
        .sc-icon.purple { background: #f3eeff;          color: #7c3aed; }

        .summary-card .sc-info .sc-value {
            font-size: 1.4rem; font-weight: 700;
            line-height: 1.2; color: var(--text);
        }
        .summary-card .sc-info .sc-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 2px; font-weight: 500;
        }

        /* ─── Spiritual Notice Banner ─────────────────────── */
        .umrah-notice {
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
        .umrah-notice i { font-size: 1.1rem; flex-shrink: 0; color: var(--umrah-chip); }

        /* ─── Main Card ───────────────────────────────────── */
        .umrahs-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .umrahs-card-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .umrahs-card-header .uch-left { display: flex; align-items: center; gap: 12px; }
        .umrahs-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .umrahs-card-header .count-chip {
            background: var(--umrah-lt); color: var(--umrah-chip);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .umrahs-table { width: 100%; border-collapse: collapse; }
        .umrahs-table thead tr { border-bottom: 2px solid var(--border); }
        .umrahs-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .umrahs-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .umrahs-table tbody tr:last-child { border-bottom: none; }
        .umrahs-table tbody tr:hover { background: var(--umrah-bg); }
        .umrahs-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* Booking ID chip */
        .booking-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: var(--umrah-lt); color: var(--umrah-chip);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* Pilgrim cell */
        .pilgrim-name       { font-weight: 700; color: var(--text); font-size: 0.92rem; }
        .pilgrim-lineage    { font-size: 0.78rem; color: var(--text-light); margin-top: 3px; }
        .lineage-sep        { margin: 0 4px; opacity: 0.4; }

        /* Flight date cell */
        .flight-cell { display: flex; flex-direction: column; gap: 4px; }
        .flight-date { font-weight: 600; font-size: 0.88rem; color: var(--text); }

        .urgency-tag {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 600;
        }
        .urgency-today  { background: #fef9c3; color: #854d0e; }
        .urgency-soon   { background: #fff4ed; color: #ea580c; }
        .urgency-past   { background: #f1f5f9; color: var(--text-light); }

        /* Duration badge */
        .duration-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: #f3eeff; color: #7c3aed;
            padding: 5px 10px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 700;
        }

        /* Price chip */
        .price-chip {
            display: inline-flex; align-items: center; gap: 4px;
            background: var(--umrah-lt); color: var(--umrah-chip);
            padding: 5px 12px; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.92rem;
        }
        .price-chip .currency { font-size: 0.75rem; font-weight: 500; opacity: 0.85; }

        /* ─── Status Badges ───────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .status-badge .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        .badge-confirmed  { background: #eafaf1; color: #1e7e34; }
        .badge-confirmed  .dot { background: var(--success); }
        .badge-completed  { background: #e0f2fe; color: #0369a1; }
        .badge-completed  .dot { background: var(--info); }
        .badge-pending    { background: #fff8e1; color: #856404; }
        .badge-pending    .dot { background: var(--warning); }
        .badge-cancelled  { background: #fdecea; color: #b02a37; }
        .badge-cancelled  .dot { background: var(--danger); }
        .badge-default    { background: #f1f3f5; color: #495057; }
        .badge-default    .dot { background: #adb5bd; }

        /* Booked date */
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
            background: linear-gradient(135deg, var(--umrah-bg), #fff4ed);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--umrah-chip);
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
        .pagination .page-item .page-link:hover { background: var(--umrah-lt); color: var(--umrah-chip); border-color: #fcd34d; }
        .pagination .page-item.active .page-link { background: var(--umrah-chip); color: #fff; border-color: var(--umrah-chip); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .umrahs-card-header { flex-direction: column; align-items: flex-start; }
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
                                <span class="header-icon"><i class="feather icon-map-pin"></i></span>
                                Umrah Bookings
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="summary-cards">

            <div class="summary-card">
                <div class="sc-icon gold"><i class="feather icon-map-pin"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format($total) ?></div>
                    <div class="sc-label">Total Bookings</div>
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
                <div class="sc-icon purple"><i class="feather icon-bar-chart-2"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['avg_price'] ?? 0), 2) ?></div>
                    <div class="sc-label">Avg. Package Price</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon green"><i class="feather icon-check-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['confirmed'] ?? 0)) ?></div>
                    <div class="sc-label">Confirmed</div>
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
                <div class="sc-icon blue"><i class="feather icon-award"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['completed'] ?? 0)) ?></div>
                    <div class="sc-label">Completed</div>
                </div>
            </div>

        </div>

        <!-- ── Spiritual Notice Banner ────────────────────── -->
        <?php if ($total > 0): ?>
        <div class="umrah-notice">
            <i class="feather icon-heart"></i>
            <span>May Allah accept your Umrah. Please ensure all pilgrim documents are verified well in advance of your flight date. Contact your agent for any updates or special requirements.</span>
        </div>
        <?php endif; ?>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="umrahs-card">

            <!-- Card Header -->
            <div class="umrahs-card-header">
                <div class="uch-left">
                    <h6><i class="feather icon-list" style="color:var(--umrah-chip);margin-right:6px;"></i>Umrah Records</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($umrahs)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-map-pin"></i></div>
                    <h6>No Umrah Bookings Found</h6>
                    <p>You have no Umrah bookings at the moment. Contact your agent to arrange your blessed journey.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="umrahs-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Booking ID</th>
                                <th>Pilgrim</th>
                                <th>Flight Date</th>
                                <th class="hide-mobile">Duration</th>
                                <th class="hide-mobile">Price</th>
                                <th>Status</th>
                                <th class="hide-mobile">Booked</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($umrahs as $index => $umrah):
                                $rowNum      = $offset + $index + 1;
                                $status      = $umrah['status'] ?? 'confirmed';
                                $badgeClass  = statusBadgeClass($status);
                                $createdAt   = strtotime($umrah['created_at']);
                                $flightDate  = $umrah['flight_date'] ?? '';
                                $urgency     = flightUrgency($flightDate);
                                $duration    = (int)($umrah['duration'] ?? 0);
                                $flightFmt   = $flightDate ? date('d M Y', strtotime($flightDate)) : '—';

                                // Pilgrim lineage
                                $name   = trim($umrah['name']   ?? '');
                                $fname  = trim($umrah['fname']  ?? '');
                                $gfname = trim($umrah['gfname'] ?? '');
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- Booking ID -->
                                <td>
                                    <span class="booking-chip"><?= htmlspecialchars($umrah['booking_id'] ?? '—') ?></span>
                                </td>

                                <!-- Pilgrim Name + Lineage -->
                                <td>
                                    <div class="pilgrim-name"><?= htmlspecialchars($name ?: '—') ?></div>
                                    <?php if ($fname || $gfname): ?>
                                        <div class="pilgrim-lineage">
                                            <?php if ($fname): ?>
                                                <span><?= htmlspecialchars($fname) ?></span>
                                            <?php endif; ?>
                                            <?php if ($fname && $gfname): ?>
                                                <span class="lineage-sep">·</span>
                                            <?php endif; ?>
                                            <?php if ($gfname): ?>
                                                <span><?= htmlspecialchars($gfname) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Flight Date + Urgency -->
                                <td>
                                    <div class="flight-cell">
                                        <div class="flight-date">
                                            <i class="feather icon-send" style="font-size:0.8rem; color:var(--umrah-chip); margin-right:4px;"></i>
                                            <?= $flightFmt ?>
                                        </div>
                                        <?php if ($urgency === 'today'): ?>
                                            <span class="urgency-tag urgency-today">
                                                <i class="feather icon-alert-circle"></i> Departing Today
                                            </span>
                                        <?php elseif ($urgency === 'soon'): ?>
                                            <span class="urgency-tag urgency-soon">
                                                <i class="feather icon-clock"></i> Departing Soon
                                            </span>
                                        <?php elseif ($urgency === 'past'): ?>
                                            <span class="urgency-tag urgency-past">
                                                <i class="feather icon-archive"></i> Departed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Duration -->
                                <td class="hide-mobile">
                                    <?php if ($duration > 0): ?>
                                        <span class="duration-badge">
                                            <i class="feather icon-calendar"></i>
                                            <?= durationLabel($duration) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Price -->
                                <td class="hide-mobile">
                                    <span class="price-chip">
                                        <span class="currency">USD</span>
                                        <?= number_format((float)($umrah['sold_price'] ?? 0), 2) ?>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <span class="dot"></span>
                                        <?= htmlspecialchars(ucfirst(strtolower($status))) ?>
                                    </span>
                                </td>

                                <!-- Booked Date -->
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
                                    <a href="handlers/view_umrah.php?booking_id=<?= urlencode($umrah['booking_id'] ?? '') ?>"
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
                        Showing <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?></strong> of <strong><?= number_format($total) ?></strong> bookings
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
        <!-- end .umrahs-card -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>