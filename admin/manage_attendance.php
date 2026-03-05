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

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$year = date('Y', strtotime($month . '-01'));
$month_num = date('m', strtotime($month . '-01'));
$month_name = date('F Y', strtotime($month . '-01'));

// Get attendance records
$query = "
    SELECT a.*, u.name as user_name, u.email, u.profile_pic, u.role as user_role
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.tenant_id = ? AND a.branch_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?
";

$params = [$tenant_id, $branch_id, $year, $month_num];

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY a.date DESC, u.name ASC";

// Pagination
$records_per_page = 25;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

$count_query = "SELECT COUNT(*) FROM (" . str_replace("SELECT a.*,", "SELECT a.id,", $query) . ") as total";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

$query .= " LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter
$stmt = $pdo->prepare("
    SELECT id, name FROM users
    WHERE tenant_id = ? AND branch_id = ? AND fired = 0
    ORDER BY name ASC
");
$stmt->execute([$tenant_id, $branch_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary statistics for entire month (not just current page)
$summary_query = "
    SELECT
        COUNT(*) as total_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        COALESCE(AVG(a.working_minutes), 0) as avg_minutes,
        COALESCE(SUM(a.working_minutes), 0) as total_minutes,
        COUNT(DISTINCT a.user_id) as unique_employees,
        COUNT(DISTINCT a.date) as days_recorded
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.tenant_id = ? AND a.branch_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?
";
$summary_params = [$tenant_id, $branch_id, $year, $month_num];

if ($user_filter > 0) {
    $summary_query .= " AND a.user_id = ?";
    $summary_params[] = $user_filter;
}

$stmt = $pdo->prepare($summary_query);
$stmt->execute($summary_params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_summary = $summary['total_records'] ?: 1;
$present_pct = round(($summary['present_count'] / $total_summary) * 100);
$late_pct = round(($summary['late_count'] / $total_summary) * 100);

// Get daily trend for mini chart
$trend_query = "
    SELECT
        DAY(a.date) as day_num,
        COUNT(*) as total,
        SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended
    FROM attendance a
    WHERE a.tenant_id = ? AND a.branch_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?
    GROUP BY DAY(a.date)
    ORDER BY day_num ASC
";
$stmt = $pdo->prepare($trend_query);
$stmt->execute([$tenant_id, $branch_id, $year, $month_num]);
$daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('manage_attendance');
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
                            <div class="ma-hero">
                                <div class="ma-hero-bg">
                                    <div class="ma-hero-circle c1"></div>
                                    <div class="ma-hero-circle c2"></div>
                                </div>
                                <div class="ma-hero-content">
                                    <div class="ma-hero-left">
                                        <span class="ma-hero-badge">
                                            <i class="feather icon-calendar"></i>
                                            <?php echo __('admin_panel'); ?>
                                        </span>
                                        <h1><?php echo __('manage_attendance'); ?></h1>
                                        <p><?php echo __('monitor_and_manage_employee_attendance_records'); ?></p>
                                    </div>
                                    <div class="ma-hero-actions">
                                        <a href="attendance.php" class="ma-hero-btn secondary">
                                            <i class="feather icon-clock"></i>
                                            <?php echo __('my_attendance'); ?>
                                        </a>
                                        <a href="attendance_settings.php" class="ma-hero-btn outline">
                                            <i class="feather icon-settings"></i>
                                            <?php echo __('settings'); ?>
                                        </a>
                                        <button onclick="exportAttendance()" class="ma-hero-btn outline">
                                            <i class="feather icon-download"></i>
                                            <?php echo __('export'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== FILTERS ========== -->
                            <div class="ma-filters">
                                <form method="GET" class="ma-filters-form" id="filterForm">
                                    <div class="ma-filter-group">
                                        <label>
                                            <i class="feather icon-calendar"></i>
                                            <?php echo __('month'); ?>
                                        </label>
                                        <input type="month" name="month" value="<?php echo $month; ?>" class="ma-filter-input">
                                    </div>
                                    <div class="ma-filter-group">
                                        <label>
                                            <i class="feather icon-user"></i>
                                            <?php echo __('employee'); ?>
                                        </label>
                                        <select name="user" class="ma-filter-input">
                                            <option value="0"><?php echo __('all_employees'); ?></option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ma-filter-pills">
                                        <button type="submit" name="status" value="all"
                                            class="ma-pill <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                            <?php echo __('all'); ?>
                                            <span class="ma-pill-count"><?php echo $summary['total_records'] ?? 0; ?></span>
                                        </button>
                                        <button type="submit" name="status" value="present"
                                            class="ma-pill present <?php echo $status_filter === 'present' ? 'active' : ''; ?>">
                                            <?php echo __('present'); ?>
                                            <span class="ma-pill-count"><?php echo $summary['present_count'] ?? 0; ?></span>
                                        </button>
                                        <button type="submit" name="status" value="late"
                                            class="ma-pill late <?php echo $status_filter === 'late' ? 'active' : ''; ?>">
                                            <?php echo __('late'); ?>
                                            <span class="ma-pill-count"><?php echo $summary['late_count'] ?? 0; ?></span>
                                        </button>
                                        <button type="submit" name="status" value="half_day"
                                            class="ma-pill half <?php echo $status_filter === 'half_day' ? 'active' : ''; ?>">
                                            <?php echo __('half_day'); ?>
                                            <span class="ma-pill-count"><?php echo $summary['half_day_count'] ?? 0; ?></span>
                                        </button>
                                        <button type="submit" name="status" value="absent"
                                            class="ma-pill absent <?php echo $status_filter === 'absent' ? 'active' : ''; ?>">
                                            <?php echo __('absent'); ?>
                                            <span class="ma-pill-count"><?php echo $summary['absent_count'] ?? 0; ?></span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- ========== STATS ========== -->
                            <div class="ma-stats-grid">
                                <div class="ma-stat-card present">
                                    <div class="ma-stat-visual">
                                        <svg viewBox="0 0 40 40" class="ma-stat-ring">
                                            <circle cx="20" cy="20" r="16" class="ma-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="ma-ring-fill"
                                                style="--pct: <?php echo $present_pct; ?>; --color: var(--ma-success);"/>
                                        </svg>
                                    </div>
                                    <div class="ma-stat-info">
                                        <span class="ma-stat-number"><?php echo $summary['present_count'] ?? 0; ?></span>
                                        <span class="ma-stat-label"><?php echo __('present'); ?></span>
                                    </div>
                                    <span class="ma-stat-pct"><?php echo $present_pct; ?>%</span>
                                </div>

                                <div class="ma-stat-card late">
                                    <div class="ma-stat-visual">
                                        <svg viewBox="0 0 40 40" class="ma-stat-ring">
                                            <circle cx="20" cy="20" r="16" class="ma-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="ma-ring-fill"
                                                style="--pct: <?php echo $late_pct; ?>; --color: var(--ma-warning);"/>
                                        </svg>
                                    </div>
                                    <div class="ma-stat-info">
                                        <span class="ma-stat-number"><?php echo $summary['late_count'] ?? 0; ?></span>
                                        <span class="ma-stat-label"><?php echo __('late'); ?></span>
                                    </div>
                                    <span class="ma-stat-pct"><?php echo $late_pct; ?>%</span>
                                </div>

                                <div class="ma-stat-card half">
                                    <div class="ma-stat-visual">
                                        <svg viewBox="0 0 40 40" class="ma-stat-ring">
                                            <circle cx="20" cy="20" r="16" class="ma-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="ma-ring-fill"
                                                style="--pct: <?php echo $total_summary > 0 ? round(($summary['half_day_count'] / $total_summary) * 100) : 0; ?>; --color: var(--ma-info);"/>
                                        </svg>
                                    </div>
                                    <div class="ma-stat-info">
                                        <span class="ma-stat-number"><?php echo $summary['half_day_count'] ?? 0; ?></span>
                                        <span class="ma-stat-label"><?php echo __('half_day'); ?></span>
                                    </div>
                                    <span class="ma-stat-pct"><?php echo $total_summary > 0 ? round(($summary['half_day_count'] / $total_summary) * 100) : 0; ?>%</span>
                                </div>

                                <div class="ma-stat-card absent">
                                    <div class="ma-stat-visual">
                                        <svg viewBox="0 0 40 40" class="ma-stat-ring">
                                            <circle cx="20" cy="20" r="16" class="ma-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="ma-ring-fill"
                                                style="--pct: <?php echo $total_summary > 0 ? round(($summary['absent_count'] / $total_summary) * 100) : 0; ?>; --color: var(--ma-danger);"/>
                                        </svg>
                                    </div>
                                    <div class="ma-stat-info">
                                        <span class="ma-stat-number"><?php echo $summary['absent_count'] ?? 0; ?></span>
                                        <span class="ma-stat-label"><?php echo __('absent'); ?></span>
                                    </div>
                                    <span class="ma-stat-pct"><?php echo $total_summary > 0 ? round(($summary['absent_count'] / $total_summary) * 100) : 0; ?>%</span>
                                </div>

                                <div class="ma-stat-card highlight">
                                    <div class="ma-stat-visual">
                                        <div class="ma-stat-icon">
                                            <i class="feather icon-users"></i>
                                        </div>
                                    </div>
                                    <div class="ma-stat-info">
                                        <span class="ma-stat-number"><?php echo $summary['unique_employees'] ?? 0; ?></span>
                                        <span class="ma-stat-label"><?php echo __('employees'); ?></span>
                                    </div>
                                    <span class="ma-stat-meta"><?php echo $summary['days_recorded'] ?? 0; ?> <?php echo __('days'); ?></span>
                                </div>

                                <div class="ma-stat-card highlight">
                                    <div class="ma-stat-visual">
                                        <div class="ma-stat-icon">
                                            <i class="feather icon-clock"></i>
                                        </div>
                                    </div>
                                    <div class="ma-stat-info">
                                        <span class="ma-stat-number"><?php echo floor(($summary['avg_minutes'] ?? 0) / 60); ?>h <?php echo round(($summary['avg_minutes'] ?? 0) % 60); ?>m</span>
                                        <span class="ma-stat-label"><?php echo __('avg_hours'); ?></span>
                                    </div>
                                    <span class="ma-stat-meta"><?php echo floor(($summary['total_minutes'] ?? 0) / 60); ?>h <?php echo __('total'); ?></span>
                                </div>
                            </div>

                            <!-- ========== DAILY TREND (Mini Bar Chart) ========== -->
                            <?php if (!empty($daily_trend)): ?>
                            <div class="ma-trend-card">
                                <div class="ma-trend-header">
                                    <h3><i class="feather icon-activity"></i> <?php echo __('daily_attendance_trend'); ?></h3>
                                    <span class="ma-trend-period"><?php echo $month_name; ?></span>
                                </div>
                                <div class="ma-trend-chart">
                                    <?php
                                    $max_total = max(array_column($daily_trend, 'total') ?: [1]);
                                    $total_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);
                                    $trend_map = [];
                                    foreach ($daily_trend as $d) {
                                        $trend_map[$d['day_num']] = $d;
                                    }
                                    for ($d = 1; $d <= min($total_days_in_month, date('d') == $month ? date('d') : $total_days_in_month); $d++):
                                        $day_data = $trend_map[$d] ?? null;
                                        $bar_height = $day_data ? ($day_data['attended'] / $max_total) * 100 : 0;
                                        $is_today = ($d == date('d') && $month == date('Y-m'));
                                    ?>
                                        <div class="ma-trend-bar-wrap <?php echo $is_today ? 'today' : ''; ?>" title="<?php echo __('day'); ?> <?php echo $d; ?>: <?php echo $day_data ? $day_data['attended'] . '/' . $day_data['total'] : '0'; ?>">
                                            <div class="ma-trend-bar" style="height: <?php echo max(4, $bar_height); ?>%"></div>
                                            <span class="ma-trend-day"><?php echo $d; ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- ========== TABLE ========== -->
                            <div class="ma-table-card">
                                <div class="ma-table-header">
                                    <div class="ma-table-title">
                                        <h3>
                                            <i class="feather icon-list"></i>
                                            <?php echo __('attendance_records'); ?>
                                        </h3>
                                        <span class="ma-table-count"><?php echo $total_records; ?> <?php echo __('records'); ?></span>
                                    </div>
                                    <div class="ma-table-meta">
                                        <?php echo sprintf(__('showing_x_to_x_of_x'), $offset + 1, min($offset + $records_per_page, $total_records), $total_records); ?>
                                    </div>
                                </div>

                                <?php if (empty($attendance_records)): ?>
                                    <div class="ma-empty">
                                        <div class="ma-empty-icon">
                                            <i class="feather icon-inbox"></i>
                                        </div>
                                        <h4><?php echo __('no_records_found'); ?></h4>
                                        <p><?php echo __('try_adjusting_filters'); ?></p>
                                        <a href="manage_attendance.php" class="ma-empty-btn">
                                            <i class="feather icon-refresh-cw"></i>
                                            <?php echo __('clear_filters'); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="ma-table-wrap">
                                        <table class="ma-table">
                                            <thead>
                                                <tr>
                                                    <th><?php echo __('date'); ?></th>
                                                    <th><?php echo __('employee'); ?></th>
                                                    <th><?php echo __('check_in'); ?></th>
                                                    <th><?php echo __('check_out'); ?></th>
                                                    <th><?php echo __('duration'); ?></th>
                                                    <th><?php echo __('status'); ?></th>
                                                    <th><?php echo __('notes'); ?></th>
                                                    <th class="text-right"><?php echo __('actions'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attendance_records as $record): ?>
                                                    <tr class="ma-table-row">
                                                        <td>
                                                            <div class="ma-date-cell">
                                                                <span class="ma-date-day"><?php echo date('d', strtotime($record['date'])); ?></span>
                                                                <div class="ma-date-info">
                                                                    <span class="ma-date-weekday"><?php echo date('D', strtotime($record['date'])); ?></span>
                                                                    <span class="ma-date-month"><?php echo date('M Y', strtotime($record['date'])); ?></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="ma-employee-cell">
                                                                <div class="ma-avatar">
                                                                    <?php if (!empty($record['profile_pic']) && $record['profile_pic'] !== 'avatar-1.jpg'): ?>
                                                                        <img src="../assets/images/user/<?= htmlspecialchars(basename($record['profile_pic'])); ?>" alt="">
                                                                    <?php else: ?>
                                                                        <span><?php echo strtoupper(substr($record['user_name'], 0, 2)); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="ma-employee-info">
                                                                    <h5><?php echo htmlspecialchars($record['user_name']); ?></h5>
                                                                    <p><?php echo htmlspecialchars($record['email']); ?></p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($record['check_in_time']): ?>
                                                                <div class="ma-time-cell in">
                                                                    <i class="feather icon-log-in"></i>
                                                                    <span><?php echo date('h:i A', strtotime($record['check_in_time'])); ?></span>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="ma-time-empty">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($record['check_out_time']): ?>
                                                                <div class="ma-time-cell out">
                                                                    <i class="feather icon-log-out"></i>
                                                                    <span><?php echo date('h:i A', strtotime($record['check_out_time'])); ?></span>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="ma-time-empty">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="ma-duration-cell">
                                                                <?php
                                                                $mins = $record['working_minutes'];
                                                                $hours = floor($mins / 60);
                                                                $remaining = $mins % 60;
                                                                ?>
                                                                <span class="ma-duration-value">
                                                                    <?php if ($hours > 0): ?>
                                                                        <?php echo $hours; ?>h <?php echo $remaining; ?>m
                                                                    <?php else: ?>
                                                                        <?php echo $mins; ?>m
                                                                    <?php endif; ?>
                                                                </span>
                                                                <div class="ma-duration-bar">
                                                                    <div class="ma-duration-fill" style="width: <?php echo min(100, ($mins / 480) * 100); ?>%"></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="ma-status-badge <?php echo strtolower($record['status']); ?>">
                                                                <span class="ma-status-dot"></span>
                                                                <?php echo __($record['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($record['notes'])): ?>
                                                                <span class="ma-notes" title="<?php echo htmlspecialchars($record['notes']); ?>">
                                                                    <i class="feather icon-message-square"></i>
                                                                    <?php echo htmlspecialchars(mb_strimwidth($record['notes'], 0, 30, '...')); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="ma-notes-empty">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right">
                                                            <div class="ma-actions">
                                                                <button class="ma-action-btn view" onclick="viewDetails(<?php echo $record['id']; ?>)" title="<?php echo __('view'); ?>">
                                                                    <i class="feather icon-eye"></i>
                                                                </button>
                                                                <button class="ma-action-btn edit" onclick="editAttendance(<?php echo $record['id']; ?>)" title="<?php echo __('edit'); ?>">
                                                                    <i class="feather icon-edit-2"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="ma-pagination">
                                        <?php
                                        $query_params = [
                                            'month' => $month,
                                            'user' => $user_filter,
                                            'status' => $status_filter
                                        ];
                                        $base_url = '?' . http_build_query($query_params) . '&page=';
                                        ?>
                                        <a href="<?php echo $base_url . max(1, $current_page - 1); ?>"
                                            class="ma-page-btn <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                            <i class="feather icon-chevron-left"></i>
                                        </a>

                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);

                                        if ($start_page > 1):
                                        ?>
                                            <a href="<?php echo $base_url . 1; ?>" class="ma-page-btn">1</a>
                                            <?php if ($start_page > 2): ?>
                                                <span class="ma-page-dots">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <a href="<?php echo $base_url . $i; ?>"
                                                class="ma-page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <span class="ma-page-dots">...</span>
                                            <?php endif; ?>
                                            <a href="<?php echo $base_url . $total_pages; ?>" class="ma-page-btn"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>

                                        <a href="<?php echo $base_url . min($total_pages, $current_page + 1); ?>"
                                            class="ma-page-btn <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                            <i class="feather icon-chevron-right"></i>
                                        </a>
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

<!-- ========== VIEW DETAILS MODAL ========== -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content ma-modal">
            <div class="ma-modal-header">
                <div class="ma-modal-header-content">
                    <i class="feather icon-clipboard"></i>
                    <h5><?php echo __('attendance_details'); ?></h5>
                </div>
                <button type="button" class="ma-modal-close" data-dismiss="modal">
                    <i class="feather icon-x"></i>
                </button>
            </div>
            <div class="ma-modal-body" id="attendanceDetailsContent">
                <div class="ma-modal-loading">
                    <div class="ma-spinner"></div>
                    <p><?php echo __('loading'); ?>...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================
   MANAGE ATTENDANCE - REDESIGN
   ============================================ */
:root {
    --ma-primary: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --ma-primary-light: rgba(64, 153, 255, 0.1);
    --ma-primary-dark: #2a7acc;
    --ma-success: #10b981;
    --ma-success-light: #ecfdf5;
    --ma-danger: #ef4444;
    --ma-danger-light: #fef2f2;
    --ma-warning: #f59e0b;
    --ma-warning-light: #fffbeb;
    --ma-info: #3b82f6;
    --ma-info-light: #eff6ff;
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
    --ma-radius: 16px;
    --ma-radius-sm: 10px;
    --ma-radius-xs: 6px;
    --ma-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --ma-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
    --ma-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
    --ma-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== HERO ===== */
.ma-hero {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-radius: var(--ma-radius);
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    color: #fff;
}

.ma-hero-bg {
    position: absolute;
    inset: 0;
    z-index: 1;
    overflow: hidden;
}

.ma-hero-circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.08);
}

.ma-hero-circle.c1 { width: 350px; height: 350px; top: -120px; right: -60px; }
.ma-hero-circle.c2 { width: 200px; height: 200px; bottom: -80px; left: 20%; background: rgba(99, 102, 241, 0.05); }

.ma-hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.ma-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(99, 102, 241, 0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 12px;
    color: #a5b4fc;
}

.ma-hero h1 {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px;
    color: #fff;
}

.ma-hero p {
    margin: 0;
    opacity: 0.6;
    font-size: 14px;
}

.ma-hero-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.ma-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: var(--ma-radius-sm);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--ma-transition);
    border: none;
    cursor: pointer;
}

.ma-hero-btn.secondary {
    background: var(--ma-primary);
    color: #fff;
}

.ma-hero-btn.secondary:hover {
    background: var(--ma-primary-dark);
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
}

.ma-hero-btn.outline {
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.8);
    border: 1px solid rgba(255,255,255,0.15);
}

.ma-hero-btn.outline:hover {
    background: rgba(255,255,255,0.15);
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
}

/* ===== FILTERS ===== */
.ma-filters {
    background: #fff;
    border-radius: var(--ma-radius);
    padding: 20px 24px;
    box-shadow: var(--ma-shadow);
    border: 1px solid var(--ma-gray-100);
    margin-bottom: 24px;
}

.ma-filters-form {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}

.ma-filter-group {
    min-width: 180px;
}

.ma-filter-group label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 600;
    color: var(--ma-gray-500);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.ma-filter-group label i {
    font-size: 13px;
}

.ma-filter-input {
    width: 100%;
    padding: 9px 14px;
    border: 2px solid var(--ma-gray-200);
    border-radius: var(--ma-radius-sm);
    font-size: 14px;
    color: var(--ma-gray-800);
    background: var(--ma-gray-50);
    transition: var(--ma-transition);
    appearance: none;
    -webkit-appearance: none;
}

.ma-filter-input:focus {
    outline: none;
    border-color: var(--ma-primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

select.ma-filter-input {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.ma-filter-pills {
    display: flex;
    gap: 6px;
    flex: 1;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.ma-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: 2px solid var(--ma-gray-200);
    border-radius: 50px;
    background: #fff;
    color: var(--ma-gray-600);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--ma-transition);
    white-space: nowrap;
}

.ma-pill:hover {
    border-color: var(--ma-gray-300);
    background: var(--ma-gray-50);
}

.ma-pill.active {
    background: var(--ma-gray-900);
    border-color: var(--ma-gray-900);
    color: #fff;
}

.ma-pill.present.active { background: var(--ma-success); border-color: var(--ma-success); }
.ma-pill.late.active { background: var(--ma-warning); border-color: var(--ma-warning); color: #000; }
.ma-pill.half.active { background: var(--ma-info); border-color: var(--ma-info); }
.ma-pill.absent.active { background: var(--ma-danger); border-color: var(--ma-danger); }

.ma-pill-count {
    background: rgba(0,0,0,0.08);
    padding: 1px 7px;
    border-radius: 20px;
    font-size: 11px;
}

.ma-pill.active .ma-pill-count {
    background: rgba(255,255,255,0.2);
}

/* ===== STATS GRID ===== */
.ma-stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}

.ma-stat-card {
    background: #fff;
    border-radius: var(--ma-radius);
    padding: 18px;
    box-shadow: var(--ma-shadow);
    border: 1px solid var(--ma-gray-100);
    display: flex;
    align-items: center;
    gap: 14px;
    transition: var(--ma-transition);
    position: relative;
    overflow: hidden;
}

.ma-stat-card:hover {
    box-shadow: var(--ma-shadow-md);
    transform: translateY(-2px);
}

.ma-stat-ring {
    width: 44px;
    height: 44px;
    transform: rotate(-90deg);
    flex-shrink: 0;
}

.ma-ring-bg {
    fill: none;
    stroke: var(--ma-gray-100);
    stroke-width: 4;
}

.ma-ring-fill {
    fill: none;
    stroke: var(--color);
    stroke-width: 4;
    stroke-linecap: round;
    stroke-dasharray: 100.53;
    stroke-dashoffset: calc(100.53 - (100.53 * var(--pct, 0) / 100));
    transition: stroke-dashoffset 1.5s ease;
}

.ma-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--ma-primary-light);
    color: var(--ma-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.ma-stat-number {
    display: block;
    font-size: 22px;
    font-weight: 800;
    color: var(--ma-gray-900);
    line-height: 1;
}

.ma-stat-label {
    display: block;
    font-size: 11px;
    color: var(--ma-gray-400);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

.ma-stat-pct {
    position: absolute;
    top: 12px;
    right: 14px;
    font-size: 11px;
    font-weight: 700;
    color: var(--ma-gray-400);
}

.ma-stat-meta {
    position: absolute;
    top: 12px;
    right: 14px;
    font-size: 11px;
    font-weight: 600;
    color: var(--ma-gray-400);
}

/* ===== TREND CHART ===== */
.ma-trend-card {
    background: #fff;
    border-radius: var(--ma-radius);
    box-shadow: var(--ma-shadow);
    border: 1px solid var(--ma-gray-100);
    overflow: hidden;
    margin-bottom: 24px;
}

.ma-trend-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--ma-gray-100);
}

.ma-trend-header h3 {
    font-size: 15px;
    font-weight: 700;
    color: var(--ma-gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ma-trend-header h3 i { color: var(--ma-primary); }

.ma-trend-period {
    font-size: 12px;
    font-weight: 600;
    color: var(--ma-gray-400);
    background: var(--ma-gray-50);
    padding: 4px 12px;
    border-radius: 20px;
}

.ma-trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    padding: 20px 24px 12px;
    height: 140px;
    overflow-x: auto;
}

.ma-trend-bar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 16px;
    height: 100%;
    justify-content: flex-end;
    cursor: pointer;
}

.ma-trend-bar {
    width: 100%;
    max-width: 20px;
    min-height: 4px;
    background: var(--ma-primary);
    border-radius: 3px 3px 0 0;
    transition: var(--ma-transition);
    opacity: 0.6;
}

.ma-trend-bar-wrap:hover .ma-trend-bar {
    opacity: 1;
    transform: scaleY(1.05);
}

.ma-trend-bar-wrap.today .ma-trend-bar {
    background: var(--ma-success);
    opacity: 1;
}

.ma-trend-day {
    font-size: 9px;
    color: var(--ma-gray-400);
    margin-top: 4px;
    font-weight: 600;
}

.ma-trend-bar-wrap.today .ma-trend-day {
    color: var(--ma-success);
    font-weight: 800;
}

/* ===== TABLE ===== */
.ma-table-card {
    background: #fff;
    border-radius: var(--ma-radius);
    box-shadow: var(--ma-shadow);
    border: 1px solid var(--ma-gray-100);
    overflow: hidden;
    margin-bottom: 24px;
}

.ma-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--ma-gray-100);
}

