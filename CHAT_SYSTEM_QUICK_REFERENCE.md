# Chat System - Quick Reference Guide

## System Overview

```
Tenant (e.g., Company XYZ)
├── Branch 1 (Headquarters)
│   ├── Users: U1, U2, U3
│   └── Settings: Max 50MB files
├── Branch 2 (Regional Office)  
│   ├── Users: U4, U5, U6
│   └── Settings: Max 10MB files (⚠️ conflicts with Branch 1)
└── Tenant Peering
    └── Can peer with Tenant ABC (⚠️ applies to ALL branches)
```

---

## Component Checklist

| Component | Status | Tenant Isolated | Branch Isolated | Notes |
|-----------|--------|-----------------|-----------------|-------|
| **chat_messages** | ⚠️ | ✅ YES (implicit) | ❌ NO | Room ID based, no branch validation |
| **tenant_peering** | ⚠️ | ✅ YES | ❌ NO | Tenant-pair level only |
| **user_blocks** | ✅ | ✅ YES | ✅ YES | Per (tenant, user) pair |
| **user_mutes** | ✅ | ✅ YES | ✅ YES | Per (tenant, user) pair |
| **chat_settings** | ❌ | ✅ YES | ❌ NO | Stored at tenant level |

---

## Critical Issues (Must Fix)

### 1. Branch Validation Missing in messages.php
```
File: /api/messages.php
Lines: 26-43 (GET), 46-82 (POST)

Problem: No branch_id validation
Impact: Users might access messages from wrong branch

Fix:
GET /api/messages.php:
  + Verify peer_id belongs to allowed branch
  + Check branch compatibility

POST /api/messages.php:
  + Verify to_user_id is in same/peered branch
  + Validate against branch chat settings
```

### 2. Chat Settings are Tenant-Wide
```
File: /admin/chat_settings.php
Table: tenants (chat_max_file_bytes, etc.)

Problem: All branches share same file size limit
Impact: Can't enforce different policies per branch

Fix:
Create branch_chat_settings table
Migrate settings from tenants to branches
Update /admin/chat_settings.php UI
```

### 3. Tenant Peering Can't Be Branch-Specific
```
File: /admin/tenant_peering.php
Table: tenant_peering (unique key: tenant_id, peer_tenant_id)

Problem: Only one peering per tenant pair
Impact: A.Branch1 can't have different peering than A.Branch2

Fix:
Option A: Add branch_id to unique key
Option B: Create separate branch_peering table
```

---

## Files Requiring Changes

### Priority 1 (Critical)
```
/api/messages.php
- Add branch validation to GET (line 26-43)
- Add branch validation to POST (line 57-74)
- Add recipient existence check
- Validate against branch settings

/admin/chat_settings.php
- Create UI for selecting branch
- Show current branch context
- Add warning about branch vs tenant settings
```

### Priority 2 (High)
```
Database Schema:
- Create branch_chat_settings table
- Add indexes to chat_messages
- Consider branch_peering table

/api/contacts.php
- Add branch filtering (line 38-48)
- Document branch isolation behavior

/admin/tenant_peering.php
- Clarify whether peering is branch or tenant level
- Update UI accordingly
```

### Priority 3 (Medium)
```
/api/chat_prefs.php
- Document branch scope
- Consider branch-specific mutes/blocks

/api/chat_settings.php
- Update to use branch settings if implemented
- Cache settings in session

Chat frontend (/chat.php)
- Show branch context in UI
- Prevent cross-branch contact selection
```

---

## Database Queries to Review

### ✅ Correct Queries

```php
// Block check - correctly scoped
SELECT 1 FROM user_blocks 
WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ?

// Peering check - correctly bidirectional  
SELECT 1 FROM tenant_peering 
WHERE status = "approved" 
AND ((tenant_id = ? AND peer_tenant_id = ?) 
     OR (tenant_id = ? AND peer_tenant_id = ?))
```

### ⚠️ Queries Missing Branch Validation

```php
// ❌ Missing branch_id check
SELECT * FROM chat_messages 
WHERE room_id = ?

// ❌ Missing branch filtering
SELECT u.id, u.name FROM users u 
WHERE u.tenant_id IN (...)

// ❌ Missing branch for settings
SELECT chat_max_file_bytes FROM tenants 
WHERE id = ?
```

