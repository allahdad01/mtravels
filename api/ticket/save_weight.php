<?php
require_once '../../includes/db.php';
require_once '../../admin/includes/db_security.php';
session_start();
// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input data
$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
$basePrice = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0;
$soldPrice = isset($_POST['sold_price']) ? floatval($_POST['sold_price']) : 0;
$profit = $soldPrice - $basePrice;
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

// Validate required fields
if (!$ticketId || !$weight || !$basePrice || !$soldPrice) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
try {
    // Start transaction
    $pdo->beginTransaction();

    // Step 1: Get ticket details for transactions
    $stmt_ticket = $pdo->prepare("
        SELECT t.*, s.currency as supplier_currency, s.supplier_type, s.balance as supplier_balance, s.name as supplier_name,
               c.name as client_name, c.client_type, c.usd_balance, c.afs_balance
        FROM ticket_bookings t
        LEFT JOIN suppliers s ON t.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN clients c ON t.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        WHERE t.id = ? AND t.tenant_id = ? AND t.branch_id = ?
    ");
    $stmt_ticket->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt_ticket->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt_ticket->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt_ticket->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt_ticket->bindParam(5, $ticketId, PDO::PARAM_INT);
    $stmt_ticket->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $stmt_ticket->bindParam(7, $branch_id, PDO::PARAM_INT);
    $stmt_ticket->execute();
    $ticket_details = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

    if (!$ticket_details) {
        throw new Exception('Ticket not found');
    }

    // Step 2: Insert into ticket_weights table
    $stmt = $pdo->prepare("
        INSERT INTO ticket_weights
        (ticket_id, weight, base_price, sold_price, profit, remarks, created_at, updated_at, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
    ");

    $stmt->bindParam(1, $ticketId, PDO::PARAM_INT);
    $stmt->bindParam(2, $weight, PDO::PARAM_STR);
    $stmt->bindParam(3, $basePrice, PDO::PARAM_STR);
    $stmt->bindParam(4, $soldPrice, PDO::PARAM_STR);
    $stmt->bindParam(5, $profit, PDO::PARAM_STR);
    $stmt->bindParam(6, $remarks, PDO::PARAM_STR);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new Exception('Failed to save weight data');
    }

    $weightId = $pdo->lastInsertId();

    // Step 3: Process supplier transaction for External suppliers
    if ($ticket_details['supplier_type'] === 'External') {
        $new_supplier_balance = $ticket_details['supplier_balance'] - $basePrice;

        // Insert supplier transaction
        $stmt_supplier = $pdo->prepare("
            INSERT INTO supplier_transactions
            (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id, branch_id)
            VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'weight_sale', ?, ?)
        ");

        $supplier_remarks = "Base amount of {$basePrice} {$ticket_details['supplier_currency']} deducted for weight transaction.";
        $stmt_supplier->bindParam(1, $ticket_details['supplier'], PDO::PARAM_INT);
        $stmt_supplier->bindParam(2, $weightId, PDO::PARAM_INT);
        $stmt_supplier->bindParam(3, $basePrice, PDO::PARAM_STR);
        $stmt_supplier->bindParam(4, $new_supplier_balance, PDO::PARAM_STR);
        $stmt_supplier->bindParam(5, $supplier_remarks, PDO::PARAM_STR);
        $stmt_supplier->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt_supplier->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$stmt_supplier->execute()) {
            throw new Exception('Failed to create supplier transaction');
        }

        // Update supplier balance
        $stmt_update_supplier = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_update_supplier->bindParam(1, $new_supplier_balance, PDO::PARAM_STR);
        $stmt_update_supplier->bindParam(2, $ticket_details['supplier'], PDO::PARAM_INT);
        $stmt_update_supplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt_update_supplier->bindParam(4, $branch_id, PDO::PARAM_INT);

        if (!$stmt_update_supplier->execute()) {
            throw new Exception('Failed to update supplier balance');
        }
    } else {
        // For non-External suppliers, just record the transaction without balance
        $stmt_supplier = $pdo->prepare("
            INSERT INTO supplier_transactions
            (supplier_id, reference_id, transaction_type, amount, remarks, transaction_date, transaction_of, tenant_id, branch_id)
            VALUES (?, ?, 'Debit', ?, ?, NOW(), 'weight_sale', ?, ?)
        ");

        $supplier_remarks = "Base amount of {$basePrice} {$ticket_details['supplier_currency']} deducted for weight transaction.";
        $stmt_supplier->bindParam(1, $ticket_details['supplier'], PDO::PARAM_INT);
        $stmt_supplier->bindParam(2, $weightId, PDO::PARAM_INT);
        $stmt_supplier->bindParam(3, $basePrice, PDO::PARAM_STR);
        $stmt_supplier->bindParam(4, $supplier_remarks, PDO::PARAM_STR);
        $stmt_supplier->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $stmt_supplier->bindParam(6, $branch_id, PDO::PARAM_INT);

        if (!$stmt_supplier->execute()) {
            throw new Exception('Failed to create supplier transaction');
        }
    }

    // Step 4: Process client transaction
    if ($ticket_details['sold_to']) {
        $current_balance = ($ticket_details['currency'] === 'USD') ?
            $ticket_details['usd_balance'] :
            $ticket_details['afs_balance'];

        $new_client_balance = $current_balance - $soldPrice;

        // Insert client transaction
        $stmt_client = $pdo->prepare("
            INSERT INTO client_transactions
            (client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id)
            VALUES (?, 'Debit', 'weight_sale', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $client_description = "Weight transaction: {$weight}kg at {$soldPrice} {$ticket_details['currency']}.";
        $stmt_client->bindParam(1, $ticket_details['sold_to'], PDO::PARAM_INT);
        $stmt_client->bindParam(2, $weightId, PDO::PARAM_INT);
        $stmt_client->bindParam(3, $soldPrice, PDO::PARAM_STR);
        $stmt_client->bindParam(4, $new_client_balance, PDO::PARAM_STR);
        $stmt_client->bindParam(5, $ticket_details['currency'], PDO::PARAM_STR);
        $stmt_client->bindParam(6, $client_description, PDO::PARAM_STR);
        $stmt_client->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_client->bindParam(8, $branch_id, PDO::PARAM_INT);

        if (!$stmt_client->execute()) {
            throw new Exception('Failed to create client transaction');
        }

        // Update client balance for regular clients
        if ($ticket_details['client_type'] === 'regular') {
            $balance_column = $ticket_details['currency'] === 'USD' ? 'usd_balance' : 'afs_balance';
            $stmt_update_client = $pdo->prepare("UPDATE clients SET {$balance_column} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_client->bindParam(1, $new_client_balance, PDO::PARAM_STR);
            $stmt_update_client->bindParam(2, $ticket_details['sold_to'], PDO::PARAM_INT);
            $stmt_update_client->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_client->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt_update_client->execute()) {
                throw new Exception('Failed to update client balance');
            }
        }
    }

    // Step 5: Log the activity
    $user_id = $_SESSION["user_id"] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $new_values = json_encode([
        'ticket_id' => $ticketId,
        'weight' => $weight,
        'base_price' => $basePrice,
        'sold_price' => $soldPrice,
        'profit' => $profit,
        'remarks' => $remarks,
        'supplier_name' => $ticket_details['supplier_name'],
        'client_name' => $ticket_details['client_name'],
        'currency' => $ticket_details['currency']
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id)
        VALUES (?, ?, 'create', 'ticket_weights', ?, NULL, ?, ?, ?, NOW(), ?)
    ");

    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $weightId, PDO::PARAM_INT);
    $stmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new Exception('Failed to log activity');
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Weight and transactions saved successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}