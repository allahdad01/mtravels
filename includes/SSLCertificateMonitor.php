<?php
/**
 * SSL Certificate Monitoring Class
 * Monitors SSL certificate expiry dates and provides alerts
 */

class SSLCertificateMonitor
{
    private $pdo;
    private $alertThresholds = [
        'critical' => 7,   // Alert 7 days before expiry
        'warning' => 30,   // Alert 30 days before expiry
        'info' => 90       // Info 90 days before expiry
    ];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check SSL certificate for a domain
     */
    public function checkCertificate($domain, $port = 443)
    {
        try {
            $context = stream_context_create([
                "ssl" => [
                    "capture_peer_cert" => true,
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ]);

            $socket = stream_socket_client(
                "ssl://{$domain}:{$port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'success' => false,
                    'error' => "Could not connect to {$domain}:{$port} - {$errstr}",
                    'domain' => $domain
                ];
            }

            $params = stream_context_get_params($socket);
            $cert = $params['options']['ssl']['peer_certificate'];

            if (!$cert) {
                fclose($socket);
                return [
                    'success' => false,
                    'error' => "No certificate found for {$domain}",
                    'domain' => $domain
                ];
            }

            $certInfo = openssl_x509_parse($cert);
            fclose($socket);

            if (!$certInfo) {
                return [
                    'success' => false,
                    'error' => "Could not parse certificate for {$domain}",
                    'domain' => $domain
                ];
            }

            $validFrom = $certInfo['validFrom_time_t'];
            $validTo = $certInfo['validTo_time_t'];
            $now = time();

            $daysUntilExpiry = ceil(($validTo - $now) / (60 * 60 * 24));
            $isExpired = $validTo < $now;

            // Determine alert level
            $alertLevel = 'ok';
            if ($isExpired) {
                $alertLevel = 'expired';
            } elseif ($daysUntilExpiry <= $this->alertThresholds['critical']) {
                $alertLevel = 'critical';
            } elseif ($daysUntilExpiry <= $this->alertThresholds['warning']) {
                $alertLevel = 'warning';
            } elseif ($daysUntilExpiry <= $this->alertThresholds['info']) {
                $alertLevel = 'info';
            }

            return [
                'success' => true,
                'domain' => $domain,
                'issuer' => $certInfo['issuer']['O'] ?? 'Unknown',
                'subject' => $certInfo['subject']['CN'] ?? $domain,
                'valid_from' => date('Y-m-d H:i:s', $validFrom),
                'valid_to' => date('Y-m-d H:i:s', $validTo),
                'days_until_expiry' => $daysUntilExpiry,
                'is_expired' => $isExpired,
                'alert_level' => $alertLevel,
                'serial_number' => $certInfo['serialNumberHex'] ?? 'Unknown'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'domain' => $domain
            ];
        }
    }

    /**
     * Get all monitored domains (system-wide)
     */
    public function getMonitoredDomains()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM ssl_certificates
                ORDER BY domain ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Add domain to monitoring
     */
    public function addDomain($domain, $port = 443, $description = '')
    {
        try {
            // Validate domain format
            if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                return ['success' => false, 'error' => 'Invalid domain format'];
            }

            // Check if domain already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM ssl_certificates
                WHERE domain = ?
            ");
            $stmt->execute([$domain]);

            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Domain already being monitored'];
            }

            // Add domain
            $stmt = $this->pdo->prepare("
                INSERT INTO ssl_certificates (domain, port, description, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$domain, $port, $description]);

            return ['success' => true, 'id' => $this->pdo->lastInsertId()];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove domain from monitoring
     */
    public function removeDomain($domainId)
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM ssl_certificates
                WHERE id = ?
            ");
            $stmt->execute([$domainId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update SSL certificate status for a domain
     */
    public function updateCertificateStatus($domainId, $certData)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ssl_certificates
                SET
                    last_checked = NOW(),
                    is_valid = ?,
                    issuer = ?,
                    subject = ?,
                    valid_from = ?,
                    valid_to = ?,
                    days_until_expiry = ?,
                    alert_level = ?,
                    error_message = ?,
                    serial_number = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $certData['success'] ? 1 : 0,
                $certData['issuer'] ?? null,
                $certData['subject'] ?? null,
                $certData['valid_from'] ?? null,
                $certData['valid_to'] ?? null,
                $certData['days_until_expiry'] ?? null,
                $certData['alert_level'] ?? 'unknown',
                $certData['error'] ?? null,
                $certData['serial_number'] ?? null,
                $domainId
            ]);

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check all monitored certificates and update status
     */
    public function checkAllCertificates()
    {
        $domains = $this->getMonitoredDomains();
        $results = [];

        foreach ($domains as $domain) {
            $certData = $this->checkCertificate($domain['domain'], $domain['port']);
            $this->updateCertificateStatus($domain['id'], $certData);
            $results[] = array_merge($domain, $certData);
        }

        return $results;
    }

