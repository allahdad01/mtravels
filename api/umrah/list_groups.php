<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
require_once '../../includes/db.php';

enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

header('Content-Type: application/json');

$stmt = $pdo->prepare("
    SELECT group_id, group_number, group_name, created_at
    FROM umrah_groups
    WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0)
    ORDER BY CAST(group_number AS UNSIGNED) ASC, group_number ASC
");
$stmt->execute([$tenant_id, $branch_id]);

echo json_encode(['success' => true, 'groups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);