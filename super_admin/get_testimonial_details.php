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
require_once '../includes/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $testimonial_id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM testimonials WHERE id = ?");
    $stmt->bind_param('i', $testimonial_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $testimonial = $result->fetch_assoc();
        echo json_encode($testimonial);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Testimonial not found']);
    }

    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>