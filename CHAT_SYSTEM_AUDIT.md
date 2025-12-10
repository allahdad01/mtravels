# Chat System Implementation Audit
## Tenant & Branch Chat/Peering System Analysis

**Audit Date**: December 10, 2025  
**Report Version**: 1.0

---

## Executive Summary

Your chat system is **well-structured** for multi-tenant environments with proper tenant isolation, branch awareness, and cross-tenant peering controls. However, there is a **critical missing feature**: **branch-level chat isolation is not properly enforced** in all components.

### Key Findings:
- ✅ **Tenant-level isolation**: Properly implemented across all APIs
- ✅ **Tenant peering system**: Correctly controls cross-tenant communication
- ✅ **User privacy controls**: Block/mute functionality works correctly
- ⚠️ **Branch isolation gap**: Chat messages don't validate branch relationships
- ⚠️ **Settings scope**: Chat settings are tenant-wide, not branch-specific

---

## 1. Architecture Overview

### Current Design
```
Tenant (Company)
  ├── Branch 1
  │   ├── Users (chat peers)
  │   ├── Chat Settings (currently TENANT-WIDE)
  │   └── Tenant Peering (currently TENANT-WIDE)
  ├── Branch 2
  │   ├── Users (chat peers)
  │   ├── Chat Settings (shared with Branch 1)
  │   └── Tenant Peering (shared with Branch 1)
  └── Branch 3
      ├── Users
      ├── Chat Settings
      └── Tenant Peering
```

### What Each Component Does

| Component | Purpose | Scope |
|-----------|---------|-------|
| **chat_messages** | Peer-to-peer messaging | User pair (encrypted room_id) |
| **tenant_peering** | Cross-tenant approval | Tenant pair level |
| **user_blocks** | User privacy | Tenant + User level |
| **user_mutes** | User notifications | Tenant + User level |
| **chat_settings** | File/MIME/auto-download | **TENANT level** ⚠️ |

---

## 2. Database Schema Analysis

### 2.1 `chat_messages` Table
```sql
CREATE TABLE `chat_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(50) NOT NULL,              -- u-{min_id}-{max_id}
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `tenant_id_from` int(11) NOT NULL,           -- Sender's tenant
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL,
  `seen_at` timestamp NULL,
  `branch_id` bigint(20) DEFAULT NULL,         -- ⚠️ NOT ENFORCED
  PRIMARY KEY (`id`),
  KEY `idx_room_time` (`room_id`,`created_at`),
  KEY `idx_to_user` (`to_user_id`),
  CONSTRAINT `fk_cm_from_user` FOREIGN KEY (`from_user_id`),
  CONSTRAINT `fk_cm_to_user` FOREIGN KEY (`to_user_id`)
)
```

**Issues**:
- `branch_id` column exists but is **NOT validated in chat_messages.php**
- No unique constraint enforcing room_id uniqueness per tenant
- `tenant_id_from` only, missing `tenant_id_to` for cross-tenant chats
- No index on `tenant_id_from` + `from_user_id` for efficient filtering

### 2.2 `tenant_peering` Table ✅
```sql
CREATE TABLE `tenant_peering` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,                -- Initiating tenant
  `peer_tenant_id` int(11) NOT NULL,           -- Peer tenant
  `status` enum('approved','pending','blocked'),
  `created_at` timestamp NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL,         -- ⚠️ ONLY ONE PER TENANT PAIR
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_peer_unique` (`tenant_id`,`peer_tenant_id`),
  CONSTRAINT `fk_tp_peer` FOREIGN KEY (`peer_tenant_id`),
  CONSTRAINT `fk_tp_tenant` FOREIGN KEY (`tenant_id`)
)
```

**Issues**:
- Peering is **tenant-wide, not branch-specific**
- If Branch A of Tenant 1 wants to peer with Tenant 2, ALL branches of Tenant 1 can communicate with ALL branches of Tenant 2
- Cannot disable communication between specific branches

