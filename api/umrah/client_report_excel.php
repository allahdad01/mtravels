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

// Ticket IDs required (comma-separated list for multi-ticket selection)
if (isset($_GET['ticket_ids']) && $_GET['ticket_ids'] !== '') {
    $rawIds = explode(',', (string)$_GET['ticket_ids']);
    $ticketIds = [];
    foreach ($rawIds as $rid) {
        $rid = (int)trim($rid);
        if ($rid > 0 && !in_array($rid, $ticketIds)) {
            $ticketIds[] = $rid;
        }
    }
} elseif (isset($_GET['ticket_id']) && !empty($_GET['ticket_id'])) {
    $ticketIds = [(int)$_GET['ticket_id']];
} else {
    $ticketIds = [];
}
if (empty($ticketIds)) {
    die('Invalid request: ticket_id required');
}
$ticketId = $ticketIds[0];

// Fetch the group tickets (all selected, in order)
$ticketPh = implode(',', array_fill(0, count($ticketIds), '?'));
$ticketStmt = $pdo->prepare("SELECT * FROM group_tickets WHERE ticket_id IN ({$ticketPh}) AND tenant_id = ? AND branch_id = ?");
$ticketStmt->execute(array_merge($ticketIds, [$tenant_id, $branch_id]));
$tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
if (count($tickets) !== count($ticketIds)) {
    die('Invalid request: ticket not found');
}
$ticket = $tickets[0];

// Members in ticket order, joined with client (sold_to) data
$memberIds = [];
foreach ($tickets as $t) {
    foreach (json_decode($t['member_ids'] ?? '[]', true) ?: [] as $mid) {
        $memberIds[] = (int)$mid;
    }
}
$memberIds = array_values(array_unique($memberIds));
$memberMap = [];
if (!empty($memberIds)) {
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $memberStmt = $pdo->prepare("
        SELECT b.booking_id, b.family_id, b.name, b.fname, b.gender, b.duration, b.room_type,
               b.passport_number, b.sold_price, b.received_bank_payment, b.currency, b.remarks, b.status,
               f.head_of_family, f.location, c.name AS client_name
        FROM umrah_bookings b
        LEFT JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
        LEFT JOIN clients c ON c.id = b.sold_to
        WHERE b.booking_id IN ({$placeholders}) AND b.tenant_id = ? AND b.branch_id = ?
    ");
    $memberStmt->execute(array_merge($memberIds, [$tenant_id, $branch_id]));
    foreach ($memberStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $memberMap[(int)$m['booking_id']] = $m;
    }
}

// Document language
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);

// Auto-translate names into the document language (MyMemory - free)
foreach ($memberMap as &$m) {
    $m['name'] = translate_name($m['name'] ?? '', $docLanguage);
    $m['fname'] = translate_name($m['fname'] ?? '', $docLanguage);
    $m['head_of_family'] = translate_name($m['head_of_family'] ?? '', $docLanguage);
    $m['client_name'] = translate_name($m['client_name'] ?? '', $docLanguage);
}
unset($m);

