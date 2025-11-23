# WhatsApp Automation API for Travel Agency System

## Overview

This implementation provides a comprehensive WhatsApp automation API for tenant-based notifications in the travel agency system. It allows sending automatic WhatsApp notifications for new visa applications, Umrah bookings, and hotel reservations to clients.

## Features

### Core Features
- **Tenant-based Configuration**: Each tenant can configure their own WhatsApp settings
- **Multi-language Support**: Message templates support English, Persian, and Pashto
- **Message Queue System**: Reliable message delivery with retry mechanisms
- **Multiple Providers Support**: Supports Twilio, MessageBird, and 360Dialog
- **Template Management**: Customizable message templates with variables
- **Real-time Analytics**: Track message delivery and performance
- **Webhook Integration**: Handle incoming messages and delivery status

### Notification Types
- **Visa Applications**: Notify clients when new visa applications are created
- **Umrah Bookings**: Send confirmation messages for Umrah bookings
- **Hotel Reservations**: Hotel booking confirmations
- **Refunds**: Notification for refund processed
- **Custom Messages**: Send custom messages to clients

## System Architecture

### Database Schema
The system uses the following main tables:
- `whatsapp_settings`: Tenant-specific WhatsApp configuration
- `whatsapp_templates`: Customizable message templates
- `whatsapp_messages`: Message queue and delivery tracking
- `whatsapp_delivery_status`: Delivery status tracking
- `whatsapp_webhook_log`: Incoming webhook logging
- `whatsapp_analytics`: Performance analytics

### Core Components

1. **WhatsAppManager Class** (`api/whatsapp/WhatsAppManager.php`)
   - Core business logic for handling WhatsApp operations
   - Message template processing
   - Queue management
   - Provider integration

2. **API Endpoints** (`api/whatsapp/index.php`)
   - RESTful API for external integrations
   - Webhook handling
   - Message status updates

3. **Admin Interface** (`admin/whatsapp_settings.php`)
   - Web-based configuration panel
   - Template management
   - Analytics dashboard

4. **Integration Layer**
   - Updated visa creation API with WhatsApp notifications
   - JavaScript integration for real-time feedback

## Installation and Setup

### 1. Database Setup

Execute the database schema to create the required tables:

```sql
-- Run the SQL file to create tables and indexes
SOURCE sql/whatsapp_schema.sql;
```

### 2. File Structure

Ensure the following files are in place:
```
├── api/
│   ├── whatsapp/
│   │   ├── WhatsAppManager.php
│   │   └── index.php
│   └── create_visa.php
├── admin/
│   └── whatsapp_settings.php
├── sql/
│   └── whatsapp_schema.sql
└── umrah/
    └── js/visa/add_visa.js (updated)
```

### 3. Directory Permissions

Ensure the system can write to the database and log files:
```bash
chmod 755 api/
chmod 755 admin/
chmod 644 sql/whatsapp_schema.sql
```

## Configuration Guide

### 1. WhatsApp Provider Setup

Choose your WhatsApp provider (Twilio, MessageBird, or 360Dialog) and obtain:
- API Token/Access Key
- Phone Number ID
- Webhook Verify Token
- Webhook URL

### 2. Tenant Configuration

1. Log in as admin
2. Navigate to `admin/whatsapp_settings.php`
3. Configure provider settings:
   - Select provider (Twilio recommended)
   - Enter API token
   - Enter phone number ID
   - Set webhook verify token
   - Configure rate limits and retry attempts

### 3. Message Templates

1. Go to Templates section
2. Create templates for each notification type:
   - Visa applications
   - Umrah bookings
   - Hotel reservations
3. Use variables like `{client_name}`, `{booking_date}`, etc.
4. Support for multiple languages

### 4. Enable Auto Notifications

- Toggle "Enable Auto Notifications" in settings
- Choose real-time or queued delivery
- Set message priority levels

## API Documentation

### Core Endpoints

#### 1. Send Notification
```
POST /api/whatsapp/index.php?send
Content-Type: application/json

{
    "type": "visa",
    "booking_id": 123,
    "additional_data": {}
}
```

