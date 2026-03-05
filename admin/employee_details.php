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

// Check if employee ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: employee_management.php');
    exit();
}

$employee_id = intval($_GET['id']);

// Get employee details
$stmt = $pdo->prepare("
    SELECT u.*, sm.base_salary, sm.currency as salary_currency, sm.status as salary_status
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
");
$stmt->execute([$employee_id, $tenant_id, $branch_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employee_management.php');
    exit();
}

// Get termination history if applicable
$termination_history = [];
if ($employee['fired']) {
    try {
        $term_stmt = $pdo->prepare("
            SELECT * FROM employee_terminations
            WHERE employee_id = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY termination_date DESC
            LIMIT 1
        ");
        $term_stmt->execute([$employee_id, $tenant_id, $branch_id]);
        $termination_history = $term_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet
        $termination_history = null;
    }
}

// Get activity log for this employee
$activity_query = "
    SELECT al.*, u.name as performed_by_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.record_id = ? AND al.table_name = 'users' AND al.tenant_id = ? AND al.branch_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
";
$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute([$employee_id, $tenant_id, $branch_id]);
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('employee_details');
include '../includes/header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap');

/* ─── tokens ─────────────────────────────────────────────────── */
:root {
  --bg:       #f1f5fb;
  --surface:  #ffffff;
  --border:   #e3e9f4;
  --text:     #0d1321;
  --muted:    #5a6482;
  --faint:    #9aa3be;
  --blue:     #4099ff;
  --indigo:   #2ed8b6;
  --cyan:     #00b4d8;
  --green:    #00c896;
  --amber:    #f9a825;
  --rose:     #ff4d6d;
  --violet:   #7c3aed;
  --font:     'Sora', sans-serif;
  --r:        18px;
}

* { font-family: var(--font); box-sizing: border-box; }

/* override pcoded bg */
.pcoded-content,
.pcoded-inner-content { background: var(--bg) !important; }

/* ─── page layout ─────────────────────────────────────────────── */
.ed-page { padding: 24px 28px 40px; }

/* ─── TOP BANNER ─────────────────────────────────────────────── */
.ed-banner {
  position: relative;
  border-radius: 22px;
  margin-bottom: 22px;
  background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  padding: 30px 36px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
  min-height: 120px;
}

/* decorative circles */
.ed-banner::before,
.ed-banner::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.ed-banner::before {
  width: 340px; height: 340px;
  background: radial-gradient(circle, rgba(108,92,231,.25) 0%, transparent 70%);
  top: -100px; right: 80px;
}
.ed-banner::after {
  width: 180px; height: 180px;
  background: radial-gradient(circle, rgba(79,110,247,.3) 0%, transparent 70%);
  bottom: -60px; right: 30%;
}

.ed-banner-dot-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

.ed-banner-left { position: relative; z-index: 1; }
.ed-banner-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.75);
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: 4px 11px;
  border-radius: 20px;
  margin-bottom: 12px;
}
.ed-banner-tag i { font-size: .7rem; }

.ed-banner-h1 {
  font-size: 1.7rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 4px;
  letter-spacing: -.03em;
  line-height: 1.15;
}
.ed-banner-sub {
  font-size: .78rem;
  color: rgba(255,255,255,.5);
  margin: 0;
  font-weight: 500;
}

/* action buttons */
.ed-banner-right {
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
  z-index: 1;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.ed-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: rgba(255,255,255,.1);
  color: #fff;
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 10px;
  font-size: .83rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
  text-decoration: none;
  font-family: var(--font);
}

.ed-btn:hover {
  background: rgba(255,255,255,.17);
  border-color: rgba(255,255,255,.25);
  color: #fff;
}

.ed-btn i { font-size: .8rem; }

/* ─── CARD STYLING ─────────────────────────────────────────────── */
.ed-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(13,19,33,.07);
  margin-bottom: 20px;
}

.ed-card-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, rgba(64,153,255,.04) 0%, transparent 100%);
}

.ed-card-header h5 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  font-family: var(--font);
}

.ed-card-body {
  padding: 24px;
}

/* ─── PROFILE SECTION ─────────────────────────────────────────── */
.ed-profile-avatar {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.ed-profile-avatar img {
  width: 140px;
  height: 140px;
  border-radius: 18px;
  object-fit: cover;
  border: 3px solid var(--blue);
  box-shadow: 0 8px 32px rgba(64,153,255,.25);
}

.ed-profile-name {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text);
  text-align: center;
  margin: 0;
  font-family: var(--font);
}

.ed-profile-email {
  font-size: .85rem;
  color: var(--muted);
  text-align: center;
  margin: 4px 0 16px;
}

