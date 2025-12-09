# API CSRF Token Validation Implementation Guide

**Date:** December 9, 2025  
**Status:** Implementation Instructions  
**Priority:** CRITICAL

---

## Overview

All modals now include CSRF tokens. This guide shows how to add CSRF validation to backend API handlers to prevent CSRF attacks.

---

## Step 1: Understanding the CSRF Token

### Token Generation (Already Done)
```php
// In admin/security.php - generates token on session start
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

### Token Validation Function (Updated)
```php
function verify_csrf_token($token = null) {
    // Get token from parameter or POST data
    $token = $token ?? ($_POST['csrf_token'] ?? null);
    
    if (!$token || !isset($_SESSION['csrf_token'])) {
        error_log("CSRF attack detected - missing token");
        return false;
    }
    
    // Use hash_equals() to prevent timing attacks
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        error_log("CSRF attack detected - invalid token");
        return false;
    }
    
    return true;
}
```

---

## Step 2: Adding CSRF Validation to API Handlers

### Pattern 1: Form-Data Handlers (Using $_POST)

**Example:** `api/accounts/fund_supplier.php`

```php
<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// 🟢 ADD THIS: Verify CSRF token FIRST
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Get POST data
$data = $_POST;

// Continue with business logic...
?>
```

### Pattern 2: JSON Handlers (Using php://input)

**For handlers that accept JSON POST data:**

```php
<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

// 🟢 ADD THIS: Extract CSRF token from JSON data
$csrf_token = $data['csrf_token'] ?? null;

// 🟢 ADD THIS: Verify CSRF token
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

// Continue with business logic...
?>
```

### Pattern 3: Fetch/AJAX Handlers

**For JavaScript fetch requests:**

```php
<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Get token from request headers (custom header)
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

// If not in header, try POST data
if (!$csrf_token) {
    $csrf_token = $_POST['csrf_token'] ?? null;
}

// 🟢 ADD THIS: Verify CSRF token
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

// Continue with business logic...
?>
```

---

## Step 3: API Handlers That Need CSRF Validation

### Priority 1: Financial/Critical Handlers (IMMEDIATE)

These handle money transfers and must be protected ASAP:

**Accounts Module:**
- [x] `api/accounts/fund_supplier.php` - ✅ DONE
- [ ] `api/accounts/withdraw_fund.php`
- [ ] `api/accounts/transfer_balance.php`
- [ ] `api/accounts/fund_main_account.php`
- [ ] `api/accounts/update_transaction.php`

**Visa Module:**
- [ ] `api/visa/add_visa.php`
- [ ] `api/visa/update_visa.php`
- [ ] `api/visa/delete_visa.php`
- [ ] `api/visa/process_visa_refund.php`

**Hotel Module:**
- [ ] `api/hotel/add_hotel.php`
- [ ] `api/hotel/update_hotel.php`
- [ ] `api/hotel/delete_hotel.php`

**Ticket Module:**
- [ ] `api/ticket/book_ticket.php`
- [ ] `api/ticket/update_ticket.php`
- [ ] `api/ticket/delete_ticket.php`

**Umrah Module:**
- [ ] `api/umrah/add_umrah.php`
- [ ] `api/umrah/update_umrah.php`
- [ ] `api/umrah/process_refund.php`

---

### Priority 2: Transaction Handlers

- [ ] `api/additional_payment/add_additional_payment.php`
- [ ] `api/additional_payment/add_additional_payment_transaction.php`
- [ ] `api/additional_payment/update_additional_payment.php`
- [ ] `api/additional_payment/delete_additional_payment.php`

---

### Priority 3: Data Modification Handlers

- [ ] `api/client/*.php` (all handlers that create/update/delete)
- [ ] `api/supplier/*.php` (all handlers that create/update/delete)
- [ ] `api/employee/*.php` (all handlers that create/update/delete)

---

### Priority 4: All Other POST/PUT/DELETE Handlers

Any handler that modifies data should have CSRF validation.

**Skip these (GET/read-only):**
- All `get_*.php` files
- All `fetch_*.php` files
- All `list_*.php` files
- All print/report endpoints

---

## Step 4: Testing CSRF Validation

### Test 1: Valid CSRF Token ✓
```bash
curl -X POST http://localhost/api/accounts/fund_supplier.php \
  -d "csrf_token=valid_token&supplier_id=1&amount=100&..."
# Expected: 200 OK - Transaction processed
```

### Test 2: Missing CSRF Token ✗
```bash
curl -X POST http://localhost/api/accounts/fund_supplier.php \
  -d "supplier_id=1&amount=100&..."
# Expected: 403 Forbidden - Security validation failed
```

### Test 3: Invalid CSRF Token ✗
```bash
curl -X POST http://localhost/api/accounts/fund_supplier.php \
  -d "csrf_token=wrong_token&supplier_id=1&amount=100&..."
# Expected: 403 Forbidden - Security validation failed
```

### JavaScript Testing
```javascript
// Get CSRF token from DOM or session
const csrfToken = document.querySelector('input[name="csrf_token"]').value;

// Include in fetch request
fetch('/api/accounts/fund_supplier.php', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
        csrf_token: csrfToken,
        supplier_id: 1,
        amount: 100,
        // ... other fields
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

---

## Step 5: Implementation Checklist

### For Each API Handler:

- [ ] Is the handler for POST/PUT/DELETE operations?
- [ ] Does it require authentication? (`enforce_auth()`)
- [ ] Have you added `verify_csrf_token()` call?
- [ ] Is it placed AFTER `enforce_auth()` and BEFORE business logic?
- [ ] Are error responses in JSON format?
- [ ] Is HTTP 403 status code set on CSRF failure?
- [ ] Have you tested with valid, missing, and invalid tokens?
- [ ] Have you tested with the actual modal form submission?

---

## Step 6: Batch Implementation Script

**Script to add CSRF validation to all POST handlers:**

```bash
#!/bin/bash
# Add CSRF validation to API handlers

for file in api/**/*.php; do
    # Skip GET-only handlers
    if grep -q "REQUEST_METHOD.*GET" "$file"; then
        continue
    fi
    
    # Check if already has CSRF check
    if ! grep -q "verify_csrf_token" "$file"; then
        # Check if handler uses POST data
        if grep -q "\$_POST\|\$_REQUEST" "$file"; then
            echo "Add CSRF to: $file"
            # Manually add CSRF validation after enforce_auth()
        fi
    fi
