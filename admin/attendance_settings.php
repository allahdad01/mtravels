<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get tenant and branch from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}



// Get current settings FIRST (before handling POST)
$attendance_settings = null;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_settings
        WHERE tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $attendance_settings = null;
}

// Only use defaults if no settings were found
if (empty($attendance_settings)) {
    echo "<div class='alert alert-info'>No settings found in database. Using default values. Please save the form to create settings for your branch.</div>";
    $attendance_settings = [
        'office_start_time' => '09:00',
        'office_end_time' => '17:00',
        'late_after_minutes' => 15,
        'half_day_minutes' => 240,
        'working_days' => 'Mon-Fri'
    ];
} 

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office_start_time = $_POST['office_start_time'] ?? '09:00';
    $office_end_time = $_POST['office_end_time'] ?? '17:00';
    $late_after_minutes = (int)($_POST['late_after_minutes'] ?? 15);
    $half_day_minutes = (int)($_POST['half_day_minutes'] ?? 240);
    $working_days = $_POST['working_days'] ?? 'Mon-Fri';

    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM attendance_settings WHERE tenant_id = ? AND branch_id = ?");
    $stmt->execute([$tenant_id, $branch_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    try {
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE attendance_settings 
                SET office_start_time = ?,
                    office_end_time = ?,
                    late_after_minutes = ?,
                    half_day_minutes = ?,
                    working_days = ?,
                    updated_at = NOW()
                WHERE tenant_id = ? AND branch_id = ?
            ");
            $stmt->execute([
                $office_start_time, 
                $office_end_time, 
                $late_after_minutes, 
                $half_day_minutes, 
                $working_days,
                $tenant_id, 
                $branch_id
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO attendance_settings 
                (tenant_id, branch_id, office_start_time, office_end_time, late_after_minutes, half_day_minutes, working_days)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, 
                $branch_id, 
                $office_start_time, 
                $office_end_time, 
                $late_after_minutes, 
                $half_day_minutes, 
                $working_days
            ]);
        }
        
        $success_message = __('settings_updated_successfully');
        
        // Re-fetch settings after update
        $stmt = $pdo->prepare("
            SELECT * FROM attendance_settings
            WHERE tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$tenant_id, $branch_id]);
        $attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error updating settings: " . $e->getMessage());
        $error_message = __('error_updating_settings') . ': ' . $e->getMessage();
    }
}

$page_title = __('attendance_settings');
include '../includes/header.php';

