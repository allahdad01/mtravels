<?php
/**
 * Apply Migration 005: Rate Limiting
 * 
 * This script creates the rate limiting tables required for Phase 5.
 * Run this before implementing the RateLimiter class.
 */

session_start();
require_once 'config.php';
require_once 'includes/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized. Only admins can apply migrations.');
}

if (!isset($pdo) || $pdo === null) {
    die('Database connection failed');
}
$db = $pdo;

// Read the migration SQL
$migrationSQL = file_get_contents('migrations/005_rate_limiting.sql');

if (!$migrationSQL) {
    die('Could not read migration file');
}

// Split by semicolons and execute each statement
$statements = array_filter(array_map('trim', explode(';', $migrationSQL)));
$errors = [];
$success = [];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Apply Migration 005: Rate Limiting</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
        .success { color: #28a745; background: #f0fff0; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: #dc3545; background: #fff5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .statement { background: #f8f9fa; padding: 10px; margin: 10px 0; font-family: monospace; overflow-x: auto; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Apply Migration 005: Rate Limiting</h1>
    <p>Applying database migration for Phase 5 rate limiting system...</p>
";

foreach ($statements as $statement) {
    if (empty(trim($statement))) {
        continue;
    }
    
    try {
        echo "<div class='statement'>Executing: " . htmlspecialchars(substr($statement, 0, 100)) . "...</div>";
        
        $stmt = $db->prepare($statement);
        $stmt->execute();
        
        $success[] = "✓ " . substr($statement, 0, 100) . "...";
        echo "<div class='success'>✓ Executed successfully</div>";
    } catch (Exception $e) {
        $errors[] = $statement . " | Error: " . $e->getMessage();
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "
    <hr style='margin: 30px 0;'>
    <h2>Migration Summary</h2>
";

if (count($success) > 0) {
    echo "<div class='success'><strong>Successfully executed:</strong> " . count($success) . " statements</div>";
}

if (count($errors) > 0) {
    echo "<div class='error'><strong>Errors encountered:</strong> " . count($errors) . " statements failed</div>";
    echo "<details><summary>Error Details</summary><pre>";
    foreach ($errors as $error) {
        echo htmlspecialchars($error) . "\n\n";
    }
    echo "</pre></details>";
} else {
    echo "
        <div class='success'>
            <h3>✅ Migration 005 Applied Successfully!</h3>
            <p>The following tables have been created:</p>
            <ul>
                <li><strong>rate_limits</strong> - Tracks rate limit usage</li>
                <li><strong>rate_limit_violations</strong> - Logs violations</li>
                <li><strong>ip_blacklist</strong> - Manages blocked IPs</li>
            </ul>
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>Review the RateLimiter class: <code>includes/RateLimiter.php</code></li>
                <li>Update API files to use RateLimiter</li>
                <li>Run tests: <code>test_rate_limits.php</code></li>
                <li>Deploy admin interface: <code>admin/rate_limits.php</code></li>
            </ol>
        </div>
    ";
}

echo "
    <hr>
    <p><a href='verify_phase5.php'>Verify Phase 5 Setup</a> | <a href='admin/index.php'>Back to Admin</a></p>
</body>
</html>
";
?>
