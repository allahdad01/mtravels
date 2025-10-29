<?php
// Start output buffering
ob_start();

// Include security module
require_once 'security.php';

// Enforce authentication and get tenant_id
enforce_auth(); // Assuming enforce_auth() returns tenant_id or sets it in sessio
$tenant_id = $_SESSION['tenant_id'];

require_once('../includes/db.php');

// Set content type header
header('Content-Type: application/json');

try {
    $dues = [
        'ticket_dues_usd' => 0,
        'ticket_dues_afs' => 0,
        'ticket_reserve_dues_usd' => 0,
        'ticket_reserve_dues_afs' => 0,
        'ticket_dues_eur' => 0,
        'ticket_dues_aed' => 0,
        'ticket_weights_dues_usd' => 0,
        'ticket_weights_dues_afs' => 0,
        'ticket_weights_dues_eur' => 0,
        'ticket_weights_dues_aed' => 0,
        'datechange_dues_usd' => 0,
        'datechange_dues_afs' => 0,
        'datechange_dues_eur' => 0,
        'datechange_dues_aed' => 0,
        'refunded_ticket_dues_usd' => 0,
        'refunded_ticket_dues_afs' => 0,
        'refunded_ticket_dues_eur' => 0,
        'refunded_ticket_dues_aed' => 0,
        'umrah_dues_usd' => 0,
        'umrah_dues_afs' => 0,
        'umrah_dues_eur' => 0,
        'umrah_dues_aed' => 0,
        'visa_dues_usd' => 0,
        'visa_dues_afs' => 0,
        'visa_dues_eur' => 0,
        'visa_dues_aed' => 0,
        'hotel_dues_usd' => 0,
        'hotel_dues_afs' => 0,
        'hotel_dues_eur' => 0,
        'hotel_dues_aed' => 0,
        'addpayment_dues_usd' => 0,
        'addpayment_dues_afs' => 0,
        'addpayment_dues_eur' => 0,
        'addpayment_dues_aed' => 0
    ];

    // Arrays to store total amounts and paid amounts for percentage calculations
    $total_amounts = [
        'ticket' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'ticket_reserve' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'ticket_weights' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'datechange' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'refunded_ticket' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'umrah' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'visa' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'hotel' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'addpayment' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0]
    ];

    $paid_amounts = [
        'ticket' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'ticket_reserve' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'ticket_weights' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'datechange' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'refunded_ticket' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'umrah' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'visa' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'hotel' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0],
        'addpayment' => ['usd' => 0, 'afs' => 0, 'eur' => 0, 'aed' => 0]
    ];
    function convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates) {
        // Normalize currency names
        $transCurrency = strtoupper($transCurrency);
        $baseCurrency = strtoupper($baseCurrency);
        
        // Handle DARHAM as AED for consistency
        if ($transCurrency === 'DARHAM') $transCurrency = 'DAR';
        if ($baseCurrency === 'DARHAM') $baseCurrency = 'DAR';
        
        // If same currency, no conversion needed - THIS IS CRITICAL!
        if ($transCurrency === $baseCurrency) {
            error_log("DEBUG: Same currency ($transCurrency), returning original amount: $amount");
            return $amount;
        }
        
        // Debug logging
        error_log("DEBUG: Converting $amount from $transCurrency to $baseCurrency with rate $transExchangeRate");
        
        // Based on your exchange rates: USD: 70, EUR: 77.2, AED: 18.5
        // This means: 1 USD = 70 AFS, 1 EUR = 77.2 AFS, 1 AED = 18.5 AFS
        
        if ($baseCurrency === 'AFS') {
            // Converting TO AFS (your main case)
            if ($transCurrency === 'USD' && $transExchangeRate > 0) {
                $result = $amount * $transExchangeRate; // USD to AFS: multiply
                error_log("DEBUG: USD to AFS: $amount * $transExchangeRate = $result");
                return $result;
            } elseif ($transCurrency === 'EUR' && $transExchangeRate > 0) {
                $result = $amount * $transExchangeRate; // EUR to AFS: multiply
                error_log("DEBUG: EUR to AFS: $amount * $transExchangeRate = $result");
                return $result;
            } elseif ($transCurrency === 'DAR' && $transExchangeRate > 0) {
                $result = $amount * $transExchangeRate; // AED to AFS: multiply
                error_log("DEBUG: AED to AFS: $amount * $transExchangeRate = $result");
                return $result;
            }
        } elseif ($baseCurrency === 'USD') {
            // Converting TO USD
            if ($transCurrency === 'AFS' && $transExchangeRate > 0) {
                $result = $amount / $transExchangeRate; // AFS to USD: divide
                error_log("DEBUG: AFS to USD: $amount / $transExchangeRate = $result");
                return $result;
            } elseif ($transCurrency === 'EUR' && $transExchangeRate > 0) {
                $result = $amount / $transExchangeRate; // EUR to USD: divide
                error_log("DEBUG: EUR to USD: $amount / $transExchangeRate = $result");
                return $result;
            } elseif ($transCurrency === 'DAR' && $transExchangeRate > 0) {
                $result = $amount / $transExchangeRate; // AED to USD: divide
                error_log("DEBUG: AED to USD: $amount / $transExchangeRate = $result");
                return $result;
            }
        }
        // Add other base currency conversions as needed...
        
        // If no conversion found, log error and return 0
        error_log("ERROR: No conversion found for $transCurrency to $baseCurrency with rate $transExchangeRate");
        return 0.0;
    }

    // Ticket Bookings Dues
    try {
        $ticketQuery = "
            SELECT tb.id, tb.currency, tb.sold
            FROM ticket_bookings tb
            JOIN clients c ON tb.sold_to = c.id
            WHERE c.client_type = 'agency' AND tb.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($ticketQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'ticket_sale' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'ticket_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['ticket'][$currency] += $sold;
            $paid_amounts['ticket'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in ticket dues calculation: " . $e->getMessage());
    }
    // Ticket Reserve Bookings Dues
    try {
        $ticketReserveQuery = "
            SELECT tb.id, tb.currency, tb.sold
            FROM ticket_reservations tb
            JOIN clients c ON tb.sold_to = c.id
            WHERE c.client_type = 'agency' AND tb.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($ticketReserveQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'ticket_reserve' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'ticket_reserve_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['ticket'][$currency] += $sold;
            $paid_amounts['ticket'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in ticket dues calculation: " . $e->getMessage());
    }
    // Ticket Weights Dues
    try {
        $ticketWeightsQuery = "
            SELECT tw.id, tb.currency, tw.sold_price
            FROM ticket_weights tw
            JOIN ticket_bookings tb ON tw.ticket_id = tb.id
            JOIN clients c ON tb.sold_to = c.id
            WHERE c.client_type = 'agency' AND tb.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($ticketWeightsQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold_price']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'weight' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'ticket_weights_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['ticket_weights'][$currency] += $sold;
            $paid_amounts['ticket_weights'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in ticket weights dues calculation: " . $e->getMessage());
    }

    // Date Change Dues
    try {
        $dateChangeQuery = "
            SELECT dc.id, dc.currency, dc.supplier_penalty + dc.service_penalty as total_amount
            FROM date_change_tickets dc
            JOIN clients c ON dc.sold_to = c.id
            WHERE c.client_type = 'agency' AND dc.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($dateChangeQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['total_amount']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'date_change' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'datechange_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['datechange'][$currency] += $sold;
            $paid_amounts['datechange'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in date change dues calculation: " . $e->getMessage());
    }

    // Refunded Ticket Dues
    try {
        $refundedTicketQuery = "
            SELECT rt.id, rt.currency, rt.refund_to_passenger as total_amount
            FROM refunded_tickets rt
            JOIN clients c ON rt.sold_to = c.id
            WHERE c.client_type = 'agency' AND rt.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($refundedTicketQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['total_amount']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'ticket_refund' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'refunded_ticket_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['refunded_ticket'][$currency] += $sold;
            $paid_amounts['refunded_ticket'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in refunded ticket dues calculation: " . $e->getMessage());
    }

    // Umrah Dues
    try {
        $umrahQuery = "
            SELECT u.booking_id, u.currency, u.sold_price
            FROM umrah_bookings u
            JOIN clients c ON u.sold_to = c.id
            WHERE c.client_type = 'agency' AND u.status = 'active' AND u.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($umrahQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold_price']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM umrah_transactions WHERE umrah_booking_id = ?");
            $transStmt->execute([$row['booking_id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['payment_amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'umrah_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['umrah'][$currency] += $sold;
            $paid_amounts['umrah'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in umrah dues calculation: " . $e->getMessage());
    }

    // Visa Dues
    try {
        $visaQuery = "
            SELECT v.id, v.currency, v.sold
            FROM visa_applications v
            JOIN clients c ON v.sold_to = c.id
            WHERE c.client_type = 'agency' AND v.status != 'refunded' AND v.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($visaQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'visa_sale' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'visa_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['visa'][$currency] += $sold;
            $paid_amounts['visa'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in visa dues calculation: " . $e->getMessage());
    }

    // Hotel Dues
    try {
        $hotelQuery = "
            SELECT h.id, h.currency, h.sold_amount
            FROM hotel_bookings h
            JOIN clients c ON h.sold_to = c.id
            WHERE c.client_type = 'agency' AND h.status = 'active' AND h.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($hotelQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold_amount']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'hotel' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'hotel_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['hotel'][$currency] += $sold;
            $paid_amounts['hotel'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in hotel dues calculation: " . $e->getMessage());
    }

    // Additional Payments Dues
    try {
        $additionalPaymentsQuery = "
            SELECT ap.id, ap.currency, ap.sold_amount
            FROM additional_payments ap
            LEFT JOIN clients c ON ap.client_id = c.id
            WHERE (c.client_type = 'agency' OR ap.client_id IS NULL) AND ap.tenant_id = :tenant_id
        ";
        $stmt = $pdo->prepare($additionalPaymentsQuery);
        $stmt->execute(['tenant_id' => $tenant_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $baseCurrency = $row['currency'];
            $sold = floatval($row['sold_amount']);
            // Query transactions
            $transStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'additional_payment' AND reference_id = ?");
            $transStmt->execute([$row['id']]);
            $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
            // Collect rates
            $rates = ['AFS' => 1.0, 'USD' => 1.0, 'EUR' => 1.0, 'DARHAM' => 1.0, 'DAR' => 1.0];
            foreach ($transactions as $trans) {
                $transCurrency = $trans['currency'];
                if (isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 && in_array($transCurrency, ['AFS', 'USD', 'EUR', 'DARHAM', 'DAR'])) {
                    $rates[$transCurrency] = floatval($trans['exchange_rate']);
                }
            }
            // Calculate total paid
            $totalPaid = 0.0;
            foreach ($transactions as $trans) {
                $amount = floatval($trans['amount']);
                $transCurrency = $trans['currency'];
                $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
                $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
                $totalPaid += $convertedAmount;
            }
            $due_amount = max(0, $sold - $totalPaid);
            $key = 'addpayment_dues_' . strtolower($baseCurrency);
            if (!isset($dues[$key])) $dues[$key] = 0;
            $dues[$key] += $due_amount;
            // Track total and paid amounts for percentage calculation
            $currency = strtolower($baseCurrency);
            $total_amounts['addpayment'][$currency] += $sold;
            $paid_amounts['addpayment'][$currency] += $totalPaid;
        }
    } catch (PDOException $e) {
        error_log("Error in additional payments dues calculation: " . $e->getMessage());
    }

    echo json_encode($dues);

} catch (PDOException $e) {
    error_log("Error in dues calculation: " . $e->getMessage());
   
}

// Flush output buffer
ob_end_flush();
?>