.ma-table-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ma-table-title h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--ma-gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ma-table-title h3 i { color: var(--ma-primary); }

.ma-table-count {
    background: var(--ma-primary-light);
    color: var(--ma-primary);
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.ma-table-meta {
    font-size: 12px;
    color: var(--ma-gray-400);
    font-weight: 500;
}

.ma-table-wrap { overflow-x: auto; }

.ma-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.ma-table thead th {
    padding: 12px 18px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--ma-gray-500);
    background: var(--ma-gray-50);
    border-bottom: 1px solid var(--ma-gray-100);
    white-space: nowrap;
}

.ma-table tbody td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--ma-gray-50);
    vertical-align: middle;
}

.ma-table-row {
    transition: var(--ma-transition);
}

.ma-table-row:hover {
    background: var(--ma-gray-50);
}

.ma-table-row:last-child td {
    border-bottom: none;
}

/* Date Cell */
.ma-date-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ma-date-day {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--ma-primary-light);
    color: var(--ma-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 800;
    flex-shrink: 0;
}

.ma-date-weekday {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--ma-gray-800);
}

.ma-date-month {
    display: block;
    font-size: 11px;
    color: var(--ma-gray-400);
}

/* Employee Cell */
.ma-employee-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ma-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--ma-primary), #8b5cf6);
}

