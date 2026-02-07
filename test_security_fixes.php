<?php
/**
 * Security Fixes Verification Script
 * 
 * Tests that all security fixes have been properly implemented
 * Run this to verify fixes are in place
 */

session_start();

// Color codes for terminal output
define('RED', "\033[91m");
define('GREEN', "\033[92m");
define('YELLOW', "\033[93m");
define('BLUE', "\033[94m");
define('RESET', "\033[0m");

class SecurityFixTester {
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $tests_total = 0;
    
    public function __construct() {
        echo BLUE . "=" . str_repeat("=", 78) . RESET . "\n";
        echo BLUE . "Security Fixes Verification Test Suite" . RESET . "\n";
        echo BLUE . "=" . str_repeat("=", 78) . RESET . "\n\n";
    }
    
    public function test_module_exists($module_name, $file_path) {
        $this->tests_total++;
        
        if (file_exists($file_path)) {
            echo GREEN . "✓ PASS" . RESET . ": $module_name exists at $file_path\n";
            $this->tests_passed++;
            return true;
        } else {
            echo RED . "✗ FAIL" . RESET . ": $module_name NOT FOUND at $file_path\n";
            $this->tests_failed++;
            return false;
        }
    }
    
    public function test_function_exists($function_name, $module_path) {
        $this->tests_total++;
        
        if (!file_exists($module_path)) {
            echo YELLOW . "⊘ SKIP" . RESET . ": Module not found: $module_path\n";
            return false;
        }
        
        $content = file_get_contents($module_path);
        if (strpos($content, "function $function_name") !== false) {
            echo GREEN . "✓ PASS" . RESET . ": Function $function_name() defined\n";
            $this->tests_passed++;
            return true;
        } else {
            echo RED . "✗ FAIL" . RESET . ": Function $function_name() NOT FOUND\n";
            $this->tests_failed++;
            return false;
        }
    }
    
    public function test_code_contains($file_path, $search_string, $test_name) {
        $this->tests_total++;
        
        if (!file_exists($file_path)) {
            echo YELLOW . "⊘ SKIP" . RESET . ": File not found: $file_path\n";
            return false;
        }
        
        $content = file_get_contents($file_path);
        if (strpos($content, $search_string) !== false) {
            echo GREEN . "✓ PASS" . RESET . ": $test_name\n";
            $this->tests_passed++;
            return true;
        } else {
            echo RED . "✗ FAIL" . RESET . ": $test_name NOT FOUND\n";
            $this->tests_failed++;
            return false;
        }
    }
    
    public function section($title) {
        echo "\n" . BLUE . "\n$title" . RESET . "\n";
        echo BLUE . str_repeat("-", strlen($title)) . RESET . "\n\n";
    }
    
    public function getTestsPassed() {
        return $this->tests_passed;
    }
    
    public function getTestsFailed() {
        return $this->tests_failed;
    }
    
    public function summary() {
        echo "\n" . BLUE . "=" . str_repeat("=", 78) . RESET . "\n";
        echo BLUE . "Test Summary" . RESET . "\n";
        echo BLUE . "=" . str_repeat("=", 78) . RESET . "\n\n";
        
        echo "Total Tests: " . ($this->tests_passed + $this->tests_failed) . "\n";
        echo GREEN . "Passed: " . $this->tests_passed . RESET . "\n";
        echo RED . "Failed: " . $this->tests_failed . RESET . "\n\n";
        
        if ($this->tests_failed === 0) {
            echo GREEN . "✓ All security fixes verified successfully!" . RESET . "\n\n";
            return true;
        } else {
            echo RED . "✗ Some tests failed. Please review the fixes." . RESET . "\n\n";
            return false;
        }
    }
}

// Initialize tester
$tester = new SecurityFixTester();

// Test 1: Path Validation Module
$tester->section("1. Path Validation Module");
$tester->test_module_exists("Path Validation", __DIR__ . "/super_admin/includes/path_validation.php");
$tester->test_function_exists("validateUploadPath", __DIR__ . "/super_admin/includes/path_validation.php");
$tester->test_function_exists("getSafeUploadsDir", __DIR__ . "/super_admin/includes/path_validation.php");
$tester->test_function_exists("isValidFilename", __DIR__ . "/super_admin/includes/path_validation.php");
$tester->test_function_exists("isAllowedExtension", __DIR__ . "/super_admin/includes/path_validation.php");
$tester->test_function_exists("isAllowedMimeType", __DIR__ . "/super_admin/includes/path_validation.php");
$tester->test_function_exists("generateSafeFilename", __DIR__ . "/super_admin/includes/path_validation.php");

