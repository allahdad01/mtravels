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

            header('Location: manage_sales_agents.php?success=Sales+agent+updated+successfully');
        } catch (Exception $e) {
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

<style>
:root {
    --primary: #4099ff;
    --primary-dark: #2673cc;
    --primary-light: #73b4ff;
    --primary-glow: rgba(64,153,255,0.2);
    --secondary: #2ed8b6;
    --secondary-glow: rgba(46,216,182,0.2);
    --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --accent: #2ed8b6;
    --bg: #f0f8ff;
    --surface: #ffffff;
    --surface2: #f3f8ff;
    --text: #1a2332;
    --muted: #6b7280;
    --border: #e2e8f0;
    --radius: 10px;
    --green: #10b981;
    --red: #ef4444;
    --amber: #f59e0b;
    --blue: #3b82f6;
}
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2673cc 50%, #2ed8b6 100%) !important;
    color: #fff;
    border: none !important;
    margin-bottom: 24px;
    padding: 22px 28px !important;
    box-shadow: 0 4px 20px rgba(64,153,255,0.3);
    border-radius: 12px;
    position: relative;
    overflow: hidden;
}
.page-header.card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 {
    color: #fff !important;
    margin: 0;
    font-weight: 700;
    font-size: 1.15rem;
    position: relative;
    z-index: 1;
}
.page-header.card .btn {
    background: rgba(255,255,255,0.12) !important;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.25) !important;
    border-radius: 8px;
    padding: 7px 16px;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(4px);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}
