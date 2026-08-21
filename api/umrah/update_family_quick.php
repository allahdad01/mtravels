<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../admin/includes/db_security.php');
require_once('../../admin/security.php');
require_once('../../includes/db.php');
enforce_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$userId = $_SESSION['user_id'] ?? 0;
$userIp = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$familyId = intval($_POST['family_id'] ?? 0);
if (!$familyId) {
    echo json_encode(['success' => false, 'message' => 'Family ID is required']);
    exit;
}

$headOfFamily = DbSecurity::validateInput($_POST['head_of_family'] ?? '', 'string', ['maxlength' => 100]);
$contact = DbSecurity::validateInput($_POST['contact'] ?? '', 'string', ['maxlength' => 50]);
$address = DbSecurity::validateInput($_POST['address'] ?? '', 'string', ['maxlength' => 500]);
$tazmin = DbSecurity::validateInput($_POST['tazmin'] ?? '', 'string', ['maxlength' => 50]);

try {
    $pdo->beginTransaction();

    // Get current data for logging
    $check = $pdo->prepare("SELECT * FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $check->execute([$familyId, $tenant_id, $branch_id]);
    $currentData = $check->fetch(PDO::FETCH_ASSOC);
    if (!$currentData) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Family not found']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE families
        SET head_of_family = ?, contact = ?, address = ?, tazmin = ?, updated_at = NOW()
        WHERE family_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$headOfFamily, $contact, $address, $tazmin, $familyId, $tenant_id, $branch_id]);

    // Activity logging
    $oldValues = json_encode([
        'head_of_family' => $currentData['head_of_family'],
        'contact' => $currentData['contact'],
        'address' => $currentData['address'],
        'tazmin' => $currentData['tazmin']
    ]);
    $newValues = json_encode([
        'head_of_family' => $headOfFamily,
        'contact' => $contact,
        'address' => $address,
        'tazmin' => $tazmin
    ]);
    $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, 'update', 'families', ?, ?, ?, NOW(), ?, ?)
    ")->execute([$userId, $userIp, $userAgent, $familyId, $oldValues, $newValues, $tenant_id, $branch_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Family updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error updating family: ' . $e->getMessage()]);
}
