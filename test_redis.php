<?php
// Test script for Redis functionality

// Load only Redis-related dependencies
require_once 'vendor/predis/predis/autoload.php';
require_once 'includes/cache.php';
require_once 'includes/session.php';
require_once 'config.php'; // Load config for database

// Test Redis cache
echo "Testing Redis Cache...\n";

$testKey = 'test_key_' . time();
$testData = ['message' => 'Hello Redis!', 'timestamp' => time()];

echo "Setting cache data...\n";
$result = setCachedData($testKey, $testData, 300); // 5 minutes
echo "Set result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

echo "Getting cache data...\n";
$retrievedData = getCachedData($testKey);
echo "Retrieved data: " . ($retrievedData ? json_encode($retrievedData) : 'NOT FOUND') . "\n";

echo "Testing session...\n";
$_SESSION['test_session'] = 'Session working with Redis';
echo "Session data set: " . $_SESSION['test_session'] . "\n";

echo "Testing database connection...\n";
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "MySQL Version: " . $result['version'] . "\n";

    // Check default storage engine
    $stmt = $pdo->query("SELECT @@default_storage_engine as engine");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Default Storage Engine: " . $result['engine'] . "\n";

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
?>