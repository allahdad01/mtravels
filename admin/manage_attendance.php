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

// Handle filters
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Parse month
$year = date('Y', strtotime($month . '-01'));
$month_num = date('m', strtotime($month . '-01'));

// Get attendance records
$query = "
    SELECT a.*, u.name as user_name, u.email
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.tenant_id = ? AND a.branch_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?
";

$params = [$tenant_id, $branch_id, $year, $month_num];
$types = "iiii";

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY a.date DESC, u.name ASC";

// Pagination setup
$records_per_page = 25;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM (" . str_replace("SELECT a.*,", "SELECT a.id,", $query) . ") as total";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Add LIMIT to main query
$query .= " LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter dropdown
$stmt = $pdo->prepare("
    SELECT id, name FROM users
    WHERE tenant_id = ? AND branch_id = ? AND fired = 0
    ORDER BY name ASC
");
$stmt->execute([$tenant_id, $branch_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_days = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);
$present_count = 0;
$late_count = 0;
$half_day_count = 0;
$absent_count = 0;

foreach ($attendance_records as $record) {
    switch ($record['status']) {
        case 'present':
            $present_count++;
            break;
        case 'late':
            $late_count++;
            break;
        case 'half_day':
            $half_day_count++;
            break;
        case 'absent':
            $absent_count++;
            break;
    }
}

$page_title = __('manage_attendance');
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

.status {
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

.summary-card {
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                                        <h5 class="mb-0"><i class="feather icon-calendar mr-2"></i><?php echo __('manage_attendance'); ?></h5>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="attendance.php" class="btn btn-primary btn-sm">
                                            <i class="feather icon-clock mr-1"></i><?php echo __('my_attendance'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label><?php echo __('month'); ?></label>
                                            <input type="month" class="form-control" name="month" value="<?php echo $month; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label><?php echo __('employee'); ?></label>
                                            <select class="form-control" name="user">
                                                <option value="0"><?php echo __('all_employees'); ?></option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label><?php echo __('status'); ?></label>
                                            <select class="form-control" name="status">
                                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo __('all_statuses'); ?></option>
                                                <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>><?php echo __('present'); ?></option>
                                                <option value="late" <?php echo $status_filter === 'late' ? 'selected' : ''; ?>><?php echo __('late'); ?></option>
                                                <option value="half_day" <?php echo $status_filter === 'half_day' ? 'selected' : ''; ?>><?php echo __('half_day'); ?></option>
                                                <option value="absent" <?php echo $status_filter === 'absent' ? 'selected' : ''; ?>><?php echo __('absent'); ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label>&nbsp;</label>
                                            <button type="submit" class="btn btn-primary btn-block">
                                                <i class="feather icon-search mr-1"></i><?php echo __('filter'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Summary Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card summary-card text-white bg-success">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="card-title"><?php echo __('present'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $present_count; ?></h3>
                                                </div>
                                                <i class="feather icon-check-circle" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card summary-card text-white bg-warning">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="card-title"><?php echo __('late'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $late_count; ?></h3>
                                                </div>
                                                <i class="feather icon-clock" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card summary-card text-white bg-info">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="card-title"><?php echo __('half_day'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $half_day_count; ?></h3>
                                                </div>
                                                <i class="feather icon-minus" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card summary-card text-white bg-danger">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="card-title"><?php echo __('absent'); ?></h6>
                                                    <h3 class="mb-0"><?php echo $absent_count; ?></h3>
                                                </div>
                                                <i class="feather icon-x" style="font-size: 2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Records Table -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><?php echo __('attendance_records'); ?> (<?php echo count($attendance_records); ?>)</h5>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success btn-sm" onclick="exportAttendance()">
                                            <i class="feather icon-download mr-1"></i><?php echo __('export'); ?>
                                        </button>
                                        <a href="attendance_settings.php" class="btn btn-info btn-sm">
                                            <i class="feather icon-settings mr-1"></i><?php echo __('settings'); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body table-border-style">
                                    <div class="table-responsive">
                                        <table id="attendance-table" class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?php echo __('date'); ?></th>
                                                    <th><?php echo __('employee'); ?></th>
                                                    <th><?php echo __('check_in'); ?></th>
                                                    <th><?php echo __('check_out'); ?></th>
                                                    <th><?php echo __('working_minutes'); ?></th>
                                                    <th><?php echo __('status'); ?></th>
                                                    <th><?php echo __('notes'); ?></th>
                                                    <th><?php echo __('actions'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attendance_records as $record): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($record['user_name']); ?></td>
                                                        <td><?php echo $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-'; ?></td>
                                                        <td><?php echo $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-'; ?></td>
                                                        <td><?php echo $record['working_minutes']; ?> min</td>
                                                        <td>
                                                            <span class="status-<?php echo strtolower($record['status']); ?>">
                                                                <?php echo __($record['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $record['id']; ?>)">
                                                                <i class="feather icon-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="editAttendance(<?php echo $record['id']; ?>)">
                                                                <i class="feather icon-edit"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <?php if ($total_pages > 1): ?>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <?php
                                            // Build query string for pagination links
                                            $query_params = [
                                                'month' => $month,
                                                'user' => $user_filter,
                                                'status' => $status_filter
                                            ];
                                            $base_url = '?' . http_build_query($query_params) . '&page=';
                                            ?>
                                            <!-- Previous button -->
                                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo $base_url . ($current_page - 1); ?>">
                                                    <?php echo __('previous'); ?>
                                                </a>
                                            </li>
                                            
                                            <!-- Page numbers -->
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="' . $base_url . '1">1</a></li>';
                                                if ($start_page > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                $active = $i === $current_page ? ' active' : '';
                                                echo '<li class="page-item' . $active . '"><a class="page-link" href="' . $base_url . $i . '">' . $i . '</a></li>';
                                            }
                                            
                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="' . $base_url . $total_pages . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <!-- Next button -->
                                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo $base_url . ($current_page + 1); ?>">
                                                    <?php echo __('next'); ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <div class="text-center text-muted small">
                                        <?php echo sprintf(__('showing_records'), ($offset + 1), min($offset + $records_per_page, $total_records), $total_records); ?>
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

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('attendance_details'); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="attendanceDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function viewDetails(attendanceId) {
    // Load attendance details via AJAX
    fetch(`../api/attendance/get_attendance_details.php?id=${attendanceId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('attendanceDetailsContent').innerHTML = html;
            $('#attendanceDetailsModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('error_loading_details'); ?>');
        });
}

function editAttendance(attendanceId) {
    // Redirect to edit page or open edit modal
    window.location.href = `edit_attendance.php?id=${attendanceId}`;
}

function exportAttendance() {
    const month = '<?php echo $month; ?>';
    const user = '<?php echo $user_filter; ?>';
    const status = '<?php echo $status_filter; ?>';

    window.open(`../api/attendance/export_attendance.php?month=${month}&user=${user}&status=${status}`, '_blank');
}
</script>
<?php include '../includes/admin_footer.php'; ?>