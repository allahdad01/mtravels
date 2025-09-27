<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate transaction_id
$transaction_id = isset($_GET['id']) ? DbSecurity::validateInput($_GET['id'], 'int', ['min' => 0]) : null;

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

try {
    // Get transaction details with customer and main account information
    $stmt = $pdo->prepare("
        SELECT 
            st.*,
            c.name as customer_name,
            c.phone as customer_phone,
            ma.name as main_account_name,
            mat.amount as main_transaction_amount,
            mat.balance as main_transaction_balance,
            mat.created_at as main_transaction_date
        FROM sarafi_transactions st
        JOIN customers c ON st.customer_id = c.id
        JOIN main_account_transactions mat ON st.id = mat.reference_id
        JOIN main_account ma ON mat.main_account_id = ma.id
        WHERE st.id = ? AND st.tenant_id = ?
    ");
    $stmt->execute([$transaction_id, $tenant_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }

    // Get customer wallet balance at the time of transaction
    $stmt = $pdo->prepare("
        SELECT balance 
        FROM customer_wallets 
        WHERE customer_id = ? AND currency = ? AND tenant_id = ?
    ");
    $stmt->execute([$transaction['customer_id'], $transaction['currency'], $tenant_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format response data
    $response = [
        'success' => true,
        'data' => [
            'transaction' => [
                'id' => $transaction['id'],
                'type' => $transaction['type'],
                'amount' => $transaction['amount'],
                'currency' => $transaction['currency'],
                'reference_number' => $transaction['reference_number'],
                'notes' => $transaction['notes'],
                'status' => $transaction['status'],
                'created_at' => $transaction['created_at'],
                'receipt_path' => $transaction['receipt_path']
            ],
            'customer' => [
                'name' => $transaction['customer_name'],
                'phone' => $transaction['customer_phone'],
                'wallet_balance' => $wallet ? $wallet['balance'] : 0
            ],
            'main_account' => [
                'name' => $transaction['main_account_name'],
                'transaction_amount' => $transaction['main_transaction_amount'],
                'balance_after' => $transaction['main_transaction_balance'],
                'transaction_date' => $transaction['main_transaction_date']
            ]
        ]
    ];

    // If it's a hawala transaction, add hawala-specific details
    if ($transaction['type'] === 'hawala_sarafi') {
        $stmt = $pdo->prepare("
            SELECT 
                h.*,
                rc.name as receiver_name,
                rc.phone as receiver_phone
            FROM hawala_transfers h
            LEFT JOIN customers rc ON h.receiver_id = rc.id
            WHERE h.sarafi_transaction_id = ? AND h.tenant_id = ?
        ");
        $stmt->execute([$transaction_id, $tenant_id]);
        $hawala = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($hawala) {
            $response['data']['hawala'] = [
                'commission_amount' => $hawala['commission_amount'],
                'commission_currency' => $hawala['commission_currency'],
                'secret_code' => $hawala['secret_code'],
                'status' => $hawala['status'],
                'receiver' => [
                    'name' => $hawala['receiver_name'],
                    'phone' => $hawala['receiver_phone']
                ]
            ];
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error viewing transaction: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error viewing transaction details']);
}