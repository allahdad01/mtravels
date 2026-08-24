<?php
/**
 * Extra Bed Cleanup Script
 * 
 * Finds extra bed pseudo-members and their related client/supplier transactions.
 * Shows what will be deleted/restored, then waits for confirmation before acting.
 * 
 * Usage:
 *   GET  ?mode=preview           — show all extra beds + transactions (dry run)
 *   POST ?mode=delete            — actually delete and restore balances
 *   POST ?mode=delete&id=123     — delete a single extra bed by booking_id
 * 
 * Safe to run multiple times — preview is read-only.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$tenant_id = (int)($_GET['tenant_id'] ?? $_POST['tenant_id'] ?? 1);
$branch_id  = (int)($_GET['branch_id']  ?? $_POST['branch_id']  ?? 1);
$mode       = $_GET['mode'] ?? $_POST['mode'] ?? 'preview';
$singleId   = !empty($_GET['id']) ? (int)$_GET['id'] : (!empty($_POST['id']) ? (int)$_POST['id'] : null);

header('Content-Type: text/plain');

// ---------- PREVIEW MODE ---------------------------------------------------
if ($mode === 'preview') {
    echo "=== EXTRA BED CLEANUP PREVIEW ===\n";
    echo "Tenant: $tenant_id | Branch: $branch_id\n\n";

    $sql = "SELECT ub.booking_id, ub.name, ub.family_id, ub.sold_to, ub.sold_price,
                   ub.currency, ub.status, ub.is_extra_bed
            FROM umrah_bookings ub
            WHERE ub.tenant_id = ? AND ub.branch_id = ?
              AND ub.is_extra_bed = 1
              AND ub.status NOT IN ('refunded', 'cancelled')
            ORDER BY ub.family_id, ub.booking_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $branch_id]);
    $extraBeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$extraBeds) {
        echo "No active extra beds found.\n";
        exit;
    }

    echo "Found " . count($extraBeds) . " extra bed(s):\n";
    echo str_repeat('-', 100) . "\n";

    $totalClientTxnAmount = 0;
    $totalSupplierTxnAmount = 0;

    foreach ($extraBeds as $eb) {
        $bid = (int)$eb['booking_id'];
        echo "\n[Booking #$bid] {$eb['name']} (Family #{$eb['family_id']})\n";
        echo "  Status: {$eb['status']} | Sold to: " . ($eb['sold_to'] ?: 'none') . " | Sold Price: {$eb['sold_price']} {$eb['currency']}\n";

        // Client transactions
        $ctStmt = $pdo->prepare("
            SELECT id, type, amount, balance, currency, reference_id, transaction_of
            FROM client_transactions
            WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah'
              AND tenant_id = ? AND branch_id = ?
            ORDER BY id");
        $ctStmt->execute([$eb['sold_to'], $bid, $tenant_id, $branch_id]);
        $clientTxns = $ctStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($clientTxns) {
            echo "  CLIENT TRANSACTIONS (" . count($clientTxns) . "):\n";
            foreach ($clientTxns as $ct) {
                $amt = abs((float)$ct['amount']);
                $totalClientTxnAmount += ($ct['type'] === 'debit') ? $amt : -$amt;
                echo "    #{$ct['id']} | {$ct['type']} | Amount: {$amt} {$ct['currency']} | Balance: {$ct['balance']} | Ref: {$ct['reference_id']}\n";
            }
            // Client info
            $cliStmt = $pdo->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?");
            $cliStmt->execute([$eb['sold_to'], $tenant_id]);
            $cli = $cliStmt->fetch(PDO::FETCH_ASSOC);
            if ($cli) {
                echo "    Client: {$cli['name']} ({$cli['client_type']}) | USD: {$cli['usd_balance']} | AFS: {$cli['afs_balance']}\n";
                echo "    After restoration: USD will be " . ($cli['usd_balance'] + ($ct['type'] === 'debit' ? $ct['amount'] : -$ct['amount'])) . "\n";
            }
        } else {
            echo "  CLIENT TRANSACTIONS: none\n";
        }

        // Supplier transactions
        $stStmt = $pdo->prepare("
            SELECT st.id, st.supplier_id, st.transaction_type, st.amount, st.balance,
                   st.currency, st.transaction_of, st.reference_id, s.name AS supplier_name, s.supplier_type
            FROM supplier_transactions st
            JOIN suppliers s ON s.id = st.supplier_id
            WHERE st.transaction_of = 'umrah' AND st.reference_id = ?
              AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.id");
        $stStmt->execute([$bid, $tenant_id, $branch_id]);
        $supplierTxns = $stStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($supplierTxns) {
            echo "  SUPPLIER TRANSACTIONS (" . count($supplierTxns) . "):\n";
            foreach ($supplierTxns as $st) {
                $amt = abs((float)$st['amount']);
                $totalSupplierTxnAmount += ($st['transaction_type'] === 'Credit') ? -$amt : $amt;
                echo "    #{$st['id']} | {$st['supplier_name']} ({$st['supplier_type']}) | {$st['transaction_type']} | Amount: {$amt} {$st['currency']} | Balance: {$st['balance']}\n";
            }
        } else {
            echo "  SUPPLIER TRANSACTIONS: none\n";
        }

        // Fulfillments
        $fStmt = $pdo->prepare("
            SELECT f.id, f.status, f.supplier_id, f.cost_amount, f.supplier_cost
            FROM umrah_fulfillments f
            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
            WHERE bs.booking_id = ? AND bs.tenant_id = ? AND f.tenant_id = ?
            ORDER BY f.id");
        $fStmt->execute([$bid, $tenant_id, $tenant_id]);
        $fulfillments = $fStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($fulfillments) {
            echo "  FULFILLMENTS (" . count($fulfillments) . "):\n";
            foreach ($fulfillments as $f) {
                echo "    #{$f['id']} | Status: {$f['status']} | Cost: {$f['cost_amount']} | Supplier Cost: {$f['supplier_cost']}\n";
            }
        } else {
            echo "  FULFILLMENTS: none\n";
        }

        // Booking services
        $bsStmt = $pdo->prepare("
            SELECT id, service_type, status FROM umrah_booking_services
            WHERE booking_id = ? AND tenant_id = ?");
        $bsStmt->execute([$bid, $tenant_id]);
        $services = $bsStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  BOOKING SERVICES: " . count($services) . "\n";
    }

    echo "\n" . str_repeat('-', 100) . "\n";
    echo "TOTALS TO REVERSE:\n";
    echo "  Client transaction debits to reverse: " . number_format(abs($totalClientTxnAmount), 2) . "\n";
    echo "  Supplier transaction amounts to reverse: " . number_format(abs($totalSupplierTxnAmount), 2) . "\n";
    echo "\nTo delete these extra beds and restore balances, run:\n";
    echo "  POST ?mode=delete (all extra beds)\n";
    echo "  POST ?mode=delete&id=<booking_id> (single extra bed)\n";
    exit;
}

// ---------- DELETE MODE -----------------------------------------------------
if ($mode === 'delete') {
    $previewOnly = false;

    $sql = "SELECT booking_id, name, family_id, sold_to, sold_price, currency, status
            FROM umrah_bookings
            WHERE tenant_id = ? AND branch_id = ?
              AND is_extra_bed = 1
              AND status NOT IN ('refunded', 'cancelled')";
    if ($singleId) {
        $sql .= " AND booking_id = ?";
    }
    $sql .= " ORDER BY family_id, booking_id";

    $params = [$tenant_id, $branch_id];
    if ($singleId) $params[] = $singleId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $extraBeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$extraBeds) {
        echo "No extra beds found to delete.\n";
        exit;
    }

    echo "=== EXTRA BED DELETE + BALANCE RESTORE ===\n";
    echo "Deleting " . count($extraBeds) . " extra bed(s)...\n\n";

    $deleted = 0;
    $errors = 0;

    foreach ($extraBeds as $eb) {
        $bid = (int)$eb['booking_id'];
        $familyId = (int)$eb['family_id'];
        $clientId  = (int)$eb['sold_to'];
        $ebCurrency = strtoupper(trim((string)$eb['currency'] ?: 'USD'));

        echo "[Booking #$bid] {$eb['name']}...\n";

        try {
            $pdo->beginTransaction();

            // --- 1. Reverse client transactions ---
            if ($clientId && (float)$eb['sold_price'] > 0) {
                $ctStmt = $pdo->prepare("
                    SELECT id, amount, type FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                      AND tenant_id = ? AND branch_id = ?
                    ORDER BY id ASC");
                $ctStmt->execute([$clientId, $bid, $tenant_id, $branch_id]);
                $clientTxns = $ctStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($clientTxns) {
                    $cliStmt = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ?");
                    $cliStmt->execute([$clientId, $tenant_id]);
                    $clientType = $cliStmt->fetchColumn();

                    foreach ($clientTxns as $ct) {
                        $amt = abs((float)$ct['amount']);
                        $ctId = (int)$ct['id'];
                        $ctType = (string)$ct['type'];
                        $balField = ($ebCurrency === 'USD') ? 'usd_balance' : 'afs_balance';

                        // Update subsequent running balances
                        if ($ctType === 'debit') {
                            $pdo->prepare("UPDATE client_transactions SET balance = balance + ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$amt, $clientId, $ctId, $ebCurrency, $tenant_id, $branch_id]);
                        } else {
                            $pdo->prepare("UPDATE client_transactions SET balance = balance - ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$amt, $clientId, $ctId, $ebCurrency, $tenant_id, $branch_id]);
                        }

                        // Restore client balance (regular clients)
                        if ($clientType === 'regular') {
                            if ($ctType === 'debit') {
                                $pdo->prepare("UPDATE clients SET $balField = $balField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                    ->execute([$amt, $clientId, $tenant_id, $branch_id]);
                            } else {
                                $pdo->prepare("UPDATE clients SET $balField = $balField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                    ->execute([$amt, $clientId, $tenant_id, $branch_id]);
                            }
                        }

                        $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                            ->execute([$ctId, $tenant_id, $branch_id]);
                    }
                    echo "  Reversed " . count($clientTxns) . " client transaction(s)\n";
                }
            }

            // --- 2. Reverse supplier transactions ---
            $supStmt = $pdo->prepare("
                SELECT DISTINCT f.supplier_id, s.supplier_type
                FROM umrah_fulfillments f
                JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                JOIN suppliers s ON s.id = f.supplier_id
                WHERE bs.booking_id = ? AND bs.tenant_id = ? AND f.tenant_id = ?
                  AND f.supplier_id IS NOT NULL AND f.status != 'cancelled'");
            $supStmt->execute([$bid, $tenant_id, $tenant_id]);
            $suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($suppliers as $sup) {
                $supId = (int)$sup['supplier_id'];
                $supType = (string)$sup['supplier_type'];

                $supTxnStmt = $pdo->prepare("
                    SELECT id, amount, transaction_type FROM supplier_transactions
                    WHERE supplier_id = ? AND transaction_of = 'umrah'
                      AND reference_id = ? AND tenant_id = ? AND branch_id = ?
                    ORDER BY id ASC");
                $supTxnStmt->execute([$supId, $bid, $tenant_id, $branch_id]);
                $supTxns = $supTxnStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($supTxns as $st) {
                    $amt = abs((float)$st['amount']);
                    $stId = (int)$st['id'];
                    $stType = (string)$st['transaction_type'];

                    if ($supType === 'External') {
                        if ($stType === 'Credit') {
                            $pdo->prepare("UPDATE supplier_transactions SET balance = balance - ? WHERE supplier_id = ? AND id > ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$amt, $supId, $stId, $tenant_id, $branch_id]);
                            $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$amt, $supId, $tenant_id, $branch_id]);
                        } else {
                            $pdo->prepare("UPDATE supplier_transactions SET balance = balance + ? WHERE supplier_id = ? AND id > ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$amt, $supId, $stId, $tenant_id, $branch_id]);
                            $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$amt, $supId, $tenant_id, $branch_id]);
                        }
                    }
                    $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$stId, $tenant_id, $branch_id]);
                }
                if ($supTxns) {
                    echo "  Reversed " . count($supTxns) . " supplier transaction(s) for supplier #$supId\n";
                }
            }

            // --- 3. Delete fulfillments ---
            $pdo->prepare("
                DELETE FROM umrah_fulfillment_details
                WHERE fulfillment_id IN (
                    SELECT id FROM umrah_fulfillments WHERE booking_service_id IN (
                        SELECT id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?
                    ) AND tenant_id = ?
                )")->execute([$bid, $tenant_id, $tenant_id]);

            $fDel = $pdo->prepare("
                DELETE FROM umrah_fulfillments
                WHERE booking_service_id IN (
                    SELECT id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?
                ) AND tenant_id = ?");
            $fDel->execute([$bid, $tenant_id, $tenant_id]);
            echo "  Deleted " . $fDel->rowCount() . " fulfillment(s)\n";

            // --- 4. Delete booking services ---
            $bsDel = $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?");
            $bsDel->execute([$bid, $tenant_id]);
            echo "  Deleted " . $bsDel->rowCount() . " booking service(s)\n";

            // --- 5. Delete the extra bed booking ---
            $bDel = $pdo->prepare("DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
            $bDel->execute([$bid, $tenant_id]);
            echo "  Deleted booking #$bid\n";

            // --- 6. Recalculate family totals ---
            $famStmt = $pdo->prepare("SELECT COALESCE(SUM(sold_price),0) AS total FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND status NOT IN ('refunded','cancelled')");
            $famStmt->execute([$familyId, $tenant_id]);
            $famTotal = $famStmt->fetchColumn();
            $pdo->prepare("UPDATE families SET total_amount = ? WHERE family_id = ? AND tenant_id = ?")->execute([$famTotal, $familyId, $tenant_id]);
            echo "  Recalculated family #$familyId total: $famTotal\n";

            $pdo->commit();
            $deleted++;
            echo "  => OK\n\n";

        } catch (Exception $ex) {
            $pdo->rollBack();
            echo "  => ERROR: " . $ex->getMessage() . "\n\n";
            $errors++;
        }
    }

    echo str_repeat('-', 60) . "\n";
    echo "Done. Deleted: $deleted | Errors: $errors\n";
    exit;
}

echo "Invalid mode. Use ?mode=preview or ?mode=delete\n";
