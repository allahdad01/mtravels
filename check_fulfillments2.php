<?php
require_once('includes/db.php');

echo "=== Fulfillments pointing to INVALID (deleted) service IDs ===" . PHP_EOL;
$stmt = $pdo->query('
    SELECT f.id as fulfill_id, f.fulfillment_type, f.booking_service_id, f.status as fulfill_status,
           f.supplier_id, f.notes
    FROM umrah_fulfillments f
    LEFT JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
    WHERE bs.id IS NULL
');
$dangling = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Count: " . count($dangling) . PHP_EOL;
foreach ($dangling as $d) {
    echo "  fulfill_id={$d['fulfill_id']} type={$d['fulfillment_type']} orphaned_service_id={$d['booking_service_id']} status={$d['fulfill_status']}" . PHP_EOL;
}

echo PHP_EOL . "=== Fulfillments pointing to VALID service IDs ===" . PHP_EOL;
$stmt2 = $pdo->query('
    SELECT f.id as fulfill_id, f.fulfillment_type, f.booking_service_id, f.status as fulfill_status,
           bs.booking_id, bs.service_type, ub.name
    FROM umrah_fulfillments f
    JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
    LEFT JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id AND ub.tenant_id = bs.tenant_id
    ORDER BY bs.booking_id
');
$valid = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "  Count: " . count($valid) . PHP_EOL;
foreach ($valid as $v) {
    echo "  booking_id={$v['booking_id']} ({$v['name']}) fulfill_id={$v['fulfill_id']} type={$v['fulfillment_type']} svc_type={$v['service_type']}" . PHP_EOL;
}

echo PHP_EOL . "=== Flight fulfillments detail ===" . PHP_EOL;
$stmt3 = $pdo->query('
    SELECT ff.id, ff.fulfillment_id, ff.airline, ff.flight_number, ff.pnr, ff.ticket_number,
           ff.departure_city, ff.arrival_city, ff.departure_time, ff.return_flight_number,
           f.status, f.booking_service_id
    FROM umrah_flight_fulfillments ff
    JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
    ORDER BY ff.id
');
$flights = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "  Count: " . count($flights) . PHP_EOL;
foreach ($flights as $fl) {
    echo "  ff_id={$fl['id']} fulfill_id={$fl['fulfillment_id']} airline={$fl['airline']} flight={$fl['flight_number']} pnr={$fl['pnr']} ticket={$fl['ticket_number']} route={$fl['departure_city']}->{$fl['arrival_city']}" . PHP_EOL;
}
