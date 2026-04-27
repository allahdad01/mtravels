# WhatsApp Notification Fix Instructions

## Problem Summary
WhatsApp notifications show as "sent" in logs but are not received on phone number +93780310431.

## Root Causes Identified

### 1. Invalid/Expired API Token
- Error: `(#131005) Access denied`
- The Meta WhatsApp Business API token is either expired, revoked, or lacks necessary permissions
- Current token appears to start with: `EAAV2y1w5z...`

### 2. Phone Numbers Not in Allowed List
- Error: `(#131030) Recipient phone number not in allowed list`
- WhatsApp Business API requires adding recipient phone numbers to an allowed list before sending messages
- Affected numbers: `+93780310431`, `0777305730`

### 3. Queue Processing Not Automated
- Messages are queued but not processed automatically
- `real_time_notifications` was disabled (now enabled)
- No cron job to process pending messages

## Solutions

### Solution A: Fix Meta WhatsApp API Configuration (Recommended)

#### Step 1: Regenerate Access Token
1. Go to [Meta Developer Portal](https://developers.facebook.com/apps/)
2. Select your WhatsApp Business app
3. Navigate to **WhatsApp → API Setup**
4. Generate a new **permanent access token**
5. Copy the new token

#### Step 2: Add Phone Numbers to Allowed List
1. In Meta Developer Portal, go to **WhatsApp → Configuration**
2. Scroll to **"Allowed Phone Numbers"** section
3. Add the following numbers:
   - `+93780310431` (your number)
   - `0777305730` (other test numbers)
4. Click **Save Changes**

#### Step 3: Update System Configuration
1. Go to WhatsApp Settings page: `http://localhost/almoqadas/mtravels/tenant_super_admin/whatsapp_settings.php`
2. Update the following fields:
   - **API Token**: Paste new token from Step 1
   - **Phone Number ID**: `1055854510949573` (keep as is if correct)
   - **Status**: `active`
   - **Auto Notifications**: `Enabled`
   - **Real Time Notifications**: `Enabled`
3. Save changes

#### Step 4: Create Approved Meta Templates
The application now falls back to approved Meta templates when a free-form message is rejected outside the 24-hour reply window. Create these templates in Meta Business Manager with the exact names below.

1. `ticket_booking_confirmation`
```text
Hello {{1}}, your flight ticket has been confirmed. PNR: {{2}}, from {{3}} to {{4}} on {{5}}. Have a safe journey! Contact: {{6}}
```

2. `visa_application_confirmation`
```text
Hello {{1}}, your visa application for {{2}} has been processed. Passport: {{3}}, Type: {{4}}. Thank you for choosing {{5}}! Contact: {{6}}
```

3. `hotel_booking_confirmation`
```text
Hello {{1}}, your hotel booking is confirmed. Hotel: {{2}}, Check-in: {{3}}, Check-out: {{4}}. Thank you for choosing {{5}}! Contact: {{6}}
```

4. `umrah_booking_confirmation`
```text
Assalamu Alaikum {{1}}, your Umrah booking is confirmed. Pilgrim: {{2}}, Departure: {{3}}, Return: {{4}}. May Allah accept your Umrah! Contact: {{5}}
```

Parameter mapping used by the application:
- `ticket_booking_confirmation`: client name, PNR, origin, destination, departure date, agency name, contact info
- `visa_application_confirmation`: client name, country, passport number, visa type, agency name, contact info
- `hotel_booking_confirmation`: client name, accommodation details, check-in date, check-out date, agency name, contact info
- `umrah_booking_confirmation`: client name, pilgrim name, flight date, return date, agency name, contact info

### Solution B: Use Mock Provider for Testing (Temporary)

If you just need the system to work for testing/development:

1. Run this SQL query:
```sql
UPDATE whatsapp_settings 
SET provider = 'twilio', 
    real_time_notifications = 1,
    updated_at = NOW()
WHERE tenant_id = 2;
```

2. Messages will be logged but not actually sent via WhatsApp
3. Good for development/testing without actual WhatsApp API

### Solution C: Automated Queue Processing

A cron job has been created to process pending messages:

1. File: `cron/process_whatsapp_queue.php`
2. Schedule to run every 5-10 minutes using cron or Windows Task Scheduler

**Windows Task Scheduler Example:**
```
schtasks /create /tn "ProcessWhatsAppQueue" /tr "php C:\xampp\htdocs\almoqadas\mtravels\cron\process_whatsapp_queue.php" /sc minute /mo 5 /st 00:00
```

**Linux Cron Example:**
```bash
*/5 * * * * php /var/www/html/almoqadas/mtravels/cron/process_whatsapp_queue.php >> /var/log/whatsapp_queue.log 2>&1
```

## Testing the Fix

### Test 1: Send Test Message
Run the test script:
```bash
php test_whatsapp_send.php
```

### Test 2: Check Message Status
Run the status check:
```bash
php check_message_status.php
```

### Test 3: Create New Booking
1. Create a new ticket booking
2. Check if WhatsApp notification is sent
3. Verify message status in database

## Verification Steps

1. **Check Error Logs**: Look for successful sending or errors
2. **Database Status**: Messages should move from `pending` to `sent` or `failed`
3. **Phone Reception**: Actual message should arrive on +93780310431

## Common Issues and Troubleshooting

### Issue: Still getting "Access denied"
- Token may have expired again (tokens typically last 60 days)
- Check token permissions in Meta Developer Portal
- Ensure WhatsApp Business account is properly set up

### Issue: "Recipient phone number not in allowed list"
- Double-check phone number format in allowed list
- Numbers must be in E.164 format (e.g., +93780310431)
- Wait 5 minutes after adding numbers (propagation delay)

### Issue: Messages stuck in "pending" status
- Ensure cron job is running
- Check `real_time_notifications` is enabled
- Run queue processor manually: `php cron/process_whatsapp_queue.php`

## Additional Configuration

### Enable Detailed Logging
Add to `config.php`:
```php
define('WHATSAPP_DEBUG', true);
```

### Monitor Webhooks
Check webhook configuration at:
- URL: `http://localhost/almoqadas/mtravels/api/whatsapp/index.php?webhook`
- Verify Token: Should match your settings

## Support

If issues persist:
1. Check PHP error logs: `c:/xampp/php/logs/php_error_log`
2. Check Apache logs: `c:/xampp/apache/logs/error.log`
3. Review Meta Developer Portal for API status
4. Test webhook with: `php test_webhook.php`

## Files Created/Modified

1. `cron/process_whatsapp_queue.php` - Automated queue processor
2. `fix_whatsapp_issues.php` - Diagnostic and fix tool
3. `check_whatsapp_settings.php` - Settings checker
4. `test_send_message.php` - Direct message tester
5. `check_message_status.php` - Status checker

## Next Steps

1. **Immediate**: Apply Solution A or B based on your needs
2. **Short-term**: Set up cron job for automated processing
3. **Long-term**: Monitor and maintain API token validity
4. **Testing**: Regularly test with new bookings

---

**Last Updated**: 2026-04-26  
**Status**: Ready for implementation
