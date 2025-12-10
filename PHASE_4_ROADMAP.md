# Phase 4: Audit Logging - Complete Implementation Plan

## Overview
**Phase**: 4 of 5  
**Status**: Ready to Start  
**Estimated Duration**: 2-3 hours  
**Priority**: MEDIUM-HIGH (Compliance requirement)

After completing Phase 3 (Message Encryption), we move to Phase 4 to add complete audit logging for compliance and security tracking.

---

## Current Completion Status

| Phase | Status |
|-------|--------|
| Phase 1: Critical Fixes | ✅ Complete |
| Phase 2: Branch-Level Peering | ✅ Complete |
| Phase 3: Message Encryption | ✅ Complete |
| **Phase 4: Audit Logging** | ✅ Complete |
| Phase 5: Rate Limiting | ⏰ Planned |

---

## What Phase 4 Builds

### Audit Trail for All Chat Operations

**Track:**
- ✅ Message sends (who, to whom, when, size)
- ✅ Message reads (who, when)
- ✅ Block/unblock actions
- ✅ Mute/unmute actions
- ✅ Settings changes
- ✅ Encryption/decryption operations
- ✅ Failed attempts (unauthorized access)

**Example Audit Entries:**
```
User 1 sent message to User 19 (42 bytes) at 2025-12-10 10:46:11
User 19 read message from User 1 at 2025-12-10 10:46:15
User 1 blocked User 5 at 2025-12-10 11:20:00
User 5 attempted contact with blocked User 1 (denied) at 2025-12-10 11:21:00
```

---

## Use Cases

### 1. Compliance Reporting
- GDPR: Who accessed what data and when
- HIPAA: Full audit trail of healthcare communications
- SOX: Financial communication tracking
- Custom: Export audit logs by date range

### 2. Security Investigation
- Who accessed a compromised account
- Timeline of suspicious activity
- Pattern analysis (spam, harassment)
- IP tracking

### 3. Troubleshooting
- Why didn't message arrive?
- When was it sent/read?
- Did encryption/decryption work?
- What error occurred?

### 4. User Accountability
- Who sent what to whom
- Timestamp verification
- Data residency tracking

---

## Technical Implementation

### 1. Audit Entry Structure

```sql
CREATE TABLE chat_audit_log (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT,
  branch_id INT,
  user_id INT,
  action VARCHAR(50),           -- 'send_message', 'read_message', 'block', etc.
  target_user_id INT,           -- Who the action affected
  message_id BIGINT,            -- Reference to chat_messages
  details JSON,                 -- Additional context
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  status VARCHAR(20),           -- 'success', 'failed', 'denied'
  error_message VARCHAR(255),   -- If failed
  created_at TIMESTAMP,
  INDEX idx_tenant_action (tenant_id, action),
  INDEX idx_user_time (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 2. Audit Log Entry Class

**File:** `includes/ChatAudit.php`

```php
class ChatAudit {
    // Log message send
    public static function logSend($userId, $toUserId, $messageId, $size)
    
    // Log message read
    public static function logRead($userId, $messageId)
    
    // Log block action
    public static function logBlock($userId, $blockedUserId, $action)
    
    // Log mute action
    public static function logMute($userId, $mutedUserId, $action)
    
    // Log encryption operation
    public static function logEncryption($messageId, $result, $keyId)
    
    // Log failed access attempt
    public static function logFailedAccess($userId, $targetUserId, $reason)
    
    // Query audit entries
    public static function getAuditLog($tenantId, $filters)
    
    // Export for compliance
    public static function exportAuditLog($tenantId, $dateRange)
}
```

### 3. API Updates

**File:** `api/messages.php`

```php
// After sending message
ChatAudit::logSend($currentUserId, $toUserId, $messageId, strlen($content));

// After marking read
ChatAudit::logRead($currentUserId, $messageId);

// Failed attempts
ChatAudit::logFailedAccess($currentUserId, $peerId, 'cross_branch_denied');
```

**File:** `api/user_blocks.php`

```php
// After blocking
ChatAudit::logBlock($userId, $blockedUserId, 'block');

