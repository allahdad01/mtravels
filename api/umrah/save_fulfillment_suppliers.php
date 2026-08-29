<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../admin/security.php';
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$fulfillmentId = isset($input['fulfillment_id']) ? (int)$input['fulfillment_id'] : 0;
$makkahSupplierId = isset($input['makkah_supplier_id']) && $input['makkah_supplier_id'] !== '' ? (int)$input['makkah_supplier_id'] : null;
$madinahSupplierId = isset($input['madinah_supplier_id']) && $input['madinah_supplier_id'] !== '' ? (int)$input['madinah_supplier_id'] : null;

if ($fulfillmentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'fulfillment_id required']);
    exit;
}

try {
    $ffStmt = $pdo->prepare("SELECT * FROM umrah_fulfillments WHERE id = ? AND tenant_id = ?");
    $ffStmt->execute([$fulfillmentId, $tenant_id]);
    $ff = $ffStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ff) {
        echo json_encode(['success' => false, 'message' => 'Fulfillment not found']);
        exit;
    }

    $bookingServiceId = (int)$ff['booking_service_id'];

    $svcStmt = $pdo->prepare("SELECT booking_id, service_type FROM umrah_booking_services WHERE id = ? AND tenant_id = ?");
    $svcStmt->execute([$bookingServiceId, $tenant_id]);
    $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);
    $bookingId = (int)($svc['booking_id'] ?? 0);
    $serviceType = $svc['service_type'] ?? 'hotel';

    $memberStmt = $pdo->prepare("
        SELECT ub.name, ub.is_extra_bed, f.head_of_family
        FROM umrah_bookings ub
        LEFT JOIN families f ON f.family_id = ub.family_id AND f.tenant_id = ub.tenant_id
        WHERE ub.booking_id = ? AND ub.tenant_id = ?");
    $memberStmt->execute([$bookingId, $tenant_id]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $memberName = (string)($member['name'] ?? '') ?: 'Member';
    $familyName = trim((string)($member['head_of_family'] ?? ''));
    $memberLabel = $familyName !== '' ? $memberName . ' (' . $familyName . ' family)' : $memberName;

    $bothStmt = $pdo->prepare("SELECT * FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ? ORDER BY id");
    $bothStmt->execute([$bookingServiceId, $tenant_id]);
    $both = $bothStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // Collect OLD supplier IDs before updating
    $oldSupIds = [];
    foreach ($both as $b) {
        if (!empty($b['supplier_id'])) $oldSupIds[(int)$b['supplier_id']] = true;
    }

    // --- Step 1: Update fulfillment rows ---
    if (count($both) >= 2) {
        if ($makkahSupplierId !== null) {
            $pdo->prepare("UPDATE umrah_fulfillments SET supplier_id = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$makkahSupplierId, (int)$both[0]['id'], $tenant_id]);
        }
        if ($madinahSupplierId !== null) {
            $pdo->prepare("UPDATE umrah_fulfillments SET supplier_id = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$madinahSupplierId, (int)$both[1]['id'], $tenant_id]);
        }
    } elseif (count($both) === 1) {
        if ($makkahSupplierId !== null && $madinahSupplierId !== null) {
            $pdo->prepare("UPDATE umrah_fulfillments SET supplier_id = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$makkahSupplierId, (int)$both[0]['id'], $tenant_id]);
            $src = $both[0];
            $pdo->prepare("
                INSERT INTO umrah_fulfillments
                (tenant_id, branch_id, booking_service_id, family_id, fulfillment_type,
                 supplier_id, status, supplier_currency, supplier_cost, exchange_rate,
                 cost_amount, created_at)
                VALUES (?, ?, ?, ?, 'hotel', ?, 'pending', ?, ?, ?, ?, NOW())
            ")->execute([
                $tenant_id, $src['branch_id'], $bookingServiceId,
                $src['family_id'] ?? null,
                $madinahSupplierId,
                $src['supplier_currency'], $src['supplier_cost'],
                $src['exchange_rate'], $src['cost_amount'],
            ]);
        } elseif ($makkahSupplierId !== null) {
            $pdo->prepare("UPDATE umrah_fulfillments SET supplier_id = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$makkahSupplierId, (int)$both[0]['id'], $tenant_id]);
        } elseif ($madinahSupplierId !== null) {
            $pdo->prepare("UPDATE umrah_fulfillments SET supplier_id = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$madinahSupplierId, (int)$both[0]['id'], $tenant_id]);
        }
    }

    // Re-read fulfillments after update
    $bothStmt2 = $pdo->prepare("SELECT * FROM umrah_fulfillments WHERE booking_service_id = ? AND tenant_id = ? ORDER BY id");
    $bothStmt2->execute([$bookingServiceId, $tenant_id]);
    $both = $bothStmt2->fetchAll(PDO::FETCH_ASSOC);

    // --- Step 2: Update city_* detail rows on first fulfillment ---
    if (!empty($both)) {
        $detailFid = (int)$both[0]['id'];
        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_fulfillment_details WHERE fulfillment_id = ? AND detail_key = 'city_makkah_supplier_id'");
        $chkStmt->execute([$detailFid]);
        $hasDetails = (int)$chkStmt->fetchColumn() > 0;

        if ($hasDetails) {
            if ($makkahSupplierId !== null) {
                $pdo->prepare("UPDATE umrah_fulfillment_details SET detail_value = ? WHERE fulfillment_id = ? AND detail_key = 'city_makkah_supplier_id'")
                    ->execute([$makkahSupplierId, $detailFid]);
            }
            if ($madinahSupplierId !== null) {
                $pdo->prepare("UPDATE umrah_fulfillment_details SET detail_value = ? WHERE fulfillment_id = ? AND detail_key = 'city_madinah_supplier_id'")
                    ->execute([$madinahSupplierId, $detailFid]);
            }
        } else {
            if ($makkahSupplierId !== null) {
                $pdo->prepare("INSERT INTO umrah_fulfillment_details (tenant_id, branch_id, fulfillment_id, detail_key, detail_value) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$tenant_id, $branch_id, $detailFid, 'city_makkah_supplier_id', (string)$makkahSupplierId]);
            }
            if ($madinahSupplierId !== null) {
                $pdo->prepare("INSERT INTO umrah_fulfillment_details (tenant_id, branch_id, fulfillment_id, detail_key, detail_value) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$tenant_id, $branch_id, $detailFid, 'city_madinah_supplier_id', (string)$madinahSupplierId]);
            }
        }
    }

    // --- Step 3: Undo OLD supplier transactions ---
    $remark = "Fulfillment for {$serviceType}: {$memberLabel}";
    $corrRemark = "Fulfillment cost correction for {$serviceType}: {$memberLabel}";

    foreach (array_keys($oldSupIds) as $oldSid) {
        // Use LIKE to match any fulfillment remark for this service type + booking
        $oldTxnStmt = $pdo->prepare("
            SELECT id, transaction_type, amount, supplier_id, remarks FROM supplier_transactions
            WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
              AND remarks LIKE ? AND tenant_id = ?");
        $oldTxnStmt->execute([$oldSid, $bookingId, "Fulfillment for {$serviceType}:%", $tenant_id]);
        $oldTxns = $oldTxnStmt->fetchAll(PDO::FETCH_ASSOC);

        // Also fetch correction rows
        $corrStmt = $pdo->prepare("
            SELECT id, transaction_type, amount, supplier_id, remarks FROM supplier_transactions
            WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
              AND remarks LIKE ? AND tenant_id = ?");
        $corrStmt->execute([$oldSid, $bookingId, "Fulfillment cost correction for {$serviceType}:%", $tenant_id]);
        $oldTxns = array_merge($oldTxns, $corrStmt->fetchAll(PDO::FETCH_ASSOC));

        // Deduplicate by id
        $seen = [];
        $deduped = [];
        foreach ($oldTxns as $ot) {
            if (!isset($seen[(int)$ot['id']])) {
                $seen[(int)$ot['id']] = true;
                $deduped[] = $ot;
            }
        }
        $oldTxns = $deduped;

        if ($oldTxns) {
            $net = 0.0;
            $minId = PHP_INT_MAX;
            foreach ($oldTxns as $ot) {
                $net += $ot['transaction_type'] === 'Debit' ? (float)$ot['amount'] : -((float)$ot['amount']);
                $minId = min($minId, (int)$ot['id']);
            }
            if ($net != 0.0) {
                $typeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
                $typeStmt->execute([$oldSid, $tenant_id]);
                if ((string)$typeStmt->fetchColumn() === 'External') {
                    $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$net, $oldSid, $tenant_id]);
                }
            }

            $delIds = array_column($oldTxns, 'id');
            if ($delIds) {
                $ph = implode(',', array_fill(0, count($delIds), '?'));
                $pdo->prepare("DELETE FROM supplier_transactions WHERE id IN ($ph) AND tenant_id = ?")
                    ->execute(array_merge($delIds, [$tenant_id]));
            }
        }
    }

    // --- Step 4: Create NEW supplier transactions (one per new supplier) ---
    // Read per-city costs from fulfillment_details (any fulfillment that has them)
    $makCost = 0;
    $madCost = 0;
    foreach ($both as $ffRow) {
        if ($makCost > 0 && $madCost > 0) break;
        $ccStmt = $pdo->prepare("SELECT detail_key, detail_value FROM umrah_fulfillment_details WHERE fulfillment_id = ? AND detail_key IN ('city_makkah_cost', 'city_madinah_cost')");
        $ccStmt->execute([(int)$ffRow['id']]);
        foreach ($ccStmt->fetchAll(PDO::FETCH_ASSOC) as $ccRow) {
            if ($ccRow['detail_key'] === 'city_makkah_cost' && (float)$ccRow['detail_value'] > 0) {
                $makCost = (float)$ccRow['detail_value'];
            }
            if ($ccRow['detail_key'] === 'city_madinah_cost' && (float)$ccRow['detail_value'] > 0) {
                $madCost = (float)$ccRow['detail_value'];
            }
        }
    }

    // Map new supplier IDs to their city costs
    $supplierCosts = [];
    if ($makkahSupplierId && $makCost > 0) {
        $supplierCosts[$makkahSupplierId] = ($supplierCosts[$makkahSupplierId] ?? 0) + $makCost;
    }
    if ($madinahSupplierId && $madCost > 0) {
        $supplierCosts[$madinahSupplierId] = ($supplierCosts[$madinahSupplierId] ?? 0) + $madCost;
    }

    foreach ($supplierCosts as $newSid => $totalCost) {
        $totalCost = round($totalCost, 3);
        if ($totalCost <= 0) continue;

        $typeStmt2 = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $typeStmt2->execute([$newSid, $tenant_id]);
        $supType = (string)$typeStmt2->fetchColumn();

        // Check if a transaction already exists for this supplier+booking+service type
        $chkTxn = $pdo->prepare("SELECT id FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND remarks LIKE ? AND tenant_id = ? LIMIT 1");
        $chkTxn->execute([$newSid, $bookingId, "Fulfillment for {$serviceType}:%", $tenant_id]);
        if ((int)$chkTxn->fetchColumn() > 0) continue;

        // Insert with balance = 0; rebuild will fix it below
        if ($supType === 'External') {
            $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                ->execute([$totalCost, $newSid, $tenant_id]);
        }
        $pdo->prepare("
            INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
            VALUES (?, ?, ?, ?, 'Debit', ?, ?, 0, 'umrah', '')")
            ->execute([$tenant_id, $branch_id, $newSid, $bookingId, $totalCost, $remark]);
    }

    // --- Step 5: Rebuild running balances for ALL affected suppliers ---
    $allAffectedSupIds = array_unique(array_merge(array_keys($oldSupIds), array_keys($supplierCosts)));
    foreach ($allAffectedSupIds as $supId) {
        $minTxnStmt = $pdo->prepare("SELECT MIN(id) FROM supplier_transactions WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ?");
        $minTxnStmt->execute([$supId, $tenant_id, $branch_id]);
        $minId = (int)($minTxnStmt->fetchColumn() ?: 0);
        if ($minId > 0) {
            umrahRebuildRunningBalances($pdo, $tenant_id, $branch_id, $supId, $minId);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Suppliers and transactions updated']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
