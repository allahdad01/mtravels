<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include WhatsApp Manager for notifications
require_once '../../api/whatsapp/WhatsAppManager.php';

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;

// Establish a connection to the MySQL database
require_once '../../includes/db.php';

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

// Validate departureTime
$departureTime = isset($_POST['departureTime']) ? DbSecurity::validateInput($_POST['departureTime'], 'string', ['maxlength' => 255]) : null;

// Validate returnDepartureTime
$returnDepartureTime = isset($_POST['returnDepartureTime']) ? DbSecurity::validateInput($_POST['returnDepartureTime'], 'string', ['maxlength' => 255]) : null;

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

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data from the POST request
    $supplier_id = intval($_POST['supplier']);
    $soldTo = intval($_POST['soldTo']);
    $paidTo = intval($_POST['paidTo']);
    $pnr = htmlspecialchars($_POST['pnr'], ENT_QUOTES, 'UTF-8');
    $origin = htmlspecialchars($_POST['origin'], ENT_QUOTES, 'UTF-8');
    $destination = htmlspecialchars($_POST['destination'], ENT_QUOTES, 'UTF-8');
    $airline = htmlspecialchars($_POST['airline'], ENT_QUOTES, 'UTF-8');
    $departureDate = $_POST['departureDate'];
    $departureTime = $_POST['departureTime'];
    $returnDepartureTime = $_POST['returnDepartureTime'];
    $issueDate = $_POST['issueDate'];
    $currency = htmlspecialchars($_POST['curr'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
    $tripType = $_POST['tripType'];
    $returnDestination = !empty($_POST['returnDestination']) ? $_POST['returnDestination'] : null;
    $returnDate = !empty($_POST['returnDate']) ? $_POST['returnDate'] : null;
    $returnDepartureTime = !empty($_POST['returnDepartureTime']) ? $_POST['returnDepartureTime'] : null;
    $phone = htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8');
    $passengers = isset($_POST['passengers']) ? $_POST['passengers'] : [];

    // Calculate totals
    $totalBase = 0;
    $totalSold = 0;
    $totalDiscount = 0;
    $totalProfit = 0;
    
    // Begin a database transaction
    $pdo->beginTransaction();

    try {
        // Check if PNR has already been used 6 or more times
        $stmt_check_pnr = $pdo->prepare("SELECT COUNT(*) FROM ticket_bookings WHERE pnr = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_check_pnr->bindParam(1, $pnr, PDO::PARAM_STR);
        $stmt_check_pnr->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_check_pnr->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmt_check_pnr->execute()) {
            throw new Exception("Failed to check PNR");
        }
        $pnr_count = $stmt_check_pnr->fetchColumn();
        
        // If PNR has been used 25 or more times, throw an exception
        if ($pnr_count >= 25) {
            throw new Exception("Duplicate entry: This PNR has already been used 25 times.");
        }

        // Fetch supplier details
        $stmt_check_balance = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_check_balance->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt_check_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_check_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmt_check_balance->execute()) {
            throw new Exception("Failed to validate supplier balance");
        }
        $supplierData = $stmt_check_balance->fetch(PDO::FETCH_ASSOC);
        $current_balance = $supplierData['balance'];
        $supplier_currency = $supplierData['currency'];
        $supplier_name = $supplierData['name'];
        $supplier_type = $supplierData['supplier_type'];

        // Ensure that the ticket currency matches the supplier's currency
        if ($currency !== $supplier_currency) {
            throw new Exception('Currency mismatch between supplier and ticket.');
        }

        // Fetch main account name
        $stmt_main_account = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_main_account->bindParam(1, $paidTo, PDO::PARAM_INT);
        $stmt_main_account->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_main_account->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmt_main_account->execute()) {
            throw new Exception("Failed to fetch main account name");
        }
        $mainAccountData = $stmt_main_account->fetch(PDO::FETCH_ASSOC);
        $main_account_name = $mainAccountData['name'];

        // Fetch client details
        $stmt_client_info = $pdo->prepare("SELECT name, usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_client_info->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_info->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_info->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmt_client_info->execute()) {
            throw new Exception("Failed to fetch client info");
        }
        $clientData = $stmt_client_info->fetch(PDO::FETCH_ASSOC);
        $client_name = $clientData['name'];
        $usd_balance = $clientData['usd_balance'];
        $afs_balance = $clientData['afs_balance'];
        $client_type = $clientData['client_type'];

        // Get initial balances
        $current_client_balance = ($currency === 'USD') ? $usd_balance : $afs_balance;
        $initial_client_balance = $current_client_balance;
        $initial_supplier_balance = $current_balance;

        // Prepare ticket booking statement
        $stmt_ticket = $pdo->prepare("INSERT INTO ticket_bookings (
            supplier, sold_to, paid_to, passenger_name, pnr, origin, destination, airline, departure_date, departure_time, issue_date,
            phone, gender, title, price, sold, discount, profit, currency, description, trip_type, return_destination, return_date, return_departure_time,
            created_by, tenant_id, branch_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Main booking ID to link all passengers
        $main_booking_id = 0;

        // Process each passenger
         foreach ($passengers as $index => $passenger) {
             // Get passenger details
             $passengerName = htmlspecialchars($passenger['name'], ENT_QUOTES, 'UTF-8');
             $gender = htmlspecialchars($passenger['gender'], ENT_QUOTES, 'UTF-8');
             $title = htmlspecialchars($passenger['title'], ENT_QUOTES, 'UTF-8');
            
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

            $stmt_ticket->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmt_ticket->bindParam(2, $soldTo, PDO::PARAM_INT);
            $stmt_ticket->bindParam(3, $paidTo, PDO::PARAM_INT);
            $stmt_ticket->bindParam(4, $passengerName, PDO::PARAM_STR);
            $stmt_ticket->bindParam(5, $pnr, PDO::PARAM_STR);
            $stmt_ticket->bindParam(6, $origin, PDO::PARAM_STR);
            $stmt_ticket->bindParam(7, $destination, PDO::PARAM_STR);
            $stmt_ticket->bindParam(8, $airline, PDO::PARAM_STR);
            $stmt_ticket->bindParam(9, $departureDate, PDO::PARAM_STR);
            $stmt_ticket->bindParam(10, $departureTime, PDO::PARAM_STR);
            $stmt_ticket->bindParam(11, $issueDate, PDO::PARAM_STR);
            $stmt_ticket->bindParam(12, $phone, PDO::PARAM_STR);
            $stmt_ticket->bindParam(13, $gender, PDO::PARAM_STR);
            $stmt_ticket->bindParam(14, $title, PDO::PARAM_STR);
            $stmt_ticket->bindParam(15, $base, PDO::PARAM_STR);
            $stmt_ticket->bindParam(16, $sold, PDO::PARAM_STR);
            $stmt_ticket->bindParam(17, $discount, PDO::PARAM_STR);
            $stmt_ticket->bindParam(18, $profit, PDO::PARAM_STR);
            $stmt_ticket->bindParam(19, $currency, PDO::PARAM_STR);
            $stmt_ticket->bindParam(20, $description, PDO::PARAM_STR);
            $stmt_ticket->bindParam(21, $tripType, PDO::PARAM_STR);
            $stmt_ticket->bindParam(22, $returnDestination, $returnDestination === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_ticket->bindParam(23, $returnDate, $returnDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_ticket->bindParam(24, $returnDepartureTime, $returnDepartureTime === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_ticket->bindParam(25, $user_id, PDO::PARAM_INT);
            $stmt_ticket->bindParam(26, $tenant_id, PDO::PARAM_INT);
            $stmt_ticket->bindParam(27, $branch_id, PDO::PARAM_INT);
            
            if (!$stmt_ticket->execute()) {
                throw new Exception('Failed to book ticket');
            }

            $ticket_id = $pdo->lastInsertId();

            // Store first ticket ID as main booking ID
            if ($index === 0) {
                $main_booking_id = $ticket_id;
            }

            // Link additional passengers to main booking
            if ($index > 0 && $main_booking_id > 0) {
                $stmt_update_ref = $pdo->prepare("UPDATE ticket_bookings SET group_booking_id = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_ref->bindParam(1, $main_booking_id, PDO::PARAM_INT);
                $stmt_update_ref->bindParam(2, $ticket_id, PDO::PARAM_INT);
                $stmt_update_ref->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_ref->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_update_ref->execute()) {
                    throw new Exception('Failed to update group booking reference');
                }
            }
            
            // Process supplier transaction
            if ($supplier_type === 'External') {
                // Update supplier balance for this passenger
                $new_supplier_balance = $initial_supplier_balance - $base;
                
                // Insert supplier transaction
                $stmt_transaction = $pdo->prepare("INSERT INTO supplier_transactions (
                    supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id, branch_id
                ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'ticket_sale', ?, ?)");
                $remarks = "Base amount of $base $currency deducted for ticket booking for $title $passengerName with PNR: $pnr.";
                $stmt_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_transaction->bindParam(2, $ticket_id, PDO::PARAM_INT);
                $stmt_transaction->bindParam(3, $base, PDO::PARAM_STR);
                $stmt_transaction->bindParam(4, $new_supplier_balance, PDO::PARAM_STR);
                $stmt_transaction->bindParam(5, $remarks, PDO::PARAM_STR);
                $stmt_transaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $stmt_transaction->bindParam(7, $branch_id, PDO::PARAM_INT);
                if (!$stmt_transaction->execute()) {
                    throw new Exception('Failed to create supplier transaction');
                }

                // Update supplier balance
                $stmt_balance = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_balance->bindParam(1, $new_supplier_balance, PDO::PARAM_STR);
                $stmt_balance->bindParam(2, $supplier_id, PDO::PARAM_INT);
                $stmt_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_balance->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_balance->execute()) {
                    throw new Exception('Failed to update supplier balance');
                }
                
                // Update initial balance for next passenger
                $initial_supplier_balance = $new_supplier_balance;
            } else {
                // For non-regular suppliers, just record the transaction
                $stmt_transaction = $pdo->prepare("INSERT INTO supplier_transactions (
                    supplier_id, reference_id, transaction_type, amount, remarks, transaction_date, transaction_of, tenant_id, branch_id
                ) VALUES (?, ?, 'Debit', ?, ?, NOW(), 'ticket_sale', ?, ?)");
                $remarks = "Base amount of $base $currency deducted for ticket booking for $title $passengerName with PNR: $pnr.";
                $stmt_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
                $stmt_transaction->bindParam(2, $ticket_id, PDO::PARAM_INT);
                $stmt_transaction->bindParam(3, $base, PDO::PARAM_STR);
                $stmt_transaction->bindParam(4, $remarks, PDO::PARAM_STR);
                $stmt_transaction->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $stmt_transaction->bindParam(6, $branch_id, PDO::PARAM_INT);
                if (!$stmt_transaction->execute()) {
                    throw new Exception('Failed to create supplier transaction');
                }
            }

            // Process client transaction
            $new_client_balance = $initial_client_balance - $sold;
            
            // Insert client transaction
            $stmt_client_transaction = $pdo->prepare("INSERT INTO client_transactions (
                client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id
            ) VALUES (?, 'Debit', 'ticket_sale', ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $description = "Ticket booked for $title $passengerName with PNR: $pnr from $origin to $destination.";

            $stmt_client_transaction->bindParam(1, $soldTo, PDO::PARAM_INT);
            $stmt_client_transaction->bindParam(2, $ticket_id, PDO::PARAM_INT);
            $stmt_client_transaction->bindParam(3, $sold, PDO::PARAM_STR);
            $stmt_client_transaction->bindParam(4, $new_client_balance, PDO::PARAM_STR);
            $stmt_client_transaction->bindParam(5, $currency, PDO::PARAM_STR);
            $stmt_client_transaction->bindParam(6, $description, PDO::PARAM_STR);
            $stmt_client_transaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $stmt_client_transaction->bindParam(8, $branch_id, PDO::PARAM_INT);
            if (!$stmt_client_transaction->execute()) {
                throw new Exception('Failed to log client transaction');
            }

            // Update client balance for regular clients
            if ($client_type === 'regular') {
                $balance_column = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
                $stmt_deduct_client_balance = $pdo->prepare("UPDATE clients SET {$balance_column} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_deduct_client_balance->bindParam(1, $new_client_balance, PDO::PARAM_STR);
                $stmt_deduct_client_balance->bindParam(2, $soldTo, PDO::PARAM_INT);
                $stmt_deduct_client_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_deduct_client_balance->bindParam(4, $branch_id, PDO::PARAM_INT);
                if (!$stmt_deduct_client_balance->execute()) {
                    throw new Exception('Failed to update client balance');
                }

                // Update initial balance for next passenger
                $initial_client_balance = $new_client_balance;
            }
        }
        
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
            'departure_time' => $departureTime,
            'return_departure_time' => $returnDepartureTime,
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
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'ticket_bookings', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

        $new_values_json = json_encode($new_values);
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $record_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(3, $new_values_json, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();

        // Commit transaction
        $pdo->commit();

        // Send ticket notification email to client with PDF attachment
        require_once '../../includes/functions.php';

        // Get client email and name
        $stmt_client_email = $pdo->prepare("SELECT email, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_client_email->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_email->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_email->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_client_email->execute();
        $client_email_data = $stmt_client_email->fetch(PDO::FETCH_ASSOC);
        $client_email = $client_email_data['email'];
        $client_name = $client_email_data['name'];

        // Get agency settings
        $stmt_agency = $pdo->prepare("SELECT agency_name, email, phone, address FROM settings WHERE tenant_id = ?");
        $stmt_agency->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt_agency->execute();
        $agency_data = $stmt_agency->fetch(PDO::FETCH_ASSOC);
        $agencyName = $agency_data['agency_name'] ?? 'MTravels';
        $agencyEmail = $agency_data['email'] ?? 'info@mtravels.com';
        $agencyPhone = $agency_data['phone'] ?? '+93 (0) 123 456 789';
        $agencyAddress = $agency_data['address'] ?? '';

        if (!empty($client_email)) {
            // Prepare booking data for PDF
            $bookingData = [
                'pnr' => $pnr,
                'issue_date' => $issueDate,
                'airline' => $airline,
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
                'departure_time' => $departureTime,
                'return_destination' => $returnDestination,
                'return_date' => $returnDate,
                'return_departure_time' => $returnDepartureTime,
                'passengers' => array_map(function($passenger) {
                    return [
                        'name' => $passenger['name'],
                        'title' => $passenger['title'],
                    ];
                }, $passengers)
            ];

            // Generate PDF
            $pdfPath = generateTicketPDF($bookingData, $tenant_id);

            // Prepare email subject and body with professional flight ticket style
            $subject = "Flight Ticket Confirmation - {$pnr}";
            
            // Format departure and return dates
            $formattedDeparture = date('H:i / d. M. Y', strtotime($departureDate));
            $formattedReturn = !empty($returnDate) ? date('H:i / d. M. Y', strtotime($returnDate)) : '';
            
            // Create passenger details table rows
            $passengerRows = '';
            foreach ($passengers as $index => $passenger) {
                $nameParts = explode(' ', trim($passenger['name']), 2);
                $firstName = strtoupper($nameParts[0] ?? '');
                $lastName = strtoupper($nameParts[1] ?? '');
                $passengerRows .= "
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd; text-align: center; font-weight: bold;'>" . ($index + 1) . "</td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($firstName) . "</td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($lastName) . "</td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($passenger['title'] ?? '') . "</td>
                    </tr>
                ";
            }
            
            $body = "
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 11pt;
                        color: #000;
                        margin: 0;
                        padding: 0;
                        line-height: 1.3;
                        background-color: white;
                    }
                    
                    .container {
                        max-width: 800px;
                        margin: 0 auto;
                        background: white;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        padding: 30px;
                    }
                    
                    .header {
                        margin-bottom: 20px;
                        padding-bottom: 15px;
                        border-bottom: 2px solid #2c3e50;
                        display: table;
                        width: 100%;
                    }
                    
                    .header-left,
                    .header-center,
                    .header-right {
                        display: table-cell;
                        vertical-align: middle;
                        width: 33.33%;
                    }
                    
                    .header-left { text-align: left; }
                    .header-center { text-align: center; }
                    .header-right { text-align: right; }
                    
                    .company-name {
                        font-size: 18pt;
                        font-weight: bold;
                        color: #2c3e50;
                        text-transform: uppercase;
                        margin: 0;
                    }
                    
                    .contact-info {
                        font-size: 10pt;
                        color: #666;
                        line-height: 1.4;
                    }
                    
                    .contact-email {
                        font-weight: bold;
                        color: #2c3e50;
                    }
                    
                    .flight-details-header {
                        font-size: 14pt;
                        font-weight: bold;
                        color: #333;
                        margin: 25px 0 5px 0;
                    }
                    
                    .pnr-display {
                        font-size: 12pt;
                        color: #e74c3c;
                        font-weight: bold;
                        margin-bottom: 20px;
                    }
                    
                    .flight-section {
                        margin: 20px 0;
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        overflow: hidden;
                    }
                    
                    .section-header {
                        background-color: #f8f9fa;
                        padding: 12px 15px;
                        font-weight: bold;
                        font-size: 12pt;
                        color: #2c3e50;
                        border-bottom: 1px solid #ddd;
                    }
                    
                    .outbound { border-left: 4px solid #27ae60; }
                    .return { border-left: 4px solid #e67e22; }
                    
                    .flight-layout-table {
                        width: 100%;
                        border-collapse: collapse;
                    }
                    
                    .flight-layout-table td {
                        vertical-align: top;
                        padding: 15px;
                        border: none;
                    }
                    
                    .flight-departs {
                        width: 40%;
                    }
                    
                    .flight-center {
                        width: 20%;
                        text-align: center;
                        border-left: 1px solid #ddd;
                        border-right: 1px solid #ddd;
                    }
                    
                    .flight-arrives {
                        width: 40%;
                        text-align: right;
                    }
                    
                    .flight-label {
                        font-size: 11pt;
                        font-weight: bold;
                        color: #666;
                        margin-bottom: 8px;
                    }
                    
                    .flight-city {
                        font-size: 16pt;
                        font-weight: bold;
                        color: #000;
                        margin-bottom: 5px;
                    }
                    
                    .flight-time {
                        font-size: 11pt;
                        color: #333;
                    }
                    
                    .flight-number {
                        font-size: 14pt;
                        font-weight: bold;
                        color: #000;
                        margin-bottom: 8px;
                    }
                    
                    .plane-icon {
                        font-size: 18pt;
                        color: #666;
                    }
                    
                    .passengers-header {
                        font-size: 14pt;
                        font-weight: bold;
                        margin: 30px 0 15px 0;
                        color: #2c3e50;
                        text-decoration: underline;
                    }
                    
                    .passengers-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                        font-size: 10pt;
                    }
                    
                    .passengers-table th {
                        background-color: #2c3e50;
                        color: white;
                        font-weight: bold;
                        padding: 10px 8px;
                        border: 1px solid #2c3e50;
                        text-align: left;
                    }
                    
                    .passengers-table tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    
                    .sno-col {
                        width: 50px;
                        text-align: center;
                        font-weight: bold;
                    }
                    
                    .name-col {
                        width: 35%;
                    }
                    
                    .passport-col {
                        width: 25%;
                        font-weight: bold;
                    }
                    
                    .amount-info {
                        background-color: #f8f9fa;
                        border-left: 5px solid #2c3e50;
                        border-radius: 5px;
                        padding: 15px;
                        margin: 20px 0;
                        font-size: 11pt;
                    }
                    
                    .amount-total {
                        font-size: 14pt;
                        font-weight: bold;
                        color: #27ae60;
                        text-align: center;
                        margin-top: 10px;
                    }
                    
                    .footer {
                        text-align: center;
                        margin-top: 30px;
                        color: #666;
                        font-size: 12px;
                        border-top: 1px solid #ddd;
                        padding-top: 15px;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='header-left'>
                            <strong>" . strtoupper($agencyName) . "</strong>
                        </div>
                        <div class='header-center'>
                            <div class='company-name'>{$agencyName}</div>
                        </div>
                        <div class='header-right'>
                            <div class='contact-info'>
                                <div class='contact-email'>{$agencyEmail}</div>
                                <div>{$agencyPhone}</div>
                                " . (!empty($agencyAddress) ? "<div style='font-size: 9pt; margin-top: 2px;'>{$agencyAddress}</div>" : "") . "
                            </div>
                        </div>
                    </div>

                    <div class='flight-details-header'>Your Flight Details</div>
                    <div class='pnr-display'>PNR: {$pnr}</div>

                    <!-- Outbound Journey -->
                    <div class='flight-section outbound'>
                        <div class='section-header'>
                            🛫 Outbound Journey
                        </div>
                        <table class='flight-layout-table'>
                            <tr>
                                <td class='flight-departs'>
                                    <div class='flight-label'>Departs</div>
                                    <div class='flight-city'>" . strtoupper($origin) . "</div>
                                    <div class='flight-time'>{$formattedDeparture}</div>
                                </td>
                                <td class='flight-center'>
                                    <div class='flight-number'>" . strtoupper($airline) . "</div>
                                    <div class='plane-icon'>✈</div>
                                </td>
                                <td class='flight-arrives'>
                                    <div class='flight-label'>Arrives</div>
                                    <div class='flight-city'>" . strtoupper($destination) . "</div>
                                    <div class='flight-time'>{$formattedDeparture}</div>
                                </td>
                            </tr>
                        </table>
                    </div>";

                    if (!empty($returnDate)) {
                        $body .= "
                    <!-- Return Journey -->
                    <div class='flight-section return'>
                        <div class='section-header'>
                            🛬 Return Journey
                        </div>
                        <table class='flight-layout-table'>
                            <tr>
                                <td class='flight-departs'>
                                    <div class='flight-label'>Departs</div>
                                    <div class='flight-city'>" . strtoupper($destination) . "</div>
                                    <div class='flight-time'>{$formattedReturn}</div>
                                </td>
                                <td class='flight-center'>
                                    <div class='flight-number'>" . strtoupper($airline) . "</div>
                                    <div class='plane-icon'>✈</div>
                                </td>
                                <td class='flight-arrives'>
                                    <div class='flight-label'>Arrives</div>
                                    <div class='flight-city'>" . strtoupper($origin) . "</div>
                                    <div class='flight-time'>{$formattedReturn}</div>
                                </td>
                            </tr>
                        </table>
                    </div>";
                    }

                    $body .= "
                    <div class='passengers-header'>Passengers Details</div>
                    
                    <table class='passengers-table'>
                        <thead>
                            <tr>
                                <th class='sno-col'>S/NO</th>
                                <th class='name-col'>First Name</th>
                                <th class='name-col'>Last Name</th>
                                <th class='passport-col'>Title</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$passengerRows}
                        </tbody>
                    </table>
                    
                    <div class='amount-info'>
                        <strong>Booking Details:</strong><br>
                        Issue Date: " . date('d. M. Y', strtotime($issueDate)) . "<br>
                        Currency: {$currency}<br>
                        Total Passengers: " . count($passengers) . "
                        <div class='amount-total'>Total Amount: {$totalSold} {$currency}</div>
                    </div>

                    <div class='footer'>
                        <p><strong>Please find your detailed ticket confirmation attached as a PDF.</strong></p>
                        <p>If you have any questions about this booking, please don't hesitate to contact our support team.</p>
                        <p>Best regards,<br><strong>{$agencyName} Team</strong></p>
                        <p style='margin-top: 20px; font-size: 10px;'>This is an automated notification. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            // Send notification email with PDF attachment
            sendTicketNotificationWithAttachment($client_email, $client_name, $subject, $body, $pdfPath);

            // Send WhatsApp notification to client (if configured)
            try {
                $whatsappManager = new WhatsAppManager($tenant_id);
                $whatsapp_result = $whatsappManager->sendBookingNotification('ticket', $main_booking_id);
                
                if ($whatsapp_result['success']) {
                    error_log("WhatsApp notification sent for Ticket booking ID: $main_booking_id");
                } else {
                    error_log("WhatsApp notification failed for Ticket booking ID: $main_booking_id - " . $whatsapp_result['message']);
                }
            } catch (Exception $e) {
                // Don't fail the operation if WhatsApp fails
                error_log("WhatsApp integration error for Ticket booking ID: $main_booking_id - " . $e->getMessage());
            }

            // Clean up temporary PDF file
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }

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
        $pdo->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
