<?php
// ============ CONFIGURATION & SESSION ============
require_once '../../includes/db.php';
require_once '../../includes/language_helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// ============ REQUEST PARAMETERS ============
// Family & Personal Info
$family_id = $_GET['family_id'];
$language = $_GET['language'] ?? 'fa';
$familyHeadFatherName = $_GET['family_head_father_name'];
$familyHeadIdNumber = $_GET['family_head_id_number'];

// Umrah Package Details
$makkahDayNumber = $_GET['makkah_day_number'];
$makkahNightNumber = $_GET['makkah_night_number'];
$madinaDayNumber = $_GET['madina_day_number'];
$madinaNightNumber = $_GET['madina_night_number'];

// Hotel Information
$makkahHotelName = $_GET['makkah_hotel_name'];
$makkahHotelDegree = $_GET['makkah_hotel_degree'];
$makkahHotelDistance = $_GET['makkah_hotel_distance'];
$makkahHotelAmount = $_GET['makkah_hotel_amount'];
$madinaHotelName = $_GET['madina_hotel_name'];
$madinaHotelDegree = $_GET['madina_hotel_degree'];
$madinaHotelDistance = $_GET['madina_hotel_distance'];
$madinaHotelAmount = $_GET['madina_hotel_amount'];

// Costs
$umrahVisaAmount = $_GET['umrah_visa_amount'];
$ticketAmount = $_GET['ticket_amount'];
$airlineName = $_GET['airline_name'];
$amountAirportHotel = $_GET['amount_airport_hotel'];
$amountHotelAirport = $_GET['amount_hotel_airport'];
$visitingZiaratsAmount = $_GET['visiting_ziarats_amount'];
$halaqatDarsiAmount = $_GET['halaqat_darsi_amount'];
$totalAmount = $_GET['total_amount'];
$commissionAmount = $_GET['commission_amount'];
$childServicesAmount = $_GET['child_services_amount'];
$childCommissionAmount = $_GET['child_commission_amount'];

if (!$family_id) die('کورنۍ شناخت نامعتبر دی.');

