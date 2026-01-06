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

<!-- Custom Dashboard CSS -->
<link rel="stylesheet" href="../css/super_admin/dashboard.css">

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
                        
                        <!-- Dashboard Header -->
                        <div class="dashboard-header">
                            <div class="dashboard-header-content">
                                <div class="dashboard-welcome">
                                    <h3 class="dashboard-title"><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name']) ?></h3>
                                    <p class="dashboard-subtitle"><?= __('manage_tenants_and_platform') ?></p>
                                </div>
                                <div class="dashboard-actions">
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#profileModal">
                                        <i class="feather icon-user mr-2"></i><?= __('my_profile') ?>
                                    </button>
                                    <button class="btn btn-secondary" data-toggle="modal" data-target="#settingsModal">
                                        <i class="feather icon-settings mr-2"></i><?= __('settings') ?>
                                    </button>
                                    <button id="themeToggle" class="btn btn-outline-secondary">
                                        <i class="feather icon-moon mr-2"></i>Dark Mode
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Platform Overview Section -->
                        <section class="metrics-section">
                            <div class="metrics-section-header">
                                <h4 class="metrics-section-title"><?= __('platform_overview') ?></h4>
                            </div>
                            <div class="metrics-grid">
                                <div class="col-xl-3 col-md-6">
                                    <div class="statustic-card metric-card metric-tenants">
                                        <div class="metric-icon">
                                            <i class="feather icon-users"></i>
                                        </div>
                                        <div class="metric-content">
                                            <h5 class="metric-value"><?= $total_tenants ?></h5>
                                            <span class="metric-label"><?= __('total_tenants') ?></span>
                                            <a href="manage_tenants.php" class="metric-link"><?= __('view_all') ?> <i class="feather icon-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="statustic-card metric-card metric-subscriptions">
                                        <div class="metric-icon">
                                            <i class="feather icon-credit-card"></i>
                                        </div>
                                        <div class="metric-content">
                                            <h5 class="metric-value"><?= $active_subscriptions ?></h5>
                                            <span class="metric-label"><?= __('active_subscriptions') ?></span>
                                            <a href="manage_subscriptions.php" class="metric-link"><?= __('view_all') ?> <i class="feather icon-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="statustic-card metric-card metric-users">
                                        <div class="metric-icon">
                                            <i class="feather icon-user-check"></i>
                                        </div>
                                        <div class="metric-content">
                                            <h5 class="metric-value"><?= $total_users ?></h5>
                                            <span class="metric-label"><?= __('total_users') ?></span>
                                            <a href="manage_users.php" class="metric-link"><?= __('view_all') ?> <i class="feather icon-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Financial Overview Section -->
                        <section class="metrics-section metrics-section-financial">
                            <div class="metrics-section-header">
                                <h4 class="metrics-section-title"><?= __('financial_overview') ?></h4>
                                <span class="metrics-period"><?= date('F Y') ?></span>
                            </div>
                            <div class="metrics-grid">
                                <?php foreach ($monthly_data as $currency => $data): 
                                    $symbol = $currency === 'AFS' ? '؋' : '$';
                                    $profit = $data['profit'];
                                    $is_positive = $profit >= 0;
                                ?>
                                <div class="col-xl-3 col-md-6">
                                    <div class="statustic-card metric-card metric-revenue">
                                        <div class="metric-icon">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <div class="metric-content">
                                            <h5 class="metric-value"><?= $symbol . number_format($data['revenue'], 2) ?></h5>
                                            <span class="metric-label"><?= __('current_month_revenue') ?> (<?= htmlspecialchars($currency) ?>)</span>
                                            <a href="profit_loss_dashboard.php?period=<?= date('Y-m') ?>" class="metric-link metric-link-revenue"><?= __('view_details') ?> <i class="feather icon-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="statustic-card metric-card metric-profit <?= $is_positive ? 'metric-profit-positive' : 'metric-profit-negative' ?>">
                                        <div class="metric-icon">
                                            <i class="fas <?= $is_positive ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                                        </div>
                                        <div class="metric-content">
                                            <h5 class="metric-value"><?= $symbol . number_format($profit, 2) ?></h5>
                                            <span class="metric-label"><?= __('monthly_profit_loss') ?> (<?= htmlspecialchars($currency) ?>)</span>
                                            <a href="profit_loss_dashboard.php?period=<?= date('Y-m') ?>" class="metric-link <?= $is_positive ? 'metric-link-positive' : 'metric-link-negative' ?>"><?= __('view_report') ?> <i class="feather icon-arrow-right"></i></a>
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
                        <section class="charts-section">
                            <div class="charts-grid">
                                <!-- Tenant Growth Chart -->
                                <div class="chart-card">
                                    <div class="chart-header">
                                        <h5 class="chart-title"><?= __('tenant_growth') ?></h5>
                                        <span class="chart-badge chart-badge-green">Last 6 months</span>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="tenantGrowthChart"></canvas>
                                    </div>
                                </div>
                                <!-- Subscription Status Chart -->
                                <div class="chart-card">
                                    <div class="chart-header">
                                        <h5 class="chart-title"><?= __('subscription_status') ?></h5>
                                        <span class="chart-badge chart-badge-purple">Current</span>
                                    </div>
                                    <div class="chart-body">
                                        <canvas id="subscriptionStatusChart"></canvas>
                                    </div>
                                </div>
                                <!-- Activity by Action Type -->
                                <div class="chart-card chart-card-full">
                                    <div class="chart-header">
                                        <h5 class="chart-title"><?= __('recent_activity_by_action') ?></h5>
                                        <span class="chart-badge chart-badge-yellow">Last 30 days</span>
                                    </div>
                                    <div class="chart-body">
                                        <div class="activity-chart-container">
                                            <canvas id="activityChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Recent Activity Section -->
                        <section class="activity-section">
                            <div class="activity-card">
                                <div class="activity-header">
                                    <h5 class="activity-title"><?= __('recent_activity') ?></h5>
                                    <span class="activity-badge">Last 7 days</span>
                                </div>
                                <div class="activity-body">
                                    <div class="activity-timeline">
                                        <?php foreach ($recent_audit_logs as $log): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon <?= $log['action'] === 'create_tenant' ? 'timeline-icon-success' : 'timeline-icon-primary' ?>">
                                                <i class="feather <?= $log['action'] === 'create_tenant' ? 'icon-plus' : 'icon-edit' ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <p class="timeline-title">
                                                    <?= htmlspecialchars($log['action']) ?> on 
                                                    <?= htmlspecialchars($log['entity_type']) ?>
                                                    (ID: <?= htmlspecialchars($log['entity_id']) ?>)
                                                </p>
                                                <small class="timeline-date">
                                                    <?= date('M d, Y H:i A', strtotime($log['created_at'])) ?>
                                                    | Details: <?= htmlspecialchars($log['details']) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($recent_audit_logs)): ?>
                                        <p class="activity-empty"><?= __('no_recent_activity') ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <a href="audit_logs.php" class="btn btn-outline-primary activity-view-all"><?= __('view_all_logs') ?></a>
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
                                        <div class="profile-header">
                                            <div class="profile-avatar-container">
                                                <img src="<?= $imagePath ?>" class="profile-avatar" alt="User Profile Image">
                                                <span class="profile-status"></span>
                                            </div>
                                            <h5 class="profile-name"><?= htmlspecialchars($user['name']) ?></h5>
                                            <p class="profile-role"><?= htmlspecialchars($_SESSION['role']) ?></p>
                                        </div>
                                        <div class="profile-info-grid">
                                            <div class="profile-info-item">
                                                <label class="profile-info-label"><?= __('email') ?></label>
                                                <p class="profile-info-value"><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                            <div class="profile-info-item">
                                                <label class="profile-info-label"><?= __('role') ?></label>
                                                <p class="profile-info-value"><?= htmlspecialchars($_SESSION['role']) ?></p>
                                            </div>
                                            <div class="profile-info-item">
                                                <label class="profile-info-label"><?= __('join_date') ?></label>
                                                <p class="profile-info-value"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                                            </div>
                                        </div>
                                        <div class="profile-account-info">
                                            <h6 class="profile-section-title"><i class="feather icon-info mr-2"></i><?= __('account_information') ?></h6>
                                            <div class="activity-timeline">
                                                <div class="timeline-item">
                                                    <div class="timeline-icon timeline-icon-info">
                                                        <i class="feather icon-clock"></i>
                                                    </div>
                                                    <div class="timeline-content">
                                                        <p class="timeline-title"><?= __('account_created') ?></p>
                                                        <small class="timeline-date"><?= date('M d, Y H:i A', strtotime($user['created_at'])) ?></small>
                                                    </div>
                                                </div>
                                            </div>
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
                                            <div class="settings-grid">
                                                <!-- Profile Picture -->
                                                <div class="settings-profile-picture">
                                                    <div class="profile-picture-container">
                                                        <img src="<?= $imagePath ?>" alt="Profile Picture" class="profile-upload-preview" id="profilePreview">
                                                        <label for="profileImage" class="profile-picture-upload">
                                                            <i class="feather icon-camera"></i>
                                                        </label>
                                                        <input type="file" class="hidden" id="profileImage" name="image" accept="image/*" onchange="previewImage(this)">
                                                    </div>
                                                    <small class="profile-picture-hint"><?= __('click_to_change_profile_picture') ?></small>
                                                </div>
                                                <!-- Form Fields -->
                                                <div class="settings-form-fields">
                                                    <div class="form-section">
                                                        <h6 class="form-section-title"><i class="feather icon-user mr-2"></i><?= __('personal_information') ?></h6>
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
                                                        <h6 class="form-section-title"><i class="feather icon-lock mr-2"></i><?= __('change_password') ?></h6>
                                                        <div class="form-group">
                                                            <input type="password" class="form-control" id="currentPassword" name="current_password">
                                                            <label for="currentPassword" class="form-label"><?= __('current_password') ?></label>
                                                        </div>
                                                        <div class="form-row">
                                                            <div class="form-group">
                                                                <input type="password" class="form-control" id="newPassword" name="new_password">
                                                                <label for="newPassword" class="form-label"><?= __('new_password') ?></label>
                                                            </div>
                                                            <div class="form-group">
                                                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password">
                                                                <label for="confirmPassword" class="form-label"><?= __('confirm_password') ?></label>
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
