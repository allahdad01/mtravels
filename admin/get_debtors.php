<?php
// Start session to check authentication
session_start();

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header('Content-Type: application/json');


// Database connection with error handling
require_once('../includes/db.php');
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

// Rate limiting - check if there have been too many requests from this user
$userId = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$currentTime = time();
$rateLimitWindow = 60; // 1 minute window
$maxRequests = 30; // Maximum 30 requests per minute

// Store rate limit data in session
if (!isset($_SESSION['api_rate_limits'])) {
    $_SESSION['api_rate_limits'] = [];
}

if (!isset($_SESSION['api_rate_limits']['get_debtors'])) {
    $_SESSION['api_rate_limits']['get_debtors'] = [
        'count' => 0,
        'window_start' => $currentTime
    ];
}

// Reset if window has expired
if ($currentTime - $_SESSION['api_rate_limits']['get_debtors']['window_start'] > $rateLimitWindow) {
    $_SESSION['api_rate_limits']['get_debtors'] = [
        'count' => 0,
        'window_start' => $currentTime
    ];
}

// Increment count and check if over limit
$_SESSION['api_rate_limits']['get_debtors']['count']++;
if ($_SESSION['api_rate_limits']['get_debtors']['count'] > $maxRequests) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

// Input validation
if (!isset($_GET['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Transaction type is required']);
    exit;
}

// Sanitize input
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$type) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid transaction type']);
    exit;
}

// Valid transaction types
$validTypes = ['ticket', 'ticket_reserve', 'weight', 'datechange', 'refunded', 'umrah', 'visa', 'hotel', 'addpayment'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid transaction type']);
    exit;
}

$debtors = [];

