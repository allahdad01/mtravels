<?php
// Function to process currency exchange
function processCurrencyExchange($conn, $data) {
    $tenant_id = $_SESSION['tenant_id'];

    try {
        // Debug log
        error_log("Starting currency exchange process with data: " . json_encode($data));
        
        // Start transaction
        $conn->autocommit(FALSE);
        
        // Verify customer has sufficient balance
        $stmt = $conn->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("isi", $data['customer_id'], $data['from_currency'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();
        
        if (!$wallet || $wallet['balance'] < $data['from_amount']) {
            throw new Exception('Insufficient balance for exchange');
        }
        
        // Calculate profit/loss using the provided rate
        $provided_rate = $data['rate'];
        error_log("Using provided exchange rate: " . $provided_rate);
        
        // Try to get market rate for profit calculation, but don't fail if not found
        try {
            $market_rate = getCurrentMarketRate($conn, $data['from_currency'], $data['to_currency']);
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
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, tenant_id) VALUES (?, ?, ?, 'exchange', ?, ?)");
        $stmt->bind_param("idssi", $data['customer_id'], $data['from_amount'], $data['from_currency'], $data['notes'], $tenant_id);
        if (!$stmt->execute()) {
            throw new Exception("Error inserting transaction: " . $conn->error);
        }
        $transaction_id = $conn->insert_id;
        
        // Record exchange details
        $stmt = $conn->prepare("INSERT INTO exchange_transactions (transaction_id, from_amount, from_currency, to_amount, to_currency, rate, profit_amount, profit_currency, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsdsddsi", $transaction_id, $data['from_amount'], $data['from_currency'], $data['to_amount'], $data['to_currency'], $provided_rate, $profit_amount, $data['to_currency'], $tenant_id);
        if (!$stmt->execute()) {
            throw new Exception("Error inserting exchange details: " . $conn->error);
        }
        
        // Update customer wallets
        // Deduct from source currency wallet
        $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("disi", $data['from_amount'], $data['customer_id'], $data['from_currency'], $tenant_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating source wallet: " . $conn->error);
        }
        
        // Add to destination currency wallet
        $stmt = $conn->prepare("
            INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE balance = balance + ?
        ");
        $stmt->bind_param("isddi", $data['customer_id'], $data['to_currency'], $data['to_amount'], $tenant_id, $data['to_amount']);
        if (!$stmt->execute()) {
            throw new Exception("Error updating destination wallet: " . $conn->error);
        }
        
        // Store the exchange rate for future reference
        try {
            updateExchangeRate($conn, $data['from_currency'], $data['to_currency'], $provided_rate);
        } catch (Exception $e) {
            error_log("Warning: Could not update exchange rate history: " . $e->getMessage());
            // Don't fail the transaction if we can't update the rate history
        }
        
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Error committing transaction: " . $conn->error);
        }
        
        // Re-enable autocommit
        $conn->autocommit(TRUE);
        
        return [
            'success' => true,
            'message' => 'Currency exchange completed successfully',
            'transaction_id' => $transaction_id,
            'profit_amount' => $profit_amount,
            'exchange_rate' => $provided_rate
        ];
        
    } catch (Exception $e) {
        error_log("Error in processCurrencyExchange: " . $e->getMessage());
        // Rollback transaction
        $conn->rollback();
        // Re-enable autocommit
        $conn->autocommit(TRUE);
        
        return [
            'success' => false,
            'message' => 'Error processing currency exchange: ' . $e->getMessage()
        ];
    }
}

// Function to get current market rate
function getCurrentMarketRate($conn, $from_currency, $to_currency) {
    error_log("Getting market rate for {$from_currency} to {$to_currency}");
    
    // Try direct rate first
    $stmt = $conn->prepare("
        SELECT rate 
        FROM exchange_rates 
        WHERE from_currency = ? 
        AND to_currency = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $from_currency, $to_currency);
    if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $rate = $result->fetch_assoc();
    
    if ($rate) {
        error_log("Found direct rate: " . $rate['rate']);
        return $rate['rate'];
    }
    
    error_log("No direct rate found, trying inverse rate");
    
    // Try inverse rate
    $stmt = $conn->prepare("
        SELECT rate 
        FROM exchange_rates 
        WHERE from_currency = ? 
        AND to_currency = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $to_currency, $from_currency);
    if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $rate = $result->fetch_assoc();
    
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
            $to_usd_rate = getCurrentMarketRate($conn, $from_currency, 'USD');
            // Get rate from USD to target currency
            $from_usd_rate = getCurrentMarketRate($conn, 'USD', $to_currency);
            
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
function updateExchangeRate($conn, $from_currency, $to_currency, $rate) {
    $tenant_id = $_SESSION['tenant_id'];

    try {
        $stmt = $conn->prepare("
            INSERT INTO exchange_rates (from_currency, to_currency, rate, tenant_id) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("ssdi", $from_currency, $to_currency, $rate, $tenant_id);
        $stmt->execute();
        
        // Also update the inverse rate
        $inverse_rate = 1 / $rate;
        $stmt = $conn->prepare("
            INSERT INTO exchange_rates (from_currency, to_currency, rate, tenant_id) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("ssdi", $to_currency, $from_currency, $inverse_rate, $tenant_id);
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
function getExchangeRateHistory($conn, $from_currency, $to_currency, $days = 7, $tenant_id) {
    try {
        $stmt = $conn->prepare("
            SELECT rate, created_at, tenant_id 
            FROM exchange_rates 
            WHERE (from_currency = ? AND to_currency = ?) 
            OR (from_currency = ? AND to_currency = ?)
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND tenant_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param("ssssii", $from_currency, $to_currency, $to_currency, $from_currency, $days, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
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
function calculatePotentialProfit($conn, $from_amount, $from_currency, $to_currency, $exchange_rate) {
    try {
        // Try to get market rate, but don't fail if not found
        try {
            $market_rate = getCurrentMarketRate($conn, $from_currency, $to_currency);
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