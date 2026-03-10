<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kabul');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get today's attendance
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

// Get this week's attendance
$stmt = $pdo->prepare("
    SELECT * FROM attendance
    WHERE tenant_id = ? AND branch_id = ? AND user_id = ?
    AND date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    AND date <= CURDATE()
    ORDER BY date ASC
");
$stmt->execute([$tenant_id, $branch_id, $user_id]);
$week_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        COALESCE(AVG(working_minutes), 0) as avg_working_minutes,
        COALESCE(SUM(working_minutes), 0) as total_working_minutes
    FROM attendance
    WHERE tenant_id = ? AND branch_id = ? AND user_id = ?
    AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
");
$stmt->execute([$tenant_id, $branch_id, $user_id]);
$monthly_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate working hours progress for today
$today_progress = 0;
$today_minutes = 0;
if ($today_attendance && $today_attendance['check_in_time']) {
    if ($today_attendance['check_out_time']) {
        $today_minutes = $today_attendance['working_minutes'];
    } else {
        $checkin = new DateTime($today_attendance['check_in_time']);
        $now = new DateTime();
        $today_minutes = ($now->getTimestamp() - $checkin->getTimestamp()) / 60;
    }
    $office_start = new DateTime($attendance_settings['office_start_time']);
    $office_end = new DateTime($attendance_settings['office_end_time']);
    $total_office_minutes = ($office_end->getTimestamp() - $office_start->getTimestamp()) / 60;
    $today_progress = min(100, ($today_minutes / $total_office_minutes) * 100);
}

// Determine current state
$state = 'not_checked_in';
if ($today_attendance) {
    if ($today_attendance['check_out_time']) {
        $state = 'completed';
    } else {
        $state = 'checked_in';
    }
}

$page_title = __('attendance');
include '../includes/header.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">

                            <!-- ========== HERO HEADER ========== -->
                            <div class="att-hero <?php echo $state; ?>">
                                <div class="att-hero-bg">
                                    <div class="att-hero-circle c1"></div>
                                    <div class="att-hero-circle c2"></div>
                                    <div class="att-hero-circle c3"></div>
                                </div>
                                <div class="att-hero-content">
                                    <div class="att-hero-left">
                                        <span class="att-hero-badge">
                                            <i class="feather icon-clock"></i>
                                            <?php echo __('attendance_system'); ?>
                                        </span>
                                        <h1><?php echo __('good'); ?>
                                            <?php
                                                $hour = date('H');
                                                if ($hour < 12) echo __('morning');
                                                elseif ($hour < 17) echo __('afternoon');
                                                else echo __('evening');
                                            ?>, <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>
                                        </h1>
                                        <p class="att-hero-date">
                                            <i class="feather icon-calendar"></i>
                                            <?php echo date('l, F j, Y'); ?>
                                        </p>
                                    </div>
                                    <div class="att-hero-right">
                                        <div class="att-live-clock" id="liveClock">
                                            <div class="att-clock-time" id="current-time"><?php echo date('h:i:s'); ?></div>
                                            <div class="att-clock-period" id="current-period"><?php echo date('A'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== STATUS + ACTION CARD ========== -->
                            <div class="att-action-section">
                                <div class="att-action-card <?php echo $state; ?>">

                                    <?php if ($state === 'not_checked_in'): ?>
                                        <!-- NOT CHECKED IN -->
                                        <div class="att-action-visual">
                                            <div class="att-pulse-ring">
                                                <div class="att-pulse-dot"></div>
                                            </div>
                                        </div>
                                        <div class="att-action-info">
                                            <div class="att-action-status">
                                                <span class="att-status-indicator offline"></span>
                                                <?php echo __('not_checked_in'); ?>
                                            </div>
                                            <h2><?php echo __('ready_to_start_your_day'); ?></h2>
                                            <p><?php echo __('office_hours'); ?>:
                                                <?php echo date('h:i A', strtotime($attendance_settings['office_start_time'])); ?> —
                                                <?php echo date('h:i A', strtotime($attendance_settings['office_end_time'])); ?>
                                            </p>
                                        </div>
                                        <div class="att-action-btn-wrap">
                                            <button type="button" class="att-checkin-btn" onclick="checkIn()" id="checkInBtn">
                                                <div class="att-btn-icon">
                                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                                        <polyline points="10 17 15 12 10 7"/>
                                                        <line x1="15" y1="12" x2="3" y2="12"/>
                                                    </svg>
                                                </div>
                                                <div class="att-btn-text">
                                                    <span><?php echo __('check_in'); ?></span>
                                                    <small><?php echo __('tap_to_start'); ?></small>
                                                </div>
                                            </button>
                                        </div>

                                    <?php elseif ($state === 'checked_in'): ?>
                                        <!-- CHECKED IN - WORKING -->
                                        <div class="att-action-visual">
                                            <div class="att-progress-ring-container">
                                                <svg class="att-progress-ring" viewBox="0 0 120 120">
                                                    <circle cx="60" cy="60" r="52" class="att-ring-bg"/>
                                                    <circle cx="60" cy="60" r="52" class="att-ring-fill"
                                                        style="--progress: <?php echo $today_progress; ?>"/>
                                                </svg>
                                                <div class="att-progress-text">
                                                    <span class="att-progress-number"><?php echo (int)floor($today_minutes / 60); ?>h <?php echo (int)round(fmod($today_minutes, 60)); ?>m</span>
                                                    <span class="att-progress-label"><?php echo __('working'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="att-action-info">
                                            <div class="att-action-status">
                                                <span class="att-status-indicator online pulse"></span>
                                                <?php echo __('currently_working'); ?>
                                            </div>
                                            <h2><?php echo __('your_shift_is_in_progress'); ?></h2>
                                            <div class="att-checkin-time">
                                                <div class="att-time-block">
                                                    <i class="feather icon-log-in"></i>
                                                    <div>
                                                        <small><?php echo __('checked_in_at'); ?></small>
                                                        <strong><?php echo date('h:i:s A', strtotime($today_attendance['check_in_time'])); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="att-action-btn-wrap">
                                            <button type="button" class="att-checkout-btn" onclick="checkOut()" id="checkOutBtn">
                                                <div class="att-btn-icon">
                                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                                        <polyline points="16 17 21 12 16 7"/>
                                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                                    </svg>
                                                </div>
                                                <div class="att-btn-text">
                                                    <span><?php echo __('check_out'); ?></span>
                                                    <small><?php echo __('end_your_shift'); ?></small>
                                                </div>
                                            </button>
                                        </div>

                                    <?php else: ?>
                                        <!-- COMPLETED -->
                                        <div class="att-action-visual">
                                            <div class="att-complete-icon">
                                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="att-action-info">
                                            <div class="att-action-status">
                                                <span class="att-status-indicator completed"></span>
                                                <?php echo __('shift_completed'); ?>
                                            </div>
                                            <h2><?php echo __('great_work_today'); ?></h2>
                                            <div class="att-completed-times">
                                                <div class="att-time-block">
                                                    <i class="feather icon-log-in"></i>
                                                    <div>
                                                        <small><?php echo __('check_in'); ?></small>
                                                        <strong><?php echo date('h:i:s A', strtotime($today_attendance['check_in_time'])); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="att-time-divider">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                                        <polyline points="12 5 19 12 12 19"/>
                                                    </svg>
                                                </div>
                                                <div class="att-time-block">
                                                    <i class="feather icon-log-out"></i>
                                                    <div>
                                                        <small><?php echo __('check_out'); ?></small>
                                                        <strong><?php echo date('h:i:s A', strtotime($today_attendance['check_out_time'])); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="att-completed-summary">
                                            <div class="att-summary-item">
                                                <span class="att-summary-value"><?php echo floor($today_attendance['working_minutes'] / 60); ?>h <?php echo $today_attendance['working_minutes'] % 60; ?>m</span>
                                                <span class="att-summary-label"><?php echo __('total_hours'); ?></span>
                                            </div>
                                            <div class="att-summary-divider"></div>
                                            <div class="att-summary-item">
                                                <span class="att-summary-badge <?php echo strtolower($today_attendance['status']); ?>">
                                                    <?php echo __($today_attendance['status']); ?>
                                                </span>
                                                <span class="att-summary-label"><?php echo __('status'); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ========== STATS + WEEK VIEW ========== -->
                            <div class="att-grid">

                                <!-- Monthly Stats -->
                                <div class="att-card">
                                    <div class="att-card-header">
                                        <h3><i class="feather icon-bar-chart-2"></i> <?php echo __('monthly_overview'); ?></h3>
                                        <span class="att-card-badge"><?php echo date('F Y'); ?></span>
                                    </div>
                                    <div class="att-card-body">
                                        <div class="att-monthly-stats">
                                            <div class="att-monthly-item">
                                                <div class="att-monthly-icon present">
                                                    <i class="feather icon-check"></i>
                                                </div>
                                                <div class="att-monthly-info">
                                                    <span class="att-monthly-number"><?php echo $monthly_stats['present_days'] ?? 0; ?></span>
                                                    <span class="att-monthly-label"><?php echo __('present'); ?></span>
                                                </div>
                                            </div>
                                            <div class="att-monthly-item">
                                                <div class="att-monthly-icon late">
                                                    <i class="feather icon-clock"></i>
                                                </div>
                                                <div class="att-monthly-info">
                                                    <span class="att-monthly-number"><?php echo $monthly_stats['late_days'] ?? 0; ?></span>
                                                    <span class="att-monthly-label"><?php echo __('late'); ?></span>
                                                </div>
                                            </div>
                                            <div class="att-monthly-item">
                                                <div class="att-monthly-icon half">
                                                    <i class="feather icon-sun"></i>
                                                </div>
                                                <div class="att-monthly-info">
                                                    <span class="att-monthly-number"><?php echo $monthly_stats['half_days'] ?? 0; ?></span>
                                                    <span class="att-monthly-label"><?php echo __('half_day'); ?></span>
                                                </div>
                                            </div>
                                            <div class="att-monthly-item">
                                                <div class="att-monthly-icon absent">
                                                    <i class="feather icon-x"></i>
                                                </div>
                                                <div class="att-monthly-info">
                                                    <span class="att-monthly-number"><?php echo $monthly_stats['absent_days'] ?? 0; ?></span>
                                                    <span class="att-monthly-label"><?php echo __('absent'); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Average Hours -->
                                        <div class="att-avg-hours">
                                            <div class="att-avg-header">
                                                <span><?php echo __('avg_daily_hours'); ?></span>
                                                <strong><?php echo (int)floor(($monthly_stats['avg_working_minutes'] ?? 0) / 60); ?>h <?php echo (int)round(fmod(($monthly_stats['avg_working_minutes'] ?? 0), 60)); ?>m</strong>
                                            </div>
                                            <?php
                                                $office_start = new DateTime($attendance_settings['office_start_time']);
                                                $office_end = new DateTime($attendance_settings['office_end_time']);
                                                $total_office_minutes = ($office_end->getTimestamp() - $office_start->getTimestamp()) / 60;
                                                $avg_pct = $total_office_minutes > 0 ? min(100, (($monthly_stats['avg_working_minutes'] ?? 0) / $total_office_minutes) * 100) : 0;
                                            ?>
                                            <div class="att-avg-bar">
                                                <div class="att-avg-bar-fill" style="width: <?php echo $avg_pct; ?>%"></div>
                                            </div>
                                            <div class="att-avg-footer">
                                                <span><?php echo __('total_this_month'); ?></span>
                                                <span><?php echo floor(($monthly_stats['total_working_minutes'] ?? 0) / 60); ?>h <?php echo ($monthly_stats['total_working_minutes'] ?? 0) % 60; ?>m</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Week View -->
                                <div class="att-card">
                                    <div class="att-card-header">
                                        <h3><i class="feather icon-calendar"></i> <?php echo __('this_week'); ?></h3>
                                    </div>
                                    <div class="att-card-body">
                                        <div class="att-week-grid">
                                            <?php
                                            $week_map = [];
                                            foreach ($week_attendance as $wa) {
                                                $week_map[$wa['date']] = $wa;
                                            }

                                            $start_of_week = new DateTime();
                                            $day_of_week = $start_of_week->format('N') - 1;
                                            $start_of_week->modify("-{$day_of_week} days");

                                            for ($i = 0; $i < 7; $i++):
                                                $day = clone $start_of_week;
                                                $day->modify("+{$i} days");
                                                $date_str = $day->format('Y-m-d');
                                                $is_today = $date_str === date('Y-m-d');
                                                $is_future = $day > new DateTime();
                                                $day_data = $week_map[$date_str] ?? null;

                                                $day_status = 'none';
                                                if ($day_data) {
                                                    $day_status = strtolower($day_data['status']);
                                                } elseif ($is_future) {
                                                    $day_status = 'future';
                                                }
                                            ?>
                                                <div class="att-week-day <?php echo $is_today ? 'today' : ''; ?> <?php echo $day_status; ?>">
                                                    <span class="att-week-day-name"><?php echo $day->format('D'); ?></span>
                                                    <div class="att-week-day-indicator">
                                                        <?php if ($day_status === 'present'): ?>
                                                            <i class="feather icon-check"></i>
                                                        <?php elseif ($day_status === 'late'): ?>
                                                            <i class="feather icon-clock"></i>
                                                        <?php elseif ($day_status === 'half_day'): ?>
                                                            <i class="feather icon-sun"></i>
                                                        <?php elseif ($day_status === 'absent'): ?>
                                                            <i class="feather icon-x"></i>
                                                        <?php elseif ($day_status === 'future'): ?>
                                                            <span class="att-week-day-num"><?php echo $day->format('d'); ?></span>
                                                        <?php else: ?>
                                                            <span class="att-week-day-num"><?php echo $day->format('d'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($day_data && $day_data['check_in_time']): ?>
                                                        <span class="att-week-day-time"><?php echo date('H:i', strtotime($day_data['check_in_time'])); ?></span>
                                                    <?php elseif ($is_today && $state === 'not_checked_in'): ?>
                                                        <span class="att-week-day-time"><?php echo __('today'); ?></span>
                                                    <?php else: ?>
                                                        <span class="att-week-day-time">—</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== OFFICE HOURS INFO ========== -->
                            <div class="att-info-strip">
                                <div class="att-info-item">
                                    <i class="feather icon-sunrise"></i>
                                    <div>
                                        <small><?php echo __('office_opens'); ?></small>
                                        <strong><?php echo date('h:i A', strtotime($attendance_settings['office_start_time'])); ?></strong>
                                    </div>
                                </div>
                                <div class="att-info-divider"></div>
                                <div class="att-info-item">
                                    <i class="feather icon-sunset"></i>
                                    <div>
                                        <small><?php echo __('office_closes'); ?></small>
                                        <strong><?php echo date('h:i A', strtotime($attendance_settings['office_end_time'])); ?></strong>
                                    </div>
                                </div>
                                <div class="att-info-divider"></div>
                                <div class="att-info-item">
                                    <i class="feather icon-alert-circle"></i>
                                    <div>
                                        <small><?php echo __('late_after'); ?></small>
                                        <strong><?php echo $attendance_settings['late_after_minutes']; ?> <?php echo __('minutes'); ?></strong>
                                    </div>
                                </div>
                                <div class="att-info-divider"></div>
                                <div class="att-info-item">
                                    <i class="feather icon-calendar"></i>
                                    <div>
                                        <small><?php echo __('working_days'); ?></small>
                                        <strong><?php echo $attendance_settings['working_days']; ?></strong>
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
   ATTENDANCE PAGE - COMPLETE REDESIGN
   ============================================ */
:root {
    --att-primary: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --att-primary-light: rgba(64, 153, 255, 0.1);
    --att-primary-dark: #2a7acc;
    --att-success: #10b981;
    --att-success-light: #ecfdf5;
    --att-success-dark: #059669;
    --att-danger: #ef4444;
    --att-danger-light: #fef2f2;
    --att-warning: #f59e0b;
    --att-warning-light: #fffbeb;
    --att-info: #3b82f6;
    --att-info-light: #eff6ff;
    --att-gray-50: #f9fafb;
    --att-gray-100: #f3f4f6;
    --att-gray-200: #e5e7eb;
    --att-gray-300: #d1d5db;
    --att-gray-400: #9ca3af;
    --att-gray-500: #6b7280;
    --att-gray-600: #4b5563;
    --att-gray-700: #374151;
    --att-gray-800: #1f2937;
    --att-gray-900: #111827;
    --att-radius: 16px;
    --att-radius-sm: 10px;
    --att-radius-xs: 6px;
    --att-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --att-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
    --att-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
    --att-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== HERO ===== */
.att-hero {
    border-radius: var(--att-radius);
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    color: #fff;
}

.att-hero.not_checked_in {
    background: linear-gradient(135deg, #475569 0%, #64748b 50%, #94a3b8 100%);
}

.att-hero.checked_in {
    background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
}

.att-hero.completed {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
}

.att-hero-bg {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 1;
}

.att-hero-circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
}

.att-hero-circle.c1 {
    width: 300px; height: 300px;
    top: -100px; right: -50px;
}

.att-hero-circle.c2 {
    width: 200px; height: 200px;
    bottom: -80px; right: 20%;
    background: rgba(255,255,255,0.04);
}

.att-hero-circle.c3 {
    width: 150px; height: 150px;
    top: 20%; left: -40px;
    background: rgba(255,255,255,0.03);
}

.att-hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.att-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 12px;
}

.att-hero h1 {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 8px;
    color: #fff;
}

.att-hero-date {
    margin: 0;
    opacity: 0.8;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Live Clock */
.att-live-clock {
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: var(--att-radius);
    padding: 20px 32px;
    text-align: center;
    min-width: 180px;
}

.att-clock-time {
    font-size: 40px;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -1px;
}

.att-clock-period {
    font-size: 14px;
    font-weight: 600;
    opacity: 0.8;
    margin-top: 4px;
    letter-spacing: 2px;
}

/* ===== ACTION CARD ===== */
.att-action-section {
    margin-bottom: 24px;
}

.att-action-card {
    background: #fff;
    border-radius: var(--att-radius);
    box-shadow: var(--att-shadow-md);
    border: 1px solid var(--att-gray-100);
    padding: 32px;
    display: flex;
    align-items: center;
    gap: 32px;
    flex-wrap: wrap;
    transition: var(--att-transition);
}

.att-action-card:hover {
    box-shadow: var(--att-shadow-lg);
}

.att-action-visual {
    flex-shrink: 0;
}

.att-action-info {
    flex: 1;
    min-width: 200px;
}

.att-action-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--att-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.att-status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.att-status-indicator.offline {
    background: var(--att-gray-400);
}

.att-status-indicator.online {
    background: var(--att-success);
}

.att-status-indicator.online.pulse {
    animation: statusPulse 2s infinite;
}

.att-status-indicator.completed {
    background: var(--att-primary);
}

@keyframes statusPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
}

.att-action-info h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--att-gray-900);
    margin: 0 0 8px;
}

.att-action-info p {
    color: var(--att-gray-500);
    font-size: 14px;
    margin: 0;
}

/* Pulse Ring (Not checked in) */
.att-pulse-ring {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--att-gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.att-pulse-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--att-gray-400);
}

/* Progress Ring (Checked in) */
.att-progress-ring-container {
    width: 100px;
    height: 100px;
    position: relative;
}

.att-progress-ring {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.att-ring-bg {
    fill: none;
    stroke: var(--att-gray-100);
    stroke-width: 8;
}

.att-ring-fill {
    fill: none;
    stroke: var(--att-success);
    stroke-width: 8;
    stroke-linecap: round;
    stroke-dasharray: 326.73;
    stroke-dashoffset: calc(326.73 - (326.73 * var(--progress, 0) / 100));
    transition: stroke-dashoffset 1.5s ease;
}

.att-progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.att-progress-number {
    display: block;
    font-size: 16px;
    font-weight: 800;
    color: var(--att-gray-900);
    line-height: 1;
}

.att-progress-label {
    display: block;
    font-size: 10px;
    color: var(--att-success);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

/* Complete Icon */
.att-complete-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--att-primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--att-primary);
}

/* Time Blocks */
.att-checkin-time,
.att-completed-times {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 12px;
}

.att-time-block {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--att-gray-50);
    padding: 10px 16px;
    border-radius: var(--att-radius-sm);
    border: 1px solid var(--att-gray-100);
}

