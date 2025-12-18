<?php
/**
 * MonthlyReportGenerator Class
 *
 * Handles generation of monthly profit reports with detailed analytics
 * Includes Excel generation and email distribution
 */

require_once dirname(dirname(__FILE__)) . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MonthlyReportGenerator {
    
    private $pdo;
    private $tempDir;
    
    /**
     * Constructor
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->tempDir = dirname(dirname(__FILE__)) . "/temp/reports";
        
        // Create temp directory if it doesn't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }


    /**
     * Generate comprehensive Excel report using existing export_comprehensive_report logic
     * @param int $tenantId
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return string Path to generated Excel file
     */
    public function generateExcelReport($tenantId, $startDate, $endDate) {
        try {
            // Use the existing export_comprehensive_report logic via AJAX simulation
            $excelPath = $this->generateExcelReportViaExistingScript($tenantId, $startDate, $endDate);
            
            if (!$excelPath) {
                error_log("Failed to generate Excel report using existing script");
                return false;
            }
            
            return $excelPath;
        } catch (Exception $e) {
            error_log("Excel Report Generation Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate comprehensive Excel report using the existing export_comprehensive_report.php logic
     * @param int $tenantId
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return string|false Path to generated Excel file or false on failure
     */
    private function generateExcelReportViaExistingScript($tenantId, $startDate, $endDate) {
        try {
            // Path to the existing export script
            $exportScriptPath = dirname(dirname(__FILE__)) . "/tenant_super_admin/export_comprehensive_report.php";

            if (!file_exists($exportScriptPath)) {
                error_log("Export script not found at: $exportScriptPath");
                return false;
            }

            // Define skip session check
            define('SKIP_SESSION_CHECK', true);

            // Set required variables for the included script
            $tenant_id = $tenantId;
            $pdo = $this->pdo;
            $_GET['startDate'] = $startDate;
            $_GET['endDate'] = $endDate;

            // Start output buffering to capture JSON response
            ob_start();

            // Include and execute the export script
            include $exportScriptPath;

            // Get the output
            $output = ob_get_clean();

            // Parse JSON response
            $response = json_decode($output, true);

            if (!$response || !$response['success']) {
                error_log("Excel generation failed: " . ($response['message'] ?? 'Unknown error'));
                return false;
            }

            // Decode base64 file content
            $fileContent = base64_decode($response['file']);

            if ($fileContent === false) {
                error_log("Failed to decode base64 file content");
                return false;
            }

            // Save to temporary file
            $filename = $this->tempDir . '/comprehensive_report_' . $tenantId . '_' . date('Y-m-d_His') . '.xlsx';

            if (file_put_contents($filename, $fileContent) === false) {
                error_log("Failed to write Excel file to: $filename");
                return false;
            }

            return $filename;
        } catch (Exception $e) {
            error_log("Error generating Excel report via existing script: " . $e->getMessage());
            return false;
        }
    }



    /**
     * Send report via email with Excel attachment using tenant SMTP
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param array $reportData Report data
     * @param string $excelPath Path to Excel file
     * @param string $pdfPath Path to PDF file (optional)
     * @param int $tenantId Tenant ID for SMTP config lookup
     * @return bool
     */
    public function sendReportEmail($email, $name, $reportData, $excelPath, $pdfPath = null, $tenantId = null) {
        try {
            // Try to use PHPMailer with tenant SMTP config if available
            if ($tenantId && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendEmailViaSMTP($email, $name, $reportData, $excelPath, $pdfPath, $tenantId);
            }
            
            // Fallback to default PHP mail
            return $this->sendEmailViaPhpMail($email, $name, $reportData, $excelPath, $pdfPath);
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using tenant SMTP configuration
     */
    private function sendEmailViaSMTP($email, $name, $reportData, $excelPath, $pdfPath, $tenantId) {
        try {
            // Fetch tenant SMTP configuration
            $stmt = $this->pdo->prepare("
                SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_name, smtp_from_email, agency_name
                FROM settings
                WHERE tenant_id = ? AND smtp_host IS NOT NULL
            ");
            $stmt->execute([$tenantId]);
            $smtpConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            if ($smtpConfig && $smtpConfig['smtp_host']) {
                // Use SMTP
                $mail->isSMTP();
                $mail->Host = $smtpConfig['smtp_host'];
                $mail->Port = $smtpConfig['smtp_port'] ?? 587;
                $mail->SMTPAuth = !empty($smtpConfig['smtp_username']);
                
                if ($mail->SMTPAuth) {
                    $mail->Username = $smtpConfig['smtp_username'];
                    $mail->Password = $smtpConfig['smtp_password'];
                }
                
                $encryption = strtolower($smtpConfig['smtp_encryption'] ?? 'tls');
                if ($encryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
                
                $fromEmail = $smtpConfig['smtp_from_email'] ?? $smtpConfig['email'] ?? 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $fromName = $smtpConfig['smtp_from_name'] ?? $smtpConfig['agency_name'] ?? 'Travel Agency';
            } else {
                // Use sendmail
                $mail->isSendmail();
                $fromEmail = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $fromName = $smtpConfig['agency_name'] ?? 'Travel Agency';
            }
            
            // Set email details
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email, $name);
            $mail->Subject = "Monthly Profit Report - " . $reportData['month'];
            $mail->isHTML(true);
            $mail->Body = $this->generateEmailHTML($reportData, $name);
            
            // Attach files
            if (file_exists($excelPath)) {
                $mail->addAttachment($excelPath, basename($excelPath));
            }
            if ($pdfPath && file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath, basename($pdfPath));
            }
            
            // Send email
            return $mail->send();
        } catch (Exception $e) {
            error_log("SMTP Email sending error: " . $e->getMessage());
            // Fallback to PHP mail
            return $this->sendEmailViaPhpMail($email, $name, $reportData, $excelPath, $pdfPath);
        }
    }

    /**
     * Send email using default PHP mail function
     */
    private function sendEmailViaPhpMail($email, $name, $reportData, $excelPath, $pdfPath) {
        try {
            // Prepare email content
            $subject = "Monthly Profit Report - " . $reportData['month'];
            $htmlContent = $this->generateEmailHTML($reportData, $name);
            
            // Create email with attachments
            if (file_exists($excelPath)) {
                $boundary = md5(time() . microtime());
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
                $headers .= "From: noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
                
                // Create message body
                $message = "--{$boundary}\r\n";
                $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
                $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message .= $htmlContent . "\r\n";
                
                // Attach Excel file
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name=\"" . basename($excelPath) . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"" . basename($excelPath) . "\"\r\n\r\n";
                $message .= chunk_split(base64_encode(file_get_contents($excelPath))) . "\r\n";
                
                // Attach PDF if exists
                if ($pdfPath && file_exists($pdfPath)) {
                    $message .= "--{$boundary}\r\n";
                    $message .= "Content-Type: application/pdf; name=\"" . basename($pdfPath) . "\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"" . basename($pdfPath) . "\"\r\n\r\n";
                    $message .= chunk_split(base64_encode(file_get_contents($pdfPath))) . "\r\n";
                }
                
                $message .= "--{$boundary}--";
                
                $result = mail($email, $subject, $message, $headers);
            } else {
                // Fallback to HTML only if Excel not available
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
                $result = mail($email, $subject, $htmlContent, $headers);
            }
            
            if (!$result) {
                error_log("Failed to send email to: " . $email);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("PHP Mail sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HTML email content
     */
    private function generateEmailHTML($reportData, $recipientName) {
        $summary = $reportData['financial_summary'];
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .summary { background-color: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #bdc3c7; }
                .summary-row:last-child { border-bottom: none; }
                .summary-label { font-weight: bold; }
                .summary-value { color: #27ae60; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background-color: #34495e; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ecf0f1; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .footer { background-color: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #7f8c8d; }
                .btn { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h1>Monthly Profit and Expense Report</h1>
                    <p>{$reportData['month']}</p>
                </div>
                
                <div class=\"content\">
                    <p>Dear {$recipientName},</p>

                    <p>The monthly report for {$reportData['month']} is attached below. Kindly check it.</p>
                </div>
                
                <div class=\"footer\">
                    <p>This is an automated report generated on " . date('Y-m-d H:i:s') . "</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
}
?>
