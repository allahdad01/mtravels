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
header('Content-Type: application/json'); // Ensure response is JSON

require_once '../../includes/db.php';

// Validate inputs using DbSecurity
$head_of_family = isset($_POST['head_of_family']) ? DbSecurity::validateInput($_POST['head_of_family'], 'string', ['maxlength' => 255]) : null;
$contact = isset($_POST['contact']) ? DbSecurity::validateInput($_POST['contact'], 'string', ['maxlength' => 255]) : null;
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;
$package_type = isset($_POST['package_type']) ? DbSecurity::validateInput($_POST['package_type'], 'string', ['maxlength' => 255]) : null;
$location = isset($_POST['location']) ? DbSecurity::validateInput($_POST['location'], 'string', ['maxlength' => 255]) : null;
$tazmin = isset($_POST['tazmin']) ? DbSecurity::validateInput($_POST['tazmin'], 'string', ['maxlength' => 255]) : null;
$visa_status = isset($_POST['visa_status']) ? DbSecurity::validateInput($_POST['visa_status'], 'string', ['maxlength' => 255]) : null;
$province = isset($_POST['province']) ? trim($_POST['province']) : null;
$district = isset($_POST['district']) ? trim($_POST['district']) : null;

// Validate required fields
if (empty($head_of_family) || empty($contact) || empty($address) || empty($package_type) || empty($location) || empty($tazmin) || empty($visa_status)) {
    echo json_encode(["success" => false, "error" => "All fields are required"]);
    exit();
}

try {
    // Prepare the SQL statement
    $sql = "INSERT INTO families (head_of_family, contact, address, package_type, location, tazmin, visa_status, province, district, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $head_of_family, PDO::PARAM_STR);
    $stmt->bindParam(2, $contact, PDO::PARAM_STR);
    $stmt->bindParam(3, $address, PDO::PARAM_STR);
    $stmt->bindParam(4, $package_type, PDO::PARAM_STR);
    $stmt->bindParam(5, $location, PDO::PARAM_STR);
    $stmt->bindParam(6, $tazmin, PDO::PARAM_STR);
    $stmt->bindParam(7, $visa_status, PDO::PARAM_STR);
    $stmt->bindParam(8, $province, PDO::PARAM_STR);
    $stmt->bindParam(9, $district, PDO::PARAM_STR);
    $stmt->bindParam(10, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(11, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Family added successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => "Error inserting data"]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
