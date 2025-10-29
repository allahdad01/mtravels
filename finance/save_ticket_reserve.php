<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();


$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;

// Establish a connection to the MySQL database
include '../includes/conn.php';


// Validate returnDate
$returnDate = isset($_POST['returnDate']) ? DbSecurity::validateInput($_POST['returnDate'], 'date') : null;

// Validate returnDestination
$returnDestination = isset($_POST['returnDestination']) ? DbSecurity::validateInput($_POST['returnDestination'], 'string', ['maxlength' => 255]) : null;

// Validate tripType
$tripType = isset($_POST['tripType']) ? DbSecurity::validateInput($_POST['tripType'], 'string', ['maxlength' => 255]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate pro
$pro = isset($_POST['pro']) ? DbSecurity::validateInput($_POST['pro'], 'float', ['min' => 0]) : null;

// Validate sold
$sold = isset($_POST['sold']) ? DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]) : null;

// Validate base
$base = isset($_POST['base']) ? DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]) : null;

// Validate curr
$curr = isset($_POST['curr']) ? DbSecurity::validateInput($_POST['curr'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;

// Validate gender
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate issueDate
$issueDate = isset($_POST['issueDate']) ? DbSecurity::validateInput($_POST['issueDate'], 'date') : null;

// Validate departureDate
$departureDate = isset($_POST['departureDate']) ? DbSecurity::validateInput($_POST['departureDate'], 'date') : null;

// Validate airline
$airline = isset($_POST['airline']) ? DbSecurity::validateInput($_POST['airline'], 'string', ['maxlength' => 255]) : null;

// Validate destination
$destination = isset($_POST['destination']) ? DbSecurity::validateInput($_POST['destination'], 'string', ['maxlength' => 255]) : null;

// Validate origin
$origin = isset($_POST['origin']) ? DbSecurity::validateInput($_POST['origin'], 'string', ['maxlength' => 255]) : null;

// Validate pnr
$pnr = isset($_POST['pnr']) ? DbSecurity::validateInput($_POST['pnr'], 'string', ['maxlength' => 255]) : null;

// Validate passengerName
$passengerName = isset($_POST['passengerName']) ? DbSecurity::validateInput($_POST['passengerName'], 'string', ['maxlength' => 255]) : null;

// Validate paidTo
$paidTo = isset($_POST['paidTo']) ? DbSecurity::validateInput($_POST['paidTo'], 'int', ['min' => 0]) : null;

// Validate soldTo
$soldTo = isset($_POST['soldTo']) ? DbSecurity::validateInput($_POST['soldTo'], 'int', ['min' => 0]) : null;

// Validate supplier
$supplier = isset($_POST['supplier']) ? DbSecurity::validateInput($_POST['supplier'], 'int', ['min' => 0]) : null;

// Check connection and handle errors
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]));
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data from the POST request
    $supplier_id = intval($_POST['supplier']);
    $soldTo = intval($_POST['soldTo']);
    $paidTo = intval($_POST['paidTo']);
    $passengerName = $conn->real_escape_string($_POST['passengerName']);
    $pnr = $conn->real_escape_string($_POST['pnr']);
    $origin = $conn->real_escape_string($_POST['origin']);
    $destination = $conn->real_escape_string($_POST['destination']);
    $airline = $conn->real_escape_string($_POST['airline']);
    $departureDate = $_POST['departureDate'];
    $issueDate = $_POST['issueDate'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $title = $conn->real_escape_string($_POST['title']);
    $currency = $conn->real_escape_string($_POST['curr']);
    $base = floatval($_POST['base']);
    $sold = floatval($_POST['sold']);
    $profit = floatval($_POST['pro']);
    $description = $conn->real_escape_string($_POST['description']);
    $tripType = $_POST['tripType'];
    $returnDestination = $_POST['returnDestination'];
    $returnDate = $_POST['returnDate'];
   

    // Begin a database transaction
    $conn->begin_transaction();

    try {
        // Check if PNR has already been used 6 or more times
        $stmt_check_pnr = $conn->prepare("SELECT COUNT(*) FROM ticket_reservations WHERE pnr = ? AND tenant_id = ?");
        $stmt_check_pnr->bind_param("si", $pnr, $tenant_id);
        if (!$stmt_check_pnr->execute()) {
            throw new Exception("Failed to check PNR: " . $stmt_check_pnr->error);
        }
        $stmt_check_pnr->bind_result($pnr_count);
        $stmt_check_pnr->fetch();
        $stmt_check_pnr->close();
        
        // If PNR has been used 6 or more times, throw an exception
        if ($pnr_count >= 6) {
            throw new Exception("Duplicate entry: This PNR has already been used 6 times.");
        }

        // Fetch supplier details (balance, currency, name, type) from the suppliers table
        $stmt_check_balance = $conn->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt_check_balance->bind_param("ii", $supplier_id, $tenant_id);
        if (!$stmt_check_balance->execute()) {
            throw new Exception("Failed to validate supplier balance: " . $stmt_check_balance->error);
        }
        $stmt_check_balance->bind_result($current_balance, $supplier_currency, $supplier_name, $supplier_type);
        $stmt_check_balance->fetch();
        $stmt_check_balance->close();

        // Ensure that the ticket currency matches the supplier's currency
        if ($currency !== $supplier_currency) {
            throw new Exception('Currency mismatch between supplier and ticket.');
        }

        // Fetch main account name (PaidTo) from the main_accounts table
        $stmt_main_account = $conn->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt_main_account->bind_param("ii", $paidTo, $tenant_id);
        if (!$stmt_main_account->execute()) {
            throw new Exception("Failed to fetch main account name: " . $stmt_main_account->error);
        }
        $stmt_main_account->bind_result($main_account_name);
        $stmt_main_account->fetch();
        $stmt_main_account->close();

        // Fetch client details (name, balance, client type) from the clients table
        $stmt_client_info = $conn->prepare("SELECT name, usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ?");
        $stmt_client_info->bind_param("ii", $soldTo, $tenant_id);
        if (!$stmt_client_info->execute()) {
            throw new Exception("Failed to fetch client info: " . $stmt_client_info->error);
        }
        $stmt_client_info->bind_result($client_name, $usd_balance, $afs_balance, $client_type);
        $stmt_client_info->fetch();
        $stmt_client_info->close();

        // Calculate new client balance based on currency
        $current_client_balance = ($currency === 'USD') ? $usd_balance : $afs_balance;
        $new_client_balance = $current_client_balance - $sold;

        // Insert a new ticket booking record into the ticket_bookings table
        $stmt_ticket = $conn->prepare("INSERT INTO ticket_reservations (
            supplier, sold_to, paid_to, passenger_name, pnr, origin, destination, airline, departure_date, issue_date, 
            phone, gender, title, price, sold, profit, currency, description, trip_type, return_destination, return_date, created_by, tenant_id
            
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ticket->bind_param(
            "iiissssssssssdddsssssii",           
            $supplier_id, $soldTo, $paidTo, $passengerName, $pnr, $origin, $destination, $airline,
            $departureDate, $issueDate, $phone, $gender, $title, $base, $sold, $profit, $currency, 
            $description, $tripType, $returnDestination, $returnDate, $user_id, $tenant_id
        );
        if (!$stmt_ticket->execute()) {
            throw new Exception('Failed to book ticket: ' . $stmt_ticket->error);
        }
        $ticket_id = $stmt_ticket->insert_id;  // Get the inserted ticket ID
        $stmt_ticket->close();

        // Insert supplier transaction and update balance only if supplier is regular type
        if ($supplier_type === 'External') {
            // Calculate new supplier balance
            $new_supplier_balance = $current_balance - $base;
            
            // Insert supplier transaction with balance
            $stmt_transaction = $conn->prepare("INSERT INTO supplier_transactions (
                supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id
            ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'ticket_reserve', ?)");
            $remarks = "Base amount of $base $currency deducted for ticket reservation.";
            $stmt_transaction->bind_param("iiddsi", $supplier_id, $ticket_id, $base, $new_supplier_balance, $remarks, $tenant_id);
            if (!$stmt_transaction->execute()) {
                throw new Exception('Failed to create supplier transaction: ' . $stmt_transaction->error);
            }
            $transaction_id = $stmt_transaction->insert_id;
            $stmt_transaction->close();

            // Update supplier balance
            $stmt_balance = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
            $stmt_balance->bind_param("di", $base, $supplier_id);
            if (!$stmt_balance->execute()) {
                throw new Exception('Failed to update supplier balance: ' . $stmt_balance->error);
            }
            $stmt_balance->close();
        } else {
            // For non-regular suppliers, just record the transaction without balance
            $stmt_transaction = $conn->prepare("INSERT INTO supplier_transactions (
                supplier_id, reference_id, transaction_type, amount, remarks, transaction_date, transaction_of, tenant_id
            ) VALUES (?, ?, 'Debit', ?, ?, NOW(), 'ticket_reserve', ?)");
            $remarks = "Base amount of $base $currency deducted for ticket reservation.";
            $stmt_transaction->bind_param("iidsi", $supplier_id, $ticket_id, $base, $remarks, $tenant_id);
            if (!$stmt_transaction->execute()) {
                throw new Exception('Failed to create supplier transaction: ' . $stmt_transaction->error);
            }
            $transaction_id = $stmt_transaction->insert_id;
            $stmt_transaction->close();
        }

        // Record client transaction (Debit for sold price) in client_transactions table
        $stmt_client_transaction = $conn->prepare("INSERT INTO client_transactions (
            client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $description = "Ticket reservation for passenger $passengerName";
        $type = 'Debit';
        $transaction_of = 'ticket_reserve';
        $stmt_client_transaction->bind_param("iiddssssi", $soldTo, $type, $transaction_of, $ticket_id, $sold, $new_client_balance, $currency, $description, $tenant_id);
        $stmt_client_transaction->execute([
            $soldTo, $type, $transaction_of, $ticket_id, $sold, $new_client_balance, $currency, $description, $tenant_id
        ]);
        $stmt_client_transaction->close();

        // Update client balance if client type is regular
        if ($client_type === 'regular') {
            $balance_column = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
            $stmt_deduct_client_balance = $conn->prepare("UPDATE clients SET $balance_column = $balance_column - ? WHERE id = ? AND tenant_id = ?");
            $stmt_deduct_client_balance->bind_param("dii", $sold, $soldTo, $tenant_id);
            if (!$stmt_deduct_client_balance->execute()) {
                throw new Exception('Failed to update client balance: ' . $stmt_deduct_client_balance->error);
            }
            $stmt_deduct_client_balance->close();
        }

        // Add activity logging
        $user_id = $_SESSION["user_id"] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare new values data
        $new_values = [
            'passenger_name' => $passengerName,
            'pnr' => $pnr,
            'origin' => $origin,
            'destination' => $destination,
            'airline' => $airline,
            'departure_date' => $departureDate,
            'base' => $base,
            'sold' => $sold,
            'profit' => $profit,
            'currency' => $currency,
            'supplier' => $supplier_id,
            'supplier_name' => $supplier_name,
            'sold_to' => $soldTo,
            'client_name' => $client_name
        ];
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'ticket_reservations', ?, '{}', ?, ?, ?, NOW(), ?)");
        
        $new_values_json = json_encode($new_values);
        $activity_log_stmt->bind_param("iisssi", $user_id, $ticket_id, $new_values_json, $ip_address, $user_agent, $tenant_id);
        $activity_log_stmt->execute();
        $activity_log_stmt->close();

        // Commit the database transaction
        $conn->commit();

        // Return a success message as JSON response
        echo json_encode(["status" => "success", "message" => "Ticket booked successfully. Notification sent to admin."]);
    } catch (Exception $e) {
        // Rollback transaction in case of an error
        $conn->rollback();

        // Return the error message as JSON response
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    // Close the database connection
    $conn->close();
}
?>
