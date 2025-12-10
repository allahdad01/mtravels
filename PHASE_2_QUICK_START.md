# Phase 2: Branch-Level Peering - Quick Start Guide

## What Was Built

✅ **Branch-specific peering relationships**  
Each branch can now independently control which branches in other organizations they can communicate with.

## Files Modified/Created

| File | Type | Purpose |
|------|------|---------|
| `migrations/002_branch_peering.sql` | NEW | Database schema for branch peering |
| `api/messages.php` | UPDATED | Added branch peering checks (GET & POST) |
| `api/contacts.php` | UPDATED | Filter contacts by branch peering |
| `api/branches.php` | NEW | Get branches for a tenant (for UI) |
| `admin/branch_peering.php` | NEW | Admin interface for managing peering |

## Quick Test

### 1. Run the Migration
```bash
mysql -u your_user -p your_db < migrations/002_branch_peering.sql
```

### 2. Verify Table Exists
```sql
SHOW TABLES LIKE 'branch_peering';
DESC branch_peering;
```

### 3. Access Admin Page
```
Navigate to: /admin/branch_peering.php
```

### 4. Test Scenarios

**Scenario A: Create Branch Peering**
1. Go to `/admin/branch_peering.php`
2. Select your branch from dropdown
3. Select peer organization
4. Select peer branch (auto-loaded)
5. Click "Create Request" → Status should be "pending"

**Scenario B: Message Blocked by Branch Peering**
1. User from Branch A (not peered) tries to message user from Branch B
2. Expected: 403 error with `branch_peer_not_allowed`

**Scenario C: Message Allowed by Branch Peering**
1. Create approved peering between Branch A and Branch B
2. User from Branch A messages user from Branch B
3. Expected: Message sent successfully

## API Changes

### Messages POST
**New error**:
```json
{"error": "branch_peer_not_allowed"}
```
Returned when branch peering is not approved, even if tenant peering is.

### Messages GET
Same branch peering check applied before returning messages.

### Contacts GET
Cross-tenant contacts now filtered by branch peering status:
- Only users from approved peer branches shown
- Same-tenant users always shown

## Admin Interface

**Location**: `/admin/branch_peering.php`

**Features**:
- Create branch peering requests
- View all peerings for current tenant's branches
- Approve/Block/Delete peering requests
- Dynamic branch loading

**Key Elements**:
- Branch selector (your branches)
- Peer org selector (other organizations)
- Peer branch selector (dynamically loaded)
- Status display (approved/pending/blocked)
- Action buttons (approve, block, delete)

## Database Schema

```sql
branch_peering {
  id: int PRIMARY KEY
  tenant_id: int FK (tenants)
  branch_id: int FK (branches)
  peer_tenant_id: int FK (tenants)
  peer_branch_id: int FK (branches)
  status: enum['approved', 'pending', 'blocked']
  created_at: timestamp
  updated_at: timestamp
}
```

## Backward Compatibility

✅ Fully backward compatible
- Existing tenant peering still works
- Both levels checked (tenant AND branch)
- Migration auto-populates from tenant peering

## Testing Checklist

- [ ] Migration runs without errors
- [ ] `branch_peering` table exists with correct schema
- [ ] `/admin/branch_peering.php` loads
- [ ] Can create peering request
- [ ] Peer branches load dynamically
- [ ] Can approve/block/delete requests
- [ ] Messages blocked when branch peering not approved
- [ ] Contacts filtered by branch peering
- [ ] Messages work when peering approved

## Next Steps

1. Run migration
2. Test scenarios above
3. Monitor logs for any errors
4. Deploy to production
5. Plan Phase 3 (Message Encryption)

## Support

See `PHASE_2_IMPLEMENTATION_SUMMARY.md` for detailed documentation.
