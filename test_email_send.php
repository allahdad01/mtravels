<?php
/**
 * Simple Email Test
 * Test if emails can be sent to a specific address
 */

require_once "config.php";
require_once "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $recipient_email = $_POST['recipient_email'] ?? null;
    $tenant_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : null;
    
    if (!$recipient_email) {
        $error = "Please enter an email address";
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
                DB_USERNAME,
                DB_PASSWORD
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $mail = new PHPMailer(true);
            
            // Get SMTP config if tenant_id provided
            if ($tenant_id) {
                $stmt = $pdo->prepare("
                    SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_name, smtp_from_email, agency_name
                    FROM settings
                    WHERE tenant_id = ? AND smtp_host IS NOT NULL
                ");
                $stmt->execute([$tenant_id]);
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($config && $config['smtp_host']) {
                    $mail->isSMTP();
                    $mail->Host = $config['smtp_host'];
                    $mail->Port = $config['smtp_port'] ?? 587;
                    $mail->SMTPAuth = !empty($config['smtp_username']);
                    
                    if ($mail->SMTPAuth) {
                        $mail->Username = $config['smtp_username'];
                        $mail->Password = $config['smtp_password'];
                    }
                    
                    $encryption = strtolower($config['smtp_encryption'] ?? 'tls');
                    if ($encryption === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    }
                    
                    $fromEmail = $config['smtp_from_email'] ?? 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                    $fromName = $config['smtp_from_name'] ?? $config['agency_name'] ?? 'Travel Agency';
                    
                    $result = "SMTP Configuration Used";
                } else {
                    $mail->isSendmail();
                    $fromEmail = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                    $fromName = $config['agency_name'] ?? 'Travel Agency';
                    
                    $result = "Default Sendmail Used (No SMTP config found)";
                }
            } else {
                $mail->isSendmail();
                $fromEmail = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $fromName = 'Travel Agency';
                
                $result = "Default Sendmail Used";
            }
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipient_email, 'Test Recipient');
            $mail->Subject = 'Test Email - Monthly Report System';
            $mail->isHTML(true);
            $mail->Body = "<h2>Email Test Successful</h2>";
            $mail->Body .= "<p>This is a test email from the Monthly Report Generator system.</p>";
            $mail->Body .= "<p><strong>Configuration:</strong> " . $result . "</p>";
            $mail->Body .= "<p><strong>From:</strong> " . $fromEmail . " (" . $fromName . ")</p>";
            $mail->Body .= "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
            
            if ($mail->send()) {
                $result = "✓ Email sent successfully to: " . htmlspecialchars($recipient_email);
            } else {
                $error = "Failed to send email. Mailer Error: " . $mail->ErrorInfo;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get list of tenants
$tenants = [];
try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
        DB_USERNAME,
        DB_PASSWORD
    );
    $stmt = $pdo->query("SELECT id, name FROM tenants LIMIT 50");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Email Send</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .container { max-width: 600px; }
        input, select { padding: 8px; width: 100%; margin: 5px 0 15px 0; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Test Email Send</h1>
        
        <?php if ($result): ?>
            <div class="success"><?php echo $result; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <label><strong>Tenant (Optional):</strong></label>
            <select name="tenant_id">
                <option value="">-- Use Default Config --</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['id']; ?>">
                        <?php echo htmlspecialchars($tenant['name']); ?> (ID: <?php echo $tenant['id']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label><strong>Recipient Email:</strong></label>
            <input type="email" name="recipient_email" placeholder="test@example.com" required>
            
            <button type="submit" name="test_email">Send Test Email</button>
        </form>
    </div>
</body>
</html>
