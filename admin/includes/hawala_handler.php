<?php
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Function to process a new Hawala transfer
// Note: This function assumes a transaction is already active
function processHawalaTransfer($pdo, $data) {
    try {
        $tenant_id = $_SESSION['tenant_id'];
        $branch_id = $_SESSION['branch_id'];
        
        // Insert sender transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'hawala_send', ?, ?, ?, ?)");
        $stmt->bindParam(1, $data['sender_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['send_amount'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['send_currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $data['notes'], PDO::PARAM_STR);
        $stmt->bindParam(5, $data['reference'], PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $sender_transaction_id = $pdo->lastInsertId();
        
        // Update sender's wallet
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['send_amount'], PDO::PARAM_STR);
        $stmt->bindParam(2, $data['sender_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $data['send_currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Create Hawala record
        $stmt = $pdo->prepare("INSERT INTO hawala_transfers (sender_transaction_id, secret_code, commission_amount, commission_currency, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $sender_transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $data['secret_code'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['commission_amount'], PDO::PARAM_STR);
        $stmt->bindParam(4, $data['commission_currency'], PDO::PARAM_STR);
        $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $hawala_id = $pdo->lastInsertId();
        
        // Record commission as income
        recordCommissionIncome($pdo, $data['commission_amount'], $data['commission_currency'], $hawala_id, $tenant_id, $branch_id);
        
        return [
            'success' => true,
            'message' => 'Hawala transfer initiated successfully',
            'hawala_id' => $hawala_id,
            'sender_transaction_id' => $sender_transaction_id
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error processing Hawala transfer: ' . $e->getMessage()
        ];
    }
}

// Function to process Hawala payout
// Note: This function assumes a transaction is already active
function processHawalaPayout($pdo, $data) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        
        // Verify Hawala exists and is pending
        $stmt = $pdo->prepare("SELECT * FROM hawala_transfers WHERE id = ? AND status = 'pending' AND secret_code = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['hawala_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['secret_code'], PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $hawala = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hawala) {
            throw new Exception('Invalid Hawala transfer or secret code');
        }
        
        // Insert receiver transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'hawala_receive', ?, ?, ?, ?)");
        $stmt->bindParam(1, $data['receiver_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['receive_amount'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['receive_currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $data['notes'], PDO::PARAM_STR);
        $stmt->bindParam(5, $data['reference'], PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $receiver_transaction_id = $pdo->lastInsertId();
        
        // Update Hawala status
        $stmt = $pdo->prepare("UPDATE hawala_transfers SET receiver_transaction_id = ?, status = 'completed' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $receiver_transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $data['hawala_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update receiver's wallet
        $stmt = $pdo->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id, branch_id) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE balance = balance + ?");
        $stmt->bindParam(1, $data['receiver_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $data['receive_currency'], PDO::PARAM_STR);
        $stmt->bindParam(3, $data['receive_amount'], PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $data['receive_amount'], PDO::PARAM_STR);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Hawala payout processed successfully'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error processing Hawala payout: ' . $e->getMessage()
        ];
    }
}

// Function to record commission income
function recordCommissionIncome($pdo, $amount, $currency, $hawala_id, $tenant_id, $branch_id) {
    // Insert into general ledger
    $stmt = $pdo->prepare("INSERT INTO general_ledger (account_type, entry_type, amount, currency, balance, tenant_id, branch_id) 
                           SELECT 'income', 'credit', ?, ?, COALESCE(MAX(balance), 0) + ?, ?, ? 
                           FROM general_ledger 
                           WHERE account_type = 'income' AND currency = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $amount, PDO::PARAM_STR);
    $stmt->bindParam(2, $currency, PDO::PARAM_STR);
    $stmt->bindParam(3, $amount, PDO::PARAM_STR);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $currency, PDO::PARAM_STR);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Update Hawala record with commission details
    $stmt = $pdo->prepare("UPDATE hawala_transfers SET commission_amount = ?, commission_currency = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $amount, PDO::PARAM_STR);
    $stmt->bindParam(2, $currency, PDO::PARAM_STR);
    $stmt->bindParam(3, $hawala_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
}

// Function to cancel Hawala transfer
function cancelHawalaTransfer($pdo, $hawala_id, $tenant_id, $branch_id) {
    try {
        $pdo->beginTransaction();
        
        // Get Hawala details
        $stmt = $pdo->prepare("SELECT * FROM hawala_transfers WHERE id = ? AND status = 'pending' AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $hawala_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $hawala = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hawala) {
            throw new Exception('Invalid Hawala transfer or already completed');
        }
        
        // Get sender transaction details
        $stmt = $pdo->prepare("SELECT * FROM sarafi_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $hawala['sender_transaction_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $sender_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Refund sender's wallet
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance + ? 
                               WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $sender_transaction['amount'], PDO::PARAM_STR);
        $stmt->bindParam(2, $sender_transaction['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $sender_transaction['currency'], PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update Hawala status
        $stmt = $pdo->prepare("UPDATE hawala_transfers SET status = 'cancelled' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $hawala_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Reverse commission entry in general ledger
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_type, entry_type, amount, currency, balance, tenant_id, branch_id) 
                               SELECT 'income', 'debit', ?, ?, COALESCE(MAX(balance), 0) - ?, ?, ? 
                               FROM general_ledger 
                               WHERE account_type = 'income' AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $hawala['commission_amount'], PDO::PARAM_STR);
        $stmt->bindParam(2, $hawala['commission_currency'], PDO::PARAM_STR);
        $stmt->bindParam(3, $hawala['commission_amount'], PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $hawala['commission_currency'], PDO::PARAM_STR);
        $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
        return [
            'success' => true,
            'message' => 'Hawala transfer cancelled successfully'
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Error cancelling Hawala transfer: ' . $e->getMessage()
        ];
    }
}
?>
