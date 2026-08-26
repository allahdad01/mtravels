<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
require_once '../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/translate_helper.php';
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

// Ticket ID or group_id required
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if (!isset($_GET['ticket_id']) && !isset($_GET['group_id'])) {
    die('Invalid request: ticket_id or group_id required');
}
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

// Fulfillment mode: ticket_id is a booking id whose flight fulfillment is used
$isFulfillment = ($_GET['src'] ?? '') === 'fulfillment';

// Fetch all flights
$flights = [];
$flightPassengers = [];
if (!empty($groupId)) {
    // 1) Fetch ALL members of the group
    $grpMemberStmt = $pdo->prepare("
        SELECT b.booking_id, b.name, b.fname, b.dob, b.gender, b.passport_number, b.family_id, b.duration
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
        if (!$ffCtx) { die('Invalid request: flight fulfillment not found'); }
        $flights = [$ffCtx['ticket']];
        $ffMembers = [$ffCtx['ticket']['ticket_id'] ?? $ticketId => $ffCtx['member_ids']];
    } else {
        $ticketStmt = $pdo->prepare("SELECT * FROM group_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ?");
        $ticketStmt->execute([$ticketId, $tenant_id, $branch_id]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { die('Invalid request: ticket not found'); }
        $flights = [$ticket];
    }

    // Fetch passengers per flight (single-ticket mode)
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
                SELECT booking_id, name, fname, dob, gender, passport_number
                FROM umrah_bookings
                WHERE booking_id IN ({$placeholders}) AND tenant_id = ? AND branch_id = ?
            ");
            $memberStmt->execute(array_merge($mids, [$tenant_id, $branch_id]));
            $flightPassengers[$fid] = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $flightPassengers[$fid] = [];
        }
    }
}

// Auto-translate names into the document language (MyMemory - free)
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);

// Translate all passengers across all flights
foreach ($flightPassengers as &$fp) {
    translate_name_fields($fp, $docLanguage, ['name', 'fname']);
}
unset($fp);

// UI labels per document language
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
        'total_passengers' => 'مجموع مسافرین',
        'male' => 'مرد',
        'female' => 'زن',
        'child' => 'کودک',
        'infant' => 'طفل',
        'years' => 'سال',
        'days' => 'روز',
        'persons' => 'نفر',
        'day_names' => [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'],
        'hijri_suffix' => 'هـ',
        'unassigned_title' => 'مسافرین بدون پرواز',
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
        'total_passengers' => 'ټول مسافرین',
        'male' => 'سړی',
        'female' => 'ښځه',
        'child' => 'ماشوم',
        'infant' => 'شیدي',
        'years' => 'کلنۍ',
        'days' => 'ورځې',
        'persons' => 'تنه',
        'day_names' => [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'],
        'hijri_suffix' => 'هـ',
        'unassigned_title' => 'د پرواز پرته مسافرین',
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
        'total_passengers' => 'Total',
        'male' => 'Male',
        'female' => 'Female',
        'child' => 'Child',
        'infant' => 'Infant',
        'years' => 'years',
        'days' => 'Days',
        'persons' => 'people',
        'day_names' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        'hijri_suffix' => 'AH',
        'unassigned_title' => 'Members Without Flight Assignment',
    ],
];
$L = $langLabels[$docLanguage];