try {
    switch($type) {
        case 'ticket':
            $mainQuery = "SELECT tb.id, CONCAT(tb.title, ' ', tb.passenger_name) as name, tb.pnr, tb.phone, tb.currency, tb.sold as total_amount, tb.issue_date as date FROM ticket_bookings tb JOIN clients c ON tb.sold_to = c.id WHERE c.client_type = 'agency' AND tb.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'ticket_sale';
            break;
        case 'ticket_reserve':
            $mainQuery = "SELECT tb.id, CONCAT(tb.title, ' ', tb.passenger_name) as name, tb.pnr, tb.phone, tb.currency, tb.sold as total_amount, tb.issue_date as date FROM ticket_reservations tb JOIN clients c ON tb.sold_to = c.id WHERE c.client_type = 'agency' AND tb.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'ticket_reserve';
            break;

            case 'weight':
                $mainQuery = "SELECT tw.id, CONCAT(tb.title, ' ', tb.passenger_name) as name, tb.pnr, tb.phone, tb.currency, tw.sold_price as total_amount, tw.created_at as date FROM ticket_weights tw JOIN ticket_bookings tb ON tw.ticket_id = tb.id JOIN clients c ON tb.sold_to = c.id WHERE c.client_type = 'agency' AND tw.tenant_id = ?";
                $transactionTable = 'main_account_transactions';
                $amountField = 'amount';
                $referenceField = 'reference_id';
                $transactionOf = 'weight';
                break;

        case 'datechange':
            $mainQuery = "SELECT dc.id, CONCAT(dc.title, ' ', dc.passenger_name) as name, dc.pnr, dc.phone, dc.currency, (dc.supplier_penalty + dc.service_penalty) as total_amount, dc.created_at as date FROM date_change_tickets dc JOIN clients c ON dc.sold_to = c.id WHERE c.client_type = 'agency' AND dc.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'date_change';
            break;

        case 'refunded':
            $mainQuery = "SELECT rt.id, CONCAT(rt.title, ' ', rt.passenger_name) as name, rt.pnr, rt.phone, rt.currency, rt.refund_to_passenger as total_amount, rt.issue_date as date FROM refunded_tickets rt JOIN clients c ON rt.sold_to = c.id WHERE c.client_type = 'agency' AND rt.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'ticket_refund';
            break;

        case 'umrah':
            $mainQuery = "SELECT u.booking_id as id, u.name, fa.contact as phone, u.currency, u.sold_price as total_amount, u.entry_date as date FROM umrah_bookings u LEFT JOIN families fa ON u.family_id = fa.family_id JOIN clients c ON u.sold_to = c.id WHERE c.client_type = 'agency' and u.status = 'active' AND u.tenant_id = ?";
            $transactionTable = 'umrah_transactions';
            $amountField = 'payment_amount';
            $referenceField = 'umrah_booking_id';
            $transactionOf = null;
            break;

        case 'visa':
            $mainQuery = "SELECT v.id, CONCAT(v.title, ' ', v.applicant_name) as name, v.passport_number as pnr, v.phone, v.currency, v.sold as total_amount, v.receive_date as date FROM visa_applications v JOIN clients c ON v.sold_to = c.id WHERE c.client_type = 'agency' and v.status != 'refunded' AND v.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'visa_sale';
            break;

        case 'hotel':
            $mainQuery = "SELECT h.id, CONCAT(h.title, ' ', h.first_name, ' ', h.last_name) as name, h.order_id as pnr, h.contact_no as phone, h.currency, h.sold_amount as total_amount, h.issue_date as date FROM hotel_bookings h JOIN clients c ON h.sold_to = c.id WHERE c.client_type = 'agency' and h.status = 'active' AND h.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'hotel';
            break;

        case 'addpayment':
            $mainQuery = "SELECT ap.id, ap.payment_type as name, null as pnr, '' as phone, ap.currency, ap.sold_amount as total_amount, ap.created_at as date FROM additional_payments ap LEFT JOIN clients c ON ap.client_id = c.id WHERE (c.client_type = 'agency' OR ap.client_id IS NULL) AND ap.tenant_id = ?";
            $transactionTable = 'main_account_transactions';
            $amountField = 'amount';
            $referenceField = 'reference_id';
            $transactionOf = 'additional_payment';
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid transaction type']);
            exit;
    }

    $stmt = $pdo->prepare($mainQuery);
    $stmt->execute([$tenant_id]);
    $debtors = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $baseCurrency = $row['currency'];
        $sold = floatval($row['total_amount']);
        // Query transactions
        if ($transactionOf) {
            $transStmt = $pdo->prepare("SELECT * FROM $transactionTable WHERE $referenceField = ? AND transaction_of = ?");
            $transStmt->execute([$row['id'], $transactionOf]);
        } else {
            $transStmt = $pdo->prepare("SELECT * FROM $transactionTable WHERE $referenceField = ?");
            $transStmt->execute([$row['id']]);
        }
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
            $amount = floatval($trans[$amountField]);
            $transCurrency = $trans['currency'];
            $transExchangeRate = isset($trans['exchange_rate']) && $trans['exchange_rate'] > 0 ? floatval($trans['exchange_rate']) : 1.0;
            $convertedAmount = convertAmountToBase($amount, $transCurrency, $baseCurrency, $transExchangeRate, $rates);
            $totalPaid += $convertedAmount;
        }
        $amount_due = max(0, $sold - $totalPaid);
        if ($amount_due > 0) {
            $debtors[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'pnr' => $row['pnr'] ?? null,
                'phone' => $row['phone'] ?? '',
                'currency' => $baseCurrency,
                'total_amount' => $sold,
                'paid_amount' => $totalPaid,
                'amount_due' => $amount_due,
                'date' => $row['date']
            ];
        }
    }

    // Sanitize output data to prevent XSS
    foreach ($debtors as &$debtor) {
        foreach ($debtor as $key => $value) {
            if ($key !== 'id' && !is_numeric($value)) {
                $debtor[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
    }
    
    // Log successful access
    error_log("Debtors data accessed: Type={$type}, User={$userId}, Count=" . count($debtors));

    // Success response
    echo json_encode($debtors);

} catch (PDOException $e) {
    // Log the error but don't expose details
    error_log("Database error in get_debtors.php: " . $e->getMessage() . " - User: {$userId}");
    
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred. Please try again later.']);
    exit;
}
?> 