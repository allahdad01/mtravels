<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;
// Accept both JSON and form data
$ticket_id = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check for JSON data first
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['id'])) {
        $ticket_id = intval($data['id']);
    }
    // If not found in JSON, check POST data
    else if (isset($_POST['id'])) {
        $ticket_id = intval($_POST['id']);
    }
}

// Check connection
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

if ($ticket_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid ticket ID."]);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Step 1: Fetch ticket-related details
    $stmt_fetch = $conn->prepare("
        SELECT st.id AS transaction_id, st.amount, st.transaction_type, st.transaction_date, s.currency, s.id AS supplier_id, 
               t.sold_to, t.sold, t.paid_to, t.currency AS ticket_currency, s.supplier_type
        FROM supplier_transactions st
        JOIN suppliers s ON st.supplier_id = s.id
        JOIN ticket_bookings t ON st.reference_id = t.id
        WHERE t.id = ? and st.transaction_of = 'ticket_sale' AND t.tenant_id = ?
    ");
    $stmt_fetch->bind_param("ii", $ticket_id, $tenant_id);
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
        $sold_amount = $transaction['sold'];
        $supplier_type = $transaction['supplier_type'];

        // Only reverse supplier's balance if supplier_type is External
        if ($supplier_type === 'External') {
            if ($type === 'Credit') {
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?");
            } elseif ($type === 'Debit') {
                $stmt_update_supplier = $conn->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?");
            } else {
                throw new Exception("Invalid transaction type for transaction ID $transaction_id.");
            }
            
            $stmt_update_supplier->bind_param("dii", $amount, $supplier_id, $tenant_id);
            if (!$stmt_update_supplier->execute()) {
                throw new Exception("Failed to reverse supplier balance for transaction ID $transaction_id.");
            }
            $stmt_update_supplier->close();
            
            // Update all subsequent transactions' running balances
            $updateSubsequentSupplierBalances = "UPDATE supplier_transactions 
                                                SET balance = balance " . ($type == 'Credit' ? '-' : '+') . " ? 
                                                WHERE supplier_id = ? AND transaction_date > ?
                                                AND tenant_id = ?
                                                ORDER BY transaction_date ASC";
            $stmtUpdate = $conn->prepare($updateSubsequentSupplierBalances);
            $stmtUpdate->bind_param("disi", $amount, $supplier_id, $transaction_date, $tenant_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        // Handle main account transactions and balance updates
        if ($paid_to_id && $paid_to_id > 0) {
            // Fetch main account transactions for this ticket
            $stmt_fetch_main_transactions = $conn->prepare("
                SELECT id, amount, type, currency, created_at
                FROM main_account_transactions 
                WHERE reference_id = ? AND transaction_of = 'ticket_sale'
                AND tenant_id = ?
            ");
            $stmt_fetch_main_transactions->bind_param("ii", $ticket_id, $tenant_id);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->get_result();
            
            while ($main_transaction = $result_main_transactions->fetch_assoc()) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                $transaction_id = $main_transaction['id'];
                
                // Update main account balance based on transaction type
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for main account balance update.");
                    }
                    
                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance - ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        AND tenant_id = ?
                        ORDER BY created_at ASC
                    ");
                } elseif ($main_type === 'debit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for main account balance update.");
                    }
                    
                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance + ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        AND tenant_id = ?
                        ORDER BY created_at ASC
                    ");
                } else {
                    throw new Exception("Invalid transaction type for main account transaction.");
                }
                
                $stmt_update_main->bind_param("dii", $main_amount, $paid_to_id, $tenant_id);
                if (!$stmt_update_main->execute()) {
                    throw new Exception("Failed to update main account balance for transaction.");
                }
                $stmt_update_main->close();
                
                // Execute the update for subsequent transactions
                $update_subsequent_main->bind_param("dissi", $main_amount, $paid_to_id, $transaction_date, $main_currency, $tenant_id);
                if (!$update_subsequent_main->execute()) {
                    throw new Exception("Failed to update subsequent main account transaction balances.");
                }
                $update_subsequent_main->close();
            }
            $stmt_fetch_main_transactions->close();
        }

        // Step 3: Fetch client transaction details for this ticket
        if ($client_id && $client_id > 0) {
            $stmt_fetch_client_transaction = $conn->prepare("
                SELECT id, amount, type, created_at
                FROM client_transactions 
                WHERE reference_id = ? AND client_id = ? and transaction_of = 'ticket_sale'
                AND tenant_id = ?
            ");
            $stmt_fetch_client_transaction->bind_param("iii", $ticket_id, $client_id, $tenant_id);
            $stmt_fetch_client_transaction->execute();
            $result_client_transaction = $stmt_fetch_client_transaction->get_result();

            // Check client type
            $stmt_check_client = $conn->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ?");
            $stmt_check_client->bind_param("ii", $client_id, $tenant_id);
            $stmt_check_client->execute();
            $result_client = $stmt_check_client->get_result();
            $client_type = $result_client->fetch_assoc()['client_type'];
            $stmt_check_client->close();

            // Add the client transaction amount back to the client's balance only for regular clients
            if ($client_type === 'regular') {
                while ($row = $result_client_transaction->fetch_assoc()) {
                    $client_transaction_amount = $row['amount'];
                    $client_transaction_type = $row['type'];
                    $transaction_date = $row['created_at'];
                    $transaction_id = $row['id'];
                    
                    // Adjust Client Balance with Correct Reversal Logic
                    if ($ticket_currency === 'USD') {
                        $stmt_update_client = $conn->prepare("UPDATE clients SET usd_balance = usd_balance " . 
                                                            ($client_transaction_type == 'credit' ? '-' : '+') . 
                                                            " ? WHERE id = ? AND tenant_id = ?");
                    } elseif ($ticket_currency === 'AFS') {
                        $stmt_update_client = $conn->prepare("UPDATE clients SET afs_balance = afs_balance " . 
                                                            ($client_transaction_type == 'credit' ? '-' : '+') . 
                                                            " ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for client balance update.");
                    }
                    
                    $stmt_update_client->bind_param("dii", $client_transaction_amount, $client_id, $tenant_id);
                    if (!$stmt_update_client->execute()) {
                        throw new Exception("Failed to update client balance for client ID $client_id.");
                    }
                    $stmt_update_client->close();
                    
                    // Update all subsequent transactions' running balances
                    $updateSubsequentBalances = "UPDATE client_transactions 
                                                SET balance = balance " . ($client_transaction_type == 'credit' ? '-' : '+') . " ? 
                                                WHERE client_id = ? AND created_at > ? 
                                                AND currency = ?
                                                AND tenant_id = ?
                                                ORDER BY created_at ASC";
                    $stmtUpdate = $conn->prepare($updateSubsequentBalances);
                    $stmtUpdate->bind_param("dissi", $client_transaction_amount, $client_id, $transaction_date, $ticket_currency, $tenant_id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
            }
            $stmt_fetch_client_transaction->close();
        }
    }

    // Step 4: Delete all supplier transactions associated with this ticket
    $stmt_delete_transactions = $conn->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? and transaction_of = 'ticket_sale' AND tenant_id = ?");
    $stmt_delete_transactions->bind_param("ii", $ticket_id, $tenant_id);
    if (!$stmt_delete_transactions->execute()) {
        throw new Exception("Failed to delete supplier transactions associated with ticket ID $ticket_id.");
    }
    $stmt_delete_transactions->close();
    
    $stmt_delete_transactions = $conn->prepare("DELETE FROM client_transactions WHERE reference_id = ? and transaction_of = 'ticket_sale' AND tenant_id = ?");
    $stmt_delete_transactions->bind_param("ii", $ticket_id, $tenant_id);
    if (!$stmt_delete_transactions->execute()) {
        throw new Exception("Failed to delete client transactions associated with ticket ID $ticket_id.");
    }
    $stmt_delete_transactions->close();

    // Delete main account transactions associated with this ticket
    $stmt_delete_main_transactions = $conn->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'ticket_sale' AND tenant_id = ?");
    $stmt_delete_main_transactions->bind_param("ii", $ticket_id, $tenant_id);
    if (!$stmt_delete_main_transactions->execute()) {
        throw new Exception("Failed to delete main account transactions associated with ticket ID $ticket_id.");
    }
    $stmt_delete_main_transactions->close();

    // Step 5: Delete the ticket
    $stmt_delete_ticket = $conn->prepare("DELETE FROM ticket_bookings WHERE id = ? AND tenant_id = ?");
    $stmt_delete_ticket->bind_param("ii", $ticket_id, $tenant_id);
    if (!$stmt_delete_ticket->execute()) {
        throw new Exception("Failed to delete ticket ID $ticket_id.");
    }
    $stmt_delete_ticket->close();

    // Commit transaction
    $conn->commit();
    
    // Log the activity
    $old_values = json_encode([
        'ticket_id' => $ticket_id,
        'client_id' => $client_id,
        'supplier_id' => $supplier_id,
        'paid_to_id' => $paid_to_id,
        'ticket_currency' => $ticket_currency
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'delete', 'ticket_bookings', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bind_param("iissssi", $user_id, $ticket_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    echo json_encode(["success" => true, "message" => "Ticket and associated transactions deleted successfully, balances adjusted."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
