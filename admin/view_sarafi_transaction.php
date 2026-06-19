<?php
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$transaction_id) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid transaction ID']));
}

try {
    // Fetch transaction details
    $stmt = $pdo->prepare("
        SELECT * FROM sarafi_transactions 
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Transaction not found']));
    }

    // Fetch customer details
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email FROM customers 
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->bindParam(1, $transaction['customer_id'], PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Customer not found']));
    }

    // Get customer wallet balance
    $stmt = $pdo->prepare("
        SELECT balance FROM customer_wallets 
        WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->bindParam(1, $customer['id'], PDO::PARAM_INT);
    $stmt->bindParam(2, $transaction['currency'], PDO::PARAM_STR);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    $customer['wallet_balance'] = $wallet ? $wallet['balance'] : 0;

    // Fetch main account details
    $main_account = null;
    $tx_type_map = ['deposit' => 'deposit_sarafi', 'withdrawal' => 'withdrawal_sarafi', 'hawala_send' => 'hawala_sarafi'];
    $tx_of = $tx_type_map[$transaction['type']] ?? null;
    if ($tx_of) {
        $stmt = $pdo->prepare("
            SELECT ma.id, ma.name FROM main_account_transactions mat
            JOIN main_account ma ON mat.main_account_id = ma.id
            WHERE mat.reference_id = ? AND mat.transaction_of = ? AND mat.tenant_id = ? AND mat.branch_id = ?
            LIMIT 1
        ");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tx_of, PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $main_account = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch hawala details if it's a hawala transfer
    $hawala = null;
    if ($transaction['type'] === 'hawala_send') {
        $stmt = $pdo->prepare("
            SELECT ht.*, c.name as receiver_name, c.phone as receiver_phone
            FROM hawala_transfers ht
            LEFT JOIN sarafi_transactions st ON st.id = ht.receiver_transaction_id
            LEFT JOIN customers c ON c.id = st.customer_id
            WHERE ht.sender_transaction_id = ? AND ht.tenant_id = ? AND ht.branch_id = ?
        ");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $hawala_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hawala_record) {
            $hawala = [
                'commission_amount' => $hawala_record['commission_amount'],
                'commission_currency' => $hawala_record['commission_currency'],
                'secret_code' => $hawala_record['secret_code'],
                'receiver' => [
                    'name' => $hawala_record['receiver_name'] ?? 'N/A',
                    'phone' => $hawala_record['receiver_phone'] ?? 'N/A'
                ]
            ];
        }
    }

    // Fetch exchange details if it's an exchange
    $exchange = null;
    if ($transaction['type'] === 'exchange') {
        $stmt = $pdo->prepare("
            SELECT * FROM exchange_transactions
            WHERE transaction_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $exchange = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction' => $transaction,
            'customer' => $customer,
            'main_account' => $main_account,
            'hawala' => $hawala,
            'exchange' => $exchange
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching transaction: ' . $e->getMessage()
    ]);
}
?>
