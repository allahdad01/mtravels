<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    // Get the date change request details
    $stmt = $pdo->prepare("
        SELECT dc.*, ub.price as current_price, ub.sold_price, ub.profit, ub.due, ubs.supplier_id as supplier, ub.sold_to, ub.currency, ub.family_id,
               s.name as supplier_name, s.balance as supplier_balance, s.supplier_type,
               c.name as client_name, c.usd_balance, c.afs_balance, c.client_type
        FROM date_change_umrah dc
        LEFT JOIN umrah_bookings ub ON dc.umrah_booking_id = ub.booking_id AND ub.tenant_id = ? AND ub.branch_id = ?
        LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND ubs.service_type IN ('all', 'ticket') AND ubs.tenant_id = ? AND ubs.branch_id = ?
        LEFT JOIN suppliers s ON ubs.supplier_id = s.id AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN clients c ON ub.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        WHERE dc.id = ? AND dc.tenant_id = ? AND dc.branch_id = ?
    ");
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(9, $id, PDO::PARAM_INT);
    $stmt->bindParam(10, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(11, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Date change request not found']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // If the request was completed, reverse the changes
        if ($request['status'] === 'Completed') {
            // Reverse booking price changes
            $reversed_price = $request['current_price'] - $request['supplier_penalty'];
            $reversed_sold_price = $request['sold_price'] - $request['total_penalty'];
            $reversed_profit = $reversed_sold_price - $reversed_price;
            $new_due = $request['due'] - $request['total_penalty'];

            $updateBookingSql = "
                UPDATE umrah_bookings
                SET price = ?, sold_price = ?, profit = ?, due = ?
                WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
            ";

            $stmt = $pdo->prepare($updateBookingSql);
            $stmt->bindParam(1, $reversed_price, PDO::PARAM_STR);
            $stmt->bindParam(2, $reversed_sold_price, PDO::PARAM_STR);
            $stmt->bindParam(3, $reversed_profit, PDO::PARAM_STR);
            $stmt->bindParam(4, $new_due, PDO::PARAM_STR);
            $stmt->bindParam(5, $request['umrah_booking_id'], PDO::PARAM_INT);
            $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new PDOException('Failed to reverse booking price changes');
            }

            // Reverse supplier penalty deduction
            if ($request['supplier_penalty'] > 0) {
                // Add back to supplier balance if external supplier
                if ($request['supplier_type'] === 'External') {
                    $stmt_update_supplier_balance = $pdo->prepare("
                        UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $stmt_update_supplier_balance->bindParam(1, $request['supplier_penalty'], PDO::PARAM_STR);
                    $stmt_update_supplier_balance->bindParam(2, $request['supplier'], PDO::PARAM_INT);
                    $stmt_update_supplier_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt_update_supplier_balance->bindParam(4, $branch_id, PDO::PARAM_INT);

                    if (!$stmt_update_supplier_balance->execute()) {
                        throw new PDOException('Failed to reverse supplier balance');
                    }
                }

                // Delete supplier transaction
                $stmt_delete_supplier_transaction = $pdo->prepare("
                    DELETE FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah_date_change' AND tenant_id = ? AND branch_id = ?
                ");
                $stmt_delete_supplier_transaction->bindParam(1, $request['supplier'], PDO::PARAM_INT);
                $stmt_delete_supplier_transaction->bindParam(2, $request['umrah_booking_id'], PDO::PARAM_INT);
                $stmt_delete_supplier_transaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_delete_supplier_transaction->bindParam(4, $branch_id, PDO::PARAM_INT);

                if (!$stmt_delete_supplier_transaction->execute()) {
                    throw new PDOException('Failed to delete supplier transaction');
                }
            }

            // Reverse client penalty deduction
            if ($request['total_penalty'] > 0) {
                // Add back to client balance if regular client
                if ($request['client_type'] === 'regular') {
                    if ($request['currency'] === 'USD') {
                        $stmt_update_client_balance = $pdo->prepare("
                            UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
                        ");
                    } else {
                        $stmt_update_client_balance = $pdo->prepare("
                            UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
                        ");
                    }

                    $stmt_update_client_balance->bindParam(1, $request['total_penalty'], PDO::PARAM_STR);
                    $stmt_update_client_balance->bindParam(2, $request['sold_to'], PDO::PARAM_INT);
                    $stmt_update_client_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt_update_client_balance->bindParam(4, $branch_id, PDO::PARAM_INT);

                    if (!$stmt_update_client_balance->execute()) {
                        throw new PDOException('Failed to reverse client balance');
                    }
                }

                // Delete client transaction
                $stmt_delete_client_transaction = $pdo->prepare("
                    DELETE FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah_date_change' AND tenant_id = ? AND branch_id = ?
                ");
                $stmt_delete_client_transaction->bindParam(1, $request['sold_to'], PDO::PARAM_INT);
                $stmt_delete_client_transaction->bindParam(2, $request['umrah_booking_id'], PDO::PARAM_INT);
                $stmt_delete_client_transaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_delete_client_transaction->bindParam(4, $branch_id, PDO::PARAM_INT);

                if (!$stmt_delete_client_transaction->execute()) {
                    throw new PDOException('Failed to delete client transaction');
                }
            }
        }

        // Delete the date change request
        $stmt = $pdo->prepare("
            DELETE FROM date_change_umrah WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new PDOException('Failed to delete date change request');
        }

        // Commit transaction
        $pdo->commit();

        // Update family totals
        $updateFamilyStmt = $pdo->prepare("
            UPDATE families f
            SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_price = (SELECT SUM(sold_price) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid = (SELECT SUM(paid) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid_to_bank = (SELECT SUM(received_bank_payment) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_due = (SELECT SUM(due) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        ");
        $updateFamilyStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(11, $request['family_id'], PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(12, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(13, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->execute();

        $message = $request['status'] === 'Completed'
            ? 'Date change request deleted and all associated changes reversed successfully'
            : 'Date change request deleted successfully';

        echo json_encode([
            'success' => true,
            'message' => $message
        ]);

    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the date change request']);
}
?>