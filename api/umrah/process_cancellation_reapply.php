<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');

// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0;
$sold_price = isset($_POST['sold_price']) ? floatval($_POST['sold_price']) : 0;
$current_profit = isset($_POST['current_profit']) ? floatval($_POST['current_profit']) : 0;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';

// Debug logging
error_log("Cancellation/Reapply Request: " . json_encode($_POST));

// Validate required fields
if (!$booking_id || !$action || empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid required fields',
        'debug' => ['booking_id' => $booking_id, 'action' => $action, 'reason' => $reason]
    ]);
    exit();
}

// Validate action
if (!in_array($action, ['cancel', 'reapply'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified',
        'debug' => ['action' => $action, 'valid_actions' => ['cancel', 'reapply']]
    ]);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Check if the booking exists and get its details
    $bookingQuery = "SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $conn->prepare($bookingQuery);
    $stmt->bind_param('iii', $booking_id, $tenant_id, $branch_id);
    $stmt->execute();
    $bookingResult = $stmt->get_result();

    if ($bookingResult->num_rows === 0) {
        throw new Exception('Umrah booking not found');
    }

    $booking = $bookingResult->fetch_assoc();

    // Get all services for this booking (multi-supplier support)
    $servicesQuery = "SELECT * FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $conn->prepare($servicesQuery);
    $stmt->bind_param('iii', $booking_id, $tenant_id, $branch_id);
    $stmt->execute();
    $servicesResult = $stmt->get_result();

    if ($servicesResult->num_rows === 0) {
        // Fallback to old single-supplier logic if no services found
        $services = array(array(
            'supplier_id' => $booking['supplier'],
            'base_price' => floatval($booking['price']),
            'sold_price' => floatval($booking['sold_price']),
            'profit' => floatval($booking['profit']),
            'currency' => $booking['currency']
        ));
    } else {
        $services = $servicesResult->fetch_all(MYSQLI_ASSOC);
    }

    // Calculate new profit based on action
    if ($action === 'cancel') {
        // Cancellation: Set profit to 0
        $new_profit = 0;
        $new_status = 'cancelled';
        $action_description = "Booking cancelled - profit set to 0";
    } else {
        // Re-apply: Recalculate profit (sold - base = profit)
        $new_profit = $sold_price - $base_price;
        $new_status = 'active';
        $action_description = "Booking re-applied - profit recalculated";
    }

    // Update booking profit and status
    $updateQuery = "UPDATE umrah_bookings SET profit = ?, status = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dsiii', $new_profit, $new_status, $booking_id, $tenant_id, $branch_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update booking');
    }

    // Update due amount if needed
    if ($action === 'cancel') {
        // Set due to 0 for cancelled bookings
        $updateDueQuery = "UPDATE umrah_bookings SET due = 0 WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $conn->prepare($updateDueQuery);
        $stmt->bind_param('iii', $booking_id, $tenant_id, $branch_id);
        $stmt->execute();
    } else {
        // Recalculate due for re-applied bookings
        $calculateDueQuery = "UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $conn->prepare($calculateDueQuery);
        $stmt->bind_param('iii', $booking_id, $tenant_id, $branch_id);
        $stmt->execute();
    }

    // Update the umrah_booking_services and handle supplier/client balances
    $totalBasePrice = 0;
    $totalSoldPrice = 0;
    
    foreach ($services as $service) {
        $supplier_id = $service['supplier_id'];
        $service_base_price = floatval($service['base_price']);
        $service_sold_price = floatval($service['sold_price']);
        $service_profit = floatval($service['profit']);

        // Track totals
        $totalBasePrice += $service_base_price;
        $totalSoldPrice += $service_sold_price;

        if ($action === 'cancel') {
            // For cancellation, set profit to 0 for all services
            $serviceNewProfit = 0;
        } else {
            // For re-apply, recalculate profit for each service
            $serviceNewProfit = $service_sold_price - $service_base_price;
        }

        $updateServiceQuery = "UPDATE umrah_booking_services SET profit = ? WHERE booking_id = ? AND id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $conn->prepare($updateServiceQuery);
        $stmt->bind_param('diiii', $serviceNewProfit, $booking_id, $service['id'], $tenant_id, $branch_id);
        $stmt->execute();

        // Handle supplier balance for External suppliers
        if ($action === 'cancel' || $action === 'reapply') {
            // Get supplier details
            $stmt_check_balance = $conn->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_check_balance->bind_param("iii", $supplier_id, $tenant_id, $branch_id);
            if (!$stmt_check_balance->execute()) {
                throw new Exception("Failed to fetch supplier details for supplier ID: $supplier_id");
            }
            $supplierResult = $stmt_check_balance->get_result()->fetch_assoc();
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
                $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $updateSupplierStmt->bind_param("diii", $newSupplierBalance, $supplier_id, $tenant_id, $branch_id);
                if (!$updateSupplierStmt->execute()) {
                    throw new Exception("Failed to update supplier balance for supplier ID: $supplier_id");
                }

                // Record supplier transaction
                $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions
                    (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id, branch_id)
                    VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $supplierRemarks = ucfirst($action) . " for umrah booking #$booking_id - " . $reason;
                $insertSupplierTransactionStmt->bind_param("iiddssii",
                    $supplier_id,
                    $booking_id,
                    $supplierAmount,
                    $newSupplierBalance,
                    $transactionType,
                    $supplierRemarks,
                    $transactionOf,
                    $tenant_id,
                    $branch_id
                );
                if (!$insertSupplierTransactionStmt->execute()) {
                    throw new Exception("Failed to record supplier transaction for supplier ID: $supplier_id");
                }
            }
        }
    }

    // Handle client balance for both regular and non-regular clients
    if ($action === 'cancel' || $action === 'reapply') {
        // Get client details and type
        $clientQuery = $conn->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $clientQuery->bind_param("iii", $booking['sold_to'], $tenant_id, $branch_id);
        if (!$clientQuery->execute()) {
            throw new Exception("Failed to fetch client details");
        }
        $clientResult = $clientQuery->get_result()->fetch_assoc();
        if (!$clientResult) {
            throw new Exception("Client not found");
        }

        $currency = $booking['currency'] ?: 'USD';
        
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
                $stmt = $conn->prepare($updateClientQuery);
                $stmt->bind_param("diii", $newBalance, $booking['sold_to'], $tenant_id, $branch_id);
            } else {
                $newBalance = $clientResult['afs_balance'];
                if ($action === 'cancel') {
                    $newBalance += $clientAmount; // Credit
                } else {
                    $newBalance -= $clientAmount; // Debit
                }
                $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $conn->prepare($updateClientQuery);
                $stmt->bind_param("diii", $newBalance, $booking['sold_to'], $tenant_id, $branch_id);
            }
            if (!$stmt->execute()) {
                throw new Exception("Failed to update client balance");
            }

            // Record client transaction with balance
            $clientTransactionQuery = "INSERT INTO client_transactions
                (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($clientTransactionQuery);
            $clientTransactionDescription = ucfirst($action) . " for umrah booking #$booking_id - $reason";
            $stmt->bind_param("sdssssiii",
                $booking['sold_to'],
                $clientTransactionType,
                $clientAmount,
                $newBalance,
                $currency,
                $clientTransactionDescription,
                $clientTransactionOf,
                $booking_id,
                $tenant_id,
                $branch_id
            );
            if (!$stmt->execute()) {
                throw new Exception("Failed to record client transaction");
            }
        } else {
            // Record client transaction without balance for non-regular clients
            $clientTransactionQuery = "INSERT INTO client_transactions
                (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($clientTransactionQuery);
            $clientTransactionDescription = ucfirst($action) . " for umrah booking #$booking_id - $reason";
            $stmt->bind_param("sdsssiii",
                $booking['sold_to'],
                $clientTransactionType,
                $clientAmount,
                $currency,
                $clientTransactionDescription,
                $clientTransactionOf,
                $booking_id,
                $tenant_id,
                $branch_id
            );
            if (!$stmt->execute()) {
                throw new Exception("Failed to record client transaction");
            }
        }
    }

    // Log the action in cancellation_reapply_log table (if table exists)
    $log_id = null;
    try {
        $logQuery = "INSERT INTO cancellation_reapply_log (booking_id, action, base_price, sold_price, previous_profit, new_profit, reason, tenant_id, branch_id, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($logQuery);
        $action_type = ($action === 'cancel') ? 'cancellation' : 'reapplication';
        $stmt->bind_param('issddssiii', $booking_id, $action_type, $base_price, $sold_price, $current_profit, $new_profit, $reason, $tenant_id, $branch_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $log_id = $conn->insert_id;
        }
    } catch (Exception $e) {
        // Log table might not exist, continue without logging
        error_log("Cancellation/Reapply log table not found or error: " . $e->getMessage());
    }

    // Log the action in activity_log table for audit trail
    try {
        $old_values = json_encode([
            'profit' => $current_profit,
            'status' => $booking['status'],
            'due' => $booking['due']
        ]);
        
        $new_values = json_encode([
            'profit' => $new_profit,
            'status' => $new_status,
            'due' => ($action === 'cancel') ? 0 : ($sold_price - $booking['paid']),
            'reason' => $reason,
            'action' => $action
        ]);

        $activity_log_stmt = $conn->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $activity_action = ($action === 'cancel') ? 'cancel_umrah_booking' : 'reapply_umrah_booking';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $activity_log_stmt->bind_param("isssssii",
            $_SESSION['user_id'],
            $activity_action,
            $booking_id,
            $old_values,
            $new_values,
            $ip_address,
            $user_agent,
            $tenant_id,
            $branch_id
        );
        
        if (!$activity_log_stmt->execute()) {
            error_log("Failed to insert activity log for booking #{$booking_id}: " . $activity_log_stmt->error);
        }
        
        $activity_log_stmt->close();
    } catch (Exception $e) {
        // Activity log is critical but don't break the process if it fails
        error_log("Activity logging failed for booking #{$booking_id}: " . $e->getMessage());
    }
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => "Booking {$action}ed successfully",
        'log_id' => $log_id,
        'new_profit' => $new_profit,
        'action' => $action,
        'previous_profit' => $current_profit,
        'debug' => [
            'booking_id' => $booking_id,
            'action' => $action,
            'base_price' => $base_price,
            'sold_price' => $sold_price,
            'new_profit' => $new_profit,
            'new_status' => $new_status,
            'balances_updated' => true,
            'supplier_transactions' => ($action === 'cancel' || $action === 'reapply'),
            'client_transactions' => ($action === 'cancel' || $action === 'reapply')
        ]
    ];
    
    error_log("Cancellation/Reapply Success Response: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $error_response = [
        'success' => false,
        'message' => 'Error processing ' . $action . ': ' . $e->getMessage(),
        'debug' => [
            'action' => $action,
            'booking_id' => $booking_id,
            'error_type' => get_class($e),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    ];
    
    error_log("Cancellation/Reapply Error: " . json_encode($error_response));
    echo json_encode($error_response);
}
?>