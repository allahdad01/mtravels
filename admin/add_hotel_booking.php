<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/conn.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Check connection
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Validate and sanitize input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags($data));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $gender = sanitize_input($_POST['gender']);
    $check_in_date = sanitize_input($_POST['check_in_date']);
    $check_out_date = sanitize_input($_POST['check_out_date']);
    $issue_date = sanitize_input($_POST['issue_date']);
    $accommodation_details = sanitize_input($_POST['accommodation_details']);
    $supplier_id = intval($_POST['supplier_id']);
    $sold_to = sanitize_input($_POST['sold_to']);
    $contact_no = sanitize_input($_POST['contact_no']);
    $base_amount = floatval($_POST['base_amount']);
    $sold_amount = floatval($_POST['sold_amount']);
    $profit = floatval($_POST['profit']);
    $currency = sanitize_input($_POST['currency']);
    $remarks = sanitize_input($_POST['remarks']);
    $order_id = sanitize_input($_POST['order_id']);
    $paid_to = sanitize_input($_POST['paid_to']);

    // Validate required fields
    if (empty($title) || empty($first_name) || empty($last_name) || empty($gender) || empty($check_in_date) ||
        empty($check_out_date) || empty($accommodation_details) || empty($supplier_id) || empty($sold_to) ||
        empty($contact_no) || empty($sold_amount) || empty($profit) || empty($currency)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);


// Validate paid_to
$paid_to = isset($_POST['paid_to']) ? DbSecurity::validateInput($_POST['paid_to'], 'string', ['maxlength' => 255]) : null;

// Validate order_id
$order_id = isset($_POST['order_id']) ? DbSecurity::validateInput($_POST['order_id'], 'int', ['min' => 0]) : null;

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate profit
$profit = isset($_POST['profit']) ? DbSecurity::validateInput($_POST['profit'], 'float', ['min' => 0]) : null;

// Validate sold_amount
$sold_amount = isset($_POST['sold_amount']) ? DbSecurity::validateInput($_POST['sold_amount'], 'float', ['min' => 0]) : null;

// Validate base_amount
$base_amount = isset($_POST['base_amount']) ? DbSecurity::validateInput($_POST['base_amount'], 'float', ['min' => 0]) : null;

// Validate contact_no
$contact_no = isset($_POST['contact_no']) ? DbSecurity::validateInput($_POST['contact_no'], 'string', ['maxlength' => 255]) : null;

// Validate sold_to
$sold_to = isset($_POST['sold_to']) ? DbSecurity::validateInput($_POST['sold_to'], 'string', ['maxlength' => 255]) : null;

