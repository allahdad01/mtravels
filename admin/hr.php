<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get employee statistics
$stats_query = "
    SELECT
        COUNT(*) as total_employees,
        SUM(CASE WHEN fired = 0 THEN 1 ELSE 0 END) as active_employees,
        SUM(CASE WHEN fired = 1 THEN 1 ELSE 0 END) as fired_employees,
        COUNT(DISTINCT CASE WHEN DATE(hire_date) = CURDATE() THEN id END) as new_hires_today
    FROM users
    WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND role != 'tenant_super_admin'
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$tenant_id, $branch_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly hire/termination trend (last 6 months)
$trend_query = "
    SELECT
        DATE_FORMAT(hire_date, '%Y-%m') as month,
        COUNT(*) as hires,
        SUM(CASE WHEN fired = 1 AND DATE_FORMAT(fired_at, '%Y-%m') = DATE_FORMAT(hire_date, '%Y-%m') THEN 1 ELSE 0 END) as terminations
    FROM users
    WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND role != 'tenant_super_admin'
    AND hire_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(hire_date, '%Y-%m')
    ORDER BY month ASC
";
$stmt = $pdo->prepare($trend_query);
$stmt->execute([$tenant_id, $branch_id]);
$trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department distribution
$dept_query = "
    SELECT
        COALESCE(role, 'Unassigned') as department,
        COUNT(*) as count
    FROM users
    WHERE tenant_id = ? AND branch_id = ? AND fired = 0 AND role != 'super_admin' AND role != 'tenant_super_admin'
    GROUP BY role
    ORDER BY count DESC
    LIMIT 5
";
$stmt = $pdo->prepare($dept_query);
$stmt->execute([$tenant_id, $branch_id]);
$dept_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activities
$recent_activities_query = "
    SELECT
        u.name,
        u.email,
        u.fired,
        u.fired_at,
        u.hire_date,
        u.role,
        CASE
            WHEN u.fired = 1 THEN 'Terminated'
            WHEN DATE(u.hire_date) = CURDATE() THEN 'Hired'
            ELSE 'Active'
        END as activity_type,
        CASE
            WHEN u.fired = 1 THEN u.fired_at
            ELSE u.hire_date
        END as activity_date
    FROM users u
    WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin' AND role != 'tenant_super_admin'
    ORDER BY activity_date DESC
    LIMIT 8
";
$stmt = $pdo->prepare($recent_activities_query);
$stmt->execute([$tenant_id, $branch_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending salary payments
$pending_salaries_query = "
    SELECT COUNT(*) as pending_salaries
    FROM salary_management sm
    WHERE sm.tenant_id = ? AND sm.branch_id = ? AND sm.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM salary_payments sp
        WHERE sp.user_id = sm.user_id AND sp.tenant_id = ? AND sp.branch_id = ?
        AND sp.payment_for_month = DATE_FORMAT(CURDATE(), '%Y-%m-01')
    )
";
$stmt = $pdo->prepare($pending_salaries_query);
$stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id]);
$pending_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate retention rate
$total = $stats['total_employees'] ?? 1;
$active = $stats['active_employees'] ?? 0;
$retention_rate = $total > 0 ? round(($active / $total) * 100, 1) : 0;

