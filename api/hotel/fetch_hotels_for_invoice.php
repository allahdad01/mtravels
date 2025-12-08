<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../admin/security.php';
enforce_auth();

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'] ?? 0;
$branch_id = $_SESSION['branch_id'] ?? 0;

if (!isset($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to access this resource']);
    exit;
}

try {
    $query = "SELECT hb.id,
                     CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) as guest_name,
                     hb.gender,
                     hb.order_id,
                     hb.check_in_date,
                     hb.check_out_date,
                     hb.accommodation_details,
                     hb.issue_date,
                     hb.supplier_id,
                     hb.contact_no,
                     hb.base_amount,
                     hb.sold_amount,
                     hb.profit,
                     hb.currency,
                     hb.remarks,
                     hb.receipt,
                     c.name as client_name
              FROM hotel_bookings hb
              JOIN clients c ON hb.sold_to = c.id
              WHERE hb.tenant_id = ? AND hb.branch_id = ?
              ORDER BY hb.id DESC
              LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