// UI labels per document language (deterministic, no external API)
$langLabels = [
    'dari' => [
        'doc_title' => 'گزارش معتمرین مشتریان',
        'subtitle' => 'گزارش معتمرین فروخته شده به مشتریان',
        'col_s' => 'شماره عمومی',
        'col_no' => 'شماره',
        'col_title' => 'عنوان',
        'col_name' => 'نام',
        'col_passport' => 'شماره پاسپورت',
        'col_duration' => 'مدت سفر',
        'col_room' => 'نوع اتاق',
        'col_client' => 'مشتری',
        'col_price' => 'قیمت مجموعی',
        'col_bank' => 'پرداخت به بانک',
        'col_remarks' => 'ملاحظات',
        'client' => 'مشتری',
        'total' => 'مجموع',
        'paid_to_bank' => 'پرداخت به بانک',
        'grand_total' => 'مجموع کلی',
        'members' => 'معتمر',
        'clients' => 'مشتری',
        'due' => 'مانده',
        'days' => 'روز',
        'mr' => 'آقا',
        'mrs' => 'خانم',
        'room_type' => ['shared' => 'مشترک', 'share' => 'مشترک', 'private' => 'خاص', 'خاص' => 'خاص', 'special' => 'خاص', 'single' => 'خاص', 'double' => 'دو نفره', 'triple' => 'سه نفره', 'quad' => 'چهار نفره', '1 bed' => 'اطاق خاص ۱ نفره', '2 beds' => 'اطاق خاص ۲ نفره', '3 beds' => 'اطاق خاص ۳ نفره', '4 beds' => 'اطاق خاص ۴ نفره'],
    ],
    'ps' => [
        'doc_title' => 'د پیرودونکو راپور',
        'subtitle' => 'پیرودونکو ته پلورل شوي معتمران',
        'col_s' => 'عمومي شمېره',
        'col_no' => 'شمېره',
        'col_title' => 'لقب',
        'col_name' => 'نوم',
        'col_passport' => 'د پاسپورټ شمېره',
        'col_duration' => 'د سفر موده',
        'col_room' => 'د اتاق ډول',
        'col_client' => 'پيرودونکی',
        'col_price' => 'ټول قیمت',
        'col_bank' => 'بانک ته تاديه',
        'col_remarks' => 'ملاحظات',
        'client' => 'پيرودونکی',
        'total' => 'مجموع',
        'paid_to_bank' => 'بانک ته تاديه',
        'grand_total' => 'ټول مجموع',
        'members' => 'معتمر',
        'clients' => 'پيرودونکی',
        'due' => 'باقي',
        'days' => 'ورځې',
        'mr' => 'ښاغلی',
        'mrs' => 'مېرمن',
        'room_type' => ['shared' => 'شریک', 'share' => 'شریک', 'private' => 'خصوصي', 'خاص' => 'خصوصي', 'special' => 'خصوصي', 'single' => 'خصوصي', '1 bed' => 'اطاق خاص ۱ نفره', '2 beds' => 'اطاق خاص ۲ نفره', '3 beds' => 'اطاق خاص ۳ نفره', '4 beds' => 'اطاق خاص ۴ نفره'],
    ],
    'en' => [
        'doc_title' => 'Umrah Client Report',
        'subtitle' => 'Umrah members sold to clients',
        'col_s' => 'S#',
        'col_no' => '#',
        'col_title' => 'Title',
        'col_name' => 'Name',
        'col_passport' => 'Passport #',
        'col_duration' => 'Duration',
        'col_room' => 'Room Type',
        'col_client' => 'Client',
        'col_price' => 'Total Price',
        'col_bank' => 'Paid to Bank',
        'col_remarks' => 'Remarks',
        'client' => 'Client',
        'total' => 'Total',
        'paid_to_bank' => 'Paid to Bank',
        'grand_total' => 'Grand Total',
        'members' => 'members',
        'clients' => 'clients',
        'due' => 'Due',
        'days' => 'Days',
        'mr' => 'MR',
        'mrs' => 'MRS',
        'room_type' => ['shared' => 'Shared', 'share' => 'Shared', 'private' => 'Private', 'خاص' => 'Private', 'special' => 'Private', 'single' => 'Private', '1 bed' => '1 Bed', '2 beds' => '2 Beds', '3 beds' => '3 Beds', '4 beds' => '4 Beds'],
    ],
];
$L = $langLabels[$docLanguage];

// Active members only (exclude refunded/cancelled from the sales report)
$members = [];
foreach ($memberIds as $id) {
    if (isset($memberMap[(int)$id])) {
        $m = $memberMap[(int)$id];
        if (in_array($m['status'] ?? '', ['refunded', 'cancelled'])) {
            continue;
        }
        $members[] = $m;
    }
}

// Families grouped by client (order of first appearance)
$families = [];
foreach ($members as $m) {
    $fid = (int)($m['family_id'] ?? 0);
    $families[$fid][] = $m;
}
$clientGroups = []; // client_name => [family_id => members]
foreach ($families as $fid => $famMembers) {
    $cname = trim((string)($famMembers[0]['client_name'] ?? ''));
    if ($cname === '') { $cname = '—'; }
    if (!isset($clientGroups[$cname])) {
        $clientGroups[$cname] = [];
    }
    $clientGroups[$cname][$fid] = $famMembers;
}

function client_room_type_label($rt, $L) {
    $rt = trim((string)$rt);
    if ($rt === '') { return ''; }
    $map = $L['room_type'];
    return $map[mb_strtolower($rt)] ?? $rt;
}

function client_title($gender, $L) {
    if ($gender === 'Male') { return $L['mr'] ?? 'MR'; }
    if ($gender === 'Female') { return $L['mrs'] ?? 'MRS'; }
    return '';
}

