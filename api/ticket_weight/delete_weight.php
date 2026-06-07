<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/db.php';
require_once '../../admin/includes/db_security.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate weight ID
$weightId = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$weightId) {
    echo json_encode(['success' => false, 'message' => 'Weight ID is required']);
    exit;
}

try {
    // Check if weight has any associated main account transactions
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'weight' AND tenant_id = ? AND branch_id = ?");
    $stmt_check->bindParam(1, $weightId, PDO::PARAM_INT);
    $stmt_check->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_check->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_check->execute();
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This weight has associated main account transactions. Please delete the transactions first before deleting the weight.']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    // Step 1: Fetch weight-related details and transactions
    $stmt_fetch = $pdo->prepare("
        SELECT st.id AS transaction_id, st.amount, st.transaction_type, st.transaction_date,
               s.currency, s.id AS supplier_id, s.supplier_type,
               t.sold_to, t.currency AS ticket_currency, t.paid_to
        FROM supplier_transactions st
        JOIN suppliers s ON st.supplier_id = s.id AND s.tenant_id = ? AND s.branch_id = ?
        JOIN ticket_weights w ON st.reference_id = w.id AND w.tenant_id = ? AND w.branch_id = ?
        JOIN ticket_bookings t ON w.ticket_id = t.id AND t.tenant_id = ? AND t.branch_id = ?
        WHERE w.id = ? AND st.transaction_of = 'weight_sale' AND st.tenant_id = ? AND st.branch_id = ?
    ");
    $stmt_fetch->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(7, $weightId, PDO::PARAM_INT);
    $stmt_fetch->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(9, $branch_id, PDO::PARAM_INT);
    $stmt_fetch->execute();

    $transactions = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    // Step 2: Process supplier and main account reversals
    foreach ($transactions as $transaction) {
        $transaction_id = $transaction['transaction_id'];
        $amount = $transaction['amount'];
        $type = $transaction['transaction_type'];
        $transaction_date = $transaction['transaction_date'];
        $currency = $transaction['currency'];
        $supplier_id = $transaction['supplier_id'];
        $client_id = $transaction['sold_to'];
        $paid_to_id = $transaction['paid_to'];
        $ticket_currency = $transaction['ticket_currency'];
        $supplier_type = $transaction['supplier_type'];

        // Only reverse supplier's balance if supplier_type is External
        if ($supplier_type === 'External') {
            if ($type === 'Credit') {
                $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            } elseif ($type === 'Debit') {
                $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            } else {
                throw new PDOException("Invalid transaction type for transaction ID $transaction_id.");
            }

            $stmt_update_supplier->bindParam(1, $amount, PDO::PARAM_STR);
            $stmt_update_supplier->bindParam(2, $supplier_id, PDO::PARAM_INT);
            $stmt_update_supplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_supplier->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt_update_supplier->execute()) {
                throw new PDOException("Failed to reverse supplier balance for transaction ID $transaction_id.");
            }

            // Update all subsequent transactions' running balances
            $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                               SET balance = balance " . ($type == 'Credit' ? '-' : '+') . " ?
                                               WHERE supplier_id = ? AND transaction_date > ? AND tenant_id = ? AND branch_id = ?
                                               ORDER BY transaction_date ASC";
            $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
            $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
            $stmtUpdate->bindParam(2, $supplier_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(3, $transaction_date, PDO::PARAM_STR);
            $stmtUpdate->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(5, $branch_id, PDO::PARAM_INT);
            $stmtUpdate->execute();
        }

        // Handle main account transactions and balance updates
        if ($paid_to_id && $paid_to_id > 0) {
            // Fetch main account transactions for this weight
            $stmt_fetch_main_transactions = $pdo->prepare("
                SELECT id, amount, type, currency, created_at
                FROM main_account_transactions
                WHERE reference_id = ? AND transaction_of = 'weight' AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_fetch_main_transactions->bindParam(1, $weightId, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->execute();

            while ($main_transaction = $stmt_fetch_main_transactions->fetch(PDO::FETCH_ASSOC)) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];

                // Update main account balance based on transaction type
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    }  elseif ($main_currency === 'EUR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'DARHAM') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        throw new PDOException("Unsupported currency type for main account balance update.");
                    }

                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance - ?
                        WHERE main_account_id = ? AND created_at > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                        ORDER BY created_at ASC
                    ");
                } elseif ($main_type === 'debit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    }  elseif ($main_currency === 'EUR') {
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
                        WHERE main_account_id = ? AND created_at > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                        ORDER BY created_at ASC
                    ");
                } else {
                    throw new PDOException("Invalid transaction type for main account transaction.");
                }

                $stmt_update_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $stmt_update_main->bindParam(2, $paid_to_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_main->execute()) {
                    throw new PDOException("Failed to update main account balance for transaction.");
                }

                // Execute the update for subsequent transactions
                $update_subsequent_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(2, $paid_to_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(3, $transaction_date, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(4, $main_currency, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(6, $branch_id, PDO::PARAM_INT);
                if (!$update_subsequent_main->execute()) {
                    throw new PDOException("Failed to update subsequent main account transaction balances.");
                }
            }
        }

        // Step 3: Handle client transactions
        if ($client_id && $client_id > 0) {
            // Check client type
            $stmt_check_client = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_check_client->bindParam(1, $client_id, PDO::PARAM_INT);
            $stmt_check_client->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_check_client->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_check_client->execute();
            $client_type = $stmt_check_client->fetch(PDO::FETCH_ASSOC)['client_type'];

            // Process client transactions only for regular clients
            if ($client_type === 'regular') {
                $stmt_fetch_client_transaction = $pdo->prepare("
                    SELECT id, amount, type, created_at
                    FROM client_transactions
                    WHERE reference_id = ? AND client_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ? AND branch_id = ?
                ");
                $stmt_fetch_client_transaction->bindParam(1, $weightId, PDO::PARAM_INT);
                $stmt_fetch_client_transaction->bindParam(2, $client_id, PDO::PARAM_INT);
                $stmt_fetch_client_transaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_fetch_client_transaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_fetch_client_transaction->execute();

                while ($row = $stmt_fetch_client_transaction->fetch(PDO::FETCH_ASSOC)) {
                    $client_transaction_amount = $row['amount'];
                    $client_transaction_type = $row['type'];
                    $transaction_date = $row['created_at'];

                    // Adjust Client Balance with Correct Reversal Logic
                    if ($ticket_currency === 'USD') {
                        $stmt_update_client = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance " .
                                                           ($client_transaction_type == 'credit' ? '-' : '+') .
                                                           " ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($ticket_currency === 'AFS') {
                        $stmt_update_client = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance " .
                                                           ($client_transaction_type == 'credit' ? '-' : '+') .
                                                           " ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        throw new PDOException("Unsupported currency type for client balance update.");
                    }

                    $stmt_update_client->bindParam(1, $client_transaction_amount, PDO::PARAM_STR);
                    $stmt_update_client->bindParam(2, $client_id, PDO::PARAM_INT);
                    $stmt_update_client->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt_update_client->bindParam(4, $branch_id, PDO::PARAM_INT);
                    if (!$stmt_update_client->execute()) {
                        throw new PDOException("Failed to update client balance for client ID $client_id.");
                    }

                    // Update all subsequent transactions' running balances
                    $updateSubsequentBalances = "UPDATE client_transactions
                                               SET balance = balance " . ($client_transaction_type == 'credit' ? '-' : '+') . " ?
                                               WHERE client_id = ? AND created_at > ?
                                               AND currency = ? AND tenant_id = ? AND branch_id = ?
                                               ORDER BY created_at ASC";
                    $stmtUpdate = $pdo->prepare($updateSubsequentBalances);
                    $stmtUpdate->bindParam(1, $client_transaction_amount, PDO::PARAM_STR);
                    $stmtUpdate->bindParam(2, $client_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(3, $transaction_date, PDO::PARAM_STR);
                    $stmtUpdate->bindParam(4, $ticket_currency, PDO::PARAM_STR);
                    $stmtUpdate->bindParam(5, $tenant_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(6, $branch_id, PDO::PARAM_INT);
                    $stmtUpdate->execute();
                }
            }
        }
    }

    // Step 4: Delete all transactions
    $stmt_delete_supplier = $pdo->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_supplier->bindParam(1, $weightId, PDO::PARAM_INT);
    $stmt_delete_supplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_supplier->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt_delete_supplier->execute()) {
        throw new PDOException("Failed to delete supplier transactions.");
    }

    $stmt_delete_client = $pdo->prepare("DELETE FROM client_transactions WHERE reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_client->bindParam(1, $weightId, PDO::PARAM_INT);
    $stmt_delete_client->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_client->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt_delete_client->execute()) {
        throw new PDOException("Failed to delete client transactions.");
    }

    $stmt_delete_main = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'weight' AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_main->bindParam(1, $weightId, PDO::PARAM_INT);
    $stmt_delete_main->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_main->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt_delete_main->execute()) {
        throw new PDOException("Failed to delete main account transactions.");
    }

    // Step 5: Delete the weight record
    $stmt = $pdo->prepare("DELETE FROM ticket_weights WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $weightId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
        throw new PDOException('Failed to delete weight record');
    }

    // Step 6: Log the activity
    $user_id = $_SESSION["user_id"] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_values = json_encode([
        'weight_id' => $weightId,
        'transactions' => $transactions
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, tenant_id, branch_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, 'delete', 'ticket_weights', ?, ?, NULL, ?, ?, NOW())
    ");

    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $weightId, PDO::PARAM_INT);
    $stmt->bindParam(5, $old_values, PDO::PARAM_STR);
    $stmt->bindParam(6, $ip_address, PDO::PARAM_STR);
    $stmt->bindParam(7, $user_agent, PDO::PARAM_STR);
    if (!$stmt->execute()) {
        throw new PDOException('Failed to log activity');
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Weight and associated transactions deleted successfully']);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>