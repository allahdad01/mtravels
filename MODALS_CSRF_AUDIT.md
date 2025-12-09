# Modal CSRF Protection Audit Report

**Date:** December 9, 2025  
**Total Modals Checked:** 120+  
**Status:** ⚠️ CRITICAL - Most modals missing CSRF tokens

---

## Executive Summary

**🔴 CRITICAL FINDING:** Out of 120+ modal files checked, **only 10 modals have CSRF protection**, while **110+ modals are completely unprotected**. This is a **critical security vulnerability** as modals handle form submissions for sensitive operations (payments, bookings, account transfers, etc.).

---

## Modals WITH CSRF Protection (10 files)

### ✅ Protected Modals:

1. **additional_payment/add_payment_modal.php** ✅
   - Line 14: `<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">`
   - Uses `h()` function for escaping

2. **additional_payment/edit_payment_modal.php** ✅
   - Line 14: CSRF token present with proper escaping

3. **umrah/umrah_modal.php** ✅
   - Line 14: CSRF token included

4. **umrah/edit_member_modal.php** ✅
   - Line 14: CSRF token included

5. **umrah/edit_family_modal.php** ✅
   - Line 14: CSRF token included

6. **umrah/create_family_modal.php** ✅
   - Line 14: CSRF token included

7. **umrah/transaction_modal.php** ✅
   - Line 122: `<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">`
   - Missing escaping with `h()` function (XSS risk)

8. **visa/transaction_modal.php** ✅
   - Line 119: CSRF token present but unescaped

9. **hotel/transaction_modal.php** ✅
   - Line 120: CSRF token present but unescaped

10. **ticket_date_change/add_date_change.php** ✅
    - Line 81: `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">`
    - Unescaped output (XSS risk)

---

## Modals WITHOUT CSRF Protection (110+ files)

### 🔴 UNPROTECTED - CRITICAL PRIORITY

**accounts/ folder (12 modals) - ALL MISSING CSRF:**
- ❌ add_main_account_modal.php
- ❌ bonus_supplier_modal.php
- ❌ client_payment_modal.php (handles payments!)
- ❌ client_transaction_history_modal.php
- ❌ edit_main_account_modal.php
- ❌ edit_transaction_modal.php
- ❌ fund_supplier_modal.php (handles transfers!)
- ❌ main_account_transaction_history_modal.php
- ❌ remarks_modal.php
- ❌ supplier_transaction_history_modal.php
- ❌ transfer_modal.php (handles account transfers!)
- ❌ withdraw_supplier_modal.php

**allocation/ folder (4 modals) - ALL MISSING:**
- ❌ allocation_modal.php
- ❌ expense_modal.php
- ❌ fund_modal.php
- ❌ view_fund_modal.php

**additional_payment/ folder (2 modals) - PARTIALLY MISSING:**
- ✅ add_payment_modal.php
- ✅ edit_payment_modal.php
- ❌ add_transaction_modal.php (no CSRF)
- ❌ edit_transaction_modal.php (no CSRF)

**client/ folder (2 modals) - ALL MISSING:**
- ❌ add_client.php
- ❌ edit_client.php

**dashboard/ folder (3 modals) - ALL MISSING:**
- ❌ debtor_modal.php
- ❌ receipt_modal.php
- ❌ sales_modal.php

**employee/ folder (6 modals) - ALL MISSING:**
- ❌ fine_modal.php
- ❌ gurantor_modal.php
- ❌ ikhtar_modal.php
- ❌ language_selection_modal.php
- ❌ tawseah_modal.php
- ❌ termination_modal.php

**expense/ folder (2 modals) - ALL MISSING:**
- ❌ category_modal.php
- ❌ expense_modal.php

**hotel/ folder (6 modals) - PARTIALLY MISSING:**
- ✅ transaction_modal.php
- ❌ add_hotel_modal.php (no CSRF - handles bookings!)
- ❌ edit_hotel_modal.php
- ❌ edit_transaction_modal.php
- ❌ multi_ticket.php
- ❌ refund_modal.php
- ❌ view_details_modal.php

