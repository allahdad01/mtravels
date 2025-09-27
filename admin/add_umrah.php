<?php
session_start();
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();


$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;
// Establish a secure connection using mysqli with error handling
require_once '../includes/conn.php';


if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]));
}

// Retrieve form data with sanitization and validation
$family_id = filter_input(INPUT_POST, 'family_id', FILTER_SANITIZE_NUMBER_INT);
$soldTo = filter_input(INPUT_POST, 'soldTo', FILTER_SANITIZE_STRING);
$paidTo = filter_input(INPUT_POST, 'paidTo', FILTER_SANITIZE_STRING);
$entry_date = filter_input(INPUT_POST, 'entry_date', FILTER_SANITIZE_STRING);
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING);
$gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
$passport_number = filter_input(INPUT_POST, 'passport_number', FILTER_SANITIZE_STRING);
$passport_expiry = filter_input(INPUT_POST, 'passport_expiry', FILTER_SANITIZE_STRING);
$id_type = filter_input(INPUT_POST, 'id_type', FILTER_SANITIZE_STRING);
$flight_date = filter_input(INPUT_POST, 'flight_date', FILTER_SANITIZE_STRING);
$return_date = filter_input(INPUT_POST, 'return_date', FILTER_SANITIZE_STRING);
$duration = filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_STRING);
$room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_STRING);
$received_bank_payment = filter_input(INPUT_POST, 'received_bank_payment', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$bank_receipt_number = filter_input(INPUT_POST, 'bank_receipt_number', FILTER_SANITIZE_STRING);
$paid = filter_input(INPUT_POST, 'paid', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$due = filter_input(INPUT_POST, 'due', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);
$relation = filter_input(INPUT_POST, 'relation', FILTER_SANITIZE_STRING);
$g_name = filter_input(INPUT_POST, 'g_name', FILTER_SANITIZE_STRING);
$father_name = filter_input(INPUT_POST, 'father_name', FILTER_SANITIZE_STRING);
$discount = filter_input(INPUT_POST, 'discount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

// Process services
$services = $_POST['services'] ?? [];
$total_base_price = 0;
$total_sold_price = 0;
$total_profit = 0;

$processed_services = [];
foreach ($services as $service) {
    $service_type = filter_var($service['service_type'], FILTER_SANITIZE_STRING);
    $supplier_id = filter_var($service['supplier_id'], FILTER_SANITIZE_NUMBER_INT);
    $currency = filter_var($service['currency'], FILTER_SANITIZE_STRING);
    $base_price = filter_var($service['base_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $sold_price = filter_var($service['sold_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $profit = $sold_price - $base_price;

    if (!empty($service_type) && !empty($supplier_id)) {
        $processed_services[] = [
            'service_type' => $service_type,
            'supplier_id' => $supplier_id,
            'currency' => $currency,
            'base_price' => $base_price,
            'sold_price' => $sold_price,
            'profit' => $profit
        ];

        $total_base_price += $base_price;
        $total_sold_price += $sold_price;
        $total_profit += $profit;
    }
}

// Apply discount to total sold price
$total_sold_price -= $discount;
$total_profit = $total_sold_price - $total_base_price;


// Validate passport expiry (must be at least 6 months from today)
if (!empty($passport_expiry)) {
    $today = new DateTime();
    $sixMonthsLater = (new DateTime())->modify('+6 months');
    $expiryDate = new DateTime($passport_expiry);
    
    if ($expiryDate < $sixMonthsLater) {
        die(json_encode([
            'success' => false, 
            'message' => 'Passport must be valid for at least 6 months from today for Umrah visa requirements'
        ]));
    }
}

// Use prepared statements to securely insert data into the database
$stmt = $conn->prepare("
    INSERT INTO umrah_bookings (
        family_id, sold_to, paid_to, entry_date, name,
        dob, gender, passport_number, passport_expiry,
        id_type, flight_date, return_date, duration, room_type,
        price, sold_price, profit, received_bank_payment,
        bank_receipt_number, paid, due,
        created_by, remarks, relation, gfname, fname, discount,
        tenant_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 
              ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
              ?, ?, ?, ?, ?, ?, ?, ?, ?, 
              ?)
");

$stmt->bind_param(
    "isssssssssssssddddsddisssssi",
    $family_id, $soldTo, $paidTo, $entry_date, $name, $dob, $gender, $passport_number,
    $passport_expiry, $id_type, $flight_date, $return_date, $duration, $room_type,
    $total_base_price, $total_sold_price, $total_profit, $received_bank_payment, $bank_receipt_number, $paid, $due,
    $user_id, $remarks, $relation, $g_name, $father_name, $discount, $tenant_id
);

// Execute the query
if ($stmt->execute()) {
    $umrah_id = $stmt->insert_id;  // Get the inserted booking ID

    // Insert services
    if (!empty($processed_services)) {
        $service_stmt = $conn->prepare("
            INSERT INTO umrah_booking_services (
                tenant_id, booking_id, service_type, supplier_id, base_price, sold_price, profit, currency
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($processed_services as $service) {
            $service_stmt->bind_param(
                "iisiddds",
                $tenant_id, $umrah_id, $service['service_type'], $service['supplier_id'],
                $service['base_price'], $service['sold_price'], $service['profit'], $service['currency']
            );
            $service_stmt->execute();
        }
        $service_stmt->close();
    }

    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

// Fetch client details
$stmt_client_info = $conn->prepare("SELECT name, usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ?");
$stmt_client_info->bind_param("ii", $soldTo, $tenant_id);
if (!$stmt_client_info->execute()) {
    throw new Exception("Failed to fetch client info: " . $stmt_client_info->error);
}
$client_data = $stmt_client_info->get_result()->fetch_assoc();
$client_name = $client_data['name'];
$usd_balance = $client_data['usd_balance'];
$afs_balance = $client_data['afs_balance'];
$client_type = $client_data['client_type'];
$stmt_client_info->close();

// Record client transaction for total sold price (assuming USD for simplicity, or we can use the first service's currency)
$client_currency = 'USD'; // Default, or determine based on services
$current_balance_client = ($client_currency === 'USD') ? $usd_balance : $afs_balance;
$new_balance_client = $current_balance_client - $total_sold_price;

$description = "Client was debited for umrah booking for $name";
$stmt_client_transaction = $conn->prepare("INSERT INTO client_transactions (
    client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id
) VALUES (?, 'Debit', 'umrah', ?, ?, ?, ?, ?, NOW(), ?)");
$stmt_client_transaction->bind_param("iiddssi", $soldTo, $umrah_id, $total_sold_price, $new_balance_client, $client_currency, $description, $tenant_id);
if (!$stmt_client_transaction->execute()) {
    throw new Exception('Failed to log client transaction: ' . $stmt_client_transaction->error);
}
$stmt_client_transaction->close();

// Process transactions for each service/supplier
foreach ($processed_services as $service) {
    $supplier_id = $service['supplier_id'];
    $base_price = $service['base_price'];
    $currency = $service['currency'];
    $service_type = $service['service_type'];

    // Fetch supplier details
    $stmt_supplier_info = $conn->prepare("SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt_supplier_info->bind_param("ii", $supplier_id, $tenant_id);
    if (!$stmt_supplier_info->execute()) {
        throw new Exception("Failed to fetch supplier info: " . $stmt_supplier_info->error);
    }
    $supplier_data = $stmt_supplier_info->get_result()->fetch_assoc();
    $supplier_name = $supplier_data['name'];
    $supplier_type = $supplier_data['supplier_type'];
    $balance = $supplier_data['balance'];
    $stmt_supplier_info->close();

    $new_balance_supplier = $balance - $base_price;

    // Insert supplier transaction
    $stmt_transaction = $conn->prepare("INSERT INTO supplier_transactions (
        supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id
    ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'umrah', ?)");
    $remarks = "Base amount of $base_price $currency deducted for umrah $service_type.";
    $stmt_transaction->bind_param("iiddsi", $supplier_id, $umrah_id, $base_price, $new_balance_supplier, $remarks, $tenant_id);
    if (!$stmt_transaction->execute()) {
        throw new Exception('Failed to create supplier transaction: ' . $stmt_transaction->error);
    }
    $stmt_transaction->close();

    // Update supplier balance if external
    if ($supplier_type === 'External') {
        $stmt_balance = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?");
        $stmt_balance->bind_param("did", $base_price, $supplier_id, $tenant_id);
        if (!$stmt_balance->execute()) {
            throw new Exception('Failed to update supplier balance: ' . $stmt_balance->error);
        }
    }
}

// Update client's balance
if ($client_type === 'regular') {
    if ($client_currency === 'USD') {
        $stmt_deduct_client_balance = $conn->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
    } else {
        $stmt_deduct_client_balance = $conn->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
    }

    $stmt_deduct_client_balance->bind_param("did", $total_sold_price, $soldTo, $tenant_id);

    if (!$stmt_deduct_client_balance->execute()) {
        throw new Exception('Failed to update client balance: ' . $stmt_deduct_client_balance->error);
    }
    $stmt_deduct_client_balance->close();
}


// Log the activity
$old_values = json_encode([]);
$new_values = json_encode([
    'family_id' => $family_id,
    'sold_to' => $soldTo,
    'paid_to' => $paidTo,
    'name' => $name,
    'passport_number' => $passport_number,
    'flight_date' => $flight_date,
    'return_date' => $return_date,
    'room_type' => $room_type,
    'total_base_price' => $total_base_price,
    'total_sold_price' => $total_sold_price,
    'total_profit' => $total_profit,
    'services' => $processed_services,
    'remarks' => $remarks,
    'discount' => $discount
]);

$user_id = $_SESSION['user_id'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt_log = $conn->prepare("
    INSERT INTO activity_log 
    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
    VALUES (?, 'add', 'umrah_bookings', ?, ?, ?, ?, ?, NOW(), ?)
");
$stmt_log->bind_param("iissssi", $user_id, $umrah_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
$stmt_log->execute();
$stmt_log->close();

// Close the statement and connection
$stmt->close();
$conn->close();
?>
