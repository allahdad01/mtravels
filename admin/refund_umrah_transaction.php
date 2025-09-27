<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();


$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
// Connect using mysqli
include_once('../includes/conn.php');

// Validate payment_currency
$payment_currency = isset($_POST['payment_currency']) ? DbSecurity::validateInput($_POST['payment_currency'], 'currency') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate transaction_to
$transaction_to = isset($_POST['transaction_to']) ? DbSecurity::validateInput($_POST['transaction_to'], 'string', ['maxlength' => 255]) : null;

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate umrah_id
$umrah_id = isset($_POST['umrah_id']) ? DbSecurity::validateInput($_POST['umrah_id'], 'int', ['min' => 0]) : null;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $umrah_id = intval($_POST['umrah_id']);
    $payment_date = $_POST['payment_date'];
    $payment_description = $_POST['payment_description'];
    $transaction_to = $_POST['transaction_to'];
    $payment_amount = floatval($_POST['payment_amount']);
    $currency = $_POST['payment_currency'];

    // Start a transaction
    $conn->begin_transaction();

    try {
        // Step 1: Insert the transaction into umrah_transactions table as a refund (Debit)
        $stmt = $conn->prepare("INSERT INTO umrah_transactions (transaction_type, umrah_booking_id, payment_date, transaction_to, payment_description, payment_amount, currency, tenant_id) VALUES ('Debit', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssi", $umrah_id, $payment_date, $transaction_to, $payment_description, $payment_amount, $currency, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to add refund transaction");
        }

        // Get the inserted umrah transaction ID
        $umrah_transaction_id = $stmt->insert_id;
        
        // Fetch Umrah booking details
        $stmt_fetch_umrah_app = $conn->prepare("SELECT paid_to, supplier, received_bank_payment FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $stmt_fetch_umrah_app->bind_param("ii", $umrah_id, $tenant_id);
        $stmt_fetch_umrah_app->execute();
        $stmt_fetch_umrah_app->bind_result($paid_to, $supplier_id, $received_bank_payment);
        if (!$stmt_fetch_umrah_app->fetch()) {
            throw new Exception('Umrah booking details not found.');
        }
        $stmt_fetch_umrah_app->close();

        // Fetch Supplier Type
        $stmt_fetch_supplier = $conn->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt_fetch_supplier->bind_param("ii", $supplier_id, $tenant_id);
        $stmt_fetch_supplier->execute();
        $stmt_fetch_supplier->bind_result($supplier_type, $supplier_currency);
        if (!$stmt_fetch_supplier->fetch()) {
            throw new Exception('Supplier details not found.');
        }
        $stmt_fetch_supplier->close();

        // Normalize $transaction_to to lowercase for case-insensitive comparison
        $transaction_to_lower = strtolower(trim($transaction_to));
        $transaction_type = 'Debit'; // Transaction type for refunds

        if ($transaction_to_lower === 'bank') {
            if ($supplier_type === 'External') {
                // Get current supplier balance
                $stmt_get_supplier_balance = $conn->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
                $stmt_get_supplier_balance->bind_param("ii", $supplier_id, $tenant_id);
                $stmt_get_supplier_balance->execute();
                $stmt_get_supplier_balance->bind_result($current_supplier_balance);
                $stmt_get_supplier_balance->fetch();
                $stmt_get_supplier_balance->close();

                // Calculate new supplier balance (subtract for refund)
                $new_supplier_balance = $current_supplier_balance - $payment_amount;

                // Update supplier balance for external suppliers
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
                $stmt_update_supplier->bind_param("dii", $new_supplier_balance, $supplier_id, $tenant_id);
                if (!$stmt_update_supplier->execute()) {
                    throw new Exception('Failed to update supplier balance: ' . $stmt_update_supplier->error);
                }
                $stmt_update_supplier->close();

                // Record transaction in supplier_transactions with balance
                $stmt_insert_supplier_transaction = $conn->prepare("INSERT INTO supplier_transactions 
                    (supplier_id, transaction_type, amount, remarks, transaction_of, reference_id, balance, transaction_date, tenant_id)
                    VALUES (?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?)");
                $stmt_insert_supplier_transaction->bind_param(
                    "isdsid",
                    $supplier_id,
                    $transaction_type,
                    $payment_amount,
                    $payment_description,
                    $umrah_transaction_id,
                    $new_supplier_balance,
                    $tenant_id
                );
                if (!$stmt_insert_supplier_transaction->execute()) {
                    throw new Exception("Failed to record supplier transaction.");
                }
                $stmt_insert_supplier_transaction->close();
            } else {
                // Get current main account balance
                $stmt_get_main_balance = $conn->prepare(
                    $currency === 'USD'
                        ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ?"
                        : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ?"
                );
                $stmt_get_main_balance->bind_param("ii", $paid_to, $tenant_id);
                $stmt_get_main_balance->execute();
                $stmt_get_main_balance->bind_result($current_main_balance);
                $stmt_get_main_balance->fetch();
                $stmt_get_main_balance->close();

                // Calculate new main account balance (subtract for refund)
                $new_main_balance = $current_main_balance - $payment_amount;

                // Update main account balance for internal suppliers
                $stmt_update_main_account = $conn->prepare(
                    $currency === 'USD'
                        ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ?"
                        : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ?"
                );
                $stmt_update_main_account->bind_param("dii", $new_main_balance, $paid_to, $tenant_id);
                if (!$stmt_update_main_account->execute()) {
                    throw new Exception('Failed to update main account balance: ' . $stmt_update_main_account->error);
                }
                $stmt_update_main_account->close();

                // Record transaction in main_account_transactions with balance
                $stmt_insert_main_account_transaction = $conn->prepare("INSERT INTO main_account_transactions 
                    (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id)
                    VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?)");
                $stmt_insert_main_account_transaction->bind_param(
                    "isdssid",
                    $paid_to,
                    $transaction_type,
                    $payment_amount,
                    $currency,
                    $payment_description,
                    $umrah_transaction_id,
                    $new_main_balance,
                    $tenant_id
                );
                if (!$stmt_insert_main_account_transaction->execute()) {
                    throw new Exception("Failed to record main account transaction.");
                }
                $stmt_insert_main_account_transaction->close();
            }

            // Update received_bank_payment in umrah_bookings (subtract for refund)
            $new_received_bank_payment = $received_bank_payment - $payment_amount;
            $stmt_update_umrah_booking = $conn->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ?");
            $stmt_update_umrah_booking->bind_param("di", $new_received_bank_payment, $umrah_id, $tenant_id);
            if (!$stmt_update_umrah_booking->execute()) {
                throw new Exception('Failed to update received bank payment in umrah_bookings: ' . $stmt_update_umrah_booking->error);
            }
            $stmt_update_umrah_booking->close();
            
            // Update paid amount in umrah_bookings (subtract for refund)
            $stmt_update_paid = $conn->prepare("UPDATE umrah_bookings SET paid = paid + ? WHERE booking_id = ? AND tenant_id = ?");
            $stmt_update_paid->bind_param("di", $payment_amount, $umrah_id, $tenant_id);
            if (!$stmt_update_paid->execute()) {
                throw new Exception('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
            }
            $stmt_update_paid->close();
            
        } elseif ($transaction_to_lower === 'internal account') {
            // Get current main account balance
            $stmt_get_main_balance = $conn->prepare(
                $currency === 'USD'
                    ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ?"
                    : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ?"
            );
            $stmt_get_main_balance->bind_param("ii", $paid_to, $tenant_id);
            $stmt_get_main_balance->execute();
            $stmt_get_main_balance->bind_result($current_main_balance);
            $stmt_get_main_balance->fetch();
            $stmt_get_main_balance->close();

            // Calculate new balance (subtract for refund)
            $new_main_balance = $current_main_balance - $payment_amount;

            // Update main account balance
            $stmt_update_main_account = $conn->prepare(
                $currency === 'USD'
                    ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ?"
                    : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ?"
            );
            $stmt_update_main_account->bind_param("dii", $new_main_balance, $paid_to, $tenant_id);
            if (!$stmt_update_main_account->execute()) {
                throw new Exception('Failed to update main account balance: ' . $stmt_update_main_account->error);
            }
            $stmt_update_main_account->close();

            // Record transaction in main_account_transactions with balance
            $stmt_insert_main_account_transaction = $conn->prepare("INSERT INTO main_account_transactions 
                (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id)
                VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?)");
            $stmt_insert_main_account_transaction->bind_param(
                "isdssid",
                $paid_to,
                $transaction_type,
                $payment_amount,
                $currency,
                $payment_description,
                $umrah_transaction_id,
                $new_main_balance,
                $tenant_id
            );
            if (!$stmt_insert_main_account_transaction->execute()) {
                throw new Exception("Failed to record main account transaction.");
            }
            $stmt_insert_main_account_transaction->close();
            
            // Update paid amount in umrah_bookings (subtract for refund)
            $stmt_update_paid = $conn->prepare("UPDATE umrah_bookings SET paid = paid + ? WHERE booking_id = ? AND tenant_id = ?");
            $stmt_update_paid->bind_param("di", $payment_amount, $umrah_id, $tenant_id);
            if (!$stmt_update_paid->execute()) {
                throw new Exception('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
            }
            $stmt_update_paid->close();
        } else {
            throw new Exception("Invalid transaction type: " . htmlspecialchars($transaction_to));
        }
        
        // Step 2: Get the supplier's name, applicant name, and base amount from umrah_bookings and suppliers
        $supplierStmt = $conn->prepare(" 
            SELECT 
                ub.booking_id AS umrah_id, 
                ub.name, 
                ub.sold_price, 
                s.name AS supplier_name,
                s.id AS supplier_id 
            FROM umrah_bookings ub
            INNER JOIN suppliers s ON ub.supplier = s.id
            WHERE ub.booking_id = ? AND ub.tenant_id = ?
        ");
        $supplierStmt->bind_param("ii", $umrah_id, $tenant_id);
        $supplierStmt->execute();
        $supplierResult = $supplierStmt->get_result();

        if ($supplierResult->num_rows === 0) {
            throw new Exception("Umrah booking or supplier not found");
        }

        $supplier = $supplierResult->fetch_assoc();
        $supplier_name = $supplier['supplier_name'];
        $traveler_name = $supplier['name'];
        $supplier_id = $supplier['supplier_id'];
        $base_amount = floatval($supplier['sold_price']);

        // Step 3: Add a notification for the admin with the correct umrah_transaction_id
        $notification_message = "A refund of <strong>$payment_amount $currency</strong> has been processed for customer <strong>$traveler_name</strong> by $username for the Umrah booking.";

        $recipient_role = "admin";
        $transaction_type = "umrah";
        $status = "unread";

        // Insert the notification, using the umrah_transaction_id instead of umrah_id
        $notificationStmt = $conn->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $notificationStmt->bind_param("issssi", $umrah_transaction_id, $transaction_type, $notification_message, $recipient_role, $status, $tenant_id);

        if (!$notificationStmt->execute()) {
            throw new Exception("Failed to create notification");
        }

        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare new values data
        $new_values = [
            'umrah_transaction_id' => $umrah_transaction_id,
            'umrah_booking_id' => $umrah_id,
            'transaction_to' => $transaction_to,
            'payment_amount' => $payment_amount,
            'currency' => $currency,
            'payment_date' => $payment_date,
            'payment_description' => $payment_description,
            'traveler_name' => $traveler_name,
            'supplier_name' => $supplier_name
        ];
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'umrah_transactions', ?, '{}', ?, ?, ?, NOW(), ?)");
        
        $new_values_json = json_encode($new_values);
        $activity_log_stmt->bind_param("iisssi", $user_id, $umrah_transaction_id, $new_values_json, $ip_address, $user_agent, $tenant_id);
        $activity_log_stmt->execute();
        $activity_log_stmt->close();

        // Commit the transaction
        $conn->commit();

        // Return success response
        echo json_encode(['success' => true, 'message' => 'Refund processed successfully, and notification sent.']);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } finally {
        // Close the statements
        if (isset($stmt)) $stmt->close();
        if (isset($supplierStmt)) $supplierStmt->close();
        if (isset($notificationStmt)) $notificationStmt->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close the connection
$conn->close();
?> 