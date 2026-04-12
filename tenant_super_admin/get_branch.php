<?php
include '../includes/db.php';
include '../includes/session_check.php';

// Check if user is owner
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'tenant_super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$tenant_id = $_SESSION['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $branch_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as manager_name
            FROM branches b
            LEFT JOIN users u ON b.manager_id = u.id
            WHERE b.id = ? AND b.tenant_id = ?
        ");
        $stmt->execute([$branch_id, $tenant_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($branch) {
            echo json_encode([
                'success' => true,
                'branch' => $branch
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Branch not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>