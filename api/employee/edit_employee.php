<?php
require_once '../../includes/language_helpers.php';
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/SecureFileUpload.php';

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
    WHERE u.id = ? AND u.tenant_id = ? AND branch_id = ? AND u.role != 'super_admin'
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
        $password = trim($_POST['password'] ?? '');

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

        // Password validation (only if provided)
        if (!empty($password) && strlen($password) < 6) {
            $errors[] = __('password_length_error');
        }

        // Handle profile picture upload using SecureFileUpload
        $profile_pic_path = $employee['profile_pic']; // Keep existing by default
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploader = new SecureFileUpload(2 * 1024 * 1024, '../../assets/');
                $result = $uploader->upload('profile_pic', 'images/user');
                
                if ($result['success']) {
                    // Delete old profile picture if it exists and is not default
                    if ($employee['profile_pic'] && $employee['profile_pic'] !== 'default-avatar.jpg' &&
                        file_exists('../../assets/images/user/' . $employee['profile_pic'])) {
                        unlink('../../assets/images/user/' . $employee['profile_pic']);
                    }
                    $profile_pic_path = $result['data']['filename'];
                } else {
                    $errors[] = __('error_uploading_profile_picture') . ': ' . $result['error'];
                }
            } catch (Exception $e) {
                $errors[] = __('error_uploading_profile_picture') . ': ' . $e->getMessage();
            }
        }

        // Handle document uploads using SecureFileUpload
        $uploaded_documents = [];
        if (isset($_FILES['user_documents']) && !empty($_FILES['user_documents']['name'][0])) {
            try {
                $uploader = new SecureFileUpload(10 * 1024 * 1024, '../../uploads/');
                $result = $uploader->uploadMultiple('user_documents', "user_documents/{$employee_id}", 10);
                
                if ($result['success']) {
                    foreach ($result['data']['files'] as $file_result) {
                        if ($file_result['success']) {
                            // Save document info to database
                            $doc_stmt = $pdo->prepare("
                                INSERT INTO user_documents (user_id, filename, original_name, file_type, uploaded_at, tenant_id, branch_id)
                                VALUES (?, ?, ?, ?, NOW(), ?, ?)
                            ");
                            $doc_stmt->execute([
                                $employee_id,
                                $file_result['data']['filename'],
                                $file_result['data']['original_name'],
                                $file_result['data']['extension'],
                                $tenant_id,
                                $branch_id
                            ]);
                            $uploaded_documents[] = $file_result['data']['filename'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Document upload error: " . $e->getMessage());
            }
        }

        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Prepare password update
                $password_sql = '';
                $password_params = [];
                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $password_sql = ', password = ?';
                    $password_params = [$password_hash];
                }

                // Update user
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?, email = ?, phone = ?, role = ?, hire_date = ?, address = ?, profile_pic = ?
                    {$password_sql}
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");

                $params = [$name, $email, $phone, $role, $hire_date, $address, $profile_pic_path];
                if (!empty($password_params)) {
                    $params = array_merge($params, $password_params);
                }
                $params[] = $employee_id;
                $params[] = $tenant_id;
                $params[] = $branch_id;

                $stmt->execute($params);

                // Log the action
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (
                        user_id, action, table_name, record_id,
                        old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
                    ) VALUES (?, 'update_employee', 'users', ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");

                $old_values = json_encode([
                    'name' => $employee['name'],
                    'email' => $employee['email'],
                    'phone' => $employee['phone'],
                    'role' => $employee['role'],
                    'hire_date' => $employee['hire_date'],
                    'address' => $employee['address'],
                    'profile_pic' => $employee['profile_pic']
                ]);

                $new_values = json_encode([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'hire_date' => $hire_date,
                    'address' => $address,
                    'profile_pic' => $profile_pic_path
                ]);

                $logStmt->execute([
                    $_SESSION['user_id'],
                    $employee_id,
                    $old_values,
                    $new_values,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    $tenant_id,
                    $branch_id
                ]);

                $pdo->commit();

                $success = __('employee_updated_successfully');

                // Update employee data for display
                $employee['name'] = $name;
                $employee['email'] = $email;
                $employee['phone'] = $phone;
                $employee['role'] = $role;
                $employee['hire_date'] = $hire_date;
                $employee['address'] = $address;
                $employee['profile_pic'] = $profile_pic_path;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __('error_updating_employee') . ': ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Get existing documents
$documents = [];
try {
    $doc_stmt = $pdo->prepare("
        SELECT * FROM user_documents
        WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY uploaded_at DESC
    ");
    $doc_stmt->execute([$employee_id, $tenant_id, $branch_id]);
    $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

$page_title = __('edit_employee');
include '../includes/header.php';
?>

<style>
/* Avatar Upload Styles */
.avatar-upload {
    position: relative;
    max-width: 120px;
    margin: 0 auto 1rem;
}

.avatar-upload img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.avatar-upload .upload-button {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #4099ff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #fff;
}

.avatar-upload .upload-button:hover {
    background-color: #2d7be3;
    transform: scale(1.1);
}

/* Document Preview Styles */
#documentPreview {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.5rem;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.document-item:last-child {
    border-bottom: none;
}

.document-item i {
    color: #6c757d;
}

/* Password Toggle Styles */
.input-group .btn {
    border-left: 0;
}

.input-group .btn:focus {
    box-shadow: none;
    border-color: #ced4da;
}

/* Form Enhancements */
.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* Document List Styles */
.list-group-item {
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
}

.list-group-item .btn {
    margin-left: 0.25rem;
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
                                <div class="page-header">
                                    <div class="page-header-content">
                                        <h1><i class="feather icon-edit mr-2"></i><?php echo __('edit_employee'); ?></h1>
                                        <p><?php echo __('edit_information_for'); ?> <?php echo htmlspecialchars($employee['name']); ?></p>
                                    </div>
                                    <div class="page-header-actions">
                                        <a href="employee_details.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline-primary">
                                            <i class="feather icon-eye mr-1"></i><?php echo __('view_details'); ?>
                                        </a>
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

                                                <form method="POST" action="" enctype="multipart/form-data">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                                    <!-- Profile Picture Section -->
                                                    <div class="form-group">
                                                        <label><?php echo __('profile_picture'); ?></label>
                                                        <div class="avatar-upload">
                                                            <img id="profilePreview" src="../assets/images/user/<?php echo htmlspecialchars($employee['profile_pic'] ?: 'default-avatar.jpg'); ?>" alt="Profile Preview">
                                                            <label for="profile_pic" class="upload-button">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" id="profile_pic" name="profile_pic" class="d-none"
                                                                   accept="image/*" onchange="previewImage(this, 'profilePreview')">
                                                        </div>
                                                        <small class="form-text text-muted"><?php echo __('profile_picture_requirements'); ?></small>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="name"><?php echo __('full_name'); ?> <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="name" name="name"
                                                                    value="<?php echo htmlspecialchars($employee['name']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="email"><?php echo __('email'); ?> <span class="text-danger">*</span></label>
                                                                <input type="email" class="form-control" id="email" name="email"
                                                                    value="<?php echo htmlspecialchars($employee['email']); ?>" required>
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
                                                                    <option value="admin" <?php echo $employee['role'] === 'admin' ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
                                                                    <option value="finance" <?php echo $employee['role'] === 'finance' ? 'selected' : ''; ?>><?php echo __('finance'); ?></option>
                                                                    <option value="sales" <?php echo $employee['role'] === 'sales' ? 'selected' : ''; ?>><?php echo __('sales'); ?></option>
                                                                    <option value="umrah" <?php echo $employee['role'] === 'umrah' ? 'selected' : ''; ?>><?php echo __('umrah'); ?></option>
                                                                    <option value="staff" <?php echo $employee['role'] === 'staff' ? 'selected' : ''; ?>><?php echo __('staff'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label for="hire_date"><?php echo __('hire_date'); ?> <span class="text-danger">*</span></label>
                                                                <input type="date" class="form-control" id="hire_date" name="hire_date"
                                                                    value="<?php echo htmlspecialchars($employee['hire_date']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label><?php echo __('account_status'); ?></label>
                                                                <p class="form-control-plaintext">
                                                                    <?php if ($employee['fired']): ?>
                                                                        <span class="badge badge-danger"><?php echo __('terminated'); ?></span>
                                                                        <?php if ($employee['fired_at']): ?>
                                                                            <br><small class="text-muted"><?php echo __('terminated_on'); ?> <?php echo date('M d, Y', strtotime($employee['fired_at'])); ?></small>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-success"><?php echo __('active'); ?></span>
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="address"><?php echo __('address'); ?></label>
                                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                                    </div>

                                                    <!-- Password Section -->
                                                    <div class="form-group">
                                                        <label for="password"><?php echo __('password'); ?></label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" id="password" name="password"
                                                                placeholder="<?php echo __('leave_blank_to_keep_current_password'); ?>">
                                                            <div class="input-group-append">
                                                                <button type="button" class="btn btn-outline-secondary" id="passwordToggle" onclick="togglePassword()">
                                                                    <i class="feather icon-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <small class="form-text text-muted"><?php echo __('only_fill_if_you_want_to_change_password'); ?></small>
                                                    </div>

                                                    <!-- Document Upload Section -->
                                                    <div class="form-group">
                                                        <label><?php echo __('upload_documents'); ?></label>
                                                        <input type="file" class="form-control-file" id="user_documents" name="user_documents[]" multiple
                                                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                        <small class="form-text text-muted"><?php echo __('allowed_file_types'); ?>: PDF, DOC, DOCX, JPG, PNG</small>
                                                        <div id="documentPreview" class="mt-2" style="display: none;"></div>
                                                    </div>

                                                    <!-- Existing Documents -->
                                                    <?php if (!empty($documents)): ?>
                                                    <div class="form-group">
                                                        <label><?php echo __('existing_documents'); ?></label>
                                                        <div class="list-group">
                                                            <?php foreach ($documents as $doc): ?>
                                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <i class="feather icon-file mr-2"></i>
                                                                    <?php echo htmlspecialchars($doc['original_name']); ?>
                                                                    <br><small class="text-muted"><?php echo date('M d, Y H:i', strtotime($doc['uploaded_at'])); ?> - <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB</small>
                                                                </div>
                                                                <div>
                                                                    <a href="../uploads/user_documents/<?php echo $employee_id; ?>/<?php echo htmlspecialchars($doc['filename']); ?>"
                                                                       target="_blank" class="btn btn-sm btn-outline-primary mr-1">
                                                                        <i class="feather icon-eye"></i>
                                                                    </a>
                                                                    <a href="../uploads/user_documents/<?php echo $employee_id; ?>/<?php echo htmlspecialchars($doc['filename']); ?>"
                                                                       download="<?php echo htmlspecialchars($doc['original_name']); ?>" class="btn btn-sm btn-outline-secondary">
                                                                        <i class="feather icon-download"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

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
                                        <!-- Employee Profile Card -->
                                        <div class="card mb-4">
                                            <div class="card-body text-center">
                                                <img src="<?php echo htmlspecialchars($employee['profile_pic'] ?: '../assets/images/user/avatar-1.jpg'); ?>"
                                                    class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                                                <h5><?php echo htmlspecialchars($employee['name']); ?></h5>
                                                <p class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></p>
                                                <div class="mb-2">
                                                    <?php if ($employee['fired']): ?>
                                                        <span class="badge badge-danger"><?php echo __('terminated'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success"><?php echo __('active'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Salary Information -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5><?php echo __('salary_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($employee['base_salary']): ?>
                                                    <div class="text-center">
                                                        <h4 class="text-primary"><?php echo number_format($employee['base_salary'], 2); ?> <?php echo htmlspecialchars($employee['salary_currency']); ?></h4>
                                                        <p class="text-muted mb-0"><?php echo ucfirst($employee['salary_status']); ?></p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="feather icon-info mr-2"></i><?php echo __('no_salary_information_available'); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Account Information -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><?php echo __('account_information'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted"><?php echo __('created'); ?></small>
                                                        <br>
                                                        <strong><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><?php echo __('last_updated'); ?></small>
                                                        <br>
                                                        <strong><?php echo date('M d, Y', strtotime($employee['updated_at'] ?? $employee['created_at'])); ?></strong>
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

    <script>
    // Image preview function
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Password visibility toggle
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const toggleBtn = document.getElementById('passwordToggle');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleBtn.innerHTML = '<i class="feather icon-eye-off"></i>';
        } else {
            passwordField.type = 'password';
            toggleBtn.innerHTML = '<i class="feather icon-eye"></i>';
        }
    }

    // Document upload preview
    document.getElementById('user_documents').addEventListener('change', function(e) {
        const files = e.target.files;
        const previewContainer = document.getElementById('documentPreview');

        if (files.length > 0) {
            previewContainer.style.display = 'block';
            previewContainer.innerHTML = '';

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'document-item';
                fileItem.innerHTML = `
                    <i class="feather icon-file mr-2"></i>
                    ${file.name} (${(file.size / 1024).toFixed(1)} KB)
                `;
                previewContainer.appendChild(fileItem);
            }
        } else {
            previewContainer.style.display = 'none';
            previewContainer.innerHTML = '';
        }
    });
    </script>
<?php include '../includes/admin_footer.php'; ?>