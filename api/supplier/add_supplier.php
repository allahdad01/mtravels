<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Use environment variables or a secure method to store database credentials
require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

// Validate category
$allowed_categories = ['ticket', 'visa', 'umrah', 'hotel', 'all'];
$category = isset($_POST['category']) ? $_POST['category'] : 'all';
if (!in_array($category, $allowed_categories)) {
    $category = 'all';
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
$category = htmlspecialchars(trim($category));

// Validate route_payment_to_main_account (checkbox: absent => 0)
$route_payment_to_main_account = isset($_POST['route_payment_to_main_account']) ? 1 : 0;

// Prepare and bind
$stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, currency, balance, supplier_type, category, route_payment_to_main_account, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bindParam(1, $name, PDO::PARAM_STR);
$stmt->bindParam(2, $contact_person, PDO::PARAM_STR);
$stmt->bindParam(3, $phone, PDO::PARAM_STR);
$stmt->bindParam(4, $email, PDO::PARAM_STR);
$stmt->bindParam(5, $address, PDO::PARAM_STR);
$stmt->bindParam(6, $currency, PDO::PARAM_STR);
$stmt->bindParam(7, $balance, PDO::PARAM_STR);
$stmt->bindParam(8, $supplier_type, PDO::PARAM_STR);
$stmt->bindParam(9, $category, PDO::PARAM_STR);
$stmt->bindParam(10, $route_payment_to_main_account, PDO::PARAM_INT);
$stmt->bindParam(11, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(12, $branch_id, PDO::PARAM_INT);

// Execute and check for errors
if ($stmt->execute()) {
    // Get the insert ID
    $supplier_id = $pdo->lastInsertId();
    
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
        'supplier_type' => $supplier_type,
        'category' => $category,
        'route_payment_to_main_account' => $route_payment_to_main_account,
        'branch_id' => $branch_id
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'add', 'suppliers', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt_log->bindParam(2, $supplier_id, PDO::PARAM_INT);
    $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt_log->execute();
    
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>
