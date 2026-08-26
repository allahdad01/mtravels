<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
session_start();

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

// Fetch settings (agency name / logo)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

// Ticket ID or group_id required
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if (!isset($_GET['ticket_id']) && !isset($_GET['group_id'])) {
    die('Invalid request: ticket_id or group_id required');
}
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

// Fulfillment mode: ticket_id is a booking id whose flight fulfillment is used
$isFulfillment = ($_GET['src'] ?? '') === 'fulfillment';

// Group mode: fetch all flights and members for the group
$flights = [];
if (!empty($groupId)) {
    // 1) Fetch ALL members of the group
    $grpMemberStmt = $pdo->prepare("
        SELECT b.booking_id, b.name, b.fname, b.dob, b.gender, b.passport_number, b.status, b.family_id, b.duration
        FROM umrah_bookings b
        JOIN families f ON b.family_id = f.family_id AND f.tenant_id = b.tenant_id
        WHERE f.group_id = ? AND b.tenant_id = ? AND b.branch_id = ?
          AND b.status NOT IN ('refunded', 'cancelled')
          AND COALESCE(b.is_extra_bed, 0) = 0
        ORDER BY f.family_id, b.booking_id
    ");
    $grpMemberStmt->execute([$groupId, $tenant_id, $branch_id]);
    $allGroupMembers = $grpMemberStmt->fetchAll(PDO::FETCH_ASSOC);
    $allBookingIds = array_map(fn($m) => (int)$m['booking_id'], $allGroupMembers);

    // 2) Fetch fulfillment-based flights for this group's members
    $memberFlightInfo = [];
    if (!empty($allBookingIds)) {
        $ph = implode(',', array_fill(0, count($allBookingIds), '?'));
        $ffStmt = $pdo->prepare("
            SELECT ff.airline, ff.flight_number, ff.pnr, ff.ticket_number,
                   ff.departure_city, ff.arrival_city, ff.departure_time,
                   ff.return_flight_number, ff.return_departure_time, ff.return_arrival_time,
                   ub.booking_id
            FROM umrah_flight_fulfillments ff
            JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
            JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
            WHERE f.tenant_id = ? AND ub.branch_id = ?
              AND ub.booking_id IN ({$ph}) AND ub.status NOT IN ('refunded', 'cancelled')
            ORDER BY ff.created_at DESC
        ");
        $ffParams = array_merge([$tenant_id, $branch_id], $allBookingIds);
        $ffStmt->execute($ffParams);
        foreach ($ffStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bid = (int)$r['booking_id'];
            if (!isset($memberFlightInfo[$bid])) {
                $memberFlightInfo[$bid] = [
                    'airline_name' => (string)$r['airline'],
                    'flight_number' => (string)$r['flight_number'],
                    'pnr' => (string)$r['pnr'],
                    'ticket_number' => (string)$r['ticket_number'],
                    'departure_city' => (string)$r['departure_city'],
                    'arrival_city' => (string)$r['arrival_city'],
                    'flight_date' => !empty($r['departure_time']) ? date('Y-m-d', strtotime($r['departure_time'])) : '',
                    'departure_time' => !empty($r['departure_time']) ? $r['departure_time'] : '',
                    'return_flight_number' => (string)$r['return_flight_number'],
                    'return_date' => !empty($r['return_departure_time']) ? date('Y-m-d', strtotime($r['return_departure_time'])) : '',
                    'return_departure_time' => !empty($r['return_departure_time']) ? $r['return_departure_time'] : '',
                    'return_arrival_time' => !empty($r['return_arrival_time']) ? $r['return_arrival_time'] : '',
                    'duration' => '',
                    '_fulfillment' => true,
                ];
            }
        }
    }

    // 3) Fetch group_tickets flights and merge (fulfillment takes priority)
    $gtStmt = $pdo->prepare("
        SELECT DISTINCT gt.*
        FROM group_tickets gt
        JOIN families f ON JSON_SEARCH(gt.family_ids, 'one', CAST(f.family_id AS CHAR)) IS NOT NULL
        WHERE f.group_id = ? AND gt.tenant_id = ? AND gt.branch_id = ?
        ORDER BY gt.flight_date ASC, gt.departure_time ASC
    ");
    $gtStmt->execute([$groupId, $tenant_id, $branch_id]);
    $gtFlights = $gtStmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge group_tickets flight info for members not already covered by fulfillment
    foreach ($gtFlights as $flt) {
        $fids = json_decode($flt['member_ids'] ?? '[]', true) ?: [];
        foreach ($fids as $mid) {
            $mid = (int)$mid;
            if (!isset($memberFlightInfo[$mid])) {
                $memberFlightInfo[$mid] = [
                    'airline_name' => (string)($flt['airline_name'] ?? ''),
                    'flight_number' => (string)($flt['flight_number_1'] ?? ''),
                    'pnr' => (string)($flt['pnr'] ?? ''),
                    'ticket_number' => (string)($flt['ticket_number'] ?? ''),
                    'departure_city' => (string)($flt['departure_city'] ?? ''),
                    'arrival_city' => (string)($flt['arrival_city'] ?? ''),
                    'flight_date' => (string)($flt['flight_date'] ?? ''),
                    'departure_time' => (string)($flt['departure_time'] ?? ''),
                    'return_flight_number' => (string)($flt['return_flight_number'] ?? ''),
                    'return_date' => (string)($flt['return_date'] ?? ''),
                    'return_departure_time' => (string)($flt['return_departure_time'] ?? ''),
                    'duration' => (string)($flt['duration'] ?? ''),
                ];
            }
        }
    }

    // 4) Attach flight info to each member, single merged list
    $mergedMembers = [];
    foreach ($allGroupMembers as $m) {
        $fltInfo = $memberFlightInfo[(int)$m['booking_id']] ?? null;
        if ($fltInfo) {
            $fltInfo['duration'] = $m['duration'] ?? '';
        }
        $m['_flight'] = $fltInfo;
        $mergedMembers[] = $m;
    }
    $flights = [['ticket_id' => 0, '_merged' => true]];
    $flightPassengers[0] = $mergedMembers;
} else {
    if ($isFulfillment) {
        require_once __DIR__ . '/fulfillment_flight_context.php';
        $ffCtx = fulfillment_flight_context($pdo, (int)$tenant_id, (int)$branch_id, $ticketId);
        if (!$ffCtx) {
            die('Invalid request: flight fulfillment not found');
        }
        $flights = [$ffCtx['ticket']];
        // Store for per-flight member rendering
        $ffMembers = [$ffCtx['ticket']['ticket_id'] ?? $ticketId => $ffCtx['member_ids']];
    } else {
        $ticketStmt = $pdo->prepare("SELECT * FROM group_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ?");
        $ticketStmt->execute([$ticketId, $tenant_id, $branch_id]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            die('Invalid request: ticket not found');
        }
        $flights = [$ticket];
    }
}

// For single-ticket mode, fetch passengers per flight. Group mode already has flightPassengers.
$ticket = $flights[0] ?? [];
$memberMap = [];
if (empty($groupId)) {
    $flightPassengers = [];
    foreach ($flights as $flt) {
        $fid = $flt['ticket_id'] ?? 0;
        if (!empty($isFulfillment) && isset($ffMembers[$fid])) {
            $mids = $ffMembers[$fid];
        } else {
            $mids = json_decode($flt['member_ids'] ?? '[]', true) ?: [];
        }
        if (!empty($mids)) {
            $placeholders = implode(',', array_fill(0, count($mids), '?'));
            $memberStmt = $pdo->prepare("
                SELECT booking_id, name, fname, dob, gender, passport_number, status
                FROM umrah_bookings
                WHERE booking_id IN ({$placeholders}) AND tenant_id = ? AND branch_id = ?
            ");
            $memberStmt->execute(array_merge($mids, [$tenant_id, $branch_id]));
            $pax = [];
            foreach ($memberStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $memberMap[(int)$m['booking_id']] = $m;
                $pax[] = $m;
            }
            $flightPassengers[$fid] = $pax;
        } else {
            $flightPassengers[$fid] = [];
        }
    }
}

// For backward compatibility with single-ticket rendering
$passengers = $flightPassengers[$ticket['ticket_id'] ?? 0] ?? [];
$memberIds = json_decode($ticket['member_ids'] ?? '[]', true) ?: [];

// Auto-translate names into the document language (MyMemory - free)
require_once __DIR__ . '/../../includes/translate_helper.php';
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
// Translate all passengers across all flights
foreach ($flightPassengers as &$fp) {
    translate_name_fields($fp, $docLanguage, ['name', 'fname']);
}
unset($fp);
$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);

// UI labels per document language (deterministic, no external API)
$langLabels = [
    'dari' => [
        'doc_title' => 'جدول مسافرین پرواز عمره',
        'includes_flight' => 'شامل پرواز',
        'day' => 'یوم',
        'hijri' => 'مورخ',
        'gregorian' => 'تاریخ میلادی',
        'col_no' => 'شماره عمومی',
        'col_reg' => 'شماره خصوصی',
        'col_name' => 'نام',
        'col_fname' => 'نام پدر',
        'col_passport' => 'شماره پاسپورت',
        'col_age' => 'سن',
        'col_gender' => 'جنسیت',
        'col_airline' => 'شرکت هوایی و ساعت پرواز',
        'col_date' => 'تاریخ پرواز',
        'col_day' => 'روز پرواز',
        'col_transfer' => 'شرکت انتقال دهنده',
        'col_duration' => 'مدت سفر',
        'col_return' => 'تاریخ برگشت',
        'col_place' => 'محل پرواز',
        'col_whatsapp' => 'شماره وتس اب معتمر',
        'col_services' => 'با خدمات / بدون خدمات',
        'empty' => 'هیچ مسافری در این پرواز ثبت نشده است',
        'unassigned_title' => 'مسافرین بدون پرواز',
        'total_passengers' => 'مجموع مسافرین',
        'male' => 'مرد',
        'female' => 'زن',
        'child' => 'کودک',
        'infant' => 'طفل',
        'years' => 'سال',
        'print' => 'چاپ',
        'days' => 'روز',
        'day_names' => [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'],
        'hijri_suffix' => 'هـ',
    ],
    'ps' => [
        'doc_title' => 'د عمرې د پرواز مسافرینو جدول',
        'includes_flight' => 'په الوتکه کې شامل',
        'day' => 'ورځ',
        'hijri' => 'هجري',
        'gregorian' => 'ميلادي نېټه',
        'col_no' => 'عمومي شمېره',
        'col_reg' => 'خصوصي شمېره',
        'col_name' => 'نوم',
        'col_fname' => 'د پلار نوم',
        'col_passport' => 'د پاسپورټ شمېره',
        'col_age' => 'عمر',
        'col_gender' => 'جنس',
        'col_airline' => 'الوتکه شرکت او د الوتنې وخت',
        'col_date' => 'د الوتنې نېټه',
        'col_day' => 'د الوتنې ورځ',
        'col_transfer' => 'د انتقال شرکت',
        'col_duration' => 'د سفر موده',
        'col_return' => 'د بېرته راستنېدو نېټه',
        'col_place' => 'د الوتنې ځای',
        'col_whatsapp' => 'د معتمر وټس‌اپ شمېره',
        'col_services' => 'د خدمت سره / پرته له خدمت',
        'empty' => 'په دې الوتکه کې هېڅ مسافر ثبت شوی نه دی',
        'unassigned_title' => 'د پرواز پرته مسافرین',
        'total_passengers' => 'ټول مسافرین',
        'male' => 'سړی',
        'female' => 'ښځه',
        'child' => 'ماشوم',
        'infant' => 'شیدي',
        'years' => 'کلنۍ',
        'days' => 'ورځې',
        'print' => 'چاپ',
        'day_names' => [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'],
        'hijri_suffix' => 'هـ',
    ],
    'en' => [
        'doc_title' => 'Umrah Flight Passenger Manifest',
        'includes_flight' => 'Includes Flight',
        'day' => 'Day',
        'hijri' => 'Hijri',
        'gregorian' => 'Gregorian',
        'col_no' => 'General No.',
        'col_reg' => 'Private No.',
        'col_name' => 'Name',
        'col_fname' => 'Father Name',
        'col_passport' => 'Passport No.',
        'col_age' => 'Age',
        'col_gender' => 'Gender',
        'col_airline' => 'Airline & Flight Time',
        'col_date' => 'Flight Date',
        'col_day' => 'Flight Day',
        'col_transfer' => 'Transfer Company',
        'col_duration' => 'Duration',
        'col_return' => 'Return Date',
        'col_place' => 'Departure City',
        'col_whatsapp' => 'Muttamer WhatsApp No.',
        'col_services' => 'With / Without Service',
        'empty' => 'No passengers registered on this flight',
        'unassigned_title' => 'Members Without Flight Assignment',
        'total' => 'Total',
        'male' => 'Male',
        'female' => 'Female',
        'child' => 'Child',
        'infant' => 'Infant',
        'persons' => 'people',
        'years' => 'years',
        'total_passengers' => 'Total',
        'print' => 'Print',
        'days' => 'Days',
        'day_names' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        'hijri_suffix' => 'AH',
    ],
];
$L = $langLabels[$docLanguage];

// Helpers
function manifest_age($dob) {
    if (empty($dob) || $dob === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($dob);
    if (!$ts) {
        return '—';
    }
    return date('Y') - date('Y', $ts) - (date('md') < date('md', $ts) ? 1 : 0);
}

function manifest_gender($gender, $L) {
    if ($gender === 'Male') { return $L['male'] ?? 'مرد'; }
    if ($gender === 'Female') { return $L['female'] ?? 'زن'; }
    return '—';
}

function manifest_day($date, $L) {
    $ts = strtotime($date);
    if ($ts === false) { return '—'; }
    if (isset($L['day_names'])) {
        return $L['day_names'][(int)date('N', $ts)] ?? '—';
    }
    $days = [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'];
    return $days[(int)date('N', $ts)] ?? '—';
}

function manifest_hijri($date, $L) {
    if (empty($date)) { return '—'; }
    $ts = strtotime($date);
    if ($ts === false) { return $date; }
    $jd = gregoriantojd((int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
    $l = $jd - 1948440 + 10632;
    $n = (int)(($l - 1) / 10631);
    $l = $l - 10631 * $n + 354;
    $j = (int)((10985 - $l) / 5316) * (int)((50 * $l) / 17719) + (int)($l / 5670) * (int)((43 * $l) / 15238);
    $l = $l - (int)((30 - $j) / 15) * (int)((17719 * $j) / 50) - (int)($j / 16) * (int)((15238 * $j) / 43) + 29;
    $hm = (int)((24 * $l) / 709);
    $hd = $l - (int)((709 * $hm) / 24);
    $hy = 30 * $n + $j - 30;
    return $hy . '/' . str_pad((string)$hm, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string)$hd, 2, '0', STR_PAD_LEFT) . ' ' . $L['hijri_suffix'];
}

// Flight info helpers
function manifest_flight_info($ticket, $docLanguage, $L, $pdo, $tenant_id) {
    $airlineName = translate_name($ticket['airline_name'] ?? '', $docLanguage);
    $departureTime = $ticket['departure_time'] ?? '';
    if (!empty($departureTime)) {
        // Handle full datetime strings like "2026-08-23 18:00:00"
        if (strpos($departureTime, ' ') !== false) {
            $departureTime = substr($departureTime, strpos($departureTime, ' ') + 1);
        }
        if (strpos($departureTime, ':') !== false) {
            $departureTime = substr($departureTime, 0, 5);
        }
    }
    $durationRaw = trim((string)($ticket['duration'] ?? ''));
    $durationLocalized = '—';
    $departureCity = translate_place($ticket['departure_city'] ?? '', $docLanguage);
    if ($durationRaw !== '') {
        $durationNum = preg_replace('/\s*days$/i', '', $durationRaw);
        $durationLocalized = $durationNum . ' ' . $L['days'];
    }
    return compact('airlineName', 'departureTime', 'durationLocalized', 'departureCity');
}

function manifest_passenger_counts($passengers, $L) {
    $total = count($passengers);
    $male = $female = $child = $infant = 0;
    foreach ($passengers as $p) {
        if (($p['gender'] ?? '') === 'Male') { $male++; }
        if (($p['gender'] ?? '') === 'Female') { $female++; }
        $age = manifest_age($p['dob'] ?? '');
        if ($age !== '—') {
            if ($age < 2) { $infant++; }
            elseif ($age <= 11) { $child++; }
        }
    }
    return compact('total', 'male', 'female', 'child', 'infant');
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($docLanguage === 'en') ? 'en' : (($docLanguage === 'ps') ? 'ps' : 'fa'); ?>" dir="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($L['doc_title']); ?> - <?php echo htmlspecialchars($agencyName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── CSS Variables ─────────────────────────────────────────── */
        :root {
            --gold:        #b8960c;
            --gold-light:  #d4af37;
            --gold-pale:   #f5edd6;
            --dark:        #1a1a2e;
            --ink:         #1c1c1c;
            --muted:       #555;
            --border:      #c8a951;
            --grid:        #555;
            --bg:          #ffffff;
            --section-bg:  #fdfbf5;
        }

        /* ── Page / Print Setup ────────────────────────────────────── */
        @page {
            size: A4 landscape;
            margin: 1cm 0.9cm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Noto Naskh Arabic', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: var(--ink);
            background: var(--bg);
            max-width: 29.7cm;
            margin: 0 auto;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body.ltr {
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        /* ── Decorative top stripe ─────────────────────────────────── */
        .top-stripe {
            height: 5px;
            background: linear-gradient(to left, var(--gold), var(--gold-light), var(--gold));
        }

        /* ── Header ────────────────────────────────────────────────── */
        .header {
            display: grid;
            grid-template-columns: 75px 1fr 105px;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-bottom: 1.5px solid var(--border);
        }

        .header-logo {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .header-logo img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
        }

        .header-center h1 {
            font-size: 15px;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 2px;
        }

        .header-center .subtitle {
            font-size: 11px;
            color: var(--gold);
            font-weight: 600;
            margin: 0;
        }

        .header-contact {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 9.5px;
            color: var(--muted);
            text-align: left;
        }

        .header-contact span {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* ── Document meta bar ─────────────────────────────────────── */
        .meta-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 14px;
            flex-wrap: nowrap;
            background: var(--gold-pale);
            border-bottom: 1px solid var(--border);
            padding: 4px 12px;
            font-size: 10px;
            color: var(--ink);
        }

        .meta-bar .doc-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--dark);
        }

        .meta-bar .doc-subtitle {
            font-size: 11px;
            font-weight: 600;
            color: var(--dark);
        }

        .meta-bar .doc-date {
            font-size: 10px;
            color: var(--muted);
        }

        /* ── Passenger table ───────────────────────────────────────── */
        .manifest-wrap {
            margin: 10px 12px 0;
        }

        .manifest-table {
            width: 100%;
            border-collapse: collapse;
            direction: rtl;
        }

        .manifest-table thead {
            display: table-header-group;
        }

        .manifest-table th,
        .manifest-table td {
            border: 0.6px solid var(--grid);
            padding: 5px 4px;
            font-size: 10px;
            line-height: 1.4;
            text-align: center;
        }

        .manifest-table th {
            background: var(--gold-pale);
            font-size: 10px;
            font-weight: 700;
            color: var(--dark);
            border-bottom: 1px solid var(--grid);
        }

        .manifest-table tbody tr {
            page-break-inside: avoid;
        }

        .manifest-table tbody tr:nth-child(even) {
            background: #fafaf7;
        }

        body.ltr .manifest-table {
            direction: ltr;
        }

        body.ltr .manifest-table td,
        body.ltr .manifest-table th {
            text-align: left;
        }

        .manifest-table .col-no { width: 5%; }
        .manifest-table .col-reg { width: 7%; }
        .manifest-table .col-name { width: 11%; }
        .manifest-table .col-fname { width: 10%; }
        .manifest-table .col-passport { width: 9%; direction: ltr; }
        .manifest-table .col-age { width: 4%; }
        .manifest-table .col-gender { width: 4%; }
        .manifest-table .col-status { width: 8%; }
        .manifest-table .col-airline { width: 8%; }
        .manifest-table .col-date { width: 7%; }
        .manifest-table .col-day { width: 5%; }
        .manifest-table .col-transfer { width: 7%; }
        .manifest-table .col-duration { width: 5%; }
        .manifest-table .col-return { width: 7%; }
        .manifest-table .col-place { width: 5%; }
        .manifest-table .col-whatsapp { width: 8%; direction: ltr; }

        .manifest-table td.col-passport,
        .manifest-table td.col-whatsapp {
            direction: ltr;
        }

        /* ── Summary box ───────────────────────────────────────────── */
        .summary {
            margin: 10px 12px 0;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--section-bg);
            padding: 7px 12px;
            font-size: 11px;
            font-weight: 600;
            flex-wrap: wrap;
        }

        .summary .summary-total {
            font-size: 12px;
            font-weight: 700;
            color: var(--dark);
        }

        /* ── Bottom stripe ─────────────────────────────────────────── */
        .bottom-stripe {
            height: 5px;
            background: linear-gradient(to left, var(--gold), var(--gold-light), var(--gold));
            margin-top: 12px;
        }

        /* ── Floating print button ─────────────────────────────────── */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12pt;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 9999;
        }

        .print-button:hover {
            background-color: #34495e;
        }

        /* ── Print overrides ───────────────────────────────────────── */
        @media print {
            body {
                max-width: none;
            }

            .print-button {
                display: none !important;
            }

            .summary {
                background-color: var(--section-bg) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                break-inside: avoid;
            }

            .meta-bar,
            .manifest-table th {
                background-color: var(--gold-pale) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .top-stripe,
            .bottom-stripe {
                background: linear-gradient(to left, var(--gold), var(--gold-light), var(--gold)) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">

    <!-- Top gold stripe -->
    <div class="top-stripe"></div>

    <!-- Floating print button -->
    <button class="print-button no-print" onclick="window.print()">🖨️ <?php echo htmlspecialchars($L['print']); ?></button>

    <!-- ── HEADER ───────────────────────────────────────────────────── -->
    <div class="header">
        <!-- Logo (RTL: appears on the right) -->
        <div class="header-logo">
            <?php if (!empty($settings['logo'])): ?>
                <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="Agency Logo">
            <?php endif; ?>
        </div>

        <!-- Center: agency name + branch -->
        <div class="header-center">
            <h1><?php echo htmlspecialchars($agencyName); ?></h1>
            <div class="subtitle"><?php echo htmlspecialchars($branch['name'] ?? ''); ?></div>
        </div>

        <!-- Contact info (left column in RTL layout) -->
        <div class="header-contact">
            <?php if (!empty($branch['phone'])): ?>
            <span>📞 <?= htmlspecialchars($branch['phone']) ?></span>
            <?php endif; ?>
            <?php if (!empty($branch['email'])): ?>
            <span>✉ <?= htmlspecialchars($branch['email']) ?></span>
            <?php endif; ?>
            <?php if (!empty($branch['address'])): ?>
            <span>📍 <?= htmlspecialchars($branch['address']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── FLIGHTS ──────────────────────────────────────────────────── -->
    <?php foreach ($flights as $flt):
        $fp = $flightPassengers[$flt['ticket_id'] ?? 0] ?? [];
        $counts = manifest_passenger_counts($fp, $L);
    ?>
    <!-- ── META BAR ─────────────────────────────────────────────────── -->
    <div class="meta-bar">
        <?php if (!empty($flt['_merged'])): ?>
        <span class="doc-title"><?php echo htmlspecialchars($L['doc_title']); ?></span>
        <span class="doc-subtitle"><?php echo htmlspecialchars($agencyName); ?></span>
        <?php else: ?>
        <span class="doc-title"><?php echo htmlspecialchars($L['doc_title']); ?></span>
        <span class="doc-subtitle"><?php echo htmlspecialchars($agencyName); ?> <?php echo htmlspecialchars($L['includes_flight']); ?></span>
        <span class="doc-date"><?php echo htmlspecialchars($L['day']); ?>: <?php echo manifest_day($flt['flight_date'] ?? '', $L); ?> | <?php echo htmlspecialchars($L['hijri']); ?>: <?php echo manifest_hijri($flt['flight_date'] ?? '', $L); ?> | <?php echo htmlspecialchars($L['gregorian']); ?>: <?php echo htmlspecialchars($flt['flight_date'] ?? '—'); ?></span>
        <?php endif; ?>
    </div>

    <!-- ── PASSENGER TABLE ──────────────────────────────────────────── -->
    <div class="manifest-wrap">
        <table class="manifest-table">
            <thead>
                <tr>
                    <th class="col-no"><?= $L['col_no'] ?></th>
                    <th class="col-reg"><?= $L['col_reg'] ?></th>
                    <th class="col-name"><?= $L['col_name'] ?></th>
                    <th class="col-fname"><?= $L['col_fname'] ?></th>
                    <th class="col-passport"><?= $L['col_passport'] ?></th>
                    <th class="col-age"><?= $L['col_age'] ?></th>
                    <th class="col-gender"><?= $L['col_gender'] ?></th>
                    <th class="col-airline"><?= $L['col_airline'] ?></th>
                    <th class="col-date"><?= $L['col_date'] ?></th>
                    <th class="col-day"><?= $L['col_day'] ?></th>
                    <th class="col-transfer"><?= $L['col_transfer'] ?></th>
                    <th class="col-duration"><?= $L['col_duration'] ?></th>
                    <th class="col-return"><?= $L['col_return'] ?></th>
                    <th class="col-place"><?= $L['col_place'] ?></th>
                    <th class="col-whatsapp"><?= $L['col_whatsapp'] ?></th>
                    <th class="col-status"><?= $L['col_services'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fp)): ?>
                <tr>
                    <td colspan="16" style="padding: 20px; color: var(--muted);"><?= $L['empty'] ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($fp as $i => $p):
                    $pFlt = $p['_flight'] ?? $flt;
                    $pFi = manifest_flight_info($pFlt, $docLanguage, $L, $pdo, $tenant_id);
                ?>
                <tr>
                    <td class="col-no">&nbsp;</td>
                    <td class="col-reg"><?php echo $i + 1; ?></td>
                    <td class="col-name"><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                    <td class="col-fname"><?php echo htmlspecialchars($p['fname'] ?? ''); ?></td>
                    <td class="col-passport"><?php echo htmlspecialchars($p['passport_number'] ?? ''); ?></td>
                    <td class="col-age"><?php echo manifest_age($p['dob'] ?? ''); ?></td>
                    <td class="col-gender"><?php echo manifest_gender($p['gender'] ?? '', $L); ?></td>
                    <td class="col-airline"><?php echo htmlspecialchars($pFi['airlineName']); ?><?php if ($pFi['departureTime'] !== ''): ?> — <?php echo htmlspecialchars($pFi['departureTime']); ?><?php endif; ?></td>
                    <td class="col-date"><?php echo manifest_hijri($pFlt['flight_date'] ?? '', $L); ?></td>
                    <td class="col-day"><?php echo manifest_day($pFlt['flight_date'] ?? '', $L); ?></td>
                    <td class="col-transfer"><?php echo htmlspecialchars($agencyName); ?></td>
                    <td class="col-duration"><?php echo htmlspecialchars($pFi['durationLocalized']); ?></td>
                    <td class="col-return"><?php echo manifest_hijri($pFlt['return_date'] ?? '', $L); ?></td>
                    <td class="col-place"><?php echo htmlspecialchars($pFi['departureCity']); ?></td>
                    <td class="col-whatsapp">&nbsp;</td>
                    <td class="col-status">&nbsp;</td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── SUMMARY ──────────────────────────────────────────────────── -->
    <div class="summary">
        <span class="summary-total"><?= $L['total_passengers'] ?>: <?php echo $counts['total']; ?> <?= ($docLanguage === 'ps') ? 'تنه' : (($docLanguage === 'en') ? 'people' : 'نفر'); ?></span>
        <span><?= $L['male'] ?>: <?php echo $counts['male']; ?></span>
        <span><?= $L['female'] ?>: <?php echo $counts['female']; ?></span>
        <span><?= $L['child'] ?> (2-11 <?= $L['years'] ?>): <?php echo $counts['child']; ?></span>
        <span><?= $L['infant'] ?> (0-2 <?= $L['years'] ?>): <?php echo $counts['infant']; ?></span>
    </div>

    <?php if (count($flights) > 1): ?>
    <div style="page-break-after: always;"></div>
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Bottom gold stripe -->
    <div class="bottom-stripe"></div>

<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>
