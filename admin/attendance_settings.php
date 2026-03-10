<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get current settings
$attendance_settings = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE tenant_id = ? AND branch_id = ?");
    $stmt->execute([$tenant_id, $branch_id]);
    $attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

$is_new = empty($attendance_settings);

if ($is_new) {
    $attendance_settings = [
        'office_start_time' => '09:00',
        'office_end_time' => '17:00',
        'late_after_minutes' => 15,
        'half_day_minutes' => 240,
        'working_days' => 'Mon-Fri'
    ];
}

require_once '../includes/InputValidator.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $office_start_time = InputValidator::getPattern($_POST['office_start_time'] ?? '', '/^\d{2}:\d{2}$/', '09:00');
    $office_end_time = InputValidator::getPattern($_POST['office_end_time'] ?? '', '/^\d{2}:\d{2}$/', '17:00');
    $late_after_minutes = InputValidator::getInt($_POST['late_after_minutes'] ?? '', 15, 1, 480);
    $half_day_minutes = InputValidator::getInt($_POST['half_day_minutes'] ?? '', 240, 1, 480);
    $working_days = InputValidator::getString($_POST['working_days'] ?? '', 50);

    $stmt = $pdo->prepare("SELECT tenant_id, branch_id FROM attendance_settings WHERE tenant_id = ? AND branch_id = ?");
    $stmt->execute([$tenant_id, $branch_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    try {
        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE attendance_settings
                SET office_start_time = ?, office_end_time = ?, late_after_minutes = ?,
                    half_day_minutes = ?, working_days = ?, updated_at = NOW()
                WHERE tenant_id = ? AND branch_id = ?
            ");
            $stmt->execute([$office_start_time, $office_end_time, $late_after_minutes, $half_day_minutes, $working_days, $tenant_id, $branch_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_settings
                (tenant_id, branch_id, office_start_time, office_end_time, late_after_minutes, half_day_minutes, working_days)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $branch_id, $office_start_time, $office_end_time, $late_after_minutes, $half_day_minutes, $working_days]);
        }

        $success_message = __('settings_updated_successfully');

        $stmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE tenant_id = ? AND branch_id = ?");
        $stmt->execute([$tenant_id, $branch_id]);
        $attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_new = false;

    } catch (Exception $e) {
        error_log("Error updating settings: " . $e->getMessage());
        $error_message = __('error_updating_settings');
    }
}

// Calculate derived values
$start = strtotime($attendance_settings['office_start_time'] ?? '09:00');
$end = strtotime($attendance_settings['office_end_time'] ?? '17:00');
$expected_hours = round(($end - $start) / 3600, 1);
$half_day_hours = round(($attendance_settings['half_day_minutes'] ?? 240) / 60, 1);

