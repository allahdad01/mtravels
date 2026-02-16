<?php
/**
 * Password Strength Validator
 * Enforces strong password requirements
 */

class PasswordValidator {
    private static $minLength = 12;
    private static $requireUppercase = true;
    private static $requireLowercase = true;
    private static $requireNumbers = true;
    private static $requireSpecialChars = true;
    
    /**
     * Validate password strength
     * @param string $password The password to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate($password) {
        $errors = [];
        
        // Check minimum length
        if (strlen($password) < self::$minLength) {
            $errors[] = "Password must be at least " . self::$minLength . " characters";
        }
        
        // Check for uppercase letters
        if (self::$requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Check for lowercase letters
        if (self::$requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Check for numbers
        if (self::$requireNumbers && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // Check for special characters
        if (self::$requireSpecialChars && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\,.<>\/?]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*...)";
        }
        
        // Check for common weak passwords
        if (self::isCommonPassword($password)) {
            $errors[] = "Password is too common. Please choose a stronger password";
        }
        
        // Check for sequential characters
        if (self::hasSequentialChars($password)) {
            $errors[] = "Password contains sequential characters (abc, 123, etc.)";
        }
        
        // Check for repeated characters
        if (self::hasRepeatedChars($password)) {
            $errors[] = "Password contains too many repeated characters";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if password is in common passwords list
     */
    private static function isCommonPassword($password) {
        $commonPasswords = [
            'password', '12345678', 'qwerty', 'abc123', 'letmein',
            'welcome', 'monkey', 'dragon', '1234567890', 'password123',
            'admin', 'root', 'test', 'changeme', 'iloveyou',
            'princess', 'master', 'sunshine', 'shadow', 'ashley'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }
    
    /**
     * Check for sequential characters (abc, 123, xyz)
     */
    private static function hasSequentialChars($password) {
        $sequences = [
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            'qwertyuiopasdfghjklzxcvbnm'
        ];
        
        $lowerPassword = strtolower($password);
        
        foreach ($sequences as $sequence) {
            for ($i = 0; $i < strlen($sequence) - 2; $i++) {
                $pattern = substr($sequence, $i, 3);
                if (strpos($lowerPassword, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check for repeated characters (aaa, 111)
     */
    private static function hasRepeatedChars($password) {
        // Check for 3+ consecutive identical characters
        if (preg_match('/(.)\1{2,}/', $password)) {
            return true;
        }
        return false;
    }
    
    /**
     * Get password strength score (0-100)
     */
    public static function getStrengthScore($password) {
        $score = 0;
        
        // Length bonus
        if (strlen($password) >= 8) $score += 10;
        if (strlen($password) >= 12) $score += 10;
        if (strlen($password) >= 16) $score += 10;
        
        // Character diversity
        if (preg_match('/[a-z]/', $password)) $score += 15;
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 20;
        
        // Penalty for common patterns
        if (self::isCommonPassword($password)) $score -= 50;
        if (self::hasSequentialChars($password)) $score -= 20;
        if (self::hasRepeatedChars($password)) $score -= 10;
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get strength level (weak, fair, good, strong)
     */
    public static function getStrengthLevel($password) {
        $score = self::getStrengthScore($password);
        
        if ($score >= 80) return 'strong';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'weak';
    }
}
?>