#### 2. Send Test Message
```
POST /api/whatsapp/index.php?send-test
Content-Type: application/json

{
    "phone_number": "+1234567890",
    "message": "Test message",
    "message_type": "test"
}
```

#### 3. Get Settings
```
GET /api/whatsapp/index.php?settings
```

#### 4. Update Settings
```
PUT /api/whatsapp/index.php?settings
Content-Type: application/json

{
    "provider": "twilio",
    "api_token": "your_token",
    "auto_notifications": true
}
```

#### 5. Get Message Status
```
GET /api/whatsapp/index.php?message-status?message_id=123
```

#### 6. Get Analytics
```
GET /api/whatsapp/index.php?analytics?start_date=2023-01-01&end_date=2023-12-31
```

### Message Templates Variables

Common variables available in templates:
- `{client_name}` - Client's name
- `{booking_date}` - Date of booking
- `{agency_name}` - Agency name
- `{contact_info}` - Contact information
- `{amount}` - Booking amount
- `{currency}` - Currency code

### Visa-specific Variables
- `{applicant_name}` - Visa applicant's name
- `{passport_number}` - Passport number
- `{country}` - Destination country
- `{visa_type}` - Type of visa
- `{receive_date}` - Application receive date

### Umrah-specific Variables
- `{member_name}` - Umrah member's name
- `{package_type}` - Package type
- `{flight_date}` - Flight departure date
- `{hotel_name}` - Hotel name
- `{duration}` - Trip duration

## Integration Examples

### 1. Visa Creation Integration

The system automatically sends WhatsApp notifications when new visa applications are created:

```javascript
// Frontend integration (already implemented)
fetch('../../api/create_visa.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.status === 'success') {
        if (data.whatsapp_notified) {
            showToast('WhatsApp notification sent to client', 'success');
        }
    }
});
```

### 2. Manual Notification Trigger

```php
require_once 'api/whatsapp/WhatsAppManager.php';

$whatsapp = new WhatsAppManager($tenant_id);
$result = $whatsapp->sendBookingNotification('visa', $visa_id);

if ($result['success']) {
    echo "Notification sent successfully";
} else {
    echo "Notification failed: " . $result['message'];
}
```

### 3. Bulk Notifications

```javascript
// Send notifications for multiple bookings
const bookings = [
    { type: 'visa', booking_id: 123 },
    { type: 'umrah', booking_id: 456 }
];

fetch('../../api/whatsapp/index.php?bulk-send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ bookings })
});
```

## Testing Instructions

### 1. Provider Connection Test

1. Configure provider settings in admin panel
2. Click "Test Connection" button
3. Verify success message appears

### 2. Send Test Message

1. Go to "Send Test Message" section
2. Enter a phone number with country code (e.g., +1234567890)
3. Enter test message
4. Click "Send Test Message"
5. Check message appears in recipient's WhatsApp

### 3. Template Testing

1. Create or edit a template
2. Use available variables
3. Save template
4. Trigger a notification that uses the template
5. Verify variables are replaced correctly

### 4. End-to-End Testing

1. Create a new visa application through the system
2. Check that:
   - Visa is created successfully
   - WhatsApp notification is sent (if auto-notifications enabled)
   - Message appears in recipient's WhatsApp
   - Message status is tracked in the system

### 5. Queue Testing

1. Configure real-time notifications to "off" (queue mode)
2. Create multiple bookings quickly
3. Go to "Message Queue Status"
4. Click "Process Queue"
5. Verify messages are processed and sent

## Webhook Configuration

### Setting Up Webhooks

1. Configure webhook URL in provider dashboard:
   ```
   https://yourdomain.com/api/whatsapp/index.php?webhook
   ```

2. Set webhook verify token in both:
   - Provider dashboard
   - Admin WhatsApp settings

3. Test webhook by sending a test message to your WhatsApp number

### Webhook Events

The system handles the following webhook events:
- `message_status`: Delivery status updates
- `received_message`: Incoming messages from clients
- `delivered`: Message delivery confirmation
- `read`: Message read confirmation
- `failed`: Message delivery failure

