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

// Get attendance ID from URL
$attendance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$attendance_id) {
    header('Location: manage_attendance.php');
    exit();
}

// Get attendance record
$stmt = $pdo->prepare("
    SELECT a.*, u.name as user_name, u.email
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND a.tenant_id = ? AND a.branch_id = ?
");
$stmt->execute([$attendance_id, $tenant_id, $branch_id]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    header('Location: manage_attendance.php');
    exit();
}

// Get attendance settings for reference
$stmt = $pdo->prepare("
    SELECT * FROM attendance_settings
    WHERE tenant_id = ? AND branch_id = ?
");
$stmt->execute([$tenant_id, $branch_id]);
$attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = __('invalid_csrf_token');
    } else {
        $check_in_time = trim($_POST['check_in_time'] ?? '');
        $check_out_time = trim($_POST['check_out_time'] ?? '');
        $status = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        // Validation
        $errors = [];

        if (empty($status)) {
            $errors[] = __('status_required');
        }

        if (!empty($check_in_time) && !strtotime($check_in_time)) {
            $errors[] = __('invalid_check_in_time');
        }

        if (!empty($check_out_time) && !strtotime($check_out_time)) {
            $errors[] = __('invalid_check_out_time');
        }

        if (!empty($check_in_time) && !empty($check_out_time) && strtotime($check_out_time) <= strtotime($check_in_time)) {
            $errors[] = __('check_out_must_be_after_check_in');
        }

        if (empty($errors)) {
            try {
                // Calculate working minutes if both times are provided
                $working_minutes = 0;
                if (!empty($check_in_time) && !empty($check_out_time)) {
                    $check_in_timestamp = strtotime($check_in_time);
                    $check_out_timestamp = strtotime($check_out_time);
                    $working_minutes = round(($check_out_timestamp - $check_in_timestamp) / 60);
                }

                // Update attendance record
                $stmt = $pdo->prepare("
                    UPDATE attendance
                    SET check_in_time = ?, check_out_time = ?, working_minutes = ?, status = ?, notes = ?
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");

                $stmt->execute([
                    $check_in_time ?: null,
                    $check_out_time ?: null,
                    $working_minutes,
                    $status,
                    $notes,
                    $attendance_id,
                    $tenant_id,
                    $branch_id
                ]);

                // Log the action
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (
                        user_id, action, table_name, record_id,
                        old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
                    ) VALUES (?, 'update_attendance', 'attendance', ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");

                $old_values = json_encode([
                    'check_in_time' => $attendance['check_in_time'],
                    'check_out_time' => $attendance['check_out_time'],
                    'working_minutes' => $attendance['working_minutes'],
                    'status' => $attendance['status'],
                    'notes' => $attendance['notes']
                ]);

                $new_values = json_encode([
                    'check_in_time' => $check_in_time ?: null,
                    'check_out_time' => $check_out_time ?: null,
                    'working_minutes' => $working_minutes,
                    'status' => $status,
                    'notes' => $notes
                ]);

                $logStmt->execute([
                    $_SESSION['user_id'],
                    $attendance_id,
                    $old_values,
                    $new_values,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    $tenant_id,
                    $branch_id
                ]);

                $success = __('attendance_updated_successfully');

                // Refresh attendance data
                $stmt = $pdo->prepare("
                    SELECT a.*, u.name as user_name, u.email
                    FROM attendance a
                    JOIN users u ON a.user_id = u.id
                    WHERE a.id = ? AND a.tenant_id = ? AND a.branch_id = ?
                ");
                $stmt->execute([$attendance_id, $tenant_id, $branch_id]);
                $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $error = __('error_updating_attendance') . ': ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$page_title = __('edit_attendance');
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

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.status-present {
    background-color: #28a745;
    color: white;
}

.status-late {
    background-color: #ffc107;
    color: black;
}

.status-half-day {
    background-color: #17a2b8;
    color: white;
}

.status-absent {
    background-color: #dc3545;
    color: white;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
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
                                        <h5 class="mb-0"><i class="feather icon-edit mr-2"></i><?php echo __('edit_attendance'); ?></h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('edit_attendance_for'); ?> <?php echo htmlspecialchars($attendance['user_name']); ?> - <?php echo date('M d, Y', strtotime($attendance['date'])); ?></p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="manage_attendance.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_manage_attendance'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Current Attendance Info -->
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-info mr-2"></i><?php echo __('current_information'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="text-center mb-4">
                                                <h6><?php echo htmlspecialchars($attendance['user_name']); ?></h6>
                                                <p class="text-muted"><?php echo htmlspecialchars($attendance['email']); ?></p>
                                                <div class="mb-3">
                                                    <span class="badge status-<?php echo strtolower($attendance['status']); ?>">
                                                        <?php echo __($attendance['status']); ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <h6><?php echo __('check_in'); ?></h6>
                                                    <span class="h5"><?php echo $attendance['check_in_time'] ? date('H:i', strtotime($attendance['check_in_time'])) : '-'; ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <h6><?php echo __('check_out'); ?></h6>
                                                    <span class="h5"><?php echo $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : '-'; ?></span>
                                                </div>
                                            </div>

                                            <?php if ($attendance['working_minutes'] > 0): ?>
                                            <hr>
                                            <div class="text-center">
                                                <h6><?php echo __('working_minutes'); ?></h6>
                                                <span class="h4"><?php echo $attendance['working_minutes']; ?> min</span>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($attendance_settings): ?>
                                            <hr>
                                            <div class="text-center">
                                                <h6><?php echo __('office_hours'); ?></h6>
                                                <small><?php echo date('H:i', strtotime($attendance_settings['office_start_time'])); ?> - <?php echo date('H:i', strtotime($attendance_settings['office_end_time'])); ?></small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Form -->
                                <div class="col-md-8">
                                    <?php if (isset($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $error; ?>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (isset($success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $success; ?>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <?php endif; ?>

                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-edit-2 mr-2"></i><?php echo __('edit_attendance_details'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="update_attendance" value="1">

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="check_in_time"><i class="feather icon-log-in mr-2"></i><?php echo __('check_in_time'); ?></label>
                                                            <input type="time" class="form-control" id="check_in_time" name="check_in_time"
                                                                   value="<?php echo $attendance['check_in_time'] ? date('H:i', strtotime($attendance['check_in_time'])) : ''; ?>">
                                                            <small class="form-text text-muted"><?php echo __('leave_empty_if_not_checked_in'); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="check_out_time"><i class="feather icon-log-out mr-2"></i><?php echo __('check_out_time'); ?></label>
                                                            <input type="time" class="form-control" id="check_out_time" name="check_out_time"
                                                                   value="<?php echo $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : ''; ?>">
                                                            <small class="form-text text-muted"><?php echo __('leave_empty_if_not_checked_out'); ?></small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label for="status"><i class="feather icon-tag mr-2"></i><?php echo __('status'); ?> <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="status" name="status" required>
                                                        <option value=""><?php echo __('select_status'); ?></option>
                                                        <option value="present" <?php echo $attendance['status'] === 'present' ? 'selected' : ''; ?>><?php echo __('present'); ?></option>
                                                        <option value="late" <?php echo $attendance['status'] === 'late' ? 'selected' : ''; ?>><?php echo __('late'); ?></option>
                                                        <option value="half_day" <?php echo $attendance['status'] === 'half_day' ? 'selected' : ''; ?>><?php echo __('half_day'); ?></option>
                                                        <option value="absent" <?php echo $attendance['status'] === 'absent' ? 'selected' : ''; ?>><?php echo __('absent'); ?></option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="notes"><i class="feather icon-file-text mr-2"></i><?php echo __('notes'); ?></label>
                                                    <textarea class="form-control" id="notes" name="notes" rows="3"
                                                              placeholder="<?php echo __('add_notes_here'); ?>"><?php echo htmlspecialchars($attendance['notes'] ?? ''); ?></textarea>
                                                </div>

                                                <div class="form-group mt-4">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="feather icon-save mr-2"></i><?php echo __('update_attendance'); ?>
                                                    </button>
                                                    <a href="manage_attendance.php" class="btn btn-secondary ml-2">
                                                        <i class="feather icon-x mr-2"></i><?php echo __('cancel'); ?>
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Attendance Rules Reference -->
                                    <?php if ($attendance_settings): ?>
                                    <div class="card mt-3">
                                        <div class="card-header">
                                            <h5><i class="feather icon-info mr-2"></i><?php echo __('attendance_rules_reference'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6><?php echo __('office_hours'); ?></h6>
                                                    <p><?php echo date('H:i', strtotime($attendance_settings['office_start_time'])); ?> - <?php echo date('H:i', strtotime($attendance_settings['office_end_time'])); ?></p>

                                                    <h6><?php echo __('late_threshold'); ?></h6>
                                                    <p><?php echo $attendance_settings['late_after_minutes']; ?> <?php echo __('minutes_after_start'); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6><?php echo __('half_day_minimum'); ?></h6>
                                                    <p><?php echo $attendance_settings['half_day_minutes']; ?> <?php echo __('minutes'); ?></p>

                                                    <h6><?php echo __('working_days'); ?></h6>
                                                    <p><?php echo $attendance_settings['working_days']; ?></p>
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
    </div>
</div>

<script>
// Auto-calculate working minutes when times change
document.getElementById('check_in_time').addEventListener('change', updateWorkingMinutes);
document.getElementById('check_out_time').addEventListener('change', updateWorkingMinutes);

function updateWorkingMinutes() {
    const checkIn = document.getElementById('check_in_time').value;
    const checkOut = document.getElementById('check_out_time').value;

    if (checkIn && checkOut) {
        const checkInTime = new Date(`1970-01-01T${checkIn}:00`);
        const checkOutTime = new Date(`1970-01-01T${checkOut}:00`);

        if (checkOutTime > checkInTime) {
            const diffMinutes = Math.round((checkOutTime - checkInTime) / (1000 * 60));
            console.log(`Working minutes: ${diffMinutes}`);
        }
    }
}
</script>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>