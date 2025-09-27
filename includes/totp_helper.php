<?php
require_once __DIR__ . '/../vendor/autoload.php';

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class TotpHelper {
    private $pdo;
    private $mysqli;
    
    public function __construct($pdo, $mysqli) {
        $this->pdo = $pdo;
        $this->mysqli = $mysqli;
    }
    
    /**
     * Generate a new TOTP secret for a user
     */
    public function generateSecret($userId, $userType, $username, $tenant_id = null) {
        try {
            error_log("TOTP Debug: Starting generateSecret for user $userId ($userType)");
            
            // Create a new TOTP instance
            $totp = TOTP::create();
            error_log("TOTP Debug: TOTP instance created");
            
            $totp->setLabel($username);
            $totp->setIssuer('Travel Agency');
            error_log("TOTP Debug: Label and issuer set");
            
            $secret = $totp->getSecret();
            error_log("TOTP Debug: Secret generated: " . substr($secret, 0, 10) . "...");
            
            // Store the secret in the database
            try {
                error_log("TOTP Debug: Preparing SQL statement");
                $sql = "INSERT INTO totp_secrets (user_id, user_type, secret, tenant_id) 
                       VALUES (:user_id, :user_type, :secret, :tenant_id)
                       ON DUPLICATE KEY UPDATE secret = :secret, is_enabled = 0";
                
                $stmt = $this->pdo->prepare($sql);
                error_log("TOTP Debug: SQL statement prepared");
                
                $params = [
                    ':user_id' => $userId,
                    ':user_type' => $userType,
                    ':secret' => $secret,
                    ':tenant_id' => $tenant_id
                ];
                error_log("TOTP Debug: Parameters ready: user_id=$userId, user_type=$userType, tenant_id=$tenant_id");
                
                $result = $stmt->execute($params);
                error_log("TOTP Debug: SQL executed, result: " . ($result ? "true" : "false"));
                
                // Generate recovery codes
                error_log("TOTP Debug: Generating recovery codes");
                try {
                    $this->generateRecoveryCodes($userId, $userType, $tenant_id);
                    error_log("TOTP Debug: Recovery codes generated");
                } catch (Exception $e) {
                    // If recovery code generation fails, we still want to return the TOTP
                    error_log("TOTP Warning: Recovery code generation failed: " . $e->getMessage());
                    error_log("TOTP Debug: Continuing despite recovery codes failure");
                }
                
                return $totp;
            } catch (PDOException $e) {
                error_log("TOTP Secret Generation Error: " . $e->getMessage());
                error_log("TOTP Debug: SQL: " . $sql);
                error_log("TOTP Debug: Parameters: " . print_r($params, true));
                
                // Try alternative query without ON DUPLICATE KEY
                try {
                    error_log("TOTP Debug: Trying simpler query");
                    // Delete existing record first
                    $delete = $this->pdo->prepare("DELETE FROM totp_secrets WHERE user_id = ? AND user_type = ? AND tenant_id = ?");
                    $delete->execute([$userId, $userType, $tenant_id]);
                    error_log("TOTP Debug: Deleted existing records");
                    
                    // Insert new record
                    $stmt = $this->pdo->prepare("INSERT INTO totp_secrets (user_id, user_type, secret, tenant_id) VALUES (?, ?, ?, ?)");
                    $result = $stmt->execute([$userId, $userType, $secret, $tenant_id]);
                    error_log("TOTP Debug: Simple insert executed, result: " . ($result ? "true" : "false"));
                    
                    // Generate recovery codes
                    try {
                        $this->generateRecoveryCodes($userId, $userType, $tenant_id);
                        error_log("TOTP Debug: Recovery codes generated after fallback");
                    } catch (Exception $e) {
                        // Continue even if recovery code generation fails
                        error_log("TOTP Warning: Recovery code generation failed after fallback: " . $e->getMessage());
                    }
                    
                    return $totp;
                } catch (PDOException $e2) {
                    error_log("TOTP Debug: Even simpler query failed: " . $e2->getMessage());
                    return false;
                }
            }
        } catch (Exception $e) {
            error_log("TOTP Overall Error: " . $e->getMessage());
            error_log("TOTP Debug: Exception type: " . get_class($e));
            error_log("TOTP Debug: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Verify a TOTP code
     */
    public function verifyCode($userId, $userType, $code, $tenant_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT secret FROM totp_secrets 
                WHERE user_id = :user_id AND user_type = :user_type AND tenant_id = :tenant_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':tenant_id' => $tenant_id
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            $secret = $result['secret'];
            
            // Create a TOTP instance with the stored secret
            $totp = TOTP::create($secret);
            
            // Verify the code (allowing a 30-second window on either side)
            if ($totp->verify($code, null, 1)) {
                // Update last used timestamp
                $updateStmt = $this->pdo->prepare("
                    UPDATE totp_secrets 
                    SET last_used = NOW() 
                    WHERE user_id = :user_id AND user_type = :user_type AND tenant_id = :tenant_id
                ");
                
                $updateStmt->execute([
                    ':user_id' => $userId,
                    ':user_type' => $userType,
                    ':tenant_id' => $tenant_id
                ]);
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("TOTP Verification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable TOTP for a user after successful verification
     */
    public function enableTotp($userId, $userType, $tenant_id = null) {
        try {
            // Update the TOTP secrets table
            $stmt = $this->pdo->prepare("
                UPDATE totp_secrets 
                SET is_enabled = 1 
                WHERE user_id = :user_id AND user_type = :user_type AND tenant_id = :tenant_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':tenant_id' => $tenant_id
            ]);
            
            // Update the user table
            $table = ($userType == 'staff') ? 'users' : 'clients';
            $updateStmt = $this->pdo->prepare("
                UPDATE {$table} 
                SET totp_enabled = 1 
                WHERE id = :user_id
            ");
            
            $updateStmt->execute([
                ':user_id' => $userId
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("TOTP Enable Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable TOTP for a user
     */
    public function disableTotp($userId, $userType, $tenant_id = null) {
        try {
            // Update the TOTP secrets table
            $stmt = $this->pdo->prepare("
                UPDATE totp_secrets 
                SET is_enabled = 0 
                WHERE user_id = :user_id AND user_type = :user_type AND tenant_id = :tenant_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':tenant_id' => $tenant_id
            ]);
            
            // Update the user table
            $table = ($userType == 'staff') ? 'users' : 'clients';
            $updateStmt = $this->pdo->prepare("
                UPDATE {$table} 
                SET totp_enabled = 0 
                WHERE id = :user_id
            ");
            
            $updateStmt->execute([
                ':user_id' => $userId
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("TOTP Disable Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if TOTP is enabled for a user
     */
    public function isTotpEnabled($userId, $userType, $tenant_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT is_enabled FROM totp_secrets 
                WHERE user_id = :user_id AND user_type = :user_type AND tenant_id = :tenant_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':tenant_id' => $tenant_id
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result && $result['is_enabled'] == 1);
        } catch (PDOException $e) {
            error_log("TOTP Status Check Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a QR code for TOTP setup
     */
    public function generateQrCode($totpUrl) {
        $writer = new PngWriter();
        $qrCode = QrCode::create($totpUrl)
            ->setSize(300)
            ->setMargin(10);
        
        $result = $writer->write($qrCode);
        
        return $result->getDataUri();
    }
    
    /**
     * Generate recovery codes for a user
     */
    private function generateRecoveryCodes($userId, $userType, $tenant_id = null) {
        try {
            error_log("TOTP Debug: Starting recovery code generation for user $userId ($userType)");
            
            // Delete existing unused recovery codes
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM totp_recovery_codes 
                WHERE user_id = :user_id AND user_type = :user_type AND is_used = 0 AND tenant_id = :tenant_id
            ");
            
            $deleteStmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':tenant_id' => $tenant_id
            ]);
            error_log("TOTP Debug: Deleted existing recovery codes");
            
            // Generate 8 new recovery codes
            $insertStmt = $this->pdo->prepare("
                INSERT INTO totp_recovery_codes (user_id, user_type, recovery_code, tenant_id) 
                VALUES (:user_id, :user_type, :code, :tenant_id)
            ");
            
            $inserted = 0;
            for ($i = 0; $i < 8; $i++) {
                try {
                    $code = $this->generateRandomCode();
                    $insertStmt->execute([
                        ':user_id' => $userId,
                        ':user_type' => $userType,
                        ':code' => $code,
                        ':tenant_id' => $tenant_id
                    ]);
                    $inserted++;
                } catch (Exception $e) {
                    error_log("TOTP Warning: Failed to insert recovery code #$i: " . $e->getMessage());
                }
            }
            
            error_log("TOTP Debug: Generated $inserted recovery codes");
            return ($inserted > 0);
        } catch (PDOException $e) {
            error_log("Recovery Code Generation Error: " . $e->getMessage());
            
            // Try with simpler query as fallback
            try {
                error_log("TOTP Debug: Trying simpler recovery code insert");
                // Delete existing codes
                $delete = $this->pdo->prepare("DELETE FROM totp_recovery_codes WHERE user_id = ? AND user_type = ? AND is_used = 0 AND tenant_id = ?");
                $delete->execute([$userId, $userType, $tenant_id]);
                
                // Use simpler insert
                $insertStmt = $this->pdo->prepare("INSERT INTO totp_recovery_codes (user_id, user_type, recovery_code, tenant_id) VALUES (?, ?, ?, ?)");
                
                $inserted = 0;
                for ($i = 0; $i < 8; $i++) {
                    try {
                        $code = $this->generateRandomCode();
                        $insertStmt->execute([$userId, $userType, $code, $tenant_id]);
                        $inserted++;
                    } catch (Exception $e) {
                        error_log("TOTP Warning: Failed to insert recovery code #$i in fallback: " . $e->getMessage());
                    }
                }
                
                error_log("TOTP Debug: Generated $inserted recovery codes with fallback method");
                return ($inserted > 0);
            } catch (Exception $e2) {
                error_log("TOTP Debug: Even simpler recovery code generation failed: " . $e2->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Generate a random recovery code
     */
    private function generateRandomCode() {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < 16; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
            
            // Add a hyphen after every 4 characters except the last group
            if ($i % 4 == 3 && $i < 15) {
                $code .= '-';
            }
        }
        
        return $code;
    }
    
    /**
     * Get recovery codes for a user
     */
    public function getRecoveryCodes($userId, $userType, $tenant_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT recovery_code FROM totp_recovery_codes 
                WHERE user_id = :user_id AND user_type = :user_type AND is_used = 0 AND tenant_id = :tenant_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':tenant_id' => $tenant_id
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Recovery Code Retrieval Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verify a recovery code
     */
    public function verifyRecoveryCode($userId, $userType, $code, $tenant_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM totp_recovery_codes 
                WHERE user_id = :user_id 
                AND user_type = :user_type 
                AND recovery_code = :code 
                AND is_used = 0
                AND tenant_id = :tenant_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':code' => $code,
                ':tenant_id' => $tenant_id
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Mark code as used
                $updateStmt = $this->pdo->prepare("
                    UPDATE totp_recovery_codes 
                    SET is_used = 1, used_at = NOW() 
                    WHERE id = :id
                ");
                
                $updateStmt->execute([
                    ':id' => $result['id']
                ]);
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Recovery Code Verification Error: " . $e->getMessage());
            return false;
        }
    }
} 