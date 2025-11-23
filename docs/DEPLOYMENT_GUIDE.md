# WhatsApp Automation API - Deployment Guide

## Quick Start Checklist

- [ ] Review system requirements
- [ ] Install database schema
- [ ] Configure file permissions
- [ ] Set up WhatsApp provider account
- [ ] Configure tenant settings
- [ ] Test basic functionality
- [ ] Set up monitoring

## Step-by-Step Deployment

### Step 1: Prerequisites Check

#### System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- cURL extension enabled
- JSON extension enabled
- PDO MySQL extension enabled

#### Verify Requirements
```bash
php -v
mysql --version
php -m | grep curl
php -m | grep json
php -m | grep pdo_mysql
```

### Step 2: Database Installation

1. **Backup Current Database**
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

2. **Run Schema Installation**
```sql
-- Execute the schema file
SOURCE /path/to/sql/whatsapp_schema.sql;
```

3. **Verify Tables Created**
```sql
SHOW TABLES LIKE 'whatsapp_%';
```

Expected tables:
- whatsapp_settings
- whatsapp_templates
- whatsapp_messages
- whatsapp_delivery_status
- whatsapp_webhook_log
- whatsapp_analytics

### Step 3: File Deployment

1. **Copy Files to Server**
```bash
# Copy API files
cp api/whatsapp/ /var/www/html/api/
cp api/create_visa.php /var/www/html/api/
cp admin/whatsapp_settings.php /var/www/html/admin/
cp sql/whatsapp_schema.sql /var/www/html/sql/

# Set permissions
chmod 755 /var/www/html/api/whatsapp/
chmod 644 /var/www/html/api/whatsapp/*.php
chmod 644 /var/www/html/api/create_visa.php
chmod 755 /var/www/html/admin/
chmod 644 /var/www/html/admin/whatsapp_settings.php
chmod 644 /var/www/html/sql/whatsapp_schema.sql
```

2. **Verify File Structure**
```bash
ls -la api/whatsapp/
ls -la admin/
```

### Step 4: WhatsApp Provider Setup

#### Twilio Setup (Recommended)

1. **Create Twilio Account**
   - Go to https://www.twilio.com
   - Sign up for WhatsApp Business API
   - Complete verification process

2. **Get API Credentials**
   - Account SID
   - Auth Token
   - Phone Number SID (for WhatsApp)

3. **Configure Webhook**
   - Webhook URL: `https://yourdomain.com/api/whatsapp/index.php?webhook`
   - HTTP Method: POST
   - Content Type: application/json

#### Alternative Providers

**MessageBird:**
- Sign up at https://messagebird.com
- Configure WhatsApp Business API
- Get API key and WhatsApp template ID

**360Dialog:**
- Register at https://360dialog.com
- Set up WhatsApp Business API
- Configure API credentials

### Step 5: Initial Configuration

1. **Access Admin Panel**
   - Navigate to `https://yourdomain.com/admin/whatsapp_settings.php`
   - Log in with admin credentials

2. **Basic Settings Configuration**
   ```
   Provider: Twilio
   API Token: [Your Twilio Auth Token]
   Phone Number ID: [Your WhatsApp Phone Number SID]
   Webhook Verify Token: [Generate secure token]
   Status: Active
   Auto Notifications: Enabled
   ```

3. **Test Connection**
   - Click "Test Connection" button
   - Verify success message appears

### Step 6: Template Setup

1. **Create Default Templates**

   **English Visa Template:**
   ```
   🛂 New Visa Application
   
   Dear {{client_name}},
   
   Your visa application has been successfully processed:
   
   🌍 Country: {{country}}
   📄 Type: {{visa_type}}
   💰 Amount: {{amount}}
   📅 Applied: {{booking_date}}
   
   Thank you for choosing {{agency_name}}!
   📞 Contact: {{contact_info}}
   ```

   **Persian Visa Template:**
   ```
   🛂 درخواست ویزا جدید
   
   عزیز {{client_name}}،
   
   درخواست ویزای شما با موفقیت پردازش شده:
   
   🌍 کشور: {{country}}
   📄 نوع: {{visa_type}}
   💰 مبلغ: {{amount}}
   📅 تاریخ: {{booking_date}}
   
   از انتخاب {{agency_name}} متشکریم!
   📞 تماس: {{contact_info}}
   ```

