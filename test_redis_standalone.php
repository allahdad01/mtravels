<?php
// Standalone test script for Redis functionality

require_once 'vendor/predis/predis/autoload.php';

use Predis\Client;

echo "Testing Redis Connection...\n";

try {
    $redis = new Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'database' => 0,
        'read_write_timeout' => 0,
    ]);

    // Test connection
    $pong = $redis->ping();
    echo "Redis PING: " . $pong . "\n";

    // Test set/get
    $testKey = 'test_key_' . time();
    $testData = json_encode(['message' => 'Hello Redis!', 'timestamp' => time()]);

    $redis->setex($testKey, 300, $testData); // 5 minutes
    echo "Set data: SUCCESS\n";

    $retrievedData = $redis->get($testKey);
    echo "Retrieved data: " . $retrievedData . "\n";

    // Clean up
    $redis->del([$testKey]);
    echo "Cleanup: SUCCESS\n";

} catch (Exception $e) {
    echo "Redis Error: " . $e->getMessage() . "\n";
    echo "Redis not available, testing will fallback to file cache\n";
}

// Test file-based fallback
echo "\nTesting File Cache Fallback...\n";

$cacheDir = __DIR__ . '/cache/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$testKey = 'fallback_test_' . time();
$testData = ['message' => 'Hello File Cache!', 'timestamp' => time()];
$file = $cacheDir . md5($testKey) . '.cache';

file_put_contents($file, serialize($testData));
echo "File cache set: SUCCESS\n";

$retrievedData = unserialize(file_get_contents($file));
echo "File cache retrieved: " . json_encode($retrievedData) . "\n";

unlink($file);
echo "File cache cleanup: SUCCESS\n";

echo "\nTesting Database Connection...\n";

require_once 'config.php';

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

echo "\nAll tests completed!\n";
?>