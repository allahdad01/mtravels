<?php
/**
 * Phase 4 Verification Script
 * Tests that audit logging implementation is complete
 */

require_once __DIR__ . '/includes/db.php';

echo "=== Phase 4: Audit Logging - Verification ===\n\n";

$checks = [];
$passed = 0;
$failed = 0;

// Check 1: ChatAudit class exists
echo "[1] Checking ChatAudit class...";
if (file_exists(__DIR__ . '/includes/ChatAudit.php')) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - ChatAudit.php not found\n";
    $failed++;
}

// Check 2: Database tables exist
echo "[2] Checking audit_log table...";
$result = $pdo->query("SHOW TABLES LIKE 'chat_audit_log'");
if ($result && $result->rowCount() > 0) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - Table not found. Run migrations/004_audit_logging.sql\n";
    $failed++;
}

// Check 3: Archive table exists
echo "[3] Checking audit_log_archive table...";
$result = $pdo->query("SHOW TABLES LIKE 'chat_audit_log_archive'");
if ($result && $result->rowCount() > 0) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - Archive table not found\n";
    $failed++;
}

// Check 4: API files updated
echo "[4] Checking api/messages.php for ChatAudit...";
$content = file_get_contents(__DIR__ . '/api/messages.php');
if (strpos($content, 'ChatAudit') !== false) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - ChatAudit not found in messages.php\n";
    $failed++;
}

echo "[5] Checking api/chat_prefs.php for ChatAudit...";
$content = file_get_contents(__DIR__ . '/api/chat_prefs.php');
if (strpos($content, 'ChatAudit') !== false) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - ChatAudit not found in chat_prefs.php\n";
    $failed++;
}

// Check 5: Admin interfaces exist
echo "[6] Checking admin/audit_logs.php...";
if (file_exists(__DIR__ . '/admin/audit_logs.php')) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - audit_logs.php not found\n";
    $failed++;
}

echo "[7] Checking admin/compliance_report.php...";
if (file_exists(__DIR__ . '/admin/compliance_report.php')) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - compliance_report.php not found\n";
    $failed++;
}

// Check 6: Test ChatAudit methods
echo "[8] Testing ChatAudit class methods...";
require_once __DIR__ . '/includes/ChatAudit.php';
$methods = ['logSend', 'logRead', 'logBlock', 'logMute', 'logEncryption', 'logDecryption', 'logFailedAccess', 'getAuditLog', 'getSummary'];
$allExist = true;
foreach ($methods as $method) {
    if (!method_exists('ChatAudit', $method)) {
        $allExist = false;
        echo "\n   Missing method: $method";
    }
}
if ($allExist) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL\n";
    $failed++;
}

// Check 7: Table structure
echo "[9] Checking table columns...";
$result = $pdo->query("DESCRIBE chat_audit_log");
$columns = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[$row['Field']] = true;
    }
}
$requiredColumns = ['id', 'tenant_id', 'user_id', 'action', 'status', 'created_at', 'details', 'ip_address'];
$allPresent = true;
foreach ($requiredColumns as $col) {
    if (!isset($columns[$col])) {
        echo "\n   Missing column: $col";
        $allPresent = false;
    }
}
if ($allPresent) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL\n";
    $failed++;
}

// Check 8: Summary document
echo "[10] Checking PHASE_4_IMPLEMENTATION_SUMMARY.md...";
if (file_exists(__DIR__ . '/PHASE_4_IMPLEMENTATION_SUMMARY.md')) {
    echo " ✅ PASS\n";
    $passed++;
} else {
    echo " ❌ FAIL - Summary document not found\n";
    $failed++;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "RESULTS: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "✅ Phase 4 implementation is COMPLETE!\n\n";
    echo "Next steps:\n";
    echo "1. Access /admin/audit_logs.php to view logs\n";
    echo "2. Access /admin/compliance_report.php for compliance reporting\n";
    echo "3. Test message sending to verify logging works\n";
    echo "4. Review PHASE_4_IMPLEMENTATION_SUMMARY.md for details\n";
    echo "5. Proceed to Phase 5: Rate Limiting\n";
} else {
    echo "❌ Phase 4 implementation has issues!\n";
    echo "Please fix the failed checks above.\n";
}

echo "\n";
?>