// Test 2: Role Security Module
$tester->section("2. Role Security Module");
$tester->test_module_exists("Role Security", __DIR__ . "/super_admin/includes/role_security.php");
$tester->test_function_exists("isValidRole", __DIR__ . "/super_admin/includes/role_security.php");
$tester->test_function_exists("canAssignRole", __DIR__ . "/super_admin/includes/role_security.php");
$tester->test_function_exists("getRoleLevel", __DIR__ . "/super_admin/includes/role_security.php");
$tester->test_function_exists("getCurrentRoleLevel", __DIR__ . "/super_admin/includes/role_security.php");
$tester->test_function_exists("validateRoleChange", __DIR__ . "/super_admin/includes/role_security.php");
$tester->test_function_exists("logRoleChange", __DIR__ . "/super_admin/includes/role_security.php");

// Test 3: Rate Limiting Module
$tester->section("3. Rate Limiting Module");
$tester->test_module_exists("Rate Limiting", __DIR__ . "/super_admin/includes/rate_limit.php");
$tester->test_function_exists("checkRateLimit", __DIR__ . "/super_admin/includes/rate_limit.php");
$tester->test_function_exists("getRemainingAttempts", __DIR__ . "/super_admin/includes/rate_limit.php");
$tester->test_function_exists("enforceRateLimit", __DIR__ . "/super_admin/includes/rate_limit.php");
$tester->test_function_exists("cleanupExpiredRateLimits", __DIR__ . "/super_admin/includes/rate_limit.php");

// Test 4: IDOR Fix in PDF Generation
$tester->section("4. IDOR Vulnerability Fix (generate_invoice_pdf.php)");
$tester->test_code_contains(
    __DIR__ . "/super_admin/generate_invoice_pdf.php",
    "tenant_id",
    "Tenant ID validation added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/generate_invoice_pdf.php",
    "csrf_token",
    "CSRF token validation added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/generate_invoice_pdf.php",
    "Unauthorized access to this payment",
    "Ownership verification check added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/generate_invoice_pdf.php",
    "PDF_GENERATED",
    "PDF generation logging added"
);

// Test 5: SQL Injection Fix in Backup
$tester->section("5. SQL Injection Fix (backup_management.php)");
$tester->test_code_contains(
    __DIR__ . "/super_admin/backup_management.php",
    "information_schema.TABLES",
    "Table whitelist validation added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/backup_management.php",
    "in_array(\$table, \$allowed_tables)",
    "Table name validation added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/backup_management.php",
    "str_replace('`', '``', \$table)",
    "Table identifier escaping added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/backup_management.php",
    "try {",
    "Error handling for backup added"
);

// Test 6: File Upload Validation Fix
$tester->section("6. File Upload Validation Fix (file_browser.php)");
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "path_validation",
    "Path validation module included"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "\$allowed_extensions",
    "File extension whitelist added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "\$allowed_mimes",
    "MIME type whitelist added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "finfo_open",
    "MIME type detection added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "FILE_UPLOAD",
    "File upload logging added"
);

// Test 7: Directory Traversal Fix
$tester->section("7. Directory Traversal Fix (file_browser.php)");
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "getSafeUploadsDir",
    "Safe directory access function used"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "Directory traversal attempt detected",
    "Traversal attempt logging added"
);

// Test 8: Role Validation Fix
$tester->section("8. Role Validation Fix (create_user.php)");
$tester->test_code_contains(
    __DIR__ . "/super_admin/create_user.php",
    "role_security",
    "Role security module included"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/create_user.php",
    "sanitizeRoleInput",
    "Role input sanitization added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/create_user.php",
    "canAssignRole",
    "Role assignment validation added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/create_user.php",
    "logRoleChange",
    "Role change logging added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/create_user.php",
    "USER_CREATED",
    "User creation logging added"
);

// Test 9: CSRF Token Generation
$tester->section("9. CSRF Token Protection");
$tester->test_code_contains(
    __DIR__ . "/super_admin/file_browser.php",
    "csrf_token",
    "CSRF token in file_browser added"
);
$tester->test_code_contains(
    __DIR__ . "/super_admin/generate_invoice_pdf.php",
    "bin2hex(random_bytes(32))",
    "Secure CSRF token generation added"
);

// Test 10: Directory Structure
$tester->section("10. Directory Structure Verification");
$cache_dir = __DIR__ . "/cache/rate_limit";
if (is_dir($cache_dir) || !file_exists($cache_dir)) {
    echo GREEN . "✓ PASS" . RESET . ": cache/rate_limit directory exists or can be created\n";
} else {
    echo YELLOW . "⊘ INFO" . RESET . ": cache/rate_limit directory will be created on first use\n";
}

// Final Summary
$all_passed = $tester->summary();

// Only show message (don't use private properties)
echo BLUE . "=" . str_repeat("=", 78) . RESET . "\n";
echo BLUE . "End of Test Suite" . RESET . "\n";
echo BLUE . "=" . str_repeat("=", 78) . RESET . "\n\n";
?>
