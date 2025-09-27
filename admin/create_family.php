<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
header('Content-Type: application/json'); // Ensure response is JSON

require_once '../includes/conn.php';


// Check for connection errors
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Set UTF-8 encoding for proper character handling
$conn->set_charset("utf8");

// Retrieve and sanitize input
$family_head = trim($_POST['head_of_family']);
$contact = trim($_POST['contact']);
$address = trim($_POST['address']);
$package_type = trim($_POST['package_type']);
$location = trim($_POST['location']);
$tazmin = trim($_POST['tazmin']);
$visa_status = trim($_POST['visa_status']);
$province = trim($_POST['province']);
$district = trim($_POST['district']);

// Validate required fields
if (empty($family_head) || empty($contact) || empty($address) || empty($package_type) || empty($location) || empty($tazmin) || empty($visa_status)) {
    echo json_encode(["success" => false, "error" => "All fields are required"]);

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
    exit();
}

// Prepare the SQL statement
$sql = "INSERT INTO families (head_of_family, contact, address, package_type, location, tazmin, visa_status, province, district, tenant_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sssssssssi", $family_head, $contact, $address, $package_type, $location, $tazmin, $visa_status, $province, $district, $tenant_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Family added successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => "Error inserting data: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Failed to prepare SQL statement"]);
}

$conn->close();
?>
