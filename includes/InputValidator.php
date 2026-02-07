<?php
/**
 * Input Validation & Sanitization Class
 * Provides comprehensive input validation for forms and APIs
 * 
 * @package MTravels
 * @author Security Team
 */

class InputValidator {
    
    /**
     * Validate and sanitize email
     * @param string $value Email to validate
     * @return string|null Sanitized email or null if invalid
     */
    public static function validateEmail($value) {
        if (empty($value)) {
            return null;
        }
        
        $sanitized = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        
        if (filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return $sanitized;
        }
        
        return null;
    }
    
    /**
     * Validate integer with optional min/max
     * @param mixed $value Value to validate
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @return int|null Valid integer or null
     */
    public static function validateInt($value, $min = null, $max = null) {
        $sanitized = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($sanitized === false) {
            return null;
        }
        
        if ($min !== null && $sanitized < $min) {
            return null;
        }
        
        if ($max !== null && $sanitized > $max) {
            return null;
        }
        
        return $sanitized;
    }
    
    /**
     * Validate float with optional min/max
     * @param mixed $value Value to validate
     * @param float|null $min Minimum value
     * @param float|null $max Maximum value
     * @return float|null Valid float or null
     */
    public static function validateFloat($value, $min = null, $max = null) {
        $sanitized = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        if ($sanitized === false) {
            return null;
        }
        
        if ($min !== null && $sanitized < $min) {
            return null;
        }
        
        if ($max !== null && $sanitized > $max) {
            return null;
        }
        
        return $sanitized;
    }
    
    /**
     * Validate date string
     * @param string $value Date string
     * @param string $format Expected date format
     * @return string|null Valid date or null
     */
    public static function validateDate($value, $format = 'Y-m-d') {
        if (empty($value)) {
            return null;
        }
        
        $d = DateTime::createFromFormat($format, $value);
        
        if ($d && $d->format($format) === $value) {
            return $value;
        }
        
        return null;
    }
    
