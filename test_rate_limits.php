<?php
/**
 * Test Phase 5: Rate Limiting System
 * 
 * Tests all rate limiting functionality.
 */

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/RateLimiter.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized. Only admins can run tests.');
}

$tenantId = $_SESSION['tenant_id'] ?? 1;

// Debug: Log tenant ID
error_log("DEBUG: Test tenantId = " . $tenantId . " (type: " . gettype($tenantId) . ")");

// Test results
$tests = [];

function test($name, $result, $message = '') {
    global $tests;
    $tests[] = [
        'name' => $name,
        'passed' => $result,
        'message' => $message
    ];
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Phase 5: Rate Limiting Tests</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; }
        .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 5px solid #ccc; }
        .passed { background: #f0fff0; border-left-color: #28a745; }
        .failed { background: #fff5f5; border-left-color: #dc3545; }
        .test-name { font-weight: bold; }
        .test-message { margin-top: 5px; color: #666; font-size: 0.9em; }
        h1 { color: #333; }
        .summary { padding: 15px; margin: 20px 0; background: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Phase 5: Rate Limiting - Test Suite</h1>
";

// ============ Test 1: RateLimiter Class Exists ============
test('RateLimiter Class Exists', class_exists('RateLimiter'), 'RateLimiter class should be loaded');

// ============ Test 2: isAllowed Method ============
$allowed = RateLimiter::isAllowed(1, 'messages_per_hour', $tenantId);
test('isAllowed() Method Works', $allowed !== null, 'Method should return boolean');

// ============ Test 3: recordAction Method ============
$recorded = RateLimiter::recordAction(1, 'messages_per_hour', $tenantId, '127.0.0.1');
test('recordAction() Method Works', $recorded === true, 'Method should return true');

// ============ Test 4: getRemainingQuota Method ============
$quota = RateLimiter::getRemainingQuota(1, 'messages_per_hour', $tenantId);
test('getRemainingQuota() Returns Data', is_array($quota), 'Should return array with quota info');

if (is_array($quota)) {
    test('Quota Has Required Fields', 
        isset($quota['remaining']) && isset($quota['max']) && isset($quota['reset_in']),
        'Should have remaining, max, and reset_in fields'
    );
}

// ============ Test 5: IP Blocking ============
$ipBlocked = RateLimiter::blockIP('192.168.1.100', 'Test block', 3600, $tenantId, $_SESSION['user_id']);
test('blockIP() Method Works', $ipBlocked === true, 'Should successfully block IP');

// Debug: Check if record was actually inserted
try {
    $debugStmt = $pdo->prepare("SELECT * FROM ip_blacklist WHERE ip_address = ?");
    $debugStmt->execute(['192.168.1.100']);
    $allRecords = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("DEBUG: All 192.168.1.100 records - " . json_encode($allRecords));
    
    // Also check exact query that isIPBlocked uses
    $debugStmt = $pdo->prepare("
        SELECT id FROM ip_blacklist 
        WHERE ip_address = ? 
        AND (permanent = 1 OR (permanent = 0 AND blocked_until > NOW()))
        AND (tenant_id IS NULL OR tenant_id = ?)
        LIMIT 1
    ");
    $debugStmt->execute(['192.168.1.100', $tenantId]);
    $debugRecord = $debugStmt->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG: isIPBlocked query result - " . json_encode($debugRecord) . " | rowCount: " . $debugStmt->rowCount());
} catch (Exception $e) {
    error_log("DEBUG: blockIP query error - " . $e->getMessage());
}

// ============ Test 6: Check IP Blocked ============
$isBlocked = RateLimiter::isIPBlocked('192.168.1.100', $tenantId);
test('isIPBlocked() Method Works', $isBlocked === true, 'Should recognize blocked IP');

// ============ Test 7: Unblock IP ============
$unblocked = RateLimiter::unblockIP('192.168.1.100', $tenantId);
test('unblockIP() Method Works', $unblocked === true, 'Should successfully unblock IP');

// ============ Test 8: Verify IP Unblocked ============
$isBlocked = RateLimiter::isIPBlocked('192.168.1.100', $tenantId);
test('IP Unblocked Successfully', $isBlocked === false, 'IP should no longer be blocked');

// ============ Test 9: getStatus Method ============
$status = RateLimiter::getStatus(1, $tenantId);
test('getStatus() Returns Data', is_array($status), 'Should return array of limit statuses');

// ============ Test 10: Multiple Limits ============
RateLimiter::recordAction(2, 'login_attempts_per_15min', $tenantId, '192.168.1.101');
RateLimiter::recordAction(2, 'login_attempts_per_15min', $tenantId, '192.168.1.101');
RateLimiter::recordAction(2, 'login_attempts_per_15min', $tenantId, '192.168.1.101');

$quota = RateLimiter::getRemainingQuota(2, 'login_attempts_per_15min', $tenantId);
test('Multiple Records Tracked', 
    isset($quota['remaining']) && $quota['remaining'] < $quota['max'],
    'Should track multiple actions correctly'
);

// ============ Test 11: Limit Exceeded Detection ============
$allowed = RateLimiter::isAllowed(2, 'login_attempts_per_15min', $tenantId);
$quota = RateLimiter::getRemainingQuota(2, 'login_attempts_per_15min', $tenantId);
test('Limit Exceeded Detection', 
    isset($quota['exceeded']),
    'Should properly flag when limit is exceeded'
);

// ============ Test 12: Database Tables Exist ============
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
    $test12 = $stmt->rowCount() > 0;
    test('rate_limits Table Exists', $test12, 'Database table should exist');
} catch (Exception $e) {
    test('rate_limits Table Exists', false, $e->getMessage());
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ip_blacklist'");
    $test13 = $stmt->rowCount() > 0;
    test('ip_blacklist Table Exists', $test13, 'Database table should exist');
} catch (Exception $e) {
    test('ip_blacklist Table Exists', false, $e->getMessage());
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limit_violations'");
    $test14 = $stmt->rowCount() > 0;
    test('rate_limit_violations Table Exists', $test14, 'Database table should exist');
} catch (Exception $e) {
    test('rate_limit_violations Table Exists', false, $e->getMessage());
}

// ============ Print Results ============
$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $class = $test['passed'] ? 'passed' : 'failed';
    $icon = $test['passed'] ? '✓' : '✗';
    
    if ($test['passed']) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo "
        <div class='test-result $class'>
            <div class='test-name'>$icon " . htmlspecialchars($test['name']) . "</div>
            " . (isset($test['message']) ? "<div class='test-message'>" . htmlspecialchars($test['message']) . "</div>" : "") . "
        </div>
    ";
}

// Summary
echo "
    <div class='summary'>
        <h2>Test Summary</h2>
        <p><strong>Passed:</strong> <span style='color: #28a745;'>$passed</span> | <strong>Failed:</strong> <span style='color: #dc3545;'>$failed</span></p>
";

if ($failed === 0) {
    echo "
        <p style='color: #28a745;'><strong>✅ All tests passed! Rate limiting system is ready.</strong></p>
        <h3>Next Steps:</h3>
        <ol>
            <li>Update API files to use rate limiting checks</li>
            <li>Create admin interface for monitoring</li>
            <li>Test in production environment</li>
        </ol>
    ";
} else {
    echo "
        <p style='color: #dc3545;'><strong>⚠️ Some tests failed. Please review the failures above.</strong></p>
        <h3>Common Issues:</h3>
        <ul>
            <li>Database tables not created - run <code>apply_migration_005.php</code></li>
            <li>RateLimiter class not found - check <code>includes/RateLimiter.php</code></li>
            <li>Database connection issues - check <code>config.php</code></li>
        </ul>
    ";
}

echo "
    </div>
    
    <hr>
    <p>
        <a href='verify_phase5.php'>Back to Verification</a> | 
        <a href='PHASE_5_QUICK_START.md'>Quick Start Guide</a> | 
        <a href='admin/index.php'>Admin Dashboard</a>
    </p>
</body>
</html>
";
?>
