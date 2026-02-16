<?php
// Email sending function using PHPMailer with tracking
function sendEmail($to, $subject, $body, $isHtml = true, $emailType = 'general', $recipientName = '', $tenantId = null, $attachments = []) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';

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
        global $branch_id;
        $tenantName = 'MTravels'; // Default fallback

        if (isset($tenant_id) && $tenant_id) {
            global $pdo;
            if ($pdo !== null) {
                $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
                $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                $stmt->execute();
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($tenant) {
                    $tenantName = $tenant['name'];
                }
            }
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

        // Add attachments if provided
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['path']) && file_exists($attachment['path'])) {
                    $name = $attachment['name'] ?? basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $name);
                } elseif (is_string($attachment) && file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
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
    global $pdo, $branch_id;

    if ($pdo === null) {
        error_log("No database connection available in recordEmailTracking");
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO email_tracking (email_id, recipient_email, email_type, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $emailId, PDO::PARAM_STR);
        $stmt->bindParam(2, $recipientEmail, PDO::PARAM_STR);
        $stmt->bindParam(3, $emailType, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenantId, PDO::PARAM_INT);
        $branchIdVal = isset($branch_id) ? intval($branch_id) : null;
        $stmt->bindParam(5, $branchIdVal, PDO::PARAM_INT);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("Error in recordEmailTracking: " . $e->getMessage());
        return false;
    }
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
        global $pdo;

        if ($pdo === null) {
            error_log("No database connection available in getPlatformSettings");
            return [];
        }

        $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
    }

    return $settings;
}

// Get platform settings formatted for SMTP use
function getPlatformSettingsFormatted() {
    $raw_settings = getPlatformSettings();
    
    // Map platform_settings keys to SMTP field names
    $formatted = [
        'smtp_host' => $raw_settings['smtp_host'] ?? '',
        'smtp_port' => $raw_settings['smtp_port'] ?? 587,
        'smtp_encryption' => $raw_settings['smtp_encryption'] ?? 'tls',
        'smtp_username' => $raw_settings['smtp_username'] ?? '',
        'smtp_password' => $raw_settings['smtp_password'] ?? '',
        'smtp_from_email' => $raw_settings['smtp_from_email'] ?? '',
        'smtp_from_name' => $raw_settings['smtp_from_name'] ?? 'MTravels'
    ];
    
    return $formatted;
}

// Get tenant-specific SMTP settings (fallback to platform settings)
function getTenantSMTPSettings($tenantId = null) {
    global $pdo;

    if ($pdo === null) {
        error_log("No database connection available in getTenantSMTPSettings");
        return [];
    }

    if (!$tenantId) {
        return getPlatformSettingsFormatted();
    }

    // Check if tenant has custom SMTP settings in settings table
    $stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, smtp_from_email, smtp_from_name FROM settings WHERE tenant_id = ?");
    $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $tenantSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Filter out empty values and return only configured settings
    $filteredSettings = array_filter($tenantSettings, function($value) {
        return !empty($value);
    });

    // If tenant has SMTP settings, use them; otherwise fall back to platform settings
    if (!empty($filteredSettings)) {
        return $filteredSettings;
    }

    return getPlatformSettingsFormatted();
}

// Send ticket notification email
function sendTicketNotification($clientEmail, $clientName, $ticketType, $ticketDetails) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
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
                    <h4>{$ticketType} Details:</h4>
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

// Send visa application notification email
function sendVisaNotification($clientEmail, $clientName, $applicationId, $applicantName, $passportNumber, $country, $visaType, $appliedDate, $issuedDate, $sold, $currency) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
    }

    $subject = "New Visa Application Added - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .visa-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - New Visa Application</h2>
            </div>
            <div class='content'>
                <p>Dear {$clientName},</p>
                <p>A new <strong>visa application</strong> has been added to your account.</p>

                <div class='visa-info'>
                    <h4>Visa Application Details:</h4>
                    <p><strong>Application ID:</strong> {$applicationId}</p>
                    <p><strong>Applicant Name:</strong> {$applicantName}</p>
                    <p><strong>Passport Number:</strong> {$passportNumber}</p>
                    <p><strong>Country:</strong> {$country}</p>
                    <p><strong>Visa Type:</strong> {$visaType}</p>
                    <p><strong>Applied Date:</strong> {$appliedDate}</p>
                    <p><strong>Issued Date:</strong> {$issuedDate}</p>
                    <p><strong>Total Amount:</strong> {$sold} {$currency}</p>
                </div>

                <p>Please log in to your account to view the complete application details and track the status.</p>

                <p>If you have any questions about this visa application, please don't hesitate to contact our support team.</p>

                <p>Best regards,<br>{$tenantName} Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($clientEmail, $subject, $body, true, 'visa_notification', $clientName, $tenant_id);
}