.page-header.card .btn:hover {
    background: rgba(255,255,255,0.2) !important;
    border-color: rgba(255,255,255,0.4) !important;
    transform: translateY(-1px);
}
.page-header.card .row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    position: relative;
    z-index: 2;
}
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }
.sa-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    margin-bottom: 16px;
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.sa-alert-icon { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sa-alert-icon svg { width: 20px; height: 20px; }
.sa-alert-content { flex: 1; font-size: 0.85rem; }
.sa-alert-close { background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; transition: color 0.2s; flex-shrink: 0; display: flex; }
.sa-alert-close:hover { color: var(--text); }
.sa-alert-success { background: #d1fae5; border-color: var(--green); color: #065f46; }
.sa-alert-success .sa-alert-icon svg { color: var(--green); }
.sa-alert-danger { background: #fee2e2; border-color: var(--red); color: #7f1d1d; }
.sa-alert-danger .sa-alert-icon svg { color: var(--red); }
.sa-section-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-bottom: 20px;
}
.sa-section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
}
.sa-agent-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 4px;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.sa-agent-email {
    font-size: 0.85rem;
    color: var(--muted);
    margin: 0 0 2px;
}
.sa-agent-location {
    font-size: 0.82rem;
    color: var(--muted);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}
.sa-section-body { padding: 24px; }
.sa-form-section { margin-bottom: 24px; }
.sa-form-section:last-child { margin-bottom: 0; }
.sa-form-section-title {
    font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--primary-dark); margin: 0 0 16px 0; padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 6px;
}
.sa-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
@media (max-width: 768px) { .sa-form-grid-2 { grid-template-columns: 1fr; } }
.sa-form-grid-1 { margin-bottom: 16px; }
.sa-form-group { display: flex; flex-direction: column; }
.sa-form-label { font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text); display: flex; align-items: center; gap: 4px; }
.sa-required { color: var(--red); font-weight: 700; }
.sa-form-input { padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); font-size: 0.95rem; transition: all 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.sa-form-input::placeholder { color: var(--muted); }
.sa-form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.sa-form-input:disabled { background: var(--surface2); color: var(--muted); cursor: not-allowed; }
.sa-form-select {
    appearance: none; cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234099ff' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px;
}
.sa-form-textarea { resize: vertical; min-height: 80px; }
.sa-form-hint { font-size: 0.75rem; color: var(--muted); margin-top: 4px; }
.sa-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 0.82rem; font-weight: 600; transition: all 0.2s;
    text-decoration: none; white-space: nowrap;
}
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 14px var(--primary-glow); }
.sa-btn-ghost { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
.sa-btn-ghost:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.pill {
    font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 4px;
}
.pill-green { background: rgba(34,211,160,0.12); color: var(--green); }
.pill-red { background: rgba(244,63,94,0.12); color: var(--red); }
.pill-gray { background: rgba(107,114,128,0.12); color: var(--muted); }
.sa-password-divider {
    margin: 20px 0;
    border: none;
    border-top: 1px solid var(--border);
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">

                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit Sales Agent
                                        </h5>
                                        <p class="page-desc">Update sales agent information</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="manage_sales_agents.php" class="btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back to List
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($_GET['error'])): ?>
                            <div class="sa-alert sa-alert-danger" id="errorAlert">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <div class="sa-alert-content"><strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?></div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.remove();"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <script>document.getElementById('errorAlert')?.scrollIntoView({ behavior: 'smooth', block: 'start' });</script>
                            <?php endif; ?>

                            <?php if (!empty($_GET['success'])): ?>
                            <div class="sa-alert sa-alert-success" id="successAlert">
                                <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                                <div class="sa-alert-content"><strong>Success:</strong> <?= htmlspecialchars($_GET['success']) ?></div>
                                <button type="button" class="sa-alert-close" onclick="this.parentElement.remove();"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <script>
                                document.getElementById('successAlert')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                setTimeout(() => { const a = document.getElementById('successAlert'); if(a) { a.style.transition = 'opacity 0.3s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 300); } }, 5000);
                            </script>
                            <?php endif; ?>

                            <div class="sa-section-card">
                                <div class="sa-section-header">
                                    <div class="sa-agent-info">
                                        <h4>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            <?= htmlspecialchars($agent['name']) ?>
                                        </h4>
                                        <p class="sa-agent-email"><?= htmlspecialchars($agent['email']) ?></p>
                                        <p class="sa-agent-location">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <?= htmlspecialchars($agent['province']) ?>
                                            <?= !empty($agent['region']) ? ' • ' . htmlspecialchars($agent['region']) : '' ?>
                                        </p>
                                    </div>
                                    <span class="pill <?= $agent['status'] === 'active' ? 'pill-green' : ($agent['status'] === 'inactive' ? 'pill-gray' : 'pill-red') ?>">
                                        <?= htmlspecialchars(ucfirst($agent['status'])) ?>
                                    </span>
                                </div>
                                <div class="sa-section-body">
                                    <form method="POST" action="edit_sales_agent.php?id=<?= $agent_id ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                        <div class="sa-form-section">
                                            <h6 class="sa-form-section-title">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                Personal Information
                                            </h6>
                                            <div class="sa-form-grid-2">
                                                <div class="sa-form-group">
                                                    <label for="name" class="sa-form-label">Name <span class="sa-required">*</span></label>
                                                    <input type="text" class="sa-form-input" id="name" name="name" value="<?= htmlspecialchars($agent['name']) ?>" required>
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="email" class="sa-form-label">Email (Read-only)</label>
                                                    <input type="email" class="sa-form-input" value="<?= htmlspecialchars($agent['email']) ?>" disabled>
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="phone" class="sa-form-label">Phone</label>
                                                    <input type="tel" class="sa-form-input" id="phone" name="phone" value="<?= htmlspecialchars($agent['phone'] ?? '') ?>">
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="province" class="sa-form-label">Province <span class="sa-required">*</span></label>
                                                    <input type="text" class="sa-form-input" id="province" name="province" value="<?= htmlspecialchars($agent['province']) ?>" required>
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="region" class="sa-form-label">Region</label>
                                                    <input type="text" class="sa-form-input" id="region" name="region" value="<?= htmlspecialchars($agent['region'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sa-form-section">
                                            <h6 class="sa-form-section-title">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                                Commission & Salary
                                            </h6>
                                            <div class="sa-form-grid-2">
                                                <div class="sa-form-group">
                                                    <label for="commission_rate" class="sa-form-label">Commission Rate (%) <span class="sa-required">*</span></label>
                                                    <input type="number" class="sa-form-input" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100" value="<?= $agent['commission_rate'] ?>" required>
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="salary_type" class="sa-form-label">Salary Type <span class="sa-required">*</span></label>
                                                    <select class="sa-form-input sa-form-select" id="salary_type" name="salary_type" required>
                                                        <option value="commission" <?= $agent['salary_type'] == 'commission' ? 'selected' : '' ?>>Commission Only</option>
                                                        <option value="salary" <?= $agent['salary_type'] == 'salary' ? 'selected' : '' ?>>Salary Only</option>
                                                        <option value="hybrid" <?= $agent['salary_type'] == 'hybrid' ? 'selected' : '' ?>>Salary + Commission</option>
                                                    </select>
                                                </div>
                                                <div class="sa-form-group" id="baseSalaryGroup" style="display: <?= ($agent['salary_type'] === 'salary' || $agent['salary_type'] === 'hybrid') ? 'block' : 'none' ?>;">
                                                    <label for="base_salary" class="sa-form-label">Base Salary</label>
                                                    <input type="number" class="sa-form-input" id="base_salary" name="base_salary" step="0.01" min="0" value="<?= htmlspecialchars($agent['base_salary'] ?? '') ?>">
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="status" class="sa-form-label">Status <span class="sa-required">*</span></label>
                                                    <select class="sa-form-input sa-form-select" id="status" name="status" required>
                                                        <option value="active" <?= $agent['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $agent['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        <option value="suspended" <?= $agent['status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sa-form-section">
                                            <h6 class="sa-form-section-title">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                                Notes
                                            </h6>
                                            <div class="sa-form-grid-1">
                                                <div class="sa-form-group">
                                                    <label for="notes" class="sa-form-label">Internal Notes</label>
                                                    <textarea class="sa-form-input sa-form-textarea" id="notes" name="notes" rows="3"><?= htmlspecialchars($agent['notes'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="sa-password-divider">

                                        <div class="sa-form-section">
                                            <h6 class="sa-form-section-title">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                Change Password (Optional)
                                            </h6>
                                            <div class="sa-form-grid-2">
                                                <div class="sa-form-group">
                                                    <label for="password" class="sa-form-label">New Password</label>
                                                    <input type="password" class="sa-form-input" id="password" name="password" placeholder="Leave empty to keep current" minlength="8">
                                                    <span class="sa-form-hint">Minimum 8 characters</span>
                                                </div>
                                                <div class="sa-form-group">
                                                    <label for="password_confirm" class="sa-form-label">Confirm Password</label>
                                                    <input type="password" class="sa-form-input" id="password_confirm" name="password_confirm" placeholder="Confirm new password" minlength="8">
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display:flex;gap:12px;padding-top:16px;border-top:1px solid var(--border);margin-top:8px;">
                                            <button type="submit" class="sa-btn sa-btn-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                                Update Sales Agent
                                            </button>
                                            <a href="manage_sales_agents.php" class="sa-btn sa-btn-ghost">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </div>

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

</body>
</html>

