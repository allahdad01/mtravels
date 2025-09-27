<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();



$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
// Connect using mysqli
include_once('../includes/conn.php');

// Validate umrah_id
$umrah_id = isset($_POST['umrah_id']) ? DbSecurity::validateInput($_POST['umrah_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if input is JSON
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Handle JSON input
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        
        $transaction_id = isset($data['transaction_id']) ? intval($data['transaction_id']) : 0;
        $umrah_id = isset($data['umrah_id']) ? intval($data['umrah_id']) : 0;
    } else {
        // Handle form data input
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        $umrah_id = isset($_POST['umrah_id']) ? intval($_POST['umrah_id']) : 0;
    }
    
    // Validate input
    if ($transaction_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
        exit;
    }
    
    // If umrah_id is not provided, try to get it from the transaction
    if ($umrah_id <= 0) {
        $stmt_get_umrah_id = $conn->prepare("SELECT umrah_booking_id FROM umrah_transactions WHERE id = ? AND tenant_id = ?");
        $stmt_get_umrah_id->bind_param("ii", $transaction_id, $tenant_id);
        $stmt_get_umrah_id->execute();
        $umrah_result = $stmt_get_umrah_id->get_result();
        
        if ($umrah_result->num_rows > 0) {
            $umrah_row = $umrah_result->fetch_assoc();
            $umrah_id = intval($umrah_row['umrah_booking_id']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found or umrah_id not provided']);
            exit;
        }
    }
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Step 1: Get transaction details before deleting
        $stmt_get_transaction = $conn->prepare("SELECT payment_amount, currency, transaction_to, payment_description FROM umrah_transactions WHERE id = ? AND tenant_id = ?");
        $stmt_get_transaction->bind_param("ii", $transaction_id, $tenant_id);
        $stmt_get_transaction->execute();
        $result = $stmt_get_transaction->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaction not found");
        }
        
        $transaction = $result->fetch_assoc();
        $payment_amount = floatval($transaction['payment_amount']);
        $currency = $transaction['currency'];
        $transaction_to = $transaction['transaction_to'];
        $payment_description = $transaction['payment_description'];
        $is_refund = $payment_amount < 0 || strpos($payment_description, 'Refund for:') === 0;
        
        // For proper reversal, we need to reverse the sign of the amount
        $reversal_amount = -$payment_amount;
        
        // Step 2: Fetch Umrah booking details
        $stmt_fetch_umrah = $conn->prepare("SELECT paid_to, supplier, received_bank_payment, paid FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $stmt_fetch_umrah->bind_param("ii", $umrah_id, $tenant_id);
        $stmt_fetch_umrah->execute();
        $umrah_result = $stmt_fetch_umrah->get_result();
        
        if ($umrah_result->num_rows === 0) {
            throw new Exception("Umrah booking not found");
        }
        
        $umrah = $umrah_result->fetch_assoc();
        $paid_to = $umrah['paid_to'];
        $supplier_id = $umrah['supplier'];
        $received_bank_payment = $umrah['received_bank_payment'];
        $current_paid = $umrah['paid'];
        
        // Step 3: Fetch Supplier Type
        $stmt_fetch_supplier = $conn->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt_fetch_supplier->bind_param("ii", $supplier_id, $tenant_id);
        $stmt_fetch_supplier->execute();
        $supplier_result = $stmt_fetch_supplier->get_result();
        
        if ($supplier_result->num_rows === 0) {
            throw new Exception("Supplier not found");
        }
        
        $supplier = $supplier_result->fetch_assoc();
        $supplier_type = $supplier['supplier_type'];
        
        // Normalize $transaction_to to lowercase for case-insensitive comparison
        $transaction_to_lower = strtolower(trim($transaction_to));
        
        // Step 4: Get booking currency for proper conversion
        $stmt_get_booking_currency = $conn->prepare("SELECT currency FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $stmt_get_booking_currency->bind_param("ii", $umrah_id, $tenant_id);
        $stmt_get_booking_currency->execute();
        $booking_result = $stmt_get_booking_currency->get_result();
        $booking_currency = $booking_result->fetch_assoc()['currency'];
        $stmt_get_booking_currency->close();

       // Get exchange rate from the transaction
$stmt_get_exchange_rate = $conn->prepare("SELECT exchange_rate FROM umrah_transactions WHERE id = ? AND tenant_id = ?");
$stmt_get_exchange_rate->bind_param("ii", $transaction_id, $tenant_id);
$stmt_get_exchange_rate->execute();
$exchange_result = $stmt_get_exchange_rate->get_result();
$transaction_exchange_rate = $exchange_result->fetch_assoc()['exchange_rate'] ?: 1;
$stmt_get_exchange_rate->close();

// Convert payment amount to booking currency for proper reversal
$converted_payment_amount = $payment_amount;
if ($currency !== $booking_currency) {
    if ($booking_currency === 'AFS') {
        // Converting TO AFS: always multiply
        $converted_payment_amount = $payment_amount * $transaction_exchange_rate;
    } elseif ($booking_currency === 'USD') {
        // Converting TO USD: always divide
        $converted_payment_amount = $payment_amount / $transaction_exchange_rate;
    }
}

        // Step 5: Revert changes based on transaction type
        if ($transaction_to_lower === 'bank') {
            // Update the received_bank_payment (revert the transaction)
            $new_received_bank_payment = $received_bank_payment - $converted_payment_amount; // Subtracting the converted payment amount reverses it
            $stmt_update_received = $conn->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ?");
            $stmt_update_received->bind_param("dii", $new_received_bank_payment, $umrah_id, $tenant_id);

            if (!$stmt_update_received->execute()) {
                throw new Exception("Failed to update received bank payment");
            }

            if ($supplier_type === 'External') {
                // Get current supplier balance
                $stmt_get_supplier_balance = $conn->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
                $stmt_get_supplier_balance->bind_param("ii", $supplier_id, $tenant_id);
                $stmt_get_supplier_balance->execute();
                $balance_result = $stmt_get_supplier_balance->get_result();
                $current_supplier_balance = $balance_result->fetch_assoc()['balance'];

                // Calculate new supplier balance (reverse the transaction)
                $new_supplier_balance = $current_supplier_balance - $payment_amount; // Subtracting the payment amount reverses it

                // Update supplier balance
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
                $stmt_update_supplier->bind_param("dii", $new_supplier_balance, $supplier_id, $tenant_id);

                if (!$stmt_update_supplier->execute()) {
                    throw new Exception("Failed to update supplier balance");
                }

                // Delete related supplier_transactions record
                $stmt_delete_supplier_transaction = $conn->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
                $stmt_delete_supplier_transaction->bind_param("ii", $transaction_id, $tenant_id);
                $stmt_delete_supplier_transaction->execute();
            } else {
                // Determine balance field based on transaction currency
                if ($currency === 'USD') {
                    $balance_field = 'usd_balance';
                } elseif ($currency === 'AFS') {
                    $balance_field = 'afs_balance';
                } elseif ($currency === 'EUR') {
                    $balance_field = 'usd_balance'; // EUR transactions affect USD balance (converted)
                } elseif ($currency === 'DARHAM' || $currency === 'DAR') {
                    $balance_field = 'usd_balance'; // DARHAM transactions affect USD balance (converted)
                } else {
                    $balance_field = 'usd_balance'; // Default to USD balance
                }

                $stmt_get_balance = $conn->prepare("SELECT $balance_field FROM main_account WHERE id = ? AND tenant_id = ?");
                $stmt_get_balance->bind_param("ii", $paid_to, $tenant_id);
                $stmt_get_balance->execute();
                $balance_result = $stmt_get_balance->get_result();
                $current_balance = $balance_result->fetch_assoc()[$balance_field];

                // Calculate new balance (reverse the transaction)
                $new_balance = $current_balance - $payment_amount; // Subtracting the payment amount reverses it

                // Update main account balance
                $stmt_update_balance = $conn->prepare("UPDATE main_account SET $balance_field = ? WHERE id = ? AND tenant_id = ?");
                $stmt_update_balance->bind_param("dii", $new_balance, $paid_to, $tenant_id);

                if (!$stmt_update_balance->execute()) {
                    throw new Exception("Failed to update main account balance");
                }

                // Delete related main_account_transactions record
                $stmt_delete_main_transaction = $conn->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
                $stmt_delete_main_transaction->bind_param("ii", $transaction_id, $tenant_id);
                $stmt_delete_main_transaction->execute();
            }
        } elseif ($transaction_to_lower === 'internal account') {
            // Determine balance field based on transaction currency
            if ($currency === 'USD') {
                $balance_field = 'usd_balance';
            } elseif ($currency === 'AFS') {
                $balance_field = 'afs_balance';
            } elseif ($currency === 'EUR') {
                $balance_field = 'usd_balance'; // EUR transactions affect USD balance (converted)
            } elseif ($currency === 'DARHAM' || $currency === 'DAR') {
                $balance_field = 'usd_balance'; // DARHAM transactions affect USD balance (converted)
            } else {
                $balance_field = 'usd_balance'; // Default to USD balance
            }

            $stmt_get_balance = $conn->prepare("SELECT $balance_field FROM main_account WHERE id = ? AND tenant_id = ?");
            $stmt_get_balance->bind_param("ii", $paid_to, $tenant_id);
            $stmt_get_balance->execute();
            $balance_result = $stmt_get_balance->get_result();
            $current_balance = $balance_result->fetch_assoc()[$balance_field];

            // Calculate new balance (reverse the transaction)
            $new_balance = $current_balance - $payment_amount; // Subtracting the payment amount reverses it

            // Update main account balance
            $stmt_update_balance = $conn->prepare("UPDATE main_account SET $balance_field = ? WHERE id = ? AND tenant_id = ?");
            $stmt_update_balance->bind_param("dii", $new_balance, $paid_to, $tenant_id);

            if (!$stmt_update_balance->execute()) {
                throw new Exception("Failed to update main account balance");
            }

            // Get the transaction date to find subsequent transactions
            $stmt_get_transaction_date = $conn->prepare("SELECT created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
            $stmt_get_transaction_date->bind_param("ii", $transaction_id, $tenant_id);
            $stmt_get_transaction_date->execute();
            $date_result = $stmt_get_transaction_date->get_result();

            if ($date_result->num_rows === 0) {
                throw new Exception("Transaction record not found in main_account_transactions");
            }

            $transaction_date = $date_result->fetch_assoc()['created_at'];

            // Update balances of all subsequent transactions
            $stmt_update_subsequent = $conn->prepare("
                UPDATE main_account_transactions
                SET balance = balance - ?
                WHERE currency = ?
                AND created_at > ?
                AND reference_id != ?
                AND tenant_id = ?
            ");
            $stmt_update_subsequent->bind_param("dssii", $payment_amount, $currency, $transaction_date, $transaction_id, $tenant_id);

            if (!$stmt_update_subsequent->execute()) {
                throw new Exception("Failed to update subsequent transaction balances");
            }

            // Delete related main_account_transactions record
            $stmt_delete_main_transaction = $conn->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
            $stmt_delete_main_transaction->bind_param("ii", $transaction_id, $tenant_id);
            $stmt_delete_main_transaction->execute();
        }
        
        // Update the paid amount in umrah_bookings
        $new_paid = $current_paid - $converted_payment_amount; // Subtracting the converted payment amount reverses it
        $stmt_update_paid = $conn->prepare("UPDATE umrah_bookings SET paid = ? WHERE booking_id = ? AND tenant_id = ?");
        $stmt_update_paid->bind_param("dii", $new_paid, $umrah_id, $tenant_id);
        
        if (!$stmt_update_paid->execute()) {
            throw new Exception("Failed to update paid amount");
        }
        
        // Delete the transaction
        $stmt_delete_transaction = $conn->prepare("DELETE FROM umrah_transactions WHERE id = ? AND tenant_id = ?");
        $stmt_delete_transaction->bind_param("ii", $transaction_id, $tenant_id);
        
        if (!$stmt_delete_transaction->execute()) {
            throw new Exception("Failed to delete transaction");
        }
        
        // Delete related notifications
        $stmt_delete_notification = $conn->prepare("DELETE FROM notifications WHERE transaction_id = ? AND transaction_type = 'umrah' AND tenant_id = ?");
        $stmt_delete_notification->bind_param("ii", $transaction_id, $tenant_id);
        $stmt_delete_notification->execute();
        
        // Add notification about the deletion
        $transaction_type_text = $is_refund ? "refund" : "payment";
        $amount_display = abs($payment_amount);
        $notification_message = "A $transaction_type_text of $amount_display $currency has been deleted by $username for the Umrah booking.";
        $recipient_role = "admin";
        $status = "unread";
        
        $stmt_add_notification = $conn->prepare("INSERT INTO notifications (message, recipient_role, status, created_at, tenant_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmt_add_notification->bind_param("sssi", $notification_message, $recipient_role, $status, $tenant_id);
        
        if (!$stmt_add_notification->execute()) {
            throw new Exception("Failed to create notification");
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Log the activity
        $old_values = json_encode([
            'transaction_id' => $transaction_id,
            'umrah_id' => $umrah_id,
            'payment_amount' => $payment_amount,
            'currency' => $currency,
            'transaction_to' => $transaction_to,
            'payment_description' => $payment_description,
            'is_refund' => $is_refund,
            'supplier_id' => $supplier_id,
            'paid_to' => $paid_to
        ]);
        $new_values = json_encode([]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'delete', 'umrah_transactions', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt_log->bind_param("iissssi", $user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close the connection
$conn->close();
?>
