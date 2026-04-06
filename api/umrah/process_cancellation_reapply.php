<?php
// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');

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
    $pdo->beginTransaction();

    // Check if the booking exists and get its details
    $bookingQuery = "SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($bookingQuery);
    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Umrah booking not found');
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
            'supplier_id' => $booking['supplier'],
            'base_price' => floatval($booking['price']),
            'sold_price' => floatval($booking['sold_price']),
            'profit' => floatval($booking['profit']),
            'currency' => $booking['currency']
        ));
    } else {
        $services = $servicesResult;
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
        $new_status = 'pending';
        $action_description = "Booking re-applied - profit recalculated";
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
        throw new Exception('Failed to update booking');
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

    // Update the umrah_booking_services profit
    foreach ($services as $service) {
        $service_base_price = floatval($service['base_price']);
        $service_sold_price = floatval($service['sold_price']);

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
    }

    // Log the action in cancellation_reapply_log table (if table exists)
    $log_id = null;
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
        if ($stmt->execute()) {
            $log_id = $pdo->lastInsertId();
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

        $activity_log_stmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $activity_action = ($action === 'cancel') ? 'cancel_umrah_booking' : 'reapply_umrah_booking';
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
            error_log("Failed to insert activity log for booking #{$booking_id}: " . $activity_log_stmt->error);
        }

        $activity_log_stmt->close();
    } catch (Exception $e) {
        // Activity log is critical but don't break the process if it fails
        error_log("Activity logging failed for booking #{$booking_id}: " . $e->getMessage());
    }

    // Commit transaction
    $pdo->commit();

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
            'new_status' => $new_status
        ]
    ];

    error_log("Cancellation/Reapply Success Response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();

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
