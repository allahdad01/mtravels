<?php
// Prevent HTML output from PHP errors/warnings that would break JSON response
ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";

// Connect using mysqli
include_once('../../includes/conn.php');

// Validate inputs
$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int', ['min' => 0]) : null;
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;
$transaction_to = isset($_POST['transaction_to']) ? DbSecurity::validateInput($_POST['transaction_to'], 'string', ['maxlength' => 255]) : null;
$payment_currency = isset($_POST['payment_currency']) ? DbSecurity::validateInput($_POST['payment_currency'], 'currency') : null;
$receipt_number = isset($_POST['receipt_number']) && !empty($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : '';
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;
$exchange_rate = isset($_POST['exchange_rate']) && !empty($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0.01]) : 1.0;

// Validate member payments array (JSON string from JavaScript)
$member_payments_json = isset($_POST['member_payments']) ? $_POST['member_payments'] : '[]';
$member_payments = json_decode($member_payments_json, true);
if (!is_array($member_payments)) {
    $member_payments = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        $processed_members = 0;
        $total_amount = 0;

        // Process each member's payment
        foreach ($member_payments as $member_data) {
            $booking_id = intval($member_data['booking_id']);
            $payment_amount = floatval($member_data['amount']);

            if ($payment_amount <= 0) {
                continue; // Skip if no payment for this member
            }

            $processed_members++;
            $total_amount += $payment_amount;

            // Get the umrah booking details
            $stmt_fetch_umrah_details = $conn->prepare("SELECT paid_to, received_bank_payment, currency as booking_currency FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? And branch_id = ?");
            $stmt_fetch_umrah_details->bind_param("iii", $booking_id, $tenant_id, $branch_id);
            $stmt_fetch_umrah_details->execute();
            $stmt_fetch_umrah_details->bind_result($paid_to, $received_bank_payment, $booking_currency);
            if (!$stmt_fetch_umrah_details->fetch()) {
                throw new Exception('Umrah booking details not found for booking ID: ' . $booking_id);
            }
            $stmt_fetch_umrah_details->close();

            // Get supplier_id from umrah_booking_services
            $stmt_fetch_supplier_id = $conn->prepare("SELECT supplier_id FROM umrah_booking_services WHERE booking_id = ? And tenant_id = ? And branch_id = ? AND service_type IN ('all', 'visa') LIMIT 1");
            $stmt_fetch_supplier_id->bind_param("iii", $booking_id, $tenant_id, $branch_id);
            $stmt_fetch_supplier_id->execute();
            $stmt_fetch_supplier_id->bind_result($supplier_id);
            if (!$stmt_fetch_supplier_id->fetch()) {
                // Skip this member if no supplier is found - they might not be fully set up yet
                $stmt_fetch_supplier_id->close();
                continue;
            }
            $stmt_fetch_supplier_id->close();

            // Insert the transaction
            $stmt = $conn->prepare("INSERT INTO umrah_transactions (transaction_type, umrah_booking_id, payment_date, transaction_to, payment_description, payment_amount, currency, receipt, tenant_id, exchange_rate, branch_id) VALUES ('Credit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssisi", $booking_id, $payment_date, $transaction_to, $payment_description, $payment_amount, $payment_currency, $receipt_number, $tenant_id, $exchange_rate, $branch_id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to add transaction for booking ID: " . $booking_id);
            }

            $umrah_transaction_id = $stmt->insert_id;
            $stmt->close();

            // Handle balance updates based on transaction_to
            $transaction_to_lower = strtolower(trim($transaction_to));

            if ($transaction_to_lower === 'bank') {
                // Get supplier type
                $stmt_fetch_supplier = $conn->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ? And branch_id = ?");
                $stmt_fetch_supplier->bind_param("iii", $supplier_id, $tenant_id, $branch_id);
                $stmt_fetch_supplier->execute();
                $stmt_fetch_supplier->bind_result($supplier_type, $supplier_currency);
                if (!$stmt_fetch_supplier->fetch()) {
                    throw new Exception('Supplier details not found for supplier ID: ' . $supplier_id);
                }
                $stmt_fetch_supplier->close();

                if ($supplier_type === 'External') {
                    // Update supplier balance
                    $stmt_get_supplier_balance = $conn->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? And branch_id = ?");
                    $stmt_get_supplier_balance->bind_param("iii", $supplier_id, $tenant_id, $branch_id);
                    $stmt_get_supplier_balance->execute();
                    $stmt_get_supplier_balance->bind_result($current_supplier_balance);
                    $stmt_get_supplier_balance->fetch();
                    $stmt_get_supplier_balance->close();

                    $new_supplier_balance = $current_supplier_balance + $payment_amount;

                    $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
                    $stmt_update_supplier->bind_param("diii", $new_supplier_balance, $supplier_id, $tenant_id, $branch_id);
                    if (!$stmt_update_supplier->execute()) {
                        throw new Exception('Failed to update supplier balance for supplier ID: ' . $supplier_id);
                    }
                    $stmt_update_supplier->close();

                    // Record supplier transaction
                    $stmt_insert_supplier_transaction = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, remarks, transaction_of, reference_id, balance, transaction_date, receipt, tenant_id, branch_id) VALUES (?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?, ?)");
                    $stmt_insert_supplier_transaction->bind_param("isdsidsii", $supplier_id, 'Credit', $payment_amount, $payment_description, $umrah_transaction_id, $new_supplier_balance, $receipt_number, $tenant_id, $branch_id);
                    if (!$stmt_insert_supplier_transaction->execute()) {
                        throw new Exception("Failed to record supplier transaction for booking ID: " . $booking_id);
                    }
                    $stmt_insert_supplier_transaction->close();
                } else {
                    // Update main account balance for internal suppliers
                    $stmt_get_main_balance = $conn->prepare(
                        $payment_currency === 'USD'
                            ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ? And branch_id = ?"
                            : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ? And branch_id = ?"
                    );
                    $stmt_get_main_balance->bind_param("iii", $paid_to, $tenant_id, $branch_id);
                    $stmt_get_main_balance->execute();
                    $stmt_get_main_balance->bind_result($current_main_balance);
                    $stmt_get_main_balance->fetch();
                    $stmt_get_main_balance->close();

                    $new_main_balance = $current_main_balance + $payment_amount;

                    $stmt_update_main_account = $conn->prepare(
                        $payment_currency === 'USD'
                            ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?"
                            : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?"
                    );
                    $stmt_update_main_account->bind_param("diii", $new_main_balance, $paid_to, $tenant_id, $branch_id);
                    if (!$stmt_update_main_account->execute()) {
                        throw new Exception('Failed to update main account balance for booking ID: ' . $booking_id);
                    }
                    $stmt_update_main_account->close();

                    // Record main account transaction
                    $stmt_insert_main_account_transaction = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate, branch_id) VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?, ?, ?)");
                    $stmt_insert_main_account_transaction->bind_param("isdssidsisi", $paid_to, 'Credit', $payment_amount, $payment_currency, $payment_description, $umrah_transaction_id, $new_main_balance, $receipt_number, $tenant_id, $exchange_rate, $branch_id);
                    if (!$stmt_insert_main_account_transaction->execute()) {
                        throw new Exception("Failed to record main account transaction for booking ID: " . $booking_id);
                    }
                    $stmt_insert_main_account_transaction->close();
                }

                // Update received_bank_payment
                $new_received_bank_payment = $received_bank_payment + $payment_amount;
                $stmt_update_umrah_booking = $conn->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ? And branch_id = ?");
                $stmt_update_umrah_booking->bind_param("diii", $new_received_bank_payment, $booking_id, $tenant_id, $branch_id);
                if (!$stmt_update_umrah_booking->execute()) {
                    throw new Exception('Failed to update received bank payment for booking ID: ' . $booking_id);
                }
                $stmt_update_umrah_booking->close();

                // Update bank receipt number
                $stmt_update_bank_receipt = $conn->prepare("UPDATE umrah_bookings SET bank_receipt_number = ? WHERE booking_id = ? AND tenant_id = ? And branch_id = ?");
                $stmt_update_bank_receipt->bind_param("siii", $receipt_number, $booking_id, $tenant_id, $branch_id);
                if (!$stmt_update_bank_receipt->execute()) {
                    throw new Exception('Failed to update bank receipt number for booking ID: ' . $booking_id);
                }
                $stmt_update_bank_receipt->close();

            } elseif ($transaction_to_lower === 'internal account') {
                // Update main account balance
                $stmt_get_main_balance = $conn->prepare(
                    $payment_currency === 'USD'
                        ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ? And branch_id = ?"
                        : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ? And branch_id = ?"
                );
                $stmt_get_main_balance->bind_param("iii", $paid_to, $tenant_id, $branch_id);
                $stmt_get_main_balance->execute();
                $stmt_get_main_balance->bind_result($current_main_balance);
                $stmt_get_main_balance->fetch();
                $stmt_get_main_balance->close();

                $new_main_balance = $current_main_balance + $payment_amount;

                $stmt_update_main_account = $conn->prepare(
                    $payment_currency === 'USD'
                        ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?"
                        : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?"
                );
                $stmt_update_main_account->bind_param("diii", $new_main_balance, $paid_to, $tenant_id, $branch_id);
                if (!$stmt_update_main_account->execute()) {
                    throw new Exception('Failed to update main account balance for booking ID: ' . $booking_id);
                }
                $stmt_update_main_account->close();

                // Record main account transaction
                $stmt_insert_main_account_transaction = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate, branch_id) VALUES (?, 'credit', ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?, ?, ?)");
                $stmt_insert_main_account_transaction->bind_param("idssidsisi", $paid_to, $payment_amount, $payment_currency, $payment_description, $umrah_transaction_id, $new_main_balance, $receipt_number, $tenant_id, $exchange_rate, $branch_id);
                if (!$stmt_insert_main_account_transaction->execute()) {
                    throw new Exception("Failed to record main account transaction for booking ID: " . $booking_id);
                }
                $stmt_insert_main_account_transaction->close();
            }

            // Calculate and update paid/due amounts for this booking
            $stmt_get_transactions = $conn->prepare("
                SELECT payment_amount, currency, exchange_rate
                FROM umrah_transactions
                WHERE umrah_booking_id = ? AND transaction_type = 'Credit' AND tenant_id = ? And branch_id = ?
            ");
            $stmt_get_transactions->bind_param("iii", $booking_id, $tenant_id, $branch_id);
            $stmt_get_transactions->execute();
            $transactions_result = $stmt_get_transactions->get_result();

            $total_paid_in_base_currency = 0;

            while ($transaction = $transactions_result->fetch_assoc()) {
                $txn_amount = floatval($transaction['payment_amount']);
                $txn_currency = $transaction['currency'];
                $txn_exchange_rate = floatval($transaction['exchange_rate']) ?: 1;

                if ($txn_currency === $booking_currency) {
                    $total_paid_in_base_currency += $txn_amount;
                } else {
                    if ($booking_currency === 'AFS') {
                        $total_paid_in_base_currency += ($txn_amount * $txn_exchange_rate);
                    } elseif ($booking_currency === 'USD') {
                        $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate);
                    } else {
                        $total_paid_in_base_currency += $txn_amount;
                    }
                }
            }
            $stmt_get_transactions->close();

            // Update paid amount
            $stmt_update_paid = $conn->prepare("UPDATE umrah_bookings SET paid = ? WHERE booking_id = ? AND tenant_id = ? And branch_id = ?");
            $stmt_update_paid->bind_param("diii", $total_paid_in_base_currency, $booking_id, $tenant_id, $branch_id);
            if (!$stmt_update_paid->execute()) {
                throw new Exception('Failed to update paid amount for booking ID: ' . $booking_id);
            }
            $stmt_update_paid->close();

            // Update due amount
            $stmt_update_due = $conn->prepare("UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? And branch_id = ?");
            $stmt_update_due->bind_param("iii", $booking_id, $tenant_id, $branch_id);
            if (!$stmt_update_due->execute()) {
                throw new Exception('Failed to update due amount for booking ID: ' . $booking_id);
            }
            $stmt_update_due->close();
        }

        // Create individual notifications for each processed member (like single transactions)
        if ($processed_members > 0) {
            $recipient_role = "admin";
            $transaction_type = "umrah";
            $status = "unread";

            // Create notifications for each processed member
            foreach ($member_payments as $member_data) {
                $booking_id = intval($member_data['booking_id']);
                $member_payment_amount = floatval($member_data['amount']);

                if ($member_payment_amount <= 0) {
                    continue; // Skip if no payment for this member
                }

                // Get traveler name for this booking
                $travelerStmt = $conn->prepare("SELECT name FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? And branch_id = ?");
                $travelerStmt->bind_param("iii", $booking_id, $tenant_id, $branch_id);
                $travelerStmt->execute();
                $travelerStmt->bind_result($traveler_name);
                $travelerStmt->fetch();
                $travelerStmt->close();

                // Get the transaction ID for this booking (from the loop above)
                // We need to get the umrah_transaction_id that was created for this booking
                $transactionIdStmt = $conn->prepare("SELECT id FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? And branch_id = ? ORDER BY id DESC LIMIT 1");
                $transactionIdStmt->bind_param("iii", $booking_id, $tenant_id, $branch_id);
                $transactionIdStmt->execute();
                $transactionIdStmt->bind_result($umrah_transaction_id);
                $transactionIdStmt->fetch();
                $transactionIdStmt->close();

                // Create individual notification for this member
                $notification_message = "Customer: $traveler_name has paid: $member_payment_amount $payment_currency to $transaction_to processed by $username for the Umrah booking.";

                $notificationStmt = $conn->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                $notificationStmt->bind_param("issssii", $umrah_transaction_id, $transaction_type, $notification_message, $recipient_role, $status, $tenant_id, $branch_id);

                if (!$notificationStmt->execute()) {
                    throw new Exception("Failed to create notification for booking ID: $booking_id");
                }
            }
        }

        // Commit the transaction
        $conn->commit();

        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'family_id' => $family_id,
            'transaction_to' => $transaction_to,
            'total_amount' => $total_amount,
            'payment_currency' => $payment_currency,
            'payment_description' => $payment_description,
            'payment_date' => $payment_date,
            'receipt_number' => $receipt_number,
            'processed_members' => $processed_members
        ]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt_log = $conn->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'umrah_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bind_param("iissssii", $user_id, $family_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id);
        $stmt_log->execute();
        $stmt_log->close();

        // Return success response
        echo json_encode(['success' => true, 'processed_members' => $processed_members, 'total_amount' => $total_amount]);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();

        // Log the error for debugging
        error_log('Family Transaction Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}

// Close the connection
$conn->close();
?>