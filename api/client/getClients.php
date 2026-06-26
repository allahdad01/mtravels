<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication before reading session
enforce_auth();

require_once '../../includes/db.php';

header('Content-Type: application/json');

$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

if (!$tenant_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session. Please log in again.']);
    exit;
}

$branchSql = $branch_id > 0
    ? 'c.tenant_id = ? AND (c.branch_id = ? OR c.branch_id IS NULL)'
    : 'c.tenant_id = ? AND c.branch_id IS NULL';
$branchParams = $branch_id > 0 ? [$tenant_id, $branch_id] : [$tenant_id];

try {
    $stmt = $pdo->prepare("
        SELECT c.*,
            (
                SELECT COUNT(*) FROM client_transactions ct
                WHERE ct.client_id = c.id AND ct.tenant_id = c.tenant_id AND ct.branch_id = c.branch_id
            ) > 0 AS has_transactions,
            (
                SELECT COUNT(*) FROM client_transactions ct
                WHERE ct.client_id = c.id AND ct.tenant_id = c.tenant_id AND ct.branch_id = c.branch_id
            ) +
            (
                SELECT COUNT(*) FROM additional_payments ap
                WHERE ap.client_id = c.id AND ap.tenant_id = c.tenant_id AND ap.branch_id = c.branch_id
            ) +
            (
                SELECT COUNT(*) FROM ticket_bookings tb
                WHERE tb.sold_to = c.id AND tb.tenant_id = c.tenant_id AND tb.branch_id = c.branch_id
            ) +
            (
                SELECT COUNT(*) FROM hotel_bookings hb
                WHERE hb.sold_to = c.id AND hb.tenant_id = c.tenant_id AND hb.branch_id = c.branch_id
            ) +
            (
                SELECT COUNT(*) FROM visa_applications va
                WHERE va.sold_to = c.id AND va.tenant_id = c.tenant_id AND va.branch_id = c.branch_id
            ) +
            (
                SELECT COUNT(*) FROM umrah_bookings ub
                WHERE ub.sold_to = c.id AND ub.tenant_id = c.tenant_id AND ub.branch_id = c.branch_id
            ) +
            (
                SELECT COUNT(*) FROM refunded_tickets rt
                WHERE rt.sold_to = c.id AND rt.tenant_id = c.tenant_id
            ) +
            (
                SELECT COUNT(*) FROM ticket_reservations tr
                WHERE tr.sold_to = c.id AND tr.tenant_id = c.tenant_id
            ) +
            (
                SELECT COUNT(*) FROM date_change_tickets dct
                WHERE dct.sold_to = c.id AND dct.tenant_id = c.tenant_id
            ) = 0 AS can_delete
        FROM clients c
        WHERE {$branchSql}
        ORDER BY c.name
    ");
    $stmt->execute($branchParams);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($clients);
} catch (PDOException $e) {
    echo json_encode([]);
}
