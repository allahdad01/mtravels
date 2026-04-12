<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and security
require_once '../../includes/db.php';
require_once '../../admin/includes/db_security.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get weight ID from request
$weightId = isset($_GET['weight_id']) ? DbSecurity::validateInput($_GET['weight_id'], 'int', ['min' => 0]) : 0;

if ($weightId <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid weight ID'
    ]));
}

try {
    // First get the weight details
    $weightQuery = "
        SELECT
            tw.*,
            t.passenger_name,
            t.pnr,
            t.airline,
            t.origin,
            t.destination,
            t.departure_date,
            t.currency,
            t.paid_to,
            s.name AS supplier_name,
            c.name AS sold_to_name,
            ma.name AS paid_to_name
        FROM
            ticket_weights tw
        LEFT JOIN
            ticket_bookings t ON tw.ticket_id = t.id AND t.tenant_id = ? AND t.branch_id = ?
        LEFT JOIN
            suppliers s ON t.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN
            clients c ON t.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        LEFT JOIN
            main_account ma ON t.paid_to = ma.id AND ma.tenant_id = ? AND ma.branch_id = ?
        WHERE
            tw.id = ? AND tw.tenant_id = ? AND tw.branch_id = ?
    ";

    $weightStmt = $pdo->prepare($weightQuery);
    $weightStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $weightStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $weightStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $weightStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $weightStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $weightStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $weightStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $weightStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $weightStmt->bindParam(9, $weightId, PDO::PARAM_INT);
    $weightStmt->bindParam(10, $tenant_id, PDO::PARAM_INT);
    $weightStmt->bindParam(11, $branch_id, PDO::PARAM_INT);
    $weightStmt->execute();
    $weight = $weightStmt->fetch(PDO::FETCH_ASSOC);

    if (!$weight) {
        throw new PDOException('Weight not found');
    }

    // Get transactions from main_account_transactions
    $transactionQuery = "
        SELECT
            mat.*,
            ma.name AS account_name
        FROM
            main_account_transactions mat
        LEFT JOIN
            main_account ma ON mat.main_account_id = ma.id AND ma.tenant_id = ? AND ma.branch_id = ?
        WHERE
            mat.reference_id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
            AND LOWER(mat.type) = 'credit'
            AND mat.transaction_of = 'weight'
        ORDER BY
            mat.created_at DESC
    ";

    $transactionStmt = $pdo->prepare($transactionQuery);
    $transactionStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(3, $weightId, PDO::PARAM_INT);
    $transactionStmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $transactionStmt->execute();
    $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'weight' => $weight,
        'transactions' => $transactions
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>