?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0"><i class="feather icon-settings mr-2"></i><?php echo __('attendance_settings'); ?></h5>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="manage_attendance.php" class="btn btn-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_attendance'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="feather icon-check-circle mr-2"></i><?php echo $success_message; ?>
                                    <button type="button" class="close" data-dismiss="alert">
                                        <span>&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="feather icon-alert-circle mr-2"></i><?php echo $error_message; ?>
                                    <button type="button" class="close" data-dismiss="alert">
                                        <span>&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><?php echo __('configure_attendance_rules'); ?></h5>
                                            <small class="text-muted"><?php echo __('these_settings_apply_to_all_employees_in_this_branch'); ?></small>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="office_start_time"><?php echo __('office_start_time'); ?> *</label>
                                                            <input type="time" class="form-control" id="office_start_time" name="office_start_time"
                                                                   value="<?php echo htmlspecialchars($attendance_settings['office_start_time'] ?? '09:00'); ?>" required>
                                                            <small class="form-text text-muted"><?php echo __('when_office_opens'); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="office_end_time"><?php echo __('office_end_time'); ?> *</label>
                                                            <input type="time" class="form-control" id="office_end_time" name="office_end_time"
                                                                   value="<?php echo htmlspecialchars($attendance_settings['office_end_time'] ?? '17:00'); ?>" required>
                                                            <small class="form-text text-muted"><?php echo __('when_office_closes'); ?></small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="late_after_minutes"><?php echo __('late_after_minutes'); ?> *</label>
                                                            <input type="number" class="form-control" id="late_after_minutes" name="late_after_minutes"
                                                                   value="<?php echo htmlspecialchars($attendance_settings['late_after_minutes'] ?? 15); ?>" min="0" max="480" required>
                                                            <small class="form-text text-muted"><?php echo __('minutes_after_start_to_be_considered_late'); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="half_day_minutes"><?php echo __('half_day_minutes'); ?> *</label>
                                                            <input type="number" class="form-control" id="half_day_minutes" name="half_day_minutes"
                                                                   value="<?php echo htmlspecialchars($attendance_settings['half_day_minutes'] ?? 240); ?>" min="0" max="480" required>
                                                            <small class="form-text text-muted"><?php echo __('minimum_minutes_for_half_day'); ?></small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label for="working_days"><?php echo __('working_days'); ?> *</label>
                                                    <select class="form-control" id="working_days" name="working_days" required>
                                                        <option value="Mon-Fri" <?php echo (($attendance_settings['working_days'] ?? 'Mon-Fri') === 'Mon-Fri') ? 'selected' : ''; ?>>Monday - Friday</option>
                                                        <option value="Mon-Sat" <?php echo (($attendance_settings['working_days'] ?? 'Mon-Fri') === 'Mon-Sat') ? 'selected' : ''; ?>>Monday - Saturday</option>
                                                        <option value="Sat-Thu" <?php echo (($attendance_settings['working_days'] ?? 'Mon-Fri') === 'Sat-Thu') ? 'selected' : ''; ?>>Saturday - Thursday</option>
                                                        <option value="Mon-Sun" <?php echo (($attendance_settings['working_days'] ?? 'Mon-Fri') === 'Mon-Sun') ? 'selected' : ''; ?>>Monday - Sunday</option>
                                                    </select>
                                                    <small class="form-text text-muted"><?php echo __('days_considered_for_attendance'); ?></small>
                                                </div>

                                                <div class="form-group mt-4">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="feather icon-save mr-2"></i><?php echo __('save_settings'); ?>
                                                    </button>
                                                    <a href="manage_attendance.php" class="btn btn-secondary ml-2">
                                                        <i class="feather icon-x mr-2"></i><?php echo __('cancel'); ?>
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><?php echo __('current_settings_summary'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="summary-item mb-3">
                                                <strong><?php echo __('office_hours'); ?>:</strong><br>
                                                <span class="badge badge-primary">
                                                    <?php echo date('H:i', strtotime($attendance_settings['office_start_time'] ?? '09:00')); ?> -
                                                    <?php echo date('H:i', strtotime($attendance_settings['office_end_time'] ?? '17:00')); ?>
                                                </span>
                                            </div>

                                            <div class="summary-item mb-3">
                                                <strong><?php echo __('expected_working_hours'); ?>:</strong><br>
                                                <?php
                                                $start = strtotime($attendance_settings['office_start_time'] ?? '09:00');
                                                $end = strtotime($attendance_settings['office_end_time'] ?? '17:00');
                                                $expected_hours = round(($end - $start) / 3600, 2);
                                                echo $expected_hours . ' ' . __('hours');
                                                ?>
                                            </div>

                                            <div class="summary-item mb-3">
                                                <strong><?php echo __('late_threshold'); ?>:</strong><br>
                                                <?php echo ($attendance_settings['late_after_minutes'] ?? 15); ?> <?php echo __('minutes_after_start'); ?>
                                            </div>

                                            <div class="summary-item mb-3">
                                                <strong><?php echo __('half_day_threshold'); ?>:</strong><br>
                                                <?php echo round(($attendance_settings['half_day_minutes'] ?? 240) / 60, 1); ?> <?php echo __('hours'); ?>
                                                (<?php echo ($attendance_settings['half_day_minutes'] ?? 240); ?> <?php echo __('minutes'); ?>)
                                            </div>

                                            <div class="summary-item">
                                                <strong><?php echo __('working_days'); ?>:</strong><br>
                                                <?php echo ($attendance_settings['working_days'] ?? 'Mon-Fri'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mt-3">
                                        <div class="card-header">
                                            <h5><?php echo __('attendance_logic'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="logic-explanation">
                                                <h6><?php echo __('how_status_is_calculated'); ?>:</h6>
                                                <ul class="small">
                                                    <li><strong><?php echo __('present'); ?>:</strong> <?php echo __('worked_full_day_or_more'); ?></li>
                                                    <li><strong><?php echo __('half_day'); ?>:</strong> <?php echo __('worked_more_than_half_day'); ?></li>
                                                    <li><strong><?php echo __('absent'); ?>:</strong> <?php echo __('worked_less_than_half_day'); ?></li>
                                                </ul>
                                                <hr>
                                                <p class="small text-muted">
                                                    <?php echo __('status_calculated_automatically_on_checkout'); ?>
                                                </p>
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

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.progress {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.progress-bar {
    transition: width 0.6s ease;
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

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
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

.btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.btn-secondary {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.summary-item {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.logic-explanation ul {
    padding-left: 20px;
}

.logic-explanation li {
    margin-bottom: 5px;
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<?php include '../includes/admin_footer.php'; ?>