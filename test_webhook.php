<?php
/**
 * Test WhatsApp Webhook Verification
 * Run this script to test if your webhook endpoint is working
 */

require_once 'includes/db.php';

// Generate test webhook payload (simulating Meta WhatsApp webhook)
$test_payload = [
    'object' => 'whatsapp_business_account',
    'entry' => [
        [
            'id' => '123456789',
            'changes' => [
                [
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '1234567890',
                            'phone_number_id' => '866031719930862'
                        ],
                        'contacts' => [
                            [
                                'profile' => ['name' => 'Test User'],
                                'wa_id' => '12345678901'
                            ]
                        ],
                        'messages' => [
                            [
                                'from' => '12345678901',
                                'id' => 'wamid.test123',
                                'timestamp' => '1700000000',
                                'text' => ['body' => 'Test message'],
                                'type' => 'text'
                            ]
                        ]
                    ],
                    'field' => 'messages'
                ]
            ]
        ]
    ]
];

$payload_json = json_encode($test_payload);
$secret = 'c7c53acfcfe212378413636951870d2a2ee101a0820bbcf1797c421150a56f5e';
$signature = 'sha256=' . hash_hmac('sha256', $payload_json, $secret);

echo "=== WhatsApp Webhook Test ===\n";
echo "Webhook Secret: " . substr($secret, 0, 10) . "...\n";
echo "Generated Signature: " . $signature . "\n";
echo "Payload Size: " . strlen($payload_json) . " bytes\n\n";

// Test the verification function
function verifyWebhookSignature($payload, $signature, $secret) {
    if (strpos($signature, 'sha256=') !== 0) {
        return false;
    }
    
    $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($signature, $expected_signature);
}

$is_valid = verifyWebhookSignature($payload_json, $signature, $secret);
echo "Signature Verification: " . ($is_valid ? "✅ PASSED" : "❌ FAILED") . "\n\n";

// Test webhook URL
$webhook_url = "https://taking-spilt-willing.ngrok-free.dev/almoqadas/mtravels/api/whatsapp/index.php?webhook";
echo "Webhook URL: " . $webhook_url . "\n";
echo "To test with curl:\n";
echo "curl -X POST \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"X-Hub-Signature: {$signature}\" \\\n";
echo "  -d '" . $payload_json . "' \\\n";
echo "  \"{$webhook_url}\"\n\n";

// Check if webhook endpoint exists
echo "=== Webhook Endpoint Check ===\n";
$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $http_code . "\n";
echo "Response: " . ($response ? substr($response, 0, 100) : "No response") . "\n";

if ($http_code == 200 || $http_code == 401) {
    echo "✅ Webhook endpoint is accessible\n";
} else {
    echo "⚠️  Webhook endpoint may not be accessible (check XAMPP is running)\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Ensure XAMPP Apache is running\n";
echo "2. Visit: https://taking-spilt-willing.ngrok-free.dev/almoqadas/mtravels/tenant_super_admin/whatsapp_settings.php\n";
echo "3. Update WhatsApp settings with your test API credentials\n";
echo "4. Configure webhook in Meta Developer Portal:\n";
echo "   - Callback URL: {$webhook_url}\n";
echo "   - Verify Token: Use the webhook_verify_token from your settings\n";
echo "   - Webhook Fields: messages, message_template_status_update\n";
?>