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
require_permission('hr.attendance');

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
/* ===== VARIABLES ===== */
:root {
    --ma-primary: #4099ff;
    --ma-primary-light: #e3f2fd;
    --ma-secondary: #2ed8b6;
    --ma-success: #10b981;
    --ma-warning: #f59e0b;
    --ma-danger: #ef4444;
    --ma-info: #3b82f6;
    --ma-gray-50: #f9fafb;
    --ma-gray-100: #f3f4f6;
    --ma-gray-200: #e5e7eb;
    --ma-gray-300: #d1d5db;
    --ma-gray-400: #9ca3af;
    --ma-gray-500: #6b7280;
    --ma-gray-600: #4b5563;
    --ma-gray-700: #374151;
    --ma-gray-800: #1f2937;
    --ma-gray-900: #111827;
    --ma-radius: 12px;
    --ma-transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== LAYOUT ===== */
.pcoded-main-container {
    width: calc(100% - 264px);
    min-height: 100vh;
    background: var(--ma-gray-50);
    margin-right: 0;
}

.pcoded-wrapper, .pcoded-content, .pcoded-inner-content {
    width: 100%;
}

.main-body, .page-wrapper {
    width: 100%;
    min-height: auto;
}

.main-content {
    width: 100%;
    min-height: auto;
}

/* Collapsed sidebar state */
.pcoded-wrapper.navbar-collapsed .pcoded-main-container {
    width: calc(100% - 70px);
}

/* Mobile: full width */
@media (max-width: 991px) {
    .pcoded-main-container,
    .pcoded-wrapper.navbar-collapsed .pcoded-main-container {
        width: 100%;
    }
}

.main-content {
    padding: 32px 28px;
}

/* ===== HERO SECTION ===== */
.ma-hero {
    background: #fff;
    border-radius: var(--ma-radius);
    padding: 40px;
    margin-bottom: 32px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.ma-hero-bg {
    position: absolute;
    top: 0;
    right: 0;
    width: 400px;
    height: 100%;
    opacity: 0.05;
    pointer-events: none;
}

.ma-hero-circle {
    position: absolute;
    border-radius: 50%;
    background: var(--ma-primary);
}

.ma-hero-circle.c1 {
    width: 300px;
    height: 300px;
    top: -50px;
    right: -100px;
}

.ma-hero-circle.c2 {
    width: 150px;
    height: 150px;
    bottom: -30px;
    right: 50px;
}

.ma-hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ma-hero-left {
    flex: 1;
}

.ma-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--ma-primary-light);
    color: var(--ma-primary);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 12px;
}

.ma-hero-badge i {
    font-size: 16px;
}

.ma-hero h1 {
    font-size: 32px;
    font-weight: 700;
    color: var(--ma-gray-900);
    margin: 0 0 8px;
    line-height: 1.2;
}

.ma-hero p {
    color: var(--ma-gray-500);
    font-size: 16px;
    margin: 0;
    line-height: 1.5;
}

.ma-hero-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.ma-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: var(--ma-transition);
}

.ma-hero-btn.primary {
    background: var(--ma-primary);
    color: #fff;
}

.ma-hero-btn.primary:hover {
    background: #2d7dd9;
    transform: translateY(-2px);
}

.ma-hero-btn.secondary {
    background: var(--ma-gray-100);
    color: var(--ma-gray-700);
}

.ma-hero-btn.secondary:hover {
    background: var(--ma-gray-200);
}

.ma-hero-btn.outline {
    background: transparent;
    color: var(--ma-gray-600);
    border: 1px solid var(--ma-gray-300);
}

.ma-hero-btn.outline:hover {
    border-color: var(--ma-primary);
    color: var(--ma-primary);
    background: var(--ma-primary-light);
}

/* ===== FORM SECTION ===== */
.ma-form-card {
    background: #fff;
    border-radius: var(--ma-radius);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 24px;
    animation: fadeInUp 0.5s ease forwards;
}

.ma-form-header {
    background: linear-gradient(135deg, var(--ma-primary) 0%, var(--ma-secondary) 100%);
    color: #fff;
    padding: 20px 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 700;
}

.ma-form-header i {
    font-size: 20px;
}

.ma-form-body {
    padding: 28px;
}

.ma-form-group {
    margin-bottom: 24px;
}

.ma-form-group label {
    display: block;
    font-weight: 600;
    color: var(--ma-gray-700);
    margin-bottom: 8px;
    font-size: 14px;
}

.ma-form-group label i {
    color: var(--ma-primary);
    margin-right: 4px;
}

.ma-form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--ma-gray-300);
    border-radius: 8px;
    font-size: 14px;
    transition: var(--ma-transition);
    font-family: inherit;
}

.ma-form-control:focus {
    outline: none;
    border-color: var(--ma-primary);
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
}

.ma-form-small {
    display: block;
    color: var(--ma-gray-500);
    font-size: 12px;
    margin-top: 4px;
}

.ma-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--ma-gray-200);
}

.ma-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--ma-transition);
    text-decoration: none;
}

