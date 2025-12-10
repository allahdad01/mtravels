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


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = __('invalid_csrf_token');
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
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
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? And branch_id = ?");
            $stmt->execute([$email, $tenant_id, $branch_id]);
            if ($stmt->fetch()) {
                $errors[] = __('email_already_exists');
            }
        }

        if (empty($password)) {
            $errors[] = __('password_required');
        } elseif (strlen($password) < 6) {
            $errors[] = __('password_too_short');
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

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        name, email, phone, password, role, hire_date, address,
                        tenant_id, created_at, updated_at, branch_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                ");
                $stmt->execute([$name, $email, $phone, $hashed_password, $role, $hire_date, $address, $tenant_id, $branch_id]);

                $user_id = $pdo->lastInsertId();

                // Log the action
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (
                        user_id, action, table_name, record_id,
                        old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
                    ) VALUES (?, 'add_employee', 'users', ?, ?, ?, ?, ?, NOW(), ?, ?)
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
                    $user_id,
                    'null',
                    $new_values,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    $tenant_id,
                    $branch_id
                ]);

                $pdo->commit();

                $success = __('employee_added_successfully');
                // Reset form
                $name = $email = $phone = $password = $role = $hire_date = $address = '';

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __('error_adding_employee') . ': ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$page_title = __('add_employee');
include '../includes/header.php';
?>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header">
                                    <div class="page-header-content">
                                        <h1><i class="feather icon-user-plus mr-2"></i><?php echo __('add_employee'); ?></h1>
                                        <p><?php echo __('add_new_employee_to_system'); ?></p>
                                    </div>
                                    <div class="page-header-actions">
                                        <a href="employee_management.php" class="btn btn-outline-secondary">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_employee_management'); ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="row">
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
                                                                    value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="email"><?php echo __('email'); ?> <span class="text-danger">*</span></label>
                                                                <input type="email" class="form-control" id="email" name="email"
                                                                    value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="phone"><?php echo __('phone'); ?></label>
                                                                <input type="tel" class="form-control" id="phone" name="phone"
                                                                    value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="role"><?php echo __('role'); ?> <span class="text-danger">*</span></label>
                                                                <select class="form-control" id="role" name="role" required>
                                                                    <option value=""><?php echo __('select_role'); ?></option>
                                                                    <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
                                                                    <option value="finance" <?php echo ($role ?? '') === 'finance' ? 'selected' : ''; ?>><?php echo __('finance'); ?></option>
                                                                    <option value="sales" <?php echo ($role ?? '') === 'sales' ? 'selected' : ''; ?>><?php echo __('sales'); ?></option>
                                                                    <option value="umrah" <?php echo ($role ?? '') === 'umrah' ? 'selected' : ''; ?>><?php echo __('umrah'); ?></option>
                                                                    <option value="staff" <?php echo ($role ?? '') === 'staff' ? 'selected' : ''; ?>><?php echo __('staff'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="hire_date"><?php echo __('hire_date'); ?> <span class="text-danger">*</span></label>
                                                                <input type="date" class="form-control" id="hire_date" name="hire_date"
                                                                    value="<?php echo htmlspecialchars($hire_date ?? ''); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="password"><?php echo __('password'); ?> <span class="text-danger">*</span></label>
                                                                <input type="password" class="form-control" id="password" name="password"
                                                                    required minlength="6">
                                                                <small class="form-text text-muted"><?php echo __('password_min_length'); ?></small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="address"><?php echo __('address'); ?></label>
                                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                                                    </div>

                                                    <div class="form-group">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="feather icon-save mr-1"></i><?php echo __('add_employee'); ?>
                                                        </button>
                                                        <a href="employee_management.php" class="btn btn-secondary ml-2">
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
                                                <h5><?php echo __('help_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <h6><?php echo __('required_fields'); ?></h6>
                                                <ul class="list-unstyled">
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('full_name_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('email_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('password_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('role_required'); ?></li>
                                                    <li><i class="feather icon-check text-success mr-1"></i><?php echo __('hire_date_required'); ?></li>
                                                </ul>

                                                <hr>

                                                <h6><?php echo __('password_requirements'); ?></h6>
                                                <ul class="list-unstyled">
                                                    <li><i class="feather icon-info text-info mr-1"></i><?php echo __('minimum_6_characters'); ?></li>
                                                    <li><i class="feather icon-info text-info mr-1"></i><?php echo __('use_strong_password'); ?></li>
                                                </ul>
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