.ma-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ma-avatar span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}

.ma-employee-info h5 {
    font-size: 13px;
    font-weight: 600;
    color: var(--ma-gray-800);
    margin: 0 0 1px;
}

.ma-employee-info p {
    font-size: 11px;
    color: var(--ma-gray-400);
    margin: 0;
}

/* Time Cells */
.ma-time-cell {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: var(--ma-radius-xs);
}

.ma-time-cell.in {
    color: var(--ma-success);
    background: var(--ma-success-light);
}

.ma-time-cell.out {
    color: var(--ma-danger);
    background: var(--ma-danger-light);
}

.ma-time-cell i {
    font-size: 13px;
}

.ma-time-empty {
    color: var(--ma-gray-300);
    font-size: 14px;
}

/* Duration Cell */
.ma-duration-cell {
    min-width: 80px;
}

.ma-duration-value {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--ma-gray-700);
    margin-bottom: 4px;
}

.ma-duration-bar {
    height: 4px;
    background: var(--ma-gray-100);
    border-radius: 2px;
    overflow: hidden;
}

.ma-duration-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--ma-primary), #8b5cf6);
    border-radius: 2px;
    transition: width 1s ease;
}

/* Status Badge */
.ma-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.ma-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.ma-status-badge.present { background: var(--ma-success-light); color: var(--ma-success); }
.ma-status-badge.present .ma-status-dot { background: var(--ma-success); }
.ma-status-badge.late { background: var(--ma-warning-light); color: #b45309; }
.ma-status-badge.late .ma-status-dot { background: var(--ma-warning); }
.ma-status-badge.half_day { background: var(--ma-info-light); color: var(--ma-info); }
.ma-status-badge.half_day .ma-status-dot { background: var(--ma-info); }
.ma-status-badge.absent { background: var(--ma-danger-light); color: var(--ma-danger); }
.ma-status-badge.absent .ma-status-dot { background: var(--ma-danger); }

/* Notes */
.ma-notes {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--ma-gray-500);
    cursor: help;
}

