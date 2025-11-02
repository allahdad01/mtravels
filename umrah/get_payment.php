<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];

// Enforce authentication
enforce_auth();

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/conn.php';

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $stmt = $conn->prepare("SELECT ap.*, ma.name as main_account_name 
                           FROM additional_payments ap 
                           LEFT JOIN main_account ma ON ap.main_account_id = ma.id 
                           WHERE ap.id = ? AND ap.tenant_id = ?");
    $stmt->bind_param("si", $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($payment = $result->fetch_assoc()) {
        echo json_encode($payment);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Payment not found']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID not provided']);
}
?> 