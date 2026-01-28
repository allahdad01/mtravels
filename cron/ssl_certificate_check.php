<?php
/**
 * SSL Certificate Check Cron Job
 * Run this script periodically to check SSL certificates and send alerts
 *
 * Recommended cron schedule:
 * 0 *\/6 * * * php /path/to/mtravels/cron/ssl_certificate_check.php
 * (Runs every 6 hours)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SSLCertificateMonitor.php';

echo "🔍 SSL Certificate Check Cron Job Started\n";
echo "==========================================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $sslMonitor = new SSLCertificateMonitor($pdo);

    // Check all certificates
    echo "📋 Checking all monitored SSL certificates...\n";
    $results = $sslMonitor->checkAllCertificates();

    $totalChecked = count($results);
    $validCount = 0;
    $expiredCount = 0;
    $criticalCount = 0;
    $warningCount = 0;

    foreach ($results as $result) {
        if ($result['success']) {
            $validCount++;
            switch ($result['alert_level']) {
                case 'expired':
                    $expiredCount++;
                    break;
                case 'critical':
                    $criticalCount++;
                    break;
                case 'warning':
                    $warningCount++;
                    break;
            }
        }
    }

    echo "✅ Checked {$totalChecked} certificate(s)\n";
    echo "   • Valid: {$validCount}\n";
    echo "   • Expired: {$expiredCount}\n";
    echo "   • Critical (< 7 days): {$criticalCount}\n";
    echo "   • Warning (< 30 days): {$warningCount}\n\n";

    // Send alerts for certificates needing attention
    if ($expiredCount > 0 || $criticalCount > 0 || $warningCount > 0) {
        echo "📧 Sending expiry alerts...\n";
        $alertsSent = $sslMonitor->sendExpiryAlerts();
        echo "✅ Sent {$alertsSent} alert notification(s)\n\n";
    } else {
        echo "✅ No alerts needed - all certificates are healthy\n\n";
    }

    // Log summary to file
    $logFile = '../logs/ssl_check_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logEntry = sprintf(
        "[%s] Checked %d certificates: %d valid, %d expired, %d critical, %d warning. Sent %d alerts.\n",
        date('Y-m-d H:i:s'),
        $totalChecked,
        $validCount,
        $expiredCount,
        $criticalCount,
        $warningCount,
        $alertsSent ?? 0
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND);

    echo "📝 Log entry written to: {$logFile}\n";
    echo "🎉 SSL Certificate check completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error during SSL certificate check: " . $e->getMessage() . "\n";

    // Log error
    $errorLog = '../logs/ssl_check_errors_' . date('Y-m-d') . '.log';
    $errorEntry = sprintf(
        "[%s] ERROR: %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage()
    );
    file_put_contents($errorLog, $errorEntry, FILE_APPEND);

    exit(1);
}

echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
?>