// Working days map
$days_map = [
    'Mon-Fri' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
    'Mon-Sat' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    'Sat-Thu' => ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu'],
    'Mon-Sun' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
];
$all_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$active_days = $days_map[$attendance_settings['working_days'] ?? 'Mon-Fri'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

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

                            <!-- ========== HERO ========== -->
                            <div class="as-hero">
                                <div class="as-hero-bg">
                                    <div class="as-hero-circle c1"></div>
                                    <div class="as-hero-circle c2"></div>
                                    <div class="as-hero-gear">
                                        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1">
                                            <circle cx="12" cy="12" r="3"/>
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="as-hero-content">
                                    <div class="as-hero-left">
                                        <a href="manage_attendance.php" class="as-back-link">
                                            <i class="feather icon-arrow-left"></i>
                                            <?php echo __('back_to_attendance'); ?>
                                        </a>
                                        <h1>
                                            <i class="feather icon-settings"></i>
                                            <?php echo __('attendance_settings'); ?>
                                        </h1>
                                        <p><?php echo __('configure_attendance_rules_for_your_branch'); ?></p>
                                    </div>
                                    <?php if (!$is_new): ?>
                                    <div class="as-hero-indicator">
                                        <div class="as-hero-indicator-dot"></div>
                                        <span><?php echo __('configured'); ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="as-hero-indicator new">
                                        <div class="as-hero-indicator-dot"></div>
                                        <span><?php echo __('needs_setup'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ========== ALERTS ========== -->
                            <?php if (isset($success_message)): ?>
                                <div class="as-alert success" id="successAlert">
                                    <div class="as-alert-icon">
                                        <i class="feather icon-check-circle"></i>
                                    </div>
                                    <div class="as-alert-content">
                                        <strong><?php echo __('success'); ?></strong>
                                        <p><?php echo $success_message; ?></p>
                                    </div>
                                    <button class="as-alert-close" onclick="this.parentElement.remove()">
                                        <i class="feather icon-x"></i>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($error_message)): ?>
                                <div class="as-alert error">
                                    <div class="as-alert-icon">
                                        <i class="feather icon-alert-triangle"></i>
                                    </div>
                                    <div class="as-alert-content">
                                        <strong><?php echo __('error'); ?></strong>
                                        <p><?php echo $error_message; ?></p>
                                    </div>
                                    <button class="as-alert-close" onclick="this.parentElement.remove()">
                                        <i class="feather icon-x"></i>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_new): ?>
                                <div class="as-alert info">
                                    <div class="as-alert-icon">
                                        <i class="feather icon-info"></i>
                                    </div>
                                    <div class="as-alert-content">
                                        <strong><?php echo __('first_time_setup'); ?></strong>
                                        <p><?php echo __('no_settings_found_using_defaults'); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- ========== MAIN LAYOUT ========== -->
                            <div class="as-layout">
                                <!-- Left: Settings Form -->
                                <div class="as-form-section">
                                    <form method="POST" id="settingsForm">

                                        <!-- Office Hours -->
                                        <div class="as-form-card">
                                            <div class="as-form-card-header">
                                                <div class="as-form-card-icon clock">
                                                    <i class="feather icon-clock"></i>
                                                </div>
                                                <div>
                                                    <h3><?php echo __('office_hours'); ?></h3>
                                                    <p><?php echo __('set_daily_working_schedule'); ?></p>
                                                </div>
                                            </div>
                                            <div class="as-form-card-body">
                                                <div class="as-time-inputs">
                                                    <div class="as-time-group">
                                                        <label><?php echo __('start_time'); ?></label>
                                                        <div class="as-time-input-wrap">
                                                            <div class="as-time-icon start">
                                                                <i class="feather icon-sunrise"></i>
                                                            </div>
                                                            <input type="time" name="office_start_time" id="office_start_time"
                                                                value="<?php echo htmlspecialchars(substr($attendance_settings['office_start_time'] ?? '09:00', 0, 5)); ?>"
                                                                required>
                                                        </div>
                                                        <small><?php echo __('when_office_opens'); ?></small>
                                                    </div>
                                                    <div class="as-time-separator">
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <line x1="5" y1="12" x2="19" y2="12"/>
                                                            <polyline points="12 5 19 12 12 19"/>
                                                        </svg>
                                                    </div>
                                                    <div class="as-time-group">
                                                        <label><?php echo __('end_time'); ?></label>
                                                        <div class="as-time-input-wrap">
                                                            <div class="as-time-icon end">
                                                                <i class="feather icon-sunset"></i>
                                                            </div>
                                                            <input type="time" name="office_end_time" id="office_end_time"
                                                                value="<?php echo htmlspecialchars(substr($attendance_settings['office_end_time'] ?? '17:00', 0, 5)); ?>"
                                                                required>
                                                        </div>
                                                        <small><?php echo __('when_office_closes'); ?></small>
                                                    </div>
                                                </div>

                                                <!-- Visual Timeline -->
                                                <div class="as-timeline-visual">
                                                    <div class="as-timeline-track">
                                                        <div class="as-timeline-fill" id="timelineFill"></div>
                                                        <div class="as-timeline-marker start" id="timelineStart">
                                                            <span id="timelineStartLabel"><?php echo date('h:i A', strtotime($attendance_settings['office_start_time'] ?? '09:00')); ?></span>
                                                        </div>
                                                        <div class="as-timeline-marker end" id="timelineEnd">
                                                            <span id="timelineEndLabel"><?php echo date('h:i A', strtotime($attendance_settings['office_end_time'] ?? '17:00')); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="as-timeline-labels">
                                                        <span>12 AM</span>
                                                        <span>6 AM</span>
                                                        <span>12 PM</span>
                                                        <span>6 PM</span>
                                                        <span>12 AM</span>
                                                    </div>
                                                </div>

                                                <div class="as-hours-display" id="hoursDisplay">
                                                    <i class="feather icon-clock"></i>
                                                    <span><strong id="expectedHours"><?php echo $expected_hours; ?></strong> <?php echo __('hours_per_day'); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Thresholds -->
                                        <div class="as-form-card">
                                            <div class="as-form-card-header">
                                                <div class="as-form-card-icon threshold">
                                                    <i class="feather icon-sliders"></i>
                                                </div>
                                                <div>
                                                    <h3><?php echo __('attendance_thresholds'); ?></h3>
                                                    <p><?php echo __('define_when_employees_are_marked_late_or_half_day'); ?></p>
                                                </div>
                                            </div>
                                            <div class="as-form-card-body">
                                                <div class="as-threshold-grid">
                                                    <div class="as-threshold-item">
                                                        <div class="as-threshold-header">
                                                            <div class="as-threshold-label">
                                                                <span class="as-threshold-dot late"></span>
                                                                <label><?php echo __('late_threshold'); ?></label>
                                                            </div>
                                                            <span class="as-threshold-value" id="lateValue"><?php echo $attendance_settings['late_after_minutes'] ?? 15; ?> min</span>
                                                        </div>
                                                        <input type="range" class="as-range" name="late_after_minutes" id="late_after_minutes"
                                                            value="<?php echo htmlspecialchars($attendance_settings['late_after_minutes'] ?? 15); ?>"
                                                            min="1" max="120" step="1">
                                                        <div class="as-range-labels">
                                                            <span>1 min</span>
                                                            <span>120 min</span>
                                                        </div>
                                                        <small><?php echo __('minutes_after_start_considered_late'); ?></small>
                                                    </div>

                                                    <div class="as-threshold-item">
                                                        <div class="as-threshold-header">
                                                            <div class="as-threshold-label">
                                                                <span class="as-threshold-dot half"></span>
                                                                <label><?php echo __('half_day_threshold'); ?></label>
                                                            </div>
                                                            <span class="as-threshold-value" id="halfDayValue"><?php echo $half_day_hours; ?>h</span>
                                                        </div>
                                                        <input type="range" class="as-range half" name="half_day_minutes" id="half_day_minutes"
                                                            value="<?php echo htmlspecialchars($attendance_settings['half_day_minutes'] ?? 240); ?>"
                                                            min="30" max="480" step="10">
                                                        <div class="as-range-labels">
                                                            <span>0.5h</span>
                                                            <span>8h</span>
                                                        </div>
                                                        <small><?php echo __('minimum_minutes_for_half_day_credit'); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Working Days -->
                                        <div class="as-form-card">
                                            <div class="as-form-card-header">
                                                <div class="as-form-card-icon days">
                                                    <i class="feather icon-calendar"></i>
                                                </div>
                                                <div>
                                                    <h3><?php echo __('working_days'); ?></h3>
                                                    <p><?php echo __('select_your_work_week_schedule'); ?></p>
                                                </div>
                                            </div>
                                            <div class="as-form-card-body">
                                                <div class="as-days-visual">
                                                    <?php foreach ($all_days as $day): ?>
                                                        <div class="as-day-chip <?php echo in_array($day, $active_days) ? 'active' : ''; ?>">
                                                            <span class="as-day-letter"><?php echo substr($day, 0, 1); ?></span>
                                                            <span class="as-day-name"><?php echo __($day); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="as-schedule-select">
                                                    <label><?php echo __('schedule_preset'); ?></label>
                                                    <div class="as-schedule-options">
                                                        <?php
                                                        $schedules = [
                                                            'Mon-Fri' => __('monday_friday'),
                                                            'Mon-Sat' => __('monday_saturday'),
                                                            'Sat-Thu' => __('saturday_thursday'),
                                                            'Mon-Sun' => __('every_day'),
                                                        ];
                                                        foreach ($schedules as $val => $label):
                                                        ?>
                                                            <label class="as-schedule-option">
                                                                <input type="radio" name="working_days" value="<?php echo $val; ?>"
                                                                    <?php echo ($attendance_settings['working_days'] ?? 'Mon-Fri') === $val ? 'checked' : ''; ?>>
                                                                <div class="as-schedule-card">
                                                                    <span class="as-schedule-name"><?php echo $label; ?></span>
                                                                    <span class="as-schedule-val"><?php echo count($days_map[$val]); ?> <?php echo __('days'); ?></span>
                                                                </div>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Save Button -->
                                        <div class="as-form-actions">
                                            <a href="manage_attendance.php" class="as-btn secondary">
                                                <i class="feather icon-x"></i>
                                                <?php echo __('cancel'); ?>
                                            </a>
                                            <button type="submit" class="as-btn primary" id="saveBtn">
                                                <i class="feather icon-save"></i>
                                                <?php echo __('save_settings'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Right: Summary Sidebar -->
                                <div class="as-sidebar">
                                    <!-- Live Preview -->
                                    <div class="as-preview-card">
                                        <div class="as-preview-header">
                                            <i class="feather icon-eye"></i>
                                            <h4><?php echo __('live_preview'); ?></h4>
                                        </div>
                                        <div class="as-preview-body">
                                            <div class="as-preview-clock">
                                                <div class="as-preview-clock-face">
                                                    <div class="as-preview-hand hour" id="previewHourHand"></div>
                                                    <div class="as-preview-hand minute" id="previewMinuteHand"></div>
                                                    <div class="as-preview-center-dot"></div>
                                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                                        <span class="as-preview-number" style="--i: <?php echo $i; ?>"><?php echo $i; ?></span>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>

                                            <div class="as-preview-items">
                                                <div class="as-preview-item">
                                                    <div class="as-preview-item-icon start">
                                                        <i class="feather icon-play"></i>
                                                    </div>
                                                    <div class="as-preview-item-info">
                                                        <small><?php echo __('office_opens'); ?></small>
                                                        <strong id="previewStart"><?php echo date('h:i A', strtotime($attendance_settings['office_start_time'] ?? '09:00')); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="as-preview-item">
                                                    <div class="as-preview-item-icon late">
                                                        <i class="feather icon-alert-triangle"></i>
                                                    </div>
                                                    <div class="as-preview-item-info">
                                                        <small><?php echo __('late_after'); ?></small>
                                                        <strong id="previewLate"><?php echo date('h:i A', strtotime($attendance_settings['office_start_time'] ?? '09:00') + (($attendance_settings['late_after_minutes'] ?? 15) * 60)); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="as-preview-item">
                                                    <div class="as-preview-item-icon end">
                                                        <i class="feather icon-square"></i>
                                                    </div>
                                                    <div class="as-preview-item-info">
                                                        <small><?php echo __('office_closes'); ?></small>
                                                        <strong id="previewEnd"><?php echo date('h:i A', strtotime($attendance_settings['office_end_time'] ?? '17:00')); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="as-preview-item">
                                                    <div class="as-preview-item-icon hours">
                                                        <i class="feather icon-clock"></i>
                                                    </div>
                                                    <div class="as-preview-item-info">
                                                        <small><?php echo __('total_hours'); ?></small>
                                                        <strong id="previewTotal"><?php echo $expected_hours; ?>h</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- How It Works -->
                                    <div class="as-logic-card">
                                        <div class="as-logic-header">
                                            <i class="feather icon-help-circle"></i>
                                            <h4><?php echo __('how_status_is_calculated'); ?></h4>
                                        </div>
                                        <div class="as-logic-body">
                                            <div class="as-logic-item">
                                                <div class="as-logic-icon present">
                                                    <i class="feather icon-check-circle"></i>
                                                </div>
                                                <div class="as-logic-info">
                                                    <strong><?php echo __('present'); ?></strong>
                                                    <p><?php echo __('checked_in_on_time_before_late_threshold'); ?></p>
                                                </div>
                                            </div>
                                            <div class="as-logic-item">
                                                <div class="as-logic-icon late">
                                                    <i class="feather icon-clock"></i>
                                                </div>
                                                <div class="as-logic-info">
                                                    <strong><?php echo __('late'); ?></strong>
                                                    <p><?php echo __('checked_in_after_grace_period'); ?></p>
                                                </div>
                                            </div>
                                            <div class="as-logic-item">
                                                <div class="as-logic-icon half">
                                                    <i class="feather icon-sun"></i>
                                                </div>
                                                <div class="as-logic-info">
                                                    <strong><?php echo __('half_day'); ?></strong>
                                                    <p><?php echo __('worked_less_than_minimum_hours'); ?></p>
                                                </div>
                                            </div>
                                            <div class="as-logic-item">
                                                <div class="as-logic-icon absent">
                                                    <i class="feather icon-x-circle"></i>
                                                </div>
                                                <div class="as-logic-info">
                                                    <strong><?php echo __('absent'); ?></strong>
                                                    <p><?php echo __('no_attendance_recorded'); ?></p>
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

<style>
/* ============================================
   ATTENDANCE SETTINGS - REDESIGN
   ============================================ */
:root {
    --as-primary: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --as-primary-light: rgba(64, 153, 255, 0.1);
    --as-primary-dark: #2a7acc;
    --as-success: #10b981;
    --as-success-light: #ecfdf5;
    --as-danger: #ef4444;
    --as-danger-light: #fef2f2;
    --as-warning: #f59e0b;
    --as-warning-light: #fffbeb;
    --as-info: #3b82f6;
    --as-info-light: #eff6ff;
    --as-gray-50: #f9fafb;
    --as-gray-100: #f3f4f6;
    --as-gray-200: #e5e7eb;
    --as-gray-300: #d1d5db;
    --as-gray-400: #9ca3af;
    --as-gray-500: #6b7280;
    --as-gray-600: #4b5563;
    --as-gray-700: #374151;
    --as-gray-800: #1f2937;
    --as-gray-900: #111827;
    --as-radius: 16px;
    --as-radius-sm: 10px;
    --as-radius-xs: 6px;
    --as-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --as-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
    --as-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
    --as-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== HERO ===== */
.as-hero {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-radius: var(--as-radius);
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    color: #fff;
}

.as-hero-bg {
    position: absolute;
    inset: 0;
    z-index: 1;
    overflow: hidden;
}

.as-hero-circle { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.04); }
.as-hero-circle.c1 { width: 300px; height: 300px; top: -100px; right: -50px; }
.as-hero-circle.c2 { width: 180px; height: 180px; bottom: -60px; left: 10%; }

.as-hero-gear {
    position: absolute;
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
    animation: gearSpin 20s linear infinite;
    opacity: 0.5;
}

@keyframes gearSpin {
    to { transform: translateY(-50%) rotate(360deg); }
}

.as-hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.as-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.6);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    margin-bottom: 12px;
    transition: var(--as-transition);
}

