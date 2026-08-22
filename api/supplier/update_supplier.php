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

require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate supplier_type
$supplier_type = isset($_POST['supplier_type']) ? DbSecurity::validateInput($_POST['supplier_type'], 'string', ['maxlength' => 255]) : null;

// Validate category
$allowed_categories = ['ticket', 'visa', 'umrah', 'hotel', 'all'];
$category = isset($_POST['category']) ? $_POST['category'] : 'all';
if (!in_array($category, $allowed_categories)) {
    $category = 'all';
}
$category = htmlspecialchars(trim($category));

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Validate id and contact_person
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;
$contact_person = isset($_POST['contact_person']) ? DbSecurity::validateInput($_POST['contact_person'], 'string', ['maxlength' => 255]) : null;

// Validate route_payment_to_main_account (checkbox: absent => 0)
$route_payment_to_main_account = isset($_POST['route_payment_to_main_account']) ? 1 : 0;

// Validate balance
$balance = isset($_POST['balance']) ? (float)$_POST['balance'] : null;

// Check if transactions exist for this supplier
$has_transactions = false;
if ($id) {
    $txn_check = $pdo->prepare("SELECT COUNT(*) FROM supplier_transactions WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?");
    $txn_check->execute([$id, $tenant_id, $branch_id]);
    $has_transactions = (int)$txn_check->fetchColumn() > 0;
}

// Build dynamic UPDATE query
$set_clauses = ['name = ?', 'contact_person = ?', 'phone = ?', 'email = ?', 'address = ?', 'currency = ?', 'supplier_type = ?', 'category = ?', 'route_payment_to_main_account = ?'];
$params = [$name, $contact_person, $phone, $email, $address, $currency, $supplier_type, $category, $route_payment_to_main_account];

// Only update balance if no transactions exist
if ($balance !== null && !$has_transactions) {
    $set_clauses[] = 'balance = ?';
    $params[] = $balance;
}

$params[] = $id;
$params[] = $tenant_id;
$params[] = $branch_id;

$query = "UPDATE suppliers SET " . implode(', ', $set_clauses) . " WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($query);
foreach ($params as $idx => $val) {
    $stmt->bindValue($idx + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}

if ($stmt->execute()) {
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Get original supplier data
    $old_values = [];
    $get_original_stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $get_original_stmt->bindParam(1, $id, PDO::PARAM_INT);
    $get_original_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $get_original_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $get_original_stmt->execute();
    $original_data = $get_original_stmt->fetch(PDO::FETCH_ASSOC);

    if ($original_data) {
         $old_values = [
             'name' => $original_data['name'],
             'contact_person' => $original_data['contact_person'],
             'phone' => $original_data['phone'],
             'email' => $original_data['email'],
             'address' => $original_data['address'],
             'currency' => $original_data['currency'],
             'route_payment_to_main_account' => $original_data['route_payment_to_main_account']
         ];
     }
     
     // Prepare new values
     $new_values = [
         'name' => $name,
         'contact_person' => $contact_person,
         'phone' => $phone,
         'email' => $email,
         'address' => $address,
         'currency' => $currency,
         'route_payment_to_main_account' => $route_payment_to_main_account
     ];
    $action = 'update';
    $table_name = 'suppliers';
    $old_values = json_encode($old_values);
    $new_values = json_encode($new_values);
    // Insert activity log
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(2, $action, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(4, $id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(5, $old_values, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(6, $new_values, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
    $activity_log_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $activity_log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
    $activity_log_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
