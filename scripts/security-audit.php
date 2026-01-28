<?php
/**
 * MTravels Security Audit Script
 * Automated security checks for the application
 */

class SecurityAuditor
{
    private $results = [];
    private $warnings = 0;
    private $errors = 0;

    public function runAudit()
    {
        echo "🔍 MTravels Security Audit\n";
        echo "========================\n\n";

        $this->checkFilePermissions();
        $this->checkEnvironmentFiles();
        $this->checkDatabaseSecurity();
        $this->checkSessionSecurity();
        $this->checkHeadersSecurity();
        $this->checkDependencyVulnerabilities();

        $this->printSummary();

        return $this->errors === 0;
    }

    private function checkFilePermissions()
    {
        echo "📁 Checking File Permissions...\n";

        $sensitiveFiles = [
            '.env' => 0600,
            'composer.lock' => 0644,
            'config.php' => 0644,
            '.htaccess' => 0644
        ];

        foreach ($sensitiveFiles as $file => $expectedPerms) {
            if (file_exists($file)) {
                $actualPerms = fileperms($file) & 0777;
                if ($actualPerms > $expectedPerms) {
                    $this->addWarning("File '$file' has overly permissive permissions: " . decoct($actualPerms));
                } else {
                    $this->addResult("✓ File '$file' permissions are secure");
                }
            }
        }
        echo "\n";
    }

    private function checkEnvironmentFiles()
    {
        echo "🔐 Checking Environment Configuration...\n";

        if (file_exists('.env')) {
            $content = file_get_contents('.env');
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;

                // Check for default/weak passwords
                if (preg_match('/^(DB_PASSWORD|API_KEY|SECRET)=(.+)$/i', $line, $matches)) {
                    $value = $matches[2];
                    if (in_array(strtolower($value), ['password', '123456', 'admin', 'root', ''])) {
                        $this->addError("Weak or default value found for {$matches[1]}");
                    }
                }
            }
            $this->addResult("✓ Environment file exists and is configured");
        } else {
            $this->addError("❌ .env file not found - environment variables not configured");
        }
        echo "\n";
    }

    private function checkDatabaseSecurity()
    {
        echo "🗄️  Checking Database Security...\n";

        if (file_exists('config.php')) {
            $content = file_get_contents('config.php');

            // Check for prepared statements usage
            if (strpos($content, 'prepare(') !== false) {
                $this->addResult("✓ Prepared statements are being used");
            }

            // Check for direct SQL queries (warning)
            if (preg_match('/\$conection_db->query\(/', $content)) {
                $this->addWarning("Direct SQL queries found - consider using prepared statements");
            }
        }
        echo "\n";
    }

    private function checkSessionSecurity()
    {
        echo "🔒 Checking Session Security...\n";

        $sessionFiles = glob('admin/*.php') + glob('tenant_super_admin/*.php') + glob('super_admin/*.php');

        foreach ($sessionFiles as $file) {
            $content = file_get_contents($file);

            if (strpos($content, 'session_start()') !== false) {
                if (strpos($content, 'session_regenerate_id') !== false) {
                    $this->addResult("✓ Session regeneration found in $file");
                }

                if (strpos($content, 'httponly') !== false) {
                    $this->addResult("✓ HttpOnly sessions found in $file");
                }
            }
        }
        echo "\n";
    }

    private function checkHeadersSecurity()
    {
        echo "🛡️  Checking Security Headers...\n";

        if (file_exists('.htaccess')) {
            $content = file_get_contents('.htaccess');

            $requiredHeaders = [
                'Strict-Transport-Security' => 'HSTS',
                'X-Frame-Options' => 'Clickjacking protection',
                'X-Content-Type-Options' => 'MIME sniffing protection',
                'X-XSS-Protection' => 'XSS protection'
            ];

            foreach ($requiredHeaders as $header => $description) {
                if (strpos($content, $header) !== false) {
                    $this->addResult("✓ $description header configured");
                } else {
                    $this->addWarning("Missing $description header");
                }
            }
        }
        echo "\n";
    }

    private function checkDependencyVulnerabilities()
    {
        echo "📦 Checking Dependencies...\n";

        if (file_exists('composer.lock')) {
            $this->addResult("✓ Composer lock file exists");

            // Check if composer audit can be run (modern approach)
            if (file_exists('vendor/composer/installed.json')) {
                $this->addResult("✓ Composer dependencies are installed");
                $this->addResult("  Run: composer audit");
            } else {
                $this->addWarning("Composer dependencies not installed. Run: composer install");
            }
        } else {
            $this->addWarning("No composer.lock found - dependencies not locked");
        }
        echo "\n";
    }

    private function addResult($message)
    {
        $this->results[] = $message;
    }

    private function addWarning($message)
    {
        $this->results[] = "⚠️  $message";
        $this->warnings++;
    }

    private function addError($message)
    {
        $this->results[] = "❌ $message";
        $this->errors++;
    }

    private function printSummary()
    {
        echo "📊 Audit Summary\n";
        echo "================\n";
        echo "Total Checks: " . count($this->results) . "\n";
        echo "Warnings: $this->warnings\n";
        echo "Errors: $this->errors\n\n";

        if ($this->errors === 0 && $this->warnings === 0) {
            echo "🎉 All security checks passed!\n";
        } elseif ($this->errors === 0) {
            echo "✅ No critical issues found, but $this->warnings warnings to address.\n";
        } else {
            echo "⚠️  $this->errors critical issues found that need immediate attention.\n";
        }

        echo "\n📋 Detailed Results:\n";
        echo "===================\n";
        foreach ($this->results as $result) {
            echo "$result\n";
        }
    }
}

// Run the audit
$auditor = new SecurityAuditor();
$success = $auditor->runAudit();

exit($success ? 0 : 1);
?>