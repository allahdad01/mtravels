<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/conn.php';

// Check if ticket_id is provided
if (!isset($_GET['ticket_id'])) {
    echo '<div class="alert alert-danger">Ticket ID is required.</div>';
    exit;
}

$ticketId = intval($_GET['ticket_id']);

try {
    // Get ticket details
    $ticketQuery = "SELECT t.*, c.name as client_name 
                   FROM date_change_tickets t
                   LEFT JOIN clients c ON t.sold_to = c.id
                   WHERE t.id = ?";
    $stmt = $conn->prepare($ticketQuery);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticketResult = $stmt->get_result();
    $ticket = $ticketResult->fetch_assoc();

    if (!$ticket) {
        echo '<div class="alert alert-danger">Ticket not found.</div>';
        exit;
    }

    // Get transactions
    $transQuery = "SELECT t.*, m.name as account_name 
                  FROM main_account_transactions t
                  LEFT JOIN main_account m ON t.main_account_id = m.id
                  WHERE t.transaction_of = 'date_change' 
                  AND t.reference_id = ?
                  ORDER BY t.transaction_date DESC";
    $stmt = $conn->prepare($transQuery);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $transResult = $stmt->get_result();
    
    // Calculate totals
    $totalAmount = $ticket['supplier_penalty'] + $ticket['service_penalty'];
    $totalPaid = 0;
    $transactions = [];
    while ($row = $transResult->fetch_assoc()) {
        $transactions[] = $row;
        if ($row['currency'] === 'USD') {
            $totalPaid += $row['amount'] * $ticket['exchange_rate'];
        } else {
            $totalPaid += $row['amount'];
        }
    }
    
    // Display ticket details and transactions
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title">Ticket Details</h6>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Passenger:</strong> <?= htmlspecialchars($ticket['passenger_name']) ?></p>
                    <p class="mb-1"><strong>PNR:</strong> <?= htmlspecialchars($ticket['pnr']) ?></p>
                    <p class="mb-1"><strong>Client:</strong> <?= htmlspecialchars($ticket['client_name']) ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Total Amount:</strong> <?= number_format($totalAmount, 2) ?> <?= htmlspecialchars($ticket['currency']) ?></p>
                    <p class="mb-1"><strong>Total Paid:</strong> <?= number_format($totalPaid, 2) ?> <?= htmlspecialchars($ticket['currency']) ?></p>
                    <p class="mb-1"><strong>Balance:</strong> <?= number_format($totalAmount - $totalPaid, 2) ?> <?= htmlspecialchars($ticket['currency']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="card-title mb-0">Transactions</h6>
                <button type="button" class="btn btn-sm btn-primary" onclick="addTransaction(<?= $ticketId ?>)">
                    <i class="feather icon-plus mr-1"></i>Add Transaction
                </button>
            </div>

            <?php if (empty($transactions)): ?>
                <div class="alert alert-info">No transactions found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Amount</th>
                                <th>Currency</th>
                                <th>Description</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $trans): ?>
                                <tr>
                                    <td><?= date('Y-m-d', strtotime($trans['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars($trans['account_name']) ?></td>
                                    <td><?= number_format($trans['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($trans['currency']) ?></td>
                                    <td><?= htmlspecialchars($trans['description']) ?></td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteTransaction(<?= $trans['id'] ?>)">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading transactions: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log("Error in get_date_change_transactions.php: " . $e->getMessage());
} 