### 2.3 `user_blocks` & `user_mutes` Tables ✅
```sql
-- user_blocks
`id`, `tenant_id`, `user_id`, `blocked_user_id`, `created_at`, `branch_id`
UNIQUE KEY `unique_block` (`tenant_id`,`user_id`,`blocked_user_id`)

-- user_mutes
`id`, `tenant_id`, `user_id`, `muted_user_id`, `created_at`, `branch_id`
UNIQUE KEY `unique_mute` (`tenant_id`,`user_id`,`muted_user_id`)
```

**Status**: ✅ Correctly scoped to tenant + user level

---

## 3. API Endpoints Analysis

### 3.1 GET /api/messages.php - Retrieve Messages ⚠️

**Current Code**:
```php
$peerId = isset($_GET['peer_id']) ? (int)$_GET['peer_id'] : 0;
$room = room_from_users($currentUserId, $peerId);

$sql = 'SELECT id, room_id, from_user_id, to_user_id, content, created_at, seen_at 
        FROM chat_messages 
        WHERE room_id = ? 
        ORDER BY id DESC 
        LIMIT ' . $limit;
$stmt = secure_query($pdo, $sql, [$room]);
```

**Missing Validations**:
- ❌ No branch_id validation
- ❌ No verification that both users belong to allowed tenants/branches
- ❌ `$peerId` only validated as integer, not existence check

**Security Gap**: A user can retrieve messages from any peer_id if they know the room_id formula

### 3.2 POST /api/messages.php - Send Messages ✅

**Current Code**:
```php
// Check block relations (either side)
$blockedA = secure_query($pdo, '...WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ?...', 
                         [$tenantId, $currentUserId, $toUserId]);
$blockedB = secure_query($pdo, '...WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ?...', 
                         [$tenantId, $toUserId, $currentUserId]);

// Check tenant peering if cross-tenant
$peerStmt = secure_query($pdo, 'SELECT tenant_id FROM users WHERE id = ?', [$toUserId]);
$peerTenant = (int)$peer['tenant_id'];
if ($peerTenant !== $tenantId) {
    $allow = secure_query($pdo, 
        'SELECT 1 FROM tenant_peering 
         WHERE status = "approved" 
         AND ((tenant_id = ? AND peer_tenant_id = ?) OR (tenant_id = ? AND peer_tenant_id = ?)) 
         LIMIT 1', 
        [$tenantId, $peerTenant, $peerTenant, $tenantId]);
    if (!$allow || !$allow->fetch()) { 
        http_response_code(403); 
        echo json_encode(['error' => 'peer_not_allowed']); 
        exit; 
    }
}

$room = room_from_users($currentUserId, $toUserId);
$stmt = secure_query($pdo, 
    'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content) 
     VALUES (?, ?, ?, ?, ?)', 
    [$room, $currentUserId, $toUserId, $tenantId, $content]);
```

**Strengths**: ✅
- Checks if recipient exists
- Validates tenant peering (bidirectional)
- Validates block/mute relationships

**Missing**:
- ❌ No branch compatibility check
- ❌ `tenant_id_to` not stored (only `tenant_id_from`)
- ❌ No validation that recipient exists (should fetch user before peering check)

### 3.3 GET /api/contacts.php - List Available Peers ⚠️

**Current Code** (Simplified):
```php
// Load current user with tenant
$stmt = secure_query($pdo, 
    'SELECT u.id, u.tenant_id FROM users u 
     JOIN tenants t ON u.tenant_id = t.id 
     WHERE u.id = ?', [$currentUserId]);

// Allowed tenants: self + approved peers
$peerSql = 'SELECT peer_tenant_id AS peer FROM tenant_peering 
            WHERE tenant_id = ? AND status = "approved" 
            UNION 
            SELECT tenant_id AS peer FROM tenant_peering 
            WHERE peer_tenant_id = ? AND status = "approved"';
$allowedTenantIds = array_merge([$tenantId], $peerTenantIds);

// Fetch contacts from allowed tenants
$sql = 'SELECT u.id, u.role, u.name, u.tenant_id, u.profile_pic 
        FROM users u 
        WHERE u.tenant_id IN (' . $in . ') AND u.id <> ? 
        AND u.deleted_at IS NULL AND u.fired <> 1';

// Exclude blocked/muted users
```

