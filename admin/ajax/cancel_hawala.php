<?php
require_once '../../includes/db.php';
require_once '../includes/hawala_handler.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['hawala_id']) || !is_numeric($data['hawala_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Hawala ID']);
    exit();
}

$hawala_id = intval($data['hawala_id']);

try {
    $result = cancelHawalaTransfer($pdo, $hawala_id, $tenant_id, $branch_id);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling Hawala transfer: ' . $e->getMessage()
    ]);
} 