.as-back-link:hover { color: #fff; gap: 8px; text-decoration: none; }

.as-hero h1 {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.as-hero p { margin: 0; opacity: 0.6; font-size: 14px; }

.as-hero-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(16, 185, 129, 0.15);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: #6ee7b7;
}

.as-hero-indicator.new {
    background: rgba(251, 191, 36, 0.15);
    color: #fcd34d;
}

.as-hero-indicator-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
    animation: indicatorPulse 2s infinite;
}

@keyframes indicatorPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* ===== ALERTS ===== */
.as-alert {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-radius: var(--as-radius);
    margin-bottom: 16px;
    animation: fadeInDown 0.4s ease;
}

.as-alert.success {
    background: var(--as-success-light);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.as-alert.error {
    background: var(--as-danger-light);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.as-alert.info {
    background: var(--as-info-light);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.as-alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.as-alert.success .as-alert-icon { background: rgba(16, 185, 129, 0.1); color: var(--as-success); }
.as-alert.error .as-alert-icon { background: rgba(239, 68, 68, 0.1); color: var(--as-danger); }
.as-alert.info .as-alert-icon { background: rgba(59, 130, 246, 0.1); color: var(--as-info); }

.as-alert-content { flex: 1; }
.as-alert-content strong { display: block; font-size: 14px; color: var(--as-gray-800); }
.as-alert-content p { margin: 2px 0 0; font-size: 13px; color: var(--as-gray-600); }

.as-alert-close {
    background: none;
    border: none;
    color: var(--as-gray-400);
    cursor: pointer;
    padding: 4px;
    transition: var(--as-transition);
}

.as-alert-close:hover { color: var(--as-gray-600); }

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== LAYOUT ===== */
.as-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
    align-items: start;
}

/* ===== FORM CARDS ===== */
.as-form-card {
    background: #fff;
    border-radius: var(--as-radius);
    box-shadow: var(--as-shadow);
    border: 1px solid var(--as-gray-100);
    margin-bottom: 20px;
    overflow: hidden;
    transition: var(--as-transition);
}

.as-form-card:hover {
    box-shadow: var(--as-shadow-md);
}

.as-form-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--as-gray-100);
}

