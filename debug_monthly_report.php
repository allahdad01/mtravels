<?php
/**
 * Debug Monthly Report Email Sending
 * Check if SMTP is configured and test email functionality
 */

require_once "config.php";
require_once "vendor/autoload.php";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
        DB_USERNAME,
        DB_PASSWORD
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Monthly Report Email Debug</h2>";
    
    // Get all tenants
    $stmt = $pdo->query("SELECT id, name FROM tenants");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Checking SMTP Configuration for All Tenants</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Tenant ID</th><th>Tenant Name</th><th>SMTP Host</th><th>SMTP Port</th><th>SMTP User</th><th>From Email</th><th>From Name</th><th>Has Config</th></tr>";
    
    foreach ($tenants as $tenant) {
        $stmt = $pdo->prepare("
            SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_name, smtp_from_email, agency_name
            FROM settings
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenant['id']]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hasConfig = $config && !empty($config['smtp_host']) ? '✓ Yes' : '✗ No';
        
        echo "<tr>";
        echo "<td>" . $tenant['id'] . "</td>";
        echo "<td>" . htmlspecialchars($tenant['name']) . "</td>";
        echo "<td>" . ($config['smtp_host'] ?? 'N/A') . "</td>";
        echo "<td>" . ($config['smtp_port'] ?? 'N/A') . "</td>";
        echo "<td>" . ($config['smtp_username'] ?? 'N/A') . "</td>";
        echo "<td>" . ($config['smtp_from_email'] ?? 'N/A') . "</td>";
        echo "<td>" . ($config['smtp_from_name'] ?? 'N/A') . "</td>";
        echo "<td>" . $hasConfig . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>Checking Tenant Super Admin Emails</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Tenant ID</th><th>Tenant Name</th><th>Admin Name</th><th>Admin Email</th><th>Admin Role</th></tr>";
    
    foreach ($tenants as $tenant) {
        $stmt = $pdo->prepare("
            SELECT email, name, role FROM users 
            WHERE tenant_id = ? AND role IN ('super_admin', 'tenant_super_admin', 'admin') 
            ORDER BY role DESC LIMIT 1
        ");
        $stmt->execute([$tenant['id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<tr>";
        echo "<td>" . $tenant['id'] . "</td>";
        echo "<td>" . htmlspecialchars($tenant['name']) . "</td>";
        echo "<td>" . ($admin['name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($admin['email'] ?? 'N/A') . "</td>";
        echo "<td>" . ($admin['role'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>Test Email Sending</h3>";
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<p>✓ PHPMailer is available</p>";
    } else {
        echo "<p>✗ PHPMailer is NOT available</p>";
    }
    
    // Check PHP mail function
    if (ini_get('sendmail_path') || ini_get('SMTP')) {
        echo "<p>✓ PHP Mail function is configured</p>";
    } else {
        echo "<p>⚠ PHP Mail function may not be properly configured</p>";
    }
    
    echo "<hr>";
    echo "<h3>Recommendations:</h3>";
    echo "<ul>";
    echo "<li>If SMTP config is missing, configure it in tenant_settings table</li>";
    echo "<li>Test the cron at: <a href='test_monthly_report_cron.php'>test_monthly_report_cron.php</a></li>";
    echo "<li>Check PHP error logs for email sending issues</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