**Status**: ✅ Correctly filters by tenant peering

**Missing**:
- ❌ No branch-level filtering
- ⚠️ If Branch A should NOT communicate with Branch B (different organizations), this can't be enforced

### 3.4 POST /admin/chat_settings.php - Configure Settings ❌

**Current Code**:
```php
$tenantId = (int)$u['tenant_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max = isset($_POST['max_file_bytes']) ? ... : 26214400;
    $pref = isset($_POST['allowed_mime_prefixes']) ? ... : 'image/,video/,...';
    $auto = isset($_POST['default_auto_download']) ? 1 : 0;

    secure_query($pdo,
        'UPDATE tenants 
         SET chat_max_file_bytes = ?, chat_allowed_mime_prefixes = ?, chat_default_auto_download = ? 
         WHERE id = ?',
        [$max, $pref, $auto, $tenantId]
    );
}

// Load current settings
$sStmt = secure_query($pdo,
    'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download 
     FROM tenants 
     WHERE id = ?',
    [$tenantId]
);
```

**Critical Issues**: ❌
- Settings are stored in `tenants` table, affecting **ALL branches**
- No branch_id validation
- All branches must use same file size limits
- Cannot set different policies per branch
- UI doesn't show which branch's settings are being modified

### 3.5 POST /admin/tenant_peering.php - Manage Peering ⚠️

**Current Code**:
```php
$currentBranchId = $u ? (int)$u['branch_id'] : 0;

// Handle create/update peering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
    $peer_tenant_id = isset($_POST['peer_tenant_id']) ? (int)$_POST['peer_tenant_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
    
    if ($tenant_id > 0 && $peer_tenant_id > 0 && $tenant_id !== $peer_tenant_id) {
        $sql = 'INSERT INTO tenant_peering 
                (tenant_id, peer_tenant_id, status, branch_id) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE status = VALUES(status)';
        secure_query($pdo, $sql, [$tenant_id, $peer_tenant_id, $status, $currentBranchId]);
    }
}

// Load peerings (only those involving my tenant and branch)
$peeringsSql = '...WHERE (tp.tenant_id = ? OR tp.peer_tenant_id = ?) AND tp.branch_id = ?...';
$peeringsStmt = secure_query($pdo, $peeringsSql, [$currentTenantId, $currentTenantId, $currentBranchId]);
```

**Mixed Approach** ⚠️:
- `branch_id` is stored but **only filters display**
- When creating peering, it saves `currentBranchId`
- But `tenant_peering` unique key is `(tenant_id, peer_tenant_id)` - **NOT** including `branch_id`
- This means **only ONE peering can exist per tenant pair**, regardless of branches

**Problem**:
```
Tenant A (multiple branches):
  - Branch 1 might create peering with Tenant B
  - Branch 2 can't create separate peering (duplicate key constraint)
  - Both branches forced to use same peering relationship
```

---

## 4. Chat Settings Storage Issues

### Current Tenants Table Schema (Relevant Columns):
```sql
CREATE TABLE `tenants` (
  `id` int(11),
  `chat_max_file_bytes` bigint(20),              -- ⚠️ TENANT-WIDE
  `chat_allowed_mime_prefixes` text,             -- ⚠️ TENANT-WIDE
  `chat_default_auto_download` tinyint(1),       -- ⚠️ TENANT-WIDE
  ...
)
```

### Problem:
- All branches must share the same chat file size limits
- All branches must allow/disallow the same MIME types
- Cannot enforce stricter policies on sensitive branches

