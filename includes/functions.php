<?php
// Email sending function using PHPMailer with tracking
function sendEmail($to, $subject, $body, $isHtml = true, $emailType = 'general', $recipientName = '', $tenantId = null) {
    require_once '../vendor/autoload.php';

    // Get SMTP settings - tenant-specific or platform fallback
    $smtpSettings = getTenantSMTPSettings($tenantId);

    if (empty($smtpSettings['smtp_host']) || empty($smtpSettings['smtp_username']) || empty($smtpSettings['smtp_password'])) {
        error_log("SMTP settings not configured for tenant: " . ($tenantId ?? 'platform'));
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Generate unique email ID for tracking
        $emailId = uniqid('email_', true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpSettings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['smtp_username'];
        $mail->Password = $smtpSettings['smtp_password'];

        if (!empty($smtpSettings['smtp_encryption'])) {
            if ($smtpSettings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($smtpSettings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
        }

        $mail->Port = !empty($smtpSettings['smtp_port']) ? (int)$smtpSettings['smtp_port'] : 587;

        // Get tenant name for sender
        global $tenant_id;
        $tenantName = 'MTravels'; // Default fallback

        if (isset($tenant_id) && $tenant_id) {
            global $conn;
            $stmt = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bind_param("i", $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenant = $result->fetch_assoc();
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
            $stmt->close();
        }

        // Recipients
        $mail->setFrom(
            !empty($smtpSettings['smtp_from_email']) ? $smtpSettings['smtp_from_email'] : $smtpSettings['smtp_username'],
            $tenantName
        );
        $mail->addAddress($to, $recipientName);

        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;

        // Add tracking pixel to HTML emails
        if ($isHtml) {
            $trackingPixel = "<img src=\"" . getBaseUrl() . "/email_tracking.php?email_id={$emailId}\" width=\"1\" height=\"1\" style=\"display:none;\" alt=\"\" />";
            $body .= $trackingPixel;
        }

        $mail->Body = $body;

        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();

        // Record email in tracking table
        recordEmailTracking($emailId, $to, $emailType, $tenant_id);

        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Record email tracking
function recordEmailTracking($emailId, $recipientEmail, $emailType, $tenantId) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO email_tracking (email_id, recipient_email, email_type, tenant_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $emailId, $recipientEmail, $emailType, $tenantId);
    $stmt->execute();
    $stmt->close();
}

// Get base URL for tracking
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

// Get platform settings
function getPlatformSettings() {
    static $settings = null;

    if ($settings === null) {
        global $conn;
        $stmt = $conn->prepare("SELECT `key`, `value` FROM platform_settings");
        $stmt->execute();
        $result = $stmt->get_result();
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['key']] = $row['value'];
        }
        $stmt->close();
    }

    return $settings;
}

// Get tenant-specific SMTP settings (fallback to platform settings)
function getTenantSMTPSettings($tenantId = null) {
    global $conn;

    if (!$tenantId) {
        return getPlatformSettings();
    }

    // Check if tenant has custom SMTP settings in settings table
    $stmt = $conn->prepare("SELECT smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, smtp_from_email, smtp_from_name FROM settings WHERE tenant_id = ?");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenantSettings = $result->fetch_assoc();
    $stmt->close();

    // Filter out empty values and return only configured settings
    $filteredSettings = array_filter($tenantSettings, function($value) {
        return !empty($value);
    });

    // If tenant has SMTP settings, use them; otherwise fall back to platform settings
    if (!empty($filteredSettings)) {
        return $filteredSettings;
    }

    return getPlatformSettings();
}

// Send ticket notification email
function sendTicketNotification($clientEmail, $clientName, $ticketType, $ticketDetails) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
        $stmt->bind_param("i", $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $tenant = $result->fetch_assoc();
        if ($tenant) {
            $tenantName = $tenant['name'];
        }
        $stmt->close();
    }

    $subject = "New {$ticketType} Ticket Added - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .ticket-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #667eea; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - New Ticket Notification</h2>
            </div>
            <div class='content'>
                <p>Dear {$clientName},</p>
                <p>A new <strong>{$ticketType}</strong> ticket has been added to your account.</p>

                <div class='ticket-info'>
                    <h4>Ticket Details:</h4>
                    {$ticketDetails}
                </div>

                <p>Please log in to your account to view the complete details and manage your booking.</p>

                <p>If you have any questions, please don't hesitate to contact our support team.</p>

                <p>Best regards,<br>{$tenantName} Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($clientEmail, $subject, $body, true, 'ticket_notification', $clientName, $tenant_id);
}

// Send notification to debtors/creditors
function sendAccountNotification($email, $name, $type, $amount, $currency, $message = '') {
    $accountType = ucfirst($type); // Debtor or Creditor
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
        $stmt->bind_param("i", $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $tenant = $result->fetch_assoc();
        if ($tenant) {
            $tenantName = $tenant['name'];
        }
        $stmt->close();
    }

    $subject = "Account Update - {$accountType} Balance - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .balance-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #667eea; text-align: center; }
            .amount { font-size: 24px; font-weight: bold; color: #667eea; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - Account Notification</h2>
            </div>
            <div class='content'>
                <p>Dear {$name},</p>
                <p>This is to notify you about an update to your {$type} account balance.</p>

                <div class='balance-info'>
                    <h4>Current Balance</h4>
                    <div class='amount'>{$amount} {$currency}</div>
                </div>

                " . (!empty($message) ? "<p><strong>Additional Information:</strong><br>{$message}</p>" : "") . "

                <p>Please log in to your account for detailed transaction history and statements.</p>

                <p>If you have any questions about this notification, please contact our finance team.</p>

                <p>Best regards,<br>{$tenantName} Finance Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($email, $subject, $body, true, 'account_notification', $name, $tenant_id);
}

// Send tenant welcome email
function sendTenantWelcomeEmail($tenantEmail, $tenantName, $agencyName, $subdomain) {
    $subject = "Welcome to MTravels - Your Agency Account is Ready!";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .welcome-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
            .login-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .cta-button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to MTravels!</h1>
                <p>Your Travel Agency Management Platform</p>
            </div>
            <div class='content'>
                <h2>Dear {$tenantName},</h2>
                <p>Congratulations! Your agency account has been successfully created on the MTravels platform.</p>

                <div class='welcome-box'>
                    <h3>🎉 Your Account Details</h3>
                    <p><strong>Agency Name:</strong> {$agencyName}</p>
                    <p><strong>Subdomain:</strong> {$subdomain}.mtravels.com</p>
                    <p><strong>Status:</strong> Active</p>
                </div>

                <div class='login-info'>
                    <h4>🚀 Getting Started</h4>
                    <p>To access your agency dashboard:</p>
                    <ol>
                        <li>Visit: <strong>https://{$subdomain}.mtravels.com</strong></li>
                        <li>Use the login credentials that will be sent to you separately</li>
                        <li>Complete your agency profile and SMTP configuration</li>
                        <li>Start adding your first clients and managing bookings</li>
                    </ol>
                </div>

                <div class='welcome-box'>
                    <h4>📋 What's Next?</h4>
                    <ul>
                        <li><strong>Configure SMTP:</strong> Set up email sending for notifications</li>
                        <li><strong>Add Team Members:</strong> Create user accounts for your staff</li>
                        <li><strong>Import Clients:</strong> Upload your existing client database</li>
                        <li><strong>Customize Settings:</strong> Personalize your agency branding</li>
                    </ul>
                </div>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://{$subdomain}.mtravels.com' class='cta-button'>Access Your Dashboard →</a>
                </p>

                <p>If you have any questions or need assistance getting started, please don't hesitate to contact our support team at <a href='mailto:support@mtravels.com'>support@mtravels.com</a>.</p>

                <p>Welcome aboard! We're excited to help your agency grow and succeed.</p>

                <p>Best regards,<br><strong>The MTravels Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated welcome message from MTravels Platform.<br>
                © 2024 MTravels. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($tenantEmail, $subject, $body, true, 'tenant_welcome', $tenantName, null); // Use platform SMTP for tenant welcome
}

// Send user credentials email
function sendUserCredentialsEmail($userEmail, $userName, $password, $role, $tenantId = null) {
    // Get tenant information if applicable
    $tenantName = 'MTravels Platform';
    $subdomain = '';
    $agencyName = '';

    if ($tenantId) {
        global $conn;
        $stmt = $conn->prepare("SELECT name, subdomain FROM tenants WHERE id = ?");
        $stmt->bind_param("i", $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tenant = $result->fetch_assoc();
        if ($tenant) {
            $tenantName = $tenant['name'];
            $subdomain = $tenant['subdomain'];

            // Get agency name from settings
            $stmt2 = $conn->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
            $stmt2->bind_param("i", $tenantId);
            $stmt2->execute();
            $settings = $stmt2->get_result()->fetch_assoc();
            if ($settings && $settings['agency_name']) {
                $agencyName = $settings['agency_name'];
            }
            $stmt2->close();
        }
        $stmt->close();
    }

    $roleDisplay = ucfirst(str_replace('_', ' ', $role));
    $subject = "Your {$tenantName} Account Credentials";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .credentials-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #667eea; }
            .credential-item { background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #667eea; }
            .password-warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .login-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .cta-button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to {$tenantName}!</h1>
                <p>Your Account Has Been Created</p>
            </div>
            <div class='content'>
                <h2>Dear {$userName},</h2>
                <p>Your account has been successfully created on the {$tenantName} platform.</p>

                <div class='credentials-box'>
                    <h3 style='color: #667eea; margin-top: 0;'>🔐 Your Login Credentials</h3>
                    <div class='credential-item'>
                        <strong>Email:</strong> {$userEmail}
                    </div>
                    <div class='credential-item'>
                        <strong>Password:</strong> {$password}
                    </div>
                    <div class='credential-item'>
                        <strong>Role:</strong> {$roleDisplay}
                    </div>" .
                    (!empty($agencyName) ? "<div class='credential-item'><strong>Agency:</strong> {$agencyName}</div>" : "") .
                    (!empty($subdomain) ? "<div class='credential-item'><strong>Subdomain:</strong> {$subdomain}.mtravels.com</div>" : "") .
                "</div>

                <div class='password-warning'>
                    <h4 style='color: #856404; margin-top: 0;'>⚠️ Important Security Notice</h4>
                    <p><strong>Please change your password immediately after first login</strong> for security purposes. Your current password is temporary and should not be shared with anyone.</p>
                </div>

                <div class='login-info'>
                    <h4>🚀 Getting Started</h4>
                    <p>To access your account:</p>
                    <ol>
                        <li>Visit: <strong>" . (!empty($subdomain) ? "https://{$subdomain}.mtravels.com" : "https://app.mtravels.com") . "</strong></li>
                        <li>Enter your email and the password above</li>
                        <li>Change your password in the profile settings</li>
                        <li>Complete your profile information</li>
                    </ol>
                </div>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . (!empty($subdomain) ? "https://{$subdomain}.mtravels.com" : "https://app.mtravels.com") . "' class='cta-button'>Login to Your Account →</a>
                </p>

                <p>If you have any questions or need assistance, please don't hesitate to contact our support team at <a href='mailto:support@mtravels.com'>support@mtravels.com</a>.</p>

                <p>Welcome to the team! We're excited to have you on board.</p>

                <p>Best regards,<br><strong>The {$tenantName} Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from {$tenantName}.<br>
                If you did not expect this email, please contact support immediately.<br>
                © 2024 MTravels. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($userEmail, $subject, $body, true, 'user_credentials', $userName, null); // Use platform SMTP for super admin actions
}

// Send notification to tenant admin about new user creation
function sendTenantUserNotificationEmail($tenantId, $userName, $userEmail, $userRole) {
    global $conn;

    // Get tenant information
    $stmt = $conn->prepare("SELECT name, billing_email, subdomain FROM tenants WHERE id = ?");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    $stmt->close();

    if (!$tenant) {
        error_log("Tenant not found for user notification email: {$tenantId}");
        return false;
    }

    $tenantName = $tenant['name'];
    $billingEmail = $tenant['billing_email'];
    $subdomain = $tenant['subdomain'];

    // Get agency name from settings
    $agencyName = '';
    $stmt = $conn->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    if ($settings && $settings['agency_name']) {
        $agencyName = $settings['agency_name'];
    }
    $stmt->close();

    $roleDisplay = ucfirst(str_replace('_', ' ', $userRole));
    $subject = "New User Added to Your {$tenantName} Account";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .user-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8; }
            .action-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>👤 New User Added</h1>
                <p>A new team member has been added to your account</p>
            </div>
            <div class='content'>
                <h2>Dear {$tenantName} Team,</h2>
                <p>A new user has been added to your MTravels agency account.</p>

                <div class='user-info'>
                    <h3 style='color: #17a2b8; margin-top: 0;'>New User Details</h3>
                    <p><strong>Name:</strong> {$userName}</p>
                    <p><strong>Email:</strong> {$userEmail}</p>
                    <p><strong>Role:</strong> {$roleDisplay}</p>
                    <p><strong>Agency:</strong> {$agencyName}</p>
                    <p><strong>Added On:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                </div>

                <div class='action-info'>
                    <h4 style='color: #007bff; margin-top: 0;'>What Happens Next?</h4>
                    <ul>
                        <li>The user will receive their login credentials via email</li>
                        <li>They should change their password after first login</li>
                        <li>You can manage their permissions in the user management section</li>
                        <li>The user can now access your agency dashboard</li>
                    </ul>
                </div>

                <p>If you did not authorize this user addition or have any concerns, please contact our support team immediately at <a href='mailto:support@mtravels.com'>support@mtravels.com</a>.</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://{$subdomain}.mtravels.com' style='background: #17a2b8; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Manage Users →</a>
                </p>

                <p>Best regards,<br><strong>The MTravels Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated notification from MTravels Platform.<br>
                © 2024 MTravels. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($billingEmail, $subject, $body, true, 'user_notification', $tenantName, null); // Use platform SMTP for super admin notifications
}

// Send payment confirmation email to tenant
function sendPaymentConfirmationEmail($tenantId, $amount, $currency, $paymentDate, $billingCycle) {
    global $conn;

    // Get tenant information
    $stmt = $conn->prepare("SELECT name, billing_email, subdomain FROM tenants WHERE id = ?");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    $stmt->close();

    if (!$tenant) {
        error_log("Tenant not found for payment confirmation email: {$tenantId}");
        return false;
    }

    $tenantName = $tenant['name'];
    $billingEmail = $tenant['billing_email'];
    $subdomain = $tenant['subdomain'];

    // Get agency name from settings
    $agencyName = '';
    $stmt = $conn->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    if ($settings && $settings['agency_name']) {
        $agencyName = $settings['agency_name'];
    }
    $stmt->close();

    $subject = "Payment Confirmation - {$tenantName} Subscription";

    // Calculate next billing date
    $nextBillingDate = calculateNextBillingDate($paymentDate, $billingCycle);

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .payment-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
            .amount { font-size: 28px; font-weight: bold; color: #28a745; text-align: center; margin: 15px 0; }
            .billing-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Payment Confirmed!</h1>
                <p>Your subscription payment has been processed successfully</p>
            </div>
            <div class='content'>
                <h2>Dear {$tenantName} Team,</h2>
                <p>We're pleased to confirm that your subscription payment has been received and processed successfully.</p>

                <div class='payment-details'>
                    <h3 style='color: #28a745; margin-top: 0;'>Payment Details</h3>
                    <div class='amount'>{$amount} {$currency}</div>
                    <p><strong>Payment Date:</strong> " . date('F j, Y', strtotime($paymentDate)) . "</p>
                    <p><strong>Billing Cycle:</strong> " . ucfirst($billingCycle) . "</p>
                    <p><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>Paid</span></p>
                </div>

                <div class='billing-info'>
                    <h4 style='color: #007bff; margin-top: 0;'>📅 Billing Information</h4>
                    <p><strong>Next Billing Date:</strong> " . date('F j, Y', strtotime($nextBillingDate)) . "</p>
                    <p><strong>Account Status:</strong> Active</p>
                    <p><strong>Agency:</strong> {$agencyName}</p>
                    <p><strong>Subdomain:</strong> {$subdomain}.mtravels.com</p>
                </div>

                <p>Your account is now current and all services are active. You will receive a reminder email 7 days before your next billing date.</p>

                <p>If you have any questions about this payment or need to update your billing information, please don't hesitate to contact our billing support team.</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://{$subdomain}.mtravels.com' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Access Your Dashboard →</a>
                </p>

                <p>Thank you for choosing MTravels! We appreciate your business and continued partnership.</p>

                <p>Best regards,<br><strong>The MTravels Billing Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated payment confirmation from MTravels Platform.<br>
                Payment Reference: PAY-" . date('Ymd', strtotime($paymentDate)) . "-{$tenantId}<br>
                © 2024 MTravels. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($billingEmail, $subject, $body, true, 'payment_confirmation', $tenantName, null); // Use platform SMTP for payment confirmations
}

// Helper function to calculate next billing date (duplicate from subscription_payments.php)
function calculateNextBillingDate($payment_date, $billing_cycle) {
    $date = new DateTime($payment_date);

    switch ($billing_cycle) {
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'quarterly':
            $date->modify('+3 months');
            break;
        case 'yearly':
            $date->modify('+1 year');
            break;
        default:
            $date->modify('+1 month'); // Default to monthly
    }

    return $date->format('Y-m-d');
}
?>