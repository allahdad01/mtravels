<?php
include 'header.php';

// Include UserAddonManager for user limit checks
require_once '../includes/UserAddonManager.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Initialize UserAddonManager
$userAddonManager = new UserAddonManager($pdo, $tenant_id);

// Get usage stats
$usageStats = $userAddonManager->getUsageStats();
$canAddMoreUsers = $usageStats['can_add_more'];
$availableSlots = $usageStats['available_slots'];
$usagePercentage = $usageStats['usage_percentage'];

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
                } elseif (strlen($password) < 12) {
                    $message = 'Password must be at least 12 characters long.';
                    $messageType = 'danger';
                } elseif (!$canAddMoreUsers) {
                    $message = 'You have reached your user limit (' . $usageStats['max_users'] . ' users). Please request additional user slots.';
                    $messageType = 'danger';
                } else {
                    // Validate password strength using PasswordValidator
                    require_once '../includes/PasswordValidator.php';
                    $validation = PasswordValidator::validate($password);
                    
                    if (!$validation['valid']) {
                        $message = 'Password does not meet requirements: ' . implode(', ', $validation['errors']);
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
                } elseif (strlen($new_password) < 12) {
                    $message = 'Password must be at least 12 characters long.';
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
    'finance' => 'Finance'
];

if (hasFeature('umrah_bookings', $allowed_features ?? [])) {
    $userRoles['umrah'] = 'Umrah';
}
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

<style>
    /* Custom Styles for Users Page */
    .users-page .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        color: white;
    }
    
    .users-page .page-header .page-header-title h5 {
        color: white;
        font-size: 1.5rem;
        font-weight: 600;
    }
    
    .users-page .breadcrumb-item a {
        color: rgba(255,255,255,0.8);
    }
    
    .users-page .breadcrumb-item a:hover {
        color: white;
    }
    
    .users-page .breadcrumb-item {
        color: rgba(255,255,255,0.9);
    }
    
    /* Stats Card Styling */
    .stats-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .stats-card .card-body {
        padding: 25px;
    }
    
    .stats-card .usage-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
    
    .stats-card .usage-icon.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .stats-card .usage-icon.success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    
    .stats-card .usage-icon.warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }
    
    .stats-card .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .stats-card .stats-label {
        color: #7f8c8d;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Progress Bar Styling */
    .custom-progress {
        height: 10px;
        border-radius: 10px;
        background: #e9ecef;
        
    }
    
    .custom-progress .progress-bar {
        border-radius: 10px;
        transition: width 0.5s ease;
    }
    
    /* Table Styling */
    .users-table {
        border-radius: 10px;
        
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .users-table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        padding: 15px 20px;
        border: none;
    }
    
    .users-table tbody td {
        padding: 15px 20px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f3f4;
    }
    
    .users-table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .users-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .users-table .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .users-table .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e9ecef;
    }
    
    .users-table .user-name {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .users-table .user-email {
        color: #7f8c8d;
        font-size: 0.875rem;
    }
    
    /* Badge Styling */
    .custom-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    
    .custom-badge.role-admin {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .custom-badge.role-sales {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    
    .custom-badge.role-finance {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }
    
    .custom-badge.role-umrah {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }
    
    .custom-badge.role-visa {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        color: white;
    }
    
    .custom-badge.branch {
        background: #e9ecef;
        color: #495057;
    }
    
    /* Button Styling */
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 3px;
        transition: all 0.2s ease;
    }
    
    .btn-action:hover {
        transform: scale(1.1);
    }
    
    /* Modal Styling */
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0;
        padding: 20px 25px;
    }
    
    .modal-header .modal-title {
        font-weight: 600;
    }
    
    .modal-header .close {
        color: white;
        text-shadow: none;
        opacity: 0.8;
    }
    
    .modal-header .close:hover {
        opacity: 1;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-footer {
        border-top: 1px solid #e9ecef;
        padding: 15px 25px;
    }
    
    /* Form Styling */
    .form-group label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
    }
    
    .form-control {
        border-radius: 8px;
        padding: 12px 15px;
        border: 1px solid #e9ecef;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Alert Styling */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 15px 20px;
    }
    
    /* Empty State Styling */
    .empty-state {
        padding: 60px 20px;
    }
    
    .empty-state i {
        opacity: 0.3;
    }
    
    /* Action Buttons Container */
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .stats-card .card-body {
            padding: 20px;
        }
        
        .stats-card .stats-number {
            font-size: 1.5rem;
        }
        
        .users-table thead th,
        .users-table tbody td {
            padding: 12px 15px;
        }
    }