.ma-notes i { font-size: 13px; color: var(--ma-gray-400); }
.ma-notes-empty { color: var(--ma-gray-300); }

/* Actions */
.ma-actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}

.ma-action-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid var(--ma-gray-200);
    background: #fff;
    color: var(--ma-gray-500);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--ma-transition);
    font-size: 14px;
}

.ma-action-btn.view:hover {
    border-color: var(--ma-info);
    color: var(--ma-info);
    background: var(--ma-info-light);
}

.ma-action-btn.edit:hover {
    border-color: var(--ma-warning);
    color: var(--ma-warning);
    background: var(--ma-warning-light);
}

/* ===== EMPTY STATE ===== */
.ma-empty {
    padding: 60px 20px;
    text-align: center;
}

.ma-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--ma-gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.ma-empty-icon i { font-size: 32px; color: var(--ma-gray-400); }

.ma-empty h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--ma-gray-700);
    margin: 0 0 8px;
}

.ma-empty p {
    font-size: 14px;
    color: var(--ma-gray-400);
    margin: 0 0 20px;
}

.ma-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--ma-radius-sm);
    background: var(--ma-primary);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--ma-transition);
}

.ma-empty-btn:hover {
    background: var(--ma-primary-dark);
    color: #fff;
    text-decoration: none;
}

