<?php
// update_client.php
header('Content-Type: application/json');

// Start session to access $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../../admin/security.php';

    // Get the input data
    $data = json_decode(file_get_contents("php://input"), true);

    // ✅ CSRF Token Validation
    if (!verify_csrf_token($data['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
        exit;
    }

    // Access tenant_id and branch_id from session
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    $branch_id = $_SESSION['branch_id'] ?? null;
    if (!$tenant_id) {
        echo json_encode(['success' => false, 'message' => 'Tenant not found in session.']);
        exit;
    }
    if (!$branch_id) {
        echo json_encode(['success' => false, 'message' => 'Branch not found in session.']);
        exit;
    }

    // Extract and validate input
    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = $data['phone'] ?? null; // Nullable
    $client_type = $data['client_type'] ?? '';
    $status = $data['status'] ?? 'active';
    $usd_balance = isset($data['usd_balance']) ? (float)$data['usd_balance'] : null;
    $afs_balance = isset($data['afs_balance']) ? (float)$data['afs_balance'] : null;

    if (!$id || !is_numeric($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
        exit;
    }

    if (empty($name) || empty($client_type)) {
        echo json_encode(['success' => false, 'message' => 'Name and client type are required']);
        exit;
    }

    // Email is optional — store NULL when empty so the unique index is not violated
    if ($email === '') $email = null;

    // Database connection
    include '../../includes/db.php';

    // Check if transactions exist for this client
    $has_transactions = false;
    $txn_check = $pdo->prepare("SELECT COUNT(*) FROM client_transactions WHERE client_id = ? AND tenant_id = ? AND branch_id = ?");
    $txn_check->execute([$id, $tenant_id, $branch_id]);
    $has_transactions = (int)$txn_check->fetchColumn() > 0;

    // Build dynamic UPDATE query
    $set_clauses = ['name = ?', 'email = ?', 'phone = ?', 'client_type = ?', 'status = ?'];
    $params = [$name, $email, $phone, $client_type, $status];

    // Only update balance if no transactions exist
    if (!$has_transactions) {
        if ($usd_balance !== null) {
            $set_clauses[] = 'usd_balance = ?';
            $params[] = $usd_balance;
        }
        if ($afs_balance !== null) {
            $set_clauses[] = 'afs_balance = ?';
            $params[] = $afs_balance;
        }
    }

    $params[] = $id;
    $params[] = $tenant_id;
    $params[] = $branch_id;

    // Update client in the database
    $stmt = $pdo->prepare("UPDATE clients SET " . implode(', ', $set_clauses) . " WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute($params);

    // Check if the update affected any rows
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Client updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or client not found.']);
    }
} catch (Exception $e) {
    // Handle errors gracefully
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
