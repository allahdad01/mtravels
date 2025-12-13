<?php
/**
 * Monthly Profit Report Generator for Tenant Super Admins
 * 
 * This script generates monthly profit reports for each branch and sends them via email
 * Run this via cron job on the 1st of each month at a scheduled time
 * 
 * Cron example:
 * 0 8 1 * * /usr/bin/php /path/to/generate_monthly_reports.php
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    header("HTTP/1.0 403 Forbidden");
    exit("This script can only be run from command line.");
}

// Include configuration and database
require_once dirname(dirname(__FILE__)) . "/config.php";
require_once dirname(dirname(__FILE__)) . "/includes/db.php";
require_once dirname(dirname(__FILE__)) . "/vendor/autoload.php";
require_once dirname(__FILE__) . "/MonthlyReportGenerator.php";

// Initialize report generator
$reportGenerator = new MonthlyReportGenerator($pdo);

try {
    // Get all active tenants
    $stmt = $pdo->prepare("SELECT id, name, email FROM tenants WHERE status = 'active'");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tenants)) {
        error_log("No active tenants found for monthly report generation");
        exit(1);
    }

    $reports_sent = 0;
    $reports_failed = 0;

    // Get previous month's date range
    $previousMonth = date('Y-m-d', strtotime('first day of last month'));
    $previousMonthEnd = date('Y-m-d', strtotime('last day of last month'));

    foreach ($tenants as $tenant) {
        try {
            echo "Generating report for tenant: {$tenant['name']} (ID: {$tenant['id']})\n";
            
            // Generate report data
            $reportData = $reportGenerator->generateMonthlyReport(
                $tenant['id'],
                $previousMonth,
                $previousMonthEnd
            );

            if ($reportData) {
                // Generate Excel report
                $excelPath = $reportGenerator->generateExcelReport(
                    $tenant['id'],
                    $previousMonth,
                    $previousMonthEnd
                );

                // Generate PDF (optional)
                $pdfPath = $reportGenerator->generatePDF(
                    $reportData,
                    $tenant['id'],
                    $tenant['name']
                );

                // Send email with Excel (and PDF if available)
                $emailSent = $reportGenerator->sendReportEmail(
                    $tenant['email'],
                    $tenant['name'],
                    $reportData,
                    $excelPath,
                    $pdfPath
                );

                if ($emailSent) {
                    echo "✓ Report sent successfully for tenant: {$tenant['name']}\n";
                    $reports_sent++;

                    // Log the report sending
                    $stmt = $pdo->prepare("
                        INSERT INTO report_logs (tenant_id, report_type, report_date, status, recipient_email)
                        VALUES (?, 'monthly_profit', NOW(), 'sent', ?)
                    ");
                    $stmt->execute([$tenant['id'], $tenant['email']]);
                } else {
                    echo "✗ Failed to send report for tenant: {$tenant['name']}\n";
                    $reports_failed++;
                }
            } else {
                echo "✗ Failed to generate report data for tenant: {$tenant['name']}\n";
                $reports_failed++;
            }
        } catch (Exception $e) {
            error_log("Error processing tenant {$tenant['id']}: " . $e->getMessage());
            echo "✗ Exception for tenant {$tenant['name']}: " . $e->getMessage() . "\n";
            $reports_failed++;
        }
    }

    echo "\n=== Monthly Report Generation Complete ===\n";
    echo "Reports Sent: $reports_sent\n";
    echo "Reports Failed: $reports_failed\n";
    echo "Total Tenants Processed: " . count($tenants) . "\n";

    exit($reports_failed > 0 ? 1 : 0);

} catch (Exception $e) {
    error_log("Fatal error in monthly report generation: " . $e->getMessage());
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
