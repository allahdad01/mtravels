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

// Ticket IDs required (comma-separated list for multi-ticket rooming) OR group_id
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if (isset($_GET['ticket_ids']) && $_GET['ticket_ids'] !== '') {
    $rawIds = explode(',', (string)$_GET['ticket_ids']);
    $ticketIds = [];
    $directMemberIds = [];
    foreach ($rawIds as $rid) {
        $rid = trim($rid);
        // 'b<booking_id>' entries come from fulfillment flight cards (direct booking ids)
        if (stripos($rid, 'b') === 0 && is_numeric(substr($rid, 1))) {
            $bid = (int)substr($rid, 1);
            if ($bid > 0 && !in_array($bid, $directMemberIds)) {
                $directMemberIds[] = $bid;
            }
        } else {
            $rid = (int)$rid;
            if ($rid > 0 && !in_array($rid, $ticketIds)) {
                $ticketIds[] = $rid;
            }
        }
    }
} elseif (isset($_GET['ticket_id']) && !empty($_GET['ticket_id'])) {
    $ticketIds = [(int)$_GET['ticket_id']];
} else {
    $ticketIds = [];
}
if (empty($ticketIds) && empty($directMemberIds) && empty($groupId)) {
    die('Invalid request: ticket_id or group_id required');
}
$ticketId = $ticketIds[0] ?? 0;

// Fetch the group tickets (all selected, in order)
$tickets = [];
if (!empty($ticketIds)) {
    $ticketPh = implode(',', array_fill(0, count($ticketIds), '?'));
    $ticketStmt = $pdo->prepare("SELECT * FROM group_tickets WHERE ticket_id IN ({$ticketPh}) AND tenant_id = ? AND branch_id = ?");
    $ticketStmt->execute(array_merge($ticketIds, [$tenant_id, $branch_id]));
    $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($tickets) !== count($ticketIds)) {
        die('Invalid request: ticket not found');
    }
}
$ticket = $tickets[0] ?? [];

// Members in ticket order, joined with family data (family = room group)
$memberIds = $directMemberIds;
$memberMap = [];