.as-form-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.as-form-card-icon.clock { background: var(--as-primary-light); color: var(--as-primary); }
.as-form-card-icon.threshold { background: var(--as-warning-light); color: var(--as-warning); }
.as-form-card-icon.days { background: var(--as-success-light); color: var(--as-success); }

.as-form-card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--as-gray-800);
    margin: 0;
}

.as-form-card-header p {
    font-size: 13px;
    color: var(--as-gray-400);
    margin: 2px 0 0;
}

.as-form-card-body {
    padding: 24px;
}

/* ===== TIME INPUTS ===== */
.as-time-inputs {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.as-time-group {
    flex: 1;
}

.as-time-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--as-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 8px;
}

.as-time-input-wrap {
    display: flex;
    align-items: center;
    border: 2px solid var(--as-gray-200);
    border-radius: var(--as-radius-sm);
    overflow: hidden;
    transition: var(--as-transition);
}

.as-time-input-wrap:focus-within {
    border-color: var(--as-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.as-time-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.as-time-icon.start { background: var(--as-success-light); color: var(--as-success); }
.as-time-icon.end { background: var(--as-danger-light); color: var(--as-danger); }

.as-time-input-wrap input[type="time"] {
    flex: 1;
    border: none;
    padding: 12px 14px;
    font-size: 18px;
    font-weight: 700;
    color: var(--as-gray-800);
    background: transparent;
    outline: none;
}

.as-time-group small {
    display: block;
    font-size: 12px;
    color: var(--as-gray-400);
    margin-top: 6px;
}

.as-time-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--as-gray-300);
    padding-top: 28px;
    flex-shrink: 0;
}