.att-time-block i {
    color: var(--att-primary);
    font-size: 16px;
}

.att-time-block small {
    display: block;
    font-size: 11px;
    color: var(--att-gray-400);
    font-weight: 500;
}

.att-time-block strong {
    display: block;
    font-size: 14px;
    color: var(--att-gray-800);
    font-weight: 700;
}

.att-time-divider {
    color: var(--att-gray-300);
}

/* Check In/Out Buttons */
.att-action-btn-wrap {
    flex-shrink: 0;
}

.att-checkin-btn,
.att-checkout-btn {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 28px;
    border: none;
    border-radius: 14px;
    font-size: 16px;
    cursor: pointer;
    transition: var(--att-transition);
    color: #fff;
    min-width: 200px;
}

.att-checkin-btn {
    background: linear-gradient(135deg, var(--att-success), var(--att-success-dark));
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
}

.att-checkin-btn:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}

.att-checkout-btn {
    background: linear-gradient(135deg, var(--att-danger), #dc2626);
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
}

.att-checkout-btn:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}

.att-checkin-btn:active,
.att-checkout-btn:active {
    transform: translateY(0) scale(0.98);
}

.att-btn-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.att-btn-text span {
    display: block;
    font-weight: 700;
    font-size: 16px;
}

.att-btn-text small {
    display: block;
    font-size: 12px;
    opacity: 0.8;
    font-weight: 400;
}

