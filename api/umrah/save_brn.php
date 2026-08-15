<?php
/**
 * Save BRN API — optional per-booking Booking Reference Number (BRN)
 * procurement cost.
 *
 * Stores one umrah_brn_costs row per booking (supplier + cost, converted
 * with the same currency/rate rules as fulfillments), keeps the supplier
 * exposure in sync via supplier_transactions ("BRN for {member}"), and
 * folds the cost into the booking totals (price = Σ base_price + Σ BRN,
 * profit = sold - discount - price).
 *
 * Family/group scope mirrors save_multi_fulfillment.php: one shared cost
 * applied to every active member of the family (or of all families in the
 * source family's group), converted per booking currency.
 *
 * POST:
 *   booking_id         int    source booking (member mode, or the member whose
 *                             family drives a multi-member scope)
 *   family_id          int    optional; target family (defaults to the source
 *                             booking's family)
 *   scope              string optional; 'group' applies to all families of the
 *                             source family's group (default 'family')
 *   supplier_id        int|'' optional
 *   supplier_currency  string USD|AFS|SAR
 *   supplier_cost      float|'' optional
 *   exchange_rate      float|'' optional
 *   notes              string optional
 *
 * Leaving supplier_id AND supplier_cost empty removes the BRN record (its
 * supplier exposure is reversed, booking totals recomputed).
 */

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
umrah_require('fulfill');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

/**
 * Recompute a booking's totals including its BRN costs:
 * price = Σ base_price + Σ BRN cost_amount, profit = sold - discount - price.
 */
function brn_recalc_booking(PDO $pdo, int $bookingId, int $tenantId): void
{
    $baseStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(base_price, 0)), 0) FROM umrah_booking_services WHERE booking_id = ?");
    $baseStmt->execute([$bookingId]);
    $baseTotal = round((float)$baseStmt->fetchColumn(), 3);

    $brnStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(cost_amount, 0)), 0) FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ?");
    $brnStmt->execute([$bookingId, $tenantId]);
    $brnTotal = round((float)$brnStmt->fetchColumn(), 3);

    $bStmt = $pdo->prepare("SELECT sold_price, discount FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $bStmt->execute([$bookingId, $tenantId]);
    $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
    if (!$bRow) return;

    $price = round($baseTotal + $brnTotal, 3);
    $profit = round(((float)$bRow['sold_price'] - (float)$bRow['discount']) - $price, 3);
    $pdo->prepare("UPDATE umrah_bookings SET price = ?, profit = ? WHERE booking_id = ?")
        ->execute([$price, $profit, $bookingId]);
}

$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int') : 0;
if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
    exit;
}

