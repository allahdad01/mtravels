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
    // Get the approved request details with booking and supplier/client info
    $stmt = $conn->prepare("
        SELECT dc.*, ub.price as current_price, ub.sold_price, ub.profit, ub.due,ub.supplier, ub.sold_to, ub.currency, ub.family_id,
               s.name as supplier_name, s.balance as supplier_balance, s.supplier_type,
               c.name as client_name, c.usd_balance, c.afs_balance, c.client_type
        FROM date_change_umrah dc
        LEFT JOIN umrah_bookings ub ON dc.umrah_booking_id = ub.booking_id
        LEFT JOIN suppliers s ON ub.supplier = s.id
        LEFT JOIN clients c ON ub.sold_to = c.id
        WHERE dc.id = ? AND dc.tenant_id = ? AND dc.status = 'Approved'
    ");
    $stmt->bind_param("ii", $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Approved date change request not found']);
        exit;
    }

    $request = $result->fetch_assoc();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Calculate new prices with penalties
        $new_price = $request['current_price'] + $request['supplier_penalty'];
        $new_sold_price = $request['sold_price'] + $request['total_penalty'];
        $new_profit = $new_sold_price - $new_price;
        $new_due = $request['total_penalty'] + $request['due'];

        // Update the booking with new dates and adjusted prices
        $updateBookingSql = "
            UPDATE umrah_bookings
            SET flight_date = ?, return_date = ?, duration = ?,
                price = ?, sold_price = ?, profit = ?, due = ?
        ";

        $params = [
            $request['new_flight_date'],
            $request['new_return_date'],
            $request['new_duration'],
            $new_price,
            $new_sold_price,
            $new_profit,
            $new_due
        ];
        $types = "sssdddd";

        $updateBookingSql .= " WHERE booking_id = ? AND tenant_id = ?";
        $params = array_merge($params, [$request['umrah_booking_id'], $tenant_id]);
        $types .= "ii";

        $stmt = $conn->prepare($updateBookingSql);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking');
        }

        // Handle supplier penalty deduction
        if ($request['supplier_penalty'] > 0) {
            $new_supplier_balance = $request['supplier_balance'] - $request['supplier_penalty'];

            // Insert supplier transaction
            $supplier_remarks = "Supplier penalty of {$request['supplier_penalty']} {$request['currency']} deducted for date change on booking #{$request['umrah_booking_id']}";
            $stmt_supplier_transaction = $conn->prepare("
                INSERT INTO supplier_transactions (
                    supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id
                ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'umrah_date_change', ?)
            ");
            $stmt_supplier_transaction->bind_param(
                "iiddsi",
                $request['supplier'],
                $request['umrah_booking_id'],
                $request['supplier_penalty'],
                $new_supplier_balance,
                $supplier_remarks,
                $tenant_id
            );

            if (!$stmt_supplier_transaction->execute()) {
                throw new Exception('Failed to create supplier penalty transaction');
            }

            // Update supplier balance if external supplier
            if ($request['supplier_type'] === 'External') {
                $stmt_update_supplier_balance = $conn->prepare("
                    UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?
                ");
                $stmt_update_supplier_balance->bind_param("dii", $request['supplier_penalty'], $request['supplier'], $tenant_id);

                if (!$stmt_update_supplier_balance->execute()) {
                    throw new Exception('Failed to update supplier balance');
                }
            }
        }

        // Handle client penalty deduction
        if ($request['total_penalty'] > 0) {
            $client_description = "Client debited {$request['total_penalty']} {$request['currency']} for date change penalty on booking #{$request['umrah_booking_id']}";

            // Determine which balance to update based on currency
            $current_client_balance = ($request['currency'] === 'USD') ? $request['usd_balance'] : $request['afs_balance'];
            $new_client_balance = $current_client_balance - $request['total_penalty'];

            // Insert client transaction
            $stmt_client_transaction = $conn->prepare("
                INSERT INTO client_transactions (
                    client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id
                ) VALUES (?, 'Debit', 'umrah_date_change', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt_client_transaction->bind_param(
                "iiddsss",
                $request['sold_to'],
                $request['umrah_booking_id'],
                $request['total_penalty'],
                $new_client_balance,
                $request['currency'],
                $client_description,
                $tenant_id
            );

            if (!$stmt_client_transaction->execute()) {
                throw new Exception('Failed to create client penalty transaction');
            }

            // Update client balance if regular client
            if ($request['client_type'] === 'regular') {
                if ($request['currency'] === 'USD') {
                    $stmt_update_client_balance = $conn->prepare("
                        UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?
                    ");
                } else {
                    $stmt_update_client_balance = $conn->prepare("
                        UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?
                    ");
                }

                $stmt_update_client_balance->bind_param("dii", $request['total_penalty'], $request['sold_to'], $tenant_id);

                if (!$stmt_update_client_balance->execute()) {
                    throw new Exception('Failed to update client balance');
                }
            }
        }

        // Update the request status to completed
        $stmt = $conn->prepare("
            UPDATE date_change_umrah
            SET status = 'Completed',
                processed_by = ?,
                processed_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param("iii", $_SESSION['user_id'], $id, $tenant_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update request status');
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

        // Log the processing
        error_log("Date change request processed - ID: $id, Booking: {$request['umrah_booking_id']}, Supplier Penalty: {$request['supplier_penalty']}, Total Penalty: {$request['total_penalty']}, Processed by: {$_SESSION['user_id']}");

        echo json_encode([
            'success' => true,
            'message' => 'Date changes applied successfully to booking #' . $request['umrah_booking_id'] . ' with penalties processed'
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Process date change request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the date changes']);
}
?>