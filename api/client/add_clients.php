<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate afs_balance
$afs_balance = isset($_POST['afs_balance']) ? DbSecurity::validateInput($_POST['afs_balance'], 'float', ['min' => 0]) : null;

// Validate usd_balance
$usd_balance = isset($_POST['usd_balance']) ? DbSecurity::validateInput($_POST['usd_balance'], 'float', ['min' => 0]) : null;

// Validate password
$password = isset($_POST['password']) ? DbSecurity::validateInput($_POST['password'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate client_type
$client_type = isset($_POST['client_type']) ? DbSecurity::validateInput($_POST['client_type'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $clientType = $_POST['client_type'];
    $phone = $_POST['phone'] ?? null;
    $password = $_POST['password'];
    $usd_balance = $_POST['usd_balance'] ?? 0.00;
    $afs_balance = $_POST['afs_balance'] ?? 0.00;
    $address = $_POST['address'] ?? null;

    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Required fields are missing"]);
        exit;
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Prepare SQL query
    $stmt = $pdo->prepare("
        INSERT INTO clients (name, email, client_type,password_hash, phone, usd_balance, afs_balance, address, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Bind parameters
    $stmt->bindParam(1, $name, PDO::PARAM_STR);
    $stmt->bindParam(2, $email, PDO::PARAM_STR);
    $stmt->bindParam(3, $clientType, PDO::PARAM_STR);
    $stmt->bindParam(4, $password_hash, PDO::PARAM_STR);
    $stmt->bindParam(5, $phone, PDO::PARAM_STR);
    $stmt->bindParam(6, $usd_balance, PDO::PARAM_STR); // Note: PDO::PARAM_STR for floats
    $stmt->bindParam(7, $afs_balance, PDO::PARAM_STR);
    $stmt->bindParam(8, $address, PDO::PARAM_STR);
    $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);

    // Execute the query and handle success or failure
    try {
        $stmt->execute();
        
        // Get the inserted client ID
        $client_id = $pdo->lastInsertId();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'name' => $name,
            'email' => $email,
            'client_type' => $clientType,
            'phone' => $phone,
            'usd_balance' => $usd_balance,
            'afs_balance' => $afs_balance,
            'address' => $address
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'clients', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $client_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        echo json_encode(["status" => "success", "message" => "Client added successfully"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>
