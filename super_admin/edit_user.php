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
    error_log("Unauthorized access attempt to edit_user.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user details
$edit_user_id = $_GET['id'] ?? '';
$errors = [];
$success = [];
$edit_user = null;

if ($edit_user_id && is_numeric($edit_user_id)) {
    $stmt = $pdo->prepare("SELECT id, name, email, role, tenant_id FROM users WHERE id = ?");
    $stmt->execute([$edit_user_id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        $errors[] = "User not found.";
    }
} else {
    $errors[] = "Invalid user ID.";
}

// Fetch tenants for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$tenants = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_users.php?error=invalid_csrf');
        exit();
    }

    // Verify user exists
    if (!$edit_user) {
        $errors[] = "User not found.";
    }

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $tenant_id = trim($_POST['tenant_id'] ?? '');

    // Validate input
    if (empty($name) || empty($email) || empty($role)) {
        $errors[] = "Name, email, and role are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($password && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!in_array($role, ['super_admin', 'tenant_super_admin', 'admin'])) {
        $errors[] = "Invalid role.";
    }
    if ($role !== 'super_admin' && empty($tenant_id)) {
        $errors[] = "Tenant is required for non-super admin roles.";
    }
    if ($role === 'super_admin' && !empty($tenant_id)) {
        $errors[] = "Super admins cannot be assigned to a tenant.";
    }

    // Check for duplicate email (excluding current user) - only if email changed
    if (!empty($edit_user)) {
    $original_email = strtolower($edit_user['email']);
    if ($email !== $original_email) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE LOWER(email) = ? AND id != ?");
        $stmt->execute([$email, intval($edit_user_id)]);
        $result = $stmt->fetch();
        if ($result && $result['count'] > 0) {
            $errors[] = "Email already exists for another user.";
        }
    }
    }

    // Verify tenant exists (if applicable)
    if ($tenant_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE id = ? AND status != 'deleted'");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch();
        if ($result && $result['count'] == 0) {
            $errors[] = "Invalid or deleted tenant.";
        }
    }

    if (empty($errors)) {
        // Update user
        $tenant_id = $tenant_id ?: null;
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, tenant_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $email, $hashed_password, $role, $tenant_id, intval($edit_user_id)]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, tenant_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $email, $role, $tenant_id, intval($edit_user_id)]);
        }
        
        // Log action
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                                VALUES (?, 'update_user', 'user', ?, ?, ?, NOW())");
        $details = json_encode(['name' => $name, 'email' => $email, 'role' => $role, 'tenant_id' => $tenant_id, 'password_updated' => !!$password]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$_SESSION['user_id'], intval($edit_user_id), $details, $ip_address]);
        
        header('Location: manage_users.php?success=user_updated');
        exit();
    }
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─────────────────────────────────────────────── */
:root {
  --bg:       #f8fafc;
  --surface:  #ffffff;
  --surface2: #f1f5f9;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --accent:   #4099ff;
  --accent2:  #2ed8b6;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --blue:     #3b82f6;
  --purple:   #8b5cf6;
  --radius:   14px;
}

/* ─── RESET / BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────── */
.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { 
    padding: 24px 28px; 
    display: flex; 
    flex-direction: column; 
    gap: 24px; 
}

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius); 
  overflow: hidden;
  transition: all .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sa-card:hover { 
    border-left-color: var(--accent2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sa-card-hdr {
  padding: 16px 24px; 
  border-bottom: 1px solid var(--border);
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(46,216,182,0.02));
}
.sa-card-hdr h3 { 
    font-size: .95rem; 
    font-weight: 600; 
    color: var(--text);
    display: flex;
    align-items: center;
    letter-spacing: -0.01em;
}
.sa-card-body { 
    padding: 24px; 
}

/* ─── BUTTON ─────────────────────────────────────────────── */
.sa-btn {
  font-size: .8rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 8px 16px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: var(--accent); }

/* ─── FORM STYLES ────────────────────────────────────────── */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    position: relative;
    display: flex;
    flex-direction: column;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all .15s ease;
    background: var(--surface2);
    color: var(--text);
    font-family: 'Sora', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
}

/* ─── ALERT STYLES ───────────────────────────────────────── */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert i {
    font-size: 0.9rem;
}

/* ─── BUTTON GROUP ───────────────────────────────────────── */
.button-group {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    border-top: 1px solid var(--border);
    padding-top: 20px;
}

.button-group .sa-btn {
    flex: 1;
    justify-content: center;
}
</style>

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
                                    <h5 class="m-b-10">
                                        <i class="feather icon-edit-2" style="margin-right:8px"></i>
                                        Edit User - <?= htmlspecialchars($edit_user['name'] ?? 'Loading...') ?>
                                    </h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="manage_users.php"><?= __('users') ?></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('edit') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="sa-wrap">
                    <div class="sa-content">
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="feather icon-alert-circle"></i>
                            <div>
                                <?php foreach ($errors as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Edit User Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3>
                                    <i class="feather icon-user" style="margin-right:8px"></i>
                                    User Information
                                </h3>
                            </div>
                            <div class="sa-card-body">
                                <form method="POST" action="edit_user.php?id=<?= htmlspecialchars($edit_user_id) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="name"><?= __('name') ?></label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?= htmlspecialchars($edit_user['name'] ?? '') ?>" required placeholder="Full name">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="email"><?= __('email') ?></label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" required placeholder="user@example.com">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="role"><?= __('role') ?></label>
                                            <select class="form-control" id="role" name="role" required>
                                                <option value="">Select a role</option>
                                                <option value="super_admin" <?= ($edit_user['role'] ?? '') == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                <option value="tenant_super_admin" <?= ($edit_user['role'] ?? '') == 'tenant_super_admin' ? 'selected' : '' ?>>Tenant Super Admin</option>
                                                <option value="admin" <?= ($edit_user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Tenant Admin</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="tenant_id"><?= __('tenant') ?></label>
                                            <select class="form-control" id="tenant_id" name="tenant_id">
                                                <option value="">None (Super Admin)</option>
                                                <?php foreach ($tenants as $tenant): ?>
                                                <option value="<?= htmlspecialchars($tenant['id']) ?>" <?= ($edit_user['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($tenant['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="password"><?= __('password') ?></label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Leave blank to keep unchanged">
                                        </div>
                                    </div>

                                    <div class="button-group">
                                        <a href="manage_users.php" class="sa-btn sa-btn-ghost">
                                            <i class="feather icon-x"></i>
                                            Cancel
                                        </a>
                                        <button type="submit" class="sa-btn sa-btn-primary">
                                            <i class="feather icon-save"></i>
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div><!-- /sa-content -->
                </div><!-- /sa-wrap -->
            </div><!-- /.pcoded-inner-content -->
        </div><!-- /.pcoded-content -->
    </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
</script>
</body>
</html>
