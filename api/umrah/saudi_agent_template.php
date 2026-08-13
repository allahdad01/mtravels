<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
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

// Branch data
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

// Ticket IDs required (comma-separated list for multi-ticket rooming)
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
if (empty($ticketIds) && empty($directMemberIds)) {
    die('Invalid request: ticket_id required');
}
$ticketId = $ticketIds[0] ?? 0;

// Fetch the group tickets (all selected, in order). Raw ids that are not
// group_tickets ids are treated as booking ids (fulfillment selections).
$tickets = [];
if (!empty($ticketIds)) {
    $ticketPh = implode(',', array_fill(0, count($ticketIds), '?'));
    $ticketStmt = $pdo->prepare("SELECT * FROM group_tickets WHERE ticket_id IN ({$ticketPh}) AND tenant_id = ? AND branch_id = ?");
    $ticketStmt->execute(array_merge($ticketIds, [$tenant_id, $branch_id]));
    $foundTickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
    $foundTicketIds = array_map('intval', array_column($foundTickets, 'ticket_id'));
    $missingIds = [];
    foreach ($ticketIds as $tid) {
        if (!in_array((int)$tid, $foundTicketIds, true)) {
            $missingIds[] = (int)$tid;
        }
    }
    if (!empty($missingIds)) {
        $mbPh = implode(',', array_fill(0, count($missingIds), '?'));
        $mbStmt = $pdo->prepare("SELECT booking_id FROM umrah_bookings WHERE booking_id IN ({$mbPh}) AND tenant_id = ? AND branch_id = ?");
        $mbStmt->execute(array_merge($missingIds, [$tenant_id, $branch_id]));
        foreach ($mbStmt->fetchAll(PDO::FETCH_COLUMN) as $bid) {
            if (!in_array((int)$bid, $directMemberIds, true)) {
                $directMemberIds[] = (int)$bid;
            }
        }
    }
    $tickets = $foundTickets;
}
if (empty($tickets) && empty($directMemberIds)) {
    die('Invalid request: ticket not found');
}
$ticket = $tickets[0] ?? [];

// Members in ticket order, joined with family data (family = room group)
$memberIds = $directMemberIds;
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
               b.passport_number, f.head_of_family, f.location,
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
        'subtitle' => 'جدول تنظیم اتاق‌های عمره',
        'doc_title' => 'مدیریت اتاق‌ها',
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
        'print' => 'چاپ',
        'empty' => 'هیچ مسافری در این پرواز ثبت نشده است',
        'family_head' => 'سرپرست خانواده',
        'males' => 'مردان',
        'females' => 'زنان',
        'unspecified' => 'نامشخص',
        'room_type' => ['shared' => 'مشترک', 'share' => 'مشترک', 'private' => 'خاص', 'خاص' => 'خاص', 'special' => 'خاص', 'single' => 'خاص', 'double' => 'دو نفره', 'triple' => 'سه نفره', 'quad' => 'چهار نفره', '1 bed' => 'اطاق خاص ۱ نفره', '2 beds' => 'اطاق خاص ۲ نفره', '3 beds' => 'اطاق خاص ۳ نفره', '4 beds' => 'اطاق خاص ۴ نفره'],
        'hijri_suffix' => 'هـ',
    ],
    'ps' => [
        'subtitle' => 'د عمرې د اتاقونو تنظیم جدول',
        'doc_title' => 'د اتاقونو مدیریت',
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
        'print' => 'چاپ',
        'empty' => 'په دې الوتکه کې هېڅ مسافر ثبت شوی نه دی',
        'family_head' => 'د کورنۍ مشر',
        'males' => 'نرینه',
        'females' => 'ښځې',
        'unspecified' => 'نامعلوم',
        'room_type' => ['shared' => 'شریک', 'share' => 'شریک', 'private' => 'خصوصي', 'خاص' => 'خصوصي', 'special' => 'خصوصي', 'single' => 'خصوصي', '1 bed' => 'اطاق خاص ۱ نفره', '2 beds' => 'اطاق خاص ۲ نفره', '3 beds' => 'اطاق خاص ۳ نفره', '4 beds' => 'اطاق خاص ۴ نفره'],
        'hijri_suffix' => 'هـ',
    ],
    'en' => [
        'subtitle' => 'Umrah Rooming List',
        'doc_title' => 'ROOM MANAGEMENT',
        'col_room' => 'ROOM',
        'col_title' => 'TITLE',
        'col_name' => 'NAME',
        'col_passport' => 'PASSPORT #',
        'col_days' => 'DAYS',
        'col_otagh' => 'ROOM TYPE',
        'mr' => 'MR',
        'mrs' => 'MRS',
        'services' => 'Service',
        'days' => 'Days',
        'print' => 'Print',
        'empty' => 'No passengers registered on this flight',
        'family_head' => 'Family Head',
        'males' => 'Males',
        'females' => 'Females',
        'unspecified' => 'Unspecified',
        'room_type' => ['shared' => 'Shared', 'share' => 'Shared', 'private' => 'Private', 'خاص' => 'Private', 'special' => 'Private', 'single' => 'Private', '1 bed' => '1 Bed', '2 beds' => '2 Beds', '3 beds' => '3 Beds', '4 beds' => '4 Beds'],
        'hijri_suffix' => 'AH',
    ],
];
$L = $langLabels[$docLanguage];