### Solution Required:
Create `branch_chat_settings` table or move settings to branch level:
```sql
CREATE TABLE `branch_chat_settings` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL UNIQUE,
  `chat_max_file_bytes` bigint(20) DEFAULT 26214400,
  `chat_allowed_mime_prefixes` text DEFAULT 'image/,video/,audio/,application/pdf,text/',
  `chat_default_auto_download` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
)
```

---

## 5. Security Assessment

### ✅ Properly Secured
1. **Tenant isolation** - All queries filter by `tenant_id`
2. **Block/mute relationships** - Enforced at tenant + user level
3. **Cross-tenant peering** - Bidirectional approval required
4. **Message ownership** - Edit/delete only allowed by sender
5. **Session authentication** - All endpoints check `$_SESSION['user_id']`
6. **Prepared statements** - `secure_query()` used throughout

### ❌ Security Gaps
1. **Branch isolation missing** - No validation in `messages.php` GET/POST
2. **Room ID enumeration** - Attacker can theoretically brute-force room_ids
3. **No rate limiting** - Can spam contact list API
4. **No message encryption** - Content stored in plaintext
5. **Recipient validation missing** - Can send to non-existent users (silently fails at insert)

---

## 6. Issue Checklist

### Critical Issues (Must Fix)
- [ ] `messages.php` GET - Add branch validation
- [ ] `messages.php` POST - Validate recipient exists + branch compatibility
- [ ] `tenant_peering.php` - Make branch_id part of unique key or branch-specific
- [ ] Chat settings - Move to branch level, not tenant-wide

### High Priority
- [ ] Add `tenant_id_to` to `chat_messages` for cross-tenant tracking
- [ ] Add index on `(tenant_id_from, from_user_id)` for performance
- [ ] Validate branch_id in all chat APIs
- [ ] Add branch filtering to `contacts.php`

### Medium Priority
- [ ] Rate limiting on message send/contact fetch
- [ ] Message encryption (at rest)
- [ ] Room ID should include tenant hash to prevent enumeration
- [ ] Add API rate limiting headers

### Low Priority
- [ ] Add message search endpoint with full-text search
- [ ] Message pagination improvements
- [ ] Typing indicators implementation (WebSocket)
- [ ] Read receipts at message level

---

## 7. Recommended Database Changes

### Add Missing Indexes
```sql
-- Improve message retrieval performance
ALTER TABLE `chat_messages` ADD INDEX `idx_tenant_user` (`tenant_id_from`, `from_user_id`);
ALTER TABLE `chat_messages` ADD INDEX `idx_to_tenant_user` (`to_user_id`, `tenant_id_from`);

-- Improve peering lookups
ALTER TABLE `tenant_peering` ADD INDEX `idx_status` (`status`);
```

### Create Branch-Level Settings
```sql
CREATE TABLE `branch_chat_settings` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `chat_max_file_bytes` bigint(20) DEFAULT 26214400,
  `chat_allowed_mime_prefixes` text DEFAULT 'image/,video/,audio/,application/pdf,text/',
  `chat_default_auto_download` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `tenant_branch` (`tenant_id`, `branch_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
);
```

### Refactor Tenant Peering for Branch Support
```sql
-- If wanting true branch-level peering:
ALTER TABLE `tenant_peering` 
  DROP UNIQUE KEY `tenant_peer_unique`,
  ADD UNIQUE KEY `tenant_peer_branch_unique` (`tenant_id`, `peer_tenant_id`, `branch_id`);

-- Or create new table for branch-level peering:
CREATE TABLE `branch_peering` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `peer_branch_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `status` enum('approved','pending','blocked') DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `branch_peer_unique` (`branch_id`, `peer_branch_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`peer_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
);
```

---

## 8. Code Audit Results by File

### chat.php - Frontend ✅
- Modern responsive design
- Proper authentication check
- Good UX with theme switching
- Status: No security issues found

