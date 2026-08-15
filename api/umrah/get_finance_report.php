<?php
/**
 * Get Finance Report API (Phases 26-28)
 * Read-only financial reporting derived from transactional data:
 *   report=members      -> member profitability (selling, cost, profit, margin, paid, due)
 *   report=services     -> service profitability per service_type
 *   report=suppliers    -> supplier payables (from fulfilled services, not manual totals)
 *   report=hotels       -> hotel report (rooms, reservations, occupancy, contract utilization)
 *   report=outstanding  -> outstanding payments (total, paid, due)
 *
 * Cost amounts (umrah_fulfillments.cost_amount) are USD-normalized at save time;
 * selling prices are converted to USD using the booking exchange rate so
 * profit/margin are comparable.
 */

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
umrah_require('finance_view');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';
require_once __DIR__ . '/hotels/occupancy_helper.php';

$report = isset($_GET['report']) ? DbSecurity::validateInput($_GET['report'], 'string') : 'members';

// USD conversion factor for a booking's native currency (1 USD = X native)
// Mirrors add_umrah_transaction.php: converting TO USD always divides.
$sellFactor = "CASE WHEN b.currency = 'USD' OR b.exchange_rate IS NULL OR b.exchange_rate <= 0 THEN 1 ELSE 1 / b.exchange_rate END";

