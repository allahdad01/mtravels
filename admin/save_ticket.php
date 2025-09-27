<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;
// Establish a connection to the MySQL database
include '../includes/conn.php';

// Validate passengers
$passengers = isset($_POST['passengers']) ? DbSecurity::validateInput($_POST['passengers'], 'string', ['maxlength' => 255]) : null;

// Validate passengerCount
$passengerCount = isset($_POST['passengerCount']) ? DbSecurity::validateInput($_POST['passengerCount'], 'int', ['min' => 0]) : null;

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

// Validate discount
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float', ['min' => 0]) : null;

// Validate sold
$sold = isset($_POST['sold']) ? DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]) : null;

// Validate base
$base = isset($_POST['base']) ? DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]) : null;

// Validate curr
$curr = isset($_POST['curr']) ? DbSecurity::validateInput($_POST['curr'], 'string', ['maxlength' => 255]) : null;

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
    $pnr = $conn->real_escape_string($_POST['pnr']);
    $origin = $conn->real_escape_string($_POST['origin']);
    $destination = $conn->real_escape_string($_POST['destination']);
    $airline = $conn->real_escape_string($_POST['airline']);
    $departureDate = $_POST['departureDate'];
    $issueDate = $_POST['issueDate'];
    $currency = $conn->real_escape_string($_POST['curr']);
    $description = $conn->real_escape_string($_POST['description']);
    $tripType = $_POST['tripType'];
    $returnDestination = $_POST['returnDestination'];
    $returnDate = $_POST['returnDate'];
    $passengers = isset($_POST['passengers']) ? $_POST['passengers'] : [];

    // Calculate totals
    $totalBase = 0;
    $totalSold = 0;
    $totalDiscount = 0;
    $totalProfit = 0;
    
    // Begin a database transaction
    $conn->begin_transaction();

    try {
        // Check if PNR has already been used 6 or more times
        $stmt_check_pnr = $conn->prepare("SELECT COUNT(*) FROM ticket_bookings WHERE pnr = ? AND tenant_id = ?");
        $stmt_check_pnr->bind_param("si", $pnr, $tenant_id);
        if (!$stmt_check_pnr->execute()) {
            throw new Exception("Failed to check PNR: " . $stmt_check_pnr->error);
        }
        $stmt_check_pnr->bind_result($pnr_count);
        $stmt_check_pnr->fetch();
        $stmt_check_pnr->close();
        
        // If PNR has been used 25 or more times, throw an exception
        if ($pnr_count >= 25) {
            throw new Exception("Duplicate entry: This PNR has already been used 25 times.");
        }

        // Fetch supplier details
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

        // Fetch main account name
        $stmt_main_account = $conn->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt_main_account->bind_param("ii", $paidTo, $tenant_id);
        if (!$stmt_main_account->execute()) {
            throw new Exception("Failed to fetch main account name: " . $stmt_main_account->error);
        }
        $stmt_main_account->bind_result($main_account_name);
        $stmt_main_account->fetch();
        $stmt_main_account->close();

        // Fetch client details
        $stmt_client_info = $conn->prepare("SELECT name, usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ?");
        $stmt_client_info->bind_param("ii", $soldTo, $tenant_id);
        if (!$stmt_client_info->execute()) {
            throw new Exception("Failed to fetch client info: " . $stmt_client_info->error);
        }
        $stmt_client_info->bind_result($client_name, $usd_balance, $afs_balance, $client_type);
        $stmt_client_info->fetch();
        $stmt_client_info->close();

        // Get initial balances
        $current_client_balance = ($currency === 'USD') ? $usd_balance : $afs_balance;
        $initial_client_balance = $current_client_balance;
        $initial_supplier_balance = $current_balance;

        // Prepare ticket booking statement
        $stmt_ticket = $conn->prepare("INSERT INTO ticket_bookings (
            supplier, sold_to, paid_to, passenger_name, pnr, origin, destination, airline, departure_date, issue_date, 
            phone, gender, title, price, sold, discount, profit, currency, description, trip_type, return_destination, return_date, 
            created_by, tenant_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Main booking ID to link all passengers
        $main_booking_id = 0;

        // Process each passenger
        foreach ($passengers as $index => $passenger) {
            // Get passenger details
            $passengerName = $conn->real_escape_string($passenger['name']);
            $phone = $conn->real_escape_string($passenger['phone']);
            $gender = $conn->real_escape_string($passenger['gender']);
            $title = $conn->real_escape_string($passenger['title']);
            
            // Get passenger pricing
            $base = floatval($passenger['base']);
            $sold = floatval($passenger['sold']);
            $discount = floatval($passenger['discount'] ?? 0);
            $profit = $sold - $base - $discount;

            // Update totals
            $totalBase += $base;
            $totalSold += $sold;
            $totalDiscount += $discount;
            $totalProfit += $profit;

            $stmt_ticket->bind_param(
                "iiissssssssssddddsssssii",           
                $supplier_id, $soldTo, $paidTo, $passengerName, $pnr, $origin, $destination, $airline,
                $departureDate, $issueDate, $phone, $gender, $title, $base, $sold, $discount, $profit, $currency, 
                $description, $tripType, $returnDestination, $returnDate, $user_id, $tenant_id
            );
            
            if (!$stmt_ticket->execute()) {
                throw new Exception('Failed to book ticket: ' . $stmt_ticket->error);
            }
            
            $ticket_id = $stmt_ticket->insert_id;
            
            // Store first ticket ID as main booking ID
            if ($index === 0) {
                $main_booking_id = $ticket_id;
            }
            
            // Link additional passengers to main booking
            if ($index > 0 && $main_booking_id > 0) {
                $stmt_update_ref = $conn->prepare("UPDATE ticket_bookings SET group_booking_id = ? WHERE id = ?");
                $stmt_update_ref->bind_param("ii", $main_booking_id, $ticket_id);
                if (!$stmt_update_ref->execute()) {
                    throw new Exception('Failed to update group booking reference: ' . $stmt_update_ref->error);
                }
                $stmt_update_ref->close();
            }
            
            // Process supplier transaction
            if ($supplier_type === 'External') {
                // Update supplier balance for this passenger
                $new_supplier_balance = $initial_supplier_balance - $base;
                
                // Insert supplier transaction
                $stmt_transaction = $conn->prepare("INSERT INTO supplier_transactions (
                    supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id
                ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'ticket_sale', ?)");
                $remarks = "Base amount of $base $currency deducted for ticket booking for $title $passengerName with PNR: $pnr.";
                $stmt_transaction->bind_param("iiddsi", $supplier_id, $ticket_id, $base, $new_supplier_balance, $remarks, $tenant_id);
                if (!$stmt_transaction->execute()) {
                    throw new Exception('Failed to create supplier transaction: ' . $stmt_transaction->error);
                }
                $stmt_transaction->close();

                // Update supplier balance
                $stmt_balance = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ?");
                $stmt_balance->bind_param("di", $new_supplier_balance, $supplier_id);
                if (!$stmt_balance->execute()) {
                    throw new Exception('Failed to update supplier balance: ' . $stmt_balance->error);
                }
                $stmt_balance->close();
                
                // Update initial balance for next passenger
                $initial_supplier_balance = $new_supplier_balance;
            } else {
                // For non-regular suppliers, just record the transaction
                $stmt_transaction = $conn->prepare("INSERT INTO supplier_transactions (
                    supplier_id, reference_id, transaction_type, amount, remarks, transaction_date, transaction_of, tenant_id
                ) VALUES (?, ?, 'Debit', ?, ?, NOW(), 'ticket_sale', ?)");
                $remarks = "Base amount of $base $currency deducted for ticket booking for $title $passengerName with PNR: $pnr.";
                $stmt_transaction->bind_param("iidsi", $supplier_id, $ticket_id, $base, $remarks, $tenant_id);
                if (!$stmt_transaction->execute()) {
                    throw new Exception('Failed to create supplier transaction: ' . $stmt_transaction->error);
                }
                $stmt_transaction->close();
            }

            // Process client transaction
            $new_client_balance = $initial_client_balance - $sold;
            
            // Insert client transaction
            $stmt_client_transaction = $conn->prepare("INSERT INTO client_transactions (
                client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id
            ) VALUES (?, 'Debit', 'ticket_sale', ?, ?, ?, ?, ?, NOW(), ?)");
            $description = "Ticket booked for $title $passengerName with PNR: $pnr from $origin to $destination.";
            
            $stmt_client_transaction->bind_param("iiddssi", $soldTo, $ticket_id, $sold, $new_client_balance, $currency, $description, $tenant_id);
            if (!$stmt_client_transaction->execute()) {
                throw new Exception('Failed to log client transaction: ' . $stmt_client_transaction->error);
            }
            $stmt_client_transaction->close();

            // Update client balance for regular clients
            if ($client_type === 'regular') {
                $balance_column = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
                $stmt_deduct_client_balance = $conn->prepare("UPDATE clients SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
                $stmt_deduct_client_balance->bind_param("dii", $new_client_balance, $soldTo, $tenant_id);
                if (!$stmt_deduct_client_balance->execute()) {
                    throw new Exception('Failed to update client balance: ' . $stmt_deduct_client_balance->error);
                }
                $stmt_deduct_client_balance->close();
                
                // Update initial balance for next passenger
                $initial_client_balance = $new_client_balance;
            }
        }
        
        $stmt_ticket->close();

        // Add activity log
        $user_id = $user_id ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare activity log data
        $new_values = [
            'multiple_passengers' => true,
            'passenger_count' => count($passengers),
            'pnr' => $pnr,
            'origin' => $origin,
            'destination' => $destination,
            'airline' => $airline,
            'departure_date' => $departureDate,
            'total_base' => $totalBase,
            'total_sold' => $totalSold,
            'total_discount' => $totalDiscount,
            'total_profit' => $totalProfit,
            'currency' => $currency,
            'supplier_id' => $supplier_id,
            'supplier_name' => $supplier_name,
            'client_id' => $soldTo,
            'client_name' => $client_name,
            'trip_type' => $tripType,
        ];
        
        // Insert activity log
        $record_id = $main_booking_id > 0 ? $main_booking_id : $ticket_id;
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'ticket_bookings', ?, '{}', ?, ?, ?, NOW(), ?)");
        
        $new_values_json = json_encode($new_values);
        $activity_log_stmt->bind_param("iisssi", $user_id, $record_id, $new_values_json, $ip_address, $user_agent, $tenant_id);
        $activity_log_stmt->execute();
        $activity_log_stmt->close();

        // Commit transaction
        $conn->commit();

        // Return success response
        echo json_encode([
            "status" => "success", 
            "message" => "Ticket booked successfully for " . count($passengers) . " passenger(s).",
            "totals" => [
                "base" => $totalBase,
                "sold" => $totalSold,
                "discount" => $totalDiscount,
                "profit" => $totalProfit
            ]
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    // Close database connection
    $conn->close();
}
?>
