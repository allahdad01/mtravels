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
require_permission('hr.reports');

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
<style>
    /* Page Header */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 30px;
        padding: 25px !important;
        box-shadow: 0 4px 20px rgba(64, 153, 255, 0.15);
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-size: 1.75rem;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    /* Stat Cards with Gradients */
    .stat-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        position: relative;
        min-height: 150px;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(30px, -30px);
    }

    .stat-card.primary-stat {
        background: linear-gradient(135deg, #4099ff 0%, #357abd 100%);
    }

    .stat-card.success-stat {
        background: linear-gradient(135deg, #2ed8b6 0%, #1fa889 100%);
    }

    .stat-card.danger-stat {
        background: linear-gradient(135deg, #f74871 0%, #d63650 100%);
    }

    .stat-card.info-stat {
        background: linear-gradient(135deg, #7c3aed 0%, #5a2d91 100%);
    }

    .stat-card .card-body {
        display: flex;
        align-items: center;
        padding: 25px;
        position: relative;
        z-index: 1;
        color: white;
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 28px;
        margin-right: 20px;
        background: rgba(255, 255, 255, 0.2);
        flex-shrink: 0;
    }

    .stat-content h3 {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
        color: white;
        line-height: 1;
    }

    .stat-content p {
        font-size: 0.95rem;
        margin: 8px 0 0 0;
        opacity: 0.95;
        color: white;
        font-weight: 500;
    }

    /* Report Action Cards */
    .report-action-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .report-action-card {
        background: white;
        border: 2px solid #e8ecef;
        border-radius: 12px;
        padding: 25px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .report-action-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #4099ff, #2ed8b6);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }

    .report-action-card:hover {
        border-color: #4099ff;
        box-shadow: 0 8px 24px rgba(64, 153, 255, 0.12);
        transform: translateY(-4px);
    }

    .report-action-card:hover::before {
        transform: scaleX(1);
    }

    .report-action-card .card-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        color: #4099ff;
        transition: all 0.3s ease;
    }

    .report-action-card:hover .card-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .report-action-card h6 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 15px 0 8px;
        color: #2c3e50;
    }

    .report-action-card p {
        font-size: 0.9rem;
        color: #7f8c8d;
        margin: 0;
    }

    .report-action-card.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: #f8f9fa;
        border-color: #dee2e6;
    }

    .report-action-card.disabled:hover {
        border-color: #dee2e6;
        box-shadow: none;
        transform: none;
    }

    /* Progress Bars in Tables */
    .progress-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .progress {
        flex: 1;
        height: 8px;
        border-radius: 4px;
        background: #e8ecef;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease;
        background: linear-gradient(90deg, #4099ff, #2ed8b6);
    }

    .percentage-badge {
        font-weight: 600;
        font-size: 0.9rem;
        color: #4099ff;
        min-width: 45px;
        text-align: right;
    }

    /* Tenure Analysis Cards */
    .tenure-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .tenure-metric-card {
        background: white;
        border: 1px solid #e8ecef;
        border-radius: 12px;
        padding: 25px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .tenure-metric-card:hover {
        border-color: #4099ff;
        box-shadow: 0 4px 16px rgba(64, 153, 255, 0.1);
    }

    .tenure-metric-card .metric-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
        display: inline-block;
    }

    .tenure-metric-card .metric-value {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 10px 0;
        color: #2c3e50;
    }

    .tenure-metric-card .metric-label {
        font-size: 0.95rem;
        color: #7f8c8d;
        font-weight: 500;
    }

    /* Filter Bar */
    .filter-bar {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .filter-bar h6 {
        margin-bottom: 15px;
        font-weight: 600;
        color: #2c3e50;
    }
</style>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                                <div class="main-content">
                                    <div class="page-header card">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h5 class="mb-0"><i class="feather icon-file-text mr-2"></i><?php echo __('hr_reports'); ?></h5>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <a href="hr.php" class="btn btn-outline-secondary btn-sm">
                                                    <i class="feather icon-arrow-left mr-1"></i><?php echo __('back'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Report Actions - Icon Cards -->
                                    <div class="report-action-cards">
                                        <div class="report-action-card" onclick="generateReport('employee_overview')">
                                            <i class="feather icon-users card-icon"></i>
                                            <h6><?php echo __('employee_overview_report'); ?></h6>
                                            <p><?php echo __('view_all_employee_data'); ?></p>
                                        </div>
                                        <div class="report-action-card <?php echo empty($termination_reasons) ? 'disabled' : ''; ?>" onclick="<?php echo empty($termination_reasons) ? 'showTerminationTooltip()' : "generateReport('termination_summary')"; ?>">
                                            <i class="feather icon-user-x card-icon"></i>
                                            <h6><?php echo __('termination_summary'); ?></h6>
                                            <p><?php echo empty($termination_reasons) ? __('requires_migration') : __('view_termination_data'); ?></p>
                                        </div>
                                        <div class="report-action-card" onclick="generateReport('role_distribution')">
                                            <i class="feather icon-pie-chart card-icon"></i>
                                            <h6><?php echo __('role_distribution_report'); ?></h6>
                                            <p><?php echo __('analyze_role_breakdown'); ?></p>
                                        </div>
                                        <div class="report-action-card" onclick="generateReport('tenure_analysis')">
                                            <i class="feather icon-trending-up card-icon"></i>
                                            <h6><?php echo __('tenure_analysis'); ?></h6>
                                            <p><?php echo __('examine_employee_tenure'); ?></p>
                                        </div>
                                    </div>

                                    <!-- Statistics Cards with Gradients -->
                                    <div class="row mb-4">
                                        <div class="col-md-6 col-lg-3">
                                            <div class="card stat-card primary-stat">
                                                <div class="card-body">
                                                    <div class="stat-icon">
                                                        <i class="feather icon-users"></i>
                                                    </div>
                                                    <div class="stat-content">
                                                        <h3><?php echo $stats['total_employees'] ?? 0; ?></h3>
                                                        <p><?php echo __('total_employees'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-3">
                                            <div class="card stat-card success-stat">
                                                <div class="card-body">
                                                    <div class="stat-icon">
                                                        <i class="feather icon-user-check"></i>
                                                    </div>
                                                    <div class="stat-content">
                                                        <h3><?php echo $stats['active_employees'] ?? 0; ?></h3>
                                                        <p><?php echo __('active_employees'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-3">
                                            <div class="card stat-card danger-stat">
                                                <div class="card-body">
                                                    <div class="stat-icon">
                                                        <i class="feather icon-user-x"></i>
                                                    </div>
                                                    <div class="stat-content">
                                                        <h3><?php echo $stats['terminated_employees'] ?? 0; ?></h3>
                                                        <p><?php echo __('terminated_employees'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-3">
                                            <div class="card stat-card info-stat">
                                                <div class="card-body">
                                                    <div class="stat-icon">
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
                                                         <div class="role-distribution-list">
                                                             <?php
                                                             $total_active = array_sum(array_column($role_distribution, 'count'));
                                                             foreach ($role_distribution as $role_data):
                                                                 $percentage = $total_active > 0 ? round(($role_data['count'] / $total_active) * 100, 1) : 0;
                                                             ?>
                                                                 <div class="role-item mb-3">
                                                                     <div class="d-flex justify-content-between align-items-center mb-2">
                                                                         <span class="role-name" style="font-weight: 500; color: #2c3e50;"><?php echo htmlspecialchars(ucfirst($role_data['role'])); ?></span>
                                                                         <span class="role-count" style="font-size: 0.9rem; color: #7f8c8d;"><?php echo $role_data['count']; ?> <?php echo __('employees'); ?></span>
                                                                     </div>
                                                                     <div class="progress-container">
                                                                         <div class="progress">
                                                                             <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                                                         </div>
                                                                         <span class="percentage-badge"><?php echo $percentage; ?>%</span>
                                                                     </div>
                                                                 </div>
                                                             <?php endforeach; ?>
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
                                                         <div class="termination-reasons-list">
                                                             <?php 
                                                             $total_terminations = array_sum(array_column($termination_reasons, 'count'));
                                                             foreach ($termination_reasons as $reason): 
                                                                 $term_percentage = $total_terminations > 0 ? round(($reason['count'] / $total_terminations) * 100, 1) : 0;
                                                             ?>
                                                                 <div class="termination-item mb-3">
                                                                     <div class="d-flex justify-content-between align-items-center mb-2">
                                                                         <span class="reason-name" style="font-weight: 500; color: #2c3e50;"><?php echo htmlspecialchars($reason['termination_reason']); ?></span>
                                                                         <span class="reason-count" style="font-size: 0.9rem; color: #7f8c8d;"><?php echo $reason['count']; ?> <?php echo __('cases'); ?></span>
                                                                     </div>
                                                                     <div class="progress-container">
                                                                         <div class="progress">
                                                                             <div class="progress-bar" style="width: <?php echo $term_percentage; ?>%"></div>
                                                                         </div>
                                                                         <span class="percentage-badge"><?php echo $term_percentage; ?>%</span>
                                                                     </div>
                                                                 </div>
                                                             <?php endforeach; ?>
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
                                                     <div class="tenure-metrics">
                                                         <div class="tenure-metric-card">
                                                             <div class="metric-icon text-primary">
                                                                 <i class="feather icon-calendar"></i>
                                                             </div>
                                                             <div class="metric-value text-primary"><?php echo round(($stats['avg_tenure_days'] ?? 0) / 30, 1); ?></div>
                                                             <div class="metric-label"><?php echo __('average_tenure_months'); ?></div>
                                                         </div>
                                                         <div class="tenure-metric-card">
                                                             <div class="metric-icon text-success">
                                                                 <i class="feather icon-user-plus"></i>
                                                             </div>
                                                             <div class="metric-value text-success"><?php echo $stats['new_hires_today'] ?? 0; ?></div>
                                                             <div class="metric-label"><?php echo __('new_hires_today'); ?></div>
                                                         </div>
                                                         <div class="tenure-metric-card">
                                                             <div class="metric-icon text-info">
                                                                 <i class="feather icon-trending-up"></i>
                                                             </div>
                                                             <div class="metric-value text-info"><?php echo $stats['new_hires_this_year'] ?? 0; ?></div>
                                                             <div class="metric-label"><?php echo __('new_hires_this_year'); ?></div>
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

function showTerminationTooltip() {
    showToast('<?php echo __('termination_data_not_available'); ?> - <?php echo __('run_migration_to_enable_termination_tracking'); ?>', 'info');
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
        url: '../api/employee/generate_hr_report.php',
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

function showToast(message, type = 'success') {
    // Simple toast implementation
    const toast = document.createElement('div');
    const alertClass = type === 'success' ? 'alert-success' : type === 'info' ? 'alert-info' : 'alert-danger';
    const icon = type === 'success' ? 'check' : 'alert-circle';
    
    toast.className = `alert ${alertClass} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    toast.innerHTML = `
        <i class="feather icon-${icon} mr-2"></i>
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

<?php include '../includes/admin_footer.php'; ?>