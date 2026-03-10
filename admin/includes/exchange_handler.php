<?php
// Function to process currency exchange
// Note: Assumes transaction is already active in the caller
function processCurrencyExchange($pdo, $data) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        // Debug log
        error_log("Starting currency exchange process with data: " . json_encode($data));
        
        // Verify customer has sufficient balance
        $stmt = $pdo->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['from_currency'], PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wallet || $wallet['balance'] < $data['from_amount']) {
            throw new Exception('Insufficient balance for exchange');
        }
        
        // Calculate profit/loss using the provided rate
        $provided_rate = $data['rate'];
        error_log("Using provided exchange rate: " . $provided_rate);
        
        // Try to get market rate for profit calculation, but don't fail if not found
        try {
            $market_rate = getCurrentMarketRate($pdo, $data['from_currency'], $data['to_currency']);
            error_log("Market rate found: " . $market_rate);
            $market_amount = $data['from_amount'] * $market_rate;
            $profit_amount = $data['to_amount'] - $market_amount;
        } catch (Exception $e) {
            error_log("Market rate not found, using provided rate for profit calculation");
            // If market rate is not available, assume provided rate is market rate (no profit)
            $market_rate = $provided_rate;
            $market_amount = $data['from_amount'] * $provided_rate;
            $profit_amount = 0;
        }
        
        // Debug log
        error_log("Market amount: " . $market_amount . ", Profit amount: " . $profit_amount);
        
        // Insert exchange transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, tenant_id, branch_id) VALUES (?, ?, ?, 'exchange', ?, ?, ?)");
        $stmt->bindParam(1, $data['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['from_amount'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['from_currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $data['notes'], PDO::PARAM_STR);
        $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_id = $pdo->lastInsertId();
        
        // Record exchange details
        $stmt = $pdo->prepare("INSERT INTO exchange_transactions (transaction_id, from_amount, from_currency, to_amount, to_currency, rate, profit_amount, profit_currency, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $data['from_amount'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['from_currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $data['to_amount'], PDO::PARAM_STR);
        $stmt->bindParam(5, $data['to_currency'], PDO::PARAM_STR);
        $stmt->bindParam(6, $provided_rate, PDO::PARAM_STR);
        $stmt->bindParam(7, $profit_amount, PDO::PARAM_STR);
        $stmt->bindParam(8, $data['to_currency'], PDO::PARAM_STR);
        $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update customer wallets
        // Deduct from source currency wallet
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['from_amount'], PDO::PARAM_STR);
        $stmt->bindParam(2, $data['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $data['from_currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Add to destination currency wallet
        $stmt = $pdo->prepare("
            INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id, branch_id) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE balance = balance + ?
        ");
        $stmt->bindParam(1, $data['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['to_currency'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['to_amount'], PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $data['to_amount'], PDO::PARAM_STR);
        $stmt->execute();
        
        // Store the exchange rate for future reference
        try {
            updateExchangeRate($pdo, $data['from_currency'], $data['to_currency'], $provided_rate, $tenant_id, $branch_id);
        } catch (Exception $e) {
            error_log("Warning: Could not update exchange rate history: " . $e->getMessage());
            // Don't fail the transaction if we can't update the rate history
        }
        
        return [
            'success' => true,
            'message' => 'Currency exchange completed successfully',
            'transaction_id' => $transaction_id,
            'profit_amount' => $profit_amount,
            'exchange_rate' => $provided_rate
        ];
        
    } catch (Exception $e) {
        error_log("Error in processCurrencyExchange: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error processing currency exchange: ' . $e->getMessage()
        ];
    }
}

// Function to get current market rate
function getCurrentMarketRate($pdo, $from_currency, $to_currency) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    
    error_log("Getting market rate for {$from_currency} to {$to_currency}");
    
    // Try direct rate first
    $stmt = $pdo->prepare("
        SELECT rate 
        FROM exchange_rates 
        WHERE from_currency = ? 
        AND to_currency = ? 
        AND tenant_id = ? AND branch_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->bindParam(1, $from_currency, PDO::PARAM_STR);
    $stmt->bindParam(2, $to_currency, PDO::PARAM_STR);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $rate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rate) {
        error_log("Found direct rate: " . $rate['rate']);
        return $rate['rate'];
    }
    
    error_log("No direct rate found, trying inverse rate");
    
    // Try inverse rate
    $stmt = $pdo->prepare("
        SELECT rate 
        FROM exchange_rates 
        WHERE from_currency = ? 
        AND to_currency = ? 
        AND tenant_id = ? AND branch_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->bindParam(1, $to_currency, PDO::PARAM_STR);
    $stmt->bindParam(2, $from_currency, PDO::PARAM_STR);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $rate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rate) {
        $inverse_rate = 1 / $rate['rate'];
        error_log("Found inverse rate: " . $inverse_rate);
        return $inverse_rate;
    }
    
    // If no rate found, try to calculate through USD
    error_log("No direct or inverse rate found, trying through USD");
    
    if ($from_currency != 'USD' && $to_currency != 'USD') {
        try {
            // Get rate from source currency to USD
            $to_usd_rate = getCurrentMarketRate($pdo, $from_currency, 'USD');
            // Get rate from USD to target currency
            $from_usd_rate = getCurrentMarketRate($pdo, 'USD', $to_currency);
            
            $calculated_rate = $to_usd_rate * $from_usd_rate;
            error_log("Calculated rate through USD: " . $calculated_rate);
            return $calculated_rate;
            
        } catch (Exception $e) {
            error_log("Failed to calculate rate through USD: " . $e->getMessage());
        }
    }
    
    error_log("No exchange rate found for {$from_currency} to {$to_currency}");
    throw new Exception("Exchange rate not found for {$from_currency} to {$to_currency}");
}

// Function to update exchange rate
function updateExchangeRate($pdo, $from_currency, $to_currency, $rate, $tenant_id, $branch_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO exchange_rates (from_currency, to_currency, rate, tenant_id, branch_id) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bindParam(1, $from_currency, PDO::PARAM_STR);
        $stmt->bindParam(2, $to_currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $rate, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Also update the inverse rate
        $inverse_rate = 1 / $rate;
        $stmt = $pdo->prepare("
            INSERT INTO exchange_rates (from_currency, to_currency, rate, tenant_id, branch_id) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bindParam(1, $to_currency, PDO::PARAM_STR);
        $stmt->bindParam(2, $from_currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $inverse_rate, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Exchange rate updated successfully'
        ];
    } catch (Exception $e) {
        error_log("Error updating exchange rate: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error updating exchange rate: ' . $e->getMessage()
        ];
    }
}

// Function to get exchange rate history
function getExchangeRateHistory($pdo, $from_currency, $to_currency, $tenant_id, $branch_id, $days = 7) {
    try {
        $stmt = $pdo->prepare("
            SELECT rate, created_at, tenant_id 
            FROM exchange_rates 
            WHERE (from_currency = ? AND to_currency = ?) 
            OR (from_currency = ? AND to_currency = ?)
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND tenant_id = ? AND branch_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->bindParam(1, $from_currency, PDO::PARAM_STR);
        $stmt->bindParam(2, $to_currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $to_currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $from_currency, PDO::PARAM_STR);
        $stmt->bindParam(5, $days, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $history = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rate = ($row['from_currency'] == $from_currency) ? $row['rate'] : 1 / $row['rate'];
            $history[] = [
                'rate' => $rate,
                'date' => $row['created_at']
            ];
        }
        
        return [
            'success' => true,
            'history' => $history
        ];
    } catch (Exception $e) {
        error_log("Error getting exchange rate history: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error getting exchange rate history: ' . $e->getMessage()
        ];
    }
}

// Function to calculate potential profit
function calculatePotentialProfit($pdo, $from_amount, $from_currency, $to_currency, $exchange_rate) {
    try {
        // Try to get market rate, but don't fail if not found
        try {
            $market_rate = getCurrentMarketRate($pdo, $from_currency, $to_currency);
        } catch (Exception $e) {
            // If market rate is not available, use the provided exchange rate
            $market_rate = $exchange_rate;
        }
        
        $market_amount = $from_amount * $market_rate;
        $exchange_amount = $from_amount * $exchange_rate;
        $profit = $exchange_amount - $market_amount;
        
        return [
            'success' => true,
            'profit_amount' => $profit,
            'profit_currency' => $to_currency,
            'market_rate' => $market_rate,
            'market_amount' => $market_amount
        ];
    } catch (Exception $e) {
        error_log("Error calculating potential profit: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
