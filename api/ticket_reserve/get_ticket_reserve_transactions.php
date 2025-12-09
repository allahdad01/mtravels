<?php
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);

    try {
        // Prepare a query to fetch all transactions for the given ticket reservation ID
        $stmt = $pdo->prepare("
            SELECT t.*
            FROM main_account_transactions t
            LEFT JOIN main_account m ON t.main_account_id = m.id
            LEFT JOIN ticket_reservations tb ON t.reference_id = tb.id
            WHERE t.reference_id = ? AND t.tenant_id = ? AND t.branch_id = ?
            AND LOWER(t.type) = 'credit'
            AND t.transaction_of = 'ticket_reserve'
            ORDER BY t.created_at DESC
        ");
        $stmt->bindParam(1, $ticket_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch all the results
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($transactions)) {
            echo json_encode([]);
        } else {
            echo json_encode($transactions);
        }

    } catch (PDOException $e) {
        error_log("Error fetching ticket reservation transactions: " . $e->getMessage());
        echo json_encode(['error' => 'Error fetching transactions']);
    }
} else {
    echo json_encode(['error' => 'No ticket reservation ID provided']);
}
?>
