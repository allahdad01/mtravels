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
             COALESCE(ticket_stats.ticket_profit_usd, 0) as ticket_profit_usd,
             COALESCE(ticket_stats.ticket_profit_afs, 0) as ticket_profit_afs,
             COALESCE(reservation_stats.ticket_reservations, 0) as ticket_reservations,
             COALESCE(reservation_stats.reservation_profit_usd, 0) as reservation_profit_usd,
             COALESCE(reservation_stats.reservation_profit_afs, 0) as reservation_profit_afs,
             COALESCE(weight_stats.ticket_weights, 0) as ticket_weights,
             COALESCE(weight_stats.weight_profit_usd, 0) as weight_profit_usd,
             COALESCE(weight_stats.weight_profit_afs, 0) as weight_profit_afs,
             COALESCE(hotel_stats.hotel_bookings, 0) as hotel_bookings,
             COALESCE(hotel_stats.hotel_profit_usd, 0) as hotel_profit_usd,
             COALESCE(hotel_stats.hotel_profit_afs, 0) as hotel_profit_afs,
             COALESCE(visa_stats.visa_applications, 0) as visa_applications,
             COALESCE(visa_stats.visa_profit_usd, 0) as visa_profit_usd,
             COALESCE(visa_stats.visa_profit_afs, 0) as visa_profit_afs,
             COALESCE(umrah_stats.umrah_bookings, 0) as umrah_bookings,
             COALESCE(umrah_stats.umrah_profit_usd, 0) as umrah_profit_usd,
             COALESCE(umrah_stats.umrah_profit_afs, 0) as umrah_profit_afs,
             COALESCE(additional_stats.additional_payments, 0) as additional_payments,
             COALESCE(additional_stats.additional_profit_usd, 0) as additional_profit_usd,
             COALESCE(additional_stats.additional_profit_afs, 0) as additional_profit_afs,
             COALESCE(refund_stats.refunded_tickets, 0) as refunded_tickets,
             COALESCE(refund_stats.refund_profit_usd, 0) as refund_profit_usd,
             COALESCE(refund_stats.refund_profit_afs, 0) as refund_profit_afs,
             COALESCE(date_change_stats.date_change_tickets, 0) as date_change_tickets,
             COALESCE(date_change_stats.date_change_profit_usd, 0) as date_change_profit_usd,
             COALESCE(date_change_stats.date_change_profit_afs, 0) as date_change_profit_afs,
             COALESCE(ticket_stats.ticket_profit_usd, 0) +
             COALESCE(reservation_stats.reservation_profit_usd, 0) +
             COALESCE(weight_stats.weight_profit_usd, 0) +
             COALESCE(hotel_stats.hotel_profit_usd, 0) +
             COALESCE(visa_stats.visa_profit_usd, 0) +
             COALESCE(umrah_stats.umrah_profit_usd, 0) +
             COALESCE(additional_stats.additional_profit_usd, 0) +
             COALESCE(refund_stats.refund_profit_usd, 0) +
             COALESCE(date_change_stats.date_change_profit_usd, 0) as total_revenue_usd,
             COALESCE(ticket_stats.ticket_profit_afs, 0) +
             COALESCE(reservation_stats.reservation_profit_afs, 0) +
             COALESCE(weight_stats.weight_profit_afs, 0) +
             COALESCE(hotel_stats.hotel_profit_afs, 0) +
             COALESCE(visa_stats.visa_profit_afs, 0) +
             COALESCE(umrah_stats.umrah_profit_afs, 0) +
             COALESCE(additional_stats.additional_profit_afs, 0) +
             COALESCE(refund_stats.refund_profit_afs, 0) +
             COALESCE(date_change_stats.date_change_profit_afs, 0) as total_revenue_afs
        FROM branches b

        -- Ticket bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(t.id) as ticket_bookings,
                SUM(CASE WHEN t.currency = 'USD' THEN t.profit ELSE 0 END) as ticket_profit_usd,
                SUM(CASE WHEN t.currency = 'AFS' THEN t.profit ELSE 0 END) as ticket_profit_afs
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
                SUM(CASE WHEN tr.currency = 'USD' THEN tr.profit ELSE 0 END) as reservation_profit_usd,
                SUM(CASE WHEN tr.currency = 'AFS' THEN tr.profit ELSE 0 END) as reservation_profit_afs
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
                SUM(CASE WHEN tb.currency = 'USD' THEN tw.profit ELSE 0 END) as weight_profit_usd,
                SUM(CASE WHEN tb.currency = 'AFS' THEN tw.profit ELSE 0 END) as weight_profit_afs
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
                SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END) as hotel_profit_usd,
                SUM(CASE WHEN h.currency = 'AFS' THEN h.profit ELSE 0 END) as hotel_profit_afs
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
                SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END) as visa_profit_usd,
                SUM(CASE WHEN v.currency = 'AFS' THEN v.profit ELSE 0 END) as visa_profit_afs
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
                SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END) as umrah_profit_usd,
                SUM(CASE WHEN um.currency = 'AFS' THEN um.profit ELSE 0 END) as umrah_profit_afs
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
                SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END) as additional_profit_usd,
                SUM(CASE WHEN ap.currency = 'AFS' THEN ap.profit ELSE 0 END) as additional_profit_afs
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
                    ELSE 0 END) as refund_profit_usd,
                SUM(CASE WHEN rt.currency = 'AFS' THEN
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END)
                    ELSE 0 END) as refund_profit_afs
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
                SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END) as date_change_profit_usd,
                SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END) as date_change_profit_afs
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

    $performanceQuery .= " GROUP BY b.id, b.name ORDER BY total_revenue_usd DESC";

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
<style>
/* Enhanced custom styles for better layout and design */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.page-header-title h5 {
    color: #007bff;
    font-weight: 600;
}
.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}
.card.border-left-primary {
    border-left: 4px solid #007bff !important;
}
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}
.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    color: #ffffff !important;
    margin-bottom: 0 !important;
}
.card-header .card-header-right {
    color: #ffffff !important;
}
.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}
.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}
.badge-success {
    background-color: #28a745;
}
.badge-info {
    background-color: #17a2b8;
}
.badge-secondary {
    background-color: #6c757d;
}
.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}
.table {
    margin-bottom: 0;
}
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}
.table tbody tr:hover {
    background-color: #f1f3f4;
}
.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}
.btn {
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}
.btn-outline-primary {
    border-color: #007bff;
    color: #007bff;
}
.btn-outline-primary:hover {
    background-color: #007bff;
    border-color: #007bff;
}
.btn-outline-danger {
    border-color: #dc3545;
    color: #dc3545;
}
.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
}
.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}
.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}
.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}
.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}
.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}
.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px 15px 0 0;
    border: none;
}
.modal-header .close {
    color: white;
    opacity: 0.8;
}
.modal-header .close:hover {
    opacity: 1;
}
.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}
.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}
.btn-group .btn {
    border-radius: 50% !important;
    margin: 0 2px;
}
.text-primary {
    color: #007bff !important;
}
.text-muted {
    color: #6c757d !important;
}
.progress {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.progress-bar {
    transition: width 0.6s ease;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.pagination .page-link {
    border-radius: 50%;
    margin: 0 2px;
    border: 1px solid #dee2e6;
    color: #007bff;
}
.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}
.card.bg-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}
#estimatedCost {
    color: #28a745;
    font-weight: bold;
}

