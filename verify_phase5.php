<?php
/**
 * Verify Phase 5: Rate Limiting - Setup Check
 * 
 * Verifies that all Phase 5 components are properly installed.
 */

session_start();
require_once __DIR__ . '/includes/db.php';

$checks = [
    'files' => [
        'includes/RateLimiter.php' => 'Rate Limiter Class',
        'migrations/005_rate_limiting.sql' => 'Migration SQL File',
        'apply_migration_005.php' => 'Migration Application Script',
    ],
    'tables' => [
        'rate_limits' => 'Rate Limits Table',
        'rate_limit_violations' => 'Violations Table',
        'ip_blacklist' => 'IP Blacklist Table',
    ],
    'classes' => [
        'RateLimiter' => 'Rate Limiter Class',
    ]
];

$results = [
    'passed' => [],
    'failed' => [],
    'warnings' => []
];

// Check files
echo "<!DOCTYPE html>
<html>
<head>
    <title>Phase 5: Verification Status</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; }
        .check-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .passed { color: #28a745; }
        .failed { color: #dc3545; }
        .warning { color: #ffc107; }
        .status-icon { font-size: 1.2em; margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:hover { background: #f5f5f5; }
        .summary { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .summary.all-good { background: #f0fff0; border-left: 5px solid #28a745; }
        .summary.needs-work { background: #fff5f5; border-left: 5px solid #dc3545; }
    </style>
</head>
<body>
    <h1>Phase 5: Rate Limiting - Verification Status</h1>
    <p>Checking all Phase 5 components...</p>
";

// ============ Check Files ============
echo "<div class='check-section'>";
echo "<h2>File Checks</h2>";
echo "<table>";
echo "<tr><th>Component</th><th>Status</th><th>Details</th></tr>";

foreach ($checks['files'] as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? '<span class="status-icon passed">✓</span> Exists' : '<span class="status-icon failed">✗</span> Missing';
    
    if ($exists) {
        $size = filesize($file);
        $details = "Size: " . number_format($size) . " bytes";
        $results['passed'][] = $description;
    } else {
        $details = "File not found at: " . $file;
        $results['failed'][] = $description;
    }
    
    echo "<tr><td>$description</td><td>$status</td><td>$details</td></tr>";
}

echo "</table>";
echo "</div>";

// ============ Check Database Tables ============
if ($pdo) {
    echo "<div class='check-section'>";
    echo "<h2>Database Table Checks</h2>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Status</th><th>Details</th></tr>";
    
    foreach ($checks['tables'] as $table => $description) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            
            if ($exists) {
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->rowCount();
                $status = '<span class="status-icon passed">✓</span> Exists';
                $details = "Columns: $columns";
                $results['passed'][] = $description;
            } else {
                $status = '<span class="status-icon failed">✗</span> Missing';
                $details = "Run: apply_migration_005.php";
                $results['failed'][] = $description;
            }
        } catch (Exception $e) {
            $status = '<span class="status-icon failed">✗</span> Error';
            $details = $e->getMessage();
            $results['failed'][] = $description;
        }
        
        echo "<tr><td>$description</td><td>$status</td><td>$details</td></tr>";
    }
    
    echo "</table>";
    echo "</div>";
}

// ============ Check Classes ============
echo "<div class='check-section'>";
echo "<h2>Class Checks</h2>";
echo "<table>";
echo "<tr><th>Class</th><th>Status</th><th>Details</th></tr>";

if (file_exists('includes/RateLimiter.php')) {
    require_once 'includes/RateLimiter.php';
    
    foreach ($checks['classes'] as $class => $description) {
        if (class_exists($class)) {
            $status = '<span class="status-icon passed">✓</span> Loaded';
            
            // Check for key methods
            $methods = ['isAllowed', 'recordAction', 'getRemainingQuota', 'isIPBlocked'];
            $methodStatus = [];
            foreach ($methods as $method) {
                if (method_exists($class, $method)) {
                    $methodStatus[] = $method;
                }
            }
            
            $details = "Methods: " . implode(', ', $methodStatus);
            $results['passed'][] = $description;
        } else {
            $status = '<span class="status-icon failed">✗</span> Not Found';
            $details = "Class could not be loaded";
            $results['failed'][] = $description;
        }
        
        echo "<tr><td>$description</td><td>$status</td><td>$details</td></tr>";
    }
} else {
    echo "<tr><td colspan='3'><span class='status-icon failed'>✗</span> RateLimiter.php not found</td></tr>";
}

echo "</table>";
echo "</div>";

// ============ Summary ============
$allPassed = count($results['failed']) === 0;

echo "<div class='summary " . ($allPassed ? 'all-good' : 'needs-work') . "'>";

if ($allPassed) {
    echo "<h2>✅ Phase 5 is Ready!</h2>";
    echo "<p>All components are installed and configured correctly.</p>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Update <code>api/messages.php</code> to use RateLimiter</li>";
    echo "<li>Update <code>login.php</code> to protect login attempts</li>";
    echo "<li>Create <code>admin/rate_limits.php</code> for admin interface</li>";
    echo "<li>Test rate limiting functionality</li>";
    echo "<li>Deploy to production</li>";
    echo "</ol>";
} else {
    echo "<h2>⚠️ Phase 5 Needs Setup</h2>";
    echo "<p>Some components are missing. Please complete the following:</p>";
    echo "<ul>";
    
    foreach ($results['failed'] as $item) {
        echo "<li>$item</li>";
    }
    
    echo "</ul>";
    echo "<p><strong>To fix:</strong> Run <code>apply_migration_005.php</code> first.</p>";
}

echo "</div>";

echo "
    <hr style='margin: 30px 0;'>
    <h2>Statistics</h2>
    <p>Passed: <strong class='passed'>" . count($results['passed']) . "</strong> | Failed: <strong class='failed'>" . count($results['failed']) . "</strong></p>
    
    <hr>
    <p>
        <a href='PHASE_5_QUICK_START.md' target='_blank'>Quick Start Guide</a> | 
        <a href='PHASE_5_ROADMAP.md' target='_blank'>Full Roadmap</a> | 
        <a href='admin/index.php'>Back to Admin</a>
    </p>
</body>
</html>
";
?>
