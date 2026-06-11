<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['name'] ?? 'Unknown';

require_once '../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$dcId = intval($input['id']);
$newSupplierPenalty = floatval($input['supplier_penalty'] ?? 0);
$newServicePenalty = floatval($input['service_penalty'] ?? 0);
$newRemarks = trim($input['remarks'] ?? '');
$newTotalPenalty = $newSupplierPenalty + $newServicePenalty;

$pdo->beginTransaction();

try {
    // Fetch current date change record
    $stmt = $pdo->prepare("SELECT * FROM date_change_tickets WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$dcId, $tenant_id, $branch_id]);
    $dc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dc) {
        throw new Exception('Date change record not found');
    }

    $oldSupplierPenalty = floatval($dc['supplier_penalty']);
    $oldServicePenalty = floatval($dc['service_penalty']);
    $currency = $dc['currency'];
    $supplierId = $dc['supplier'];
    $soldToId = $dc['sold_to'];

    // Get client type
    $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $clientStmt->execute([$soldToId, $tenant_id, $branch_id]);
    $clientData = $clientStmt->fetch(PDO::FETCH_ASSOC);
    $clientType = $clientData ? $clientData['client_type'] : null;

    // Get supplier type
    $supplierStmt = $pdo->prepare("SELECT supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $supplierStmt->execute([$supplierId, $tenant_id, $branch_id]);
    $supplierData = $supplierStmt->fetch(PDO::FETCH_ASSOC);
    $supplierType = $supplierData ? $supplierData['supplier_type'] : null;

    // --- UPDATE DATE CHANGE RECORD ---
    $updateDcStmt = $pdo->prepare("UPDATE date_change_tickets SET supplier_penalty = ?, service_penalty = ?, remarks = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateDcStmt->execute([$newSupplierPenalty, $newServicePenalty, $newRemarks, $dcId, $tenant_id, $branch_id]);

    // --- CLIENT BALANCE UPDATE (regular clients only) ---
    if ($clientType === 'regular') {
        $oldTotal = $oldSupplierPenalty + $oldServicePenalty;
        $clientDiff = $newTotalPenalty - $oldTotal;

        if ($clientDiff != 0) {
            $balanceField = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';

            // Update client master balance
            $updateClientStmt = $pdo->prepare("UPDATE clients SET {$balanceField} = {$balanceField} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateClientStmt->execute([$clientDiff, $soldToId, $tenant_id, $branch_id]);

            // Find and update client transaction
            $ctStmt = $pdo->prepare("SELECT id, balance FROM client_transactions WHERE client_id = ? AND transaction_of = 'date_change' AND reference_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
            $ctStmt->execute([$soldToId, $dcId, $tenant_id, $branch_id]);
            $ct = $ctStmt->fetch(PDO::FETCH_ASSOC);

            if ($ct) {
                $ctId = $ct['id'];
                $updateCtStmt = $pdo->prepare("UPDATE client_transactions SET amount = ?, balance = balance - ?, description = CONCAT('Updated: ', description) WHERE id = ?");
                $updateCtStmt->execute([$newTotalPenalty, $clientDiff, $ctId]);

                // Update subsequent client transactions
                $updateSubCtStmt = $pdo->prepare("UPDATE client_transactions SET balance = balance - ? WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ? AND id > ? ORDER BY id ASC");
                $updateSubCtStmt->execute([$clientDiff, $soldToId, $currency, $tenant_id, $branch_id, $ctId]);
            }
        }
    }

    // --- SUPPLIER BALANCE UPDATE (external suppliers only) ---
    if ($supplierType === 'External') {
        $supplierDiff = $newSupplierPenalty - $oldSupplierPenalty;

        if ($supplierDiff != 0) {
            // Update supplier master balance
            $updateSuppStmt = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateSuppStmt->execute([$supplierDiff, $supplierId, $tenant_id, $branch_id]);

            // Find and update supplier transaction
            $stStmt = $pdo->prepare("SELECT id, balance FROM supplier_transactions WHERE supplier_id = ? AND transaction_of = 'date_change' AND reference_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
            $stStmt->execute([$supplierId, $dcId, $tenant_id, $branch_id]);
            $st = $stStmt->fetch(PDO::FETCH_ASSOC);

            if ($st) {
                $stId = $st['id'];
                $updateStStmt = $pdo->prepare("UPDATE supplier_transactions SET amount = ?, balance = balance - ?, remarks = CONCAT('Updated: ', remarks) WHERE id = ?");
                $updateStStmt->execute([$newSupplierPenalty, $supplierDiff, $stId]);

                // Update subsequent supplier transactions
                $updateSubStStmt = $pdo->prepare("UPDATE supplier_transactions SET balance = balance - ? WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND id > ? ORDER BY id ASC");
                $updateSubStStmt->execute([$supplierDiff, $supplierId, $tenant_id, $branch_id, $stId]);
            }
        }
    }

    // --- ACTIVITY LOG ---
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_values = [
        'supplier_penalty' => $oldSupplierPenalty,
        'service_penalty' => $oldServicePenalty,
        'remarks' => $dc['remarks'],
    ];
    $new_values = [
        'supplier_penalty' => $newSupplierPenalty,
        'service_penalty' => $newServicePenalty,
        'remarks' => $newRemarks,
    ];

    $logStmt = $pdo->prepare("INSERT INTO activity_log (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id) VALUES (?, ?, 'update', 'date_change_tickets', ?, ?, ?, ?, ?, NOW(), ?)");
    $logStmt->execute([$tenant_id, $user_id, $dcId, json_encode($old_values), json_encode($new_values), $ip_address, $user_agent, $branch_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Date change penalties updated successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
