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

require_once('../../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;
// Accept both JSON and form data
$booking_id = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check for JSON data first
    $json_data = json_decode(file_get_contents("php://input"), true);
    if (isset($json_data['booking_id'])) {
        $booking_id = intval($json_data['booking_id']);
    }
    // If not found in JSON, check POST data
    else if (isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
    }
}

if ($booking_id !== null) {
    try {
        // Step 1: Fetch Booking Details (Including Client Type)
        $query = "SELECT ub.*, c.client_type FROM umrah_bookings ub
                  JOIN clients c ON ub.sold_to = c.id WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit();
        }

        $booking = $result;
        $client_id = $booking['sold_to'];
        $currency = $booking['currency'];
        $client_type = $booking['client_type'];
        $mainAccountId = $booking['paid_to'];

        // Get all services/suppliers for this booking
        $servicesQuery = "SELECT ubs.id as service_id, ubs.supplier_id, ubs.service_type, ubs.base_price, ubs.sold_price, ubs.profit, ubs.currency, s.supplier_type
                          FROM umrah_booking_services ubs
                          JOIN suppliers s ON ubs.supplier_id = s.id
                          WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ? AND s.tenant_id = ? AND s.branch_id = ?";
        $stmtServices = $pdo->prepare($servicesQuery);
        $stmtServices->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmtServices->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtServices->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmtServices->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmtServices->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmtServices->execute();
        $servicesResult = $stmtServices->fetchAll(PDO::FETCH_ASSOC);
        $services = $servicesResult;

        // Start Transaction
        $pdo->beginTransaction();

        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($client_type === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                   WHERE client_id = ? AND transaction_of = 'umrah'
                                   AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($clientTransactions);
            $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $clientResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clientResults as $row) {
                $amount = abs($row['amount']); // Ensure positive value
                $transaction_date = $row['created_at'];
                $transaction_id = $row['id'];
                $transaction_type = $row['type'];

                // Adjust Client Balance with Correct Reversal Logic
                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';

                // Get current client balance before update
                $getCurrentBalanceQuery = "SELECT $clientBalanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                $stmtGetCurrentBalance->bindParam(1, $client_id, PDO::PARAM_INT);
                $stmtGetCurrentBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmtGetCurrentBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmtGetCurrentBalance->execute();
                $currentBalanceResult = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC);
                $currentBalance = $currentBalanceResult[$clientBalanceField];

                // Using the same logic as update_visa.php:
                // If transaction was 'debit' (client owes more), we need to add that amount back (client owes less)
                // If transaction was 'credit' (client owes less), we need to subtract that amount (client owes more)
                if ($transaction_type == 'debit') {
                    // For debit transactions, add the amount back to client balance
                    $adjustClientBalance = "UPDATE clients
                                           SET $clientBalanceField = $clientBalanceField + ?
                                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                } else { // credit
                    // For credit transactions, subtract the amount from client balance
                    $adjustClientBalance = "UPDATE clients
                                           SET $clientBalanceField = $clientBalanceField - ?
                                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                }

                $stmt = $pdo->prepare($adjustClientBalance);
                $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $client_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();

                // Update all subsequent transactions' running balances
                // If the deleted transaction was a debit, we need to add that amount to all later transactions
                // If it was a credit, we need to subtract that amount from all later transactions
                if ($transaction_type == 'debit') {
                    $updateSubsequentBalances = "UPDATE client_transactions
                                                SET balance = balance + ?
                                                WHERE client_id = ?
                                                AND id > ?
                                                AND currency = ?
                                                AND tenant_id = ? AND branch_id = ?";
                } else { // credit
                    $updateSubsequentBalances = "UPDATE client_transactions
                                                SET balance = balance - ?
                                                WHERE client_id = ?
                                                AND id > ?
                                                AND currency = ?
                                                AND tenant_id = ? AND branch_id = ?";
                }

                $stmtUpdate = $pdo->prepare($updateSubsequentBalances);
                $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                $stmtUpdate->bindParam(2, $client_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(4, $currency, PDO::PARAM_STR);
                $stmtUpdate->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(6, $branch_id, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteClientTransaction);
                $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        } else if ($client_type === 'agency') {
            // For agency clients, just delete the transactions without balance adjustments
            $deleteClientTransactions = "DELETE FROM client_transactions
                                       WHERE client_id = ? AND transaction_of = 'umrah'
                                       AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($deleteClientTransactions);
            $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Step 3: Reverse Supplier Transactions for all unique suppliers
        $uniqueSuppliers = [];
        foreach ($services as $service) {
            $supplier_id = $service['supplier_id'];
            if (!isset($uniqueSuppliers[$supplier_id])) {
                $uniqueSuppliers[$supplier_id] = $service['supplier_type'];
            }
        }

        foreach ($uniqueSuppliers as $supplier_id => $supplier_type) {
            if ($supplier_type === 'External') {
                $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                       WHERE supplier_id = ? AND transaction_of = 'umrah'
                                       AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($supplierTransactions);
                $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                $supplierResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($supplierResults as $row) {
                    $amount = $row['amount'];
                    $transaction_date = $row['transaction_date'];
                    $transaction_id = $row['id'];

                    // Adjust Supplier Balance
                    $adjustSupplierBalance = "UPDATE suppliers
                                            SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                            WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmt = $pdo->prepare($adjustSupplierBalance);
                    $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                    $stmt->bindParam(2, $supplier_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmt->execute();

                    // Update all subsequent transactions' running balances
                    // If the deleted transaction was a Credit, we need to subtract that amount from all later transactions
                    // If it was a Debit, we need to add that amount to all later transactions
                    $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                      SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                                      WHERE supplier_id = ?
                                                      AND id > ?
                                                      AND tenant_id = ? AND branch_id = ?";
                    $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
                    $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                    $stmtUpdate->bindParam(2, $supplier_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(4, $tenant_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(5, $branch_id, PDO::PARAM_INT);
                    $stmtUpdate->execute();

                    // Delete Supplier Transaction
                    $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmt = $pdo->prepare($deleteSupplierTransaction);
                    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
            } else if ($supplier_type === 'Internal') {
                // For internal suppliers, just delete the transactions without balance adjustments
                $deleteSupplierTransactions = "DELETE FROM supplier_transactions
                                          WHERE supplier_id = ? AND transaction_of = 'umrah'
                                          AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteSupplierTransactions);
                $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        // Handle main account transactions and balance updates
        if ($mainAccountId && $mainAccountId > 0) {
            // Fetch main account transactions for this ticket
            $stmt_fetch_main_transactions = $pdo->prepare("
                SELECT mat.id, mat.amount, mat.type, mat.currency, mat.created_at
                FROM main_account_transactions mat
                JOIN umrah_transactions ut ON mat.reference_id = ut.id
                WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
                AND mat.tenant_id = ? AND branch_id = ?
            ");
            $stmt_fetch_main_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result_main_transactions as $main_transaction) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                $transaction_id = $main_transaction['id'];

                // Update main account balance based on transaction type
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'EUR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'DARHAM') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        throw new PDOException("Unsupported currency type for main account balance update.");
                    }

                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance - ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ?
                        AND branch_id = ?
                    ");
                } elseif ($main_type === 'debit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'EUR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'DARHAM') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        throw new PDOException("Unsupported currency type for main account balance update.");
                    }

                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance + ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ?
                        AND branch_id = ?
                    ");
                } else {
                    throw new PDOException("Invalid transaction type for main account transaction.");
                }

                $stmt_update_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $stmt_update_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $stmt_update_main->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_update_main->execute();

                // Execute the update for subsequent transactions
                $update_subsequent_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(4, $main_currency, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(6, $branch_id, PDO::PARAM_INT);
                $update_subsequent_main->execute();
            }
            $stmt_fetch_main_transactions->close();
        }

        // Delete main account transactions associated with this booking
        $stmt_delete_main_transactions = $pdo->prepare("
            DELETE mat FROM main_account_transactions mat
            JOIN umrah_transactions ut ON mat.reference_id = ut.id
            WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
            AND mat.tenant_id = ? AND branch_id = ?
        ");
        $stmt_delete_main_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt_delete_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_main_transactions->execute();

        // Delete Umrah transactions associated with this booking
        $stmt_delete_umrah_transactions = $pdo->prepare("DELETE FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_umrah_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt_delete_umrah_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_umrah_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_umrah_transactions->execute();

        // Delete booking services associated with this booking
        $stmt_delete_services = $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_services->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt_delete_services->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_services->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_services->execute();

        // Step 5: Delete the Booking Record
        $deleteBooking = "DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($deleteBooking);
        $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Commit Transaction
        $pdo->commit();

        // Log the activity
        $old_values = json_encode([
            'booking_id' => $booking_id,
            'client_id' => $client_id,
            'services' => $services,
            'paid_to' => $mainAccountId,
            'currency' => $currency,
            'client_type' => $client_type,
            'total_base_price' => $booking['price'],
            'total_sold_price' => $booking['sold_price'],
            'total_profit' => $booking['profit']
        ]);
        $new_values = json_encode([]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'umrah_bookings', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        echo json_encode(['success' => true, 'message' => 'Booking deleted successfully!']);
    } catch (PDOException $e) {
        $pdo->rollback(); // Roll back the transaction in case of errors
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // Improved error handling with more specific messages
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST request.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id parameter.']);
    }
}
?>
