<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;
require_once '../../includes/db.php';

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate departureDate
$departureDate = isset($_POST['departureDate']) ? DbSecurity::validateInput($_POST['departureDate'], 'date') : null;

// Validate returnDate
$returnDate = isset($_POST['returnDate']) ? DbSecurity::validateInput($_POST['returnDate'], 'date') : null;

// Validate dateType (which date is being changed: departure, return, or both)
$dateType = isset($_POST['dateType']) ? DbSecurity::validateInput($_POST['dateType'], 'string', ['maxlength' => 50]) : 'departure';

// Validate service_penalty
$service_penalty = isset($_POST['service_penalty']) ? DbSecurity::validateInput($_POST['service_penalty'], 'float', ['min' => 0]) : null;

// Validate supplier_penalty
$supplier_penalty = isset($_POST['supplier_penalty']) ? DbSecurity::validateInput($_POST['supplier_penalty'], 'float', ['min' => 0]) : null;

// Validate sold
$sold = isset($_POST['sold']) ? DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]) : null;

// Validate base
$base = isset($_POST['base']) ? DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]) : null;

// Validate status
$status = isset($_POST['status']) ? DbSecurity::validateInput($_POST['status'], 'string', ['maxlength' => 255]) : null;

// Validate ticketId
$ticketId = isset($_POST['ticketId']) ? DbSecurity::validateInput($_POST['ticketId'], 'string', ['maxlength' => 255]) : null;

