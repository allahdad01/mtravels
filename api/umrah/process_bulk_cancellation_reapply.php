<?php
// Include necessary files
require_once('../../includes/db.php');

// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$action = isset($_POST['action']) ? $_POST['action'] : '';
$new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';
$bookings_json = isset($_POST['bookings']) ? $_POST['bookings'] : '';
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';

// Validate required fields
if (!$action || !$new_status || empty($bookings_json) || empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid required fields'
    ]);
    exit();
}

// Validate action
if (!in_array($action, ['cancel', 'reapply'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified'
    ]);
    exit();
}

// Validate new status
if (!in_array($new_status, ['cancelled', 'active'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status specified'
    ]);
    exit();
}

// Parse bookings data
try {
    $bookings = json_decode($bookings_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid bookings data format');
    }

    if (empty($bookings) || !is_array($bookings)) {
        throw new Exception('No bookings data provided');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error parsing bookings data: ' . $e->getMessage()
    ]);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    $processed_count = 0;
    $errors = [];

    // Process each booking
    foreach ($bookings as $booking) {
        $booking_id = intval($booking['booking_id']);
        $base_price = floatval($booking['base_price']);
        $sold_price = floatval($booking['sold_price']);
        $current_profit = floatval($booking['current_profit']);

        try {
            // Check if the booking exists and get its details
            $bookingQuery = "SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($bookingQuery);
            $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking_data) {
                throw new Exception("Umrah booking #{$booking_id} not found");
            }

            // Calculate new profit based on action
            if ($action === 'cancel') {
                // Cancellation: Set profit to 0
                $new_profit = 0;
            } else {
                // Re-apply: Recalculate profit (sold - base = profit)
                $new_profit = $sold_price - $base_price;
            }

            // Update booking profit and status
            $updateQuery = "UPDATE umrah_bookings SET profit = ?, status = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->bindParam(1, $new_profit, PDO::PARAM_STR);
            $stmt->bindParam(2, $new_status, PDO::PARAM_STR);
            $stmt->bindParam(3, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update booking #{$booking_id}");
            }

            // Update due amount if needed
            if ($action === 'cancel') {
                // Set due to 0 for cancelled bookings
                $updateDueQuery = "UPDATE umrah_bookings SET due = 0 WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($updateDueQuery);
                $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Recalculate due for re-applied bookings
                $calculateDueQuery = "UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($calculateDueQuery);
                $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }

            // Get all services for this booking (multi-supplier support)
            $servicesQuery = "SELECT * FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($servicesQuery);
            $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $servicesResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($servicesResult)) {
                // Fallback to old single-supplier logic if no services found
                $services = array(array(
                    'supplier_id' => $booking_data['supplier'],
                    'base_price' => floatval($booking_data['price']),
                    'sold_price' => floatval($booking_data['sold_price']),
                    'profit' => floatval($booking_data['profit']),
                    'currency' => $booking_data['currency']
                ));
            } else {
                $services = $servicesResult;
            }

            // Update services and handle supplier/client balances for both actions
            foreach ($services as $service) {
                $supplier_id = $service['supplier_id'];
                $service_base_price = floatval($service['base_price']);
                $service_sold_price = floatval($service['sold_price']);
                $service_profit = floatval($service['profit']);

                if ($action === 'cancel') {
                    // For cancellation, set profit to 0 for all services
                    $serviceNewProfit = 0;
                } else {
                    // For re-apply, recalculate profit for each service
                    $serviceNewProfit = $service_sold_price - $service_base_price;
                }

                $updateServiceQuery = "UPDATE umrah_booking_services SET profit = ? WHERE booking_id = ? AND id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($updateServiceQuery);
                $stmt->bindParam(1, $serviceNewProfit, PDO::PARAM_STR);
                $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $service['id'], PDO::PARAM_INT);
                $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
                $stmt->execute();

                // Handle supplier balance for External suppliers
                if ($action === 'cancel' || $action === 'reapply') {
                    // Get supplier details
                    $stmt_check_balance = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $stmt_check_balance->bindParam(1, $supplier_id, PDO::PARAM_INT);
                    $stmt_check_balance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $stmt_check_balance->bindParam(3, $branch_id, PDO::PARAM_INT);
                    if (!$stmt_check_balance->execute()) {
                        throw new Exception("Failed to fetch supplier details for supplier ID: $supplier_id");
                    }
                    $supplierResult = $stmt_check_balance->fetch(PDO::FETCH_ASSOC);
                    if (!$supplierResult) {
                        throw new Exception("Supplier not found for ID: $supplier_id");
                    }
                    $current_balance = $supplierResult['balance'];
                    $supplier_currency = $supplierResult['currency'];
                    $supplier_name = $supplierResult['name'];
                    $supplier_type = $supplierResult['supplier_type'];

                    // Handle supplier balance and transaction for External suppliers
                    if ($supplier_type === 'External') {
                        if ($action === 'cancel') {
                            // Cancellation: Return base price to supplier (credit)
                            $supplierAmount = $service_base_price;
                            $newSupplierBalance = $current_balance + $supplierAmount;
                            $transactionType = 'credit';
                            $transactionOf = 'umrah_cancellation';
                        } else {
                            // Re-apply: Charge base price from supplier (debit)
                            $supplierAmount = $service_base_price;
                            $newSupplierBalance = $current_balance - $supplierAmount;
                            $transactionType = 'debit';
                            $transactionOf = 'umrah';
                        }

                        // Update supplier balance
                        $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                        $updateSupplierStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
                        $updateSupplierStmt->bindParam(2, $supplier_id, PDO::PARAM_INT);
                        $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                        if (!$updateSupplierStmt->execute()) {
                            throw new Exception("Failed to update supplier balance for supplier ID: $supplier_id");
                        }

                        // Record supplier transaction
                        $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
                            (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id, branch_id)
                            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $supplierRemarks = ucfirst($action) . " for umrah booking #$booking_id - " . $reason;
                        $insertSupplierTransactionStmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
                        $insertSupplierTransactionStmt->bindParam(2, $booking_id, PDO::PARAM_INT);
                        $insertSupplierTransactionStmt->bindParam(3, $supplierAmount, PDO::PARAM_STR);
                        $insertSupplierTransactionStmt->bindParam(4, $newSupplierBalance, PDO::PARAM_STR);
                        $insertSupplierTransactionStmt->bindParam(5, $transactionType, PDO::PARAM_STR);
                        $insertSupplierTransactionStmt->bindParam(6, $supplierRemarks, PDO::PARAM_STR);
                        $insertSupplierTransactionStmt->bindParam(7, $transactionOf, PDO::PARAM_STR);
                        $insertSupplierTransactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                        $insertSupplierTransactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                        if (!$insertSupplierTransactionStmt->execute()) {
                            throw new Exception("Failed to record supplier transaction for supplier ID: $supplier_id");
                        }
                    }
                }
            }

            // Handle client balance for both regular and non-regular clients
            if ($action === 'cancel' || $action === 'reapply') {
                // Get client details and type
                $clientQuery = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $clientQuery->bindParam(1, $booking_data['sold_to'], PDO::PARAM_INT);
                $clientQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $clientQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
                if (!$clientQuery->execute()) {
                    throw new Exception("Failed to fetch client details");
                }
                $clientResult = $clientQuery->fetch(PDO::FETCH_ASSOC);
                if (!$clientResult) {
                    throw new Exception("Client not found");
                }

                $currency = $booking_data['currency'] ?: 'USD';

                if ($action === 'cancel') {
                    // Cancellation: Return sold price to client (credit)
                    $clientAmount = $sold_price;
                    $clientTransactionType = 'Credit';
                    $clientTransactionOf = 'umrah_cancellation';
                } else {
                    // Re-apply: Charge sold price from client (debit)
                    $clientAmount = $sold_price;
                    $clientTransactionType = 'Debit';
                    $clientTransactionOf = 'umrah';
                }

                // Handle client balance for regular clients
                if ($clientResult['client_type'] === 'regular') {
                    // Update client balance based on currency
                    if ($currency === 'USD') {
                        $newBalance = $clientResult['usd_balance'];
                        if ($action === 'cancel') {
                            $newBalance += $clientAmount; // Credit
                        } else {
                            $newBalance -= $clientAmount; // Debit
                        }
                        $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmt = $pdo->prepare($updateClientQuery);
                        $stmt->bindParam(1, $newBalance, PDO::PARAM_STR);
                        $stmt->bindParam(2, $booking_data['sold_to'], PDO::PARAM_INT);
                        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    } else {
                        $newBalance = $clientResult['afs_balance'];
                        if ($action === 'cancel') {
                            $newBalance += $clientAmount; // Credit
                        } else {
                            $newBalance -= $clientAmount; // Debit
                        }
                        $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmt = $pdo->prepare($updateClientQuery);
                        $stmt->bindParam(1, $newBalance, PDO::PARAM_STR);
                        $stmt->bindParam(2, $booking_data['sold_to'], PDO::PARAM_INT);
                        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update client balance");
                    }

                    // Record client transaction with balance
                    $clientTransactionQuery = "INSERT INTO client_transactions
                        (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($clientTransactionQuery);
                    $clientTransactionDescription = ucfirst($action) . " for umrah booking #$booking_id - $reason";
                    $stmt->bindParam(1, $booking_data['sold_to'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $clientTransactionType, PDO::PARAM_STR);
                    $stmt->bindParam(3, $clientAmount, PDO::PARAM_STR);
                    $stmt->bindParam(4, $newBalance, PDO::PARAM_STR);
                    $stmt->bindParam(5, $currency, PDO::PARAM_STR);
                    $stmt->bindParam(6, $clientTransactionDescription, PDO::PARAM_STR);
                    $stmt->bindParam(7, $clientTransactionOf, PDO::PARAM_STR);
                    $stmt->bindParam(8, $booking_id, PDO::PARAM_INT);
                    $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to record client transaction");
                    }
                } else {
                    // Record client transaction without balance for non-regular clients
                    $clientTransactionQuery = "INSERT INTO client_transactions
                        (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($clientTransactionQuery);
                    $clientTransactionDescription = ucfirst($action) . " for umrah booking #$booking_id - $reason";
                    $stmt->bindParam(1, $booking_data['sold_to'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $clientTransactionType, PDO::PARAM_STR);
                    $stmt->bindParam(3, $clientAmount, PDO::PARAM_STR);
                    $stmt->bindParam(4, $currency, PDO::PARAM_STR);
                    $stmt->bindParam(5, $clientTransactionDescription, PDO::PARAM_STR);
                    $stmt->bindParam(6, $clientTransactionOf, PDO::PARAM_STR);
                    $stmt->bindParam(7, $booking_id, PDO::PARAM_INT);
                    $stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to record client transaction");
                    }
                }
            }

            // Log the action in cancellation_reapply_log table (if table exists)
            try {
                $logQuery = "INSERT INTO cancellation_reapply_log (booking_id, action, base_price, sold_price, previous_profit, new_profit, reason, tenant_id, branch_id, created_by, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($logQuery);
                $action_type = ($action === 'cancel') ? 'cancellation' : 'reapplication';
                $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $action_type, PDO::PARAM_STR);
                $stmt->bindParam(3, $base_price, PDO::PARAM_STR);
                $stmt->bindParam(4, $sold_price, PDO::PARAM_STR);
                $stmt->bindParam(5, $current_profit, PDO::PARAM_STR);
                $stmt->bindParam(6, $new_profit, PDO::PARAM_STR);
                $stmt->bindParam(7, $reason, PDO::PARAM_STR);
                $stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                $stmt->bindParam(10, $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->execute();
            } catch (Exception $e) {
                // Log table might not exist, continue without logging
                error_log("Cancellation/Reapply log table not found for booking {$booking_id}: " . $e->getMessage());
            }

            // Log the action in activity_log table for audit trail
            try {
                $old_values = json_encode([
                    'profit' => $current_profit,
                    'status' => $booking_data['status'],
                    'due' => $booking_data['due']
                ]);

                $new_values = json_encode([
                    'profit' => $new_profit,
                    'status' => $new_status,
                    'due' => ($action === 'cancel') ? 0 : ($sold_price - $booking_data['paid']),
                    'reason' => $reason,
                    'action' => $action,
                    'bulk_operation' => true
                ]);

                $activity_log_stmt = $pdo->prepare("
                    INSERT INTO activity_log
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");

                $activity_action = ($action === 'cancel') ? 'bulk_cancel_umrah_booking' : 'bulk_reapply_umrah_booking';
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

                $activity_log_stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
                $activity_log_stmt->bindParam(2, $activity_action, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(3, $booking_id, PDO::PARAM_INT);
                $activity_log_stmt->bindParam(4, $old_values, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(5, $new_values, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(6, $ip_address, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(7, $user_agent, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $activity_log_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);

                if (!$activity_log_stmt->execute()) {
                    error_log("Failed to insert activity log for bulk booking #{$booking_id}: " . $activity_log_stmt->error);
                }

                $activity_log_stmt->close();
            } catch (Exception $e) {
                // Activity log is critical but don't break the process if it fails
                error_log("Activity logging failed for bulk booking #{$booking_id}: " . $e->getMessage());
            }

            $processed_count++;

        } catch (Exception $e) {
            $errors[] = "Error processing booking #{$booking_id}: " . $e->getMessage();
        }
    }

    // Check if we processed any bookings successfully
    if ($processed_count === 0) {
        throw new Exception('No bookings were successfully processed. ' . implode('; ', $errors));
    }

    // If there were some errors but we processed some successfully, we still commit
    if ($processed_count < count($bookings)) {
        $warning_message = "Processed {$processed_count} of " . count($bookings) . " bookings successfully.";
        if (!empty($errors)) {
            $warning_message .= " Errors: " . implode('; ', $errors);
        }

        // Log warning but don't throw exception
        error_log("Bulk {$action} processing warning: " . $warning_message);
    }

    // Commit transaction
    $pdo->commit();

    $message = "Successfully processed {$processed_count} booking" . ($processed_count > 1 ? 's' : '') . " with bulk {$action}";
    if (!empty($errors)) {
        $message .= ". Some bookings had errors: " . implode('; ', $errors);
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'processed_count' => $processed_count,
        'total_count' => count($bookings),
        'action' => $action,
        'new_status' => $new_status,
        'balances_updated' => true,
        'supplier_transactions' => ($action === 'cancel' || $action === 'reapply'),
        'client_transactions' => ($action === 'cancel' || $action === 'reapply')
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => 'Error processing bulk ' . $action . ': ' . $e->getMessage(),
        'processed_count' => isset($processed_count) ? $processed_count : 0,
        'errors' => isset($errors) ? $errors : []
    ]);
}
?>