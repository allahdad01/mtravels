# Security Implementation Checklist
**Start Date:** February 7, 2026  
**Target Completion:** February 15, 2026

---

## Phase 1: Critical Vulnerabilities (MUST DO FIRST)
**Target:** Complete by Feb 9, 2026

- [x] **FIX #1: Create SecureFileUpload Class** 
  - File: `/includes/SecureFileUpload.php`
  - Status: ✅ COMPLETED
  - Tests: Validates MIME types, file sizes, prevents directory traversal

- [x] **FIX #2: Create CsrfProtection Class**
  - File: `/includes/CsrfProtection.php`
  - Status: ✅ COMPLETED
  - Tests: Token generation, validation, constant-time comparison

- [x] **FIX #3: Create InputValidator Class**
  - File: `/includes/InputValidator.php`
  - Status: ✅ COMPLETED
  - Tests: Email, int, enum, date, password strength validation

- [x] **FIX #4: Fix Shell Execution in paddle_ocr.php**
  - File: `/includes/paddle_ocr.php`
  - Status: ✅ COMPLETED
  - Changes: Replaced `shell_exec()` with `getTesseractPath()` and `proc_open()`

- [x] **FIX #5: Fix Shell Execution in document_patterns.php**
  - File: `/includes/document_patterns.php`
  - Status: ✅ COMPLETED
  - Changes: Replaced `shell_exec()` and `exec()` with safe path detection and `proc_open()`

- [ ] **FIX #6: Secure File Upload in admin/assets.php**
  - File: `/admin/assets.php`
  - Task: Replace lines 59-77 and 136-162 with SecureFileUpload class
  - Priority: CRITICAL

- [ ] **FIX #7: Secure File Upload in admin/save_user.php**
  - File: `/admin/save_user.php`
  - Task: Use SecureFileUpload for profile pics and documents
  - Priority: CRITICAL

- [ ] **FIX #8: Secure File Upload in admin/customer_detail.php**
  - File: `/admin/customer_detail.php`
  - Task: Use SecureFileUpload for receipt uploads
  - Priority: CRITICAL

- [ ] **FIX #9: Add CSRF Protection to All POST API Endpoints**
  - Files: All files in `/api/*/handler.php`
  - Task: Add at top of each handler:
    ```php
    require_once '../../includes/CsrfProtection.php';
    if (!CsrfProtection::validateRequest('POST')) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF validation failed']));
    }
    ```
  - Priority: CRITICAL

- [ ] **FIX #10: Validate Dashboard Date Parameters**
  - File: `/admin/dashboard.php`
  - Task: Replace line 1025 with:
    ```php
    $selected_date = InputValidator::validateDate(
        $_GET['departure_date'] ?? null,
        'Y-m-d'
    ) ?? date('Y-m-d');
    ```
  - Priority: CRITICAL

- [ ] **FIX #11: Validate Budget Allocation Parameters**
  - File: `/admin/budget_allocations.php`
  - Task: Replace lines 30-31 with InputValidator validation
  - Priority: CRITICAL

---

## Phase 2: Session & Authentication (Complete by Feb 11)

