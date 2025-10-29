<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Use environment variables or a secure method to store database credentials
include '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];
// Validate supplier_type
$supplier_type = isset($_POST['supplier_type']) ? DbSecurity::validateInput($_POST['supplier_type'], 'string', ['maxlength' => 255]) : null;

// Validate balance
$balance = isset($_POST['balance']) ? DbSecurity::validateInput($_POST['balance'], 'float', ['min' => 0]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate contact_person
$contact_person = isset($_POST['contact_person']) ? DbSecurity::validateInput($_POST['contact_person'], 'string', ['maxlength' => 255]) : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate and sanitize input data
$name = htmlspecialchars(trim($_POST['name']));
$contact_person = htmlspecialchars(trim($_POST['contact_person']));
$phone = htmlspecialchars(trim($_POST['phone']));
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$address = htmlspecialchars(trim($_POST['address']));
$currency = htmlspecialchars(trim($_POST['currency']));
$balance = filter_var(trim($_POST['balance']), FILTER_VALIDATE_FLOAT);
$supplier_type = htmlspecialchars(trim($_POST['supplier_type']));

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, currency, balance, supplier_type, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssdsi", $name, $contact_person, $phone, $email, $address, $currency, $balance, $supplier_type, $tenant_id);

// Execute and check for errors
if ($stmt->execute()) {
    // Get the insert ID
    $supplier_id = $conn->insert_id;
    
    // Log the activity
    $old_values = json_encode([]);
    $new_values = json_encode([
        'name' => $name,
        'contact_person' => $contact_person,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'currency' => $currency,
        'balance' => $balance,
        'supplier_type' => $supplier_type
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'add', 'suppliers', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bind_param("iissssi", $user_id, $supplier_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}

// Close statement and connection
$stmt->close();
$conn->close();
?>
