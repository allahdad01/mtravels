<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

enforce_auth();
require_permission('umrah.member_create');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

require_once '../../includes/db.php';

$group_number = isset($_POST['group_number']) ? DbSecurity::validateInput($_POST['group_number'], 'string', ['maxlength' => 50]) : null;
$group_name = isset($_POST['group_name']) ? DbSecurity::validateInput($_POST['group_name'], 'string', ['maxlength' => 255]) : null;

if (empty($group_number) || empty($group_name)) {
    echo json_encode(['success' => false, 'message' => 'Group number and name are required']);
    exit;
}

try {
    $dup = $pdo->prepare("SELECT COUNT(*) FROM umrah_groups WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0) AND group_number = ?");
    $dup->execute([$tenant_id, $branch_id, $group_number]);
    if ((int)$dup->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Group number already exists']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO umrah_groups (group_number, group_name, tenant_id, branch_id, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$group_number, $group_name, $tenant_id, $branch_id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Group created successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}