$page_title = __('hr_management');
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
                            <div class="hr-hero">
                                <div class="hr-hero-content">
                                    <div class="hr-hero-left">
                                        <div class="hr-hero-greeting">
                                            <span class="hr-badge">
                                                <i class="feather icon-briefcase"></i>
                                                <?php echo __('hr_management'); ?>
                                            </span>
                                            <h1><?php echo __('manage_employee_lifecycle_and_hr_operations'); ?></h1>
                                            <p><?php echo date('l, F j, Y'); ?></p>
                                        </div>
                                    </div>
                                    <div class="hr-hero-right">
                                        <a href="add_employee.php" class="hr-hero-btn primary">
                                            <i class="feather icon-user-plus"></i>
                                            <?php echo __('add_employee'); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="hr-hero-shape"></div>
                            </div>

                            <!-- ========== STAT CARDS ========== -->
                            <div class="hr-stats-grid">
                                <div class="hr-stat-card">
                                    <div class="hr-stat-icon total">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        </svg>
                                    </div>
                                    <div class="hr-stat-info">
                                        <span class="hr-stat-number"><?php echo $stats['total_employees'] ?? 0; ?></span>
                                        <span class="hr-stat-label"><?php echo __('total_employees'); ?></span>
                                    </div>
                                    <div class="hr-stat-footer">
                                        <span class="hr-stat-trend neutral">
                                            <i class="feather icon-minus"></i> <?php echo __('all_time'); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="hr-stat-card">
                                    <div class="hr-stat-icon active">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="8.5" cy="7" r="4"/>
                                            <polyline points="17 11 19 13 23 9"/>
                                        </svg>
                                    </div>
                                    <div class="hr-stat-info">
                                        <span class="hr-stat-number"><?php echo $stats['active_employees'] ?? 0; ?></span>
                                        <span class="hr-stat-label"><?php echo __('active_employees'); ?></span>
                                    </div>
                                    <div class="hr-stat-footer">
                                        <span class="hr-stat-trend up">
                                            <i class="feather icon-trending-up"></i> <?php echo $retention_rate; ?>% <?php echo __('retention'); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="hr-stat-card">
                                    <div class="hr-stat-icon terminated">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="8.5" cy="7" r="4"/>
                                            <line x1="18" y1="8" x2="23" y2="13"/>
                                            <line x1="23" y1="8" x2="18" y2="13"/>
                                        </svg>
                                    </div>
                                    <div class="hr-stat-info">
                                        <span class="hr-stat-number"><?php echo $stats['fired_employees'] ?? 0; ?></span>
                                        <span class="hr-stat-label"><?php echo __('terminated_employees'); ?></span>
                                    </div>
                                    <div class="hr-stat-footer">
                                        <span class="hr-stat-trend down">
                                            <i class="feather icon-trending-down"></i>
                                            <?php echo $total > 0 ? round((($stats['fired_employees'] ?? 0) / $total) * 100, 1) : 0; ?>% <?php echo __('turnover'); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="hr-stat-card">
                                    <div class="hr-stat-icon new-hire">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="8.5" cy="7" r="4"/>
                                            <line x1="20" y1="8" x2="20" y2="14"/>
                                            <line x1="23" y1="11" x2="17" y2="11"/>
                                        </svg>
                                    </div>
                                    <div class="hr-stat-info">
                                        <span class="hr-stat-number"><?php echo $stats['new_hires_today'] ?? 0; ?></span>
                                        <span class="hr-stat-label"><?php echo __('new_hires_today'); ?></span>
                                    </div>
                                    <div class="hr-stat-footer">
                                        <span class="hr-stat-trend neutral">
                                            <i class="feather icon-calendar"></i> <?php echo __('today'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== PENDING TASKS BANNER ========== -->
                            <?php if (($pending_data['pending_salaries'] ?? 0) > 0): ?>
                            <div class="hr-alert-banner">
                                <div class="hr-alert-icon">
                                    <i class="feather icon-alert-triangle"></i>
                                </div>
                                <div class="hr-alert-content">
                                    <strong><?php echo __('pending_salary_payments'); ?></strong>
                                    <p><?php echo sprintf(__('x_employees_awaiting_salary'), $pending_data['pending_salaries']); ?></p>
                                </div>
                                <a href="salary_management.php" class="hr-alert-action">
                                    <?php echo __('process_now'); ?>
                                    <i class="feather icon-arrow-right"></i>
                                </a>
                            </div>
                            <?php endif; ?>

                            <!-- ========== MAIN GRID ========== -->
                            <div class="hr-main-grid">

                                <!-- Quick Actions - Redesigned as Icon Cards -->
                                <div class="hr-section">
                                    <div class="hr-section-header">
                                        <h3><i class="feather icon-grid"></i> <?php echo __('quick_actions'); ?></h3>
                                    </div>
                                    <div class="hr-actions-grid">
                                        <a href="employee_management.php" class="hr-action-card">
                                            <div class="hr-action-icon" style="--accent: #4099ff;">
                                                <i class="feather icon-users"></i>
                                            </div>
                                            <div class="hr-action-info">
                                                <h4><?php echo __('manage_employees'); ?></h4>
                                                <p><?php echo __('view_edit_employee_records'); ?></p>
                                            </div>
                                            <i class="feather icon-chevron-right hr-action-arrow"></i>
                                        </a>

                                        <a href="salary_management.php" class="hr-action-card">
                                            <div class="hr-action-icon" style="--accent: #2ed8b6;">
                                                <i class="feather icon-dollar-sign"></i>
                                            </div>
                                            <div class="hr-action-info">
                                                <h4><?php echo __('salary_management'); ?></h4>
                                                <p><?php echo __('manage_payroll_and_compensation'); ?></p>
                                            </div>
                                            <i class="feather icon-chevron-right hr-action-arrow"></i>
                                        </a>

                                        <a href="employee_performance.php" class="hr-action-card">
                                            <div class="hr-action-icon" style="--accent: #FFB64D;">
                                                <i class="feather icon-trending-up"></i>
                                            </div>
                                            <div class="hr-action-info">
                                                <h4><?php echo __('performance_reviews'); ?></h4>
                                                <p><?php echo __('track_and_evaluate_performance'); ?></p>
                                            </div>
                                            <i class="feather icon-chevron-right hr-action-arrow"></i>
                                        </a>

                                        <a href="hr_reports.php" class="hr-action-card">
                                            <div class="hr-action-icon" style="--accent: #FF5370;">
                                                <i class="feather icon-file-text"></i>
                                            </div>
                                            <div class="hr-action-info">
                                                <h4><?php echo __('hr_reports'); ?></h4>
                                                <p><?php echo __('generate_hr_analytics_reports'); ?></p>
                                            </div>
                                            <i class="feather icon-chevron-right hr-action-arrow"></i>
                                        </a>
                                    </div>
                                </div>

                                <!-- Activity Timeline -->
                                <div class="hr-section">
                                    <div class="hr-section-header">
                                        <h3><i class="feather icon-activity"></i> <?php echo __('recent_hr_activities'); ?></h3>
                                        <a href="employee_management.php" class="hr-section-link">
                                            <?php echo __('view_all'); ?> <i class="feather icon-arrow-right"></i>
                                        </a>
                                    </div>

                                    <?php if (empty($recent_activities)): ?>
                                        <div class="hr-empty-state">
                                            <i class="feather icon-inbox"></i>
                                            <p><?php echo __('no_recent_activities'); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <div class="hr-timeline">
                                            <?php foreach ($recent_activities as $index => $activity): ?>
                                                <div class="hr-timeline-item <?php echo $activity['activity_type'] === 'Terminated' ? 'terminated' : ($activity['activity_type'] === 'Hired' ? 'hired' : 'active'); ?>">
                                                    <div class="hr-timeline-dot"></div>
                                                    <div class="hr-timeline-content">
                                                        <div class="hr-timeline-header">
                                                            <div class="hr-timeline-avatar">
                                                                <?php echo strtoupper(substr($activity['name'], 0, 1)); ?>
                                                            </div>
                                                            <div class="hr-timeline-info">
                                                                <h5><?php echo htmlspecialchars($activity['name']); ?></h5>
                                                                <span class="hr-timeline-role"><?php echo htmlspecialchars($activity['role'] ?? ''); ?></span>
                                                            </div>
                                                            <span class="hr-timeline-badge <?php echo $activity['activity_type'] === 'Terminated' ? 'danger' : ($activity['activity_type'] === 'Hired' ? 'success' : 'primary'); ?>">
                                                                <?php
                                                                    if ($activity['activity_type'] === 'Terminated') echo __('terminated');
                                                                    elseif ($activity['activity_type'] === 'Hired') echo __('hired');
                                                                    else echo __('active');
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <div class="hr-timeline-date">
                                                            <i class="feather icon-clock"></i>
                                                            <?php echo $activity['activity_date'] ? date('M d, Y', strtotime($activity['activity_date'])) : 'N/A'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ========== RETENTION RING + DEPARTMENT CHART ========== -->
                            <div class="hr-charts-grid">
                                <div class="hr-chart-card">
                                    <div class="hr-section-header">
                                        <h3><i class="feather icon-pie-chart"></i> <?php echo __('retention_rate'); ?></h3>
                                    </div>
                                    <div class="hr-retention-ring">
                                        <div class="hr-ring-container">
                                            <svg viewBox="0 0 120 120" class="hr-ring-svg">
                                                <circle cx="60" cy="60" r="50" class="hr-ring-bg"/>
                                                <circle cx="60" cy="60" r="50" class="hr-ring-fill"
                                                    style="--percentage: <?php echo $retention_rate; ?>;
                                                           --color: <?php echo $retention_rate >= 80 ? '#2ed8b6' : ($retention_rate >= 50 ? '#FFB64D' : '#FF5370'); ?>"/>
                                            </svg>
                                            <div class="hr-ring-text">
                                                <span class="hr-ring-number"><?php echo $retention_rate; ?>%</span>
                                                <span class="hr-ring-label"><?php echo __('retention'); ?></span>
                                            </div>
                                        </div>
                                        <div class="hr-ring-legend">
                                            <div class="hr-ring-legend-item">
                                                <span class="dot active"></span>
                                                <?php echo __('active'); ?>: <?php echo $stats['active_employees'] ?? 0; ?>
                                            </div>
                                            <div class="hr-ring-legend-item">
                                                <span class="dot terminated"></span>
                                                <?php echo __('terminated'); ?>: <?php echo $stats['fired_employees'] ?? 0; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="hr-chart-card">
                                    <div class="hr-section-header">
                                        <h3><i class="feather icon-bar-chart-2"></i> <?php echo __('role_distribution'); ?></h3>
                                    </div>
                                    <div class="hr-bar-chart">
                                        <?php
                                        $max_count = max(array_column($dept_data, 'count') ?: [1]);
                                        $bar_colors = ['#4099ff', '#2ed8b6', '#FFB64D', '#FF5370', '#7C4DFF'];
                                        foreach ($dept_data as $i => $dept):
                                            $percentage = ($dept['count'] / $max_count) * 100;
                                            $color = $bar_colors[$i % count($bar_colors)];
                                        ?>
                                            <div class="hr-bar-item">
                                                <div class="hr-bar-label">
                                                    <span><?php echo htmlspecialchars(ucfirst($dept['department'])); ?></span>
                                                    <span class="hr-bar-count"><?php echo $dept['count']; ?></span>
                                                </div>
                                                <div class="hr-bar-track">
                                                    <div class="hr-bar-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>;"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (empty($dept_data)): ?>
                                            <div class="hr-empty-state small">
                                                <i class="feather icon-bar-chart"></i>
                                                <p><?php echo __('no_data_available'); ?></p>
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
</div>

<style>
/* ============================================
   HR MANAGEMENT - COMPLETE REDESIGN
   ============================================ */

:root {
    --hr-primary: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --hr-primary-light: rgba(64, 153, 255, 0.1);
    --hr-success: #10b981;
    --hr-success-light: #ecfdf5;
    --hr-danger: #ef4444;
    --hr-danger-light: #fef2f2;
    --hr-warning: #f59e0b;
    --hr-warning-light: #fffbeb;
    --hr-info: #3b82f6;
    --hr-info-light: #eff6ff;
    --hr-gray-50: #f9fafb;
    --hr-gray-100: #f3f4f6;
    --hr-gray-200: #e5e7eb;
    --hr-gray-300: #d1d5db;
    --hr-gray-400: #9ca3af;
    --hr-gray-500: #6b7280;
    --hr-gray-600: #4b5563;
    --hr-gray-700: #374151;
    --hr-gray-800: #1f2937;
    --hr-gray-900: #111827;
    --hr-radius: 16px;
    --hr-radius-sm: 10px;
    --hr-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --hr-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
    --hr-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
}

/* ===== HERO HEADER ===== */
.hr-hero {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-radius: var(--hr-radius);
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    color: #fff;
}

.hr-hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.hr-hero-shape {
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    z-index: 1;
}

.hr-hero-shape::before {
    content: '';
    position: absolute;
    top: 60px;
    left: 60px;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}

.hr-badge {
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

.hr-hero-greeting h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 6px;
    color: #fff;
}

.hr-hero-greeting p {
    margin: 0;
    opacity: 0.8;
    font-size: 14px;
}

.hr-hero-right {
    display: flex;
    gap: 10px;
}

.hr-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: var(--hr-radius-sm);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.hr-hero-btn.primary {
    background: var(--hr-primary);
    color: #fff;
}

.hr-hero-btn.primary:hover {
    background: #f0f7ff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    text-decoration: none;
    color: var(--hr-primary);
}

.hr-hero-btn.secondary {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
}

.hr-hero-btn.secondary:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-1px);
    text-decoration: none;
    color: #fff;
}

