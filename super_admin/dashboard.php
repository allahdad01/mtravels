<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to super admin dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/conn.php';

// Fetch super admin data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT email, name, profile_pic, created_at FROM users WHERE id = ? AND role = 'super_admin'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc() ?: ['name' => 'Admin', 'email' => 'Not Set', 'profile_pic' => null, 'created_at' => 'now'];
$stmt->close();

// Default profile image
$imagePath = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : '../assets/images/user/avatar-2.jpg';

// Fetch dashboard metrics
// Total tenants
$stmt = $conn->prepare("SELECT COUNT(*) as total_tenants FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$total_tenants = $stmt->get_result()->fetch_assoc()['total_tenants'];
$stmt->close();

// Total users
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE deleted_at IS NULL");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total_users'];
$stmt->close();

// Active subscriptions
$stmt = $conn->prepare("SELECT COUNT(*) as active_subscriptions FROM tenant_subscriptions WHERE status = 'active'");
$stmt->execute();
$active_subscriptions = $stmt->get_result()->fetch_assoc()['active_subscriptions'];
$stmt->close();

// Calculate actual monthly revenue from subscription payments
$stmt = $conn->prepare("
    SELECT SUM(sp.amount) as total_revenue
    FROM subscription_payments sp
    LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
    WHERE ts.status = 'active'
    AND DATE_FORMAT(sp.payment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$total_revenue = $result['total_revenue'] ?? 0;
$stmt->close();

// Tenant growth (last 6 months)
$tenant_growth = [];
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime($month));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE status != 'deleted' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->bind_param('s', $month);
    $stmt->execute();
    $tenant_growth[] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
}

// Subscription status distribution
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM tenant_subscriptions GROUP BY status");
$stmt->execute();
$sub_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$sub_status_data = ['active' => 0, 'expired' => 0, 'pending' => 0];
foreach ($sub_status as $status) {
    $sub_status_data[$status['status']] = $status['count'];
}

// Recent audit logs (last 5 actions)
$stmt = $conn->prepare("
    SELECT action, entity_type, entity_id, details, created_at 
    FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_audit_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Activity by action type (last 30 days)
$stmt = $conn->prepare("
    SELECT action, COUNT(*) as count 
    FROM audit_logs 
    WHERE created_at >= NOW() - INTERVAL 30 DAY 
    GROUP BY action
");
$stmt->execute();
$activity_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$activity_labels = [];
$activity_counts = [];
foreach ($activity_data as $data) {
    $activity_labels[] = $data['action'];
    $activity_counts[] = $data['count'];
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container dark:bg-gray-900">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10 text-2xl font-semibold text-gray-800 dark:text-gray-100"><?= __('super_admin_dashboard') ?></h5>
                                </div>
                                <ul class="breadcrumb flex space-x-2 text-sm text-gray-500 dark:text-gray-400">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('dashboard') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <!-- Dashboard Header -->
                        <div class="row mb-6">
                            <div class="col-md-12">
                                <div class="dashboard-header flex justify-between items-center flex-wrap bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                                    <div>
                                        <h3 class="text-3xl font-bold text-gray-800 dark:text-white"><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name']) ?></h3>
                                        <p class="text-gray-600 dark:text-gray-300"><?= __('manage_tenants_and_platform') ?></p>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button class="btn btn-primary flex items-center px-4 py-2 rounded-lg" data-toggle="modal" data-target="#profileModal">
                                            <i class="feather icon-user mr-2"></i><?= __('my_profile') ?>
                                        </button>
                                        <button class="btn btn-secondary flex items-center px-4 py-2 rounded-lg" data-toggle="modal" data-target="#settingsModal">
                                            <i class="feather icon-settings mr-2"></i><?= __('settings') ?>
                                        </button>
                                        <button id="themeToggle" class="btn btn-outline-secondary flex items-center px-4 py-2 rounded-lg">
                                            <i class="feather icon-moon mr-2"></i>Dark Mode
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dashboard Metrics -->
                        <div class="row gap-y-6">
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow border-l-4 border-blue-500">
                                    <div class="flex items-center">
                                        <div class="bg-blue-100 text-blue-600 rounded-full p-3">
                                            <i class="feather icon-users text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white"><?= $total_tenants ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300"><?= __('total_tenants') ?></span>
                                            <a href="manage_tenants.php" class="text-blue-500 hover:underline block mt-2"><?= __('view_all') ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow border-l-4 border-purple-500">
                                    <div class="flex items-center">
                                        <div class="bg-purple-100 text-purple-600 rounded-full p-3">
                                            <i class="feather icon-credit-card text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white"><?= $active_subscriptions ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300"><?= __('active_subscriptions') ?></span>
                                            <a href="manage_subscriptions.php" class="text-purple-500 hover:underline block mt-2"><?= __('view_all') ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow border-l-4 border-purple-500">
                                    <div class="flex items-center">
                                        <div class="bg-purple-100 text-purple-600 rounded-full p-3">
                                            <i class="feather icon-user-check text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white"><?= $total_users ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300"><?= __('total_users') ?></span>
                                            <a href="manage_users.php" class="text-purple-500 hover:underline block mt-2"><?= __('view_all') ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow border-l-4 border-yellow-500">
                                    <div class="flex items-center">
                                        <div class="bg-yellow-100 text-yellow-600 rounded-full p-3">
                                            <i class="fas fa-dollar-sign text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white"><span class="currency">$</span><?= number_format($total_revenue, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300"><?= __('current_month_revenue') ?></span>
                                            <a href="manage_subscriptions.php" class="text-yellow-500 hover:underline block mt-2"><?= __('view_details') ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Section -->
                        <div class="row mt-6 gap-y-6">
                            <!-- Tenant Growth Chart -->
                            <div class="col-xl-6">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4 flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('tenant_growth') ?></h5>
                                        <span class="badge bg-green-100 text-green-800 rounded-full px-3 py-1 text-xs font-medium">Last 6 months</span>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="tenantGrowthChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <!-- Subscription Status Chart -->
                            <div class="col-xl-6">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4 flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('subscription_status') ?></h5>
                                        <span class="badge bg-purple-100 text-purple-800 rounded-full px-3 py-1 text-xs font-medium">Current</span>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="subscriptionStatusChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <!-- Activity by Action Type -->
                            <div class="col-xl-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4 flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('recent_activity_by_action') ?></h5>
                                        <span class="badge bg-yellow-100 text-yellow-800 rounded-full px-3 py-1 text-xs font-medium">Last 30 days</span>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="activityChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="row mt-6">
                            <div class="col-xl-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4 flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('recent_activity') ?></h5>
                                        <span class="badge bg-blue-100 text-blue-800 rounded-full px-3 py-1 text-xs font-medium">Last 7 days</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="activity-timeline space-y-4">
                                            <?php foreach ($recent_audit_logs as $log): ?>
                                            <div class="timeline-item flex">
                                                <i class="activity-icon <?= $log['action'] === 'create_tenant' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600' ?> rounded-full w-3 h-3 mt-2 mr-4"></i>
                                                <div class="timeline-content">
                                                    <p class="mb-0 text-gray-800 dark:text-gray-100 font-medium">
                                                        <?= htmlspecialchars($log['action']) ?> on 
                                                        <?= htmlspecialchars($log['entity_type']) ?>
                                                        (ID: <?= htmlspecialchars($log['entity_id']) ?>)
                                                    </p>
                                                    <small class="text-gray-600 dark:text-gray-400 block mt-1">
                                                        <?= date('M d, Y H:i A', strtotime($log['created_at'])) ?>
                                                        | Details: <?= htmlspecialchars($log['details']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($recent_audit_logs)): ?>
                                            <p class="text-gray-600 dark:text-gray-300"><?= __('no_recent_activity') ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <a href="audit_logs.php" class="btn btn-outline-primary mt-4 hover:bg-blue-500 hover:text-white transition"><?= __('view_all_logs') ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                        <!-- Profile Modal -->
                        <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                                    <div class="modal-header bg-blue-500 text-white border-0">
                                        <h5 class="modal-title" id="profileModalLabel">
                                            <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                                        </h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body p-6">
                                        <div class="text-center mb-4">
                                            <div class="relative inline-block">
                                                <img src="<?= $imagePath ?>" class="rounded-full w-24 h-24 border-4 border-blue-500 shadow-md" alt="User Profile Image">
                                                <div class="absolute bottom-0 right-0 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                                            </div>
                                            <h5 class="mt-3 mb-1 text-xl font-semibold text-gray-800 dark:text-white"><?= htmlspecialchars($user['name']) ?></h5>
                                            <p class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($_SESSION['role']) ?></p>
                                        </div>
                                        <div class="profile-info space-y-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="text-gray-600 dark:text-gray-400 text-sm"><?= __('email') ?></label>
                                                    <p class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($user['email']) ?></p>
                                                </div>
                                                <div>
                                                    <label class="text-gray-600 dark:text-gray-400 text-sm"><?= __('role') ?></label>
                                                    <p class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($_SESSION['role']) ?></p>
                                                </div>
                                                <div>
                                                    <label class="text-gray-600 dark:text-gray-400 text-sm"><?= __('join_date') ?></label>
                                                    <p class="text-gray-800 dark:text-white font-medium"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                                                </div>
                                            </div>
                                            <div class="border-t pt-4 mt-4">
                                                <h6 class="text-blue-500 font-semibold"><?= __('account_information') ?></h6>
                                                <div class="activity-timeline space-y-2">
                                                    <div class="timeline-item flex">
                                                        <i class="activity-icon bg-blue-500 rounded-full w-3 h-3 mt-2 mr-4"></i>
                                                        <div class="timeline-content">
                                                            <p class="mb-0 text-gray-800 dark:text-white"><?= __('account_created') ?></p>
                                                            <small class="text-gray-600 dark:text-gray-400"><?= date('M d, Y H:i A', strtotime($user['created_at'])) ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Modal -->
                        <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <form id="updateProfileForm" enctype="multipart/form-data" method="POST" action="update_profile.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                                        <div class="modal-header bg-blue-500 text-white border-0">
                                            <h5 class="modal-title">
                                                <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                            </h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body p-6">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                <!-- Profile Picture -->
                                                <div class="text-center">
                                                    <div class="relative inline-block">
                                                        <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                             class="profile-upload-preview rounded-full w-32 h-32 border-4 border-gray-200 dark:border-gray-600 shadow-md" id="profilePreview">
                                                        <label for="profileImage" class="absolute bottom-0 right-0 bg-blue-500 text-white rounded-full p-2 cursor-pointer hover:bg-blue-600">
                                                            <i class="feather icon-camera"></i>
                                                        </label>
                                                        <input type="file" class="hidden" id="profileImage" name="image" 
                                                               accept="image/*" onchange="previewImage(this)">
                                                    </div>
                                                    <small class="text-gray-600 dark:text-gray-400 block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                </div>
                                                <!-- Form Fields -->
                                                <div class="col-span-2">
                                                    <div class="space-y-4">
                                                        <h6 class="text-blue-500 font-semibold">
                                                            <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                        </h6>
                                                        <div class="relative">
                                                            <input type="text" class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" id="updateName" name="name" 
                                                                   value="<?= htmlspecialchars($user['name']) ?>" required>
                                                            <label for="updateName" class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('full_name') ?></label>
                                                        </div>
                                                        <div class="relative">
                                                            <input type="email" class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" id="updateEmail" name="email" 
                                                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                                                            <label for="updateEmail" class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('email_address') ?></label>
                                                        </div>
                                                        <h6 class="text-blue-500 font-semibold mt-6">
                                                            <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                        </h6>
                                                        <div class="relative">
                                                            <input type="password" class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" id="currentPassword" name="current_password">
                                                            <label for="currentPassword" class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('current_password') ?></label>
                                                        </div>
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                            <div class="relative">
                                                                <input type="password" class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" id="newPassword" name="new_password">
                                                                <label for="newPassword" class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('new_password') ?></label>
                                                            </div>
                                                            <div class="relative">
                                                                <input type="password" class="form-control w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" id="confirmPassword" name="confirm_password">
                                                                <label for="confirmPassword" class="absolute top-0 left-3 -mt-2 bg-white dark:bg-gray-800 px-1 text-gray-600 dark:text-gray-400 text-sm"><?= __('confirm_password') ?></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                                            <button type="button" class="btn btn-outline-secondary flex items-center" data-dismiss="modal">
                                                <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                            </button>
                                            <button type="submit" class="btn btn-primary flex items-center">
                                                <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<style>
/* Enhanced Dashboard Styling */
.currency {
    font-size: 0.8em;
    vertical-align: baseline;
    position: relative;
    top: -0.1em;
    margin-right: 0.1em;
    color: #10B981;
}

/* Card hover effects */
.statustic-card, .quick-action-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
}

.statustic-card:hover, .quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

/* Icon styling */
.icon-circle {
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.statustic-card:hover .icon-circle {
    transform: scale(1.1);
}

/* Chart container styling */
.card {
    overflow: hidden;
}

.card-header {
    position: relative;
}

.card-header:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: linear-gradient(90deg, #3B82F6, #10B981);
}

/* Dark mode enhancements */
.dark .card-header:after {
    background: linear-gradient(90deg, #60A5FA, #34D399);
}

/* Timeline styling */
.activity-timeline .timeline-item {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid #e2e8f0;
    margin-bottom: 15px;
    padding-bottom: 15px;
}

.activity-timeline .timeline-item:last-child {
    border-left-color: transparent;
    margin-bottom: 0;
    padding-bottom: 0;
}

.activity-icon {
    position: absolute;
    left: -8px;
    top: 2px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.timeline-item:hover .activity-icon {
    transform: scale(1.2);
    box-shadow: 0 3px 7px rgba(0,0,0,0.3);
}

.timeline-content {
    padding-left: 10px;
}

/* Button styling */
.btn-primary {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    border: none;
    box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 8px rgba(37, 99, 235, 0.3);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .statustic-card {
        margin-bottom: 1rem;
    }
    
    .quick-action-card {
        margin-bottom: 1rem;
    }
    
    .activity-timeline .timeline-item {
        padding-left: 15px;
    }
}
</style>
<script>
// Preview profile image
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Dark mode toggle
document.getElementById('themeToggle').addEventListener('click', function() {
    document.documentElement.classList.toggle('dark');
    this.innerHTML = document.documentElement.classList.contains('dark') 
        ? '<i class="feather icon-sun mr-2"></i>Light Mode' 
        : '<i class="feather icon-moon mr-2"></i>Dark Mode';
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
});

// Load saved theme
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark');
    document.getElementById('themeToggle').innerHTML = '<i class="feather icon-sun mr-2"></i>Light Mode';
}

// Tenant Growth Chart
const tenantGrowthChart = new Chart(document.getElementById('tenantGrowthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: '<?= __('tenants') ?>',
            data: <?= json_encode($tenant_growth) ?>,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.2)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3B82F6',
            pointRadius: 4,
            pointHoverRadius: 7,
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#3B82F6',
            pointHoverBorderWidth: 2,
            borderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                mode: 'index', 
                intersect: false,
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                titleColor: '#333',
                bodyColor: '#666',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 10,
                cornerRadius: 4,
                displayColors: true,
                boxShadow: '0 2px 10px rgba(0,0,0,0.1)',
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.raw}`;
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                title: { display: true, text: '<?= __('number_of_tenants') ?>', font: { weight: 'bold' } },
                grid: {
                    color: 'rgba(200, 200, 200, 0.2)',
                    borderDash: [5, 5]
                },
                ticks: {
                    font: { size: 11 },
                    padding: 8
                }
            },
            x: { 
                title: { display: true, text: '<?= __('month') ?>', font: { weight: 'bold' } },
                grid: {
                    display: false
                },
                ticks: {
                    font: { size: 11 },
                    padding: 8
                }
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        },
        elements: {
            line: {
                tension: 0.4
            }
        }
    }
});

// Subscription Status Chart
const subscriptionStatusChart = new Chart(document.getElementById('subscriptionStatusChart'), {
    type: 'pie',
    data: {
        labels: ['<?= __('active') ?>', '<?= __('expired') ?>', '<?= __('pending') ?>'],
        datasets: [{
            data: [
                <?= $sub_status_data['active'] ?>,
                <?= $sub_status_data['expired'] ?>,
                <?= $sub_status_data['pending'] ?>
            ],
            backgroundColor: ['#10B981', '#EF4444', '#F59E0B'],
            borderColor: '#fff',
            borderWidth: 2,
            hoverBackgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(245, 158, 11, 0.8)'],
            hoverBorderColor: '#fff',
            hoverBorderWidth: 3,
            hoverOffset: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: { 
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 15,
                    font: {
                        size: 12,
                        weight: 'bold'
                    }
                }
            },
            tooltip: { 
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                titleColor: '#333',
                bodyColor: '#666',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 10,
                cornerRadius: 4,
                displayColors: true,
                boxShadow: '0 2px 10px rgba(0,0,0,0.1)',
                callbacks: { 
                    label: context => `${context.label}: ${context.raw} (${(context.raw / context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0) * 100).toFixed(1)}%)` 
                }
            }
        },
        animation: {
            animateRotate: true,
            animateScale: true,
            duration: 1000,
            easing: 'easeOutQuart'
        },
        elements: {
            arc: {
                borderWidth: 2
            }
        }
    }
});

// Activity Chart
const activityChart = new Chart(document.getElementById('activityChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($activity_labels) ?>,
        datasets: [{
            label: '<?= __('actions') ?>',
            data: <?= json_encode($activity_counts) ?>,
            backgroundColor: 'rgba(139, 92, 246, 0.8)',
            borderColor: '#7C3AED',
            borderWidth: 1,
            borderRadius: 6,
            hoverBackgroundColor: '#8B5CF6',
            hoverBorderColor: '#6D28D9',
            hoverBorderWidth: 2,
            barPercentage: 0.7,
            categoryPercentage: 0.8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                mode: 'index', 
                intersect: false,
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                titleColor: '#333',
                bodyColor: '#666',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 10,
                cornerRadius: 4,
                displayColors: true,
                boxShadow: '0 2px 10px rgba(0,0,0,0.1)',
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: ${context.raw}`;
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                title: { display: true, text: '<?= __('number_of_actions') ?>', font: { weight: 'bold' } },
                grid: {
                    color: 'rgba(200, 200, 200, 0.2)',
                    borderDash: [5, 5]
                },
                ticks: {
                    font: { size: 11 },
                    padding: 8,
                    stepSize: 1
                }
            },
            x: { 
                title: { display: true, text: '<?= __('action_type') ?>', font: { weight: 'bold' } },
                grid: {
                    display: false
                },
                ticks: {
                    font: { size: 11 },
                    padding: 8,
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart',
            delay: function(context) {
                return context.dataIndex * 100;
            }
        }
    }
});
</script>
</body>
</html>