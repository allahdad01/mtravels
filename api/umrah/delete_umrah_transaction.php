<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
// Connect using PDO
require_once '../../includes/db.php';

// Validate umrah_id
$umrah_id = isset($_POST['umrah_id']) ? DbSecurity::validateInput($_POST['umrah_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

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
        $stmt_get_umrah_id = $pdo->prepare("SELECT umrah_booking_id FROM umrah_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_get_umrah_id->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt_get_umrah_id->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_umrah_id->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_umrah_id->execute();
        $umrah_result = $stmt_get_umrah_id->fetch(PDO::FETCH_ASSOC);

        if ($umrah_result) {
            $umrah_id = intval($umrah_result['umrah_booking_id']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found or umrah_id not provided']);
            exit;
        }
    }

    // Start a transaction
    $pdo->beginTransaction();

    try {
        // Step 1: Get transaction details before deleting
        $stmt_get_transaction = $pdo->prepare("SELECT payment_amount, currency, transaction_to, payment_description FROM umrah_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_get_transaction->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt_get_transaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_transaction->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_transaction->execute();
        $result = $stmt_get_transaction->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new PDOException("Transaction not found");
        }

        $transaction = $result;
        $payment_amount = floatval($transaction['payment_amount']);
        $currency = $transaction['currency'];
        $transaction_to = $transaction['transaction_to'];
        $payment_description = $transaction['payment_description'];
        $is_refund = $payment_amount < 0 || strpos($payment_description, 'Refund for:') === 0;

        // For proper reversal, we need to reverse the sign of the amount
        $reversal_amount = -$payment_amount;

        // Step 2: Fetch Umrah booking details
        $stmt_fetch_umrah = $pdo->prepare("SELECT paid_to, received_bank_payment, paid FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_fetch_umrah->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_fetch_umrah->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_umrah->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_umrah->execute();
        $umrah_result = $stmt_fetch_umrah->fetch(PDO::FETCH_ASSOC);

        if (!$umrah_result) {
            throw new PDOException("Umrah booking not found");
        }

        $umrah = $umrah_result;
        $paid_to = $umrah['paid_to'];
        $received_bank_payment = $umrah['received_bank_payment'];
        $current_paid = $umrah['paid'];

        // Get supplier_id from umrah_booking_services where service_type is 'all' or 'visa'
        $stmt_fetch_supplier_id = $pdo->prepare("SELECT supplier_id FROM umrah_booking_services WHERE booking_id = ? AND service_type IN ('all', 'visa') AND tenant_id = ? AND branch_id = ? LIMIT 1");
        $stmt_fetch_supplier_id->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_fetch_supplier_id->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_supplier_id->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_supplier_id->execute();
        $supplier_result = $stmt_fetch_supplier_id->fetch(PDO::FETCH_ASSOC);
        if (!$supplier_result) {
            throw new PDOException("Supplier not found for this booking");
        }
        $supplier_id = $supplier_result['supplier_id'];

        // Step 3: Fetch Supplier Type
        $stmt_fetch_supplier = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_fetch_supplier->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->execute();
        $supplier_result = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);

        if (!$supplier_result) {
            throw new PDOException("Supplier not found");
        }

        $supplier = $supplier_result;
        $supplier_type = $supplier['supplier_type'];

        // Normalize $transaction_to to lowercase for case-insensitive comparison
        $transaction_to_lower = strtolower(trim($transaction_to));

        // Step 4: Get booking currency for proper conversion
        $stmt_get_booking_currency = $pdo->prepare("SELECT currency FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_get_booking_currency->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_get_booking_currency->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_booking_currency->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_booking_currency->execute();
        $booking_result = $stmt_get_booking_currency->fetch(PDO::FETCH_ASSOC);
        $booking_currency = $booking_result['currency'];

        // Get exchange rate from the transaction
        $stmt_get_exchange_rate = $pdo->prepare("SELECT exchange_rate FROM umrah_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_get_exchange_rate->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt_get_exchange_rate->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_exchange_rate->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_exchange_rate->execute();
        $exchange_result = $stmt_get_exchange_rate->fetch(PDO::FETCH_ASSOC);
        $transaction_exchange_rate = $exchange_result['exchange_rate'] ?: 1;

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
            $stmt_update_received = $pdo->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_received->bindParam(1, $new_received_bank_payment, PDO::PARAM_STR);
            $stmt_update_received->bindParam(2, $umrah_id, PDO::PARAM_INT);
            $stmt_update_received->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_received->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt_update_received->execute()) {
                throw new PDOException("Failed to update received bank payment");
            }

            if ($supplier_type === 'External') {
                // Get current supplier balance
                $stmt_get_supplier_balance = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_get_supplier_balance->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_get_supplier_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_get_supplier_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_get_supplier_balance->execute();
                $balance_result = $stmt_get_supplier_balance->fetch(PDO::FETCH_ASSOC);
                $current_supplier_balance = $balance_result['balance'];

                // Calculate new supplier balance (reverse the transaction)
                $new_supplier_balance = $current_supplier_balance - $payment_amount; // Subtracting the payment amount reverses it

                // Update supplier balance
                $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_supplier->bindParam(1, $new_supplier_balance, PDO::PARAM_STR);
                $stmt_update_supplier->bindParam(2, $supplier_id, PDO::PARAM_INT);
                $stmt_update_supplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_supplier->bindParam(4, $branch_id, PDO::PARAM_INT);

                if (!$stmt_update_supplier->execute()) {
                    throw new PDOException("Failed to update supplier balance");
                }

                // Delete related supplier_transactions record
                $stmt_delete_supplier_transaction = $pdo->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?");
                $stmt_delete_supplier_transaction->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt_delete_supplier_transaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_delete_supplier_transaction->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_delete_supplier_transaction->execute();
            } else {
                // Determine balance field based on transaction currency
                if ($currency === 'USD') {
                    $balance_field = 'usd_balance';
                } elseif ($currency === 'AFS') {
                    $balance_field = 'afs_balance';
                } elseif ($currency === 'EUR') {
                    $balance_field = 'euro_balance'; // EUR transactions affect USD balance (converted)
                } elseif ($currency === 'DARHAM' || $currency === 'DAR') {
                    $balance_field = 'darham_balance'; // DARHAM transactions affect USD balance (converted)
                } else {
                    $balance_field = 'usd_balance'; // Default to USD balance
                }

                $stmt_get_balance = $pdo->prepare("SELECT $balance_field FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_get_balance->bindParam(1, $paid_to, PDO::PARAM_INT);
                $stmt_get_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_get_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_get_balance->execute();
                $balance_result = $stmt_get_balance->fetch(PDO::FETCH_ASSOC);
                $current_balance = $balance_result[$balance_field];

                // Calculate new balance (reverse the transaction)
                $new_balance = $current_balance - $payment_amount; // Subtracting the payment amount reverses it

                // Update main account balance
                $stmt_update_balance = $pdo->prepare("UPDATE main_account SET $balance_field = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_balance->bindParam(1, $new_balance, PDO::PARAM_STR);
                $stmt_update_balance->bindParam(2, $paid_to, PDO::PARAM_INT);
                $stmt_update_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_balance->bindParam(4, $branch_id, PDO::PARAM_INT);

                if (!$stmt_update_balance->execute()) {
                    throw new PDOException("Failed to update main account balance");
                }

                // Delete related main_account_transactions record
                $stmt_delete_main_transaction = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?");
                $stmt_delete_main_transaction->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt_delete_main_transaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_delete_main_transaction->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_delete_main_transaction->execute();
            }
        } elseif ($transaction_to_lower === 'internal account') {
            // Determine balance field based on transaction currency
            if ($currency === 'USD') {
                $balance_field = 'usd_balance';
            } elseif ($currency === 'AFS') {
                $balance_field = 'afs_balance';
            } elseif ($currency === 'EUR') {
                $balance_field = 'euro_balance'; // EUR transactions affect USD balance (converted)
            } elseif ($currency === 'DARHAM' || $currency === 'DAR') {
                $balance_field = 'darham_balance'; // DARHAM transactions affect USD balance (converted)
            } else {
                $balance_field = 'usd_balance'; // Default to USD balance
            }

            $stmt_get_balance = $pdo->prepare("SELECT $balance_field FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_get_balance->bindParam(1, $paid_to, PDO::PARAM_INT);
            $stmt_get_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_get_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_get_balance->execute();
            $balance_result = $stmt_get_balance->fetch(PDO::FETCH_ASSOC);
            $current_balance = $balance_result[$balance_field];

            // Calculate new balance (reverse the transaction)
            $new_balance = $current_balance - $payment_amount; // Subtracting the payment amount reverses it

            // Update main account balance
            $stmt_update_balance = $pdo->prepare("UPDATE main_account SET $balance_field = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_balance->bindParam(1, $new_balance, PDO::PARAM_STR);
            $stmt_update_balance->bindParam(2, $paid_to, PDO::PARAM_INT);
            $stmt_update_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_balance->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt_update_balance->execute()) {
                throw new PDOException("Failed to update main account balance");
            }

            // Get the transaction date to find subsequent transactions
            $stmt_get_transaction_date = $pdo->prepare("SELECT created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?");
            $stmt_get_transaction_date->bindParam(1, $transaction_id, PDO::PARAM_INT);
            $stmt_get_transaction_date->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_get_transaction_date->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_get_transaction_date->execute();
            $date_result = $stmt_get_transaction_date->fetch(PDO::FETCH_ASSOC);

            if (!$date_result) {
                throw new PDOException("Transaction record not found in main_account_transactions");
            }

            $transaction_date = $date_result['created_at'];

            // Update balances of all subsequent transactions
            $stmt_update_subsequent = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = balance - ?
                WHERE currency = ?
                AND id > ?
                AND reference_id != ?
                AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_update_subsequent->bindParam(1, $payment_amount, PDO::PARAM_STR);
            $stmt_update_subsequent->bindParam(2, $currency, PDO::PARAM_STR);
            $stmt_update_subsequent->bindParam(3, $transaction_id, PDO::PARAM_INT);
            $stmt_update_subsequent->bindParam(4, $transaction_id, PDO::PARAM_INT);
            $stmt_update_subsequent->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $stmt_update_subsequent->bindParam(6, $branch_id, PDO::PARAM_INT);

            if (!$stmt_update_subsequent->execute()) {
                throw new PDOException("Failed to update subsequent transaction balances");
            }

            // Delete related main_account_transactions record
            $stmt_delete_main_transaction = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?");
            $stmt_delete_main_transaction->bindParam(1, $transaction_id, PDO::PARAM_INT);
            $stmt_delete_main_transaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_delete_main_transaction->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_delete_main_transaction->execute();
        }

        // Update the paid amount in umrah_bookings
        $new_paid = $current_paid - $converted_payment_amount; // Subtracting the converted payment amount reverses it
        $stmt_update_paid = $pdo->prepare("UPDATE umrah_bookings SET paid = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_update_paid->bindParam(1, $new_paid, PDO::PARAM_STR);
        $stmt_update_paid->bindParam(2, $umrah_id, PDO::PARAM_INT);
        $stmt_update_paid->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt_update_paid->bindParam(4, $branch_id, PDO::PARAM_INT);

        if (!$stmt_update_paid->execute()) {
            throw new PDOException("Failed to update paid amount");
        }

        // Delete the transaction
        $stmt_delete_transaction = $pdo->prepare("DELETE FROM umrah_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_transaction->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt_delete_transaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_transaction->bindParam(3, $branch_id, PDO::PARAM_INT);

        if (!$stmt_delete_transaction->execute()) {
            throw new PDOException("Failed to delete transaction");
        }

        // Delete related notifications
        $stmt_delete_notification = $pdo->prepare("DELETE FROM notifications WHERE transaction_id = ? AND transaction_type = 'umrah' AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_notification->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt_delete_notification->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_notification->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_notification->execute();

        // Add notification about the deletion
        $transaction_type_text = $is_refund ? "refund" : "payment";
        $amount_display = abs($payment_amount);
        $notification_message = "A $transaction_type_text of $amount_display $currency has been deleted by $username for the Umrah booking.";
        $recipient_role = "admin";
        $status = "unread";

        $stmt_add_notification = $pdo->prepare("INSERT INTO notifications (message, recipient_role, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt_add_notification->bindParam(1, $notification_message, PDO::PARAM_STR);
        $stmt_add_notification->bindParam(2, $recipient_role, PDO::PARAM_STR);
        $stmt_add_notification->bindParam(3, $status, PDO::PARAM_STR);
        $stmt_add_notification->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt_add_notification->bindParam(5, $branch_id, PDO::PARAM_INT);

        if (!$stmt_add_notification->execute()) {
            throw new PDOException("Failed to create notification");
        }

        // Commit the transaction
        $pdo->commit();

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

        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'umrah_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $transaction_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        // Return success response
        echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
