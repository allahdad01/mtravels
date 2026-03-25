<?php
/**
 * Client Date Changed Tickets Page
 * Displays all tickets with date changes for the client
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
            COUNT(*)                           AS total_changes,
            SUM(dct.service_penalty)           AS total_fees,
            MAX(dct.service_penalty)           AS largest_fee,
            MIN(dct.service_penalty)           AS smallest_fee,
            AVG(dct.service_penalty)           AS avg_fee
        FROM date_change_tickets dct
        JOIN ticket_bookings tb ON dct.ticket_id = tb.id
        WHERE tb.sold_to = ? AND tb.tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_changes'  => 0,
        'total_fees'     => 0,
        'largest_fee'    => 0,
        'smallest_fee'   => 0,
        'avg_fee'        => 0,
    ];
}

$total       = (int)($stats['total_changes'] ?? 0);
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;

// Fetch date changed tickets
$changes = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            dct.ticket_id,
            tb.id,
            tb.pnr              AS original_pnr,
            tb.passenger_name,
            tb.origin,
            tb.destination,
            tb.airline,
            tb.departure_date   AS original_date,
            dct.departure_date  AS new_date,
            dct.service_penalty AS change_fee,
            dct.status,
            dct.created_at
        FROM date_change_tickets dct
        JOIN ticket_bookings tb ON dct.ticket_id = tb.id
        WHERE tb.sold_to = ? AND tb.tenant_id = ?
        ORDER BY dct.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ticket date changes fetch error: " . $e->getMessage());
    $changes = [];
}

// Helper: safe date format
function safeDate(string $value, string $format = 'd M Y'): string {
    if (empty($value) || $value === '0000-00-00') return '—';
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '—';
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Date Changed Tickets</title>
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
            --change:       #7c3aed;
            --change-dk:    #5b21b6;
            --change-bg:    #f5f3ff;
            --change-lt:    #ede9fe;
            --orig:         #0369a1;
            --orig-bg:      #e0f2fe;
            --new-clr:      #7c3aed;
            --new-bg:       #ede9fe;
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
            background: linear-gradient(135deg, var(--change), #a855f7);
            border-radius: var(--radius-sm);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem;
        }

        /* ─── Summary Cards ───────────────────────────────── */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
        .sc-icon.purple { background: var(--change-lt); color: var(--change); }
        .sc-icon.blue   { background: #e8f3ff;          color: var(--primary); }
        .sc-icon.teal   { background: #e0faf5;          color: #1aaa8a; }
        .sc-icon.green  { background: #eafaf1;          color: var(--success); }
        .sc-icon.orange { background: #fff4ed;          color: #ea580c; }

        .summary-card .sc-info .sc-value {
            font-size: 1.45rem; font-weight: 700;
            line-height: 1.2; color: var(--text);
        }
        .summary-card .sc-info .sc-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 2px; font-weight: 500;
        }

        /* ─── Notice Banner ───────────────────────────────── */
        .change-notice {
            display: flex; align-items: center; gap: 12px;
            background: var(--change-bg);
            border: 1px solid var(--change-lt);
            border-left: 4px solid var(--change);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 22px;
            font-size: 0.87rem;
            color: var(--change-dk);
        }
        .change-notice i { font-size: 1.1rem; flex-shrink: 0; }

        /* ─── Main Card ───────────────────────────────────── */
        .changes-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .changes-card-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .changes-card-header .cch-left { display: flex; align-items: center; gap: 12px; }
        .changes-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .changes-card-header .count-chip {
            background: var(--change-lt); color: var(--change);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .changes-table { width: 100%; border-collapse: collapse; }
        .changes-table thead tr { border-bottom: 2px solid var(--border); }
        .changes-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .changes-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .changes-table tbody tr:last-child { border-bottom: none; }
        .changes-table tbody tr:hover { background: var(--change-bg); }
        .changes-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* PNR chip */
        .pnr-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: var(--change-lt); color: var(--change);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* Passenger cell */
        .passenger-name  { font-weight: 600; color: var(--text); font-size: 0.9rem; }
        .passenger-route {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 3px; display: flex; align-items: center; gap: 5px;
        }
        .route-arrow { color: var(--change); font-size: 0.75rem; }

        /* Airline chip */
        .airline-chip {
            background: #e0f2fe; color: var(--orig);
            padding: 4px 10px; border-radius: var(--radius-sm);
            font-size: 0.8rem; font-weight: 600;
            display: inline-block;
        }

        /* Date change display */
        .date-change-cell { display: flex; flex-direction: column; gap: 6px; }

        .date-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 10px; border-radius: var(--radius-sm);
            font-size: 0.8rem; font-weight: 600;
            white-space: nowrap;
        }
        .date-pill i { font-size: 0.8rem; }
        .date-pill.original { background: var(--orig-bg); color: var(--orig); }
        .date-pill.new-date  { background: var(--new-bg);  color: var(--new-clr); }

        .date-change-arrow {
            display: flex; align-items: center;
            font-size: 0.7rem; color: var(--text-light);
            gap: 4px; padding-left: 4px;
        }

        /* Change fee */
        .fee-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fff4ed; color: #ea580c;
            padding: 5px 12px; border-radius: var(--radius-sm);
            font-weight: 700; font-size: 0.9rem;
        }
        .fee-chip .currency { font-size: 0.75rem; font-weight: 500; opacity: 0.8; }
        .fee-free {
            display: inline-flex; align-items: center; gap: 5px;
            background: #eafaf1; color: #1e7e34;
            padding: 5px 12px; border-radius: var(--radius-sm);
            font-weight: 600; font-size: 0.82rem;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .status-badge .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .badge-changed  { background: var(--change-lt); color: var(--change); }
        .badge-changed .dot { background: var(--change); }

        /* Record date */
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
            background: linear-gradient(135deg, var(--change-bg), #e0f2fe);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--change);
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
        .pagination .page-item .page-link:hover { background: var(--change-lt); color: var(--change); border-color: #c4b5fd; }
        .pagination .page-item.active .page-link { background: var(--change); color: #fff; border-color: var(--change); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .changes-card-header { flex-direction: column; align-items: flex-start; }
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
                                <span class="header-icon"><i class="feather icon-calendar"></i></span>
                                Date Changed Tickets
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="summary-cards">

            <div class="summary-card">
                <div class="sc-icon purple"><i class="feather icon-calendar"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format($total) ?></div>
                    <div class="sc-label">Total Changes</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon orange"><i class="feather icon-dollar-sign"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['total_fees'] ?? 0), 2) ?></div>
                    <div class="sc-label">Total Fees Paid</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon teal"><i class="feather icon-bar-chart-2"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['avg_fee'] ?? 0), 2) ?></div>
                    <div class="sc-label">Average Fee</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon green"><i class="feather icon-arrow-down-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['smallest_fee'] ?? 0), 2) ?></div>
                    <div class="sc-label">Lowest Fee</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon blue"><i class="feather icon-arrow-up-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)($stats['largest_fee'] ?? 0), 2) ?></div>
                    <div class="sc-label">Highest Fee</div>
                </div>
            </div>

        </div>

        <!-- ── Notice Banner ──────────────────────────────── -->
        <?php if ($total > 0): ?>
        <div class="change-notice">
            <i class="feather icon-info"></i>
            <span>The dates shown reflect the original departure and updated return dates recorded at the time of the change. Change fees are based on the profit field at booking. Contact your agent for further clarifications.</span>
        </div>
        <?php endif; ?>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="changes-card">

            <!-- Card Header -->
            <div class="changes-card-header">
                <div class="cch-left">
                    <h6><i class="feather icon-list" style="color:var(--change);margin-right:6px;"></i>Change Records</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($changes)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-calendar"></i></div>
                    <h6>No Date Changes Found</h6>
                    <p>None of your tickets have had their travel dates changed. Your itineraries are all on track!</p>
                    <div class="es-good">
                        <i class="feather icon-check-circle"></i> All dates unchanged
                    </div>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="changes-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>PNR</th>
                                <th>Passenger & Route</th>
                                <th class="hide-mobile">Airline</th>
                                <th>Date Change</th>
                                <th class="hide-mobile">Change Fee</th>
                                <th class="hide-mobile">Status</th>
                                <th class="hide-mobile">Recorded</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($changes as $index => $change):
                                $rowNum    = $offset + $index + 1;
                                $fee       = (float)($change['change_fee'] ?? 0);
                                $createdAt = strtotime($change['created_at']);
                                $origDate  = safeDate($change['original_date'] ?? '');
                                $newDate   = safeDate($change['new_date'] ?? '');
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- PNR -->
                                <td>
                                    <span class="pnr-chip"><?= htmlspecialchars($change['original_pnr'] ?? '—') ?></span>
                                </td>

                                <!-- Passenger + Route -->
                                <td>
                                    <div class="passenger-name"><?= htmlspecialchars($change['passenger_name'] ?? '—') ?></div>
                                    <div class="passenger-route">
                                        <span><?= htmlspecialchars($change['origin'] ?? '—') ?></span>
                                        <span class="route-arrow"><i class="feather icon-arrow-right"></i></span>
                                        <span><?= htmlspecialchars($change['destination'] ?? '—') ?></span>
                                    </div>
                                </td>

                                <!-- Airline -->
                                <td class="hide-mobile">
                                    <?php if (!empty($change['airline'])): ?>
                                        <span class="airline-chip"><?= htmlspecialchars($change['airline']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date Change (original → new) -->
                                <td>
                                    <div class="date-change-cell">
                                        <span class="date-pill original">
                                            <i class="feather icon-calendar"></i>
                                            <?= $origDate ?>
                                        </span>
                                        <div class="date-change-arrow">
                                            <i class="feather icon-arrow-down"></i>
                                            <span>changed to</span>
                                        </div>
                                        <span class="date-pill new-date">
                                            <i class="feather icon-calendar"></i>
                                            <?= $newDate ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- Change Fee -->
                                <td class="hide-mobile">
                                    <?php if ($fee > 0): ?>
                                        <span class="fee-chip">
                                            <span class="currency">USD</span>
                                            <?= number_format($fee, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="fee-free">
                                            <i class="feather icon-check"></i> No Fee
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="hide-mobile">
                                    <span class="status-badge badge-changed">
                                        <span class="dot"></span>
                                        Date Changed
                                    </span>
                                </td>

                                <!-- Recorded Date -->
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
                                    <a href="ticket_detail.php?id=<?= urlencode($change['ticket_id']) ?>"
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
                        Showing <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?></strong> of <strong><?= number_format($total) ?></strong> changes
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
        <!-- end .changes-card -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>