/* ===== PAGINATION ===== */
.ma-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 4px;
    padding: 18px 24px;
    border-top: 1px solid var(--ma-gray-100);
}

.ma-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--ma-gray-200);
    background: #fff;
    color: var(--ma-gray-600);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--ma-transition);
}

.ma-page-btn:hover {
    border-color: var(--ma-primary);
    color: var(--ma-primary);
    background: var(--ma-primary-light);
    text-decoration: none;
}

.ma-page-btn.active {
    background: var(--ma-primary);
    border-color: var(--ma-primary);
    color: #fff;
}

.ma-page-btn.disabled {
    opacity: 0.4;
    pointer-events: none;
}

.ma-page-dots {
    color: var(--ma-gray-400);
    font-size: 14px;
    padding: 0 4px;
}

/* ===== MODAL ===== */
.ma-modal {
    border: none;
    border-radius: var(--ma-radius);
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
}

.ma-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 28px;
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #fff;
}

.ma-modal-header-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ma-modal-header-content i { font-size: 20px; }
.ma-modal-header-content h5 { font-size: 18px; font-weight: 700; margin: 0; color: #fff; }

.ma-modal-close {
    background: rgba(255,255,255,0.1);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    cursor: pointer;
    transition: var(--ma-transition);
}

.ma-modal-close:hover { background: rgba(255,255,255,0.2); }

.ma-modal-body {
    padding: 28px;
    max-height: 60vh;
    overflow-y: auto;
}

.ma-modal-loading {
    text-align: center;
    padding: 40px;
}

.ma-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid var(--ma-gray-200);
    border-top-color: var(--ma-primary);
    border-radius: 50%;
    margin: 0 auto 12px;
    animation: maSpin 0.8s linear infinite;
}

