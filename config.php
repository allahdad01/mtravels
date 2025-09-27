<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'travelagency_saas');

// Hesabpay API Configuration
define('HESABPAY_MERCHANT_ID', '0780310431'); // Replace with actual merchant ID
define('HESABPAY_API_KEY', 'M2FjMGYxNjctNDEzZi00ODY2LWJjY2ItNmRiOGUxZDVkMzVmX18yNzZlN2U5YjFhOTU5MmJmYjgwMw=='); // Replace with actual API key
define('HESABPAY_BASE_URL', 'https://api.hesabpay.com/v1');


// Validate tenant_id
$tenant_id = isset($_SESSION['tenant_id']) ? intval($_SESSION['tenant_id']) : null;

/* Attempt to connect to MySQL database */
$conection_db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($conection_db === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Function to fetch settings data
function getSettings($conection_db) {
    global $tenant_id;
    if ($tenant_id === null) {
        error_log("Tenant ID is not set");
        return null;
    }
    $sql = "SELECT * FROM settings WHERE tenant_id = ?";
    $stmt = mysqli_prepare($conection_db, $sql);
    mysqli_stmt_bind_param($stmt, "i", $tenant_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// PDO version of getSettings
function getSettingsPdo() {
    global $tenant_id;
    if ($tenant_id === null) {
        error_log("Tenant ID is not set");
        return null;
    }
    try {
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error in getSettingsPdo: " . $e->getMessage());
        return null;
    }
}
?>