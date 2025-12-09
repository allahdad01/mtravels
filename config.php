<?php
/* Database credentials. Using environment variables for security */
define('DB_SERVER', getenv('DB_SERVER') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');

// Validate DB_PASSWORD is configured (cannot be empty for security)
$db_password = getenv('DB_PASSWORD');
if ($db_password === false) {
    // In development (localhost/XAMPP), allow empty password
    // In production (non-localhost), require environment variable
    $is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], 'xampp') !== false);
    
    if ($is_localhost) {
        // Development environment - allow empty password for XAMPP
        define('DB_PASSWORD', '');
    } else {
        // Production environment - require password
        error_log("CRITICAL: DB_PASSWORD environment variable not configured");
        die("ERROR: Database security not configured. Please set DB_PASSWORD environment variable.");
    }
} else {
    define('DB_PASSWORD', $db_password);
}

define('DB_NAME', getenv('DB_NAME') ?: 'travelagency_saas');

// Hesabpay API Configuration - Using environment variables for security
define('HESABPAY_MERCHANT_ID', getenv('HESABPAY_MERCHANT_ID') ?: ''); // Set in environment variables
define('HESABPAY_API_KEY', getenv('HESABPAY_API_KEY') ?: ''); // Set in environment variables
define('HESABPAY_BASE_URL', getenv('HESABPAY_BASE_URL') ?: 'https://api-sandbox.hesab.com/api/v1');

// Platform Configuration
define('PLATFORM_NAME', 'MTravels');


// Validate tenant_id
$tenant_id = isset($_SESSION['tenant_id']) ? intval($_SESSION['tenant_id']) : null;

/* Attempt to connect to MySQL database */
$conection_db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($conection_db === false){
    error_log("Database connection failed: " . mysqli_connect_error());
    die("A database error occurred. Please try again later.");
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