<?php
require_once '../../includes/conn.php';
require_once '../../includes/db.php';
require_once '../security.php';
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enforce authentication
enforce_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
// Debug logging
error_log("Received POST data: " . print_r($_POST, true));

// Validate required fields
if (!isset($_POST['customer_id']) || !isset($_POST['name']) || !isset($_POST['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    error_log("Missing required fields in customer update: " . print_r($_POST, true));
    exit;
}

$customer_id = intval($_POST['customer_id']);
$name = trim($_POST['name']);
$phone = trim($_POST['phone']);
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');

// Debug values
error_log("Processing update for customer ID: $customer_id");
error_log("Name: $name");
error_log("Phone: $phone");
error_log("Email: $email");
error_log("Address: $address");

try {
    // Check database connection
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check if customer exists and is active
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customer_id, $tenant_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        throw new Exception("Customer with ID $customer_id not found");
    }

    // Update customer information
    $updateQuery = "
        UPDATE customers 
        SET name = :name, 
            phone = :phone, 
            email = :email, 
            address = :address,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :customer_id
        AND tenant_id = :tenant_id
    ";
    
    $stmt = $pdo->prepare($updateQuery);
    
    $params = [
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':address' => $address,
        ':customer_id' => $customer_id,
        ':tenant_id' => $tenant_id
    ];
    
    // Debug the query and parameters
    error_log("Update Query: " . $updateQuery);
    error_log("Parameters: " . print_r($params, true));
    
    $result = $stmt->execute($params);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Update failed: " . ($errorInfo[2] ?? 'Unknown error'));
    }

    // Check if any rows were affected
    if ($stmt->rowCount() === 0) {
        throw new Exception("No changes were made to the customer record");
    }

    // Commit transaction
    $pdo->commit();

    // Success response
    echo json_encode([
        'success' => true, 
        'message' => 'Customer updated successfully',
        'customer_id' => $customer_id
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMessage = sprintf(
        "Database error: %s (Code: %s)",
        $e->getMessage(),
        $e->getCode()
    );
    error_log($errorMessage);
    
    // Check for specific error codes
    $message = match ($e->getCode()) {
        '23000' => 'Duplicate entry or constraint violation',
        '42S02' => 'Table not found',
        '42S22' => 'Column not found',
        default => 'A database error occurred'
    };
    
    echo json_encode([
        'success' => false, 
        'message' => $message,
        'debug' => $errorMessage // Only in development
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => $e->getTraceAsString() // Only in development
    ]);
} 