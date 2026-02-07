<?php
/**
 * WhatsApp API Endpoints
 * RESTful API for sending ticket notifications via WhatsApp
 */

header('Content-Type: application/json');
session_start();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/db.php';

// CSRF Protection for POST/PUT/DELETE requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE']) && !verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['error' => 'Security validation failed. Please try again.']);
    exit;
}
require_once '../../includes/conn.php';
require_once 'WhatsAppManager.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

class WhatsAppAPI {
    private $whatsappManager;
    
    public function __construct() {
        try {
            // Verify user authentication
            if (!isset($_SESSION['user_id'])) {
                throw new Exception("Authentication required");
            }
            
            $tenant_id = $_SESSION['tenant_id'] ?? null;
            $this->whatsappManager = new WhatsAppManager($tenant_id);
            
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 401);
        }
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getPath();
        
        try {
            switch ($method) {
                case 'POST':
                    $this->handlePostRequest($path);
                    break;
                case 'GET':
                    $this->handleGetRequest($path);
                    break;
                case 'PUT':
                    $this->handlePutRequest($path);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($path);
                    break;
                default:
                    throw new Exception("Method not allowed", 405);
            }
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    /**
     * Parse request path
     */
    private function getPath() {
        $path = $_GET['path'] ?? '';
        return trim($path, '/');
    }
    
    /**
     * Handle POST requests
     */
    private function handlePostRequest($path) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($path) {
            case 'send':
                $this->sendNotification($data);
                break;
            case 'send-test':
                $this->sendTestMessage($data);
                break;
            case 'bulk-send':
                $this->bulkSendNotifications($data);
                break;
            case 'schedule':
                $this->scheduleMessage($data);
                break;
            default:
                throw new Exception("Endpoint not found", 404);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGetRequest($path) {
        switch ($path) {
            case 'settings':
                $this->getSettings();
                break;
            case 'messages':
                $this->getMessages();
                break;
            case 'message-status':
                $this->getMessageStatus();
                break;
            case 'templates':
                $this->getTemplates();
                break;
            case 'analytics':
                $this->getAnalytics();
                break;
            case 'queue-status':
                $this->getQueueStatus();
                break;
            default:
                throw new Exception("Endpoint not found", 404);
        }
    }
    
    /**
     * Handle PUT requests
     */
    private function handlePutRequest($path) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($path) {
            case 'settings':
                $this->updateSettings($data);
                break;
            case 'template':
                $this->updateTemplate($data);
                break;
            default:
                throw new Exception("Endpoint not found", 404);
        }
    }
    
    /**
     * Handle DELETE requests
     */
    private function handleDeleteRequest($path) {
        switch ($path) {
            case 'message':
                $this->deleteMessage();
                break;
            default:
                throw new Exception("Endpoint not found", 404);
        }
    }
    
    /**
     * Send individual notification
     */
    private function sendNotification($data) {
        // Validate required fields
        $required = ['type', 'booking_id'];
        $this->validateRequired($data, $required);
        
        $type = $data['type'];
        $booking_id = $data['booking_id'];
        $additional_data = $data['additional_data'] ?? [];
        
        // Send notification
        $result = $this->whatsappManager->sendBookingNotification($type, $booking_id, $additional_data);
        
        $this->successResponse([
            'notification' => $result,
            'message_id' => $result['message_id'] ?? null
        ]);
    }
    
    /**
     * Send test message
     */
    private function sendTestMessage($data) {
        // Validate phone number
        $phone_number = $data['phone_number'] ?? '';
        if (empty($phone_number)) {
            throw new Exception("Phone number is required");
        }
        
        $message = $data['message'] ?? 'Test message from WhatsApp API';
        $message_type = $data['message_type'] ?? 'test';
        
        // Queue test message
        $message_id = $this->whatsappManager->queueMessage($phone_number, $message, $message_type, 0);
        
        $this->successResponse([
            'message_id' => $message_id,
            'status' => 'queued'
        ]);
    }
    
    /**
     * Bulk send notifications
     */
    private function bulkSendNotifications($data) {
        $bookings = $data['bookings'] ?? [];
        if (empty($bookings)) {
            throw new Exception("Bookings array is required");
        }
        
        $results = [];
        foreach ($bookings as $booking) {
            $result = $this->whatsappManager->sendBookingNotification(
                $booking['type'], 
                $booking['booking_id'], 
                $booking['additional_data'] ?? []
            );
            $results[] = $result;
        }
        
        $this->successResponse([
            'bulk_results' => $results,
            'total_sent' => count($results)
        ]);
    }
    
    /**
     * Schedule message
     */
    private function scheduleMessage($data) {
        $required = ['type', 'booking_id', 'scheduled_at'];
        $this->validateRequired($data, $required);
        
        // This would require additional implementation in WhatsAppManager
        // For now, return success with placeholder
        $this->successResponse([
            'status' => 'scheduled',
            'scheduled_for' => $data['scheduled_at']
        ]);
    }
    
    /**
     * Get WhatsApp settings
     */
    private function getSettings() {
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT * FROM whatsapp_settings WHERE tenant_id = ?
        ");
        $stmt->execute([$_SESSION['tenant_id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Remove sensitive information
        unset($settings['api_token']);
        
        $this->successResponse(['settings' => $settings]);
    }
    
    /**
     * Update WhatsApp settings
     */
    private function updateSettings($data) {
        $this->whatsappManager->updateSettings($data);
        $this->successResponse(['status' => 'updated']);
    }
    
    /**
     * Get messages
     */
    private function getMessages() {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $status = $_GET['status'] ?? '';
        $type = $_GET['type'] ?? '';
        
        // Validate and sanitize pagination parameters
        // Ensure page is at least 1
        if ($page < 1) $page = 1;
        
        // Limit maximum results per page to prevent abuse
        if ($limit < 1) $limit = 50;
        if ($limit > 1000) $limit = 1000;
        
        $offset = ($page - 1) * $limit;
        
        $where_conditions = ["tenant_id = ?"];
        $params = [$_SESSION['tenant_id']];
        
        if (!empty($status)) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
        }
        
        if (!empty($type)) {
            $where_conditions[] = "message_type = ?";
            $params[] = $type;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_stmt = $GLOBALS['pdo']->prepare("
            SELECT COUNT(*) as total FROM whatsapp_messages WHERE $where_clause
        ");
        $count_stmt->execute($params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get messages - use parameterized LIMIT and OFFSET
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT * FROM whatsapp_messages 
            WHERE $where_clause 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        
        // Bind pagination parameters
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Bind WHERE clause parameters
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param);
        }
        
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->successResponse([
            'messages' => $messages,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => $total,
                'items_per_page' => $limit
            ]
        ]);
    }
    
    /**
     * Get message status
     */
    private function getMessageStatus() {
        $message_id = $_GET['message_id'] ?? '';
        if (empty($message_id)) {
            throw new Exception("Message ID is required");
        }
        
        $status = $this->whatsappManager->getMessageStatus($message_id);
        if (!$status) {
            throw new Exception("Message not found", 404);
        }
        
        $this->successResponse(['status' => $status]);
    }
    
    /**
     * Get templates
     */
    private function getTemplates() {
        $type = $_GET['type'] ?? '';
        $language = $_GET['language'] ?? 'en';
        
        $where_conditions = ["tenant_id = ?", "status = 'active'"];
        $params = [$_SESSION['tenant_id']];
        
        if (!empty($type)) {
            $where_conditions[] = "template_type = ?";
            $params[] = $type;
        }
        
        if (!empty($language)) {
            $where_conditions[] = "language = ?";
            $params[] = $language;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT * FROM whatsapp_templates 
            WHERE $where_clause 
            ORDER BY template_type, language
        ");
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->successResponse(['templates' => $templates]);
    }
    
    /**
     * Update template
     */
    private function updateTemplate($data) {
        $required = ['template_type', 'language', 'message_template'];
        $this->validateRequired($data, $required);
        
        $stmt = $GLOBALS['pdo']->prepare("
            INSERT INTO whatsapp_templates (
                tenant_id, template_type, language, message_template, 
                created_by, status
            ) VALUES (?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE
                message_template = VALUES(message_template),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $_SESSION['tenant_id'],
            $data['template_type'],
            $data['language'],
            $data['message_template'],
            $_SESSION['user_id']
        ]);
        
        $this->successResponse(['status' => 'template_updated']);
    }
    
    /**
     * Get analytics
     */
    private function getAnalytics() {
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $type = $_GET['type'] ?? '';
        
        $where_conditions = [
            "tenant_id = ?",
            "date BETWEEN ? AND ?"
        ];
        $params = [$_SESSION['tenant_id'], $start_date, $end_date];
        
        if (!empty($type)) {
            $where_conditions[] = "message_type = ?";
            $params[] = $type;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT * FROM whatsapp_analytics 
            WHERE $where_clause 
            ORDER BY date DESC
        ");
        $stmt->execute($params);
        $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->successResponse(['analytics' => $analytics]);
    }
    
    /**
     * Get queue status
     */
    private function getQueueStatus() {
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour
            FROM whatsapp_messages 
            WHERE tenant_id = ?
            GROUP BY status
        ");
        $stmt->execute([$_SESSION['tenant_id']]);
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->successResponse(['queue_status' => $statuses]);
    }
    
    /**
     * Delete message
     */
    private function deleteMessage() {
        $message_id = $_GET['message_id'] ?? '';
        if (empty($message_id)) {
            throw new Exception("Message ID is required");
        }
        
        $stmt = $GLOBALS['pdo']->prepare("
            DELETE FROM whatsapp_messages 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$message_id, $_SESSION['tenant_id']]);
        
        $this->successResponse(['status' => 'deleted']);
    }
    
    /**
     * Validate required fields
     */
    private function validateRequired($data, $required) {
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
    }
    
    /**
     * Success response
     */
    private function successResponse($data) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    /**
     * Error response
     */
    private function errorResponse($message, $code = 500) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
}

// Process webhook from WhatsApp provider
if (isset($_GET['webhook'])) {
    handleWebhook();
    exit;
}

/**
 * Verify webhook signature from WhatsApp provider
 * @param string $payload Raw request body
 * @param string $signature Signature from X-Hub-Signature header
 * @param string $secret Webhook secret from WhatsApp
 * @return bool True if signature is valid
 */
function verifyWebhookSignature($payload, $signature, $secret) {
    // Expected format: sha256=<hash>
    if (strpos($signature, 'sha256=') !== 0) {
        return false;
    }
    
    $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($signature, $expected_signature);
}

/**
 * Identify tenant from webhook data
 * Webhook should contain phone number that maps to a tenant
 * @param array $data Webhook data
 * @return int|null Tenant ID or null if not found
 */
function identifyTenantFromWebhook($data) {
    global $pdo;
    
    // Try to identify from phone number or webhook metadata
    $phone = $data['from'] ?? $data['phone'] ?? null;
    
    if (!$phone) {
        error_log("Webhook: Could not identify tenant - no phone number");
        return null;
    }
    
    // Query WhatsApp settings to find matching tenant
    try {
        $stmt = $pdo->prepare("SELECT tenant_id FROM whatsapp_settings WHERE phone_number = ? OR business_phone = ? LIMIT 1");
        $stmt->execute([$phone, $phone]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return (int)$result['tenant_id'];
        }
    } catch (Exception $e) {
        error_log("Webhook error identifying tenant: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Handle incoming webhook from WhatsApp provider
 * @throws Exception if webhook verification fails
 */
function handleWebhook() {
    try {
        // 1. Get webhook secret from settings or environment
        $webhook_secret = getenv('WHATSAPP_WEBHOOK_SECRET');
        if (!$webhook_secret) {
            // If no environment variable, try to get from database (first active tenant)
            global $pdo;
            $stmt = $pdo->prepare("SELECT webhook_secret FROM whatsapp_settings WHERE status = 'active' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $webhook_secret = $result['webhook_secret'] ?? null;
        }
        
        if (!$webhook_secret) {
            throw new Exception("Webhook secret not configured");
        }
        
        // 2. Verify webhook signature
        $raw_payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
        
        if (empty($signature) || !verifyWebhookSignature($raw_payload, $signature, $webhook_secret)) {
            error_log("Webhook verification failed - Invalid signature");
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        // 3. Parse and validate JSON payload
        $data = json_decode($raw_payload, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON payload");
        }
        
        // 4. Identify tenant from webhook data
        $tenant_id = identifyTenantFromWebhook($data);
        if (!$tenant_id) {
            throw new Exception("Could not identify tenant from webhook");
        }
        
        error_log("Webhook received for tenant $tenant_id: " . json_encode($data));
        
        // 5. Process webhook based on type
        if (isset($data['type'])) {
            switch ($data['type']) {
                case 'message_status':
                    updateMessageStatus($data, $tenant_id);
                    break;
                case 'received_message':
                    handleReceivedMessage($data, $tenant_id);
                    break;
                default:
                    logWebhook($data, $tenant_id);
            }
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'processed']);
        
    } catch (Exception $e) {
        error_log("Webhook Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Update message status from webhook
 * @param array $data Webhook data
 * @param int $tenant_id Tenant ID (verified from webhook)
 */
function updateMessageStatus($data, $tenant_id) {
    global $pdo;
    
    $message_id = $data['message_id'] ?? '';
    $status = $data['status'] ?? '';
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    if (!$message_id || !$status) {
        return;
    }
    
    // Update message status - only for messages belonging to this tenant
    $update_field = '';
    switch ($status) {
        case 'sent':
            $update_field = 'sent_at';
            break;
        case 'delivered':
            $update_field = 'delivered_at';
            break;
        case 'failed':
            $update_field = 'failed_at';
            break;
    }
    
    if ($update_field) {
        $stmt = $pdo->prepare("
            UPDATE whatsapp_messages 
            SET status = ?, $update_field = ? 
            WHERE provider_message_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$status, $timestamp, $message_id, $tenant_id]);
    }
    
    // Log delivery status - ensure we're logging for the correct tenant
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_delivery_status (
            message_id, provider_message_id, status, status_message, 
            delivery_timestamp, raw_response
        ) SELECT id, provider_message_id, ?, ?, ?, ?
        FROM whatsapp_messages WHERE provider_message_id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $status, 
        $data['message'] ?? '', 
        $timestamp, 
        json_encode($data), 
        $message_id,
        $tenant_id
    ]);
}

/**
 * Handle received message
 * @param array $data Webhook data
 * @param int $tenant_id Tenant ID (verified from webhook)
 */
function handleReceivedMessage($data, $tenant_id) {
    global $pdo;
    
    // Log received message - tenant already verified
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_webhook_log (
            tenant_id, webhook_type, from_number, to_number, 
            message_content, raw_payload, processed
        ) VALUES (?, 'message', ?, ?, ?, ?, 0)
    ");
    
    $stmt->execute([
        $tenant_id,
        $data['from'] ?? '',
        $data['to'] ?? '',
        $data['message'] ?? '',
        json_encode($data)
    ]);
}

/**
 * Log webhook for debugging
 * @param array $data Webhook data
 * @param int $tenant_id Tenant ID (verified from webhook)
 */
function logWebhook($data, $tenant_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_webhook_log (
            tenant_id, webhook_type, raw_payload, processed
        ) VALUES (?, ?, ?, 0)
    ");
    
    // Tenant is verified at this point
    $stmt->execute([$tenant_id, $data['type'] ?? 'unknown', json_encode($data)]);
}

// Initialize and handle API request
try {
    session_start();
    $api = new WhatsAppAPI();
    $api->handleRequest();
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}
?>