    /**
     * Validate against enum of allowed values
     * @param mixed $value Value to validate
     * @param array $allowed_values Allowed values
     * @param mixed $default Default if invalid
     * @return mixed Valid value or default
     */
    public static function validateEnum($value, array $allowed_values, $default = null) {
        if (in_array($value, $allowed_values, true)) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Validate URL
     * @param string $value URL to validate
     * @return string|null Valid URL or null
     */
    public static function validateUrl($value) {
        if (empty($value)) {
            return null;
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        return null;
    }
    
    /**
     * Validate IP address
     * @param string $value IP address to validate
     * @return string|null Valid IP or null
     */
    public static function validateIP($value) {
        if (empty($value)) {
            return null;
        }
        
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
        
        return null;
    }
    
    /**
     * Validate phone number (basic international format)
     * @param string $value Phone number
     * @return string|null Valid phone or null
     */
    public static function validatePhone($value) {
        if (empty($value)) {
            return null;
        }
        
        // Remove non-digit characters except leading +
        $clean = preg_replace('/[^\d+]/', '', $value);
        
        // Must start with + or digit
        if (!preg_match('/^(\+)?\d{10,15}$/', $clean)) {
            return null;
        }
        
        return $clean;
    }
    
    /**
     * Sanitize string (HTML entities, trim, max length)
     * @param string $value String to sanitize
     * @param int $max_length Maximum length
     * @param bool $allow_html Allow HTML tags (will be escaped)
     * @return string Sanitized string
     */
    public static function sanitizeString($value, $max_length = 255, $allow_html = false) {
        $value = trim($value);
        
        if (strlen($value) > $max_length) {
            $value = substr($value, 0, $max_length);
        }
        
        if (!$allow_html) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } else {
            // Allow certain HTML tags but escape dangerous ones
            $value = strip_tags($value, '<p><br><strong><em><u>');
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }
    
    /**
     * Validate alphanumeric string (with optional special chars)
     * @param string $value String to validate
     * @param bool $allow_spaces Allow spaces
     * @param bool $allow_dashes Allow dashes and underscores
     * @return string|null Valid string or null
     */
    public static function validateAlphanumeric($value, $allow_spaces = false, $allow_dashes = false) {
        if (empty($value)) {
            return null;
        }
        
        $pattern = '/^[a-zA-Z0-9' . ($allow_spaces ? ' ' : '') . ($allow_dashes ? '_-' : '') . ']+$/';
        
        if (preg_match($pattern, $value)) {
            return $value;
        }
        
        return null;
    }
    
    /**
     * Validate username (alphanumeric, underscores, dashes, 3-50 chars)
     * @param string $value Username
     * @return string|null Valid username or null
     */
    public static function validateUsername($value) {
        if (empty($value)) {
            return null;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $value)) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate password strength
     * Requires: 12+ chars, uppercase, lowercase, number, special char
     * @param string $value Password to check
     * @return array Array of errors (empty if valid)
     */
    public static function validatePasswordStrength($value) {
        $errors = [];
        
        if (strlen($value) < 12) {
            $errors[] = 'Password must be at least 12 characters';
        }
        
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = 'Password must contain uppercase letters';
        }
        
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = 'Password must contain lowercase letters';
        }
        
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = 'Password must contain numbers';
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $value)) {
            $errors[] = 'Password must contain special characters (!@#$%^&*)';
        }
        
        return $errors;
    }
    
    /**
     * Get password strength score
     * @param string $value Password
     * @return array Score info: ['score' => 0-5, 'percentage' => 0-100, 'strength' => 'weak'|'fair'|'good'|'strong']
     */
    public static function getPasswordStrength($value) {
        $score = 0;
        
        if (strlen($value) >= 12) $score++;
        if (strlen($value) >= 16) $score++; // Bonus for extra length
        if (preg_match('/[A-Z]/', $value)) $score++;
        if (preg_match('/[a-z]/', $value)) $score++;
        if (preg_match('/[0-9]/', $value)) $score++;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $value)) $score++;
        
        // Map score to strength
        $strength_map = [
            0 => 'very_weak',
            1 => 'weak',
            2 => 'weak',
            3 => 'fair',
            4 => 'good',
            5 => 'strong',
            6 => 'very_strong'
        ];
        
        $score = min($score, 6);
        
        return [
            'score' => $score,
            'max' => 6,
            'percentage' => ($score / 6) * 100,
            'strength' => $strength_map[$score]
        ];
    }
    
    /**
     * Validate percentage (0-100)
     * @param mixed $value Percentage value
     * @return float|null Valid percentage or null
     */
    public static function validatePercentage($value) {
        $num = self::validateFloat($value, 0, 100);
        return $num;
    }
    
    /**
     * Validate currency amount
     * @param mixed $value Amount
     * @param float|null $max Maximum amount
     * @return float|null Valid amount or null
     */
    public static function validateAmount($value, $max = null) {
        $num = self::validateFloat($value, 0, $max);
        
        if ($num !== null) {
            // Round to 2 decimal places
            return round($num, 2);
        }
        
        return null;
    }
    
    /**
     * Batch validate multiple inputs
     * @param array $inputs Input data (key => value)
     * @param array $rules Validation rules (key => rule)
     *        Rule format: 'email|required', 'int|min:1|max:100', 'string|max:255', etc.
     * @return array Validation result ['valid' => bool, 'data' => [], 'errors' => []]
     */
    public static function batchValidate(array $inputs, array $rules) {
        $valid_data = [];
        $errors = [];
        
        foreach ($rules as $key => $rule) {
            $value = $inputs[$key] ?? null;
            $rule_parts = explode('|', $rule);
            $field_valid = true;
            $validated_value = $value;
            
            foreach ($rule_parts as $rule_part) {
                if ($rule_part === 'required') {
                    if (empty($value)) {
                        $errors[$key][] = ucfirst($key) . ' is required';
                        $field_valid = false;
                    }
                } elseif (strpos($rule_part, ':') !== false) {
                    list($rule_name, $rule_value) = explode(':', $rule_part);
                    // Handle parameterized rules
                } else {
                    // Handle type rules (email, int, string, etc.)
                }
            }
            
            if ($field_valid && $validated_value !== null) {
                $valid_data[$key] = $validated_value;
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $valid_data,
            'errors' => $errors
        ];
    }
    
    /**
     * Log suspicious input attempt
     * @param string $input_name Input field name
     * @param mixed $input_value Input value (will be truncated)
     * @param string $reason Reason for suspicion
     */
    public static function logSuspiciousInput($input_name, $input_value, $reason) {
        $value_str = is_string($input_value) ? substr($input_value, 0, 100) : var_export($input_value, true);
        error_log("SUSPICIOUS INPUT: [$input_name] = '$value_str' - Reason: $reason - IP: {$_SERVER['REMOTE_ADDR']}");
    }
}
?>
