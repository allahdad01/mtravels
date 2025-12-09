<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();


$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
// Connect using PDO
require_once('../../includes/db.php');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $umrah_id = intval($_POST['umrah_id']);
    $payment_date = $_POST['payment_date'];
    $payment_description = $_POST['payment_description'];
    $transaction_to = $_POST['transaction_to'];
    $payment_amount = floatval($_POST['payment_amount']);
    $currency = $_POST['payment_currency'];

    // Start a transaction
    $pdo->beginTransaction();

    try {
        // Step 1: Insert the transaction into umrah_transactions table as a refund (Debit)
        $stmt = $pdo->prepare("INSERT INTO umrah_transactions (transaction_type, umrah_booking_id, payment_date, transaction_to, payment_description, payment_amount, currency, tenant_id, branch_id) VALUES ('Debit', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $payment_date, PDO::PARAM_STR);
        $stmt->bindParam(3, $transaction_to, PDO::PARAM_STR);
        $stmt->bindParam(4, $payment_description, PDO::PARAM_STR);
        $stmt->bindParam(5, $payment_amount, PDO::PARAM_STR);
        $stmt->bindParam(6, $currency, PDO::PARAM_STR);
        $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new PDOException("Failed to add refund transaction");
        }

        // Get the inserted umrah transaction ID
        $umrah_transaction_id = $pdo->lastInsertId();

        // Fetch Umrah booking details
        $stmt_fetch_umrah_app = $pdo->prepare("SELECT paid_to, supplier, received_bank_payment FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_fetch_umrah_app->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_fetch_umrah_app->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_umrah_app->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_umrah_app->execute();
        $umrah_app_result = $stmt_fetch_umrah_app->fetch(PDO::FETCH_ASSOC);
        if (!$umrah_app_result) {
            throw new PDOException('Umrah booking details not found.');
        }
        $paid_to = $umrah_app_result['paid_to'];
        $supplier_id = $umrah_app_result['supplier'];
        $received_bank_payment = $umrah_app_result['received_bank_payment'];

        // Fetch Supplier Type
        $stmt_fetch_supplier = $pdo->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_fetch_supplier->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->execute();
        $supplier_result = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
        if (!$supplier_result) {
            throw new PDOException('Supplier details not found.');
        }
        $supplier_type = $supplier_result['supplier_type'];

        // Normalize $transaction_to to lowercase for case-insensitive comparison
        $transaction_to_lower = strtolower(trim($transaction_to));
        $transaction_type = 'Debit'; // Transaction type for refunds

        if ($transaction_to_lower === 'bank') {
            if ($supplier_type === 'External') {
                // Get current supplier balance
                $stmt_get_supplier_balance = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_get_supplier_balance->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_get_supplier_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_get_supplier_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_get_supplier_balance->execute();
                $supplier_balance_result = $stmt_get_supplier_balance->fetch(PDO::FETCH_ASSOC);
                $current_supplier_balance = $supplier_balance_result['balance'];

                // Calculate new supplier balance (subtract for refund)
                $new_supplier_balance = $current_supplier_balance - $payment_amount;

                // Update supplier balance for external suppliers
                $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_supplier->bindParam(1, $new_supplier_balance, PDO::PARAM_STR);
                $stmt_update_supplier->bindParam(2, $supplier_id, PDO::PARAM_INT);
                $stmt_update_supplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_supplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_supplier->execute()) {
                    throw new PDOException('Failed to update supplier balance: ' . $stmt_update_supplier->error);
                }

                // Record transaction in supplier_transactions with balance
                $stmt_insert_supplier_transaction = $pdo->prepare("INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, remarks, transaction_of, reference_id, balance, transaction_date, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?)");
                $stmt_insert_supplier_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_insert_supplier_transaction->bindParam(2, $transaction_type, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(4, $payment_description, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(5, $umrah_transaction_id, PDO::PARAM_INT);
                $stmt_insert_supplier_transaction->bindParam(6, $new_supplier_balance, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
                $stmt_insert_supplier_transaction->bindParam(8, $branch_id, PDO::PARAM_INT);
                if (!$stmt_insert_supplier_transaction->execute()) {
                    throw new PDOException("Failed to record supplier transaction.");
                }
            } else {
                // Get current main account balance
                $stmt_get_main_balance = $pdo->prepare(
                    $currency === 'USD'
                        ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                        : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                );
                $stmt_get_main_balance->bindParam(1, $paid_to, PDO::PARAM_INT);
                $stmt_get_main_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt_get_main_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt_get_main_balance->execute();
                $main_balance_result = $stmt_get_main_balance->fetch(PDO::FETCH_ASSOC);
                $current_main_balance = $main_balance_result[$currency === 'USD' ? 'usd_balance' : 'afs_balance'];

                // Calculate new main account balance (subtract for refund)
                $new_main_balance = $current_main_balance - $payment_amount;

                // Update main account balance for internal suppliers
                $stmt_update_main_account = $pdo->prepare(
                    $currency === 'USD'
                        ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                        : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                );
                $stmt_update_main_account->bindParam(1, $new_main_balance, PDO::PARAM_STR);
                $stmt_update_main_account->bindParam(2, $paid_to, PDO::PARAM_INT);
                $stmt_update_main_account->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main_account->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_main_account->execute()) {
                    throw new PDOException('Failed to update main account balance: ' . $stmt_update_main_account->error);
                }

                // Record transaction in main_account_transactions with balance
                $stmt_insert_main_account_transaction = $pdo->prepare("INSERT INTO main_account_transactions
                    (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?)");
                $stmt_insert_main_account_transaction->bindParam(1, $paid_to, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(2, $transaction_type, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(4, $currency, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(5, $payment_description, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(6, $umrah_transaction_id, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(7, $new_main_balance, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(9, $branch_id, PDO::PARAM_INT);
                if (!$stmt_insert_main_account_transaction->execute()) {
                    throw new PDOException("Failed to record main account transaction.");
                }
            }

            // Update received_bank_payment in umrah_bookings (subtract for refund)
            $new_received_bank_payment = $received_bank_payment - $payment_amount;
            $stmt_update_umrah_booking = $pdo->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_umrah_booking->bindParam(1, $new_received_bank_payment, PDO::PARAM_STR);
            $stmt_update_umrah_booking->bindParam(2, $umrah_id, PDO::PARAM_INT);
            $stmt_update_umrah_booking->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_umrah_booking->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_umrah_booking->execute()) {
                throw new PDOException('Failed to update received bank payment in umrah_bookings: ' . $stmt_update_umrah_booking->error);
            }

            // Update paid amount in umrah_bookings (subtract for refund)
            $stmt_update_paid = $pdo->prepare("UPDATE umrah_bookings SET paid = paid + ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_paid->bindParam(1, $payment_amount, PDO::PARAM_STR);
            $stmt_update_paid->bindParam(2, $umrah_id, PDO::PARAM_INT);
            $stmt_update_paid->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_paid->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_paid->execute()) {
                throw new PDOException('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
            }

        } elseif ($transaction_to_lower === 'internal account') {
            // Get current main account balance
            $stmt_get_main_balance = $pdo->prepare(
                $currency === 'USD'
                    ? "SELECT usd_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                    : "SELECT afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?"
            );
            $stmt_get_main_balance->bindParam(1, $paid_to, PDO::PARAM_INT);
            $stmt_get_main_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_get_main_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_get_main_balance->execute();
            $main_balance_result = $stmt_get_main_balance->fetch(PDO::FETCH_ASSOC);
            $current_main_balance = $main_balance_result[$currency === 'USD' ? 'usd_balance' : 'afs_balance'];

            // Calculate new balance (subtract for refund)
            $new_main_balance = $current_main_balance - $payment_amount;

            // Update main account balance
            $stmt_update_main_account = $pdo->prepare(
                $currency === 'USD'
                    ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                    : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
            );
            $stmt_update_main_account->bindParam(1, $new_main_balance, PDO::PARAM_STR);
            $stmt_update_main_account->bindParam(2, $paid_to, PDO::PARAM_INT);
            $stmt_update_main_account->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_main_account->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_main_account->execute()) {
                throw new PDOException('Failed to update main account balance: ' . $stmt_update_main_account->error);
            }

            // Record transaction in main_account_transactions with balance
            $stmt_insert_main_account_transaction = $pdo->prepare("INSERT INTO main_account_transactions
                (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, branch_id)
                VALUES (?, ?, ?, ?, ?, 'umrah', ?, ?, NOW(), ?, ?)");
            $stmt_insert_main_account_transaction->bindParam(1, $paid_to, PDO::PARAM_INT);
            $stmt_insert_main_account_transaction->bindParam(2, $transaction_type, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(4, $currency, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(5, $payment_description, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(6, $umrah_transaction_id, PDO::PARAM_INT);
            $stmt_insert_main_account_transaction->bindParam(7, $new_main_balance, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $stmt_insert_main_account_transaction->bindParam(9, $branch_id, PDO::PARAM_INT);
            if (!$stmt_insert_main_account_transaction->execute()) {
                throw new PDOException("Failed to record main account transaction.");
            }

            // Update paid amount in umrah_bookings (subtract for refund)
            $stmt_update_paid = $pdo->prepare("UPDATE umrah_bookings SET paid = paid + ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_paid->bindParam(1, $payment_amount, PDO::PARAM_STR);
            $stmt_update_paid->bindParam(2, $umrah_id, PDO::PARAM_INT);
            $stmt_update_paid->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_paid->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_paid->execute()) {
                throw new PDOException('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
            }
        } else {
            throw new PDOException("Invalid transaction type: " . htmlspecialchars($transaction_to));
        }

        // Step 2: Get the supplier's name, applicant name, and base amount from umrah_bookings and suppliers
        $supplierStmt = $pdo->prepare("
            SELECT
                ub.booking_id AS umrah_id,
                ub.name,
                ub.sold_price,
                s.name AS supplier_name,
                s.id AS supplier_id
            FROM umrah_bookings ub
            INNER JOIN suppliers s ON ub.supplier = s.id
            WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
        ");
        $supplierStmt->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $supplierStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $supplierStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $supplierStmt->execute();
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            throw new PDOException("Umrah booking or supplier not found");
        }

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
        $notificationStmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
        $notificationStmt->bindParam(1, $umrah_transaction_id, PDO::PARAM_INT);
        $notificationStmt->bindParam(2, $transaction_type, PDO::PARAM_STR);
        $notificationStmt->bindParam(3, $notification_message, PDO::PARAM_STR);
        $notificationStmt->bindParam(4, $recipient_role, PDO::PARAM_STR);
        $notificationStmt->bindParam(5, $status, PDO::PARAM_STR);
        $notificationStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $notificationStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$notificationStmt->execute()) {
            throw new PDOException("Failed to create notification");
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
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'umrah_transactions', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

        $new_values_json = json_encode($new_values);
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $umrah_transaction_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(3, $new_values_json, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();

        // Commit the transaction
        $pdo->commit();

        // Return success response
        echo json_encode(['success' => true, 'message' => 'Refund processed successfully, and notification sent.']);
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>