<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
");
$stmt->execute([$employee_id, $tenant_id, $branch_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employee_management.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = __('invalid_csrf_token');
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? '';
        $hire_date = $_POST['hire_date'] ?? '';
        $address = trim($_POST['address'] ?? '');

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = __('name_required');
        }

        if (empty($email)) {
            $errors[] = __('email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('invalid_email_format');
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND branch_id = ? AND id != ?");
            $stmt->execute([$email, $tenant_id, $branch_id, $employee_id]);
            if ($stmt->fetch()) {
                $errors[] = __('email_already_exists');
            }
        }

        if (empty($role)) {
            $errors[] = __('role_required');
        }

        if (empty($hire_date)) {
            $errors[] = __('hire_date_required');
        }

        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Get old values for logging
                $old_values = [
                    'name' => $employee['name'],
                    'email' => $employee['email'],
                    'phone' => $employee['phone'],
                    'role' => $employee['role'],
                    'hire_date' => $employee['hire_date'],
                    'address' => $employee['address']
                ];

                // Update user
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        name = ?, email = ?, phone = ?, role = ?, hire_date = ?, address = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");
                $stmt->execute([$name, $email, $phone, $role, $hire_date, $address, $employee_id, $tenant_id, $branch_id]);

                // Log the action
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (
                        user_id, action, table_name, record_id,
                        old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
                    ) VALUES (?, 'update_employee', 'users', ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");

                $new_values = json_encode([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'hire_date' => $hire_date,
                    'address' => $address
                ]);

                $logStmt->execute([
                    $_SESSION['user_id'],
                    $employee_id,
                    json_encode($old_values),
                    $new_values,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    $tenant_id,
                    $branch_id
                ]);

                $pdo->commit();

                $success = __('employee_updated_successfully');

                // Refresh employee data
                $stmt = $pdo->prepare("
                    SELECT u.*, sm.base_salary, sm.currency as salary_currency, sm.status as salary_status
                    FROM users u
                    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
                    WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
                ");
                $stmt->execute([$employee_id, $tenant_id, $branch_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __('error_updating_employee') . ': ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$page_title = __('edit_employee');
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
.em-page { padding: 24px 28px 40px; }

/* ─── TOP BANNER ─────────────────────────────────────────────── */
.em-banner {
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
  min-height: 100px;
}

/* decorative circles */
.em-banner::before,
.em-banner::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.em-banner::before {
  width: 340px; height: 340px;
  background: radial-gradient(circle, rgba(108,92,231,.25) 0%, transparent 70%);
  top: -100px; right: 80px;
}
.em-banner::after {
  width: 180px; height: 180px;
  background: radial-gradient(circle, rgba(79,110,247,.3) 0%, transparent 70%);
  bottom: -60px; right: 30%;
}

.em-banner-dot-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

.em-banner-left { position: relative; z-index: 1; }
.em-banner-tag {
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
.em-banner-tag i { font-size: .7rem; }

.em-banner-h1 {
  font-size: 1.7rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 4px;
  letter-spacing: -.03em;
  line-height: 1.15;
}
.em-banner-sub {
  font-size: .78rem;
  color: rgba(255,255,255,.5);
  margin: 0;
  font-weight: 500;
}

/* stat pills */
.em-banner-right {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
  flex-wrap: wrap;
}
.em-stat-pill {
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.15);
  backdrop-filter: blur(8px);
  border-radius: 14px;
  padding: 12px 20px;
  text-align: center;
  min-width: 78px;
  transition: background .2s;
}
.em-stat-pill:hover { background: rgba(255,255,255,.17); }
.em-stat-n {
  font-size: 1.55rem;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  letter-spacing: -.04em;
  margin-bottom: 3px;
}
.em-stat-n.accent { color: #7ee8c4; }
.em-stat-n.danger { color: #ff8fa3; }
.em-stat-l {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: rgba(255,255,255,.45);
}
.em-banner-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 22px;
  background: #fff;
  color: var(--blue);
  border: none;
  border-radius: 12px;
  font-size: .84rem;
  font-weight: 700;
  cursor: pointer;
  transition: transform .15s, box-shadow .15s;
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
  white-space: nowrap;
}
.em-banner-add-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(0,0,0,.25);
  color: var(--indigo);
}
.em-banner-add-btn i { font-size: .9rem; }

/* ─── FORM CARD STYLES ─────────────────────────────────────────── */
.em-form-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(13,19,33,.07);
  margin-bottom: 20px;
  overflow: hidden;
}

.em-form-card-header {
  background: linear-gradient(135deg, #f1f5fb 0%, #ffffff 100%);
  border-bottom: 1px solid var(--border);
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.em-form-card-header h5 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.em-form-card-body {
  padding: 24px;
}

.em-form-group {
  margin-bottom: 20px;
}

.em-form-group label {
  font-size: .85rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 8px;
  display: block;
  letter-spacing: -.01em;
}

.em-form-group label .text-danger {
  color: var(--rose);
  margin-left: 4px;
}

.em-form-group input[type="text"],
.em-form-group input[type="email"],
.em-form-group input[type="tel"],
.em-form-group input[type="date"],
.em-form-group textarea,
.em-form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: .84rem;
  font-family: var(--font);
  color: var(--text);
  background: var(--surface);
  transition: border-color .2s, box-shadow .2s;
}

.em-form-group input[type="text"]:focus,
.em-form-group input[type="email"]:focus,
.em-form-group input[type="tel"]:focus,
.em-form-group input[type="date"]:focus,
.em-form-group textarea:focus,
.em-form-group select:focus {
  outline: none;
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(64,153,255,.1);
}

.em-form-group textarea {
  resize: vertical;
  min-height: 100px;
}

.em-form-buttons {
  display: flex;
  gap: 12px;
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.em-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: .85rem;
  font-weight: 600;
  font-family: var(--font);
  cursor: pointer;
  transition: transform .15s, box-shadow .15s;
  border: none;
  white-space: nowrap;
}

.em-btn-primary {
  background: linear-gradient(135deg, var(--blue), var(--indigo));
  color: #fff;
  box-shadow: 0 4px 16px rgba(64,153,255,.3);
}

.em-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(64,153,255,.4);
}

.em-btn-secondary {
  background: var(--surface);
  color: var(--text);
  border: 1.5px solid var(--border);
}

.em-btn-secondary:hover {
  background: var(--bg);
  border-color: var(--muted);
}

/* ─── STATUS/INFO CARD ─────────────────────────────────────────── */
.em-info-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(13,19,33,.07);
  margin-bottom: 20px;
  overflow: hidden;
}

.em-info-card-header {
  background: linear-gradient(135deg, #f1f5fb 0%, #ffffff 100%);
  border-bottom: 1px solid var(--border);
  padding: 20px 24px;
}

.em-info-card-header h5 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.em-info-card-body {
  padding: 24px;
}

.em-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 20px;
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.em-status-badge.active {
  background: rgba(0,200,150,.1);
  color: #00c896;
}

.em-status-badge.terminated {
  background: rgba(255,77,109,.1);
  color: var(--rose);
}

.em-alert {
  padding: 14px 16px;
  border-radius: 10px;
  font-size: .84rem;
  margin-bottom: 16px;
  border-left: 4px solid;
}

.em-alert.success {
  background: rgba(0,200,150,.1);
  color: #00a86b;
  border-color: #00c896;
}

.em-alert.danger {
  background: rgba(255,77,109,.1);
  color: #d63031;
  border-color: var(--rose);
}

/* ─── ROW LAYOUT ─────────────────────────────────────────────── */
.em-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 0;
}

.em-form-row.full {
  grid-template-columns: 1fr;
}

@media (max-width: 768px) {
  .em-form-row {
    grid-template-columns: 1fr;
  }
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
                            <div class="em-page">

                                <!-- Banner -->
                                <div class="em-banner">
                                    <div class="em-banner-dot-grid"></div>
                                    <div class="em-banner-left">
                                        <div class="em-banner-tag">
                                            <i class="feather icon-edit-2"></i><?php echo __('edit_employee'); ?>
                                        </div>
                                        <h1 class="em-banner-h1"><?php echo htmlspecialchars($employee['name']); ?></h1>
                                        <p class="em-banner-sub"><?php echo __('update_employee_information_and_details'); ?></p>
                                    </div>
                                    <div class="em-banner-right">
                                        <a href="employee_details.php?id=<?php echo $employee['id']; ?>" class="em-banner-add-btn">
                                            <i class="feather icon-eye"></i><?php echo __('view_details'); ?>
                                        </a>
                                        <a href="employee_management.php" class="em-banner-add-btn">
                                            <i class="feather icon-arrow-left"></i><?php echo __('back_to_employee_management'); ?>
                                        </a>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 320px; gap: 20px; margin-bottom: 40px;">

                                    <!-- Main Form -->
                                    <div>
                                        <div class="em-form-card">
                                            <div class="em-form-card-header">
                                                <h5><i class="feather icon-info"></i><?php echo __('employee_information'); ?></h5>
                                            </div>
                                            <div class="em-form-card-body">
                                                <?php if (isset($error)): ?>
                                                    <div class="em-alert danger"><?php echo $error; ?></div>
                                                <?php endif; ?>

                                                <?php if (isset($success)): ?>
                                                    <div class="em-alert success"><?php echo $success; ?></div>
                                                <?php endif; ?>

                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                                    <div class="em-form-row">
                                                        <div class="em-form-group">
                                                            <label for="name"><?php echo __('full_name'); ?> <span class="text-danger">*</span></label>
                                                            <input type="text" id="name" name="name"
                                                                value="<?php echo htmlspecialchars($employee['name'] ?? ''); ?>" required>
                                                        </div>
                                                        <div class="em-form-group">
                                                            <label for="email"><?php echo __('email'); ?> <span class="text-danger">*</span></label>
                                                            <input type="email" id="email" name="email"
                                                                value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="em-form-row">
                                                        <div class="em-form-group">
                                                            <label for="phone"><?php echo __('phone'); ?></label>
                                                            <input type="tel" id="phone" name="phone"
                                                                value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                                        </div>
                                                        <div class="em-form-group">
                                                            <label for="role"><?php echo __('role'); ?> <span class="text-danger">*</span></label>
                                                            <select id="role" name="role" required>
                                                                <option value=""><?php echo __('select_role'); ?></option>
                                                                <option value="admin" <?php echo ($employee['role'] ?? '') === 'admin' ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
                                                                <option value="finance" <?php echo ($employee['role'] ?? '') === 'finance' ? 'selected' : ''; ?>><?php echo __('finance'); ?></option>
                                                                <option value="sales" <?php echo ($employee['role'] ?? '') === 'sales' ? 'selected' : ''; ?>><?php echo __('sales'); ?></option>
                                                                <option value="umrah" <?php echo ($employee['role'] ?? '') === 'umrah' ? 'selected' : ''; ?>><?php echo __('umrah'); ?></option>
                                                                <option value="staff" <?php echo ($employee['role'] ?? '') === 'staff' ? 'selected' : ''; ?>><?php echo __('staff'); ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="em-form-row">
                                                        <div class="em-form-group">
                                                            <label for="hire_date"><?php echo __('hire_date'); ?> <span class="text-danger">*</span></label>
                                                            <input type="date" id="hire_date" name="hire_date"
                                                                value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>" required>
                                                        </div>
                                                        <div class="em-form-group">
                                                            <label><?php echo __('account_created'); ?></label>
                                                            <div style="padding: 10px 12px; background: var(--bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: .84rem; color: var(--muted);">
                                                                <?php echo date('F d, Y', strtotime($employee['created_at'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="em-form-group em-form-row full">
                                                        <div>
                                                            <label for="address"><?php echo __('address'); ?></label>
                                                            <textarea id="address" name="address"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="em-form-buttons">
                                                        <button type="submit" class="em-btn em-btn-primary">
                                                            <i class="feather icon-save"></i><?php echo __('update_employee'); ?>
                                                        </button>
                                                        <a href="employee_details.php?id=<?php echo $employee['id']; ?>" class="em-btn em-btn-secondary">
                                                            <i class="feather icon-x"></i><?php echo __('cancel'); ?>
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sidebar -->
                                    <div>
                                        <!-- Status Card -->
                                        <div class="em-info-card">
                                            <div class="em-info-card-header">
                                                <h5><i class="feather icon-user-check"></i><?php echo __('employee_status'); ?></h5>
                                            </div>
                                            <div class="em-info-card-body" style="text-align: center;">
                                                <div style="margin-bottom: 16px;">
                                                    <?php if ($employee['fired']): ?>
                                                        <div class="em-status-badge terminated"><?php echo __('terminated'); ?></div>
                                                    <?php else: ?>
                                                        <div class="em-status-badge active"><?php echo __('active'); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div style="font-size: .75rem; color: var(--faint); text-transform: uppercase; letter-spacing: .08em; font-weight: 700; margin-bottom: 6px;"><?php echo __('role'); ?></div>
                                                    <div style="font-size: .95rem; font-weight: 700; color: var(--text);"><?php echo htmlspecialchars(ucfirst($employee['role'] ?? '')); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Salary Information -->
                                        <?php if ($employee['base_salary']): ?>
                                        <div class="em-info-card">
                                            <div class="em-info-card-header">
                                                <h5><i class="feather icon-dollar-sign"></i><?php echo __('salary_information'); ?></h5>
                                            </div>
                                            <div class="em-info-card-body">
                                                <div class="em-form-group">
                                                    <label style="color: var(--faint);"><?php echo __('base_salary'); ?></label>
                                                    <div style="font-size: 1.2rem; font-weight: 800; color: var(--blue); margin-top: 6px;">
                                                        <?php echo number_format($employee['base_salary'], 2); ?> <?php echo htmlspecialchars($employee['salary_currency']); ?>
                                                    </div>
                                                </div>
                                                <div class="em-form-group">
                                                    <label style="color: var(--faint);"><?php echo __('salary_status'); ?></label>
                                                    <div style="margin-top: 6px;">
                                                        <div class="em-status-badge" style="background: <?php echo $employee['salary_status'] === 'active' ? 'rgba(0,200,150,.1)' : 'rgba(249,168,37,.1)'; ?>; color: <?php echo $employee['salary_status'] === 'active' ? '#00c896' : '#f9a825'; ?>;">
                                                            <?php echo ucfirst($employee['salary_status']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Help Card -->
                                        <div class="em-info-card">
                                            <div class="em-info-card-header">
                                                <h5><i class="feather icon-help-circle"></i><?php echo __('help_information'); ?></h5>
                                            </div>
                                            <div class="em-info-card-body">
                                                <h6 style="font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text); margin: 0 0 10px;"><?php echo __('required_fields'); ?></h6>
                                                <ul style="list-style: none; padding: 0; margin: 0 0 16px; font-size: .8rem; color: var(--muted);">
                                                    <li style="margin-bottom: 6px;"><i class="feather icon-check" style="color: var(--green); margin-right: 6px;"></i><?php echo __('full_name_required'); ?></li>
                                                    <li style="margin-bottom: 6px;"><i class="feather icon-check" style="color: var(--green); margin-right: 6px;"></i><?php echo __('email_required'); ?></li>
                                                    <li style="margin-bottom: 6px;"><i class="feather icon-check" style="color: var(--green); margin-right: 6px;"></i><?php echo __('role_required'); ?></li>
                                                    <li><i class="feather icon-check" style="color: var(--green); margin-right: 6px;"></i><?php echo __('hire_date_required'); ?></li>
                                                </ul>

                                                <div style="border-top: 1px solid var(--border); padding-top: 12px;">
                                                    <h6 style="font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text); margin: 0 0 8px;"><?php echo __('note'); ?></h6>
                                                    <p style="font-size: .75rem; color: var(--faint); margin: 0;"><?php echo __('changes_will_be_logged'); ?></p>
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
<!-- Required Js -->

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>