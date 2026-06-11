<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once('../../admin/security.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Include database connection
require_once('../../includes/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// CSRF check
$data = json_decode(file_get_contents("php://input"), true);
$token = $_POST['csrf_token'] ?? $data['csrf_token'] ?? null;
if (!verify_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
    exit;
}

$maktob_id = isset($_POST['maktob_id']) ? (int)$_POST['maktob_id'] : (isset($data['maktob_id']) ? (int)$data['maktob_id'] : 0);

if ($maktob_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid maktob ID']);
    exit;
}

try {
    // Check if maktob exists and get file paths
    $check_query = "SELECT file_path, pdf_path FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($check_query);
    $stmt->execute([$maktob_id, $tenant_id, $branch_id]);
    $file_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file_data) {
        echo json_encode(['success' => false, 'message' => 'Maktob not found']);
        exit;
    }

    $file_path = $file_data['file_path'] ?? null;
    $pdf_path = $file_data['pdf_path'] ?? null;

    // Log the deletion
    $old_values = json_encode([
        'maktob_id' => $maktob_id,
        'file_path' => $file_path,
        'pdf_path' => $pdf_path
    ]);
    $new_values = json_encode([]);

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $log_query = "INSERT INTO maktob_logs
                  (tenant_id, maktob_id, user_id, action, old_values, new_values, ip_address, branch_id)
                  VALUES (?, ?, ?, 'delete', ?, ?, ?, ?)";

    $stmt_log = $pdo->prepare($log_query);
    $stmt_log->execute([$tenant_id, $maktob_id, $user_id, $old_values, $new_values, $ip_address, $branch_id]);

    // Delete maktob
    $query = "DELETE FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $deleteStmt = $pdo->prepare($query);
    $deleteStmt->execute([$maktob_id, $tenant_id, $branch_id]);

    // Delete associated files
    if ($file_path && file_exists("../../{$file_path}")) {
        unlink("../../{$file_path}");
    }
    if ($pdf_path && file_exists("../../{$pdf_path}")) {
        unlink("../../{$pdf_path}");
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
