<?php
/**
 * Client Umrah View Handler
 * Redirects to the umrah detail page
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../../login.php');
    exit();
}

require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? htmlspecialchars($_GET['booking_id']) : null;

if (!$booking_id) {
    header('Location: ../umrah.php?error=invalid_booking');
    exit();
}

// Verify the client owns this booking
try {
    $stmt = $pdo->prepare("
        SELECT booking_id FROM umrah_bookings 
        WHERE booking_id = ? AND sold_to = ? AND tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$booking_id, $client_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header('Location: ../umrah.php?error=booking_not_found');
        exit();
    }
} catch (PDOException $e) {
    header('Location: ../umrah.php?error=database_error');
    exit();
}

// Redirect to the detail page using booking_id
header('Location: ../umrah_detail.php?id=' . intval($booking_id));
exit();
?>
