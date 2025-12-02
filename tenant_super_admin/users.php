<?php
include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                // Create new user
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? '';
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;

                if (empty($name) || empty($email) || empty($password) || empty($role)) {
                    $message = 'Name, email, password, and role are required.';
                    $messageType = 'danger';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Please enter a valid email address.';
                    $messageType = 'danger';
                } elseif (strlen($password) < 6) {
                    $message = 'Password must be at least 6 characters long.';
                    $messageType = 'danger';
                } else {
                    try {
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
                        $stmt->execute([$email, $tenant_id]);
                        if ($stmt->fetch()) {
                            $message = 'Email address already exists.';
                            $messageType = 'danger';
                        } else {
                            // Hash password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                            // Insert user
                            $stmt = $pdo->prepare("
                                INSERT INTO users (tenant_id, branch_id, name, email, password, role, phone, address, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([$tenant_id, $branch_id, $name, $email, $hashed_password, $role, $phone, $address]);

                            // Log activity
                            logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'create', 'users', $pdo->lastInsertId(), null, json_encode([
                                'name' => $name,
                                'email' => $email,
                                'role' => $role,
                                'branch_id' => $branch_id
                            ]));

                            $message = 'User created successfully.';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error creating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update':
                // Update user
                $user_id = $_POST['user_id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
                $status = $_POST['status'] ?? 'active';

                if (empty($name) || empty($email) || empty($role) || !$user_id) {
                    $message = 'Name, email, role, and user ID are required.';
                    $messageType = 'danger';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Please enter a valid email address.';
                    $messageType = 'danger';
                } else {
                    try {
                        // Check if email already exists for another user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND id != ?");
                        $stmt->execute([$email, $tenant_id, $user_id]);
                        if ($stmt->fetch()) {
                            $message = 'Email address already exists.';
                            $messageType = 'danger';
                        } else {
                            // Get old values for logging
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$user_id, $tenant_id]);
                            $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Update user
                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET name = ?, email = ?, role = ?, phone = ?, address = ?, branch_id = ?, updated_at = NOW()
                                WHERE id = ? AND tenant_id = ?
                            ");
                            $stmt->execute([$name, $email, $role, $phone, $address, $branch_id, $user_id, $tenant_id]);

                            // Log activity
                            logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'update', 'users', $user_id,
                                json_encode($oldUser),
                                json_encode([
                                    'name' => $name,
                                    'email' => $email,
                                    'role' => $role,
                                    'branch_id' => $branch_id
                                ])
                            );

                            $message = 'User updated successfully.';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error updating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'reset_password':
                // Reset user password
                $user_id = $_POST['user_id'] ?? 0;
                $new_password = $_POST['new_password'] ?? '';

                if (!$user_id || empty($new_password)) {
                    $message = 'User ID and new password are required.';
                    $messageType = 'danger';
                } elseif (strlen($new_password) < 6) {
                    $message = 'Password must be at least 6 characters long.';
                    $messageType = 'danger';
                } else {
                    try {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                        $stmt->execute([$hashed_password, $user_id, $tenant_id]);

                        // Log activity
                        logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'reset_password', 'users', $user_id, null, null);

                        $message = 'Password reset successfully.';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error resetting password: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Fetch users with branch information
try {
    $stmt = $pdo->prepare("
        SELECT u.*, b.name as branch_name
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.tenant_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// Fetch branches for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, name, code FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = [];
}

// Available roles for users
$userRoles = [
    'admin' => 'Branch Admin',
    'sales' => 'Sales',
    'finance' => 'Finance',
    'umrah' => 'Umrah',
    'visa' => 'Visa'
];

// Helper function to log activity
function logActivity($pdo, $tenant_id, $user_id, $action, $table_name, $record_id, $old_values, $new_values) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id,
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values,
            $new_values,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">User Management</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">User Management</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="feather icon-<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createUserModal">
                    <i class="feather icon-plus"></i> Create New User
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="row">
            <div class="col-xl-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Branch</th>
                                        <th>Phone</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="../assets/images/user/<?= htmlspecialchars($user['profile_pic'] ?: 'default-avatar.jpg') ?>"
                                                     class="rounded-circle mr-2" width="32" height="32" alt="Profile">
                                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars($userRoles[$user['role']] ?? ucfirst($user['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['branch_name']): ?>
                                                <span class="badge badge-primary">
                                                    <?= htmlspecialchars($user['branch_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['phone']): ?>
                                                <i class="feather icon-phone mr-1"></i><?= htmlspecialchars($user['phone']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M d, Y', strtotime($user['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>)">
                                                    <i class="feather icon-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
                                                    <i class="feather icon-key"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="feather icon-users text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted mt-2">No users found</h5>
                                            <p class="text-muted">Create your first user to get started.</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Create New User</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userName">Full Name *</label>
                                <input type="text" class="form-control" id="userName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userEmail">Email Address *</label>
                                <input type="email" class="form-control" id="userEmail" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userPassword">Password *</label>
                                <input type="password" class="form-control" id="userPassword" name="password" required minlength="6">
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userRole">Role *</label>
                                <select class="form-control" id="userRole" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($userRoles as $roleKey => $roleName): ?>
                                    <option value="<?= $roleKey ?>"><?= $roleName ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userPhone">Phone</label>
                                <input type="tel" class="form-control" id="userPhone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userBranch">Branch</label>
                                <select class="form-control" id="userBranch" name="branch_id">
                                    <option value="">Select Branch (Optional)</option>
                                    <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?> (<?= htmlspecialchars($branch['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="userAddress">Address</label>
                        <textarea class="form-control" id="userAddress" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editUserName">Full Name *</label>
                                <input type="text" class="form-control" id="editUserName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editUserEmail">Email Address *</label>
                                <input type="email" class="form-control" id="editUserEmail" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editUserRole">Role *</label>
                                <select class="form-control" id="editUserRole" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($userRoles as $roleKey => $roleName): ?>
                                    <option value="<?= $roleKey ?>"><?= $roleName ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editUserBranch">Branch</label>
                                <select class="form-control" id="editUserBranch" name="branch_id">
                                    <option value="">Select Branch (Optional)</option>
                                    <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?> (<?= htmlspecialchars($branch['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editUserPhone">Phone</label>
                                <input type="tel" class="form-control" id="editUserPhone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editUserStatus">Status</label>
                                <select class="form-control" id="editUserStatus" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editUserAddress">Address</label>
                        <textarea class="form-control" id="editUserAddress" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-body">
                    <p>Reset password for user: <strong id="resetUserName"></strong></p>
                    <div class="form-group">
                        <label for="newPassword">New Password *</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6">
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="alert alert-warning">
                        <i class="feather icon-alert-triangle"></i>
                        <strong>Warning:</strong> This will change the user's password immediately. Make sure to inform the user of the new password.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit user function
function editUser(userId) {
    // Fetch user data via AJAX
    $.ajax({
        url: 'get_user.php',
        type: 'GET',
        data: { id: userId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const user = response.user;
                $('#editUserId').val(user.id);
                $('#editUserName').val(user.name);
                $('#editUserEmail').val(user.email);
                $('#editUserRole').val(user.role);
                $('#editUserPhone').val(user.phone || '');
                $('#editUserAddress').val(user.address || '');
                $('#editUserBranch').val(user.branch_id || '');
                $('#editUserStatus').val(user.fired ? 'inactive' : 'active');
                $('#editUserModal').modal('show');
            } else {
                alert('Error loading user data: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred while loading user data: ' + error);
        }
    });
}

// Reset password function
function resetPassword(userId, userName) {
    $('#resetUserId').val(userId);
    $('#resetUserName').text(userName);
    $('#resetPasswordModal').modal('show');
}

// Form validation
$('#createUserModal form').on('submit', function(e) {
    const name = $('#userName').val().trim();
    const email = $('#userEmail').val().trim();
    const password = $('#userPassword').val();
    const role = $('#userRole').val();

    if (!name || !email || !password || !role) {
        e.preventDefault();
        alert('All required fields must be filled.');
        return false;
    }

    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long.');
        return false;
    }
});

$('#editUserForm').on('submit', function(e) {
    const name = $('#editUserName').val().trim();
    const email = $('#editUserEmail').val().trim();
    const role = $('#editUserRole').val();

    if (!name || !email || !role) {
        e.preventDefault();
        alert('Name, email, and role are required.');
        return false;
    }
});
</script>

<?php include 'footer.php'; ?>