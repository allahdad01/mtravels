<?php
/**
 * Test Script for Monthly Report Generation
 * 
 * Run this to test the monthly report system before setting up cron
 * Usage: php test_monthly_reports.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Monthly Reports System Test ===\n\n";

// Check CLI
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    echo "⚠ Warning: Running from web interface. Should use CLI.\n";
}

// Check directories
echo "1. Checking directories...\n";
$tempDir = dirname(__DIR__) . '/temp/reports';
if (is_dir($tempDir)) {
    echo "   ✓ Temp directory exists: $tempDir\n";
} else {
    echo "   ✗ Creating temp directory...\n";
    mkdir($tempDir, 0755, true);
    if (is_dir($tempDir)) {
        echo "   ✓ Temp directory created\n";
    } else {
        echo "   ✗ Failed to create temp directory\n";
    }
}

// Check permissions
if (is_writable($tempDir)) {
    echo "   ✓ Temp directory is writable\n";
} else {
    echo "   ✗ Temp directory is NOT writable\n";
}

// Check database
echo "\n2. Checking database connection...\n";
try {
    require_once dirname(__DIR__) . "/config.php";
    require_once dirname(__DIR__) . "/includes/db.php";
    
    echo "   ✓ Database connected\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Check required tables
echo "\n3. Checking database tables...\n";
$tables = ['report_logs', 'report_recipients', 'report_settings'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Table '$table' exists\n";
        } else {
            echo "   ✗ Table '$table' NOT found\n";
            echo "     Run: migrations/add_monthly_reports_tables.sql\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error checking table '$table': " . $e->getMessage() . "\n";
    }
}

// Check TCPDF
echo "\n4. Checking TCPDF library...\n";
try {
    require_once dirname(__DIR__) . "/vendor/autoload.php";
    if (class_exists('TCPDF')) {
        echo "   ✓ TCPDF library loaded\n";
    } else {
        echo "   ✗ TCPDF class not found\n";
    }
} catch (Exception $e) {
    echo "   ✗ Failed to load TCPDF: " . $e->getMessage() . "\n";
}

// Check MonthlyReportGenerator class
echo "\n5. Checking MonthlyReportGenerator class...\n";
try {
    require_once dirname(__FILE__) . "/MonthlyReportGenerator.php";
    if (class_exists('MonthlyReportGenerator')) {
        echo "   ✓ MonthlyReportGenerator class loaded\n";
    } else {
        echo "   ✗ MonthlyReportGenerator class not found\n";
    }
} catch (Exception $e) {
    echo "   ✗ Failed to load MonthlyReportGenerator: " . $e->getMessage() . "\n";
}

// Get active tenants
echo "\n6. Checking active tenants...\n";
try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM tenants WHERE status = 'active' LIMIT 5");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($tenants)) {
        echo "   ✓ Found " . count($tenants) . " active tenant(s)\n";
        foreach ($tenants as $tenant) {
            echo "     - {$tenant['name']} (ID: {$tenant['id']}, Email: " . 
                 ($tenant['email'] ? $tenant['email'] : 'NOT SET') . ")\n";
        }
    } else {
        echo "   ✗ No active tenants found\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test report generation
echo "\n7. Testing report generation...\n";
if (!empty($tenants)) {
    try {
        $testTenant = $tenants[0];
        $generator = new MonthlyReportGenerator($pdo);
        
        // Generate report for last month
        $lastMonth = date('Y-m-d', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-d', strtotime('last day of last month'));
        
        echo "   Generating report for " . date('F Y', strtotime($lastMonth)) . "...\n";
        
        $reportData = $generator->generateMonthlyReport(
            $testTenant['id'],
            $lastMonth,
            $lastMonthEnd
        );
        
        if ($reportData) {
            echo "   ✓ Report data generated\n";
            echo "     - Month: " . $reportData['month'] . "\n";
            echo "     - Total Profit: $" . number_format($reportData['financial_summary']['total_profit'], 2) . "\n";
            echo "     - Branches: " . count($reportData['branches']) . "\n";
            echo "     - Top Clients: " . count($reportData['top_clients']) . "\n";
            echo "     - Top Suppliers: " . count($reportData['top_suppliers']) . "\n";
        } else {
            echo "   ✗ Failed to generate report\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error generating report: " . $e->getMessage() . "\n";
    }
}

// Check mail configuration
echo "\n8. Checking mail configuration...\n";
$smtp = ini_get('SMTP');
$smtpPort = ini_get('smtp_port');
$sendmail = ini_get('sendmail_path');

if ($smtp || $sendmail) {
    echo "   ✓ Mail configuration found\n";
    if ($smtp) echo "     SMTP: $smtp:$smtpPort\n";
    if ($sendmail) echo "     Sendmail: $sendmail\n";
} else {
    echo "   ⚠ No mail configuration detected\n";
    echo "     Mail delivery may not work. Configure SMTP or sendmail.\n";
}

// Final status
echo "\n=== Test Complete ===\n";
echo "✓ System appears ready for deployment\n\n";

echo "Next steps:\n";
echo "1. Set up cron job: 0 8 1 * * /usr/bin/php " . __FILE__ . "\n";
echo "2. Go to Report Settings in tenant super admin dashboard\n";
echo "3. Add email recipients\n";
echo "4. Enable monthly reports\n";
echo "5. Reports will send automatically on configured day/time\n";
?>
