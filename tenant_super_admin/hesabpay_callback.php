<?php
// Hesabpay callback handler
// This script handles payment callbacks from Hesabpay

require_once '../config.php';
require_once '../includes/db.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    error_log("Database connection failed in callback.");
    http_response_code(500);
    exit();
}

// Get callback data (assuming POST)
$session_id = $_POST['session_id'] ?? '';
$status = $_POST['status'] ?? '';
$amount = $_POST['amount'] ?? 0;
$currency = $_POST['currency'] ?? 'AFN';
$transaction_id = $_POST['transaction_id'] ?? '';

// Validate amount is numeric
if (!empty($amount) && (!is_numeric($amount) || floatval($amount) <= 0)) {
    error_log("Invalid amount in callback: " . json_encode($_POST));
    http_response_code(400);
    exit();
}
$amount = !empty($amount) ? floatval($amount) : 0;

// Verify the callback
if (empty($session_id) || empty($status)) {
    error_log("Invalid callback data: " . json_encode($_POST));
    http_response_code(400);
    exit();
}

try {
     error_log("Hesabpay callback received: session_id=$session_id, status=$status, amount=$amount, transaction_id=$transaction_id");

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
                 error_log("SECURITY: Subscription $subscription_id does not match tenant $session_tenant_id from payment session");
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
                 error_log("SECURITY: Failed to update subscription $subscription_id for tenant $session_tenant_id");
                 http_response_code(400);
                 echo json_encode(['status' => 'error', 'message' => 'Update failed']);
                 exit;
             }

             // SECURITY: Update session status with tenant_id validation
             $session_update = $pdo->prepare("UPDATE payment_sessions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE session_id = ? AND tenant_id = ?");
             $session_update->execute([$transaction_id, $session_id, $session_tenant_id]);

             if ($session_update->rowCount() === 0) {
                 error_log("SECURITY: Failed to update payment session $session_id for tenant $session_tenant_id");
                 http_response_code(400);
                 echo json_encode(['status' => 'error', 'message' => 'Session update failed']);
                 exit;
             }

             error_log("Payment processed successfully for subscription $subscription_id by tenant $session_tenant_id");
         } else {
             error_log("Payment session not found: $session_id");
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
                 error_log("SECURITY: Failed to update failed payment session $session_id");
                 http_response_code(400);
                 echo json_encode(['status' => 'error']);
             }
         } else {
             error_log("Payment session not found for failed status update: $session_id");
             http_response_code(400);
             echo json_encode(['status' => 'error']);
         }
     } else {
         http_response_code(200);
         echo json_encode(['status' => 'unknown']);
     }
 } catch (PDOException $e) {
     error_log("Error in callback: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['status' => 'error']);
 }
?>