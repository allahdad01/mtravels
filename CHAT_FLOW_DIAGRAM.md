# Chat System Flow Diagrams

## 1. Message Flow (Current Implementation)

```
┌─────────────────────────────────────────────────────────────────┐
│                    User Initiates Chat                           │
│                   (Frontend: chat.php)                           │
└────────────────────────────┬────────────────────────────────────┘
                             │
                    ┌────────▼────────┐
                    │ Fetch Settings  │
                    │ GET /api/       │
                    │ chat_settings   │
                    │ (Tenant-wide)   │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Fetch Contacts  │
                    │ GET /api/       │
                    │ contacts.php    │
                    │ (Tenant Peers)  │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ User Selects    │
                    │ Contact         │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Load Messages   │
                    │ GET /api/       │
                    │ messages.php    │
                    │ ?peer_id=X      │
                    └────────┬────────┘
                             │
        ┌────────────────────┴─────────────────────┐
        │ ⚠️ GAP: No branch_id validation          │
        │ Can query any peer_id's messages!        │
        └────────────────────┬─────────────────────┘
                             │
                    ┌────────▼────────────┐
                    │ Display Messages    │
                    │ (Chat UI)          │
                    └────────┬────────────┘
                             │
                    ┌────────▼────────────┐
                    │ User Types & Sends  │
                    │ POST /api/          │
                    │ messages.php        │
                    └────────┬────────────┘
                             │
        ┌────────────────────┴──────────────────────────┐
        │ Validations:                                   │
        │ ✅ Block/Mute check                           │
        │ ✅ Tenant peering check                       │
        │ ❌ NO branch compatibility check              │
        │ ❌ NO recipient existence pre-check           │
        └────────────────────┬──────────────────────────┘
                             │
                    ┌────────▼────────────┐
                    │ INSERT chat_message │
                    │ room_id: u-X-Y      │
                    │ tenant_id_from      │
                    │ branch_id (unused)  │
                    └────────┬────────────┘
                             │
                    ┌────────▼────────────┐
                    │ Message Saved ✅    │
                    └────────────────────┘
```

---

## 2. Settings Management Flow (Current - BROKEN)

```
┌──────────────────────────────────────────┐
│  Admin: /admin/chat_settings.php         │
│                                          │
│  Form shows:                             │
│  - Max file bytes                        │
│  - Allowed MIME types                    │
│  - Auto-download toggle                  │
│  ❌ NO branch selector!                  │
└────────────┬─────────────────────────────┘
             │
    ┌────────▼────────────┐
    │ Submit Form (POST)  │
    │                     │
    │ Updates:            │
    │ tenants table       │
    │ ⚠️ AFFECTS ALL      │
    │    BRANCHES!        │
    └────────┬────────────┘
             │
    ┌────────▼────────────────────────────┐
    │ UPDATE tenants SET                   │
    │   chat_max_file_bytes = ?,           │
    │   chat_allowed_mime_prefixes = ?,    │
    │   chat_default_auto_download = ?     │
    │ WHERE id = ? (tenant_id)             │
    │                                      │
    │ ⚠️ ALL BRANCHES get same settings    │
    └────────┬────────────────────────────┘
             │
    ┌────────▼────────────┐
    │ Branch 1 affected   │
    │ Branch 2 affected   │
    │ Branch 3 affected   │
    │ ... (all branches)  │
    └────────────────────┘
```

### Problem Flow:
```
Scenario: Company wants different file limits per branch

Branch 1 (Operations):    Max 50MB (needs large documents)
Branch 2 (Field Office):  Max 10MB (bandwidth limited)
Branch 3 (Headquarters):  Max 5MB (security policy)

Current System:
┌─────────────────────────────────────────┐
│ Admin changes settings to 10MB           │
│ hits SAVE                               │
├─────────────────────────────────────────┤
│ Result:                                  │
│ Branch 1: 50MB ✗ changed to 10MB         │
│ Branch 2: 10MB ✓ correct                │
│ Branch 3: 5MB ✗ changed to 10MB         │
└─────────────────────────────────────────┘

CONFLICT: Can't satisfy all branches at once!
```

---

## 3. Tenant Peering Flow (Current - INCOMPLETE)