### ✅ Should Stay Tenant-Level (Not Change)

```php
// User-to-user relationships
SELECT * FROM user_blocks WHERE tenant_id = ? AND user_id = ?
SELECT * FROM user_mutes WHERE tenant_id = ? AND user_id = ?

// Login & authentication  
SELECT * FROM users WHERE id = ?
SELECT * FROM users WHERE email = ?
```

---

## Testing Scenarios

### Test 1: Branch Isolation
```
Setup:
  Tenant A, Branch 1 (U1)
  Tenant A, Branch 2 (U2)
  
Test:
  U1 attempts: GET /api/messages.php?peer_id=U2
  
Expected: 
  ❌ Reject (different branches)
  
Current Result:
  ✅ Returns messages (NO branch check!)
```

### Test 2: Settings Override
```
Setup:
  Tenant A:
    Branch 1: max_file_bytes = 50MB
    Branch 2: max_file_bytes = 10MB
    
Test:
  Branch 1 admin changes settings to 5MB
  
Expected:
  Only Branch 1 affected
  
Current Result:
  ALL branches set to 5MB (settings are tenant-wide)
```

### Test 3: Multi-Branch Peering
```
Setup:
  Tenant A has 2 branches
  Tenant B has 2 branches
  
Test:
  A.Branch1 requests to peer with B.Sales
  A.Branch2 requests to peer with B.Support
  
Expected:
  Two separate peering relationships
  
Current Result:
  DUPLICATE KEY ERROR (only one peering per tenant pair)
```

### Test 4: Contact Visibility
```
Setup:
  Tenant A:
    Branch 1: U1, U2
    Branch 2: U3, U4
  
Test:
  U1 calls GET /api/contacts.php
  
Expected:
  See U2 only (same branch)
  
Current Result:
  See U2, U3, U4 (no branch filtering)
```

---

## Settings Migration Plan

### Current (❌ Broken):
```
tenants.chat_max_file_bytes (tenant-wide)
tenants.chat_allowed_mime_prefixes (tenant-wide)
tenants.chat_default_auto_download (tenant-wide)
```

### Target (✅ Correct):
```
branch_chat_settings.chat_max_file_bytes (per branch)
branch_chat_settings.chat_allowed_mime_prefixes (per branch)
branch_chat_settings.chat_default_auto_download (per branch)

With fallback logic:
1. Check branch_chat_settings (if exists)
2. Fallback to tenant_settings (for backwards compatibility)
3. Use hardcoded defaults (26214400, 'image/,video/,...', 0)
```

---

## Security Concerns

### High Risk 🔴
- [ ] Branch validation missing in messages API
- [ ] No recipient validation before message insert
- [ ] Room ID could be brute-forced (no rate limiting)

### Medium Risk 🟡
- [ ] No audit log for chat operations
- [ ] Settings changes affect all branches silently
- [ ] Peering approvals not logged

### Low Risk 🟢
- [ ] Message content stored in plaintext (consider encryption)
- [ ] No message retention policy
- [ ] Typing indicators not implemented securely

---

## API Endpoint Summary

| Endpoint | Method | Branch Aware | Status |
|----------|--------|--------------|--------|
| `/api/messages.php` | GET | ❌ NO | ⚠️ NEEDS FIX |
| `/api/messages.php` | POST | ❌ NO | ⚠️ NEEDS FIX |
| `/api/contacts.php` | GET | ❌ NO | ⚠️ NEEDS FIX |
| `/api/chat_settings.php` | GET | ❌ NO | ❌ BROKEN |
| `/api/chat_prefs.php` | POST/GET | ✅ YES | ✅ OK |
| `/admin/chat_settings.php` | GET/POST | ❌ NO | ❌ BROKEN |
| `/admin/tenant_peering.php` | GET/POST | ⚠️ PARTIAL | ⚠️ NEEDS FIX |

---

## Code Examples

### How to Add Branch Validation (Example)

