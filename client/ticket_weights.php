<?php
/**
 * Client Ticket Weights Page
 * Displays ticket weight information for the client
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
            COUNT(tw.id)       AS total_records,
            SUM(tw.weight)     AS total_weight,
            MAX(tw.weight)     AS max_weight,
            MIN(tw.weight)     AS min_weight,
            AVG(tw.weight)     AS avg_weight
        FROM ticket_weights tw
        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
        WHERE tb.sold_to = ? AND tw.tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_records' => 0,
        'total_weight'  => 0,
        'max_weight'    => 0,
        'min_weight'    => 0,
        'avg_weight'    => 0,
    ];
}

// Fetch total count
$total       = (int)($stats['total_records'] ?? 0);
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;

// Fetch ticket weights
$weights = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            tw.id,
            tw.ticket_id,
            tb.pnr,
            tb.passenger_name,
            tw.weight,
            tb.airline,
            tb.origin,
            tb.destination,
            tw.created_at
        FROM ticket_weights tw
        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
        WHERE tb.sold_to = ? AND tw.tenant_id = ?
        ORDER BY tw.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $weights = [];
}

// Helper: weight level badge class
function weightBadgeClass(float $kg): string {
    if ($kg <= 20)  return 'wlevel-light';
    if ($kg <= 30)  return 'wlevel-medium';
    return 'wlevel-heavy';
}

// Helper: weight level label
function weightLabel(float $kg): string {
    if ($kg <= 20)  return 'Light';
    if ($kg <= 30)  return 'Medium';
    return 'Heavy';
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Ticket Weights</title>
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
            --primary:    #4099ff;
            --primary-dk: #2d7dd2;
            --accent:     #2ed8b6;
            --success:    #28a745;
            --warning:    #f0ad4e;
            --danger:     #dc3545;
            --muted:      #6c757d;
            --bg-page:    #f4f7fa;
            --bg-card:    #ffffff;
            --border:     #e9ecef;
            --text:       #2d3748;
            --text-light: #718096;
            --radius-lg:  14px;
            --radius-md:  10px;
            --radius-sm:  6px;
            --shadow-sm:  0 2px 8px rgba(0,0,0,0.06);
            --shadow-md:  0 4px 20px rgba(0,0,0,0.09);
            --transition: all 0.22s ease;
        }

        body { background: var(--bg-page); color: var(--text); }

        /* ─── Page Header ─────────────────────────────────── */
        .page-header-title h5 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-header-title h5 .header-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
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
        .sc-icon.blue   { background: #e8f3ff; color: var(--primary); }
        .sc-icon.teal   { background: #e0faf5; color: #1aaa8a; }
        .sc-icon.green  { background: #eafaf1; color: var(--success); }
        .sc-icon.yellow { background: #fff8e1; color: #d68910; }
        .sc-icon.purple { background: #f3eeff; color: #7c3aed; }

        .summary-card .sc-info .sc-value {
            font-size: 1.5rem; font-weight: 700;
            line-height: 1.2; color: var(--text);
        }
        .summary-card .sc-info .sc-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 2px; font-weight: 500;
        }

        /* ─── Main Card ───────────────────────────────────── */
        .weights-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .weights-card-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .weights-card-header .wch-left { display: flex; align-items: center; gap: 12px; }
        .weights-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .weights-card-header .count-chip {
            background: #e8f3ff; color: var(--primary);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .weights-table { width: 100%; border-collapse: collapse; }
        .weights-table thead tr { border-bottom: 2px solid var(--border); }
        .weights-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .weights-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .weights-table tbody tr:last-child { border-bottom: none; }
        .weights-table tbody tr:hover { background: #f5f9ff; }
        .weights-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* PNR chip */
        .pnr-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: #e8f3ff; color: var(--primary);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* Passenger cell */
        .passenger-name  { font-weight: 600; color: var(--text); font-size: 0.9rem; }
        .passenger-route { font-size: 0.78rem; color: var(--text-light); margin-top: 3px; display: flex; align-items: center; gap: 5px; }
        .route-arrow     { color: var(--primary); font-size: 0.75rem; }

        /* Airline chip */
        .airline-chip {
            background: #f3eeff; color: #7c3aed;
            padding: 4px 10px; border-radius: var(--radius-sm);
            font-size: 0.8rem; font-weight: 600;
            display: inline-block;
        }

        /* Weight display */
        .weight-display {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        .weight-value {
            font-size: 1.05rem; font-weight: 700; color: var(--text);
        }
        .weight-unit { font-size: 0.75rem; color: var(--text-light); font-weight: 500; }

        /* Weight level badges */
        .wlevel-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 0.74rem; font-weight: 600;
        }
        .wlevel-badge .dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

        .wlevel-light  { background: #eafaf1; color: #1e7e34; }
        .wlevel-light  .dot { background: #28a745; }
        .wlevel-medium { background: #fff8e1; color: #856404; }
        .wlevel-medium .dot { background: #f0ad4e; }
        .wlevel-heavy  { background: #fdecea; color: #b02a37; }
        .wlevel-heavy  .dot { background: #dc3545; }

        /* Visual weight bar */
        .weight-bar-wrap { width: 80px; height: 6px; background: #e9ecef; border-radius: 3px; margin-top: 4px; }
        .weight-bar-fill { height: 100%; border-radius: 3px; transition: width 0.4s ease; }
        .bar-light  { background: #28a745; }
        .bar-medium { background: #f0ad4e; }
        .bar-heavy  { background: #dc3545; }

        /* Date */
        .date-main { font-weight: 500; font-size: 0.88rem; }
        .date-time { font-size: 0.76rem; color: var(--text-light); margin-top: 2px; }

        /* Action Button */
        .btn-view {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #fff;
            color: var(--primary);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .btn-view:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: scale(1.07); }

        /* ─── Empty State ─────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state .es-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #e8f3ff, #e0faf5);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem; color: var(--primary);
        }
        .empty-state h6 { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .empty-state p  { font-size: 0.88rem; color: var(--text-light); max-width: 300px; margin: 0 auto; }

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
            background: #fff;
            color: var(--text);
            font-size: 0.85rem; font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }
        .pagination .page-item .page-link:hover { background: #e8f3ff; color: var(--primary); border-color: #cce0ff; }
        .pagination .page-item.active .page-link { background: var(--primary); color: #fff; border-color: var(--primary); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .weights-card-header { flex-direction: column; align-items: flex-start; }
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
                                <span class="header-icon"><i class="feather icon-package"></i></span>
                                Ticket Weights
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="summary-cards">

            <div class="summary-card">
                <div class="sc-icon blue"><i class="feather icon-layers"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format($total) ?></div>
                    <div class="sc-label">Total Records</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon teal"><i class="feather icon-trending-up"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((float)($stats['total_weight'] ?? 0), 1) ?> <span style="font-size:0.9rem;color:var(--text-light);">kg</span></div>
                    <div class="sc-label">Total Weight</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon purple"><i class="feather icon-bar-chart-2"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((float)($stats['avg_weight'] ?? 0), 1) ?> <span style="font-size:0.9rem;color:var(--text-light);">kg</span></div>
                    <div class="sc-label">Average Weight</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon green"><i class="feather icon-arrow-down-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((float)($stats['min_weight'] ?? 0), 1) ?> <span style="font-size:0.9rem;color:var(--text-light);">kg</span></div>
                    <div class="sc-label">Lightest</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon yellow"><i class="feather icon-arrow-up-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((float)($stats['max_weight'] ?? 0), 1) ?> <span style="font-size:0.9rem;color:var(--text-light);">kg</span></div>
                    <div class="sc-label">Heaviest</div>
                </div>
            </div>

        </div>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="weights-card">

            <!-- Card Header -->
            <div class="weights-card-header">
                <div class="wch-left">
                    <h6><i class="feather icon-list" style="color:var(--primary);margin-right:6px;"></i>Weight Records</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($weights)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-package"></i></div>
                    <h6>No Weight Records Found</h6>
                    <p>There are no ticket weight records associated with your bookings at this time.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="weights-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>PNR</th>
                                <th>Passenger & Route</th>
                                <th class="hide-mobile">Airline</th>
                                <th>Weight</th>
                                <th class="hide-mobile">Level</th>
                                <th class="hide-mobile">Date Added</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weights as $index => $w):
                                $rowNum    = $offset + $index + 1;
                                $kg        = (float)($w['weight'] ?? 0);
                                $badgeClass = weightBadgeClass($kg);
                                $label     = weightLabel($kg);
                                $createdAt = strtotime($w['created_at']);

                                // Visual bar: cap at 50 kg = 100%
                                $barPct   = min(100, round(($kg / 50) * 100));
                                $barClass = $kg <= 20 ? 'bar-light' : ($kg <= 30 ? 'bar-medium' : 'bar-heavy');
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- PNR -->
                                <td>
                                    <span class="pnr-chip"><?= htmlspecialchars($w['pnr'] ?? '—') ?></span>
                                </td>

                                <!-- Passenger + Route -->
                                <td>
                                    <div class="passenger-name"><?= htmlspecialchars($w['passenger_name'] ?? '—') ?></div>
                                    <div class="passenger-route">
                                        <span><?= htmlspecialchars($w['origin'] ?? '—') ?></span>
                                        <span class="route-arrow"><i class="feather icon-arrow-right"></i></span>
                                        <span><?= htmlspecialchars($w['destination'] ?? '—') ?></span>
                                    </div>
                                </td>

                                <!-- Airline -->
                                <td class="hide-mobile">
                                    <?php if (!empty($w['airline'])): ?>
                                        <span class="airline-chip"><?= htmlspecialchars($w['airline']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Weight + mini bar -->
                                <td>
                                    <div class="weight-display">
                                        <span class="weight-value"><?= number_format($kg, 1) ?></span>
                                        <span class="weight-unit">kg</span>
                                    </div>
                                    <div class="weight-bar-wrap">
                                        <div class="weight-bar-fill <?= $barClass ?>" style="width:<?= $barPct ?>%;"></div>
                                    </div>
                                </td>

                                <!-- Level badge -->
                                <td class="hide-mobile">
                                    <span class="wlevel-badge <?= $badgeClass ?>">
                                        <span class="dot"></span>
                                        <?= $label ?>
                                    </span>
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
                                    <a href="ticket_detail.php?id=<?= urlencode($w['ticket_id']) ?>"
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
                        Showing <strong><?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?></strong> of <strong><?= number_format($total) ?></strong> records
                    </div>

                    <ul class="pagination">
                        <!-- First -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=1" title="First"><i class="feather icon-chevrons-left"></i></a>
                        </li>
                        <!-- Prev -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" title="Previous"><i class="feather icon-chevron-left"></i></a>
                        </li>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next -->
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" title="Next"><i class="feather icon-chevron-right"></i></a>
                        </li>
                        <!-- Last -->
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $total_pages ?>" title="Last"><i class="feather icon-chevrons-right"></i></a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <!-- end .weights-card -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>