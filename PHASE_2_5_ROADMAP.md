# Chat System - Phase 2-5 Roadmap

**Current Status**: Phase 1 Complete ✅ (Critical fixes deployed)  
**Next Phase**: Phase 2 (2-3 weeks out)

---

## Phase 2: Branch-Level Peering (4-5 hours)

### Overview
Enable branches to have **independent peering relationships** with other tenants.

**Current State**:
- Peering is **tenant-wide**
- If Tenant A peers with Tenant B, ALL branches of A can chat with ALL branches of B
- Cannot isolate communication between specific branches

**Desired State**:
- Each branch can have its own peering relationships
- A.Branch1 might peer with B.Sales, but A.Branch2 doesn't
- A.Finance can block B.Support while A.Operations approves it

### Implementation

#### Database Changes
```sql
-- Already in migration (commented out)
-- Uncomment and run to enable branch-level peering

CREATE TABLE `branch_peering` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `peer_branch_id` int(11) DEFAULT NULL,
  `status` enum('approved','pending','blocked') DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `branch_peer_unique` (`branch_id`, `peer_branch_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`peer_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
);
```

#### Code Changes Required

**1. `/api/messages.php` - Update peering check**
```php
// OLD (lines 71-73):
if ($peerTenant !== $tenantId) {
    $allow = secure_query($pdo, '..tenant_peering..', [$tenantId, $peerTenant]);
}

// NEW:
if ($peerTenant !== $tenantId) {
    // Check both tenant-level and branch-level peering
    $allow = secure_query($pdo, 
        'SELECT 1 FROM branch_peering 
         WHERE (branch_id = ? AND peer_branch_id = ?)
         OR (branch_id = ? AND peer_branch_id = ?)
         AND status = "approved" LIMIT 1',
        [$myBranch, $peerBranch, $peerBranch, $myBranch]);
}
```

**2. `/api/contacts.php` - Update contact filtering**
```php
// Filter by branch peering if enabled
if ($peerTenant !== $tenantId) {
    // Check if branch-level peering approved
    $peeringStmt = secure_query($pdo,
        'SELECT 1 FROM branch_peering 
         WHERE (branch_id = ? AND peer_branch_id = ?) 
         AND status = "approved" LIMIT 1',
        [$myBranch, $r['branch_id']]);
    
    if (!$peeringStmt || !$peeringStmt->fetch()) {
        continue; // Skip this contact
    }
}
```

**3. New Admin Page: `/admin/branch_peering.php`**
- Similar to tenant_peering.php but for branches
- Show peering requests per branch
- Approve/block/delete branch-level peering
- Estimated: 150 lines of code

#### Features
- ✅ Each branch independently manages peering
- ✅ Branch1 can approve Tenant B while Branch2 blocks it
- ✅ Granular control over cross-branch communication
- ✅ Maintain backwards compatibility with tenant-level peering

#### Testing
```bash
# Test 1: Branch-specific peering
User A.Branch1 → User B.Branch2 (peering approved)
Expected: Messages work
User A.Branch2 → User B.Branch1 (peering NOT approved)
Expected: 403 error

# Test 2: Mixed peering (tenant + branch)
Both tenant and branch peering must be approved
If either is blocked, communication fails
```

#### Effort Estimate
- Database: 30 min (uncomment + test)
- API updates: 1.5 hours (messages.php, contacts.php)
- Admin UI: 1.5 hours (new branch_peering.php)
- Testing: 1 hour
- **Total: 4-5 hours**

#### Priority
🔴 **HIGH** - Solves branch isolation for peering  
**Effort**: 4-5 hours  
**Impact**: Enables branch-specific communication control  
**Complexity**: Medium (schema ready, needs API updates)

---

## Phase 3: Message Encryption (6-8 hours)

### Overview
Encrypt messages **at rest** in the database for security/compliance.

**Current State**:
- Messages stored in plaintext
- Database admin can read all messages
- No HIPAA/GDPR compliance for sensitive data

**Desired State**:
- Messages encrypted before storage
- Decrypted only when retrieved by authorized user
- Encryption keys managed separately from data
- Admin cannot read message content

### Implementation