```
┌────────────────────────────────────────────┐
│ Admin A: /admin/tenant_peering.php         │
│ Branch: Operations (branch_id = 1)         │
│ Tenant: Company A (tenant_id = 1)          │
└─────────────┬──────────────────────────────┘
              │
    ┌─────────▼──────────┐
    │ Selects:           │
    │ Peer Tenant: B     │
    │ Status: pending    │
    └─────────┬──────────┘
              │
    ┌─────────▼──────────────────────────────┐
    │ INSERT tenant_peering                  │
    │   tenant_id = 1 (A)                    │
    │   peer_tenant_id = 2 (B)               │
    │   status = 'pending'                   │
    │   branch_id = 1                        │
    │                                        │
    │ ⚠️ Unique Key: (tenant_id,             │
    │                peer_tenant_id)         │
    │ ❌ branch_id NOT in unique key         │
    └─────────┬──────────────────────────────┘
              │
    ┌─────────▼──────────────────────────────┐
    │ Admin B: Approves peering              │
    │ Tenant: Company B (tenant_id = 2)      │
    │ Branch: Sales (branch_id = 5)          │
    │                                        │
    │ "UPDATE tenant_peering SET status =    │
    │  'approved' WHERE..."                  │
    └─────────┬──────────────────────────────┘
              │
    ┌─────────▼──────────────────────────────┐
    │ ⚠️ PROBLEM:                            │
    │ Only ONE peering per tenant pair!      │
    │                                        │
    │ A.Operations (branch 1) can't create   │
    │ separate peering from                  │
    │ A.Finance (branch 2)                   │
    │                                        │
    │ Result: DUPLICATE KEY ERROR            │
    └─────────────────────────────────────────┘
```

### Ideal Solution:
```
Branch-Level Peering:

A.Operations → B.Sales (approved)
A.Operations → B.Support (pending)
A.Finance → B.Support (approved)
A.Finance → B.Sales (blocked)

With branch_peering table:
(branch_id, peer_branch_id, peer_tenant_id)
```

---

## 4. Data Isolation Layers

### Current State:
```
┌───────────────────────────────────────────────────────┐
│                    Tenant A                            │
├───────────────────────────────────────────────────────┤
│                                                       │
│  Settings:  ✅ Isolated per tenant                    │
│  ├─ chat_max_file_bytes                              │
│  ├─ chat_allowed_mime_prefixes                       │
│  └─ chat_default_auto_download                       │
│                                                       │
│  Peering:   ⚠️ Isolated per tenant (not branch)       │
│  ├─ Can peer with Tenant B                           │
│  └─ ALL branches inherit same peering                │
│                                                       │
│  Messages:  ⚠️ Isolated by user pair (not branch)     │
│  ├─ Room: u-1-5 (between User 1 & 5)                │
│  ├─ Branch info stored but not validated             │
│  └─ Can query any peer_id without branch check       │
│                                                       │
│  Blocks:    ✅ Isolated per (tenant, user)           │
│  Mutes:     ✅ Isolated per (tenant, user)           │
│                                                       │
└───────────────────────────────────────────────────────┘

Branches within Tenant A:
├─ Branch 1 (Operations)      ← Settings shared!
├─ Branch 2 (Finance)          ← Settings shared!
└─ Branch 3 (Field Office)     ← Settings shared!
```

### Ideal State:
```
┌───────────────────────────────────────────────────────┐
│                    Tenant A                            │
├───────────────────┬───────────────┬───────────────────┤
│ Branch 1          │ Branch 2      │ Branch 3          │
│ Operations        │ Finance       │ Field Office      │
├───────────────────┼───────────────┼───────────────────┤
│ Settings:         │ Settings:     │ Settings:         │
│ Max 50MB          │ Max 5MB       │ Max 10MB          │
│ All MIME types    │ PDF only      │ Images only       │
├───────────────────┼───────────────┼───────────────────┤
│ Peering:          │ Peering:      │ Peering:          │
│ → B.Sales✅       │ → B.Legal⏳   │ → B.Ops✅         │
│ → B.Support✅     │ → B.Support❌ │                   │
├───────────────────┼───────────────┼───────────────────┤
│ Messages: U1↔U5   │ Messages: U2↔U6│ Messages: U3↔U7  │
│ (branch isolated) │ (isolated)    │ (isolated)        │
└───────────────────┴───────────────┴───────────────────┘
```

---

## 5. Security Validation Chain (Current)

```
User Sends Message:

┌──────────────────────────────────────┐
│ POST /api/messages.php               │
│ to_user_id, content                  │
└────────────┬─────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Check: Session exists?            │
    │ ✅ Yes → Continue                 │
    │ ❌ No → 401 Unauthorized          │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Check: to_user_id > 0?            │
    │ ✅ Valid → Continue               │
    │ ❌ Invalid → 400 Bad Request       │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Check: Blocked relationship?      │
    │ (Both directions)                 │
    │ ✅ Not blocked → Continue         │
    │ ❌ Blocked → 403 Forbidden        │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Check: Tenant peering?            │
    │ (Only if cross-tenant)            │
    │ ✅ Approved → Continue            │
    │ ❌ Not approved → 403 Forbidden   │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ ❌ MISSING:                       │
    │ Check: Branch compatibility?      │
    │ Check: Recipient user exists?     │
    │ Check: Recipient is not deleted?  │
    │ Check: Content within size limit? │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ INSERT chat_message               │
    │ (Trust all values at this point)  │
    └──────────────────────────────────┘
```

---

## 6. Contact List Filtering Logic

