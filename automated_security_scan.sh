#!/bin/bash

# Automated Security Penetration Testing Script
# For: mtravels application (LOCAL TESTING ONLY)
# Target: http://localhost/almoqadas/mtravels/
# Requirements: curl, jq (optional for JSON parsing)

TARGET="http://localhost/almoqadas/mtravels"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="security_report_$TIMESTAMP.txt"

echo "========================================"
echo "   Security Penetration Test Suite"
echo "========================================"
echo "Target: $TARGET"
echo "Report: $REPORT_FILE"
echo "Started: $(date)"
echo ""

exec > >(tee -a "$REPORT_FILE")
exec 2>&1

VULN_COUNT=0

# Test 1: SQL Injection
echo "[*] Testing SQL Injection Vulnerabilities..."
SQL_PAYLOADS=(
    "1' OR '1'='1"
    "admin' --"
    "1' UNION SELECT NULL--"
    "1'; DROP TABLE users; --"
)

for PAYLOAD in "${SQL_PAYLOADS[@]}"; do
    RESPONSE=$(curl -s "$TARGET/admin/assets.php?id=$(urlencode "$PAYLOAD")" 2>/dev/null)
    if echo "$RESPONSE" | grep -qi "SQL\|syntax\|database error\|mysql"; then
        echo "[!] VULNERABLE: SQL Injection detected with payload: $PAYLOAD"
        ((VULN_COUNT++))
    fi
done
echo "    [✓] SQL Injection tests complete"
echo ""

# Test 2: XSS Injection
echo "[*] Testing XSS Vulnerabilities..."
XSS_PAYLOADS=(
    '<script>alert("XSS")</script>'
    '"><script>alert(1)</script>'
    '<img src=x onerror=alert("XSS")>'
    '<svg onload=alert("XSS")>'
)

for PAYLOAD in "${XSS_PAYLOADS[@]}"; do
    RESPONSE=$(curl -s "$TARGET/index.php?search=$(urlencode "$PAYLOAD")" 2>/dev/null)
    if echo "$RESPONSE" | grep -q "$PAYLOAD"; then
        echo "[!] VULNERABLE: XSS detected - payload reflected: $PAYLOAD"
        ((VULN_COUNT++))
    fi
done
echo "    [✓] XSS tests complete"
echo ""

# Test 3: CSRF Protection
echo "[*] Testing CSRF Protection..."
CSRF_TEST=$(curl -s -X POST \
    -d "edit_asset=1&asset_id=1&name=Test" \
    "$TARGET/admin/assets.php" 2>/dev/null)

if echo "$CSRF_TEST" | grep -qvi "csrf\|token\|invalid"; then
    echo "[!] VULNERABLE: CSRF token not validated on POST"
    ((VULN_COUNT++))
else
    echo "    [✓] CSRF protection enabled"
fi
echo ""

# Test 4: Authentication Bypass
echo "[*] Testing Authentication..."
ADMIN_CHECK=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/admin/assets.php" 2>/dev/null)
if [ "$ADMIN_CHECK" = "200" ]; then
    echo "[!] VULNERABLE: Admin page accessible without authentication"
    ((VULN_COUNT++))
else
    echo "    [✓] Authentication required (HTTP $ADMIN_CHECK)"
fi
echo ""

# Test 5: Default Credentials
echo "[*] Testing Default Credentials..."
DEFAULT_CREDS=(
    "admin:admin"
    "admin:password"
    "admin:123456"
    "admin:admin@123"
)

for CRED in "${DEFAULT_CREDS[@]}"; do
    IFS=':' read -r USERNAME PASSWORD <<< "$CRED"
    LOGIN_TEST=$(curl -s -X POST \
        -d "username=$USERNAME&password=$PASSWORD&login=Login" \
        "$TARGET/login.php" 2>/dev/null)
    
    if echo "$LOGIN_TEST" | grep -qi "dashboard\|welcome\|logged"; then
        echo "[!] CRITICAL: Default credentials work: $USERNAME:$PASSWORD"
        ((VULN_COUNT++))
    fi
