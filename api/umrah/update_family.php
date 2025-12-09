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

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../../includes/db.php';

// Validate visa_status
$visa_status = isset($_POST['visa_status']) ? DbSecurity::validateInput($_POST['visa_status'], 'string', ['maxlength' => 255]) : null;

// Validate tazmin
$tazmin = isset($_POST['tazmin']) ? DbSecurity::validateInput($_POST['tazmin'], 'string', ['maxlength' => 255]) : null;

// Validate location
$location = isset($_POST['location']) ? DbSecurity::validateInput($_POST['location'], 'string', ['maxlength' => 255]) : null;

// Validate package_type
$package_type = isset($_POST['package_type']) ? DbSecurity::validateInput($_POST['package_type'], 'string', ['maxlength' => 255]) : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate contact
$contact = isset($_POST['contact']) ? DbSecurity::validateInput($_POST['contact'], 'string', ['maxlength' => 255]) : null;

// Validate head_of_family
$head_of_family = isset($_POST['head_of_family']) ? DbSecurity::validateInput($_POST['head_of_family'], 'string', ['maxlength' => 255]) : null;

// Validate family_id
$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int', ['min' => 0]) : null;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $family_id = $_POST['family_id'];
    $head_of_family = $_POST['head_of_family'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $package_type = $_POST['package_type'];
    $location = $_POST['location'];
    $tazmin = $_POST['tazmin'];
    $visa = isset($_POST['visa_status']) ? trim($_POST['visa_status']) : null;
    $province = isset($_POST['province']) ? trim($_POST['province']) : null;
    $district = isset($_POST['district']) ? trim($_POST['district']) : null;


    $sql = "UPDATE families SET head_of_family = ?, contact = ?, address = ?, package_type = ?, location = ?, tazmin = ?, visa_status = ?, province = ?, district = ? WHERE family_id = ? AND tenant_id = ? AND branch_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $head_of_family, PDO::PARAM_STR);
    $stmt->bindParam(2, $contact, PDO::PARAM_STR);
    $stmt->bindParam(3, $address, PDO::PARAM_STR);
    $stmt->bindParam(4, $package_type, PDO::PARAM_STR);
    $stmt->bindParam(5, $location, PDO::PARAM_STR);
    $stmt->bindParam(6, $tazmin, PDO::PARAM_STR);
    $stmt->bindParam(7, $visa, PDO::PARAM_STR);
    $stmt->bindParam(8, $province, PDO::PARAM_STR);
    $stmt->bindParam(9, $district, PDO::PARAM_STR);
    $stmt->bindParam(10, $family_id, PDO::PARAM_INT);
    $stmt->bindParam(11, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(12, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Get original family data (we might not have it, but at least log the updates)
        $old_values = [];

        // Prepare new values
        $new_values = [
            'family_id' => $family_id,
            'head_of_family' => $head_of_family,
            'contact' => $contact,
            'address' => $address,
            'package_type' => $package_type,
            'location' => $location,
            'tazmin' => $tazmin,
            'visa_status' => $visa
        ];
        $action = 'update';
        $table_name = 'families';
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $action, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $family_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(5, $old_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $new_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();

        echo json_encode(["status" => "success", "message" => "Family updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $stmt->error]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>