.ed-profile-badges {
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.ed-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.ed-badge-active {
  background: rgba(0,200,150,.15);
  color: var(--green);
  border: 1px solid rgba(0,200,150,.3);
}

.ed-badge-terminated {
  background: rgba(255,77,109,.15);
  color: var(--rose);
  border: 1px solid rgba(255,77,109,.3);
}

.ed-badge-role {
  background: rgba(64,153,255,.15);
  color: var(--blue);
  border: 1px solid rgba(64,153,255,.3);
}

/* ─── INFO LAYOUT ─────────────────────────────────────────────– */
.ed-info-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.ed-info-group {
  display: flex;
  flex-direction: column;
}

.ed-info-label {
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--faint);
  margin-bottom: 6px;
}

.ed-info-value {
  font-size: .95rem;
  font-weight: 600;
  color: var(--text);
  font-family: var(--font);
}

.ed-info-value.muted {
  color: var(--muted);
}

/* ─── ACTION BUTTONS ───────────────────────────────────────────– */
.ed-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
}

.ed-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px 16px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  color: var(--text);
  cursor: pointer;
  transition: all .15s;
  font-size: .82rem;
  font-weight: 600;
  text-decoration: none;
  font-family: var(--font);
}

.ed-action-btn i { font-size: .8rem; }

.ed-action-btn-primary {
  border-color: var(--blue);
  color: var(--blue);
  background: rgba(64,153,255,.08);
}

.ed-action-btn-primary:hover {
  background: var(--blue);
  color: #fff;
}

.ed-action-btn-danger {
  border-color: var(--rose);
  color: var(--rose);
  background: rgba(255,77,109,.08);
}

.ed-action-btn-danger:hover {
  background: var(--rose);
  color: #fff;
}

.ed-action-btn-success {
  border-color: var(--green);
  color: var(--green);
  background: rgba(0,200,150,.08);
}

.ed-action-btn-success:hover {
  background: var(--green);
  color: #fff;
}

/* ─── TIMELINE ─────────────────────────────────────────────────– */
.ed-timeline {
  position: relative;
  padding-left: 30px;
}

.ed-timeline-item {
  position: relative;
  margin-bottom: 20px;
  padding-bottom: 20px;
}

.ed-timeline-item:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
}

.ed-timeline-item:not(:last-child)::after {
  content: '';
  position: absolute;
  left: -20px;
  top: 20px;
  bottom: -20px;
  width: 2px;
  background: var(--border);
}

.ed-timeline-marker {
  position: absolute;
  left: -26px;
  top: 4px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--blue);
  border: 2px solid var(--surface);
  box-shadow: 0 0 0 2px var(--blue);
}

.ed-timeline-content {
  background: var(--bg);
  padding: 14px 16px;
  border-radius: 10px;
  border-left: 3px solid var(--blue);
}

.ed-timeline-title {
  font-size: .9rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 4px;
  text-transform: capitalize;
}

.ed-timeline-by {
  font-size: .8rem;
  color: var(--muted);
  margin: 0 0 4px;
}

.ed-timeline-time {
  font-size: .75rem;
  color: var(--faint);
  margin: 0;
}

@media (max-width: 768px) {
  .ed-page { padding: 16px; }
  .ed-banner { padding: 20px 24px; min-height: auto; }
  .ed-card-body { padding: 16px; }
  .ed-info-row { grid-template-columns: 1fr; }
}
</style>
   
