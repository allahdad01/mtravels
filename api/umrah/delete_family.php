<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('umrah.member_create');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$family_id = intval($data['family_id'] ?? 0);

if ($family_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Family ID is required.']);
    exit;
}

try {
    // Check family exists
    $stmt = $pdo->prepare("SELECT family_id FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$family_id, $tenant_id, $branch_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Family not found.']);
        exit;
    }

    // Check if family has members
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$family_id, $tenant_id, $branch_id]);
    $memberCount = (int) $stmt->fetchColumn();

    if ($memberCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This family has ' . $memberCount . ' member(s). Please delete all members first before deleting the family.'
        ]);
        exit;
    }

    // Delete the family
    $stmt = $pdo->prepare("DELETE FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$family_id, $tenant_id, $branch_id]);

    // Log activity
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $pdo->prepare("
        INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'families', ?, ?, '{}', ?, ?, NOW(), ?, ?)
    ")->execute([$user_id, $family_id, json_encode(['family_id' => $family_id]), $ip, $ua, $tenant_id, $branch_id]);

    echo json_encode(['success' => true, 'message' => 'Family deleted successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting family.']);
}