#### Database Changes
```sql
-- Add encryption fields to chat_messages
ALTER TABLE `chat_messages` 
ADD COLUMN `encrypted_content` longblob AFTER `content`,
ADD COLUMN `encryption_key_id` int(11) AFTER `encrypted_content`,
ADD COLUMN `is_encrypted` tinyint(1) DEFAULT 0 AFTER `encryption_key_id`;

-- Create encryption keys table
CREATE TABLE `encryption_keys` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `algorithm` varchar(50) DEFAULT 'aes-256-cbc',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `rotated_at` timestamp NULL,
  `status` enum('active','retired') DEFAULT 'active',
  UNIQUE KEY `tenant_key_name` (`tenant_id`, `key_name`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
);
```

#### Code Changes Required

**1. Helper class: `/includes/MessageEncryption.php` (NEW)**
```php
class MessageEncryption {
    private $pdo;
    private $keys = [];
    
    public function encrypt($content, $tenantId, $keyId = null) {
        $key = $this->getKey($tenantId, $keyId);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($content, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encryptedContent, $tenantId, $keyId) {
        $key = $this->getKey($tenantId, $keyId);
        $data = base64_decode($encryptedContent);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }
    
    private function getKey($tenantId, $keyId) {
        // Fetch from key management system
        // Could be AWS KMS, HashiCorp Vault, etc.
    }
}
```

**2. Update `/api/messages.php`**
```php
// On INSERT (POST)
$cipher = new MessageEncryption($pdo);
$encryptedContent = $cipher->encrypt($content, $tenantId, $activeKeyId);

secure_query($pdo,
    'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, 
     encrypted_content, encryption_key_id, is_encrypted) 
     VALUES (?, ?, ?, ?, ?, ?, 1)',
    [$room, $currentUserId, $toUserId, $tenantId, $encryptedContent, $activeKeyId]);

// On SELECT (GET)
$rows = $stmt->fetchAll();
foreach ($rows as &$row) {
    if ($row['is_encrypted']) {
        $row['content'] = $cipher->decrypt($row['encrypted_content'], $tenantId, $row['encryption_key_id']);
    }
}
```

**3. Migration script: `/includes/migrate_to_encrypted.php`**
- Encrypt all existing messages
- Run in background job
- Mark as completed
- Estimated: 200 lines

#### Features
- ✅ End-to-end encryption at rest
- ✅ Key rotation support
- ✅ Per-tenant encryption keys
- ✅ Backwards compatible (is_encrypted flag)
- ✅ Optional admin key management

#### Key Management Options
1. **Database Keys** (Simple, less secure)
   - Store keys in separate encrypted column
   - Suitable for internal use

2. **AWS KMS** (Production)
   - Keys managed by AWS
   - Audit logging
   - Automatic key rotation

3. **HashiCorp Vault** (Enterprise)
   - Centralized secret management
   - Access control policies
   - Dynamic secrets

#### Testing
```bash
# Test 1: Encryption on insert
POST /api/messages.php with message content
Verify: encrypted_content in DB is not readable plaintext
Verify: is_encrypted = 1

# Test 2: Decryption on retrieve
GET /api/messages.php
Verify: content decrypted correctly
Verify: same as original message

# Test 3: Key rotation
Rotate encryption key
Verify: new messages use new key
Verify: old messages still decrypt with old key

# Test 4: Unauthorized access
Query database directly (as admin)
Verify: message content is encrypted (unreadable)
```

#### Effort Estimate
- Design encryption strategy: 1 hour
- Create MessageEncryption class: 2 hours
- Update APIs: 1.5 hours
- Migration script: 1 hour
- Key management integration: 1.5 hours
- Testing: 1 hour
- **Total: 6-8 hours** (less if using DB keys)

#### Priority
🟠 **MEDIUM** - Improves compliance/security  
**Effort**: 6-8 hours  
**Impact**: HIPAA/GDPR compliance  
**Complexity**: High (cryptography involved)  
**Timing**: After Phase 2 (next 4-6 weeks)

---

## Phase 4: Audit Logging (2-3 hours)

### Overview
Log all chat operations for compliance and troubleshooting.

**Current State**:
- Activity logs exist for general operations
- Chat operations not logged
- Cannot track who sent what message when

