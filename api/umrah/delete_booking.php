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
        $isActiveStatus = ($booking['status'] === 'active');

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

        // Step 2: Reverse Client Transactions (Only If Client is Regular AND status is active)
        if ($isActiveStatus && $client_type === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                   WHERE client_id = ? AND transaction_of = 'umrah'
                                   AND reference_id = ? AND tenant_id = ? AND branch_id = ?
                                   ORDER BY id ASC";
            $stmt = $pdo->prepare($clientTransactions);
            $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $clientResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clientResults as $row) {
                $amount = abs($row['amount']);
                $transaction_id = $row['id'];
                $transaction_type = $row['type'];

                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';

                // Update subsequent transactions BEFORE deleting
                if ($transaction_type == 'debit') {
                    $updateSubsequentBalances = "UPDATE client_transactions
                                                SET balance = balance + ?
                                                WHERE client_id = ?
                                                AND id > ?
                                                AND currency = ?
                                                AND tenant_id = ? AND branch_id = ?";
                } else {
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

                // Adjust Client Balance
                if ($transaction_type == 'debit') {
                    $adjustClientBalance = "UPDATE clients
                                           SET $clientBalanceField = $clientBalanceField + ?
                                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                } else {
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

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteClientTransaction);
                $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        } else if ($isActiveStatus && $client_type === 'agency') {
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

        // Step 3: Get all umrah transaction IDs for this booking
        $stmt_get_umrah_txn_ids = $pdo->prepare("
            SELECT id FROM umrah_transactions 
            WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt_get_umrah_txn_ids->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt_get_umrah_txn_ids->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_umrah_txn_ids->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_umrah_txn_ids->execute();
        $umrah_txn_ids = $stmt_get_umrah_txn_ids->fetchAll(PDO::FETCH_COLUMN);

        // Step 4: Reverse Supplier Transactions for all unique suppliers (only if status is active)
        if ($isActiveStatus) {
            $uniqueSuppliers = [];
            foreach ($services as $service) {
                $supplier_id = $service['supplier_id'];
                if (!isset($uniqueSuppliers[$supplier_id])) {
                    $uniqueSuppliers[$supplier_id] = $service['supplier_type'];
                }
            }

            foreach ($uniqueSuppliers as $supplier_id => $supplier_type) {
                // Fetch ALL supplier transactions related to this booking
                // Type 1: Initial booking transactions (reference_id = booking_id, transaction_of = 'umrah')
                // Type 2: Payment transactions (reference_id = umrah_transaction_id, transaction_of = 'umrah_transaction')
                $allSupplierTransactions = [];

                // Get Type 1: Initial booking transactions
                $supplierTransactions1 = "SELECT id, amount, transaction_type, transaction_date, 'umrah' as txn_type 
                                          FROM supplier_transactions
                                          WHERE supplier_id = ? AND transaction_of = 'umrah'
                                          AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($supplierTransactions1);
                $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                $supplierResults1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $allSupplierTransactions = array_merge($allSupplierTransactions, $supplierResults1);

                // Get Type 2: Payment transactions (if umrah_transactions exist)
                if (!empty($umrah_txn_ids)) {
                    $placeholders = implode(',', array_fill(0, count($umrah_txn_ids), '?'));
                    $supplierTransactions2 = "SELECT id, amount, transaction_type, transaction_date, 'umrah_transaction' as txn_type
                                              FROM supplier_transactions
                                              WHERE supplier_id = ? AND transaction_of = 'umrah_transaction'
                                              AND reference_id IN ($placeholders) 
                                              AND tenant_id = ? AND branch_id = ?";
                    $stmt = $pdo->prepare($supplierTransactions2);
                    
                    $param_index = 1;
                    $stmt->bindValue($param_index++, $supplier_id, PDO::PARAM_INT);
                    foreach ($umrah_txn_ids as $txn_id) {
                        $stmt->bindValue($param_index++, $txn_id, PDO::PARAM_INT);
                    }
                    $stmt->bindValue($param_index++, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindValue($param_index++, $branch_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $supplierResults2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $allSupplierTransactions = array_merge($allSupplierTransactions, $supplierResults2);
                }

                // Sort all transactions by ID to process in correct order
                usort($allSupplierTransactions, function($a, $b) {
                    return $a['id'] <=> $b['id'];
                });

                // Process supplier transactions based on supplier type
                if ($supplier_type === 'External') {
                    foreach ($allSupplierTransactions as $row) {
                        $amount = abs($row['amount']);
                        $transaction_id = $row['id'];
                        $trans_type = $row['transaction_type'];

                        // Update subsequent transactions BEFORE deleting
                        if ($trans_type == 'Credit') {
                            $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                                SET balance = balance - ?
                                                                WHERE supplier_id = ?
                                                                AND id > ?
                                                                AND tenant_id = ? AND branch_id = ?";
                        } else {
                            $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                                SET balance = balance + ?
                                                                WHERE supplier_id = ?
                                                                AND id > ?
                                                                AND tenant_id = ? AND branch_id = ?";
                        }

                        $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
                        $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                        $stmtUpdate->bindParam(2, $supplier_id, PDO::PARAM_INT);
                        $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                        $stmtUpdate->bindParam(4, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdate->bindParam(5, $branch_id, PDO::PARAM_INT);
                        $stmtUpdate->execute();

                        // Adjust Supplier Balance
                        if ($trans_type == 'Credit') {
                            $adjustSupplierBalance = "UPDATE suppliers
                                                      SET balance = balance - ?
                                                      WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        } else {
                            $adjustSupplierBalance = "UPDATE suppliers
                                                      SET balance = balance + ?
                                                      WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        }

                        $stmt = $pdo->prepare($adjustSupplierBalance);
                        $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                        $stmt->bindParam(2, $supplier_id, PDO::PARAM_INT);
                        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                        $stmt->execute();

                        // Delete Supplier Transaction
                        $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmt = $pdo->prepare($deleteSupplierTransaction);
                        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                } else if ($supplier_type === 'Internal') {
                    // For internal suppliers, just delete all transactions without balance adjustments
                    // Delete Type 1: Initial booking transactions
                    $deleteSupplierTransactions1 = "DELETE FROM supplier_transactions
                                                    WHERE supplier_id = ? AND transaction_of = 'umrah'
                                                    AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmt = $pdo->prepare($deleteSupplierTransactions1);
                    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmt->execute();

                    // Delete Type 2: Payment transactions (if any)
                    if (!empty($umrah_txn_ids)) {
                        $placeholders = implode(',', array_fill(0, count($umrah_txn_ids), '?'));
                        $deleteSupplierTransactions2 = "DELETE FROM supplier_transactions
                                                        WHERE supplier_id = ? AND transaction_of = 'umrah_transaction'
                                                        AND reference_id IN ($placeholders)
                                                        AND tenant_id = ? AND branch_id = ?";
                        $stmt = $pdo->prepare($deleteSupplierTransactions2);
                        
                        $param_index = 1;
                        $stmt->bindValue($param_index++, $supplier_id, PDO::PARAM_INT);
                        foreach ($umrah_txn_ids as $txn_id) {
                            $stmt->bindValue($param_index++, $txn_id, PDO::PARAM_INT);
                        }
                        $stmt->bindValue($param_index++, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindValue($param_index++, $branch_id, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                }
            }
        }

        // Handle main account transactions and balance updates
        if ($mainAccountId && $mainAccountId > 0) {
            // Fetch main account transactions for this booking
            $stmt_fetch_main_transactions = $pdo->prepare("
                SELECT mat.id, mat.amount, mat.type, mat.currency, mat.created_at
                FROM main_account_transactions mat
                JOIN umrah_transactions ut ON mat.reference_id = ut.id
                WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah_transaction'
                AND mat.tenant_id = ? AND mat.branch_id = ?
                ORDER BY mat.id ASC
            ");
            $stmt_fetch_main_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result_main_transactions as $main_transaction) {
                $main_amount = abs($main_transaction['amount']);
                $main_type = strtolower($main_transaction['type']);
                $main_currency = $main_transaction['currency'];
                $transaction_id = $main_transaction['id'];

                // Determine balance field
                $balance_field = match($main_currency) {
                    'USD' => 'usd_balance',
                    'AFS' => 'afs_balance',
                    'EUR' => 'euro_balance',
                    'DARHAM' => 'darham_balance',
                    default => throw new PDOException("Unsupported currency type: $main_currency")
                };

                // Update subsequent transactions BEFORE deleting
                if ($main_type === 'credit') {
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance - ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                } else {
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance + ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                }

                $update_subsequent_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(4, $main_currency, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(6, $branch_id, PDO::PARAM_INT);
                $update_subsequent_main->execute();

                // Update main account balance
                if ($main_type === 'credit') {
                    $stmt_update_main = $pdo->prepare("UPDATE main_account SET $balance_field = $balance_field - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                } else {
                    $stmt_update_main = $pdo->prepare("UPDATE main_account SET $balance_field = $balance_field + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                }

                $stmt_update_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $stmt_update_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $stmt_update_main->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_update_main->execute();
            }
        }

        // Delete main account transactions (using subquery instead of JOIN for better compatibility)
        if (!empty($umrah_txn_ids)) {
            $placeholders = implode(',', array_fill(0, count($umrah_txn_ids), '?'));
            $stmt_delete_main_transactions = $pdo->prepare("
                DELETE FROM main_account_transactions
                WHERE reference_id IN ($placeholders) 
                AND transaction_of = 'umrah_transaction'
                AND tenant_id = ? AND branch_id = ?
            ");
            
            $param_index = 1;
            foreach ($umrah_txn_ids as $txn_id) {
                $stmt_delete_main_transactions->bindValue($param_index++, $txn_id, PDO::PARAM_INT);
            }
            $stmt_delete_main_transactions->bindValue($param_index++, $tenant_id, PDO::PARAM_INT);
            $stmt_delete_main_transactions->bindValue($param_index++, $branch_id, PDO::PARAM_INT);
            $stmt_delete_main_transactions->execute();
        }

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
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST request.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id parameter.']);
    }
}
?>