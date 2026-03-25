<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../includes/CsrfProtection.php');

// Get current CSRF token
$csrf_token = CsrfProtection::getToken();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'csrf_token' => $csrf_token
]);
