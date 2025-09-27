<?php
// Include necessary files
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
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

// Check if user_id is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    die(json_encode(['success' => false, 'message' => __('user_id_required')]));
}

try {
    // Prepare the update statement
    $stmt = $pdo->prepare("
        UPDATE users 
        SET fired = NOT fired, 
            fired_at = CASE WHEN fired = FALSE THEN NOW() ELSE NULL END 
        WHERE id = ? AND tenant_id = ?
    ");
   
    // Execute the update
    $stmt->execute([$_POST['id'], $tenant_id]);
    // Fetch the updated user's details for logging
    $userStmt = $pdo->prepare("SELECT name, email, fired FROM users WHERE id = ? AND tenant_id = ?");
    $userStmt->execute([$_POST['id'], $tenant_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Update salary_management status based on fired flag
    $status = $user['fired'] ? 'inactive' : 'active';
    $salaryStmt = $pdo->prepare("UPDATE salary_management SET status = ? WHERE user_id = ? AND tenant_id = ?");
    $salaryStmt->execute([$status, $_POST['id'], $tenant_id]);

    // Log the activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id, action, table_name, record_id, 
            old_values, new_values, ip_address, user_agent, created_at, tenant_id
        ) VALUES (
            ?, ?, 'users', ?, 
            ?, ?, ?, ?, NOW(), ?
        )
    ");

    $logStmt->execute([
        $_SESSION['user_id'], 
        $user['fired'] ? 'fire' : 'reinstate', 
        $_POST['id'],
        json_encode(['fired' => !$user['fired']]),
        json_encode(['fired' => $user['fired']]),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $tenant_id
    ]);

    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => $user['fired'] 
            ? __('user_fired_successfully', ['name' => $user['name']]) 
            : __('user_reinstated_successfully', ['name' => $user['name']]),
        'fired' => $user['fired']
    ]);

} catch (PDOException $e) {
    // Log the error
    error_log("Error firing/reinstating user: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => __('error_processing_request')
    ]);
}