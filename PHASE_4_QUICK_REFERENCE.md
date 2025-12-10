# Phase 4: Audit Logging - Quick Reference

## Installation

```bash
# 1. Apply database migration
mysql -u root -p database < migrations/004_audit_logging.sql

# 2. Verify installation
# Open http://localhost/mtravels/verify_phase4.php
```

## Using ChatAudit

### Import
```php
require_once __DIR__ . '/includes/ChatAudit.php';
```

### Log Message Send
```php
ChatAudit::logSend(
    $tenantId,           // Tenant ID
    $branchId,           // Branch ID
    $userId,             // Sender ID
    $toUserId,           // Recipient ID
    $messageId,          // Message ID
    strlen($content),    // Content size
    true,                // Is encrypted
    $encryptionKeyId     // Encryption key ID
);
```

### Log Message Read
```php
ChatAudit::logRead(
    $tenantId,
    $branchId,
    $userId,
    $messageId
);
```

### Log Block/Unblock
```php
ChatAudit::logBlock(
    $tenantId,
    $branchId,
    $userId,
    $blockedUserId,
    'block'  // or 'unblock'
);
```

### Log Mute/Unmute
```php
ChatAudit::logMute(
    $tenantId,
    $branchId,
    $userId,
    $mutedUserId,
    'mute'   // or 'unmute'
);
```

### Log Failed Access
```php
ChatAudit::logFailedAccess(
    $tenantId,
    $branchId,
    $userId,
    $targetUserId,
    'send_message',      // Action that failed
    'user_blocked',      // Reason
    'User is blocked'    // Optional error message
);
```

### Query Logs
```php
$logs = ChatAudit::getAuditLog($tenantId, [
    'user_id' => 5,
    'action' => 'send_message',
    'status' => 'success',
    'start_date' => '2025-12-01 00:00:00',
    'end_date' => '2025-12-31 23:59:59',
    'limit' => 100
]);

foreach ($logs as $log) {
    echo $log['created_at'] . ' - ' . $log['action'] . ' (' . $log['status'] . ')';
}
```

### Get Audit Summary
```php
$summary = ChatAudit::getSummary($tenantId, 7); // Last 7 days

foreach ($summary as $item) {
    echo $item['action'] . ': ' . $item['count'] . ' (' . $item['status'] . ')';
}
```

### Export Logs
```php
// CSV format
$csv = ChatAudit::exportAuditLog($tenantId, $filters, 'csv');
file_put_contents('audit_export.csv', $csv);

// JSON format
$json = ChatAudit::exportAuditLog($tenantId, $filters, 'json');
file_put_contents('audit_export.json', $json);
```

### Get Failed Access Attempts
```php
$denied = ChatAudit::getFailedAttempts($tenantId, [
    'user_id' => 5,
    'start_date' => '2025-12-01'
]);
```

### Archive Old Logs
```php
// Delete logs older than 90 days
ChatAudit::archiveOldLogs($tenantId, 90);
```

## Database Queries

### Find All Messages Sent by User 5
```sql
SELECT * FROM chat_audit_log 
WHERE tenant_id = 1 
AND user_id = 5 
AND action = 'send_message' 
ORDER BY created_at DESC;
```

### Find All Denied Access Attempts
```sql
SELECT * FROM chat_audit_log 
WHERE tenant_id = 1 
AND status = 'denied' 
ORDER BY created_at DESC;
```

### Get Activity Summary (Last 7 Days)
```sql
SELECT action, status, COUNT(*) as count
FROM chat_audit_log 
WHERE tenant_id = 1 
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY action, status
ORDER BY count DESC;
```

### Find Failed Encryption Operations
```sql
SELECT * FROM chat_audit_log 
WHERE tenant_id = 1 
AND action = 'encrypt_message' 
AND status = 'failed' 
ORDER BY created_at DESC;
```

### Get User Activity Timeline
```sql
SELECT created_at, action, target_user_id, status
FROM chat_audit_log 
WHERE tenant_id = 1 
AND user_id = 5 
ORDER BY created_at DESC 
LIMIT 100;
```

## Admin Interfaces

### View Audit Logs
```
http://localhost/mtravels/admin/audit_logs.php
```

Features:
- Filter by user, action, status, date range
- Export to CSV
- View activity summary
- See detailed log information

### Generate Compliance Report
```
http://localhost/mtravels/admin/compliance_report.php
```

