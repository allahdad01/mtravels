<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

enforce_auth();
umrah_require('member_create');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

require_once '../../includes/db.php';

$group_id = isset($_POST['group_id']) ? DbSecurity::validateInput($_POST['group_id'], 'int', ['min' => 1]) : null;

if (empty($group_id)) {
    echo json_encode(['success' => false, 'message' => 'Group id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
    $stmt->execute([$group_id, $tenant_id, $branch_id]);

    echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}