/* Completed Summary */
.att-completed-summary {
    display: flex;
    align-items: center;
    gap: 20px;
    background: var(--att-gray-50);
    border: 1px solid var(--att-gray-100);
    border-radius: var(--att-radius-sm);
    padding: 16px 24px;
    flex-shrink: 0;
}

.att-summary-item {
    text-align: center;
}

.att-summary-value {
    display: block;
    font-size: 20px;
    font-weight: 800;
    color: var(--att-gray-900);
}

.att-summary-label {
    display: block;
    font-size: 11px;
    color: var(--att-gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-top: 2px;
}

.att-summary-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.att-summary-badge.present {
    background: var(--att-success-light);
    color: var(--att-success);
}

.att-summary-badge.late {
    background: var(--att-warning-light);
    color: var(--att-warning);
}

.att-summary-badge.half_day {
    background: var(--att-info-light);
    color: var(--att-info);
}

.att-summary-badge.absent {
    background: var(--att-danger-light);
    color: var(--att-danger);
}

.att-summary-divider {
    width: 1px;
    height: 40px;
    background: var(--att-gray-200);
}

/* ===== GRID CARDS ===== */
.att-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.att-card {
    background: #fff;
    border-radius: var(--att-radius);
    box-shadow: var(--att-shadow);
    border: 1px solid var(--att-gray-100);
    overflow: hidden;
    transition: var(--att-transition);
}

.att-card:hover {
    box-shadow: var(--att-shadow-md);
}

.att-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--att-gray-100);
}

