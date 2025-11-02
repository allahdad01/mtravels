<?php
/**
 * Transaction Security Module
 * 
 * This file provides enhanced security features for financial transactions
 * in the admin panel.
 */

// Require the main security module
require_once(__DIR__ . '/../security.php');

/**
 * Class to handle financial transaction security
 */
class TransactionSecurity {
    // Transaction types
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_ADJUSTMENT = 'adjustment';
    
    // Amount threshold that requires additional verification
    const AMOUNT_THRESHOLD = 1000;
    
    // Maximum number of transactions per day per user
    const MAX_DAILY_TRANSACTIONS = 50;
    
    // Temporary storage for transaction verification
    private static $pendingTransactions = [];
    
    /**
     * Initialize transaction security
     */
    public static function init() {
        // Ensure user is authenticated
        enforce_auth(['admin', 'finance']);
        
        // Check for CSRF
        enforce_csrf();
        
        // Initialize session storage for transactions if not exists
        if (!isset($_SESSION['transaction_security'])) {
            $_SESSION['transaction_security'] = [
                'daily_count' => 0,
                'last_reset_date' => date('Y-m-d'),
                'last_transaction_id' => null,
                'pending_verification' => []
            ];
        }
        
        // Reset daily count if it's a new day
        if ($_SESSION['transaction_security']['last_reset_date'] !== date('Y-m-d')) {
            $_SESSION['transaction_security']['daily_count'] = 0;
            $_SESSION['transaction_security']['last_reset_date'] = date('Y-m-d');
        }
    }
    
    /**
     * Verify a transaction before processing
     * 
     * @param string $type Transaction type
     * @param float $amount Transaction amount
     * @param string $currency Currency code
     * @param array $details Additional transaction details
     * @return bool Whether the transaction is verified
     */
    public static function verifyTransaction($type, $amount, $currency, $details = []) {
        // Initialize if not already
        self::init();
        
        // Generate transaction ID
        $transactionId = self::generateTransactionId();
        
        // Check daily transaction limit
        if ($_SESSION['transaction_security']['daily_count'] >= self::MAX_DAILY_TRANSACTIONS) {
            security_log("Daily transaction limit exceeded for user", "warning", [
                'user_id' => $_SESSION['user_id'],
                'daily_count' => $_SESSION['transaction_security']['daily_count']
            ]);
            return false;
        }
        
        // Store transaction details for later verification
        $_SESSION['transaction_security']['last_transaction_id'] = $transactionId;
        $_SESSION['transaction_security']['pending_verification'][$transactionId] = [
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'details' => $details,
            'timestamp' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ];
        
        // Check if transaction needs additional verification
        if (self::needsAdditionalVerification($type, $amount, $currency)) {
            // Transaction needs additional verification
            return false;
        }
        
        // Increment daily transaction count
        $_SESSION['transaction_security']['daily_count']++;
        
        // Transaction verified
        return true;
    }
    
    /**
     * Log a completed transaction
     * 
     * @param string $transactionId Transaction ID
     * @param string $status Transaction status (success/failed)
     * @param string $notes Additional notes
     */
    public static function logTransaction($transactionId, $status, $notes = '') {
        // Check if transaction exists
        if (!isset($_SESSION['transaction_security']['pending_verification'][$transactionId])) {
            security_log("Attempt to log unknown transaction", "warning", [
                'transaction_id' => $transactionId,
                'status' => $status
            ]);
            return false;
        }
        
        // Get transaction details
        $transaction = $_SESSION['transaction_security']['pending_verification'][$transactionId];
        
        // Log transaction
        security_log("Financial transaction processed", "info", [
            'transaction_id' => $transactionId,
            'type' => $transaction['type'],
            'amount' => $transaction['amount'],
            'currency' => $transaction['currency'],
            'status' => $status,
            'notes' => $notes
        ]);
        
        // Remove from pending verification
        unset($_SESSION['transaction_security']['pending_verification'][$transactionId]);
        
        return true;
    }
    
    /**
     * Check if a transaction needs additional verification
     * 
     * @param string $type Transaction type
     * @param float $amount Transaction amount
     * @param string $currency Currency code
     * @return bool Whether the transaction needs additional verification
     */
    private static function needsAdditionalVerification($type, $amount, $currency) {
        // Convert amount to USD for consistent comparison if not USD
        $amountUSD = $amount;
        if ($currency !== 'USD') {
            // Get exchange rate (simple example)
            $exchangeRate = ($currency === 'AFS') ? 0.012 : 1; // Example rate for AFS to USD
            $amountUSD = $amount * $exchangeRate;
        }
        
        // Check amount threshold
        if ($amountUSD >= self::AMOUNT_THRESHOLD) {
            return true;
        }
        
        // Check transaction type - refunds always need verification
        if ($type === self::TYPE_REFUND) {
            return true;
        }
        
        // Check suspicious patterns (example: multiple transactions in short time)
        $recentTransactions = 0;
        $oneHourAgo = time() - 3600;
        
        foreach ($_SESSION['transaction_security']['pending_verification'] as $transaction) {
            if ($transaction['timestamp'] > $oneHourAgo) {
                $recentTransactions++;
            }
        }
        
        if ($recentTransactions > 10) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate a unique transaction ID
     * 
     * @return string Transaction ID
     */
    private static function generateTransactionId() {
        return uniqid('TX_') . '_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Validate a transaction amount
     * 
     * @param float $amount Transaction amount
     * @param string $currency Currency code
     * @return bool Whether the amount is valid
     */
    public static function validateAmount($amount, $currency) {
        // Amount must be positive
        if ($amount <= 0) {
            return false;
        }
        
        // Currency must be valid
        if (!in_array(strtoupper($currency), ['USD', 'AFS'])) {
            return false;
        }
        
        // Amount must be reasonable (example: less than 1,000,000)
        if ($amount > 1000000) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get maximum transaction amount for a user based on their role
     * 
     * @param string $role User role
     * @param string $currency Currency code
     * @return float Maximum transaction amount
     */
    public static function getMaxTransactionAmount($role, $currency) {
        // Default values
        $maxAmounts = [
            'admin' => [
                'USD' => 50000,
                'AFS' => 4000000
            ],
            'finance' => [
                'USD' => 20000,
                'AFS' => 1600000
            ],
            'default' => [
                'USD' => 5000,
                'AFS' => 400000
            ]
        ];
        
        if (isset($maxAmounts[$role]) && isset($maxAmounts[$role][$currency])) {
            return $maxAmounts[$role][$currency];
        } elseif (isset($maxAmounts['default'][$currency])) {
            return $maxAmounts['default'][$currency];
        } else {
            return 1000; // Fallback amount
        }
    }
}
?> 