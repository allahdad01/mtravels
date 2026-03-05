<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
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
    error_log("Unauthorized access attempt to view_sales_agent.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$agent_id = intval($_GET['id'] ?? 0);

// Fetch sales agent details
$stmt = $pdo->prepare("SELECT sa.*, u.email as user_email 
                       FROM sales_agents sa 
                       LEFT JOIN users u ON sa.user_id = u.id 
                       WHERE sa.id = ?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: manage_sales_agents.php?error=agent_not_found');
    exit();
}

// Fetch tenants managed by this agent
$stmt = $pdo->prepare("SELECT sat.id, t.id as tenant_id, t.name, t.status, 
                              sat.status as agent_status, sat.subscription_start_date, 
                              sat.subscription_end_date, sat.commission_earned
                       FROM sales_agent_tenants sat
                       JOIN tenants t ON sat.tenant_id = t.id
                       WHERE sat.sales_agent_id = ?
                       ORDER BY sat.created_at DESC");
$stmt->execute([$agent_id]);
$managed_tenants = $stmt->fetchAll();

// Fetch commission history
$stmt = $pdo->prepare("SELECT * FROM sales_agent_commissions 
                       WHERE sales_agent_id = ? 
                       ORDER BY period_month DESC LIMIT 12");
$stmt->execute([$agent_id]);
$commissions = $stmt->fetchAll();

// Calculate totals
$total_commission_earned = 0;
$total_pending_commission = 0;
foreach ($commissions as $commission) {
    if ($commission['status'] === 'pending') {
        $total_pending_commission += $commission['commission_amount'];
    }
    if (in_array($commission['status'], ['approved', 'paid'])) {
        $total_commission_earned += $commission['commission_amount'];
    }
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-header-content">
                                <h5 class="page-title mb-0">
                                    <i class="feather icon-user mr-2"></i><?= htmlspecialchars($agent['name']) ?>
                                </h5>
                                <p class="page-subtitle mb-0 mt-2">
                                    Sales Agent Details
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <a href="manage_sales_agents.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-arrow-left mr-1"></i>Back to List
                                </a>
                                <a href="edit_sales_agent.php?id=<?= $agent['id'] ?>" class="btn btn-header-primary">
                                    <i class="feather icon-edit mr-1"></i>Edit Agent
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <!-- Agent Info Card -->
                        <div class="sa-card" style="margin-bottom: 20px;">
                            <div class="sa-card-body">
                                <div class="sac-header" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                                    <div class="sac-info">
                                        <h4><?= htmlspecialchars($agent['name']) ?></h4>
                                        <p class="sac-email"><?= htmlspecialchars($agent['email']) ?></p>
                                        <p class="sac-location">
                                            <i class="feather icon-map-pin"></i>
                                            <?= htmlspecialchars($agent['province']) ?>
                                            <?= !empty($agent['region']) ? ' • ' . htmlspecialchars($agent['region']) : '' ?>
                                        </p>
                                    </div>
                                    <div class="sac-status">
                                        <span class="pill <?= $agent['status'] === 'active' ? 'pill-green' : ($agent['status'] === 'inactive' ? 'pill-gray' : 'pill-red') ?>">
                                            <?= htmlspecialchars(ucfirst($agent['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="sac-details" style="margin-top: 20px;">
                                    <div class="sac-detail-item">
                                        <span class="sac-detail-label">Phone</span>
                                        <span class="sac-detail-value"><?= htmlspecialchars($agent['phone'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="sac-detail-item">
                                        <span class="sac-detail-label">Commission Rate</span>
                                        <span class="sac-detail-value sac-commission"><?= $agent['commission_rate'] ?>%</span>
                                    </div>
                                    <div class="sac-detail-item">
                                        <span class="sac-detail-label">Salary Type</span>
                                        <span class="sac-detail-value"><?= htmlspecialchars(ucfirst($agent['salary_type'])) ?></span>
                                    </div>
                                    <div class="sac-detail-item">
                                        <span class="sac-detail-label">Base Salary</span>
                                        <span class="sac-detail-value"><?= $agent['base_salary'] ? '$' . number_format($agent['base_salary'], 2) : 'N/A' ?></span>
                                    </div>
                                    <div class="sac-detail-item">
                                        <span class="sac-detail-label">Created</span>
                                        <span class="sac-detail-value"><?= date('M d, Y', strtotime($agent['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row" style="margin-bottom: 20px;">
                            <!-- Commission Summary -->
                            <div class="col-md-4">
                                <div class="sa-summary-card">
                                    <div class="ssc-icon" style="background: rgba(16, 185, 129, 0.12);">
                                        <i class="feather icon-dollar-sign" style="color: #10b981;"></i>
                                    </div>
                                    <div class="ssc-content">
                                        <span class="ssc-label">Commission Earned</span>
                                        <span class="ssc-value" style="color: #10b981;">$<?= number_format($total_commission_earned, 2) ?></span>
                                        <span class="ssc-sub">Approved + Paid</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Commission -->
                            <div class="col-md-4">
                                <div class="sa-summary-card">
                                    <div class="ssc-icon" style="background: rgba(245, 158, 11, 0.12);">
                                        <i class="feather icon-clock" style="color: #f59e0b;"></i>
                                    </div>
                                    <div class="ssc-content">
                                        <span class="ssc-label">Pending Commission</span>
                                        <span class="ssc-value" style="color: #f59e0b;">$<?= number_format($total_pending_commission, 2) ?></span>
                                        <span class="ssc-sub">Awaiting approval</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Managed Tenants -->
                            <div class="col-md-4">
                                <div class="sa-summary-card">
                                    <div class="ssc-icon" style="background: rgba(64, 153, 255, 0.12);">
                                        <i class="feather icon-building" style="color: #4099ff;"></i>
                                    </div>
                                    <div class="ssc-content">
                                        <span class="ssc-label">Managed Tenants</span>
                                        <span class="ssc-value" style="color: #4099ff;"><?= count($managed_tenants) ?></span>
                                        <span class="ssc-sub">
                                            <?php 
                                            $active_count = array_filter($managed_tenants, fn($t) => $t['agent_status'] == 'active');
                                            echo count($active_count) . " Active";
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Managed Tenants Table -->
                        <?php if (!empty($managed_tenants)): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Managed Tenants</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Tenant Name</th>
                                                        <th>Status</th>
                                                        <th>Agent Status</th>
                                                        <th>Subscription Start</th>
                                                        <th>Subscription End</th>
                                                        <th>Commission Earned</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($managed_tenants as $tenant): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($tenant['name']) ?></strong></td>
                                                        <td>
                                                            <span class="badge badge-<?= $tenant['status'] == 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($tenant['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?= $tenant['agent_status'] == 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($tenant['agent_status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M d, Y', strtotime($tenant['subscription_start_date'])) ?></td>
                                                        <td><?= $tenant['subscription_end_date'] ? date('M d, Y', strtotime($tenant['subscription_end_date'])) : 'Active' ?></td>
                                                        <td>$<?= number_format($tenant['commission_earned'], 2) ?></td>
                                                        <td>
                                                            <a href="edit_tenant.php?id=<?= $tenant['tenant_id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="feather icon-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Commission History Table -->
                        <?php if (!empty($commissions)): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Commission History (Last 12 Months)</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Period</th>
                                                        <th>Base Amount</th>
                                                        <th>Commission Rate</th>
                                                        <th>Commission Amount</th>
                                                        <th>Status</th>
                                                        <th>Approved By</th>
                                                        <th>Paid Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($commissions as $commission): ?>
                                                    <tr>
                                                        <td><?= date('M Y', strtotime($commission['period_month'])) ?></td>
                                                        <td>$<?= number_format($commission['base_amount'], 2) ?></td>
                                                        <td><?= $commission['commission_rate'] ?>%</td>
                                                        <td>$<?= number_format($commission['commission_amount'], 2) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $commission['status'] == 'paid' ? 'success' : ($commission['status'] == 'approved' ? 'info' : 'warning') ?>">
                                                                <?= ucfirst($commission['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $commission['approved_by'] ? htmlspecialchars($commission['approved_by']) : 'N/A' ?></td>
                                                        <td><?= $commission['paid_at'] ? date('M d, Y', strtotime($commission['paid_at'])) : 'N/A' ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

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

<style>
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
    --blue: #4099ff;
    --amber: #ffc107;
    --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --radius: 10px;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.2);
    padding: 24px;
    margin-bottom: 24px;
}

.page-header-content {
    padding: 0.5rem 0;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    line-height: 1.2;
}

.page-title i {
    font-size: 2rem;
    margin-right: 0.75rem;
    opacity: 0.95;
}

.page-subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.btn-header-primary {
    background: rgba(255,255,255,0.15) !important;
    color: #ffffff !important;
    border: 1.5px solid rgba(255,255,255,0.40) !important;
    border-radius: 6px;
    padding: 0.65rem 1.25rem !important;
    font-size: 0.9rem !important;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-header-primary:hover {
    background: rgba(255,255,255,0.25) !important;
    border-color: rgba(255,255,255,0.60) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

/* ─── CARDS ───────────────────────────────────────────────── */
.sa-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.sa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.sa-card-body {
    padding: 20px;
}

/* ─── SUMMARY CARDS ──────────────────────────────────────── */
.sa-summary-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
}

.sa-summary-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
    transform: translateY(-2px);
}

.ssc-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ssc-icon i {
    font-size: 1.5rem;
}

.ssc-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ssc-label {
    font-size: 0.75rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.ssc-value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
}

.ssc-sub {
    font-size: 0.75rem;
    color: #999;
}

/* ─── AGENT CARD STYLES ──────────────────────────────────── */
.sac-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.sac-info h4 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: #333;
}

.sac-email {
    font-size: 0.9rem;
    color: #666;
    margin: 0 0 4px 0;
}

.sac-location {
    font-size: 0.85rem;
    color: #999;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}

.sac-location i {
    font-size: 0.8rem;
}

.sac-status {
    flex-shrink: 0;
}

.sac-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
}

.sac-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sac-detail-label {
    font-size: 0.7rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sac-detail-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: #333;
}

.sac-commission {
    color: #4099ff;
    font-weight: 700;
}

/* ─── PILLS ───────────────────────────────────────────────── */
.pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.pill-green {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.pill-gray {
    background: rgba(107, 114, 128, 0.12);
    color: #6b7280;
}

.pill-red {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .sac-header {
        flex-direction: column;
    }
    
    .sac-status {
        margin-top: 12px;
    }
    
    .sac-details {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .sa-summary-card {
        flex-direction: column;
        text-align: center;
    }
    
    .ssc-icon {
        margin: 0 auto;
    }
}
</style>
</body>
</html>

