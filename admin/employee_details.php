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

// Check if employee ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: employee_management.php');
    exit();
}

$employee_id = intval($_GET['id']);

// Get employee details
$stmt = $pdo->prepare("
    SELECT u.*, sm.base_salary, sm.currency as salary_currency, sm.status as salary_status
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.role != 'super_admin'
");
$stmt->execute([$employee_id, $tenant_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employee_management.php');
    exit();
}

// Get termination history if applicable
$termination_history = [];
if ($employee['fired']) {
    try {
        $term_stmt = $pdo->prepare("
            SELECT * FROM employee_terminations
            WHERE employee_id = ? AND tenant_id = ?
            ORDER BY termination_date DESC
            LIMIT 1
        ");
        $term_stmt->execute([$employee_id, $tenant_id]);
        $termination_history = $term_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet
        $termination_history = null;
    }
}

// Get activity log for this employee
$activity_query = "
    SELECT al.*, u.name as performed_by_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.record_id = ? AND al.table_name = 'users' AND al.tenant_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
";
$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute([$employee_id, $tenant_id]);
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('employee_details');
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
                                        <h1><i class="feather icon-user mr-2"></i><?php echo __('employee_details'); ?></h1>
                                        <p><?php echo __('detailed_information_about'); ?> <?php echo htmlspecialchars($employee['name']); ?></p>
                                    </div>
                                    <div class="page-header-actions">
                                        <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary">
                                            <i class="feather icon-edit mr-1"></i><?php echo __('edit_employee'); ?>
                                        </a>
                                        <a href="employee_management.php" class="btn btn-outline-secondary">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_employee_management'); ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Employee Profile Card -->
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <img src="<?php echo htmlspecialchars($employee['profile_pic'] ?: '../assets/images/user/avatar-1.jpg'); ?>"
                                                    class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                                                <h4><?php echo htmlspecialchars($employee['name']); ?></h4>
                                                <p class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></p>
                                                <div class="mb-3">
                                                    <?php if ($employee['fired']): ?>
                                                        <span class="badge badge-danger badge-lg"><?php echo __('terminated'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success badge-lg"><?php echo __('active'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Quick Actions -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('quick_actions'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-grid gap-2">
                                                    <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="feather icon-edit mr-1"></i><?php echo __('edit_information'); ?>
                                                    </a>
                                                    <?php if (!$employee['fired']): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                                onclick="terminateEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                            <i class="feather icon-user-x mr-1"></i><?php echo __('terminate_employee'); ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-success"
                                                                onclick="reinstateEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                            <i class="feather icon-user-check mr-1"></i><?php echo __('reinstate_employee'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Employee Information -->
                                    <div class="col-md-8">
                                        <!-- Basic Information -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5><?php echo __('basic_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('full_name'); ?></label>
                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($employee['name']); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('email'); ?></label>
                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($employee['email']); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('phone'); ?></label>
                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($employee['phone'] ?: '-'); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('role'); ?></label>
                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('hire_date'); ?></label>
                                                            <p class="form-control-plaintext">
                                                                <?php echo $employee['hire_date'] ? date('F d, Y', strtotime($employee['hire_date'])) : '-'; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('account_created'); ?></label>
                                                            <p class="form-control-plaintext"><?php echo date('F d, Y', strtotime($employee['created_at'])); ?></p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label><?php echo __('address'); ?></label>
                                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($employee['address'] ?: '-'); ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Salary Information -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5><?php echo __('salary_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($employee['base_salary']): ?>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label><?php echo __('base_salary'); ?></label>
                                                                <p class="form-control-plaintext">
                                                                    <strong><?php echo number_format($employee['base_salary'], 2); ?> <?php echo htmlspecialchars($employee['salary_currency']); ?></strong>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label><?php echo __('salary_status'); ?></label>
                                                                <p class="form-control-plaintext">
                                                                    <span class="badge badge-<?php echo $employee['salary_status'] === 'active' ? 'success' : 'warning'; ?>">
                                                                        <?php echo ucfirst($employee['salary_status']); ?>
                                                                    </span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="feather icon-info mr-2"></i><?php echo __('no_salary_information_available'); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Employment Status -->
                                        <?php if ($employee['fired']): ?>
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5><?php echo __('termination_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('termination_date'); ?></label>
                                                            <p class="form-control-plaintext">
                                                                <?php echo $employee['fired_at'] ? date('F d, Y', strtotime($employee['fired_at'])) : '-'; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <?php if ($termination_history): ?>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo __('terminated_by'); ?></label>
                                                            <p class="form-control-plaintext">
                                                                <?php
                                                                // Get terminator name
                                                                $terminator_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                                                $terminator_stmt->execute([$termination_history['terminated_by']]);
                                                                $terminator = $terminator_stmt->fetch(PDO::FETCH_ASSOC);
                                                                echo htmlspecialchars($terminator['name'] ?? 'Unknown');
                                                                ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label><?php echo __('termination_reason'); ?></label>
                                                            <p class="form-control-plaintext"><?php echo htmlspecialchars($termination_history['termination_reason']); ?></p>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Activity Log -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('recent_activity'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($activities)): ?>
                                                    <p class="text-muted"><?php echo __('no_recent_activity'); ?></p>
                                                <?php else: ?>
                                                    <div class="timeline">
                                                        <?php foreach ($activities as $activity): ?>
                                                            <div class="timeline-item">
                                                                <div class="timeline-marker bg-primary"></div>
                                                                <div class="timeline-content">
                                                                    <h6><?php echo htmlspecialchars($activity['action']); ?></h6>
                                                                    <p><?php echo htmlspecialchars($activity['performed_by_name'] ?? 'System'); ?></p>
                                                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Termination Modal -->
                            <div class="modal fade" id="terminationModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo __('terminate_employee'); ?></h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <form id="terminationForm">
                                            <div class="modal-body">
                                                <input type="hidden" id="terminateEmployeeId" name="employee_id">
                                                <div class="form-group">
                                                    <label><?php echo __('employee_name'); ?></label>
                                                    <p id="terminateEmployeeName" class="font-weight-bold"></p>
                                                </div>
                                                <div class="form-group">
                                                    <label for="termination_reason"><?php echo __('termination_reason'); ?></label>
                                                    <textarea class="form-control" id="termination_reason" name="reason" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                                                <button type="submit" class="btn btn-danger"><?php echo __('terminate_employee'); ?></button>
                                            </div>
                                        </form>
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

<script>
function terminateEmployee(employeeId, employeeName) {
    $('#terminateEmployeeId').val(employeeId);
    $('#terminateEmployeeName').text(employeeName);
    $('#terminationModal').modal('show');
}

function reinstateEmployee(employeeId, employeeName) {
    if (confirm('<?php echo __('confirm_reinstate_employee'); ?>'.replace('{name}', employeeName))) {
        $.post('terminate_employee.php', {
            employee_id: employeeId,
            action: 'reinstate',
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || '<?php echo __('error_occurred'); ?>');
            }
        })
        .fail(function() {
            alert('<?php echo __('error_occurred'); ?>');
        });
    }
}

$('#terminationForm').on('submit', function(e) {
    e.preventDefault();

    $.post('terminate_employee.php', {
        employee_id: $('#terminateEmployeeId').val(),
        reason: $('#termination_reason').val(),
        action: 'terminate',
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    })
    .done(function(response) {
        if (response.success) {
            $('#terminationModal').modal('hide');
            location.reload();
        } else {
            alert(response.message || '<?php echo __('error_occurred'); ?>');
        }
    })
    .fail(function() {
        alert('<?php echo __('error_occurred'); ?>');
    });
});
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

.timeline-content h6 {
    margin-bottom: 5px;
    color: #495057;
}

.timeline-content p {
    margin-bottom: 5px;
    color: #6c757d;
}

.badge-lg {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}
</style>

<?php include '../includes/admin_footer.php'; ?>