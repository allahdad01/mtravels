<?php
/**
 * Security Fix for backup_management.php
 * 
 * Replace the mysqldump command execution section (lines 86-122)
 * to prevent command injection vulnerabilities
 */

// ===== SECURE BACKUP EXECUTION FUNCTION =====

/**
 * Safely execute mysqldump using proc_open
 * 
 * @param string $mysqldump Path to mysqldump
 * @param string $host Database host
 * @param string $user Database user
 * @param string $pass Database password
 * @param string $database Database name
 * @param string $outputFile Path to write backup
 * @return bool True if successful
 */
function executeMysqldumpSafely($mysqldump, $host, $user, $pass, $database, $outputFile) {
    // Build command array (safer than string)
    $cmd = [
        escapeshellarg($mysqldump),
        '--no-tablespaces',
        '--single-transaction',
        '--quick',
        '--lock-tables=false',
        '--host=' . escapeshellarg($host),
        '--user=' . escapeshellarg($user),
    ];
    
    // Add password if provided
    if (!empty($pass)) {
        $cmd[] = '--password=' . escapeshellarg($pass);
    }
    
    // Add database name
    $cmd[] = escapeshellarg($database);
    
    // Prepare descriptor specification
    $descriptorspec = [
        0 => ['pipe', 'r'],              // stdin
        1 => ['file', $outputFile, 'w'], // stdout to file (SAFE)
        2 => ['pipe', 'w']               // stderr
    ];
    
    // Execute using proc_open (safer than exec)
    $process = proc_open(implode(' ', $cmd), $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        throw new Exception("Failed to execute mysqldump");
    }
    
    // Close stdin
    fclose($pipes[0]);
    
    // Read stderr
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    
    // Get return code
    $return_value = proc_close($process);
    
    if ($return_value !== 0) {
        // Log stderr but don't expose to user
        error_log("Mysqldump error: " . $stderr);
        throw new Exception("Backup command failed");
    }
    
    // Verify backup file was created and has content
    if (!file_exists($outputFile) || filesize($outputFile) === 0) {
        throw new Exception("Backup file was not created or is empty");
    }
    
    return true;
}

// ===== REPLACE IN backup_management.php (around line 86-122) =====

// OLD CODE (REMOVE):
/*
foreach ($mysqldump_paths as $mysqldump) {
    if (is_executable($mysqldump)) {
        $mysqldump_available = true;
        
        // VULNERABLE: Shell redirection is not escaped
        $cmd = sprintf(
            '%s --no-tablespaces -h%s -u%s %s %s > %s', 
            escapeshellcmd($mysqldump), 
            escapeshellarg($host), 
            escapeshellarg($user), 
            $pass ? '-p' . escapeshellarg($pass) : '', 
            escapeshellarg($name), 
            escapeshellarg($abs_path)  // This doesn't protect the > redirection
        );
        
        $ret = null; 
        $output = [];
        
        if (function_exists('exec')) {
            exec($cmd, $output, $ret);
        } elseif (function_exists('system')) {
            $ret = system($cmd, $output);
        }
        
        $dumpOk = ($ret === 0) && file_exists($abs_path) && filesize($abs_path) > 0;
        
        if ($dumpOk) {
            break;
        }
    }
}
*/

// NEW CODE (REPLACE WITH):
foreach ($mysqldump_paths as $mysqldump) {
    if (is_executable($mysqldump)) {
        $mysqldump_available = true;
        
        try {
            // Use safe proc_open method
            if (executeMysqldumpSafely($mysqldump, $host, $user, $pass, $name, $abs_path)) {
                $dumpOk = true;
                break;
            }
        } catch (Exception $e) {
            error_log("Mysqldump error: " . $e->getMessage());
            // Continue to next path
            continue;
        }
    }
}

// ===== ADDITIONAL RECOMMENDATIONS =====

/*
1. Consider disabling exec/system functions in php.ini:
   php_admin_flag[disable_functions] = exec,system,shell_exec,passthru,proc_open
   
   If using proc_open, whitelist it:
   php_admin_flag[disable_functions] = exec,system,shell_exec,passthru

2. Run mysqldump as separate service user (not www-data):
   mysqldump should have its own restricted database account

3. Store backups outside web root:
   /var/backups/mtravels/ (not ../backups/)

4. Encrypt backup files:
   Use GPG or openssl to encrypt backups containing sensitive data

5. Monitor backup execution:
   Log all backup operations with timestamp, user, size, checksum
   
6. Implement backup integrity verification:
   After backup, check table count matches expected count
*/

?>