@keyframes maSpin { to { transform: rotate(360deg); } }

.ma-modal-loading p { color: var(--ma-gray-400); font-size: 14px; margin: 0; }

/* ===== RESPONSIVE ===== */
@media (max-width: 1400px) {
    .ma-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .ma-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .ma-hero-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }

    .ma-hero-actions {
        justify-content: center;
    }

    .ma-filters-form {
        flex-direction: column;
        align-items: stretch;
    }

    .ma-filter-pills {
        justify-content: flex-start;
    }

    .ma-filter-group {
        min-width: 100%;
    }
}

@media (max-width: 768px) {
    .ma-stats-grid {
        grid-template-columns: 1fr;
    }

    .ma-hero {
        padding: 24px 20px;
    }

    .ma-hero h1 {
        font-size: 20px;
    }

    .ma-filter-pills {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 4px;
    }

    .ma-trend-chart {
        overflow-x: scroll;
    }

    .ma-table-header {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.ma-hero { animation: fadeInUp 0.4s ease forwards; }
.ma-filters { animation: fadeInUp 0.4s ease forwards; animation-delay: 0.05s; opacity: 0; }
.ma-stat-card { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
.ma-stat-card:nth-child(1) { animation-delay: 0.08s; }
.ma-stat-card:nth-child(2) { animation-delay: 0.12s; }
.ma-stat-card:nth-child(3) { animation-delay: 0.16s; }
.ma-stat-card:nth-child(4) { animation-delay: 0.20s; }
.ma-stat-card:nth-child(5) { animation-delay: 0.24s; }
.ma-stat-card:nth-child(6) { animation-delay: 0.28s; }
.ma-trend-card { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.3s; opacity: 0; }
.ma-table-card { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.35s; opacity: 0; }
</style>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function viewDetails(attendanceId) {
    const content = document.getElementById('attendanceDetailsContent');
    content.innerHTML = '<div class="ma-modal-loading"><div class="ma-spinner"></div><p><?php echo __("loading"); ?>...</p></div>';
    $('#attendanceDetailsModal').modal('show');

    fetch(`../api/attendance/get_attendance_details.php?id=${attendanceId}`)
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(() => {
            content.innerHTML = '<div class="ma-empty"><div class="ma-empty-icon"><i class="feather icon-alert-triangle"></i></div><h4><?php echo __("error_loading_details"); ?></h4></div>';
        });
}

function editAttendance(attendanceId) {
    window.location.href = `edit_attendance.php?id=${attendanceId}`;
}

function exportAttendance() {
    const month = '<?php echo $month; ?>';
    const user = '<?php echo $user_filter; ?>';
    const status = '<?php echo $status_filter; ?>';
    window.open(`../api/attendance/export_attendance.php?month=${month}&user=${user}&status=${status}`, '_blank');
    showToast('<?php echo __("export_started"); ?>', 'success');
}

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

// Auto-submit on filter change
document.querySelectorAll('.ma-filter-input').forEach(el => {
    el.addEventListener('change', () => {
        document.getElementById('filterForm').submit();
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>