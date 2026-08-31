<?php
/**
 * Save Extra Transport API — creates/removes an extra transport pseudo-member for a family.
 *
 * POST action=add  → creates a new extra transport row in umrah_bookings + booking_services
 * POST action=remove → cancels the extra transport booking row + removes its fulfillment
 *
 * Returns { success, booking_id, name, ... } on create.
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
require_permission('umrah.fulfill');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id   = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$action     = $_POST['action'] ?? 'add';
$family_id  = (int)($_POST['family_id'] ?? 0);
$booking_service_id = (int)($_POST['booking_service_id'] ?? 0);

if (!$family_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'family_id is required.']);
    exit;
}

$pdo->beginTransaction();

try {
    if ($action === 'add') {
        // ---- Create extra transport pseudo-member in umrah_bookings --------
        // Get the family's context (package, sold_to, currency) from an existing member
        $ctxStmt = $pdo->prepare("
            SELECT ub.package_id, ub.sold_to, ub.paid_to, ub.currency, ub.exchange_rate
            FROM umrah_bookings ub
            WHERE ub.family_id = ? AND ub.tenant_id = ?
              AND ub.status NOT IN ('refunded', 'cancelled')
              AND ub.is_extra_transport = 0
            ORDER BY ub.booking_id LIMIT 1");
        $ctxStmt->execute([$family_id, $tenant_id]);
        $ctx = $ctxStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ctx) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'No active family members found to derive context.']);
            exit;
        }

        // Count existing extra transport entries to generate a sequence number
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND is_extra_transport = 1 AND status NOT IN ('refunded','cancelled')");
        $cntStmt->execute([$family_id, $tenant_id]);
        $etCount = (int)$cntStmt->fetchColumn();
        $etName = 'Extra Transport ' . ($etCount + 1);

        $ins = $pdo->prepare("
            INSERT INTO umrah_bookings (
                tenant_id, branch_id, family_id, package_id, sold_to, paid_to,
                name, fname, gfname, relation, dob, gender, room_type, is_extra_bed, is_extra_transport,
                price, sold_price, profit, paid, due, discount, received_bank_payment,
                currency, exchange_rate, status, created_by, remarks, entry_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, '', '', 'extra_transport', NULL, '', '', 0, 1,
                      0, 0, 0, 0, 0, 0, 0,
                      ?, 1.0000, 'active', ?, '', CURDATE())");
        $ins->execute([
            $tenant_id, $branch_id, $family_id,
            $ctx['package_id'], $ctx['sold_to'], $ctx['paid_to'],
            $etName,
            $ctx['currency'],
            $user_id
        ]);
        $newBookingId = (int)$pdo->lastInsertId();

        // If a booking_service_id was provided, create skeleton service lines
        // for the extra transport member (copy service structure from the source).
        if ($booking_service_id) {
            $svcStmt = $pdo->prepare("
                SELECT bs.service_type, bs.service_id, bs.pricing_unit, bs.quantity,
                       bs.is_optional, bs.sold_price, bs.currency, bs.service_rate_id,
                       bs.price_snapshot
                FROM umrah_booking_services bs
                WHERE bs.id = ? AND bs.tenant_id = ?");
            $svcStmt->execute([$booking_service_id, $tenant_id]);
            $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);

            if ($svc) {
                $insSvc = $pdo->prepare("
                    INSERT INTO umrah_booking_services (
                        tenant_id, branch_id, booking_id, service_type, service_id,
                        pricing_unit, quantity, is_optional, base_price, sold_price,
                        profit, currency, service_rate_id, price_snapshot, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'pending')");
                $insSvc->execute([
                    $tenant_id, $branch_id, $newBookingId,
                    $svc['service_type'], $svc['service_id'],
                    $svc['pricing_unit'], $svc['quantity'], $svc['is_optional'],
                    $svc['currency'], $svc['service_rate_id'], $svc['price_snapshot']
                ]);
                $newBookingServiceId = (int)$pdo->lastInsertId();

                // Pre-fill the extra transport fulfillment with parent transport's supplier info
                $parentFfStmt = $pdo->prepare("
                    SELECT supplier_id, supplier_currency, exchange_rate
                    FROM umrah_fulfillments
                    WHERE booking_service_id = ? AND tenant_id = ? AND status != 'cancelled'
                    LIMIT 1");
                $parentFfStmt->execute([$booking_service_id, $tenant_id]);
                $parentFf = $parentFfStmt->fetch(PDO::FETCH_ASSOC);

                if ($parentFf && !empty($parentFf['supplier_id'])) {
                    $parentSupplierId = (int)$parentFf['supplier_id'];
                    $parentSupplierCurrency = $parentFf['supplier_currency'] ?? 'USD';
                    $parentExchangeRate = (float)($parentFf['exchange_rate'] ?? 1);

                    $insFf = $pdo->prepare("
                        INSERT INTO umrah_fulfillments (
                            tenant_id, branch_id, booking_service_id, family_id,
                            supplier_id, supplier_currency, exchange_rate, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $insFf->execute([
                        $tenant_id, $branch_id, $newBookingServiceId, $family_id,
                        $parentSupplierId, $parentSupplierCurrency, $parentExchangeRate
                    ]);
                    $newFfId = (int)$pdo->lastInsertId();

                    // Store et_supplier_id / et_currency so enrichment picks them up
                    $insDet = $pdo->prepare("
                        INSERT INTO umrah_fulfillment_details (tenant_id, fulfillment_id, detail_key, detail_value)
                        VALUES (?, ?, ?, ?)");
                    $insDet->execute([$tenant_id, $newFfId, 'et_supplier_id', (string)$parentSupplierId]);
                    $insDet->execute([$tenant_id, $newFfId, 'et_currency', $parentSupplierCurrency]);
                }
            }
        }

        // ---- Recalculate family totals -----------------------------------
        recalcFamilyTotals($pdo, $tenant_id, $family_id);

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'booking_id' => $newBookingId,
            'name'       => $etName,
            'message'    => 'Extra transport created.',
        ]);

    } elseif ($action === 'remove') {
        // ---- Remove extra transport pseudo-member --------------------------
        $extraTransportBookingId = (int)($_POST['extra_transport_booking_id'] ?? 0);
        if (!$extraTransportBookingId) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'extra_transport_booking_id is required.']);
            exit;
        }

        // Verify it belongs to this family and is an extra transport
        $chk = $pdo->prepare("SELECT booking_id, family_id, sold_to, currency, status, sold_price FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND family_id = ? AND is_extra_transport = 1");
        $chk->execute([$extraTransportBookingId, $tenant_id, $family_id]);
        $etRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$etRow) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Extra transport not found.']);
            exit;
        }

        // Reverse client transaction if the extra transport was active and had a sold_price
        if ((string)$etRow['status'] === 'active' && !empty($etRow['sold_to']) && (float)$etRow['sold_price'] > 0) {
            $etClientId = (int)$etRow['sold_to'];
            $etCurrency = strtoupper(trim((string)$etRow['currency'] ?: 'USD'));

            // Fetch client info
            $etCliStmt = $pdo->prepare("SELECT client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $etCliStmt->execute([$etClientId, $tenant_id, $branch_id]);
            $etCliRow = $etCliStmt->fetch(PDO::FETCH_ASSOC);

            if ($etCliRow) {
                // Find the client transaction for this extra transport
                $etCtStmt = $pdo->prepare("
                    SELECT id, amount, type FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                      AND tenant_id = ? AND branch_id = ?
                    ORDER BY id ASC");
                $etCtStmt->execute([$etClientId, $extraTransportBookingId, $tenant_id, $branch_id]);
                $etCtRows = $etCtStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($etCtRows as $etCt) {
                    $etAmt = abs((float)$etCt['amount']);
                    $etCtId = (int)$etCt['id'];
                    $etCtType = (string)$etCt['type'];
                    $etBalField = ($etCurrency === 'USD') ? 'usd_balance' : 'afs_balance';
                    $etIsDebit = strcasecmp($etCtType, 'debit') === 0;

                    // Update subsequent running balances
                    if ($etIsDebit) {
                        $pdo->prepare("UPDATE client_transactions SET balance = balance + ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                            ->execute([$etAmt, $etClientId, $etCtId, $etCurrency, $tenant_id, $branch_id]);
                    } else {
                        $pdo->prepare("UPDATE client_transactions SET balance = balance - ? WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?")
                            ->execute([$etAmt, $etClientId, $etCtId, $etCurrency, $tenant_id, $branch_id]);
                    }

                    // Restore client balance (regular clients only)
                    if ($etCliRow['client_type'] === 'regular') {
                        if ($etIsDebit) {
                            $pdo->prepare("UPDATE clients SET $etBalField = $etBalField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$etAmt, $etClientId, $tenant_id, $branch_id]);
                        } else {
                            $pdo->prepare("UPDATE clients SET $etBalField = $etBalField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$etAmt, $etClientId, $tenant_id, $branch_id]);
                        }
                    }

                    // Delete the client transaction
                    $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$etCtId, $tenant_id, $branch_id]);
                }
            }
        }

        // Reverse supplier transactions for this extra transport's fulfillments
        if ((string)$etRow['status'] === 'active') {
            // Find suppliers used by this extra transport's fulfillments
            $etSupStmt = $pdo->prepare("
                SELECT DISTINCT f.supplier_id, s.supplier_type
                FROM umrah_fulfillments f
                JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                JOIN suppliers s ON s.id = f.supplier_id
                WHERE bs.booking_id = ? AND bs.tenant_id = ? AND f.tenant_id = ?
                  AND f.supplier_id IS NOT NULL AND f.status != 'cancelled'");
            $etSupStmt->execute([$extraTransportBookingId, $tenant_id, $tenant_id]);
            $etSuppliers = $etSupStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($etSuppliers as $etSup) {
                $etSupId = (int)$etSup['supplier_id'];
                $etSupType = (string)$etSup['supplier_type'];

                // Fetch supplier transactions for this extra transport
                $etSupTxnStmt = $pdo->prepare("
                    SELECT id, amount, transaction_type FROM supplier_transactions
                    WHERE supplier_id = ? AND transaction_of = 'umrah'
                      AND reference_id = ? AND tenant_id = ? AND branch_id = ?
                    ORDER BY id ASC");
                $etSupTxnStmt->execute([$etSupId, $extraTransportBookingId, $tenant_id, $branch_id]);
                $etSupTxns = $etSupTxnStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($etSupTxns as $etSupTxn) {
                    $etSupAmt = abs((float)$etSupTxn['amount']);
                    $etSupTxnId = (int)$etSupTxn['id'];
                    $etSupTxnType = (string)$etSupTxn['transaction_type'];

                    if ($etSupType === 'External') {
                        // Update subsequent running balances
                        if ($etSupTxnType === 'Credit') {
                            $pdo->prepare("UPDATE supplier_transactions SET balance = balance - ? WHERE supplier_id = ? AND id > ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$etSupAmt, $etSupId, $etSupTxnId, $tenant_id, $branch_id]);
                        } else {
                            $pdo->prepare("UPDATE supplier_transactions SET balance = balance + ? WHERE supplier_id = ? AND id > ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$etSupAmt, $etSupId, $etSupTxnId, $tenant_id, $branch_id]);
                        }
                        // Restore supplier balance
                        if ($etSupTxnType === 'Credit') {
                            $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$etSupAmt, $etSupId, $tenant_id, $branch_id]);
                        } else {
                            $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                                ->execute([$etSupAmt, $etSupId, $tenant_id, $branch_id]);
                        }
                    }
                    // Delete the supplier transaction
                    $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$etSupTxnId, $tenant_id, $branch_id]);
                }
            }
        }

        // Delete fulfillment_details for this extra transport's services
        $pdo->prepare("
            DELETE FROM umrah_fulfillment_details
            WHERE fulfillment_id IN (
                SELECT id FROM umrah_fulfillments WHERE booking_service_id IN (
                    SELECT id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?
                ) AND tenant_id = ?
            )
        ")->execute([$extraTransportBookingId, $tenant_id, $tenant_id]);

        // Delete fulfillments for this extra transport's services
        $pdo->prepare("
            DELETE FROM umrah_fulfillments
            WHERE booking_service_id IN (
                SELECT id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?
            ) AND tenant_id = ?
        ")->execute([$extraTransportBookingId, $tenant_id, $tenant_id]);

        // Delete the extra transport's booking services
        $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?")
            ->execute([$extraTransportBookingId, $tenant_id]);

        // Delete the extra transport booking row
        $pdo->prepare("DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND is_extra_transport = 1")
            ->execute([$extraTransportBookingId, $tenant_id]);

        recalcFamilyTotals($pdo, $tenant_id, $family_id);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Extra transport removed.',
        ]);

    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }

} catch (\Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ---- Helper: recalculate family totals from active members -------------------
function recalcFamilyTotals($pdo, int $tenantId, int $familyId): void
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(sold_price), 0) AS total_price,
               COALESCE(SUM(paid), 0)       AS total_paid,
               COALESCE(SUM(received_bank_payment), 0) AS total_bank,
               COALESCE(SUM(due), 0)         AS total_due,
               COUNT(*) AS member_count
        FROM umrah_bookings
        WHERE family_id = ? AND tenant_id = ?
          AND status NOT IN ('refunded', 'cancelled')");
    $stmt->execute([$familyId, $tenantId]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("
        UPDATE families SET
            total_members = ?,
            total_price = ?,
            total_paid = ?,
            total_due = ?
        WHERE family_id = ? AND tenant_id = ?
    ")->execute([
        $totals['member_count'],
        $totals['total_price'],
        $totals['total_paid'] + $totals['total_bank'],
        $totals['total_due'],
        $familyId, $tenantId
    ]);
}
