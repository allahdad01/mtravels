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
$memberMap = [];
if (!empty($memberIds)) {
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $memberStmt = $pdo->prepare("
        SELECT booking_id, name, fname, dob, gender, passport_number, status
        FROM umrah_bookings
        WHERE booking_id IN ({$placeholders}) AND tenant_id = ? AND branch_id = ?
    ");
    $memberStmt->execute(array_merge($memberIds, [$tenant_id, $branch_id]));
    foreach ($memberStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $memberMap[(int)$m['booking_id']] = $m;
    }
}
$passengers = [];
foreach ($memberIds as $id) {
    if (isset($memberMap[(int)$id])) {
        $passengers[] = $memberMap[(int)$id];
    }
}

// Auto-translate names into the document language (MyMemory - free)
require_once __DIR__ . '/../../includes/translate_helper.php';
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
translate_name_fields($passengers, $docLanguage, ['name', 'fname']);
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

// Flight info
$airlineName = translate_name($ticket['airline_name'] ?? '', $docLanguage);
$departureTime = $ticket['departure_time'] ?? '';
if (!empty($departureTime) && strpos($departureTime, ':') !== false) {
    $departureTime = substr($departureTime, 0, 5);
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

// Localize duration (stored as e.g. "27 Days")
$durationRaw = trim((string)($ticket['duration'] ?? ''));
$durationLocalized = '—';
$departureCity = translate_place($ticket['departure_city'] ?? '', $docLanguage);
if ($durationRaw !== '') {
    $durationNum = preg_replace('/\s*days$/i', '', $durationRaw);
    $durationLocalized = $durationNum . ' ' . $L['days'];
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

    <!-- ── META BAR ─────────────────────────────────────────────────── -->
    <div class="meta-bar">
        <span class="doc-title"><?php echo htmlspecialchars($L['doc_title']); ?></span>
        <span class="doc-subtitle"><?php echo htmlspecialchars($agencyName); ?> <?php echo htmlspecialchars($L['includes_flight']); ?></span>
        <span class="doc-date"><?php echo htmlspecialchars($L['day']); ?>: <?php echo manifest_day($ticket['flight_date'] ?? '', $L); ?> | <?php echo htmlspecialchars($L['hijri']); ?>: <?php echo manifest_hijri($ticket['flight_date'] ?? '', $L); ?> | <?php echo htmlspecialchars($L['gregorian']); ?>: <?php echo htmlspecialchars($ticket['flight_date'] ?? '—'); ?></span>
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
                <?php if (empty($passengers)): ?>
                <tr>
                    <td colspan="16" style="padding: 20px; color: var(--muted);"><?= $L['empty'] ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($passengers as $i => $p): ?>
                <tr>
                    <td class="col-no">&nbsp;</td>
                    <td class="col-reg"><?php echo $i + 1; ?></td>
                    <td class="col-name"><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                    <td class="col-fname"><?php echo htmlspecialchars($p['fname'] ?? ''); ?></td>
                    <td class="col-passport"><?php echo htmlspecialchars($p['passport_number'] ?? ''); ?></td>
                    <td class="col-age"><?php echo manifest_age($p['dob'] ?? ''); ?></td>
                    <td class="col-gender"><?php echo manifest_gender($p['gender'] ?? '', $L); ?></td>
                    <td class="col-airline"><?php echo htmlspecialchars($airlineName); ?><?php if ($departureTime !== ''): ?> — <?php echo htmlspecialchars($departureTime); ?><?php endif; ?></td>
                    <td class="col-date"><?php echo manifest_hijri($ticket['flight_date'] ?? '', $L); ?></td>
                    <td class="col-day"><?php echo manifest_day($ticket['flight_date'] ?? '', $L); ?></td>
                    <td class="col-transfer"><?php echo htmlspecialchars($agencyName); ?></td>
                    <td class="col-duration"><?php echo htmlspecialchars($durationLocalized); ?></td>
                    <td class="col-return"><?php echo manifest_hijri($ticket['return_date'] ?? '', $L); ?></td>
                    <td class="col-place"><?php echo htmlspecialchars($departureCity); ?></td>
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
        <span class="summary-total"><?= $L['total_passengers'] ?>: <?php echo $totalPassengers; ?> <?= ($docLanguage === 'ps') ? 'تنه' : (($docLanguage === 'en') ? 'people' : 'نفر'); ?></span>
        <span><?= $L['male'] ?>: <?php echo $maleCount; ?></span>
        <span><?= $L['female'] ?>: <?php echo $femaleCount; ?></span>
        <span><?= $L['child'] ?> (2-11 <?= $L['years'] ?>): <?php echo $childCount; ?></span>
        <span><?= $L['infant'] ?> (0-2 <?= $L['years'] ?>): <?php echo $infantCount; ?></span>
    </div>

    <!-- Bottom gold stripe -->
    <div class="bottom-stripe"></div>

<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>
