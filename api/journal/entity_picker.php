<?php
/**
 * Payments Journal — entity picker (search across modules)
 *
 * Returns a uniform list of selectable records for the Record-Payment
 * action hub. Front-end uses it in step 2 of the modal to let the user
 * choose which ticket/visa/umrah/hotel/... to post a payment against.
 *
 * GET params:
 *   type   required — ticket | visa | umrah | hotel | additional_payment |
 *                     client | supplier | main_account | expense_category
 *   q      optional — free-text search
 *   limit  optional — max rows (default 20, clamp 5..50)
 *
 * Roles: admin, finance.
 */

session_status() === PHP_SESSION_NONE && session_start();

require_once __DIR__ . '/../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_permission('finance.payments');

require_once __DIR__ . '/../../includes/db.php';

$type   = isset($_GET['type']) ? trim($_GET['type']) : '';
$q      = isset($_GET['q'])    ? trim($_GET['q'])      : '';
$limit  = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if ($limit < 5)   { $limit = 5; }
if ($limit > 50)  { $limit = 50; }

$allowed_types = ['ticket', 'visa', 'umrah', 'hotel', 'additional_payment', 'ticket_reserve', 'ticket_date_change', 'ticket_refund', 'ticket_weight', 'hotel_refund', 'visa_refund', 'umrah_refund', 'client', 'supplier', 'main_account', 'expense_category'];
if (!in_array($type, $allowed_types, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

$like = '%' . $q . '%';

/**
 * Each branch builds a query returning:
 *   id, label, sublabel, meta (assoc: currency, amount, and picker-specific fields)
 */

$items = [];

try {
    switch ($type) {

        case 'ticket':
            $sql = "SELECT t.id, t.passenger_name, t.pnr, t.airline, t.origin, t.destination,
                           t.departure_date, t.currency, t.sold, t.price, t.status, t.trip_type,
                           t.description, c.name AS client_name
                    FROM ticket_bookings t
                    LEFT JOIN clients c ON c.id = t.sold_to AND c.tenant_id = t.tenant_id
                    WHERE t.tenant_id = ? AND t.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR t.pnr LIKE ? OR t.passenger_name LIKE ? OR t.airline LIKE ?)
                    ORDER BY t.departure_date DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'     => trim($r['passenger_name']) . ' — ' . trim($r['pnr']),
                    'sublabel'  => trim($r['airline']) . ' · ' . trim($r['origin']) . ' → ' . trim($r['destination']) . ' · ' . $r['departure_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold'] !== null ? (float) $r['sold'] : null,
                        'base'     => $r['price'] !== null ? (float) $r['price'] : null,
                        'trip_type'=> $r['trip_type'],
                        'description' => $r['description'],
                        'status'   => $r['status'],
                        'client'   => $r['client_name'],
                    ],
                ];
            }
            break;

        case 'visa':
            $sql = "SELECT v.id, v.applicant_name, v.passport_number, v.visa_type, v.country,
                           v.applied_date, v.sold, v.base, v.currency, v.status,
                           c.name AS client_name
                    FROM visa_applications v
                    LEFT JOIN clients c ON c.id = v.sold_to AND c.tenant_id = v.tenant_id
                    WHERE v.tenant_id = ? AND v.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR v.applicant_name LIKE ? OR v.passport_number LIKE ? OR v.visa_type LIKE ?)
                    ORDER BY v.applied_date DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['applicant_name']) . ' — ' . trim($r['passport_number']),
                    'sublabel' => trim($r['visa_type']) . ' · ' . trim($r['country']) . ' · ' . $r['applied_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold'] !== null ? (float) $r['sold'] : null,
                        'base'     => $r['base'] !== null ? (float) $r['base'] : null,
                        'status'   => $r['status'],
                        'client'   => $r['client_name'],
                    ],
                ];
            }
            break;

        case 'umrah':
            $sql = "SELECT u.booking_id, u.name, u.fname, u.passport_number,
                           (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                               JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                               JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                               WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                               ORDER BY ff.id DESC LIMIT 1) AS flight_date,
                           u.sold_price, u.currency, u.status,
                           c.name AS client_name
                    FROM umrah_bookings u
                    LEFT JOIN clients c ON c.id = u.sold_to AND c.tenant_id = u.tenant_id
                    WHERE u.tenant_id = ? AND u.branch_id = ?
                      AND (? = '' OR u.name LIKE ? OR u.fname LIKE ? OR u.passport_number LIKE ?)
                    ORDER BY u.created_at DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['booking_id'],
                    'label'    => trim($r['name']) . ' — ' . trim($r['fname']),
                    'sublabel' => 'Passport: ' . trim($r['passport_number']) . ' · Flight: ' . $r['flight_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold_price'] !== null ? (float) $r['sold_price'] : null,
                        'status'   => $r['status'],
                        'client'   => $r['client_name'],
                    ],
                ];
            }
            break;

        case 'hotel':
            $sql = "SELECT h.id, h.first_name, h.last_name, h.order_id, h.check_in_date,
                           h.sold_amount, h.base_amount, h.currency, h.status,
                           c.name AS client_name
                    FROM hotel_bookings h
                    LEFT JOIN clients c ON c.id = h.sold_to AND c.tenant_id = h.tenant_id
                    WHERE h.tenant_id = ? AND h.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR h.first_name LIKE ? OR h.last_name LIKE ? OR h.order_id LIKE ?)
                    ORDER BY h.check_in_date DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['first_name']) . ' ' . trim($r['last_name']),
                    'sublabel' => 'Order: ' . trim($r['order_id']) . ' · Check-in: ' . $r['check_in_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold_amount'] !== null ? (float) $r['sold_amount'] : null,
                        'base'     => $r['base_amount'] !== null ? (float) $r['base_amount'] : null,
                        'status'   => $r['status'],
                        'client'   => $r['client_name'],
                    ],
                ];
            }
            break;

        case 'additional_payment':
            $sql = "SELECT p.id, p.payment_type, p.description, p.sold_amount, p.base_amount,
                           p.currency, p.main_account_id, p.receipt,
                           c.name AS client_name, s.name AS supplier_name,
                           ma.name AS account_name,
                           p.is_from_supplier, p.is_for_client
                    FROM additional_payments p
                    LEFT JOIN clients c   ON c.id = p.client_id   AND c.tenant_id = p.tenant_id
                    LEFT JOIN suppliers s ON s.id = p.supplier_id AND s.tenant_id = p.tenant_id
                    LEFT JOIN main_account ma ON ma.id = p.main_account_id AND ma.tenant_id = p.tenant_id
                    WHERE p.tenant_id = ? AND p.branch_id = ?
                      AND (p.client_id IS NULL OR c.client_type = 'agency')
                      AND (? = '' OR p.payment_type LIKE ? OR p.description LIKE ? OR p.receipt LIKE ?)
                    ORDER BY p.created_at DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $party = $r['is_from_supplier'] && $r['supplier_name'] ? 'Supplier: ' . $r['supplier_name']
                      : ($r['is_for_client'] && $r['client_name'] ? 'Client: ' . $r['client_name'] : '');
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['payment_type']),
                    'sublabel' => trim($r['description']),
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold_amount'] !== null ? (float) $r['sold_amount'] : null,
                        'main_account_id' => (int) $r['main_account_id'],
                        'payment_type' => $r['payment_type'],
                        'receipt'  => $r['receipt'],
                        'party'    => $party,
                        'description' => $r['description'],
                        'account_name' => $r['account_name'],
                    ],
                ];
            }
            break;

        case 'ticket_reserve':
            $sql = "SELECT r.id, r.passenger_name, r.pnr, r.airline, r.origin, r.destination,
                           r.departure_date, r.currency, r.sold, r.price, r.status,
                           c.name AS client_name
                    FROM ticket_reservations r
                    LEFT JOIN clients c ON c.id = r.sold_to AND c.tenant_id = r.tenant_id
                    WHERE r.tenant_id = ? AND r.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR r.pnr LIKE ? OR r.passenger_name LIKE ? OR r.airline LIKE ?)
                    ORDER BY r.departure_date DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['passenger_name']) . ' — ' . trim($r['pnr']),
                    'sublabel' => trim($r['airline']) . ' · ' . trim($r['origin']) . ' → ' . trim($r['destination']) . ' · ' . $r['departure_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold'] !== null ? (float) $r['sold'] : null,
                        'base'     => $r['price'] !== null ? (float) $r['price'] : null,
                        'status'   => $r['status'],
                        'client'   => $r['client_name'],
                    ],
                ];
            }
            break;

        case 'ticket_date_change':
            $sql = "SELECT dct.id, dct.ticket_id, dct.departure_date,
                           dct.supplier_penalty, dct.service_penalty, dct.currency, dct.status,
                           tb.passenger_name, tb.pnr
                    FROM date_change_tickets dct
                    LEFT JOIN ticket_bookings tb ON dct.ticket_id = tb.id AND tb.tenant_id = ? AND tb.branch_id = ?
                    LEFT JOIN clients c ON c.id = tb.sold_to AND c.tenant_id = tb.tenant_id
                    WHERE dct.tenant_id = ? AND dct.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR tb.pnr LIKE ? OR tb.passenger_name LIKE ?)
                    ORDER BY dct.departure_date DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $q, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['passenger_name']) . ' — ' . trim($r['pnr']),
                    'sublabel' => 'New dep. ' . $r['departure_date'] . ' · ' . trim($r['status']),
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => (float) $r['supplier_penalty'] + (float) $r['service_penalty'],
                        'departure_date' => $r['departure_date'],
                        'status'   => $r['status'],
                        'ticket_id' => (int) $r['ticket_id'],
                    ],
                ];
            }
            break;

        case 'ticket_refund':
            $sql = "SELECT r.id, r.passenger_name, r.pnr, r.airline, r.origin, r.destination,
                           r.departure_date, r.currency, r.sold, r.base, r.status
                    FROM refunded_tickets r
                    LEFT JOIN clients c ON c.id = r.sold_to AND c.tenant_id = r.tenant_id
                    WHERE r.tenant_id = ? AND r.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR r.pnr LIKE ? OR r.passenger_name LIKE ?)
                    ORDER BY r.id DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['passenger_name']) . ' — ' . trim($r['pnr']),
                    'sublabel' => trim($r['airline']) . ' · ' . trim($r['origin']) . ' → ' . trim($r['destination']) . ' · ' . $r['departure_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold'] !== null ? (float) $r['sold'] : null,
                        'base'     => $r['base'] !== null ? (float) $r['base'] : null,
                        'status'   => $r['status'],
                    ],
                ];
            }
            break;

        case 'ticket_weight':
            $sql = "SELECT w.id, w.ticket_id, w.weight, w.base_price, w.sold_price, w.remarks,
                           t.passenger_name, t.pnr, t.currency, t.airline, t.origin, t.destination, t.departure_date
                    FROM ticket_weights w
                    LEFT JOIN ticket_bookings t ON w.ticket_id = t.id AND t.tenant_id = ? AND t.branch_id = ?
                    LEFT JOIN clients c ON c.id = t.sold_to AND c.tenant_id = t.tenant_id
                    WHERE w.tenant_id = ? AND w.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR t.pnr LIKE ? OR t.passenger_name LIKE ?)
                    ORDER BY w.id DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $q, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['passenger_name']) . ' — ' . trim($r['pnr']),
                    'sublabel' => 'Weight ' . (float) $r['weight'] . ' · ' . trim($r['airline']) . ' · ' . $r['departure_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['sold_price'] !== null ? (float) $r['sold_price'] : null,
                        'base'     => $r['base_price'] !== null ? (float) $r['base_price'] : null,
                        'weight'   => $r['weight'] !== null ? (float) $r['weight'] : null,
                    ],
                ];
            }
            break;

        case 'visa_refund':
            $sql = "SELECT r.id, r.visa_id, r.refund_type, r.refund_amount, r.currency, r.reason,
                           v.applicant_name, v.passport_number, v.country, v.visa_type,
                           c.name AS client_name
                    FROM visa_refunds r
                    LEFT JOIN visa_applications v ON r.visa_id = v.id AND v.tenant_id = ? AND v.branch_id = ?
                    LEFT JOIN clients c ON c.id = v.sold_to AND c.tenant_id = v.tenant_id
                    WHERE r.tenant_id = ? AND r.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR v.applicant_name LIKE ? OR v.passport_number LIKE ?)
                    ORDER BY r.refund_date DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $q, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['applicant_name']) . ' — ' . trim($r['passport_number']),
                    'sublabel' => 'Visa #' . (int) $r['visa_id'] . ' · ' . trim($r['country']) . ' · ' . trim($r['visa_type']),
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['refund_amount'] !== null ? (float) $r['refund_amount'] : null,
                        'refund_type' => $r['refund_type'],
                    ],
                ];
            }
            break;

        case 'umrah_refund':
            $sql = "SELECT r.id, r.booking_id, r.refund_type, r.refund_amount, r.currency, r.reason,
                           u.name, u.passport_number,
                           (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                               JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                               JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                               WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                               ORDER BY ff.id DESC LIMIT 1) AS flight_date,
                           c.name AS client_name
                    FROM umrah_refunds r
                    LEFT JOIN umrah_bookings u ON r.booking_id = u.booking_id AND u.tenant_id = ? AND u.branch_id = ?
                    LEFT JOIN clients c ON c.id = u.sold_to AND c.tenant_id = u.tenant_id
                    WHERE r.tenant_id = ? AND r.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR u.name LIKE ? OR u.passport_number LIKE ?)
                    ORDER BY r.id DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $q, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['name']) . ' — ' . trim($r['passport_number']),
                    'sublabel' => 'Booking #' . (int) $r['booking_id'] . ' · Flight: ' . $r['flight_date'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['refund_amount'] !== null ? (float) $r['refund_amount'] : null,
                        'refund_type' => $r['refund_type'],
                    ],
                ];
            }
            break;

        case 'hotel_refund':
            $sql = "SELECT r.id, r.booking_id, r.refund_type, r.refund_amount, r.currency, r.reason,
                           h.first_name, h.last_name, h.order_id,
                           c.name AS client_name
                    FROM hotel_refunds r
                    LEFT JOIN hotel_bookings h ON r.booking_id = h.id AND h.tenant_id = ? AND h.branch_id = ?
                    LEFT JOIN clients c ON c.id = h.sold_to AND c.tenant_id = h.tenant_id
                    WHERE r.tenant_id = ? AND r.branch_id = ?
                      AND c.client_type = 'agency'
                      AND (? = '' OR h.first_name LIKE ? OR h.last_name LIKE ? OR h.order_id LIKE ?)
                    ORDER BY r.id DESC
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $q, $like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['first_name']) . ' ' . trim($r['last_name']),
                    'sublabel' => 'Booking #' . (int) $r['booking_id'] . ' · Order: ' . trim($r['order_id']),
                    'meta' => [
                        'currency' => $r['currency'],
                        'amount'   => $r['refund_amount'] !== null ? (float) $r['refund_amount'] : null,
                        'refund_type' => $r['refund_type'],
                    ],
                ];
            }
            break;

        case 'client':
            $sql = "SELECT id, name, phone, usd_balance, afs_balance, client_type, status
                    FROM clients
                    WHERE tenant_id = ? AND branch_id = ?
                      AND (? = '' OR name LIKE ? OR phone LIKE ?)
                    ORDER BY name
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['name']),
                    'sublabel' => 'USD ' . (float) $r['usd_balance'] . ' · AFS ' . (float) $r['afs_balance'] . ($r['phone'] ? ' · ' . $r['phone'] : ''),
                    'meta' => [
                        'currency' => 'USD',
                        'usd' => (float) $r['usd_balance'],
                        'afs' => (float) $r['afs_balance'],
                        'status' => $r['status'],
                    ],
                ];
            }
            break;

        case 'supplier':
            $sql = "SELECT id, name, currency, balance, status
                    FROM suppliers
                    WHERE tenant_id = ? AND branch_id = ?
                      AND (? = '' OR name LIKE ?)
                    ORDER BY name
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['name']),
                    'sublabel' => trim($r['currency']) . ' ' . (float) $r['balance'],
                    'meta' => [
                        'currency' => $r['currency'],
                        'balance'  => $r['balance'] !== null ? (float) $r['balance'] : null,
                        'status'   => $r['status'],
                    ],
                ];
            }
            break;

        case 'main_account':
            $sql = "SELECT id, name, account_type, usd_balance, afs_balance, euro_balance, darham_balance, sar_balance, status
                    FROM main_account
                    WHERE tenant_id = ? AND branch_id = ?
                      AND (? = '' OR name LIKE ?)
                    ORDER BY name
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['name']),
                    'sublabel' => 'USD ' . (float) $r['usd_balance'] . ' · AFS ' . (float) $r['afs_balance'],
                    'meta' => [
                        'currency' => 'USD',
                        'account_type' => $r['account_type'],
                        'status' => $r['status'],
                    ],
                ];
            }
            break;

        case 'expense_category':
            $sql = "SELECT id, name
                    FROM expense_categories
                    WHERE tenant_id = ? AND branch_id = ?
                      AND (? = '' OR name LIKE ?)
                    ORDER BY name
                    LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $branch_id, $q, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id' => (int) $r['id'],
                    'label'    => trim($r['name']),
                    'sublabel' => '',
                    'meta' => [],
                ];
            }
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Picker error: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'type' => $type, 'items' => $items]);