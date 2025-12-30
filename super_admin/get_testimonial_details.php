<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $testimonial_id = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
    $stmt->execute([$testimonial_id]);
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $testimonial = $result->fetch();
        echo json_encode($testimonial);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Testimonial not found']);
    }

    } else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>
