<?php
/**
 * Save Transport Contract API (Phase 24+)
 * Transport contracts use the same pricing scheme as hotel contracts
 * (contract_type 'period' | 'per_trip') but are amount-based: the single
 * contracted amount is divided among the trip's members at fulfillment time.
 *   action=save | toggle | delete
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
require_permission('umrah.transport_manage');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../../includes/db.php';

$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string') : 'save';
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int') : 0;

if (!in_array($action, ['save', 'toggle', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$pdo->beginTransaction();
try {
    if ($action === 'toggle') {
        $row = $pdo->prepare("SELECT status FROM umrah_transport_contracts WHERE id = ? AND tenant_id = ?");
        $row->execute([$id, $tenant_id]);
        $cur = $row->fetchColumn();
        if ($cur === false) throw new Exception('Contract not found.');
        $new = $cur === 'active' ? 'inactive' : 'active';
        $upd = $pdo->prepare("UPDATE umrah_transport_contracts SET status = ? WHERE id = ? AND tenant_id = ?");
        $upd->execute([$new, $id, $tenant_id]);
        umrah_audit($pdo, 'update', 'umrah_transport_contracts', $id, ['status' => $cur], ['status' => $new]);
        echo json_encode(['success' => true, 'message' => 'Contract status updated.', 'status' => $new]);
        $pdo->commit();
        exit;
    }

    if ($action === 'delete') {
        if (!$id) throw new Exception('Contract ID required.');
        $oldRow = $pdo->prepare("SELECT * FROM umrah_transport_contracts WHERE id = ? AND tenant_id = ?");
        $oldRow->execute([$id, $tenant_id]);
        $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
        $del = $pdo->prepare("DELETE FROM umrah_transport_contracts WHERE id = ? AND tenant_id = ?");
        $del->execute([$id, $tenant_id]);
        umrah_audit($pdo, 'delete', 'umrah_transport_contracts', $id, $old, []);
        echo json_encode(['success' => true, 'message' => 'Contract deleted.']);
        $pdo->commit();
        exit;
    }

    // ---- save -------------------------------------------------------------
    $supplier_id = !empty($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int') : null;
    $contract_number = DbSecurity::validateInput($_POST['contract_number'] ?? '', 'string');
    $contract_type = in_array($_POST['contract_type'] ?? '', ['period', 'per_trip'], true) ? $_POST['contract_type'] : 'per_trip';
    $contract_amount = (isset($_POST['contract_amount']) && $_POST['contract_amount'] !== '' && $_POST['contract_amount'] !== null)
        ? (float)DbSecurity::validateInput($_POST['contract_amount'], 'float') : null;
    $contract_currency = strtoupper(trim((string)($_POST['contract_currency'] ?? 'USD')));
    if (!in_array($contract_currency, ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'], true)) { $contract_currency = 'USD'; }
    if ($contract_amount === null || $contract_amount <= 0) {
        throw new Exception('A contracted amount is required.');
    }
    $valid_from = !empty($_POST['valid_from']) ? DbSecurity::validateInput($_POST['valid_from'], 'string') : null;
    $valid_to = !empty($_POST['valid_to']) ? DbSecurity::validateInput($_POST['valid_to'], 'string') : null;
    $payment_terms = DbSecurity::validateInput($_POST['payment_terms'] ?? '', 'string');
    $notes = DbSecurity::validateInput($_POST['notes'] ?? '', 'string');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'expired'], true) ? $_POST['status'] : 'active';

    if (!$contract_number) throw new Exception('Contract number is required.');
    if ($supplier_id) {
        $supOk = $pdo->prepare("SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? AND status = 'active'");
        $supOk->execute([$supplier_id, $tenant_id]);
        if (!$supOk->fetchColumn()) throw new Exception('Supplier is inactive or does not belong to this tenant.');
    }

    if ($id) {
        $oldRow = $pdo->prepare("SELECT * FROM umrah_transport_contracts WHERE id = ? AND tenant_id = ?");
        $oldRow->execute([$id, $tenant_id]);
        $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];
        $upd = $pdo->prepare("UPDATE umrah_transport_contracts SET supplier_id=?, contract_number=?, contract_type=?,
                                     contract_amount=?, contract_currency=?, valid_from=?, valid_to=?, payment_terms=?, notes=?, status=?, updated_at=NOW()
                              WHERE id = ? AND tenant_id = ?");
        $upd->execute([$supplier_id, $contract_number, $contract_type, $contract_amount, $contract_currency,
                       $valid_from, $valid_to, $payment_terms, $notes, $status, $id, $tenant_id]);
        umrah_audit($pdo, 'update', 'umrah_transport_contracts', $id, $old, [
            'supplier_id' => $supplier_id, 'contract_number' => $contract_number,
            'contract_type' => $contract_type,
            'contract_amount' => $contract_amount, 'contract_currency' => $contract_currency,
            'valid_from' => $valid_from, 'valid_to' => $valid_to,
            'payment_terms' => $payment_terms, 'notes' => $notes, 'status' => $status,
        ]);
        $msg = 'Contract updated.';
    } else {
        $ins = $pdo->prepare("INSERT INTO umrah_transport_contracts (tenant_id, branch_id, supplier_id,
                                    contract_number, contract_type, contract_amount, contract_currency,
                                    valid_from, valid_to, payment_terms, notes, status, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$tenant_id, $branch_id, $supplier_id, $contract_number, $contract_type,
                       $contract_amount, $contract_currency, $valid_from, $valid_to, $payment_terms, $notes, $status, $user_id]);
        $id = (int)$pdo->lastInsertId();
        umrah_audit($pdo, 'add', 'umrah_transport_contracts', $id, [], [
            'supplier_id' => $supplier_id, 'contract_number' => $contract_number,
            'contract_type' => $contract_type,
            'contract_amount' => $contract_amount, 'contract_currency' => $contract_currency,
            'valid_from' => $valid_from, 'valid_to' => $valid_to,
            'payment_terms' => $payment_terms, 'notes' => $notes, 'status' => $status,
        ]);
        $msg = 'Contract created.';
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $msg, 'id' => $id]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
