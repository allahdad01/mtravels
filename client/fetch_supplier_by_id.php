<?php
$conn = new mysqli("localhost", "root", "", "travelagency");


$id = $_GET['id'];

$query = "SELECT * FROM suppliers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'Supplier not found.']);
}
?>