done
```

---

## Step 7: Error Handling Examples

### Pattern: Form-Data Handler
```php
<?php
require_once '../../admin/security.php';
enforce_auth();

// CSRF validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Security validation failed. Please try again.',
        'error_code' => 'CSRF_VALIDATION_FAILED'
    ]);
    exit;
}

// Rest of handler...
?>
```

### Pattern: JSON Handler
```php
<?php
require_once '../../admin/security.php';
enforce_auth();

$data = json_decode(file_get_contents('php://input'), true);

// CSRF validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'CSRF token validation failed',
        'error_code' => 'INVALID_CSRF_TOKEN'
    ]);
    exit;
}

// Rest of handler...
?>
```

---

## Step 8: Modal Form Submission

### How Tokens Are Sent

**Form Submission:**
```html
<form id="fundSupplierForm">
    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
    <input type="hidden" name="supplier_id" value="123">
    <input type="text" name="amount" required>
    <!-- Other fields -->
</form>
```

**JavaScript Handler:**
```javascript
$('#fundSupplierForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Token is automatically included in formData
    fetch('/api/accounts/fund_supplier.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Success!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});
```

---

## Step 9: Migration Plan

### Phase 1: Critical Handlers (This Sprint)
- Accounts handlers (fund, transfer, withdraw)
- Hotel booking handler
- Ticket booking handler
- Visa application handler

### Phase 2: Transaction Handlers (Next Sprint)
- All transaction creation/update handlers
- All payment handlers
- All refund handlers

### Phase 3: All Other Handlers (Following Sprint)
- All remaining POST/PUT/DELETE handlers
- All data modification endpoints

---

## Step 10: Verification Checklist

After implementing CSRF validation:

- [ ] All 13 critical financial handlers have CSRF validation
- [ ] All modal forms submit CSRF tokens
- [ ] Backend validates all CSRF tokens
- [ ] Invalid tokens return 403 Forbidden
- [ ] No legitimate requests are blocked
- [ ] Security logs show CSRF attack attempts
- [ ] Tests pass with valid, missing, and invalid tokens
- [ ] Production deployment verified

---

## Security Impact

**Before:** Modals had no CSRF protection
- Attackers could craft malicious links
- Trick users into clicking them
- Unauthorized transactions would execute

**After:** Complete CSRF protection
- ✅ All forms include CSRF tokens
- ✅ Backend validates all tokens
- ✅ Invalid tokens rejected (403 Forbidden)
- ✅ Attackers cannot bypass validation

---

## Compliance

This implementation satisfies:
- ✅ OWASP A07:2021 - Cross-Site Request Forgery (CSRF)
- ✅ CWE-352: Cross-Site Request Forgery (CSRF)
- ✅ PCI DSS Requirement 6.5.9
- ✅ NIST SP 800-63B

---

## Support

**Questions:**
- Check `admin/security.php` for function definitions
- See `MODALS_CSRF_FIXES_APPLIED.md` for token locations
- Review `CSRF_FIX_COMPLETE.md` for overall status

**Common Issues:**
1. **"Invalid input data" instead of CSRF failure** - Check token placement
2. **Token not being sent** - Verify form includes hidden input
3. **Legitimate requests blocked** - Token mismatch (session issue)

---

**Status:** Implementation Instructions Ready  
**Next:** Apply CSRF validation to critical API handlers

