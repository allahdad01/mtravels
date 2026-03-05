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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_sales_agents.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to delete_sales_agent.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$agent_id = intval($_POST['agent_id'] ?? 0);

// Fetch sales agent to verify it exists
$stmt = $pdo->prepare("SELECT id, user_id, name, email FROM sales_agents WHERE id = ?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: manage_sales_agents.php?error=agent_not_found');
    exit();
}

try {
    $pdo->beginTransaction();

    // Delete the sales agent record (will cascade to sales_agent_tenants)
    $stmt = $pdo->prepare("DELETE FROM sales_agents WHERE id = ?");
    $stmt->execute([$agent_id]);

    // Delete the associated user account
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'sales_agent'");
    $stmt->execute([$agent['user_id']]);

    // Log action
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                            VALUES (?, 'delete_sales_agent', 'sales_agent', ?, ?, ?, NOW())");
    $details = json_encode([
        'name' => $agent['name'],
        'email' => $agent['email'],
        'deleted_by' => $_SESSION['user_id']
    ]);
    $stmt->execute([$_SESSION['user_id'], $agent_id, $details, $_SERVER['REMOTE_ADDR']]);

    $pdo->commit();

    error_log("SALES_AGENT_DELETED: Admin {$_SESSION['user_id']} deleted sales agent {$agent_id} ({$agent['email']})");
    header('Location: manage_sales_agents.php?success=agent_deleted');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting sales agent: " . $e->getMessage());
    header('Location: manage_sales_agents.php?error=database_error');
}
exit();
?>
