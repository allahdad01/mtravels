<?php
require_once('../../admin/security.php');
enforce_auth();

require_once('../../includes/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// CSRF check for AJAX calls
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
    exit();
}

$base_number = $_POST['base_number'] ?? null;

if (!$base_number) {
    echo json_encode(['success' => false, 'message' => 'Missing base_number parameter']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT maktob_number FROM maktobs
                          WHERE tenant_id = ? AND branch_id = ? AND maktob_number LIKE ?
                          ORDER BY CAST(SUBSTRING_INDEX(maktob_number, '-', -1) AS UNSIGNED) DESC
                          LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id'], $_SESSION['branch_id'], $base_number . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $next_sequence = 1;
    if ($result) {
        $existing_number = $result['maktob_number'];
        $parts = explode('-', $existing_number);
        if (count($parts) >= 4) {
            $last_part = end($parts);
            if (is_numeric($last_part)) {
                $next_sequence = intval($last_part) + 1;
            }
        }
    }

    $formatted_sequence = str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
    $full_number = $base_number . $formatted_sequence;

    echo json_encode(['success' => true, 'number' => $full_number]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
