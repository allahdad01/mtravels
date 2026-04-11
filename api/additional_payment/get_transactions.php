<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


require_once __DIR__ . '/../../includes/db.php';


// Get additional payment ID from request
$paymentId = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

if (!$paymentId) {
    echo json_encode(['success' => false, 'message' => 'Invalid additional payment ID']);
    exit;
}

try {
    // Get transactions for the additional payment
    $stmt = $pdo->prepare("
        SELECT id, amount, currency, description, created_at, receipt, exchange_rate
        FROM main_account_transactions
        WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? AND branch_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$paymentId, $tenant_id, $branch_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($transactions);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>