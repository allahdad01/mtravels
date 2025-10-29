<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/conn.php';

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get client ID and currency from request
$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$currency = isset($_GET['currency']) ? $_GET['currency'] : '';

if (!$clientId || empty($currency)) {
    die(json_encode(['success' => false, 'message' => 'Missing client ID or currency']));
}

// Get the client balance based on currency
$balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';

$query = "SELECT name, client_type, $balanceField AS balance FROM clients WHERE id = ? AND tenant_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $clientId, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Only return balance if client is Regular
    $isRegular = strtolower($row['client_type']) === 'regular';
    
    echo json_encode([
        'success' => true, 
        'balance' => $isRegular ? $row['balance'] : 0,
        'client_name' => $row['name'],
        'client_type' => $row['client_type'],
        'is_regular' => $isRegular
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
}

$stmt->close();
$conn->close();
?>