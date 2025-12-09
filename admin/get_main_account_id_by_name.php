<?php
// Include database connection
require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
require_once 'security.php';
enforce_auth();

// Set JSON header
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['name']) || empty($_GET['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid main account name']);
    exit;
}

try {
    // Prepare and execute query to find main account ID by name
    $stmt = $pdo->prepare("SELECT id FROM main_account WHERE name = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
    $stmt->bindParam(1, $_GET['name'], PDO::PARAM_STR);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if (count($result) > 0) {
        $row = $result[0];
        // Main account found
        echo json_encode([
            'success' => true, 
            'main_account_id' => $row['id']
        ]);
    } else {
        // No main account found
        echo json_encode([
            'success' => false, 
            'message' => 'Main account not found',
            'main_account_id' => ''
        ]);
    }
} catch (Exception $e) {
    // Error handling
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching main account ID: ' . $e->getMessage(),
        'main_account_id' => ''
    ]);
}
exit;
?> 