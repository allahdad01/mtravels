<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);

    try {
        // Prepare a query to fetch all transactions for the given ticket sale ID
        $stmt = $pdo->prepare("
            SELECT t.* 
            FROM main_account_transactions t
            LEFT JOIN main_account m ON t.main_account_id = m.id
            left join ticket_bookings tb on t.reference_id = tb.id
            WHERE t.reference_id = ? AND t.tenant_id = ?
            AND LOWER(t.type) = 'credit' 
            AND t.transaction_of = 'ticket_sale'
            ORDER BY t.created_at DESC
        ");
            $stmt->execute([$ticket_id, $tenant_id]);

        // Fetch all the results
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($transactions)) {
            echo json_encode([]);
        } else {
            echo json_encode($transactions);
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching ticket sale transactions: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching transactions']);
    }
} else {
    echo json_encode(['error' => 'No ticket sale ID provided']);
}
?>