// Rooms: family = room. Shared-room members are aggregated globally and
// split by gender (all males in one room, all females in another) so the
// printed sheet matches the hotel's shared-room assignment.
$rooms = [];
$sharedBuckets = ['male' => [], 'female' => [], 'other' => []];
$sharedSeen = false;
foreach ($memberIds as $id) {
    if (!isset($memberMap[(int)$id])) {
        continue;
    }
    $m = $memberMap[(int)$id];
    $rtLow = mb_strtolower(trim((string)($m['room_type'] ?? '')));
    if ($rtLow === 'shared' || $rtLow === 'share') {
        $g = (string)($m['gender'] ?? '');
        $bk = $g === 'Male' ? 'male' : ($g === 'Female' ? 'female' : 'other');
        $sharedBuckets[$bk][] = $m;
        $sharedSeen = true;
        continue;
    }
    $fid = (int)($m['family_id'] ?? 0);
    $rooms[$fid][] = $m;
}
if ($sharedSeen) {
    foreach (['male', 'female', 'other'] as $bk) {
        if (empty($sharedBuckets[$bk])) {
            continue;
        }
        $rooms['__shared_' . $bk] = $sharedBuckets[$bk];
    }
}

// Service sections: rooms grouped by (duration, room_type); separator inserted when group changes
$sections = [];
foreach ($rooms as $members) {
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
        $sections[$lastIdx]['rooms'][] = $members;
    } else {
        $sections[] = ['key' => $key, 'duration' => $dur, 'room_type' => $rt, 'rooms' => [$members]];
    }
}

// Order sections by duration ascending (fewest days at the top)
usort($sections, function ($a, $b) {
    $da = (int)preg_replace('/[^0-9]/', '', (string)$a['duration']);
    $db = (int)preg_replace('/[^0-9]/', '', (string)$b['duration']);
    return $da <=> $db;
});

// Configurable room color palette (family color; cycles when exhausted)
// Each entry: [light row tint, deeper title-cell color]
$roomColors = [
    ['#dbeafe', '#3b82f6'],  // blue
    ['#ffedd5', '#f59e0b'],  // orange
    ['#fee2e2', '#ef4444'],  // red
    ['#fef9c3', '#eab308'],  // yellow
    ['#ede9fe', '#8b5cf6'],  // purple
    ['#dcfce7', '#22c55e'],  // green
    ['#fce7f3', '#ec4899'],  // pink
    ['#e0f2fe', '#06b6d4'],  // cyan
];

