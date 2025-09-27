<?php
session_start();

$username = isset($_SESSION["name"]) ? htmlspecialchars($_SESSION["name"]) : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;
$tenant_id = $_SESSION['tenant_id'];
// Database Connection
require_once '../includes/conn.php';
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error']);
    exit;
}

// Validate Input Parameters
if (!isset($_POST['ticketId'], $_POST['status'], $_POST['base'], $_POST['sold'], 
          $_POST['supplier_penalty'], $_POST['service_penalty'], $_POST['description'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// 1. CAPTURE POST DATA with proper validation
$ticketId = filter_input(INPUT_POST, 'ticketId', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
$base = filter_input(INPUT_POST, 'base', FILTER_VALIDATE_FLOAT);
$sold = filter_input(INPUT_POST, 'sold', FILTER_VALIDATE_FLOAT);
$supplierPenalty = filter_input(INPUT_POST, 'supplier_penalty', FILTER_VALIDATE_FLOAT);
$servicePenalty = filter_input(INPUT_POST, 'service_penalty', FILTER_VALIDATE_FLOAT);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
$calculationMethod = filter_input(INPUT_POST, 'calculationMethod', FILTER_SANITIZE_STRING);

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
$stmt = $conn->prepare("SELECT * FROM ticket_bookings WHERE id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $ticketId, $tenant_id);
if (!$stmt->execute()) {
    error_log("Query execution failed: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve ticket data']);
    exit;
}
$result = $stmt->get_result();
$ticketData = $result->fetch_assoc();

if (!$ticketData) {
    echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
    exit;
}

// Extract ticket details
$currency = $ticketData['currency'];
$supplierId = $ticketData['supplier'];
$soldToId = $ticketData['sold_to'];
$paidToId = $ticketData['paid_to'];

$conn->begin_transaction();

try {
    // 4. FETCH RELATED ENTITY DETAILS
    // 4.1 Supplier Details
    $stmt_check_balance = $conn->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt_check_balance->bind_param("ii", $supplierId, $tenant_id);
    if (!$stmt_check_balance->execute()) {
        throw new Exception("Failed to fetch supplier details");
    }
    $stmt_check_balance->bind_result($current_balance, $supplier_currency, $supplier_name, $supplier_type);
    $stmt_check_balance->fetch();
    $stmt_check_balance->close();

    // 4.2 Main Account Details
    $stmt_main_account = $conn->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ?");
    $stmt_main_account->bind_param("ii", $paidToId, $tenant_id);
    if (!$stmt_main_account->execute()) {
        throw new Exception("Failed to fetch main account details");
    }
    $stmt_main_account->bind_result($main_account_name);
    $stmt_main_account->fetch();
    $stmt_main_account->close();

    // 4.3 Client Details
    $clientQuery = $conn->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ?");
    $clientQuery->bind_param("ii", $soldToId, $tenant_id);
    if (!$clientQuery->execute()) {
        throw new Exception("Failed to fetch client details");
    }
    $clientTypeResult = $clientQuery->get_result()->fetch_assoc();
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
    $insertRefundStmt = $conn->prepare("INSERT INTO refunded_tickets 
        (tenant_id, supplier, sold_to, paid_to, ticket_id, title, passenger_name, pnr, origin, destination, phone, airline, gender, 
        issue_date, departure_date, currency, base, sold, supplier_penalty, service_penalty, refund_to_passenger, 
        status, remarks, created_at, updated_at, calculation_method, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
    $insertRefundStmt->bind_param(
        "iiiissssssssssssddddssssi",
        $tenant_id,
        $supplierId,
        $soldToId,
        $paidToId,
        $ticketId,
        $ticketData['title'],
        $ticketData['passenger_name'],
        $ticketData['pnr'],
        $ticketData['origin'],
        $ticketData['destination'],
        $ticketData['phone'],
        $ticketData['airline'],
        $ticketData['gender'],
        $ticketData['issue_date'],
        $ticketData['departure_date'],
        $currency,
        $base,
        $sold,
        $supplierPenalty,
        $servicePenalty,
        $refundToPassenger,
        $status,
        $description,
        $calculationMethod,
        $user_id
    );
    if (!$insertRefundStmt->execute()) {
        throw new Exception("Failed to insert refund record: " . $insertRefundStmt->error);
    }

    $ticket_id = $insertRefundStmt->insert_id;  // Get the inserted transaction ID

    // 6. PROCESS SUPPLIER REFUND
    if ($supplier_type === 'External') {
        // Calculate new balance - adding to a negative balance makes it less negative
        $newBalance = $current_balance + $refundToSupplier;
        
        // Update supplier balance
        $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
        $updateSupplierStmt->bind_param("dii", $newBalance, $supplierId, $tenant_id);
        if (!$updateSupplierStmt->execute()) {
            throw new Exception("Failed to update supplier balance: " . $updateSupplierStmt->error);
        }

        // Record supplier transaction with balance
        $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
            (tenant_id, transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of)
            VALUES (?, NOW(), ?, ?, ?, ?, 'credit', ?, 'ticket_refund')");
        $supplierRemarks = "Refund for ticket " . htmlspecialchars($ticketData['passenger_name']) . " added to account.";
        $insertSupplierTransactionStmt->bind_param("iiidds", 
            $tenant_id,
            $supplierId, $ticket_id, $refundToSupplier, $newBalance, $supplierRemarks);
    } else {
        // Record supplier transaction without balance
        $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions 
            (tenant_id, transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of)
            VALUES (?, NOW(), ?, ?, ?, 'credit', ?, 'ticket_refund')");
        $supplierRemarks = "Refund for ticket " . htmlspecialchars($ticketData['passenger_name']) . " added to account.";
        $insertSupplierTransactionStmt->bind_param("iiids", 
            $tenant_id,
            $supplierId, $ticket_id, $refundToSupplier, $supplierRemarks);
    }
    if (!$insertSupplierTransactionStmt->execute()) {
        throw new Exception("Failed to record supplier transaction: " . $insertSupplierTransactionStmt->error);
    }
    $transaction_id = $insertSupplierTransactionStmt->insert_id;

    // 7. PROCESS CLIENT REFUND
    if ($clientType === 'regular') {  // Only process for regular clients
        // Update client balance
        $balanceField = ($currency === 'USD') ? "usd_balance" : "afs_balance";
        $updateClientBalanceStmt = null;
        if ($currency === 'USD') {
            $updateClientBalanceStmt = $conn->prepare("UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
        } else {
            $updateClientBalanceStmt = $conn->prepare("UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
        }
        $updateClientBalanceStmt->bind_param("dii", $refundToPassenger, $soldToId, $tenant_id);
        if (!$updateClientBalanceStmt->execute()) {
            throw new Exception("Failed to update client balance: " . $updateClientBalanceStmt->error);
        }

        // Get current balance for transaction record
        $currentClientBalance = ($currency === 'USD') ? $clientTypeResult['usd_balance'] : $clientTypeResult['afs_balance'];
        $newClientBalance = $currentClientBalance + $refundToPassenger;

        // Record client transaction
        $insertClientTransactionStmt = $conn->prepare("INSERT INTO client_transactions 
            (tenant_id, client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at)
            VALUES (?, ?, 'Credit', ?, ?, ?, ?, 'ticket_refund', ?, NOW())");
        $clientTransactionDescription = "Refund for ticket " . htmlspecialchars($ticketData['passenger_name']) . ".";
        $insertClientTransactionStmt->bind_param("iiddssi", 
            $tenant_id,
            $soldToId, $refundToPassenger, $newClientBalance, $currency, $clientTransactionDescription, $ticket_id);
        if (!$insertClientTransactionStmt->execute()) {
            throw new Exception("Failed to record client transaction: " . $insertClientTransactionStmt->error);
        }
    }

    // 10. UPDATE TICKET STATUS
    $updateTicketStatusStmt = $conn->prepare("UPDATE ticket_bookings SET status = 'Refunded' WHERE id = ? AND tenant_id = ?");
    $updateTicketStatusStmt->bind_param("ii", $ticketId, $tenant_id);
    if (!$updateTicketStatusStmt->execute()) {
        throw new Exception("Failed to update ticket status: " . $updateTicketStatusStmt->error);
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
    $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
        (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
        VALUES (?, ?, 'add', 'refunded_tickets', ?, ?, '{}', ?, ?, NOW())");
    
    $old_values_json = json_encode($old_values);
    $activity_log_stmt->bind_param("iiisss", $tenant_id, $user_id, $ticket_id, $old_values_json, $ip_address, $user_agent);
    $activity_log_stmt->execute();
    $activity_log_stmt->close();

    $conn->commit();
     echo 'success';

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>
