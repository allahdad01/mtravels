<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/conn.php';
require_once '../includes/db_security.php';

$tenant_id = $_SESSION['tenant_id'];

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
    // Start transaction
    $conn->begin_transaction();

    // Step 1: Fetch weight-related details and transactions
    $stmt_fetch = $conn->prepare("
        SELECT st.id AS transaction_id, st.amount, st.transaction_type, st.transaction_date, 
               s.currency, s.id AS supplier_id, s.supplier_type,
               t.sold_to, t.currency AS ticket_currency, t.paid_to
        FROM supplier_transactions st
        JOIN suppliers s ON st.supplier_id = s.id
        JOIN ticket_weights w ON st.reference_id = w.id
        JOIN ticket_bookings t ON w.ticket_id = t.id
        WHERE w.id = ? AND st.transaction_of = 'weight_sale' AND st.tenant_id = ?
    ");
    $stmt_fetch->bind_param("ii", $weightId, $tenant_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt_fetch->close();

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
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
            } elseif ($type === 'Debit') {
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
            } else {
                throw new Exception("Invalid transaction type for transaction ID $transaction_id.");
            }
            
            $stmt_update_supplier->bind_param("di", $amount, $supplier_id);
            if (!$stmt_update_supplier->execute()) {
                throw new Exception("Failed to reverse supplier balance for transaction ID $transaction_id.");
            }
            $stmt_update_supplier->close();
            
            // Update all subsequent transactions' running balances
            $updateSubsequentSupplierBalances = "UPDATE supplier_transactions 
                                               SET balance = balance " . ($type == 'Credit' ? '-' : '+') . " ? 
                                               WHERE supplier_id = ? AND transaction_date > ?
                                               ORDER BY transaction_date ASC";
            $stmtUpdate = $conn->prepare($updateSubsequentSupplierBalances);
            $stmtUpdate->bind_param("dis", $amount, $supplier_id, $transaction_date);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        // Handle main account transactions and balance updates
        if ($paid_to_id && $paid_to_id > 0) {
            // Fetch main account transactions for this weight
            $stmt_fetch_main_transactions = $conn->prepare("
                SELECT id, amount, type, currency, created_at
                FROM main_account_transactions 
                WHERE reference_id = ? AND transaction_of = 'weight' AND tenant_id = ?
            ");
            $stmt_fetch_main_transactions->bind_param("ii", $weightId, $tenant_id);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->get_result();
            
            while ($main_transaction = $result_main_transactions->fetch_assoc()) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                
                // Update main account balance based on transaction type
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for main account balance update.");
                    }
                    
                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance - ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        ORDER BY created_at ASC
                    ");
                } elseif ($main_type === 'debit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for main account balance update.");
                    }
                    
                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance + ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        ORDER BY created_at ASC
                    ");
                } else {
                    throw new Exception("Invalid transaction type for main account transaction.");
                }
                
                $stmt_update_main->bind_param("di", $main_amount, $paid_to_id);
                if (!$stmt_update_main->execute()) {
                    throw new Exception("Failed to update main account balance for transaction.");
                }
                $stmt_update_main->close();
                
                // Execute the update for subsequent transactions
                $update_subsequent_main->bind_param("diss", $main_amount, $paid_to_id, $transaction_date, $main_currency);
                if (!$update_subsequent_main->execute()) {
                    throw new Exception("Failed to update subsequent main account transaction balances.");
                }
                $update_subsequent_main->close();
            }
            $stmt_fetch_main_transactions->close();
        }

        // Step 3: Handle client transactions
        if ($client_id && $client_id > 0) {
            // Check client type
            $stmt_check_client = $conn->prepare("SELECT client_type FROM clients WHERE id = ?");
            $stmt_check_client->bind_param("i", $client_id);
            $stmt_check_client->execute();
            $result_client = $stmt_check_client->get_result();
            $client_type = $result_client->fetch_assoc()['client_type'];
            $stmt_check_client->close();

            // Process client transactions only for regular clients
            if ($client_type === 'regular') {
                $stmt_fetch_client_transaction = $conn->prepare("
                    SELECT id, amount, type, created_at
                    FROM client_transactions 
                    WHERE reference_id = ? AND client_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ?
                ");
                $stmt_fetch_client_transaction->bind_param("iii", $weightId, $client_id, $tenant_id);
                $stmt_fetch_client_transaction->execute();
                $result_client_transaction = $stmt_fetch_client_transaction->get_result();

                while ($row = $result_client_transaction->fetch_assoc()) {
                    $client_transaction_amount = $row['amount'];
                    $client_transaction_type = $row['type'];
                    $transaction_date = $row['created_at'];
                    
                    // Adjust Client Balance with Correct Reversal Logic
                    if ($ticket_currency === 'USD') {
                        $stmt_update_client = $conn->prepare("UPDATE clients SET usd_balance = usd_balance " . 
                                                           ($client_transaction_type == 'credit' ? '-' : '+') . 
                                                           " ? WHERE id = ?");
                    } elseif ($ticket_currency === 'AFS') {
                        $stmt_update_client = $conn->prepare("UPDATE clients SET afs_balance = afs_balance " . 
                                                           ($client_transaction_type == 'credit' ? '-' : '+') . 
                                                           " ? WHERE id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for client balance update.");
                    }
                    
                    $stmt_update_client->bind_param("di", $client_transaction_amount, $client_id);
                    if (!$stmt_update_client->execute()) {
                        throw new Exception("Failed to update client balance for client ID $client_id.");
                    }
                    $stmt_update_client->close();
                    
                    // Update all subsequent transactions' running balances
                    $updateSubsequentBalances = "UPDATE client_transactions 
                                               SET balance = balance " . ($client_transaction_type == 'credit' ? '-' : '+') . " ? 
                                               WHERE client_id = ? AND created_at > ? 
                                               AND currency = ?
                                               ORDER BY created_at ASC";
                    $stmtUpdate = $conn->prepare($updateSubsequentBalances);
                    $stmtUpdate->bind_param("diss", $client_transaction_amount, $client_id, $transaction_date, $ticket_currency);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
                $stmt_fetch_client_transaction->close();
            }
        }
    }

    // Step 4: Delete all transactions
    $stmt_delete_supplier = $conn->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ?");
    $stmt_delete_supplier->bind_param("ii", $weightId, $tenant_id);
    if (!$stmt_delete_supplier->execute()) {
        throw new Exception("Failed to delete supplier transactions.");
    }
    $stmt_delete_supplier->close();

    $stmt_delete_client = $conn->prepare("DELETE FROM client_transactions WHERE reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ?");
    $stmt_delete_client->bind_param("ii", $weightId, $tenant_id);
    if (!$stmt_delete_client->execute()) {
        throw new Exception("Failed to delete client transactions.");
    }
    $stmt_delete_client->close();

    $stmt_delete_main = $conn->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ?");
    $stmt_delete_main->bind_param("ii", $weightId, $tenant_id);
    if (!$stmt_delete_main->execute()) {
        throw new Exception("Failed to delete main account transactions.");
    }
    $stmt_delete_main->close();

    // Step 5: Delete the weight record
    $stmt = $conn->prepare("DELETE FROM ticket_weights WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $weightId, $tenant_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete weight record');
    }

    // Step 6: Log the activity
    $user_id = $_SESSION["user_id"] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $old_values = json_encode([
        'weight_id' => $weightId,
        'transactions' => $transactions
    ]);
    
    $stmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
        VALUES (?, ?, 'delete', 'ticket_weights', ?, ?, NULL, ?, ?, NOW())
    ");
    
    $stmt->bind_param("iisssi", $user_id, $tenant_id, $weightId, $old_values, $ip_address, $user_agent);
    if (!$stmt->execute()) {
        throw new Exception('Failed to log activity');
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Weight and associated transactions deleted successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
} 