// ---- Resolve the source booking -------------------------------------------
$bkStmt = $pdo->prepare("
    SELECT booking_id, name, family_id, currency, status
    FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
$bkStmt->execute([$booking_id, $tenant_id]);
$srcBooking = $bkStmt->fetch(PDO::FETCH_ASSOC);

if (!$srcBooking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
if (in_array((string)$srcBooking['status'], ['refunded', 'cancelled'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This booking is ' . $srcBooking['status'] . ' — BRN cannot be recorded.']);
    exit;
}

// ---- Scope: member | family | group -----------------------------------------
// Multi-member mode applies the shared BRN to every active booking of the
// target family / group (same scope resolution as save_multi_fulfillment.php).
$targetBookings = [];
$isMulti = isset($_POST['family_id']) && $_POST['family_id'] !== '';
$scope = (isset($_POST['scope']) && $_POST['scope'] === 'group') ? 'group' : 'family';
$familyCount = 1;

if ($isMulti) {
    $family_id = (int)$_POST['family_id'];
    $famOk = $pdo->prepare("SELECT 1 FROM families WHERE family_id = ? AND tenant_id = ?");
    $famOk->execute([$family_id, $tenant_id]);
    if (!$famOk->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Family does not belong to this tenant.']);
        exit;
    }
    $targetFamilies = [$family_id];
    if ($scope === 'group') {
        $grpStmt = $pdo->prepare("SELECT group_id FROM families WHERE family_id = ? AND tenant_id = ?");
        $grpStmt->execute([$family_id, $tenant_id]);
        $group_id = (int)$grpStmt->fetchColumn();
        if ($group_id > 0) {
            $gfStmt = $pdo->prepare("SELECT family_id FROM families WHERE group_id = ? AND tenant_id = ?");
            $gfStmt->execute([$group_id, $tenant_id]);
            $targetFamilies = $gfStmt->fetchAll(PDO::FETCH_COLUMN);
            $familyCount = count($targetFamilies);
        }
    }
    $placeholders = implode(',', array_fill(0, count($targetFamilies), '?'));
    $mbStmt = $pdo->prepare("
        SELECT booking_id, name, currency FROM umrah_bookings
        WHERE family_id IN ($placeholders) AND tenant_id = ? AND status NOT IN ('refunded', 'cancelled')
        ORDER BY booking_id");
    $mbStmt->execute(array_merge($targetFamilies, [$tenant_id]));
    $targetBookings = $mbStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$targetBookings) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No active members found for this ' . $scope . '.']);
        exit;
    }
} else {
    $targetBookings[] = $srcBooking;
}

// ---- Shared BRN fields -------------------------------------------------------
$supplier_id = (isset($_POST['supplier_id']) && $_POST['supplier_id'] !== '') ? (int)$_POST['supplier_id'] : null;
$supplier_currency = strtoupper(trim((string)($_POST['supplier_currency'] ?? '')));
$supplier_cost = (isset($_POST['supplier_cost']) && $_POST['supplier_cost'] !== '') ? (float)$_POST['supplier_cost'] : null;
$exchange_rate = (isset($_POST['exchange_rate']) && $_POST['exchange_rate'] !== '') ? (float)$_POST['exchange_rate'] : null;
$notes = trim((string)($_POST['notes'] ?? ''));

$removing = ($supplier_id === null && $supplier_cost === null);

// ---- Validation (skipped for removal) -----------------------------------------
if (!$removing) {
    if ($supplier_id) {
        $supOk = $pdo->prepare("SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? AND status = 'active'");
        $supOk->execute([$supplier_id, $tenant_id]);
        if (!$supOk->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Supplier is inactive or does not belong to this tenant.']);
            exit;
        }
    }
    if ($supplier_currency !== '' && !in_array($supplier_currency, ['USD', 'AFS', 'SAR'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid supplier currency. Allowed: USD, AFS, SAR.']);
        exit;
    }
    if ($supplier_cost !== null && $supplier_cost < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'BRN cost cannot be negative.']);
        exit;
    }
    if ($exchange_rate !== null && $exchange_rate <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Exchange rate must be greater than zero.']);
        exit;
    }
}

// ---- Visa gate ------------------------------------------------------------------
// The BRN is the Ministry's visa-linked reference: only members whose package
// actually includes a visa may carry a BRN cost. In multi-member scope every
// member without a visa line is skipped (never written). Member mode and
// removals are NOT gated — a direct save targets the chosen member, and an
// empty-save must still be able to clean up a stale record.
$visaBookings = [];
$skippedNoVisa = 0;
if (!$removing && $isMulti && $targetBookings) {
    $vbPh = implode(',', array_fill(0, count($targetBookings), '?'));
    $visaStmt = $pdo->prepare("
        SELECT DISTINCT bs.booking_id
        FROM umrah_booking_services bs
        LEFT JOIN umrah_services s ON bs.service_id = s.id
        LEFT JOIN umrah_service_categories c ON s.category_id = c.id
        WHERE bs.tenant_id = ?
          AND bs.booking_id IN ($vbPh)
          AND (LOWER(COALESCE(c.name, '')) = 'visa' OR LOWER(bs.service_type) = 'visa')");
    $visaStmt->execute(array_merge([$tenant_id], array_column($targetBookings, 'booking_id')));
    $visaBookings = array_fill_keys(array_map('intval', $visaStmt->fetchAll(PDO::FETCH_COLUMN)), true);
}

// ---- Apply the BRN record to every target booking ------------------------------
$applied = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    foreach ($targetBookings as $tb) {
        $tbBookingId = (int)$tb['booking_id'];
        $tbCurrency = strtoupper(trim((string)($tb['currency'] ?? 'USD'))) ?: 'USD';
        $tbName = (string)($tb['name'] ?? 'Member');

        // Members without a visa in their package never get a BRN row.
        if (!$removing && !isset($visaBookings[$tbBookingId])) {
            $skippedNoVisa++;
            continue;
        }

        // ---- Existing BRN record (unique per booking + tenant) ------------------
        $brnStmt = $pdo->prepare("SELECT * FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ? ORDER BY id LIMIT 1");
        $brnStmt->execute([$tbBookingId, $tenant_id]);
        $existing = $brnStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $oldSum = $existing !== null ? (float)$existing['cost_amount'] : 0.0;

        $oldSupStmt = $pdo->prepare("SELECT supplier_id FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ? AND supplier_id IS NOT NULL ORDER BY id LIMIT 1");
        $oldSupStmt->execute([$tbBookingId, $tenant_id]);
        $oldSupplierId = (int)$oldSupStmt->fetchColumn() ?: null;

        $remark = "BRN for {$tbName}";
        $corrRemark = "BRN cost correction for {$tbName}";

        // ---- Supplier exposure undo (removal or supplier switch) -----------------
        $undoSupplier = $removing || ($supplier_id !== null && $oldSupplierId !== null && $oldSupplierId !== $supplier_id)
            ? $oldSupplierId
            : null;
        if ($undoSupplier !== null) {
            $oldIdStmt = $pdo->prepare("
                SELECT MIN(id) FROM supplier_transactions
                WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                  AND (remarks = ? OR remarks = ?) AND tenant_id = ?");
            $oldIdStmt->execute([$undoSupplier, $tbBookingId, $remark, $corrRemark, $tenant_id]);
            $oldDeletedMinId = (int)($oldIdStmt->fetchColumn() ?: 0);

            $otStmt = $pdo->prepare("
                SELECT transaction_type, amount FROM supplier_transactions
                WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                  AND (remarks = ? OR remarks = ?) AND tenant_id = ?");
            $otStmt->execute([$undoSupplier, $tbBookingId, $remark, $corrRemark, $tenant_id]);
            $oldTxns = $otStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($oldTxns) {
                $net = 0.0;
                foreach ($oldTxns as $ot) {
                    $net += $ot['transaction_type'] === 'Debit' ? (float)$ot['amount'] : -((float)$ot['amount']);
                }
                if ($net != 0.0) {
                    $oTypeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
                    $oTypeStmt->execute([$undoSupplier, $tenant_id]);
                    if ($oTypeStmt->fetchColumn() === 'External') {
                        $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?")
                            ->execute([$net, $undoSupplier, $tenant_id]);
                    }
                }
                $pdo->prepare("
                    DELETE FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                      AND (remarks = ? OR remarks = ?) AND tenant_id = ?")
                    ->execute([$undoSupplier, $tbBookingId, $remark, $corrRemark, $tenant_id]);
            }
            // The deleted exposure rows were part of every subsequent running
            // balance of the old supplier — bring them back in sync.
            if ($oldDeletedMinId > 0) {
                umrahRebuildRunningBalances($pdo, $tenant_id, $branch_id, $undoSupplier, $oldDeletedMinId);
            }
        }

        if ($removing) {
            if ($existing) {
                $pdo->prepare("DELETE FROM umrah_brn_costs WHERE id = ?")->execute([(int)$existing['id']]);
                umrah_audit($pdo, 'delete', 'umrah_brn_costs', (int)$existing['id'],
                    ['booking_id' => $tbBookingId, 'cost_amount' => $oldSum], []);
            }
            brn_recalc_booking($pdo, $tbBookingId, $tenant_id);
            $applied++;
            continue;
        }

        // ---- Currency conversion (mirrors fulfillment_save's rules) ---------------
        $cur = $supplier_currency !== '' ? $supplier_currency : $tbCurrency;
        $cost_amount = null;
        if ($supplier_cost !== null) {
            if ($cur === $tbCurrency) {
                $cost_amount = round($supplier_cost, 3);
            } elseif ($exchange_rate !== null && $exchange_rate > 0) {
                $cost_amount = round($supplier_cost / $exchange_rate, 3);
            } else {
                $errors[] = ['member' => $tbName, 'message' => 'Exchange rate is required when the supplier currency differs from the booking currency (' . $tbCurrency . ').'];
                continue;
            }
        }

        // ---- Upsert the BRN row ----------------------------------------------------
        if ($existing) {
            $pdo->prepare("
                UPDATE umrah_brn_costs SET
                    supplier_id = ?, supplier_currency = ?, supplier_cost = ?, exchange_rate = ?,
                    cost_amount = ?, notes = ?, updated_at = NOW()
                WHERE id = ?")
                ->execute([$supplier_id, $cur, $supplier_cost, $exchange_rate, $cost_amount, $notes, (int)$existing['id']]);
            umrah_audit($pdo, 'update', 'umrah_brn_costs', (int)$existing['id'],
                ['supplier_id' => $oldSupplierId, 'supplier_cost' => $oldSum],
                ['supplier_id' => $supplier_id, 'supplier_cost' => $supplier_cost, 'cost_amount' => $cost_amount]);
        } else {
            $pdo->prepare("
                INSERT INTO umrah_brn_costs (
                    tenant_id, branch_id, booking_id, supplier_id, supplier_currency,
                    supplier_cost, exchange_rate, cost_amount, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$tenant_id, $branch_id, $tbBookingId, $supplier_id, $cur,
                           $supplier_cost, $exchange_rate, $cost_amount, $notes, $user_id]);
            umrah_audit($pdo, 'add', 'umrah_brn_costs', (int)$pdo->lastInsertId(),
                [], ['supplier_id' => $supplier_id, 'supplier_cost' => $supplier_cost, 'cost_amount' => $cost_amount]);
        }

        // ---- Supplier transaction (once per supplier + booking) --------------------
        if ($supplier_id !== null && $supplier_cost !== null && $supplier_cost > 0) {
            $supTypeStmt = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
            $supTypeStmt->execute([$supplier_id, $tenant_id]);
            $supplierType = $supTypeStmt->fetchColumn();

            $supAmtStmt = $pdo->prepare("
                SELECT COALESCE(SUM(COALESCE(supplier_cost, cost_amount)), 0)
                FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ? AND supplier_id = ?");
            $supAmtStmt->execute([$tbBookingId, $tenant_id, $supplier_id]);
            $supplierBase = round((float)$supAmtStmt->fetchColumn(), 3);

            $dupStmt = $pdo->prepare("
                SELECT id FROM supplier_transactions
                WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                  AND remarks = ? AND tenant_id = ? LIMIT 1");
            $dupStmt->execute([$supplier_id, $tbBookingId, $remark, $tenant_id]);

            if (!$dupStmt->fetchColumn()) {
                if ($supplierType === 'External') {
                    $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$supplierBase, $supplier_id, $tenant_id]);
                    $balStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
                    $balStmt->execute([$supplier_id, $tenant_id]);
                    $newBalance = (float)$balStmt->fetchColumn();
                    $pdo->prepare("
                        INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                        VALUES (?, ?, ?, ?, 'Debit', ?, ?, ?, 'umrah', '')")
                        ->execute([$tenant_id, $branch_id, $supplier_id, $tbBookingId, $supplierBase, $remark, $newBalance]);
                } else {
                    $pdo->prepare("
                        INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                        VALUES (?, ?, ?, ?, 'Debit', ?, ?, 0, 'umrah', '')")
                        ->execute([$tenant_id, $branch_id, $supplier_id, $tbBookingId, $supplierBase, $remark]);
                }
            } else {
                // Cost change while the record stays — net the correction row
                // to the live BRN cost (rebuild, never skip) so sign flips
                // and re-saves can't leave a stale BRN correction behind.
                brnReconcileSupplierExposure($pdo, $tenant_id, $branch_id, (int)$tbBookingId, (int)$supplier_id, (string)$tbName);
            }
        }

        brn_recalc_booking($pdo, $tbBookingId, $tenant_id);
        $applied++;
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$scopeLabel = $isMulti
    ? ($scope === 'group'
        ? ' across ' . $familyCount . ' famil' . ($familyCount === 1 ? 'y' : 'ies') . ' in the group'
        : ' in the family')
    : '';

echo json_encode([
    'success' => true,
    'message' => ($removing ? 'BRN removed' : 'BRN saved') . ($applied > 1 ? ' for ' . $applied . ' member' . ($applied === 1 ? '' : 's') . $scopeLabel : '')
        . ($skippedNoVisa > 0 ? ', skipped: ' . $skippedNoVisa . ' member' . ($skippedNoVisa === 1 ? '' : 's') . ' without visa in package' : ''),
    'applied' => $applied,
    'skipped_no_visa' => $skippedNoVisa,
    'removed' => $removing,
    'errors' => $errors,
]);
