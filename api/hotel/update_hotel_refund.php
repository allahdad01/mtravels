<?php
require_once('../../includes/db.php');
require_once('../../admin/security.php');
enforce_auth();

header('Content-Type: application/json');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$refund_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$supplier_penalty = isset($_POST['supplier_penalty']) ? floatval($_POST['supplier_penalty']) : 0;
$service_penalty = isset($_POST['service_penalty']) ? floatval($_POST['service_penalty']) : 0;
$refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (!$refund_id) {
    echo json_encode(['success' => false, 'message' => 'Refund ID is required']);
    exit;
}

if ($refund_amount < 0) {
    echo json_encode(['success' => false, 'message' => 'Refund amount cannot be negative']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM hotel_refunds WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$refund_id, $tenant_id, $branch_id]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Refund record not found']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE hotel_refunds SET
        supplier_penalty = ?,
        service_penalty = ?,
        refund_amount = ?,
        reason = ?
        WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$supplier_penalty, $service_penalty, $refund_amount, $reason, $refund_id, $tenant_id, $branch_id]);

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_values = [
        'supplier_penalty' => $original['supplier_penalty'],
        'service_penalty' => $original['service_penalty'],
        'refund_amount' => $original['refund_amount'],
        'reason' => $original['reason']
    ];

    $new_values = [
        'supplier_penalty' => $supplier_penalty,
        'service_penalty' => $service_penalty,
        'refund_amount' => $refund_amount,
        'reason' => $reason
    ];

    $logStmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id)
        VALUES (?, 'update', 'hotel_refunds', ?, ?, ?, ?, ?, ?)");
    $logStmt->execute([
        $user_id,
        $refund_id,
        json_encode($old_values),
        json_encode($new_values),
        $ip_address,
        $user_agent,
        $tenant_id
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Refund updated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error updating refund: ' . $e->getMessage()]);
}