// ---- Member profitability ---------------------------------------------------
if ($report === 'members') {
    $stmt = $pdo->prepare("
        SELECT b.booking_id, b.name, b.fname, b.gfname, b.passport_number,
               b.flight_date, b.status, b.currency, b.exchange_rate,
               b.sold_price, b.paid, b.due,
               COALESCE((
                   SELECT SUM(f.cost_amount)
                   FROM umrah_fulfillments f
                   JOIN umrah_booking_services bs2 ON f.booking_service_id = bs2.id
                   WHERE bs2.booking_id = b.booking_id AND f.status <> 'cancelled'
               ), 0) AS cost_usd
        FROM umrah_bookings b
        WHERE b.tenant_id = ?
        ORDER BY b.flight_date DESC, b.booking_id DESC");
    $stmt->execute([$tenant_id]);
    $rows = [];
    $totals = ['selling_usd' => 0, 'cost_usd' => 0, 'profit_usd' => 0, 'paid_usd' => 0, 'due_usd' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $factor = (strtoupper((string)$r['currency']) === 'USD' || !$r['exchange_rate'] || (float)$r['exchange_rate'] <= 0)
            ? 1 : 1 / (float)$r['exchange_rate'];
        $selling_usd = round((float)$r['sold_price'] * $factor, 2);
        $cost_usd = round((float)$r['cost_usd'], 2);
        $profit_usd = round($selling_usd - $cost_usd, 2);
        $paid_usd = round((float)$r['paid'] * $factor, 2);
        $due_usd = round((float)$r['due'] * $factor, 2);
        $rows[] = [
            'booking_id' => (int)$r['booking_id'],
            'name' => $r['name'],
            'fname' => $r['fname'],
            'gfname' => $r['gfname'],
            'passport_number' => $r['passport_number'],
            'flight_date' => $r['flight_date'],
            'status' => $r['status'],
            'currency' => $r['currency'],
            'exchange_rate' => $r['exchange_rate'],
            'selling' => (float)$r['sold_price'],
            'selling_usd' => $selling_usd,
            'cost_usd' => $cost_usd,
            'profit_usd' => $profit_usd,
            'margin' => $selling_usd > 0 ? round($profit_usd / $selling_usd * 100, 1) : 0,
            'paid' => (float)$r['paid'],
            'due' => (float)$r['due'],
            'paid_usd' => $paid_usd,
            'due_usd' => $due_usd,
        ];
        $totals['selling_usd'] += $selling_usd;
        $totals['cost_usd'] += $cost_usd;
        $totals['profit_usd'] += $profit_usd;
        $totals['paid_usd'] += $paid_usd;
        $totals['due_usd'] += $due_usd;
    }
    $totals['margin'] = $totals['selling_usd'] > 0
        ? round($totals['profit_usd'] / $totals['selling_usd'] * 100, 1) : 0;
    echo json_encode(['success' => true, 'report' => 'members', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Service cost (services carry cost only — no per-service selling price) ----
if ($report === 'services') {
    $stmt = $pdo->prepare("
        SELECT bs.service_type,
               COUNT(*) AS cnt,
               SUM(COALESCE(bs.base_price, 0)) AS cost_usd
        FROM umrah_booking_services bs
        JOIN umrah_bookings b ON bs.booking_id = b.booking_id AND b.tenant_id = ? AND b.branch_id = ?
        WHERE bs.tenant_id = ?
        GROUP BY bs.service_type
        ORDER BY cost_usd DESC");
    $stmt->execute([$tenant_id, $branch_id, $tenant_id]);
    $rows = [];
    $totals = ['cost_usd' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cost_usd = round((float)$r['cost_usd'], 2);
        $rows[] = [
            'service_type' => $r['service_type'],
            'count' => (int)$r['cnt'],
            'cost_usd' => $cost_usd,
        ];
        $totals['cost_usd'] += $cost_usd;
    }
    echo json_encode(['success' => true, 'report' => 'services', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Supplier payables (from fulfilled services) ---------------------------------
if ($report === 'suppliers') {
    // paid_ccy: fund credits (supplier currency) recorded after the supplier's
    // first umrah fulfillment assignment, i.e. payments against umrah services.
    $stmt = $pdo->prepare("
        SELECT sup.id, sup.name, sup.currency,
               COUNT(f.id) AS services_count,
               SUM(CASE WHEN f.fulfillment_type = 'flight'   THEN f.cost_amount ELSE 0 END) AS flight_cost,
               SUM(CASE WHEN f.fulfillment_type = 'hotel'    THEN f.cost_amount ELSE 0 END) AS hotel_cost,
               SUM(CASE WHEN f.fulfillment_type = 'visa'     THEN f.cost_amount ELSE 0 END) AS visa_cost,
               SUM(CASE WHEN f.fulfillment_type = 'transport' THEN f.cost_amount ELSE 0 END) AS transport_cost,
               SUM(CASE WHEN f.fulfillment_type = 'meal'     THEN f.cost_amount ELSE 0 END) AS meal_cost,
               SUM(CASE WHEN f.fulfillment_type = 'ziyarat'  THEN f.cost_amount ELSE 0 END) AS ziyarat_cost,
               SUM(f.cost_amount) AS total_payable,
               SUM(COALESCE(f.supplier_cost, f.cost_amount)) AS payable_ccy,
               COALESCE(pay.paid_ccy, 0) AS paid_ccy
        FROM umrah_fulfillments f
        JOIN suppliers sup ON f.supplier_id = sup.id AND sup.tenant_id = ?
        LEFT JOIN (
            SELECT st.supplier_id, SUM(st.amount) AS paid_ccy
            FROM supplier_transactions st
            WHERE st.transaction_of = 'fund'
              AND LOWER(st.transaction_type) = 'credit'
              AND st.tenant_id = ?
              AND st.transaction_date >= COALESCE((
                    SELECT MIN(f2.created_at)
                    FROM umrah_fulfillments f2
                    WHERE f2.supplier_id = st.supplier_id
                      AND f2.tenant_id = st.tenant_id
                      AND f2.status <> 'cancelled'
              ), '1970-01-01 00:00:00')
            GROUP BY st.supplier_id
        ) pay ON pay.supplier_id = sup.id
        WHERE f.tenant_id = ? AND f.status <> 'cancelled' AND f.cost_amount IS NOT NULL
        GROUP BY sup.id, sup.name, sup.currency, pay.paid_ccy
        ORDER BY payable_ccy DESC");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id]);
    $rows = [];
    $totals = ['total_payable' => 0, 'services_count' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $paid_ccy = round((float)$r['paid_ccy'], 2);
        $payable_ccy = round((float)$r['payable_ccy'], 2);
        $row = [
            'supplier_id' => (int)$r['id'],
            'supplier_name' => $r['name'],
            'currency' => $r['currency'],
            'services_count' => (int)$r['services_count'],
            'flight_cost' => (float)$r['flight_cost'],
            'hotel_cost' => (float)$r['hotel_cost'],
            'visa_cost' => (float)$r['visa_cost'],
            'transport_cost' => (float)$r['transport_cost'],
            'meal_cost' => (float)$r['meal_cost'],
            'ziyarat_cost' => (float)$r['ziyarat_cost'],
            'total_payable' => (float)$r['total_payable'],
            'payable_ccy' => $payable_ccy,
            'paid_ccy' => $paid_ccy,
            'balance_ccy' => round($payable_ccy - $paid_ccy, 2),
        ];
        $rows[] = $row;
        $totals['total_payable'] += (float)$r['total_payable'];
        $totals['services_count'] += (int)$r['services_count'];
    }
    echo json_encode(['success' => true, 'report' => 'suppliers', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

// ---- Hotel report ---------------------------------------------------------------
if ($report === 'hotels') {
    $today = date('Y-m-d');
    $hStmt = $pdo->prepare("
        SELECT h.id, h.name, h.city, h.status,
               (SELECT COUNT(*) FROM umrah_hotel_rooms r
                 WHERE r.hotel_id = h.id AND r.tenant_id = ? AND r.status = 'active') AS rooms,
               (SELECT COUNT(*) FROM umrah_hotel_fulfillments hf
                 JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id
                 WHERE hf.hotel_id = h.id AND f.tenant_id = ? AND f.status <> 'cancelled'
                   AND hf.branch_id = ?
                   AND (hf.check_out IS NULL OR hf.check_out > ?)) AS reservations,
               (SELECT COUNT(*) FROM umrah_hotel_fulfillments hf
                 JOIN umrah_fulfillments f ON hf.fulfillment_id = f.id
                 WHERE hf.hotel_id = h.id AND f.tenant_id = ? AND f.status <> 'cancelled'
                   AND hf.branch_id = ?
                   AND hf.check_in IS NOT NULL AND hf.check_in <= ? 
                   AND hf.check_out IS NOT NULL AND hf.check_out > ?) AS occupied_today,
               (SELECT COUNT(DISTINCT i.room_id) FROM umrah_hotel_contract_inventory i
                 JOIN umrah_hotel_contracts c ON i.contract_id = c.id
                 WHERE c.hotel_id = h.id AND c.tenant_id = ?
                   AND c.status = 'active' AND i.status = 'active'
                   AND (i.valid_from <= ? AND i.valid_to >= ?
                        OR (i.valid_from IS NULL AND i.valid_to IS NULL))) AS inventory_rooms,
               (SELECT COUNT(*) FROM umrah_contract_hotels ch
                 JOIN umrah_hotel_contracts c ON c.id = ch.contract_id AND c.status = 'active'
                 WHERE ch.hotel_id = h.id AND ch.tenant_id = ?) AS contracts
        FROM umrah_hotels h
        WHERE h.tenant_id = ?
        ORDER BY h.name");
    $hStmt->execute([$tenant_id, $tenant_id, $branch_id, $today,
                     $tenant_id, $branch_id, $today, $today,
                     $tenant_id, $today, $today,
                     $tenant_id, $tenant_id]);
    $rows = [];
    foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $rooms = (int)$h['rooms'];
        $occupied = (int)$h['occupied_today'];
        $rows[] = [
            'hotel_id' => (int)$h['id'],
            'hotel_name' => $h['name'],
            'city' => $h['city'],
            'status' => $h['status'],
            'rooms' => $rooms,
            'reservations' => (int)$h['reservations'],
            'occupied_today' => $occupied,
            'occupancy_pct' => $rooms > 0 ? min(100, round($occupied / $rooms * 100, 1)) : 0,
            'inventory_rooms' => (int)$h['inventory_rooms'],
            'utilization_pct' => $rooms > 0 ? round((int)$h['inventory_rooms'] / $rooms * 100, 1) : 0,
            'contracts' => (int)$h['contracts'],
        ];
    }
    echo json_encode(['success' => true, 'report' => 'hotels', 'rows' => $rows]);
    exit;
}

// ---- Outstanding payments ----------------------------------------------------------
if ($report === 'outstanding') {
    $stmt = $pdo->prepare("
        SELECT booking_id, name, fname, gfname, passport_number, flight_date, status,
               currency, exchange_rate, sold_price, paid, due
        FROM umrah_bookings
        WHERE tenant_id = ? AND status IN ('active', 'pending') AND due > 0
        ORDER BY due DESC, booking_id DESC");
    $stmt->execute([$tenant_id]);
    $rows = [];
    $totals = ['total_usd' => 0, 'paid_usd' => 0, 'due_usd' => 0, 'members' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $factor = (strtoupper((string)$r['currency']) === 'USD' || !$r['exchange_rate'] || (float)$r['exchange_rate'] <= 0)
            ? 1 : 1 / (float)$r['exchange_rate'];
        $rows[] = [
            'booking_id' => (int)$r['booking_id'],
            'name' => $r['name'],
            'fname' => $r['fname'],
            'gfname' => $r['gfname'],
            'passport_number' => $r['passport_number'],
            'flight_date' => $r['flight_date'],
            'status' => $r['status'],
            'currency' => $r['currency'],
            'total' => (float)$r['sold_price'],
            'total_usd' => round((float)$r['sold_price'] * $factor, 2),
            'paid' => (float)$r['paid'],
            'paid_usd' => round((float)$r['paid'] * $factor, 2),
            'due' => (float)$r['due'],
            'due_usd' => round((float)$r['due'] * $factor, 2),
        ];
        $totals['total_usd'] += (float)$r['sold_price'] * $factor;
        $totals['paid_usd'] += (float)$r['paid'] * $factor;
        $totals['due_usd'] += (float)$r['due'] * $factor;
        $totals['members']++;
    }
    echo json_encode(['success' => true, 'report' => 'outstanding', 'rows' => $rows, 'totals' => $totals]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown report.']);
