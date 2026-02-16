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

            // Update services profit
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
        'new_status' => $new_status
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
