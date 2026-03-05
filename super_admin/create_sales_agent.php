<?php
session_start();
require_once '../includes/db.php';
require_once 'includes/role_security.php';

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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_sales_agents.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to create_sales_agent.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$province = $_POST['province'] ?? '';
$region = $_POST['region'] ?? '';
$password = $_POST['password'] ?? '';
$commission_rate = $_POST['commission_rate'] ?? 10.00;
$salary_type = $_POST['salary_type'] ?? 'commission';
$base_salary = $_POST['base_salary'] ?? null;
$errors = [];

// Validate input
if (empty($name)) {
    $errors[] = "Name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email is required.";
}
if (empty($province)) {
    $errors[] = "Province is required.";
}
if (empty($password) || strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
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

// Check if email exists in users table
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()['count'] > 0) {
    $errors[] = "Email already exists in system.";
}

// Check if email exists in sales_agents table
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_agents WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()['count'] > 0) {
    $errors[] = "Email already exists as sales agent.";
}

if (empty($errors)) {
    try {
        $pdo->beginTransaction();

        // Create user account for sales agent with role 'sales_agent'
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, tenant_id, created_at, updated_at) 
                                VALUES (?, ?, ?, 'sales_agent', NULL, NOW(), NOW())");
        $stmt->execute([$name, $email, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Create sales agent record
        $stmt = $pdo->prepare("INSERT INTO sales_agents 
                                (name, email, phone, province, region, user_id, commission_rate, salary_type, base_salary, status, created_by, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())");
        $stmt->execute([
            $name,
            $email,
            $phone ?: null,
            $province,
            $region ?: null,
            $user_id,
            $commission_rate,
            $salary_type,
            $base_salary ?: null,
            $_SESSION['user_id']
        ]);
        $agent_id = $pdo->lastInsertId();

        // Log action
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'create_sales_agent', 'sales_agent', ?, ?, ?, NOW())");
        $details = json_encode([
            'name' => $name,
            'email' => $email,
            'province' => $province,
            'region' => $region,
            'commission_rate' => $commission_rate,
            'salary_type' => $salary_type,
            'created_by' => $_SESSION['user_id']
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$_SESSION['user_id'], $agent_id, $details, $ip_address]);

        $pdo->commit();

        // Send credentials email to sales agent
        require_once '../includes/functions.php';
        sendSalesAgentCredentialsEmail($email, $name, $password);

        error_log("SALES_AGENT_CREATED: Admin {$_SESSION['user_id']} created sales agent {$agent_id} ({$email}) for province {$province}");
        header('Location: manage_sales_agents.php?success=agent_created');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating sales agent: " . $e->getMessage());
        header('Location: manage_sales_agents.php?error=database_error');
    }
} else {
    header('Location: manage_sales_agents.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>