- [ ] **FIX #12: Add IP Binding to Sessions**
  - File: `/includes/session_check.php`
  - Changes: Add IP and User-Agent verification
  - Instructions: See SECURITY_FIXES_IMPLEMENTATION_GUIDE.md (Fix #6)
  - Priority: HIGH

- [ ] **FIX #13: Enforce Password Strength on Reset**
  - Files: All password reset endpoints
  - Create: `/includes/PasswordValidator.php`
  - Task: Validate passwords match strength requirements
  - Instructions: See SECURITY_FIXES_IMPLEMENTATION_GUIDE.md (Fix #7)
  - Priority: HIGH

- [ ] **FIX #14: Add Rate Limiting to Sensitive Endpoints**
  - Files: Password reset, email verification, payment processing
  - Use: Existing `RateLimiter::isAllowed()`
  - Priority: HIGH

---

## Phase 3: API & Data Validation (Complete by Feb 12)

- [ ] **FIX #15: Fix WhatsApp Directory Traversal**
  - File: `/api/whatsapp/index.php`
  - Change: Replace `$path = $_GET['path'] ?? '';` with:
    ```php
    $path = basename($_GET['path'] ?? '');
    ```
  - Priority: HIGH

- [ ] **FIX #16: Validate All GET/POST Parameters**
  - Files: All files in `/api/` directory
  - Use: InputValidator class
  - Pattern:
    ```php
    $status = InputValidator::validateEnum(
        $_GET['status'] ?? '',
        ['active', 'inactive', 'pending']
    );
    ```
  - Priority: HIGH

- [ ] **FIX #17: Add Content-Security-Policy Header**
  - File: `/.htaccess`
  - Add after line 86:
    ```apache
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' fonts.googleapis.com; img-src 'self' data: https:;"
    ```
  - Priority: MEDIUM

---

## Phase 4: Logging & Monitoring (Complete by Feb 13)

- [ ] **FIX #18: Add Security Event Logging**
  - Files: All auth endpoints
  - Log: Failed logins, CSRF attempts, suspicious inputs
  - Use: `error_log()` with clear markers like `[SECURITY]`
  - Priority: MEDIUM

- [ ] **FIX #19: Add Subresource Integrity (SRI) Tags**
  - Files: All HTML templates
  - Task: Add integrity hashes to CDN scripts
  - Priority: LOW

- [ ] **FIX #20: Review and Update API Error Messages**
  - Files: All API endpoints
  - Task: Don't expose database details to users
  - Priority: MEDIUM

---

## Testing Checklist

### Security Testing (Per Fix)
- [ ] File upload validation works
- [ ] CSRF tokens prevent form attacks
- [ ] Input validation rejects malicious data
- [ ] Shell execution uses safe methods
- [ ] Session validation prevents hijacking
- [ ] Password strength enforced

### Integration Testing
- [ ] All features still work after fixes
- [ ] No broken functionality
- [ ] File uploads work correctly
- [ ] Forms submit successfully

### Penetration Testing
- [ ] Try uploading .php files → BLOCKED
- [ ] Try directory traversal → BLOCKED
- [ ] Try SQL injection → SAFE (PDO prepared)
- [ ] Try XSS attacks → SAFE (htmlspecialchars)
- [ ] Try CSRF → BLOCKED (token validation)
- [ ] Try shell injection → SAFE (proc_open)

---

## File Modification Tracking

### Files Created (New Security Classes)
| File | Lines | Status |
|------|-------|--------|
| `/includes/SecureFileUpload.php` | 306 | ✅ DONE |
| `/includes/CsrfProtection.php` | 136 | ✅ DONE |
| `/includes/InputValidator.php` | 378 | ✅ DONE |
| `/includes/PasswordValidator.php` | TBD | ⏳ TODO |

### Files Modified (Vulnerability Fixes)
| File | Changes | Status |
|------|---------|--------|
| `/includes/paddle_ocr.php` | Shell execution | ✅ DONE |
| `/includes/document_patterns.php` | Shell execution | ✅ DONE |
| `/admin/assets.php` | File upload | ⏳ TODO |
| `/admin/save_user.php` | File upload | ⏳ TODO |
| `/admin/customer_detail.php` | File upload | ⏳ TODO |
| `/admin/dashboard.php` | Input validation | ⏳ TODO |
| `/admin/budget_allocations.php` | Input validation | ⏳ TODO |
| `/.htaccess` | Security headers | ⏳ TODO |

---

## Implementation Progress

**Phase 1 (Critical): 5/10 DONE** 🟨  
- Created helper classes
- Fixed shell execution
- Need: File uploads, CSRF API, input validation

**Phase 2 (Session): 0/3 DONE** 🔴  
**Phase 3 (API): 0/3 DONE** 🔴  
**Phase 4 (Logging): 0/2 DONE** 🔴  

---

## Resources & References

- **Implementation Guide:** `SECURITY_FIXES_IMPLEMENTATION_GUIDE.md`
- **Audit Report:** `COMPREHENSIVE_SECURITY_AUDIT_2026.md`
- **OWASP:** https://owasp.org/www-project-top-ten/
- **CWE Details:** https://cwe.mitre.org/

---

## Sign-Off

| Role | Name | Date | Status |
|------|------|------|--------|
| Dev | TBD | — | ⏳ IN PROGRESS |
| QA | TBD | — | ⏳ PENDING |
| Security | TBD | — | ⏳ PENDING |
| Prod | TBD | — | ⏳ PENDING |

---

**Last Updated:** Feb 7, 2026  
**Next Review:** Feb 9, 2026
