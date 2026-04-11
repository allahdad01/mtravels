<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to database
require_once('../../includes/db.php');

// Validate input
if (!isset($_POST['period']) || empty($_POST['period'])) {
    echo json_encode(['status' => 'error', 'message' => 'Period is required']);
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$period = $_POST['period'];
$filteredDate = isset($_POST['filtered_date']) ? $_POST['filtered_date'] : null;



// Set up date condition based on period and filtered date
if ($period === 'daily') {
    $dailyDate = $filteredDate ?: date('Y-m-d');
    $dateCondition = "DATE(created_at) = :date";
    $params = [':date' => $dailyDate];
} elseif ($period === 'monthly') {
    if ($filteredDate) {
        list($year, $month) = explode('-', $filteredDate);
    } else {
        $year = date('Y');
        $month = date('m');
    }
    $dateCondition = "MONTH(created_at) = :month AND YEAR(created_at) = :year";
    $params = [':month' => (int)$month, ':year' => (int)$year];
} elseif ($period === 'yearly') {
    $year = $filteredDate ?: date('Y');
    $dateCondition = "YEAR(created_at) = :year";
    $params = [':year' => (int)$year];
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit();
}

// Define profit sources to fetch
$profitSources = [
    ['table' => 'ticket_bookings', 'source' => 'Ticket Bookings', 'type' => 'ticket'],
    ['table' => 'ticket_reservations', 'source' => 'Ticket Reservations', 'type' => 'ticket_reservations'],
    ['table' => 'visa_applications', 'source' => 'Visa Applications', 'type' => 'visa'],
    ['table' => 'umrah_bookings', 'source' => 'Umrah Bookings', 'type' => 'umrah'],
    ['table' => 'hotel_bookings', 'source' => 'Hotel Bookings', 'type' => 'hotel'],
    ['table' => 'additional_payments', 'source' => 'Additional Payments', 'type' => 'payment']
];

$results = [];

try {
    // Fetch profits for each source
    foreach ($profitSources as $source) {
        $table = $source['table'];

        $query = "SELECT
            COALESCE(SUM(CASE WHEN currency='USD' THEN profit ELSE 0 END),0) AS usd,
            COALESCE(SUM(CASE WHEN currency='AFS' THEN profit ELSE 0 END),0) AS afs
        FROM $table
        WHERE tenant_id = :tenant_id AND branch_id = :branch_id AND $dateCondition";

        try {
            $stmt = $pdo->prepare($query);
            $executeParams = $params + [':tenant_id' => $tenant_id, ':branch_id' => $branch_id];
            $stmt->execute($executeParams);
            $profit = $stmt->fetch(PDO::FETCH_ASSOC);

            $results[] = [
                'source' => $source['source'],
                'type' => $source['type'],
                'usd' => floatval($profit['usd']),
                'afs' => floatval($profit['afs']),
                'period' => $period
            ];
        } catch (PDOException $e) {
            $results[] = [
                'source' => $source['source'],
                'type' => $source['type'],
                'usd' => 0,
                'afs' => 0,
                'period' => $period
            ];
        }
    }

    // Ticket Weights
    if ($period === 'daily') {
        $weightDateCondition = "DATE(ticket_weights.created_at) = :date";
    } elseif ($period === 'monthly') {
        $weightDateCondition = "MONTH(ticket_weights.created_at) = :month AND YEAR(ticket_weights.created_at) = :year";
    } else {
        $weightDateCondition = "YEAR(ticket_weights.created_at) = :year";
    }
    
    $weightQuery = "SELECT
        COALESCE(SUM(CASE WHEN tb.currency='USD' THEN ticket_weights.profit ELSE 0 END),0) AS usd,
        COALESCE(SUM(CASE WHEN tb.currency='AFS' THEN ticket_weights.profit ELSE 0 END),0) AS afs
    FROM ticket_weights
    LEFT JOIN ticket_bookings tb ON ticket_weights.ticket_id = tb.id
    WHERE ticket_weights.tenant_id = :tenant_id AND ticket_weights.branch_id = :branch_id AND $weightDateCondition";

    try {
        $stmt = $pdo->prepare($weightQuery);
        $executeParams = $params + [':tenant_id' => $tenant_id, ':branch_id' => $branch_id];
        $stmt->execute($executeParams);
        $weightProfit = $stmt->fetch(PDO::FETCH_ASSOC);

        $results[] = [
            'source' => 'Ticket Weights',
            'type' => 'weight_sale',
            'usd' => floatval($weightProfit['usd']),
            'afs' => floatval($weightProfit['afs']),
            'period' => $period
        ];
    } catch (PDOException $e) {
        $results[] = [
            'source' => 'Ticket Weights',
            'type' => 'weight_sale',
            'usd' => 0,
            'afs' => 0,
            'period' => $period
        ];
    }

    // Refunded Tickets
    if ($period === 'daily') {
        $refundDateCondition = "DATE(rt.created_at) = :date";
    } elseif ($period === 'monthly') {
        $refundDateCondition = "MONTH(rt.created_at) = :month AND YEAR(rt.created_at) = :year";
    } else {
        $refundDateCondition = "YEAR(rt.created_at) = :year";
    }
    
    $refundQuery = "SELECT
        COALESCE(SUM(CASE WHEN rt.currency='USD' THEN
            (CASE WHEN rt.calculation_method='base' THEN rt.service_penalty
                  WHEN rt.calculation_method='sold' THEN (rt.service_penalty - IFNULL(tb.profit,0))
                  ELSE rt.service_penalty END) ELSE 0 END),0) AS usd,
        COALESCE(SUM(CASE WHEN rt.currency='AFS' THEN
            (CASE WHEN rt.calculation_method='base' THEN rt.service_penalty
                  WHEN rt.calculation_method='sold' THEN (rt.service_penalty - IFNULL(tb.profit,0))
                  ELSE rt.service_penalty END) ELSE 0 END),0) AS afs
    FROM refunded_tickets rt
    LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
    WHERE rt.tenant_id = :tenant_id AND rt.branch_id = :branch_id AND $refundDateCondition";

    try {
        $stmt = $pdo->prepare($refundQuery);
        $executeParams = $params + [':tenant_id' => $tenant_id, ':branch_id' => $branch_id];
        $stmt->execute($executeParams);
        $refundProfit = $stmt->fetch(PDO::FETCH_ASSOC);

        $results[] = [
            'source' => 'Refunded Tickets',
            'type' => 'refund',
            'usd' => floatval($refundProfit['usd']),
            'afs' => floatval($refundProfit['afs']),
            'period' => $period
        ];
    } catch (PDOException $e) {
        $results[] = [
            'source' => 'Refunded Tickets',
            'type' => 'refund',
            'usd' => 0,
            'afs' => 0,
            'period' => $period
        ];
    }

    // Date Change Tickets
    if ($period === 'daily') {
        $dateChangeDateCondition = "DATE(dt.created_at) = :date";
    } elseif ($period === 'monthly') {
        $dateChangeDateCondition = "MONTH(dt.created_at) = :month AND YEAR(dt.created_at) = :year";
    } else {
        $dateChangeDateCondition = "YEAR(dt.created_at) = :year";
    }
    
    $dateChangeQuery = "SELECT
        COALESCE(SUM(CASE WHEN dt.currency='USD' THEN dt.service_penalty ELSE 0 END),0) AS usd,
        COALESCE(SUM(CASE WHEN dt.currency='AFS' THEN dt.service_penalty ELSE 0 END),0) AS afs
    FROM date_change_tickets dt
    LEFT JOIN ticket_bookings tb ON dt.ticket_id = tb.id
    WHERE dt.tenant_id = :tenant_id AND dt.branch_id = :branch_id AND $dateChangeDateCondition";

    try {
        $stmt = $pdo->prepare($dateChangeQuery);
        $executeParams = $params + [':tenant_id' => $tenant_id, ':branch_id' => $branch_id];
        $stmt->execute($executeParams);
        $dateChangeProfit = $stmt->fetch(PDO::FETCH_ASSOC);

        $results[] = [
            'source' => 'Date Changed Tickets',
            'type' => 'datechange',
            'usd' => floatval($dateChangeProfit['usd']),
            'afs' => floatval($dateChangeProfit['afs']),
            'period' => $period
        ];
    } catch (PDOException $e) {
        $results[] = [
            'source' => 'Date Changed Tickets',
            'type' => 'datechange',
            'usd' => 0,
            'afs' => 0,
            'period' => $period
        ];
    }

    // Return results as JSON
    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
    exit();
}
