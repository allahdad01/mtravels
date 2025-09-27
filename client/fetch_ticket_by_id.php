<?php
$conn = new mysqli("localhost", "root", "", "travelagency");

$id = $_GET['id'];

// Query to fetch ticket details along with the supplier's name
$query = "
    SELECT t.*, s.name AS supplier_name 
    FROM ticket_bookings t 
    LEFT JOIN suppliers s ON t.supplier = s.id 
    WHERE t.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Fetch ticket data and supplier name
    $ticket = $result->fetch_assoc();
    // Return the ticket data along with the supplier's name
    echo json_encode($ticket);
} else {
    echo json_encode(['error' => 'Ticket not found.']);
}
?>

