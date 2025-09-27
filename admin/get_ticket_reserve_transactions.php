<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
include '../includes/conn.php';

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);

    try {
        // Prepare a query to fetch all transactions for the given ticket reservation ID
        $stmt = $conn->prepare("
            SELECT t.*
            FROM main_account_transactions t
            LEFT JOIN main_account m ON t.main_account_id = m.id
            LEFT JOIN ticket_reservations tb ON t.reference_id = tb.id
            WHERE t.reference_id = ? AND t.tenant_id = ?
            AND LOWER(t.type) = 'credit'
            AND t.transaction_of = 'ticket_reserve'
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param("ii", $ticket_id, $tenant_id);
        $stmt->execute();

        // Fetch all the results
        $result = $stmt->get_result();
        $transactions = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($transactions)) {
            echo json_encode([]);
        } else {
            echo json_encode($transactions);
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log("Error fetching ticket reservation transactions: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching transactions']);
    }
} else {
    echo json_encode(['error' => 'No ticket reservation ID provided']);
}
?>
