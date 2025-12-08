<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Fetch hotel bookings
$sql = "SELECT
            hb.id,
            hb.title,
            hb.first_name,
            hb.last_name,
            hb.gender,
            hb.order_id,
            hb.check_in_date,
            hb.check_out_date,
            hb.accommodation_details,
            hb.issue_date,
            s.name AS supplier_name,
            ma.name AS paid_to_name,
            cl.name AS sold_to_name,
            hb.contact_no,
            hb.base_amount,
            hb.sold_amount,
            hb.profit,
            hb.currency,
            hb.remarks,
            hb.receipt
        FROM hotel_bookings hb
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        LEFT JOIN main_account ma ON hb.paid_to = ma.id
        LEFT JOIN clients cl ON hb.sold_to = cl.id
        WHERE hb.tenant_id = ? AND hb.branch_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($result && count($result) > 0) {
    // Return the data as JSON
    echo json_encode(["success" => true, "data" => $result]);
} else {
    echo json_encode(["success" => false, "message" => "No hotel bookings found."]);
}
?>
