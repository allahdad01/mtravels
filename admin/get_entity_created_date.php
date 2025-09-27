<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$entityId = $_POST['entityId'] ?? '';
$reportType = $_POST['reportType'] ?? '';

if (!$entityId || !$reportType) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$table = '';
$field = 'created_at'; // Assuming all tables have created_at

switch ($reportType) {
    case 'supplier':
        $table = 'suppliers';
        break;
    case 'client':
        $table = 'clients';
        break;
    case 'main_account':
        $table = 'main_account';
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        exit();
}

try {
    $stmt = $pdo->prepare("SELECT $field FROM $table WHERE id = ?");
    $stmt->execute([$entityId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['success' => true, 'created_date' => $result[$field]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Entity not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>