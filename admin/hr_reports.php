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

// Get HR statistics for reports
$stats_query = "
    SELECT
        COUNT(*) as total_employees,
        SUM(CASE WHEN fired = 0 THEN 1 ELSE 0 END) as active_employees,
        SUM(CASE WHEN fired = 1 THEN 1 ELSE 0 END) as terminated_employees,
        COUNT(DISTINCT CASE WHEN DATE(hire_date) = CURDATE() THEN id END) as new_hires_today,
        COUNT(DISTINCT CASE WHEN YEAR(hire_date) = YEAR(CURDATE()) THEN id END) as new_hires_this_year,
        AVG(DATEDIFF(CURDATE(), hire_date)) as avg_tenure_days
    FROM users
    WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND role != 'tenant_super_admin'
";

$stmt = $pdo->prepare($stats_query);
$stmt->execute([$tenant_id, $branch_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get employee distribution by role
$role_distribution_query = "
    SELECT role, COUNT(*) as count
    FROM users
    WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND role != 'tenant_super_admin' AND fired = 0
    GROUP BY role
    ORDER BY count DESC
";

$stmt = $pdo->prepare($role_distribution_query);
$stmt->execute([$tenant_id, $branch_id]);
$role_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get termination reasons (if employee_terminations table exists)
$termination_reasons = [];
try {
    $termination_query = "
        SELECT termination_reason, COUNT(*) as count
        FROM employee_terminations
        WHERE tenant_id = ? AND branch_id = ?
        GROUP BY termination_reason
        ORDER BY count DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($termination_query);
    $stmt->execute([$tenant_id, $branch_id]);
    $termination_reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
    $termination_reasons = [];
}

$page_title = __('hr_reports');
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
                                            <h1><i class="feather icon-file-text mr-2"></i><?php echo __('hr_reports'); ?></h1>
                                            <p><?php echo __('comprehensive_hr_reports_and_analytics'); ?></p>
                                        </div>
                                        <div class="page-header-actions">
                                            <a href="hr.php" class="btn btn-outline-secondary">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_hr'); ?>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Report Actions -->
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><?php echo __('report_actions'); ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <button class="btn btn-outline-primary btn-block" onclick="generateReport('employee_overview')">
                                                                <i class="feather icon-users mr-1"></i><?php echo __('employee_overview_report'); ?>
                                                            </button>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="btn btn-outline-success btn-block" onclick="generateReport('termination_summary')" <?php echo empty($termination_reasons) ? 'disabled' : ''; ?>>
                                                                <i class="feather icon-user-x mr-1"></i><?php echo __('termination_summary'); ?>
                                                            </button>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="btn btn-outline-info btn-block" onclick="generateReport('role_distribution')">
                                                                <i class="feather icon-pie-chart mr-1"></i><?php echo __('role_distribution_report'); ?>
                                                            </button>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button class="btn btn-outline-warning btn-block" onclick="generateReport('tenure_analysis')">
                                                                <i class="feather icon-trending-up mr-1"></i><?php echo __('tenure_analysis'); ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Statistics Cards -->
                                    <div class="row mb-4">
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
                                                        <h3><?php echo $stats['terminated_employees'] ?? 0; ?></h3>
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
                                                        <h3><?php echo $stats['new_hires_this_year'] ?? 0; ?></h3>
                                                        <p><?php echo __('new_hires_this_year'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Role Distribution -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><?php echo __('role_distribution'); ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <?php if (empty($role_distribution)): ?>
                                                        <p class="text-muted"><?php echo __('no_role_data_available'); ?></p>
                                                    <?php else: ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?php echo __('role'); ?></th>
                                                                        <th><?php echo __('count'); ?></th>
                                                                        <th><?php echo __('percentage'); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    $total_active = array_sum(array_column($role_distribution, 'count'));
                                                                    foreach ($role_distribution as $role_data):
                                                                        $percentage = $total_active > 0 ? round(($role_data['count'] / $total_active) * 100, 1) : 0;
                                                                    ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars(ucfirst($role_data['role'])); ?></td>
                                                                            <td><?php echo $role_data['count']; ?></td>
                                                                            <td><?php echo $percentage; ?>%</td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Termination Reasons -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><?php echo __('termination_reasons'); ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <?php if (empty($termination_reasons)): ?>
                                                        <div class="alert alert-info">
                                                            <i class="feather icon-info mr-2"></i>
                                                            <?php echo __('termination_data_not_available'); ?>
                                                            <br>
                                                            <small><?php echo __('run_migration_to_enable_termination_tracking'); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th><?php echo __('reason'); ?></th>
                                                                        <th><?php echo __('count'); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($termination_reasons as $reason): ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($reason['termination_reason']); ?></td>
                                                                            <td><?php echo $reason['count']; ?></td>
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

                                    <!-- Tenure Analysis -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><?php echo __('tenure_analysis'); ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h4 class="text-primary"><?php echo round(($stats['avg_tenure_days'] ?? 0) / 30, 1); ?></h4>
                                                                <p><?php echo __('average_tenure_months'); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h4 class="text-success"><?php echo $stats['new_hires_today'] ?? 0; ?></h4>
                                                                <p><?php echo __('new_hires_today'); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h4 class="text-info"><?php echo $stats['new_hires_this_year'] ?? 0; ?></h4>
                                                                <p><?php echo __('new_hires_this_year'); ?></p>
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
        </div>
    </div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('generate_report'); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="reportForm">
                <div class="modal-body">
                    <input type="hidden" id="reportType" name="report_type">
                    <div class="form-group">
                        <label><?php echo __('select_format'); ?></label>
                        <select class="form-control" name="format" required>
                            <option value="csv"><?php echo __('csv'); ?></option>
                            <option value="pdf"><?php echo __('pdf'); ?></option>
                            <option value="xlsx"><?php echo __('excel'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeCharts" name="include_charts">
                            <label class="form-check-label" for="includeCharts">
                                <?php echo __('include_charts_and_graphs'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-download mr-1"></i><?php echo __('generate_and_download'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<script>
function generateReport(reportType) {
    $('#reportType').val(reportType);

    // Set modal title based on report type
    const titles = {
        'employee_overview': '<?php echo __('employee_overview_report'); ?>',
        'termination_summary': '<?php echo __('termination_summary'); ?>',
        'role_distribution': '<?php echo __('role_distribution_report'); ?>',
        'tenure_analysis': '<?php echo __('tenure_analysis'); ?>'
    };

    $('.modal-title').text(titles[reportType] || '<?php echo __('generate_report'); ?>');
    $('#reportModal').modal('show');
}

$('#reportForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

    // Show loading state
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="feather icon-loader mr-1"></i><?php echo __('generating'); ?>...').prop('disabled', true);

    $.ajax({
        url: 'generate_hr_report.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;

                if (data.success) {
                    $('#reportModal').modal('hide');
                    showToast(data.message, 'success');

                    // Trigger download
                    if (data.download_url) {
                        setTimeout(() => {
                            window.location.href = data.download_url;
                        }, 1000);
                    }
                } else {
                    showToast(data.message || '<?php echo __('error_occurred'); ?>', 'error');
                }
            } catch (e) {
                showToast('<?php echo __('error_occurred'); ?>', 'error');
            }
        },
        error: function() {
            showToast('<?php echo __('error_occurred'); ?>', 'error');
        },
        complete: function() {
            // Reset button state
            submitBtn.html(originalText).prop('disabled', false);
        }
    });
});

function showToast(message, type) {
    // Simple toast implementation
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        <i class="feather icon-${type === 'success' ? 'check' : 'alert-circle'} mr-2"></i>
        ${message}
        <button type="button" class="close ml-2" onclick="this.parentElement.remove()">
            <span>&times;</span>
        </button>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}
</script>

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
</style>

<?php include '../includes/admin_footer.php'; ?>