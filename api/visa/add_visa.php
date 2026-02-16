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
require_once '../../api/whatsapp/WhatsAppManager.php';

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
     
     // Validate all required fields
     foreach ($requiredFields as $field) {
         if (!isset($_POST[$field]) || empty($_POST[$field])) {
             throw new Exception("Missing required field: $field");
         }
     }

// Validate description
$description = DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]);

// Validate curr
$curr = DbSecurity::validateInput($_POST['curr'], 'string', ['maxlength' => 255]);

// Validate sold
$sold = DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]);

// Validate base
$base = DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]);

// Validate issuedDate
$issuedDate = !empty($_POST['issuedDate']) ? DbSecurity::validateInput($_POST['issuedDate'], 'date') : null;

// Validate appliedDate
$appliedDate = DbSecurity::validateInput($_POST['appliedDate'], 'date');

// Validate receiveDate
$receiveDate = DbSecurity::validateInput($_POST['receiveDate'], 'date');

// Validate visaType
$visaType = DbSecurity::validateInput($_POST['visaType'], 'string', ['maxlength' => 255]);

// Validate country
$country = DbSecurity::validateInput($_POST['country'], 'string', ['maxlength' => 255]);

// Validate passNum
$passNum = DbSecurity::validateInput($_POST['passNum'], 'string', ['maxlength' => 255]);

// Validate passengerName
$passengerName = DbSecurity::validateInput($_POST['passengerName'], 'string', ['maxlength' => 255]);

// Validate gender
$gender = DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]);

// Validate title
$title = DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]);

// Validate phone
$phone = DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]);

// Validate paidto
$paidto = DbSecurity::validateInput($_POST['paidto'], 'int', ['min' => 0]);

// Validate soldto
$soldto = DbSecurity::validateInput($_POST['soldto'], 'int', ['min' => 0]);

// Validate supplier
$supplier = DbSecurity::validateInput($_POST['supplier'], 'int', ['min' => 0]);

    // Variable declarations
     $applicantName = $passengerName;
     $passportNumber = $passNum;
     $visaType = $visaType;
     $profit = $sold - $base;
     $username = $_SESSION['name'];

     // Begin transaction for visa creation only (transactions created on approve)
     $pdo->beginTransaction();

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
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $action = 'add';
    $table_name = 'visa_applications';
    $stmtLog->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmtLog->bindParam(2, $action, PDO::PARAM_STR);
    $stmtLog->bindParam(3, $table_name, PDO::PARAM_STR);
    $stmtLog->bindParam(4, $visaApplicationId, PDO::PARAM_INT);
    $stmtLog->bindParam(5, $old_values, PDO::PARAM_STR);
    $stmtLog->bindParam(6, $new_values, PDO::PARAM_STR);
    $stmtLog->bindParam(7, $ip_address, PDO::PARAM_STR);
    $stmtLog->bindParam(8, $user_agent, PDO::PARAM_STR);
    $stmtLog->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $stmtLog->bindParam(10, $branch_id, PDO::PARAM_INT);
    $stmtLog->execute();

    echo json_encode(['status' => 'success', 'message' => 'Visa application created successfully. Please approve to process transactions.', 'visa_id' => $visaApplicationId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Transaction Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
