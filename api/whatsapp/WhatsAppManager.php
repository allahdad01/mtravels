<?php
/**
 * WhatsApp Automation API Manager
 * Handles tenant-based WhatsApp notifications for ticket bookings
 */

require_once __DIR__ . '/../../includes/db.php';

class WhatsAppManager {
    private $pdo;
    private $tenant_id;
    private $settings;
    
    public function __construct($tenant_id = null) {
        $this->pdo = $GLOBALS['pdo'];
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

        // WhatsApp can be used only when the add-on is active.
        $addonStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM communication_addons
            WHERE tenant_id = ? AND addon_type = 'whatsapp' AND status = 'active'
        ");
        $addonStmt->execute([$this->tenant_id]);
        $addon = $addonStmt->fetch(PDO::FETCH_ASSOC);
        if (intval($addon['cnt'] ?? 0) === 0) {
            throw new Exception("WhatsApp add-on is not active for this tenant");
        }

        $stmt = $this->pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->tenant_id]);
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->settings) {
            $this->createDefaultSettings();
            $this->settings = [
                'tenant_id' => $this->tenant_id,
                'provider' => 'meta',
                'api_token' => '',
                'phone_number_id' => '',
                'status' => 'inactive',
                'auto_notifications' => 0,
                'real_time_notifications' => 0
            ];
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
            if (($this->settings['status'] ?? 'inactive') !== 'active') {
                return ['success' => false, 'message' => 'WhatsApp settings are not active'];
            }

            // Get booking details based on type
            $booking = $this->getBookingDetails($type, $booking_id);
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            // Check if auto-notifications are enabled
            if (!$this->settings['auto_notifications']) {
                return ['success' => false, 'message' => 'Auto notifications disabled'];
            }
            
            // Resolve destination phone number with priority:
            // 1. Explicit override from caller
            // 2. Booking's own phone field (ticket phone, visa phone, family contact, hotel contact_no)
            // 3. Client's registered phone number (fallback)
            $client_phone = $additional_data['phone_number'] ?? null;
            if (!$client_phone) {
                $client_phone = $booking['booking_phone'] ?? null;
            }
            if (!$client_phone) {
                $client_phone = $booking['client_phone'] ?? null;
            }

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
                    SELECT va.*, va.phone as booking_phone, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM visa_applications va
                    LEFT JOIN clients c ON va.sold_to = c.id
                    LEFT JOIN suppliers s ON va.supplier = s.id
                    WHERE va.id = ? AND va.tenant_id = ?
                ");
                break;
                
            case 'umrah':
                $stmt = $this->pdo->prepare("
                    SELECT ub.*,
                           (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                               JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                               JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                               WHERE ubs2.booking_id = ub.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                               ORDER BY ff.id DESC LIMIT 1) AS flight_date,
                           (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                               JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                               JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                               WHERE ubs2.booking_id = ub.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                               ORDER BY ff.id DESC LIMIT 1) AS return_date,
                           f.contact as booking_phone, c.name as client_name, c.phone as client_phone,
                           f.head_of_family
                    FROM umrah_bookings ub
                    LEFT JOIN clients c ON ub.sold_to = c.id
                    LEFT JOIN families f ON ub.family_id = f.family_id
                    WHERE ub.booking_id = ? AND ub.tenant_id = ?
                ");
                break;

            case 'hotel':
                $stmt = $this->pdo->prepare("
                    SELECT hb.*, hb.contact_no as booking_phone, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM hotel_bookings hb
                    LEFT JOIN clients c ON hb.sold_to = c.id
                    LEFT JOIN suppliers s ON hb.supplier_id = s.id
                    WHERE hb.id = ? AND hb.tenant_id = ?
                ");
                break;
                
            case 'ticket':
                $stmt = $this->pdo->prepare("
                    SELECT tb.*, tb.phone as booking_phone, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM ticket_bookings tb
                    LEFT JOIN clients c ON tb.sold_to = c.id
                    LEFT JOIN suppliers s ON tb.supplier = s.id
                    WHERE tb.id = ? AND tb.tenant_id = ?
                ");
                break;

            case 'date_change_ticket':
                $stmt = $this->pdo->prepare("
                    SELECT dct.*, dct.phone as booking_phone, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name,
                           tb.departure_date as old_departure_date, tb.return_date as old_return_date
                    FROM date_change_tickets dct
                    LEFT JOIN clients c ON dct.sold_to = c.id
                    LEFT JOIN suppliers s ON dct.supplier = s.id
                    LEFT JOIN ticket_bookings tb ON dct.ticket_id = tb.id
                    WHERE dct.id = ? AND dct.tenant_id = ?
                ");
                break;

            case 'refund_ticket':
                $stmt = $this->pdo->prepare("
                    SELECT rt.*, rt.phone as booking_phone, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM refunded_tickets rt
                    LEFT JOIN clients c ON rt.sold_to = c.id
                    LEFT JOIN suppliers s ON rt.supplier = s.id
                    WHERE rt.id = ? AND rt.tenant_id = ?
                ");
                break;

            case 'ticket_reserve':
                $stmt = $this->pdo->prepare("
                    SELECT tres.*, tres.phone as booking_phone, c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM ticket_reservations tres
                    LEFT JOIN clients c ON tres.sold_to = c.id
                    LEFT JOIN suppliers s ON tres.supplier = s.id
                    WHERE tres.id = ? AND tres.tenant_id = ?
                ");
                break;

            case 'ticket_weight':
                $stmt = $this->pdo->prepare("
                    SELECT tw.*, tb.phone as booking_phone, tb.origin, tb.destination,
                           tb.passenger_name, tb.pnr, tb.currency, tb.sold_to, tb.supplier,
                           c.name as client_name, c.phone as client_phone,
                           s.name as supplier_name
                    FROM ticket_weights tw
                    LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id
                    LEFT JOIN clients c ON tb.sold_to = c.id
                    LEFT JOIN suppliers s ON tb.supplier = s.id
                    WHERE tw.id = ? AND tw.tenant_id = ?
                ");
                break;

            case 'jv_payment':
                $stmt = $this->pdo->prepare("
                    SELECT jp.*, c.name as client_name, c.phone as client_phone, c.phone as booking_phone,
                           s.name as supplier_name
                    FROM jv_payments jp
                    LEFT JOIN clients c ON jp.client_id = c.id
                    LEFT JOIN suppliers s ON jp.supplier_id = s.id
                    WHERE jp.id = ? AND jp.tenant_id = ?
                ");
                break;

            case 'client_fund':
                $stmt = $this->pdo->prepare("
                    SELECT ct.*, c.name as client_name, c.phone as client_phone, c.phone as booking_phone,
                           ct.amount as funded_amount, ct.currency as fund_currency
                    FROM client_transactions ct
                    LEFT JOIN clients c ON ct.client_id = c.id
                    WHERE ct.id = ? AND ct.tenant_id = ? AND ct.transaction_of = 'fund'
                ");
                break;

            default:
                throw new Exception("Invalid booking type: $type");
        }
        
        $stmt->execute([$booking_id, $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate formatted message based on booking type
     */
    private function generateMessage($type, $booking, $additional_data) {
        $agency = $this->getAgencySettings();
        $template_data = [
            'client_name' => $booking['client_name'],
            'booking_date' => date('Y-m-d'),
            'agency_name' => $agency['agency_name'] ?? 'Travel Agency',
            'contact_info' => ($agency['phone'] ?? '') . ' | ' . ($agency['email'] ?? '')
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
                // Build sector information
                $sector = $booking['origin'] . ' → ' . $booking['destination'];
                $trip_type = $booking['trip_type'] ?? 'One Way';
                $return_info = '';
                
                if ($trip_type === 'Round Trip' && isset($booking['return_date']) && !empty($booking['return_date'])) {
                    $return_sector = '';
                    if (isset($booking['return_origin']) && isset($booking['return_destination'])) {
                        $return_sector = $booking['return_origin'] . ' → ' . $booking['return_destination'];
                    } else {
                        $return_sector = $booking['destination'] . ' → ' . $booking['origin'];
                    }
                    $return_info = "\n🔄 Return: " . $booking['return_date'] . ' (' . $return_sector . ')';
                }
                
                $template_data = array_merge($template_data, [
                    'passenger_name' => $booking['passenger_name'],
                    'pnr' => $booking['pnr'],
                    'sector' => $sector,
                    'trip_type' => $trip_type,
                    'departure_date' => $booking['departure_date'],
                    'departure_time' => $booking['departure_time'] ?? 'N/A',
                    'destination' => $booking['destination'] ?? $booking['to_airport'] ?? 'N/A',
                    'origin' => $booking['origin'] ?? 'N/A',
                    'return_date' => $booking['return_date'] ?? 'N/A',
                    'return_sector' => $return_info ?: 'N/A',
                    'amount' => $booking['sold'] . ' ' . $booking['currency']
                ]);
                break;

            case 'date_change_ticket':
                // Determine which date(s) changed
                // old_departure_date and old_return_date come from ticket_bookings via JOIN
                $date_type = $booking['date_type'] ?? 'departure';
                $old_departure = $booking['old_departure_date'] ?? 'N/A';
                $new_departure = $booking['departure_date'] ?? 'N/A';
                $old_return = $booking['old_return_date'] ?? 'N/A';
                $new_return = $booking['return_date'] ?? 'N/A';
                
                $sector = ($booking['origin'] ?? 'N/A') . ' → ' . ($booking['destination'] ?? 'N/A');
                $return_sector = '';
                if (isset($booking['return_origin']) && isset($booking['return_destination'])) {
                    $return_sector = $booking['return_origin'] . ' → ' . $booking['return_destination'];
                } elseif (isset($booking['origin']) && isset($booking['destination'])) {
                    $return_sector = $booking['destination'] . ' → ' . $booking['origin'];
                }
                
                $template_data = array_merge($template_data, [
                    'passenger_name' => $booking['passenger_name'] ?? 'N/A',
                    'pnr' => $booking['pnr'] ?? 'N/A',
                    'sector' => $sector,
                    'date_type' => $date_type,
                    'old_departure_date' => $old_departure,
                    'new_departure_date' => $new_departure,
                    'old_return_date' => $old_return,
                    'new_return_date' => $new_return,
                    'return_sector' => $return_sector,
                    'change_fee' => ($booking['service_penalty'] ?? 0) . ' ' . ($booking['currency'] ?? 'USD')
                ]);
                break;

            case 'refund_ticket':
                // Build sector information
                $sector = ($booking['origin'] ?? 'N/A') . ' → ' . ($booking['destination'] ?? 'N/A');
                $refund_to_passenger = $booking['refund_to_passenger'] ?? 'N/A';
                
                $template_data = array_merge($template_data, [
                    'passenger_name' => $booking['passenger_name'] ?? $booking['name'] ?? 'N/A',
                    'pnr' => $booking['pnr'] ?? 'N/A',
                    'sector' => $sector,
                    'refund_amount' => $refund_to_passenger . ' ' . ($booking['currency'] ?? 'USD'),
                    'refund_reason' => $booking['remarks'] ?? 'N/A',
                    'refund_date' => isset($booking['created_at']) ? date('Y-m-d', strtotime($booking['created_at'])) : date('Y-m-d')
                ]);
                break;

            case 'ticket_reserve':
                $template_data = array_merge($template_data, [
                    'passenger_name' => $booking['passenger_name'] ?? $booking['name'] ?? 'N/A',
                    'pnr' => $booking['pnr'] ?? 'N/A',
                    'sector' => ($booking['origin'] ?? 'N/A') . ' → ' . ($booking['destination'] ?? 'N/A'),
                    'airline' => $booking['airline'] ?? 'N/A',
                    'departure_date' => $booking['departure_date'] ?? 'N/A',
                    'reservation_date' => $booking['issue_date'] ?? $booking['created_at'] ?? date('Y-m-d'),
                    'expiry_date' => $booking['expiry_date'] ?? 'N/A',
                    'reservation_amount' => ($booking['sold'] ?? 0) . ' ' . ($booking['currency'] ?? 'USD')
                ]);
                break;

            case 'ticket_weight':
                // origin, destination, passenger_name, pnr, currency come from ticket_bookings via JOIN
                $sector = ($booking['origin'] ?? 'N/A') . ' → ' . ($booking['destination'] ?? 'N/A');
                $weight = $booking['weight'] ?? 'N/A';
                $weight_unit = 'kg'; // default unit
                $excess_fee = ($booking['sold_price'] ?? 0) . ' ' . ($booking['currency'] ?? 'USD');
                
                $template_data = array_merge($template_data, [
                    'passenger_name' => $booking['passenger_name'] ?? 'N/A',
                    'pnr' => $booking['pnr'] ?? 'N/A',
                    'sector' => $sector,
                    'weight' => $weight,
                    'weight_unit' => $weight_unit,
                    'excess_fee' => $excess_fee
                ]);
                break;

            case 'jv_payment':
                $template_data = array_merge($template_data, [
                    'jv_name' => $booking['jv_name'] ?? 'N/A',
                    'supplier_name' => $booking['supplier_name'] ?? 'N/A',
                    'amount' => ($booking['total_amount'] ?? 0) . ' ' . ($booking['currency'] ?? 'USD'),
                    'receipt' => $booking['receipt'] ?? 'N/A'
                ]);
                break;

            case 'client_fund':
                $template_data = array_merge($template_data, [
                    'funded_amount' => ($booking['funded_amount'] ?? $booking['amount'] ?? 0) . ' ' . ($booking['fund_currency'] ?? $booking['currency'] ?? 'USD'),
                    'new_balance' => ($booking['balance'] ?? 'N/A') . ' ' . ($booking['fund_currency'] ?? $booking['currency'] ?? 'USD'),
                    'receipt' => $booking['receipt'] ?? 'N/A'
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
        
        // Process conditional blocks {{#key}}...{{/key}} first
        // If the key has a non-empty, non-'N/A' value, keep the content (with variables replaced)
        // Otherwise, remove the entire block
        $message = preg_replace_callback(
            '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function($matches) use ($data) {
                $key = $matches[1];
                $content = $matches[2];
                $value = $data[$key] ?? '';
                // Check if value is meaningful (not empty, not 'N/A')
                if ($value !== '' && $value !== 'N/A' && $value !== null) {
                    // Replace variables inside the conditional block
                    foreach ($data as $k => $v) {
                        $content = str_replace('{{' . $k . '}}', (string)$v, $content);
                    }
                    return $content;
                }
                return ''; // Remove the entire block
            },
            $message
        );
        
        // Replace remaining variables in template
        foreach ($data as $key => $value) {
            $message = str_replace('{{' . $key . '}}', (string)$value, $message);
        }
        
        // Clean up any leftover empty lines from removed conditionals
        $message = preg_replace("/\n{3,}/", "\n\n", $message);
        
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
🌍 Sector: {{sector}}
📅 Trip Type: {{trip_type}}
📅 Departure Date: {{departure_date}}
⏰ Departure Time: {{departure_time}}
{{#return_date}}🔄 Return Date: {{return_date}}{{/return_date}}
💰 Amount: {{amount}}

📅 Booking Date: {{booking_date}}

Have a safe journey!
📞 Contact: {{contact_info}}",

            'date_change_ticket' => "📅 *Flight Date Change Confirmation*

Hello {{client_name}},

Your flight date change has been processed:

👤 Passenger: {{passenger_name}}
🆔 PNR: {{pnr}}
🌍 Sector: {{sector}}
{{#date_type}}📅 Change Type: {{date_type}}{{/date_type}}
{{#old_departure_date}}📅 Old Departure Date: {{old_departure_date}}{{/old_departure_date}}
{{#new_departure_date}}📅 New Departure Date: {{new_departure_date}}{{/new_departure_date}}
{{#old_return_date}}🔄 Old Return Date: {{old_return_date}}{{/old_return_date}}
{{#new_return_date}}🔄 New Return Date: {{new_return_date}}{{/new_return_date}}
💰 Change Fee: {{change_fee}}

📅 Processed Date: {{booking_date}}

Thank you for choosing {{agency_name}}!
📞 Contact: {{contact_info}}",

            'refund_ticket' => "💸 *Ticket Refund Confirmation*

Hello {{client_name}},

Your ticket refund has been processed:

👤 Passenger: {{passenger_name}}
🆔 PNR: {{pnr}}
🌍 Sector: {{sector}}
💰 Refund Amount: {{refund_amount}}
� Reason: {{refund_reason}}
📅 Refund Date: {{refund_date}}

📅 Processed Date: {{booking_date}}

The refund will be processed to your original payment method.
📞 Contact: {{contact_info}}",

            'ticket_reserve' => "📋 *Ticket Reservation Confirmation*

Hello {{client_name}},

Your ticket reservation has been confirmed:

👤 Passenger: {{passenger_name}}
🆔 PNR: {{pnr}}
🌍 Sector: {{sector}}
✈️ Airline: {{airline}}
📅 Departure Date: {{departure_date}}
📅 Reservation Date: {{reservation_date}}
💰 Reservation Amount: {{reservation_amount}}

📅 Booking Date: {{booking_date}}

Please complete the payment before expiry to secure your ticket.
📞 Contact: {{contact_info}}",

            'ticket_weight' => "⚖️ *Excess Baggage Confirmation*

Hello {{client_name}},

Your excess baggage has been processed:

👤 Passenger: {{passenger_name}}
🆔 PNR: {{pnr}}
🌍 Sector: {{sector}}
⚖️ Weight: {{weight}} {{weight_unit}}
💰 Excess Fee: {{excess_fee}}

� Processed Date: {{booking_date}}

Safe travels with your baggage!
📞 Contact: {{contact_info}}",

            'jv_payment' => "💳 *JV Payment Confirmation*

Hello {{client_name}},

A JV payment has been processed for your account:

📋 Payment: {{jv_name}}
🏢 Supplier: {{supplier_name}}
💰 Amount: {{amount}}
🧾 Receipt: {{receipt}}

📅 Date: {{booking_date}}

Thank you for choosing {{agency_name}}!
📞 Contact: {{contact_info}}",

            'client_fund' => "💰 *Account Funding Confirmation*

Hello {{client_name}},

Your account has been funded successfully:

💰 Amount Credited: {{funded_amount}}
📊 New Balance: {{new_balance}}
🧾 Receipt: {{receipt}}

📅 Date: {{booking_date}}

Thank you for choosing {{agency_name}}!
📞 Contact: {{contact_info}}",

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
            // Native prepares (ATTR_EMULATE_PREPARES=false) bind LIMIT as string
            // unless typed as int, causing SQLSTATE[42000] near ''10''
            $stmt->bindValue(1, $this->tenant_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
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
            $provider = $this->getProvider();
            $booking = [];

            try {
                if (!empty($message_data['reference_id'])) {
                    $booking = $this->getBookingDetails(
                        $message_data['message_type'],
                        $message_data['reference_id']
                    ) ?: [];
                }
            } catch (Exception $e) {
                $booking = [];
            }

            if ($booking) {
                $agency = $this->getAgencySettings();
                $booking['agency_name'] = $agency['agency_name'] ?? 'Travel Agency';
                $booking['contact_info'] = ($agency['phone'] ?? '') . ' | ' . ($agency['email'] ?? '');
            }

            $result = $provider->sendMessage(
                $message_data['phone_number'],
                $message_data['message'],
                $booking,
                $message_data['message_type']
            );
            
            return [
                'success' => $result['success'],
                'provider_message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null,
                'via' => $result['via'] ?? 'text'
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
        $via = $result['via'] ?? 'text';
        
        $stmt = $this->pdo->prepare("
            UPDATE whatsapp_messages 
            SET status = ?, provider_message_id = ?, error_message = ?,
                sent_at = NOW(), sent_via = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $provider_message_id, $error, $via, $message_id]);
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
     * Get agency settings (name, phone, email) in a single query
     */
    private function getAgencySettings() {
        $stmt = $this->pdo->prepare("
            SELECT agency_name, phone, email FROM settings WHERE tenant_id = ? LIMIT 1
        ");
        $stmt->execute([$this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
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
    private $default_templates = [
        'ticket' => [
            'name' => 'ticket_booking_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'pnr', 'origin', 'destination', 'departure_date', '__agency__', '__contact__']
        ],
        'visa' => [
            'name' => 'visa_application_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'country', 'passport_number', 'visa_type', '__agency__', '__contact__']
        ],
        'hotel' => [
            'name' => 'hotel_booking_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'accommodation_details', 'check_in_date', 'check_out_date', '__agency__', '__contact__']
        ],
        'umrah' => [
            'name' => 'umrah_booking_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'name', 'flight_date', 'return_date', '__agency__', '__contact__']
        ],
        'date_change_ticket' => [
            'name' => 'ticket_date_change_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'pnr', 'old_departure_date', 'departure_date', '__agency__', '__contact__']
        ],
        'refund_ticket' => [
            'name' => 'ticket_refund_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'pnr', 'refund_to_passenger', 'remarks', '__agency__', '__contact__']
        ],
        'ticket_reserve' => [
            'name' => 'ticket_reservation_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'pnr', 'issue_date', 'departure_date', '__agency__', '__contact__']
        ],
        'ticket_weight' => [
            'name' => 'excess_baggage_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'pnr', 'weight', 'sold_price', '__agency__', '__contact__']
        ],
        'jv_payment' => [
            'name' => 'jv_payment_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'jv_name', 'total_amount', 'currency', '__agency__', '__contact__']
        ],
        'client_fund' => [
            'name' => 'client_fund_confirmation',
            'lang' => 'en',
            'fields' => ['client_name', 'amount', 'currency', 'receipt', '__agency__', '__contact__']
        ],
    ];
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->api_token = $settings['api_token'] ?? '';
        $this->phone_number_id = $settings['phone_number_id'] ?? '';
        $this->base_url = 'https://graph.facebook.com/v21.0';
    }
    
    /**
     * Send message via Meta WhatsApp Business API
     */
    public function sendMessage($phone_number, $message, $booking = [], $type = null) {
        try {
            if (empty($this->api_token) || empty($this->phone_number_id)) {
                throw new Exception("API token or phone number ID is missing");
            }
            
            $formatted_phone = $this->formatPhoneNumber($phone_number);

            $result = $this->sendTextMessage($formatted_phone, $message);

            if (
                !$result['success'] &&
                ($result['error_code'] ?? null) === 131047 &&
                $type &&
                isset($this->default_templates[$type])
            ) {
                error_log("WhatsApp 131047: falling back to template for type=$type");
                return $this->sendDefaultTemplate($formatted_phone, $type, $booking);
            }

            return $result;
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
        $default_country_code = $this->settings['default_country_code'] ?? '93';
        if (strlen($clean_phone) <= 10) {
            $clean_phone = $default_country_code . $clean_phone;
        }
        
        return $clean_phone;
    }

    /**
     * Send a free-form text message.
     */
    private function sendTextMessage($phone_number, $message) {
        $url = $this->base_url . '/' . $this->phone_number_id . '/messages';
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $phone_number,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];

        try {
            $response = $this->makeHttpRequest('POST', $url, $data);

            if ($response && isset($response['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $response['messages'][0]['id'],
                    'status' => 'sent',
                    'via' => 'text'
                ];
            }

            throw new Exception("Unexpected API response");
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $this->parseErrorCode($e->getMessage()),
                'via' => 'text'
            ];
        }
    }

    /**
     * Send one of the built-in approved Meta templates.
     */
    private function sendDefaultTemplate($phone_number, $type, $booking) {
        if (!isset($this->default_templates[$type])) {
            return [
                'success' => false,
                'error' => "No default template for type: $type"
            ];
        }

        $template = $this->default_templates[$type];
        $parameters = [];

        foreach ($template['fields'] as $field) {
            if ($field === '__agency__') {
                $text = (string)($booking['agency_name'] ?? 'Travel Agency');
            } elseif ($field === '__contact__') {
                $text = (string)($booking['contact_info'] ?? '');
            } else {
                $text = (string)($booking[$field] ?? 'N/A');
            }

            $parameters[] = [
                'type' => 'text',
                'text' => $text
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $phone_number,
            'type' => 'template',
            'template' => [
                'name' => $template['name'],
                'language' => ['code' => $template['lang']],
                'components' => [[
                    'type' => 'body',
                    'parameters' => $parameters
                ]]
            ]
        ];

        try {
            $url = $this->base_url . '/' . $this->phone_number_id . '/messages';
            $response = $this->makeHttpRequest('POST', $url, $data);

            if ($response && isset($response['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $response['messages'][0]['id'],
                    'via' => 'template'
                ];
            }

            throw new Exception("Template send failed");
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'via' => 'template'
            ];
        }
    }

    /**
     * Extract Meta error codes from exception messages.
     */
    private function parseErrorCode($error_message) {
        if (preg_match('/"code"\s*:\s*(\d+)/', $error_message, $matches)) {
            return (int)$matches[1];
        }

        return null;
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
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("cURL Error: $curl_error");
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            return json_decode($response, true);
        } else {
            error_log("WhatsApp API HTTP $http_code: $response");
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
    
    public function sendMessage($phone_number, $message, $booking = [], $type = null) {
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
