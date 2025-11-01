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
        // First, get the ticket details
        $ticketStmt = $pdo->prepare("
            SELECT dct.id, dct.ticket_id, dct.departure_date, dct.supplier_penalty, 
                   dct.service_penalty, dct.status, t.exchange_rate,
                   tb.passenger_name, tb.pnr, t.currency
            FROM date_change_tickets dct
            LEFT JOIN ticket_bookings tb ON dct.ticket_id = tb.id
            left join main_account_transactions t  on dct.id = t.reference_id 
            WHERE dct.id = ? AND dct.tenant_id = ?
        ");
        $ticketStmt->execute([$ticket_id, $tenant_id]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        // Check if ticket exists
        if (!$ticket) {
            echo json_encode([
                'success' => false,
                'message' => 'Date change ticket not found',
                'transactions' => []
            ]);
            exit;
        }

        // Prepare a query to fetch all transactions for the given ticket ID
        $stmt = $pdo->prepare("
            SELECT t.id, t.amount, t.type, t.description, t.created_at as transaction_date,
                   t.balance, t.main_account_id, t.reference_id, t.currency, t.exchange_rate
            FROM main_account_transactions t
            LEFT JOIN main_account m ON t.main_account_id = m.id
            WHERE t.reference_id = ? AND t.tenant_id = ?
            AND t.transaction_of = 'date_change'
            ORDER BY t.created_at ASC, t.id ASC
        ");
        $stmt->execute([$ticket_id, $tenant_id]);

        // Fetch all the results
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format transactions for the UI
        $formattedTransactions = [];
        foreach ($transactions as $tx) {
            $formattedTransactions[] = [
                'id' => $tx['id'],
                'amount' => $tx['amount'],
                'type' => $tx['type'], // 'credit' or 'debit'
                'description' => $tx['description'],
                'transaction_date' => $tx['transaction_date'],
                'balance' => $tx['balance'],
                'currency' => $tx['currency'],
                'exchange_rate' => $tx['exchange_rate'],
                'reference_id' => $tx['reference_id']
            ];
        }

        // Calculate total paid amount
        $totalPaid = 0;
        foreach ($formattedTransactions as $tx) {
            if ($tx['type'] === 'credit') {
                $totalPaid += floatval($tx['amount']);
            } else {
                $totalPaid -= floatval($tx['amount']);
            }
        }

        // Calculate total amount (supplier penalty + service penalty)
        $totalAmount = floatval($ticket['supplier_penalty']) + floatval($ticket['service_penalty']);

        echo json_encode([
            'success' => true,
            'ticket' => [
                'id' => $ticket['id'],
                'passenger_name' => $ticket['passenger_name'],
                'pnr' => $ticket['pnr'],
                'departure_date' => $ticket['departure_date'],
                'currency' => $ticket['currency'],
                'sold' => $totalAmount,
                'paid' => $totalPaid,
                'remaining' => $totalAmount - $totalPaid
            ],
            'transactions' => $formattedTransactions
        ]);
        
    } catch (PDOException $e) {
        error_log("Error fetching date change ticket transactions: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching transactions: ' . $e->getMessage(),
            'transactions' => []
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ticket ID provided',
        'transactions' => []
    ]);
}
?>
