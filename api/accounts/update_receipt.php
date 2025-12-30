<?php
// Include security module
require_once '../../includes/db.php';
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get JSON data from request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['transaction_id']) || !isset($input['transaction_type']) || !isset($input['receipt'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$transaction_id = $input['transaction_id'];
$transaction_type = $input['transaction_type'];
$receipt = trim($input['receipt']);

// Validate receipt is not empty
if (empty($receipt)) {
    echo json_encode(['success' => false, 'message' => 'Receipt number cannot be empty']);
    exit;
}

try {
    // Determine which table to update based on transaction type
    if ($transaction_type === 'main') {
        // Update main account transaction
        $query = "UPDATE main_account_transactions 
                  SET receipt = ? 
                  WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$receipt, $transaction_id, $tenant_id, $branch_id]);
        
        if ($stmt->rowCount() > 0) {
            // Get the account ID and name for response
            $getAccountQuery = "SELECT mat.main_account_id, ma.name 
                               FROM main_account_transactions mat
                               JOIN main_account ma ON mat.main_account_id = ma.id
                               WHERE mat.id = ? AND mat.tenant_id = ? AND mat.branch_id = ?";
            
            $getStmt = $pdo->prepare($getAccountQuery);
            $getStmt->execute([$transaction_id, $tenant_id, $branch_id]);
            $result = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Receipt updated successfully',
                    'account_id' => $result['main_account_id'],
                    'account_name' => $result['name']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Account information not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found or no changes made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