/* ===== STAT CARDS ===== */
.hr-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.hr-stat-card {
    background: #fff;
    border-radius: var(--hr-radius);
    padding: 22px;
    box-shadow: var(--hr-shadow);
    border: 1px solid var(--hr-gray-100);
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.hr-stat-card:hover {
    box-shadow: var(--hr-shadow-lg);
    transform: translateY(-2px);
    border-color: var(--hr-gray-200);
}

.hr-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hr-stat-icon.total {
    background: var(--hr-primary-light);
    color: var(--hr-primary);
}

.hr-stat-icon.active {
    background: var(--hr-success-light);
    color: var(--hr-success);
}

.hr-stat-icon.terminated {
    background: var(--hr-danger-light);
    color: var(--hr-danger);
}

.hr-stat-icon.new-hire {
    background: var(--hr-info-light);
    color: var(--hr-info);
}

.hr-stat-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hr-stat-number {
    font-size: 30px;
    font-weight: 800;
    color: var(--hr-gray-900);
    line-height: 1;
}

.hr-stat-label {
    font-size: 13px;
    color: var(--hr-gray-500);
    font-weight: 500;
}

.hr-stat-footer {
    padding-top: 12px;
    border-top: 1px solid var(--hr-gray-100);
}

.hr-stat-trend {
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.hr-stat-trend.up { color: var(--hr-success); }
.hr-stat-trend.down { color: var(--hr-danger); }
.hr-stat-trend.neutral { color: var(--hr-gray-400); }

/* ===== ALERT BANNER ===== */
.hr-alert-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #fbbf24;
    border-radius: var(--hr-radius);
    padding: 16px 24px;
    margin-bottom: 24px;
}

.hr-alert-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(245, 158, 11, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #b45309;
    font-size: 20px;
    flex-shrink: 0;
}

.hr-alert-content {
    flex: 1;
}

.hr-alert-content strong {
    color: #92400e;
    font-size: 14px;
}

.hr-alert-content p {
    margin: 2px 0 0;
    color: #a16207;
    font-size: 13px;
}

.hr-alert-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f59e0b;
    color: #fff;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
}

