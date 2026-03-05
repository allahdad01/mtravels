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

// Database connection
require_once '../../includes/db.php';

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
    $pdo->beginTransaction();

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
            $stmt_fetch_umrah_details = $pdo->prepare("SELECT paid_to, received_bank_payment, currency as booking_currency FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_fetch_umrah_details->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_fetch_umrah_details->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_umrah_details->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_umrah_details->execute();
            $umrah_details = $stmt_fetch_umrah_details->fetch(PDO::FETCH_ASSOC);
            if (!$umrah_details) {
                throw new PDOException('Umrah booking details not found for booking ID: ' . $booking_id);
            }
            $paid_to = $umrah_details['paid_to'];
            $received_bank_payment = $umrah_details['received_bank_payment'];
            $booking_currency = $umrah_details['booking_currency'];

            // Get supplier_id from umrah_booking_services
            $stmt_fetch_supplier_id = $pdo->prepare("SELECT supplier_id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ? AND service_type IN ('all', 'visa') LIMIT 1");
            $stmt_fetch_supplier_id->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_fetch_supplier_id->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_supplier_id->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_supplier_id->execute();
            $supplier_result = $stmt_fetch_supplier_id->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_result) {
                // Skip this member if no supplier is found - they might not be fully set up yet
                continue;
            }
            $supplier_id = $supplier_result['supplier_id'];

            // Insert the transaction
            $stmt = $pdo->prepare("INSERT INTO umrah_transactions (transaction_type, umrah_booking_id, payment_date, transaction_to, payment_description, payment_amount, currency, receipt, tenant_id, exchange_rate, branch_id) VALUES ('Credit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $payment_date, PDO::PARAM_STR);
            $stmt->bindParam(3, $transaction_to, PDO::PARAM_STR);
            $stmt->bindParam(4, $payment_description, PDO::PARAM_STR);
            $stmt->bindParam(5, $payment_amount, PDO::PARAM_STR);
            $stmt->bindParam(6, $payment_currency, PDO::PARAM_STR);
            $stmt->bindParam(7, $receipt_number, PDO::PARAM_STR);
            $stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(9, $exchange_rate, PDO::PARAM_STR);
            $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new PDOException("Failed to add transaction for booking ID: " . $booking_id);
            }

            $umrah_transaction_id = $pdo->lastInsertId();

            // Handle balance updates based on transaction_to
            $transaction_to_lower = strtolower(trim($transaction_to));

            if ($transaction_to_lower === 'bank') {
                // Get supplier type
                $stmt_fetch_supplier = $pdo->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_fetch_supplier->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_fetch_supplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_fetch_supplier->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_fetch_supplier->execute();
                $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
                if (!$supplier_data) {
                    throw new PDOException('Supplier details not found for supplier ID: ' . $supplier_id);
                }
                $supplier_type = $supplier_data['supplier_type'];

                if ($supplier_type === 'External') {
                    // Update supplier balance
                    $stmt_get_supplier_balance = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $stmt_get_supplier_balance->bindParam(1, $supplier_id, PDO::PARAM_INT);
                    $stmt_get_supplier_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $stmt_get_supplier_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmt_get_supplier_balance->execute();
                    $supplier_balance_result = $stmt_get_supplier_balance->fetch(PDO::FETCH_ASSOC);
                    $current_supplier_balance = $supplier_balance_result['balance'];

                    $new_supplier_balance = $current_supplier_balance + $payment_amount;

                    $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $stmt_update_supplier->bindParam(1, $new_supplier_balance, PDO::PARAM_STR);
                    $stmt_update_supplier->bindParam(2, $supplier_id, PDO::PARAM_INT);
                    $stmt_update_supplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt_update_supplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                    if (!$stmt_update_supplier->execute()) {
                        throw new PDOException('Failed to update supplier balance for supplier ID: ' . $supplier_id);
                    }

                    // Record supplier transaction
                    $stmt_insert_supplier_transaction = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, remarks, transaction_of, reference_id, balance, transaction_date, receipt, tenant_id, branch_id) VALUES (?, ?, ?, ?, 'umrah_transaction', ?, ?, NOW(), ?, ?, ?)");
                    $stmt_insert_supplier_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
                    $stmt_insert_supplier_transaction->bindParam(2, 'Credit', PDO::PARAM_STR);
                    $stmt_insert_supplier_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                    $stmt_insert_supplier_transaction->bindParam(4, $payment_description, PDO::PARAM_STR);
                    $stmt_insert_supplier_transaction->bindParam(5, $umrah_transaction_id, PDO::PARAM_INT);
                    $stmt_insert_supplier_transaction->bindParam(6, $new_supplier_balance, PDO::PARAM_STR);
                    $stmt_insert_supplier_transaction->bindParam(7, $receipt_number, PDO::PARAM_STR);
                    $stmt_insert_supplier_transaction->bindParam(8, $tenant_id, PDO::PARAM_INT);
                    $stmt_insert_supplier_transaction->bindParam(9, $branch_id, PDO::PARAM_INT);
                    if (!$stmt_insert_supplier_transaction->execute()) {
                        throw new PDOException("Failed to record supplier transaction for booking ID: " . $booking_id);
                    }
                } else {
                    // Update main account balance for internal suppliers
                    $stmt_get_main_balance = $pdo->prepare(
                        $payment_currency === 'USD'
                            ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                            : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                    );
                    $stmt_get_main_balance->bindParam(1, $paid_to, PDO::PARAM_INT);
                    $stmt_get_main_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $stmt_get_main_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmt_get_main_balance->execute();
                    $main_balance_result = $stmt_get_main_balance->fetch(PDO::FETCH_ASSOC);
                    $current_main_balance = $main_balance_result[$payment_currency === 'USD' ? 'usd_balance' : 'afs_balance'];

                    $new_main_balance = $current_main_balance + $payment_amount;

                    $stmt_update_main_account = $pdo->prepare(
                        $payment_currency === 'USD'
                            ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                            : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                    );
                    $stmt_update_main_account->bindParam(1, $new_main_balance, PDO::PARAM_STR);
                    $stmt_update_main_account->bindParam(2, $paid_to, PDO::PARAM_INT);
                    $stmt_update_main_account->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt_update_main_account->bindParam(4, $branch_id, PDO::PARAM_INT);
                    if (!$stmt_update_main_account->execute()) {
                        throw new PDOException('Failed to update main account balance for booking ID: ' . $booking_id);
                    }

                    // Record main account transaction
                    $stmt_insert_main_account_transaction = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate, branch_id) VALUES (?, ?, ?, ?, ?, 'umrah_transaction', ?, ?, NOW(), ?, ?, ?, ?)");
                    $stmt_insert_main_account_transaction->bindParam(1, $paid_to, PDO::PARAM_INT);
                    $stmt_insert_main_account_transaction->bindParam(2, 'Credit', PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(4, $payment_currency, PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(5, $payment_description, PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(6, $umrah_transaction_id, PDO::PARAM_INT);
                    $stmt_insert_main_account_transaction->bindParam(7, $new_main_balance, PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(8, $receipt_number, PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(9, $tenant_id, PDO::PARAM_INT);
                    $stmt_insert_main_account_transaction->bindParam(10, $exchange_rate, PDO::PARAM_STR);
                    $stmt_insert_main_account_transaction->bindParam(11, $branch_id, PDO::PARAM_INT);
                    if (!$stmt_insert_main_account_transaction->execute()) {
                        throw new PDOException("Failed to record main account transaction for booking ID: " . $booking_id);
                    }
                }

                // Update received_bank_payment
                $new_received_bank_payment = $received_bank_payment + $payment_amount;
                $stmt_update_umrah_booking = $pdo->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_umrah_booking->bindParam(1, $new_received_bank_payment, PDO::PARAM_STR);
                $stmt_update_umrah_booking->bindParam(2, $booking_id, PDO::PARAM_INT);
                $stmt_update_umrah_booking->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_umrah_booking->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_umrah_booking->execute()) {
                    throw new PDOException('Failed to update received bank payment for booking ID: ' . $booking_id);
                }

                // Update bank receipt number
                $stmt_update_bank_receipt = $pdo->prepare("UPDATE umrah_bookings SET bank_receipt_number = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_bank_receipt->bindParam(1, $receipt_number, PDO::PARAM_STR);
                $stmt_update_bank_receipt->bindParam(2, $booking_id, PDO::PARAM_INT);
                $stmt_update_bank_receipt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_bank_receipt->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_bank_receipt->execute()) {
                    throw new PDOException('Failed to update bank receipt number for booking ID: ' . $booking_id);
                }

            } elseif ($transaction_to_lower === 'internal account') {
                // Update main account balance
                $stmt_get_main_balance = $pdo->prepare(
                    $payment_currency === 'USD'
                        ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                        : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                );
                $stmt_get_main_balance->bindParam(1, $paid_to, PDO::PARAM_INT);
                $stmt_get_main_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_get_main_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_get_main_balance->execute();
                $main_balance_result = $stmt_get_main_balance->fetch(PDO::FETCH_ASSOC);
                $current_main_balance = $main_balance_result[$payment_currency === 'USD' ? 'usd_balance' : 'afs_balance'];

                $new_main_balance = $current_main_balance + $payment_amount;

                $stmt_update_main_account = $pdo->prepare(
                    $payment_currency === 'USD'
                        ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                        : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                );
                $stmt_update_main_account->bindParam(1, $new_main_balance, PDO::PARAM_STR);
                $stmt_update_main_account->bindParam(2, $paid_to, PDO::PARAM_INT);
                $stmt_update_main_account->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main_account->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_main_account->execute()) {
                    throw new PDOException('Failed to update main account balance for booking ID: ' . $booking_id);
                }

                // Record main account transaction
                $stmt_insert_main_account_transaction = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate, branch_id) VALUES (?, 'credit', ?, ?, ?, 'umrah_transaction', ?, ?, NOW(), ?, ?, ?, ?)");
                $stmt_insert_main_account_transaction->bindParam(1, $paid_to, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(2, $payment_amount, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(3, $payment_currency, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(4, $payment_description, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(5, $umrah_transaction_id, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(6, $new_main_balance, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(7, $receipt_number, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(9, $exchange_rate, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(10, $branch_id, PDO::PARAM_INT);
                if (!$stmt_insert_main_account_transaction->execute()) {
                    throw new PDOException("Failed to record main account transaction for booking ID: " . $booking_id);
                }
            }

            // Calculate and update paid/due amounts for this booking
            $stmt_get_transactions = $pdo->prepare("
                SELECT payment_amount, currency, exchange_rate
                FROM umrah_transactions
                WHERE umrah_booking_id = ? AND transaction_type = 'Credit' AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_get_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_get_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_get_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_get_transactions->execute();
            $transactions = $stmt_get_transactions->fetchAll(PDO::FETCH_ASSOC);

            $total_paid_in_base_currency = 0;

            foreach ($transactions as $transaction) {
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

            // Update paid amount
            $stmt_update_paid = $pdo->prepare("UPDATE umrah_bookings SET paid = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_paid->bindParam(1, $total_paid_in_base_currency, PDO::PARAM_STR);
            $stmt_update_paid->bindParam(2, $booking_id, PDO::PARAM_INT);
            $stmt_update_paid->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_paid->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_paid->execute()) {
                throw new PDOException('Failed to update paid amount for booking ID: ' . $booking_id);
            }

            // Update due amount
            $stmt_update_due = $pdo->prepare("UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_due->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_update_due->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_update_due->bindParam(3, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_due->execute()) {
                throw new PDOException('Failed to update due amount for booking ID: ' . $booking_id);
            }
        }

        // Update family totals for all processed members if they are active
        if ($processed_members > 0) {
            // Get all unique family_ids for the processed members that are active
            $stmt_get_families = $pdo->prepare("
                SELECT DISTINCT family_id FROM umrah_bookings 
                WHERE family_id IN (
                    SELECT DISTINCT family_id FROM umrah_bookings WHERE tenant_id = ? AND branch_id = ? AND status = 'active'
                )
                AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_get_families->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt_get_families->bindParam(2, $branch_id, PDO::PARAM_INT);
            $stmt_get_families->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_get_families->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt_get_families->execute();
            $families = $stmt_get_families->fetchAll(PDO::FETCH_ASSOC);
            
            // Update totals for each affected family
            foreach ($families as $family) {
                $affected_family_id = $family['family_id'];
                $stmt_update_family = $pdo->prepare("
                    UPDATE families f
                    SET
                        f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                        f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                        f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
                    WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
                ");
                $stmt_update_family->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $affected_family_id, $tenant_id, $branch_id]);
            }
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
                $travelerStmt = $pdo->prepare("SELECT name FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
                $travelerStmt->bindParam(1, $booking_id, PDO::PARAM_INT);
                $travelerStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $travelerStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $travelerStmt->execute();
                $traveler_result = $travelerStmt->fetch(PDO::FETCH_ASSOC);
                $traveler_name = $traveler_result['name'];

                // Get the transaction ID for this booking (from the loop above)
                // We need to get the umrah_transaction_id that was created for this booking
                $transactionIdStmt = $pdo->prepare("SELECT id FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
                $transactionIdStmt->bindParam(1, $booking_id, PDO::PARAM_INT);
                $transactionIdStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $transactionIdStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $transactionIdStmt->execute();
                $transaction_result = $transactionIdStmt->fetch(PDO::FETCH_ASSOC);
                $umrah_transaction_id = $transaction_result['id'];

                // Create individual notification for this member
                $notification_message = "Customer: $traveler_name has paid: $member_payment_amount $payment_currency to $transaction_to processed by $username for the Umrah booking.";

                $notificationStmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                $notificationStmt->bindParam(1, $umrah_transaction_id, PDO::PARAM_INT);
                $notificationStmt->bindParam(2, $transaction_type, PDO::PARAM_STR);
                $notificationStmt->bindParam(3, $notification_message, PDO::PARAM_STR);
                $notificationStmt->bindParam(4, $recipient_role, PDO::PARAM_STR);
                $notificationStmt->bindParam(5, $status, PDO::PARAM_STR);
                $notificationStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $notificationStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

                if (!$notificationStmt->execute()) {
                    throw new PDOException("Failed to create notification for booking ID: $booking_id");
                }
            }
        }

        // Commit the transaction
        $pdo->commit();

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

        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'umrah_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $family_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        // Return success response
        echo json_encode(['success' => true, 'processed_members' => $processed_members, 'total_amount' => $total_amount]);
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();

        // Log the error for debugging
        error_log('Family Transaction Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());

        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>