/* ===== TIMELINE VISUAL ===== */
.as-timeline-visual {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--as-gray-100);
}

.as-timeline-track {
    position: relative;
    height: 8px;
    background: var(--as-gray-100);
    border-radius: 4px;
    margin: 20px 0 8px;
}

.as-timeline-fill {
    position: absolute;
    height: 100%;
    background: linear-gradient(90deg, var(--as-success), var(--as-primary), var(--as-danger));
    border-radius: 4px;
    transition: var(--as-transition);
}

.as-timeline-marker {
    position: absolute;
    top: -8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 3px solid #fff;
    transform: translateX(-50%);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    z-index: 2;
}

.as-timeline-marker.start { background: var(--as-success); }
.as-timeline-marker.end { background: var(--as-danger); }

.as-timeline-marker span {
    position: absolute;
    top: -22px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    font-weight: 700;
    color: var(--as-gray-500);
    white-space: nowrap;
}

.as-timeline-labels {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: var(--as-gray-400);
    margin-top: 4px;
}

.as-hours-display {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
    padding: 10px 16px;
    background: var(--as-primary-light);
    border-radius: var(--as-radius-xs);
    font-size: 14px;
    color: var(--as-primary);
}

.as-hours-display i { font-size: 16px; }

/* ===== THRESHOLD SLIDERS ===== */
.as-threshold-grid {
    display: flex;
    flex-direction: column;
    gap: 28px;
}