.att-card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--att-gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.att-card-header h3 i {
    color: var(--att-primary);
}

.att-card-badge {
    background: var(--att-primary-light);
    color: var(--att-primary);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.att-card-body {
    padding: 24px;
}

/* Monthly Stats */
.att-monthly-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}

.att-monthly-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: var(--att-radius-sm);
    border: 1px solid var(--att-gray-100);
    transition: var(--att-transition);
}

.att-monthly-item:hover {
    border-color: var(--att-gray-200);
    background: var(--att-gray-50);
}

.att-monthly-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.att-monthly-icon.present { background: var(--att-success-light); color: var(--att-success); }
.att-monthly-icon.late { background: var(--att-warning-light); color: var(--att-warning); }
.att-monthly-icon.half { background: var(--att-info-light); color: var(--att-info); }
.att-monthly-icon.absent { background: var(--att-danger-light); color: var(--att-danger); }

.att-monthly-number {
    display: block;
    font-size: 20px;
    font-weight: 800;
    color: var(--att-gray-900);
    line-height: 1;
}

.att-monthly-label {
    display: block;
    font-size: 11px;
    color: var(--att-gray-400);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

/* Average Hours Bar */
.att-avg-hours {
    background: var(--att-gray-50);
    border-radius: var(--att-radius-sm);
    padding: 16px;
    border: 1px solid var(--att-gray-100);
}

.att-avg-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 13px;
}