**hotel_refund/ folder (2 modals) - ALL MISSING:**
- ❌ edit_transaction_modal.php
- ❌ transaction_modal.php

**maktob/ folder (3 modals) - ALL MISSING:**
- ❌ delete_modal.php
- ❌ edit_modal.php
- ❌ view_modal.php

**send_message/ folder (3 modals) - ALL MISSING:**
- ❌ delete_message_modal.php
- ❌ edit_message_modal.php
- ❌ view_modal.php

**supplier/ folder (2 modals) - ALL MISSING:**
- ❌ add_supplier.php
- ❌ edit_supplier.php

**ticket/ folder (8 modals) - ALL MISSING:**
- ❌ book_ticket_modal.php (handles bookings + SQL injection!)
- ❌ edit_ticket_modal.php
- ❌ multi_ticket_modal.php
- ❌ ticket_date_change_modal.php
- ❌ ticket_details.php
- ❌ ticket_refund_modal.php
- ❌ ticket_weight_modal.php
- ❌ transaction_modal.php

**ticket_date_change/ folder (3 modals) - PARTIALLY MISSING:**
- ✅ add_date_change.php
- ❌ edit_transaction.php
- ❌ multi_ticket.php
- ❌ transaction_modal.php

**ticket_refund/ folder (4 modals) - ALL MISSING:**
- ❌ edit_transaction_modal.php
- ❌ multi_ticket.php
- ❌ refund_ticket_modal.php
- ❌ transaction_modal.php

**ticket_reserve/ folder (5 modals) - ALL MISSING:**
- ❌ book_ticket_modal.php
- ❌ edit_ticket_modal.php
- ❌ multi_ticket_modal.php
- ❌ ticket_details.php
- ❌ transaction_modal.php

**ticket_weight/ folder (4 modals) - ALL MISSING:**
- ❌ book_ticket_modal.php
- ❌ edit_ticket_modal.php
- ❌ multi_ticket_modal.php
- ❌ transaction_modal.php

**umrah/ folder (25 modals) - PARTIALLY MISSING:**
- ✅ umrah_modal.php
- ✅ edit_member_modal.php
- ✅ edit_family_modal.php
- ✅ create_family_modal.php
- ✅ transaction_modal.php
- ❌ bank_receipt_modal.php
- ❌ cancellation_details_modal.php
- ❌ cancellation_reapply_modal.php
- ❌ completion_details_modal.php
- ❌ date_change_modal.php
- ❌ edit_transaction_modal.php
- ❌ family_cancellation_details_modal.php
- ❌ family_cancellation_language_modal.php
- ❌ family_completion_details_modal.php
- ❌ family_language_modal.php
- ❌ family_transaction_modal.php
- ❌ group_ticket_modal.php
- ❌ id_card_modal.php
- ❌ language_modal.php
- ❌ member_details_modal.php
- ❌ member_document_template.php
- ❌ multi_ticket_invoice_modal.php
- ❌ profile_modal.php
- ❌ refund_modal.php
- ❌ settings_modal.php
- ❌ umrah_presidency_modal.php

**umrah_date_change/ folder (2 modals) - ALL MISSING:**
- ❌ date_change_modal.php
- ❌ penalty_modal.php

**umrah_refund/ folder (2 modals) - ALL MISSING:**
- ❌ edit_transaction_modal.php
- ❌ transaction_modal.php

**visa/ folder (11 modals) - PARTIALLY MISSING:**
- ✅ transaction_modal.php
- ❌ add_visa_modal.php (no CSRF - handles applications!)
- ❌ cancellation_modal.php
- ❌ details_modal.php
- ❌ edit_transaction_modal.php
- ❌ edit_visa_modal.php
- ❌ multi_visa_mdal.php
- ❌ reapply_modal.php
- ❌ refund_modal.php

**visa_refund/ folder (2 modals) - ALL MISSING:**
- ❌ edit_transaction_modal.php
- ❌ transaction_modal.php

---

