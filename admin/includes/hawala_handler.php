<?php
$tenant_id = $_SESSION['tenant_id'];

// Function to process a new Hawala transfer
function processHawalaTransfer($conn, $data) {
    try {
        $conn->begin_transaction();
        $tenant_id = $_SESSION['tenant_id'];

        // Insert sender transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id) VALUES (?, ?, ?, 'hawala_send', ?, ?, ?)");
        $stmt->bind_param("idsssi", $data['sender_id'], $data['send_amount'], $data['send_currency'], $data['notes'], $data['reference'], $tenant_id);
        $stmt->execute();
        $sender_transaction_id = $conn->insert_id;
        
        // Update sender's wallet
        $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("disi", $data['send_amount'], $data['sender_id'], $data['send_currency'], $tenant_id);
        $stmt->execute();
        
        // Create Hawala record
        $stmt = $conn->prepare("INSERT INTO hawala_transfers (sender_transaction_id, secret_code, commission_amount, commission_currency, tenant_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsi", $sender_transaction_id, $data['secret_code'], $data['commission_amount'], $data['commission_currency'], $tenant_id);
        $stmt->execute();
        $hawala_id = $conn->insert_id;
        
        // Record commission as income
        recordCommissionIncome($conn, $data['commission_amount'], $data['commission_currency'], $hawala_id, $tenant_id);
        
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Hawala transfer initiated successfully',
            'hawala_id' => $hawala_id,
            'sender_transaction_id' => $sender_transaction_id
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error processing Hawala transfer: ' . $e->getMessage()
        ];
    }
}

// Function to process Hawala payout
function processHawalaPayout($conn, $data) {
    $tenant_id = $_SESSION['tenant_id'];
    try {
        $conn->begin_transaction();
        
        // Verify Hawala exists and is pending
        $stmt = $conn->prepare("SELECT * FROM hawala_transfers WHERE id = ? AND status = 'pending' AND secret_code = ? AND tenant_id = ?");
        $stmt->bind_param("isi", $data['hawala_id'], $data['secret_code'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hawala = $result->fetch_assoc();
        
        if (!$hawala) {
            throw new Exception('Invalid Hawala transfer or secret code');
        }
        
        // Insert receiver transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id) VALUES (?, ?, ?, 'hawala_receive', ?, ?, ?)");
        $stmt->bind_param("idsssi", $data['receiver_id'], $data['receive_amount'], $data['receive_currency'], $data['notes'], $data['reference'], $tenant_id);
        $stmt->execute();
        $receiver_transaction_id = $conn->insert_id;
        
        // Update Hawala status
        $stmt = $conn->prepare("UPDATE hawala_transfers SET receiver_transaction_id = ?, status = 'completed' WHERE id = ?");
        $stmt->bind_param("ii", $receiver_transaction_id, $data['hawala_id']);
        $stmt->execute();
        
        // Update receiver's wallet
        $stmt = $conn->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id) 
                               VALUES (?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE balance = balance + ?");
        $stmt->bind_param("isddi", $data['receiver_id'], $data['receive_currency'], $data['receive_amount'], $data['receive_amount'], $tenant_id );
        $stmt->execute();
        
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Hawala payout processed successfully'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error processing Hawala payout: ' . $e->getMessage()
        ];
    }
}

// Function to record commission income
function recordCommissionIncome($conn, $amount, $currency, $hawala_id, $tenant_id) {
    // Insert into general ledger
    $stmt = $conn->prepare("INSERT INTO general_ledger (account_type, entry_type, amount, currency, balance, tenant_id) 
                           SELECT 'income', 'credit', ?, ?, COALESCE(MAX(balance), 0) + ?, ? 
                           FROM general_ledger 
                           WHERE account_type = 'income' AND currency = ? AND tenant_id = ?");
    $stmt->bind_param(
    "dsdsii", 
    $amount,              // ?
    $currency,            // ?
    $amount,              // ?
    $tenant_id,           // ?
    $currency,            // ?
    $tenant_id );
    $stmt->execute();
    
    // Update Hawala record with commission details
    $stmt = $conn->prepare("UPDATE hawala_transfers SET commission_amount = ?, commission_currency = ? WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("dsii", $amount, $currency, $hawala_id, $tenant_id);
    $stmt->execute();
}

// Function to cancel Hawala transfer
function cancelHawalaTransfer($conn, $hawala_id, $tenant_id) {

    try {
        $conn->begin_transaction();
        
        // Get Hawala details
        $stmt = $conn->prepare("SELECT * FROM hawala_transfers WHERE id = ? AND status = 'pending' AND tenant_id = ?");
        $stmt->bind_param("ii", $hawala_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hawala = $result->fetch_assoc();
        
        if (!$hawala) {
            throw new Exception('Invalid Hawala transfer or already completed');
        }
        
        // Get sender transaction details
        $stmt = $conn->prepare("SELECT * FROM sarafi_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $hawala['sender_transaction_id'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $sender_transaction = $result->fetch_assoc();
        
        // Refund sender's wallet
        $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance + ? 
                               WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("disi", $sender_transaction['amount'], $sender_transaction['customer_id'], $sender_transaction['currency'], $tenant_id);
        $stmt->execute();
        
        // Update Hawala status
        $stmt = $conn->prepare("UPDATE hawala_transfers SET status = 'cancelled' WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $hawala_id, $tenant_id);
        $stmt->execute();
        
        // Reverse commission entry in general ledger
        $stmt = $conn->prepare("INSERT INTO general_ledger (account_type, entry_type, amount, currency, balance, tenant_id) 
                               SELECT 'income', 'debit', ?, ?, COALESCE(MAX(balance), 0) - ?, ? 
                               FROM general_ledger 
                               WHERE account_type = 'income' AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("dsdsii", $hawala['commission_amount'], $hawala['commission_currency'], 
                         $hawala['commission_amount'], $hawala['commission_currency'], $tenant_id);
        $stmt->execute();
        
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Hawala transfer cancelled successfully'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error cancelling Hawala transfer: ' . $e->getMessage()
        ];
    }
}
?> 