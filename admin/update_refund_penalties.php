<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
// update_refund_penalties.php
require_once('../includes/db.php');
require_once('../includes/conn.php'); // Adding mysqli connection for compatibility

// Validate refund_amount
$refund_amount = isset($_POST['refund_amount']) ? DbSecurity::validateInput($_POST['refund_amount'], 'float', ['min' => 0]) : null;

// Validate service_penalty
$service_penalty = isset($_POST['service_penalty']) ? DbSecurity::validateInput($_POST['service_penalty'], 'float', ['min' => 0]) : null;

// Validate supplier_penalty
$supplier_penalty = isset($_POST['supplier_penalty']) ? DbSecurity::validateInput($_POST['supplier_penalty'], 'float', ['min' => 0]) : null;

// Validate ticket_id
$ticket_id = isset($_POST['ticket_id']) ? DbSecurity::validateInput($_POST['ticket_id'], 'int', ['min' => 0]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Get POST data
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $supplier_penalty = isset($_POST['supplier_penalty']) ? floatval($_POST['supplier_penalty']) : 0;
    $service_penalty = isset($_POST['service_penalty']) ? floatval($_POST['service_penalty']) : 0;
    $refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get original values to calculate differences
        $originalQuery = "SELECT rt.*, t.supplier, t.sold_to, t.currency 
                         FROM refunded_tickets rt
                         JOIN ticket_bookings t ON rt.ticket_id = t.id
                         WHERE rt.id = ? AND rt.tenant_id = ?";
        $stmtOriginal = $conn->prepare($originalQuery);
        $stmtOriginal->bind_param('ii', $ticket_id, $tenant_id);
        $stmtOriginal->execute();
        $resultOriginal = $stmtOriginal->get_result();
        $originalData = $resultOriginal->fetch_assoc();
        $stmtOriginal->close();
        
        if (!$originalData) {
            $response['message'] = 'Original refund data not found.';
            echo json_encode($response);
            exit;
        }
        
        // Calculate differences
        $supplierPenaltyDifference = $originalData['supplier_penalty'] - $supplier_penalty;
        $servicePenaltyDifference = $originalData['service_penalty'] - $service_penalty;
        $refundDifference = $originalData['refund_to_passenger'] - $refund_amount;
        
        $supplier_id = $originalData['supplier'];
        $client_id = $originalData['sold_to'];
        $currency = $originalData['currency'];
        
        // Handle supplier transactions if penalty changed
        if ($supplierPenaltyDifference != 0 && $supplier_id > 0) {
            // Check if supplier is external
            $supplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?";
            $stmtSupplier = $conn->prepare($supplierQuery);
            $stmtSupplier->bind_param('ii', $supplier_id, $tenant_id);
            $stmtSupplier->execute();
            $supplierResult = $stmtSupplier->get_result();
            $supplierData = $supplierResult->fetch_assoc();
            $stmtSupplier->close();
            
            $supplierType = isset($supplierData['supplier_type']) ? $supplierData['supplier_type'] : '';
            if (!$supplierType) {
                $supplierType = isset($supplierData['type']) ? $supplierData['type'] : '';
            }
            $isExternal = (strtolower(trim($supplierType)) === 'external');
            
            if ($isExternal) {
                // Get current supplier balance
                $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                $stmtGetCurrentSupplierBalance = $conn->prepare($getCurrentSupplierBalanceQuery);
                $stmtGetCurrentSupplierBalance->bind_param('ii', $supplier_id, $tenant_id);
                $stmtGetCurrentSupplierBalance->execute();
                $stmtGetCurrentSupplierBalance->bind_result($currentSupplierBalance);
                $stmtGetCurrentSupplierBalance->fetch();
                $stmtGetCurrentSupplierBalance->close();
                
                // If supplier penalty increased, supplier gets less money back (debit)
                // If supplier penalty decreased, supplier gets more money back (credit)
                if ($supplierPenaltyDifference > 0) {
                    // Penalty decreased, supplier gets more money back
                    $updateSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?";
                    $newSupplierBalance = $currentSupplierBalance + $supplierPenaltyDifference;
                    $transactionType = 'credit';
                } else {
                    // Penalty increased, supplier gets less money back
                    $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?";
                    $absPenaltyDiff = abs($supplierPenaltyDifference);
                    $newSupplierBalance = $currentSupplierBalance - $absPenaltyDiff;
                    $transactionType = 'debit';
                }
                
                // Update supplier balance
                $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                $absSupplierPenaltyDiff = abs($supplierPenaltyDifference);
                $stmtUpdateSupplier->bind_param('di', $absSupplierPenaltyDiff, $supplier_id, $tenant_id);
                $stmtUpdateSupplier->execute();
                $stmtUpdateSupplier->close();
                
                // Check if transaction exists for this refund
                $checkTransactionQuery = "SELECT id, transaction_date, balance, amount, transaction_type FROM supplier_transactions 
                                         WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'ticket_refund' AND tenant_id = ?";
                $stmtCheckTransaction = $conn->prepare($checkTransactionQuery);
                $stmtCheckTransaction->bind_param('iiii', $supplier_id, $ticket_id, $tenant_id);
                $stmtCheckTransaction->execute();
                $transactionResult = $stmtCheckTransaction->get_result();
                $existingTransaction = $transactionResult->fetch_assoc();
                $stmtCheckTransaction->close();
                
                // Get the base amount from the ticket_bookings
                $getBaseQuery = "SELECT price FROM ticket_bookings WHERE id = ? AND tenant_id = ?";
                $stmtGetBase = $conn->prepare($getBaseQuery);
                $stmtGetBase->bind_param('ii', $originalData['ticket_id'], $tenant_id);
                $stmtGetBase->execute();
                $stmtGetBase->bind_result($baseAmount);
                $stmtGetBase->fetch();
                $stmtGetBase->close();
                
                // Calculate the refund to supplier (base - supplier_penalty)
                $oldRefundToSupplier = $baseAmount - $originalData['supplier_penalty'];
                $newRefundToSupplier = $baseAmount - $supplier_penalty;
                $refundToSupplierDifference = $oldRefundToSupplier - $newRefundToSupplier;
                
                $remarks = "Updated supplier refund from {$oldRefundToSupplier} to {$newRefundToSupplier} (penalty changed from {$originalData['supplier_penalty']} to {$supplier_penalty})";
                
                if ($existingTransaction) {
                    // Get transaction details
                    $transactionId = $existingTransaction['id'];
                    $transactionDate = $existingTransaction['transaction_date'];
                    $currentTransactionBalance = $existingTransaction['balance'];
                    $currentTransactionAmount = $existingTransaction['amount'];
                    $existingTransactionType = $existingTransaction['transaction_type'];
                    
                    // Calculate the difference between the new refund amount and the current transaction amount
                    $amountDifference = $newRefundToSupplier - $currentTransactionAmount;
                    
                    // Calculate the new balance for this transaction
                    $newTransactionBalance = $currentTransactionBalance;
                    if ($refundToSupplierDifference > 0) {
                        // Refund to supplier decreased - balance should decrease
                        $newTransactionBalance = $currentTransactionBalance - abs($amountDifference);
                    } else if ($refundToSupplierDifference < 0) {
                        // Refund to supplier increased - balance should increase
                        $newTransactionBalance = $currentTransactionBalance + abs($amountDifference);
                    }
                    
                    // Update existing transaction - maintain the original transaction type
                    $updateTransactionQuery = "UPDATE supplier_transactions 
                                             SET amount = ?, balance = ?, remarks = CONCAT('Updated: ', ?) 
                                             WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateTransaction = $conn->prepare($updateTransactionQuery);
                    $stmtUpdateTransaction->bind_param('ddsi', $newRefundToSupplier, 
                                                   $newTransactionBalance, $remarks, $transactionId, $tenant_id);
                    $stmtUpdateTransaction->execute();
                    $stmtUpdateTransaction->close();
                    
                    // Update all subsequent transactions' balances
                    if ($amountDifference != 0) {
                        if ($amountDifference > 0) {
                            // New refund amount is higher than current transaction amount - increase subsequent balances
                            $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                           SET balance = balance + ? 
                                                           WHERE supplier_id = ? 
                                                           AND transaction_date > ? 
                                                           AND id != ?
                                                           AND tenant_id = ?";
                        } else {
                            // New refund amount is lower than current transaction amount - decrease subsequent balances
                            $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                           SET balance = balance - ? 
                                                           WHERE supplier_id = ? 
                                                           AND transaction_date > ? 
                                                           AND id != ?
                                                           AND tenant_id = ?";
                        }
                        
                        $stmtUpdateSubsequentSupplier = $conn->prepare($updateSubsequentSupplierQuery);
                        $absAmountDifference = abs($amountDifference);
                        $stmtUpdateSubsequentSupplier->bind_param('disi', $absAmountDifference, $supplier_id, $transactionDate, $transactionId, $tenant_id);
                        $stmtUpdateSubsequentSupplier->execute();
                        $stmtUpdateSubsequentSupplier->close();
                    }
                } else {
                    // Create new transaction record if one doesn't exist
                    $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions 
                        (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, tenant_id) 
                        VALUES (?, ?, ?, ?, ?, ?, 'ticket_refund', ?)";
                    $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                    $stmtInsertSupplierTransaction->bind_param('iisddsi', $supplier_id, $ticket_id, $transactionType, 
                                                         $newRefundToSupplier, $newSupplierBalance, $remarks, $tenant_id);
                    $stmtInsertSupplierTransaction->execute();
                    $stmtInsertSupplierTransaction->close();
                }
            }
        }
        
        // Handle client transactions if refund amount changed
        if ($refundDifference != 0 && $client_id > 0) {
            // Check if client is regular
            $clientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ?";
            $stmtClient = $conn->prepare($clientQuery);
            $stmtClient->bind_param('ii', $client_id, $tenant_id);
            $stmtClient->execute();
            $clientResult = $stmtClient->get_result();
            $clientData = $clientResult->fetch_assoc();
            $stmtClient->close();
            
            $clientType = isset($clientData['client_type']) ? $clientData['client_type'] : '';
            if (!$clientType) {
                $clientType = isset($clientData['type']) ? $clientData['type'] : '';
            }
            $isRegular = (strtolower(trim($clientType)) === 'regular');
            
            if ($isRegular) {
                // Determine which balance field to update based on currency
                $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                
                // Get current client balance
                $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ?";
                $stmtGetCurrentBalance = $conn->prepare($getCurrentBalanceQuery);
                $stmtGetCurrentBalance->bind_param('ii', $client_id, $tenant_id);
                $stmtGetCurrentBalance->execute();
                $stmtGetCurrentBalance->bind_result($currentBalance);
                $stmtGetCurrentBalance->fetch();
                $stmtGetCurrentBalance->close();
                
                // If refund increased, client gets more money back (debit)
                // If refund decreased, client gets less money back (credit)
                if ($refundDifference > 0) {
                    // Refund decreased, client gets less money back
                    $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?";
                    $newBalance = $currentBalance + $refundDifference;
                    $transactionType = 'credit';
                } else {
                    // Refund increased, client gets more money back
                    $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
                    $absRefundDiff = abs($refundDifference);
                    $newBalance = $currentBalance - $absRefundDiff;
                    $transactionType = 'debit';
                }
                
                // Update client balance
                $stmtUpdateClient = $conn->prepare($updateClientQuery);
                $absRefundDifference = abs($refundDifference);
                $stmtUpdateClient->bind_param('di', $absRefundDifference, $client_id, $tenant_id);
                $stmtUpdateClient->execute();
                $stmtUpdateClient->close();
                
                // Check if transaction exists for this refund
                $checkClientTransactionQuery = "SELECT id, created_at, balance, amount, type FROM client_transactions 
                                             WHERE client_id = ? AND reference_id = ? AND transaction_of = 'ticket_refund' AND tenant_id = ?";
                $stmtCheckClientTransaction = $conn->prepare($checkClientTransactionQuery);
                $stmtCheckClientTransaction->bind_param('iiii', $client_id, $ticket_id, $tenant_id);
                $stmtCheckClientTransaction->execute();
                $clientTransactionResult = $stmtCheckClientTransaction->get_result();
                $existingClientTransaction = $clientTransactionResult->fetch_assoc();
                $stmtCheckClientTransaction->close();
                
                $description = "Updated passenger refund from {$originalData['refund_to_passenger']} to {$refund_amount}";
                
                if ($existingClientTransaction) {
                    // Get transaction details
                    $transactionId = $existingClientTransaction['id'];
                    $transactionDate = $existingClientTransaction['created_at'];
                    $currentTransactionBalance = $existingClientTransaction['balance'];
                    $currentTransactionAmount = $existingClientTransaction['amount'];
                    $existingTransactionType = $existingClientTransaction['type'];
                    
                    // Calculate the difference between the new refund amount and the current transaction amount
                    $amountDifference = $refund_amount - $currentTransactionAmount;
                    
                    // Calculate the new balance for this transaction
                    $newTransactionBalance = $currentTransactionBalance;
                    if ($refundDifference > 0) {
                        // Refund decreased - balance should increase
                        $newTransactionBalance = $currentTransactionBalance + abs($amountDifference);
                    } else if ($refundDifference < 0) {
                        // Refund increased - balance should decrease
                        $newTransactionBalance = $currentTransactionBalance - abs($amountDifference);
                    }
                    
                    // Update existing transaction - maintain the original transaction type
                    $updateClientTransactionQuery = "UPDATE client_transactions 
                                                  SET amount = ?, balance = ?, description = CONCAT('Updated: ', ?) 
                                                  WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateClientTransaction = $conn->prepare($updateClientTransactionQuery);
                    $stmtUpdateClientTransaction->bind_param('ddsi', $refund_amount, 
                                                         $newTransactionBalance, $description, $transactionId, $tenant_id);
                    $stmtUpdateClientTransaction->execute();
                    $stmtUpdateClientTransaction->close();
                    
                    // Update all subsequent transactions' balances
                    if ($amountDifference != 0) {
                        if ($amountDifference > 0) {
                            // New refund amount is higher than current transaction amount - increase subsequent balances
                            $updateSubsequentQuery = "UPDATE client_transactions 
                                                   SET balance = balance + ? 
                                                   WHERE client_id = ? 
                                                   AND created_at > ? 
                                                   AND currency = ?
                                                   AND id != ?
                                                   AND tenant_id = ?";
                        } else {
                            // New refund amount is lower than current transaction amount - decrease subsequent balances
                            $updateSubsequentQuery = "UPDATE client_transactions 
                                                   SET balance = balance - ? 
                                                   WHERE client_id = ? 
                                                   AND created_at > ? 
                                                   AND currency = ?
                                                   AND id != ?
                                                   AND tenant_id = ?";
                        }
                        
                        $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                        $absAmountDifference = abs($amountDifference);
                        $stmtUpdateSubsequent->bind_param('dissi', $absAmountDifference, $client_id, $transactionDate, $currency, $transactionId, $tenant_id);
                        $stmtUpdateSubsequent->execute();
                        $stmtUpdateSubsequent->close();
                    }
                } else {
                    // Create new transaction record if one doesn't exist
                    $insertClientTransactionQuery = "INSERT INTO client_transactions 
                        (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'ticket_refund', ?)";
                    $stmtInsertClientTransaction = $conn->prepare($insertClientTransactionQuery);
                    $stmtInsertClientTransaction->bind_param('iisdsds', $client_id, $ticket_id, $transactionType, 
                                                       $refund_amount, $currency, $newBalance, $description, $tenant_id);
                    $stmtInsertClientTransaction->execute();
                    $stmtInsertClientTransaction->close();
                }
            }
        }
        
        // Update the refunded_tickets table
        $updateTicketQuery = "UPDATE refunded_tickets SET 
            supplier_penalty = ?,
            service_penalty = ?,
            refund_to_passenger = ?
            WHERE id = ? AND tenant_id = ?";
        
        $stmtTicket = $conn->prepare($updateTicketQuery);
        $stmtTicket->bind_param('dddi', $supplier_penalty, $service_penalty, $refund_amount, $ticket_id, $tenant_id);
        $stmtTicket->execute();
        $stmtTicket->close();
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'ticket_id' => $ticket_id,
            'supplier_penalty' => $originalData['supplier_penalty'],
            'service_penalty' => $originalData['service_penalty'],
            'refund_to_passenger' => $originalData['refund_to_passenger']
        ];
        
        // Prepare new values
        $new_values = [
            'supplier_penalty' => $supplier_penalty,
            'service_penalty' => $service_penalty,
            'refund_to_passenger' => $refund_amount
        ];
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bind_param("isisssssi", 
            $user_id, 
            'update', 
            'refunded_tickets', 
            $ticket_id, 
            json_encode($old_values), 
            json_encode($new_values), 
            $ip_address, 
            $user_agent,
            $tenant_id
        );
        $activity_log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Penalties updated successfully';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $response['message'] = 'Error updating penalties: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>