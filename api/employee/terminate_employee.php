<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => __('unauthorized_access')]));
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => __('invalid_csrf_token')]));
}

// Check if employee_id is provided
if (!isset($_POST['employee_id']) || empty($_POST['employee_id'])) {
    die(json_encode(['success' => false, 'message' => __('employee_id_required')]));
}

$employee_id = intval($_POST['employee_id']);
$action = isset($_POST['action']) ? $_POST['action'] : 'terminate';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get employee details
    $stmt = $pdo->prepare("SELECT name, email, fired FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$employee_id, $tenant_id, $branch_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception(__('employee_not_found'));
    }

    if ($action === 'terminate') {
        // Check if termination reason is provided
        if (!isset($_POST['reason']) || empty(trim($_POST['reason']))) {
            die(json_encode(['success' => false, 'message' => __('termination_reason_required')]));
        }

        $reason = trim($_POST['reason']);

        // Update employee status to fired
        $stmt = $pdo->prepare("
            UPDATE users
            SET fired = 1, fired_at = NOW()
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$employee_id, $tenant_id, $branch_id]);

        // Update salary management status
        $stmt = $pdo->prepare("
            UPDATE salary_management
            SET status = 'inactive'
            WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$employee_id, $tenant_id, $branch_id]);

        // Log the termination
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log (
                user_id, action, table_name, record_id,
                old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
            ) VALUES (
                ?, 'terminate_employee', 'users', ?,
                ?, ?, ?, ?, NOW(), ?, ?
            )
        ");

        $old_values = json_encode(['fired' => $employee['fired']]);
        $new_values = json_encode([
            'fired' => 1,
            'fired_at' => date('Y-m-d H:i:s'),
            'termination_reason' => $reason
        ]);

        $logStmt->execute([
            $_SESSION['user_id'],
            $employee_id,
            $old_values,
            $new_values,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'],
            $tenant_id,
            $branch_id
        ]);

        // Create termination record
        $terminationStmt = $pdo->prepare("
            INSERT INTO employee_terminations (
                employee_id, terminated_by, termination_reason, termination_date, tenant_id, branch_id
            ) VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $terminationStmt->execute([$employee_id, $_SESSION['user_id'], $reason, $tenant_id, $branch_id]);

        $message = __('employee_terminated_successfully', ['name' => $employee['name']]);

    } elseif ($action === 'reinstate') {
        // Reinstate employee
        $stmt = $pdo->prepare("
            UPDATE users
            SET fired = 0, fired_at = NULL
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$employee_id, $tenant_id, $branch_id]);

        // Update salary management status
        $stmt = $pdo->prepare("
            UPDATE salary_management
            SET status = 'active'
            WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$employee_id, $tenant_id, $branch_id]);

        // Log the reinstatement
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log (
                user_id, action, table_name, record_id,
                old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
            ) VALUES (
                ?, 'reinstate_employee', 'users', ?,
                ?, ?, ?, ?, NOW(), ?, ?
            )
        ");

        $old_values = json_encode(['fired' => $employee['fired']]);
        $new_values = json_encode(['fired' => 0, 'fired_at' => null]);

        $logStmt->execute([
            $_SESSION['user_id'],
            $employee_id,
            $old_values,
            $new_values,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'],
            $tenant_id,
            $branch_id
        ]);

        $message = __('employee_reinstated_successfully', ['name' => $employee['name']]);

    } else {
        throw new Exception(__('invalid_action'));
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    // Rollback transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error in terminate_employee.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}