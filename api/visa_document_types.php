<?php
/**
 * Visa Document Types API
 * Manages document types for visa applications
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../admin/security.php';

enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all document types for tenant
    $query = "SELECT id, name, description, is_required, category, status
              FROM visa_document_types
              WHERE tenant_id = ? AND status = 'active'
              ORDER BY is_required DESC, name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->execute();

    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'types' => $types
    ]);
    exit();
}

if ($method === 'POST') {
    // Create new document type (admin only)
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $isRequired = isset($data['is_required']) ? 1 : 0;
    $category = trim($data['category'] ?? 'other');
    $visaType = trim($data['visa_type'] ?? null);

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit();
    }

    // Check if type already exists
    $checkQuery = "SELECT id FROM visa_document_types 
                  WHERE tenant_id = ? AND name = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$tenant_id, $name]);

    if ($checkStmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document type already exists']);
        exit();
    }

    $insertQuery = "INSERT INTO visa_document_types 
                   (tenant_id, name, description, is_required, category, visa_type, status)
                   VALUES (?, ?, ?, ?, ?, ?, 'active')";
    
    $insertStmt = $pdo->prepare($insertQuery);
    
    try {
        $insertStmt->execute([
            $tenant_id,
            $name,
            $description,
            $isRequired,
            $category,
            $visaType
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Document type created successfully',
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit();
}

if ($method === 'PUT') {
    // Update document type (admin only)
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $isRequired = isset($data['is_required']) ? 1 : 0;
    $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active';

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document type ID']);
        exit();
    }

    // Verify ownership
    $checkQuery = "SELECT id FROM visa_document_types 
                  WHERE id = ? AND tenant_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id, $tenant_id]);

    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document type not found']);
        exit();
    }

    $updateQuery = "UPDATE visa_document_types 
                   SET is_required = ?, status = ?, updated_at = NOW()
                   WHERE id = ? AND tenant_id = ?";
    
    $updateStmt = $pdo->prepare($updateQuery);
    
    try {
        $updateStmt->execute([$isRequired, $status, $id, $tenant_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Document type updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