    /**
     * Get certificates requiring attention (warnings/critical)
     */
    public function getCertificatesNeedingAttention()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM ssl_certificates
                WHERE alert_level IN ('critical', 'warning', 'expired')
                    OR (last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL 24 HOUR))
                ORDER BY
                    CASE alert_level
                        WHEN 'expired' THEN 1
                        WHEN 'critical' THEN 2
                        WHEN 'warning' THEN 3
                        ELSE 4
                    END,
                    days_until_expiry ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Send alert notifications for expiring certificates
     */
    public function sendExpiryAlerts()
    {
        $certificates = $this->getCertificatesNeedingAttention();
        $alertsSent = 0;

        foreach ($certificates as $cert) {
            // Check if we already sent an alert recently (within 24 hours)
            if ($this->shouldSendAlert($cert)) {
                $this->sendAlertNotification($cert);
                $this->markAlertSent($cert['id']);
                $alertsSent++;
            }
        }

        return $alertsSent;
    }

    /**
     * Check if alert should be sent for this certificate
     */
    private function shouldSendAlert($cert)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT last_alert_sent FROM ssl_certificates
                WHERE id = ? AND (
                    last_alert_sent IS NULL
                    OR last_alert_sent < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
            ");
            $stmt->execute([$cert['id']]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Send alert notification
     */
    private function sendAlertNotification($cert)
    {
        // Get super admin email from platform settings
        global $pdo;

        if ($pdo === null) {
            return;
        }

        // Get platform settings for super admin email
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings WHERE `key` IN ('super_admin_email', 'platform_name')");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }

        $superAdminEmail = $settings['super_admin_email'] ?? 'admin@mtravels.com';
        $platformName = $settings['platform_name'] ?? 'MTravels';

        // Prepare alert message
        $alertLevel = ucfirst($cert['alert_level']);
        $domain = htmlspecialchars($cert['domain']);

        if ($cert['is_expired']) {
            $statusMessage = "<span style='color: #dc3545; font-weight: bold;'>EXPIRED</span>";
            $subject = "🚨 CRITICAL: SSL Certificate EXPIRED - {$domain}";
            $priority = "CRITICAL - IMMEDIATE ACTION REQUIRED";
        } elseif ($cert['alert_level'] === 'critical') {
            $statusMessage = "<span style='color: #dc3545; font-weight: bold;'>{$cert['days_until_expiry']} days until expiry</span>";
            $subject = "🚨 CRITICAL: SSL Certificate Expires Soon - {$domain}";
            $priority = "CRITICAL - RENEW IMMEDIATELY";
        } elseif ($cert['alert_level'] === 'warning') {
            $statusMessage = "<span style='color: #fd7e14; font-weight: bold;'>{$cert['days_until_expiry']} days until expiry</span>";
            $subject = "⚠️ WARNING: SSL Certificate Expires Soon - {$domain}";
            $priority = "WARNING - RENEW SOON";
        } else {
            $statusMessage = "<span style='color: #17a2b8; font-weight: bold;'>{$cert['days_until_expiry']} days until expiry</span>";
            $subject = "ℹ️ INFO: SSL Certificate Expiring - {$domain}";
            $priority = "INFO - PLAN TO RENEW";
        }

        // Build HTML email content
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .alert-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4099ff; }
                .certificate-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-required { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔒 SSL Certificate Alert</h1>
                    <p>{$platformName} - Security Monitoring System</p>
                </div>
                <div class='content'>
                    <h2>SSL Certificate Status Update</h2>
                    <p>This automated alert is sent to notify you about SSL certificate expiry status.</p>

                    <div class='alert-box'>
                        <h3 style='color: #4099ff; margin-top: 0;'>Certificate Details</h3>
                        <div class='certificate-details'>
                            <p><strong>Domain:</strong> {$domain}</p>
                            <p><strong>Issuer:</strong> " . htmlspecialchars($cert['issuer'] ?? 'Unknown') . "</p>
                            <p><strong>Status:</strong> {$statusMessage}</p>
                            <p><strong>Valid Until:</strong> " . ($cert['valid_to'] ? date('F j, Y \a\t g:i A', strtotime($cert['valid_to'])) : 'Unknown') . "</p>
                            <p><strong>Serial Number:</strong> " . htmlspecialchars($cert['serial_number'] ?? 'Unknown') . "</p>
                        </div>
                    </div>

                    <div class='action-required'>
                        <h4 style='color: #856404; margin-top: 0;'>⚠️ {$priority}</h4>
                        <p><strong>What you need to do:</strong></p>
                        <ol>
                            <li>Contact your SSL certificate provider (Let's Encrypt, DigiCert, etc.)</li>
                            <li>Initiate certificate renewal process</li>
                            <li>Update the certificate before expiry to avoid website downtime</li>
                            <li>Test the renewed certificate after installation</li>
                        </ol>
                    </div>

                    <p>This alert was generated automatically by the SSL Certificate Monitoring System.</p>

                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='https://app.mtravels.com/super_admin/ssl_monitoring.php' style='background: #4099ff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>View SSL Dashboard →</a>
                    </p>

                    <p>Best regards,<br><strong>{$platformName} Security Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated security alert from {$platformName}.<br>
                    Alert generated on: " . date('F j, Y \a\t g:i A') . "<br>
                    © 2024 {$platformName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";

        // Send email using existing email system
        require_once 'functions.php';
        $result = sendEmail(
            $superAdminEmail,
            $subject,
            $htmlBody,
            true, // is HTML
            'ssl_certificate_alert', // email type
            'Super Administrator', // recipient name
            null, // tenant ID (null for platform)
            [] // no attachments
        );

        if ($result) {
            error_log("SSL ALERT: Email sent successfully to {$superAdminEmail} for {$domain}");
        } else {
            error_log("SSL ALERT: Failed to send email alert for {$domain}");
        }
    }

    /**
     * Mark that alert was sent
     */
    private function markAlertSent($certId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ssl_certificates
                SET last_alert_sent = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$certId]);
        } catch (Exception $e) {
            error_log("SSL Monitor - Mark alert sent error: " . $e->getMessage());
        }
    }

    /**
     * Get alert thresholds
     */
    public function getAlertThresholds()
    {
        return $this->alertThresholds;
    }

    /**
     * Set custom alert thresholds
     */
    public function setAlertThresholds($thresholds)
    {
        $this->alertThresholds = array_merge($this->alertThresholds, $thresholds);
    }
}
?>