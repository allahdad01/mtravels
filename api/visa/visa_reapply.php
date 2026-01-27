<?php
// Enable CORS for API requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include required files
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!isset($data['action']) || $data['action'] !== 'reapply_visa') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

if (!isset($data['visa_id']) || !isset($data['new_status']) || !isset($data['reapply_reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Validate and fetch visa data
    $visa_id = intval($data['visa_id']);
    $new_status = $data['new_status'];
    $reapply_reason = $data['reapply_reason'];
    $base_amount = floatval($data['base_amount']);
    $sold_amount = floatval($data['sold_amount']);
    $currency = $data['currency'];
    $original_profit = floatval($data['sold_amount']) - floatval($data['base_amount']);
    // Validate status
    if (!in_array($new_status, ['Pending', 'Approved'])) {
        throw new PDOException('Invalid re-apply status');
    }

    // Get current visa data
    $visaQuery = "SELECT * FROM visa_applications WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($visaQuery);
    $stmt->bindParam(1, $visa_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $visa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visa) {
        throw new PDOException('Visa application not found');
    }

    // Check if visa is actually cancelled
    if (!in_array(strtolower($visa['status']), ['cancelled', 'rejected', 'withdrawn'])) {
        throw new PDOException('Only cancelled, rejected, or withdrawn visas can be re-applied');
    }

    // Update visa status and restore profit
    $update_query = "UPDATE visa_applications
                    SET status = ?,
                        profit = ?,
                        remarks = CONCAT(COALESCE(remarks, ''), '\n[RE-APPLIED] ', ?, ' | ', NOW()),
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?";

    $reapply_note = "Re-applied: " . $reapply_reason;
    $stmt = $pdo->prepare($update_query);
    $stmt->bindParam(1, $new_status, PDO::PARAM_STR);
    $stmt->bindParam(2, $original_profit, PDO::PARAM_STR);
    $stmt->bindParam(3, $reapply_note, PDO::PARAM_STR);
    $stmt->bindParam(4, $visa_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new PDOException('Failed to update visa status');
    }

    // Handle balance restorations (reverse the cancellation logic)

    // 1. Restore supplier balance for external suppliers
    if ($visa['base'] > 0) {
        // Get supplier details
        $supplierQuery = $pdo->prepare("SELECT supplier_type, balance, name FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $supplierQuery->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
        $supplierQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $supplierQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
        $supplierQuery->execute();
        $supplier = $supplierQuery->fetch(PDO::FETCH_ASSOC);

        if ($supplier) {
            try {
                // For external suppliers, we need to deduct the base amount (reverse the cancellation)
                if ($supplier['supplier_type'] === 'External') {
                    // Update supplier balance (deduct the base amount)
                    $newBalance = $supplier['balance'] - $visa['base'];
                    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmt = $pdo->prepare($updateSupplierQuery);
                    $stmt->bindParam(1, $newBalance, PDO::PARAM_STR);
                    $stmt->bindParam(2, $visa['supplier'], PDO::PARAM_INT);
                    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmt->execute();
                }

                // Record supplier transaction
                $supplierTransactionQuery = "INSERT INTO supplier_transactions
                    (supplier_id, transaction_type, amount, balance, remarks, transaction_of, reference_id, tenant_id, branch_id)
                    VALUES (?, 'Debit', ?, ?, ?, 'visa_sale', ?, ?, ?)";
                $stmt = $pdo->prepare($supplierTransactionQuery);
                $supplierTransactionDescription = "Re-application reversal for visa #$visa_id - $reapply_reason";
                $balance = ($supplier['supplier_type'] === 'External') ? $newBalance : $supplier['balance'];
                $stmt->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
                $stmt->bindParam(2, $visa['base'], PDO::PARAM_STR);
                $stmt->bindParam(3, $balance, PDO::PARAM_STR);
                $stmt->bindParam(4, $supplierTransactionDescription, PDO::PARAM_STR);
                $stmt->bindParam(5, $visa_id, PDO::PARAM_INT);
                $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new PDOException("Failed to record supplier transaction");
                }
            } catch (PDOException $e) {
                throw new PDOException("Supplier balance restoration failed: " . $e->getMessage());
            }
        }
        $supplierQuery->close();
    }

    // 2. Restore client balance for regular clients (reverse the cancellation)
    if ($visa['sold'] > 0) {
        // Get client details and type
        $clientQuery = $pdo->prepare("SELECT client_type, usd_balance, afs_balance, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $clientQuery->bindParam(1, $visa['sold_to'], PDO::PARAM_INT);
        $clientQuery->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $clientQuery->bindParam(3, $branch_id, PDO::PARAM_INT);
        $clientQuery->execute();
        $client = $clientQuery->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            try {
                // Handle client balance for regular clients (restore the original charge)
                if ($client['client_type'] === 'regular') {
                    // Calculate the amount in the appropriate currency
                    $restoreInClientCurrency = $visa['sold'];

                    // Update client balance based on currency (restore the original charge)
                    if ($visa['currency'] === 'USD') {
                        $newUsdBalance = $client['usd_balance'] + $restoreInClientCurrency;
                        $updateClientQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmt = $pdo->prepare($updateClientQuery);
                        $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
                        $stmt->bindParam(2, $visa['sold_to'], PDO::PARAM_INT);
                        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    } else {
                       $newAfsBalance = $client['afs_balance'] + $restoreInClientCurrency;
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

                    // Record client transaction (credit to restore the original charge)
                    $clientTransactionQuery = "INSERT INTO client_transactions
                        (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
                        VALUES (?, 'debit', ?, ?, ?, ?, 'visa_sale', ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Re-application reversal for visa #$visa_id - $reapply_reason";
                    $balance = ($visa['currency'] === 'USD') ? $newUsdBalance : $newAfsBalance;
                    $stmt->bindParam(1, $visa['sold_to'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $restoreInClientCurrency, PDO::PARAM_STR);
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
                        VALUES (?, 'debit', ?, ?, ?, 'visa_sale', ?, NOW(), ?, ?)";
                    $stmt = $pdo->prepare($clientTransactionQuery);
                    $clientTransactionDescription = "Re-application reversal for visa #$visa_id - $reapply_reason";
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
                throw new PDOException("Client balance restoration failed: " . $e->getMessage());
            }
        }
        $clientQuery->close();
    }

    // 3. Log activity
    try {
        // Prepare old and new values as JSON
        $old_values = json_encode([
            'status' => $visa['status'],
            'profit' => $visa['profit'],
            'remarks' => $visa['remarks']
        ]);

        $new_values = json_encode([
            'status' => $new_status,
            'profit' => $original_profit,
            'remarks' => "Re-applied: " . $reapply_reason
        ]);

        // Get client IP address and user agent
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $logQuery = "INSERT INTO activity_log
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                    VALUES (?, 'visa_reapply', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?, ?)";

        $stmt = $pdo->prepare($logQuery);
        $stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $visa_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->close();
    } catch (PDOException $e) {
        // Don't fail the entire operation for activity log failure
        error_log("Activity log failed: " . $e->getMessage());
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Visa re-applied successfully with balance restorations and profit restoration',
        'data' => [
            'visa_id' => $visa_id,
            'old_status' => $visa['status'],
            'new_status' => $new_status,
            'applicant_name' => $visa['applicant_name'],
            'old_profit' => $visa['profit'],
            'new_profit' => $original_profit,
            'balance_restorations' => [
                'supplier_amount' => $visa['base'],
                'client_amount' => $visa['sold'],
                'currency' => $visa['currency']
            ]
        ]
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>
