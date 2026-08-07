<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

if ($ticket_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid ticket ID."]);
    exit();
}

// Check if ticket reserve has any associated main account transactions
$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ? AND branch_id = ?");
$stmt_check->bindParam(1, $ticket_id, PDO::PARAM_INT);
$stmt_check->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt_check->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt_check->execute();
if ($stmt_check->fetchColumn() > 0) {
    echo json_encode(["success" => false, "message" => "This ticket reserve has associated main account transactions. Please delete the transactions first before deleting the ticket reserve."]);
    exit();
}

// Start transaction
$pdo->beginTransaction();

try {
    // Step 1: Fetch ticket-related details
    $stmt_fetch = $pdo->prepare("
        SELECT st.id AS transaction_id, st.amount, st.transaction_type, st.transaction_date, s.currency, s.id AS supplier_id,
               t.sold_to, t.sold, t.paid_to, t.currency AS ticket_currency, s.supplier_type
        FROM supplier_transactions st
        JOIN suppliers s ON st.supplier_id = s.id
        JOIN ticket_reservations t ON st.reference_id = t.id
        WHERE t.id = ? and st.transaction_of = 'ticket_reserve' AND t.tenant_id = ? AND t.branch_id = ?
    ");
    $stmt_fetch->bindParam(1, $ticket_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $transactions = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

    if (empty($transactions)) {
        throw new PDOException("No transactions found for this ticket.");
    }

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
                $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? And tenant_id = ? AND branch_id = ?");
            } elseif ($type === 'Debit') {
                $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? And tenant_id = ? AND branch_id = ?");
            } else {
                throw new PDOException("Invalid transaction type for transaction ID $transaction_id.");
            }

            $stmt_update_supplier->bindParam(1, $amount, PDO::PARAM_STR);
            $stmt_update_supplier->bindParam(2, $supplier_id, PDO::PARAM_INT);
            $stmt_update_supplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_supplier->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt_update_supplier->execute();

            // Update all subsequent transactions' running balances
            $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                SET balance = balance " . ($type == 'Credit' ? '-' : '+') . " ?
                                                WHERE supplier_id = ?
                                                AND id > ? AND tenant_id = ? AND branch_id = ?";
            $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
            $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
            $stmtUpdate->bindParam(2, $supplier_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(5, $branch_id, PDO::PARAM_INT);
            $stmtUpdate->execute();
        }

        // Handle main account transactions and balance updates
        if ($paid_to_id && $paid_to_id > 0) {
            // Fetch main account transactions for this ticket
            $stmt_fetch_main_transactions = $pdo->prepare("
                SELECT id, amount, type, currency, created_at
                FROM main_account_transactions
                WHERE reference_id = ? AND transaction_of = 'ticket_reserve'
                AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_fetch_main_transactions->bindParam(1, $ticket_id, PDO::PARAM_INT);
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
                    } elseif ($main_currency === 'SAR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET sar_balance = sar_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
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
                        AND tenant_id = ? AND branch_id = ?
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
                    } elseif ($main_currency === 'SAR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET sar_balance = sar_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
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
                        AND tenant_id = ? AND branch_id = ?
                    ");
                } else {
                    throw new PDOException("Invalid transaction type for main account transaction.");
                }

                $stmt_update_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $stmt_update_main->bindParam(2, $paid_to_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_update_main->execute();

                // Execute the update for subsequent transactions
                $update_subsequent_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(2, $paid_to_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(4, $main_currency, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(6, $branch_id, PDO::PARAM_INT);
                $update_subsequent_main->execute();
            }
        }

        // Step 3: Fetch client transaction details for this ticket
        if ($client_id && $client_id > 0) {
            $stmt_fetch_client_transaction = $pdo->prepare("
                SELECT id, amount, type, created_at
                FROM client_transactions
                WHERE reference_id = ? AND client_id = ? and transaction_of = 'ticket_reserve'
                AND tenant_id = ? And branch_id = ?
            ");
            $stmt_fetch_client_transaction->bindParam(1, $ticket_id, PDO::PARAM_INT);
            $stmt_fetch_client_transaction->bindParam(2, $client_id, PDO::PARAM_INT);
            $stmt_fetch_client_transaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_client_transaction->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_client_transaction->execute();
            $result_client_transaction = $stmt_fetch_client_transaction->fetchAll(PDO::FETCH_ASSOC);

            // Check client type
            $stmt_check_client = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_check_client->bindParam(1, $client_id, PDO::PARAM_INT);
            $stmt_check_client->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_check_client->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_check_client->execute();
            $result_client = $stmt_check_client->fetch(PDO::FETCH_ASSOC);
            $client_type = $result_client['client_type'];

            // Add the client transaction amount back to the client's balance only for regular clients
            if ($client_type === 'regular') {
                foreach ($result_client_transaction as $row) {
                    $client_transaction_amount = $row['amount'];
                    $client_transaction_type = $row['type'];
                    $transaction_date = $row['created_at'];
                    $transaction_id = $row['id'];

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
                    $stmt_update_client->execute();

                    // Update all subsequent transactions' running balances
                    $updateSubsequentBalances = "UPDATE client_transactions
                                                SET balance = balance " . ($client_transaction_type == 'credit' ? '-' : '+') . " ?
                                                WHERE client_id = ?
                                                AND id > ?
                                                AND currency = ?
                                                AND tenant_id = ? AND branch_id = ?";
                    $stmtUpdate = $pdo->prepare($updateSubsequentBalances);
                    $stmtUpdate->bindParam(1, $client_transaction_amount, PDO::PARAM_STR);
                    $stmtUpdate->bindParam(2, $client_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(4, $ticket_currency, PDO::PARAM_STR);
                    $stmtUpdate->bindParam(5, $tenant_id, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(6, $branch_id, PDO::PARAM_INT);
                    $stmtUpdate->execute();
                }
            }
        }
    }

    // Step 4: Delete all supplier transactions associated with this ticket
    $stmt_delete_transactions = $pdo->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? and transaction_of = 'ticket_reserve' AND tenant_id = ? And branch_id = ?");
    $stmt_delete_transactions->bindParam(1, $ticket_id, PDO::PARAM_INT);
    $stmt_delete_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_delete_transactions->execute();

    $stmt_delete_transactions = $pdo->prepare("DELETE FROM client_transactions WHERE reference_id = ? and transaction_of = 'ticket_reserve' AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_transactions->bindParam(1, $ticket_id, PDO::PARAM_INT);
    $stmt_delete_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_delete_transactions->execute();

    // Delete main account transactions associated with this ticket
    $stmt_delete_main_transactions = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_main_transactions->bindParam(1, $ticket_id, PDO::PARAM_INT);
    $stmt_delete_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_delete_main_transactions->execute();

    // Step 5: Delete the ticket
    $stmt_delete_ticket = $pdo->prepare("DELETE FROM ticket_reservations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_ticket->bindParam(1, $ticket_id, PDO::PARAM_INT);
    $stmt_delete_ticket->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_ticket->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_delete_ticket->execute();

    // Commit transaction
    $pdo->commit();

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

    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'ticket_reservations', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt_log->bindParam(2, $ticket_id, PDO::PARAM_INT);
    $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt_log->execute();

    echo json_encode(["success" => true, "message" => "Ticket and associated transactions deleted successfully, balances adjusted."]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
