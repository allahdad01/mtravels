<?php
require_once __DIR__ . '/../vendor/autoload.php';

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class TotpHelper {
    private $pdo;
    private $mysqli;
    private $tableColumns = [];
    
    public function __construct($pdo, $mysqli) {
        $this->pdo = $pdo;
        $this->mysqli = $mysqli ?? null;
    }

    private function getTableColumns($table) {
        if (!isset($this->tableColumns[$table])) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM {$table}");
            $this->tableColumns[$table] = array_flip(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field'));
        }

        return $this->tableColumns[$table];
    }

    private function hasTableColumn($table, $column) {
        $columns = $this->getTableColumns($table);

        return isset($columns[$column]);
    }

    private function appendScopeConditions($table, &$sql, array &$params, $tenant_id = null) {
        if ($this->hasTableColumn($table, 'tenant_id')) {
            if ($tenant_id === null) {
                $sql .= ' AND tenant_id IS NULL';
            } else {
                $sql .= ' AND tenant_id = :tenant_id';
                $params[':tenant_id'] = $tenant_id;
            }
        }
    }

    private function buildScopedInsertData($table, array $data, $tenant_id = null, $branch_id = null) {
        if ($this->hasTableColumn($table, 'tenant_id')) {
            $data['tenant_id'] = $tenant_id;
        }

        if ($this->hasTableColumn($table, 'branch_id')) {
            $data['branch_id'] = $branch_id;
        }

        return $data;
    }

    private function insertRow($table, array $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function ($column) {
            return ':' . $column;
        }, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }
    
    /**
     * Generate a new TOTP secret for a user
     */
    public function generateSecret($userId, $userType, $username, $tenant_id = null, $branch_id = null) {
        try {

            // Get agency name from settings
            $agencyName = 'Travel Agency'; // Default fallback
            if ($tenant_id) {
                try {
                    $stmt = $this->pdo->prepare("SELECT agency_name FROM settings WHERE tenant_id = ?");
                    $stmt->execute([$tenant_id]);
                    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($settings && !empty($settings['agency_name'])) {
                        $agencyName = $settings['agency_name'];
                    }
                } catch (Exception $e) {
                    error_log("TOTP Debug: Could not fetch agency name: " . $e->getMessage());
                }
            }

            // Create a new TOTP instance
            $totp = TOTP::create();

            $totp->setLabel($username);
            $totp->setIssuer($agencyName);
            
            $secret = $totp->getSecret();
            
            // Store the secret in the database
            try {
                $sql = "UPDATE totp_secrets SET secret = :secret, is_enabled = 0";
                $params = [
                    ':secret' => $secret,
                ];

                if ($this->hasTableColumn('totp_secrets', 'branch_id')) {
                    $sql .= ', branch_id = :branch_id';
                    $params[':branch_id'] = $branch_id;
                }

                $sql .= " WHERE user_id = :user_id AND user_type = :user_type";
                $params[':user_id'] = $userId;
                $params[':user_type'] = $userType;

                $this->appendScopeConditions('totp_secrets', $sql, $params, $tenant_id);

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);

                if ($stmt->rowCount() === 0) {
                    $this->insertRow(
                        'totp_secrets',
                        $this->buildScopedInsertData(
                            'totp_secrets',
                            [
                                'user_id' => $userId,
                                'user_type' => $userType,
                                'secret' => $secret,
                            ],
                            $tenant_id,
                            $branch_id
                        )
                    );
                }

                try {
                    $this->generateRecoveryCodes($userId, $userType, $tenant_id, $branch_id);
                } catch (Exception $e) {
                    // If recovery code generation fails, we still want to return the TOTP
                    error_log("TOTP Warning: Recovery code generation failed: " . $e->getMessage());
                    error_log("TOTP Debug: Continuing despite recovery codes failure");
                }
                
                return $totp;
            } catch (PDOException $e) {
                
                // Try alternative query without ON DUPLICATE KEY
                try {
                    // Delete existing record first
                    $deleteSql = "DELETE FROM totp_secrets WHERE user_id = :user_id AND user_type = :user_type";
                    $deleteParams = [
                        ':user_id' => $userId,
                        ':user_type' => $userType,
                    ];
                    $this->appendScopeConditions('totp_secrets', $deleteSql, $deleteParams, $tenant_id);
                    $delete = $this->pdo->prepare($deleteSql);
                    $delete->execute($deleteParams);

                    // Insert new record
                    $this->insertRow(
                        'totp_secrets',
                        $this->buildScopedInsertData(
                            'totp_secrets',
                            [
                                'user_id' => $userId,
                                'user_type' => $userType,
                                'secret' => $secret,
                            ],
                            $tenant_id,
                            $branch_id
                        )
                    );
                    
                    // Generate recovery codes
                    try {
                        $this->generateRecoveryCodes($userId, $userType, $tenant_id, $branch_id);
                    } catch (Exception $e) {
                        // Continue even if recovery code generation fails
                        error_log("TOTP Warning: Recovery code generation failed after fallback: " . $e->getMessage());
                    }
                    
                    return $totp;
                } catch (PDOException $e2) {
                    return false;
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verify a TOTP code
     */
    public function verifyCode($userId, $userType, $code, $tenant_id = null, $branch_id = null) {
        try {
            $sql = "SELECT secret FROM totp_secrets WHERE user_id = :user_id AND user_type = :user_type";
            $params = [
                ':user_id' => $userId,
                ':user_type' => $userType,
            ];
            $this->appendScopeConditions('totp_secrets', $sql, $params, $tenant_id);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

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
                $updateSql = "UPDATE totp_secrets SET last_used = NOW() WHERE user_id = :user_id AND user_type = :user_type";
                $updateParams = [
                    ':user_id' => $userId,
                    ':user_type' => $userType,
                ];
                $this->appendScopeConditions('totp_secrets', $updateSql, $updateParams, $tenant_id);

                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute($updateParams);

                return true;
            }

            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Enable TOTP for a user after successful verification
     */
    public function enableTotp($userId, $userType, $tenant_id = null, $branch_id = null) {
        try {
            // Update the TOTP secrets table
            $sql = "UPDATE totp_secrets SET is_enabled = 1 WHERE user_id = :user_id AND user_type = :user_type";
            $params = [
                ':user_id' => $userId,
                ':user_type' => $userType,
            ];
            $this->appendScopeConditions('totp_secrets', $sql, $params, $tenant_id);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

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
            return false;
        }
    }
    
    /**
     * Disable TOTP for a user
     */
    public function disableTotp($userId, $userType, $tenant_id = null, $branch_id = null) {
        try {
            // Update the TOTP secrets table
            $sql = "UPDATE totp_secrets SET is_enabled = 0 WHERE user_id = :user_id AND user_type = :user_type";
            $params = [
                ':user_id' => $userId,
                ':user_type' => $userType,
            ];
            $this->appendScopeConditions('totp_secrets', $sql, $params, $tenant_id);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

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
            return false;
        }
    }
    
    /**
     * Check if TOTP is enabled for a user
     */
    public function isTotpEnabled($userId, $userType, $tenant_id = null, $branch_id = null) {
        try {
            $sql = "SELECT is_enabled FROM totp_secrets WHERE user_id = :user_id AND user_type = :user_type";
            $params = [
                ':user_id' => $userId,
                ':user_type' => $userType,
            ];
            $this->appendScopeConditions('totp_secrets', $sql, $params, $tenant_id);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($result && $result['is_enabled'] == 1);
        } catch (PDOException $e) {
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
    private function generateRecoveryCodes($userId, $userType, $tenant_id = null, $branch_id = null) {
        try {

            // Delete existing unused recovery codes
            $deleteSql = "DELETE FROM totp_recovery_codes WHERE user_id = :user_id AND user_type = :user_type AND is_used = 0";
            $deleteParams = [
                ':user_id' => $userId,
                ':user_type' => $userType,
            ];
            $this->appendScopeConditions('totp_recovery_codes', $deleteSql, $deleteParams, $tenant_id);

            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->execute($deleteParams);

            // Generate 8 new recovery codes
            $inserted = 0;
            for ($i = 0; $i < 8; $i++) {
                try {
                    $code = $this->generateRandomCode();
                    $this->insertRow(
                        'totp_recovery_codes',
                        $this->buildScopedInsertData(
                            'totp_recovery_codes',
                            [
                                'user_id' => $userId,
                                'user_type' => $userType,
                                'recovery_code' => $code,
                            ],
                            $tenant_id,
                            $branch_id
                        )
                    );
                    $inserted++;
                } catch (Exception $e) {
                }
            }
            
            return ($inserted > 0);
        } catch (PDOException $e) {
            
            // Try with simpler query as fallback
            try {
                // Delete existing codes
                $deleteSql = "DELETE FROM totp_recovery_codes WHERE user_id = :user_id AND user_type = :user_type AND is_used = 0";
                $deleteParams = [
                    ':user_id' => $userId,
                    ':user_type' => $userType,
                ];
                $this->appendScopeConditions('totp_recovery_codes', $deleteSql, $deleteParams, $tenant_id);
                $delete = $this->pdo->prepare($deleteSql);
                $delete->execute($deleteParams);

                $inserted = 0;
                for ($i = 0; $i < 8; $i++) {
                    try {
                        $code = $this->generateRandomCode();
                        $this->insertRow(
                            'totp_recovery_codes',
                            $this->buildScopedInsertData(
                                'totp_recovery_codes',
                                [
                                    'user_id' => $userId,
                                    'user_type' => $userType,
                                    'recovery_code' => $code,
                                ],
                                $tenant_id,
                                $branch_id
                            )
                        );
                        $inserted++;
                    } catch (Exception $e) {
                        error_log("TOTP Warning: Failed to insert recovery code #$i in fallback: " . $e->getMessage());
                    }
                }
                
                return ($inserted > 0);
            } catch (Exception $e2) {
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
    public function getRecoveryCodes($userId, $userType, $tenant_id = null, $branch_id = null) {
        try {
            $sql = "SELECT recovery_code FROM totp_recovery_codes WHERE user_id = :user_id AND user_type = :user_type AND is_used = 0";
            $params = [
                ':user_id' => $userId,
                ':user_type' => $userType,
            ];
            $this->appendScopeConditions('totp_recovery_codes', $sql, $params, $tenant_id);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Verify a recovery code
     */
    public function verifyRecoveryCode($userId, $userType, $code, $tenant_id = null, $branch_id = null) {
        try {
            $sql = "SELECT id FROM totp_recovery_codes WHERE user_id = :user_id AND user_type = :user_type AND recovery_code = :code AND is_used = 0";
            $params = [
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':code' => $code,
            ];
            $this->appendScopeConditions('totp_recovery_codes', $sql, $params, $tenant_id);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

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
            return false;
        }
    }
} 
