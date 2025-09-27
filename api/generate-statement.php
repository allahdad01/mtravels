<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
try {
    // Validate input
    $clientId = filter_input(INPUT_POST, 'clientId', FILTER_VALIDATE_INT);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_STRING);
    $dateRange = filter_input(INPUT_POST, 'dateRange', FILTER_SANITIZE_STRING);

    if (!$clientId || !$currency || !$dateRange) {
        throw new Exception('Missing required parameters');
    }

    // Parse date range
    $dates = explode(' - ', $dateRange);
    $startDate = date('Y-m-d', strtotime($dates[0]));
    $endDate = date('Y-m-d', strtotime($dates[1]));

    // Get opening balance before start date
    $openingBalanceQuery = "
        SELECT 
            COALESCE(SUM(CASE 
                WHEN transaction_type = 'debit' THEN amount 
                WHEN transaction_type = 'credit' THEN -amount 
                WHEN transaction_type = 'refund' THEN -amount 
            END), 0) as opening_balance
        FROM transactions 
        WHERE client_id = ? 
        AND currency = ?
        AND transaction_date < ?
        AND tenant_id = ?
    ";

    $stmt = $pdo->prepare($openingBalanceQuery);
    $stmt->execute([$clientId, $currency, $startDate, $tenant_id]);
    $openingBalance = $stmt->fetch(PDO::FETCH_ASSOC)['opening_balance'];

    // Get transactions for the date range
    $query = "
        SELECT 
            t.transaction_date as issue_date,
            t.passenger_name as p_name,
            t.sector,
            t.departure_date as dep_date,
            t.details,
            t.pnr,
            t.invoice_number as inv,
            CASE WHEN t.transaction_type = 'debit' THEN t.amount ELSE NULL END as debit,
            CASE WHEN t.transaction_type = 'credit' THEN t.amount ELSE NULL END as credit,
            CASE WHEN t.transaction_type = 'refund' THEN t.amount ELSE NULL END as refund,
            t.remarks,
            c.name as client_name
        FROM transactions t
        JOIN clients c ON t.client_id = c.id
        WHERE t.client_id = ?
        AND t.currency = ?
        AND t.transaction_date BETWEEN ? AND ?
        AND t.tenant_id = ?
        ORDER BY t.transaction_date ASC, t.id ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$clientId, $currency, $startDate, $endDate, $tenant_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate running balance
    $runningBalance = $openingBalance;
    $statementData = [];

    // Add opening balance row
    $statementData[] = [
        'issue_date' => $startDate,
        'p_name' => 'Opening Balance',
        'sector' => '',
        'dep_date' => '',
        'details' => '',
        'pnr' => '',
        'inv' => '',
        'debit' => $openingBalance > 0 ? abs($openingBalance) : null,
        'credit' => $openingBalance < 0 ? abs($openingBalance) : null,
        'refund' => null,
        'balance' => $runningBalance,
        'remarks' => 'Opening Balance'
    ];

    // Process transactions
    foreach ($transactions as $transaction) {
        $debit = floatval($transaction['debit'] ?? 0);
        $credit = floatval($transaction['credit'] ?? 0);
        $refund = floatval($transaction['refund'] ?? 0);

        $runningBalance += $debit - $credit - $refund;

        $transaction['balance'] = $runningBalance;
        $statementData[] = $transaction;
    }

    // Get client details
    $clientQuery = "SELECT * FROM clients WHERE id = ? and tenant_id = ?";
    $stmt = $pdo->prepare($clientQuery);
    $stmt->execute([$clientId, $tenant_id]);
    $clientDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get company settings
    $settingsQuery = "SELECT * FROM settings WHERE tenant_id = ?";
    $stmt = $pdo->prepare($settingsQuery);
    $stmt->execute([$tenant_id]);
    $companySettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'statement' => $statementData,
            'client' => $clientDetails,
            'company' => $companySettings,
            'summary' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'openingBalance' => $openingBalance,
                'closingBalance' => $runningBalance,
                'currency' => $currency
            ]
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 