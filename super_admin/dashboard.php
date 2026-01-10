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
require_once '../includes/db.php';

// Fetch super admin data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, name, profile_pic, created_at FROM users WHERE id = ? AND role = 'super_admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: ['name' => 'Admin', 'email' => 'Not Set', 'profile_pic' => null, 'created_at' => 'now'];

// Default profile image
$imagePath = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : '../assets/images/user/avatar-2.jpg';

// Fetch dashboard metrics
// Total tenants
$stmt = $pdo->prepare("SELECT COUNT(*) as total_tenants FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$total_tenants = $stmt->fetch()['total_tenants'];

// Total users
$stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE deleted_at IS NULL");
$stmt->execute();
$total_users = $stmt->fetch()['total_users'];

// Active subscriptions
$stmt = $pdo->prepare("SELECT COUNT(*) as active_subscriptions FROM tenant_subscriptions WHERE status = 'active'");
$stmt->execute();
$active_subscriptions = $stmt->fetch()['active_subscriptions'];

// Current month P&L data - calculate from actual revenue and expenses by currency
$current_month = date('Y-m');
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Get revenue by currency for current month from system_revenue
$stmt = $pdo->prepare("
    SELECT currency, SUM(amount) as total FROM system_revenue
    WHERE payment_date BETWEEN ? AND ? AND status = 'completed'
    GROUP BY currency
");
$stmt->execute([$start_date, $end_date]);
$revenue_by_currency = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get expenses by currency for current month from system_expenses
$stmt = $pdo->prepare("
    SELECT currency, SUM(amount) as total FROM system_expenses
    WHERE date BETWEEN ? AND ?
    GROUP BY currency
");
$stmt->execute([$start_date, $end_date]);
$expense_by_currency = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate totals and P&L by currency
$monthly_data = [];
$all_currencies = array_unique(array_merge(array_keys($revenue_by_currency), array_keys($expense_by_currency)));

foreach ($all_currencies as $currency) {
    $rev = floatval($revenue_by_currency[$currency] ?? 0);
    $exp = floatval($expense_by_currency[$currency] ?? 0);
    $profit = $rev - $exp;
    $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
    
    $monthly_data[$currency] = [
        'revenue' => $rev,
        'expenses' => $exp,
        'profit' => $profit,
        'margin' => $margin
    ];
}

// For backward compatibility, use first currency (usually AFS or USD)
$total_revenue = array_sum($revenue_by_currency);
$monthly_profit = array_sum(array_column($monthly_data, 'profit'));
$profit_margin = $total_revenue > 0 ? ($monthly_profit / $total_revenue) * 100 : 0;

// Tenant growth (last 6 months)
$tenant_growth = [];
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime($month));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE status != 'deleted' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $tenant_growth[] = $stmt->fetch()['count'];
}

// Subscription status distribution
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tenant_subscriptions GROUP BY status");
$stmt->execute();
$sub_status = $stmt->fetchAll();
$sub_status_data = ['active' => 0, 'expired' => 0, 'pending' => 0];
foreach ($sub_status as $status) {
    $sub_status_data[$status['status']] = $status['count'];
}

// Recent audit logs (last 5 actions)
$stmt = $pdo->prepare("
    SELECT action, entity_type, entity_id, details, created_at 
    FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_audit_logs = $stmt->fetchAll();

// Activity by action type (last 30 days)
$stmt = $pdo->prepare("
    SELECT action, COUNT(*) as count 
    FROM audit_logs 
    WHERE created_at >= NOW() - INTERVAL 30 DAY 
    GROUP BY action
");
$stmt->execute();
$activity_data = $stmt->fetchAll();
$activity_labels = [];
$activity_counts = [];
foreach ($activity_data as $data) {
    $activity_labels[] = $data['action'];
    $activity_counts[] = $data['count'];
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="page-header-title-text"><?= __('super_admin_dashboard') ?></h5>
                                </div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item active" aria-current="page"><?= __('dashboard') ?></li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        
                        <!-- Dashboard Header Card -->
                        <div class="page-header card dashboard-header-card">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0"><i class="feather icon-home mr-2"></i><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name']) ?></h5>
                                    <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?= __('manage_tenants_and_platform') ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#profileModal">
                                        <i class="feather icon-user mr-2"></i><?= __('my_profile') ?>
                                    </button>
                                    <button class="btn btn-secondary" data-toggle="modal" data-target="#settingsModal">
                                        <i class="feather icon-settings mr-2"></i><?= __('settings') ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Platform Overview Section -->
                        <section class="mb-4">
                            <div class="mb-3">
                                <h4 class="section-title"><i class="feather icon-bar-chart-2 mr-2"></i><?= __('platform_overview') ?></h4>
                            </div>
                            <div class="row">
                                <div class="col-xl-3 col-md-6">
                                    <div class="card metric-card">
                                        <div class="card-body text-center">
                                            <div class="metric-icon-container mb-3">
                                                <i class="feather icon-users metric-icon"></i>
                                            </div>
                                            <h3 class="metric-value"><?= $total_tenants ?></h3>
                                            <p class="text-muted mb-3"><?= __('total_tenants') ?></p>
                                            <a href="manage_tenants.php" class="btn btn-outline-primary btn-sm">
                                                <i class="feather icon-arrow-right mr-1"></i><?= __('view_all') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card metric-card">
                                        <div class="card-body text-center">
                                            <div class="metric-icon-container mb-3">
                                                <i class="feather icon-credit-card metric-icon"></i>
                                            </div>
                                            <h3 class="metric-value"><?= $active_subscriptions ?></h3>
                                            <p class="text-muted mb-3"><?= __('active_subscriptions') ?></p>
                                            <a href="manage_subscriptions.php" class="btn btn-outline-primary btn-sm">
                                                <i class="feather icon-arrow-right mr-1"></i><?= __('view_all') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card metric-card">
                                        <div class="card-body text-center">
                                            <div class="metric-icon-container mb-3">
                                                <i class="feather icon-user-check metric-icon"></i>
                                            </div>
                                            <h3 class="metric-value"><?= $total_users ?></h3>
                                            <p class="text-muted mb-3"><?= __('total_users') ?></p>
                                            <a href="manage_users.php" class="btn btn-outline-primary btn-sm">
                                                <i class="feather icon-arrow-right mr-1"></i><?= __('view_all') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Financial Overview Section -->
                        <section class="mb-4">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <h4 class="section-title mb-0"><i class="feather icon-dollar-sign mr-2"></i><?= __('financial_overview') ?></h4>
                                <span class="badge badge-info badge-pill px-3 py-2"><?= date('F Y') ?></span>
                            </div>
                            <div class="row">
                                <?php foreach ($monthly_data as $currency => $data): 
                                    $symbol = $currency === 'AFS' ? '؋' : '$';
                                    $profit = $data['profit'];
                                    $is_positive = $profit >= 0;
                                ?>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card metric-card">
                                        <div class="card-body text-center">
                                            <div class="metric-icon-container mb-3 <?= $is_positive ? 'metric-icon-success' : 'metric-icon-danger' ?>">
                                                <i class="fas <?= $is_positive ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                                            </div>
                                            <h3 class="metric-value <?= $is_positive ? 'text-success' : 'text-danger' ?>"><?= $symbol . number_format($data['revenue'], 2) ?></h3>
                                            <p class="text-muted mb-2"><?= __('current_month_revenue') ?> (<?= htmlspecialchars($currency) ?>)</p>
                                            <hr class="my-3">
                                            <h4 class="metric-value <?= $is_positive ? 'text-success' : 'text-danger' ?>"><?= $symbol . number_format($profit, 2) ?></h4>
                                            <p class="text-muted mb-0"><?= __('monthly_profit_loss') ?></p>
                                            <a href="profit_loss_dashboard.php?period=<?= date('Y-m') ?>" class="btn btn-outline-primary btn-sm mt-3">
                                                <i class="feather icon-arrow-right mr-1"></i><?= __('view_details') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($monthly_data)): ?>
                                <div class="col-xl-6 col-md-12">
                                    <div class="alert alert-info">No financial data available for current month</div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Charts Section -->
                        <section class="mb-4">
                            <div class="row">
                                <!-- Tenant Growth Chart -->
                                <div class="col-xl-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="feather icon-trending-up mr-2"></i><?= __('tenant_growth') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="tenantGrowthChart" style="height: 300px;"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <!-- Subscription Status Chart -->
                                <div class="col-xl-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="feather icon-pie-chart mr-2"></i><?= __('subscription_status') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="subscriptionStatusChart" style="height: 300px;"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Activity Chart Section -->
                        <section class="mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="feather icon-activity mr-2"></i><?= __('recent_activity_by_action') ?></h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="activityChart" style="height: 250px;"></canvas>
                                </div>
                            </div>
                        </section>

                        <!-- Recent Activity Section -->
                        <section class="mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="feather icon-clock mr-2"></i><?= __('recent_activity') ?></h5>
                                    <span class="badge badge-info badge-pill">Last 7 days</span>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recent_audit_logs)): ?>
                                    <div class="activity-timeline">
                                        <?php foreach ($recent_audit_logs as $log): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon <?= $log['action'] === 'create_tenant' ? 'timeline-icon-success' : 'timeline-icon-primary' ?>">
                                                <i class="feather <?= $log['action'] === 'create_tenant' ? 'icon-plus' : 'icon-edit' ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <p class="timeline-title mb-1">
                                                    <?= htmlspecialchars($log['action']) ?> on 
                                                    <?= htmlspecialchars($log['entity_type']) ?>
                                                    (ID: <?= htmlspecialchars($log['entity_id']) ?>)
                                                </p>
                                                <small class="text-muted">
                                                    <?= date('M d, Y H:i A', strtotime($log['created_at'])) ?>
                                                    | Details: <?= htmlspecialchars($log['details']) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-center text-muted py-4"><?= __('no_recent_activity') ?></p>
                                    <?php endif; ?>
                                    <div class="text-end mt-3">
                                        <a href="audit_logs.php" class="btn btn-outline-primary btn-sm">
                                            <i class="feather icon-list mr-1"></i><?= __('view_all_logs') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Profile Modal -->
                        <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="profileModalLabel">
                                            <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="text-center mb-4">
                                            <div class="profile-avatar-container mb-3">
                                                <img src="<?= $imagePath ?>" class="profile-avatar" alt="User Profile Image" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                                            </div>
                                            <h5 class="mb-1"><?= htmlspecialchars($user['name']) ?></h5>
                                            <span class="badge badge-primary badge-pill px-3"><?= htmlspecialchars($_SESSION['role']) ?></span>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <p class="text-muted mb-1 small"><?= __('email') ?></p>
                                                <p class="font-weight-bold mb-0"><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                            <div class="col-4">
                                                <p class="text-muted mb-1 small"><?= __('role') ?></p>
                                                <p class="font-weight-bold mb-0"><?= htmlspecialchars($_SESSION['role']) ?></p>
                                            </div>
                                            <div class="col-4">
                                                <p class="text-muted mb-1 small"><?= __('join_date') ?></p>
                                                <p class="font-weight-bold mb-0"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                                            </div>
                                        </div>
                                        <hr class="my-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="feather icon-info mr-2"></i><?= __('account_created') ?>: <?= date('M d, Y H:i A', strtotime($user['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
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
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <!-- Profile Picture -->
                                                <div class="col-md-4 text-center">
                                                    <div class="profile-picture-container mb-3">
                                                        <img src="<?= $imagePath ?>" alt="Profile Picture" class="profile-upload-preview" id="profilePreview" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #667eea;">
                                                        <label for="profileImage" class="profile-picture-upload" style="position: absolute; bottom: 10px; right: 10px; background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                                            <i class="feather icon-camera"></i>
                                                        </label>
                                                        <input type="file" class="hidden" id="profileImage" name="image" accept="image/*" onchange="previewImage(this)">
                                                    </div>
                                                    <small class="text-muted"><?= __('click_to_change_profile_picture') ?></small>
                                                </div>
                                                <!-- Form Fields -->
                                                <div class="col-md-8">
                                                    <div class="form-section mb-4">
                                                        <h6 class="form-section-title mb-3"><i class="feather icon-user mr-2"></i><?= __('personal_information') ?></h6>
                                                        <div class="form-group">
                                                            <input type="text" class="form-control" id="updateName" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                                            <label for="updateName" class="form-label"><?= __('full_name') ?></label>
                                                        </div>
                                                        <div class="form-group">
                                                            <input type="email" class="form-control" id="updateEmail" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                                            <label for="updateEmail" class="form-label"><?= __('email_address') ?></label>
                                                        </div>
                                                    </div>
                                                    <div class="form-section">
                                                        <h6 class="form-section-title mb-3"><i class="feather icon-lock mr-2"></i><?= __('change_password') ?></h6>
                                                        <div class="form-group">
                                                            <input type="password" class="form-control" id="currentPassword" name="current_password">
                                                            <label for="currentPassword" class="form-label"><?= __('current_password') ?></label>
                                                        </div>
                                                        <div class="form-row">
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <input type="password" class="form-control" id="newPassword" name="new_password">
                                                                    <label for="newPassword" class="form-label"><?= __('new_password') ?></label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password">
                                                                    <label for="confirmPassword" class="form-label"><?= __('confirm_password') ?></label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                            </button>
                                            <button type="submit" class="btn btn-primary">
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
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
/* Enhanced custom styles matching request_user_addon.php style */
.page-header.card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0 !important;
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

.metric-card .card-body {
    padding: 2rem 1.5rem;
}

.metric-icon-container {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.metric-icon {
    font-size: 28px;
    color: white;
}

.metric-icon-success {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
}

.metric-icon-danger {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
}

.metric-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #333;
    margin: 0.5rem 0;
}

.section-title {
    font-weight: 600;
    color: #333;
    margin: 0;
    display: flex;
    align-items: center;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-primary {
    background-color: #667eea;
}

.badge-success {
    background-color: #10B981;
}

.badge-info {
    background-color: #667eea;
}

.badge-warning {
    background-color: #F59E0B;
    color: #212529;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}

.btn-secondary {
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-outline-primary {
    border-radius: 25px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    transform: translateY(-1px);
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem 1rem;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-label {
    font-weight: 500;
    color: #667eea;
}

.form-section-title {
    font-weight: 600;
    color: #667eea;
    display: flex;
    align-items: center;
}

/* Timeline styles */
.activity-timeline {
    position: relative;
    padding-left: 30px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -30px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: white;
}

.timeline-icon-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.timeline-icon-success {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
}

.timeline-icon-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.timeline-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

.timeline-title {
    font-weight: 600;
    color: #333;
}

.timeline-date {
    color: #6c757d;
}

/* Profile avatar */
.profile-avatar-container {
    position: relative;
    display: inline-block;
}

.profile-avatar {
    border: 4px solid #667eea;
}

.profile-picture-upload {
    transition: all 0.3s ease;
}

.profile-picture-upload:hover {
    background: #764ba2 !important;
    transform: scale(1.1);
}

/* Modal styles */
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 0;
}

.modal-header .close {
    color: white;
    text-shadow: none;
    opacity: 0.8;
}

.modal-header .close:hover {
    opacity: 1;
}

.modal-title {
    font-weight: 600;
    display: flex;
    align-items: center;
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

// Tenant Growth Chart
const tenantGrowthChart = new Chart(document.getElementById('tenantGrowthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: '<?= __('tenants') ?>',
            data: <?= json_encode($tenant_growth) ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.2)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#667eea',
            pointRadius: 5,
            pointHoverRadius: 8,
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#667eea',
            pointHoverBorderWidth: 3,
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
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#333',
                bodyColor: '#666',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
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
        }
    }
});

// Subscription Status Chart
const subscriptionStatusChart = new Chart(document.getElementById('subscriptionStatusChart'), {
    type: 'doughnut',
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
            borderWidth: 3,
            hoverOffset: 8
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
                    padding: 20,
                    font: {
                        size: 12,
                        weight: 'bold'
                    }
                }
            },
            tooltip: { 
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#333',
                bodyColor: '#666',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
            }
        },
        animation: {
            animateRotate: true,
            animateScale: true,
            duration: 1000,
            easing: 'easeOutQuart'
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
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: '#667eea',
            borderWidth: 1,
            borderRadius: 6,
            hoverBackgroundColor: '#764ba2',
            hoverBorderColor: '#764ba2',
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
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#333',
                bodyColor: '#666',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
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