## Monitoring and Analytics

### Dashboard Metrics

- **Message Volume**: Total messages sent by date
- **Delivery Rate**: Percentage of messages successfully delivered
- **Read Rate**: Percentage of messages read by recipients
- **Response Time**: Average time for message delivery
- **Error Rate**: Percentage of failed messages

### Database Queries for Analytics

```sql
-- Messages by type and date
SELECT 
    message_type,
    DATE(created_at) as date,
    COUNT(*) as total_sent,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
FROM whatsapp_messages
WHERE tenant_id = ?
GROUP BY message_type, DATE(created_at)
ORDER BY date DESC;

-- Queue status
SELECT 
    status,
    COUNT(*) as count
FROM whatsapp_messages
WHERE tenant_id = ?
AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY status;
```

## Troubleshooting

### Common Issues

#### 1. "Connection Test Failed"
**Cause**: Invalid API credentials or network issues
**Solution**: 
- Verify API token and phone number ID
- Check internet connection
- Ensure provider account is active

#### 2. "Messages Not Sending"
**Cause**: Rate limiting or queue processing issues
**Solution**:
- Check queue status in admin panel
- Verify message queue is being processed
- Check rate limit settings
- Review error logs

#### 3. "Template Variables Not Replaced"
**Cause**: Template syntax errors or missing variables
**Solution**:
- Check template syntax uses `{variable}` format
- Verify variables are available in context
- Test template with sample data

#### 4. "Webhook Not Receiving Updates"
**Cause**: Webhook URL or verification issues
**Solution**:
- Verify webhook URL is accessible
- Check webhook verify token matches
- Test webhook endpoint manually

### Error Logs

Check the following for error details:
- PHP error logs: `/var/log/php_errors.log`
- Application logs: Check `error_log()` entries
- Database logs: Check MySQL error logs

### Debug Mode

Enable debug logging by adding to `WhatsAppManager.php`:
```php
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/whatsapp_debug.log');
```

## Security Considerations

### API Security
- All endpoints require authentication
- API tokens are encrypted in database
- Webhook verification for incoming requests
- Rate limiting to prevent abuse

### Data Protection
- Phone numbers and messages are encrypted
- Compliance with WhatsApp Business API terms
- GDPR compliance for user data

### Best Practices
1. Regular backup of message templates and settings
2. Monitor API usage and costs
3. Keep API credentials secure
4. Regular testing of webhook endpoints
5. Monitor message delivery rates

## Cost Considerations

### WhatsApp Business API Costs
- Conversation-based pricing varies by region
- Template messages may have different rates
- Provider fees (Twilio, MessageBird, etc.)
- Volume discounts for high usage

### Optimization Tips
- Use message templates efficiently
- Implement rate limiting
- Monitor delivery rates
- Optimize template content to reduce message length

## Support and Maintenance

### Regular Maintenance Tasks
1. **Weekly**: Check message delivery rates
2. **Monthly**: Review analytics and performance
3. **Quarterly**: Update templates and test workflows
4. **Annually**: Review provider agreements and pricing

### Backup Strategy
- Export WhatsApp settings regularly
- Backup message templates
- Document custom integrations
- Keep API credentials in secure location

## Future Enhancements

### Planned Features
1. **Rich Media Support**: Images, documents, and links
2. **Interactive Messages**: Buttons and quick replies
3. **Multi-tenant Analytics**: Cross-tenant reporting
4. **AI Integration**: Smart message responses
5. **Integration APIs**: Connect with more booking systems

### Customization Options
1. **Custom Fields**: Add client-specific variables
2. **Workflow Rules**: Conditional messaging
3. **Language Detection**: Automatic language selection
4. **Timezone Support**: Send messages in client's timezone

## Conclusion

This WhatsApp automation system provides a comprehensive solution for travel agencies to automatically notify clients about bookings and services. The system is designed to be scalable, secure, and easy to maintain while providing detailed analytics and monitoring capabilities.

For technical support or custom development needs, please refer to the system documentation or contact the development team.