.ma-btn.primary {
    background: var(--ma-primary);
    color: #fff;
}

.ma-btn.primary:hover {
    background: #2d7dd9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.ma-btn.secondary {
    background: var(--ma-gray-200);
    color: var(--ma-gray-700);
}

.ma-btn.secondary:hover {
    background: var(--ma-gray-300);
}

/* ===== INFO CARD ===== */
.ma-info-card {
    background: #fff;
    border-radius: var(--ma-radius);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 24px;
}

.ma-info-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 20px 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
    font-weight: 700;
}

.ma-info-header i {
    font-size: 18px;
}

.ma-info-body {
    padding: 28px;
}

.ma-info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 20px;
}

.ma-info-item h6 {
    color: var(--ma-gray-500);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.ma-info-item p {
    color: var(--ma-gray-900);
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.ma-info-divider {
    height: 1px;
    background: var(--ma-gray-200);
    margin: 20px 0;
}

.ma-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.ma-badge.present {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.ma-badge.late {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.ma-badge.half {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.ma-badge.absent {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .main-content {
        padding: 24px 16px;
    }

    .ma-hero {
        padding: 28px;
    }

    .ma-hero-content {
        flex-direction: column;
        gap: 20px;
    }

    .ma-hero-actions {
        width: 100%;
        justify-content: center;
    }

    .ma-hero h1 {
        font-size: 24px;
    }

    .ma-info-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .ma-form-body {
        padding: 20px;
    }

    .ma-form-header {
        padding: 16px 20px;
    }

    .ma-info-header {
        padding: 16px 20px;
    }

    .ma-hero-actions {
        flex-wrap: wrap;
    }

    .ma-hero-btn {
        flex: 1;
        min-width: 140px;
        justify-content: center;
    }

    .ma-btn {
        flex: 1;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ma-hero { animation: fadeInUp 0.4s ease forwards; }
.ma-info-card { animation: fadeInUp 0.4s ease forwards; animation-delay: 0.1s; opacity: 0; }
.ma-form-card { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.2s; opacity: 0; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">

                            <!-- ========== HERO ========== -->
                            <div class="ma-hero">
                                <div class="ma-hero-bg">
                                    <div class="ma-hero-circle c1"></div>
                                    <div class="ma-hero-circle c2"></div>
                                </div>
                                <div class="ma-hero-content">
                                    <div class="ma-hero-left">
                                        <span class="ma-hero-badge">
                                            <i class="feather icon-edit"></i>
                                            <?php echo __('admin_panel'); ?>
                                        </span>
                                        <h1><?php echo __('edit_attendance'); ?></h1>
                                        <p><?php echo __('edit_attendance_for'); ?> <strong><?php echo htmlspecialchars($attendance['user_name']); ?></strong> - <?php echo date('M d, Y', strtotime($attendance['date'])); ?></p>
                                    </div>
                                    <div class="ma-hero-actions">
                                        <a href="manage_attendance.php" class="ma-hero-btn secondary">
                                            <i class="feather icon-arrow-left"></i>
                                            <?php echo __('back_to_manage_attendance'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Current Information Card -->
                                <div class="col-md-4">
                                    <div class="ma-info-card">
                                        <div class="ma-info-header">
                                            <i class="feather icon-info"></i>
                                            <?php echo __('current_information'); ?>
                                        </div>
                                        <div class="ma-info-body">
                                            <div style="text-align: center; margin-bottom: 20px;">
                                                <h6 style="margin: 0 0 4px; color: var(--ma-gray-700);"><?php echo htmlspecialchars($attendance['user_name']); ?></h6>
                                                <p style="margin: 0; color: var(--ma-gray-400); font-size: 13px;"><?php echo htmlspecialchars($attendance['email']); ?></p>
                                                <div style="margin-top: 12px;">
                                                    <span class="ma-badge <?php echo strtolower($attendance['status']); ?>">
                                                        <?php echo __(strtolower($attendance['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="ma-info-divider"></div>

                                            <div class="ma-info-row">
                                                <div>
                                                    <h6><?php echo __('check_in'); ?></h6>
                                                    <p><?php echo $attendance['check_in_time'] ? date('H:i', strtotime($attendance['check_in_time'])) : '-'; ?></p>
                                                </div>
                                                <div>
                                                    <h6><?php echo __('check_out'); ?></h6>
                                                    <p><?php echo $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : '-'; ?></p>
                                                </div>
                                            </div>

                                            <?php if ($attendance['working_minutes'] > 0): ?>
                                            <div class="ma-info-divider"></div>
                                            <div>
                                                <h6><?php echo __('working_minutes'); ?></h6>
                                                <p><?php echo $attendance['working_minutes']; ?> min</p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($attendance_settings): ?>
                                            <div class="ma-info-divider"></div>
                                            <div>
                                                <h6><?php echo __('office_hours'); ?></h6>
                                                <p><?php echo date('H:i', strtotime($attendance_settings['office_start_time'])); ?> - <?php echo date('H:i', strtotime($attendance_settings['office_end_time'])); ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Form -->
                                <div class="col-md-8">
                                    <?php if (isset($error)): ?>
                                    <div style="
                                        background: #fee2e2; border: 1px solid #fecaca; color: #dc2626;
                                        padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
                                    ">
                                        <i class="feather icon-alert-circle" style="margin-right: 8px;"></i>
                                        <?php echo $error; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (isset($success)): ?>
                                    <div style="
                                        background: #dcfce7; border: 1px solid #bbf7d0; color: #16a34a;
                                        padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
                                    ">
                                        <i class="feather icon-check-circle" style="margin-right: 8px;"></i>
                                        <?php echo $success; ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="ma-form-card">
                                        <div class="ma-form-header">
                                            <i class="feather icon-edit-2"></i>
                                            <?php echo __('edit_attendance_details'); ?>
                                        </div>
                                        <div class="ma-form-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="update_attendance" value="1">

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="ma-form-group">
                                                            <label for="check_in_time">
                                                                <i class="feather icon-log-in"></i><?php echo __('check_in_time'); ?>
                                                            </label>
                                                            <input type="time" class="ma-form-control" id="check_in_time" name="check_in_time"
                                                                   value="<?php echo $attendance['check_in_time'] ? date('H:i', strtotime($attendance['check_in_time'])) : ''; ?>">
                                                            <small class="ma-form-small"><?php echo __('leave_empty_if_not_checked_in'); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="ma-form-group">
                                                            <label for="check_out_time">
                                                                <i class="feather icon-log-out"></i><?php echo __('check_out_time'); ?>
                                                            </label>
                                                            <input type="time" class="ma-form-control" id="check_out_time" name="check_out_time"
                                                                   value="<?php echo $attendance['check_out_time'] ? date('H:i', strtotime($attendance['check_out_time'])) : ''; ?>">
                                                            <small class="ma-form-small"><?php echo __('leave_empty_if_not_checked_out'); ?></small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="ma-form-group">
                                                    <label for="status">
                                                        <i class="feather icon-tag"></i><?php echo __('status'); ?> <span style="color: var(--ma-danger);">*</span>
                                                    </label>
                                                    <select class="ma-form-control" id="status" name="status" required>
                                                        <option value=""><?php echo __('select_status'); ?></option>
                                                        <option value="present" <?php echo $attendance['status'] === 'present' ? 'selected' : ''; ?>><?php echo __('present'); ?></option>
                                                        <option value="late" <?php echo $attendance['status'] === 'late' ? 'selected' : ''; ?>><?php echo __('late'); ?></option>
                                                        <option value="half_day" <?php echo $attendance['status'] === 'half_day' ? 'selected' : ''; ?>><?php echo __('half_day'); ?></option>
                                                        <option value="absent" <?php echo $attendance['status'] === 'absent' ? 'selected' : ''; ?>><?php echo __('absent'); ?></option>
                                                    </select>
                                                </div>

                                                <div class="ma-form-group">
                                                    <label for="notes">
                                                        <i class="feather icon-file-text"></i><?php echo __('notes'); ?>
                                                    </label>
                                                    <textarea class="ma-form-control" id="notes" name="notes" rows="3"
                                                              style="resize: vertical; min-height: 100px;"
                                                              placeholder="<?php echo __('add_notes_here'); ?>"><?php echo htmlspecialchars($attendance['notes'] ?? ''); ?></textarea>
                                                </div>

                                                <div class="ma-form-actions">
                                                    <button type="submit" class="ma-btn primary">
                                                        <i class="feather icon-save"></i><?php echo __('update_attendance'); ?>
                                                    </button>
                                                    <a href="manage_attendance.php" class="ma-btn secondary">
                                                        <i class="feather icon-x"></i><?php echo __('cancel'); ?>
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Attendance Rules Reference -->
                                    <?php if ($attendance_settings): ?>
                                    <div class="ma-info-card">
                                        <div class="ma-info-header">
                                            <i class="feather icon-info"></i>
                                            <?php echo __('attendance_rules_reference'); ?>
                                        </div>
                                        <div class="ma-info-body">
                                            <div class="ma-info-row">
                                                <div>
                                                    <h6><?php echo __('office_hours'); ?></h6>
                                                    <p><?php echo date('H:i', strtotime($attendance_settings['office_start_time'])); ?> - <?php echo date('H:i', strtotime($attendance_settings['office_end_time'])); ?></p>

                                                    <h6 style="margin-top: 16px;"><?php echo __('late_threshold'); ?></h6>
                                                    <p><?php echo $attendance_settings['late_after_minutes']; ?> <?php echo __('minutes_after_start'); ?></p>
                                                </div>
                                                <div>
                                                    <h6><?php echo __('half_day_minimum'); ?></h6>
                                                    <p><?php echo $attendance_settings['half_day_minutes']; ?> <?php echo __('minutes'); ?></p>

                                                    <h6 style="margin-top: 16px;"><?php echo __('working_days'); ?></h6>
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