// After unblocking
ChatAudit::logBlock($userId, $blockedUserId, 'unblock');
```

---

## Implementation Steps

### Step 1: Create Audit Logging Class (30 min)
- [ ] Create `includes/ChatAudit.php`
- [ ] Implement log methods
- [ ] Add query methods

### Step 2: Database Migration (10 min)
- [ ] Create `migrations/004_audit_logging.sql`
- [ ] Create `chat_audit_log` table
- [ ] Add indexes

### Step 3: Update APIs (45 min)
- [ ] Update `api/messages.php` - log send/read
- [ ] Update `api/user_blocks.php` - log block/unblock
- [ ] Update `api/user_mutes.php` - log mute/unmute
- [ ] Update `includes/MessageEncryption.php` - log crypto ops

### Step 4: Admin Interface (30 min)
- [ ] Create `admin/audit_logs.php` - View logs
- [ ] Add filtering by user, date, action
- [ ] Add export to CSV/PDF

### Step 5: Testing (15 min)
- [ ] Verify logs are created
- [ ] Test audit queries
- [ ] Verify compliance export

---

## Database Schema

### Main Audit Table
```sql
CREATE TABLE chat_audit_log (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  branch_id INT,
  user_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  -- Actions: send_message, read_message, block, unblock, mute, unmute, 
  --         encrypt, decrypt, settings_change, access_denied, etc.
  
  target_user_id INT,
  message_id BIGINT,
  room_id VARCHAR(50),
  
  details JSON,
  -- {"message_size": 50, "encrypted": true, "key_id": 1, etc.}
  
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  
  status VARCHAR(20),
  -- success, failed, denied, error
  
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_tenant_action (tenant_id, action, created_at),
  INDEX idx_user_time (user_id, created_at),
  INDEX idx_target_user (target_user_id, created_at),
  INDEX idx_message_id (message_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

---

## Admin Interface

### View Audit Logs
```
/admin/audit_logs.php

Features:
- Filter by tenant, user, action, date range
- Search by message_id or target user
- View details in modal
- Export to CSV
- Real-time monitoring dashboard
```

### Compliance Reports
```
/admin/compliance_report.php

Reports:
- GDPR: User data access timeline
- HIPAA: Communication audit trail
- SOX: Financial message tracking
- Custom: Configurable date range & fields
- Export: CSV, PDF, JSON
```

---

## Examples

### Example 1: Message Send Audit Entry
```json
{
  "id": 1001,
  "tenant_id": 1,
  "user_id": 1,
  "action": "send_message",
  "target_user_id": 19,
  "message_id": 44,
  "details": {
    "content_size": 42,
    "encrypted": true,
    "encryption_key_id": 1,
    "room_id": "u-1-19"
  },
  "ip_address": "192.168.1.100",
  "status": "success",
  "created_at": "2025-12-10 10:46:11"
}
```

### Example 2: Failed Access Audit Entry
```json
{
  "id": 1002,
  "tenant_id": 1,
  "user_id": 5,
  "action": "send_message",
  "target_user_id": 1,
  "details": {
    "reason": "user_blocked_by_recipient"
  },
  "ip_address": "192.168.1.105",
  "status": "denied",
  "error_message": "User 1 has blocked you",
  "created_at": "2025-12-10 11:21:00"
}
```

### Example 3: Encryption Operation Audit Entry
```json
{
  "id": 1003,
  "tenant_id": 1,
  "user_id": 1,
  "action": "encrypt_message",
  "message_id": 44,
  "details": {
    "algorithm": "aes-256-cbc",
    "key_id": 1,
    "iv_generated": true,
    "content_size": 42,
    "encrypted_size": 64
  },
  "status": "success",
  "created_at": "2025-12-10 10:46:10"
}
```

---

## Queries

### Get All Messages Sent By User
```sql
SELECT * FROM chat_audit_log 
WHERE user_id = 1 
AND action = 'send_message' 
AND created_at BETWEEN '2025-12-01' AND '2025-12-31'
ORDER BY created_at DESC;
```

### Get Failed Access Attempts
```sql
SELECT * FROM chat_audit_log 
WHERE status = 'denied' 
AND action IN ('send_message', 'read_message')
ORDER BY created_at DESC;
```

### Compliance Report: GDPR
```sql
SELECT user_id, action, COUNT(*) as count, MIN(created_at) as first_action, MAX(created_at) as last_action
FROM chat_audit_log 
WHERE tenant_id = 1 
AND action IN ('send_message', 'read_message', 'access_denied')
GROUP BY user_id, action
ORDER BY last_action DESC;
```

---

## Performance Considerations

- **Log volume:** ~10-20 entries per message (send, read, encryption, etc.)
- **Retention:** Archive after 90 days
- **Indexes:** On tenant_id, user_id, action, created_at
- **Archival:** Move old logs to separate archive table

---

## Timeline

| Task | Duration | Start | End |
|------|----------|-------|-----|
| Design & Planning | 30 min | Now | +30m |
| Audit class implementation | 30 min | +30m | +60m |
| Database migration | 10 min | +60m | +70m |
| API updates | 45 min | +70m | +115m |
| Admin interface | 30 min | +115m | +145m |
| Testing & QA | 15 min | +145m | +160m |
| **TOTAL** | **~2.5h** | | |

---

## Files to Create

```
includes/
  ├── ChatAudit.php (new)
  
api/
  ├── audit_logs.php (new - for admin viewing)
  ├── compliance_export.php (new - for reports)
  
admin/
  ├── audit_logs.php (new - admin UI)
  ├── compliance_report.php (new - compliance UI)
  
migrations/
  ├── 004_audit_logging.sql (new)
```

---

## After Phase 4

Once complete, you'll have:
✅ Complete audit trail of all chat operations
✅ Compliance reporting (GDPR, HIPAA, SOX)
✅ Security investigation capabilities
✅ User accountability tracking
✅ Ready for Phase 5 (Rate Limiting)

---

## Next: Phase 5

After Phase 4, we move to:
- **Phase 5: Rate Limiting** - Prevent spam, brute force, DDoS
  - Message rate limits
  - Contact discovery limits
  - IP blocking
  - Request throttling

---

## Phase 4 Complete! ✅

### What Was Done:

1. ✅ Created the `ChatAudit` class (includes/ChatAudit.php)
2. ✅ Generated database migration (migrations/004_audit_logging.sql)
3. ✅ Updated all APIs (api/messages.php, api/chat_prefs.php)
4. ✅ Built admin interfaces (audit_logs.php, compliance_report.php)
5. ✅ Complete documentation and examples

### Next: Get Started
1. Visit http://localhost/mtravels/apply_migration_004.php to apply database changes
2. Visit http://localhost/mtravels/test_audit.php to test the system
3. Visit http://localhost/mtravels/admin/audit_logs.php to view logs
4. Read PHASE_4_FIXES.md for setup instructions