.hr-alert-action:hover {
    background: #d97706;
    color: #fff;
    text-decoration: none;
    transform: translateX(2px);
}

/* ===== MAIN GRID ===== */
.hr-main-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.hr-section {
    background: #fff;
    border-radius: var(--hr-radius);
    box-shadow: var(--hr-shadow);
    border: 1px solid var(--hr-gray-100);
    overflow: hidden;
}

.hr-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--hr-gray-100);
}

.hr-section-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--hr-gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hr-section-header h3 i {
    color: var(--hr-primary);
    font-size: 18px;
}

.hr-section-link {
    font-size: 13px;
    color: var(--hr-primary);
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}

.hr-section-link:hover {
    color: #3730a3;
    gap: 6px;
    text-decoration: none;
}

/* ===== ACTION CARDS ===== */
.hr-actions-grid {
    padding: 8px;
}

.hr-action-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: var(--hr-radius-sm);
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.hr-action-card:hover {
    background: var(--hr-gray-50);
    border-color: var(--hr-gray-200);
    text-decoration: none;
    transform: translateX(4px);
}

.hr-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--accent) 12%, transparent);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: all 0.2s;
}

.hr-action-card:hover .hr-action-icon {
    background: var(--accent);
    color: #fff;
}

.hr-action-info {
    flex: 1;
}

