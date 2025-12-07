<?php
// Include necessary files
require_once('../../includes/db.php');
require_once('../../includes/conn.php');
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
$refund_type = isset($_POST['refund_type']) ? $_POST['refund_type'] : '';
$refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';

// Validate required fields
if (!$booking_id || !$refund_type || empty($reason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid required fields'
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

    // Calculate totals from services
    $totalBasePrice = array_sum(array_column($services, 'base_price'));
    $totalSoldPrice = array_sum(array_column($services, 'sold_price'));
    $totalProfit = array_sum(array_column($services, 'profit'));

    // Calculate new profit based on refund type
    if ($refund_type === 'full') {
        // Full refund - set profit to zero and refund the total sold amount
        $newProfit = 0;
        $refund_amount = $totalSoldPrice; // Refund the total sold amount
    } else {
        // Partial refund
        if ($refund_amount < 0) {
            throw new Exception('Invalid refund amount');
        }

        if ($refund_amount > $totalSoldPrice) {
            throw new Exception('Refund amount cannot be greater than sold amount');
        }

        // Calculate how much we're keeping (not refunding)
        $amountKept = $totalSoldPrice - $refund_amount;

        // New profit is proportional to amount kept
        $newProfit = $amountKept;
    }

    // Insert refund record
    $insertQuery = "INSERT INTO umrah_refunds (booking_id, refund_type, refund_amount, reason, currency, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param('isdssdi', $booking_id, $refund_type, $refund_amount, $reason, $currency, $tenant_id, $branch_id);
    $stmt->execute();
    
    // Get the ID of the newly inserted refund record
    $refund_id = $conn->insert_id;
    
    // Update booking profit and status
    $updateQuery = "UPDATE umrah_bookings SET profit = ?, due = '0', status = 'refunded' WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('diii', $newProfit, $booking_id, $tenant_id, $branch_id);
    $stmt->execute();


    // Process refunds for each service/supplier
    foreach ($services as $service) {
        $supplier_id = $service['supplier_id'];
        $service_base_price = floatval($service['base_price']);
        $service_sold_price = floatval($service['sold_price']);
        $service_profit = floatval($service['profit']);


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
            // Convert refund amount if currencies differ (simplified - assuming same currency for now)
            $supplierRefundAmount = $service_base_price;

            // Update supplier balance
            $newSupplierBalance = $current_balance + $supplierRefundAmount;
            $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateSupplierStmt->bind_param("diii", $newSupplierBalance, $supplier_id, $tenant_id, $branch_id);
            if (!$updateSupplierStmt->execute()) {
                throw new Exception("Failed to update supplier balance for supplier ID: $supplier_id");
            }

            // Record supplier transaction with balance
            $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions
                (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id, branch_id)
                VALUES (NOW(), ?, ?, ?, ?, 'credit', ?, 'umrah_refund', ?, ?)");
            $supplierRemarks = "Refund for umrah booking #$booking_id - " . $reason;
            $insertSupplierTransactionStmt->bind_param("iiddsii",
                $supplier_id,
                $refund_id,
                $supplierRefundAmount,
                $newSupplierBalance,
                $supplierRemarks,
                $tenant_id,
                $branch_id
            );
        } else {
            // Record supplier transaction without balance for non-External suppliers
            $insertSupplierTransactionStmt = $conn->prepare("INSERT INTO supplier_transactions
                (transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, tenant_id, branch_id)
                VALUES (NOW(), ?, ?, ?, 'credit', ?, 'umrah_refund', ?, ?)");
            $supplierRemarks = "Refund for umrah booking #$booking_id - " . $reason;
            $insertSupplierTransactionStmt->bind_param("iidsii",
                $supplier_id,
                $refund_id,
                $service_refund_amount,
                $supplierRemarks,
                $tenant_id,
                $branch_id
            );
        }
        if (!$insertSupplierTransactionStmt->execute()) {
            throw new Exception("Failed to record supplier transaction for supplier ID: $supplier_id");
        }
    }

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

    // Handle client balance for regular clients
    if ($clientResult['client_type'] === 'regular') {
        // Calculate the amount in the appropriate currency
        $refundInClientCurrency = $refund_amount;

        // Update client balance based on currency
        if ($currency === 'USD') {
            $newUsdBalance = $clientResult['usd_balance'] + $refundInClientCurrency;
            $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($updateClientQuery);
            $stmt->bind_param("diii", $newUsdBalance, $booking['sold_to'], $tenant_id, $branch_id);
        } else {
            $newAfsBalance = $clientResult['afs_balance'] + $refundInClientCurrency;
            $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($updateClientQuery);
            $stmt->bind_param("diii", $newAfsBalance, $booking['sold_to'], $tenant_id, $branch_id);
        }
        if (!$stmt->execute()) {
            throw new Exception("Failed to update client balance");
        }

        // Record client transaction
        $clientTransactionQuery = "INSERT INTO client_transactions
            (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
            VALUES (?, 'Credit', ?, ?, ?, ?, 'umrah_refund', ?, NOW(), ?, ?)";
        $stmt = $conn->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for umrah booking #$booking_id - $reason";
        $balance = ($currency === 'USD') ? $newUsdBalance : $newAfsBalance;
        $stmt->bind_param("iddssi",
            $booking['sold_to'],
            $refundInClientCurrency,
            $balance,
            $currency,
            $clientTransactionDescription,
            $refund_id,
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
            VALUES (?, 'Credit', ?, ?, ?, 'umrah_refund', ?, NOW(), ?, ?)";
        $stmt = $conn->prepare($clientTransactionQuery);
        $clientTransactionDescription = "Refund for umrah booking #$booking_id - $reason";
        $stmt->bind_param("idssi",
            $booking['sold_to'],
            $refund_amount,
            $currency,
            $clientTransactionDescription,
            $refund_id,
            $tenant_id,
            $branch_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to record client transaction");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Umrah booking refund processed successfully',
        'refund_id' => $refund_id,
        'new_profit' => $newProfit
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing refund: ' . $e->getMessage()
    ]);
}
?> 
