<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Query to fetch visa details along with the supplier's name and sold_to name
    $query = "
        SELECT 
            v.*, 
            s1.name AS supplier_name, 
            s2.name AS sold_to_name
        FROM visa_applications v
        LEFT JOIN suppliers s1 ON v.supplier = s1.id
        LEFT JOIN suppliers s2 ON v.sold_to = s2.id
        WHERE v.id = ? AND v.tenant_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $visa = $result->fetch_assoc();
        echo json_encode($visa);
    } else {
        echo json_encode(['error' => 'Visa not found.']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid ID.']);
}

$conn->close();
?>
