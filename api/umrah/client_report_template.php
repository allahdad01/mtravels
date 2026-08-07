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
        'families' => 'خانواده',
        'due' => 'مانده',
        'days' => 'روز',
        'print' => 'چاپ',
        'empty' => 'هیچ معتمری در این پرواز ثبت نشده است',
        'hijri_suffix' => 'هـ',
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
        'families' => 'کورنۍ',
        'due' => 'باقي',
        'days' => 'ورځې',
        'print' => 'چاپ',
        'empty' => 'په دې الوتکه کې هېڅ معتمر ثبت شوی نه دی',
        'hijri_suffix' => 'هـ',
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
        'families' => 'families',
        'due' => 'Due',
        'days' => 'Days',
        'print' => 'Print',
        'empty' => 'No passengers registered on this flight',
        'hijri_suffix' => 'AH',
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

// Rooms: family = room, members in ticket order
$families = [];
foreach ($members as $m) {
    $fid = (int)($m['family_id'] ?? 0);
    $families[$fid][] = $m;
}

function client_room_type_label($rt, $L) {
    $rt = trim((string)$rt);
    if ($rt === '') { return '—'; }
    $map = $L['room_type'];
    return $map[mb_strtolower($rt)] ?? $rt;
}

function client_title($gender, $L) {
    if ($gender === 'Male') { return $L['mr'] ?? 'MR'; }
    if ($gender === 'Female') { return $L['mrs'] ?? 'MRS'; }
    return '';
}

// Add MR/MRS keys and family-head label
foreach ($langLabels as $ll => $arr) {
    $langLabels[$ll]['mr'] = $ll === 'dari' ? 'آقا' : ($ll === 'ps' ? 'ښاغلی' : 'MR');
    $langLabels[$ll]['mrs'] = $ll === 'dari' ? 'خانم' : ($ll === 'ps' ? 'مېرمن' : 'MRS');
    $langLabels[$ll]['family_head'] = $ll === 'dari' ? 'سرپرست خانواده' : ($ll === 'ps' ? 'د کورنۍ مشر' : 'Family Head');
}
$L = $langLabels[$docLanguage];

function client_duration_label($dur, $L) {
    $dur = trim((string)$dur);
    if ($dur === '') { return '—'; }
    $num = preg_replace('/[^0-9]/', '', $dur);
    return ($num !== '' ? $num : $dur) . ' ' . $L['days'];
}

// Group families by client (order of first appearance)
$clientGroups = []; // client_name => [family_id => members]
foreach ($families as $fid => $famMembers) {
    $cname = trim((string)($famMembers[0]['client_name'] ?? ''));
    if ($cname === '') { $cname = '—'; }
    if (!isset($clientGroups[$cname])) {
        $clientGroups[$cname] = [];
    }
    $clientGroups[$cname][$fid] = $famMembers;
}

// Totals
$totalMembers = count($members);
$totalFamilies = count($families);
$totalClients = count($clientGroups);
$grandTotals = []; // by currency
$today = date('Y/m/d H:i');

