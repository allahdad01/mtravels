<?php
require_once('../../includes/db.php');
require_once('../../admin/security.php');
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
if (!isset($_POST['visa_id'], $_POST['sold'],
          $_POST['supplier_penalty'], $_POST['service_penalty'], $_POST['description'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$visaId = filter_input(INPUT_POST, 'visa_id', FILTER_VALIDATE_INT);
$sold = filter_input(INPUT_POST, 'sold', FILTER_VALIDATE_FLOAT);
$supplierPenalty = filter_input(INPUT_POST, 'supplier_penalty', FILTER_VALIDATE_FLOAT);
$servicePenalty = filter_input(INPUT_POST, 'service_penalty', FILTER_VALIDATE_FLOAT);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$visaId || !$sold || $supplierPenalty === false || $servicePenalty === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

try {
    // Fetch visa data
    $stmt = $pdo->prepare("SELECT * FROM visa_applications WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $visaId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $visa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visa) {
        throw new Exception('Visa application not found');
    }

    // Prevent double refund
    if (strtolower($visa['status']) === 'refunded') {
        echo json_encode(['success' => false, 'message' => 'This visa has already been refunded.']);
        exit;
    }

    $currency = $visa['currency'];
    $soldToId = $visa['sold_to'];

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

    // Calculate refund amounts
    $base = floatval($visa['base'] ?? 0);
    $refundToSupplier = $base - $supplierPenalty;
    $refundToCustomer = $sold - ($supplierPenalty + $servicePenalty);

    if ($refundToCustomer < 0) {
        throw new Exception('Refund amount cannot be negative.');
    }

    $pdo->beginTransaction();

    // Determine refund type
    $refundType = ($refundToCustomer >= $sold) ? 'full' : 'partial';

    // Insert refund record
    $insertRefundStmt = $pdo->prepare("INSERT INTO visa_refunds
        (visa_id, refund_type, refund_amount, supplier_penalty, service_penalty, base, sold, reason, description, currency, processed_by, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertRefundStmt->bindParam(1, $visaId, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(2, $refundType, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(3, $refundToCustomer, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(4, $supplierPenalty, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(5, $servicePenalty, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(6, $base, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(7, $sold, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(8, $description, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(9, $description, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(10, $currency, PDO::PARAM_STR);
    $insertRefundStmt->bindParam(11, $user_id, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(12, $tenant_id, PDO::PARAM_INT);
    $insertRefundStmt->bindParam(13, $branch_id, PDO::PARAM_INT);
    if (!$insertRefundStmt->execute()) {
        throw new Exception('Failed to insert refund record');
    }
    $refundId = $pdo->lastInsertId();

    // Get supplier details
    $stmtSupplier = $pdo->prepare("SELECT balance, currency, name, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtSupplier->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
    $stmtSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmtSupplier->execute();
    $supplierResult = $stmtSupplier->fetch(PDO::FETCH_ASSOC);
    if (!$supplierResult) {
        throw new Exception("Supplier not found");
    }

    $currentSupplierBalance = $supplierResult['balance'];
    $supplierType = $supplierResult['supplier_type'];

    // Handle supplier balance and transaction
    if ($supplierType === 'External') {
        $newSupplierBalance = $currentSupplierBalance + $refundToSupplier;
        $updateStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
        $updateStmt->bindParam(2, $visa['supplier'], PDO::PARAM_INT);
        $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update supplier balance");
        }

        $insertTransStmt = $pdo->prepare("INSERT INTO supplier_transactions
            (transaction_date, supplier_id, reference_id, amount, balance, transaction_type, remarks, transaction_of, tenant_id, branch_id)
            VALUES (NOW(), ?, ?, ?, ?, 'credit', ?, 'visa_refund', ?, ?)");
        $supplierRemarks = "Refund for visa application #$visaId - $description";
        $insertTransStmt->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
        $insertTransStmt->bindParam(2, $refundId, PDO::PARAM_INT);
        $insertTransStmt->bindParam(3, $refundToSupplier, PDO::PARAM_STR);
        $insertTransStmt->bindParam(4, $newSupplierBalance, PDO::PARAM_STR);
        $insertTransStmt->bindParam(5, $supplierRemarks, PDO::PARAM_STR);
        $insertTransStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $insertTransStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    } else {
        $insertTransStmt = $pdo->prepare("INSERT INTO supplier_transactions
            (transaction_date, supplier_id, reference_id, amount, transaction_type, remarks, transaction_of, tenant_id, branch_id, balance)
            VALUES (NOW(), ?, ?, ?, 'credit', ?, 'visa_refund', ?, ?, 0)");
        $supplierRemarks = "Refund for visa application #$visaId - $description";
        $insertTransStmt->bindParam(1, $visa['supplier'], PDO::PARAM_INT);
        $insertTransStmt->bindParam(2, $refundId, PDO::PARAM_INT);
        $insertTransStmt->bindParam(3, $refundToSupplier, PDO::PARAM_STR);
        $insertTransStmt->bindParam(4, $supplierRemarks, PDO::PARAM_STR);
        $insertTransStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $insertTransStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    }
    if (!$insertTransStmt->execute()) {
        throw new Exception("Failed to record supplier transaction");
    }

    // Process client refund
    if ($clientType === 'regular') {
        $balanceField = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
        $updateClientStmt = $pdo->prepare("UPDATE clients SET {$balanceField} = {$balanceField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateClientStmt->bindParam(1, $refundToCustomer, PDO::PARAM_STR);
        $updateClientStmt->bindParam(2, $soldToId, PDO::PARAM_INT);
        $updateClientStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateClientStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updateClientStmt->execute()) {
            throw new Exception('Failed to update client balance');
        }

        $currentClientBalance = ($currency === 'USD') ? $clientResult['usd_balance'] : $clientResult['afs_balance'];
        $newClientBalance = $currentClientBalance + $refundToCustomer;

        $insertClientTransStmt = $pdo->prepare("INSERT INTO client_transactions
            (client_id, type, amount, balance, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id)
            VALUES (?, 'Credit', ?, ?, ?, ?, 'visa_refund', ?, NOW(), ?, ?)");
        $clientDesc = "Refund for visa application - $description";
        $insertClientTransStmt->bindParam(1, $soldToId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(2, $refundToCustomer, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(3, $newClientBalance, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(4, $currency, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(5, $clientDesc, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(6, $refundId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        if (!$insertClientTransStmt->execute()) {
            throw new Exception('Failed to record client transaction');
        }
    } else {
        $insertClientTransStmt = $pdo->prepare("INSERT INTO client_transactions
            (client_id, type, amount, currency, description, transaction_of, reference_id, created_at, tenant_id, branch_id, balance)
            VALUES (?, 'Credit', ?, ?, ?, 'visa_refund', ?, NOW(), ?, ?, 0)");
        $clientDesc = "Refund for visa application - $description";
        $insertClientTransStmt->bindParam(1, $soldToId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(2, $refundToCustomer, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(3, $currency, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(4, $clientDesc, PDO::PARAM_STR);
        $insertClientTransStmt->bindParam(5, $refundId, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $insertClientTransStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        if (!$insertClientTransStmt->execute()) {
            throw new Exception('Failed to record client transaction');
        }
    }

    // Update visa profit and status
    $newProfit = $servicePenalty;
    $updateVisaStmt = $pdo->prepare("UPDATE visa_applications SET profit = ?, status = 'refunded' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateVisaStmt->bindParam(1, $newProfit, PDO::PARAM_STR);
    $updateVisaStmt->bindParam(2, $visaId, PDO::PARAM_INT);
    $updateVisaStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateVisaStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$updateVisaStmt->execute()) {
        throw new Exception('Failed to update visa status');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully',
        'refund_id' => $refundId,
        'refund_amount' => $refundToCustomer
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error processing refund: ' . $e->getMessage()]);
}