2. **Save Templates**
   - Click "Add Template" button
   - Select template type (visa/umrah/hotel)
   - Choose language (en/fa/ps)
   - Paste template content
   - Save template

### Step 7: Integration Testing

#### Test 1: API Connectivity
```bash
# Test API endpoint
curl -X GET https://yourdomain.com/api/whatsapp/index.php?settings \
  -H "Content-Type: application/json"
```

#### Test 2: Message Queue
1. Go to admin panel
2. Check "Message Queue Status"
3. Should show "No messages in queue"

#### Test 3: Test Message
1. Go to "Send Test Message" section
2. Enter test phone number (with country code)
3. Enter test message
4. Click "Send Test Message"
5. Verify message received on phone

### Step 8: End-to-End Testing

#### Visa Creation Test
1. **Create Test Visa Application**
   - Go to Umrah > Visa section
   - Click "New Visa Application"
   - Fill required fields
   - Submit form

2. **Verify WhatsApp Notification**
   - Check if notification sent
   - Verify message appears in recipient's WhatsApp
   - Check message content and formatting

3. **Check Message Tracking**
   - Go to Message Queue Status
   - Verify message status (sent/delivered/failed)

### Step 9: Production Configuration

#### Security Hardening
1. **HTTPS Configuration**
   ```apache
   # .htaccess for API directory
   <Directory "/var/www/html/api">
       SSLRequireSSL
       Require all denied
       Require valid-user
   </Directory>
   ```

2. **API Rate Limiting**
   ```php
   // Add to WhatsAppManager.php
   ini_set('max_execution_time', 30);
   ini_set('memory_limit', '128M');
   ```

3. **Database Security**
   ```sql
   -- Create dedicated database user
   CREATE USER 'whatsapp_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON database.whatsapp_* TO 'whatsapp_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

#### Performance Optimization
1. **Enable Database Indexes**
   ```sql
   CREATE INDEX idx_whatsapp_messages_status ON whatsapp_messages(status);
   CREATE INDEX idx_whatsapp_messages_tenant ON whatsapp_messages(tenant_id);
   ```

2. **Configure Message Queue Processing**
   ```bash
   # Set up cron job for queue processing
   # Edit crontab
   crontab -e
   
   # Add line for processing queue every 5 minutes
   */5 * * * * curl -s https://yourdomain.com/api/whatsapp/index.php?process-queue >/dev/null 2>&1
   ```

### Step 10: Monitoring Setup

#### Application Logs
```bash
# Set up log rotation
sudo nano /etc/logrotate.d/whatsapp

