#!/bin/bash

# MTravels Daily Security Check Script
# This script should be run daily via cron job

echo "🔍 MTravels Daily Security Check - $(date)"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
LOG_FILE="/var/log/mtravels-security.log"
REPORT_EMAIL="security@mtravels.com"
ALERTS=0

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
    echo "$1"
}

alert() {
    echo -e "${RED}🚨 ALERT: $1${NC}"
    ALERTS=$((ALERTS + 1))
    log "ALERT: $1"
}

success() {
    echo -e "${GREEN}✓ $1${NC}"
    log "SUCCESS: $1"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    log "WARNING: $1"
}

# Check 1: File permissions
echo "📁 Checking file permissions..."
if [ -f ".env" ]; then
    perms=$(stat -c "%a" .env 2>/dev/null || stat -f "%A" .env 2>/dev/null)
    if [ "$perms" -gt "600" ]; then
        alert ".env file has overly permissive permissions: $perms"
    else
        success ".env file permissions are secure"
    fi
else
    warning ".env file not found"
fi

# Check 2: SSL certificate expiry
echo "🔒 Checking SSL certificate..."
if command -v openssl >/dev/null 2>&1; then
    # Get domain from .env or use localhost for development
    if [ -f ".env" ]; then
        DOMAIN=$(grep -E "^APP_URL|DOMAIN" .env | cut -d'=' -f2 | tr -d '"' | head -1)
    fi

    if [ -n "$DOMAIN" ] && [ "$DOMAIN" != "localhost" ]; then
        expiry=$(openssl s_client -connect "$DOMAIN:443" -servername "$DOMAIN" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d'=' -f2)
        if [ -n "$expiry" ]; then
            expiry_epoch=$(date -d "$expiry" +%s 2>/dev/null || date -j -f "%b %d %H:%M:%S %Y %Z" "$expiry" +%s 2>/dev/null)
            now_epoch=$(date +%s)
            days_left=$(( (expiry_epoch - now_epoch) / 86400 ))

            if [ $days_left -lt 30 ]; then
                alert "SSL certificate expires in $days_left days"
            elif [ $days_left -lt 60 ]; then
                warning "SSL certificate expires in $days_left days"
            else
                success "SSL certificate valid for $days_left more days"
            fi
        else
            warning "Could not check SSL certificate expiry"
        fi
    else
        success "SSL check skipped (localhost/development)"
    fi
else
    warning "OpenSSL not available for SSL checks"
fi

# Check 3: Security headers
echo "🛡️  Checking security headers..."
if [ -f ".htaccess" ]; then
    if grep -q "Strict-Transport-Security" .htaccess; then
        success "HSTS header configured"
    else
        alert "HSTS header missing from .htaccess"
    fi

    if grep -q "X-Frame-Options" .htaccess; then
        success "X-Frame-Options header configured"
    else
        alert "X-Frame-Options header missing"
    fi
else
    alert ".htaccess file not found"
fi

# Check 4: Run PHP security audit
echo "🐘 Running PHP security audit..."
if [ -f "scripts/security-audit.php" ]; then
    php scripts/security-audit.php > /tmp/security_audit.log 2>&1
    if [ $? -eq 0 ]; then
        success "PHP security audit passed"
    else
        alert "PHP security audit found issues"
        cat /tmp/security_audit.log >> "$LOG_FILE"
    fi
else
    warning "PHP security audit script not found"
fi

# Check 5: Dependency vulnerabilities
echo "📦 Checking dependencies..."
if [ -f "composer.lock" ] && command -v composer >/dev/null 2>&1; then
    if composer audit --no-interaction > /tmp/composer_audit.log 2>&1; then
        success "Composer dependencies are secure"
    else
        alert "Composer security vulnerabilities found"
        cat /tmp/composer_audit.log >> "$LOG_FILE"
    fi
else
    warning "Composer audit not available"
fi

# Check 6: Log file analysis
echo "📊 Analyzing log files..."
if [ -f "$LOG_FILE" ]; then
    # Check for recent security events
    recent_alerts=$(grep -c "ALERT" "$LOG_FILE" 2>/dev/null || echo "0")
    if [ "$recent_alerts" -gt 0 ]; then
        warning "Found $recent_alerts recent security alerts in logs"
    else
        success "No recent security alerts in logs"
    fi
else
    warning "Security log file not found"
fi

# Check 7: Backup verification
echo "💾 Checking backup integrity..."
# This would need customization based on your backup system
success "Backup check placeholder - customize for your backup system"

# Summary
echo ""
echo "📋 Security Check Summary"
echo "========================="
echo "Date: $(date)"
echo "Alerts: $ALERTS"

if [ $ALERTS -eq 0 ]; then
    echo -e "${GREEN}🎉 All security checks passed!${NC}"
    log "Daily security check completed successfully"
else
    echo -e "${RED}⚠️  $ALERTS security issues found${NC}"
    log "Daily security check found $ALERTS issues"

    # Send email alert if configured
    if command -v mail >/dev/null 2>&1 && [ -n "$REPORT_EMAIL" ]; then
        echo "Security check found $ALERTS issues on $(date)" | mail -s "MTravels Security Alert" "$REPORT_EMAIL"
    fi
fi

echo "Full log available at: $LOG_FILE"
echo ""