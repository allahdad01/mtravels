<?php
// Send test email for tenant admin SMTP configuration testing
require_once '../includes/conn.php';
require_once '../includes/functions.php';
// Include security module
require_once 'security.php';
// Check if user is a tenant admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$tenant_id = $_SESSION['tenant_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$testEmail = trim($data['test_email'] ?? '');
$smtpHost = trim($data['smtp_host'] ?? '');
$smtpPort = trim($data['smtp_port'] ?? '');
$smtpEncryption = trim($data['smtp_encryption'] ?? '');
$smtpUsername = trim($data['smtp_username'] ?? '');
$smtpPassword = trim($data['smtp_password'] ?? '');
$smtpFromEmail = trim($data['smtp_from_email'] ?? '');
$smtpFromName = trim($data['smtp_from_name'] ?? '');

// Validate required fields
if (empty($testEmail) || empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required SMTP configuration']);
    exit();
}

// Validate email format
if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid test email address']);
    exit();
}

try {
    // Create PHPMailer instance
    require_once '../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;

    if (!empty($smtpEncryption)) {
        if ($smtpEncryption === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpEncryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
    }

    $mail->Port = !empty($smtpPort) ? (int)$smtpPort : 587;

    // Get tenant name
    $tenantName = 'MTravels'; // Default fallback
    $stmt = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    if ($tenant) {
        $tenantName = $tenant['name'];
    }
    $stmt->close();

    // Recipients
    $mail->setFrom(
        !empty($smtpFromEmail) ? $smtpFromEmail : $smtpUsername,
        !empty($smtpFromName) ? $smtpFromName : $tenantName
    );
    $mail->addAddress($testEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = "SMTP Test Email - {$tenantName}";

    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .success { color: #28a745; font-weight: bold; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>SMTP Configuration Test</h2>
                <p>{$tenantName}</p>
            </div>
            <div class='content'>
                <h3 class='success'>✅ Test Email Sent Successfully!</h3>
                <p>This email confirms that your SMTP configuration is working correctly.</p>

                <div class='info'>
                    <strong>Test Details:</strong><br>
                    • SMTP Host: {$smtpHost}<br>
                    • Port: " . (!empty($smtpPort) ? $smtpPort : '587') . "<br>
                    • Encryption: " . (!empty($smtpEncryption) ? strtoupper($smtpEncryption) : 'None') . "<br>
                    • From: " . (!empty($smtpFromEmail) ? $smtpFromEmail : $smtpUsername) . "<br>
                    • Tenant: {$tenantName}<br>
                    • Sent at: " . date('Y-m-d H:i:s') . "
                </div>

                <p>If you received this email, your SMTP settings are properly configured and ready to send emails from your agency.</p>

                <hr>
                <p style='font-size: 12px; color: #666;'>
                    This is an automated test email from {$tenantName}.<br>
                    If you did not expect this email, please contact your system administrator.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    $mail->AltBody = "SMTP Test Email - {$tenantName}

✅ Test Email Sent Successfully!

This email confirms that your SMTP configuration is working correctly.

Test Details:
• SMTP Host: {$smtpHost}
• Port: " . (!empty($smtpPort) ? $smtpPort : '587') . "
• Encryption: " . (!empty($smtpEncryption) ? strtoupper($smtpEncryption) : 'None') . "
• From: " . (!empty($smtpFromEmail) ? $smtpFromEmail : $smtpUsername) . "
• Tenant: {$tenantName}
• Sent at: " . date('Y-m-d H:i:s') . "

If you received this email, your SMTP settings are properly configured and ready to send emails from your agency.

---
This is an automated test email from {$tenantName}.
If you did not expect this email, please contact your system administrator.";

    $mail->send();

    // Log successful test
    error_log("Tenant SMTP test email sent successfully to: {$testEmail} for tenant: {$tenantName}");

    echo json_encode([
        'success' => true,
        'message' => 'Test email sent successfully to ' . $testEmail
    ]);

} catch (Exception $e) {
    error_log("Tenant SMTP test failed for tenant {$tenant_id}: " . $mail->ErrorInfo);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to send test email: ' . $mail->ErrorInfo
    ]);
}
?>