if (
    isset($_POST['ticketId'], $_POST['status'], $_POST['base'], $_POST['sold'], 
          $_POST['supplier_penalty'], $_POST['service_penalty'], $_POST['description'])
) {
    // Capture POST data
    $ticketId = $_POST['ticketId'];
    $status = $_POST['status'];
    $base = floatval($_POST['base']);
    $sold = floatval($_POST['sold']);
    $supplierPenalty = floatval($_POST['supplier_penalty']);
    $servicePenalty = floatval($_POST['service_penalty']);
    $newDepartureDate = isset($_POST['departureDate']) && !empty($_POST['departureDate']) ? $_POST['departureDate'] : null;
    $description = $_POST['description'];

    // Retrieve ticket data (ticket booking and supplier info)
    $stmt = $pdo->prepare("SELECT * FROM ticket_bookings WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $ticketId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ticketData) {
        $currency = $ticketData['currency']; // Currency from the ticket
        $supplierId = $ticketData['supplier']; // Supplier ID from the ticket booking
        $soldToId = $ticketData['sold_to'];
        $paidToId = $ticketData['paid_to'];
        $passengerName = $ticketData['passenger_name'];

        // Get client type (regular or agency)
        $clientStmt = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $clientStmt->bindParam(1, $soldToId, PDO::PARAM_INT);
        $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $clientStmt->execute();
        $clientData = $clientStmt->fetch(PDO::FETCH_ASSOC);
        $clientType = $clientData['client_type']; // Default to regular if not found
        $client_name = $clientData['name'];

        // Refund calculations based on client type
        if ($clientType === 'regular') {
            // Calculate refund based on sold amount for regular client
            $deductClient = $supplierPenalty + $servicePenalty;
        } else if ($clientType === 'agency') {
            // Logic for agency, if applicable
        } else {
            throw new Exception("Invalid client type.");
        }

        $pdo->beginTransaction(); // Start a transaction

        try {
            // 1. Update supplier balance (ensure foreign key validation)
            $deductSupplier = $supplierPenalty;

            // Check if supplier exists in suppliers table before proceeding
            $checkSupplierStmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $checkSupplierStmt->bindParam(1, $supplierId, PDO::PARAM_INT);
            $checkSupplierStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $checkSupplierStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $checkSupplierStmt->execute();
            $supplierExists = $checkSupplierStmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplierExists) {
                throw new Exception("Supplier with ID $supplierId does not exist.");
            }

            // 3. Insert date change record into date_change_tickets table
            $insertDateChangeStmt = $pdo->prepare("INSERT INTO date_change_tickets
                (tenant_id, supplier, sold_to, paid_to, ticket_id, title, passenger_name, pnr, origin, destination, return_origin, return_destination, phone, airline, gender,
                issue_date, departure_date, return_date, date_type, currency, base, sold, supplier_penalty, service_penalty,
                status, remarks, created_at, updated_at, created_by, branch_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");

            $insertDateChangeStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $insertDateChangeStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
            $insertDateChangeStmt->bindParam(3, $soldToId, PDO::PARAM_INT);
            $insertDateChangeStmt->bindParam(4, $paidToId, PDO::PARAM_INT);
            $insertDateChangeStmt->bindParam(5, $ticketId, PDO::PARAM_INT);
            $insertDateChangeStmt->bindParam(6, $ticketData['title'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(7, $ticketData['passenger_name'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(8, $ticketData['pnr'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(9, $ticketData['origin'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(10, $ticketData['destination'], PDO::PARAM_STR);
            
            // Bind return route (for round-trip tickets)
            $returnOrigin = $ticketData['return_origin'] ?? null;
            $returnDestination = $ticketData['return_destination'] ?? null;
            $insertDateChangeStmt->bindParam(11, $returnOrigin, $returnOrigin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(12, $returnDestination, $returnDestination === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            $insertDateChangeStmt->bindParam(13, $ticketData['phone'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(14, $ticketData['airline'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(15, $ticketData['gender'], PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(16, $ticketData['issue_date'], PDO::PARAM_STR);
            // Use existing departure_date if not provided (when only return_date is being changed)
            $departureDate = $newDepartureDate ?? $ticketData['departure_date'];
            $insertDateChangeStmt->bindParam(17, $departureDate, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(18, $returnDate, $returnDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(19, $dateType, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(20, $currency, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(21, $base, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(22, $sold, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(23, $supplierPenalty, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(24, $servicePenalty, PDO::PARAM_STR);
            // Validate status is one of the allowed enum values
            $allowedStatuses = ['Date Changed', 'Refunded', 'Booked'];
            $validStatus = (in_array($status, $allowedStatuses)) ? $status : 'Date Changed';
            $insertDateChangeStmt->bindParam(25, $validStatus, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(26, $description, PDO::PARAM_STR);
            $insertDateChangeStmt->bindParam(27, $user_id, PDO::PARAM_INT);
            $insertDateChangeStmt->bindParam(28, $branch_id, PDO::PARAM_INT);

            if (!$insertDateChangeStmt->execute()) {
                throw new Exception("Failed to insert date change record.");
            }
            $ticket_id = $pdo->lastInsertId();  // Get the inserted transaction ID

            if ($deductSupplier > 0) {
                // First check supplier type
                $supplierTypeStmt = $pdo->prepare("SELECT supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $supplierTypeStmt->bindParam(1, $supplierId, PDO::PARAM_INT);
                $supplierTypeStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $supplierTypeStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                if (!$supplierTypeStmt->execute()) {
                    throw new Exception("Failed to fetch supplier type.");
                }
                $supplierData = $supplierTypeStmt->fetch(PDO::FETCH_ASSOC);
                $supplierType = $supplierData['supplier_type'];
                $currentBalance = $supplierData['balance'];

                // Only update balance for regular suppliers
                if ($supplierType === 'External') {
                    $newBalance = $currentBalance - $deductSupplier;
                    
                    $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? And tenant_id = ? And branch_id = ?");
                    $updateSupplierStmt->bindParam(1, $deductSupplier, PDO::PARAM_STR);
                    $updateSupplierStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
                    $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    if (!$updateSupplierStmt->execute()) {
                        throw new Exception("Failed to update supplier balance.");
                    }

                    $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
                        (tenant_id, supplier_id, reference_id, transaction_type, transaction_of, amount, balance, remarks, transaction_date, branch_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

                    if (!$insertSupplierTransactionStmt) {
                        throw new Exception("Error preparing supplier transaction statement");
                    }

                    $transactionType = 'Debit';
                    $transactionOf = 'date_change';
                    $refundRemarks = "Penalty for ticket Name {$passengerName} date change deducted from account";


                    $insertSupplierTransactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(3, $ticket_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(4, $transactionType, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(5, $transactionOf, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(6, $deductSupplier, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(7, $newBalance, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(8, $refundRemarks, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);

                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new Exception("Failed to insert supplier transaction");
                    }

                    $transaction_id = $pdo->lastInsertId();
                } else {
                    // For non-regular suppliers, just record the transaction without balance
                    $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
                        (tenant_id, supplier_id, reference_id, transaction_type, transaction_of, amount, remarks, transaction_date, branch_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

                    if (!$insertSupplierTransactionStmt) {
                        throw new Exception("Error preparing supplier transaction statement");
                    }

                    $transactionType = 'Debit';
                    $transactionOf = 'date_change';
                    $refundRemarks = "Penalty for ticket Name {$passengerName} date change deducted from account";


                    $insertSupplierTransactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(3, $ticket_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(4, $transactionType, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(5, $transactionOf, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(6, $deductSupplier, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(7, $refundRemarks, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);

                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new Exception("Failed to insert supplier transaction");
                    }

                    $transaction_id = $pdo->lastInsertId();
                }
            }

            // 2. Refund to client (if regular or agency)
            if ($clientType === 'regular') {
                $balanceField = ($currency === 'USD') ? "usd_balance" : "afs_balance";
                $updateClientBalanceStmt = $pdo->prepare("UPDATE clients SET {$balanceField} = {$balanceField} - ? WHERE id = ? And tenant_id = ? And branch_id = ?");
                $penaltyAmount = $supplierPenalty + $servicePenalty; // Calculate refund amount
                $updateClientBalanceStmt->bindParam(1, $penaltyAmount, PDO::PARAM_STR);
                $updateClientBalanceStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
                $updateClientBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $updateClientBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                if (!$updateClientBalanceStmt->execute()) {
                    throw new Exception("Failed to update client balance.");
                }

                // Record refund transaction for the client
                $clientTransactionType = 'debit';
                $clientTransactionDescription = "Date Change for ticket {$ticketData['passenger_name']}.";
                
                // Get current balance based on currency
                $currentBalance = ($currency === 'USD') ? $clientData['usd_balance'] : $clientData['afs_balance'];
                $newBalance = $currentBalance - $penaltyAmount;
                
                $insertClientTransactionStmt = $pdo->prepare("INSERT INTO client_transactions
                    (tenant_id, client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'date_change', ?, NOW(), ?)");

                $insertClientTransactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                $insertClientTransactionStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
                $insertClientTransactionStmt->bindParam(3, $clientTransactionType, PDO::PARAM_STR);
                $insertClientTransactionStmt->bindParam(4, $penaltyAmount, PDO::PARAM_STR);
                $insertClientTransactionStmt->bindParam(5, $newBalance, PDO::PARAM_STR);
                $insertClientTransactionStmt->bindParam(6, $currency, PDO::PARAM_STR);
                $insertClientTransactionStmt->bindParam(7, $clientTransactionDescription, PDO::PARAM_STR);
                $insertClientTransactionStmt->bindParam(8, $ticket_id, PDO::PARAM_INT);
                $insertClientTransactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);

                if (!$insertClientTransactionStmt->execute()) {
                    throw new Exception("Failed to record refund transaction for client.");
                }
            }
            // 4. Update ticket status to "Date Changed"
            $updateTicketStatusStmt = $pdo->prepare("UPDATE ticket_bookings SET status = 'Date Changed' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateTicketStatusStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
            $updateTicketStatusStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $updateTicketStatusStmt->bindParam(3, $branch_id, PDO::PARAM_INT);

            if (!$updateTicketStatusStmt->execute()) {
                throw new Exception("Failed to update ticket status.");
            }

            // Add activity logging
            $user_id = $_SESSION["user_id"] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Prepare old values data
            $old_values = [
                'ticket_id' => $ticketId,
                'passenger_name' => $passengerName,
                'pnr' => $ticketData['pnr'],
                'base' => $base,
                'sold' => $sold,
                'supplier_penalty' => $supplierPenalty,
                'service_penalty' => $servicePenalty,
                'currency' => $currency,
                'status' => $status
            ];
            
            // Insert activity log
            $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
                (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id)
                VALUES (?, ?, 'add', 'date_change_tickets', ?, ?, '{}', ?, ?, NOW(), ?)");

            $old_values_json = json_encode($old_values);
            $activity_log_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $activity_log_stmt->bindParam(2, $user_id, PDO::PARAM_INT);
            $activity_log_stmt->bindParam(3, $ticket_id, PDO::PARAM_INT);
            $activity_log_stmt->bindParam(4, $old_values_json, PDO::PARAM_STR);
            $activity_log_stmt->bindParam(5, $ip_address, PDO::PARAM_STR);
            $activity_log_stmt->bindParam(6, $user_agent, PDO::PARAM_STR);
            $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
            $activity_log_stmt->execute();

            $pdo->commit(); // Commit the transaction
            echo 'success';
        } catch (Exception $e) {
            $pdo->rollback(); // Roll back the transaction in case of errors
            echo 'error: ' . $e->getMessage();
        }
    } else {
        echo 'ticket not found';
    }
} else {
    echo 'invalid parameters';
}
?>