<!-- [ Main Content ] start -->
   <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="ed-page">
                                    <!-- Banner -->
                                    <div class="ed-banner">
                                        <div class="ed-banner-dot-grid"></div>
                                            <div class="ed-banner-left">
                                                <div class="ed-banner-tag">
                                                    <i class="feather icon-user"></i>
                                                    <?php echo __('employee_details'); ?>
                                                </div>
                                                <h1 class="ed-banner-h1"><?php echo htmlspecialchars($employee['name']); ?></h1>
                                                <p class="ed-banner-sub"><?php echo __('view_complete_employee_profile_and_information'); ?></p>
                                            </div>
                                            <div class="ed-banner-right">
                                                <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="ed-btn">
                                                    <i class="feather icon-edit"></i>
                                                    <?php echo __('edit_employee'); ?>
                                                </a>
                                                <a href="employee_management.php" class="ed-btn">
                                                    <i class="feather icon-arrow-left"></i>
                                                    <?php echo __('back'); ?>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <!-- Employee Profile Card -->
                                            <div class="col-lg-4">
                                                <div class="ed-card">
                                                    <div class="ed-card-body">
                                                        <div class="ed-profile-avatar">
                                                            <img src="<?php echo htmlspecialchars($employee['profile_pic'] ?: '../assets/images/user/avatar-1.jpg'); ?>" alt="">
                                                        </div>
                                                        <h3 class="ed-profile-name"><?php echo htmlspecialchars($employee['name']); ?></h3>
                                                        <p class="ed-profile-email"><?php echo htmlspecialchars($employee['email']); ?></p>
                                                        <div class="ed-profile-badges">
                                                            <?php if ($employee['fired']): ?>
                                                                <span class="ed-badge ed-badge-terminated"><i class="feather icon-user-x"></i><?php echo __('terminated'); ?></span>
                                                            <?php else: ?>
                                                                <span class="ed-badge ed-badge-active"><i class="feather icon-user-check"></i><?php echo __('active'); ?></span>
                                                            <?php endif; ?>
                                                            <span class="ed-badge ed-badge-role"><i class="feather icon-briefcase"></i><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quick Actions -->
                                                <div class="ed-card">
                                                    <div class="ed-card-header">
                                                        <h5><?php echo __('quick_actions'); ?></h5>
                                                    </div>
                                                    <div class="ed-card-body">
                                                        <div class="ed-actions">
                                                            <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="ed-action-btn ed-action-btn-primary">
                                                                <i class="feather icon-edit"></i><?php echo __('edit'); ?>
                                                            </a>
                                                            <?php if (!$employee['fired']): ?>
                                                                <button type="button" class="ed-action-btn ed-action-btn-danger"
                                                                        onclick="terminateEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                                    <i class="feather icon-user-x"></i><?php echo __('terminate'); ?>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="ed-action-btn ed-action-btn-success"
                                                                        onclick="reinstateEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                                    <i class="feather icon-user-check"></i><?php echo __('reinstate'); ?>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Employee Information -->
                                            <div class="col-lg-8">
                                                <!-- Basic Information -->
                                                <div class="ed-card">
                                                    <div class="ed-card-header">
                                                        <h5><?php echo __('basic_information'); ?></h5>
                                                    </div>
                                                    <div class="ed-card-body">
                                                        <div class="ed-info-row">
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('full_name'); ?></span>
                                                                <span class="ed-info-value"><?php echo htmlspecialchars($employee['name']); ?></span>
                                                            </div>
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('email'); ?></span>
                                                                <span class="ed-info-value"><?php echo htmlspecialchars($employee['email']); ?></span>
                                                            </div>
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('phone'); ?></span>
                                                                <span class="ed-info-value muted"><?php echo htmlspecialchars($employee['phone'] ?: __('not_provided')); ?></span>
                                                            </div>
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('role'); ?></span>
                                                                <span class="ed-info-value"><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></span>
                                                            </div>
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('hire_date'); ?></span>
                                                                <span class="ed-info-value"><?php echo $employee['hire_date'] ? date('M d, Y', strtotime($employee['hire_date'])) : __('not_provided'); ?></span>
                                                            </div>
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('account_created'); ?></span>
                                                                <span class="ed-info-value"><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></span>
                                                            </div>
                                                        </div>
                                                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('address'); ?></span>
                                                                <span class="ed-info-value muted"><?php echo htmlspecialchars($employee['address'] ?: __('not_provided')); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Salary Information -->
                                                <div class="ed-card">
                                                    <div class="ed-card-header">
                                                        <h5><?php echo __('salary_information'); ?></h5>
                                                    </div>
                                                    <div class="ed-card-body">
                                                        <?php if ($employee['base_salary']): ?>
                                                            <div class="ed-info-row">
                                                                <div class="ed-info-group">
                                                                    <span class="ed-info-label"><?php echo __('base_salary'); ?></span>
                                                                    <span class="ed-info-value">
                                                                        <strong><?php echo number_format($employee['base_salary'], 2); ?> <?php echo htmlspecialchars($employee['salary_currency']); ?></strong>
                                                                    </span>
                                                                </div>
                                                                <div class="ed-info-group">
                                                                    <span class="ed-info-label"><?php echo __('salary_status'); ?></span>
                                                                    <span class="ed-info-value">
                                                                        <span class="ed-badge <?php echo $employee['salary_status'] === 'active' ? 'ed-badge-active' : 'ed-badge-role'; ?>">
                                                                            <?php echo ucfirst($employee['salary_status']); ?>
                                                                        </span>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div style="padding: 16px; background: rgba(0,180,216,.08); border: 1px solid rgba(0,180,216,.3); border-radius: 10px; color: var(--cyan);">
                                                                <i class="feather icon-info" style="margin-right: 6px;"></i><?php echo __('no_salary_information_available'); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Employment Status -->
                                                <?php if ($employee['fired']): ?>
                                                <div class="ed-card">
                                                    <div class="ed-card-header">
                                                        <h5><?php echo __('termination_information'); ?></h5>
                                                    </div>
                                                    <div class="ed-card-body">
                                                        <div class="ed-info-row">
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('termination_date'); ?></span>
                                                                <span class="ed-info-value">
                                                                    <?php echo $employee['fired_at'] ? date('M d, Y', strtotime($employee['fired_at'])) : __('not_provided'); ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($termination_history): ?>
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('terminated_by'); ?></span>
                                                                <span class="ed-info-value">
                                                                    <?php
                                                                    // Get terminator name
                                                                    $terminator_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                                                    $terminator_stmt->execute([$termination_history['terminated_by']]);
                                                                    $terminator = $terminator_stmt->fetch(PDO::FETCH_ASSOC);
                                                                    echo htmlspecialchars($terminator['name'] ?? 'Unknown');
                                                                    ?>
                                                                </span>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($termination_history): ?>
                                                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                                                            <div class="ed-info-group">
                                                                <span class="ed-info-label"><?php echo __('termination_reason'); ?></span>
                                                                <span class="ed-info-value"><?php echo htmlspecialchars($termination_history['termination_reason']); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>

                                                <!-- Activity Log -->
                                                <div class="ed-card">
                                                    <div class="ed-card-header">
                                                        <h5><?php echo __('recent_activity'); ?></h5>
                                                    </div>
                                                    <div class="ed-card-body">
                                                        <?php if (empty($activities)): ?>
                                                            <p class="ed-info-value muted" style="text-align: center;"><?php echo __('no_recent_activity'); ?></p>
                                                        <?php else: ?>
                                                            <div class="ed-timeline">
                                                                <?php foreach ($activities as $activity): ?>
                                                                    <div class="ed-timeline-item">
                                                                        <div class="ed-timeline-marker"></div>
                                                                        <div class="ed-timeline-content">
                                                                            <h6 class="ed-timeline-title"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                                                            <p class="ed-timeline-by">By <?php echo htmlspecialchars($activity['performed_by_name'] ?? 'System'); ?></p>
                                                                            <p class="ed-timeline-time"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></p>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Termination Modal -->
                            <div class="modal fade" id="terminationModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 32px 80px rgba(13,19,33,.22);">
                                        <div class="modal-header" style="background: linear-gradient(135deg, #4099ff, #2ed8b6); border: none; padding: 22px 26px;">
                                            <h5 class="modal-title" style="color: #fff; font-weight: 800; margin: 0; font-family: 'Sora', sans-serif; font-size: 1rem;"><?php echo __('terminate_employee'); ?></h5>
                                            <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: .8;"><span>&times;</span></button>
                                        </div>
                                        <form id="terminationForm">
                                            <div class="modal-body" style="padding: 26px;">
                                                <input type="hidden" id="terminateEmployeeId" name="employee_id">
                                                <div class="form-group">
                                                    <label style="font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em; color: var(--faint);"><?php echo __('employee_name'); ?></label>
                                                    <p id="terminateEmployeeName" style="font-weight: 700; font-size: .95rem; margin: 5px 0 0; color: var(--text); font-family: 'Sora', sans-serif;"></p>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="termination_reason" style="font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em; color: var(--faint);"><?php echo __('termination_reason'); ?></label>
                                                    <textarea class="form-control" id="termination_reason" name="reason" rows="3" required style="border-radius: 10px; border: 1.5px solid var(--border); margin-top: 6px; font-size: .84rem; resize: none; font-family: 'Sora', sans-serif;"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer" style="border-top: 1px solid var(--border); padding: 14px 26px; gap: 8px;">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 9px; font-size: .83rem; font-family: 'Sora', sans-serif;"><?php echo __('cancel'); ?></button>
                                                <button type="submit" class="btn btn-danger" style="border-radius: 9px; background: #ff4d6d; border-color: #ff4d6d; font-weight: 700; font-size: .83rem; font-family: 'Sora', sans-serif;"><?php echo __('terminate_employee'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

<script>
function terminateEmployee(employeeId, employeeName) {
    $('#terminateEmployeeId').val(employeeId);
    $('#terminateEmployeeName').text(employeeName);
    $('#terminationModal').modal('show');
}

function reinstateEmployee(employeeId, employeeName) {
    if (confirm('<?php echo __('confirm_reinstate_employee'); ?>'.replace('{name}', employeeName))) {
        $.post('terminate_employee.php', {
            employee_id: employeeId,
            action: 'reinstate',
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || '<?php echo __('error_occurred'); ?>');
            }
        })
        .fail(function() {
            alert('<?php echo __('error_occurred'); ?>');
        });
    }
}

$('#terminationForm').on('submit', function(e) {
    e.preventDefault();

    $.post('terminate_employee.php', {
        employee_id: $('#terminateEmployeeId').val(),
        reason: $('#termination_reason').val(),
        action: 'terminate',
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    })
    .done(function(response) {
        if (response.success) {
            $('#terminationModal').modal('hide');
            location.reload();
        } else {
            alert(response.message || '<?php echo __('error_occurred'); ?>');
        }
    })
    .fail(function() {
        alert('<?php echo __('error_occurred'); ?>');
    });
});
</script>
<?php include '../includes/admin_footer.php'; ?>