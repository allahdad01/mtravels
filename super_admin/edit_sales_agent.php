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
    error_log("Unauthorized access attempt to edit_sales_agent.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$agent_id = intval($_GET['id'] ?? 0);

// Fetch sales agent
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

// Handle POST request to update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: edit_sales_agent.php?id=' . $agent_id . '&error=Security+check+failed');
        exit();
    }

    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $province = $_POST['province'] ?? '';
    $region = $_POST['region'] ?? '';
    $commission_rate = $_POST['commission_rate'] ?? $agent['commission_rate'];
    $salary_type = $_POST['salary_type'] ?? $agent['salary_type'];
    $base_salary = $_POST['base_salary'] ?? $agent['base_salary'];
    $status = $_POST['status'] ?? $agent['status'];
    $notes = $_POST['notes'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $errors = [];

    // Validate input
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($province)) {
        $errors[] = "Province is required.";
    }
    if (!in_array($salary_type, ['salary', 'commission', 'hybrid'])) {
        $errors[] = "Invalid salary type.";
    }
    // Validate commission rate only for commission or hybrid types
    if (($salary_type === 'commission' || $salary_type === 'hybrid') && (empty($commission_rate) || $commission_rate < 0 || $commission_rate > 100)) {
        $errors[] = "Commission rate must be between 0 and 100.";
    }
    // Validate base salary only for salary or hybrid types
    if (($salary_type === 'salary' || $salary_type === 'hybrid') && (empty($base_salary) || $base_salary < 0)) {
        $errors[] = "Base salary is required for salary/hybrid type and must be positive.";
    }
    if (!in_array($status, ['active', 'inactive', 'suspended'])) {
        $errors[] = "Invalid status.";
    }
    
    // Validate password if provided
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if ($password !== $password_confirm) {
            $errors[] = "Passwords do not match.";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE sales_agents 
                                   SET name = ?, phone = ?, province = ?, region = ?, 
                                       commission_rate = ?, salary_type = ?, base_salary = ?, 
                                       status = ?, notes = ?, updated_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([
                $name,
                $phone ?: null,
                $province,
                $region ?: null,
                $commission_rate,
                $salary_type,
                $base_salary ?: null,
                $status,
                $notes ?: null,
                $agent_id
            ]);

            // Also update user name and password
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $hashed_password, $agent['user_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $agent['user_id']]);
            }

            // Log action
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                    VALUES (?, 'update_sales_agent', 'sales_agent', ?, ?, ?, NOW())");
            $details = json_encode([
                'name' => $name,
                'province' => $province,
                'region' => $region,
                'commission_rate' => $commission_rate,
                'salary_type' => $salary_type,
                'status' => $status,
                'updated_by' => $_SESSION['user_id']
            ]);
            $stmt->execute([$_SESSION['user_id'], $agent_id, $details, $_SERVER['REMOTE_ADDR']]);

            error_log("SALES_AGENT_UPDATED: Admin {$_SESSION['user_id']} updated sales agent {$agent_id}");
            header('Location: manage_sales_agents.php?success=Sales+agent+updated+successfully');
        } catch (Exception $e) {
            error_log("Error updating sales agent: " . $e->getMessage());
            header('Location: edit_sales_agent.php?id=' . $agent_id . '&error=Failed+to+update:+' . urlencode($e->getMessage()));
        }
    } else {
        header('Location: edit_sales_agent.php?id=' . $agent_id . '&error=' . urlencode(implode('. ', $errors)));
    }
    exit();
}