done
echo "    [✓] Default credentials tests complete"
echo ""

# Test 6: Security Headers
echo "[*] Checking Security Headers..."
HEADERS=$(curl -s -I "$TARGET/" 2>/dev/null)

REQUIRED_HEADERS=(
    "Content-Security-Policy"
    "X-Frame-Options"
    "X-Content-Type-Options"
    "Strict-Transport-Security"
    "X-XSS-Protection"
)

for HEADER in "${REQUIRED_HEADERS[@]}"; do
    if echo "$HEADERS" | grep -qi "$HEADER"; then
        echo "    [✓] $HEADER present"
    else
        echo "    [!] WARNING: Missing security header: $HEADER"
    fi
done
echo ""

# Test 7: Sensitive File Disclosure
echo "[*] Checking for Sensitive File Disclosure..."
SENSITIVE_FILES=(
    "config.php"
    ".env"
    "database_structure.sql"
    ".htaccess"
)

for FILE in "${SENSITIVE_FILES[@]}"; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/$FILE" 2>/dev/null)
    if [ "$HTTP_CODE" = "200" ]; then
        echo "[!] WARNING: Sensitive file accessible: $FILE (HTTP $HTTP_CODE)"
    fi
done
echo "    [✓] File disclosure tests complete"
echo ""

# Test 8: Path Traversal
echo "[*] Testing Path Traversal..."
TRAVERSAL_PAYLOADS=(
    "../../../etc/passwd"
    "../../config.php"
    "....//....//config.php"
)

for PAYLOAD in "${TRAVERSAL_PAYLOADS[@]}"; do
    RESPONSE=$(curl -s "$TARGET/admin/file_browser.php?dir=$(urlencode "$PAYLOAD")" 2>/dev/null)
    if [ ${#RESPONSE} -gt 100 ] && ! echo "$RESPONSE" | grep -qi "access denied\|forbidden"; then
        echo "[!] VULNERABLE: Path traversal possible: $PAYLOAD"
        ((VULN_COUNT++))
    fi
done
echo "    [✓] Path traversal tests complete"
echo ""

# Test 9: Input Validation
echo "[*] Testing Input Validation..."
INVALID_INPUTS=(
    "email=notanemail"
    "phone=notaphone"
    "amount=abc"
    "date=invalid"
)

for INPUT in "${INVALID_INPUTS[@]}"; do
    RESPONSE=$(curl -s -X POST -d "$INPUT" "$TARGET/contact_handler.php" 2>/dev/null)
    if ! echo "$RESPONSE" | grep -qi "error\|invalid\|required"; then
        echo "    [!] WARNING: Input not validated: $INPUT"
    fi
done
echo "    [✓] Input validation tests complete"
echo ""

# Test 10: File Permissions
echo "[*] Checking File Permissions..."
if [ -d "uploads" ]; then
    PERMS=$(ls -ld uploads | awk '{print $1}')
    echo "    uploads/ permissions: $PERMS"
    
    PHP_COUNT=$(find uploads -name "*.php" 2>/dev/null | wc -l)
    if [ "$PHP_COUNT" -gt 0 ]; then
        echo "[!] CRITICAL: PHP files in uploads directory found"
        ((VULN_COUNT++))
    fi
fi
echo ""

# Summary
echo "========================================"
echo "SECURITY TEST SUMMARY"
echo "========================================"
echo "Total Vulnerabilities Found: $VULN_COUNT"
if [ "$VULN_COUNT" -eq 0 ]; then
    echo "Status: ✅ SECURE"
else
    echo "Status: ⚠️  VULNERABLE"
fi
echo ""
echo "Test completed: $(date)"
echo "Report saved to: $REPORT_FILE"
echo ""
echo "Recommendations:"
echo "1. Fix all CRITICAL vulnerabilities immediately"
echo "2. Add security headers to .htaccess or web server config"
echo "3. Review and update input validation"
echo "4. Implement CSRF tokens on all state-changing requests"
echo "5. Run file permission audit"
echo "6. Review access logs for suspicious activity"
echo ""
