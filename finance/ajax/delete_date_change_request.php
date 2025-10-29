<?php
// Include security and database connections
require_once '../security.php';
require_once '../../includes/db.php';
require_once '../../includes/conn.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

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
    $stmt = $conn->prepare("
        SELECT dc.*, ub.price as current_price, ub.sold_price, ub.profit, ub.due, ubs.supplier_id as supplier, ub.sold_to, ub.currency, ub.family_id,
               s.name as supplier_name, s.balance as supplier_balance, s.supplier_type,
               c.name as client_name, c.usd_balance, c.afs_balance, c.client_type
        FROM date_change_umrah dc
        LEFT JOIN umrah_bookings ub ON dc.umrah_booking_id = ub.booking_id
        LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND ubs.service_type IN ('all', 'ticket')
        LEFT JOIN suppliers s ON ubs.supplier_id = s.id
        LEFT JOIN clients c ON ub.sold_to = c.id
        WHERE dc.id = ? AND dc.tenant_id = ?
    ");
    $stmt->bind_param("ii", $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Date change request not found']);
        exit;
    }

    $request = $result->fetch_assoc();

    // Start transaction
    $conn->begin_transaction();

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
                WHERE booking_id = ? AND tenant_id = ?
            ";

            $stmt = $conn->prepare($updateBookingSql);
            $stmt->bind_param("ddddii", $reversed_price, $reversed_sold_price, $reversed_profit, $new_due, $request['umrah_booking_id'], $tenant_id);

            if (!$stmt->execute()) {
                throw new Exception('Failed to reverse booking price changes');
            }

            // Reverse supplier penalty deduction
            if ($request['supplier_penalty'] > 0) {
                // Add back to supplier balance if external supplier
                if ($request['supplier_type'] === 'External') {
                    $stmt_update_supplier_balance = $conn->prepare("
                        UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?
                    ");
                    $stmt_update_supplier_balance->bind_param("dii", $request['supplier_penalty'], $request['supplier'], $tenant_id);

                    if (!$stmt_update_supplier_balance->execute()) {
                        throw new Exception('Failed to reverse supplier balance');
                    }
                }

                // Delete supplier transaction
                $stmt_delete_supplier_transaction = $conn->prepare("
                    DELETE FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah_date_change' AND tenant_id = ?
                ");
                $stmt_delete_supplier_transaction->bind_param("iii", $request['supplier'], $request['umrah_booking_id'], $tenant_id);

                if (!$stmt_delete_supplier_transaction->execute()) {
                    throw new Exception('Failed to delete supplier transaction');
                }
            }

            // Reverse client penalty deduction
            if ($request['total_penalty'] > 0) {
                // Add back to client balance if regular client
                if ($request['client_type'] === 'regular') {
                    if ($request['currency'] === 'USD') {
                        $stmt_update_client_balance = $conn->prepare("
                            UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?
                        ");
                    } else {
                        $stmt_update_client_balance = $conn->prepare("
                            UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?
                        ");
                    }

                    $stmt_update_client_balance->bind_param("dii", $request['total_penalty'], $request['sold_to'], $tenant_id);

                    if (!$stmt_update_client_balance->execute()) {
                        throw new Exception('Failed to reverse client balance');
                    }
                }

                // Delete client transaction
                $stmt_delete_client_transaction = $conn->prepare("
                    DELETE FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah_date_change' AND tenant_id = ?
                ");
                $stmt_delete_client_transaction->bind_param("iii", $request['sold_to'], $request['umrah_booking_id'], $tenant_id);

                if (!$stmt_delete_client_transaction->execute()) {
                    throw new Exception('Failed to delete client transaction');
                }
            }

         
        }

        // Delete the date change request
        $stmt = $conn->prepare("
            DELETE FROM date_change_umrah WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param("ii", $id, $tenant_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to delete date change request');
        }

        // Commit transaction
        $conn->commit();

        // Update family totals
        $updateFamilyStmt = $conn->prepare("
            UPDATE families f
            SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id),
                f.total_price = (SELECT SUM(sold_price) FROM umrah_bookings WHERE family_id = f.family_id),
                f.total_paid = (SELECT SUM(paid) FROM umrah_bookings WHERE family_id = f.family_id),
                f.total_paid_to_bank = (SELECT SUM(received_bank_payment) FROM umrah_bookings WHERE family_id = f.family_id),
                f.total_due = (SELECT SUM(due) FROM umrah_bookings WHERE family_id = f.family_id)
            WHERE f.family_id = ? AND f.tenant_id = ?
        ");
        $updateFamilyStmt->bind_param("ii", $request['family_id'], $tenant_id);
        $updateFamilyStmt->execute();

        // Log the deletion
        error_log("Date change request deleted - ID: $id, Booking: {$request['umrah_booking_id']}, Status: {$request['status']}, Deleted by: {$_SESSION['user_id']}");

        $message = $request['status'] === 'Completed'
            ? 'Date change request deleted and all associated changes reversed successfully'
            : 'Date change request deleted successfully';

        echo json_encode([
            'success' => true,
            'message' => $message
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Delete date change request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the date change request']);
}
?>