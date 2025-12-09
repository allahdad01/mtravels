<?php
session_start();

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Include WhatsApp Manager for notifications
require_once '../api/whatsapp/WhatsAppManager.php';

$user_id = $_SESSION['user_id'] ?? 0;

header('Content-Type: application/json');
if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}
require_once '../../includes/db.php';
if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    error_log("DB Error: PDO connection failed");
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
    $phone = $_POST['phone'];
    $title = $_POST['title'];
    $gender = $_POST['gender'];
    $applicantName = $_POST['passengerName'];
    $passportNumber = $_POST['passNum'];
    $country = $_POST['country'];
    $visaType = $_POST['visaType'];
   $receiveDate = $_POST['receiveDate'] ?? null;
    $appliedDate = $_POST['appliedDate'] ?? null;
    $issuedDate = $_POST['issuedDate'] ?? null;
    $base = floatval($_POST['base']);
    $sold = floatval($_POST['sold']);
    $currency = $_POST['curr'];
    $description = $_POST['description'];
    $profit = $sold - $base;
    $username = $_SESSION['name'];

    // Add debug logging to verify the values
    error_log("Debug - soldTo: $soldTo, paidTo: $paidTo, issuedDate: $issuedDate");

    // Begin transaction
    $pdo->beginTransaction();

    // Check if supplier is internal or external
    $stmtSupplier = $pdo->prepare("SELECT name, supplier_type,balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtSupplier->bindParam(1, $supplier, PDO::PARAM_INT);
    $stmtSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmtSupplier->execute()) {
        throw new PDOException("Failed to fetch supplier details.");
    }
    $supplierData = $stmtSupplier->fetch(PDO::FETCH_ASSOC);
    if (!$supplierData) {
        throw new PDOException("Supplier not found.");
    }
    $supplierName = $supplierData['name'];
    $supplierType = $supplierData['supplier_type'];
    $balance = $supplierData['balance'];

    // Fetch PaidTo account name
    $stmtAccount = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtAccount->bindParam(1, $paidTo, PDO::PARAM_INT);
    $stmtAccount->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtAccount->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmtAccount->execute()) {
        throw new PDOException("Failed to execute the query for fetching 'paidto' account.");
    }
    $accountData = $stmtAccount->fetch(PDO::FETCH_ASSOC);
    if (!$accountData || empty($accountData['name'])) {
        throw new PDOException("Account name for 'paidto' not found. Please verify the input data.");
    }
    $paidToName = $accountData['name'];

    // Fetch client details
    $stmtClient = $pdo->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtClient->bindParam(1, $soldTo, PDO::PARAM_INT);
    $stmtClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtClient->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmtClient->execute()) {
        throw new PDOException("Failed to fetch client details.");
    }
    $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
    if (!$clientData || empty($clientData['client_type'])) {
        throw new PDOException("Client not found. Please verify the soldTo ID.");
    }
    $clientName = $clientData['name'];
    $clientType = $clientData['client_type'];
    $usdBalance = $clientData['usd_balance'];
    $afsBalance = $clientData['afs_balance'];

    // Insert visa applications
    $stmtVisa = $pdo->prepare("
        INSERT INTO visa_applications (
            supplier, sold_to, paid_to, phone, title, gender, applicant_name,
            passport_number, country, visa_type, receive_date, applied_date,
            issued_date, base, sold, profit, currency, remarks, created_at, updated_at, created_by, tenant_id, branch_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)
    ");
    $stmtVisa->bindParam(1, $supplier, PDO::PARAM_INT);
    $stmtVisa->bindParam(2, $soldTo, PDO::PARAM_INT);
    $stmtVisa->bindParam(3, $paidTo, PDO::PARAM_INT);
    $stmtVisa->bindParam(4, $phone, PDO::PARAM_STR);
    $stmtVisa->bindParam(5, $title, PDO::PARAM_STR);
    $stmtVisa->bindParam(6, $gender, PDO::PARAM_STR);
    $stmtVisa->bindParam(7, $applicantName, PDO::PARAM_STR);
    $stmtVisa->bindParam(8, $passportNumber, PDO::PARAM_STR);
    $stmtVisa->bindParam(9, $country, PDO::PARAM_STR);
    $stmtVisa->bindParam(10, $visaType, PDO::PARAM_STR);
    $stmtVisa->bindParam(11, $receiveDate, PDO::PARAM_STR);
    $stmtVisa->bindParam(12, $appliedDate, PDO::PARAM_STR);
    $stmtVisa->bindParam(13, $issuedDate, PDO::PARAM_STR);
    $stmtVisa->bindParam(14, $base, PDO::PARAM_STR);
    $stmtVisa->bindParam(15, $sold, PDO::PARAM_STR);
    $stmtVisa->bindParam(16, $profit, PDO::PARAM_STR);
    $stmtVisa->bindParam(17, $currency, PDO::PARAM_STR);
    $stmtVisa->bindParam(18, $description, PDO::PARAM_STR);
    $stmtVisa->bindParam(19, $user_id, PDO::PARAM_INT);
    $stmtVisa->bindParam(20, $tenant_id, PDO::PARAM_INT);
    $stmtVisa->bindParam(21, $branch_id, PDO::PARAM_INT);

    if (!$stmtVisa->execute()) {
        throw new PDOException('Insert Visa Error: ' . $stmtVisa->errorInfo()[2]);
    }

    $visaApplicationId = $pdo->lastInsertId();

    // Create supplier transaction for both internal and external suppliers
    // Calculate new balance only for external suppliers
    $newBalance = ($supplierType === 'External') ? $balance - $base : 0;
    $description = "Visa purchase for $applicantName - $passportNumber";

    $updateSupplierBalance = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateSupplierBalance->bindParam(1, $base, PDO::PARAM_STR);
    $updateSupplierBalance->bindParam(2, $supplier, PDO::PARAM_INT);
    $updateSupplierBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateSupplierBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateSupplierBalance->execute();

    $stmtSupplierTrans = $pdo->prepare("
        INSERT INTO supplier_transactions (
            supplier_id, transaction_type, amount, transaction_of,
            reference_id, remarks, transaction_date, balance, tenant_id, branch_id
        ) VALUES (?, 'Debit', ?, 'visa_sale', ?, ?, NOW(), ?, ?, ?)
    ");
    $stmtSupplierTrans->bindParam(1, $supplier, PDO::PARAM_INT);
    $stmtSupplierTrans->bindParam(2, $base, PDO::PARAM_STR);
    $stmtSupplierTrans->bindParam(3, $visaApplicationId, PDO::PARAM_INT);
    $stmtSupplierTrans->bindParam(4, $description, PDO::PARAM_STR);
    $stmtSupplierTrans->bindParam(5, $newBalance, PDO::PARAM_STR);
    $stmtSupplierTrans->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $stmtSupplierTrans->bindParam(7, $branch_id, PDO::PARAM_INT);

    if (!$stmtSupplierTrans->execute()) {
        throw new PDOException('Failed to create supplier transaction: ' . $stmtSupplierTrans->errorInfo()[2]);
    }

    // Fetch client details and handle balance deduction (only once)
    $stmtClient = $pdo->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? And branch_id = ?");
    $stmtClient->bindParam(1, $soldTo, PDO::PARAM_INT);
    $stmtClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtClient->bindParam(3, $branch_id, PDO::PARAM_INT);
    if (!$stmtClient->execute()) {
        throw new PDOException("Failed to fetch client details.");
    }
    $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
    if (!$clientData || empty($clientData['client_type'])) {
        throw new PDOException("Client not found. Please verify the soldTo ID.");
    }
    $clientName = $clientData['name'];
    $clientType = $clientData['client_type'];
    $usdBalance = $clientData['usd_balance'];
    $afsBalance = $clientData['afs_balance'];

    // Handle client balance and transactions
    if ($clientType === 'regular') {
        // Get current balance based on currency
        $currentBalance = ($currency === 'USD') ? $usdBalance : $afsBalance;
        $newBalance = $currentBalance - $sold;

        if ($currency === 'USD') {
            $stmtUpdateClientBalance = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtUpdateClientBalance->bindParam(1, $sold, PDO::PARAM_STR);
            $stmtUpdateClientBalance->bindParam(2, $soldTo, PDO::PARAM_INT);
            $stmtUpdateClientBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmtUpdateClientBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
        } elseif ($currency === 'AFS') {
            $stmtUpdateClientBalance = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtUpdateClientBalance->bindParam(1, $sold, PDO::PARAM_STR);
            $stmtUpdateClientBalance->bindParam(2, $soldTo, PDO::PARAM_INT);
            $stmtUpdateClientBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmtUpdateClientBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
        } else {
            throw new PDOException("Unsupported currency type.");
        }

        if (!$stmtUpdateClientBalance->execute()) {
            throw new PDOException("Failed to deduct client balance: " . $stmtUpdateClientBalance->errorInfo()[2]);
        }

        // Insert into client_transactions with balance
        $stmtTransaction = $pdo->prepare("
            INSERT INTO client_transactions (
                client_id, type, currency, amount, balance, transaction_of, description, reference_id, created_at, tenant_id, branch_id
            ) VALUES (?, 'Debit', ?, ?, ?, 'visa_sale', ?, ?, NOW(), ?, ?)
        ");
        $description = "Visa booking for $applicantName";
        $stmtTransaction->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmtTransaction->bindParam(2, $currency, PDO::PARAM_STR);
        $stmtTransaction->bindParam(3, $sold, PDO::PARAM_STR);
        $stmtTransaction->bindParam(4, $newBalance, PDO::PARAM_STR);
        $stmtTransaction->bindParam(5, $description, PDO::PARAM_STR);
        $stmtTransaction->bindParam(6, $visaApplicationId, PDO::PARAM_INT);
        $stmtTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmtTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
        if (!$stmtTransaction->execute()) {
            throw new PDOException('Failed to create client transaction: ' . $stmtTransaction->errorInfo()[2]);
        }
    } else {
        // For non-regular clients, insert transaction without affecting balance
        $stmtTransaction = $pdo->prepare("
            INSERT INTO client_transactions (
                client_id, type, currency, amount, transaction_of, description, reference_id, created_at, tenant_id, branch_id
            ) VALUES (?, 'Debit', ?, ?, 'visa_sale', ?, ?, NOW(), ?, ?)
        ");
        $description = "Visa booking for $applicantName";
        $stmtTransaction->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmtTransaction->bindParam(2, $currency, PDO::PARAM_STR);
        $stmtTransaction->bindParam(3, $sold, PDO::PARAM_STR);
        $stmtTransaction->bindParam(4, $description, PDO::PARAM_STR);
        $stmtTransaction->bindParam(5, $visaApplicationId, PDO::PARAM_INT);
        $stmtTransaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmtTransaction->bindParam(7, $branch_id, PDO::PARAM_INT);
        if (!$stmtTransaction->execute()) {
            throw new PDOException('Failed to create client transaction: ' . $stmtTransaction->errorInfo()[2]);
        }
    }

    // Commit transaction
    $pdo->commit();

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

    $stmtLog = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'add', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmtLog->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmtLog->bindParam(2, $visaApplicationId, PDO::PARAM_INT);
    $stmtLog->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmtLog->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmtLog->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmtLog->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmtLog->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmtLog->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmtLog->execute();

    // Send email notification to client
    require_once '../includes/functions.php';

    // Get client email and name
    $stmt_client_email = $pdo->prepare("SELECT email, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_client_email->bindParam(1, $soldTo, PDO::PARAM_INT);
    $stmt_client_email->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_client_email->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_client_email->execute();
    $client_email_data = $stmt_client_email->fetch(PDO::FETCH_ASSOC);
    $client_email = $client_email_data['email'];
    $client_name = $client_email_data['name'];

    // Send email notification to client
    require_once '../includes/functions.php';

    if (!empty($client_email)) {
        sendVisaNotification(
            $client_email,
            $client_name,
            $visaApplicationId,
            $applicantName,
            $passportNumber,
            $country,
            $visaType,
            $appliedDate,
            $issuedDate,
            $sold,
            $currency
        );
    }

    // Send WhatsApp notification to client (if configured)
    try {
        $whatsappManager = new WhatsAppManager($tenant_id);
        $whatsapp_result = $whatsappManager->sendBookingNotification('visa', $visaApplicationId);

        if ($whatsapp_result['success']) {
            error_log("WhatsApp notification sent for Visa application ID: $visaApplicationId");
        } else {
            error_log("WhatsApp notification failed for Visa application ID: $visaApplicationId - " . $whatsapp_result['message']);
        }
    } catch (Exception $e) {
        // Don't fail the operation if WhatsApp fails
        error_log("WhatsApp integration error for Visa application ID: $visaApplicationId - " . $e->getMessage());
    }

    echo json_encode(['status' => 'success', 'message' => 'Visa application, transaction, and notification added successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Transaction Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
