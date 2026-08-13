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

    // Update client in the database
    $stmt = $pdo->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, client_type = ?, status = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$name, $email, $phone, $client_type, $status, $id, $tenant_id, $branch_id]);

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