.h2 {
    font-size: 2.5rem;
}

.h4 {
    font-size: 1.5rem;
}

.h5 {
    font-size: 1.25rem;
}

.h6 {
    font-size: 1rem;
}
</style>
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="feather icon-bar-chart-2 mr-2"></i>Owner Dashboard</h5>
                    <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Monitor your business performance and manage your team</p>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-outline-light btn-sm" data-toggle="modal" data-target="#profileModal">
                        <i class="feather icon-user mr-1"></i>Profile
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm" data-toggle="modal" data-target="#settingsModal">
                        <i class="feather icon-settings mr-1"></i>Settings
                    </button>
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
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="feather icon-git-branch text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <div class="h2 font-weight-bold text-primary mb-2">
                            <i class="feather icon-git-branch mr-2"></i><?= $branchStats['total_branches'] ?? 0 ?>
                        </div>
                        <p class="text-muted mb-0">Total Branches</p>
                        <small class="text-muted">Active branches in your network</small>
                    </div>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="feather icon-users text-success" style="font-size: 3rem;"></i>
                        </div>
                        <div class="h2 font-weight-bold text-success mb-2">
                            <i class="feather icon-users mr-2"></i><?= $userStats['total_users'] ?? 0 ?>
                        </div>
                        <p class="text-muted mb-0">Total Users</p>
                        <small class="text-muted">Across all branches</small>
                    </div>
                </div>
            </div>

            <!-- Admin Users -->
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="feather icon-user-check text-info" style="font-size: 3rem;"></i>
                        </div>
                        <div class="h2 font-weight-bold text-info mb-2">
                            <i class="feather icon-user-check mr-2"></i><?= $userStats['admin_users'] ?? 0 ?>
                        </div>
                        <p class="text-muted mb-0">Branch Admins</p>
                        <small class="text-muted">Administrative users</small>
                    </div>
                </div>
            </div>

            <!-- Operational Users -->
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="feather icon-user-plus text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <div class="h2 font-weight-bold text-warning mb-2">
                            <i class="feather icon-user-plus mr-2"></i><?= ($userStats['sales_users'] ?? 0) + ($userStats['finance_users'] ?? 0) + ($userStats['umrah_users'] ?? 0) ?>
                        </div>
                        <p class="text-muted mb-0">Staff Users</p>
                        <small class="text-muted">Sales, Finance, Umrah</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Alerts Section -->
        <?php if (!empty($performance_alerts)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-alert-triangle"></i> Branch Performance Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert-container">
                            <?php foreach ($performance_alerts as $alert): ?>
                            <div class="alert alert-<?= ($alert['severity'] === 'CRITICAL') ? 'danger' : 'warning' ?> alert-dismissible fade show" role="alert">
                                <h6 class="alert-heading">
                                    <?= ($alert['severity'] === 'CRITICAL') ? '⚠️ CRITICAL' : '⚡ WARNING' ?> - 
                                    <?= htmlspecialchars($alert['branch_name'] ?? 'Unknown Branch') ?>
                                </h6>
                                <p class="mb-2"><?= htmlspecialchars($alert['message']) ?></p>
                                <small class="text-muted">
                                    <i class="feather icon-clock"></i> 
                                    <?= date('M d, Y H:i', strtotime($alert['created_at'])) ?>
                                    <?php if ($alert['status'] !== 'new'): ?>
                                    | Status: <span class="badge badge-secondary"><?= ucfirst($alert['status']) ?></span>
                                    <?php endif; ?>
                                </small>
                                <?php if ($alert['status'] === 'new'): ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" 
                                        onclick="acknowledgeAlert(<?= $alert['id'] ?>)"></button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                                                <small class="text-success">USD: $<?= number_format($branch['ticket_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['ticket_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['ticket_reservations'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['reservation_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['reservation_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['ticket_weights'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['weight_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['weight_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['hotel_bookings'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['hotel_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['hotel_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['visa_applications'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['visa_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['visa_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['umrah_bookings'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['umrah_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['umrah_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['additional_payments'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['additional_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['additional_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['refunded_tickets'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['refund_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['refund_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $branch['date_change_tickets'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($branch['date_change_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($branch['date_change_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-success">USD: $<?= number_format($branch['total_revenue_usd'] ?? 0, 2) ?></small><br>
                                            <small class="text-warning">AFS: <?= number_format($branch['total_revenue_afs'] ?? 0, 0) ?></small>
                                        </td>
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
                        <h5><i class="feather icon-zap mr-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="branches.php" class="btn btn-primary btn-lg btn-block">
                                    <i class="feather icon-git-branch mr-2"></i>Manage Branches
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="users.php" class="btn btn-success btn-lg btn-block">
                                    <i class="feather icon-user-plus mr-2"></i>Manage Users
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="btn btn-info btn-lg btn-block">
                                    <i class="feather icon-file mr-2"></i>View Reports
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="settings.php" class="btn btn-warning btn-lg btn-block">
                                    <i class="feather icon-settings mr-2"></i>Settings
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="generate_report.php" class="btn btn-outline-success btn-lg btn-block">
                                    <i class="feather icon-download mr-2"></i>Generate Report
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="report_settings.php" class="btn btn-outline-info btn-lg btn-block">
                                    <i class="feather icon-mail mr-2"></i>Report Settings
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="subscription_payments.php" class="btn btn-outline-primary btn-lg btn-block">
                                    <i class="feather icon-credit-card mr-2"></i>Payments
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="tenant_settings.php" class="btn btn-outline-secondary btn-lg btn-block">
                                    <i class="feather icon-sliders mr-2"></i>Tenant Settings
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
// Acknowledge performance alert
function acknowledgeAlert(alertId) {
    $.ajax({
        url: 'acknowledge_performance_alert.php',
        type: 'POST',
        data: { alert_id: alertId },
        success: function(response) {
            if (response.success) {
                location.reload();
            }
        },
        error: function(xhr) {
            console.error('Error acknowledging alert:', xhr);
        },
        dataType: 'json'
    });
}

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