function client_duration_label($dur, $L) {
    $dur = trim((string)$dur);
    if ($dur === '') { return ''; }
    $num = preg_replace('/[^0-9]/', '', $dur);
    return ($num !== '' ? $num : $dur) . ' ' . $L['days'];
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft($docLanguage !== 'en');
$sheet->setTitle('Client Report');

$headers = [$L['col_s'], $L['col_no'], $L['col_title'], $L['col_name'], $L['col_passport'], $L['col_duration'], $L['col_room'], $L['col_client'], $L['col_price'], $L['col_bank'], $L['col_remarks']];
$lastCol = 'K';

// Title rows
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->setCellValue('A1', $L['doc_title']);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->setCellValue('A2', $L['subtitle']);
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->setCellValue('A3', $agencyName);

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

// Configurable client color palette (client block tint; cycles when exhausted)
$roomColors = [
    ['DBEAFE', '3B82F6'],  // blue
    ['FFEDD5', 'F59E0B'],  // orange
    ['FEE2E2', 'EF4444'],  // red
    ['FEF9C3', 'EAB308'],  // yellow
    ['EDE9FE', '8B5CF6'],  // purple
    ['DCFCE7', '22C55E'],  // green
    ['FCE7F3', 'EC4899'],  // pink
    ['E0F2FE', '06B6D4'],  // cyan
];

// Data rows
$row = $headerRow + 1;
$globalS = 0;
$colorIdx = 0;
$grandTotals = [];
$totalMembers = count($members);
$totalClients = count($clientGroups);

foreach ($clientGroups as $clientName => $famGroups) {
    $clientTotals = [];
    foreach ($famGroups as $fid => $famMembers) {
        foreach ($famMembers as $fm) {
            $cur = strtoupper((string)($fm['currency'] ?: 'USD'));
            if (!isset($clientTotals[$cur])) {
                $clientTotals[$cur] = ['price' => 0.0, 'bank' => 0.0];
            }
            $clientTotals[$cur]['price'] += (float)($fm['sold_price'] ?? 0);
            $clientTotals[$cur]['bank']  += (float)($fm['received_bank_payment'] ?? 0);
            if (!isset($grandTotals[$cur])) {
                $grandTotals[$cur] = ['price' => 0.0, 'bank' => 0.0];
            }
            $grandTotals[$cur]['price'] += (float)($fm['sold_price'] ?? 0);
            $grandTotals[$cur]['bank']  += (float)($fm['received_bank_payment'] ?? 0);
        }
    }
    $palette = $roomColors[$colorIdx % count($roomColors)];
    $colorIdx++;
    $tint = $palette[0];
    $accent = $palette[1];
    $clientNo = 0;

    foreach ($famGroups as $fid => $famMembers) {
        foreach ($famMembers as $fm) {
            $globalS++;
            $clientNo++;
            $cur = strtoupper((string)($fm['currency'] ?: 'USD'));
            $sheet->setCellValue('A' . $row, $globalS);
            $sheet->setCellValue('B' . $row, $clientNo);
            $sheet->setCellValue('C' . $row, client_title($fm['gender'] ?? '', $L));
            $sheet->setCellValue('D' . $row, $fm['name'] ?? '');
            $sheet->setCellValue('E' . $row, $fm['passport_number'] ?? '');
            $sheet->setCellValue('F' . $row, client_duration_label($fm['duration'] ?? '', $L));
            $sheet->setCellValue('G' . $row, client_room_type_label($fm['room_type'] ?? '', $L));
            $sheet->setCellValue('H' . $row, $fm['client_name'] ?? '');
            $sheet->setCellValue('I' . $row, number_format((float)($fm['sold_price'] ?? 0), 2) . ' ' . $cur);
            $sheet->setCellValue('J' . $row, number_format((float)($fm['received_bank_payment'] ?? 0), 2));
            $sheet->setCellValue('K' . $row, $fm['remarks'] ?? '');

            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tint);
            $sheet->getStyle('C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($accent);
            $sheet->getStyle('C' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $row++;
        }
    }

    // Client total foot row
    $footRow = $row;
    $cPriceParts = [];
    $cBankParts = [];
    $cDueParts = [];
    foreach ($clientTotals as $cur => $t) {
        $cPriceParts[] = number_format($t['price'], 2) . ' ' . $cur;
        $cBankParts[] = number_format($t['bank'], 2) . ' ' . $cur;
        $cDueParts[] = $L['due'] . ': ' . number_format($t['price'] - $t['bank'], 2) . ' ' . $cur;
    }
    $sheet->mergeCells('A' . $footRow . ':H' . $footRow);
    $sheet->setCellValue('A' . $footRow, $L['client'] . ': ' . $clientName);
    $sheet->setCellValue('I' . $footRow, implode("\n", $cPriceParts));
    $sheet->setCellValue('J' . $footRow, implode("\n", $cBankParts));
    $sheet->setCellValue('K' . $footRow, implode("\n", $cDueParts));
    $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
    $sheet->getStyle('A' . $footRow)->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle('K' . $footRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFB91C1C'));
    $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    $row++;
}

// Grand total row
$gPriceParts = [];
$gBankParts = [];
$gDueParts = [];
foreach ($grandTotals as $cur => $t) {
    $gPriceParts[] = number_format($t['price'], 2) . ' ' . $cur;
    $gBankParts[] = number_format($t['bank'], 2) . ' ' . $cur;
    $gDueParts[] = $L['due'] . ': ' . number_format($t['price'] - $t['bank'], 2) . ' ' . $cur;
}
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->setCellValue('A' . $row, $L['grand_total'] . ' (' . $totalMembers . ' ' . $L['members'] . ' | ' . $totalClients . ' ' . $L['clients'] . ')');
$sheet->setCellValue('I' . $row, implode("\n", $gPriceParts));
$sheet->setCellValue('J' . $row, implode("\n", $gBankParts));
$sheet->setCellValue('K' . $row, implode("\n", $gDueParts));
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('K' . $row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFB91C1C'));
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

// Column widths
$widths = [8, 8, 10, 26, 18, 10, 14, 20, 14, 14, 18];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
}
$sheet->getRowDimension($headerRow)->setRowHeight(30);
$sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastCol . $row)->getAlignment()->setWrapText(true);

// Freeze header
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'client_report_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)($ticket['pnr'] ?? $ticketId)) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