// Fetch distinct provinces for filter
$stmt = $pdo->prepare("SELECT DISTINCT province FROM sales_agents ORDER BY province ASC");
$stmt->execute();
$provinces = $stmt->fetchAll();
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
                                    <i class="feather icon-edit-2 mr-2"></i>Edit Sales Agent
                                </h5>
                                <p class="page-subtitle mb-0 mt-2">
                                    Update sales agent information
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <a href="manage_sales_agents.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-arrow-left mr-1"></i>Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="sa-card">
                            <div class="sa-card-body">
                                <div class="sac-header" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                                    <div class="sac-info">
                                        <h4>Edit Sales Agent</h4>
                                        <p class="sac-email"><?= htmlspecialchars($agent['name']) ?></p>
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
                            </div>
                        </div>

                        <?php if (!empty($_GET['error'])): ?>
                        <div class="sa-alert sa-alert-danger" style="margin-bottom: 20px;" id="errorAlert">
                            <div class="sa-alert-icon">⚠</div>
                            <div class="sa-alert-content">
                                <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
                            </div>
                            <button type="button" class="sa-alert-close" onclick="document.getElementById('errorAlert').remove();">×</button>
                        </div>
                        <script>
                            // Auto-scroll to alert
                            document.getElementById('errorAlert').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        </script>
                        <?php endif; ?>

                        <?php if (!empty($_GET['success'])): ?>
                        <div class="sa-alert sa-alert-success" style="margin-bottom: 20px;" id="successAlert">
                            <div class="sa-alert-icon">✓</div>
                            <div class="sa-alert-content">
                                <strong>Success:</strong> <?= htmlspecialchars($_GET['success']) ?>
                            </div>
                            <button type="button" class="sa-alert-close" onclick="document.getElementById('successAlert').remove();">×</button>
                        </div>
                        <script>
                            // Auto-scroll to alert
                            document.getElementById('successAlert').scrollIntoView({ behavior: 'smooth', block: 'start' });
                            // Auto-hide success alert after 5 seconds
                            setTimeout(() => {
                                const alert = document.getElementById('successAlert');
                                if (alert) {
                                    alert.style.transition = 'opacity 0.3s ease';
                                    alert.style.opacity = '0';
                                    setTimeout(() => alert.remove(), 300);
                                }
                            }, 5000);
                        </script>
                        <?php endif; ?>

                        <div class="sa-card">
                            <div class="sa-card-body">
                                <form method="POST" action="edit_sales_agent.php?id=<?= $agent_id ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="name">Name *</label>
                                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($agent['name']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email (Read-only)</label>
                                                <input type="email" class="form-control" value="<?= htmlspecialchars($agent['email']) ?>" disabled>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Phone</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($agent['phone'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="province">Province *</label>
                                                <input type="text" class="form-control" id="province" name="province" value="<?= htmlspecialchars($agent['province']) ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="region">Region</label>
                                                <input type="text" class="form-control" id="region" name="region" value="<?= htmlspecialchars($agent['region'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="commission_rate">Commission Rate (%) *</label>
                                                <input type="number" class="form-control" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100" value="<?= $agent['commission_rate'] ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="salary_type">Salary Type *</label>
                                                <select class="form-control" id="salary_type" name="salary_type" required>
                                                    <option value="commission" <?= $agent['salary_type'] == 'commission' ? 'selected' : '' ?>>Commission Only</option>
                                                    <option value="salary" <?= $agent['salary_type'] == 'salary' ? 'selected' : '' ?>>Salary Only</option>
                                                    <option value="hybrid" <?= $agent['salary_type'] == 'hybrid' ? 'selected' : '' ?>>Salary + Commission</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="baseSalaryGroup" style="display: <?= ($agent['salary_type'] === 'salary' || $agent['salary_type'] === 'hybrid') ? 'block' : 'none' ?>;">
                                                <label for="base_salary">Base Salary</label>
                                                <input type="number" class="form-control" id="base_salary" name="base_salary" step="0.01" min="0" value="<?= htmlspecialchars($agent['base_salary'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="status">Status *</label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="active" <?= $agent['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $agent['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    <option value="suspended" <?= $agent['status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($agent['notes'] ?? '') ?></textarea>
                                    </div>

                                    <hr style="margin: 25px 0; border-color: #e0e0e0;">
                                    <h5 style="margin-bottom: 20px; color: #333; font-weight: 600;">Change Password (Optional)</h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="password">New Password</label>
                                                <input type="password" class="form-control" id="password" name="password" placeholder="Leave empty to keep current password" minlength="8">
                                                <small style="color: #999; margin-top: 4px; display: block;">Minimum 8 characters</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="password_confirm">Confirm Password</label>
                                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirm new password" minlength="8">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-top: 25px;">
                                        <button type="submit" class="sa-btn sa-btn-primary">
                                            <i class="feather icon-save"></i> Update Sales Agent
                                        </button>
                                        <a href="manage_sales_agents.php" class="sa-btn sa-btn-ghost">Cancel</a>
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
<script>
document.getElementById('salary_type')?.addEventListener('change', function() {
    const baseSalaryGroup = document.getElementById('baseSalaryGroup');
    if (this.value === 'salary' || this.value === 'hybrid') {
        baseSalaryGroup.style.display = 'block';
        document.getElementById('base_salary').required = true;
    } else {
        baseSalaryGroup.style.display = 'none';
        document.getElementById('base_salary').required = false;
    }
});

// Password validation
const passwordInput = document.getElementById('password');
const passwordConfirmInput = document.getElementById('password_confirm');

if (passwordInput && passwordConfirmInput) {
    const validatePasswordMatch = () => {
        if (passwordInput.value && passwordConfirmInput.value) {
            if (passwordInput.value === passwordConfirmInput.value) {
                passwordConfirmInput.style.borderColor = '#10b981';
                passwordConfirmInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
            } else {
                passwordConfirmInput.style.borderColor = '#ef4444';
                passwordConfirmInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            }
        } else {
            passwordConfirmInput.style.borderColor = '#e0e0e0';
            passwordConfirmInput.style.boxShadow = '';
        }
    };

    passwordConfirmInput.addEventListener('input', validatePasswordMatch);
    passwordInput.addEventListener('input', validatePasswordMatch);
}

// Form submission validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password_confirm').value;

    if (password && password !== passwordConfirm) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
        return false;
    }

    if (password && password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return false;
    }

    if (!password && passwordConfirm) {
        e.preventDefault();
        alert('Please enter a password or leave both fields empty.');
        return false;
    }
});
</script>

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
    margin-bottom: 20px;
}

.sa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.sa-card-body {
    padding: 20px;
}

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    border: none;
    margin-bottom: 1.5rem;
}

.sa-alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.sa-alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.sa-alert-icon {
    flex-shrink: 0;
    font-weight: bold;
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.sa-alert-content {
    flex: 1;
    align-self: center;
}

.sa-alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: inherit;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
}

.sa-alert-close:hover {
    opacity: 0.7;
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

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    padding: 10px 16px;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
}

.sa-btn:hover {
    transform: translateY(-2px);
}

.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.sa-btn-primary:hover {
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.sa-btn-ghost {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #e0e0e0;
}

.sa-btn-ghost:hover {
    background: #e8e8e8;
    border-color: #d0d0d0;
}

/* ─── FORM STYLES ────────────────────────────────────────── */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
}

.form-control:disabled {
    background-color: #f5f5f5;
    color: #999;
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
    
    .form-group .row > div {
        margin-bottom: 12px;
    }
}
</style>
</body>
</html>