// Send hotel booking notification email
function sendHotelNotification($clientEmail, $clientName, $bookingId, $guestName, $checkInDate, $checkOutDate, $accommodationDetails, $soldAmount, $currency) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
    }

    $subject = "New Hotel Booking Added - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .hotel-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007bff; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - New Hotel Booking</h2>
            </div>
            <div class='content'>
                <p>Dear {$clientName},</p>
                <p>A new <strong>hotel booking</strong> has been added to your account.</p>

                <div class='hotel-info'>
                    <h4>Hotel Booking Details:</h4>
                    <p><strong>Booking ID:</strong> {$bookingId}</p>
                    <p><strong>Guest Name:</strong> {$guestName}</p>
                    <p><strong>Check-in Date:</strong> {$checkInDate}</p>
                    <p><strong>Check-out Date:</strong> {$checkOutDate}</p>
                    <p><strong>Accommodation:</strong> {$accommodationDetails}</p>
                    <p><strong>Total Amount:</strong> {$soldAmount} {$currency}</p>
                </div>

                <p>Please log in to your account to view the complete booking details and manage your reservation.</p>

                <p>If you have any questions about this hotel booking, please don't hesitate to contact our support team.</p>

                <p>Best regards,<br>{$tenantName} Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($clientEmail, $subject, $body, true, 'hotel_notification', $clientName, $tenant_id);
}

// Send umrah booking notification email
function sendUmrahNotification($clientEmail, $clientName, $bookingId, $passengerName, $flightDate, $returnDate, $roomType, $totalAmount, $amountPaid, $dueAmount, $currency) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
    }

    $subject = "New Umrah Booking Added - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .umrah-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #6f42c1; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - New Umrah Booking</h2>
            </div>
            <div class='content'>
                <p>Dear {$clientName},</p>
                <p>A new <strong>Umrah booking</strong> has been added to your account.</p>

                <div class='umrah-info'>
                    <h4>Umrah Booking Details:</h4>
                    <p><strong>Booking ID:</strong> {$bookingId}</p>
                    <p><strong>Passenger Name:</strong> {$passengerName}</p>
                    <p><strong>Flight Date:</strong> {$flightDate}</p>
                    <p><strong>Return Date:</strong> {$returnDate}</p>
                    <p><strong>Room Type:</strong> {$roomType}</p>
                    <p><strong>Total Amount:</strong> {$totalAmount} {$currency}</p>
                    <p><strong>Amount Paid:</strong> {$amountPaid} {$currency}</p>
                    <p><strong>Due Amount:</strong> {$dueAmount} {$currency}</p>
                </div>

                <p>Please log in to your account to view the complete booking details and manage your Umrah package.</p>

                <p>If you have any questions about this Umrah booking, please don't hesitate to contact our support team.</p>

                <p>Best regards,<br>{$tenantName} Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($clientEmail, $subject, $body, true, 'umrah_notification', $clientName, $tenant_id);
}

// Send ticket reservation notification email
function sendTicketReservationNotification($clientEmail, $clientName, $ticketId, $passengerName, $pnr, $origin, $destination, $airline, $departureDate, $returnDate, $sold, $currency) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
    }

    $subject = "New Flight Ticket Reservation - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #fd7e14 0%, #e55100 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .ticket-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #fd7e14; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - Flight Ticket Reservation</h2>
            </div>
            <div class='content'>
                <p>Dear {$clientName},</p>
                <p>A new <strong>flight ticket reservation</strong> has been added to your account.</p>

                <div class='ticket-info'>
                    <h4>Flight Reservation Details:</h4>
                    <p><strong>Reservation ID:</strong> {$ticketId}</p>
                    <p><strong>Passenger Name:</strong> {$passengerName}</p>
                    <p><strong>PNR:</strong> {$pnr}</p>
                    <p><strong>Route:</strong> {$origin} → {$destination}</p>
                    <p><strong>Airline:</strong> {$airline}</p>
                    <p><strong>Departure Date:</strong> {$departureDate}</p>
                    " . (!empty($returnDate) ? "<p><strong>Return Date:</strong> {$returnDate}</p>" : "") . "
                    <p><strong>Total Amount:</strong> {$sold} {$currency}</p>
                </div>

                <p>Please log in to your account to view the complete reservation details and manage your booking.</p>

                <p>If you have any questions about this flight reservation, please don't hesitate to contact our support team.</p>

                <p>Best regards,<br>{$tenantName} Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($clientEmail, $subject, $body, true, 'ticket_reservation_notification', $clientName, $tenant_id);
}

