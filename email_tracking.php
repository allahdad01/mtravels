<?php
// Email tracking endpoint
require_once 'includes/conn.php';

// Get email ID from URL parameter
$emailId = isset($_GET['email_id']) ? $_GET['email_id'] : '';

if (empty($emailId)) {
    // Return a 1x1 transparent pixel
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Get client information
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

// Update tracking record
$stmt = $conn->prepare("UPDATE email_tracking SET opened = 1, opened_at = NOW(), user_agent = ?, ip_address = ? WHERE email_id = ? AND opened = 0");
$stmt->bind_param("sss", $userAgent, $ipAddress, $emailId);
$stmt->execute();
$stmt->close();

$conn->close();

// Return a 1x1 transparent pixel
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>