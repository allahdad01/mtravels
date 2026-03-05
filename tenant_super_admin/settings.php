<?php
include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                // Update profile
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');

                if (empty($name) || empty($email)) {
                    $message = 'Name and email are required.';
                    $messageType = 'danger';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Please enter a valid email address.';
                    $messageType = 'danger';
                } else {
                    try {
                        // Check if email already exists for another user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND id != ?");
                        $stmt->execute([$email, $tenant_id, $_SESSION['user_id']]);
                        if ($stmt->fetch()) {
                            $message = 'Email address already exists.';
                            $messageType = 'danger';
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW()
                                WHERE id = ? AND tenant_id = ?
                            ");
                            $stmt->execute([$name, $email, $phone, $address, $_SESSION['user_id'], $tenant_id]);

                            // Update session
                            $_SESSION['name'] = $name;

                            $message = 'Profile updated successfully.';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error updating profile: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'change_password':
                // Change password
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($current_password) || empty($new_password)) {
                    $message = 'Current password and new password are required.';
                    $messageType = 'danger';
                } elseif (strlen($new_password) < 12) {
                    $message = 'New password must be at least 12 characters long.';
                    $messageType = 'danger';
                } elseif ($new_password !== $confirm_password) {
                    $message = 'New password and confirmation do not match.';
                    $messageType = 'danger';
                } else {
                    // Validate password strength
                    require_once '../includes/PasswordValidator.php';
                    $validation = PasswordValidator::validate($new_password);
                    
                    if (!$validation['valid']) {
                        $message = 'Password does not meet requirements: ' . implode(', ', $validation['errors']);
                        $messageType = 'danger';
                    } else {
                    try {
                        // Verify current password
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND tenant_id = ?");
                        $stmt->execute([$_SESSION['user_id'], $tenant_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$user || !password_verify($current_password, $user['password'])) {
                            $message = 'Current password is incorrect.';
                            $messageType = 'danger';
                        } else {
                            // Update password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$hashed_password, $_SESSION['user_id'], $tenant_id]);

                            $message = 'Password changed successfully.';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error changing password: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                    }
                }
                break;
        }
    }
}

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = [];
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
                            <h5 class="m-b-10">Settings</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Settings</a></li>
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

        <div class="row">
            <!-- Profile Settings -->
            <div class="col-xl-8 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Profile Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="role">Role</label>
                                        <input type="text" class="form-control" value="Owner" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Profile Picture & Security -->
            <div class="col-xl-4 col-md-12">
                <!-- Profile Picture -->
                <div class="card">
                    <div class="card-header">
                        <h5>Profile Picture</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="../assets/images/user/<?= htmlspecialchars($user['profile_pic'] ?: 'default-avatar.jpg') ?>"
                             class="rounded-circle mb-3" width="100" height="100" alt="Profile Picture">
                        <p class="text-muted">Profile picture upload feature coming soon</p>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="card">
                    <div class="card-header">
                        <h5>Security Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="12">
                                <small class="form-text text-muted">Minimum 12 characters with uppercase, lowercase, numbers, and special characters (!@#$%^&*...)</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="12">
                            </div>
                            <button type="submit" class="btn btn-warning btn-block">
                                <i class="feather icon-lock"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="card">
                    <div class="card-header">
                        <h5>Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">Account Created</small>
                                <p><?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">Last Updated</small>
                                <p><?= $user['updated_at'] ? date('M d, Y H:i', strtotime($user['updated_at'])) : 'Never' ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">Login History</small>
                                <p><a href="login_history.php" class="btn btn-sm btn-outline-primary">View History</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>PHP Version</label>
                                    <input type="text" class="form-control" value="<?= phpversion() ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Server</label>
                                    <input type="text" class="form-control" value="<?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Database</label>
                                    <input type="text" class="form-control" value="MySQL" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Session Timeout</label>
                                    <input type="text" class="form-control" value="30 minutes" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Timezone</label>
                                    <input type="text" class="form-control" value="<?= date_default_timezone_get() ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Current Time</label>
                                    <input type="text" class="form-control" value="<?= date('Y-m-d H:i:s T') ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
$('#confirm_password').on('input', function() {
    const newPassword = $('#new_password').val();
    const confirmPassword = $(this).val();

    if (newPassword !== confirmPassword) {
        $(this).addClass('is-invalid');
        $('#confirm_password').after('<div class="invalid-feedback">Passwords do not match.</div>');
    } else {
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
    }
});

// Form validation
$('form').on('submit', function(e) {
    const form = $(this);
    const action = form.find('input[name="action"]').val();

    if (action === 'change_password') {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New password and confirmation do not match.');
            return false;
        }

        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long.');
            return false;
        }
    }
});
</script>

<?php include 'footer.php'; ?>