**Desired State**:
- Every message send/delete logged
- Every block/mute logged
- Every settings change logged
- Can trace entire user communication history

### Implementation

#### Database Changes
```sql
-- Existing activity_log table is sufficient
-- Just need to log chat operations

-- Sample entries:
INSERT INTO activity_log 
(tenant_id, branch_id, user_id, action, table_name, record_id, new_values, created_at)
VALUES
(1, 1, 5, 'CREATE', 'chat_messages', 123, '{"to_user_id":8,"content":"..."}', NOW()),
(1, 1, 5, 'DELETE', 'chat_messages', 123, '{"reason":"user_request"}', NOW()),
(1, 1, 5, 'CREATE', 'user_blocks', 45, '{"blocked_user_id":9}', NOW());
```

#### Code Changes Required

**1. Helper function: `/includes/ChatAudit.php` (NEW)**
```php
class ChatAudit {
    public static function logMessageSent($pdo, $tenantId, $branchId, $userId, $messageId, $toUserId, $content) {
        secure_query($pdo,
            'INSERT INTO activity_log (tenant_id, branch_id, user_id, action, table_name, record_id, new_values)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tenantId, $branchId, $userId, 'CREATE', 'chat_messages', $messageId, 
             json_encode(['to_user_id' => $toUserId, 'content_length' => strlen($content)])]
        );
    }
    
    public static function logMessageDeleted($pdo, $tenantId, $branchId, $userId, $messageId) {
        secure_query($pdo,
            'INSERT INTO activity_log (tenant_id, branch_id, user_id, action, table_name, record_id)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $branchId, $userId, 'DELETE', 'chat_messages', $messageId]
        );
    }
}
```

**2. Update `/api/messages.php`**
```php
// After INSERT
ChatAudit::logMessageSent($pdo, $tenantId, $myBranch, $currentUserId, $id, $toUserId, $content);

// After DELETE
ChatAudit::logMessageDeleted($pdo, $tenantId, $myBranch, $currentUserId, $messageId);
```

**3. Update `/api/chat_prefs.php`**
```php
// After blocking/muting
ChatAudit::logUserAction($pdo, $tenantId, $branchId, $currentUserId, 
    'CREATE', 'user_blocks', $targetId, $action);
```

**4. Update `/admin/chat_settings.php`**
```php
// After settings update
ChatAudit::logSettingsChange($pdo, $tenantId, $selectedBranch, $uid, $oldSettings, $newSettings);
```

#### Audit Log Queries

```sql
-- Who sent messages to whom?
SELECT u1.name as sender, u2.name as recipient, al.created_at
FROM activity_log al
JOIN users u1 ON al.user_id = u1.id
JOIN activity_log al2 ON al.new_values LIKE CONCAT('%"to_user_id":', u2.id, '%')
WHERE al.action = 'CREATE' AND al.table_name = 'chat_messages'
ORDER BY al.created_at DESC;

-- What blocks happened?
SELECT u1.name as blocker, u2.name as blocked, al.created_at
FROM activity_log al
JOIN users u1 ON al.user_id = u1.id
WHERE al.action = 'CREATE' AND al.table_name = 'user_blocks'
ORDER BY al.created_at DESC;

-- When were settings changed?
SELECT * FROM activity_log
WHERE table_name = 'branch_chat_settings'
ORDER BY created_at DESC;
```

#### Features
- ✅ Complete audit trail of all chat operations
- ✅ User accountability (who did what)
- ✅ Compliance reporting (GDPR, HIPAA)
- ✅ Troubleshooting aid
- ✅ Security incident investigation

#### Testing
```bash
# Test 1: Message logging
Send message → Check activity_log has entry
Verify: action='CREATE', table_name='chat_messages'

# Test 2: Block logging
Block user → Check activity_log has entry
Verify: action='CREATE', table_name='user_blocks'

# Test 3: Settings logging
Change settings → Check activity_log has entry
Verify: includes old and new values

# Test 4: Query audit trail
Run: SELECT * FROM activity_log WHERE table_name='chat_messages'
Verify: Can reconstruct user communication history
```

