# CSRF Protection - Quick Reference Guide

## What Was Done

### ✅ Frontend (100% Complete)
- **94 modals** now include CSRF tokens
- All tokens are **XSS-safe** (escaped with h())
- Tokens are **cryptographically strong** (256-bit)

### 🟡 Backend (18% Complete)
- **4 handlers** validate CSRF tokens
- **18 handlers** still pending implementation
- Security framework **enhanced** with hash_equals()

---

## How to Add CSRF Validation to a Handler

### Step 1: Choose Your Pattern

**Pattern A: If handler uses `$_POST`**
```php
// Right after enforce_auth()
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}
```

**Pattern B: If handler uses JSON input**
```php
// Get data first, then verify
$data = json_decode(file_get_contents('php://input'), true);
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}
```

### Step 2: Apply to Handler

**Example: api/accounts/transfer_balance.php**

```php
<?php
require_once '../../admin/security.php';
enforce_auth();

// 👇 ADD THIS
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
    exit;
}

// Rest of code continues...
```

### Step 3: Test

```bash
# Test 1: Valid token (should work)
curl -X POST http://localhost/api/accounts/transfer_balance.php \
  -H "Content-Type: application/json" \
  -d '{"csrf_token":"valid_token","...":"..."}'

# Test 2: Missing token (should return 403)
curl -X POST http://localhost/api/accounts/transfer_balance.php \
  -H "Content-Type: application/json" \
  -d '{"...":"..."}'

# Test 3: Wrong token (should return 403)
curl -X POST http://localhost/api/accounts/transfer_balance.php \
  -H "Content-Type: application/json" \
  -d '{"csrf_token":"wrong","...":"..."}'
```

---

## Handlers Still Needing CSRF Validation

### Accounts (6 pending)
- [ ] `api/accounts/transfer_balance.php`
- [ ] `api/accounts/add_main_account.php`
- [ ] `api/accounts/edit_main_account.php`
- [ ] `api/accounts/delete_main_account.php`
- [ ] `api/accounts/add_client.php`
- [ ] `api/accounts/add_supplier.php`

### Hotel (4 pending)
- [ ] `api/hotel/add_hotel.php`
- [ ] `api/hotel/update_hotel.php`
- [ ] `api/hotel/delete_hotel.php`
- [ ] `api/hotel/edit_transaction.php`

### Ticket (3 pending)
- [ ] `api/ticket/book_ticket.php`
- [ ] `api/ticket/update_ticket.php`
- [ ] `api/ticket/delete_ticket.php`

### Visa (1 pending)
- [ ] `api/visa/add_visa.php`

### Additional Payment (4 pending)
- [ ] `api/additional_payment/add_additional_payment.php`
- [ ] `api/additional_payment/add_transaction.php`
- [ ] `api/additional_payment/update_additional_payment.php`
- [ ] `api/additional_payment/delete_additional_payment.php`

### Other (Optional - read-only handlers)
Skip these (they don't modify data):
- `get_*.php` files
- `fetch_*.php` files
- `list_*.php` files
- Print/report endpoints

---

## Key Files to Know

| File | Purpose | Last Updated |
|------|---------|--------------|
| `admin/security.php` | CSRF validation functions | ✅ Today |
| `API_CSRF_VALIDATION_GUIDE.md` | Detailed implementation guide | ✅ Today |
| `CSRF_VALIDATION_IMPLEMENTATION.md` | Progress tracking | ✅ Today |
| `CSRF_FIX_COMPLETE.md` | Frontend implementation details | ✅ Today |
| All `modals/**/*.php` | Modal forms with CSRF tokens | ✅ Today |

---

## Common Questions

**Q: How do I know if a handler needs CSRF validation?**
- A: If it has `$_POST`, `json_decode(php://input)`, or modifies data → needs CSRF

**Q: Will old code break?**
- A: No. CSRF tokens are optional in existing code, required in new validation

**Q: How do I handle JSON requests from JavaScript?**
- A: Include `csrf_token` in the JSON body, then use Pattern B

**Q: What if session expires?**
- A: New token is generated automatically on next page load

**Q: Can I skip CSRF validation for some handlers?**
- A: No. All POST/PUT/DELETE handlers that modify data should validate.

---

## Testing Checklist

After adding CSRF validation to a handler:

- [ ] Handler still works with valid CSRF token
- [ ] Handler rejects requests without token (returns 403)
- [ ] Handler rejects requests with wrong token (returns 403)
- [ ] Error messages are clear
- [ ] Response is JSON format
- [ ] HTTP status code is 403
- [ ] Corresponding modal still works
- [ ] No legitimate requests are blocked

---

## Copy-Paste Templates

### Quick Add - Form Handler
```php
<?php
require_once '../../admin/security.php';
enforce_auth();

// ADD THIS LINE
if (!verify_csrf_token()) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'CSRF failed']); exit; }

// Your code here...
```

### Quick Add - JSON Handler
```php
<?php
require_once '../../admin/security.php';
enforce_auth();

$data = json_decode(file_get_contents('php://input'), true);
// ADD THIS LINE
if (!verify_csrf_token($data['csrf_token'] ?? null)) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'CSRF failed']); exit; }

// Your code here...
```

---

## Git Workflow

```bash
# Check which handlers don't have CSRF validation
grep -r "verify_csrf_token" api/

# After adding CSRF to a handler
git add api/path/to/handler.php
git commit -m "Add CSRF validation to handler_name"

# After completing a phase
git add api/accounts/*.php
git commit -m "Add CSRF validation to all accounts handlers"
```

---

## Performance Notes

- ✅ **hash_equals()** has minimal overhead (<1ms)
- ✅ No database queries added
- ✅ No external service calls
- ✅ All tokens already in memory
- **Impact:** Negligible performance cost

---

## Security Notes

- ✅ Tokens are **32 bytes (256 bits)** - very strong
- ✅ Tokens use **cryptographically secure random** generation
- ✅ Tokens are **session-bound** - can't be reused across sessions
- ✅ Tokens are **single-use aware** in modals
- ✅ Validation uses **timing-safe comparison** (hash_equals)

---

## Last Implemented

**✅ Today (Dec 9, 2025)**
- admin/security.php - Enhanced verification
- api/accounts/fund_supplier.php
- api/accounts/withdraw_fund.php
- api/accounts/fund_main_account.php
- api/accounts/update_transaction.php

**Next to do:** Remaining 18 handlers in Phases 2-3

---

## Need Help?

1. **Check:** API_CSRF_VALIDATION_GUIDE.md
2. **Find:** CSRF_VALIDATION_IMPLEMENTATION.md
3. **Review:** CSRF_FIX_COMPLETE.md
4. **Compare:** Look at a completed handler (e.g., fund_supplier.php)

---

**Version:** 1.0  
**Last Updated:** December 9, 2025  
**Status:** Ready for Phase 2 implementation

