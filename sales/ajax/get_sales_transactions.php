<?php
// Include database security module for input validation
require_once '../includes/db_security.php';

// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/conn.php';

$tenant_id = $_SESSION['tenant_id'];

// Check if period is set
if (!isset($_POST['period']) || empty($_POST['period'])) {
    echo json_encode(['status' => 'error', 'message' => 'Period is required']);

// Validate period
$period = isset($_POST['period']) ? DbSecurity::validateInput($_POST['period'], 'string', ['maxlength' => 255]) : null;
    exit;
}

$period = $_POST['period'];
$validPeriods = ['daily', 'monthly', 'yearly'];

if (!in_array($period, $validPeriods)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid period']);
    exit;
}

try {
    $transactions = [];
    
    // Get start and end dates based on period
    $startDate = '';
    $endDate = '';
    
    if ($period == 'daily') {
        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59');
    } elseif ($period == 'monthly') {
        $startDate = date('Y-m-01 00:00:00');
        $endDate = date('Y-m-t 23:59:59');
    } elseif ($period == 'yearly') {
        $startDate = date('Y-01-01 00:00:00');
        $endDate = date('Y-12-31 23:59:59');
    }
    
    // Get transactions from different tables based on period
    
    // 1. Get ticket transactions
    $ticketQuery = "SELECT 
                        'Ticket' as type,
                        CONCAT(t.airline, ' ', t.flight_no, ' (PNR: ', t.pnr, ')') as name,
                        t.profit_usd as usd_profit,
                        t.profit_afs as afs_profit
                    FROM tickets t
                    WHERE t.created_at BETWEEN :startDate AND :endDate AND t.tenant_id = ?
                    ORDER BY t.profit_usd DESC";
    
    $ticketStmt = $pdo->prepare($ticketQuery);
    $ticketStmt->bindParam(':startDate', $startDate);
    $ticketStmt->bindParam(':endDate', $endDate);
    $ticketStmt->execute([$tenant_id]);
    
    while ($row = $ticketStmt->fetch(PDO::FETCH_ASSOC)) {
        $transactions[] = $row;
    }
    
    // 2. Get visa transactions
    $visaQuery = "SELECT 
                    'Visa' as type,
                    CONCAT(v.visa_type, ' for ', v.customer_name, ' (Passport: ', v.passport_number, ')') as name,
                    v.profit_usd as usd_profit,
                    v.profit_afs as afs_profit
                FROM visas v
                WHERE v.created_at BETWEEN :startDate AND :endDate AND v.tenant_id = ?
                ORDER BY v.profit_usd DESC";
    
    $visaStmt = $pdo->prepare($visaQuery);
    $visaStmt->bindParam(':startDate', $startDate);
    $visaStmt->bindParam(':endDate', $endDate);
    $visaStmt->execute([$tenant_id]);
    
    while ($row = $visaStmt->fetch(PDO::FETCH_ASSOC)) {
        $transactions[] = $row;
    }
    
    // 3. Get hotel transactions
    $hotelQuery = "SELECT 
                    'Hotel' as type,
                    CONCAT(h.hotel_name, ' - ', h.room_type, ' (Booking ID: ', h.booking_id, ')') as name,
                    h.profit_usd as usd_profit,
                    h.profit_afs as afs_profit
                FROM hotel_bookings h
                WHERE h.created_at BETWEEN :startDate AND :endDate AND h.tenant_id = ?
                ORDER BY h.profit_usd DESC";
    
    $hotelStmt = $pdo->prepare($hotelQuery);
    $hotelStmt->bindParam(':startDate', $startDate);
    $hotelStmt->bindParam(':endDate', $endDate);
    $hotelStmt->execute([$tenant_id]);
    
    while ($row = $hotelStmt->fetch(PDO::FETCH_ASSOC)) {
        $transactions[] = $row;
    }
    
    // Sort all transactions by profit (highest first)
    usort($transactions, function($a, $b) {
        return (float)$b['usd_profit'] - (float)$a['usd_profit'];
    });
    
    // Format numbers for display
    foreach ($transactions as &$transaction) {
        $transaction['usd_profit'] = number_format((float)$transaction['usd_profit'], 2);
        $transaction['afs_profit'] = number_format((float)$transaction['afs_profit'], 2);
    }
    
    echo json_encode(['status' => 'success', 'data' => $transactions]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?> 