#### Effort Estimate
- Create ChatAudit helper class: 45 min
- Add logging to APIs: 1 hour
- Add logging to admin: 30 min
- Testing: 30 min
- **Total: 2.5-3 hours**

#### Priority
🟡 **MEDIUM** - Compliance/troubleshooting  
**Effort**: 2-3 hours  
**Impact**: Audit trail, compliance  
**Complexity**: Low (straightforward logging)  
**Timing**: After Phase 2 (1-2 weeks)

---

## Phase 5: Rate Limiting & Security (3-4 hours)

### Overview
Prevent spam, brute force attacks, and resource abuse.

**Current State**:
- No rate limiting on API calls
- Users can spam messages
- Attackers can brute-force room IDs
- No protection against DDoS

**Desired State**:
- Rate limits on message sending
- Rate limits on API calls
- Room ID enumeration protection
- Request throttling

### Implementation

#### Database Changes
```sql
-- Create rate limiting table
CREATE TABLE `api_rate_limits` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `request_count` int(11) DEFAULT 0,
  `reset_at` timestamp NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_endpoint` (`user_id`, `endpoint`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

-- Create blocked IPs table
CREATE TABLE `api_blocked_ips` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL UNIQUE,
  `reason` varchar(255),
  `blocked_until` timestamp NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);
```

#### Code Changes Required

**1. Helper class: `/includes/RateLimiter.php` (NEW)**
```php
class RateLimiter {
    private $pdo;
    
    public function checkLimit($userId, $endpoint, $maxRequests = 100, $windowSeconds = 60) {
        // Get current request count
        $stmt = secure_query($this->pdo,
            'SELECT request_count, reset_at FROM api_rate_limits 
             WHERE user_id = ? AND endpoint = ?',
            [$userId, $endpoint]
        );
        $record = $stmt ? $stmt->fetch() : null;
        
        $now = time();
        $resetAt = $record ? strtotime($record['reset_at']) : 0;
        
        if ($now > $resetAt) {
            // Reset window
            $resetAt = $now + $windowSeconds;
            secure_query($this->pdo,
                'INSERT INTO api_rate_limits (user_id, endpoint, request_count, reset_at)
                 VALUES (?, ?, 1, FROM_UNIXTIME(?))
                 ON DUPLICATE KEY UPDATE request_count = 1, reset_at = FROM_UNIXTIME(?)',
                [$userId, $endpoint, $resetAt, $resetAt]
            );
            return true;
        }
        
        if ($record['request_count'] >= $maxRequests) {
            return false; // Rate limit exceeded
        }
        
        // Increment
        secure_query($this->pdo,
            'UPDATE api_rate_limits SET request_count = request_count + 1 
             WHERE user_id = ? AND endpoint = ?',
            [$userId, $endpoint]
        );
        return true;
    }
    
    public function blockIP($ip, $reason, $durationSeconds = 3600) {
        secure_query($this->pdo,
            'INSERT INTO api_blocked_ips (ip_address, reason, blocked_until)
             VALUES (?, ?, FROM_UNIXTIME(?))
             ON DUPLICATE KEY UPDATE blocked_until = FROM_UNIXTIME(?)',
            [$ip, $reason, time() + $durationSeconds, time() + $durationSeconds]
        );
    }
    
    public function isIPBlocked($ip) {
        $stmt = secure_query($this->pdo,
            'SELECT 1 FROM api_blocked_ips 
             WHERE ip_address = ? AND blocked_until > NOW()',
            [$ip]
        );
        return $stmt && $stmt->fetch() ? true : false;
    }
}
```

**2. Update API endpoints with rate limiting**
```php
// Top of /api/messages.php
$limiter = new RateLimiter($pdo);
$clientIP = $_SERVER['REMOTE_ADDR'];

// Check IP not blocked
if ($limiter->isIPBlocked($clientIP)) {
    http_response_code(429); // Too Many Requests
    echo json_encode(['error' => 'ip_blocked']);
    exit;
}

