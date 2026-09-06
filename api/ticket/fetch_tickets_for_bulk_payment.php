<?php
// Fetch tickets with paid amounts for bulk payment
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../admin/security.php';
enforce_auth();
require_permission('tickets.view');

require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

try {
    // Fetch tickets for agency clients that are not fully paid
    if ($client_id > 0) {
        $stmt = $pdo->prepare("
            SELECT tb.id, tb.pnr, tb.passenger_name, tb.title, tb.origin, tb.destination,
                   tb.airline, tb.sold, tb.currency, tb.status, tb.departure_date, tb.issue_date,
                   tb.sold_to, tb.trip_type, tb.return_destination,
                   c.name as client_name
            FROM ticket_bookings tb
            JOIN clients c ON tb.sold_to = c.id
            WHERE tb.sold_to = ?
            AND c.client_type = 'agency'
            AND tb.tenant_id = ? AND tb.branch_id = ?
            AND tb.status IN ('Booked', 'Date Changed')
            ORDER BY tb.id DESC
        ");
        $stmt->execute([$client_id, $tenant_id, $branch_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT tb.id, tb.pnr, tb.passenger_name, tb.title, tb.origin, tb.destination,
                   tb.airline, tb.sold, tb.currency, tb.status, tb.departure_date, tb.issue_date,
                   tb.sold_to, tb.trip_type, tb.return_destination,
                   c.name as client_name
            FROM ticket_bookings tb
            JOIN clients c ON tb.sold_to = c.id
            WHERE c.client_type = 'agency'
            AND tb.tenant_id = ? AND tb.branch_id = ?
            AND tb.status IN ('Booked', 'Date Changed')
            ORDER BY tb.id DESC
        ");
        $stmt->execute([$tenant_id, $branch_id]);
    }
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each ticket, calculate total paid amount with currency conversion
    $result = [];
    foreach ($tickets as $ticket) {
        // Fetch all credit transactions for this ticket
        $transStmt = $pdo->prepare("
            SELECT amount, currency, exchange_rate
            FROM main_account_transactions
            WHERE reference_id = ?
            AND transaction_of = 'ticket_sale'
            AND type = 'credit'
            AND tenant_id = ? AND branch_id = ?
        ");
        $transStmt->execute([$ticket['id'], $tenant_id, $branch_id]);
        $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert each transaction to ticket's base currency and sum
        $total_paid_converted = 0;
        foreach ($transactions as $tx) {
            $txAmount = floatval($tx['amount']);
            $txCurrency = $tx['currency'];
            $txRate = isset($tx['exchange_rate']) && floatval($tx['exchange_rate']) > 0
                ? floatval($tx['exchange_rate']) : 1.0;

            if ($txCurrency === $ticket['currency']) {
                $total_paid_converted += $txAmount;
            } else {
                if ($ticket['currency'] === 'AFS') {
                    $total_paid_converted += $txAmount * $txRate;
                } else {
                    $total_paid_converted += $txAmount / $txRate;
                }
            }
        }

        $sold = floatval($ticket['sold']);
        $outstanding_converted = max(0, $sold - $total_paid_converted);

        // Only include tickets that have outstanding balance
        if ($outstanding_converted > 0.01) {
            $result[] = [
                'id' => $ticket['id'],
                'pnr' => $ticket['pnr'],
                'passenger_name' => $ticket['passenger_name'],
                'title' => $ticket['title'],
                'origin' => $ticket['origin'],
                'destination' => $ticket['destination'],
                'airline' => $ticket['airline'],
                'sold' => $ticket['sold'],
                'currency' => $ticket['currency'],
                'status' => $ticket['status'],
                'departure_date' => $ticket['departure_date'],
                'issue_date' => $ticket['issue_date'],
                'trip_type' => $ticket['trip_type'],
                'return_destination' => $ticket['return_destination'],
                'total_paid' => round($total_paid_converted, 3),
                'outstanding' => round($outstanding_converted, 3),
                'client_name' => $ticket['client_name']
            ];
        }
    }

    echo json_encode(['status' => 'success', 'tickets' => $result]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
