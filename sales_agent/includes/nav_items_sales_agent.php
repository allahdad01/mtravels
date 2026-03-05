<?php
/**
 * nav_items_sales_agent.php
 * Navigation menu items for sales agents.
 *
 * Usage:
 *   <?php include __DIR__ . '/nav_items_sales_agent.php'; ?>
 */

// Get agent salary type for conditional menu display
$salary_type = 'both'; // Default
if (isset($_SESSION['user_id'])) {
    require_once dirname(__DIR__, 2) . '/includes/db.php';
    $stmt = $pdo->prepare("SELECT salary_type FROM sales_agents WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $agent_info = $stmt->fetch();
    if ($agent_info) {
        $salary_type = $agent_info['salary_type'];
    }
}
?>

<li class="nav-item">
    <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
        <span class="pcoded-mtext">Dashboard</span>
    </a>
</li>

<li class="nav-item">
    <a href="tenants.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'tenants.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="feather icon-briefcase"></i></span>
        <span class="pcoded-mtext">Managed Tenants</span>
    </a>
</li>

<?php if (in_array($salary_type, ['salary', 'both'])): ?>
<li class="nav-item">
    <a href="salary_payments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'salary_payments.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
        <span class="pcoded-mtext">Salary Payments</span>
    </a>
</li>
<?php endif; ?>

<?php if (in_array($salary_type, ['commission', 'both'])): ?>
<li class="nav-item">
    <a href="commissions.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="feather icon-bar-chart-2"></i></span>
        <span class="pcoded-mtext">Commissions</span>
    </a>
</li>

<li class="nav-item">
    <a href="payments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
        <span class="pcoded-mtext">Payments</span>
    </a>
</li>

<li class="nav-item">
    <a href="statements.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'statements.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="feather icon-file-text"></i></span>
        <span class="pcoded-mtext">Statements</span>
    </a>
</li>
<?php endif; ?>

<li class="nav-item">
    <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
        <span class="pcoded-micon"><i class="feather icon-user"></i></span>
        <span class="pcoded-mtext">My Profile</span>
    </a>
</li>
