<?php
// Database connection file with enhanced security
require_once __DIR__ . "/../config.php";

// Prevent direct access to this file
if (count(get_included_files()) == 1) {
    header("HTTP/1.0 403 Forbidden");
    exit("Direct access to this file is not allowed.");
}

// PDO connection with error handling
try {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // Persistent connections can improve performance but should be used cautiously
        // PDO::ATTR_PERSISTENT => true
    ];
    
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    
    
} catch (PDOException $e) {
    // Log the error but don't expose details to users
    error_log("Database Connection Error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Legacy mysqli connection for backward compatibility
try {
    $conection_db = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conection_db->connect_error) {
        throw new Exception("Connection failed: " . $conection_db->connect_error);
    }
} catch (Exception $e) {
    error_log("MySQLi Connection Error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to execute secure queries with PDO
function secure_query($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage() . " - SQL: " . $sql);
        return false;
    }
}
?>
