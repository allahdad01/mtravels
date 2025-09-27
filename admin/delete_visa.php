<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

// Accept both JSON and form data
$visa_id = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check for JSON data first
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['id'])) {
        $visa_id = intval($data['id']);
    }
    // If not found in JSON, check POST data
    else if (isset($_POST['id'])) {
        $visa_id = intval($_POST['id']);
    }
}

if ($visa_id === null || $visa_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Visa application ID not provided or invalid']);
    exit;
}

// Start transaction
$pdo->beginTransaction();

try {
    // Step 1: Fetch visa-related details
    $stmt_fetch = $pdo->prepare("
        SELECT va.sold_to, va.supplier, va.paid_to, va.currency, c.client_type, s.supplier_type
        FROM visa_applications va
        JOIN clients c ON va.sold_to = c.id
        JOIN suppliers s ON va.supplier = s.id
        WHERE va.id = ? AND va.tenant_id = ?
    ");
    $stmt_fetch->execute([$visa_id, $tenant_id]);
    $visa = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$visa) {
        throw new Exception("Visa application not found.");
    }

    $client_id = $visa['sold_to'];
    $supplier_id = $visa['supplier'];
    $currency = $visa['currency'];
    $client_type = $visa['client_type'];
    $supplier_type = $visa['supplier_type'];
    $mainAccountId = $visa['paid_to'];

    // Step 2: Reverse Client Transactions (Only If Client is Regular)
    if ($client_type === 'regular') {
        $stmt_client_transactions = $pdo->prepare("
            SELECT id, amount, type, created_at FROM client_transactions 
            WHERE client_id = ? AND transaction_of = 'visa_sale' 
            AND reference_id = ? AND tenant_id = ?
        ");
        $stmt_client_transactions->execute([$client_id, $visa_id, $tenant_id]);
        $client_transactions = $stmt_client_transactions->fetchAll(PDO::FETCH_ASSOC);

        foreach ($client_transactions as $transaction) {
            $amount = $transaction['amount'];
            $transaction_date = $transaction['created_at'];
            $transaction_id = $transaction['id'];

            // Adjust Client Balance with Correct Reversal Logic
            $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
            
            // Reverse logic: If original was 'credit', subtract; if 'debit', add.
            $adjustClientBalance = $pdo->prepare("
                UPDATE clients 
                SET $clientBalanceField = $clientBalanceField " . ($transaction['type'] == 'credit' ? '-' : '+') . " ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $adjustClientBalance->execute([$amount, $client_id, $tenant_id]);

            // Update all subsequent transactions' running balances
            // If the deleted transaction was a credit, we need to subtract that amount from all later transactions
            // If it was a debit, we need to add that amount to all later transactions
            $updateSubsequentBalances = $pdo->prepare("
                UPDATE client_transactions 
                SET balance = balance " . ($transaction['type'] == 'credit' ? '-' : '+') . " ? 
                WHERE client_id = ? AND created_at > ? 
                AND currency = ?
                AND tenant_id = ?
                ORDER BY created_at ASC
            ");
            $updateSubsequentBalances->execute([$amount, $client_id, $transaction_date, $currency, $tenant_id]);

            // Delete Client Transaction
            $deleteClientTransaction = $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ?");
            $deleteClientTransaction->execute([$transaction_id, $tenant_id]);
        }
    }

    // Step 3: Reverse Supplier Transactions
    if ($supplier_type === 'External') {
        $stmt_supplier_transactions = $pdo->prepare("
            SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions 
            WHERE supplier_id = ? AND transaction_of = 'visa_sale' 
            AND reference_id = ? AND tenant_id = ?
        ");
        $stmt_supplier_transactions->execute([$supplier_id, $visa_id, $tenant_id]);
        $supplier_transactions = $stmt_supplier_transactions->fetchAll(PDO::FETCH_ASSOC);

        foreach ($supplier_transactions as $transaction) {
            $amount = $transaction['amount'];
            $transaction_date = $transaction['transaction_date'];
            $transaction_id = $transaction['id'];
            
            // Adjust Supplier Balance
            $adjustSupplierBalance = $pdo->prepare("
                UPDATE suppliers 
                SET balance = balance " . ($transaction['transaction_type'] == 'Credit' ? '-' : '+') . " ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $adjustSupplierBalance->execute([$amount, $supplier_id, $tenant_id]);
            
            // Update all subsequent transactions' running balances
            $updateSubsequentSupplierBalances = $pdo->prepare("
                UPDATE supplier_transactions 
                SET balance = balance " . ($transaction['transaction_type'] == 'Credit' ? '-' : '+') . " ? 
                WHERE supplier_id = ? AND transaction_date > ?
                AND tenant_id = ?
                ORDER BY transaction_date ASC
            ");
            $updateSubsequentSupplierBalances->execute([$amount, $supplier_id, $transaction_date, $tenant_id]);

            // Delete Supplier Transaction
            $deleteSupplierTransaction = $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?");
            $deleteSupplierTransaction->execute([$transaction_id, $tenant_id]);
        }
    }
    
    // Handle main account transactions and balance updates
    if ($mainAccountId && $mainAccountId > 0) {
        // Fetch main account transactions for this visa application
        $stmt_fetch_main_transactions = $pdo->prepare("
            SELECT id, amount, type, currency, created_at
            FROM main_account_transactions 
            WHERE reference_id = ? AND transaction_of = 'visa_sale'
            AND tenant_id = ?
        ");
        $stmt_fetch_main_transactions->execute([$visa_id, $tenant_id]);
        $main_transactions = $stmt_fetch_main_transactions->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($main_transactions as $main_transaction) {
            $main_amount = $main_transaction['amount'];
            $main_type = $main_transaction['type'];
            $main_currency = $main_transaction['currency'];
            $transaction_date = $main_transaction['created_at'];
            $transaction_id = $main_transaction['id'];
            
            // Update main account balance based on transaction type
            if ($main_type === 'credit') {
                if ($main_currency === 'USD') {
                    $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
                } elseif ($main_currency === 'AFS') {
                    $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
                } else {
                    throw new Exception("Unsupported currency type for main account balance update.");
                }
                
                // Update running balances for all subsequent main account transactions
                $update_subsequent_main = $pdo->prepare("
                    UPDATE main_account_transactions 
                    SET balance = balance - ? 
                    WHERE main_account_id = ? AND created_at > ? 
                    AND currency = ?
                    AND tenant_id = ?
                    ORDER BY created_at ASC
                ");
            } elseif ($main_type === 'debit') {
                if ($main_currency === 'USD') {
                    $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
                } elseif ($main_currency === 'AFS') {
                    $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
                } else {
                    throw new Exception("Unsupported currency type for main account balance update.");
                }
                
                // Update running balances for all subsequent main account transactions
                $update_subsequent_main = $pdo->prepare("
                    UPDATE main_account_transactions 
                    SET balance = balance + ? 
                    WHERE main_account_id = ? AND created_at > ? 
                    AND currency = ?
                    AND tenant_id = ?
                    ORDER BY created_at ASC
                ");
            } else {
                throw new Exception("Invalid transaction type for main account transaction.");
            }
            
            $stmt_update_main->execute([$main_amount, $mainAccountId, $tenant_id]);
            
            // Execute the update for subsequent transactions
            $update_subsequent_main->execute([$main_amount, $mainAccountId, $transaction_date, $main_currency, $tenant_id]);
        }
    }
    
    // Delete main account transactions associated with this visa application
    $delete_main_transactions = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ?");
    $delete_main_transactions->execute([$visa_id, $tenant_id]);

    // Step 5: Delete the Visa Application Record
    $deleteVisa = $pdo->prepare("DELETE FROM visa_applications WHERE id = ? AND tenant_id = ?");
    $deleteVisa->execute([$visa_id, $tenant_id]);

    // Commit Transaction
    $pdo->commit();
    
    // Log the activity
    $old_values = json_encode([
        'visa_id' => $visa_id,
        'client_id' => $client_id,
        'supplier_id' => $supplier_id,
        'currency' => $currency,
        'client_type' => $client_type,
        'supplier_type' => $supplier_type,
        'main_account_id' => $mainAccountId
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'delete', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $activityStmt->execute([$user_id, $visa_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
    
    echo json_encode(['success' => true, 'message' => 'Visa application deleted successfully!']);
} catch (Exception $e) {
    $pdo->rollBack(); // Roll back the transaction in case of errors
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    // No need to close connection with PDO
}
?>