// Validate supplier_id
$supplier_id = isset($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int', ['min' => 0]) : null;

// Validate accommodation_details
$accommodation_details = isset($_POST['accommodation_details']) ? DbSecurity::validateInput($_POST['accommodation_details'], 'string', ['maxlength' => 255]) : null;

// Validate issue_date
$issue_date = isset($_POST['issue_date']) ? DbSecurity::validateInput($_POST['issue_date'], 'date') : null;

// Validate check_out_date
$check_out_date = isset($_POST['check_out_date']) ? DbSecurity::validateInput($_POST['check_out_date'], 'date') : null;

// Validate check_in_date
$check_in_date = isset($_POST['check_in_date']) ? DbSecurity::validateInput($_POST['check_in_date'], 'date') : null;

// Validate gender
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

// Validate last_name
$last_name = isset($_POST['last_name']) ? DbSecurity::validateInput($_POST['last_name'], 'string', ['maxlength' => 255]) : null;

// Validate first_name
$first_name = isset($_POST['first_name']) ? DbSecurity::validateInput($_POST['first_name'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into hotel_bookings
        $stmt = $conn->prepare("INSERT INTO hotel_bookings (title, first_name, last_name, gender, order_id, check_in_date, check_out_date, issue_date, accommodation_details, supplier_id, sold_to, paid_to, contact_no, base_amount, sold_amount, profit, currency, remarks, created_by, tenant_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssdiddsssssii",
            $title, $first_name, $last_name, $gender, $order_id, $check_in_date, $check_out_date, $issue_date,
            $accommodation_details, $supplier_id, $sold_to, $paid_to, $contact_no,
            $base_amount, $sold_amount, $profit, $currency, $remarks, $user_id, $tenant_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error inserting hotel booking: " . $stmt->error);
        }

        $booking_id = $conn->insert_id;
        $stmt->close();

        // Fetch client details
        $stmtClient = $conn->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?");
        $stmtClient->bind_param("ii", $sold_to, $tenant_id);
        if (!$stmtClient->execute()) {
            throw new Exception("Failed to fetch client details");
        }
        $clientResult = $stmtClient->get_result();
        $clientData = $clientResult->fetch_assoc();
        $stmtClient->close();

        if (!$clientData) {
            throw new Exception("Client not found");
        }

        // Handle client balance and transactions for regular clients
        if ($clientData['client_type'] === 'regular') {
            $currentBalance = ($currency === 'USD') ? $clientData['usd_balance'] : $clientData['afs_balance'];
            $newBalance = $currentBalance - $sold_amount;

            // Update client balance
            if ($currency === 'USD') {
                $stmtUpdateBalance = $conn->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ?");
            } else {
                $stmtUpdateBalance = $conn->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ?");
            }
            $stmtUpdateBalance->bind_param("di", $sold_amount, $sold_to);
            if (!$stmtUpdateBalance->execute()) {
                throw new Exception("Failed to update client balance");
            }
            $stmtUpdateBalance->close();

            // Insert client transaction
            $stmtClientTrans = $conn->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, created_at, tenant_id) 
                                             VALUES (?, 'Debit', ?, ?, ?, 'hotel', ?, ?, NOW(), ?)");
            $description = "Hotel booking for $title $first_name $last_name";
            $stmtClientTrans->bind_param("isddssi", $sold_to, $currency, $sold_amount, $newBalance, $description, $booking_id, $tenant_id);
            if (!$stmtClientTrans->execute()) {
                throw new Exception("Failed to create client transaction");
            }
            $stmtClientTrans->close();
        }

        // Fetch supplier details
        $stmtSupplier = $conn->prepare("SELECT name, balance, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmtSupplier->bind_param("ii", $supplier_id, $tenant_id);
        if (!$stmtSupplier->execute()) {
            throw new Exception("Failed to fetch supplier details");
        }
        $supplierResult = $stmtSupplier->get_result();
        $supplierData = $supplierResult->fetch_assoc();
        $stmtSupplier->close();

        if (!$supplierData) {
            throw new Exception("Supplier not found");
        }

        // Update supplier balance only if it's an external supplier
        if ($supplierData['supplier_type'] === 'External') {
            $stmtUpdateSupplier = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
            $stmtUpdateSupplier->bind_param("di", $base_amount, $supplier_id);
            if (!$stmtUpdateSupplier->execute()) {
                throw new Exception("Failed to update supplier balance");
            }
            $stmtUpdateSupplier->close();

            // Insert supplier transaction with balance
            $supplierNewBalance = $supplierData['balance'] - $base_amount;
            
            $stmtSupplierTrans = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, balance, transaction_of, remarks, reference_id, transaction_date, tenant_id) 
                                               VALUES (?, 'Debit', ?, ?, 'hotel', ?, ?, NOW(), ?)");
            $supplierDescription = "Hotel booking for $title $first_name $last_name";
            $stmtSupplierTrans->bind_param("isdssi", $supplier_id, $base_amount, $supplierNewBalance, $supplierDescription, $booking_id, $tenant_id);
        } else {
            // For non-external suppliers, just record the transaction without balance
            $stmtSupplierTrans = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, transaction_of, remarks, reference_id, transaction_date, tenant_id) 
                                               VALUES (?, 'Debit', ?, 'hotel', ?, ?, NOW(), ?)");
            $supplierDescription = "Hotel booking for $title $first_name $last_name";
            $stmtSupplierTrans->bind_param("isdsi", $supplier_id, $base_amount, $supplierDescription, $booking_id, $tenant_id);
        }

        if (!$stmtSupplierTrans->execute()) {
            throw new Exception("Failed to create supplier transaction");
        }
        $stmtSupplierTrans->close();

        // Commit transaction
        $conn->commit();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'title' => $title,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'check_in_date' => $check_in_date,
            'check_out_date' => $check_out_date,
            'accommodation_details' => $accommodation_details,
            'supplier_id' => $supplier_id,
            'sold_to' => $sold_to,
            'base_amount' => $base_amount,
            'sold_amount' => $sold_amount,
            'profit' => $profit,
            'currency' => $currency,
            'paid_to' => $paid_to,
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'hotel_bookings', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt_log->bind_param("iissssi", $user_id, $booking_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();
        
        echo json_encode(["success" => true, "message" => "Hotel booking added successfully."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
}
$conn->close();
?>

