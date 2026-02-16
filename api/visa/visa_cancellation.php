<?php
// Include security module
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../../includes/db.php');

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['visa_id']) || !isset($input['new_status']) || !isset($input['cancellation_reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$visa_id = intval($input['visa_id']);
$new_status = $input['new_status'];
$cancellation_reason = $input['cancellation_reason'];
$current_status = isset($input['current_status']) ? $input['current_status'] : '';

try {
    // Start transaction
    $pdo->begin_transaction();

    // Check if visa exists and belongs to current tenant
    $check_query = "SELECT supplier, sold_to, id, status, applicant_name, base, sold, profit, currency, tenant_id
                   FROM visa_applications
                   WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($check_query);
    $stmt->bindParam(1, $visa_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $visa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visa) {
        throw new PDOException('Visa not found or access denied');
    }
    $stmt->close();

    // Check if visa is already cancelled/rejected
    if (in_array(strtolower($visa['status']), ['cancelled', 'rejected', 'withdrawn'])) {
        throw new PDOException('Visa is already ' . strtolower($visa['status']));
    }

    // Log the cancellation attempt
    // Prepare old and new values as JSON
    $log_old_values = json_encode(['status' => $visa['status']]);
    $log_new_values = json_encode([
        'status' => $new_status,
        'cancellation_reason' => $cancellation_reason,
        'cancelled_by' => $_SESSION['user_id'],
        'cancelled_at' => date('Y-m-d H:i:s')
    ]);

    // Get client IP address and user agent
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $log_query = "INSERT INTO activity_log
                 (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                 VALUES (?, 'visa_cancellation', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?, ?)";

    $stmt = $pdo->prepare($log_query);
    $stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(2, $visa_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $log_old_values, PDO::PARAM_STR);
    $stmt->bindParam(4, $log_new_values, PDO::PARAM_STR);
    $stmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->close();

    // Update visa status and set profit to 0 (like refund logic)
    $update_query = "UPDATE visa_applications
                    SET status = ?,
                        profit = 0,
                        remarks = CONCAT(COALESCE(remarks, ''), '\n[CANCELLED] ', ?, ' | ', NOW()),
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?";

    $cancellation_note = "Cancelled: " . $cancellation_reason;
    $stmt = $pdo->prepare($update_query);
    $stmt->bindParam(1, $new_status, PDO::PARAM_STR);
    $stmt->bindParam(2, $cancellation_note, PDO::PARAM_STR);
    $stmt->bindParam(3, $visa_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new PDOException('Failed to update visa status');
    }

    $stmt->close();

    // Only reverse balances if visa was approved (had transactions)
    // Pending visas that are cancelled never had transactions, so nothing to reverse
    $visaWasApproved = ($visa['status'] === 'Approved');

    if ($visaWasApproved) {
        // Handle supplier balance reversal for External suppliers
        if ($visa['base'] > 0) {
        // Get supplier details
        $supplierQuery = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $supplierQuery->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
        $supplierQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $supplierQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
        $supplierQuery->execute();
        $supplierResult = $supplierQuery->fetch(PDO::FETCH_ASSOC);

        if ($supplierResult) {
            $supplier = $supplierResult;

            try {
                // Handle supplier balance for External suppliers (reverse the original payment)
                if ($supplier['supplier_type'] === 'External') {
                    // Reverse the original base amount
                    $supplierRefundAmount = $visa['base'];

                    // Update supplier balance (add back the amount that was originally deducted)
                    $newSupplierBalance = $supplier['balance'] + $supplierRefundAmount;
                    $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateSupplierStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
                    $updateSupplierStmt->bindParam(2, $visa['supplier'], PDO::PARAM_INT);
                    $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                    if (!$updateSupplierStmt->execute()) {
                        throw new PDOException("Failed to update supplier balance");
                    }

                    // Record supplier transaction
                    $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
                        (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id, branch_id)
                        VALUES (NOW(), ?, ?, ?, ?, 'Credit', ?, 'visa_cancellation', ?, ?)");
                    $supplierRemarks = "Cancellation reversal for visa application #$visa_id - " . $cancellation_reason;
                    $insertSupplierTransactionStmt->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(2, $visa_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(3, $supplierRefundAmount, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(4, $newSupplierBalance, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(5, $supplierRemarks, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new PDOException("Failed to record supplier transaction");
                    }
                } else {
                    // Record supplier transaction without balance for non-External suppliers
                    $insertSupplierTransactionStmt = $pdo->prepare("INSERT INTO supplier_transactions
                        (transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, tenant_id, branch_id)
                        VALUES (NOW(), ?, ?, ?, 'Credit', ?, 'visa_cancellation', ?, ?)");
                    $supplierRemarks = "Cancellation reversal for visa application #$visa_id - " . $cancellation_reason;
                    $insertSupplierTransactionStmt->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(2, $visa_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(3, $visa['base'], PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(4, $supplierRemarks, PDO::PARAM_STR);
                    $insertSupplierTransactionStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
                    $insertSupplierTransactionStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
                    if (!$insertSupplierTransactionStmt->execute()) {
                        throw new PDOException("Failed to record supplier transaction");
                    }
                }
            } catch (PDOException $e) {
                throw new PDOException("Supplier balance reversal failed: " . $e->getMessage());
            }
        }
        $supplierQuery->close();
    }

    // Handle client balance reversal for regular clients
    if ($visa['sold'] > 0) {
        // Get client details and type
        $clientQuery = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $clientQuery->bindParam(1, $visa['sold_to'], PDO::PARAM_INT);
        $clientQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $clientQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
        $clientQuery->execute();
        $clientResult = $clientQuery->fetch(PDO::FETCH_ASSOC);

        if ($clientResult) {
            $client = $clientResult;

            try {
                // Handle client balance for regular clients (reverse the original sale)
                if ($client['client_type'] === 'regular') {
                    // Calculate the amount in the appropriate currency
                    $refundInClientCurrency = $visa['sold'];

                    // Update client balance based on currency (reverse the original sale)
                    if ($visa['currency'] === 'USD') {
                        $newUsdBalance = $client['usd_balance'] - $refundInClientCurrency;
                        $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmt = $pdo->prepare($updateClientQuery);
                        $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
                        $stmt->bindParam(2, $visa['sold_to'], PDO::PARAM_INT);
                        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    } else {
                       $newAfsBalance = $client['afs_balance'] - $refundInClientCurrency;
                       $updateClientQuery = "UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                       $stmt = $pdo->prepare($updateClientQuery);
                       $stmt->bindParam(1, $newAfsBalance, PDO::PARAM_STR);
                       $stmt->bindParam(2, $visa['sold_to'], PDO::PARAM_INT);
                       $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                       $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    }
                    if (!$stmt->execute()) {
                        throw new PDOException("Failed to update client balance");
                    }

                    // Record client transaction (debit to reverse the original sale)
                    $clientTransactionQuery = "INSERT INTO client_transactions
                        (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                        VALUES (?, 'Debit', ?, ?, ?, ?, 'visa_cancellation', ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Cancellation reversal for visa application #$visa_id - $cancellation_reason";
                    $balance = ($visa['currency'] === 'USD') ? $newUsdBalance : $newAfsBalance;
                    $stmt->bindParam(1, $visa['sold_to'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $refundInClientCurrency, PDO::PARAM_STR);
                    $stmt->bindParam(3, $balance, PDO::PARAM_STR);
                    $stmt->bindParam(4, $visa['currency'], PDO::PARAM_STR);
                    $stmt->bindParam(5, $clientTransactionDescription, PDO::PARAM_STR);
                    $stmt->bindParam(6, $visa_id, PDO::PARAM_INT);
                    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
                    if (!$stmt->execute()) {
                        throw new PDOException("Failed to record client transaction");
                    }
                } else {
                    // Record client transaction for agency clients without balance
                    $clientTransactionQuery = "INSERT INTO client_transactions
                        (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                        VALUES (?, 'Debit', ?, ?, ?, 'visa_cancellation', ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Cancellation reversal for visa application #$visa_id - $cancellation_reason";
                    $stmt->bindParam(1, $visa['sold_to'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $visa['sold'], PDO::PARAM_STR);
                    $stmt->bindParam(3, $visa['currency'], PDO::PARAM_STR);
                    $stmt->bindParam(4, $clientTransactionDescription, PDO::PARAM_STR);
                    $stmt->bindParam(5, $visa_id, PDO::PARAM_INT);
                    $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                    if (!$stmt->execute()) {
                        throw new PDOException("Failed to record client transaction");
                    }
                }
            } catch (PDOException $e) {
                throw new PDOException("Client balance reversal failed: " . $e->getMessage());
            }
        }
        $clientQuery->close();
        }
        } // End of: if ($visaWasApproved)

        // Commit transaction
        $pdo->commit();

        $message = $visaWasApproved
        ? 'Visa cancelled successfully with balance reversals and profit reset'
        : 'Visa cancelled successfully. No balance reversals as visa was not approved.';

        echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'visa_id' => $visa_id,
            'old_status' => $visa['status'],
            'new_status' => $new_status,
            'applicant_name' => $visa['applicant_name'],
            'old_profit' => $visa['profit'],
            'new_profit' => 0,

        ]
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
?>