<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();



// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Get JV payment ID from request
$jvId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($jvId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid JV payment ID']);
    exit();
}

try {
    // Different query based on payment type
    if ($type === 'client_supplier') {
        // For client-supplier payments, join with clients and suppliers tables
        $paymentQuery = "SELECT jp.*, 
                         u.name as created_by_name,
                         c.name as client_name,
                         s.name as supplier_name  
                         FROM jv_payments jp 
                         LEFT JOIN users u ON jp.created_by = u.id
                         LEFT JOIN clients c ON jp.client_id = c.id
                         LEFT JOIN suppliers s ON jp.supplier_id = s.id
                         WHERE jp.id = ? AND jp.tenant_id = ?";
    } else {
        // Standard query for regular JV payments
        $paymentQuery = "SELECT jp.*, u.name as created_by_name 
                         FROM jv_payments jp 
                         LEFT JOIN users u ON jp.created_by = u.id
                         WHERE jp.id = ? AND jp.tenant_id = ?";
    }
    
    $paymentStmt = $pdo->prepare($paymentQuery);
    $paymentStmt->execute([$jvId, $tenant_id]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'JV payment not found']);
        exit();
    }
    
    // Format the created_at date
    $payment['created_at'] = date('Y-m-d H:i', strtotime($payment['created_at']));
    
    // Get JV transactions
    $transactionsQuery = "SELECT jt.*, DATE_FORMAT(jt.created_at, '%Y-%m-%d %H:%i') as created_at
                          FROM jv_transactions jt
                          WHERE jt.jv_payment_id = ? AND jt.tenant_id = ?
                          ORDER BY jt.created_at DESC";
    $transactionsStmt = $pdo->prepare($transactionsQuery);
    $transactionsStmt->execute([$jvId, $tenant_id]);
    $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'payment' => $payment,
        'transactions' => $transactions
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
} 