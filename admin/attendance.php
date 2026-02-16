<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to user's local time
date_default_timezone_set('Asia/Kabul');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get today's attendance record
$stmt = $pdo->prepare("
    SELECT * FROM attendance
    WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND date = CURDATE()
");
$stmt->execute([$tenant_id, $branch_id, $user_id]);
$today_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance settings
$stmt = $pdo->prepare("
    SELECT * FROM attendance_settings
    WHERE tenant_id = ? AND branch_id = ?
");
$stmt->execute([$tenant_id, $branch_id]);
$attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If no settings exist, create default ones
if (!$attendance_settings) {
    $stmt = $pdo->prepare("
        INSERT INTO attendance_settings (tenant_id, branch_id, office_start_time, office_end_time, late_after_minutes, half_day_minutes, working_days)
        VALUES (?, ?, '09:00:00', '17:00:00', 15, 240, 'Mon-Fri')
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $attendance_settings = [
        'office_start_time' => '09:00:00',
        'office_end_time' => '17:00:00',
        'late_after_minutes' => 15,
        'half_day_minutes' => 240,
        'working_days' => 'Mon-Fri'
    ];
}

$page_title = __('attendance');
include '../includes/header.php';
?>

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

.badge-primary {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-success {
    background-color: #28a745;
}

.status-present{
    background-color: #28a745;
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.status-absent{
    background-color: #f44336;
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
}

.badge-primary {
    background-color: #007bff;
}

.table-responsive {
    border-radius: 10px;

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

/* Attendance-specific styles */
.attendance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 15px;
    color: white;
    transition: all 0.3s ease;
}

.attendance-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.check-in-btn {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    border: none;
    border-radius: 50px;
    padding: 15px 30px;
    font-size: 18px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.check-in-btn:hover {
    background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
    transform: scale(1.05);
}

.check-out-btn {
    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
    border: none;
    border-radius: 50px;
    padding: 15px 30px;
    font-size: 18px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.check-out-btn:hover {
    background: linear-gradient(135deg, #d32f2f 0%, #f44336 100%);
    transform: scale(1.05);
}

.status-present {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
}

.status-late {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
}

.status-half-day {
    background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
}

.status-absent {
    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
}

.time-display {
    font-size: 2rem;
    font-weight: bold;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.working-hours {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 15px;
    margin: 15px 0;
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
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0"><i class="feather icon-clock mr-2"></i><?php echo __('attendance_system'); ?></h5>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="badge-primary"><?php echo date('l, F j, Y'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Current Time and Status -->
                                <div class="col-md-6">
                                    <div class="card attendance-card">
                                        <div class="card-body text-center">
                                            <h5 class="card-title"><?php echo __('current_time'); ?></h5>
                                            <div class="time-display" id="current-time">
                                                <?php echo date('h:i:s A'); ?>
                                            </div>
                                            <div class="mt-3">
                                                <?php if ($today_attendance): ?>
                                                    <?php if ($today_attendance['check_out_time']): ?>
                                                        <span class="status-present"><?php echo __('checked_out'); ?></span>
                                                    <?php else: ?>
                                                        <span class="status-present"><?php echo __('checked_in'); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-absent"><?php echo __('not_checked_in'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Office Hours -->
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo __('office_hours'); ?></h5>
                                            <div class="working-hours">
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <h6><?php echo __('start_time'); ?></h6>
                                                        <span class="h4"><?php echo date('h:i A', strtotime($attendance_settings['office_start_time'])); ?></span>
                                                    </div>
                                                    <div class="col-6">
                                                        <h6><?php echo __('end_time'); ?></h6>
                                                        <span class="h4"><?php echo date('h:i A', strtotime($attendance_settings['office_end_time'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-muted small"><?php echo __('working_days'); ?>: <?php echo $attendance_settings['working_days']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Check In/Out Buttons -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <?php if (!$today_attendance): ?>
                                                <!-- Not checked in yet -->
                                                <button type="button" class="btn check-in-btn" onclick="checkIn()">
                                                    <i class="feather icon-log-in mr-2"></i><?php echo __('check_in'); ?>
                                                </button>
                                            <?php elseif (!$today_attendance['check_out_time']): ?>
                                                <!-- Checked in but not checked out -->
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="alert alert-success">
                                                            <h6><?php echo __('check_in_time'); ?></h6>
                                                            <span class="h5"><?php echo date('H:i:s', strtotime($today_attendance['check_in_time'])); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <button type="button" class="btn check-out-btn" onclick="checkOut()">
                                                            <i class="feather icon-log-out mr-2"></i><?php echo __('check_out'); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- Already checked out -->
                                                <div class="alert alert-info">
                                                    <h6><?php echo __('attendance_complete'); ?></h6>
                                                    <p><?php echo __('check_in'); ?>: <?php echo date('H:i:s', strtotime($today_attendance['check_in_time'])); ?></p>
                                                    <p><?php echo __('check_out'); ?>: <?php echo date('H:i:s', strtotime($today_attendance['check_out_time'])); ?></p>
                                                    <p><?php echo __('working_minutes'); ?>: <?php echo $today_attendance['working_minutes']; ?> <?php echo __('minutes'); ?></p>
                                                    <span class="status-<?php echo strtolower($today_attendance['status']); ?>"><?php echo __($today_attendance['status']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Today's Attendance Details -->
                            <?php if ($today_attendance): ?>
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><?php echo __('todays_attendance'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <h6><?php echo __('check_in'); ?></h6>
                                                        <span class="h5"><?php echo $today_attendance['check_in_time'] ? date('H:i:s', strtotime($today_attendance['check_in_time'])) : '-'; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <h6><?php echo __('check_out'); ?></h6>
                                                        <span class="h5"><?php echo $today_attendance['check_out_time'] ? date('H:i:s', strtotime($today_attendance['check_out_time'])) : '-'; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <h6><?php echo __('working_minutes'); ?></h6>
                                                        <span class="h5"><?php echo $today_attendance['working_minutes']; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-center">
                                                        <h6><?php echo __('status'); ?></h6>
                                                        <span class="badge status-<?php echo strtolower($today_attendance['status']); ?>"><?php echo __($today_attendance['status']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour12: true,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('current-time').textContent = timeString;
}

// Update time every second
setInterval(updateTime, 1000);

function checkIn() {
    if (confirm('<?php echo __('confirm_check_in'); ?>')) {
        fetch('../api/attendance/process_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_in'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || '<?php echo __('error_occurred'); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('error_occurred'); ?>');
        });
    }
}

function checkOut() {
    if (confirm('<?php echo __('confirm_check_out'); ?>')) {
        fetch('../api/attendance/process_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_out'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || '<?php echo __('error_occurred'); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('error_occurred'); ?>');
        });
    }
}
</script>
<!-- Required Js -->

    <script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>