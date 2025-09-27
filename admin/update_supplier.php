<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
require_once '../includes/conn.php';

$tenant_id = $_SESSION['tenant_id'];
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
$supplier_type = isset($_POST['supplier_type']) ? DbSecurity::validateInput($_POST['supplier_type'], 'string', ['maxlength' => 255]) : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;
$contact_person = isset($_POST['contact_person']) ? DbSecurity::validateInput($_POST['contact_person'], 'string', ['maxlength' => 255]) : null;
$id = $_POST['id'];
$name = $_POST['name'];
$contact_person = $_POST['contact_person'] ?? null;
$phone = $_POST['phone'];
$email = $_POST['email'] ?? null;
$address = $_POST['address'] ?? null;
$currency = $_POST['currency'] ?? null;
$balance = $_POST['balance'] ?? 0;
$supplier_type = $_POST['supplier_type'] ?? null;
$query = "UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?, currency = ?, balance = ?, supplier_type = ? WHERE id = ? AND tenant_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ssssssdsii', $name, $contact_person, $phone, $email, $address, $currency, $balance, $supplier_type, $id, $tenant_id);

if ($stmt->execute()) {
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Get original supplier data
    $old_values = [];
    $get_original_stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?");
    $get_original_stmt->bind_param('ii', $id, $tenant_id);
    $get_original_stmt->execute();
    $original_result = $get_original_stmt->get_result();
    
    if ($original_result->num_rows > 0) {
        $original_data = $original_result->fetch_assoc();
        $old_values = [
            'name' => $original_data['name'],
            'contact_person' => $original_data['contact_person'],
            'phone' => $original_data['phone'],
            'email' => $original_data['email'],
            'address' => $original_data['address'],
            'currency' => $original_data['currency'],
            'balance' => $original_data['balance']
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
        'balance' => $balance
    ];
    $action = 'update';
    $table_name = 'suppliers';
    $old_values = json_encode($old_values);
    $new_values = json_encode($new_values);
    // Insert activity log
    $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $activity_log_stmt->bind_param("isisssssi", 
        $user_id, 
        $action, 
        $table_name, 
        $id, 
        $old_values, 
        $new_values, 
        $ip_address, 
        $user_agent,
        $tenant_id
    );
    $activity_log_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
?>
