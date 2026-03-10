<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include database connection
require_once('../../includes/db.php');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $id = $_POST['visa_id'] ?? 0;
    $supplier = $_POST['supplier'] ?? 0;
    $sold_to = $_POST['sold_to'] ?? 0;
    $title = $_POST['title'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $applicant_name = $_POST['applicant_name'] ?? '';
    $passport_number = $_POST['passport_number'] ?? '';
    $country = $_POST['country'] ?? '';
    $visa_type = $_POST['visa_type'] ?? '';
    $receive_date = !empty($_POST['receive_date']) ? $_POST['receive_date'] : NULL;
    $applied_date = !empty($_POST['applied_date']) ? $_POST['applied_date'] : NULL;
    $issued_date = !empty($_POST['issued_date']) ? $_POST['issued_date'] : NULL;
    $base = floatval($_POST['base'] ?? 0);
    $sold = floatval($_POST['sold'] ?? 0);
    $profit = floatval($_POST['profit'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // Validate required fields
    if (!$id || !$supplier || !$sold_to || !$applicant_name || !$passport_number || !$country || !$visa_type) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
         // Get original data for comparison
         $stmt = $pdo->prepare("SELECT * FROM visa_applications WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->bindParam(1, $id, PDO::PARAM_INT);
         $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
         $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
         $stmt->execute();
         $originalData = $stmt->fetch(PDO::FETCH_ASSOC);

         if (!$originalData) {
             throw new PDOException("Visa application not found");
         }

         // Calculate differences
         $soldDifference = $sold - $originalData['sold'];
         $baseDifference = $base - $originalData['base'];
         $sold_to = intval($sold_to);
         $supplier = intval($supplier);
         $originalClient = intval($originalData['sold_to']);
         $originalSupplier = intval($originalData['supplier']);
         $originalCurrency = $originalData['currency'];
         $visaStatus = $originalData['status'];

         // Only process client and supplier calculations if visa is approved
         if ($visaStatus === 'Approved') {
         // Handle supplier changes or base amount differences
         if ($supplier != $originalSupplier || $baseDifference != 0) {
             // Check if original supplier exists
             if ($originalSupplier > 0) {
                 $oldSupplierQuery = "SELECT supplier_type, balance, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtOldSupplier = $pdo->prepare($oldSupplierQuery);
                 $stmtOldSupplier->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                 $stmtOldSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtOldSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtOldSupplier->execute();
                 $oldSupplierData = $stmtOldSupplier->fetch(PDO::FETCH_ASSOC);

                 $oldSupplierType = isset($oldSupplierData['supplier_type']) ? $oldSupplierData['supplier_type'] : '';

                 // If supplier changed and old supplier was external
                 if ($supplier != $originalSupplier && $oldSupplierType === 'External') {
                     // Update old supplier balance - ADDING back the original base amount
                     // This INCREASES the supplier balance (supplier gets their money back)
                     $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmtUpdateOldSupplier = $pdo->prepare($updateOldSupplierQuery);
                     $stmtUpdateOldSupplier->bindParam(1, $originalData['base'], PDO::PARAM_STR);
                     $stmtUpdateOldSupplier->bindParam(2, $originalSupplier, PDO::PARAM_INT);
                     $stmtUpdateOldSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmtUpdateOldSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                     $stmtUpdateOldSupplier->execute();

                     // Check if transaction record exists for old supplier
                     $checkOldSupplierTransactionQuery = "SELECT id FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                     $stmtCheckOldSupplierTransaction = $pdo->prepare($checkOldSupplierTransactionQuery);
                     $stmtCheckOldSupplierTransaction->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                     $stmtCheckOldSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                     $stmtCheckOldSupplierTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmtCheckOldSupplierTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                     $stmtCheckOldSupplierTransaction->execute();
                     $oldSupplierTransactionResult = $stmtCheckOldSupplierTransaction->fetch(PDO::FETCH_ASSOC);
                     $oldSupplierTransactionExists = !empty($oldSupplierTransactionResult);

                     if ($oldSupplierTransactionExists) {
                         // Get transaction details before deleting
                         $getOldSupplierTransactionQuery = "SELECT id, created_at, balance, amount FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                         $stmtGetOldSupplierTransaction = $pdo->prepare($getOldSupplierTransactionQuery);
                         $stmtGetOldSupplierTransaction->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                         $stmtGetOldSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtGetOldSupplierTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtGetOldSupplierTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtGetOldSupplierTransaction->execute();
                         $oldSupplierTransactionData = $stmtGetOldSupplierTransaction->fetch(PDO::FETCH_ASSOC);

                         if ($oldSupplierTransactionData) {
                             // Update all subsequent transactions' balances
                             // Since we're removing a debit transaction, we need to decrease subsequent balances
                             $updateSubsequentQuery = "UPDATE supplier_transactions
                                                       SET balance = balance + ?
                                                       WHERE supplier_id = ?
                                                       AND id > ?
                                                       AND currency = ?
                                                       AND id != ? AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                             $transactionAmount = abs($oldSupplierTransactionData['amount']); // Make sure it's positive
                             $stmtUpdateSubsequent->bindParam(1, $transactionAmount, PDO::PARAM_STR);
                             $stmtUpdateSubsequent->bindParam(2, $originalSupplier, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(3, $oldSupplierTransactionData['id'], PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(4, $originalCurrency, PDO::PARAM_STR);
                             $stmtUpdateSubsequent->bindParam(5, $oldSupplierTransactionData['id'], PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->execute();
                         }

                         // Delete the transaction record
                         $updateOldSupplierTransactionQuery = "DELETE FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateOldSupplierTransaction = $pdo->prepare($updateOldSupplierTransactionQuery);
                         $stmtUpdateOldSupplierTransaction->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                         $stmtUpdateOldSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtUpdateOldSupplierTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateOldSupplierTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateOldSupplierTransaction->execute();
                     }
                 }
             }

             // Check if new supplier exists
             if ($supplier > 0) {
                 $supplierQuery = "SELECT supplier_type, balance, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtSupplier = $pdo->prepare($supplierQuery);
                 $stmtSupplier->bindParam(1, $supplier, PDO::PARAM_INT);
                 $stmtSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtSupplier->execute();
                 $supplierData = $stmtSupplier->fetch(PDO::FETCH_ASSOC);

                 $supplierType = isset($supplierData['supplier_type']) ? $supplierData['supplier_type'] : '';

                 if ($supplierType === 'External') {
                     // If supplier changed
                     if ($supplier != $originalSupplier) {
                         // Update new supplier balance - DEDUCTING the new base amount
                         // This DECREASES the supplier balance (supplier gives us their service)
                         $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
                         $stmtUpdateSupplier->bindParam(1, $base, PDO::PARAM_STR);
                         $stmtUpdateSupplier->bindParam(2, $supplier, PDO::PARAM_INT);
                         $stmtUpdateSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateSupplier->execute();

                         // Create new transaction record for new supplier
                         $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'visa_sale', ?, ?)";
                         $stmtInsertSupplierTransaction = $pdo->prepare($insertSupplierTransactionQuery);
                         $description = "Sale for visa: $applicant_name (Passport: $passport_number)";
                         $newBalance = $supplierData['balance'] - $base;
                         $stmtInsertSupplierTransaction->bindParam(1, $supplier, PDO::PARAM_INT);
                         $stmtInsertSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtInsertSupplierTransaction->bindParam(3, $base, PDO::PARAM_STR);
                         $stmtInsertSupplierTransaction->bindParam(4, $currency, PDO::PARAM_STR);
                         $stmtInsertSupplierTransaction->bindParam(5, $newBalance, PDO::PARAM_STR);
                         $stmtInsertSupplierTransaction->bindParam(6, $description, PDO::PARAM_STR);
                         $stmtInsertSupplierTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
                         $stmtInsertSupplierTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
                         $stmtInsertSupplierTransaction->execute();
                     }
                     // Same supplier but base amount changed
                     else if ($baseDifference != 0) {
                         // Update supplier balance based on base difference
                         if ($baseDifference > 0) {
                             // Base amount increased, deduct more from supplier
                             $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $balanceChange = $baseDifference;
                         } else {
                             // Base amount decreased, add back to supplier
                             $updateSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $balanceChange = abs($baseDifference);
                         }

                         $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
                         $stmtUpdateSupplier->bindParam(1, $balanceChange, PDO::PARAM_STR);
                         $stmtUpdateSupplier->bindParam(2, $supplier, PDO::PARAM_INT);
                         $stmtUpdateSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateSupplier->execute();

                         // Check if transaction record exists for this supplier
                         $checkSupplierTransactionQuery = "SELECT id, created_at, balance, amount FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                         $stmtCheckSupplierTransaction = $pdo->prepare($checkSupplierTransactionQuery);
                         $stmtCheckSupplierTransaction->bindParam(1, $supplier, PDO::PARAM_INT);
                         $stmtCheckSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtCheckSupplierTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtCheckSupplierTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtCheckSupplierTransaction->execute();
                         $supplierTransactionResult = $stmtCheckSupplierTransaction->fetch(PDO::FETCH_ASSOC);
                         $transactionRow = $supplierTransactionResult;

                         if ($transactionRow) {
                             $transactionId = $transactionRow['id'];
                             $transactionDate = $transactionRow['created_at'];
                             $currentTransactionBalance = $transactionRow['balance'];
                             $currentTransactionAmount = abs($transactionRow['amount']); // Ensure positive value

                             // Calculate the difference between the new base amount and the current transaction amount
                             $amountDifference = $base - $currentTransactionAmount;

                             // Calculate the new balance for this transaction
                             // If amount increased, balance should decrease by the difference
                             // If amount decreased, balance should increase by the difference
                             $newTransactionBalance = $currentTransactionBalance - $amountDifference;

                             // Update amount field to new base price (as negative value for debit)
                             $negativeAmount = -1 * abs($base);
                             $updateSupplierAmountQuery = "UPDATE supplier_transactions SET amount = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateSupplierAmount = $pdo->prepare($updateSupplierAmountQuery);
                             $stmtUpdateSupplierAmount->bindParam(1, $negativeAmount, PDO::PARAM_STR);
                             $stmtUpdateSupplierAmount->bindParam(2, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateSupplierAmount->bindParam(3, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateSupplierAmount->bindParam(4, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateSupplierAmount->execute();

                             // Update existing transaction record with adjusted balance
                             $updateSupplierTransactionQuery = "UPDATE supplier_transactions SET balance = ?, description = CONCAT('Updated: ', description) WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateSupplierTransaction = $pdo->prepare($updateSupplierTransactionQuery);
                             $stmtUpdateSupplierTransaction->bindParam(1, $newTransactionBalance, PDO::PARAM_STR);
                             $stmtUpdateSupplierTransaction->bindParam(2, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateSupplierTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateSupplierTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateSupplierTransaction->execute();

                             // Update all subsequent transactions' balances
                             // If amount increased, decrease subsequent balances
                             // If amount decreased, increase subsequent balances
                             if ($amountDifference > 0) {
                                 // Amount increased, decrease subsequent balances
                                 $updateSubsequentQuery = "UPDATE supplier_transactions
                                                           SET balance = balance - ?
                                                           WHERE supplier_id = ?
                                                           AND id > ?
                                                           AND currency = ?
                                                           AND id != ? AND tenant_id = ? AND branch_id = ?";
                             } else {
                                 // Amount decreased, increase subsequent balances
                                 $updateSubsequentQuery = "UPDATE supplier_transactions
                                                           SET balance = balance + ?
                                                           WHERE supplier_id = ?
                                                           AND id > ?
                                                           AND currency = ?
                                                           AND id != ? AND tenant_id = ? AND branch_id = ?";
                             }

                             $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                             $absAmountDifference = abs($amountDifference);
                             $stmtUpdateSubsequent->bindParam(1, $absAmountDifference, PDO::PARAM_STR);
                             $stmtUpdateSubsequent->bindParam(2, $supplier, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(3, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(4, $originalCurrency, PDO::PARAM_STR);
                             $stmtUpdateSubsequent->bindParam(5, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->execute();
                         }
                     }
                 }
             }
         }

         // Handle client changes or sold amount differences
         if ($sold_to != $originalClient || $soldDifference != 0) {
             // Check if original client exists
             if ($originalClient > 0) {
                 $balanceField = strtolower($originalCurrency) === 'usd' ? 'usd_balance' : 'afs_balance';
                 $oldClientQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtOldClient = $pdo->prepare($oldClientQuery);
                 $stmtOldClient->bindParam(1, $originalClient, PDO::PARAM_INT);
                 $stmtOldClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtOldClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtOldClient->execute();
                 $oldClientData = $stmtOldClient->fetch(PDO::FETCH_ASSOC);

                 if ($oldClientData) {
                     // If client changed
                     if ($sold_to != $originalClient) {
                         // Update old client balance - ADDING back the original sold amount
                         // This INCREASES the client balance (client owes less)
                         $balanceField = strtolower($originalCurrency) === 'usd' ? 'usd_balance' : 'afs_balance';
                         $updateOldClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateOldClient = $pdo->prepare($updateOldClientQuery);
                         $stmtUpdateOldClient->bindParam(1, $originalData['sold'], PDO::PARAM_STR);
                         $stmtUpdateOldClient->bindParam(2, $originalClient, PDO::PARAM_INT);
                         $stmtUpdateOldClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateOldClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateOldClient->execute();

                         // Check if transaction record exists for old client
                         $checkOldClientTransactionQuery = "SELECT id FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                         $stmtCheckOldClientTransaction = $pdo->prepare($checkOldClientTransactionQuery);
                         $stmtCheckOldClientTransaction->bindParam(1, $originalClient, PDO::PARAM_INT);
                         $stmtCheckOldClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtCheckOldClientTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtCheckOldClientTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtCheckOldClientTransaction->execute();
                         $oldClientTransactionResult = $stmtCheckOldClientTransaction->fetch(PDO::FETCH_ASSOC);
                         $oldClientTransactionExists = !empty($oldClientTransactionResult);

                         if ($oldClientTransactionExists) {
                             // Get transaction details before deleting
                             $getOldClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                             $stmtGetOldClientTransaction = $pdo->prepare($getOldClientTransactionQuery);
                             $stmtGetOldClientTransaction->bindParam(1, $originalClient, PDO::PARAM_INT);
                             $stmtGetOldClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                             $stmtGetOldClientTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                             $stmtGetOldClientTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                             $stmtGetOldClientTransaction->execute();
                             $oldClientTransactionData = $stmtGetOldClientTransaction->fetch(PDO::FETCH_ASSOC);

                             if ($oldClientTransactionData) {
                                 // Update all subsequent transactions' balances
                                 // Since we're removing a debit transaction, we need to increase subsequent balances
                                 $updateSubsequentQuery = "UPDATE client_transactions
                                                           SET balance = balance + ?
                                                           WHERE client_id = ?
                                                           AND id > ?
                                                           AND currency = ?
                                                           AND id != ? AND tenant_id = ? AND branch_id = ?";
                                 $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                                 $transactionAmount = abs($oldClientTransactionData['amount']); // Make sure it's positive
                                 $stmtUpdateSubsequent->bindParam(1, $transactionAmount, PDO::PARAM_STR);
                                 $stmtUpdateSubsequent->bindParam(2, $originalClient, PDO::PARAM_INT);
                                 $stmtUpdateSubsequent->bindParam(3, $oldClientTransactionData['id'], PDO::PARAM_INT);
                                 $stmtUpdateSubsequent->bindParam(4, $originalCurrency, PDO::PARAM_STR);
                                 $stmtUpdateSubsequent->bindParam(5, $oldClientTransactionData['id'], PDO::PARAM_INT);
                                 $stmtUpdateSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                                 $stmtUpdateSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                                 $stmtUpdateSubsequent->execute();
                             }

                             // Delete the transaction record
                             $updateOldClientTransactionQuery = "DELETE FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateOldClientTransaction = $pdo->prepare($updateOldClientTransactionQuery);
                             $stmtUpdateOldClientTransaction->bindParam(1, $originalClient, PDO::PARAM_INT);
                             $stmtUpdateOldClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                             $stmtUpdateOldClientTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateOldClientTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateOldClientTransaction->execute();
                         }
                     }
                 }
             }

             // Check if new client exists
             if ($sold_to > 0) {
                 $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                 $clientQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtClient = $pdo->prepare($clientQuery);
                 $stmtClient->bindParam(1, $sold_to, PDO::PARAM_INT);
                 $stmtClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtClient->execute();
                 $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);

                 if ($clientData) {
                     // If client changed
                     if ($sold_to != $originalClient) {
                         // Update new client balance - DEDUCTING the new sold amount
                         // This DECREASES the client balance (client owes more)
                         $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                         $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                         $stmtUpdateClient->bindParam(1, $sold, PDO::PARAM_STR);
                         $stmtUpdateClient->bindParam(2, $sold_to, PDO::PARAM_INT);
                         $stmtUpdateClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateClient->execute();

                         // Get current client balance for transaction record
                         $getBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetBalance = $pdo->prepare($getBalanceQuery);
                         $stmtGetBalance->bindParam(1, $sold_to, PDO::PARAM_INT);
                         $stmtGetBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                         $stmtGetBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                         $stmtGetBalance->execute();
                         $balanceResult = $stmtGetBalance->fetch(PDO::FETCH_ASSOC);

                         // Create new transaction record for new client
                         $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'visa_sale', ?, ?)";
                         $stmtInsertClientTransaction = $pdo->prepare($insertClientTransactionQuery);
                         $description = "Sale for visa: $applicant_name (Passport: $passport_number)";
                         $stmtInsertClientTransaction->bindParam(1, $sold_to, PDO::PARAM_INT);
                         $stmtInsertClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtInsertClientTransaction->bindParam(3, $sold, PDO::PARAM_STR);
                         $stmtInsertClientTransaction->bindParam(4, $currency, PDO::PARAM_STR);
                         $stmtInsertClientTransaction->bindParam(5, $newBalance, PDO::PARAM_STR);
                         $stmtInsertClientTransaction->bindParam(6, $description, PDO::PARAM_STR);
                         $stmtInsertClientTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
                         $stmtInsertClientTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
                         $stmtInsertClientTransaction->execute();
                     }
                     // Same client but sold price changed
                     else if ($soldDifference != 0) {
                         $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';

                         // Get current client balance before update
                         $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                         $stmtGetCurrentBalance->bindParam(1, $sold_to, PDO::PARAM_INT);
                         $stmtGetCurrentBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                         $stmtGetCurrentBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                         $stmtGetCurrentBalance->execute();
                         $currentBalanceResult = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC);
                         $currentBalance = $currentBalanceResult[$balanceField];

                         // Calculate new balance
                         $newBalance = 0;
                         if ($soldDifference > 0) {
                             // Sold price decreased, client owes less, balance increases
                             $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $newBalance = $currentBalance + $soldDifference;
                         } else {
                             // Sold price increased, client owes more, balance decreases
                             $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             // Make the difference positive for the query
                             $soldDifference = abs($soldDifference);
                             $newBalance = $currentBalance - $soldDifference;
                         }

                         $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                         $stmtUpdateClient->bindParam(1, $soldDifference, PDO::PARAM_STR);
                         $stmtUpdateClient->bindParam(2, $sold_to, PDO::PARAM_INT);
                         $stmtUpdateClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateClient->execute();

                         // Check if transaction record exists for this client
                         $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                         $stmtCheckClientTransaction = $pdo->prepare($checkClientTransactionQuery);
                         $stmtCheckClientTransaction->bindParam(1, $sold_to, PDO::PARAM_INT);
                         $stmtCheckClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                         $stmtCheckClientTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtCheckClientTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtCheckClientTransaction->execute();
                         $clientTransactionResult = $stmtCheckClientTransaction->fetch(PDO::FETCH_ASSOC);
                         $transactionRow = $clientTransactionResult;

                         if ($transactionRow) {
                             $transactionId = $transactionRow['id'];
                             $transactionDate = $transactionRow['created_at'];
                             $currentTransactionBalance = $transactionRow['balance'];
                             $currentTransactionAmount = abs($transactionRow['amount']); // Ensure positive value

                             // Calculate the difference between the new sold amount and the current transaction amount
                             $amountDifference = $sold - $currentTransactionAmount;

                             // Calculate the new balance for this transaction
                             // If amount increased, balance should decrease by the difference
                             // If amount decreased, balance should increase by the difference
                             $newTransactionBalance = $currentTransactionBalance - $amountDifference;

                             // Update amount field to new sold price (as negative value for debit)
                             $negativeAmount = -1 * abs($sold);
                             $updateClientAmountQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateClientAmount = $pdo->prepare($updateClientAmountQuery);
                             $stmtUpdateClientAmount->bindParam(1, $negativeAmount, PDO::PARAM_STR);
                             $stmtUpdateClientAmount->bindParam(2, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateClientAmount->bindParam(3, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateClientAmount->bindParam(4, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateClientAmount->execute();

                             // Update existing transaction record with adjusted balance
                             $updateClientTransactionQuery = "UPDATE client_transactions SET balance = ?, description = CONCAT('Updated: ', description) WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateClientTransaction = $pdo->prepare($updateClientTransactionQuery);
                             $stmtUpdateClientTransaction->bindParam(1, $newTransactionBalance, PDO::PARAM_STR);
                             $stmtUpdateClientTransaction->bindParam(2, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateClientTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateClientTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateClientTransaction->execute();

                             // Update all subsequent transactions' balances
                             // If amount increased, decrease subsequent balances
                             // If amount decreased, increase subsequent balances
                             if ($amountDifference > 0) {
                                 // Amount increased, decrease subsequent balances
                                 $updateSubsequentQuery = "UPDATE client_transactions
                                                           SET balance = balance - ?
                                                           WHERE client_id = ?
                                                           AND id > ?
                                                           AND currency = ?
                                                           AND id != ? AND tenant_id = ? AND branch_id = ?";
                             } else {
                                 // Amount decreased, increase subsequent balances
                                 $updateSubsequentQuery = "UPDATE client_transactions
                                                           SET balance = balance + ?
                                                           WHERE client_id = ?
                                                           AND id > ?
                                                           AND currency = ?
                                                           AND id != ? AND tenant_id = ? AND branch_id = ?";
                             }

                             $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                             $absAmountDifference = abs($amountDifference);
                             $stmtUpdateSubsequent->bindParam(1, $absAmountDifference, PDO::PARAM_STR);
                             $stmtUpdateSubsequent->bindParam(2, $sold_to, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(3, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(4, $currency, PDO::PARAM_STR);
                             $stmtUpdateSubsequent->bindParam(5, $transactionId, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                             $stmtUpdateSubsequent->execute();
                         }
                     }
                 }
             }
         }
         } // End of: if ($visaStatus === 'Approved')

         // Prepare the SQL update statement for the visa application
         $sql = "UPDATE visa_applications
                 SET supplier = ?, sold_to = ?, title = ?, gender = ?, applicant_name = ?,
                     passport_number = ?, country = ?, visa_type = ?, receive_date = ?,
                     applied_date = ?, issued_date = ?, base = ?, sold = ?, profit = ?,
                     currency = ?, status = ?, remarks = ?, phone = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ? AND branch_id = ?";

         $stmt = $pdo->prepare($sql);
         $stmt->bindParam(1, $supplier, PDO::PARAM_INT);
         $stmt->bindParam(2, $sold_to, PDO::PARAM_INT);
         $stmt->bindParam(3, $title, PDO::PARAM_STR);
         $stmt->bindParam(4, $gender, PDO::PARAM_STR);
         $stmt->bindParam(5, $applicant_name, PDO::PARAM_STR);
         $stmt->bindParam(6, $passport_number, PDO::PARAM_STR);
         $stmt->bindParam(7, $country, PDO::PARAM_STR);
         $stmt->bindParam(8, $visa_type, PDO::PARAM_STR);
         $stmt->bindParam(9, $receive_date, PDO::PARAM_STR);
         $stmt->bindParam(10, $applied_date, PDO::PARAM_STR);
         $stmt->bindParam(11, $issued_date, PDO::PARAM_STR);
         $stmt->bindParam(12, $base, PDO::PARAM_STR);
         $stmt->bindParam(13, $sold, PDO::PARAM_STR);
         $stmt->bindParam(14, $profit, PDO::PARAM_STR);
         $stmt->bindParam(15, $currency, PDO::PARAM_STR);
         $stmt->bindParam(16, $status, PDO::PARAM_STR);
         $stmt->bindParam(17, $remarks, PDO::PARAM_STR);
         $stmt->bindParam(18, $phone, PDO::PARAM_STR);
         $stmt->bindParam(19, $id, PDO::PARAM_INT);
         $stmt->bindParam(20, $tenant_id, PDO::PARAM_INT);
         $stmt->bindParam(21, $branch_id, PDO::PARAM_INT);

         if ($stmt->execute()) {
             // Add activity log
             $user_id = $_SESSION['user_id'];
             $ip_address = $_SERVER['REMOTE_ADDR'];
             $user_agent = $_SERVER['HTTP_USER_AGENT'];

             // Construct old_values from originalData
             $old_values = json_encode([
                 'id' => $id,
                 'supplier' => $originalData['supplier'],
                 'sold_to' => $originalData['sold_to'],
                 'base' => $originalData['base'],
                 'sold' => $originalData['sold'],
                 'currency' => $originalData['currency']
             ]);

             // Construct new_values
             $new_values = json_encode([
                 'id' => $id,
                 'supplier' => $supplier,
                 'sold_to' => $sold_to,
                 'title' => $title,
                 'gender' => $gender,
                 'applicant_name' => $applicant_name,
                 'passport_number' => $passport_number,
                 'country' => $country,
                 'visa_type' => $visa_type,
                 'receive_date' => $receive_date,
                 'applied_date' => $applied_date,
                 'issued_date' => $issued_date,
                 'base' => $base,
                 'sold' => $sold,
                 'profit' => $profit,
                 'currency' => $currency,
                 'status' => $status,
                 'remarks' => $remarks,
                 'phone' => $phone
             ]);

             // Insert into activity_log table
             $log_sql = "INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
                         VALUES (?, ?, ?, 'update', 'visa_applications', ?, ?, ?, NOW(), ?, ?)";
             $log_stmt = $pdo->prepare($log_sql);
             $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
             $log_stmt->bindParam(2, $ip_address, PDO::PARAM_STR);
             $log_stmt->bindParam(3, $user_agent, PDO::PARAM_STR);
             $log_stmt->bindParam(4, $id, PDO::PARAM_INT);
             $log_stmt->bindParam(5, $old_values, PDO::PARAM_STR);
             $log_stmt->bindParam(6, $new_values, PDO::PARAM_STR);
             $log_stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
             $log_stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

             if (!$log_stmt->execute()) {
                 // Just log the error, don't affect the transaction success
                 error_log("Failed to insert activity log: " . $log_stmt->error);
             }

             // Commit transaction
             $pdo->commit();
             $response['success'] = true;
             $response['message'] = 'Visa updated successfully.';
         } else {
             // Rollback transaction on error
             $pdo->rollback();
             $response['message'] = 'Error updating visa: ' . $stmt->error;
         }
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