.as-threshold-item {
    padding: 16px;
    background: var(--as-gray-50);
    border-radius: var(--as-radius-sm);
    border: 1px solid var(--as-gray-100);
}

.as-threshold-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.as-threshold-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.as-threshold-label label {
    font-size: 14px;
    font-weight: 600;
    color: var(--as-gray-700);
    margin: 0;
}

.as-threshold-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.as-threshold-dot.late { background: var(--as-warning); }
.as-threshold-dot.half { background: var(--as-info); }

.as-threshold-value {
    font-size: 16px;
    font-weight: 800;
    color: var(--as-gray-800);
    background: #fff;
    padding: 4px 14px;
    border-radius: 8px;
    border: 1px solid var(--as-gray-200);
}

.as-range {
    width: 100%;
    -webkit-appearance: none;
    appearance: none;
    height: 6px;
    border-radius: 3px;
    background: var(--as-gray-200);
    outline: none;
    margin: 8px 0;
}

.as-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--as-warning);
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    cursor: pointer;
    transition: var(--as-transition);
}

.as-range::-webkit-slider-thumb:hover {
    transform: scale(1.15);
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.as-range.half::-webkit-slider-thumb {
    background: var(--as-info);
}

.as-range::-moz-range-thumb {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--as-warning);
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    cursor: pointer;
}

.as-range.half::-moz-range-thumb {
    background: var(--as-info);
}

.as-range-labels {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--as-gray-400);
}

.as-threshold-item small {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: var(--as-gray-400);
}

/* ===== WORKING DAYS ===== */
.as-days-visual {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.as-day-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 12px 14px;
    border-radius: var(--as-radius-sm);
    border: 2px solid var(--as-gray-200);
    background: #fff;
    min-width: 56px;
    transition: var(--as-transition);
}

.as-day-chip.active {
    border-color: var(--as-success);
    background: var(--as-success-light);
}

.as-day-letter {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    background: var(--as-gray-100);
    color: var(--as-gray-400);
}

.as-day-chip.active .as-day-letter {
    background: var(--as-success);
    color: #fff;
}

.as-day-name {
    font-size: 10px;
    font-weight: 600;
    color: var(--as-gray-400);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.as-day-chip.active .as-day-name {
    color: var(--as-success);
}

.as-schedule-select label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--as-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 10px;
}

.as-schedule-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.as-schedule-option {
    cursor: pointer;
    margin: 0;
}

.as-schedule-option input { display: none; }

.as-schedule-card {
    padding: 14px 16px;
    border: 2px solid var(--as-gray-200);
    border-radius: var(--as-radius-sm);
    text-align: center;
    transition: var(--as-transition);
}

.as-schedule-card:hover {
    border-color: var(--as-gray-300);
}

.as-schedule-option input:checked + .as-schedule-card {
    border-color: var(--as-success);
    background: var(--as-success-light);
}

.as-schedule-name {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--as-gray-700);
}

.as-schedule-val {
    display: block;
    font-size: 11px;
    color: var(--as-gray-400);
    margin-top: 2px;
}

/* ===== FORM ACTIONS ===== */
.as-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 8px;
}

.as-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: var(--as-radius-sm);
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: var(--as-transition);
    text-decoration: none;
}

.as-btn.primary {
    background: var(--as-primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
}

.as-btn.primary:hover {
    background: var(--as-primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
    color: #fff;
}

.as-btn.secondary {
    background: var(--as-gray-100);
    color: var(--as-gray-600);
}

.as-btn.secondary:hover {
    background: var(--as-gray-200);
    color: var(--as-gray-700);
}

/* ===== SIDEBAR ===== */
.as-sidebar {
    position: sticky;
    top: 24px;
}

/* Preview Card */
.as-preview-card,
.as-logic-card {
    background: #fff;
    border-radius: var(--as-radius);
    box-shadow: var(--as-shadow);
    border: 1px solid var(--as-gray-100);
    overflow: hidden;
    margin-bottom: 20px;
}

.as-preview-header,
.as-logic-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--as-gray-100);
}

