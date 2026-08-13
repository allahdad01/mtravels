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

// Ticket ID is required
if (!isset($_GET['ticket_id']) || empty($_GET['ticket_id'])) {
    die('Invalid request: ticket_id required');
}
$ticketId = (int)$_GET['ticket_id'];

// Fulfillment mode: ticket_id is a booking id whose flight fulfillment is used
$isFulfillment = ($_GET['src'] ?? '') === 'fulfillment';

if ($isFulfillment) {
    require_once __DIR__ . '/fulfillment_flight_context.php';
    $ffCtx = fulfillment_flight_context($pdo, (int)$tenant_id, (int)$branch_id, $ticketId);
    if (!$ffCtx) {
        die('Invalid request: flight fulfillment not found');
    }
    $ticket = $ffCtx['ticket'];
    $memberIds = $ffCtx['member_ids'];
} else {
    // Fetch the group ticket
    $ticketStmt = $pdo->prepare("SELECT * FROM group_tickets WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ?");
    $ticketStmt->execute([$ticketId, $tenant_id, $branch_id]);
    $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        die('Invalid request: ticket not found');
    }
    $memberIds = json_decode($ticket['member_ids'] ?? '[]', true) ?: [];
}
$passengers = [];
if (!empty($memberIds)) {
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $memberStmt = $pdo->prepare("
        SELECT booking_id, name, fname, dob, gender, passport_number
        FROM umrah_bookings
        WHERE booking_id IN ({$placeholders}) AND tenant_id = ? AND branch_id = ?
    ");
    $memberStmt->execute(array_merge($memberIds, [$tenant_id, $branch_id]));
    $memberMap = [];
    foreach ($memberStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $memberMap[(int)$m['booking_id']] = $m;
    }
    foreach ($memberIds as $id) {
        if (isset($memberMap[(int)$id])) {
            $passengers[] = $memberMap[(int)$id];
        }
    }
}

// Auto-translate names into the document language (MyMemory - free)
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
translate_name_fields($passengers, $docLanguage, ['name', 'fname']);
$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);
$airlineName = translate_name($ticket['airline_name'] ?? '', $docLanguage);
$departureCity = translate_place($ticket['departure_city'] ?? '', $docLanguage);

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

$departureTime = $ticket['departure_time'] ?? '';
if (!empty($departureTime) && strpos($departureTime, ':') !== false) {
    $departureTime = substr($departureTime, 0, 5);
}

$durationRaw = trim((string)($ticket['duration'] ?? ''));
$durationLocalized = '—';
if ($durationRaw !== '') {
    $durationNum = preg_replace('/\s*days$/i', '', $durationRaw);
    $durationLocalized = $durationNum . ' ' . $L['days'];
}

$totalPassengers = count($passengers);
$maleCount = 0;
$femaleCount = 0;
$childCount = 0;
$infantCount = 0;
foreach ($passengers as $p) {
    if (($p['gender'] ?? '') === 'Male') { $maleCount++; }
    if (($p['gender'] ?? '') === 'Female') { $femaleCount++; }
    $age = manifest_age($p['dob'] ?? '');
    if ($age !== '—') {
        if ($age < 2) { $infantCount++; }
        elseif ($age <= 11) { $childCount++; }
    }
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

$titleRow = $L['doc_title'];
$subtitleRow = $agencyName . ' ' . $L['includes_flight'];
$dateRow = $L['day'] . ': ' . manifest_day($ticket['flight_date'] ?? '', $L)
    . ' | ' . $L['hijri'] . ': ' . manifest_hijri($ticket['flight_date'] ?? '', $L)
    . ' | ' . $L['gregorian'] . ': ' . ($ticket['flight_date'] ?? '—');

$lastCol = 'P';

// Title rows
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->setCellValue('A1', $titleRow);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->setCellValue('A2', $subtitleRow);
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->setCellValue('A3', $dateRow);

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A3')->getFont()->setSize(10);
foreach (['A1', 'A2', 'A3'] as $c) {
    $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
}

// Header row (row 5)
$headerRow = 5;
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . $headerRow, $h);
}
$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5EDD6');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Data rows
$row = $headerRow + 1;
foreach ($passengers as $i => $p) {
    $airlineCell = $airlineName . ($departureTime !== '' ? ' — ' . $departureTime : '');
    $values = [
        '', $i + 1, $p['name'] ?? '', $p['fname'] ?? '', $p['passport_number'] ?? '',
        manifest_age($p['dob'] ?? ''), manifest_gender($p['gender'] ?? '', $L),
        $airlineCell, manifest_hijri($ticket['flight_date'] ?? '', $L), manifest_day($ticket['flight_date'] ?? '', $L),
        $agencyName, $durationLocalized, manifest_hijri($ticket['return_date'] ?? '', $L), $departureCity, '', '',
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
}

// Column widths
$widths = [10, 10, 14, 14, 14, 6, 6, 18, 12, 8, 14, 8, 12, 10, 14, 12];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
}
$sheet->getRowDimension($headerRow)->setRowHeight(30);
$sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastCol . max($headerRow + 1, $row))->getAlignment()->setWrapText(true);

// Freeze header
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'passenger_manifest_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)($ticket['pnr'] ?? $ticketId)) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
