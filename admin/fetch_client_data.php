<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require '../vendor/autoload.php';
require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$clientId = intval($_GET['clientId']);
$type = $_GET['type'] ?? 'both';

$data = ['success' => true, 'tickets' => [], 'visas' => []];

if ($type === 'ticket' || $type === 'both') {
    $ticketQuery = "SELECT id, CONCAT(description, ' (', sold, ' ', currency, ')') as description, sold, currency FROM ticket_bookings WHERE sold_to = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($ticketQuery);
    $stmt->bindParam(1, $clientId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $data['tickets'] = $stmt->fetchAll();
}

if ($type === 'visa' || $type === 'both') {
    $visaQuery = "SELECT id, CONCAT(remarks, ' (', sold, ' ', currency, ')') as description, sold, currency FROM visa_applications WHERE sold_to = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($visaQuery);
    $stmt->bindParam(1, $clientId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $data['visas'] = $stmt->fetchAll();
}

echo json_encode($data);
?>