// Check rate limit
if (!$limiter->checkLimit($currentUserId, 'messages_post', 100, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limit_exceeded', 'retry_after' => 60]);
    exit;
}

// ... rest of code
```

**3. Protect room ID enumeration**
```php
// Instead of user ID in URL, use hashed room ID
// /api/messages.php?room_id=abc123def456 (hashed, not predictable)

function generateRoomToken($userId1, $userId2, $secret) {
    $roomId = min($userId1, $userId2) . '-' . max($userId1, $userId2);
    return hash_hmac('sha256', $roomId, $secret);
}
```

#### Rate Limit Rules

| Endpoint | Limit | Window | Reason |
|----------|-------|--------|--------|
| POST /api/messages.php | 100 | 60s | Prevent message spam |
| GET /api/contacts.php | 30 | 60s | Prevent contact scraping |
| GET /api/messages.php | 200 | 60s | Normal operation |
| POST /api/chat_prefs.php | 50 | 60s | Prevent block spam |

#### IP Blocking Rules
- 10 failed auth attempts → block 1 hour
- 50 rate limit violations → block 6 hours
- Manual: admin can block specific IPs

#### Features
- ✅ Prevent message spam
- ✅ Prevent contact enumeration
- ✅ Prevent brute force attacks
- ✅ Protect against DDoS
- ✅ Per-user, per-endpoint limits

#### Testing
```bash
# Test 1: Message spam prevention
Send 101 messages in 60 seconds
Result: 429 Too Many Requests (after 100)

# Test 2: Contact scraping prevention
Call contacts.php 31 times in 60 seconds
Result: 429 (after 30)

# Test 3: IP blocking
10 failed auth attempts from IP
Result: IP blocked, returns 429

# Test 4: Rate limit headers
Check response headers include:
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 50
X-RateLimit-Reset: 1234567890
```

#### Effort Estimate
- Create RateLimiter class: 1.5 hours
- Add to API endpoints: 1 hour
- IP blocking logic: 45 min
- Testing: 30 min
- **Total: 3-4 hours**

#### Priority
🟡 **MEDIUM** - Security hardening  
**Effort**: 3-4 hours  
**Impact**: DDoS/spam prevention  
**Complexity**: Medium (distributed rate limiting is harder)  
**Timing**: After Phase 4 (2-3 weeks)

---

## Implementation Timeline

```
Week 1-2: Phase 1 ✅ COMPLETE
  ✅ Critical fixes deployed
  ✅ Testing & monitoring

Week 3-4: Phase 2 (Branch-Level Peering)
  - Estimated: 4-5 hours
  - Effort: Medium
  - Priority: High
  
Week 5: Phase 3 (Message Encryption)
  - Estimated: 6-8 hours
  - Effort: High
  - Priority: Medium
  
Week 6: Phase 4 (Audit Logging)
  - Estimated: 2-3 hours
  - Effort: Low
  - Priority: Medium
  
Week 7: Phase 5 (Rate Limiting)
  - Estimated: 3-4 hours
  - Effort: Medium
  - Priority: Medium
```

**Total Phase 2-5**: ~18-24 hours of development

---

## Prioritization Matrix

| Phase | Effort | Impact | Priority | Complexity | When |
|-------|--------|--------|----------|-----------|------|
| 2: Branch Peering | 4-5h | High | 🔴 HIGH | Medium | Next |
| 3: Encryption | 6-8h | High | 🟠 MEDIUM | High | +2w |
| 4: Audit Log | 2-3h | Medium | 🟡 MEDIUM | Low | +3w |
| 5: Rate Limit | 3-4h | High | 🟡 MEDIUM | Medium | +4w |

---

## Recommendation

**Start with Phase 2** (Branch Peering):
- ✅ High impact (solves organizational isolation)
- ✅ Medium effort (manageable scope)
- ✅ Schema already ready (uncomment table)
- ✅ Builds on Phase 1 foundation

**Then Phase 3** (Encryption):
- For compliance/security if needed
- Can use simple DB keys initially
- Upgrade to KMS later

**Then Phase 4** (Audit Logging):
- Quick win (2-3 hours)
- Essential for compliance
- Helps with troubleshooting

**Then Phase 5** (Rate Limiting):
- Security hardening
- Important for production
- Can be added incrementally

---

**Status**: Ready to plan Phase 2  
**Next**: Schedule 1-hour design session for branch peering
