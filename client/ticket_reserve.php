<?php
/**
 * Client Ticket Reservations Page
 * Displays all ticket reservations for the client
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
            COUNT(*)                                                        AS total_reservations,
            SUM(sold)                                                       AS total_amount,
            SUM(CASE WHEN status = 'reserved'  THEN 1 ELSE 0 END)         AS active,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END)         AS confirmed,
            SUM(CASE WHEN status = 'expired'   THEN 1 ELSE 0 END)         AS expired,
            SUM(CASE WHEN DATE_ADD(created_at, INTERVAL 7 DAY) < NOW()
                      AND status = 'reserved'  THEN 1 ELSE 0 END)         AS expiring_soon
        FROM ticket_reservations
        WHERE sold_to = ? AND tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_reservations' => 0,
        'total_amount'       => 0,
        'active'             => 0,
        'confirmed'          => 0,
        'expired'            => 0,
        'expiring_soon'      => 0,
    ];
}

$total       = (int)($stats['total_reservations'] ?? 0);
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;

// Fetch reservations
$reservations = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            id                                              AS reservation_id,
            pnr,
            passenger_name,
            origin,
            destination,
            airline,
            sold                                            AS amount,
            status,
            created_at,
            DATE_ADD(created_at, INTERVAL 7 DAY)           AS reservation_until
        FROM ticket_reservations
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservations = [];
}

// Helper: status badge class
function statusBadgeClass(string $status): string {
    return match(strtolower(trim($status))) {
        'confirmed' => 'badge-confirmed',
        'expired'   => 'badge-expired',
        'cancelled' => 'badge-expired',
        default     => 'badge-reserved',
    };
}

// Helper: days remaining until expiry
function daysRemaining(string $until): int {
    $now   = new DateTime();
    $exp   = new DateTime($until);
    $diff  = $now->diff($exp);
    return $exp > $now ? (int)$diff->days : -(int)$diff->days;
}

// Helper: expiry urgency class
function expiryClass(int $days): string {
    if ($days < 0)  return 'expired';
    if ($days <= 2) return 'critical';
    if ($days <= 4) return 'warning';
    return 'safe';
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Ticket Reservations</title>
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
            --resv:         #0369a1;
            --resv-bg:      #f0f9ff;
            --resv-lt:      #e0f2fe;
            --resv-chip:    #0284c7;
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
            background: linear-gradient(135deg, var(--resv-chip), var(--accent));
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
        .sc-icon.blue   { background: var(--resv-lt);  color: var(--resv-chip); }
        .sc-icon.teal   { background: #e0faf5;         color: #1aaa8a; }
        .sc-icon.green  { background: #eafaf1;         color: var(--success); }
        .sc-icon.yellow { background: #fff8e1;         color: #d68910; }
        .sc-icon.red    { background: #fdecea;         color: var(--danger); }
        .sc-icon.orange { background: #fff4ed;         color: #ea580c; }

        .summary-card .sc-info .sc-value {
            font-size: 1.45rem; font-weight: 700;
            line-height: 1.2; color: var(--text);
        }
        .summary-card .sc-info .sc-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 2px; font-weight: 500;
        }

        /* ─── Notice Banner ───────────────────────────────── */
        .resv-notice {
            display: flex; align-items: center; gap: 12px;
            background: var(--resv-bg);
            border: 1px solid var(--resv-lt);
            border-left: 4px solid var(--resv-chip);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.87rem;
            color: var(--resv);
        }
        .resv-notice i { font-size: 1.1rem; flex-shrink: 0; }

        /* ─── Main Card ───────────────────────────────────── */
        .reservations-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .reservations-card-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .reservations-card-header .rch-left { display: flex; align-items: center; gap: 12px; }
        .reservations-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .reservations-card-header .count-chip {
            background: var(--resv-lt); color: var(--resv-chip);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .reservations-table { width: 100%; border-collapse: collapse; }
        .reservations-table thead tr { border-bottom: 2px solid var(--border); }
        .reservations-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .reservations-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .reservations-table tbody tr:last-child { border-bottom: none; }
        .reservations-table tbody tr:hover { background: var(--resv-bg); }
        .reservations-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* Reservation ID chip */
        .resv-id-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.8rem;
            background: var(--resv-lt); color: var(--resv-chip);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* PNR chip */
        .pnr-chip {
            font-size: 0.76rem; font-weight: 600;
            background: #f1f5f9; color: var(--text-light);
            padding: 3px 8px; border-radius: var(--radius-sm);
            display: inline-block; margin-top: 4px;
            font-family: 'Courier New', monospace;
        }

        /* Passenger cell */
        .passenger-name  { font-weight: 600; color: var(--text); font-size: 0.9rem; }
        .passenger-route {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 3px; display: flex; align-items: center; gap: 5px;
        }
        .route-arrow { color: var(--resv-chip); font-size: 0.75rem; }

        /* Airline chip */
        .airline-chip {
            background: #f0f9ff; color: var(--resv);
            padding: 4px 10px; border-radius: var(--radius-sm);
            font-size: 0.8rem; font-weight: 600;
            display: inline-block;
        }

        /* Amount chip */
        .amount-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #e0f2fe; color: var(--resv);
            padding: 5px 12px; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.92rem;
        }
        .amount-chip .currency { font-size: 0.75rem; font-weight: 500; opacity: 0.8; }

        /* ─── Expiry Countdown ────────────────────────────── */
        .expiry-cell { display: flex; flex-direction: column; gap: 4px; }
        .expiry-date { font-size: 0.84rem; font-weight: 600; color: var(--text); }

        .countdown-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 0.74rem; font-weight: 600;
        }
        .countdown-pill i { font-size: 0.72rem; }

        .countdown-pill.safe     { background: #eafaf1; color: #1e7e34; }
        .countdown-pill.warning  { background: #fff8e1; color: #856404; }
        .countdown-pill.critical { background: #fff4ed; color: #ea580c; }
        .countdown-pill.expired  { background: #fdecea; color: #b02a37; }

        /* ─── Status Badges ───────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .status-badge .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        .badge-reserved  { background: var(--resv-lt);  color: var(--resv-chip); }
        .badge-reserved  .dot { background: var(--resv-chip); }
        .badge-confirmed { background: #eafaf1; color: #1e7e34; }
        .badge-confirmed .dot { background: var(--success); }
        .badge-expired   { background: #fdecea; color: #b02a37; }
        .badge-expired   .dot { background: var(--danger); }

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
            background: linear-gradient(135deg, var(--resv-bg), #e0faf5);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--resv-chip);
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
        .pagination .page-item .page-link:hover { background: var(--resv-lt); color: var(--resv-chip); border-color: #7dd3fc; }
        .pagination .page-item.active .page-link { background: var(--resv-chip); color: #fff; border-color: var(--resv-chip); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .reservations-card-header { flex-direction: column; align-items: flex-start; }
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
                                <span class="header-icon"><i class="feather icon-bookmark"></i></span>
                                Ticket Reservations
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="summary-cards">

            <div class="summary-card">
                <div class="sc-icon blue"><i class="feather icon-bookmark"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format($total) ?></div>
                    <div class="sc-label">Total Reservations</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon teal"><i class="feather icon-dollar-sign"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['total_amount'] ?? 0), 2) ?></div>
                    <div class="sc-label">Total Amount</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon yellow"><i class="feather icon-clock"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['active'] ?? 0)) ?></div>
                    <div class="sc-label">Active</div>
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
                <div class="sc-icon red"><i class="feather icon-x-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)($stats['expired'] ?? 0)) ?></div>
                    <div class="sc-label">Expired</div>
                </div>
            </div>

        </div>

        <!-- ── Notice Banner ──────────────────────────────── -->
        <?php if ($total > 0): ?>
        <div class="resv-notice">
            <i class="feather icon-info"></i>
            <span>Reservations are held for <strong>7 days</strong> from the booking date. Please confirm or cancel before the expiry date to avoid automatic release of your seat.</span>
        </div>
        <?php endif; ?>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="reservations-card">

            <!-- Card Header -->
            <div class="reservations-card-header">
                <div class="rch-left">
                    <h6><i class="feather icon-list" style="color:var(--resv-chip);margin-right:6px;"></i>Reservation Records</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-bookmark"></i></div>
                    <h6>No Reservations Found</h6>
                    <p>You have no ticket reservations at the moment. Contact your agent to make a new reservation.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="reservations-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reservation</th>
                                <th>Passenger & Route</th>
                                <th class="hide-mobile">Airline</th>
                                <th class="hide-mobile">Amount</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th class="hide-mobile">Booked</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $index => $res):
                                $rowNum    = $offset + $index + 1;
                                $status    = strtolower(trim($res['status'] ?? 'reserved'));
                                $badgeClass = statusBadgeClass($status);
                                $createdAt  = strtotime($res['created_at']);
                                $untilTs    = strtotime($res['reservation_until']);
                                $untilDate  = $untilTs ? date('d M Y', $untilTs) : '—';
                                $days       = $untilTs ? daysRemaining(date('Y-m-d H:i:s', $untilTs)) : -999;
                                $urgency    = expiryClass($days);
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- Reservation ID + PNR -->
                                <td>
                                    <span class="resv-id-chip">#<?= htmlspecialchars($res['reservation_id']) ?></span>
                                    <?php if (!empty($res['pnr'])): ?>
                                        <div><span class="pnr-chip"><?= htmlspecialchars($res['pnr']) ?></span></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Passenger + Route -->
                                <td>
                                    <div class="passenger-name"><?= htmlspecialchars($res['passenger_name'] ?? '—') ?></div>
                                    <div class="passenger-route">
                                        <span><?= htmlspecialchars($res['origin'] ?? '—') ?></span>
                                        <span class="route-arrow"><i class="feather icon-arrow-right"></i></span>
                                        <span><?= htmlspecialchars($res['destination'] ?? '—') ?></span>
                                    </div>
                                </td>

                                <!-- Airline -->
                                <td class="hide-mobile">
                                    <?php if (!empty($res['airline'])): ?>
                                        <span class="airline-chip"><?= htmlspecialchars($res['airline']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Amount -->
                                <td class="hide-mobile">
                                    <span class="amount-chip">
                                        <span class="currency">USD</span>
                                        <?= number_format((float)($res['amount'] ?? 0), 2) ?>
                                    </span>
                                </td>

                                <!-- Expiry countdown -->
                                <td>
                                    <div class="expiry-cell">
                                        <div class="expiry-date"><?= $untilDate ?></div>
                                        <?php if ($urgency === 'expired'): ?>
                                            <span class="countdown-pill expired">
                                                <i class="feather icon-x-circle"></i> Expired
                                            </span>
                                        <?php elseif ($urgency === 'critical'): ?>
                                            <span class="countdown-pill critical">
                                                <i class="feather icon-alert-triangle"></i>
                                                <?= $days ?> day<?= $days !== 1 ? 's' : '' ?> left
                                            </span>
                                        <?php elseif ($urgency === 'warning'): ?>
                                            <span class="countdown-pill warning">
                                                <i class="feather icon-clock"></i>
                                                <?= $days ?> days left
                                            </span>
                                        <?php else: ?>
                                            <span class="countdown-pill safe">
                                                <i class="feather icon-check"></i>
                                                <?= $days ?> days left
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <span class="dot"></span>
                                        <?= htmlspecialchars(ucfirst($status)) ?>
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
                                    <a href="ticket_reservation_detail.php?id=<?= urlencode($res['reservation_id']) ?>"
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
                        Showing <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?></strong> of <strong><?= number_format($total) ?></strong> reservations
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
        <!-- end .reservations-card -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>