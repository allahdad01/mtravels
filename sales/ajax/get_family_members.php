<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once('../../includes/db.php');
require_once('../../includes/conn.php');
require_once('../security.php');

// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Unauthorized access');
}

// Check if family ID is provided
if (!isset($_GET['family_id'])) {
    http_response_code(400);
    die('Family ID is required');
}

$familyId = intval($_GET['family_id']);

try {
    // Get family members
    $query = "
        SELECT ub.booking_id, ub.name, ub.passport_number
        FROM umrah_bookings ub
        WHERE ub.family_id = ?
        ORDER BY ub.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$familyId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($members);
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error fetching family members: ' . $e->getMessage());
} 