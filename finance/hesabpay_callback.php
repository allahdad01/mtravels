<?php
// Hesabpay callback handler
// This script handles payment callbacks from Hesabpay

require_once '../config.php';
require_once '../includes/conn.php';
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

// Verify the callback
if (empty($session_id) || empty($status)) {
    error_log("Invalid callback data: " . json_encode($_POST));
    http_response_code(400);
    exit();
}

try {
    error_log("Hesabpay callback received: session_id=$session_id, status=$status, amount=$amount, transaction_id=$transaction_id");

    if ($status === 'success') {
        // Find the payment session
        $stmt = $pdo->prepare("SELECT * FROM payment_sessions WHERE session_id = ? AND status = 'pending'");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $subscription_id = $session['subscription_id'];
            $tenant_id = $session['tenant_id'];

            // Insert payment record
            $processed_by = 1; // System user
            $stmt2 = $pdo->prepare("INSERT INTO subscription_payments (subscription_id, amount, currency, payment_method, payment_date, processed_by, receipt_number) VALUES (?, ?, ?, 'Hesabpay', CURDATE(), ?, ?)");
            $stmt2->execute([$subscription_id, $amount, $currency, $processed_by, $transaction_id]);

            // Update subscription status to active
            $pdo->prepare("UPDATE tenant_subscriptions SET status = 'active' WHERE id = ? AND tenant_id = ?")->execute([$subscription_id, $tenant_id]);

            // Update session status
            $pdo->prepare("UPDATE payment_sessions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE session_id = ?")->execute([$transaction_id, $session_id]);

            error_log("Payment processed successfully for subscription $subscription_id");
        } else {
            error_log("Payment session not found: $session_id");
        }

        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } elseif ($status === 'failed') {
        // Update session status to failed
        $pdo->prepare("UPDATE payment_sessions SET status = 'failed', updated_at = NOW() WHERE session_id = ?")->execute([$session_id]);

        http_response_code(200);
        echo json_encode(['status' => 'failed']);
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