<?php
require_once('../../includes/db.php');
require_once '../../admin/security.php';
enforce_auth();

// CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
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

// Validate required parameters
if (!isset($_POST['booking_id'], $_POST['sold'],
          $_POST['supplier_penalty'], $_POST['service_penalty'], $_POST['description'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$sold = filter_input(INPUT_POST, 'sold', FILTER_VALIDATE_FLOAT);
$supplierPenalty = filter_input(INPUT_POST, 'supplier_penalty', FILTER_VALIDATE_FLOAT);
$servicePenalty = filter_input(INPUT_POST, 'service_penalty', FILTER_VALIDATE_FLOAT);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$bookingId || !$sold || $supplierPenalty === false || $servicePenalty === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

try {
    // Fetch booking data
    $stmt = $pdo->prepare("SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Umrah booking not found');
    }

    // Prevent double refund
    if ($booking['status'] === 'refunded') {
        echo json_encode(['success' => false, 'message' => 'This booking has already been refunded.']);
        exit;
    }

    $currency = $booking['currency'];
    $soldToId = $booking['sold_to'];
    $paidToId = $booking['paid_to'];


    // Fetch client details
    $clientQuery = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $clientQuery->bindParam(1, $soldToId, PDO::PARAM_INT);
    $clientQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $clientQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
    $clientQuery->execute();
    $clientResult = $clientQuery->fetch(PDO::FETCH_ASSOC);
    if (!$clientResult) {
        throw new Exception('Client not found');
    }
    $clientType = $clientResult['client_type'];
    $clientName = $clientResult['name'];

    // Calculate refund amounts (always from sold)
    $base = floatval($booking['price'] ?? 0);
    $refundToSupplier = $base - $supplierPenalty;
    $refundToPassenger = $sold - ($supplierPenalty + $servicePenalty);

    if ($refundToPassenger < 0) {
        throw new Exception('Refund amount cannot be negative.');
    }

    $pdo->beginTransaction();

    // Insert refund record into umrah_refunds
    $insertRefundStmt = $pdo->prepare("INSERT INTO umrah_refunds
        (tenant_id, booking_id, refund_type, refund_amount, base, sold, supplier_penalty, service_penalty, reason, currency, processed_by, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $refundType = ($refundToPassenger >= $sold) ? 'full' : 'partial';
    $insertRefundStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(2, $bookingId, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(3, $refundType, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(4, $refundToPassenger, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(5, $base, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(6, $sold, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(7, $supplierPenalty, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(8, $servicePenalty, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(9, $description, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(10, $currency, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(11, $user_id, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(12, $branch_id, PDO::PARAM_INT);
    if (!$insertRefundStmt->execute()) {
        throw new Exception('Failed to insert refund record');
    }
    $refundId = $pdo->lastInsertId();

    // Process services (multi-supplier), preferring the fulfillment-assigned
    // supplier (umrah_fulfillments.supplier_id) with a legacy fallback to the
    // sold-service line supplier.
    $servicesQuery = $pdo->prepare("SELECT ubs.*, COALESCE(f.supplier_id, ubs.supplier_id) AS supplier_id
        FROM umrah_booking_services ubs
        LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id
          AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ubs.tenant_id)
        WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ?");
    $servicesQuery->bindParam(1, $bookingId, PDO::PARAM_INT);
    $servicesQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $servicesQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
    $servicesQuery->execute();
    $services = $servicesQuery->fetchAll(PDO::FETCH_ASSOC);

    $totalServiceBase = array_sum(array_column($services, 'base_price'));
    $totalServiceSold = array_sum(array_column($services, 'sold_price'));

    foreach ($services as $service) {
        $supplierId = $service['supplier_id'];
        $serviceBase = floatval($service['base_price']);
        $serviceSold = floatval($service['sold_price']);

        // Proportionate refund for this service
        $proportion = ($totalServiceBase > 0) ? ($serviceBase / $totalServiceBase) : (1 / count($services));
        $serviceRefundAmount = $refundToSupplier * $proportion;

        // Get supplier details
        $stmtSupplier = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmtSupplier->bindParam(1, $supplierId, PDO::PARAM_INT);
        $stmtSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmtSupplier->execute();
        $supplierResult = $stmtSupplier->fetch(PDO::FETCH_ASSOC);
        if (!$supplierResult) {
            throw new Exception("Supplier not found for ID: $supplierId");
        }

        $currentBalance = $supplierResult['balance'];
        $supplierType = $supplierResult['supplier_type'];
        $supplierName = $supplierResult['name'];

        // Handle supplier balance for External suppliers
        if ($supplierType === 'External') {
            $newBalance = $currentBalance + $serviceRefundAmount;
            $updateStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
            $updateStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
            $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update supplier balance for supplier ID: $supplierId");
            }

            // Record supplier transaction with balance
            $insertTransStmt = $pdo->prepare("INSERT INTO supplier_transactions
                (tenant_id, transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, branch_id)
                VALUES (?, NOW(), ?, ?, ?, ?, 'credit', ?, 'umrah_refund', ?)");
            $remarks = "Refund for umrah booking #$bookingId - $description";
            $insertTransStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $insertTransStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
            $insertTransStmt->bindParam(3, $refundId, PDO::PARAM_INT);
            $insertTransStmt->bindParam(4, $serviceRefundAmount, PDO::PARAM_STR);
            $insertTransStmt->bindParam(5, $newBalance, PDO::PARAM_STR);
            $insertTransStmt->bindParam(6, $remarks, PDO::PARAM_STR);
            $insertTransStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        } else {
            // Record supplier transaction with balance = 0
            $insertTransStmt = $pdo->prepare("INSERT INTO supplier_transactions
                (tenant_id, transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, branch_id)
                VALUES (?, NOW(), ?, ?, ?, 0, 'credit', ?, 'umrah_refund', ?)");
            $remarks = "Refund for umrah booking #$bookingId - $description";
            $insertTransStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $insertTransStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
            $insertTransStmt->bindParam(3, $refundId, PDO::PARAM_INT);
            $insertTransStmt->bindParam(4, $serviceRefundAmount, PDO::PARAM_STR);
            $insertTransStmt->bindParam(5, $remarks, PDO::PARAM_STR);
            $insertTransStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        }
        if (!$insertTransStmt->execute()) {
            throw new Exception("Failed to record supplier transaction");
        }
    }

    // Process client refund (mirrors ticket logic)
    if ($clientType === 'regular') {
        $balanceField = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
        $updateClientStmt = $pdo->prepare("UPDATE clients SET {$balanceField} = {$balanceField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateClientStmt->bindParam(1, $refundToPassenger, PDO::PARAM_STR);
        $updateClientStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
        $updateClientStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateClientStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updateClientStmt->execute()) {
            throw new Exception('Failed to update client balance');
        }

        $currentClientBalance = ($currency === 'USD') ? $clientResult['usd_balance'] : $clientResult['afs_balance'];
        $newClientBalance = $currentClientBalance + $refundToPassenger;

        $insertClientTransStmt = $pdo->prepare("INSERT INTO client_transactions
            (tenant_id, client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, branch_id)
            VALUES (?, ?, 'Credit', ?, ?, ?, ?, 'umrah_refund', ?, NOW(), ?)");
        $clientDesc = "Refund for umrah booking - $description";
        $insertClientTransStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(3, $refundToPassenger, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(4, $newClientBalance, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(5, $currency, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(6, $clientDesc, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(7, $refundId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        if (!$insertClientTransStmt->execute()) {
            throw new Exception('Failed to record client transaction');
        }
    } else {
        // Non-regular client transaction without balance
        $insertClientTransStmt = $pdo->prepare("INSERT INTO client_transactions
            (tenant_id, client_id, type, amount, currency, description, transaction_of, reference_id, created_at, branch_id, balance)
            VALUES (?, ?, 'Credit', ?, ?, ?, 'umrah_refund', ?, NOW(), ?, 0)");
        $clientDesc = "Refund for umrah booking - $description";
        $insertClientTransStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(3, $refundToPassenger, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(4, $currency, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(5, $clientDesc, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(6, $refundId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        if (!$insertClientTransStmt->execute()) {
            throw new Exception('Failed to record client transaction');
        }
    }

    // Update booking status and profit
    $newProfit = $sold - $refundToPassenger;
    $updateBookingStmt = $pdo->prepare("UPDATE umrah_bookings SET profit = ?, due = '0', status = 'refunded' WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $updateBookingStmt->bindParam(1, $newProfit, PDO::PARAM_STR);
    $updateBookingStmt->bindParam(2, $bookingId, PDO::PARAM_INT);
    $updateBookingStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateBookingStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$updateBookingStmt->execute()) {
        throw new Exception('Failed to update booking status');
    }

    // Update family totals
    $familyId = $booking['family_id'];
    if ($familyId) {
        $updateFamilyStmt = $pdo->prepare("
            UPDATE families f
            SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        ");
        $updateFamilyStmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $familyId, $tenant_id, $branch_id]);
    }

    // Activity logging
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $oldValues = [
        'booking_id' => $bookingId,
        'passenger_name' => $booking['name'],
        'base' => $base,
        'sold' => $sold,
        'supplier_penalty' => $supplierPenalty,
        'service_penalty' => $servicePenalty,
        'currency' => $currency,
        'refund_amount' => $refundToPassenger,
        'description' => $description
    ];

    $activityStmt = $pdo->prepare("INSERT INTO activity_log
        (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id)
        VALUES (?, ?, 'add', 'umrah_refunds', ?, ?, '{}', ?, ?, NOW(), ?)");
    $activityStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $activityStmt->bindParam(2, $user_id, PDO::PARAM_INT);
    $activityStmt->bindParam(3, $refundId, PDO::PARAM_INT);
    $activityStmt->bindParam(4, json_encode($oldValues), PDO::PARAM_STR);
    $activityStmt->bindParam(5, $ipAddress, PDO::PARAM_STR);
    $activityStmt->bindParam(6, $userAgent, PDO::PARAM_STR);
    $activityStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $activityStmt->execute();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully',
        'refund_id' => $refundId,
        'refund_amount' => $refundToPassenger
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error processing refund: ' . $e->getMessage()]);
}