// Send salary advance notification email
function sendSalaryAdvanceNotification($employeeEmail, $employeeName, $advanceId, $amount, $currency, $advanceDate, $description, $receipt) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
    }

    $subject = "Salary Advance Processed - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .advance-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #dc3545; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - Salary Advance</h2>
            </div>
            <div class='content'>
                <p>Dear {$employeeName},</p>
                <p>Your <strong>salary advance</strong> has been processed successfully.</p>

                <div class='advance-info'>
                    <h4>Advance Details:</h4>
                    <p><strong>Advance ID:</strong> {$advanceId}</p>
                    <p><strong>Amount:</strong> {$amount} {$currency}</p>
                    <p><strong>Advance Date:</strong> {$advanceDate}</p>
                    <p><strong>Receipt:</strong> {$receipt}</p>
                    " . (!empty($description) ? "<p><strong>Description:</strong> {$description}</p>" : "") . "
                </div>

                <p>Please note that this advance will be deducted from your upcoming salary payments.</p>

                <p>If you have any questions about this advance, please contact the HR department.</p>

                <p>Best regards,<br>{$tenantName} HR Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($employeeEmail, $subject, $body, true, 'salary_advance_notification', $employeeName, $tenant_id);
}

// Send salary payment notification email
function sendSalaryPaymentNotification($employeeEmail, $employeeName, $paymentId, $amount, $currency, $paymentDate, $paymentForMonth, $paymentType, $description, $receipt) {
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo === null) {
            error_log("No database connection available in sendSalaryPaymentNotification");
            return false;
        }

        $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            $tenantName = $tenant['name'];
        }
    }

    $subject = "Salary Payment Processed - {$tenantName}";

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .payment-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$tenantName} - Salary Payment</h2>
            </div>
            <div class='content'>
                <p>Dear {$employeeName},</p>
                <p>Your <strong>salary payment</strong> has been processed successfully.</p>

                <div class='payment-info'>
                    <h4>Payment Details:</h4>
                    <p><strong>Payment ID:</strong> {$paymentId}</p>
                    <p><strong>Amount:</strong> {$amount} {$currency}</p>
                    <p><strong>Payment Date:</strong> {$paymentDate}</p>
                    <p><strong>For Month:</strong> {$paymentForMonth}</p>
                    <p><strong>Payment Type:</strong> " . ucfirst($paymentType) . "</p>
                    <p><strong>Receipt:</strong> {$receipt}</p>
                    " . (!empty($description) ? "<p><strong>Description:</strong> {$description}</p>" : "") . "
                </div>

                <p>Your salary has been processed and will be reflected in your account accordingly.</p>

                <p>If you have any questions about this payment, please contact the HR department.</p>

                <p>Best regards,<br>{$tenantName} HR Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($employeeEmail, $subject, $body, true, 'salary_payment_notification', $employeeName, $tenant_id);
}