## Critical Issues Found

### Issue #1: Missing CSRF Protection on Payment/Transfer Modals

**Severity:** 🔴 CRITICAL

**Vulnerable Modals:**
- `accounts/fund_supplier_modal.php` - **Transfers money between accounts**
- `accounts/transfer_modal.php` - **Account transfers**
- `accounts/client_payment_modal.php` - **Client payments**
- `accounts/withdraw_supplier_modal.php` - **Supplier withdrawals**
- `accounts/bonus_supplier_modal.php` - **Bonus payments**

**Risk:** Attackers can perform unauthorized transactions via CSRF attacks

---

### Issue #2: Missing CSRF Protection on Booking/Transaction Modals

**Severity:** 🔴 CRITICAL

**Vulnerable Modals:**
- `hotel/add_hotel_modal.php` - **Hotel booking creation**
- `ticket/book_ticket_modal.php` - **Ticket booking creation + SQL injection in line 44!**
- `visa/add_visa_modal.php` - **Visa application creation**
- `ticket_reserve/book_ticket_modal.php` - **Ticket reservation**

**Risk:** Unauthorized bookings/applications, financial fraud

---

### Issue #3: XSS in CSRF Token Output (Inconsistent Escaping)

**Severity:** 🟠 HIGH

**Vulnerable Modals (unescaped output):**
- `umrah/transaction_modal.php:122` - `<?php echo $_SESSION['csrf_token']; ?>` (not escaped)
- `visa/transaction_modal.php:119` - `<?php echo $_SESSION['csrf_token']; ?>` (not escaped)
- `hotel/transaction_modal.php:120` - `<?php echo $_SESSION['csrf_token']; ?>` (not escaped)
- `ticket_date_change/add_date_change.php:81` - `<?= $_SESSION['csrf_token'] ?>` (not escaped)

**Risk:** If session token is compromised, it could be exposed via XSS

**Fix:** Use `<?php echo h($_SESSION['csrf_token']); ?>` in all modals

---

### Issue #4: SQL Injection in book_ticket_modal.php

**Severity:** 🔴 CRITICAL

**File:** `modals/ticket/book_ticket_modal.php:44, 219`

**Vulnerable Code:**
```php
$result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id AND branch_id = $branch_id");
```

**Issue:** Direct string interpolation of `$tenant_id` and `$branch_id` (already fixed in this audit, but needs updating in modal too)

---

## Remediation Action Items

### Priority 1: Add CSRF Tokens to Critical Modals (Today)

**Payment/Transfer Modals:**
```php
<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
```

Files to update:
1. accounts/fund_supplier_modal.php
2. accounts/transfer_modal.php
3. accounts/client_payment_modal.php
4. accounts/withdraw_supplier_modal.php
5. accounts/bonus_supplier_modal.php

**Booking/Application Modals:**
6. hotel/add_hotel_modal.php
7. ticket/book_ticket_modal.php
8. visa/add_visa_modal.php
9. ticket_reserve/book_ticket_modal.php

**Estimated time:** 1-2 hours

---

### Priority 2: Add CSRF Tokens to All Remaining Modals (This Week)

Add CSRF token to all 100+ remaining modals.

**Script to find all modals:**
```bash
find modals -name "*.php" ! -exec grep -l "csrf_token" {} \;
```

**Estimated time:** 4-6 hours (can be automated)

---

### Priority 3: Fix XSS in CSRF Token Output (This Week)

Update all modals to use proper escaping:

**Before:**
```php
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
```

**After:**
```php
<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
```

Files to update:
1. umrah/transaction_modal.php:122
2. visa/transaction_modal.php:119
3. hotel/transaction_modal.php:120
4. ticket_date_change/add_date_change.php:81

**Estimated time:** 30 minutes

---

### Priority 4: Fix SQL Injection in Modals (This Week)

Update modals that use direct SQL queries with session variables to use prepared statements.

**Files to check/fix:**
- ticket/book_ticket_modal.php (lines 44, 219)
- ticket_reserve/book_ticket_modal.php
- ticket_weight/book_ticket_modal.php

