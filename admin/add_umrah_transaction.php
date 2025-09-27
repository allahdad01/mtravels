<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
// Connect using mysqli
include_once('../includes/conn.php');

// Validate payment_currency
$payment_currency = isset($_POST['payment_currency']) ? DbSecurity::validateInput($_POST['payment_currency'], 'currency') : null;

// Validate receipt_number
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;

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
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $umrah_id = intval($_POST['umrah_id']);
    $payment_date = $_POST['payment_date'];
    $payment_description = $_POST['payment_description'];
    $transaction_to = $_POST['transaction_to'];
    $payment_amount = floatval($_POST['payment_amount']);
    $receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;
    $currency = $_POST['payment_currency'];

    // Start a transaction
    $conn->begin_transaction();

    try {
        // Step 1: Get the umrah booking details including currency and exchange rate
        $stmt_fetch_umrah_details = $conn->prepare("SELECT paid_to, supplier, received_bank_payment, currency as booking_currency FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $stmt_fetch_umrah_details->bind_param("ii", $umrah_id, $tenant_id);
        $stmt_fetch_umrah_details->execute();
        $stmt_fetch_umrah_details->bind_result($paid_to, $supplier_id, $received_bank_payment, $booking_currency);
        if (!$stmt_fetch_umrah_details->fetch()) {
            throw new Exception('Umrah booking details not found.');
        }
        $stmt_fetch_umrah_details->close();

        // Step 2: Insert the transaction into umrah_transactions table
        $stmt = $conn->prepare("INSERT INTO umrah_transactions (transaction_type, umrah_booking_id, payment_date, transaction_to, payment_description, payment_amount, currency, receipt, tenant_id, exchange_rate) VALUES ('Credit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssis", $umrah_id, $payment_date, $transaction_to, $payment_description, $payment_amount, $currency, $receipt_number, $tenant_id, $exchange_rate);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to add transaction");
        }

        // Get the inserted umrah transaction ID
        $umrah_transaction_id = $stmt->insert_id;
        
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
        $transaction_type = 'Credit'; // Default transaction type for adding a transaction

        if ($transaction_to_lower === 'bank') {
            if ($supplier_type === 'External') {
                // Get current supplier balance
                $stmt_get_supplier_balance = $conn->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
                $stmt_get_supplier_balance->bind_param("ii", $supplier_id, $tenant_id);
                $stmt_get_supplier_balance->execute();
                $stmt_get_supplier_balance->bind_result($current_supplier_balance);
                $stmt_get_supplier_balance->fetch();
                $stmt_get_supplier_balance->close();

                // Calculate new supplier balance
                $new_supplier_balance = $current_supplier_balance + $payment_amount;

                // Update supplier balance for external suppliers
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
                $stmt_update_supplier->bind_param("dii", $new_supplier_balance, $supplier_id, $tenant_id);
                if (!$stmt_update_supplier->execute()) {
                    throw new Exception('Failed to update supplier balance: ' . $stmt_update_supplier->error);
                }
                $stmt_update_supplier->close();

                // Record transaction in supplier_transactions with balance
                $stmt_insert_supplier_transaction = $conn->prepare("INSERT INTO supplier_transactions 
                    (supplier_id, transaction_type, amount, remarks, transaction_of, reference_id, balance, transaction_date, receipt, tenant_id)
                    VALUES (?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?)");
                $stmt_insert_supplier_transaction->bind_param(
                    "isdsidsi",
                    $supplier_id,
                    $transaction_type,
                    $payment_amount,
                    $payment_description,
                    $umrah_transaction_id,
                    $new_supplier_balance,
                    $receipt_number,
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

                // Calculate new main account balance
                $new_main_balance = $current_main_balance + $payment_amount;

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
                    (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate)
                    VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?, ?)");
                $stmt_insert_main_account_transaction->bind_param(
                    "isdssidsi",
                    $paid_to,
                    $transaction_type,
                    $payment_amount,
                    $currency,
                    $payment_description,
                    $umrah_transaction_id,
                    $new_main_balance,
                    $receipt_number,
                    $tenant_id,
                    $exchange_rate
                );
                if (!$stmt_insert_main_account_transaction->execute()) {
                    throw new Exception("Failed to record main account transaction.");
                }
                $stmt_insert_main_account_transaction->close();
            }

            // Update received_bank_payment in umrah_bookings
            $new_received_bank_payment = $received_bank_payment + $payment_amount;
            $stmt_update_umrah_booking = $conn->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ?");
            $stmt_update_umrah_booking->bind_param("dii", $new_received_bank_payment, $umrah_id, $tenant_id);
            if (!$stmt_update_umrah_booking->execute()) {
                throw new Exception('Failed to update received bank payment in umrah_bookings: ' . $stmt_update_umrah_booking->error);
            }
            $stmt_update_umrah_booking->close();
            
            // update bank receipt number
            $stmt_update_bank_receipt = $conn->prepare("UPDATE umrah_bookings SET bank_receipt_number = ? WHERE booking_id = ? AND tenant_id = ?");
            $stmt_update_bank_receipt->bind_param("sii", $receipt_number, $umrah_id, $tenant_id);
            if (!$stmt_update_bank_receipt->execute()) {
                throw new Exception('Failed to update bank receipt number in umrah_bookings: ' . $stmt_update_bank_receipt->error);
            }
            $stmt_update_bank_receipt->close();
            
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

            // Calculate new balance based on transaction type
            $new_main_balance = $current_main_balance + $payment_amount;

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
                (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate)
                VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?, ?)");
            $stmt_insert_main_account_transaction->bind_param(
                "isdssidsis",
                $paid_to,
                $transaction_type,
                $payment_amount,
                $currency,
                $payment_description,
                $umrah_transaction_id,
                $new_main_balance,
                $receipt_number,
                $tenant_id,
                $exchange_rate
            );
            if (!$stmt_insert_main_account_transaction->execute()) {
                throw new Exception("Failed to record main account transaction.");
            }
            $stmt_insert_main_account_transaction->close();
        } else {
            throw new Exception("Invalid transaction type: " . htmlspecialchars($transaction_to));
        }
        
        // Step 3: Calculate the total paid amount in the booking's base currency
        // First, get all transactions for this booking
        $stmt_get_transactions = $conn->prepare("
            SELECT payment_amount, currency, exchange_rate
            FROM umrah_transactions
            WHERE umrah_booking_id = ? AND transaction_type = 'Credit' AND tenant_id = ?
        ");
        $stmt_get_transactions->bind_param("ii", $umrah_id, $tenant_id);
        $stmt_get_transactions->execute();
        $transactions_result = $stmt_get_transactions->get_result();

        $total_paid_in_base_currency = 0;

        while ($transaction = $transactions_result->fetch_assoc()) {
            $txn_amount = floatval($transaction['payment_amount']);
            $txn_currency = $transaction['currency'];
            $txn_exchange_rate = floatval($transaction['exchange_rate']) ?: 1;
        
            // Convert to booking's base currency
            if ($txn_currency === $booking_currency) {
                // Same currency, no conversion needed
                $total_paid_in_base_currency += $txn_amount;
            } else {
                // FIXED: Apply your simple rule consistently
                if ($booking_currency === 'AFS') {
                    // Converting TO AFS: always multiply
                    $total_paid_in_base_currency += ($txn_amount * $txn_exchange_rate);
                } elseif ($booking_currency === 'USD') {
                    // Converting TO USD: always divide
                    $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate);
                } else {
                    // For other base currencies, add as is
                    $total_paid_in_base_currency += $txn_amount;
                }
            }
        }
        $stmt_get_transactions->close();
        
        // Update paid amount in umrah_bookings with the converted total
        $stmt_update_paid = $conn->prepare("UPDATE umrah_bookings SET paid = ? WHERE booking_id = ? AND tenant_id = ?");
        $stmt_update_paid->bind_param("dii", $total_paid_in_base_currency, $umrah_id, $tenant_id);
        if (!$stmt_update_paid->execute()) {
            throw new Exception('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
        }
        $stmt_update_paid->close();
        
        // Step 4: Get the supplier's name, applicant name, and base amount from umrah_bookings and suppliers
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

        // Step 5: Add a notification for the admin with the correct umrah_transaction_id
        $notification_message = "Customer: $traveler_name has paid: $payment_amount $currency to $transaction_to processed by $username for the Umrah booking.";

        $recipient_role = "admin";
        $transaction_type = "umrah";
        $status = "unread";

        // Insert the notification, using the umrah_transaction_id instead of umrah_id
        $notificationStmt = $conn->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $notificationStmt->bind_param("issssi", $umrah_transaction_id, $transaction_type, $notification_message, $recipient_role, $status, $tenant_id);

        if (!$notificationStmt->execute()) {
            throw new Exception("Failed to create notification");
        }

        // Commit the transaction
        $conn->commit();

        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'umrah_booking_id' => $umrah_id,
            'transaction_to' => $transaction_to,
            'payment_amount' => $payment_amount,
            'payment_currency' => $currency,
            'payment_description' => $payment_description,
            'payment_date' => $payment_date,
            'receipt_number' => $receipt_number
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'umrah_transactions', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt_log->bind_param("iissssi", $user_id, $umrah_transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();

        // Return success response
        echo json_encode(['success' => true]);
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
    echo json_encode(['success' => false]);
}

// Close the connection
$conn->close();
?>