# Add content:
/var/log/whatsapp/*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 644 www-data www-data
}
```

#### Database Monitoring
```sql
-- Create monitoring views
CREATE VIEW whatsapp_queue_monitor AS
SELECT 
    status,
    COUNT(*) as count,
    MIN(created_at) as oldest_message,
    MAX(created_at) as newest_message
FROM whatsapp_messages
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY status;
```

## Post-Deployment Tasks

### Immediate (First 24 Hours)
- [ ] Monitor message delivery rates
- [ ] Check error logs for issues
- [ ] Test all notification types
- [ ] Verify webhook functionality
- [ ] Confirm template rendering

### Short Term (First Week)
- [ ] Review analytics dashboard
- [ ] Optimize templates based on feedback
- [ ] Set up automated monitoring alerts
- [ ] Train users on admin interface
- [ ] Document any custom requirements

### Ongoing Maintenance
- [ ] Weekly: Check delivery rates and error logs
- [ ] Monthly: Review and update templates
- [ ] Quarterly: Update API credentials if needed
- [ ] Annually: Review provider agreements

## Testing Procedures

### Automated Testing Script
```bash
#!/bin/bash
# whatsapp_test.sh - Automated testing script

echo "WhatsApp API Testing Suite"
echo "=========================="

# Test 1: API connectivity
echo "Test 1: API Connectivity"
response=$(curl -s -o /dev/null -w "%{http_code}" https://yourdomain.com/api/whatsapp/index.php?settings)
if [ $response -eq 200 ]; then
    echo "✓ API is accessible"
else
    echo "✗ API test failed (HTTP $response)"
fi

# Test 2: Database connectivity
echo "Test 2: Database Connectivity"
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;dbname=test', 'test', 'test');
    \$stmt = \$pdo->prepare('SELECT COUNT(*) FROM whatsapp_settings');
    \$stmt->execute();
    echo \"✓ Database connected\";
} catch(Exception \$e) {
    echo \"✗ Database test failed\";
}
echo \"\n\";
"

# Test 3: Template rendering
echo "Test 3: Template Rendering"
php -r "
require_once 'api/whatsapp/WhatsAppManager.php';
\$manager = new WhatsAppManager(1);
\$template = 'Hello {{client_name}}!';
\$data = ['client_name' => 'Test User'];
\$rendered = \$manager->renderTemplate(\$template, \$data);
if (strpos(\$rendered, 'Test User') !== false) {
    echo \"✓ Template rendering works\";
} else {
    echo \"✗ Template rendering failed\";
}
echo \"\n\";
"

echo "Testing complete!"
```

### Manual Testing Checklist

#### Admin Interface Testing
- [ ] Settings page loads correctly
- [ ] Template editor works
- [ ] Test message sending works
- [ ] Queue status displays correctly
- [ ] Connection test passes

#### API Testing
- [ ] Settings endpoint responds
- [ ] Message sending works
- [ ] Status checking works
- [ ] Error handling works
- [ ] Authentication works

#### Integration Testing
- [ ] Visa creation triggers notification
- [ ] Notifications reach clients
- [ ] Message status tracking works
- [ ] Templates render correctly
- [ ] Multi-language support works

## Troubleshooting Common Issues

### Issue: "Connection Test Failed"
**Symptoms**: Admin panel shows connection test failed
**Solutions**:
1. Verify API credentials are correct
2. Check internet connectivity
3. Ensure provider account is active
4. Check firewall/proxy settings

### Issue: "Messages Not Sending"
**Symptoms**: Queue shows pending messages but they're not being sent
**Solutions**:
1. Check if queue processing is enabled
2. Verify webhook URL is accessible
3. Check provider rate limits
4. Review error logs

### Issue: "Template Variables Not Replaced"
**Symptoms**: Messages show literal variable names instead of values
**Solutions**:
1. Check template syntax uses `{variable}` format
2. Verify variables are passed correctly
3. Test template rendering in admin panel
4. Check for typos in variable names

### Issue: "Database Connection Errors"
**Symptoms**: API returns database connection errors
**Solutions**:
1. Verify database credentials
2. Check database server is running
3. Ensure proper file permissions
4. Check MySQL user privileges

## Rollback Procedure

If deployment fails or issues occur:

### Quick Rollback
1. **Restore Database**
   ```bash
   mysql -u username -p database_name < backup_YYYYMMDD.sql
   ```

2. **Restore Files**
   ```bash
   # Remove new files
   rm -rf /var/www/html/api/whatsapp/
   rm /var/www/html/api/create_visa.php
   rm /var/www/html/admin/whatsapp_settings.php
   ```

3. **Clear Caches**
   ```bash
   # Clear any application caches
   # Restart web server if needed
   sudo systemctl restart apache2
   ```

### Partial Rollback
If only specific components fail:
1. Disable WhatsApp notifications in settings
2. Comment out WhatsApp integration code
3. Keep database schema for future use
4. Document issues for resolution

## Post-Deployment Verification

### Final Checklist
- [ ] All tests pass
- [ ] No critical errors in logs
- [ ] WhatsApp notifications working
- [ ] Admin interface functional
- [ ] Templates rendering correctly
- [ ] Webhooks receiving updates
- [ ] Monitoring alerts configured
- [ ] Documentation updated
- [ ] Team trained on new features

### Success Metrics
- **Availability**: 99.9% uptime
- **Delivery Rate**: >95% messages delivered
- **Response Time**: <5 seconds for API calls
- **Error Rate**: <1% failed messages

## Support Contact

For technical support during deployment:
- Email: support@example.com
- Phone: +1-XXX-XXX-XXXX
- Documentation: https://docs.example.com/whatsapp-api
- Issue Tracker: https://github.com/example/whatsapp-api/issues

## Conclusion

This deployment guide provides comprehensive instructions for implementing the WhatsApp automation API system. Follow the steps carefully and test thoroughly before going live. The system is designed to be robust and scalable, providing reliable WhatsApp notifications for your travel agency operations.