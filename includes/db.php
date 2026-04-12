<?php
// Database connection file with enhanced security
require_once dirname(__DIR__) . "/config.php";
require_once __DIR__ . "/helpers.php";

// Prevent direct access to this file
if (count(get_included_files()) == 1) {
    header("HTTP/1.0 403 Forbidden");
    exit("Direct access to this file is not allowed.");
}

// Only initialize PDO if not already initialized
if (!isset($pdo) || $pdo === null) {
    // PDO connection with error handling
    try {
        $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Enable InnoDB transactions and set collation
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'; SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            // Persistent connections can improve performance but should be used cautiously
            // PDO::ATTR_PERSISTENT => true
        ];

        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);


        
        // Ensure InnoDB is the default storage engine
        $pdo->exec("SET SESSION default_storage_engine = InnoDB");

    } catch (PDOException $e) {
        die("A database error occurred. Please try again later.");
    }
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
        return false;
    }
}
?>