.hr-action-info h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--hr-gray-800);
    margin: 0 0 2px;
}

.hr-action-info p {
    font-size: 12px;
    color: var(--hr-gray-400);
    margin: 0;
}

.hr-action-arrow {
    color: var(--hr-gray-300);
    transition: all 0.2s;
}

.hr-action-card:hover .hr-action-arrow {
    color: var(--hr-primary);
    transform: translateX(2px);
}

/* ===== TIMELINE ===== */
.hr-timeline {
    padding: 16px 24px;
    max-height: 420px;
    overflow-y: auto;
}

.hr-timeline::-webkit-scrollbar {
    width: 4px;
}

.hr-timeline::-webkit-scrollbar-track {
    background: transparent;
}

.hr-timeline::-webkit-scrollbar-thumb {
    background: var(--hr-gray-200);
    border-radius: 4px;
}

.hr-timeline-item {
    position: relative;
    padding-left: 28px;
    padding-bottom: 20px;
    border-left: 2px solid var(--hr-gray-200);
    margin-left: 6px;
}

.hr-timeline-item:last-child {
    padding-bottom: 0;
    border-left-color: transparent;
}

.hr-timeline-dot {
    position: absolute;
    left: -7px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px var(--hr-gray-300);
    background: var(--hr-gray-300);
}

