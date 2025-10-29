<?php
session_start();
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

$user_id = $_SESSION['user_id'] ?? 0;

header('Content-Type: application/json');
if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}
require_once '../includes/conn.php';
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    error_log("DB Error: " . $conn->connect_error);
    exit;
}

try {
    // Inputs & Validation
    $requiredFields = [
        'supplier', 'soldto', 'paidto', 'phone', 'title', 'gender',
        'passengerName', 'passNum', 'country', 'visaType', 'receiveDate',
        'appliedDate', 'issuedDate', 'base', 'sold', 'curr', 'description'
    ];
    foreach ($requiredFields as $field) {


// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate curr
$curr = isset($_POST['curr']) ? DbSecurity::validateInput($_POST['curr'], 'string', ['maxlength' => 255]) : null;

// Validate sold
$sold = isset($_POST['sold']) ? DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]) : null;

// Validate base
$base = isset($_POST['base']) ? DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]) : null;

// Validate issuedDate
$issuedDate = isset($_POST['issuedDate']) ? DbSecurity::validateInput($_POST['issuedDate'], 'date') : null;

// Validate appliedDate
$appliedDate = isset($_POST['appliedDate']) ? DbSecurity::validateInput($_POST['appliedDate'], 'date') : null;

// Validate receiveDate
$receiveDate = isset($_POST['receiveDate']) ? DbSecurity::validateInput($_POST['receiveDate'], 'date') : null;

// Validate visaType
$visaType = isset($_POST['visaType']) ? DbSecurity::validateInput($_POST['visaType'], 'string', ['maxlength' => 255]) : null;

// Validate country
$country = isset($_POST['country']) ? DbSecurity::validateInput($_POST['country'], 'string', ['maxlength' => 255]) : null;

// Validate passNum
$passNum = isset($_POST['passNum']) ? DbSecurity::validateInput($_POST['passNum'], 'string', ['maxlength' => 255]) : null;

// Validate passengerName
$passengerName = isset($_POST['passengerName']) ? DbSecurity::validateInput($_POST['passengerName'], 'string', ['maxlength' => 255]) : null;

// Validate gender
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate paidto
$paidto = isset($_POST['paidto']) ? DbSecurity::validateInput($_POST['paidto'], 'string', ['maxlength' => 255]) : null;

// Validate soldto
$soldto = isset($_POST['soldto']) ? DbSecurity::validateInput($_POST['soldto'], 'string', ['maxlength' => 255]) : null;

