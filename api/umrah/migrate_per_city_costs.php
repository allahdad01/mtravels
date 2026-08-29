<?php
/**
 * Migration: Convert single-supplier hotel fulfillments to per-city
 * (Makkah + Madinah) structure.
 *
 * What it does:
 * 1. Finds hotel fulfillments that have NO city_* detail rows (old format)
 * 2. Creates a second fulfillment row (Madinah) cloned from the first
 * 3. Inserts city_* detail rows on the first fulfillment (costs left empty)
 * 4. Migrates extra bed eb_* legacy data to eb_makkah_* keys
 *
 * Usage: php migrate_per_city_costs.php --tenant=<id> [--apply]
 * Without --apply it runs in dry-run mode and shows what would be changed.
 */

$apply = in_array('--apply', $argv);
$tenantId = 0;
foreach ($argv as $arg) {
    if (preg_match('/--tenant=(\d+)/', $arg, $m)) { $tenantId = (int)$m[1]; }
}
if ($tenantId <= 0) {
    fwrite(STDERR, "Usage: php migrate_per_city_costs.php --tenant=<id> [--apply]\n");
    exit(1);
}

require_once __DIR__ . '/../../includes/dbcon.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$pdo->beginTransaction();
try {
    // ---- 1. Hotel fulfillments: create per-city structure ----
    // Find hotel fulfillments that have NO city_* detail rows
    $stmt = $pdo->prepare("
        SELECT f.id AS fulfillment_id, f.tenant_id, f.branch_id,
               f.supplier_id, f.supplier_currency, f.supplier_cost,
               f.exchange_rate, f.cost_amount, f.booking_service_id,
               bs.booking_id, bs.service_type
        FROM umrah_fulfillments f
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
        WHERE f.tenant_id = ?
          AND LOWER(f.fulfillment_type) = 'hotel'
          AND NOT EXISTS (
              SELECT 1 FROM umrah_fulfillment_details fd
              WHERE fd.fulfillment_id = f.id AND fd.detail_key LIKE 'city_%'
          )
        ORDER BY f.id ASC
    ");
    $stmt->execute([$tenantId]);
    $hotelFulfills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($hotelFulfills) . " hotel fulfillments to migrate.\n";

    // Group by booking_service_id (each service may have 1 or 2 fulfillments)
    $byService = [];
    foreach ($hotelFulfills as $hf) {
        $sid = (int)$hf['booking_service_id'];
        $byService[$sid][] = $hf;
    }

    $hotelMigrated = 0;
    $hotelSkipped = 0;

    foreach ($byService as $svcId => $rows) {
        if (count($rows) >= 2) {
            // Already has 2 fulfillments (Makkah + Madinah) — just needs city_* details
            // Use the first fulfillment's supplier as Makkah, second as Madinah
            $mak = $rows[0];
            $mad = $rows[1];
        } elseif (count($rows) === 1) {
            // Single fulfillment — clone it for Madinah
            $mak = $rows[0];
            $mad = null;
        } else {
            continue;
        }

        // Check if city_* details already exist on the first fulfillment
        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_fulfillment_details WHERE fulfillment_id = ? AND detail_key LIKE 'city_%'");
        $chkStmt->execute([(int)$mak['fulfillment_id']]);
        if ((int)$chkStmt->fetchColumn() > 0) {
            $hotelSkipped++;
            continue; // Already migrated
        }

        echo "\nService #{$svcId} (Booking #{$mak['booking_id']}):\n";
        echo "  Makkah fulfillment #{$mak['fulfillment_id']}: supplier_id={$mak['supplier_id']}, cost={$mak['supplier_cost']}, currency={$mak['supplier_currency']}\n";

        if (!$mad) {
            // Clone the Makkah fulfillment to create Madinah
            $insStmt = $pdo->prepare("
                INSERT INTO umrah_fulfillments
                (tenant_id, branch_id, booking_service_id, family_id, fulfillment_type,
                 supplier_id, status, supplier_currency, supplier_cost, exchange_rate,
                 cost_amount, created_at)
                VALUES (?, ?, ?, ?, 'hotel', ?, 'pending', ?, ?, ?, ?, NOW())
            ");
            $insStmt->execute([
                $mak['tenant_id'], $mak['branch_id'], $mak['booking_service_id'],
                null, // family_id
                null, // supplier_id — leave empty for manual entry
                null, // supplier_currency
                null, // supplier_cost
                null, // exchange_rate
                null, // cost_amount
            ]);
            $newMadId = (int)$pdo->lastInsertId();
            echo "  Created Madinah fulfillment #{$newMadId} (empty — fill manually)\n";
        } else {
            $newMadId = (int)$mad['fulfillment_id'];
            echo "  Madinah fulfillment #{$newMadId}: supplier_id={$mad['supplier_id']}, cost={$mad['supplier_cost']}, currency={$mad['supplier_currency']}\n";
        }

        // Insert city_* detail rows on the first fulfillment (costs empty)
        if ($apply) {
            $detailFid = (int)$mak['fulfillment_id'];
            $delStmt = $pdo->prepare("DELETE FROM umrah_fulfillment_details WHERE fulfillment_id = ? AND detail_key LIKE 'city_%'");
            $delStmt->execute([$detailFid]);

            $cityPairs = [
                'city_makkah_currency'    => $mak['supplier_currency'] ?? '',
                'city_makkah_cost'        => '',  // Leave empty for manual entry
                'city_makkah_rate'        => '',
                'city_makkah_cost_amount' => '',
                'city_makkah_supplier_id' => '',  // Leave empty for manual entry
                'city_madinah_currency'    => ($mad['supplier_currency'] ?? ''),
                'city_madinah_cost'        => '',
                'city_madinah_rate'        => '',
                'city_madinah_cost_amount' => '',
                'city_madinah_supplier_id' => '',
            ];
            $insD = $pdo->prepare("INSERT INTO umrah_fulfillment_details (tenant_id, branch_id, fulfillment_id, detail_key, detail_value) VALUES (?, ?, ?, ?, ?)");
            foreach ($cityPairs as $k => $v) {
                $insD->execute([$tenantId, $mak['branch_id'], $detailFid, $k, $v]);
            }
            echo "  Inserted city_* detail rows on fulfillment #{$detailFid}\n";
        } else {
            echo "  [DRY RUN] Would insert city_* detail rows on fulfillment #{$mak['fulfillment_id']}\n";
        }

        $hotelMigrated++;
    }

    // ---- 2. Extra beds: migrate eb_* to eb_makkah_* ----
    $ebStmt = $pdo->prepare("
        SELECT DISTINCT fd.fulfillment_id, f.tenant_id, f.branch_id
        FROM umrah_fulfillment_details fd
        JOIN umrah_fulfillments f ON f.id = fd.fulfillment_id AND f.tenant_id = fd.tenant_id
        WHERE f.tenant_id = ?
          AND fd.detail_key = 'eb_currency'
          AND NOT EXISTS (
              SELECT 1 FROM umrah_fulfillment_details fd2
              WHERE fd2.fulfillment_id = fd.fulfillment_id AND fd2.detail_key = 'eb_makkah_supplier_id'
          )
    ");
    $ebStmt->execute([$tenantId]);
    $ebFulfills = $ebStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nFound " . count($ebFulfills) . " extra bed fulfillments to migrate.\n";

    $ebMigrated = 0;
    foreach ($ebFulfills as $ebf) {
        $fid = (int)$ebf['fulfillment_id'];

        // Load existing eb_* values
        $loadStmt = $pdo->prepare("SELECT detail_key, detail_value FROM umrah_fulfillment_details WHERE fulfillment_id = ? AND detail_key LIKE 'eb_%'");
        $loadStmt->execute([$fid]);
        $ebDetails = [];
        foreach ($loadStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ebDetails[$r['detail_key']] = $r['detail_value'];
        }

        echo "\nExtra bed fulfillment #{$fid}:\n";
        echo "  eb_currency=" . ($ebDetails['eb_currency'] ?? '') . ", eb_cost=" . ($ebDetails['eb_cost'] ?? '') . ", eb_cost_usd=" . ($ebDetails['eb_cost_usd'] ?? '') . "\n";

        if ($apply) {
            // Insert eb_makkah_* rows from legacy eb_* data
            $insD = $pdo->prepare("INSERT INTO umrah_fulfillment_details (tenant_id, branch_id, fulfillment_id, detail_key, detail_value) VALUES (?, ?, ?, ?, ?)");
            $ebMakPairs = [
                'eb_makkah_supplier_id' => '',  // No supplier mapping from legacy data
                'eb_makkah_currency'    => $ebDetails['eb_currency'] ?? '',
                'eb_makkah_cost'        => $ebDetails['eb_cost'] ?? '',
                'eb_makkah_rate'        => $ebDetails['eb_rate'] ?? '',
                'eb_makkah_cost_usd'    => $ebDetails['eb_cost_usd'] ?? '',
                'eb_madinah_supplier_id'=> '',
                'eb_madinah_currency'   => '',
                'eb_madinah_cost'       => '',
                'eb_madinah_rate'       => '',
                'eb_madinah_cost_usd'   => '',
            ];
            foreach ($ebMakPairs as $k => $v) {
                $insD->execute([$tenantId, $ebf['branch_id'], $fid, $k, $v]);
            }
            echo "  Migrated to eb_makkah_* (currency/cost/rate)\n";
        } else {
            echo "  [DRY RUN] Would migrate eb_* to eb_makkah_*\n";
        }

        $ebMigrated++;
    }

    if ($apply) {
        $pdo->commit();
        echo "\n=== Migration applied successfully ===\n";
    } else {
        $pdo->rollBack();
        echo "\n=== DRY RUN — no changes made. Re-run with --apply to execute. ===\n";
    }

    echo "\nSummary:\n";
    echo "  Hotel fulfillments migrated: {$hotelMigrated}\n";
    echo "  Hotel fulfillments skipped (already migrated): {$hotelSkipped}\n";
    echo "  Extra bed fulfillments migrated: {$ebMigrated}\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
