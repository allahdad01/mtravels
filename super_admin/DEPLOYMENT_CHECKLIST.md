# Super Admin Security - Deployment Checklist

## Pre-Deployment Verification

### 1. Code Review
- [ ] All super admin files reviewed for authorization checks
- [ ] No hardcoded credentials in any files
- [ ] No debug code left in production paths
- [ ] All error messages are user-friendly (no system details exposed)

### 2. Database Verification
```sql
-- Verify super admin users have NULL tenant_id
SELECT id, email, role, tenant_id FROM users 
WHERE role = 'super_admin' AND tenant_id IS NOT NULL;

-- Should return: 0 rows (empty result)

-- Verify all super admin users exist
SELECT id, email, role, tenant_id FROM users 
WHERE role = 'super_admin' 
ORDER BY created_at DESC;

-- Expected: Only system administrators should appear
```

### 3. File Permissions Check
```bash
# Super admin directory should be readable
ls -la /path/to/super_admin/

# Verify security.php exists and is readable
ls -la /path/to/super_admin/security.php

# Check that includes directory has proper permissions
ls -la /path/to/super_admin/includes/
```

### 4. Session Configuration
Edit `.htaccess` or `php.ini` to verify:
```ini
session.cookie_httponly = On
session.use_only_cookies = On
session.cookie_samesite = Strict
# For HTTPS only:
session.cookie_secure = On
```

### 5. Security Headers Test
```bash
# Test from command line
curl -I https://yourdomain.com/super_admin/dashboard.php

# Verify these headers are present:
# - X-XSS-Protection
# - X-Content-Type-Options
# - X-Frame-Options
# - Content-Security-Policy
# - Referrer-Policy
# - Strict-Transport-Security (if HTTPS)
```

### 6. HTTPS Enforcement
- [ ] All super admin pages accessible ONLY via HTTPS
- [ ] HTTP requests redirect to HTTPS
- [ ] SSL certificate is valid and not expired
- [ ] HSTS header is set: `Strict-Transport-Security: max-age=31536000; includeSubDomains`

### 7. Error Logging
- [ ] Error log location is outside web root
- [ ] Error log is not world-readable
- [ ] PHP errors are logged, not displayed to users

```bash
# Set proper permissions
chmod 600 /var/log/php-errors.log
chmod 600 /var/log/apache2/error.log
```

### 8. Audit Logging
- [ ] Audit logs table exists in database
- [ ] Audit logs include: user_id, action, entity_type, entity_id, ip_address, timestamp
- [ ] Audit logs are not accessible from web interface

### 9. Rate Limiting Cache
- [ ] Cache directory for rate limiting exists with restricted permissions
```bash
# Create if needed
mkdir -p /tmp/php_rate_limits
chmod 700 /tmp/php_rate_limits
```

### 10. Database Backups
- [ ] Regular backups are enabled
- [ ] Backup location is outside web root
- [ ] Backups are encrypted and access-restricted
- [ ] Restore process has been tested

---

## Deployment Steps

