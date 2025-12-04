<?php
require_once '../../includes/conn.php';
require_once '../includes/db_security.php';
session_start();
// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Get and validate input data
$weightId = isset($_POST['weight_id']) ? intval($_POST['weight_id']) : 0;
$weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
$basePrice = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0;
$soldPrice = isset($_POST['sold_price']) ? floatval($_POST['sold_price']) : 0;
$profit = $soldPrice - $basePrice;
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

// Validate required fields
if (!$weightId || !$weight || !$basePrice || !$soldPrice) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Step 1: Get the old weight values and related ticket details before updating
    $stmt = $conn->prepare("
        SELECT w.*, t.passenger_name, t.pnr, t.origin, t.destination, t.supplier, t.sold_to, t.currency, t.paid_to,
               s.supplier_type, s.balance as supplier_balance, s.name as supplier_name,
               c.client_type, c.usd_balance, c.afs_balance, c.name as client_name
        FROM ticket_weights w
        LEFT JOIN ticket_bookings t ON w.ticket_id = t.id AND t.tenant_id = ? AND t.branch_id = ?
        LEFT JOIN suppliers s ON t.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN clients c ON t.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        WHERE w.id = ? AND w.tenant_id = ? And w.branch_id = ?
    ");
    $stmt->bind_param('iii', $weightId, $tenant_id, );
    $stmt->execute();
    $oldWeight = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$oldWeight) {
        throw new Exception('Weight record not found');
    }

    // Calculate differences for transaction updates
    $basePriceDifference = $oldWeight['base_price'] - $basePrice;
    $soldPriceDifference = $oldWeight['sold_price'] - $soldPrice;
    
    // Step 2: Process supplier transaction updates if base price changed
    if ($basePriceDifference != 0 && $oldWeight['supplier_type'] === 'External') {
        // Get supplier transaction related to this weight
        $stmt = $conn->prepare("
            SELECT * FROM supplier_transactions 
            WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ? AND branch_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('iiii', $oldWeight['supplier'], $weightId, $tenant_id, $branch_id);
        $stmt->execute();
        $supplierTransaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($supplierTransaction) {
            // Update supplier balance based on base price difference
            // If basePriceDifference is positive: base price decreased, add to balance (supplier gets money back)
            // If basePriceDifference is negative: base price increased, subtract from balance (supplier pays more)
            $newBalance = $oldWeight['supplier_balance'] + $basePriceDifference;
            $stmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bind_param('didi', $newBalance, $oldWeight['supplier'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
            
            // Update supplier transaction amount and balance
            $stmt = $conn->prepare("
                UPDATE supplier_transactions 
                SET amount = ?, 
                    balance = ?,
                    remarks = CONCAT('Updated: ', remarks)
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $stmt->bind_param('ddii', $basePrice, $newBalance, $supplierTransaction['id'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
            
            // Update all subsequent transactions' balances
            $stmt = $conn->prepare("
                UPDATE supplier_transactions 
                SET balance = balance + ? 
                WHERE supplier_id = ? 
                AND transaction_date > ? 
                AND id != ? AND tenant_id = ? AND branch_id = ?
                ORDER BY transaction_date ASC
            ");
            $stmt->bind_param('disiii', $basePriceDifference, $oldWeight['supplier'], $supplierTransaction['transaction_date'], $supplierTransaction['id'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new supplier transaction if none exists
            $newBalance = $oldWeight['supplier_balance'] - $basePrice;
            $stmt = $conn->prepare("
                INSERT INTO supplier_transactions 
                (supplier_id, reference_id, transaction_type, amount, balance, remarks, status, transaction_date, transaction_of, tenant_id, branch_id) 
                VALUES (?, ?, 'Debit', ?, ?, ?, 'Borrowed', NOW(), 'weight_sale', ?, ?)
            ");
            $description = "Base amount for weight transaction: {$weight}kg for passenger {$oldWeight['passenger_name']} (PNR: {$oldWeight['pnr']})";
            $stmt->bind_param('iiddsii', $oldWeight['supplier'], $weightId, $basePrice, $newBalance, $description, $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
            
            // Update supplier balance
            $stmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bind_param('didi', $newBalance, $oldWeight['supplier'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Step 3: Process client transaction updates if sold price changed
    if ($soldPriceDifference != 0 && $oldWeight['client_type'] === 'regular') {
        // Get client transaction related to this weight
        $stmt = $conn->prepare("
            SELECT * FROM client_transactions 
            WHERE client_id = ? AND reference_id = ? AND transaction_of = 'weight_sale' AND tenant_id = ? And branch_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('iiii', $oldWeight['sold_to'], $weightId, $tenant_id, $branch_id);
        $stmt->execute();
        $clientTransaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($clientTransaction) {
            // Determine which balance field to update based on currency
            $balanceField = ($oldWeight['currency'] === 'USD') ? 'usd_balance' : 'afs_balance';
            $currentBalance = ($oldWeight['currency'] === 'USD') ? $oldWeight['usd_balance'] : $oldWeight['afs_balance'];
            
            // Update client balance based on sold price difference
            // If soldPriceDifference is positive: sold price decreased, add to balance (client owes less)
            // If soldPriceDifference is negative: sold price increased, subtract from balance (client owes more)
            $newClientBalance = $currentBalance + $soldPriceDifference;
            $stmt = $conn->prepare("UPDATE clients SET $balanceField = ? WHERE id = ? And tenant_id = ? And branch_id = ?");
            $stmt->bind_param('diii', $newClientBalance, $oldWeight['sold_to'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
            
            // Calculate the difference between new sold price and current transaction amount
            $amountDifference = $soldPrice - $clientTransaction['amount'];
            
            // For client transactions, subsequent balances should:
            // - Increase (add) when amount decreases
            // - Decrease (subtract) when amount increases
            $balanceAdjustment = -$amountDifference;
            
            // Update client transaction amount and balance
            $stmt = $conn->prepare("
                UPDATE client_transactions 
                SET amount = ?, 
                    balance = balance + ?,
                    description = CONCAT('Updated: ', description)
                WHERE id = ? AND tenant_id = ? And branch_id = ?
            ");
            $stmt->bind_param('ddiii', $soldPrice, $balanceAdjustment, $clientTransaction['id'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
            
            // Update all subsequent transactions' balances
            $stmt = $conn->prepare("
                UPDATE client_transactions 
                SET balance = balance + ? 
                WHERE client_id = ? 
                AND created_at > ? 
                AND currency = ? 
                AND id != ? AND tenant_id = ? And branch_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->bind_param('dissii', $balanceAdjustment, $oldWeight['sold_to'], $clientTransaction['created_at'], $oldWeight['currency'], $clientTransaction['id'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new client transaction if none exists
            $balanceField = ($oldWeight['currency'] === 'USD') ? 'usd_balance' : 'afs_balance';
            $currentBalance = ($oldWeight['currency'] === 'USD') ? $oldWeight['usd_balance'] : $oldWeight['afs_balance'];
            $newClientBalance = $currentBalance - $soldPrice;
            
            $stmt = $conn->prepare("
                INSERT INTO client_transactions 
                (client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id)
                VALUES (?, 'Debit', 'weight_sale', ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $description = "Weight transaction: {$weight}kg at {$soldPrice} {$oldWeight['currency']} for passenger {$oldWeight['passenger_name']} (PNR: {$oldWeight['pnr']})";
            $stmt->bind_param('iiddssiii', $oldWeight['sold_to'], $weightId, $soldPrice, $newClientBalance, $oldWeight['currency'], $description, $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
            
            // Update client balance
            $stmt = $conn->prepare("UPDATE clients SET $balanceField = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bind_param('didi', $newClientBalance, $oldWeight['sold_to'], $tenant_id, $branch_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Step 4: Update the weight record with new values
    $stmt = $conn->prepare("
        UPDATE ticket_weights 
        SET weight = ?, 
            base_price = ?, 
            sold_price = ?, 
            profit = ?, 
            remarks = ?,
            updated_at = NOW()
        WHERE id = ? AND tenant_id = ? And branch_id = ?
    ");
    
    $stmt->bind_param('ddddsiii', $weight, $basePrice, $soldPrice, $profit, $remarks, $weightId, $tenant_id, $branch_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update weight data');
    }

    // Step 5: Log the activity
    $user_id = $_SESSION["user_id"] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare activity log data
    $old_values = json_encode([
        'weight_id' => $weightId,
        'weight' => $oldWeight['weight'],
        'base_price' => $oldWeight['base_price'],
        'sold_price' => $oldWeight['sold_price'],
        'profit' => $oldWeight['profit'],

        'remarks' => $oldWeight['remarks']
    ]);
    
    $new_values = json_encode([
        'weight_id' => $weightId,
        'weight' => $weight,
        'base_price' => $basePrice,
        'sold_price' => $soldPrice,
        'profit' => $profit,
        'remarks' => $remarks,
        'supplier_name' => $oldWeight['supplier_name'],
        'client_name' => $oldWeight['client_name'],
        'base_price_difference' => $basePriceDifference,
        'sold_price_difference' => $soldPriceDifference
    ]);
    
    // Insert activity log
    $stmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id) 
        VALUES (?, ?, 'update', 'ticket_weights', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    
    $stmt->bind_param("iissssii", $user_id, $tenant_id, $weightId, $old_values, $new_values, $ip_address, $user_agent, $branch_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to log activity');
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Weight and associated transactions updated successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
} 