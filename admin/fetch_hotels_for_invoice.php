<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'security.php';
enforce_auth();

include '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'] ?? 0;

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
                     hb.exchange_rate,
                     c.name as client_name
              FROM hotel_bookings hb
              JOIN clients c ON hb.sold_to = c.id
              WHERE hb.tenant_id = ?
              ORDER BY hb.id DESC
              LIMIT 100";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result(); // ✅ correct way

    $tickets = [];
    while ($row = $res->fetch_assoc()) {
        $tickets[] = $row;
    }

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