### Step 1: Backup Current Installation
```bash
# Create timestamped backup
cp -r /var/www/html/super_admin /var/www/html/super_admin.backup.$(date +%Y%m%d_%H%M%S)
mysqldump -u user -p database > /path/to/backup/db_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Deploy Security Updates
```bash
# Copy updated files
cp security.php /var/www/html/super_admin/
cp manage_tenants.php /var/www/html/super_admin/
cp file_browser.php /var/www/html/super_admin/
cp get_demo_request_details.php /var/www/html/super_admin/
cp user_addon_payments.php /var/www/html/super_admin/
cp subscription_payments.php /var/www/html/super_admin/
cp get_subscription_payments.php /var/www/html/super_admin/
cp branch_addon_payments.php /var/www/html/super_admin/
cp generate_invoice_pdf.php /var/www/html/super_admin/
```

### Step 3: Set File Permissions
```bash
# Super admin directory
chmod 755 /var/www/html/super_admin
chmod 755 /var/www/html/super_admin/*
chmod 644 /var/www/html/super_admin/*.php

# Includes directory
chmod 755 /var/www/html/super_admin/includes
chmod 644 /var/www/html/super_admin/includes/*.php

# Handlers directory
chmod 755 /var/www/html/super_admin/handlers
chmod 644 /var/www/html/super_admin/handlers/*.php

# .htaccess
chmod 644 /var/www/html/super_admin/.htaccess
```

### Step 4: Clear Session Cache
```bash
# Clear all existing sessions
rm -rf /var/lib/php/sessions/*
# Or on different systems:
rm -rf /tmp/php_sessions/*
```

### Step 5: Test Authorization
```bash
# Test as super admin user (no tenant_id)
# 1. Login with super admin credentials
# 2. Verify access to dashboard
# 3. Verify access to manage_tenants
# 4. Verify access to user management

# Test as tenant admin (with tenant_id)
# 1. Login with tenant admin credentials
# 2. Attempt to access super_admin pages
# 3. Verify redirect to access_denied.php
```

### Step 6: Run Security Tests
```bash
# Test CSRF protection
# 1. Attempt POST without csrf_token
# 2. Verify request is rejected

# Test rate limiting
# 1. Send multiple rapid requests
# 2. Verify 429 response after limit

# Test input validation
# 1. Submit search with > 255 characters
# 2. Verify input is rejected or truncated

# Test path traversal
# 1. Attempt to access ../../../etc/passwd
# 2. Verify access is denied
```

### Step 7: Monitor Logs
```bash
# Check for errors in first 5 minutes post-deployment
tail -f /var/log/php-errors.log

# Look for authorization-related messages
grep "Unauthorized" /var/log/php-errors.log | tail -20
```

---

## Post-Deployment Verification

### Daily Checks (First Week)
- [ ] Monitor error logs for exceptions
- [ ] Verify audit logs are recording actions
- [ ] Test random super admin operations
- [ ] Check rate limiting is not blocking legitimate traffic

### Weekly Checks
- [ ] Review audit logs for suspicious activity
- [ ] Verify all super admin users have NULL tenant_id
- [ ] Test backup/restore process
- [ ] Review CSRF token generation logs

### Monthly Checks
- [ ] Security log review and analysis
- [ ] Penetration testing (if applicable)
- [ ] Database integrity check
- [ ] User access review (remove inactive accounts)

---

## Rollback Plan

If issues are discovered post-deployment:

### Immediate Actions
```bash
# 1. Restore from backup
cp -r /var/www/html/super_admin.backup.YYYYMMDD_HHMMSS /var/www/html/super_admin

# 2. Restore database if needed
mysql -u user -p database < /path/to/backup/db_YYYYMMDD_HHMMSS.sql

# 3. Clear session cache again
rm -rf /var/lib/php/sessions/*

# 4. Restart web server
systemctl restart apache2
# or
systemctl restart nginx
```

### Investigation
- Review what went wrong
- Check error logs
- Verify database state
- Test specific scenario that failed

### Redeployment
- Once issue is identified and fixed
- Deploy updates again
- Repeat deployment steps

---

## Security Testing Scenarios

### Test 1: Authorization Bypass
```
User: tenant_admin_user with tenant_id = 5
Action: Try to access /super_admin/dashboard.php
Expected: Redirect to access_denied.php
Actual: _______________
Status: ☐ PASS ☐ FAIL
```

### Test 2: CSRF Protection
```
Action: POST to /super_admin/create_tenant.php without csrf_token
Expected: 403 error or redirect with error message
Actual: _______________
Status: ☐ PASS ☐ FAIL
```

### Test 3: Input Validation
```
Input: Search field with 10000 characters
Expected: Request rejected or input truncated to 255 chars
Actual: _______________
Status: ☐ PASS ☐ FAIL
```

### Test 4: Path Traversal
```
Input: file_browser.php?folder=../../../etc
Expected: Access denied, redirected to uploads directory
Actual: _______________
Status: ☐ PASS ☐ FAIL
```

### Test 5: Rate Limiting
```
Action: Send 40 requests in 60 seconds to an AJAX endpoint
Expected: 429 response after 30 requests
Actual: _______________
Status: ☐ PASS ☐ FAIL
```

### Test 6: XSS Prevention
```
Input: Search field with <script>alert('xss')</script>
Expected: Script is escaped/sanitized in output
Actual: _______________
Status: ☐ PASS ☐ FAIL
```

---

## Emergency Contacts

In case of security incident during/after deployment:

- **Security Team Lead**: [Contact Info]
- **Database Administrator**: [Contact Info]
- **System Administrator**: [Contact Info]
- **Web Server Administrator**: [Contact Info]

---

## Sign-Off

- [ ] QA Team: __________________ Date: __________
- [ ] Security Team: __________________ Date: __________
- [ ] DevOps Team: __________________ Date: __________
- [ ] Project Manager: __________________ Date: __________

---

## Notes

```
[Space for deployment notes and observations]
```
