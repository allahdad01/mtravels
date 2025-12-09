<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();


// Database connection
require_once '../../includes/db.php';

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

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;

// Check connection and handle errors
if ($pdo->errorCode() !== '00000') {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . implode(' ', $pdo->errorInfo())]));
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data from the POST request
    $supplier_id = intval($_POST['supplier']);
    $soldTo = intval($_POST['soldTo']);
    $paidTo = intval($_POST['paidTo']);
    $passengerName = htmlspecialchars($_POST['passengerName']);
    $pnr = htmlspecialchars($_POST['pnr']);
    $origin = htmlspecialchars($_POST['origin']);
    $destination = htmlspecialchars($_POST['destination']);
    $airline = htmlspecialchars($_POST['airline']);
    $departureDate = $_POST['departureDate'];
    $issueDate = $_POST['issueDate'];
    $phone = htmlspecialchars($_POST['phone']);
    $gender = htmlspecialchars($_POST['gender']);
    $title = htmlspecialchars($_POST['title']);
    $currency = htmlspecialchars($_POST['curr']);
    $base = floatval($_POST['base']);
    $sold = floatval($_POST['sold']);
    $profit = floatval($_POST['pro']);
    $description = htmlspecialchars($_POST['description']);
    $tripType = $_POST['tripType'];
    $returnDestination = $_POST['returnDestination'];
    $returnDate = $_POST['returnDate'];

    // Begin a database transaction
    $pdo->beginTransaction();

    try {
        // Check if PNR has already been used 6 or more times
        $stmt_check_pnr = $pdo->prepare("SELECT COUNT(*) FROM ticket_reservations WHERE pnr = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_check_pnr->bindParam(1, $pnr, PDO::PARAM_STR);
        $stmt_check_pnr->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_check_pnr->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_check_pnr->execute();
        $pnr_count = $stmt_check_pnr->fetchColumn();

        // If PNR has been used 6 or more times, throw an exception
        if ($pnr_count >= 6) {
            throw new PDOException("Duplicate entry: This PNR has already been used 6 times.");
        }

        // Fetch supplier details (balance, currency, name, type) from the suppliers table
        $stmt_check_balance = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_check_balance->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt_check_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_check_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_check_balance->execute();
        $supplierData = $stmt_check_balance->fetch(PDO::FETCH_ASSOC);

        $current_balance = $supplierData['balance'];
        $supplier_currency = $supplierData['currency'];
        $supplier_name = $supplierData['supplier_name'];
        $supplier_type = $supplierData['supplier_type'];

        // Ensure that the ticket currency matches the supplier's currency
        if ($currency !== $supplier_currency) {
            throw new PDOException('Currency mismatch between supplier and ticket.');
        }

        // Fetch main account name (PaidTo) from the main_accounts table
        $stmt_main_account = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_main_account->bindParam(1, $paidTo, PDO::PARAM_INT);
        $stmt_main_account->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_main_account->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_main_account->execute();
        $main_account_name = $stmt_main_account->fetchColumn();

        // Fetch client details (name, balance, client type) from the clients table
        $stmt_client_info = $pdo->prepare("SELECT name, usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_client_info->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_info->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_info->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_client_info->execute();
        $clientData = $stmt_client_info->fetch(PDO::FETCH_ASSOC);

        $client_name = $clientData['name'];
        $usd_balance = $clientData['usd_balance'];
        $afs_balance = $clientData['afs_balance'];
        $client_type = $clientData['client_type'];

        // Calculate new client balance based on currency
        $current_client_balance = ($currency === 'USD') ? $usd_balance : $afs_balance;
        $new_client_balance = $current_client_balance - $sold;

        // Insert a new ticket booking record into the ticket_bookings table
        $stmt_ticket = $pdo->prepare("INSERT INTO ticket_reservations (
            supplier, sold_to, paid_to, passenger_name, pnr, origin, destination, airline, departure_date, issue_date,
            phone, gender, title, price, sold, profit, currency, description, trip_type, return_destination, return_date, created_by, tenant_id, branch_id

        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ticket->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt_ticket->bindParam(2, $soldTo, PDO::PARAM_INT);
        $stmt_ticket->bindParam(3, $paidTo, PDO::PARAM_INT);
        $stmt_ticket->bindParam(4, $passengerName, PDO::PARAM_STR);
        $stmt_ticket->bindParam(5, $pnr, PDO::PARAM_STR);
        $stmt_ticket->bindParam(6, $origin, PDO::PARAM_STR);
        $stmt_ticket->bindParam(7, $destination, PDO::PARAM_STR);
        $stmt_ticket->bindParam(8, $airline, PDO::PARAM_STR);
        $stmt_ticket->bindParam(9, $departureDate, PDO::PARAM_STR);
        $stmt_ticket->bindParam(10, $issueDate, PDO::PARAM_STR);
        $stmt_ticket->bindParam(11, $phone, PDO::PARAM_STR);
        $stmt_ticket->bindParam(12, $gender, PDO::PARAM_STR);
        $stmt_ticket->bindParam(13, $title, PDO::PARAM_STR);
        $stmt_ticket->bindParam(14, $base, PDO::PARAM_STR);
        $stmt_ticket->bindParam(15, $sold, PDO::PARAM_STR);
        $stmt_ticket->bindParam(16, $profit, PDO::PARAM_STR);
        $stmt_ticket->bindParam(17, $currency, PDO::PARAM_STR);
        $stmt_ticket->bindParam(18, $description, PDO::PARAM_STR);
        $stmt_ticket->bindParam(19, $tripType, PDO::PARAM_STR);
        $stmt_ticket->bindParam(20, $returnDestination, PDO::PARAM_STR);
        $stmt_ticket->bindParam(21, $returnDate, PDO::PARAM_STR);
        $stmt_ticket->bindParam(22, $user_id, PDO::PARAM_INT);
        $stmt_ticket->bindParam(23, $tenant_id, PDO::PARAM_INT);
        $stmt_ticket->bindParam(24, $branch_id, PDO::PARAM_INT);
        $stmt_ticket->execute();
        $ticket_id = $pdo->lastInsertId();  // Get the inserted ticket ID

        // Insert supplier transaction and update balance only if supplier is regular type
        if ($supplier_type === 'External') {
            // Calculate new supplier balance
            $new_supplier_balance = $current_balance - $base;

            // Insert supplier transaction with balance
            $stmt_transaction = $pdo->prepare("INSERT INTO supplier_transactions (
                supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id, branch_id
            ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'ticket_reserve', ?, ?)");
            $remarks = "Base amount of $base $currency deducted for ticket reservation.";
            $stmt_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(2, $ticket_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(3, $base, PDO::PARAM_STR);
            $stmt_transaction->bindParam(4, $new_supplier_balance, PDO::PARAM_STR);
            $stmt_transaction->bindParam(5, $remarks, PDO::PARAM_STR);
            $stmt_transaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(7, $branch_id, PDO::PARAM_INT);
            $stmt_transaction->execute();
            $transaction_id = $pdo->lastInsertId();

            // Update supplier balance
            $stmt_balance = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
            $stmt_balance->bindParam(1, $base, PDO::PARAM_STR);
            $stmt_balance->bindParam(2, $supplier_id, PDO::PARAM_INT);
            $stmt_balance->execute();
        } else {
            // For non-regular suppliers, just record the transaction without balance
            $stmt_transaction = $pdo->prepare("INSERT INTO supplier_transactions (
                supplier_id, reference_id, transaction_type, amount, remarks, transaction_date, transaction_of, tenant_id, branch_id
            ) VALUES (?, ?, 'Debit', ?, ?, NOW(), 'ticket_reserve', ?, ?)");
            $remarks = "Base amount of $base $currency deducted for ticket reservation.";
            $stmt_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(2, $ticket_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(3, $base, PDO::PARAM_STR);
            $stmt_transaction->bindParam(4, $remarks, PDO::PARAM_STR);
            $stmt_transaction->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(6, $branch_id, PDO::PARAM_INT);
            $stmt_transaction->execute();
            $transaction_id = $pdo->lastInsertId();
        }

        // Record client transaction (Debit for sold price) in client_transactions table
        $stmt_client_transaction = $pdo->prepare("INSERT INTO client_transactions (
            client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $description = "Ticket reservation for passenger $passengerName";
        $type = 'Debit';
        $transaction_of = 'ticket_reserve';
        $stmt_client_transaction->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(2, $type, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(3, $transaction_of, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(4, $ticket_id, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(5, $sold, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(6, $new_client_balance, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(7, $currency, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(8, $description, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(10, $branch_id, PDO::PARAM_INT);
        $stmt_client_transaction->execute();

        // Update client balance if client type is regular
        if ($client_type === 'regular') {
            $balance_column = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
            $stmt_deduct_client_balance = $pdo->prepare("UPDATE clients SET $balance_column = $balance_column - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_deduct_client_balance->bindParam(1, $sold, PDO::PARAM_STR);
            $stmt_deduct_client_balance->bindParam(2, $soldTo, PDO::PARAM_INT);
            $stmt_deduct_client_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_deduct_client_balance->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt_deduct_client_balance->execute();
        }

        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
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
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, 'add', 'ticket_reservations', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

        $new_values_json = json_encode($new_values);
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $ticket_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(3, $new_values_json, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();

        // Commit the database transaction
        $pdo->commit();

        // Send email notification to client
        require_once '../includes/functions.php';

        // Get client email
        $stmt_client_email = $pdo->prepare("SELECT email, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_client_email->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_email->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_email->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_client_email->execute();
        $client_email_data = $stmt_client_email->fetch(PDO::FETCH_ASSOC);
        $client_email = $client_email_data['email'];
        $client_name = $client_email_data['name'];

        if (!empty($client_email)) {
            sendTicketReservationNotification(
                $client_email,
                $client_name,
                $ticket_id,
                $passengerName,
                $pnr,
                $origin,
                $destination,
                $airline,
                $departureDate,
                $returnDate,
                $sold,
                $currency
            );
        }

        // Return a success message as JSON response
        echo json_encode(["status" => "success", "message" => "Ticket booked successfully. Notification sent to admin."]);
    } catch (PDOException $e) {
        // Rollback transaction in case of an error
        $pdo->rollBack();

        // Return the error message as JSON response
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
