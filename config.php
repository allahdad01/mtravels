<?php
/* SECURITY: Hide PHP version and implementation details */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('expose_php', 'off');
header_remove('X-Powered-By');
header_remove('X-AspNet-Version');
header_remove('X-AspNetMvc-Version');

/* Load environment variables from .env file */
require_once dirname(__FILE__) . '/includes/env_loader.php';

/* Database credentials. Strictly from environment variables - no fallbacks */
$db_server = EnvLoader::get('DB_SERVER');
$db_username = EnvLoader::get('DB_USERNAME');
$db_password = EnvLoader::get('DB_PASSWORD');
$db_name = EnvLoader::get('DB_NAME');
$app_env = EnvLoader::get('APP_ENV');

$missing = [];
if (empty($db_server)) { $db_server = 'localhost'; $missing[] = 'DB_SERVER'; }
if (empty($db_username)) { $db_username = 'root'; $missing[] = 'DB_USERNAME'; }
if ($db_password === null || $db_password === false) { $db_password = ''; $missing[] = 'DB_PASSWORD'; }
if (empty($db_name)) { $db_name = 'travelagency_saas'; $missing[] = 'DB_NAME'; }
if (empty($app_env)) { $app_env = 'development'; $missing[] = 'APP_ENV'; }

if (!empty($missing)) {
    error_log('Missing environment variables: ' . implode(', ', $missing) . '. Using defaults.');
}

define('DB_SERVER', $db_server);
define('DB_USERNAME', $db_username);
define('DB_PASSWORD', $db_password);
define('DB_NAME', $db_name);
define('APP_ENV', $app_env);

// Hesabpay API Configuration - Strictly from environment variables
define('HESABPAY_MERCHANT_ID', EnvLoader::get('HESABPAY_MERCHANT_ID'));
define('HESABPAY_API_KEY', EnvLoader::get('HESABPAY_API_KEY'));
define('HESABPAY_BASE_URL', EnvLoader::get('HESABPAY_BASE_URL'));

// Platform Configuration
define('PLATFORM_NAME', 'MTravels');


// Validate tenant_id
$tenant_id = isset($_SESSION['tenant_id']) ? intval($_SESSION['tenant_id']) : null;

/* Attempt to connect to MySQL database */
$conection_db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($conection_db === false){
    die("A database error occurred. Please try again later.");
}

// Function to fetch settings data
function getSettings($conection_db) {
    global $tenant_id;
    if ($tenant_id === null) {
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
        return null;
    }
    try {
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}
?>