function room_service_label($section, $docLanguage, $L) {
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

function room_title($gender, $L) {
    if ($gender === 'Male') { return $L['mr'] ?? 'MR'; }
    if ($gender === 'Female') { return $L['mrs'] ?? 'MRS'; }
    return '';
}

function room_type_label($rt, $L) {
    $rt = trim((string)$rt);
    if ($rt === '') { return ''; }
    $map = $L['room_type'];
    return $map[mb_strtolower($rt)] ?? $rt;
}

$totalPassengers = count($memberIds);
$totalRooms = count($rooms);
$today = date('Y/m/d H:i');
?>
<!DOCTYPE html>
<html lang="<?php echo ($docLanguage === 'en') ? 'en' : (($docLanguage === 'ps') ? 'ps' : 'fa'); ?>" dir="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <title>ROOM MANAGEMENT - <?php echo htmlspecialchars($agencyName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 1cm 0.9cm;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            font-family: 'Noto Naskh Arabic', 'Arial', sans-serif;
            font-size: 10.5px;
            line-height: 1.5;
            color: #111;
            background: #fff;
            max-width: 21cm;
            margin: 0 auto;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body.ltr {
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        /* ── Header ─────────────────────────────────────────────────── */
        .doc-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        .doc-header h1 {
            font-size: 18px;
            margin: 0;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #111;
        }

        .doc-header .subtitle {
            font-size: 13px;
            font-weight: 600;
            color: #111;
        }

        .doc-header .agency {
            font-size: 12px;
            font-weight: 700;
            color: #111;
        }

        .doc-header .branch {
            font-size: 10px;
            color: #444;
        }

        /* ── Rooming table ──────────────────────────────────────────── */
        .room-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #444;
        }

        .room-table th,
        .room-table td {
            border: 1px solid #555;
            padding: 4px 6px;
            font-size: 10.5px;
        }

        .room-table th {
            background: #eee;
            font-weight: 700;
            text-align: center;
        }

        .room-table td {
            text-align: center;
            vertical-align: middle;
        }

        .room-table .col-room { width: 9%; font-weight: 700; }
        .room-table .col-title { width: 9%; font-weight: 700; }
        .room-table .col-name { width: 40%; text-align: right; padding-right: 8px; }
        .room-table .col-passport { width: 25%; direction: ltr; }
        .room-table .col-days { width: 9%; }
        .room-table .col-otagh { width: 15%; }

        body.ltr .room-table .col-name {
            text-align: left;
            padding-left: 8px;
        }

        .room-table .service-sep td {
            background: #f5f5f5;
            font-weight: 700;
            text-align: center;
            padding: 5px 6px;
        }

        .room-table .family-foot td {
            background: #fafafa;
            border-top: 1.5px solid #333;
            text-align: right;
            padding-right: 10px;
            font-size: 10px;
        }

        body.ltr .room-table .family-foot td {
            text-align: left;
            padding-left: 10px;
        }

        .room-table .empty-row td {
            padding: 18px;
            color: #555;
            text-align: center;
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
            border-radius: 3px;
            cursor: pointer;
            font-size: 12pt;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 9999;
        }

        .print-button:hover {
            background-color: #34495e;
        }

        @media print {
            body { max-width: none; }
            .print-button { display: none !important; }
        }
    </style>
</head>
<body class="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">

    <!-- Floating print button -->
    <button class="print-button no-print" onclick="window.print()">🖨️ <?php echo htmlspecialchars($L['print']); ?></button>

    <!-- ── HEADER ───────────────────────────────────────────────────── -->
    <div class="doc-header">
        <div class="agency"><?php echo htmlspecialchars($agencyName); ?></div>
        <h1><?php echo htmlspecialchars($L['doc_title']); ?></h1>
        <div class="subtitle"><?php echo htmlspecialchars($L['subtitle']); ?></div>
        <div class="branch"><?php echo htmlspecialchars($branch['name'] ?? ''); ?></div>
    </div>

    <!-- ── ROOMING TABLE ────────────────────────────────────────────── -->
    <table class="room-table">
        <thead>
            <tr>
                <th class="col-room"><?php echo htmlspecialchars($L['col_room']); ?></th>
                <th class="col-title"><?php echo htmlspecialchars($L['col_title']); ?></th>
                <th class="col-name"><?php echo htmlspecialchars($L['col_name']); ?></th>
                <th class="col-passport"><?php echo htmlspecialchars($L['col_passport']); ?></th>
                <th class="col-days"><?php echo htmlspecialchars($L['col_days']); ?></th>
                <th class="col-otagh"><?php echo htmlspecialchars($L['col_otagh']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sections)): ?>
            <tr class="empty-row">
                <td colspan="6"><?php echo htmlspecialchars($L['empty']); ?></td>
            </tr>
            <?php else: ?>
            <?php
                $roomNum = 0;
                $colorIdx = 0;
                foreach ($sections as $si => $section):
            ?>
            <tr class="service-sep">
                <td colspan="6"><?php echo htmlspecialchars(room_service_label($section, $docLanguage, $L)); ?></td>
            </tr>
            <?php foreach ($section['rooms'] as $room): ?>
            <?php
                $roomNum++;
                $roomPalette = $roomColors[$colorIdx % count($roomColors)];
                $colorIdx++;
                $roomTint = $roomPalette[0];
                $roomAccent = $roomPalette[1];
                $rowspan = count($room);
                $daysNum = '';
                $roomTypeSet = [];
                foreach ($room as $rm) {
                    if ($daysNum === '' && trim((string)($rm['duration'] ?? '')) !== '') {
                        $daysNum = preg_replace('/[^0-9]/', '', (string)$rm['duration']);
                    }
                    $roomTypeSet[trim((string)($rm['room_type'] ?? ''))] = true;
                }
                $mergeOtagh = count($roomTypeSet) <= 1;
            ?>
            <?php foreach ($room as $ri => $rm): ?>
            <?php
                $fullName = trim((string)($rm['name'] ?? ''));
            ?>
            <tr style="background-color: <?php echo $roomTint; ?>;">
                <?php if ($ri === 0): ?>
                <?php
                    // A room group usually shares one hotel room; when the
                    // stored assignments differ (e.g. a shared gender bucket
                    // spread across two rooms) every row shows its own room.
                    $roomDisplays = [];
                    foreach ($room as $rm0) {
                        $rd = '';
                        if (trim((string)($rm0['room_number'] ?? '')) !== '') {
                            $rd = trim((string)$rm0['room_number']);
                            $floorTxt = trim((string)($rm0['floor'] ?? ''));
                            if ($floorTxt !== '') {
                                $rd .= ' / ' . $floorTxt;
                            }
                        }
                        if ($rd === '') {
                            $rd = (string)$roomNum;
                        }
                        $roomDisplays[] = $rd;
                    }
                    $mergeRoomCell = count(array_unique($roomDisplays)) === 1;
                ?>
                <?php if ($mergeRoomCell): ?>
                <td class="col-room" rowspan="<?php echo $rowspan + 1; ?>"><?php echo htmlspecialchars($roomDisplays[0]); ?></td>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (!$mergeRoomCell): ?>
                <td class="col-room"><?php echo htmlspecialchars($roomDisplays[$ri]); ?></td>
                <?php endif; ?>
                <td class="col-title" style="background-color: <?php echo $roomAccent; ?>; color: #fff;"><?php echo htmlspecialchars(room_title($rm['gender'] ?? '', $L)); ?></td>
                <td class="col-name"><?php echo htmlspecialchars($fullName); ?></td>
                <td class="col-passport"><?php echo htmlspecialchars($rm['passport_number'] ?? ''); ?></td>
                <td class="col-days"><?php echo htmlspecialchars($daysNum); ?></td>
                <?php if ($mergeOtagh): ?>
                <?php if ($ri === 0): ?>
                <td class="col-otagh" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars(room_type_label($section['room_type'], $L)); ?></td>
                <?php endif; ?>
                <?php else: ?>
                <td class="col-otagh"><?php echo htmlspecialchars(room_type_label($rm['room_type'] ?? '', $L)); ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <tr class="family-foot" style="background-color: <?php echo $roomTint; ?>;">
                <td colspan="5"><?php
                    $rtFoot = mb_strtolower(trim((string)($room[0]['room_type'] ?? '')));
                    if ($rtFoot === 'shared' || $rtFoot === 'share') {
                        $gFoot = (string)($room[0]['gender'] ?? '');
                        $grpFoot = $gFoot === 'Male' ? $L['males'] : ($gFoot === 'Female' ? $L['females'] : ($L['unspecified'] ?? ''));
                        echo htmlspecialchars(room_type_label('shared', $L) . ' · ' . $grpFoot);
                    } else {
                        echo htmlspecialchars($L['family_head']); ?>: <b><?php echo htmlspecialchars($room[0]['head_of_family'] ?? ''); ?></b><?php
                    }
                ?></td>
                <td class="col-otagh"></td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ── FOOTER ───────────────────────────────────────────────────── -->
    <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:9.5px; color:#444;">
        <span><?php echo $totalPassengers; ?> <?php echo ($docLanguage === 'en') ? 'passengers' : (($docLanguage === 'ps') ? 'مسافرین' : 'مسافرین'); ?> | <?php echo $totalRooms; ?> <?php echo ($docLanguage === 'en') ? 'rooms' : 'اتاق‌ها'; ?></span>
        <span><?php echo $today; ?></span>
    </div>

<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>
