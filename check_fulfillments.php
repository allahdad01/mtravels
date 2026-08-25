<?php
require_once('includes/db.php');

echo "=== Current fulfillment counts ===" . PHP_EOL;
$stmt = $pdo->query('SELECT tenant_id, COUNT(*) as cnt FROM umrah_fulfillments GROUP BY tenant_id');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  tenant {$r['tenant_id']}: {$r['cnt']} fulfillments" . PHP_EOL;
}

echo PHP_EOL . "=== Booking services counts ===" . PHP_EOL;
$stmt2 = $pdo->query('SELECT tenant_id, COUNT(*) as cnt FROM umrah_booking_services GROUP BY tenant_id');
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "  tenant {$r['tenant_id']}: {$r['cnt']} services" . PHP_EOL;
}

echo PHP_EOL . "=== Services WITHOUT fulfillments (orphans) ===" . PHP_EOL;
$stmt3 = $pdo->query('
    SELECT bs.booking_id, bs.tenant_id, bs.service_type, bs.id as service_id, ub.name, ub.passport_number
    FROM umrah_booking_services bs
    LEFT JOIN umrah_fulfillments f ON f.booking_service_id = bs.id AND f.tenant_id = bs.tenant_id
    LEFT JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id AND ub.tenant_id = bs.tenant_id
    WHERE f.id IS NULL
    ORDER BY bs.tenant_id, bs.booking_id
');
$orphans = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "  Count: " . count($orphans) . PHP_EOL;
foreach ($orphans as $o) {
    echo "  booking_id={$o['booking_id']} ({$o['name']}) service_type={$o['service_type']} service_id={$o['service_id']}" . PHP_EOL;
}

echo PHP_EOL . "=== Active bookings with flight_date set (may indicate lost flight fulfillments) ===" . PHP_EOL;
$stmt4 = $pdo->query('
    SELECT ub.booking_id, ub.name, ub.flight_date, ub.return_date, ub.tenant_id
    FROM umrah_bookings ub
    WHERE ub.flight_date IS NOT NULL AND ub.flight_date != ""
      AND ub.status = "active"
    ORDER BY ub.tenant_id, ub.booking_id
');
$flightBookings = $stmt4->fetchAll(PDO::FETCH_ASSOC);
echo "  Count: " . count($flightBookings) . PHP_EOL;
foreach ($flightBookings as $fb) {
    echo "  booking_id={$fb['booking_id']} ({$fb['name']}) flight={$fb['flight_date']} return={$fb['return_date']}" . PHP_EOL;
}

echo PHP_EOL . "=== Flight fulfillments ===" . PHP_EOL;
$stmt5 = $pdo->query('SELECT COUNT(*) as cnt FROM umrah_flight_fulfillments');
echo "  Total: " . $stmt5->fetchColumn() . PHP_EOL;

echo PHP_EOL . "=== Hotel fulfillments ===" . PHP_EOL;
$stmt6 = $pdo->query('SELECT COUNT(*) as cnt FROM umrah_hotel_fulfillments');
echo "  Total: " . $stmt6->fetchColumn() . PHP_EOL;

echo PHP_EOL . "=== Fulfillment details ===" . PHP_EOL;
$stmt7 = $pdo->query('SELECT COUNT(*) as cnt FROM umrah_fulfillment_details');
echo "  Total: " . $stmt7->fetchColumn() . PHP_EOL;