// Send notification to debtors/creditors
function sendAccountNotification($email, $name, $type, $amount, $currency, $message = '') {
    $accountType = ucfirst($type); // Debtor or Creditor
    // Get tenant name for subject
    global $tenant_id;
    $tenantName = 'MTravels'; // Default fallback

    if (isset($tenant_id) && $tenant_id) {
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
            }
        }
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
        global $pdo;
        if ($pdo !== null) {
            $stmt = $pdo->prepare("SELECT name, subdomain FROM tenants WHERE id = ?");
            $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                $tenantName = $tenant['name'];
                $subdomain = $tenant['subdomain'];

                // Get agency name from settings
                $stmt2 = $pdo->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
                $stmt2->bindParam(1, $tenantId, PDO::PARAM_INT);
                $stmt2->execute();
                $settings = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($settings && $settings['agency_name']) {
                    $agencyName = $settings['agency_name'];
                }
            }
        }
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
    global $pdo;

    if ($pdo === null) {
        error_log("No database connection available in sendTenantUserNotificationEmail");
        return false;
    }

    // Get tenant information
    $stmt = $pdo->prepare("SELECT name, billing_email, subdomain FROM tenants WHERE id = ?");
    $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        error_log("Tenant not found for user notification email: {$tenantId}");
        return false;
    }

    $tenantName = $tenant['name'];
    $billingEmail = $tenant['billing_email'];
    $subdomain = $tenant['subdomain'];

    // Get agency name from settings
    $agencyName = '';
    $stmt = $pdo->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
    $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings && $settings['agency_name']) {
        $agencyName = $settings['agency_name'];
    }

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
function sendPaymentConfirmationEmail($tenantId, $amount, $currency, $paymentDate, $billingCycle, $paymentId = null, $subscriptionId = null) {
    global $pdo;

    if ($pdo === null) {
        error_log("No database connection available in sendPaymentConfirmationEmail");
        return false;
    }

    // Get tenant information
    $stmt = $pdo->prepare("SELECT name, billing_email, subdomain FROM tenants WHERE id = ?");
    $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        error_log("Tenant not found for payment confirmation email: {$tenantId}");
        return false;
    }

    $tenantName = $tenant['name'];
    $billingEmail = $tenant['billing_email'];
    $subdomain = $tenant['subdomain'];

    // Validate billing email
    if (empty($billingEmail)) {
        error_log("No billing email configured for tenant: {$tenantId} ({$tenantName})");
        return false;
    }

    // Generate invoice PDF if payment_id is available
    $attachments = [];
    if ($paymentId && $subscriptionId) {
        $pdfPath = generateInvoicePDF($paymentId, $subscriptionId);
        if ($pdfPath && file_exists($pdfPath)) {
            $attachments[] = [
                'path' => $pdfPath,
                'name' => 'Invoice_' . $paymentId . '.pdf'
            ];
        }
    }

    // Get agency name from settings
    $agencyName = '';
    $stmt = $pdo->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
    $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings && $settings['agency_name']) {
        $agencyName = $settings['agency_name'];
    }

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

    return sendEmail($billingEmail, $subject, $body, true, 'payment_confirmation', $tenantName, null, $attachments); // Use platform SMTP for payment confirmations with PDF attachment
}

