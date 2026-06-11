<?php
require_once '../../admin/security.php';
enforce_auth();

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$data = json_decode(file_get_contents("php://input"), true);

if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
    exit;
}

$client_id = intval($data['id'] ?? 0);
if (!$client_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

try {
    // Check for related client transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM client_transactions WHERE client_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$client_id, $tenant_id, $branch_id]);
    $txnCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($txnCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete: $txnCount client transaction(s) exist. Remove or reassign them first."
        ]);
        exit;
    }

    // Check for related additional payments
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM additional_payments WHERE client_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$client_id, $tenant_id, $branch_id]);
    $apCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($apCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete: $apCount additional payment(s) reference this client. Remove or reassign them first."
        ]);
        exit;
    }

    // Check for related bookings using this client
    $tables = [
        'ticket_bookings' => 'sold_to',
        'hotel_bookings' => 'sold_to',
        'visa_applications' => 'sold_to',
        'umrah_bookings' => 'sold_to'
    ];

    $refs = [];
    foreach ($tables as $table => $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM `$table` WHERE `$column` = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$client_id, $tenant_id, $branch_id]);
        $cnt = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        if ($cnt > 0) {
            $refs[] = "$cnt in $table";
        }
    }

    if (count($refs) > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete: client is referenced in " . implode(', ', $refs) . ". Remove or reassign them first."
        ]);
        exit;
    }

    // Also check refund/reservation tables
    $extra_tables = [
        'refunded_tickets' => 'sold_to',
        'ticket_reservations' => 'sold_to',
        'date_change_tickets' => 'sold_to'
    ];
    foreach ($extra_tables as $table => $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM `$table` WHERE `$column` = ? AND tenant_id = ?");
        $stmt->execute([$client_id, $tenant_id]);
        $cnt = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        if ($cnt > 0) {
            $refs[] = "$cnt in $table";
        }
    }

    if (count($refs) > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete: client is referenced in " . implode(', ', $refs) . ". Remove or reassign them first."
        ]);
        exit;
    }

    // Proceed with deletion
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$client_id, $tenant_id, $branch_id]);

    // Log activity
    $old_values = json_encode(['client_id' => $client_id]);
    $new_values = json_encode([]);

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'clients', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->execute([$user_id, $client_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