.as-preview-header i,
.as-logic-header i {
    color: var(--as-primary);
    font-size: 16px;
}

.as-preview-header h4,
.as-logic-header h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--as-gray-800);
    margin: 0;
}

.as-preview-body {
    padding: 20px;
}

/* Mini Clock */
.as-preview-clock {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.as-preview-clock-face {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: var(--as-gray-50);
    border: 3px solid var(--as-gray-200);
    position: relative;
}

.as-preview-hand {
    position: absolute;
    bottom: 50%;
    left: 50%;
    transform-origin: bottom center;
    border-radius: 2px;
}

.as-preview-hand.hour {
    width: 3px;
    height: 28px;
    background: var(--as-gray-700);
    margin-left: -1.5px;
}

.as-preview-hand.minute {
    width: 2px;
    height: 36px;
    background: var(--as-primary);
    margin-left: -1px;
}

.as-preview-center-dot {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--as-primary);
    z-index: 3;
}

.as-preview-number {
    position: absolute;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: var(--as-gray-400);
    --angle: calc((var(--i) * 30) * 1deg);
    top: calc(50% - 10px - 40px * cos(var(--angle)));
    left: calc(50% - 10px + 40px * sin(var(--angle)));
}

/* Preview Items */
.as-preview-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.as-preview-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: var(--as-radius-sm);
    background: var(--as-gray-50);
    border: 1px solid var(--as-gray-100);
    transition: var(--as-transition);
}

.as-preview-item:hover {
    border-color: var(--as-gray-200);
}

.as-preview-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.as-preview-item-icon.start { background: var(--as-success-light); color: var(--as-success); }
.as-preview-item-icon.late { background: var(--as-warning-light); color: var(--as-warning); }
.as-preview-item-icon.end { background: var(--as-danger-light); color: var(--as-danger); }
.as-preview-item-icon.hours { background: var(--as-primary-light); color: var(--as-primary); }

.as-preview-item-info small {
    display: block;
    font-size: 11px;
    color: var(--as-gray-400);
    font-weight: 500;
}

.as-preview-item-info strong {
    display: block;
    font-size: 14px;
    color: var(--as-gray-800);
    font-weight: 700;
}

/* Logic Card */
.as-logic-body {
    padding: 16px 20px;
}

.as-logic-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--as-gray-50);
}

.as-logic-item:last-child { border-bottom: none; }

.as-logic-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.as-logic-icon.present { background: var(--as-success-light); color: var(--as-success); }
.as-logic-icon.late { background: var(--as-warning-light); color: var(--as-warning); }
.as-logic-icon.half { background: var(--as-info-light); color: var(--as-info); }
.as-logic-icon.absent { background: var(--as-danger-light); color: var(--as-danger); }

.as-logic-info strong {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--as-gray-700);
}

.as-logic-info p {
    font-size: 12px;
    color: var(--as-gray-400);
    margin: 2px 0 0;
    line-height: 1.4;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .as-layout {
        grid-template-columns: 1fr 300px;
    }
}

@media (max-width: 992px) {
    .as-layout {
        grid-template-columns: 1fr;
    }

    .as-sidebar {
        position: static;
    }

    .as-hero-content {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }

    .as-back-link { justify-content: center; }
}

