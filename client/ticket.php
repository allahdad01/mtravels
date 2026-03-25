<?php
/**
 * Client Ticket Bookings Page
 * Displays all tickets booked by the client, filtered by client_id and tenant_id
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

// Fetch total count
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM ticket_bookings 
        WHERE sold_to = ? AND tenant_id = ?
    ");
    $countStmt->execute([$client_id, $tenant_id]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $total = 0;
}
$total_pages = $total > 0 ? ceil($total / $per_page) : 1;

// Fetch summary stats
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(sold) as total_spent,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM ticket_bookings 
        WHERE sold_to = ? AND tenant_id = ?
    ");
    $statsStmt->execute([$client_id, $tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_bookings' => 0,
        'total_spent'    => 0,
        'confirmed'      => 0,
        'pending'        => 0,
        'cancelled'      => 0,
    ];
}

// Fetch tickets
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM ticket_bookings 
        WHERE sold_to = ? AND tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$client_id, $tenant_id, $per_page, $offset]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Tickets fetch error: " . $e->getMessage());
    $tickets = [];
}

// Helper: status badge class
function statusBadgeClass(string $status): string {
    return match(strtolower(trim($status))) {
        'confirmed' => 'badge-confirmed',
        'pending'   => 'badge-pending',
        'cancelled' => 'badge-cancelled',
        default     => 'badge-default',
    };
}

// Helper: status icon
function statusIcon(string $status): string {
    return match(strtolower(trim($status))) {
        'confirmed' => 'icon-check-circle',
        'pending'   => 'icon-clock',
        'cancelled' => 'icon-x-circle',
        default     => 'icon-help-circle',
    };
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – My Ticket Bookings</title>
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
            --primary:     #4099ff;
            --primary-dk:  #2d7dd2;
            --accent:      #2ed8b6;
            --success:     #28a745;
            --warning:     #f0ad4e;
            --danger:      #dc3545;
            --muted:       #6c757d;
            --bg-page:     #f4f7fa;
            --bg-card:     #ffffff;
            --border:      #e9ecef;
            --text:        #2d3748;
            --text-light:  #718096;
            --radius-lg:   14px;
            --radius-md:   10px;
            --radius-sm:   6px;
            --shadow-sm:   0 2px 8px rgba(0,0,0,0.06);
            --shadow-md:   0 4px 20px rgba(0,0,0,0.09);
            --transition:  all 0.22s ease;
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
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 18px; margin-bottom: 28px; }

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
            width: 48px; height: 48px; border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .sc-icon.blue   { background: #e8f3ff; color: var(--primary); }
        .sc-icon.green  { background: #eafaf1; color: var(--success); }
        .sc-icon.yellow { background: #fff8e1; color: #d68910; }
        .sc-icon.red    { background: #fdecea; color: var(--danger); }
        .sc-icon.teal   { background: #e0faf5; color: #1aaa8a; }

        .summary-card .sc-info { min-width: 0; }
        .summary-card .sc-info .sc-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; color: var(--text); }
        .summary-card .sc-info .sc-label { font-size: 0.78rem; color: var(--text-light); margin-top: 2px; font-weight: 500; }

        /* ─── Main Card ───────────────────────────────────── */
        .tickets-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .tickets-card-header {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .tickets-card-header .tch-left { display: flex; align-items: center; gap: 12px; }
        .tickets-card-header h6 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--text); }
        .tickets-card-header .count-chip {
            background: #e8f3ff; color: var(--primary);
            font-size: 0.78rem; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ─── Table ───────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        .bookings-table { width: 100%; border-collapse: collapse; }
        .bookings-table thead tr { border-bottom: 2px solid var(--border); }
        .bookings-table thead th {
            padding: 13px 16px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-light);
            background: #f9fafb;
            white-space: nowrap;
        }
        .bookings-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .bookings-table tbody tr:last-child { border-bottom: none; }
        .bookings-table tbody tr:hover { background: #f5f9ff; }
        .bookings-table td { padding: 14px 16px; font-size: 0.9rem; vertical-align: middle; }

        /* PNR chip */
        .pnr-chip {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: #e8f3ff; color: var(--primary);
            padding: 4px 10px; border-radius: var(--radius-sm);
            display: inline-block; letter-spacing: 0.5px;
        }

        /* Passenger cell */
        .passenger-name { font-weight: 600; color: var(--text); font-size: 0.9rem; }
        .passenger-route { font-size: 0.78rem; color: var(--text-light); margin-top: 3px; display: flex; align-items: center; gap: 5px; }
        .route-arrow { color: var(--primary); font-size: 0.75rem; }

        /* Price */
        .price-value { font-weight: 700; color: var(--text); }
        .price-value .currency { font-size: 0.75rem; color: var(--text-light); font-weight: 500; }

        /* Date */
        .date-main { font-weight: 500; font-size: 0.88rem; }
        .date-time { font-size: 0.76rem; color: var(--text-light); margin-top: 2px; }

        /* ─── Status Badges ───────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600;
        }
        .status-badge .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        .badge-confirmed { background: #eafaf1; color: #1e7e34; }
        .badge-confirmed .dot { background: #28a745; }
        .badge-pending   { background: #fff8e1; color: #856404; }
        .badge-pending   .dot { background: #f0ad4e; }
        .badge-cancelled { background: #fdecea; color: #b02a37; }
        .badge-cancelled .dot { background: #dc3545; }
        .badge-default   { background: #f1f3f5; color: #495057; }
        .badge-default   .dot { background: #adb5bd; }

        /* ─── Action Button ───────────────────────────────── */
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
        .empty-state p  { font-size: 0.88rem; color: var(--text-light); max-width: 300px; margin: 0 auto 20px; }

        /* ─── Pagination ──────────────────────────────────── */
        .pagination-wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 18px 24px; border-top: 1px solid var(--border); }
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
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }
        .pagination .page-item .page-link:hover { background: #e8f3ff; color: var(--primary); border-color: #cce0ff; }
        .pagination .page-item.active .page-link { background: var(--primary); color: #fff; border-color: var(--primary); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }
        .pagination .page-item.page-label .page-link { width: auto; padding: 0 12px; font-size: 0.8rem; }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .tickets-card-header { flex-direction: column; align-items: flex-start; }
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
                                My Ticket Bookings
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
                    <div class="sc-value"><?= number_format((int)$stats['total_bookings']) ?></div>
                    <div class="sc-label">Total Bookings</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon teal"><i class="feather icon-dollar-sign"></i></div>
                <div class="sc-info">
                    <div class="sc-value">$<?= number_format((float)$stats['total_spent'], 2) ?></div>
                    <div class="sc-label">Total Spent</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon green"><i class="feather icon-check-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)$stats['confirmed']) ?></div>
                    <div class="sc-label">Confirmed</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon yellow"><i class="feather icon-clock"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)$stats['pending']) ?></div>
                    <div class="sc-label">Pending</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="sc-icon red"><i class="feather icon-x-circle"></i></div>
                <div class="sc-info">
                    <div class="sc-value"><?= number_format((int)$stats['cancelled']) ?></div>
                    <div class="sc-label">Cancelled</div>
                </div>
            </div>

        </div>

        <!-- ── Main Table Card ────────────────────────────── -->
        <div class="tickets-card">

            <!-- Card Header -->
            <div class="tickets-card-header">
                <div class="tch-left">
                    <h6><i class="feather icon-list" style="color:var(--primary);margin-right:6px;"></i>All Bookings</h6>
                    <span class="count-chip"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-light);">
                    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
            </div>

            <!-- Table or Empty State -->
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <div class="es-icon"><i class="feather icon-inbox"></i></div>
                    <h6>No Tickets Found</h6>
                    <p>You haven't made any ticket bookings yet. Contact your agent to book your first ticket.</p>
                    <a href="mailto:<?= htmlspecialchars($settings['agency_email'] ?? '') ?>" class="btn btn-primary btn-sm" style="border-radius:var(--radius-sm);padding:8px 20px;">
                        <i class="feather icon-mail" style="margin-right:5px;"></i> Contact Agent
                    </a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>PNR</th>
                                <th>Passenger & Route</th>
                                <th class="hide-mobile">Price (USD)</th>
                                <th>Status</th>
                                <th class="hide-mobile">Date</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $index => $ticket):
                                $rowNum    = $offset + $index + 1;
                                $status    = $ticket['status'] ?? 'active';
                                $badgeClass = statusBadgeClass($status);
                                $createdAt  = strtotime($ticket['created_at']);
                            ?>
                            <tr>
                                <!-- Row Number -->
                                <td style="color:var(--text-light); font-size:0.82rem; width:40px;"><?= $rowNum ?></td>

                                <!-- PNR -->
                                <td>
                                    <span class="pnr-chip"><?= htmlspecialchars($ticket['pnr'] ?? '—') ?></span>
                                </td>

                                <!-- Passenger + Route (combined for mobile friendliness) -->
                                <td>
                                    <div class="passenger-name"><?= htmlspecialchars($ticket['passenger_name'] ?? '—') ?></div>
                                    <div class="passenger-route">
                                        <span><?= htmlspecialchars($ticket['origin'] ?? '—') ?></span>
                                        <span class="route-arrow"><i class="feather icon-arrow-right"></i></span>
                                        <span><?= htmlspecialchars($ticket['destination'] ?? '—') ?></span>
                                    </div>
                                </td>

                                <!-- Price -->
                                <td class="hide-mobile">
                                    <div class="price-value">
                                        <span class="currency">USD </span><?= number_format((float)($ticket['sold'] ?? 0), 2) ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td>
                                    <span class="status-badge <?= $badgeClass ?>">
                                        <span class="dot"></span>
                                        <?= htmlspecialchars(ucfirst($status)) ?>
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
                                    <a href="ticket_detail.php?id=<?= urlencode($ticket['id']) ?>"
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
        <!-- end .tickets-card -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>