// Helpers
function manifest_age($dob) {
    if (empty($dob) || $dob === '0000-00-00') { return '—'; }
    $ts = strtotime($dob);
    if (!$ts) { return '—'; }
    return date('Y') - date('Y', $ts) - (date('md') < date('md', $ts) ? 1 : 0);
}
function manifest_gender($gender, $L) {
    if ($gender === 'Male') { return $L['male']; }
    if ($gender === 'Female') { return $L['female']; }
    return '—';
}
function manifest_day($date, $L) {
    $ts = strtotime($date);
    if ($ts === false) { return '—'; }
    return $L['day_names'][(int)date('N', $ts)] ?? '—';
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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft($docLanguage !== 'en');
$sheet->setTitle('Manifest');

$headers = [
    $L['col_no'], $L['col_reg'], $L['col_name'], $L['col_fname'], $L['col_passport'], $L['col_age'], $L['col_gender'],
    $L['col_airline'], $L['col_date'], $L['col_day'], $L['col_transfer'], $L['col_duration'],
    $L['col_return'], $L['col_place'], $L['col_whatsapp'], $L['col_services'],
];

$lastCol = 'P';
$row = 1;

foreach ($flights as $flt) {
    $isMerged = !empty($flt['_merged']);

    $passengers = $flightPassengers[$flt['ticket_id'] ?? 0] ?? [];
    $titleRow = $L['doc_title'];
    $subtitleRow = $agencyName;

    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->setCellValue('A' . $row, $titleRow);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;

    if (!$isMerged) {
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $subtitleRow);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $dateRow = $L['day'] . ': ' . manifest_day($flt['flight_date'] ?? '', $L)
            . ' | ' . $L['hijri'] . ': ' . manifest_hijri($flt['flight_date'] ?? '', $L)
            . ' | ' . $L['gregorian'] . ': ' . ($flt['flight_date'] ?? '—');
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $dateRow);
        $sheet->getStyle('A' . $row)->getFont()->setSize(10);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    }
    $row++;

    // Header row
    $headerRow = $row;
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $sheet->setCellValue($col . $headerRow, $h);
    }
    $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5EDD6');
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;

    // Data rows
    foreach ($passengers as $i => $p) {
        $pFlt = $p['_flight'] ?? null;
        $pAirlineName = $pFlt ? translate_name($pFlt['airline_name'] ?? '', $docLanguage) : '';
        $pDepartureCity = $pFlt ? translate_place($pFlt['departure_city'] ?? '', $docLanguage) : '';
        $pDepartureTime = $pFlt['departure_time'] ?? '';
        if ($pFlt && !empty($pDepartureTime)) {
            if (strpos($pDepartureTime, ' ') !== false) {
                $pDepartureTime = substr($pDepartureTime, strpos($pDepartureTime, ' ') + 1);
            }
            if (strpos($pDepartureTime, ':') !== false) {
                $pDepartureTime = substr($pDepartureTime, 0, 5);
            }
        }
        $pDurationRaw = trim((string)($pFlt['duration'] ?? ''));
        $pDurationLocalized = '—';
        if ($pFlt && $pDurationRaw !== '') {
            $pDurationNum = preg_replace('/\s*days$/i', '', $pDurationRaw);
            $pDurationLocalized = $pDurationNum . ' ' . $L['days'];
        }
        $airlineCell = $pAirlineName . ($pDepartureTime !== '' ? ' — ' . $pDepartureTime : '');
        $values = [
            '', $i + 1, $p['name'] ?? '', $p['fname'] ?? '', $p['passport_number'] ?? '',
            manifest_age($p['dob'] ?? ''), manifest_gender($p['gender'] ?? '', $L),
            $airlineCell, manifest_hijri($pFlt['flight_date'] ?? '', $L), manifest_day($pFlt['flight_date'] ?? '', $L),
            $agencyName, $pDurationLocalized, manifest_hijri($pFlt['return_date'] ?? '', $L), $pDepartureCity, '', '',
        ];
        foreach ($values as $ci => $v) {
            $col = chr(65 + $ci);
            $sheet->setCellValue($col . $row, $v);
        }
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $row++;
    }

    // Summary row
    $totalPassengers = count($passengers);
    $maleCount = $femaleCount = $childCount = $infantCount = 0;
    foreach ($passengers as $p) {
        if (($p['gender'] ?? '') === 'Male') { $maleCount++; }
        if (($p['gender'] ?? '') === 'Female') { $femaleCount++; }
        $age = manifest_age($p['dob'] ?? '');
        if ($age !== '—') {
            if ($age < 2) { $infantCount++; }
            elseif ($age <= 11) { $childCount++; }
        }
    }
    if ($totalPassengers > 0) {
        $row++;
        $summary = $L['total_passengers'] . ': ' . $totalPassengers . ' ' . $L['persons'] . ' | ' . $L['male'] . ': ' . $maleCount
            . ' | ' . $L['female'] . ': ' . $femaleCount
            . ' | ' . $L['child'] . ' (2-11 ' . $L['years'] . '): ' . $childCount
            . ' | ' . $L['infant'] . ' (0-2 ' . $L['years'] . '): ' . $infantCount;
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $summary);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    }

    // Gap between flights
    if (count($flights) > 1) {
        $row += 2;
    }
}

// Column widths
$widths = [10, 10, 14, 14, 14, 6, 6, 18, 12, 8, 14, 8, 12, 10, 14, 12];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
}

$filename = 'passenger_manifest_group.xlsx';
if ($groupId) {
    $filename = 'passenger_manifest_group_' . $groupId . '.xlsx';
} else {
    $firstPnr = $flights[0]['pnr'] ?? $ticketId;
    $filename = 'passenger_manifest_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$firstPnr) . '.xlsx';
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