</style>

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

        <!-- User Usage Stats -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="usage-icon primary">
                            <i class="feather icon-users"></i>
                        </div>
                        <h6 class="card-title mb-3">User Usage</h6>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="stats-label">Users</span>
                            <span class="stats-number">
                                <?= $usageStats['current_users'] ?> / <?= $usageStats['max_users'] ?>
                            </span>
                        </div>
                        <div class="progress mb-3 custom-progress" style="height: 10px;">
                            <div class="progress-bar <?= $usagePercentage >= 90 ? 'bg-danger' : ($usagePercentage >= 75 ? 'bg-warning' : 'bg-success') ?>" 
                                 role="progressbar" 
                                 style="width: <?= min(100, $usagePercentage) ?>%;">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-3">
                            <small><?= $usagePercentage ?>% used</small>
                            <small><?= $availableSlots ?> slots left</small>
                        </div>
                        <div class="row text-center">
                            <div class="col-6 border-right">
                                <small class="text-muted">Base Users</small>
                                <div class="font-weight-bold"><?= $usageStats['base_users'] ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-success">Addon Users</small>
                                <div class="font-weight-bold">+<?= $usageStats['additional_users'] ?></div>
                            </div>
                        </div>
                        <?php if (!$canAddMoreUsers): ?>
                        <div class="alert alert-warning mt-3 mb-0 py-2">
                            <small><i class="feather icon-alert-triangle mr-1"></i>Limit reached</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="usage-icon success mb-3">
                            <i class="feather icon-user-plus"></i>
                        </div>
                        <h6 class="card-title mb-2">User Management</h6>
                        <p class="text-muted mb-3">Manage your team members and their access permissions.</p>
                        <div class="mt-auto">
                            <?php if ($canAddMoreUsers): ?>
                            <button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#createUserModal">
                                <i class="feather icon-plus"></i> Create New User
                            </button>
                            <span class="text-muted ml-3">
                                <small><?= $availableSlots ?> user<?= $availableSlots != 1 ? 's' : '' ?> available</small>
                            </span>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-lg" disabled>
                                <i class="feather icon-plus"></i> Create New User
                            </button>
                            <a href="request_user_addon.php" class="btn btn-warning btn-lg ml-2">
                                <i class="feather icon-plus-circle"></i> Request More Users
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="row">
            <div class="col-xl-12 col-md-12">
                <div class="card stats-card">
                    <div class="card-header border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="feather icon-users mr-2"></i>All Users</h5>
                            <div class="search-box">
                                <input type="text" class="form-control" id="userSearch" placeholder="Search users..." style="max-width: 250px;">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0" id="usersTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th><i class="feather icon-user mr-1"></i>Name</th>
                                        <th><i class="feather icon-mail mr-1"></i>Email</th>
                                        <th><i class="feather icon-shield mr-1"></i>Role</th>
                                        <th><i class="feather icon-git-branch mr-1"></i>Branch</th>
                                        <th><i class="feather icon-phone mr-1"></i>Phone</th>
                                        <th><i class="feather icon-calendar mr-1"></i>Created</th>
                                        <th><i class="feather icon-settings mr-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php 
                                                    $pic = $user['profile_pic'] ?: 'default-avatar.jpg';
                                                    echo strpos($pic, 'assets/') !== false ? '../' . htmlspecialchars($pic) : '../assets/images/user/' . htmlspecialchars($pic);
                                                ?>"
                                                     class="rounded-circle mr-2" width="40" height="40" alt="Profile">
                                                <div>
                                                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                                                    <br><small class="text-muted">ID: <?= $user['id'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-primary">
                                                <?= htmlspecialchars($user['email']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge badge-role badge-<?= $user['role'] ?>">
                                                <?= htmlspecialchars($userRoles[$user['role']] ?? ucfirst($user['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['branch_name']): ?>
                                                <span class="badge badge-primary">
                                                    <i class="feather icon-git-branch mr-1"></i><?= htmlspecialchars($user['branch_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="feather icon-minus-circle mr-1"></i>Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['phone']): ?>
                                                <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="text-muted">
                                                    <i class="feather icon-phone mr-1"></i><?= htmlspecialchars($user['phone']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('M d, Y', strtotime($user['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>)" data-toggle="tooltip" title="Edit User">
                                                    <i class="feather icon-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" data-toggle="tooltip" title="Reset Password">
                                                    <i class="feather icon-key"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="empty-state">
                                                <i class="feather icon-users text-muted" style="font-size: 4rem;"></i>
                                                <h5 class="text-muted mt-3">No users found</h5>
                                                <p class="text-muted">Create your first user to get started.</p>
                                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createUserModal">
                                                    <i class="feather icon-plus"></i> Create First User
                                                </button>
                                            </div>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
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
                                <input type="password" class="form-control" id="userPassword" name="password" required minlength="12">
                                <small class="form-text text-muted">Minimum 12 characters with uppercase, lowercase, numbers, and special characters (!@#$%^&*...)</small>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <p>Reset password for user: <strong id="resetUserName"></strong></p>
                    <div class="form-group">
                        <label for="newPassword">New Password *</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="12">
                        <small class="form-text text-muted">Minimum 12 characters with uppercase, lowercase, numbers, and special characters (!@#$%^&*...)</small>
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
// Initialize tooltips
$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip({
        placement: 'top',
        trigger: 'hover'
    });
    
    // User search functionality
    $('#userSearch').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('#usersTable tbody tr').each(function() {
            const rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.includes(searchTerm));
        });
    });
});

// Check if user can add more users on page load
const canAddMoreUsers = <?= $canAddMoreUsers ? 'true' : 'false' ?>;

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

// Form validation for create user
$('#createUserModal form').on('submit', function(e) {
    if (!canAddMoreUsers) {
        e.preventDefault();
        alert('You have reached your user limit. Please request additional user slots.');
        $('#createUserModal').modal('hide');
        return false;
    }
    
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

// Disable modal show when limit reached
$('#createUserModal').on('show.bs.modal', function(e) {
    if (!canAddMoreUsers) {
        e.preventDefault();
        alert('You have reached your user limit. Please request additional user slots.');
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