@media (max-width: 768px) {
    .as-hero { padding: 24px 20px; }
    .as-hero h1 { font-size: 20px; }

    .as-time-inputs {
        flex-direction: column;
    }

    .as-time-separator {
        padding-top: 0;
        transform: rotate(90deg);
    }

    .as-schedule-options {
        grid-template-columns: 1fr;
    }

    .as-form-actions {
        flex-direction: column;
    }

    .as-btn { justify-content: center; }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.as-hero { animation: fadeInUp 0.4s ease forwards; }
.as-form-card { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
.as-form-card:nth-child(1) { animation-delay: 0.1s; }
.as-form-card:nth-child(2) { animation-delay: 0.2s; }
.as-form-card:nth-child(3) { animation-delay: 0.3s; }
.as-preview-card { animation: fadeInUp 0.4s ease forwards; animation-delay: 0.15s; opacity: 0; }
.as-logic-card { animation: fadeInUp 0.4s ease forwards; animation-delay: 0.25s; opacity: 0; }
</style>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startInput = document.getElementById('office_start_time');
    const endInput = document.getElementById('office_end_time');
    const lateRange = document.getElementById('late_after_minutes');
    const halfRange = document.getElementById('half_day_minutes');

    function timeToMinutes(time) {
        const [h, m] = time.split(':').map(Number);
        return h * 60 + m;
    }

    function minutesToTime12(totalMin) {
        let h = Math.floor(totalMin / 60) % 24;
        const m = totalMin % 60;
        const period = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${String(m).padStart(2, '0')} ${period}`;
    }

    function updateTimeline() {
        const startMin = timeToMinutes(startInput.value);
        const endMin = timeToMinutes(endInput.value);
        const totalDayMin = 1440;

        const startPct = (startMin / totalDayMin) * 100;
        const endPct = (endMin / totalDayMin) * 100;

        const fill = document.getElementById('timelineFill');
        const startMarker = document.getElementById('timelineStart');
        const endMarker = document.getElementById('timelineEnd');
        const startLabel = document.getElementById('timelineStartLabel');
        const endLabel = document.getElementById('timelineEndLabel');

        fill.style.left = startPct + '%';
        fill.style.width = (endPct - startPct) + '%';
        startMarker.style.left = startPct + '%';
        endMarker.style.left = endPct + '%';
        startLabel.textContent = minutesToTime12(startMin);
        endLabel.textContent = minutesToTime12(endMin);

        // Update expected hours
        const diffHours = ((endMin - startMin) / 60).toFixed(1);
        document.getElementById('expectedHours').textContent = diffHours;

        // Update preview
        document.getElementById('previewStart').textContent = minutesToTime12(startMin);
        document.getElementById('previewEnd').textContent = minutesToTime12(endMin);
        document.getElementById('previewTotal').textContent = diffHours + 'h';

        // Update late preview
        const lateMin = startMin + parseInt(lateRange.value);
        document.getElementById('previewLate').textContent = minutesToTime12(lateMin);
    }

    function updateLateValue() {
        const val = parseInt(lateRange.value);
        document.getElementById('lateValue').textContent = val + ' min';

        // Update late preview time
        const startMin = timeToMinutes(startInput.value);
        const lateMin = startMin + val;
        document.getElementById('previewLate').textContent = minutesToTime12(lateMin);

        // Update range track fill
        const pct = ((val - 1) / 119) * 100;
        lateRange.style.background = `linear-gradient(to right, var(--as-warning) ${pct}%, var(--as-gray-200) ${pct}%)`;
    }

    function updateHalfDayValue() {
        const val = parseInt(halfRange.value);
        const hours = (val / 60).toFixed(1);
        document.getElementById('halfDayValue').textContent = hours + 'h';

        const pct = ((val - 30) / 450) * 100;
        halfRange.style.background = `linear-gradient(to right, var(--as-info) ${pct}%, var(--as-gray-200) ${pct}%)`;
    }

    function updateDaysVisual() {
        const selected = document.querySelector('input[name="working_days"]:checked').value;
        const daysMap = {
            'Mon-Fri': ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'Mon-Sat': ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'Sat-Thu': ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu'],
            'Mon-Sun': ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        };
        const activeDays = daysMap[selected] || [];
        const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        document.querySelectorAll('.as-day-chip').forEach((chip, i) => {
            const dayName = allDays[i];
            if (activeDays.includes(dayName)) {
                chip.classList.add('active');
            } else {
                chip.classList.remove('active');
            }
        });
    }

    function updateClockHands() {
        const now = new Date();
        const startMin = timeToMinutes(startInput.value);
        const h = Math.floor(startMin / 60);
        const m = startMin % 60;

        const hourDeg = (h % 12) * 30 + m * 0.5;
        const minDeg = m * 6;

        const hourHand = document.getElementById('previewHourHand');
        const minHand = document.getElementById('previewMinuteHand');

        if (hourHand) hourHand.style.transform = `rotate(${hourDeg}deg)`;
        if (minHand) minHand.style.transform = `rotate(${minDeg}deg)`;
    }

    // Event listeners
    startInput.addEventListener('change', () => { updateTimeline(); updateClockHands(); });
    endInput.addEventListener('change', updateTimeline);
    lateRange.addEventListener('input', updateLateValue);
    halfRange.addEventListener('input', updateHalfDayValue);
    document.querySelectorAll('input[name="working_days"]').forEach(r => {
        r.addEventListener('change', updateDaysVisual);
    });

    // Initial
    updateTimeline();
    updateLateValue();
    updateHalfDayValue();
    updateClockHands();

    // Auto-dismiss success
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateY(-10px)';
            successAlert.style.transition = 'all 0.3s ease';
            setTimeout(() => successAlert.remove(), 300);
        }, 4000);
    }

    // Save button feedback
    document.getElementById('settingsForm').addEventListener('submit', function() {
        const btn = document.getElementById('saveBtn');
        btn.innerHTML = '<div style="width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;"></div> <?php echo __("saving"); ?>...';
        btn.disabled = true;
    });
});
</script>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php include '../includes/admin_footer.php'; ?>