.hr-timeline-item.hired .hr-timeline-dot {
    background: var(--hr-success);
    box-shadow: 0 0 0 2px var(--hr-success);
}

.hr-timeline-item.terminated .hr-timeline-dot {
    background: var(--hr-danger);
    box-shadow: 0 0 0 2px var(--hr-danger);
}

.hr-timeline-item.active .hr-timeline-dot {
    background: var(--hr-primary);
    box-shadow: 0 0 0 2px var(--hr-primary);
}

.hr-timeline-content {
    background: var(--hr-gray-50);
    border-radius: var(--hr-radius-sm);
    padding: 14px 16px;
    border: 1px solid var(--hr-gray-100);
    transition: all 0.2s;
}

.hr-timeline-content:hover {
    border-color: var(--hr-gray-200);
    box-shadow: var(--hr-shadow);
}

.hr-timeline-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hr-timeline-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--hr-primary), #7c3aed);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    flex-shrink: 0;
}

.hr-timeline-info {
    flex: 1;
}

.hr-timeline-info h5 {
    font-size: 14px;
    font-weight: 600;
    color: var(--hr-gray-800);
    margin: 0;
}

.hr-timeline-role {
    font-size: 11px;
    color: var(--hr-gray-400);
    text-transform: capitalize;
}

.hr-timeline-badge {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.hr-timeline-badge.success {
    background: var(--hr-success-light);
    color: var(--hr-success);
}

.hr-timeline-badge.danger {
    background: var(--hr-danger-light);
    color: var(--hr-danger);
}

.hr-timeline-badge.primary {
    background: var(--hr-primary-light);
    color: var(--hr-primary);
}

.hr-timeline-date {
    margin-top: 8px;
    font-size: 12px;
    color: var(--hr-gray-400);
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ===== CHARTS GRID ===== */
.hr-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.hr-chart-card {
    background: #fff;
    border-radius: var(--hr-radius);
    box-shadow: var(--hr-shadow);
    border: 1px solid var(--hr-gray-100);
    overflow: hidden;
}

/* ===== RETENTION RING ===== */
.hr-retention-ring {
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.hr-ring-container {
    position: relative;
    width: 160px;
    height: 160px;
}

.hr-ring-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.hr-ring-bg {
    fill: none;
    stroke: var(--hr-gray-100);
    stroke-width: 10;
}

.hr-ring-fill {
    fill: none;
    stroke: var(--color, var(--hr-success));
    stroke-width: 10;
    stroke-linecap: round;
    stroke-dasharray: 314.16;
    stroke-dashoffset: calc(314.16 - (314.16 * var(--percentage, 0) / 100));
    transition: stroke-dashoffset 1.5s ease;
}

.hr-ring-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.hr-ring-number {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: var(--hr-gray-900);
    line-height: 1;
}

.hr-ring-label {
    display: block;
    font-size: 12px;
    color: var(--hr-gray-400);
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.hr-ring-legend {
    display: flex;
    gap: 20px;
}

.hr-ring-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--hr-gray-600);
    font-weight: 500;
}

.hr-ring-legend-item .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.hr-ring-legend-item .dot.active {
    background: var(--hr-success);
}

.hr-ring-legend-item .dot.terminated {
    background: var(--hr-danger);
}

/* ===== BAR CHART ===== */
.hr-bar-chart {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.hr-bar-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.hr-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 600;
    color: var(--hr-gray-700);
}

.hr-bar-count {
    color: var(--hr-gray-400);
    font-weight: 500;
}

.hr-bar-track {
    width: 100%;
    height: 8px;
    background: var(--hr-gray-100);
    border-radius: 4px;
    overflow: hidden;
}

.hr-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease;
}

/* ===== EMPTY STATE ===== */
.hr-empty-state {
    padding: 40px;
    text-align: center;
    color: var(--hr-gray-400);
}

.hr-empty-state i {
    font-size: 40px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.hr-empty-state p {
    margin: 0;
    font-size: 14px;
}

.hr-empty-state.small {
    padding: 20px;
}

.hr-empty-state.small i {
    font-size: 28px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .hr-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .hr-main-grid,
    .hr-charts-grid {
        grid-template-columns: 1fr;
    }

    .hr-hero-content {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }

    .hr-hero-right {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .hr-stats-grid {
        grid-template-columns: 1fr;
    }

    .hr-hero {
        padding: 24px 20px;
    }

    .hr-hero-greeting h1 {
        font-size: 20px;
    }

    .hr-alert-banner {
        flex-direction: column;
        text-align: center;
    }
}

/* ===== ANIMATION ===== */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hr-stat-card { animation: fadeInUp 0.4s ease forwards; }
.hr-stat-card:nth-child(1) { animation-delay: 0.05s; }
.hr-stat-card:nth-child(2) { animation-delay: 0.1s; }
.hr-stat-card:nth-child(3) { animation-delay: 0.15s; }
.hr-stat-card:nth-child(4) { animation-delay: 0.2s; }

.hr-section { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.25s; opacity: 0; }
.hr-chart-card { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.35s; opacity: 0; }
.hr-alert-banner { animation: fadeInUp 0.4s ease forwards; animation-delay: 0.2s; opacity: 0; }
</style>

<?php include '../includes/admin_footer.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>