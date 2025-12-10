<?php
/**
 * Apply Phase 4 Database Migration
 * This script applies the audit logging schema
 */

require_once __DIR__ . '/includes/db.php';

echo "Applying Phase 4 Migration (004_audit_logging.sql)...\n\n";

// Read the migration file
$migrationFile = __DIR__ . '/migrations/004_audit_logging.sql';
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found at $migrationFile\n");
}

$migrationSQL = file_get_contents($migrationFile);

// Split by semicolon and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $migrationSQL)),
    fn($s) => !empty($s) && !preg_match('/^--/', $s)
);

$success = 0;
$failed = 0;

foreach ($statements as $statement) {
    // Remove comments
    $statement = preg_replace('/--.*$/m', '', $statement);
    $statement = trim($statement);
    
    if (empty($statement)) continue;
    
    try {
        echo "[EXECUTING] " . substr($statement, 0, 70) . "...\n";
        $pdo->exec($statement);
        echo "  ✅ Success\n\n";
        $success++;
    } catch (PDOException $e) {
        echo "  ⚠️  " . $e->getMessage() . "\n\n";
        
        // Check if it's "already exists" error (which is OK)
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $success++;
        } else {
            $failed++;
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "MIGRATION RESULTS\n";
echo str_repeat("=", 60) . "\n";
echo "Successful: $success statements\n";
echo "Failed: $failed statements\n\n";

if ($failed == 0) {
    echo "✅ Migration applied successfully!\n\n";
    
    // Verify tables exist
    echo "VERIFICATION:\n";
    echo "-" . str_repeat("-", 58) . "\n";
    
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'chat_audit_log%'");
        $tables = $result->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            echo "✅ Table '$table' exists\n";
        }
        
        if (in_array('chat_audit_log', $tables)) {
            echo "\n✅ All tables created successfully!\n\n";
            echo "NEXT STEPS:\n";
            echo "1. Test the system: http://localhost/mtravels/test_audit.php\n";
            echo "2. View logs: http://localhost/mtravels/admin/audit_logs.php\n";
            echo "3. Send a chat message and refresh the logs page\n";
        }
    } catch (Exception $e) {
        echo "Verification error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Migration had failures!\n";
    echo "Please review the errors above.\n";
}
?>
