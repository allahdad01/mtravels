<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;
require_once '../includes/conn.php';

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate departureDate
$departureDate = isset($_POST['departureDate']) ? DbSecurity::validateInput($_POST['departureDate'], 'date') : null;

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

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (
    isset($_POST['ticketId'], $_POST['status'], $_POST['base'], $_POST['sold'], 
          $_POST['supplier_penalty'], $_POST['service_penalty'], $_POST['departureDate'],$_POST['description'])
) {
    // Capture POST data
    $ticketId = $_POST['ticketId'];
    $status = $_POST['status'];
    $base = floatval($_POST['base']);
    $sold = floatval($_POST['sold']);
    $supplierPenalty = floatval($_POST['supplier_penalty']);
    $servicePenalty = floatval($_POST['service_penalty']);
    $newDepartureDate = $_POST['departureDate'];
    $description = $_POST['description'];

    // Retrieve ticket data (ticket booking and supplier info)
    $stmt = $conn->prepare("SELECT * FROM ticket_bookings WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $ticketId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticketData = $result->fetch_assoc();

    if ($ticketData) {
        $currency = $ticketData['currency']; // Currency from the ticket
        $supplierId = $ticketData['supplier']; // Supplier ID from the ticket booking
        $soldToId = $ticketData['sold_to'];
        $paidToId = $ticketData['paid_to'];
        $passengerName = $ticketData['passenger_name'];

        // Get client type (regular or agency)
        $clientStmt = $conn->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ?");
        $clientStmt->bind_param("ii", $soldToId, $tenant_id); // Using sold_to ID to fetch client type
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $clientData = $clientResult->fetch_assoc();
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

        $conn->begin_transaction(); // Start a transaction

        try {
            // 1. Update supplier balance (ensure foreign key validation)
            $deductSupplier = $supplierPenalty;

            // Check if supplier exists in suppliers table before proceeding
            $checkSupplierStmt = $conn->prepare("SELECT id, name FROM suppliers WHERE id = ? AND tenant_id = ?");
            $checkSupplierStmt->bind_param("ii", $supplierId, $tenant_id);
            $checkSupplierStmt->execute();
            $checkSupplierStmt->store_result();

            if ($checkSupplierStmt->num_rows === 0) {
                throw new Exception("Supplier with ID $supplierId does not exist.");
            }

            // 3. Insert date change record into date_change_tickets table
            $insertDateChangeStmt = $conn->prepare("INSERT INTO date_change_tickets 
                (tenant_id, supplier, sold_to, paid_to, ticket_id, title, passenger_name, pnr, origin, destination, phone, airline, gender, 
                issue_date, departure_date, currency, base, sold, supplier_penalty, service_penalty,
                status, remarks, created_at, updated_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");

            $insertDateChangeStmt->bind_param(
                "iiiiissssssssssssssddss", 
                $tenant_id,
                $supplierId, $soldToId, $paidToId, $ticketId, $ticketData['title'],
                $ticketData['passenger_name'], $ticketData['pnr'], $ticketData['origin'], 
                $ticketData['destination'], $ticketData['phone'], $ticketData['airline'], 
                $ticketData['gender'], $ticketData['issue_date'], $newDepartureDate, $currency, 
                $base, $sold, $supplierPenalty, $servicePenalty, $status, $description, $user_id
            );

            if (!$insertDateChangeStmt->execute()) {
                throw new Exception("Failed to insert date change record.");
            }
            $ticket_id = $insertDateChangeStmt->insert_id;  // Get the inserted transaction ID

            if ($deductSupplier > 0) {
                // First check supplier type
                $supplierTypeStmt = $conn->prepare("SELECT supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ?");
                $supplierTypeStmt->bind_param("ii", $supplierId, $tenant_id);
                if (!$supplierTypeStmt->execute()) {
                    throw new Exception("Failed to fetch supplier type.");
                }
                $supplierResult = $supplierTypeStmt->get_result();
                $supplierData = $supplierResult->fetch_assoc();
                $supplierType = $supplierData['supplier_type'];
                $currentBalance = $supplierData['balance'];
                $supplierTypeStmt->close();

                // Only update balance for regular suppliers
                if ($supplierType === 'External') {
                    $newBalance = $currentBalance - $deductSupplier;
                    
                    $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
                    $updateSupplierStmt->bind_param("di", $deductSupplier, $supplierId);
                    if (!$updateSupplierStmt->execute()) {
                        throw new Exception("Failed to update supplier balance.");
                    }

                    $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
                        (tenant_id, supplier_id, reference_id, transaction_type, transaction_of, amount, balance, remarks, transaction_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                    if (!$insertSupplierTransactionStmt) {
                        throw new Exception("Error preparing supplier transaction statement: " . $conn->error);
                    }

                    $transactionType = 'Debit';
                    $transactionOf = 'date_change';
                    $refundRemarks = "Penalty for ticket Name {$passengerName} date change deducted from account";


                    $insertSupplierTransactionStmt->bind_param("iiissdss", 
                        $tenant_id,
                        $supplierId, $ticket_id, $transactionType, $transactionOf, $deductSupplier, $newBalance, $refundRemarks
                    );

                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new Exception("Failed to insert supplier transaction: " . $insertSupplierTransactionStmt->error);
                    }

                    $transaction_id = $insertSupplierTransactionStmt->insert_id;
                    $insertSupplierTransactionStmt->close();
                } else {
                    // For non-regular suppliers, just record the transaction without balance
                    $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
                        (tenant_id, supplier_id, reference_id, transaction_type, transaction_of, amount, remarks, transaction_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

                    if (!$insertSupplierTransactionStmt) {
                        throw new Exception("Error preparing supplier transaction statement: " . $conn->error);
                    }

                    $transactionType = 'Debit';
                    $transactionOf = 'date_change';
                    $refundRemarks = "Penalty for ticket Name {$passengerName} date change deducted from account";
                    

                    $insertSupplierTransactionStmt->bind_param("iissdss", 
                        $tenant_id,
                        $supplierId, $ticket_id, $transactionType, $transactionOf, $deductSupplier, $refundRemarks
                    );

                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new Exception("Failed to insert supplier transaction: " . $insertSupplierTransactionStmt->error);
                    }

                    $transaction_id = $insertSupplierTransactionStmt->insert_id;
                    $insertSupplierTransactionStmt->close();
                }
            }

            // 2. Refund to client (if regular or agency)
            if ($clientType === 'regular') {
                $balanceField = ($currency === 'USD') ? "usd_balance" : "afs_balance";
                $updateClientBalanceStmt = $conn->prepare("UPDATE clients SET {$balanceField} = {$balanceField} - ? WHERE id = ?");
                $penaltyAmount = $supplierPenalty + $servicePenalty; // Calculate refund amount
                $updateClientBalanceStmt->bind_param("di", $penaltyAmount, $soldToId);

                if (!$updateClientBalanceStmt->execute()) {
                    throw new Exception("Failed to update client balance.");
                }

                // Record refund transaction for the client
                $clientTransactionType = 'debit';
                $clientTransactionDescription = "Date Change for ticket {$ticketData['passenger_name']}.";
                
                // Get current balance based on currency
                $currentBalance = ($currency === 'USD') ? $clientData['usd_balance'] : $clientData['afs_balance'];
                $newBalance = $currentBalance - $penaltyAmount;
                
                $insertClientTransactionStmt = $conn->prepare("INSERT INTO client_transactions 
                    (tenant_id, client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'date_change', ?, NOW())");

                $insertClientTransactionStmt->bind_param(
                    "iisddsss", 
                    $tenant_id,
                    $soldToId,
                    $clientTransactionType,
                    $penaltyAmount,
                    $newBalance,
                    $currency,
                    $clientTransactionDescription,
                    $ticket_id
                );

                if (!$insertClientTransactionStmt->execute()) {
                    throw new Exception("Failed to record refund transaction for client.");
                }
            }
            // 4. Update ticket status to "Date Changed"
            $updateTicketStatusStmt = $conn->prepare("UPDATE ticket_bookings SET status = 'Date Changed' WHERE id = ? AND tenant_id = ?");
            $updateTicketStatusStmt->bind_param("ii", $ticketId, $tenant_id);

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
            $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
                (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
                VALUES (?, ?, 'add', 'date_change_tickets', ?, ?, '{}', ?, ?, NOW())");
            
            $old_values_json = json_encode($old_values);
            $activity_log_stmt->bind_param("iiisss", $tenant_id, $user_id, $ticket_id, $old_values_json, $ip_address, $user_agent);
            $activity_log_stmt->execute();
            $activity_log_stmt->close();

            $conn->commit(); // Commit the transaction
            echo 'success';
        } catch (Exception $e) {
            $conn->rollback(); // Roll back the transaction in case of errors
            echo 'error: ' . $e->getMessage();
        }

        // Close statements
        $stmt->close();
    } else {
        echo 'ticket not found';
    }

    $conn->close();
} else {
    echo 'invalid parameters';
}
?>
