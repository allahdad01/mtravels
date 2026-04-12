<?php
// Hesabpay callback handler
// This script handles payment callbacks from Hesabpay

require_once '../config.php';
require_once '../includes/db.php';


// Get callback data (assuming POST)
$session_id = $_POST['session_id'] ?? '';
$status = $_POST['status'] ?? '';
$amount = $_POST['amount'] ?? 0;
$currency = $_POST['currency'] ?? 'AFN';
$transaction_id = $_POST['transaction_id'] ?? '';

// Validate amount is numeric
if (!empty($amount) && (!is_numeric($amount) || floatval($amount) <= 0)) {
    http_response_code(400);
    exit();
}
$amount = !empty($amount) ? floatval($amount) : 0;

// Verify the callback
if (empty($session_id) || empty($status)) {
    http_response_code(400);
    exit();
}

try {

     if ($status === 'success') {
         // Find the payment session with proper validation
         // SECURITY: Verify session exists and is pending
         $stmt = $pdo->prepare("SELECT * FROM payment_sessions WHERE session_id = ? AND status = 'pending'");
         $stmt->execute([$session_id]);
         $session = $stmt->fetch(PDO::FETCH_ASSOC);

         if ($session) {
             $subscription_id = $session['subscription_id'];
             $session_tenant_id = $session['tenant_id'];

             // SECURITY: Verify the subscription belongs to the tenant in the payment session
             // Prevent cross-tenant payment processing
             $sub_stmt = $pdo->prepare("SELECT id, tenant_id FROM tenant_subscriptions WHERE id = ? AND tenant_id = ?");
             $sub_stmt->execute([$subscription_id, $session_tenant_id]);
             $subscription = $sub_stmt->fetch(PDO::FETCH_ASSOC);

             if (!$subscription) {
                 http_response_code(400);
                 echo json_encode(['status' => 'error', 'message' => 'Invalid subscription']);
                 exit;
             }

             // Insert payment record
             $processed_by = 1; // System user
             $stmt2 = $pdo->prepare("INSERT INTO subscription_payments (subscription_id, amount, currency, payment_method, payment_date, processed_by, receipt_number) VALUES (?, ?, ?, 'Hesabpay', CURDATE(), ?, ?)");
             $stmt2->execute([$subscription_id, $amount, $currency, $processed_by, $transaction_id]);

             // SECURITY: Update subscription with tenant_id check to prevent cross-tenant modification
             $update_stmt = $pdo->prepare("UPDATE tenant_subscriptions SET status = 'active' WHERE id = ? AND tenant_id = ?");
             $update_rows = $update_stmt->execute([$subscription_id, $session_tenant_id]);

             if ($update_stmt->rowCount() === 0) {
                 http_response_code(400);
                 echo json_encode(['status' => 'error', 'message' => 'Update failed']);
                 exit;
             }

             // SECURITY: Update session status with tenant_id validation
             $session_update = $pdo->prepare("UPDATE payment_sessions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE session_id = ? AND tenant_id = ?");
             $session_update->execute([$transaction_id, $session_id, $session_tenant_id]);

             if ($session_update->rowCount() === 0) {
                 http_response_code(400);
                 echo json_encode(['status' => 'error', 'message' => 'Session update failed']);
                 exit;
             }

         } else {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Session not found']);
             exit;
         }

         http_response_code(200);
         echo json_encode(['status' => 'success']);
     } elseif ($status === 'failed') {
         // SECURITY: Update session status with tenant_id validation to prevent cross-tenant tampering
         // First verify the session exists
         $verify_stmt = $pdo->prepare("SELECT tenant_id FROM payment_sessions WHERE session_id = ? AND status = 'pending'");
         $verify_stmt->execute([$session_id]);
         $session_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);

         if ($session_data) {
             $session_update = $pdo->prepare("UPDATE payment_sessions SET status = 'failed', updated_at = NOW() WHERE session_id = ? AND tenant_id = ?");
             $session_update->execute([$session_id, $session_data['tenant_id']]);

             if ($session_update->rowCount() > 0) {
                 http_response_code(200);
                 echo json_encode(['status' => 'failed']);
             } else {
                 http_response_code(400);
                 echo json_encode(['status' => 'error']);
             }
         } else {
             http_response_code(400);
             echo json_encode(['status' => 'error']);
         }
     } else {
         http_response_code(200);
         echo json_encode(['status' => 'unknown']);
     }
 } catch (PDOException $e) {
     http_response_code(500);
     echo json_encode(['status' => 'error']);
 }
?>