**Estimated time:** 2-3 hours

---

## Automated CSRF Token Template

To make fixing all modals easier, here's a standard CSRF token snippet for all forms:

```php
<!-- At the beginning of every modal form -->
<form id="formName" method="POST">
    <!-- Add this line to every form -->
    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
    
    <!-- Rest of form fields -->
</form>
```

---

## Backend Validation Checklist

Ensure these handlers validate CSRF tokens:

- [ ] admin/security.php - verify_csrf_token() function (already implemented)
- [ ] All form handlers in api/ folder
- [ ] All form handlers in admin/ folder
- [ ] All handlers that process modal form submissions

---

## Testing Procedure

After adding CSRF tokens to all modals:

1. **Test valid CSRF token:**
   - Submit form with matching token
   - Should succeed ✓

2. **Test missing CSRF token:**
   - Submit form without token
   - Should fail with 403 Forbidden ✓

3. **Test invalid CSRF token:**
   - Submit form with mismatched token
   - Should fail with 403 Forbidden ✓

4. **Test expired CSRF token:**
   - Submit form after session changes
   - Should fail with 403 Forbidden ✓

---

## Files to Create

### File 1: includes/csrf_helper.php

```php
<?php
/**
 * CSRF Helper Functions
 */

/**
 * Get CSRF token for use in forms
 * @return string CSRF token
 */
function get_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate CSRF token hidden input field
 * @return string HTML hidden input
 */
function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * Verify CSRF token from POST request
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verify_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? null);
    
    if (!$token || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
```

Then use in modals:
```php
<?php echo csrf_field(); ?>
```

---

## Summary Statistics

| Category | Count | With CSRF | Without CSRF | % Protected |
|----------|-------|-----------|--------------|------------|
| accounts | 12 | 0 | 12 | 0% |
| allocation | 4 | 0 | 4 | 0% |
| additional_payment | 4 | 2 | 2 | 50% |
| client | 2 | 0 | 2 | 0% |
| dashboard | 3 | 0 | 3 | 0% |
| employee | 6 | 0 | 6 | 0% |
| expense | 2 | 0 | 2 | 0% |
| hotel | 7 | 1 | 6 | 14% |
| hotel_refund | 2 | 0 | 2 | 0% |
| maktob | 3 | 0 | 3 | 0% |
| send_message | 3 | 0 | 3 | 0% |
| supplier | 2 | 0 | 2 | 0% |
| ticket | 8 | 0 | 8 | 0% |
| ticket_date_change | 4 | 1 | 3 | 25% |
| ticket_refund | 4 | 0 | 4 | 0% |
| ticket_reserve | 5 | 0 | 5 | 0% |
| ticket_weight | 4 | 0 | 4 | 0% |
| umrah | 25 | 5 | 20 | 20% |
| umrah_date_change | 2 | 0 | 2 | 0% |
| umrah_refund | 2 | 0 | 2 | 0% |
| visa | 11 | 1 | 10 | 9% |
| visa_refund | 2 | 0 | 2 | 0% |
| **TOTAL** | **125** | **10** | **115** | **8%** |

---

## Compliance Impact

This vulnerability violates:
- ✗ OWASP A01:2021 – Broken Access Control (CSRF attacks can bypass authorization)
- ✗ OWASP A07:2021 – Cross-Site Request Forgery
- ✗ PCI DSS Requirement 6.5.9 (protection against CSRF)
- ✗ CWE-352: Cross-Site Request Forgery (CSRF)

---

## Estimated Total Fix Time

- **Priority 1 (Payment modals):** 1-2 hours
- **Priority 2 (All modals):** 4-6 hours
- **Priority 3 (XSS fix):** 30 minutes
- **Priority 4 (SQL injection):** 2-3 hours
- **Total:** 8-12 hours

---

**Status:** URGENT - Requires immediate action  
**Last Updated:** December 9, 2025  
**Recommended Action:** Start with Priority 1 & 3 today (2 hours max)
