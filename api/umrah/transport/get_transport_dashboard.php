<?php
/**
 * Get Transport Dashboard API — transport contracts + reference data
 * (suppliers) for the transport management page.
 */

require_once '../../../admin/includes/db_security.php';
require_once '../../../admin/security.php';
enforce_auth();
umrah_require('transport_manage');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../../includes/db.php';

// ---- Contracts ------------------------------------------------------------------
$cStmt = $pdo->prepare("SELECT id, supplier_id, contract_number, contract_type,
                               contract_amount, contract_currency, valid_from, valid_to,
                               payment_terms, notes, status
                        FROM umrah_transport_contracts WHERE tenant_id = ? ORDER BY valid_from DESC, id DESC");
$cStmt->execute([$tenant_id]);
$contracts = $cStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Suppliers (for contract form) ----------------------------------------------
$supStmt = $pdo->prepare("SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$supStmt->execute([$tenant_id]);
$suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

$supplierById = [];
foreach ($suppliers as $s) $supplierById[$s['id']] = $s;
foreach ($contracts as &$c) {
    $c['supplier_name'] = $supplierById[$c['supplier_id']]['name'] ?? null;
}
unset($c);

// ---- Overview stats --------------------------------------------------------------
$totalAmount = 0.0;
foreach ($contracts as $c) {
    if ($c['status'] === 'active' && $c['contract_amount'] !== null) {
        $totalAmount += (float)$c['contract_amount'];
    }
}
$stats = [
    'contracts'      => count(array_filter($contracts, fn($c) => $c['status'] === 'active')),
    'total_contracts'=> count($contracts),
    'contract_amount'=> round($totalAmount, 2),
];

echo json_encode([
    'success'   => true,
    'stats'     => $stats,
    'contracts' => $contracts,
    'suppliers' => $suppliers,
]);
