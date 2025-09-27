<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "travelagency");

// Check connection
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Database connection failed"]));
}

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
        LEFT JOIN clients cl ON hb.sold_to = cl.id";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    // Return the data as JSON
    echo json_encode(["success" => true, "data" => $bookings]);
} else {
    echo json_encode(["success" => false, "message" => "No hotel bookings found."]);
}

$conn->close();
?>
