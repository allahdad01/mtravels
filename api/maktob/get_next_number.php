<?php
// Get next maktob number for auto-numbering
require_once('../../includes/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$base_number = $_POST['base_number'] ?? null;

if (!$base_number) {
    echo json_encode(['success' => false, 'message' => 'Missing base_number parameter']);
    exit();
}

try {
    // Find the highest sequence number for this base
    $stmt = $pdo->prepare("SELECT maktob_number FROM maktobs
                          WHERE tenant_id = ? AND branch_id = ? AND maktob_number LIKE ?
                          ORDER BY CAST(SUBSTRING_INDEX(maktob_number, '-', -1) AS UNSIGNED) DESC
                          LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id'], $_SESSION['branch_id'], $base_number . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $next_sequence = 1;
    if ($result) {
        // Extract the sequence number from the existing number
        $existing_number = $result['maktob_number'];
        $parts = explode('-', $existing_number);
        if (count($parts) >= 4) {
            $last_part = end($parts);
            if (is_numeric($last_part)) {
                $next_sequence = intval($last_part) + 1;
            }
        }
    }

    // Format the sequence number with leading zeros (3 digits)
    $formatted_sequence = str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
    $full_number = $base_number . $formatted_sequence;

    echo json_encode(['success' => true, 'number' => $full_number]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>