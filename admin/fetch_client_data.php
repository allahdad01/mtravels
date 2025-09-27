<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require '../vendor/autoload.php';
require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$clientId = intval($_GET['clientId']);
$type = $_GET['type'] ?? 'both';

$data = ['success' => true, 'tickets' => [], 'visas' => []];

if ($type === 'ticket' || $type === 'both') {
    $ticketQuery = "SELECT id, CONCAT(description, ' (', sold, ' ', currency, ')') as description, sold, currency FROM ticket_bookings WHERE sold_to = ? AND tenant_id = ?";
    $stmt = $conn->prepare($ticketQuery);
    $stmt->bind_param("ii", $clientId, $tenant_id);
    $stmt->execute();
    $data['tickets'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($type === 'visa' || $type === 'both') {
    $visaQuery = "SELECT id, CONCAT(remarks, ' (', sold, ' ', currency, ')') as description, sold, currency FROM visa_applications WHERE sold_to = ? AND tenant_id = ?";
    $stmt = $conn->prepare($visaQuery);
    $stmt->bind_param("ii", $clientId, $tenant_id);
    $stmt->execute();
    $data['visas'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

echo json_encode($data);
?>
