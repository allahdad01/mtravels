<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
// Connect using PDO
require_once '../../includes/db.php';

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
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : 1.0;
$main_account_id = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $umrah_id = intval($_POST['umrah_id']);
    $payment_date = $_POST['payment_date'];
    $payment_description = $_POST['payment_description'];
    $transaction_to = $_POST['transaction_to'];
    $payment_amount = floatval($_POST['payment_amount']);
    $receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;
    $currency = $_POST['payment_currency'];
    $main_account_id = isset($_POST['main_account_id']) ? intval($_POST['main_account_id']) : null;
    $transaction_to_lower = strtolower(trim($transaction_to));

    if ($transaction_to_lower === 'bank' || $transaction_to_lower === 'internal account') {
        if (empty(trim((string) $receipt_number))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Receipt number is required for bank and internal account transactions']);
            exit;
        }
    }

    // Start a transaction
    $pdo->beginTransaction();

    try {
         // Step 1: Get the umrah booking details including currency and exchange rate
         $stmt_fetch_umrah_details = $pdo->prepare("SELECT paid_to, sold_to, received_bank_payment, currency as booking_currency FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
         $stmt_fetch_umrah_details->bindParam(1, $umrah_id, PDO::PARAM_INT);
         $stmt_fetch_umrah_details->bindParam(2, $tenant_id, PDO::PARAM_INT);
         $stmt_fetch_umrah_details->bindParam(3, $branch_id, PDO::PARAM_INT);
         $stmt_fetch_umrah_details->execute();
         $umrah_details = $stmt_fetch_umrah_details->fetch(PDO::FETCH_ASSOC);

         if (!$umrah_details) {
             throw new PDOException('Umrah booking details not found.');
         }

         $paid_to = $umrah_details['paid_to'];
         // If main_account_id is provided, use it instead of the default paid_to
         if ($main_account_id > 0) {
             $paid_to = $main_account_id;
         }
         $client_id = $umrah_details['sold_to'];
         $received_bank_payment = $umrah_details['received_bank_payment'];
         $booking_currency = $umrah_details['booking_currency'];

        // Get supplier_id from umrah_booking_services where service_type is 'all' or 'visa'
        $stmt_fetch_supplier_id = $pdo->prepare("SELECT supplier_id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ? AND service_type IN ('all', 'visa') LIMIT 1");
        $stmt_fetch_supplier_id->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_fetch_supplier_id->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_supplier_id->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_supplier_id->execute();
        $supplier_result = $stmt_fetch_supplier_id->fetch(PDO::FETCH_ASSOC);

        if (!$supplier_result) {
            throw new PDOException('Supplier not found for this booking.');
        }

        $supplier_id = $supplier_result['supplier_id'];

        // Step 2: Insert the transaction into umrah_transactions table
        $stmt = $pdo->prepare("INSERT INTO umrah_transactions (transaction_type, umrah_booking_id, payment_date, transaction_to, payment_description, payment_amount, currency, receipt, tenant_id, exchange_rate, branch_id) VALUES ('Credit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $payment_date, PDO::PARAM_STR);
        $stmt->bindParam(3, $transaction_to, PDO::PARAM_STR);
        $stmt->bindParam(4, $payment_description, PDO::PARAM_STR);
        $stmt->bindParam(5, $payment_amount, PDO::PARAM_STR);
        $stmt->bindParam(6, $currency, PDO::PARAM_STR);
        $stmt->bindParam(7, $receipt_number, PDO::PARAM_STR);
        $stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(9, $exchange_rate, PDO::PARAM_STR);
        $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new PDOException("Failed to add transaction");
        }

        // Get the inserted umrah transaction ID
        $umrah_transaction_id = $pdo->lastInsertId();

        // Fetch Supplier Type
        $stmt_fetch_supplier = $pdo->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_fetch_supplier->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_fetch_supplier->execute();
        $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);

        if (!$supplier_data) {
            throw new PDOException('Supplier details not found.');
        }

        $supplier_type = $supplier_data['supplier_type'];

        // Normalize $transaction_to to lowercase for case-insensitive comparison
        $transaction_type = 'Credit'; // Default transaction type for adding a transaction
        $client_transaction_type = 'credit';

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

                // Calculate new supplier balance
                $new_supplier_balance = $current_supplier_balance + $payment_amount;

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
                    (supplier_id, transaction_type, amount, remarks, transaction_of, reference_id, balance, transaction_date, receipt, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, 'umrah_transaction', ?, ?, NOW(), ?, ?, ?)");
                $stmt_insert_supplier_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_insert_supplier_transaction->bindParam(2, $transaction_type, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(4, $payment_description, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(5, $umrah_transaction_id, PDO::PARAM_INT);
                $stmt_insert_supplier_transaction->bindParam(6, $new_supplier_balance, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(7, $receipt_number, PDO::PARAM_STR);
                $stmt_insert_supplier_transaction->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $stmt_insert_supplier_transaction->bindParam(9, $branch_id, PDO::PARAM_INT);
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

                // Calculate new main account balance
                $new_main_balance = $current_main_balance + $payment_amount;

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
                    (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate, branch_id)
                    VALUES (?, ?, ?, ?, ?, 'umrah_transaction', ?, ?, NOW(), ?, ?, ?, ?)");
                $stmt_insert_main_account_transaction->bindParam(1, $paid_to, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(2, $transaction_type, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(4, $currency, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(5, $payment_description, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(6, $umrah_transaction_id, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(7, $new_main_balance, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(8, $receipt_number, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(9, $tenant_id, PDO::PARAM_INT);
                $stmt_insert_main_account_transaction->bindParam(10, $exchange_rate, PDO::PARAM_STR);
                $stmt_insert_main_account_transaction->bindParam(11, $branch_id, PDO::PARAM_INT);
                if (!$stmt_insert_main_account_transaction->execute()) {
                    throw new PDOException("Failed to record main account transaction.");
                }
            }

            // Update received_bank_payment in umrah_bookings
             $new_received_bank_payment = $received_bank_payment + $payment_amount;
             $stmt_update_umrah_booking = $pdo->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
             $stmt_update_umrah_booking->bindParam(1, $new_received_bank_payment, PDO::PARAM_STR);
             $stmt_update_umrah_booking->bindParam(2, $umrah_id, PDO::PARAM_INT);
             $stmt_update_umrah_booking->bindParam(3, $tenant_id, PDO::PARAM_INT);
             $stmt_update_umrah_booking->bindParam(4, $branch_id, PDO::PARAM_INT);
             if (!$stmt_update_umrah_booking->execute()) {
                 throw new PDOException('Failed to update received bank payment in umrah_bookings: ' . $stmt_update_umrah_booking->error);
             }

             // update bank receipt number
             $stmt_update_bank_receipt = $pdo->prepare("UPDATE umrah_bookings SET bank_receipt_number = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
             $stmt_update_bank_receipt->bindParam(1, $receipt_number, PDO::PARAM_STR);
             $stmt_update_bank_receipt->bindParam(2, $umrah_id, PDO::PARAM_INT);
             $stmt_update_bank_receipt->bindParam(3, $tenant_id, PDO::PARAM_INT);
             $stmt_update_bank_receipt->bindParam(4, $branch_id, PDO::PARAM_INT);
             if (!$stmt_update_bank_receipt->execute()) {
                 throw new PDOException('Failed to update bank receipt number in umrah_bookings: ' . $stmt_update_bank_receipt->error);
             }

             // Update client balance and record transaction for bank payments only if client type is regular
             if ($client_id > 0) {
                 // Check client type
                 $stmt_check_client_type = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                 $stmt_check_client_type->bindParam(1, $client_id, PDO::PARAM_INT);
                 $stmt_check_client_type->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmt_check_client_type->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmt_check_client_type->execute();
                 $client_type_result = $stmt_check_client_type->fetch(PDO::FETCH_ASSOC);
                 
                 if ($client_type_result && $client_type_result['client_type'] === 'regular') {
                     // Get current client balance based on currency
                     $balance_column = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
                     $stmt_get_client_balance = $pdo->prepare("SELECT $balance_column FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                     $stmt_get_client_balance->bindParam(1, $client_id, PDO::PARAM_INT);
                     $stmt_get_client_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                     $stmt_get_client_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                     $stmt_get_client_balance->execute();
                     $client_balance_result = $stmt_get_client_balance->fetch(PDO::FETCH_ASSOC);
                     
                     if ($client_balance_result) {
                         $current_client_balance = floatval($client_balance_result[$balance_column]);
                         // Deduct payment from client balance (they paid, so balance decreases)
                         $new_client_balance = $current_client_balance + $payment_amount;

                     // Update client balance
                     $update_query = ($currency === 'USD')
                         ? "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                         : "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmt_update_client = $pdo->prepare($update_query);
                     $stmt_update_client->bindParam(1, $new_client_balance, PDO::PARAM_STR);
                     $stmt_update_client->bindParam(2, $client_id, PDO::PARAM_INT);
                     $stmt_update_client->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmt_update_client->bindParam(4, $branch_id, PDO::PARAM_INT);
                     if (!$stmt_update_client->execute()) {
                         throw new PDOException('Failed to update client balance: ' . $stmt_update_client->error);
                     }
                     $client_transaction_of = 'umrah_transaction';
                     // Record transaction in client_transactions table
                     $stmt_insert_client_transaction = $pdo->prepare("INSERT INTO client_transactions
                         (client_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, exchange_rate, branch_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                     $stmt_insert_client_transaction->bindParam(1, $client_id, PDO::PARAM_INT);
                     $stmt_insert_client_transaction->bindParam(2, $client_transaction_type, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(4, $currency, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(5, $payment_description, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(6, $client_transaction_of, PDO::PARAM_INT);
                     $stmt_insert_client_transaction->bindParam(7, $umrah_transaction_id, PDO::PARAM_INT);
                     $stmt_insert_client_transaction->bindParam(8, $new_client_balance, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(9, $receipt_number, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(10, $tenant_id, PDO::PARAM_INT);
                     $stmt_insert_client_transaction->bindParam(11, $exchange_rate, PDO::PARAM_STR);
                     $stmt_insert_client_transaction->bindParam(12, $branch_id, PDO::PARAM_INT);
                     if (!$stmt_insert_client_transaction->execute()) {
                         throw new PDOException("Failed to record client transaction.");
                     }
                     }
                     }
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

            // Calculate new balance based on transaction type
            $new_main_balance = $current_main_balance + $payment_amount;

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
                (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, receipt, tenant_id, exchange_rate, branch_id)
                VALUES (?, ?, ?, ?, ?, 'umrah_transaction', ?, ?, NOW(), ?, ?, ?, ?)");
            $stmt_insert_main_account_transaction->bindParam(1, $paid_to, PDO::PARAM_INT);
            $stmt_insert_main_account_transaction->bindParam(2, $transaction_type, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(3, $payment_amount, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(4, $currency, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(5, $payment_description, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(6, $umrah_transaction_id, PDO::PARAM_INT);
            $stmt_insert_main_account_transaction->bindParam(7, $new_main_balance, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(8, $receipt_number, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(9, $tenant_id, PDO::PARAM_INT);
            $stmt_insert_main_account_transaction->bindParam(10, $exchange_rate, PDO::PARAM_STR);
            $stmt_insert_main_account_transaction->bindParam(11, $branch_id, PDO::PARAM_INT);
            if (!$stmt_insert_main_account_transaction->execute()) {
                throw new PDOException("Failed to record main account transaction.");
            }
        } else {
            throw new PDOException("Invalid transaction type: " . htmlspecialchars($transaction_to));
        }

        // Step 3: Calculate the total paid amount in the booking's base currency
        // First, get all transactions for this booking
        $stmt_get_transactions = $pdo->prepare("
            SELECT payment_amount, currency, exchange_rate
            FROM umrah_transactions
            WHERE umrah_booking_id = ? AND transaction_type = 'Credit' AND tenant_id = ? AND branch_id = ?
        ");
        $stmt_get_transactions->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_get_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_transactions->execute();
        $transactions = $stmt_get_transactions->fetchAll(PDO::FETCH_ASSOC);

        $total_paid_in_base_currency = 0;

        foreach ($transactions as $transaction) {
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

        // Update paid amount in umrah_bookings with the converted total
        $stmt_update_paid = $pdo->prepare("UPDATE umrah_bookings SET paid = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_update_paid->bindParam(1, $total_paid_in_base_currency, PDO::PARAM_STR);
        $stmt_update_paid->bindParam(2, $umrah_id, PDO::PARAM_INT);
        $stmt_update_paid->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt_update_paid->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$stmt_update_paid->execute()) {
            throw new PDOException('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
        }

        // Update due amount: due = sold_price - paid
        $stmt_update_due = $pdo->prepare("UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_update_due->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_update_due->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_update_due->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmt_update_due->execute()) {
            throw new PDOException('Failed to update due amount in umrah_bookings: ' . $stmt_update_due->error);
        }

        // Update family totals if booking is active
        $stmt_check_booking = $pdo->prepare("SELECT family_id, status FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_check_booking->bindParam(1, $umrah_id, PDO::PARAM_INT);
        $stmt_check_booking->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_check_booking->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_check_booking->execute();
        $booking_check = $stmt_check_booking->fetch(PDO::FETCH_ASSOC);
        
        if ($booking_check && $booking_check['status'] === 'active') {
            $family_id = $booking_check['family_id'];
            $stmt_update_family = $pdo->prepare("
                UPDATE families f
                SET
                    f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                    f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                    f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
                WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
            ");
            $stmt_update_family->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $family_id, $tenant_id, $branch_id]);
        }

        // Step 4: Get the supplier's name, applicant name, and base amount from umrah_bookings and suppliers
        $supplierStmt = $pdo->prepare("
            SELECT
                ub.booking_id AS umrah_id,
                ub.name,
                ub.sold_price,
                s.name AS supplier_name,
                ubs.supplier_id
            FROM umrah_bookings ub
            INNER JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND ubs.service_type IN ('all', 'visa')
            INNER JOIN suppliers s ON ubs.supplier_id = s.id
            WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
            LIMIT 1
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

        // Step 5: Add a notification for the admin with the correct umrah_transaction_id
        $notification_message = "Customer {$traveler_name} paid {$payment_amount} {$currency} to {$transaction_to} for the Umrah booking. Processed by {$username}.";
        if (!empty($receipt_number)) {
            $notification_message .= " Receipt: {$receipt_number}.";
        }

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

        // Commit the transaction
        $pdo->commit();

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

        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'umrah_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $umrah_transaction_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        // Return success response
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
