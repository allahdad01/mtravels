<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['account_id']) || !isset($data['new_status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$accountId = intval($data['account_id']);
$newStatus = $data['new_status'];

// Validate status
if (!in_array($newStatus, ['active', 'inactive'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    // Update the account status
    $stmt = $pdo->prepare("UPDATE main_account SET status = ?, last_updated = NOW() WHERE id = ? AND tenant_id = ?");
    $result = $stmt->execute([$newStatus, $accountId, $tenant_id]);

    if ($result) {
        // Log status change to server logs instead
        $username = isset($_SESSION['name']) ? $_SESSION['name'] : 'User ID: ' . $_SESSION['user_id'];
        error_log("Account status change: User $username changed account ID $accountId to status '$newStatus'");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Account status updated successfully',
            'new_status' => $newStatus
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update account status']);
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 