// Configurable family color palette (family block tint; cycles when exhausted)
$familyColors = [
    ['#dbeafe', '#3b82f6'],  // blue
    ['#ffedd5', '#f59e0b'],  // orange
    ['#fee2e2', '#ef4444'],  // red
    ['#fef9c3', '#eab308'],  // yellow
    ['#ede9fe', '#8b5cf6'],  // purple
    ['#dcfce7', '#22c55e'],  // green
    ['#fce7f3', '#ec4899'],  // pink
    ['#e0f2fe', '#06b6d4'],  // cyan
];
?>
<!DOCTYPE html>
<html lang="<?php echo ($docLanguage === 'en') ? 'en' : (($docLanguage === 'ps') ? 'ps' : 'fa'); ?>" dir="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($L['doc_title']); ?> - <?php echo htmlspecialchars($agencyName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 1cm 0.9cm;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            font-family: 'Noto Naskh Arabic', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #111;
            background: #fff;
            max-width: 29.7cm;
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
            font-size: 16px;
            margin: 0;
            color: #111;
        }

        .doc-header .subtitle {
            font-size: 12px;
            font-weight: 600;
            color: #111;
        }

        .doc-header .agency {
            font-size: 12px;
            font-weight: 700;
            color: #111;
        }

        .doc-header .branch {
            font-size: 9.5px;
            color: #444;
        }

        /* ── Client table ───────────────────────────────────────────── */
        .client-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #444;
        }

        .client-table th,
        .client-table td {
            border: 1px solid #555;
            padding: 4px 5px;
            font-size: 9.5px;
        }

        .client-table th {
            background: #eee;
            font-weight: 700;
            text-align: center;
        }

        .client-table td {
            text-align: center;
            vertical-align: middle;
        }

        .client-table .col-s { width: 4%; }
        .client-table .col-no { width: 4%; }
        .client-table .col-title { width: 6%; font-weight: 700; }
        .client-table .col-name { width: 17%; text-align: right; padding-right: 8px; }
        .client-table .col-passport { width: 11%; direction: ltr; }
        .client-table .col-duration { width: 7%; }
        .client-table .col-room { width: 9%; }
        .client-table .col-client { width: 11%; text-align: right; padding-right: 8px; }
        .client-table .col-price { width: 9%; direction: ltr; }
        .client-table .col-bank { width: 9%; direction: ltr; }
        .client-table .col-remarks { width: 13%; text-align: right; padding-right: 8px; }

        body.ltr .client-table .col-name,
        body.ltr .client-table .col-client,
        body.ltr .client-table .col-remarks {
            text-align: left;
            padding-left: 8px;
            padding-right: 0;
        }

        .client-table .client-total td {
            background: #f3f4f6;
            font-weight: 700;
            border-top: 1.5px solid #333;
            border-bottom: 1px solid #999;
            text-align: right;
            padding-right: 10px;
            font-size: 9.5px;
        }

        body.ltr .client-table .client-total td {
            text-align: left;
            padding-left: 10px;
        }

        .client-table .client-total .foot-nums {
            direction: ltr;
            text-align: center;
        }

        .client-table .grand-total td {
            background: #e5e7eb;
            font-weight: 700;
            border-top: 2px solid #333;
            text-align: right;
            padding-right: 10px;
            font-size: 10px;
        }

        body.ltr .client-table .grand-total td {
            text-align: left;
            padding-left: 10px;
        }

        .client-table .grand-total .foot-nums {
            direction: ltr;
            text-align: center;
        }

        .client-table .foot-nums.due {
            color: #b91c1c;
        }

        .client-table .empty-row td {
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
            .client-table th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .client-table .grand-total td { background: #e5e7eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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

    <!-- ── CLIENT TABLE ─────────────────────────────────────────────── -->
    <table class="client-table">
        <thead>
            <tr>
                <th class="col-s"><?php echo htmlspecialchars($L['col_s']); ?></th>
                <th class="col-no"><?php echo htmlspecialchars($L['col_no']); ?></th>
                <th class="col-title"><?php echo htmlspecialchars($L['col_title']); ?></th>
                <th class="col-name"><?php echo htmlspecialchars($L['col_name']); ?></th>
                <th class="col-passport"><?php echo htmlspecialchars($L['col_passport']); ?></th>
                <th class="col-duration"><?php echo htmlspecialchars($L['col_duration']); ?></th>
                <th class="col-room"><?php echo htmlspecialchars($L['col_room']); ?></th>
                <th class="col-client"><?php echo htmlspecialchars($L['col_client']); ?></th>
                <th class="col-price"><?php echo htmlspecialchars($L['col_price']); ?></th>
                <th class="col-bank"><?php echo htmlspecialchars($L['col_bank']); ?></th>
                <th class="col-remarks"><?php echo htmlspecialchars($L['col_remarks']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($families)): ?>
            <tr class="empty-row">
                <td colspan="11"><?php echo htmlspecialchars($L['empty']); ?></td>
            </tr>
            <?php else: ?>
            <?php
                $globalS = 0;
                $colorIdx = 0;
                foreach ($clientGroups as $clientName => $famGroups):
                    $clientTotals = [];
                    $tint = $familyColors[$colorIdx % count($familyColors)][0];
                    $accent = $familyColors[$colorIdx % count($familyColors)][1];
                    $colorIdx++;
                    $clientNo = 0;
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
                    $famKeys = array_keys($famGroups);
                    foreach ($famKeys as $fid):
                        $famMembers = $famGroups[$fid];
            ?>
            <?php foreach ($famMembers as $fm):
                $globalS++;
                $clientNo++;
            ?>
            <tr style="background-color: <?php echo $tint; ?>;">
                <td class="col-s"><?php echo $globalS; ?></td>
                <td class="col-no"><?php echo $clientNo; ?></td>
                <td class="col-title" style="background-color: <?php echo $accent; ?>; color: #fff;"><?php echo htmlspecialchars(client_title($fm['gender'] ?? '', $L)); ?></td>
                <td class="col-name"><?php echo htmlspecialchars($fm['name'] ?? ''); ?></td>
                <td class="col-passport"><?php echo htmlspecialchars($fm['passport_number'] ?? ''); ?></td>
                <td class="col-duration"><?php echo htmlspecialchars(client_duration_label($fm['duration'] ?? '', $L)); ?></td>
                <td class="col-room"><?php echo htmlspecialchars(client_room_type_label($fm['room_type'] ?? '', $L)); ?></td>
                <td class="col-client"><?php echo htmlspecialchars($fm['client_name'] ?? ''); ?></td>
                <td class="col-price"><?php echo number_format((float)($fm['sold_price'] ?? 0), 2); ?> <?php echo htmlspecialchars(strtoupper((string)($fm['currency'] ?: 'USD'))); ?></td>
                <td class="col-bank"><?php echo number_format((float)($fm['received_bank_payment'] ?? 0), 2); ?></td>
                <td class="col-remarks"><?php echo htmlspecialchars($fm['remarks'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <tr class="client-total">
                <td colspan="8"><b><?php echo htmlspecialchars($L['client']); ?>:</b> <?php echo htmlspecialchars($clientName); ?></td>
                <?php
                    $cPriceParts = [];
                    $cBankParts = [];
                    $cDueParts = [];
                    foreach ($clientTotals as $cur => $t) {
                        $cPriceParts[] = number_format($t['price'], 2) . ' ' . $cur;
                        $cBankParts[] = number_format($t['bank'], 2) . ' ' . $cur;
                        $cDueParts[] = number_format($t['price'] - $t['bank'], 2) . ' ' . $cur;
                    }
                ?>
                <td class="foot-nums"><?php echo implode('<br>', array_map('htmlspecialchars', $cPriceParts)); ?></td>
                <td class="foot-nums"><?php echo implode('<br>', array_map('htmlspecialchars', $cBankParts)); ?></td>
                <td class="foot-nums due"><b><?php echo htmlspecialchars($L['due']); ?>:</b><br><?php echo implode('<br>', array_map('htmlspecialchars', $cDueParts)); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="8"><?php echo htmlspecialchars($L['grand_total']); ?> (<?php echo $totalMembers; ?> <?php echo htmlspecialchars($L['members']); ?> | <?php echo $totalClients; ?> <?php echo htmlspecialchars($L['clients']); ?>)</td>
                <?php
                    $gPriceParts = [];
                    $gBankParts = [];
                    $gDueParts = [];
                    foreach ($grandTotals as $cur => $t) {
                        $gPriceParts[] = number_format($t['price'], 2) . ' ' . $cur;
                        $gBankParts[] = number_format($t['bank'], 2) . ' ' . $cur;
                        $gDueParts[] = number_format($t['price'] - $t['bank'], 2) . ' ' . $cur;
                    }
                ?>
                <td class="foot-nums"><?php echo implode('<br>', array_map('htmlspecialchars', $gPriceParts)); ?></td>
                <td class="foot-nums"><?php echo implode('<br>', array_map('htmlspecialchars', $gBankParts)); ?></td>
                <td class="foot-nums due"><b><?php echo htmlspecialchars($L['due']); ?>:</b><br><?php echo implode('<br>', array_map('htmlspecialchars', $gDueParts)); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ── FOOTER ───────────────────────────────────────────────────── -->
    <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:9.5px; color:#444;">
        <span><?php echo $totalMembers; ?> <?php echo htmlspecialchars($L['members']); ?></span>
        <span><?php echo $today; ?></span>
    </div>

<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>
