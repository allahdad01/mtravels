<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get family ID from request
$family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : 0;

if (!$family_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid family ID']);
    exit;
}

try {
    // Get all bookings for this family
    $sql = "SELECT booking_id, name, passport_number FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$family_id, $tenant_id, $branch_id]);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'bookings' => $bookings]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch family bookings']);
} 