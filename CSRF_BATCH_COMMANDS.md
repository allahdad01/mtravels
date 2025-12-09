# CSRF Batch Implementation Commands

**Purpose:** Quick reference for implementing CSRF validation across remaining handlers  
**Date:** December 9, 2025

---

## Phase 2: Supplier & Client (5 handlers)

### 1. api/supplier/add_supplier.php
```bash
# Find insertion point (after enforce_auth() call)
grep -n "enforce_auth()" "api/supplier/add_supplier.php"

# Edit file and add CSRF validation after line shown
```

**Changes needed:** Add after `enforce_auth()` or `if ($_SERVER['REQUEST_METHOD'])` check

---

### 2. api/supplier/update_supplier.php
```bash
grep -n "enforce_auth()" "api/supplier/update_supplier.php"
```

---

### 3. api/supplier/delete_supplier.php
```bash
grep -n "json_decode" "api/supplier/delete_supplier.php"
```

**Changes needed:** Add after `json_decode()` call

---

### 4. api/client/add_clients.php
```bash
grep -n "enforce_auth()" "api/client/add_clients.php"
```

---

### 5. api/client/update_client.php
```bash
grep -n "json_decode" "api/client/update_client.php"
```

---

## Phase 3: Hotel Module (7 handlers)

All form data handlers - add after `enforce_auth()`:

```bash
# Check each file
grep -n "enforce_auth()" "api/hotel/add_hotel_transaction.php"
grep -n "enforce_auth()" "api/hotel/add_hotel_booking.php"
grep -n "enforce_auth()" "api/hotel/update_hotel_booking.php"
grep -n "enforce_auth()" "api/hotel/delete_hotel_booking.php"
grep -n "enforce_auth()" "api/hotel/delete_hotel_transaction.php"
grep -n "enforce_auth()" "api/hotel/update_hotel_transaction.php"
grep -n "enforce_auth()" "api/hotel/process_hotel_refund.php"
```

---

## Phase 4: Visa Module (6 handlers)

All form data handlers - add after `enforce_auth()`:

```bash
grep -n "enforce_auth()" "api/visa/add_visa.php"
grep -n "enforce_auth()" "api/visa/add_visa_transaction.php"
grep -n "enforce_auth()" "api/visa/update_visa.php"
grep -n "enforce_auth()" "api/visa/delete_visa.php"
grep -n "enforce_auth()" "api/visa/delete_visa_transaction.php"
grep -n "enforce_auth()" "api/visa/process_visa_refund.php"
```

---

## Phase 5: Umrah Module (7 handlers)

All form data handlers - add after `enforce_auth()`:

```bash
grep -n "enforce_auth()" "api/umrah/add_umrah.php"
grep -n "enforce_auth()" "api/umrah/add_umrah_transaction.php"
grep -n "enforce_auth()" "api/umrah/delete_umrah_transaction.php"
grep -n "enforce_auth()" "api/umrah/delete_booking.php"
grep -n "enforce_auth()" "api/umrah/create_family.php"
grep -n "enforce_auth()" "api/umrah/update_family.php"
grep -n "enforce_auth()" "api/umrah/process_umrah_refund.php"
```

---

## Phase 6: Ticket Module (4 handlers)

All form data handlers - add after `enforce_auth()`:

```bash
grep -n "enforce_auth()" "api/ticket/add_ticket_payment.php"
grep -n "enforce_auth()" "api/ticket/update_ticket.php"
grep -n "enforce_auth()" "api/ticket/delete_ticket.php"
grep -n "enforce_auth()" "api/ticket/save_ticket.php"
```

---

## Phase 7: Other Modules (7 handlers)

All form data handlers - add after `enforce_auth()`:

```bash
grep -n "enforce_auth()" "api/expense/expense_actions.php"
grep -n "enforce_auth()" "api/additional_payment/add_additional_payment.php"
grep -n "enforce_auth()" "api/additional_payment/update_additional_payment_base.php"
grep -n "enforce_auth()" "api/additional_payment/delete_additional_payment.php"
grep -n "enforce_auth()" "api/additional_payment/add_additional_payment_transaction.php"
grep -n "enforce_auth()" "api/debtor/debtors_handler.php"
grep -n "enforce_auth()" "api/creditor/creditor_handler.php"
```

---

## Verification Script

After completing each phase, verify CSRF is implemented:

