<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

if (isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);

    try {
        // First, get the booking details
        $bookingStmt = $pdo->prepare("
            SELECT hb.id, hb.sold_amount, 
                   CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) as guest_name,
                   hb.order_id
            FROM hotel_bookings hb
            WHERE hb.id = ? AND hb.tenant_id = ?
        ");
        $bookingStmt->execute([$booking_id, $tenant_id]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

        // Prepare a query to fetch all transactions for the given hotel booking ID
        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.amount,
                t.description,
                t.created_at as transaction_date,
                CASE
                    WHEN LOWER(t.type) = 'credit' THEN 'payment'
                    WHEN LOWER(t.type) = 'debit' THEN 'receipt'
                    ELSE t.type
                END as type,
                t.currency,
                t.exchange_rate
            FROM main_account_transactions t
            LEFT JOIN main_account m ON t.main_account_id = m.id
            LEFT JOIN hotel_bookings hb ON t.reference_id = hb.id
            WHERE t.reference_id = ? AND t.tenant_id = ?
            AND t.transaction_of = 'hotel'
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$booking_id, $tenant_id]);

        // Fetch all the results
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return both booking and transactions data
        echo json_encode([
            'success' => true,
            'bookings' => [$booking],
            'transactions' => $transactions
        ]);
        
    } catch (PDOException $e) {
        error_log("Error fetching hotel transactions: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching transactions: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No hotel booking ID provided'
    ]);
}
?>
