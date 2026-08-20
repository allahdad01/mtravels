<?php
require_once '../../includes/db.php';
require_once '../../includes/language_helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . '/../../includes/permissions.php';
require_permission('hr.attendance');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo 'Access denied';
    exit();
}
 // Define h() function if not already defined
 if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
$tenant_id = $_SESSION['tenant_id'];
$role = $_SESSION['role'] ?? '';
$branch_id = $_SESSION['branch_id'] ?? null;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo 'Invalid attendance ID';
    exit();
}

// Build query based on role
if ($role === 'tenant_super_admin') {
    // Tenant super admin can see all attendance in their tenant
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as user_name, u.email, u.profile_pic
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);
} else {
    // Regular users are restricted to their branch
    if (!$branch_id) {
        echo 'Branch access denied';
        exit();
    }
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as user_name, u.email, u.profile_pic
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND a.tenant_id = ? AND a.branch_id = ?
    ");
    $stmt->execute([$id, $tenant_id, $branch_id]);
}

$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    echo 'Attendance record not found';
    exit();
}

// Get attendance settings for context
if ($role === 'tenant_super_admin') {
    // For tenant super admin, get settings for the specific branch of the attendance record
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_settings
        WHERE tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$tenant_id, $attendance['branch_id']]);
} else {
    // For regular users, use their branch settings
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_settings
        WHERE tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$tenant_id, $branch_id]);
}
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="row">
    <!-- Employee Information Card -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-user mr-2"></i><?php echo __('employee_info'); ?></h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <img src="../assets/images/user/<?php echo htmlspecialchars($attendance['profile_pic'] ?? 'default-avatar.jpg'); ?>"
                         class="rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                    <h5 class="mb-1"><?php echo htmlspecialchars($attendance['user_name']); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($attendance['email']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Information Card -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-calendar mr-2"></i><?php echo __('attendance_info'); ?></h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="h4 mb-2 font-weight-bold text-primary">
                        <?php echo date('l, F j, Y', strtotime($attendance['date'])); ?>
                    </div>
                    <span class="badge-<?php echo strtolower($attendance['status']) === 'present' ? 'success' : (strtolower($attendance['status']) === 'absent' ? 'danger' : 'warning'); ?> badge-pill px-3 py-2">
                        <i class="feather icon-<?php echo strtolower($attendance['status']) === 'present' ? 'check-circle' : (strtolower($attendance['status']) === 'absent' ? 'x-circle' : 'clock'); ?> mr-1"></i>
                        <?php echo __($attendance['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <!-- Check In/Out Information -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-log-in mr-2"></i><?php echo __('check_in'); ?></h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h3 font-weight-bold text-success mb-2">
                        <?php echo $attendance['check_in_time'] ? date('H:i:s', strtotime($attendance['check_in_time'])) : '-'; ?>
                    </div>
                    <?php if ($attendance['check_in_time'] && $settings): ?>
                        <?php
                        $check_in_time = strtotime($attendance['check_in_time']);
                        $office_start = strtotime($settings['office_start_time']);
                        $late_threshold = $office_start + ($settings['late_after_minutes'] * 60);

                        if ($check_in_time > $late_threshold) {
                            $late_minutes = round(($check_in_time - $office_start) / 60);
                            echo '<span class="badge-danger badge-pill px-3 py-1"><i class="feather icon-clock mr-1"></i>' . __('late_by') . ' ' . $late_minutes . ' ' . __('minutes') . '</span>';
                        } else {
                            echo '<span class="badge-success badge-pill px-3 py-1"><i class="feather icon-check mr-1"></i>' . __('on_time') . '</span>';
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-log-out mr-2"></i><?php echo __('check_out'); ?></h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h3 font-weight-bold text-info">
                        <?php echo $attendance['check_out_time'] ? date('H:i:s', strtotime($attendance['check_out_time'])) : '-'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($attendance['check_in_time'] && $attendance['check_out_time']): ?>
<div class="row mt-3">
    <!-- Working Hours Information -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-clock mr-2"></i><?php echo __('working_hours'); ?></h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h2 font-weight-bold text-primary mb-2">
                        <?php echo round($attendance['working_minutes'] / 60, 2); ?> <?php echo __('hours'); ?>
                    </div>
                    <small class="text-muted"><?php echo $attendance['working_minutes']; ?> <?php echo __('minutes'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-target mr-2"></i><?php echo __('expected_hours'); ?></h5>
            </div>
            <div class="card-body">
                <?php if ($settings): ?>
                    <div class="text-center mb-3">
                        <div class="h2 font-weight-bold text-warning mb-2">
                            <?php
                            $start = strtotime($settings['office_start_time']);
                            $end = strtotime($settings['office_end_time']);
                            $expected_minutes = round(($end - $start) / 60);
                            echo round($expected_minutes / 60, 2);
                            ?> <?php echo __('hours'); ?>
                        </div>
                        <small class="text-muted"><?php echo $expected_minutes; ?> <?php echo __('minutes'); ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($attendance['notes'])): ?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-file-text mr-2"></i><?php echo __('notes'); ?></h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($attendance['notes'])); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($settings): ?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="feather icon-settings mr-2"></i><?php echo __('office_settings'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="text-center mb-3">
                            <i class="feather icon-sun h1 text-warning mb-2"></i>
                            <h6><?php echo __('office_start'); ?></h6>
                            <div class="h4 font-weight-bold text-success"><?php echo date('H:i', strtotime($settings['office_start_time'])); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center mb-3">
                            <i class="feather icon-moon h1 text-dark mb-2"></i>
                            <h6><?php echo __('office_end'); ?></h6>
                            <div class="h4 font-weight-bold text-danger"><?php echo date('H:i', strtotime($settings['office_end_time'])); ?></div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <div class="text-center">
                            <i class="feather icon-alert-triangle h1 text-danger mb-2"></i>
                            <h6><?php echo __('late_after'); ?></h6>
                            <div class="h4 font-weight-bold text-danger"><?php echo $settings['late_after_minutes']; ?> <?php echo __('minutes'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center">
                            <i class="feather icon-pause h1 text-warning mb-2"></i>
                            <h6><?php echo __('half_day_threshold'); ?></h6>
                            <div class="h4 font-weight-bold text-warning"><?php echo $settings['half_day_minutes']; ?> <?php echo __('minutes'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
    margin-bottom: 0;
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

.card-body {
    padding: 1.5rem;
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

.badge-danger {
    background-color: #dc3545;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
}

.h2 {
    font-size: 2.5rem;
}

.h3 {
    font-size: 2rem;
}

.h4 {
    font-size: 1.5rem;
}

.text-primary {
    color: #4099ff !important;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-info {
    color: #17a2b8 !important;
}

.text-muted {
    color: #6c757d !important;
}

.font-weight-bold {
    font-weight: 600 !important;
}

.mb-0 {
    margin-bottom: 0 !important;
}

.mb-1 {
    margin-bottom: 0.25rem !important;
}

.mb-2 {
    margin-bottom: 0.5rem !important;
}

.mb-3 {
    margin-bottom: 1rem !important;
}

.mb-4 {
    margin-bottom: 1.5rem !important;
}

.mt-3 {
    margin-top: 1rem !important;
}

.mr-1 {
    margin-right: 0.25rem !important;
}

.mr-2 {
    margin-right: 0.5rem !important;
}

.feather {
    width: 1em;
    height: 1em;
    vertical-align: middle;
}
</style>