// Generate invoice PDF for email attachment using the existing generate_invoice_pdf.php
function generateInvoicePDF($paymentId, $subscriptionId) {
        global $pdo;
        
        try {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            
            // Create temp directory if not exists
            $temp_dir = '../temp/';
            if (!is_dir($temp_dir)) {
                mkdir($temp_dir, 0755, true);
            }
            
            // Generate filename with timestamp
            $pdf_filename = 'invoice_' . $paymentId . '_' . time() . '.pdf';
            $pdf_path = $temp_dir . $pdf_filename;
            
            // Use output buffering to capture PDF from generate_invoice_pdf.php
            ob_start();
            
            // Call the existing generate_invoice_pdf.php with parameters
            $_GET['payment_id'] = $paymentId;
            $_GET['subscription_id'] = $subscriptionId;
            $_GET['output'] = 'file'; // Add flag to save as file
            $_GET['output_path'] = $pdf_path;
            
            // Include the existing invoice generator
            include '../super_admin/generate_invoice_pdf.php';
            
            ob_end_clean();
            
            // Check if file was created
            if (file_exists($pdf_path)) {
                return $pdf_path;
            } else {
                error_log("Invoice PDF file was not created at: {$pdf_path}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Error generating invoice PDF: " . $e->getMessage());
            return false;
        }
    }

     // Generate PDF ticket from booking data
    function generateTicketPDF($bookingData, $tenantId) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    // Create new PDF document
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);
    
    // Set PDF properties
    $mpdf->SetTitle('Flight Ticket - ' . $bookingData['pnr']);
    $mpdf->SetAuthor('MTravels');
    $mpdf->SetSubject('Flight Ticket');
    
    // Get agency settings for header
    global $pdo;

    $agencyName = 'MTravels';
    $agencyEmail = 'info@mtravels.com';
    $agencyPhone = '+93 (0) 123 456 789';
    $agencyAddress = '';

    if ($tenantId && $pdo !== null) {
        $stmt = $pdo->prepare("SELECT agency_name, email, phone, address FROM settings WHERE tenant_id = ?");
        $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            $agencyName = $settings['agency_name'] ?: 'MTravels';
            $agencyEmail = $settings['email'] ?: 'info@mtravels.com';
            $agencyPhone = $settings['phone'] ?: '+93 (0) 123 456 789';
            $agencyAddress = $settings['address'] ?: '';
        }
    }
    
    // Format dates for display
    function formatFlightDate($dateTime) {
        if (empty($dateTime) || trim($dateTime) === '') return '';
        $date = DateTime::createFromFormat('Y-m-d H:i', trim($dateTime));
        return $date ? $date->format('H:i / d. M. Y') : $dateTime;
    }
    
    $formattedDeparture = formatFlightDate($bookingData['departure_date']);
    $formattedReturn = !empty($bookingData['return_date']) ? formatFlightDate($bookingData['return_date']) : '';
    
    // Build HTML content for PDF with professional layout
    $html = '
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
            padding: 20px;
            line-height: 1.3;
            background-color: white;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2c3e50;
            display: table;
            width: 100%;
        }
        
        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 33.33%;
        }
        
        .header-left { text-align: left; }
        .header-center { text-align: center; }
        .header-right { text-align: right; }
        
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            margin: 0;
        }
        
        .contact-info {
            font-size: 10pt;
            color: #666;
            line-height: 1.4;
        }
        
        .contact-email {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .flight-details-header {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
            margin: 25px 0 5px 0;
        }
        
        .pnr-display {
            font-size: 12pt;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .flight-section {
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-header {
            background-color: #f8f9fa;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 12pt;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
        }
        
        .outbound { border-left: 4px solid #27ae60; }
        .return { border-left: 4px solid #e67e22; }
        
        .flight-layout-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .flight-layout-table td {
            vertical-align: top;
            padding: 15px;
            border: none;
        }
        
        .flight-departs {
            width: 40%;
        }
        
        .flight-center {
            width: 20%;
            text-align: center;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
        }
        
        .flight-arrives {
            width: 40%;
            text-align: right;
        }
        
        .flight-label {
            font-size: 11pt;
            font-weight: bold;
            color: #666;
            margin-bottom: 8px;
        }
        
        .flight-city {
            font-size: 16pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }
        
        .flight-time {
            font-size: 11pt;
            color: #333;
        }
        
        .flight-number {
            font-size: 14pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 8px;
        }
        
        .plane-icon {
            font-size: 18pt;
            color: #666;
        }
        
        .passengers-header {
            font-size: 14pt;
            font-weight: bold;
            margin: 30px 0 15px 0;
            color: #2c3e50;
            text-decoration: underline;
        }
        
        .passengers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10pt;
        }
        
        .passengers-table th {
            background-color: #2c3e50;
            color: white;
            font-weight: bold;
            padding: 10px 8px;
            border: 1px solid #2c3e50;
            text-align: left;
        }
        
        .passengers-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .sno-col {
            width: 50px;
            text-align: center;
            font-weight: bold;
        }
        
        .name-col {
            width: 35%;
        }
        
        .title-col {
            width: 25%;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
    </style>
    
    <div class="container">
        <div class="header">
            <div class="header-left">
                <strong>' . strtoupper($agencyName) . '</strong>
            </div>
            <div class="header-center">
                <div class="company-name">' . htmlspecialchars($agencyName) . '</div>
            </div>
            <div class="header-right">
                <div class="contact-info">
                    <div class="contact-email">' . htmlspecialchars($agencyEmail) . '</div>
                    <div>' . htmlspecialchars($agencyPhone) . '</div>
                    ' . (!empty($agencyAddress) ? '<div style="font-size: 9pt; margin-top: 2px;">' . htmlspecialchars($agencyAddress) . '</div>' : '') . '
                </div>
            </div>
        </div>

        <div class="flight-details-header">Your Flight Details</div>
        <div class="pnr-display">PNR: ' . htmlspecialchars($bookingData['pnr']) . '</div>

        <!-- Outbound Journey -->
        <div class="flight-section outbound">
            <div class="section-header">
                🛫 Outbound Journey
            </div>
            <table class="flight-layout-table">
                <tr>
                    <td class="flight-departs">
                        <div class="flight-label">Departs</div>
                        <div class="flight-city">' . strtoupper($bookingData['origin']) . '</div>
                        <div class="flight-time">' . $formattedDeparture . '</div>
                    </td>
                    <td class="flight-center">
                        <div class="flight-number">' . strtoupper($bookingData['airline']) . '</div>
                        <div class="plane-icon">✈</div>
                    </td>
                    <td class="flight-arrives">
                        <div class="flight-label">Arrives</div>
                        <div class="flight-city">' . strtoupper($bookingData['destination']) . '</div>
                        <div class="flight-time">' . $formattedDeparture . '</div>
                    </td>
                </tr>
            </table>
        </div>';

    if (!empty($formattedReturn)) {
        $html .= '
        <!-- Return Journey -->
        <div class="flight-section return">
            <div class="section-header">
                🛬 Return Journey
            </div>
            <table class="flight-layout-table">
                <tr>
                    <td class="flight-departs">
                        <div class="flight-label">Departs</div>
                        <div class="flight-city">' . strtoupper($bookingData['destination']) . '</div>
                        <div class="flight-time">' . $formattedReturn . '</div>
                    </td>
                    <td class="flight-center">
                        <div class="flight-number">' . strtoupper($bookingData['airline']) . '</div>
                        <div class="plane-icon">✈</div>
                    </td>
                    <td class="flight-arrives">
                        <div class="flight-label">Arrives</div>
                        <div class="flight-city">' . strtoupper($bookingData['origin']) . '</div>
                        <div class="flight-time">' . $formattedReturn . '</div>
                    </td>
                </tr>
            </table>
        </div>';
    }

    $html .= '
        <div class="passengers-header">Passengers Details</div>
        
        <table class="passengers-table">
            <thead>
                <tr>
                    <th class="sno-col">S/NO</th>
                    <th class="name-col">First Name</th>
                    <th class="name-col">Last Name</th>
                    <th class="title-col">Title</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($bookingData['passengers'] as $index => $passenger) {
        $nameParts = explode(' ', trim($passenger['name']), 2);
        $firstName = strtoupper($nameParts[0] ?? '');
        $lastName = strtoupper($nameParts[1] ?? '');
        
        $html .= '
                <tr>
                    <td class="sno-col">' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($firstName) . '</td>
                    <td>' . htmlspecialchars($lastName) . '</td>
                    <td>' . htmlspecialchars($passenger['title'] ?? '') . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p><strong>Issue Date:</strong> ' . date('d. M. Y', strtotime($bookingData['issue_date'])) . '</p>
            <p>This is a computer-generated ticket. Please verify all details before travel.</p>
            <p>Generated on: ' . date('M d, Y H:i:s') . '</p>
        </div>
    </div>';
    
    // Write HTML to PDF
    $mpdf->WriteHTML($html);
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/tickets/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'ticket_' . $bookingData['pnr'] . '_' . time() . '.pdf';
    $filepath = $uploadDir . $filename;
    
    // Save PDF to file
    $mpdf->Output($filepath, 'F');
    
    return $filepath;
}

// Send ticket notification email with PDF attachment
function sendTicketNotificationWithAttachment($email, $name, $subject, $body, $attachmentPath) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    // Get SMTP settings
    global $tenant_id;
    $smtpSettings = getTenantSMTPSettings($tenant_id);
    
    if (empty($smtpSettings['smtp_host']) || empty($smtpSettings['smtp_username']) || empty($smtpSettings['smtp_password'])) {
        error_log("SMTP settings not configured for tenant: " . ($tenant_id ?? 'platform'));
        return false;
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
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
        $tenantName = 'MTravels'; // Default fallback
        
        if (isset($tenant_id) && $tenant_id) {
            global $pdo;
            if ($pdo !== null) {
                $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
                $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                $stmt->execute();
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($tenant) {
                    $tenantName = $tenant['name'];
                }
            }
        }
        
        // Recipients
        $mail->setFrom(
            !empty($smtpSettings['smtp_from_email']) ? $smtpSettings['smtp_from_email'] : $smtpSettings['smtp_username'],
            $tenantName
        );
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        // Add PDF attachment
        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, 'Flight_Ticket.pdf');
        }
        
        $mail->send();
        
        // Record email in tracking table
        $emailId = uniqid('email_', true);
        recordEmailTracking($emailId, $email, 'ticket_notification_with_pdf', $tenant_id);
        
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>