### api/messages.php - Message Handler ⚠️
**Issues Found**:
1. No branch validation on GET
2. No branch validation on POST
3. Missing recipient validation
4. `tenant_id_to` not stored

### api/contacts.php - Contact List ⚠️
**Issues Found**:
1. No branch filtering
2. Blocks/mutes work correctly
3. Peering works correctly

### api/chat_settings.php - Settings Fetch ❌
**Issues Found**:
1. Fetches from `tenants` table (tenant-wide)
2. No branch awareness

### api/chat_prefs.php - User Preferences ✅
**Status**: Correctly implemented
- Block/mute operations work per tenant + user
- Proper validation

### admin/chat_settings.php - Settings Admin ❌
**Critical Issues**:
1. Updates entire tenant settings
2. No branch selector
3. No warning about affecting all branches
4. UI doesn't show branch context

### admin/tenant_peering.php - Peering Admin ⚠️
**Issues**:
1. Stores `branch_id` but doesn't enforce it in unique key
2. Filtering works for display only
3. Can't set branch-specific peering

---

## 9. Implementation Priority & Effort Estimate

| Task | Priority | Effort | Impact |
|------|----------|--------|--------|
| Add branch validation to messages.php | Critical | 2 hrs | Prevents cross-branch abuse |
| Create branch_chat_settings table | Critical | 3 hrs | Enables branch-specific policies |
| Migrate chat settings to branch level | High | 4 hrs | Full branch isolation |
| Add indexes to chat_messages | High | 1 hr | Performance improvement |
| Refactor tenant_peering for branches | High | 5 hrs | Branch-level control |
| Add branch filtering to contacts.php | High | 1 hr | Data isolation |
| Add message encryption | Medium | 6 hrs | Data security |
| Room ID enumeration protection | Medium | 2 hrs | Security hardening |

**Total Effort**: ~24 hours of development

---

## 10. Recommendations

### Immediate Actions (This Week)
1. Add branch validation to all chat API endpoints
2. Create audit logging for chat operations
3. Document current chat system architecture
4. Add warning when editing tenant-wide settings

### Short-term (Next 2 Weeks)
1. Create `branch_chat_settings` table
2. Migrate admin/chat_settings.php to branch-aware
3. Add message encryption (optional but recommended)
4. Implement rate limiting

### Long-term (Next Month)
1. Implement WebSocket for typing indicators
2. Add full-text message search
3. Message archives/export functionality
4. Chat analytics (message volume, active users, etc.)

---

## 11. Testing Recommendations

### Branch Isolation Testing
```
Test Case 1: Users from different branches can't chat
- Create Tenant A with Branch 1 and Branch 2
- Create User U1 in Branch 1, User U2 in Branch 2
- U1 attempts to send message to U2
- Expected: Should be blocked or isolated by branch
- Current Result: ❌ Likely succeeds (no validation)
```

```
Test Case 2: Branch-specific settings apply
- Branch 1 sets max_file_bytes = 5MB
- Branch 2 sets max_file_bytes = 50MB
- User in Branch 1 attempts upload > 5MB
- Expected: Rejected
- Current Result: ⚠️ Applied tenant-wide
```

```
Test Case 3: Tenant peering respects branches
- Tenant A (2 branches) peers with Tenant B
- User from A.Branch1 to B.AnyBranch
- Expected: Can configure per branch
- Current Result: ⚠️ Tenant-wide only
```

---

## 12. Conclusion

Your chat system has a **solid foundation** with proper tenant isolation and security practices. However, **branch-level isolation is incomplete**, which could lead to:

1. **Unintended cross-branch communication** if branches should be isolated
2. **Shared settings** preventing granular control per branch  
3. **Peering conflicts** if multiple branches need different peer relationships

**Recommended Next Step**: Schedule a 2-3 hour working session to implement branch validation in `messages.php` and create the `branch_chat_settings` table. This will address the most critical gaps.

---

**Report Generated**: 2025-12-10
**Auditor**: AI Code Analysis System
**Status**: Ready for Implementation
