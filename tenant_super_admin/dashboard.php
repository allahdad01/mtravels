<?php
include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Get branch parameter from URL or session
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : null;
if ($selected_branch_id === 0) {
    $selected_branch_id = null; // 0 means "All Branches"
}

// Handle success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'branch_switched':
            $branch_name = $_GET['branch'] ?? 'Unknown';
            $success_message = "Successfully switched to branch: " . htmlspecialchars($branch_name);
            break;
        default:
            $success_message = "Operation completed successfully";
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'branch_not_found':
            $error_message = "Selected branch not found or not accessible";
            break;
        case 'no_branch_selected':
            $error_message = "No branch selected";
            break;
        case 'database_error':
            $error_message = "Database error occurred";
            break;
        default:
            $error_message = "An error occurred";
    }
}

// Get current branch information
$current_branch_name = "All Branches";
$current_branch_id = null;

if ($selected_branch_id) {
    $branch_query = "SELECT name FROM branches WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($branch_query);
    $stmt->execute([$selected_branch_id, $tenant_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $current_branch_name = $branch_data['name'];
        $current_branch_id = $selected_branch_id;
    }
}

// Fetch dashboard statistics
try {
    // Branch statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_branches FROM branches WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $branchStats = $stmt->fetch(PDO::FETCH_ASSOC);
// User statistics by branch
$userQuery = "
    SELECT
        COUNT(u.id) as total_users,
        COUNT(CASE WHEN u.role = 'admin' THEN 1 END) as admin_users,
        COUNT(CASE WHEN u.role = 'sales' THEN 1 END) as sales_users,
        COUNT(CASE WHEN u.role = 'finance' THEN 1 END) as finance_users,
        COUNT(CASE WHEN u.role = 'umrah' THEN 1 END) as umrah_users
    FROM users u
    WHERE u.tenant_id = ?
";
    $userParams = [$tenant_id];

    if ($current_branch_id) {
        $userQuery .= " AND u.branch_id = ?";
        $userParams[] = $current_branch_id;
    }

    $stmt = $pdo->prepare($userQuery);
    $stmt->execute($userParams);
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recent activities across all branches
    $activityQuery = "
        SELECT al.*, u.name as user_name, b.name as branch_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN branches b ON al.branch_id = b.id
        WHERE al.tenant_id = ?
    ";
    $activityParams = [$tenant_id];

    if ($current_branch_id) {
        $activityQuery .= " AND al.branch_id = ?";
        $activityParams[] = $current_branch_id;
    }

    $activityQuery .= " ORDER BY al.created_at DESC LIMIT 10";

    $stmt = $pdo->prepare($activityQuery);
    $stmt->execute($activityParams);
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Branch performance (bookings, revenue, etc.) - using aggregated subqueries to prevent multiplication
    $performanceQuery = "
        SELECT
            b.name as branch_name,
            b.id as branch_id,
            COALESCE(ticket_stats.ticket_bookings, 0) as ticket_bookings,
            COALESCE(ticket_stats.ticket_profit, 0) as ticket_profit,
            COALESCE(reservation_stats.ticket_reservations, 0) as ticket_reservations,
            COALESCE(reservation_stats.reservation_profit, 0) as reservation_profit,
            COALESCE(weight_stats.ticket_weights, 0) as ticket_weights,
            COALESCE(weight_stats.weight_profit, 0) as weight_profit,
            COALESCE(hotel_stats.hotel_bookings, 0) as hotel_bookings,
            COALESCE(hotel_stats.hotel_profit, 0) as hotel_profit,
            COALESCE(visa_stats.visa_applications, 0) as visa_applications,
            COALESCE(visa_stats.visa_profit, 0) as visa_profit,
            COALESCE(umrah_stats.umrah_bookings, 0) as umrah_bookings,
            COALESCE(umrah_stats.umrah_profit, 0) as umrah_profit,
            COALESCE(additional_stats.additional_payments, 0) as additional_payments,
            COALESCE(additional_stats.additional_profit, 0) as additional_profit,
            COALESCE(refund_stats.refunded_tickets, 0) as refunded_tickets,
            COALESCE(refund_stats.refund_profit, 0) as refund_profit,
            COALESCE(date_change_stats.date_change_tickets, 0) as date_change_tickets,
            COALESCE(date_change_stats.date_change_profit, 0) as date_change_profit,
            COALESCE(ticket_stats.ticket_profit, 0) +
            COALESCE(reservation_stats.reservation_profit, 0) +
            COALESCE(weight_stats.weight_profit, 0) +
            COALESCE(hotel_stats.hotel_profit, 0) +
            COALESCE(visa_stats.visa_profit, 0) +
            COALESCE(umrah_stats.umrah_profit, 0) +
            COALESCE(additional_stats.additional_profit, 0) +
            COALESCE(refund_stats.refund_profit, 0) +
            COALESCE(date_change_stats.date_change_profit, 0) as total_revenue
        FROM branches b

        -- Ticket bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(t.id) as ticket_bookings,
                SUM(CASE WHEN t.currency = 'USD' THEN t.profit ELSE 0 END) as ticket_profit
            FROM ticket_bookings t
            JOIN users u ON t.created_by = u.id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) ticket_stats ON ticket_stats.branch_id = b.id

        -- Ticket reservations aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(tr.id) as ticket_reservations,
                SUM(CASE WHEN tr.currency = 'USD' THEN tr.profit ELSE 0 END) as reservation_profit
            FROM ticket_reservations tr
            JOIN users u ON tr.created_by = u.id
            WHERE tr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) reservation_stats ON reservation_stats.branch_id = b.id

        -- Ticket weights aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(tw.id) as ticket_weights,
                SUM(CASE WHEN tb.currency = 'USD' THEN tw.profit ELSE 0 END) as weight_profit
            FROM ticket_weights tw
            JOIN users u ON tw.created_by = u.id
            LEFT JOIN ticket_bookings tb ON tb.id = tw.ticket_id
            WHERE tw.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) weight_stats ON weight_stats.branch_id = b.id

        -- Hotel bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(h.id) as hotel_bookings,
                SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END) as hotel_profit
            FROM hotel_bookings h
            JOIN users u ON h.created_by = u.id
            WHERE h.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) hotel_stats ON hotel_stats.branch_id = b.id

        -- Visa applications aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(v.id) as visa_applications,
                SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END) as visa_profit
            FROM visa_applications v
            JOIN users u ON v.created_by = u.id
            WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) visa_stats ON visa_stats.branch_id = b.id

        -- Umrah bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(um.booking_id) as umrah_bookings,
                SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END) as umrah_profit
            FROM umrah_bookings um
            JOIN users u ON um.created_by = u.id
            WHERE um.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) umrah_stats ON umrah_stats.branch_id = b.id

        -- Additional payments aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(ap.id) as additional_payments,
                SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END) as additional_profit
            FROM additional_payments ap
            JOIN users u ON ap.created_by = u.id
            WHERE ap.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) additional_stats ON additional_stats.branch_id = b.id

        -- Refunded tickets aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(rt.id) as refunded_tickets,
                SUM(CASE WHEN rt.currency = 'USD' THEN
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END)
                    ELSE 0 END) as refund_profit
            FROM refunded_tickets rt
            JOIN users u ON rt.created_by = u.id
            LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            WHERE rt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) refund_stats ON refund_stats.branch_id = b.id

        -- Date change tickets aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(dt.id) as date_change_tickets,
                SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END) as date_change_profit
            FROM date_change_tickets dt
            JOIN users u ON dt.created_by = u.id
            WHERE dt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.branch_id
        ) date_change_stats ON date_change_stats.branch_id = b.id

        WHERE b.tenant_id = ? AND b.status = 'active'
    ";

    $performanceParams = [$tenant_id];

    if ($current_branch_id) {
        $performanceQuery .= " AND b.id = ?";
        $performanceParams[] = $current_branch_id;
    }

    $performanceQuery .= " GROUP BY b.id, b.name ORDER BY total_revenue DESC";

    $stmt = $pdo->prepare($performanceQuery);
    $stmt->execute($performanceParams);
    $branchPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $branchStats = ['total_branches' => 0];
    $userStats = ['total_users' => 0, 'admin_users' => 0, 'sales_users' => 0, 'finance_users' => 0, 'umrah_users' => 0];
    $recentActivities = [];
    $branchPerformance = [];
}
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Owner Dashboard</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Dashboard</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="feather icon-check-circle"></i> <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="feather icon-alert-circle"></i> <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Branch Selector -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-0">
                                    <i class="feather icon-git-branch mr-2"></i>
                                    Branch Filter: <strong><?= htmlspecialchars($current_branch_name) ?></strong>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <select class="form-control" id="branchSelector" onchange="changeBranch()">
                                        <option value="0" <?= !$current_branch_id ? 'selected' : '' ?>>All Branches</option>
                                        <?php
                                        $branchQuery = "SELECT id, name, code FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name";
                                        $branchStmt = $pdo->prepare($branchQuery);
                                        $branchStmt->execute([$tenant_id]);
                                        $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($branches as $branch) {
                                            $selected = ($current_branch_id == $branch['id']) ? 'selected' : '';
                                            echo '<option value="' . $branch['id'] . '" ' . $selected . '>' . htmlspecialchars($branch['name']) . ' (' . htmlspecialchars($branch['code']) . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Branch Statistics -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-event">
                    <div class="card-block">
                        <div class="row align-items-center justify-content-center">
                            <div class="col">
                                <h5 class="m-0"><i class="feather icon-git-branch"></i> Total Branches</h5>
                                <h2 class="mt-3 mb-3"><?= $branchStats['total_branches'] ?? 0 ?></h2>
                                <h6 class="text-muted m-b-0">Active branches in your network</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-event">
                    <div class="card-block">
                        <div class="row align-items-center justify-content-center">
                            <div class="col">
                                <h5 class="m-0"><i class="feather icon-users"></i> Total Users</h5>
                                <h2 class="mt-3 mb-3"><?= $userStats['total_users'] ?? 0 ?></h2>
                                <h6 class="text-muted m-b-0">Across all branches</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Users -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-event">
                    <div class="card-block">
                        <div class="row align-items-center justify-content-center">
                            <div class="col">
                                <h5 class="m-0"><i class="feather icon-user-check"></i> Branch Admins</h5>
                                <h2 class="mt-3 mb-3"><?= $userStats['admin_users'] ?? 0 ?></h2>
                                <h6 class="text-muted m-b-0">Administrative users</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operational Users -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-event">
                    <div class="card-block">
                        <div class="row align-items-center justify-content-center">
                            <div class="col">
                                <h5 class="m-0"><i class="feather icon-user-plus"></i> Staff Users</h5>
                                <h2 class="mt-3 mb-3"><?= ($userStats['sales_users'] ?? 0) + ($userStats['finance_users'] ?? 0) + ($userStats['umrah_users'] ?? 0) ?></h2>
                                <h6 class="text-muted m-b-0">Sales, Finance, Umrah</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branch Performance Table -->
        <div class="row">
            <div class="col-xl-8 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Branch Performance (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Branch Name</th>
                                        <th>Tickets<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Reservations<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Ticket Weights<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Hotels<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Visas<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Umrah<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Add. Payments<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Refunds<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Date Changes<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Total Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branchPerformance as $branch): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($branch['branch_name']) ?></strong></td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['ticket_bookings'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['ticket_profit'] ?? 0, 2) ?></small>
                                                <?php if (isset($_GET['debug'])): ?>
                                                <br><small class="text-muted">Debug: $<?= number_format($branch['ticket_profit'] ?? 0, 2) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['ticket_reservations'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['reservation_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['ticket_weights'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['weight_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['hotel_bookings'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['hotel_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['visa_applications'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['visa_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['umrah_bookings'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['umrah_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['additional_payments'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['additional_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['refunded_tickets'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['refund_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['date_change_tickets'] ?? 0 ?></div>
                                                <small class="text-success">$<?= number_format($branch['date_change_profit'] ?? 0, 2) ?></small>
                                            </div>
                                        </td>
                                        <td>$<?= number_format($branch['total_revenue'] ?? 0, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($branchPerformance)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No branch data available</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-xl-4 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <?php if (!empty($recentActivities)): ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <div class="media mb-3">
                                        <div class="media-body">
                                            <h6 class="mt-0 mb-1">
                                                <small class="text-muted">
                                                    <i class="feather icon-activity"></i>
                                                    <?= htmlspecialchars($activity['action']) ?>
                                                </small>
                                            </h6>
                                            <p class="mb-1">
                                                <?= htmlspecialchars($activity['table_name']) ?>
                                                <?php if ($activity['record_id']): ?>
                                                    (ID: <?= $activity['record_id'] ?>)
                                                <?php endif; ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php if ($activity['branch_name']): ?>
                                                    Branch: <?= htmlspecialchars($activity['branch_name']) ?> |
                                                <?php endif; ?>
                                                User: <?= htmlspecialchars($activity['user_name'] ?? 'System') ?> |
                                                <?= date('M d, H:i', strtotime($activity['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted">No recent activities</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
        <div class="col-12">
        <div class="card">
        <div class="card-header">
        <h5>Quick Actions</h5>
        </div>
        <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <a href="branches.php" class="btn btn-primary btn-block">
                    <i class="feather icon-git-branch"></i> Manage Branches
                </a>
            </div>
            <div class="col-md-3">
                <a href="users.php" class="btn btn-success btn-block">
                    <i class="feather icon-user-plus"></i> Manage Users
                </a>
            </div>
            <div class="col-md-3">
                <a href="reports.php" class="btn btn-info btn-block">
                    <i class="feather icon-file"></i> View Reports
                </a>
            </div>
            <div class="col-md-3">
                <a href="settings.php" class="btn btn-warning btn-block">
                    <i class="feather icon-settings"></i> Settings
                </a>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3">
                <a href="generate_report.php" class="btn btn-outline-success btn-block">
                    <i class="feather icon-download"></i> Generate Report
                </a>
            </div>
            <div class="col-md-3">
                <a href="report_settings.php" class="btn btn-outline-info btn-block">
                    <i class="feather icon-mail"></i> Report Settings
                </a>
            </div>
        </div>
        </div>
        </div>
        </div>
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">Profile Settings</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="profileUpdateForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="profile_pic">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel">Account Settings</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="settingsUpdateForm">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Branch change functionality
function changeBranch() {
    const branchId = document.getElementById('branchSelector').value;
    const currentUrl = new URL(window.location);

    if (branchId === '0') {
        currentUrl.searchParams.delete('branch');
    } else {
        currentUrl.searchParams.set('branch', branchId);
    }

    window.location.href = currentUrl.toString();
}

// Profile update functionality
$('#profileUpdateForm').on('submit', function(e) {
    e.preventDefault();

    var formData = new FormData(this);

    $.ajax({
        url: 'update_profile.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#profileModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred while updating profile');
        }
    });
});

// Settings update functionality
$('#settingsUpdateForm').on('submit', function(e) {
    e.preventDefault();

    var formData = $(this).serialize();

    $.ajax({
        url: 'update_settings.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#settingsModal').modal('hide');
                alert('Password updated successfully');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred while updating settings');
        }
    });
});
</script>

<?php include 'footer.php'; ?>