.att-avg-header span {
    color: var(--att-gray-500);
    font-weight: 500;
}

.att-avg-header strong {
    color: var(--att-gray-800);
    font-weight: 700;
}

.att-avg-bar {
    height: 8px;
    background: var(--att-gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.att-avg-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--att-primary), #8b5cf6);
    border-radius: 4px;
    transition: width 1.5s ease;
}

.att-avg-footer {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: var(--att-gray-400);
}

/* ===== WEEK VIEW ===== */
.att-week-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}

.att-week-day {
    text-align: center;
    padding: 12px 6px;
    border-radius: var(--att-radius-sm);
    border: 2px solid var(--att-gray-100);
    transition: var(--att-transition);
    background: #fff;
}

.att-week-day.today {
    border-color: var(--att-primary);
    background: var(--att-primary-light);
}

.att-week-day-name {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: var(--att-gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.att-week-day.today .att-week-day-name {
    color: var(--att-primary);
}

.att-week-day-indicator {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    background: var(--att-gray-50);
    color: var(--att-gray-400);
}

.att-week-day.present .att-week-day-indicator {
    background: var(--att-success-light);
    color: var(--att-success);
}

.att-week-day.late .att-week-day-indicator {
    background: var(--att-warning-light);
    color: var(--att-warning);
}

.att-week-day.half_day .att-week-day-indicator {
    background: var(--att-info-light);
    color: var(--att-info);
}

.att-week-day.absent .att-week-day-indicator {
    background: var(--att-danger-light);
    color: var(--att-danger);
}

.att-week-day.today .att-week-day-indicator {
    background: var(--att-primary);
    color: #fff;
}

.att-week-day-num {
    font-size: 13px;
    font-weight: 700;
}

.att-week-day-time {
    display: block;
    font-size: 11px;
    color: var(--att-gray-400);
    font-weight: 500;
}

/* ===== INFO STRIP ===== */
.att-info-strip {
    background: #fff;
    border-radius: var(--att-radius);
    box-shadow: var(--att-shadow);
    border: 1px solid var(--att-gray-100);
    padding: 20px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.att-info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.att-info-item i {
    font-size: 20px;
    color: var(--att-primary);
    width: 40px;
    height: 40px;
    background: var(--att-primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.att-info-item small {
    display: block;
    font-size: 11px;
    color: var(--att-gray-400);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.att-info-item strong {
    display: block;
    font-size: 14px;
    color: var(--att-gray-800);
    font-weight: 700;
}

.att-info-divider {
    width: 1px;
    height: 36px;
    background: var(--att-gray-200);
    flex-shrink: 0;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .att-monthly-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .att-grid {
        grid-template-columns: 1fr;
    }

    .att-hero-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }

    .att-action-card {
        flex-direction: column;
        text-align: center;
    }

    .att-checkin-time,
    .att-completed-times {
        justify-content: center;
        flex-wrap: wrap;
    }

    .att-info-strip {
        flex-wrap: wrap;
        gap: 16px;
    }

    .att-info-divider {
        display: none;
    }

    .att-info-item {
        min-width: calc(50% - 16px);
    }
}

@media (max-width: 768px) {
    .att-hero {
        padding: 24px 20px;
    }

    .att-hero h1 {
        font-size: 20px;
    }

    .att-clock-time {
        font-size: 30px;
    }

    .att-action-card {
        padding: 24px;
    }

    .att-week-grid {
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
    }

    .att-week-day {
        padding: 8px 4px;
    }

    .att-week-day-indicator {
        width: 30px;
        height: 30px;
        font-size: 14px;
    }

    .att-monthly-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .att-checkin-btn,
    .att-checkout-btn {
        min-width: unset;
        width: 100%;
        justify-content: center;
    }

    .att-info-item {
        min-width: 100%;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.att-hero {
    animation: fadeInUp 0.5s ease forwards;
}

.att-action-card {
    animation: scaleIn 0.5s ease forwards;
    animation-delay: 0.1s;
    opacity: 0;
}

.att-card {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

.att-card:nth-child(1) { animation-delay: 0.2s; }
.att-card:nth-child(2) { animation-delay: 0.3s; }

.att-info-strip {
    animation: fadeInUp 0.5s ease forwards;
    animation-delay: 0.4s;
    opacity: 0;
}

/* Button loading state */
.att-btn-loading {
    pointer-events: none;
    opacity: 0.8;
}

.att-btn-loading .att-btn-icon {
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// ===== LIVE CLOCK =====
function updateClock() {
    const now = new Date();
    const hours = now.getHours();
    const h12 = hours % 12 || 12;
    const mins = String(now.getMinutes()).padStart(2, '0');
    const secs = String(now.getSeconds()).padStart(2, '0');
    const period = hours >= 12 ? 'PM' : 'AM';

    document.getElementById('current-time').textContent = `${String(h12).padStart(2, '0')}:${mins}:${secs}`;
    document.getElementById('current-period').textContent = period;
}

setInterval(updateClock, 1000);
updateClock();

// ===== UPDATE PROGRESS (if checked in) =====
<?php if ($state === 'checked_in'): ?>
(function() {
    const checkinTime = new Date('<?php echo date('Y-m-d') . 'T' . $today_attendance['check_in_time']; ?>');
    const totalMinutes = <?php echo $total_office_minutes; ?>;

    function updateProgress() {
        const now = new Date();
        const elapsed = (now - checkinTime) / 60000;
        const pct = Math.min(100, (elapsed / totalMinutes) * 100);
        const hours = Math.floor(elapsed / 60);
        const mins = Math.round(elapsed % 60);

        const ring = document.querySelector('.att-ring-fill');
        if (ring) {
            ring.style.strokeDashoffset = 326.73 - (326.73 * pct / 100);
        }

        const numEl = document.querySelector('.att-progress-number');
        if (numEl) {
            numEl.textContent = `${hours}h ${mins}m`;
        }
    }

    setInterval(updateProgress, 30000);
})();
<?php endif; ?>

// ===== CHECK IN =====
function checkIn() {
    if (!confirm('<?php echo __("confirm_check_in"); ?>')) return;

    const btn = document.getElementById('checkInBtn');
    btn.classList.add('att-btn-loading');

    fetch('../api/attendance/process_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'check_in' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Smooth transition before reload
            document.querySelector('.att-action-card').style.opacity = '0';
            document.querySelector('.att-action-card').style.transform = 'scale(0.95)';
            setTimeout(() => location.reload(), 300);
        } else {
            btn.classList.remove('att-btn-loading');
            showToast(data.message || '<?php echo __("error_occurred"); ?>', 'error');
        }
    })
    .catch(() => {
        btn.classList.remove('att-btn-loading');
        showToast('<?php echo __("error_occurred"); ?>', 'error');
    });
}

// ===== CHECK OUT =====
function checkOut() {
    if (!confirm('<?php echo __("confirm_check_out"); ?>')) return;

    const btn = document.getElementById('checkOutBtn');
    btn.classList.add('att-btn-loading');

    fetch('../api/attendance/process_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'check_out' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector('.att-action-card').style.opacity = '0';
            document.querySelector('.att-action-card').style.transform = 'scale(0.95)';
            setTimeout(() => location.reload(), 300);
        } else {
            btn.classList.remove('att-btn-loading');
            showToast(data.message || '<?php echo __("error_occurred"); ?>', 'error');
        }
    })
    .catch(() => {
        btn.classList.remove('att-btn-loading');
        showToast('<?php echo __("error_occurred"); ?>', 'error');
    });
}

// ===== SIMPLE TOAST =====
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        background: ${type === 'error' ? '#ef4444' : '#10b981'}; color: #fff;
        padding: 14px 24px; border-radius: 12px; font-size: 14px; font-weight: 600;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        animation: fadeInUp 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php include '../includes/admin_footer.php'; ?>