// Validate supplier
$supplier = isset($_POST['supplier']) ? DbSecurity::validateInput($_POST['supplier'], 'int', ['min' => 0]) : null;
     
    }

    // Variable declarations
    $supplier = intval($_POST['supplier']);
    $soldTo = $_POST['soldto'];
    $paidTo = $_POST['paidto'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $title = $conn->real_escape_string($_POST['title']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $applicantName = $conn->real_escape_string($_POST['passengerName']);
    $passportNumber = $conn->real_escape_string($_POST['passNum']);
    $country = $conn->real_escape_string($_POST['country']);
    $visaType = $conn->real_escape_string($_POST['visaType']);
   $receiveDate = $_POST['receiveDate'] ?? null;
    $appliedDate = $_POST['appliedDate'] ?? null;
    $issuedDate = $_POST['issuedDate'] ?? null;
    $base = floatval($_POST['base']);
    $sold = floatval($_POST['sold']);
    $currency = $conn->real_escape_string($_POST['curr']);
    $description = $conn->real_escape_string($_POST['description']);
    $profit = $sold - $base;
    $username = $_SESSION['name'];

    // Add debug logging to verify the values
    error_log("Debug - soldTo: $soldTo, paidTo: $paidTo, issuedDate: $issuedDate");

    // Begin transaction
    $conn->begin_transaction();

    // Check if supplier is internal or external
    $stmtSupplier = $conn->prepare("SELECT name, supplier_type,balance FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmtSupplier->bind_param("ii", $supplier, $tenant_id);
    if (!$stmtSupplier->execute()) {
        throw new Exception("Failed to fetch supplier details.");
    }
    $stmtSupplier->bind_result($supplierName, $supplierType,$balance);
    if (!$stmtSupplier->fetch()) {
        $stmtSupplier->close();
        throw new Exception("Supplier not found.");
    }
    $stmtSupplier->close();

    // Fetch PaidTo account name
    $stmtAccount = $conn->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ?");
    $stmtAccount->bind_param("ii", $paidTo, $tenant_id);
    if (!$stmtAccount->execute()) {
        throw new Exception("Failed to execute the query for fetching 'paidto' account.");
    }
    $stmtAccount->bind_result($paidToName);
    if (!$stmtAccount->fetch() || empty($paidToName)) {
        $stmtAccount->close();
        throw new Exception("Account name for 'paidto' not found. Please verify the input data.");
    }
    $stmtAccount->close();

    // Fetch client details
    $stmtClient = $conn->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?");
    $stmtClient->bind_param("ii", $soldTo, $tenant_id);
    if (!$stmtClient->execute()) {
        throw new Exception("Failed to fetch client details.");
    }
    $stmtClient->bind_result($clientName, $clientType, $usdBalance, $afsBalance);
    if (!$stmtClient->fetch() || empty($clientType)) {
        $stmtClient->close();
        throw new Exception("Client not found. Please verify the soldTo ID.");
    }
    $stmtClient->close();

    // Insert visa applications
    $stmtVisa = $conn->prepare("
        INSERT INTO visa_applications (
            supplier, sold_to, paid_to, phone, title, gender, applicant_name,
            passport_number, country, visa_type, receive_date, applied_date,
            issued_date, base, sold, profit, currency, remarks, created_at, updated_at, created_by, tenant_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
    ");
    $stmtVisa->bind_param(
        "ssssssssssssssssssii",
        $supplier, $soldTo, $paidTo, $phone, $title, $gender, $applicantName,
        $passportNumber, $country, $visaType, $receiveDate, $appliedDate,
        $issuedDate, $base, $sold, $profit, $currency, $description, $user_id, $tenant_id
    );

    if (!$stmtVisa->execute()) {
        throw new Exception('Insert Visa Error: ' . $stmtVisa->error . ' SQL: ' . $stmtVisa->sqlstate);
    }

    $visaApplicationId = $conn->insert_id;
    $stmtVisa->close();

    // Create supplier transaction for both internal and external suppliers
    // Calculate new balance only for external suppliers
    $newBalance = ($supplierType === 'External') ? $balance - $base : 0;
    $description = "Visa purchase for $applicantName - $passportNumber";

    $updateSupplierBalance = $conn->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?");
    $updateSupplierBalance->bind_param("did", $base, $supplier, $tenant_id);
    $updateSupplierBalance->execute();
    $updateSupplierBalance->close();

    $stmtSupplierTrans = $conn->prepare("
        INSERT INTO supplier_transactions (
            supplier_id, transaction_type, amount, transaction_of,
            reference_id, remarks, transaction_date, balance, tenant_id
        ) VALUES (?, 'Debit', ?, 'visa_sale', ?, ?, NOW(), ?, ?)
    ");
    $stmtSupplierTrans->bind_param(
        "idsssi", 
        $supplier, 
        $base,
        $visaApplicationId, 
        $description,
        $newBalance,
        $tenant_id
    );

    if (!$stmtSupplierTrans->execute()) {
        throw new Exception('Failed to create supplier transaction: ' . $stmtSupplierTrans->error);
    }
    $stmtSupplierTrans->close();

    // Fetch client details and handle balance deduction (only once)
    $stmtClient = $conn->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?");
    $stmtClient->bind_param("ii", $soldTo, $tenant_id);
    if (!$stmtClient->execute()) {
        throw new Exception("Failed to fetch client details.");
    }
    $stmtClient->bind_result($clientName, $clientType, $usdBalance, $afsBalance);
    if (!$stmtClient->fetch() || empty($clientType)) {
        $stmtClient->close();
        throw new Exception("Client not found. Please verify the soldTo ID.");
    }
    $stmtClient->close();

    // Handle client balance and transactions
    if ($clientType === 'regular') {
        // Get current balance based on currency
        $currentBalance = ($currency === 'USD') ? $usdBalance : $afsBalance;
        $newBalance = $currentBalance - $sold;

        if ($currency === 'USD') {
            $stmtUpdateClientBalance = $conn->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
            $stmtUpdateClientBalance->bind_param("did", $sold, $soldTo, $tenant_id);
        } elseif ($currency === 'AFS') {
            $stmtUpdateClientBalance = $conn->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
            $stmtUpdateClientBalance->bind_param("did", $sold, $soldTo, $tenant_id);
        } else {
            throw new Exception("Unsupported currency type.");
        }

        if (!$stmtUpdateClientBalance->execute()) {
            throw new Exception("Failed to deduct client balance: " . $stmtUpdateClientBalance->error);
        }

        $stmtUpdateClientBalance->close();

        // Insert into client_transactions with balance
        $stmtTransaction = $conn->prepare("
            INSERT INTO client_transactions (
                client_id, type, currency, amount, balance, transaction_of, description, reference_id, created_at, tenant_id
            ) VALUES (?, 'Debit', ?, ?, ?, 'visa_sale', ?, ?, NOW(), ?)
        ");
        $description = "Visa booking for $applicantName";
        $stmtTransaction->bind_param("isddssi", $soldTo, $currency, $sold, $newBalance, $description, $visaApplicationId, $tenant_id);
        if (!$stmtTransaction->execute()) {
            throw new Exception('Failed to create client transaction: ' . $stmtTransaction->error);
        }
        $stmtTransaction->close();
    } else {
        // For non-regular clients, insert transaction without affecting balance
        $stmtTransaction = $conn->prepare("
            INSERT INTO client_transactions (
                client_id, type, currency, amount, transaction_of, description, reference_id, created_at, tenant_id
            ) VALUES (?, 'Debit', ?, ?, 'visa_sale', ?, ?, NOW(), ?)
        ");
        $description = "Visa booking for $applicantName";
        $stmtTransaction->bind_param("isdssi", $soldTo, $currency, $sold, $description, $visaApplicationId, $tenant_id);
        if (!$stmtTransaction->execute()) {
            throw new Exception('Failed to create client transaction: ' . $stmtTransaction->error);
        }
        $stmtTransaction->close();
    }

    // Commit transaction
    $conn->commit();

    // Log the activity
    $old_values = json_encode([]);
    $new_values = json_encode([
        'supplier' => $supplier,
        'sold_to' => $soldTo,
        'paid_to' => $paidTo,
        'applicant_name' => $applicantName,
        'passport_number' => $passportNumber,
        'visa_type' => $visaType,
        'base' => $base,
        'sold' => $sold,
        'profit' => $profit,
        'currency' => $currency
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmtLog = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'add', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmtLog->bind_param("iissssi", $user_id, $visaApplicationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmtLog->execute();
    $stmtLog->close();

    echo json_encode(['status' => 'success', 'message' => 'Visa application, transaction, and notification added successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
