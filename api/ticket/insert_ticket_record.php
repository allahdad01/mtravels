<?php
session_start();

$username = isset($_SESSION["name"]) ? htmlspecialchars($_SESSION["name"]) : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Database Connection
require_once '../../includes/db.php';
require_once '../../api/whatsapp/WhatsAppManager.php';

// Validate Input Parameters
if (!isset($_POST['ticketId'], $_POST['status'], $_POST['base'], $_POST['sold'], 
          $_POST['supplier_penalty'], $_POST['service_penalty'], $_POST['description'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// 1. CAPTURE POST DATA with proper validation
$ticketId = filter_input(INPUT_POST, 'ticketId', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$base = filter_input(INPUT_POST, 'base', FILTER_VALIDATE_FLOAT);
$sold = filter_input(INPUT_POST, 'sold', FILTER_VALIDATE_FLOAT);
$supplierPenalty = filter_input(INPUT_POST, 'supplier_penalty', FILTER_VALIDATE_FLOAT);
$servicePenalty = filter_input(INPUT_POST, 'service_penalty', FILTER_VALIDATE_FLOAT);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$calculationMethod = filter_input(INPUT_POST, 'calculationMethod', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Additional validation
if (!$ticketId || !$base || !$sold || $supplierPenalty === false || $servicePenalty === false) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input parameters']);
    exit;
}

// Default calculation method if not provided or invalid
if (!in_array($calculationMethod, ['base', 'sold'])) {
    $calculationMethod = 'base';
}

// 2. RETRIEVE TICKET DATA
$stmt = $pdo->prepare("SELECT * FROM ticket_bookings WHERE id = ? AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $ticketId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve ticket data']);
    exit;
}
$ticketData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticketData) {
    echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
    exit;
}

// Extract ticket details
$currency = $ticketData['currency'];
$supplierId = $ticketData['supplier'];
$soldToId = $ticketData['sold_to'];
$paidToId = $ticketData['paid_to'];

$pdo->beginTransaction();

try {
    // 4. FETCH RELATED ENTITY DETAILS
    // 4.1 Supplier Details
    $stmt_check_balance = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_check_balance->bindParam(1, $supplierId, PDO::PARAM_INT);
    $stmt_check_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_check_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt_check_balance->execute()) {
        throw new Exception("Failed to fetch supplier details");
    }
    $supplierData = $stmt_check_balance->fetch(PDO::FETCH_ASSOC);
    $current_balance = $supplierData['balance'];
    $supplier_currency = $supplierData['currency'];
    $supplier_name = $supplierData['name'];
    $supplier_type = $supplierData['supplier_type'];

    // 4.2 Main Account Details
    $stmt_main_account = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_main_account->bindParam(1, $paidToId, PDO::PARAM_INT);
    $stmt_main_account->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_main_account->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmt_main_account->execute()) {
        throw new Exception("Failed to fetch main account details");
    }
    $mainAccountData = $stmt_main_account->fetch(PDO::FETCH_ASSOC);
    $main_account_name = $mainAccountData['name'];

    // 4.3 Client Details
    $clientQuery = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $clientQuery->bindParam(1, $soldToId, PDO::PARAM_INT);
    $clientQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $clientQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$clientQuery->execute()) {
        throw new Exception("Failed to fetch client details");
    }
    $clientTypeResult = $clientQuery->fetch(PDO::FETCH_ASSOC);
    if (!$clientTypeResult) {
        throw new Exception("Client not found");
    }
    $clientType = $clientTypeResult['client_type'];
    $client_name = $clientTypeResult['name'];
    
    // 5. CALCULATE REFUNDS
    // Always calculate the refund to supplier from base price
    $refundToSupplier = $base - $supplierPenalty;
    
    // Calculate refund to passenger based on selected calculation method
    if ($calculationMethod === 'base') {
        $refundToPassenger = $base - ($supplierPenalty + $servicePenalty);
    } else { // 'sold'
        $refundToPassenger = $sold - ($supplierPenalty + $servicePenalty);
    }

    if ($refundToPassenger < 0) {
        throw new Exception("Refund amount cannot be negative.");
    }
    
    // 3. INSERT INTO REFUNDED_TICKETS
    $insertRefundStmt = $pdo->prepare("INSERT INTO refunded_tickets
        (tenant_id, supplier, sold_to, paid_to, ticket_id, title, passenger_name, pnr, origin, destination, phone, airline, gender,
        issue_date, departure_date, currency, base, sold, supplier_penalty, service_penalty, refund_to_passenger,
        remarks, created_at, updated_at, calculation_method, created_by, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)");
    $insertRefundStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(3, $soldToId, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(4, $paidToId, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(5, $ticketId, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(6, $ticketData['title'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(7, $ticketData['passenger_name'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(8, $ticketData['pnr'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(9, $ticketData['origin'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(10, $ticketData['destination'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(11, $ticketData['phone'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(12, $ticketData['airline'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(13, $ticketData['gender'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(14, $ticketData['issue_date'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(15, $ticketData['departure_date'], PDO::PARAM_STR);
    $insertRefundStmt->bindParam(16, $currency, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(17, $base, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(18, $sold, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(19, $supplierPenalty, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(20, $servicePenalty, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(21, $refundToPassenger, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(22, $description, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(23, $calculationMethod, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(24, $user_id, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(25, $branch_id, PDO::PARAM_INT);
    if (!$insertRefundStmt->execute()) {
        throw new Exception("Failed to insert refund record");
    }

    $ticket_id = $pdo->lastInsertId();  // Get the inserted transaction ID

    // 6. PROCESS SUPPLIER REFUND
    if ($supplier_type === 'External') {
        // Calculate new balance - adding to a negative balance makes it less negative
        $newBalance = $current_balance + $refundToSupplier;
        
        // Update supplier balance
        $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateSupplierStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
        $updateSupplierStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
        $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updateSupplierStmt->execute()) {
            throw new Exception("Failed to update supplier balance");
        }

        // Record supplier transaction with balance
        $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
            (tenant_id, transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, branch_id)
            VALUES (?, NOW(), ?, ?, ?, ?, 'credit', ?, 'ticket_refund', ?)");
        $supplierRemarks = "Refund for ticket " . htmlspecialchars($ticketData['passenger_name']) . " added to account.";
        $insertSupplierTransactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(3, $ticket_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(4, $refundToSupplier, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(5, $newBalance, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(6, $supplierRemarks, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    } else {
        // Record supplier transaction without balance
        $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
            (tenant_id, transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, branch_id)
            VALUES (?, NOW(), ?, ?, ?, 'credit', ?, 'ticket_refund', ?)");
        $supplierRemarks = "Refund for ticket " . htmlspecialchars($ticketData['passenger_name']) . " added to account.";
        $insertSupplierTransactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(3, $ticket_id, PDO::PARAM_INT);
        $insertSupplierTransactionStmt->bindParam(4, $refundToSupplier, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(5, $supplierRemarks, PDO::PARAM_STR);
        $insertSupplierTransactionStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    }
    if (!$insertSupplierTransactionStmt->execute()) {
        throw new Exception("Failed to record supplier transaction");
    }
    $transaction_id = $pdo->lastInsertId();

    // 7. PROCESS CLIENT REFUND
    if ($clientType === 'regular') {  // Only process for regular clients
        // Update client balance
        $balanceField = ($currency === 'USD') ? "usd_balance" : "afs_balance";
        $updateClientBalanceStmt = $pdo->prepare("UPDATE clients SET {$balanceField} = {$balanceField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateClientBalanceStmt->bindParam(1, $refundToPassenger, PDO::PARAM_STR);
        $updateClientBalanceStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
        $updateClientBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateClientBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updateClientBalanceStmt->execute()) {
            throw new Exception("Failed to update client balance");
        }

        // Get current balance for transaction record
        $currentClientBalance = ($currency === 'USD') ? $clientTypeResult['usd_balance'] : $clientTypeResult['afs_balance'];
        $newClientBalance = $currentClientBalance + $refundToPassenger;

        // Record client transaction
        $insertClientTransactionStmt = $pdo->prepare("INSERT INTO client_transactions
            (tenant_id, client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, branch_id)
            VALUES (?, ?, 'Credit', ?, ?, ?, ?, 'ticket_refund', ?, NOW(), ?)");
        $clientTransactionDescription = "Refund for ticket " . htmlspecialchars($ticketData['passenger_name']) . ".";
        $insertClientTransactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $insertClientTransactionStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
        $insertClientTransactionStmt->bindParam(3, $refundToPassenger, PDO::PARAM_STR);
        $insertClientTransactionStmt->bindParam(4, $newClientBalance, PDO::PARAM_STR);
        $insertClientTransactionStmt->bindParam(5, $currency, PDO::PARAM_STR);
        $insertClientTransactionStmt->bindParam(6, $clientTransactionDescription, PDO::PARAM_STR);
        $insertClientTransactionStmt->bindParam(7, $ticket_id, PDO::PARAM_INT);
        $insertClientTransactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        if (!$insertClientTransactionStmt->execute()) {
            throw new Exception("Failed to record client transaction");
        }
    }

    // 10. UPDATE TICKET STATUS
    $updateTicketStatusStmt = $pdo->prepare("UPDATE ticket_bookings SET status = 'Refunded' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateTicketStatusStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
    $updateTicketStatusStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $updateTicketStatusStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$updateTicketStatusStmt->execute()) {
        throw new Exception("Failed to update ticket status");
    }

    // Add activity logging
    $user_id = $_SESSION["user_id"] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare old values data
    $old_values = [
        'ticket_id' => $ticketId,
        'passenger_name' => $ticketData['passenger_name'],
        'pnr' => $ticketData['pnr'],
        'base' => $base,
        'sold' => $sold,
        'supplier_penalty' => $supplierPenalty,
        'service_penalty' => $servicePenalty,
        'currency' => $currency,
        'status' => $status,
        'description' => $description,
        'calculation_method' => $calculationMethod
    ];
    
    // Insert activity log
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
        (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id)
        VALUES (?, ?, 'add', 'refunded_tickets', ?, ?, '{}', ?, ?, NOW(), ?)");

    $old_values_json = json_encode($old_values);
    $activity_log_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(2, $user_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(3, $ticket_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(4, $old_values_json, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $activity_log_stmt->execute();

    $pdo->commit();

    // Send WhatsApp notification for refund ticket (if configured)
    try {
        $whatsappManager = new WhatsAppManager($tenant_id);
        $whatsapp_result = $whatsappManager->sendBookingNotification('refund_ticket', $ticket_id);
        
        if ($whatsapp_result['success']) {
            error_log("WhatsApp notification sent for Refund Ticket ID: $ticket_id");
        } else {
            error_log("WhatsApp notification failed for Refund Ticket ID: $ticket_id - " . $whatsapp_result['message']);
        }
    } catch (Exception $e) {
        // Don't fail the operation if WhatsApp fails
        error_log("WhatsApp integration error for Refund Ticket ID: $ticket_id - " . $e->getMessage());
    }

     echo 'success';

} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