```
GET /api/contacts.php

┌─────────────────────────────────────┐
│ Current User: U1 (Tenant A)         │
└────────────┬────────────────────────┘
             │
    ┌────────▼────────────────────────┐
    │ Get allowed tenants:            │
    │ My tenant: A                    │
    │ + Peer tenants (approved):      │
    │   B, C, D                       │
    │                                │
    │ allowedTenantIds = [1, 2, 3, 4]│
    └────────┬────────────────────────┘
             │
    ┌────────▼────────────────────────┐
    │ SELECT users WHERE               │
    │ tenant_id IN (1,2,3,4)          │
    │ AND id <> (current user)        │
    │ AND deleted_at IS NULL          │
    │ AND fired <> 1                  │
    └────────┬────────────────────────┘
             │
    ┌────────▼────────────────────────┐
    │ Filter out blocked/muted users  │
    │ ✅ Works correctly              │
    └────────┬────────────────────────┘
             │
    ┌────────▼────────────────────────┐
    │ ❌ MISSING:                     │
    │ Filter by branch_id             │
    │ (If branches are isolated)      │
    └────────┬────────────────────────┘
             │
    ┌────────▼────────────────────────┐
    │ Build contact objects:          │
    │ - id, name, agency              │
    │ - lastMessage                   │
    │ - unread count                  │
    │ - profile pic                   │
    └────────┬────────────────────────┘
             │
    ┌────────▼────────────────────────┐
    │ Return JSON contact list        │
    └─────────────────────────────────┘
```

---

## 7. Database Query Dependencies

```
User wants to send message:

1️⃣ SELECT u.tenant_id FROM users WHERE id = ?
   ↓ (Get current user's tenant)

2️⃣ SELECT tenant_id FROM users WHERE id = ?
   ↓ (Get recipient's tenant)

3️⃣ SELECT 1 FROM user_blocks WHERE ...
   ↓ (Check if blocked)

4️⃣ SELECT tenant_id FROM user_blocks WHERE ...
   ↓ (Check if blocked in reverse)

5️⃣ SELECT 1 FROM tenant_peering WHERE ...
   ↓ (Check if peer approved - only if cross-tenant)

6️⃣ INSERT INTO chat_messages ...
   ↓ (Save message)

7️⃣ ❌ MISSING: Validate recipient's branch_id
   ❌ MISSING: Validate setting constraints
   ❌ MISSING: Log operation to audit_log
```

---

## 8. Table Relationship Diagram

```
┌─────────────┐
│  tenants    │ 1 ──────────────────┐
└─────────────┘                     │
      │                             │
      │ 1:M                         │
      ▼                             │
┌─────────────┐                     │
│  branches   │ 1 ────────────────────────┐
└─────────────┘                          │
      │                                  │
      │ 1:M                              │
      ▼                                  │
┌─────────────┐     ┌──────────────────┐ │
│   users     │─────│ chat_messages    │◄─┘
└─────────────┘ M:M └──────────────────┘
      │ │             (room_id based)
      │ │ M:M          
      │ ▼              
  ┌────────────────┐  
  │ user_blocks    │  ✅ Proper isolation
  │ user_mutes     │
  └────────────────┘

┌─────────────────────────────┐
│  tenant_peering             │
├─────────────────────────────┤
│ tenant_id (FK tenants)      │
│ peer_tenant_id (FK tenants) │
│ status                      │
│ branch_id ⚠️ NOT in PK      │
└─────────────────────────────┘

┌──────────────────────────────┐
│  message_reactions           │
├──────────────────────────────┤
│ message_id (FK chat_messages)│  ❌ Points to
│ user_id (FK users)           │     non-existent
│ emoji                        │     messages
└──────────────────────────────┘

⚠️ MISSING:
┌──────────────────────────────┐
│  branch_chat_settings        │
├──────────────────────────────┤
│ branch_id (FK branches)      │  Should exist!
│ chat_max_file_bytes          │
│ chat_allowed_mime_prefixes   │
│ chat_default_auto_download   │
└──────────────────────────────┘
```

---

## 9. Message ID Reference Problem

```
Current Schema:

message_reactions table:
  message_id INT references chat_messages(id)

BUT: chat_messages.id is BIGINT(20)
     message_reactions.message_id is INT(11)

⚠️ Potential overflow:
- INT max: 2,147,483,647
- BIGINT max: 9,223,372,036,854,775,807

If chat messages exceed 2B, reactions fail!
```

---

## 10. Audit Trail (Missing)

```
Current State: ❌ NO AUDIT LOG for chat operations

Ideal:

┌─────────────────────────────────────┐
│ activity_log / audit_logs           │
├─────────────────────────────────────┤
│ ✅ Exists for most operations       │
│ ❌ Missing for:                     │
│   - chat_messages CREATE            │
│   - chat_messages DELETE            │
│   - user_blocks CREATE/DELETE       │
│   - user_mutes CREATE/DELETE        │
│   - chat_settings UPDATE            │
│   - tenant_peering APPROVE/BLOCK    │
└─────────────────────────────────────┘

Should log:
- WHO changed what
- WHEN they changed it
- WHY (if possible)
- WHAT was the old value
- WHAT is the new value
```

---

**Key Takeaway**: Your chat system has solid **tenant isolation** but needs **branch-level validation** to fully support multi-branch organizations. The settings should be branch-specific, not tenant-wide.
