<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../admin/security.php';

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
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
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
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-edit mr-2"></i><?php echo __('edit_employee'); ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('update_employee_information_and_details'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="employee_details.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="feather icon-eye mr-1"></i><?php echo __('view_details'); ?>
                                            </a>
                                            <a href="employee_management.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_employee_management'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('employee_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (isset($error)): ?>
                                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                                <?php endif; ?>

                                                <?php if (isset($success)): ?>
                                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                                <?php endif; ?>

                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="name"><?php echo __('full_name'); ?> <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="name" name="name"
                                                                    value="<?php echo htmlspecialchars($employee['name'] ?? ''); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="email"><?php echo __('email'); ?> <span class="text-danger">*</span></label>
                                                                <input type="email" class="form-control" id="email" name="email"
                                                                    value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="phone"><?php echo __('phone'); ?></label>
                                                                <input type="tel" class="form-control" id="phone" name="phone"
                                                                    value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="role"><?php echo __('role'); ?> <span class="text-danger">*</span></label>
                                                                <select class="form-control" id="role" name="role" required>
                                                                    <option value=""><?php echo __('select_role'); ?></option>
                                                                    <option value="admin" <?php echo ($employee['role'] ?? '') === 'admin' ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
                                                                    <option value="finance" <?php echo ($employee['role'] ?? '') === 'finance' ? 'selected' : ''; ?>><?php echo __('finance'); ?></option>
                                                                    <option value="sales" <?php echo ($employee['role'] ?? '') === 'sales' ? 'selected' : ''; ?>><?php echo __('sales'); ?></option>
                                                                    <option value="umrah" <?php echo ($employee['role'] ?? '') === 'umrah' ? 'selected' : ''; ?>><?php echo __('umrah'); ?></option>
                                                                    <option value="staff" <?php echo ($employee['role'] ?? '') === 'staff' ? 'selected' : ''; ?>><?php echo __('staff'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="hire_date"><?php echo __('hire_date'); ?> <span class="text-danger">*</span></label>
                                                                <input type="date" class="form-control" id="hire_date" name="hire_date"
                                                                    value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label><?php echo __('account_created'); ?></label>
                                                                <p class="form-control-plaintext"><?php echo date('F d, Y', strtotime($employee['created_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="address"><?php echo __('address'); ?></label>
                                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                                    </div>

                                                    <div class="form-group">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="feather icon-save mr-1"></i><?php echo __('update_employee'); ?>
                                                        </button>
                                                        <a href="employee_details.php?id=<?php echo $employee['id']; ?>" class="btn btn-secondary ml-2">
                                                            <i class="feather icon-x mr-1"></i><?php echo __('cancel'); ?>
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('employee_status'); ?></h5>
                                            </div>
                                            <div class="card-body text-center">
                                                <div class="mb-3">
                                                    <?php if ($employee['fired']): ?>
                                                        <span class="badge-danger badge-lg"><?php echo __('terminated'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge-success badge-lg"><?php echo __('active'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge-primary"><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Salary Information -->
                                        <?php if ($employee['base_salary']): ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('salary_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label><?php echo __('base_salary'); ?></label>
                                                    <p class="form-control-plaintext">
                                                        <strong><?php echo number_format($employee['base_salary'], 2); ?> <?php echo htmlspecialchars($employee['salary_currency']); ?></strong>
                                                    </p>
                                                </div>
                                                <div class="form-group">
                                                    <label><?php echo __('salary_status'); ?></label>
                                                    <p class="form-control-plaintext">
                                                        <span class="badge-<?php echo $employee['salary_status'] === 'active' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($employee['salary_status']); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('help_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <h6><?php echo __('required_fields'); ?></h6>
                                                <ul class="list-unstyled">
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('full_name_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('email_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('role_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('hire_date_required'); ?></li>
                                                </ul>

                                                <hr>

                                                <h6><?php echo __('note'); ?></h6>
                                                <p class="text-muted small"><?php echo __('changes_will_be_logged'); ?></p>
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