Report Types:
- GDPR - Data access by user
- HIPAA - Healthcare communication audit trail
- SOX - Financial communication tracking
- Failed Access - Security incidents
- Activity Summary - Overall statistics

## Filter Options

All filters are optional and can be combined:

| Filter | Type | Example |
|--------|------|---------|
| `user_id` | integer | `['user_id' => 5]` |
| `action` | string | `['action' => 'send_message']` |
| `status` | string | `['status' => 'success']` |
| `start_date` | datetime | `['start_date' => '2025-12-01 00:00:00']` |
| `end_date` | datetime | `['end_date' => '2025-12-31 23:59:59']` |
| `target_user_id` | integer | `['target_user_id' => 10]` |
| `message_id` | integer | `['message_id' => 123]` |
| `limit` | integer | `['limit' => 100]` |

## Action Types

| Action | Description |
|--------|-------------|
| `send_message` | User sends a message |
| `read_message` | User reads a message |
| `block_user` | User blocks another user |
| `unblock_user` | User unblocks another user |
| `mute_user` | User mutes another user |
| `unmute_user` | User unmutes another user |
| `encrypt_message` | Message encryption operation |
| `decrypt_message` | Message decryption operation |
| `settings_change` | User settings changed |

## Status Values

| Status | Meaning |
|--------|---------|
| `success` | Operation completed successfully |
| `denied` | Access was denied (blocked, peering denied, etc.) |
| `failed` | Operation failed (encryption error, etc.) |
| `error` | System error occurred |

## Common Use Cases

### Investigate User Activity
```php
$logs = ChatAudit::getAuditLog(1, [
    'user_id' => $suspiciousUserId,
    'start_date' => date('Y-m-d', strtotime('-30 days')),
    'limit' => 500
]);

// Analyze the logs
foreach ($logs as $log) {
    echo "User {$log['user_id']} did {$log['action']} at {$log['created_at']}\n";
}
```

### Find Failed Security Events
```php
$failedAccess = ChatAudit::getFailedAttempts(1, [
    'start_date' => date('Y-m-d', strtotime('-7 days'))
]);

echo "Found " . count($failedAccess) . " denied access attempts";
```

### Generate GDPR Report
```php
// Via admin interface: http://localhost/mtravels/admin/compliance_report.php
// Select "GDPR" and date range
// Click "Export CSV"

// Or programmatically:
$csv = ChatAudit::exportAuditLog(1, [
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31'
], 'csv');

// Send to user or save
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="gdpr_report.csv"');
echo $csv;
```

### Monitor Encryption Status
```php
$summary = ChatAudit::getSummary(1, 1); // Last 24 hours

$encryptionLog = ChatAudit::getAuditLog(1, [
    'action' => 'encrypt_message',
    'limit' => 100
]);

$failedCount = 0;
foreach ($encryptionLog as $log) {
    if ($log['status'] === 'failed') {
        $failedCount++;
    }
}

echo "Encryption failures: $failedCount";
```

## Troubleshooting

### Logs not appearing?
```php
// Check if table exists
$result = $pdo->query("SHOW TABLES LIKE 'chat_audit_log'");

// Check if there are any logs
$count = $pdo->query("SELECT COUNT(*) FROM chat_audit_log")->fetchColumn();
echo "Total logs: " . $count;
```

### Verify indexes
```sql
-- Check indexes
SHOW INDEX FROM chat_audit_log;

-- Should see: idx_tenant_action, idx_user_time, idx_target_user, etc.
```

### Performance check
```sql
-- Check query performance
EXPLAIN SELECT * FROM chat_audit_log 
WHERE tenant_id = 1 
AND action = 'send_message' 
ORDER BY created_at DESC 
LIMIT 100;
```

## Notes

- All times are stored in UTC
- IP addresses are captured for security audit
- Details field is JSON for flexible metadata
- Archive table can be used for historical analysis
- Recommend archiving monthly for performance
- All queries use prepared statements for security

## Related Files

- Core: `includes/ChatAudit.php`
- Database: `migrations/004_audit_logging.sql`
- API Integration: `api/messages.php`, `api/chat_prefs.php`
- Admin UI: `admin/audit_logs.php`, `admin/compliance_report.php`
- Documentation: `PHASE_4_IMPLEMENTATION_SUMMARY.md`
