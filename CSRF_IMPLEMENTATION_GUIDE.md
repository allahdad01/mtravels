# CSRF Implementation Guide - Step-by-Step

**Status:** Phase 1 Complete, Phases 2-7 Ready to Implement  
**Date:** December 9, 2025

---

## Quick Summary

✅ **PHASE 1 COMPLETE:** All 8 Accounts handlers now have CSRF validation

**Remaining:** 37 handlers across 6 phases

---

## Phase 2: Supplier & Client Modules (5 handlers)

### Handler 1: api/supplier/add_supplier.php

**Type:** Form Data (uses `$_POST`)

**Location:** Add after `enforce_auth()` or first `if ($_SERVER['REQUEST_METHOD'])` check

**Code to add:**
```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

---

### Handler 2: api/supplier/update_supplier.php

**Type:** Form Data (uses `$_POST`)

**Location:** Add after `enforce_auth()` or first `if ($_SERVER['REQUEST_METHOD'])` check

**Code to add:**
```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

---

### Handler 3: api/supplier/delete_supplier.php

**Type:** JSON Data (uses `json_decode()`)

**Location:** Add immediately after `json_decode()` call

**Code to add:**
```php
// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

---

### Handler 4: api/client/add_clients.php

**Type:** Form Data (uses `$_POST`)

**Location:** Add after `enforce_auth()` or first `if ($_SERVER['REQUEST_METHOD'])` check

**Code to add:**
```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

---

### Handler 5: api/client/update_client.php

**Type:** JSON Data (uses `json_decode()`)

**Location:** Add immediately after `json_decode()` call

**Code to add:**
```php
// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

---

## Phase 3: Hotel Module (7 handlers)

All form data handlers. Add validation after `enforce_auth()`:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

**Handlers:**
1. api/hotel/add_hotel_transaction.php
2. api/hotel/add_hotel_booking.php
3. api/hotel/update_hotel_booking.php
4. api/hotel/delete_hotel_booking.php
5. api/hotel/delete_hotel_transaction.php
6. api/hotel/update_hotel_transaction.php
7. api/hotel/process_hotel_refund.php

---

## Phase 4: Visa Module (6 handlers)

All form data handlers. Add validation after `enforce_auth()`:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

**Handlers:**
1. api/visa/add_visa.php
2. api/visa/add_visa_transaction.php
3. api/visa/update_visa.php
4. api/visa/delete_visa.php
5. api/visa/delete_visa_transaction.php
6. api/visa/process_visa_refund.php

---

## Phase 5: Umrah Module (7 handlers)

All form data handlers. Add validation after `enforce_auth()`:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

**Handlers:**
1. api/umrah/add_umrah.php
2. api/umrah/add_umrah_transaction.php
3. api/umrah/delete_umrah_transaction.php
4. api/umrah/delete_booking.php
5. api/umrah/create_family.php
6. api/umrah/update_family.php
7. api/umrah/process_umrah_refund.php

---

## Phase 6: Ticket Module (4 handlers)

All form data handlers. Add validation after `enforce_auth()`:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

**Handlers:**
1. api/ticket/add_ticket_payment.php
2. api/ticket/update_ticket.php
3. api/ticket/delete_ticket.php
4. api/ticket/save_ticket.php

---

## Phase 7: Other Modules (7 handlers)

All form data handlers. Add validation after `enforce_auth()`:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

**Handlers:**
1. api/expense/expense_actions.php
2. api/additional_payment/add_additional_payment.php
3. api/additional_payment/update_additional_payment_base.php
4. api/additional_payment/delete_additional_payment.php
5. api/additional_payment/add_additional_payment_transaction.php
6. api/debtor/debtors_handler.php
7. api/creditor/creditor_handler.php

---

## Implementation Rules

### For Form Data Handlers

These use `$_POST` and should validate CSRF immediately after `enforce_auth()`:

```php
<?php
require_once '../../admin/security.php';
enforce_auth();

// ✅ ADD THIS
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Continue with existing logic...
?>
```

### For JSON Handlers

These use `json_decode()` and should validate CSRF right after parsing:

```php
<?php
require_once '../../admin/security.php';
enforce_auth();

$data = json_decode(file_get_contents('php://input'), true);

// ✅ ADD THIS
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Continue with existing logic...
?>
```

---

## Testing Each Handler

After implementing CSRF validation for a handler:

### Test 1: Valid Token (Should Work)
```bash
curl -X POST http://localhost/api/supplier/add_supplier.php \
  -d "csrf_token=YOUR_SESSION_TOKEN&supplier_name=Test&..."
```
**Expected:** 200 OK, operation succeeds

### Test 2: Missing Token (Should Fail)
```bash
curl -X POST http://localhost/api/supplier/add_supplier.php \
  -d "supplier_name=Test&..."
```
**Expected:** 403 Forbidden, "Security validation failed"

### Test 3: Invalid Token (Should Fail)
```bash
curl -X POST http://localhost/api/supplier/add_supplier.php \
  -d "csrf_token=WRONG_TOKEN&supplier_name=Test&..."
```
**Expected:** 403 Forbidden, "Security validation failed"

---

## Progress Tracking

Track your progress by updating `CSRF_BACKEND_IMPLEMENTATION.md`:

```markdown
- [x] Handler 1 - Form pattern - ✅ COMPLETE
- [ ] Handler 2 - JSON pattern
- [ ] Handler 3 - Form pattern
```

---

## Troubleshooting

### Issue: "Security validation failed" on valid requests

**Cause:** CSRF token not being sent from frontend

**Solution:** 
1. Verify modal includes `<input type="hidden" name="csrf_token" ...>`
2. Check that JavaScript includes token in request body
3. Verify token is being passed correctly

### Issue: "Undefined function verify_csrf_token"

**Cause:** `admin/security.php` not included

**Solution:** Ensure this line is at the top of handler:
```php
require_once '../../admin/security.php';
```

### Issue: HTTP 500 error instead of 403

**Cause:** Validation code not in right place (after data parsing)

**Solution:** Move CSRF validation:
- **After:** `json_decode()` or after first form data access
- **Before:** Any database operations

---

## Success Criteria

Each phase is complete when:
- [ ] All handlers in phase have CSRF validation
- [ ] All 3 test cases pass per handler
- [ ] No false positives (legitimate requests blocked)
- [ ] Error logs show only CSRF attack attempts
- [ ] Team confirms testing is complete

---

## Next Review

**Target:** December 10, 2025
- Complete Phase 2 (5 handlers)
- Begin Phase 3 testing
- Report progress

---

## Questions?

See:
- **API_CSRF_VALIDATION_GUIDE.md** - General CSRF patterns
- **SECURITY_PROGRESS_SUMMARY.md** - Overall security status
- **admin/security.php** - Function definitions