```php
// In api/messages.php, GET handler:

$peerId = isset($_GET['peer_id']) ? (int)$_GET['peer_id'] : 0;
if ($peerId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_peer']);
    exit;
}

// ✅ ADD THIS:
$peerStmt = secure_query($pdo, 
    'SELECT u.id, u.branch_id, u.tenant_id FROM users WHERE id = ?', 
    [$peerId]
);
$peerUser = $peerStmt ? $peerStmt->fetch() : null;

if (!$peerUser) {
    http_response_code(404);
    echo json_encode(['error' => 'peer_not_found']);
    exit;
}

// Check if same tenant or if branch-level isolation needed
$peerBranch = (int)$peerUser['branch_id'];
$peerTenant = (int)$peerUser['tenant_id'];

// If different tenant, peering already validated
// If same tenant, check branch compatibility
if ($peerTenant === $tenantId && $peerBranch !== $myBranch) {
    // Decision: Allow cross-branch within same tenant?
    // For now, implement as: require explicit approval
    http_response_code(403);
    echo json_encode(['error' => 'cross_branch_not_allowed']);
    exit;
}

// ... rest of the code
```

### How to Create Branch Settings

```php
// Migration: Copy tenant settings to all branches

$tenantsStmt = secure_query($pdo, 'SELECT id, chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download FROM tenants');
$tenants = $tenantsStmt ? $tenantsStmt->fetchAll() : [];

foreach ($tenants as $tenant) {
    $tenantId = (int)$tenant['id'];
    
    // Get all branches for this tenant
    $branchesStmt = secure_query($pdo, 'SELECT id FROM branches WHERE tenant_id = ?', [$tenantId]);
    $branches = $branchesStmt ? $branchesStmt->fetchAll() : [];
    
    foreach ($branches as $branch) {
        $branchId = (int)$branch['id'];
        
        // Create branch settings with tenant defaults
        secure_query($pdo,
            'INSERT INTO branch_chat_settings 
             (tenant_id, branch_id, chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download)
             VALUES (?, ?, ?, ?, ?)',
            [$tenantId, $branchId, 
             $tenant['chat_max_file_bytes'],
             $tenant['chat_allowed_mime_prefixes'], 
             $tenant['chat_default_auto_download']]
        );
    }
}
```

---

## Recommended Next Steps

### Week 1
- [ ] Review this audit report
- [ ] Plan implementation with team
- [ ] Create migration scripts
- [ ] Set up test environment

### Week 2
- [ ] Create `branch_chat_settings` table
- [ ] Add branch validation to messages.php
- [ ] Update chat_settings.php UI
- [ ] Write unit tests

### Week 3
- [ ] Deploy to staging
- [ ] Run through test scenarios
- [ ] Performance testing
- [ ] Deploy to production

### Ongoing
- [ ] Monitor chat operations
- [ ] Gather user feedback
- [ ] Plan future enhancements (WebSocket, encryption, etc.)

---

## Reference Files

**Audit Documents**:
- `CHAT_SYSTEM_AUDIT.md` - Detailed analysis (this document's companion)
- `CHAT_FLOW_DIAGRAM.md` - Visual flow diagrams

**Code Files to Review**:
- `/api/messages.php` - Core messaging API
- `/api/contacts.php` - Contact listing
- `/api/chat_settings.php` - Settings retrieval
- `/api/chat_prefs.php` - User preferences
- `/admin/chat_settings.php` - Settings management UI
- `/admin/tenant_peering.php` - Peering management UI
- `/chat.php` - Frontend chat interface

**Database**:
- `database_structure.sql` - Current schema (lines 229-248 for chat_messages, etc.)

---

## Key Metrics

```
Current Implementation:
- Chat Messages: 39 rows
- Message Reactions: 4 rows
- Tenant Peering: 1 row
- User Blocks: 0 rows
- User Mutes: 0 rows

Complexity:
- API Endpoints: 5 (messages, contacts, settings, prefs, + frontend)
- Database Tables: 5 (chat_messages, tenant_peering, user_blocks, user_mutes, message_reactions)
- Lines of Code: ~500 (backend) + ~1000 (frontend JavaScript)

Issues Found:
- Critical: 3 (branch validation, settings scope, peering model)
- High: 4 (recipient validation, indexes, contact filtering, audit logging)
- Medium: 3 (encryption, rate limiting, room ID enumeration)
- Low: 3 (pagination, websocket, export)
```

---

**Document Version**: 1.0  
**Last Updated**: 2025-12-10  
**Status**: Ready for Implementation  

For detailed analysis, see: `CHAT_SYSTEM_AUDIT.md`  
For flow diagrams, see: `CHAT_FLOW_DIAGRAM.md`