```bash
#!/bin/bash
# Count handlers with CSRF validation

echo "=== CSRF Validation Status ==="
echo ""

# Phase 1 - Accounts
echo "PHASE 1 - ACCOUNTS:"
grep -l "verify_csrf_token" api/accounts/*.php | wc -l
echo "/ 8 handlers"
echo ""

# Phase 2 - Supplier & Client
echo "PHASE 2 - SUPPLIER & CLIENT:"
grep -l "verify_csrf_token" api/supplier/add_supplier.php api/supplier/update_supplier.php api/supplier/delete_supplier.php api/client/add_clients.php api/client/update_client.php 2>/dev/null | wc -l
echo "/ 5 handlers"
echo ""

# Phase 3 - Hotel
echo "PHASE 3 - HOTEL:"
find api/hotel -name "*.php" | xargs grep -l "verify_csrf_token" | wc -l
echo "/ 7 handlers"
echo ""

# Phase 4 - Visa
echo "PHASE 4 - VISA:"
find api/visa -name "*.php" | xargs grep -l "verify_csrf_token" 2>/dev/null | wc -l
echo "/ 6 handlers"
echo ""

# Total
echo "TOTAL CSRF IMPLEMENTATIONS:"
find api -name "*.php" | xargs grep -l "verify_csrf_token" | wc -l
echo "/ 44 critical handlers"
```

---

## Quick Reference: Code to Add

### For Form Data (\_POST)

Place after `enforce_auth()` or at start of request method check:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

### For JSON Data

Place immediately after `json_decode()`:

```php
// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}
```

---

## Testing Checklist

For each handler implemented:

- [ ] Handler reads correctly with token
- [ ] Handler rejects requests without token (403)
- [ ] Handler rejects requests with wrong token (403)
- [ ] Error message is consistent
- [ ] HTTP status code is 403
- [ ] No legitimate operations are blocked
- [ ] Modal forms still work correctly

---

## Common Patterns in Handlers

### Pattern 1: Simple Form Data
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD CSRF HERE
    $var = $_POST['field'];
}
```

### Pattern 2: JSON Input
```php
$data = json_decode(file_get_contents('php://input'), true);
// ADD CSRF HERE
$var = $data['field'];
```

### Pattern 3: Dual Input (Form or JSON)
```php
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}
// ADD CSRF HERE
$var = $data['field'];
```

---

## Debugging: Check if CSRF is Already in File

```bash
# Search for CSRF in specific file
grep -n "csrf" api/supplier/add_supplier.php

# Should return nothing if not implemented
# If you see "verify_csrf_token", it's already done
```

---

## Progress Tracker

Update this as you implement:

```
Phase 1 (Accounts):      ████████░░░░░░ 8/8 COMPLETE
Phase 2 (Supplier/Client): ░░░░░░░░░░░░░░ 0/5
Phase 3 (Hotel):         ░░░░░░░░░░░░░░ 0/7
Phase 4 (Visa):          ░░░░░░░░░░░░░░ 0/6
Phase 5 (Umrah):         ░░░░░░░░░░░░░░ 0/7
Phase 6 (Ticket):        ░░░░░░░░░░░░░░ 0/4
Phase 7 (Other):         ░░░░░░░░░░░░░░ 0/7

TOTAL: 8/44 (18%) Complete
```

---

## Expected Completion Timeline

| Phase | Handlers | Est. Time | Target Date |
|-------|----------|-----------|-------------|
| Phase 1 | 8 | ✅ Complete | Dec 9 |
| Phase 2 | 5 | 1-2 hours | Dec 10 |
| Phase 3 | 7 | 1-2 hours | Dec 10 |
| Phase 4 | 6 | 1-2 hours | Dec 10 |
| Phase 5 | 7 | 1-2 hours | Dec 11 |
| Phase 6 | 4 | 1 hour | Dec 11 |
| Phase 7 | 7 | 1-2 hours | Dec 11 |
| **Total** | **44** | **8-10 hours** | **Dec 11** |

---

## Documentation Resources

- **API_CSRF_VALIDATION_GUIDE.md** - Complete implementation guide with examples
- **CSRF_IMPLEMENTATION_GUIDE.md** - Step-by-step instructions per handler
- **CSRF_BACKEND_IMPLEMENTATION.md** - Status tracking document
- **SECURITY_PROGRESS_SUMMARY.md** - Overall security progress
- **admin/security.php** - Core validation function

---

**Status:** Ready to implement Phases 2-7  
**Last Updated:** December 9, 2025
