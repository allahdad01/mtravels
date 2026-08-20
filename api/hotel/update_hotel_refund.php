<?php
require_once('../../includes/db.php');
require_once('../../admin/security.php');
enforce_auth();
// Editing refund records is admin/manager & finance only.
$editRoles = ['admin', 'finance', 'tenant_super_admin', 'super_admin'];
if (!in_array($_SESSION['role'] ?? '', $editRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$refund_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$supplier_penalty = isset($_POST['supplier_penalty']) ? floatval($_POST['supplier_penalty']) : 0;
$service_penalty = isset($_POST['service_penalty']) ? floatval($_POST['service_penalty']) : 0;
$refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (!$refund_id) {
    echo json_encode(['success' => false, 'message' => 'Refund ID is required']);
    exit;
}

if ($refund_amount < 0) {
    echo json_encode(['success' => false, 'message' => 'Refund amount cannot be negative']);
    exit;
}

$sql_where = "AND tenant_id = ? AND branch_id = ?";

try {
    $pdo->beginTransaction();

    // ---------------------------------------------------------------
    // 1) Load the existing refund record (source of truth for old values)
    // ---------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT * FROM hotel_refunds WHERE id = ? $sql_where");
    $stmt->execute([$refund_id, $tenant_id, $branch_id]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Refund record not found']);
        exit;
    }

    $booking_id = $original['booking_id'];
    $currency = $original['currency'];
    $base     = floatval($original['base']);
    $sold     = floatval($original['sold']);

    $old_supplier_penalty = floatval($original['supplier_penalty']);
    $old_service_penalty  = floatval($original['service_penalty']);
    $old_refund_to_supplier  = $base - $old_supplier_penalty;
    $old_refund_to_customer  = $sold - ($old_supplier_penalty + $old_service_penalty);

    $new_refund_to_supplier  = $base - $supplier_penalty;
    $new_refund_to_customer  = $sold - ($supplier_penalty + $service_penalty);

    if ($new_refund_to_customer < 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Refund amount cannot be negative.']);
        exit;
    }

    $supplier_delta = $new_refund_to_supplier - $old_refund_to_supplier;
    $customer_delta = $new_refund_to_customer - $old_refund_to_customer;

    // ---------------------------------------------------------------
    // 2) Sync the supplier ledger when the supplier refund changed
    //    (hotel has a single supplier_transactions row per refund)
    // ---------------------------------------------------------------
    if (abs($supplier_delta) >= 0.001) {
        $stmtTx = $pdo->prepare("SELECT id, amount, supplier_id FROM supplier_transactions
                                 WHERE reference_id = ? AND transaction_of = 'hotel_refund' $sql_where
                                 ORDER BY id ASC LIMIT 1");
        $stmtTx->execute([$refund_id, $tenant_id, $branch_id]);
        $supplierTx = $stmtTx->fetch(PDO::FETCH_ASSOC);

        if ($supplierTx) {
            $oldAmt = floatval($supplierTx['amount']);
            $newAmt = round($new_refund_to_supplier, 3);
            $txDelta = $newAmt - $oldAmt;

            if (abs($txDelta) >= 0.001) {
                // Only External suppliers held a live balance entry.
                $stmtType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? $sql_where");
                $stmtType->execute([$supplierTx['supplier_id'], $tenant_id, $branch_id]);
                $typeRow = $stmtType->fetch(PDO::FETCH_ASSOC);

                if ($typeRow && $typeRow['supplier_type'] === 'External') {
                    $updBalance = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? $sql_where");
                    $updBalance->execute([$txDelta, $supplierTx['supplier_id'], $tenant_id, $branch_id]);

                    // The refund's own transaction stays in the chain, so its
                    // stored balance must shift by the same delta as the
                    // subsequent transactions.
                    $updOwn = $pdo->prepare("UPDATE supplier_transactions
                                             SET balance = balance + ?
                                             WHERE id = ? $sql_where");
                    $updOwn->execute([$txDelta, $supplierTx['id'], $tenant_id, $branch_id]);

                    $updSub = $pdo->prepare("UPDATE supplier_transactions
                                             SET balance = balance + ?
                                             WHERE supplier_id = ? AND id > ? $sql_where");
                    $updSub->execute([$txDelta, $supplierTx['supplier_id'], $supplierTx['id'], $tenant_id, $branch_id]);
                }

                $updAmt = $pdo->prepare("UPDATE supplier_transactions SET amount = ? WHERE id = ? $sql_where");
                $updAmt->execute([$newAmt, $supplierTx['id'], $tenant_id, $branch_id]);
            }
        }
    }

    // ---------------------------------------------------------------
    // 3) Sync the client credit when the customer refund changed
    // ---------------------------------------------------------------
    if ($booking_id) {
        $stmtBook = $pdo->prepare("SELECT hb.sold_to, c.client_type
                                   FROM hotel_bookings hb
                                   LEFT JOIN clients c ON hb.sold_to = c.id
                                   WHERE hb.id = ? $sql_where");
        $stmtBook->execute([$booking_id, $tenant_id, $branch_id]);
        $booking = $stmtBook->fetch(PDO::FETCH_ASSOC);

        if ($booking && $booking['sold_to']) {
            $stmtCT = $pdo->prepare("SELECT id, amount, currency FROM client_transactions
                                     WHERE client_id = ? AND transaction_of = 'hotel_refund'
                                     AND reference_id = ? $sql_where ORDER BY id ASC LIMIT 1");
            $stmtCT->execute([$booking['sold_to'], $refund_id, $tenant_id, $branch_id]);
            $clientTx = $stmtCT->fetch(PDO::FETCH_ASSOC);

            if ($clientTx) {
                if (abs($customer_delta) >= 0.001) {
                    if (strtolower((string)$booking['client_type']) === 'regular') {
                        $balanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                        $updClient = $pdo->prepare("UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? $sql_where");
                        $updClient->execute([$customer_delta, $booking['sold_to'], $tenant_id, $branch_id]);

                        // The refund's own transaction stays in the chain, so its
                        // stored balance must shift by the same delta as the
                        // subsequent transactions.
                        $updOwn = $pdo->prepare("UPDATE client_transactions
                                                 SET balance = balance + ?
                                                 WHERE id = ? $sql_where");
                        $updOwn->execute([$customer_delta, $clientTx['id'], $tenant_id, $branch_id]);

                        $updSub = $pdo->prepare("UPDATE client_transactions
                                                 SET balance = balance + ?
                                                 WHERE client_id = ? AND id > ? AND currency = ? $sql_where");
                        $updSub->execute([$customer_delta, $booking['sold_to'], $clientTx['id'],
                                          $clientTx['currency'], $tenant_id, $branch_id]);
                    }

                    $updAmt = $pdo->prepare("UPDATE client_transactions SET amount = ? WHERE id = ? $sql_where");
                    $updAmt->execute([$new_refund_to_customer, $clientTx['id'], $tenant_id, $branch_id]);
                }
            }
        }

        // Hotel booking profit mirrors process_hotel_refund: profit = service penalty
        $updBook = $pdo->prepare("UPDATE hotel_bookings SET profit = ? WHERE id = ? $sql_where");
        $updBook->execute([$service_penalty, $booking_id, $tenant_id, $branch_id]);
    }

    // ---------------------------------------------------------------
    // 4) Update the refund record itself (recomputed amount keeps the
    //    stored value consistent with the synced ledgers)
    // ---------------------------------------------------------------
    $stmt = $pdo->prepare("UPDATE hotel_refunds SET
        supplier_penalty = ?,
        service_penalty = ?,
        refund_amount = ?,
        reason = ?
        WHERE id = ? $sql_where");
    $stmt->execute([$supplier_penalty, $service_penalty, $new_refund_to_customer, $reason, $refund_id, $tenant_id, $branch_id]);

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_values = [
        'supplier_penalty' => $original['supplier_penalty'],
        'service_penalty' => $original['service_penalty'],
        'refund_amount' => $original['refund_amount'],
        'reason' => $original['reason']
    ];

    $new_values = [
        'supplier_penalty' => $supplier_penalty,
        'service_penalty' => $service_penalty,
        'refund_amount' => $new_refund_to_customer,
        'reason' => $reason
    ];

    $logStmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id)
        VALUES (?, 'update', 'hotel_refunds', ?, ?, ?, ?, ?, ?)");
    $logStmt->execute([
        $user_id,
        $refund_id,
        json_encode($old_values),
        json_encode($new_values),
        $ip_address,
        $user_agent,
        $tenant_id
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Refund updated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error updating refund: ' . $e->getMessage()]);
}