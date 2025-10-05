<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query for employees
$query = "
    SELECT u.*, sm.base_salary, sm.currency as salary_currency
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id
    WHERE u.tenant_id = ? AND u.role != 'super_admin'
";

$params = [$tenant_id];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $query .= " AND u.fired = 0";
    } elseif ($status_filter === 'terminated') {
        $query .= " AND u.fired = 1";
    }
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('performance_reviews');
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
                                        <h1><i class="feather icon-trending-up mr-2"></i><?php echo __('performance_reviews'); ?></h1>
                                        <p><?php echo __('manage_employee_performance_reviews_and_evaluations'); ?></p>
                                    </div>
                                    <div class="page-header-actions">
                                        <a href="hr.php" class="btn btn-outline-secondary">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_hr'); ?>
                                        </a>
                                    </div>
                                </div>

                                <!-- Filters and Search -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <form method="GET" class="row g-3">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="search" placeholder="<?php echo __('search_employees'); ?>"
                                                    value="<?php echo htmlspecialchars($search); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-control" name="status">
                                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo __('all_statuses'); ?></option>
                                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                                    <option value="terminated" <?php echo $status_filter === 'terminated' ? 'selected' : ''; ?>><?php echo __('terminated'); ?></option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="feather icon-search mr-1"></i><?php echo __('search'); ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Performance Overview -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('performance_overview'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <?php echo __('performance_reviews_feature_coming_soon'); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo __('this_feature_will_include_employee_evaluations_goals_and_performance_tracking'); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Employees Table -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?php echo __('employees'); ?> (<?php echo count($employees); ?>)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($employees)): ?>
                                            <div class="text-center py-5">
                                                <i class="feather icon-users text-muted" style="font-size: 48px;"></i>
                                                <h5 class="mt-3"><?php echo __('no_employees_found'); ?></h5>
                                                <p class="text-muted"><?php echo __('try_adjusting_filters'); ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo __('employee'); ?></th>
                                                            <th><?php echo __('role'); ?></th>
                                                            <th><?php echo __('hire_date'); ?></th>
                                                            <th><?php echo __('status'); ?></th>
                                                            <th><?php echo __('performance_status'); ?></th>
                                                            <th><?php echo __('actions'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($employees as $employee): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="<?php echo htmlspecialchars($employee['profile_pic'] ?: '../assets/images/user/avatar-1.jpg'); ?>"
                                                                            class="rounded-circle mr-3" style="width: 40px; height: 40px; object-fit: cover;">
                                                                        <div>
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($employee['name']); ?></h6>
                                                                            <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-primary"><?php echo htmlspecialchars(ucfirst($employee['role'] ?: 'N/A')); ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php echo $employee['hire_date'] ? date('M d, Y', strtotime($employee['hire_date'])) : '-'; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($employee['fired']): ?>
                                                                        <span class="badge badge-danger"><?php echo __('terminated'); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-success"><?php echo __('active'); ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-secondary"><?php echo __('not_evaluated'); ?></span>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group">
                                                                        <button type="button" class="btn btn-sm btn-outline-primary" disabled title="<?php echo __('coming_soon'); ?>">
                                                                            <i class="feather icon-eye"></i> <?php echo __('view_review'); ?>
                                                                        </button>
                                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="<?php echo __('coming_soon'); ?>">
                                                                            <i class="feather icon-edit"></i> <?php echo __('add_review'); ?>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>