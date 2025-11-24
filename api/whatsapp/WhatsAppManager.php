<?php
/**
 * WhatsApp Automation API Manager
 * Handles tenant-based WhatsApp notifications for ticket bookings
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/conn.php';

class WhatsAppManager {
    private $pdo;
    private $conn;
    private $tenant_id;
    private $settings;
    
    public function __construct($tenant_id = null) {
        $this->pdo = $GLOBALS['pdo'];
        $this->conn = $GLOBALS['conection_db'];
        $this->tenant_id = $tenant_id ?: $_SESSION['tenant_id'] ?? null;
        $this->loadSettings();
    }
    
    /**
     * Load WhatsApp settings for the tenant
     */
    private function loadSettings() {
        if (!$this->tenant_id) {
            throw new Exception("Tenant ID is required");
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$this->tenant_id]);
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->settings) {
            // Create default settings if none exist
            $this->createDefaultSettings();
            $this->loadSettings();
        }
    }
    
    /**
     * Create default WhatsApp settings for a tenant
     */
    private function createDefaultSettings() {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_settings (
                tenant_id, provider, api_token, phone_number_id, 
                webhook_verify_token, status, auto_notifications, 
                created_at, updated_at
            ) VALUES (?, 'meta', '', '', '', 'inactive', 1, NOW(), NOW())
        ");
        $stmt->execute([$this->tenant_id]);
    }
    
    /**
     * Send WhatsApp notification for new booking
     * @param string $type - booking type (visa, umrah, hotel)
     * @param int $booking_id - booking reference ID
     * @param array $additional_data - additional notification data
     * @return array response with success status
     */
    public function sendBookingNotification($type, $booking_id, $additional_data = []) {
        try {
            // Get booking details based on type
            $booking = $this->getBookingDetails($type, $booking_id);
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            // Check if auto-notifications are enabled
            if (!$this->settings['auto_notifications']) {
                return ['success' => false, 'message' => 'Auto notifications disabled'];
            }
            
            // Get client phone number
            $client_phone = $this->getClientPhone($booking['sold_to']);
            if (!$client_phone) {
                throw new Exception("Client phone number not found");
            }
            
            // Generate message content
            $message = $this->generateMessage($type, $booking, $additional_data);
            
            // Queue the message for sending
            $message_id = $this->queueMessage($client_phone, $message, $type, $booking_id);
            
            // Send immediately if configured for real-time
            if ($this->settings['real_time_notifications']) {
                $this->processQueue();
            }
            
            return [
                'success' => true, 
                'message_id' => $message_id,
                'message' => 'Notification queued successfully'
            ];
            
        } catch (Exception $e) {
            error_log("WhatsApp Notification Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get booking details based on type
     */
    private function getBookingDetails($type, $booking_id) {
        switch ($type) {
            case 'visa':
                $stmt = $this->pdo->prepare("
                    SELECT va.*, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM visa_applications va
                    LEFT JOIN clients c ON va.sold_to = c.id
                    LEFT JOIN suppliers s ON va.supplier = s.id
                    WHERE va.id = ? AND va.tenant_id = ?
                ");
                break;
                
            case 'umrah':
                $stmt = $this->pdo->prepare("
                    SELECT ub.*, c.name as client_name, c.phone as client_phone,
                           f.head_of_family
                    FROM umrah_bookings ub
                    LEFT JOIN clients c ON ub.sold_to = c.id
                    LEFT JOIN families f ON ub.family_id = f.family_id
                    WHERE ub.id = ? AND ub.tenant_id = ?
                ");
                break;
                
            case 'hotel':
                $stmt = $this->pdo->prepare("
                    SELECT hb.*, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM hotel_bookings hb
                    LEFT JOIN clients c ON hb.sold_to = c.id
                    LEFT JOIN suppliers s ON hb.supplier_id = s.id
                    WHERE hb.id = ? AND hb.tenant_id = ?
                ");
                break;
                
            case 'ticket':
                $stmt = $this->pdo->prepare("
                    SELECT tb.*, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM ticket_bookings tb
                    LEFT JOIN clients c ON tb.sold_to = c.id
                    LEFT JOIN suppliers s ON tb.supplier_id = s.id
                    WHERE tb.id = ? AND tb.tenant_id = ?
                ");
                break;
                
            default:
                throw new Exception("Invalid booking type: $type");
        }
        
        $stmt->execute([$booking_id, $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get client phone number
     */
    private function getClientPhone($client_id) {
        $stmt = $this->pdo->prepare("SELECT phone FROM clients WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$client_id, $this->tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['phone'] ?? null;
    }
    
    /**
     * Generate formatted message based on booking type
     */
    private function generateMessage($type, $booking, $additional_data) {
        $template_data = [
            'client_name' => $booking['client_name'],
            'booking_date' => date('Y-m-d'),
            'agency_name' => $this->getAgencyName(),
            'contact_info' => $this->getContactInfo()
        ];
        
        // Add type-specific data
        switch ($type) {
            case 'visa':
                $template_data = array_merge($template_data, [
                    'applicant_name' => $booking['applicant_name'],
                    'passport_number' => $booking['passport_number'],
                    'country' => $booking['country'],
                    'visa_type' => $booking['visa_type'],
                    'amount' => $booking['sold'] . ' ' . $booking['currency']
                ]);
                break;
                
            case 'umrah':
                $template_data = array_merge($template_data, [
                    'member_name' => $booking['name'],
                    'passport_number' => $booking['passport_number'],
                    'flight_date' => $booking['flight_date'],
                    'return_date' => $booking['return_date'],
                    'room_type' => $booking['room_type'],
                    'duration' => $booking['duration'],
                    'amount' => $booking['sold_price'] . ' USD' // Default currency, should be dynamic
                ]);
                break;
                
            case 'hotel':
                $template_data = array_merge($template_data, [
                    'guest_name' => $booking['first_name'] . ' ' . $booking['last_name'],
                    'hotel_name' => $booking['accommodation_details'],
                    'check_in' => $booking['check_in_date'],
                    'check_out' => $booking['check_out_date'],
                    'amount' => $booking['sold_amount'] . ' ' . $booking['currency']
                ]);
                break;
                
            case 'ticket':
                $template_data = array_merge($template_data, [
                    'passenger_name' => $booking['passenger_name'],
                    'pnr' => $booking['pnr'],
                    'flight_number' => $booking['flight_number'] ?? 'N/A',
                    'departure_date' => $booking['departure_date'],
                    'departure_time' => $booking['departure_time'] ?? 'N/A',
                    'destination' => $booking['destination'] ?? $booking['to_airport'],
                    'amount' => $booking['sold'] . ' ' . $booking['currency']
                ]);
                break;
        }
        
        // Get template from database
        return $this->getMessageTemplate($type, $template_data);
    }
    
    /**
     * Get message template and replace variables
     */
    private function getMessageTemplate($type, $data) {
        // Check database for custom template first
        $stmt = $this->pdo->prepare("
            SELECT message_template FROM whatsapp_templates 
            WHERE tenant_id = ? AND template_type = ? AND status = 'active'
        ");
        $stmt->execute([$this->tenant_id, $type]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template && $template['message_template']) {
            $message = $template['message_template'];
        } else {
            // Use default templates
            $message = $this->getDefaultTemplate($type);
        }
        
        // Replace variables in template
        foreach ($data as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Get default message templates
     */
    private function getDefaultTemplate($type) {
        $templates = [
            'visa' => "🛂 *New Visa Application*
            
Hello {{client_name}},

Your visa application has been successfully processed:

👤 Applicant: {{applicant_name}}
📋 Passport: {{passport_number}}
🌍 Country: {{country}}
📄 Type: {{visa_type}}
💰 Amount: {{amount}}

📅 Date: {{booking_date}}

Thank you for choosing {{agency_name}}!
📞 Contact: {{contact_info}}",

            'umrah' => "🕌 *Umrah Booking Confirmation*
            
Assalamu Alaikum {{client_name}},

Your Umrah booking has been confirmed:

👤 Pilgrim: {{member_name}}
🆔 Passport: {{passport_number}}
✈️ Departure: {{flight_date}}
🔄 Return: {{return_date}}
🏠 Room: {{room_type}}
📅 Duration: {{duration}}
💰 Amount: {{amount}}

📅 Booking Date: {{booking_date}}

May Allah accept your Umrah!
📞 Contact: {{contact_info}}",

            'hotel' => "🏨 *Hotel Booking Confirmation*
            
Hello {{client_name}},

Your hotel booking is confirmed:

👤 Guest: {{guest_name}}
🏨 Hotel: {{hotel_name}}
📅 Check-in: {{check_in}}
📅 Check-out: {{check_out}}
💰 Amount: {{amount}}

📅 Booking Date: {{booking_date}}

Thank you for choosing {{agency_name}}!
📞 Contact: {{contact_info}}",

            'ticket' => "✈️ *Flight Ticket Confirmation*
            
Hello {{client_name}},

Your flight ticket has been confirmed:

👤 Passenger: {{passenger_name}}
🆔 PNR: {{pnr}}
✈️ Flight: {{flight_number}}
📅 Date: {{departure_date}}
⏰ Time: {{departure_time}}
🌍 Destination: {{destination}}
💰 Amount: {{amount}}

📅 Booking Date: {{booking_date}}

Have a safe journey!
📞 Contact: {{contact_info}}"
        ];
        
        return $templates[$type] ?? "New booking notification for {{client_name}}";
    }
    
    /**
     * Queue message for sending
     */
    private function queueMessage($phone_number, $message, $type, $reference_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_messages (
                tenant_id, phone_number, message, message_type, 
                reference_id, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $this->tenant_id, 
            $phone_number, 
            $message, 
            $type, 
            $reference_id
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Process message queue
     */
    public function processQueue($limit = 10) {
        try {
            // Get pending messages
            $stmt = $this->pdo->prepare("
                SELECT * FROM whatsapp_messages 
                WHERE tenant_id = ? AND status = 'pending' 
                ORDER BY created_at ASC LIMIT ?
            ");
            $stmt->execute([$this->tenant_id, $limit]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($messages as $message) {
                $result = $this->sendMessage($message);
                $results[] = $result;
                
                // Update message status
                $this->updateMessageStatus($message['id'], $result);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Queue Processing Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send individual message via WhatsApp provider
     */
    private function sendMessage($message_data) {
        try {
            // For demo purposes, we'll use a mock provider
            // In production, integrate with actual WhatsApp Business API
            
            $provider = $this->getProvider();
            $result = $provider->sendMessage(
                $message_data['phone_number'],
                $message_data['message']
            );
            
            return [
                'success' => $result['success'],
                'provider_message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update message status in database
     */
    private function updateMessageStatus($message_id, $result) {
        $status = $result['success'] ? 'sent' : 'failed';
        $provider_message_id = $result['provider_message_id'] ?? null;
        $error = $result['error'] ?? null;
        
        $stmt = $this->pdo->prepare("
            UPDATE whatsapp_messages 
            SET status = ?, provider_message_id = ?, error_message = ?, 
                sent_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $provider_message_id, $error, $message_id]);
    }
    
    /**
     * Get WhatsApp provider instance
     */
    private function getProvider() {
        $provider_type = $this->settings['provider'] ?? 'twilio';
        
        switch ($provider_type) {
            case 'meta':
                return new MetaWhatsAppProvider($this->settings);
            case 'twilio':
                // Would return Twilio provider here
                return new MockWhatsAppProvider($this->settings);
            case 'messagebird':
                // Would return MessageBird provider here
                return new MockWhatsAppProvider($this->settings);
            case '360dialog':
                // Would return 360Dialog provider here
                return new MockWhatsAppProvider($this->settings);
            default:
                return new MockWhatsAppProvider($this->settings);
        }
    }
    
    /**
     * Get agency name for templates
     */
    private function getAgencyName() {
        $stmt = $this->pdo->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
        $stmt->execute([$this->tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['agency_name'] ?? 'Travel Agency';
    }
    
    /**
     * Get contact info for templates
     */
    private function getContactInfo() {
        $stmt = $this->pdo->prepare("SELECT phone, email FROM settings WHERE tenant_id = ?");
        $stmt->execute([$this->tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['phone'] . ' | ' . $result['email'];
    }
    
    /**
     * Update WhatsApp settings
     */
    public function updateSettings($settings) {
        $allowed_fields = [
            'provider', 'api_token', 'phone_number_id', 'webhook_verify_token',
            'auto_notifications', 'real_time_notifications', 'status'
        ];
        
        $update_fields = [];
        $values = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($settings[$field])) {
                $update_fields[] = "$field = ?";
                $values[] = $settings[$field];
            }
        }
        
        if (empty($update_fields)) {
            throw new Exception("No valid settings provided");
        }
        
        $values[] = $this->tenant_id;
        
        $sql = "UPDATE whatsapp_settings SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE tenant_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        // Reload settings
        $this->loadSettings();
        
        return true;
    }
    
    /**
     * Get message delivery status
     */
    public function getMessageStatus($message_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM whatsapp_messages 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$message_id, $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get message history for a booking
     */
    public function getBookingMessages($type, $booking_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM whatsapp_messages 
            WHERE tenant_id = ? AND message_type = ? AND reference_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->tenant_id, $type, $booking_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Meta WhatsApp Business API Provider
 * Direct integration with Meta's WhatsApp Business API
 */
class MetaWhatsAppProvider {
    private $settings;
    private $api_token;
    private $phone_number_id;
    private $base_url;
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->api_token = $settings['api_token'] ?? '';
        $this->phone_number_id = $settings['phone_number_id'] ?? '';
        $this->base_url = 'https://graph.facebook.com/v18.0';
    }
    
    /**
     * Send message via Meta WhatsApp Business API
     */
    public function sendMessage($phone_number, $message) {
        try {
            // Validate inputs
            if (empty($this->api_token) || empty($this->phone_number_id)) {
                throw new Exception("API token or phone number ID is missing");
            }
            
            // Format phone number (ensure it starts with country code)
            $formatted_phone = $this->formatPhoneNumber($phone_number);
            
            // Prepare API request
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $formatted_phone,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ];
            
            $url = $this->base_url . '/' . $this->phone_number_id . '/messages';
            
            $response = $this->makeHttpRequest('POST', $url, $data);
            
            if ($response && isset($response['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $response['messages'][0]['id'],
                    'status' => 'sent'
                ];
            } else {
                throw new Exception("Failed to send message via Meta API");
            }
            
        } catch (Exception $e) {
            error_log("Meta WhatsApp API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format phone number for Meta API
     */
    private function formatPhoneNumber($phone_number) {
        // Remove all non-digit characters
        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // If phone number doesn't start with country code, assume it's missing
        // You'll need to configure the default country code in settings
        $default_country_code = $this->settings['default_country_code'] ?? '1'; // Default to US
        if (strlen($clean_phone) <= 10) {
            $clean_phone = $default_country_code . $clean_phone;
        }
        
        return $clean_phone;
    }
    
    /**
     * Make HTTP request with proper headers
     */
    private function makeHttpRequest($method, $url, $data = []) {
        $headers = [
            'Authorization: Bearer ' . $this->api_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return json_decode($response, true);
        } else {
            throw new Exception("HTTP Error: $http_code - $response");
        }
    }
    
    /**
     * Handle webhook verification
     */
    public function verifyWebhook($verify_token, $challenge) {
        // Compare with stored verify token
        if ($verify_token === $this->settings['webhook_verify_token']) {
            return $challenge;
        }
        return false;
    }
    
    /**
     * Process incoming webhook data
     */
    public function processWebhook($webhook_data) {
        try {
            if (isset($webhook_data['entry'])) {
                $results = [];
                foreach ($webhook_data['entry'] as $entry) {
                    if (isset($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            if (isset($change['field']) && $change['field'] === 'messages') {
                                $results[] = $this->processMessageChange($change);
                            }
                        }
                    }
                }
                return $results;
            }
            return [];
        } catch (Exception $e) {
            error_log("Webhook processing error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Process individual message changes
     */
    private function processMessageChange($change) {
        if (isset($change['value']['messages'])) {
            foreach ($change['value']['messages'] as $message) {
                $this->logIncomingMessage($message);
            }
        }
        return ['status' => 'processed'];
    }
    
    /**
     * Log incoming messages for debugging
     */
    private function logIncomingMessage($message) {
        error_log("Incoming WhatsApp Message: " . json_encode($message));
    }
}

/**
 * Mock WhatsApp Provider for demo purposes
 */
class MockWhatsAppProvider {
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
    }
    
    public function sendMessage($phone_number, $message) {
        // Mock implementation - always succeeds in demo
        error_log("Mock WhatsApp Message to $phone_number: " . substr($message, 0, 100) . "...");
        
        return [
            'success' => true,
            'message_id' => 'mock_' . uniqid(),
            'status' => 'sent'
        ];
    }
}

// Usage example:
// $whatsapp = new WhatsAppManager($tenant_id);
// $result = $whatsapp->sendBookingNotification('visa', $visa_id);
// $result = $whatsapp->sendBookingNotification('umrah', $umrah_id);
// $result = $whatsapp->sendBookingNotification('hotel', $hotel_id);
?>