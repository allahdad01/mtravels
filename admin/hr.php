<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get employee statistics
$stats_query = "
    SELECT
        COUNT(*) as total_employees,
        SUM(CASE WHEN fired = 0 THEN 1 ELSE 0 END) as active_employees,
        SUM(CASE WHEN fired = 1 THEN 1 ELSE 0 END) as fired_employees,
        COUNT(DISTINCT CASE WHEN DATE(hire_date) = CURDATE() THEN id END) as new_hires_today
    FROM users
    WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND role != 'tenant_super_admin'
";

$stmt = $pdo->prepare($stats_query);
$stmt->execute([$tenant_id, $branch_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent employee activities
$recent_activities_query = "
    SELECT
        u.name,
        u.fired,
        u.fired_at,
        u.hire_date,
        CASE
            WHEN u.fired = 1 THEN 'Terminated'
            WHEN DATE(u.hire_date) = CURDATE() THEN 'Hired'
            ELSE 'Active'
        END as activity_type,
        CASE
            WHEN u.fired = 1 THEN u.fired_at
            ELSE u.hire_date
        END as activity_date
    FROM users u
    WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin' AND role != 'tenant_super_admin'
    ORDER BY activity_date DESC
    LIMIT 10
";

$stmt = $pdo->prepare($recent_activities_query);
$stmt->execute([$tenant_id, $branch_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending salary payments
$pending_salaries_query = "
    SELECT COUNT(*) as pending_salaries
    FROM salary_management sm
    WHERE sm.tenant_id = ? AND sm.branch_id = ? AND sm.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM salary_payments sp
        WHERE sp.user_id = sm.user_id AND sp.tenant_id = ? AND sp.branch_id = ?
        AND sp.payment_for_month = DATE_FORMAT(CURDATE(), '%Y-%m-01')
    )
";

$stmt = $pdo->prepare($pending_salaries_query);
$stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id]);
$pending_data = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = __('hr_management');
include '../includes/header.php';
?>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header">
                                    <div class="page-header-content">
                                        <h1><i class="feather icon-users mr-2"></i><?php echo __('hr_management'); ?></h1>
                                        <p><?php echo __('manage_employee_lifecycle_and_hr_operations'); ?></p>
                                    </div>
                                    <div class="page-header-actions">
                                        <a href="../api/employee/add_employee.php" class="btn btn-primary">
                                            <i class="feather icon-user-plus mr-1"></i><?php echo __('add_employee'); ?>
                                        </a>
                                    </div>
                                </div>

                                <!-- Statistics Cards -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card stat-card">
                                            <div class="card-body">
                                                <div class="stat-icon bg-primary">
                                                    <i class="feather icon-users"></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h3><?php echo $stats['total_employees'] ?? 0; ?></h3>
                                                    <p><?php echo __('total_employees'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stat-card">
                                            <div class="card-body">
                                                <div class="stat-icon bg-success">
                                                    <i class="feather icon-user-check"></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h3><?php echo $stats['active_employees'] ?? 0; ?></h3>
                                                    <p><?php echo __('active_employees'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stat-card">
                                            <div class="card-body">
                                                <div class="stat-icon bg-danger">
                                                    <i class="feather icon-user-x"></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h3><?php echo $stats['fired_employees'] ?? 0; ?></h3>
                                                    <p><?php echo __('terminated_employees'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stat-card">
                                            <div class="card-body">
                                                <div class="stat-icon bg-info">
                                                    <i class="feather icon-user-plus"></i>
                                                </div>
                                                <div class="stat-content">
                                                    <h3><?php echo $stats['new_hires_today'] ?? 0; ?></h3>
                                                    <p><?php echo __('new_hires_today'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Quick Actions -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-zap mr-2"></i><?php echo __('quick_actions'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <a href="employee_management.php" class="btn btn-outline-primary btn-block mb-2">
                                                            <i class="feather icon-users mr-1"></i><?php echo __('manage_employees'); ?>
                                                        </a>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <a href="salary_management.php" class="btn btn-outline-success btn-block mb-2">
                                                            <i class="feather icon-dollar-sign mr-1"></i><?php echo __('salary_management'); ?>
                                                        </a>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <a href="employee_performance.php" class="btn btn-outline-info btn-block mb-2">
                                                            <i class="feather icon-trending-up mr-1"></i><?php echo __('performance_reviews'); ?>
                                                        </a>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <a href="hr_reports.php" class="btn btn-outline-secondary btn-block mb-2">
                                                            <i class="feather icon-file-text mr-1"></i><?php echo __('hr_reports'); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recent Activities -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-activity mr-2"></i><?php echo __('recent_hr_activities'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($recent_activities)): ?>
                                                    <p class="text-muted"><?php echo __('no_recent_activities'); ?></p>
                                                <?php else: ?>
                                                    <div class="activity-list">
                                                        <?php foreach ($recent_activities as $activity): ?>
                                                            <div class="activity-item">
                                                                <div class="activity-icon">
                                                                    <?php if ($activity['activity_type'] === 'Hired'): ?>
                                                                        <i class="feather icon-user-plus text-success"></i>
                                                                    <?php elseif ($activity['activity_type'] === 'Terminated'): ?>
                                                                        <i class="feather icon-user-x text-danger"></i>
                                                                    <?php else: ?>
                                                                        <i class="feather icon-user-check text-primary"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="activity-content">
                                                                    <p class="mb-0">
                                                                        <strong><?php echo htmlspecialchars($activity['name']); ?></strong>
                                                                        <?php echo __('was'); ?>
                                                                        <span class="badge badge-<?php echo $activity['activity_type'] === 'Terminated' ? 'danger' : ($activity['activity_type'] === 'Hired' ? 'success' : 'primary'); ?>">
                                                                            <?php echo __($activity['activity_type'] === 'Terminated' ? 'terminated' : ($activity['activity_type'] === 'Hired' ? 'hired' : 'active')); ?>
                                                                        </span>
                                                                    </p>
                                                                    <small class="text-muted">
                                                                        <?php echo $activity['activity_date'] ? date('M d, Y', strtotime($activity['activity_date'])) : 'N/A'; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Tasks -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-alert-circle mr-2"></i><?php echo __('pending_hr_tasks'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <?php echo __('pending_salary_payments'); ?>: <strong><?php echo $pending_data['pending_salaries'] ?? 0; ?></strong>
                                                    <a href="salary_management.php" class="btn btn-sm btn-primary ml-2"><?php echo __('process_now'); ?></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<style>
    .stat-card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-card .card-body {
        display: flex;
        align-items: center;
        padding: 20px;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        margin-right: 15px;
    }

    .stat-content h3 {
        margin: 0;
        font-size: 28px;
        font-weight: bold;
        color: #333;
    }

    .stat-content p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }

    .activity-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .activity-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 18px;
    }

    .activity-content p {
        font-size: 14px;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-danger {
        background-color: #dc3545;
    }

    .badge-primary {
        background-color: #007bff;
    }
</style>

<?php include '../includes/admin_footer.php'; ?>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>