<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
header('Content-Type: application/json');

try {
    // Get date range from request and ensure proper formatting
    $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-01');
    $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-t');

    // Validate and format dates
    $startDateTime = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);
    
    // Set time to beginning of start day and end of end day
    $startDateTime->setTime(0, 0, 0);
    $endDateTime->setTime(23, 59, 59);
    
    // Format dates for database queries
    $startDate = $startDateTime->format('Y-m-d H:i:s');
    $endDate = $endDateTime->format('Y-m-d H:i:s');
    
    // Initialize arrays for income
    $incomeData = [
        'tickets' => ['USD' => 0, 'AFS' => 0],
        'ticket_weights' => ['USD' => 0, 'AFS' => 0],
        'reservations' => ['USD' => 0, 'AFS' => 0],
        'refunds' => ['USD' => 0, 'AFS' => 0],
        'dateChanges' => ['USD' => 0, 'AFS' => 0],
        'visa' => ['USD' => 0, 'AFS' => 0],
        'umrah' => ['USD' => 0, 'AFS' => 0],
        'hotel' => ['USD' => 0, 'AFS' => 0],
        'additionalPayments' => ['USD' => 0, 'AFS' => 0]
    ];

    // Fetch ticket bookings income
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as total, currency 
        FROM ticket_bookings 
        WHERE created_at BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['tickets'][$currency] = floatval($row['total']);
    }

    // Fetch ticket bookings income
    $stmt = $pdo->prepare("
        SELECT SUM(tw.profit) as total, tb.currency 
        FROM ticket_weights tw
        Left join ticket_bookings tb ON tb.id = tw.ticket_id 
        WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ?
        GROUP BY tb.currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['ticket_weights'][$currency] = floatval($row['total']);
    }

    // Fetch ticket reservations income
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as total, currency 
        FROM ticket_reservations 
        WHERE created_at BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['reservations'][$currency] = floatval($row['total']);
    }

    // Fetch refunded tickets income
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE 
                WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                ELSE rt.service_penalty 
            END) as total, 
            rt.currency 
        FROM refunded_tickets rt
        JOIN ticket_bookings tb ON rt.ticket_id = tb.id
        WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
        GROUP BY rt.currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['refunds'][$currency] = floatval($row['total']);
    }

    // Fetch date change tickets income
    $stmt = $pdo->prepare("
        SELECT SUM(dt.service_penalty) as total, dt.currency 
        FROM date_change_tickets dt 
        JOIN ticket_bookings tb ON dt.ticket_id = tb.id
        WHERE dt.created_at BETWEEN ? AND ? AND dt.tenant_id = ?
        GROUP BY dt.currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['dateChanges'][$currency] = floatval($row['total']);
    }

    // Fetch visa income
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as total, currency 
        FROM visa_applications 
        WHERE created_at BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['visa'][$currency] = floatval($row['total']);
    }

    // Fetch umrah income
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as total, currency 
        FROM umrah_bookings 
        WHERE created_at BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['umrah'][$currency] = floatval($row['total']);
    }

    // Fetch hotel income
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as total, currency 
        FROM hotel_bookings 
        WHERE created_at BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['hotel'][$currency] = floatval($row['total']);
    }

    // Fetch additional payments income
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as total, currency 
        FROM additional_payments 
        WHERE created_at BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $incomeData['additionalPayments'][$currency] = floatval($row['total']);
    }

    // Get expenses by category and currency - FIXED: Added tenant_id filter for expense_categories
    $expenseData = [
        'USD' => [
            'categories' => [],
            'amounts' => []
        ],
        'AFS' => [
            'categories' => [],
            'amounts' => []
        ]
    ];

    // Fetch expense categories and their totals - FIXED: Proper tenant_id handling
    $stmt = $pdo->prepare("
        SELECT 
            ec.name,
            SUM(CASE WHEN e.currency = 'USD' OR e.currency IS NULL THEN e.amount ELSE 0 END) as usd_amount,
            SUM(CASE WHEN e.currency = 'AFS' THEN e.amount ELSE 0 END) as afs_amount
        FROM expense_categories ec
        LEFT JOIN expenses e ON e.category_id = ec.id AND e.tenant_id = ?
        WHERE (e.date BETWEEN ? AND ? OR e.date IS NULL) AND ec.tenant_id = ?
        GROUP BY ec.id, ec.name
    ");
    $stmt->execute([$tenant_id, $startDate, $endDate, $tenant_id]);
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($row['usd_amount'] > 0) {
            $expenseData['USD']['categories'][] = $row['name'];
            $expenseData['USD']['amounts'][] = floatval($row['usd_amount']);
        }
        if($row['afs_amount'] > 0) {
            $expenseData['AFS']['categories'][] = $row['name'];
            $expenseData['AFS']['amounts'][] = floatval($row['afs_amount']);
        }
    }

    // FIXED: Removed the problematic UNION query that was causing tenant_id issues
    // Calculate total expenses for the date range
    $totalExpenses = ['USD' => 0, 'AFS' => 0];
    
    // Get expenses total
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total, currency 
        FROM expenses
        WHERE date BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $totalExpenses[$currency] += floatval($row['total']);
    }

    // Get salary payments total
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total, currency 
        FROM salary_payments
        WHERE payment_date BETWEEN ? AND ? AND tenant_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currency = $row['currency'] ?? 'USD';
        $totalExpenses[$currency] += floatval($row['total']);
    }

    // Also add salary payments to expense categories
    $stmt = $pdo->prepare("
        SELECT 
            'Salaries' as name,
            SUM(CASE WHEN currency = 'USD' OR currency IS NULL THEN amount ELSE 0 END) as usd_amount,
            SUM(CASE WHEN currency = 'AFS' THEN amount ELSE 0 END) as afs_amount
        FROM salary_payments
        WHERE payment_date BETWEEN ? AND ? AND tenant_id = ?
    ");
    $stmt->execute([$startDate, $endDate, $tenant_id]);
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($row['usd_amount'] > 0) {
            $expenseData['USD']['categories'][] = $row['name'];
            $expenseData['USD']['amounts'][] = floatval($row['usd_amount']);
        }
        if($row['afs_amount'] > 0) {
            $expenseData['AFS']['categories'][] = $row['name'];
            $expenseData['AFS']['amounts'][] = floatval($row['afs_amount']);
        }
    }

    // FIXED: Simplified total income calculation to avoid UNION issues
    $totalIncome = ['USD' => 0, 'AFS' => 0];
    
    // Calculate total income by summing up individual income data
    foreach($incomeData as $category => $currencies) {
        foreach($currencies as $currency => $amount) {
            $totalIncome[$currency] += $amount;
        }
    }

    // Debugging: Print total income
    error_log("Total Income: " . print_r($totalIncome, true));
    error_log("Total Expenses: " . print_r($totalExpenses, true));

    // Calculate profit/loss for the date range
    $profitLossData = [
        'USD' => $totalIncome['USD'] - $totalExpenses['USD'],
        'AFS' => $totalIncome['AFS'] - $totalExpenses['AFS']
    ];

    // Adjust profit/loss calculation based on definitions
    foreach ($profitLossData as $currency => $value) {
        if ($value < 0) {
            // If the result is negative, it's a loss
            $profitLossData[$currency] = [
                'profit' => 0,
                'loss' => abs($value)
            ];
        } else {
            // If the result is positive, it's a profit
            $profitLossData[$currency] = [
                'profit' => $value,
                'loss' => 0
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'income' => $incomeData,
        'expenses' => $expenseData,
        'profitLoss' => $profitLossData,
        'debug' => [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'dateRange' => ['start' => $startDate, 'end' => $endDate],
            'tenant_id' => $tenant_id
        ]
    ]);

} catch(Exception $e) {
    // Enhanced error logging
    error_log("Dashboard API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'tenant_id' => isset($tenant_id) ? $tenant_id : 'not set'
        ]
    ]);
}

// Additional debugging function to check table structures
function debugTableStructures($pdo) {
    $tables = [
        'ticket_bookings', 'ticket_reservations', 'refunded_tickets', 
        'date_change_tickets', 'visa_applications', 'umrah_bookings', 
        'hotel_bookings', 'additional_payments', 'expenses', 
        'salary_payments', 'expense_categories'
    ];
    
    foreach($tables as $table) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE 'tenant_id'");
            $stmt->execute();
            $result = $stmt->fetch();
            error_log("Table $table has tenant_id column: " . ($result ? 'YES' : 'NO'));
        } catch(Exception $e) {
            error_log("Error checking table $table: " . $e->getMessage());
        }
    }
}

// Uncomment this line to debug table structures
// debugTableStructures($pdo);
?>