if (!empty($groupId)) {
    $grpStmt = $pdo->prepare("
        SELECT b.booking_id, b.family_id, b.name, b.fname, b.gender, b.duration, b.room_type,
               b.passport_number, f.head_of_family,
               hr.room_number, hr.floor
        FROM umrah_bookings b
        LEFT JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
        LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = (
            SELECT f3.id
            FROM umrah_booking_services bs3
            JOIN umrah_fulfillments f3 ON f3.booking_service_id = bs3.id
                 AND f3.fulfillment_type = 'hotel' AND f3.tenant_id = bs3.tenant_id
            WHERE bs3.booking_id = b.booking_id AND bs3.service_type = 'hotel'
                  AND bs3.tenant_id = b.tenant_id
            ORDER BY f3.id LIMIT 1
        )
        LEFT JOIN umrah_hotel_rooms hr ON hr.id = hf.room_id
        WHERE f.group_id = ? AND b.tenant_id = ? AND b.branch_id = ?
          AND b.status NOT IN ('refunded', 'cancelled')
    ");
    $grpStmt->execute([$groupId, $tenant_id, $branch_id]);
    foreach ($grpStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $memberMap[(int)$m['booking_id']] = $m;
        $memberIds[] = (int)$m['booking_id'];
    }
    $memberIds = array_values(array_unique($memberIds));
} else {
foreach ($tickets as $t) {
    foreach (json_decode($t['member_ids'] ?? '[]', true) ?: [] as $mid) {
        $memberIds[] = (int)$mid;
    }
} else {
    foreach ($tickets as $t) {
        foreach (json_decode($t['member_ids'] ?? '[]', true) ?: [] as $mid) {
            $memberIds[] = (int)$mid;
        }
    }
    $memberIds = array_values(array_unique($memberIds));
    if (!empty($memberIds)) {
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $memberStmt = $pdo->prepare("
            SELECT b.booking_id, b.family_id, b.name, b.fname, b.gender, b.duration, b.room_type,
                   b.passport_number, f.head_of_family,
                   hr.room_number, hr.floor
            FROM umrah_bookings b
            LEFT JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
            LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = (
                SELECT f3.id
                FROM umrah_booking_services bs3
                JOIN umrah_fulfillments f3 ON f3.booking_service_id = bs3.id
                     AND f3.fulfillment_type = 'hotel' AND f3.tenant_id = bs3.tenant_id
                WHERE bs3.booking_id = b.booking_id AND bs3.service_type = 'hotel'
                      AND bs3.tenant_id = b.tenant_id
                ORDER BY f3.id LIMIT 1
            )
            LEFT JOIN umrah_hotel_rooms hr ON hr.id = hf.room_id
            WHERE b.booking_id IN ({$placeholders}) AND b.tenant_id = ? AND b.branch_id = ?
        ");
        $memberStmt->execute(array_merge($memberIds, [$tenant_id, $branch_id]));
        foreach ($memberStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $memberMap[(int)$m['booking_id']] = $m;
        }
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
}
unset($m);

// UI labels per document language (deterministic, no external API)
$langLabels = [
    'dari' => [
        'doc_title' => 'مدیریت اتاق‌ها',
        'subtitle' => 'جدول تنظیم اتاق‌های عمره',
        'col_room' => 'اتاق',
        'col_title' => 'عنوان',
        'col_name' => 'نام',
        'col_passport' => 'شماره پاسپورت',
        'col_days' => 'روز',
        'col_otagh' => 'اطاق',
        'mr' => 'آقا',
        'mrs' => 'خانم',
        'services' => 'خدمات',
        'days' => 'روز',
        'empty' => 'هیچ مسافری در این پرواز ثبت نشده است',
        'males' => 'مردان',
        'females' => 'زنان',
        'unspecified' => 'نامشخص',
        'family_head' => 'سرپرست خانواده',
        'total_passengers' => 'مجموع مسافرین',
        'total_rooms' => 'مجموع اتاق‌ها',
        'persons' => 'نفر',
        'room_type' => ['shared' => 'مشترک', 'share' => 'مشترک', 'private' => 'خاص', 'خاص' => 'خاص', 'special' => 'خاص', 'single' => 'خاص', 'double' => 'دو نفره', 'triple' => 'سه نفره', 'quad' => 'چهار نفره', '1 bed' => 'اطاق خاص ۱ نفره', '2 beds' => 'اطاق خاص ۲ نفره', '3 beds' => 'اطاق خاص ۳ نفره', '4 beds' => 'اطاق خاص ۴ نفره'],
    ],
    'ps' => [
        'doc_title' => 'د اتاقونو مدیریت',
        'subtitle' => 'د عمرې د اتاقونو تنظیم جدول',
        'col_room' => 'کوټه',
        'col_title' => 'لقب',
        'col_name' => 'نوم',
        'col_passport' => 'د پاسپورټ شمېره',
        'col_days' => 'ورځې',
        'col_otagh' => 'اتاق',
        'mr' => 'ښاغلی',
        'mrs' => 'مېرمن',
        'services' => 'خدمت',
        'days' => 'ورځې',
        'empty' => 'په دې الوتکه کې هېڅ مسافر ثبت شوی نه دی',
        'males' => 'نرینه',
        'females' => 'ښځې',
        'unspecified' => 'نامعلوم',
        'family_head' => 'د کورنۍ مشر',
        'total_passengers' => 'ټول مسافرین',
        'total_rooms' => 'ټول اتاقونه',
        'persons' => 'تنه',
        'room_type' => ['shared' => 'شریک', 'share' => 'شریک', 'private' => 'خصوصي', 'خاص' => 'خصوصي', 'special' => 'خصوصي', 'single' => 'خصوصي', '1 bed' => 'اطاق خاص ۱ نفره', '2 beds' => 'اطاق خاص ۲ نفره', '3 beds' => 'اطاق خاص ۳ نفره', '4 beds' => 'اطاق خاص ۴ نفره'],
    ],
    'en' => [
        'doc_title' => 'ROOM MANAGEMENT',
        'subtitle' => 'Umrah Rooming List',
        'col_room' => 'Room',
        'col_title' => 'Title',
        'col_name' => 'Name',
        'col_passport' => 'Passport #',
        'col_days' => 'Days',
        'col_otagh' => 'Room Type',
        'mr' => 'MR',
        'mrs' => 'MRS',
        'services' => 'Service',
        'days' => 'Days',
        'empty' => 'No passengers registered on this flight',
        'males' => 'Males',
        'females' => 'Females',
        'unspecified' => 'Unspecified',
        'family_head' => 'Family Head',
        'total_passengers' => 'Total Passengers',
        'total_rooms' => 'Total Rooms',
        'persons' => 'people',
        'room_type' => ['shared' => 'Shared', 'share' => 'Shared', 'private' => 'Private', 'خاص' => 'Private', 'special' => 'Private', 'single' => 'Private', '1 bed' => '1 Bed', '2 beds' => '2 Beds', '3 beds' => '3 Beds', '4 beds' => '4 Beds'],
    ],
];
$L = $langLabels[$docLanguage];

// Rooms: family = room, members in ticket order. Each room carries its
// members plus the family head and an optional gender-split label.
$rooms = [];
foreach ($memberIds as $id) {
    if (isset($memberMap[(int)$id])) {
        $m = $memberMap[(int)$id];
        $fid = (int)($m['family_id'] ?? 0);
        if (!isset($rooms[$fid])) {
            $rooms[$fid] = ['members' => [], 'head' => (string)($m['head_of_family'] ?? ''), 'split' => '', 'shared' => false];
        }
        $rooms[$fid]['members'][] = $m;
    }
}

// Shared-room members (room_type = shared) are pulled out of their families
// and grouped globally: all males in one room, all females in another (plus
// one bucket for members whose gender is unknown), matching the hotel's
// shared-room assignment. The shared rooms are appended after all private
// families so they are displayed together on the sheet.
$sharedBuckets = ['male' => [], 'female' => [], 'other' => []];
$sharedSeen = false;
$groupedRooms = [];
foreach ($rooms as $fid => $roomData) {
    $rt = '';
    foreach ($roomData['members'] as $m) {
        if ($rt === '' && trim((string)($m['room_type'] ?? '')) !== '') { $rt = trim((string)$m['room_type']); }
    }
    $rtLow = mb_strtolower(trim($rt));
    if ($rtLow === 'shared' || $rtLow === 'share') {
        foreach ($roomData['members'] as $m) {
            $g = (string)($m['gender'] ?? '');
            $bk = $g === 'Male' ? 'male' : ($g === 'Female' ? 'female' : 'other');
            $sharedBuckets[$bk][] = $m;
            $sharedSeen = true;
        }
        continue;
    }
    $groupedRooms[$fid] = $roomData;
}
if ($sharedSeen) {
    foreach (['male', 'female', 'other'] as $bk) {
        if (empty($sharedBuckets[$bk])) { continue; }
        $splitLabel = $bk === 'male' ? $L['males'] : ($bk === 'female' ? $L['females'] : $L['unspecified']);
        $groupedRooms['__shared_' . $bk] = [
            'members' => $sharedBuckets[$bk],
            'head' => '',
            'split' => $splitLabel,
            'shared' => true,
        ];
    }
}
$rooms = $groupedRooms;

// Service sections: rooms grouped by (duration, room_type); separator inserted when group changes
$sections = [];
foreach ($rooms as $roomData) {
    $members = $roomData['members'];
    $dur = '';
    $rt = '';
    foreach ($members as $m) {
        if ($dur === '' && trim((string)($m['duration'] ?? '')) !== '') { $dur = trim((string)$m['duration']); }
        if ($rt === '' && trim((string)($m['room_type'] ?? '')) !== '') { $rt = trim((string)$m['room_type']); }
    }
    if ($dur === '') { $dur = trim((string)($ticket['duration'] ?? '')); }
    $key = $dur . "\x1f" . $rt;
    $lastIdx = count($sections) - 1;
    if ($lastIdx >= 0 && $sections[$lastIdx]['key'] === $key) {
        $sections[$lastIdx]['rooms'][] = $roomData;
    } else {
        $sections[] = ['key' => $key, 'duration' => $dur, 'room_type' => $rt, 'rooms' => [$roomData]];
    }
}

// Order sections by duration ascending (fewest days at the top)
usort($sections, function ($a, $b) {
    $da = (int)preg_replace('/[^0-9]/', '', (string)$a['duration']);
    $db = (int)preg_replace('/[^0-9]/', '', (string)$b['duration']);
    return $da <=> $db;
});

// Configurable room color palette (family color; cycles when exhausted)
// Each entry: [light row tint, deeper title-cell color] (hex without '#')
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

function rooming_service_label($section, $L) {
    $days = preg_replace('/[^0-9]/', '', (string)$section['duration']);
    $rt = trim((string)$section['room_type']);
    $rtLabel = '';
    if ($rt !== '') {
        $map = $L['room_type'];
        $rtLabel = $map[mb_strtolower($rt)] ?? $rt;
    }
    $parts = [];
    if ($rtLabel !== '') { $parts[] = $rtLabel; }
    if ($days !== '') { $parts[] = $days . ' ' . $L['days']; }
    $parts[] = $L['services'];
    return implode(' ', $parts);
}

function rooming_title($gender, $L) {
    if ($gender === 'Male') { return $L['mr'] ?? 'MR'; }
    if ($gender === 'Female') { return $L['mrs'] ?? 'MRS'; }
    return '';
}

function rooming_days($m) {
    $d = trim((string)($m['duration'] ?? ''));
    if ($d === '') { return ''; }
    return preg_replace('/[^0-9]/', '', $d);
}

function rooming_full_name($m) {
    return trim((string)($m['name'] ?? ''));
}

// One room group normally shares a single hotel room. When the members'
// stored assignments differ (e.g. a shared gender bucket the hotel spread
// across two rooms), each row falls back to its own room instead of the
// group's first member being shown for everyone.
function rooming_member_room($m, $fallback) {
    $r = trim((string)($m['room_number'] ?? ''));
    if ($r !== '') {
        $f = trim((string)($m['floor'] ?? ''));
        if ($f !== '') { $r .= ' / ' . $f; }
        return $r;
    }
    return (string)$fallback;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft($docLanguage !== 'en');
$sheet->setTitle('Rooming List');

$headers = [$L['col_room'], $L['col_title'], $L['col_name'], $L['col_passport'], $L['col_days'], $L['col_otagh']];
$lastCol = 'F';

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

// Data rows
$row = $headerRow + 1;
$roomNum = 0;
$colorIdx = 0;
$totalPassengers = 0;
$totalRooms = 0;

foreach ($sections as $si => $section) {
    {
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, rooming_service_label($section, $L));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E5E4');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
    }

    foreach ($section['rooms'] as $roomData) {
        $room = $roomData['members'];
        $roomNum++;
        $totalRooms++;
        $palette = $roomColors[$colorIdx % count($roomColors)];
        $colorIdx++;
        $tint = $palette[0];
        $accent = $palette[1];

        $roomStart = $row;
        $roomTypeSet = [];
        foreach ($room as $m) {
            $roomTypeSet[trim((string)($m['room_type'] ?? ''))] = true;
        }
        $mergeOtagh = count($roomTypeSet) <= 1;

        $roomDisplays = [];
        foreach ($room as $rm) {
            $roomDisplays[] = rooming_member_room($rm, $roomNum);
        }
        $mergeRoom = count(array_unique($roomDisplays)) === 1;
        $roomDisplay = (string)$roomDisplays[0];

        foreach ($room as $ri => $m) {
            $sheet->setCellValue('A' . $row, $mergeRoom ? $roomDisplay : (string)$roomDisplays[$ri]);
            $sheet->setCellValue('B' . $row, rooming_title($m['gender'] ?? '', $L));
            $sheet->setCellValue('C' . $row, rooming_full_name($m));
            $sheet->setCellValue('D' . $row, $m['passport_number'] ?? '');
            $sheet->setCellValue('E' . $row, rooming_days($m));
            $sheet->setCellValue('F' . $row, $mergeOtagh ? rooming_otagh_label($section['room_type'], $L) : rooming_otagh_label($m['room_type'] ?? '', $L));

            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tint);
            $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($accent);
            $sheet->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $row++;
        }

        // Family foot row: merged A:E label, empty F (split rooms append the
        // gender group, e.g. "Family Head: X · Males")
        $footRow = $row;
        if (!empty($roomData['shared'])) {
            $footLabel = ($L['room_type']['shared'] ?? 'Shared') . ' · ' . ($roomData['split'] ?? '');
        } else {
            $footLabel = $L['family_head'] . ': ' . ($roomData['head'] ?? '');
            if (($roomData['split'] ?? '') !== '') { $footLabel .= ' · ' . $roomData['split']; }
        }
        $sheet->mergeCells('A' . $footRow . ':E' . $footRow);
        $sheet->setCellValue('A' . $footRow, $footLabel);
        $sheet->setCellValue('F' . $footRow, '');
        $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($tint);
        $sheet->getStyle('A' . $footRow)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        // Merge the room number cell across the member rows (only when the
        // whole group shares one assignment; otherwise each row already got
        // its own room in the loop above)
        if ($mergeRoom && $footRow - $roomStart > 1) {
            $sheet->mergeCells('A' . $roomStart . ':A' . ($footRow - 1));
        }
        // Merge the room type cell when all members share one room type
        if ($mergeOtagh && ($footRow - $roomStart) > 1) {
            $sheet->mergeCells('F' . $roomStart . ':F' . ($footRow - 1));
        }

        $totalPassengers += count($room);
    }
}

function rooming_otagh_label($rt, $L) {
    $rt = trim((string)$rt);
    if ($rt === '') { return ''; }
    $map = $L['room_type'];
    return $map[mb_strtolower($rt)] ?? $rt;
}

// Summary row
$summary = $L['total_passengers'] . ': ' . $totalPassengers . ' ' . $L['persons'] . ' | ' . $L['total_rooms'] . ': ' . $totalRooms;
$sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
$sheet->setCellValue('A' . $row, $summary);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Column widths
$widths = [8, 10, 26, 18, 8, 14];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
}
$sheet->getRowDimension($headerRow)->setRowHeight(30);
$sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastCol . $row)->getAlignment()->setWrapText(true);

// Freeze header
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'rooming_list_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)($ticket['pnr'] ?? ($memberIds[0] ?? $ticketId))) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