// ============ DATABASE QUERIES ============
try {
    // Settings
    try {
        $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
        $settingStmt->execute([$tenant_id]);
        $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Travel Agency'];
    } catch (Exception $e) {
        $settings = ['agency_name' => 'Travel Agency'];
    }

    // Branch Data
    try {
        $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
        $branchStmt->execute([$branch_id, $tenant_id]);
        $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $branch = null;
    }

    // Logo Path
    $logoPath = '../../uploads/logo/' . ($settings['logo'] ?? 'default.png');

    // Family Members
    $stmt = $pdo->prepare("SELECT umrah_bookings.*, families.head_of_family, families.contact, families.province, families.district 
        FROM umrah_bookings 
        LEFT JOIN families ON umrah_bookings.family_id = families.family_id AND families.tenant_id = ? AND families.branch_id = ? 
        WHERE umrah_bookings.family_id = ? AND umrah_bookings.tenant_id = ? AND umrah_bookings.branch_id = ?");
    $stmt->execute([$tenant_id, $branch_id, $family_id, $tenant_id, $branch_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$members) die('د دې کورنۍ لپاره کوم غړی نه پیدا شو.');
} catch (PDOException $e) {
    die("معلومات ترلاسه کولو کې خرابی.");
}
?>
<!DOCTYPE html>
<html lang="<?= $language ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>د عمرې قرارداد - <?= htmlspecialchars($settings['agency_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&family=Tajawal&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Amiri', serif; 
            background: #f7f7fa; 
            color: #222; 
            direction: rtl; 
            line-height: 1.3;
        }
        .container { 
            max-width: 210mm; 
            height: 297mm;
            margin: 0 auto; 
            background: #fff; 
            padding: 8mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            border-bottom: 2px solid #2d3a4a; 
            margin-bottom: 4mm; 
            padding-bottom: 3mm;
            gap: 8px;
        }
        .header img { 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            object-fit: cover;
            flex-shrink: 0;
        }
        .header-title { flex: 1; text-align: center; }
        .header h1 { 
            font-size: 14pt; 
            color: #2d3a4a; 
            font-weight: 700; 
            margin-bottom: 2px;
            line-height: 1.2;
        }
        .header-date { 
            font-size: 9pt; 
            color: #666; 
            min-width: 60px;
            text-align: left;
        }
        .section-title { 
            font-size: 10pt; 
            font-weight: 700; 
            color: #2d3a4a; 
            margin-top: 3mm; 
            margin-bottom: 2mm;
            background: #e9f0f7;
            padding: 2mm 3mm;
            border-right: 3px solid #2d3a4a;
        }
        .article { font-size: 9pt; margin-bottom: 3mm; line-height: 1.4; }
        .article strong { color: #2d3a4a; }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 3mm 0;
            font-size: 8pt;
            background: #fafbfc;
        }
        .table th, .table td { 
            border: 1px solid #d1e0ee; 
            padding: 2mm 2mm; 
            text-align: center;
        }
        .table th { 
            background: #e9f0f7; 
            color: #2d3a4a; 
            font-weight: 700;
        }
        .table tr:nth-child(even) td { background: #f3f6fa; }
        .signature { 
            display: flex; 
            justify-content: space-between;
            margin-top: 4mm;
            font-size: 9pt;
            padding-top: 3mm;
            border-top: 1px solid #ccc;
        }
        .sig-line { 
            flex: 1; 
            text-align: center; 
            border-top: 1px solid #000;
            padding-top: 8mm;
        }
        @media print {
            body { background: #fff !important; }
            .container { 
                max-width: 100%; 
                box-shadow: none !important; 
                margin: 0; 
                padding: 10mm;
                height: 277mm;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- HEADER -->
    <div class="header">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
        <div class="header-title">
            <h1><?= htmlspecialchars($settings['agency_name'] ?? '') ?> - <?= htmlspecialchars($branch['name'] ?? '') ?></h1>
            <div style="font-size: 9pt; color: #666;">د عمرې خدماتو قرارداد</div>
        </div>
        <div class="header-date"><?= date('Y-m-d') ?></div>
    </div>

    <!-- MEMBERS TABLE -->
    <table class="table">
        <tr>
            <th>#</th><th>نوم</th><th>د پلار</th><th>د نیکه</th><th>تعلق</th><th>پاسپورټ</th><th>ولایت</th><th>ولسوالی</th>
        </tr>
        <?php foreach ($members as $i => $m): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($m['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['fname'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['gfname'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['relation'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['passport_number'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['province'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['district'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- CONTRACT INTRO -->
    <div class="article">
        <strong>د عمرې قرارداد</strong>: دا قرارداد د <?= htmlspecialchars($settings['agency_name']) ?> (جواز نمبر: <?= htmlspecialchars($settings['umrah_id'] ?? '') ?>, تماس: <?= htmlspecialchars($settings['phone'] ?? '') ?>) او <?= htmlspecialchars($members[0]['head_of_family'] ?? '') ?> (تذکره: <?= htmlspecialchars($familyHeadIdNumber ?? '') ?>) ترمنځ جوړ شوی دی.
    </div>

    <!-- ARTICLES -->
    <div class="section-title">لومړی ماده: د قرارداد اړتیا</div>
    <div class="article">دا قرارداد د معتمر د حقوق د تامین او د حقونو د ضایع کېدو د مخنیوي لپاره تنظیم شوی دی.</div>

    <div class="section-title">دوهم ماده: موضوع</div>
    <div class="article">موضوع: د عمرې خدمات، ویزه، ټکټ، او نور اړوند خدمات.</div>

    <div class="section-title">دریم ماده: د شرکت ژمنې</div>
    <div class="article">خدمات وړاندې کول؛ اسناد ترلاسه کول؛ تعرفه صادرول؛ ټکټ ریزرو کول؛ ارشادي خدمات؛ پېژند کارتونه برابرول؛ نمایندگانو د تماس شمیره.</div>

    <div class="section-title">څلورم ماده: د معتمر ژمنې</div>
    <div class="article">قرارداد لوستل؛ پاسپورټ سپارل؛ اصولو رعایت؛ پیسې ادا کول؛ هوايي میدان ته حاضري.</div>

    <div class="section-title">پنځم ماده: خدمات او قیمتونه</div>
    <table class="table" style="font-size: 7.5pt; margin: 2mm 0;">
        <tr>
            <th style="width: 50%">خدمت</th><th>رقم ($)</th>
        </tr>
        <tr><td>عمرې ویزه</td><td><?= htmlspecialchars($umrahVisaAmount ?? '') ?></td></tr>
        <tr><td>ټکټ (<?= htmlspecialchars($airlineName ?? '') ?>)</td><td><?= htmlspecialchars($ticketAmount ?? '') ?></td></tr>
        <tr><td>د مکې هوټل (<?= $makkahDayNumber ?> شپې / <?= $makkahNightNumber ?> ورځې)</td><td><?= htmlspecialchars($makkahHotelAmount ?? '') ?></td></tr>
        <tr><td>د مدینې هوټل (<?= $madinaDayNumber ?> شپې / <?= $madinaNightNumber ?> ورځې)</td><td><?= htmlspecialchars($madinaHotelAmount ?? '') ?></td></tr>
        <tr><td>ترانسپورت (جده - هوټل)</td><td><?= htmlspecialchars($amountAirportHotel ?? '') ?></td></tr>
        <tr><td>ترانسپورت (بیرته تګ)</td><td><?= htmlspecialchars($amountHotelAirport ?? '') ?></td></tr>
        <tr><td>مشاعر لیدنه</td><td><?= htmlspecialchars($visitingZiaratsAmount ?? '') ?></td></tr>
        <tr><td>ارشادي خدمات</td><td><?= htmlspecialchars($halaqatDarsiAmount ?? '') ?></td></tr>
        <tr style="background: #e9f0f7; font-weight: bold;"><td>ټول خدمات</td><td><?= htmlspecialchars($totalAmount ?? '') ?></td></tr>
        <tr><td>د شرکت کمیشن</td><td><?= htmlspecialchars($commissionAmount ?? '') ?></td></tr>
        <tr><td>ماشوم خدمات</td><td><?= htmlspecialchars($childServicesAmount ?? '') ?></td></tr>
        <tr><td>د ماشوم کمیشن</td><td><?= htmlspecialchars($childCommissionAmount ?? '') ?></td></tr>
    </table>

    <div class="section-title">شیږم ماده: د تګ راتګ نېټې</div>
    <div class="article" style="margin-bottom: 2mm;">
        موده: <?= htmlspecialchars($members[0]['duration'] ?? '') ?> ورځې | 
        تګ: <?= htmlspecialchars($members[0]['flight_date'] ?? '') ?> | 
        راتګ: <?= htmlspecialchars($members[0]['return_date'] ?? '') ?>
    </div>

    <!-- SIGNATURE -->
    <div class="signature">
        <div class="sig-line">معتمر</div>
        <div class="sig-line">شرکت</div>
    </div>
</div>
</body>
</html>
