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

if (!$family_id) die('شناسه خانواده نامعتبر است.');

// ============ DATABASE QUERIES ============
try {
    // Settings
    try {
        $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
        $settingStmt->execute([$tenant_id]);
        $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Travel Agency'];
    } catch (Exception $e) {
        error_log("Settings Error: " . $e->getMessage());
        $settings = ['agency_name' => 'Travel Agency'];
    }

    // Branch Data
    try {
        $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
        $branchStmt->execute([$branch_id, $tenant_id]);
        $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Branch Error: " . $e->getMessage());
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

    if (!$members) die('هیچ عضوی برای این خانواده یافت نشد.');
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("خطا در دریافت معلومات.");
}
?>
<!DOCTYPE html>
<html lang="<?= $language ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قرارداد عمره - <?= htmlspecialchars($settings['agency_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&family=Tajawal&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Tajawal', 'Amiri', serif; 
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
            <div style="font-size: 9pt; color: #666;">قرارداد خدمات عمره</div>
        </div>
        <div class="header-date"><?= date('Y-m-d') ?></div>
    </div>

    <!-- MEMBERS TABLE -->
    <table class="table">
        <tr>
            <th>#</th><th>اسم</th><th>ولد</th><th>ولدیت</th><th>رابطه</th><th>پاسپورت</th><th>ولایت</th><th>ولسوالی</th>
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
        <strong>قرارداد عمره</strong>: این قرارداد بین شرکت <?= htmlspecialchars($settings['agency_name']) ?> (شماره مجوز: <?= htmlspecialchars($settings['umrah_id'] ?? '') ?>, تماس: <?= htmlspecialchars($settings['phone'] ?? '') ?>) و <?= htmlspecialchars($members[0]['head_of_family'] ?? '') ?> (تذکره: <?= htmlspecialchars($familyHeadIdNumber ?? '') ?>) منعقد می‌باشد.
    </div>

    <!-- ARTICLES -->
    <div class="section-title">ماده اول: مبنی و ضرورت</div>
    <div class="article">این قرارداد جهت احقاق حقوق معتمر و جلوگیری از ضیاع حقوق میان طرفین عقد می‌گردد.</div>

    <div class="section-title">ماده دوم: موضوع</div>
    <div class="article">موضوع: پذیرش، اخذ ویزه، تکت، و ارایه خدمات عمره.</div>

    <div class="section-title">ماده سوم: تعهدات شرکت</div>
    <div class="article">ارایه خدمات؛ اخذ مدارک؛ صدور تعرفه؛ رزرو تکت؛ ارایه خدمات ارشادی؛ تهیه کارت شناسایی؛ ارایه شماره تماس نمایندگان.</div>

    <div class="section-title">ماده چهارم: تعهدات معتمر</div>
    <div class="article">مطالعه قرارداد؛ تحویل پاسپورت معتبر؛ رعایت مقررات؛ پرداخت هزینه‌ها؛ حضور به‌موقع میدان هوایی.</div>

    <div class="section-title">ماده پنجم: خدمات و هزینه‌ها</div>
    <table class="table" style="font-size: 7.5pt; margin: 2mm 0;">
        <tr>
            <th style="width: 50%">خدمت</th><th>مبلغ ($)</th>
        </tr>
        <tr><td>ویزه عمره</td><td><?= htmlspecialchars($umrahVisaAmount ?? '') ?></td></tr>
        <tr><td>تکت (<?= htmlspecialchars($airlineName ?? '') ?>)</td><td><?= htmlspecialchars($ticketAmount ?? '') ?></td></tr>
        <tr><td>هوتل مکه (<?= $makkahDayNumber ?> شب / <?= $makkahNightNumber ?> روز)</td><td><?= htmlspecialchars($makkahHotelAmount ?? '') ?></td></tr>
        <tr><td>هوتل مدینه (<?= $madinaDayNumber ?> شب / <?= $madinaNightNumber ?> روز)</td><td><?= htmlspecialchars($madinaHotelAmount ?? '') ?></td></tr>
        <tr><td>ترانسپورت (جده - هوتل)</td><td><?= htmlspecialchars($amountAirportHotel ?? '') ?></td></tr>
        <tr><td>ترانسپورت (برگشت)</td><td><?= htmlspecialchars($amountHotelAirport ?? '') ?></td></tr>
        <tr><td>بازدید مشاعر</td><td><?= htmlspecialchars($visitingZiaratsAmount ?? '') ?></td></tr>
        <tr><td>خدمات ارشادی</td><td><?= htmlspecialchars($halaqatDarsiAmount ?? '') ?></td></tr>
        <tr style="background: #e9f0f7; font-weight: bold;"><td>کل خدمات</td><td><?= htmlspecialchars($totalAmount ?? '') ?></td></tr>
        <tr><td>کمیشن شرکت</td><td><?= htmlspecialchars($commissionAmount ?? '') ?></td></tr>
        <tr><td>خدمات طفل</td><td><?= htmlspecialchars($childServicesAmount ?? '') ?></td></tr>
        <tr><td>کمیشن طفل</td><td><?= htmlspecialchars($childCommissionAmount ?? '') ?></td></tr>
    </table>

    <div class="section-title">ماده ششم: تاریخ سفر</div>
    <div class="article" style="margin-bottom: 2mm;">
        مدت: <?= htmlspecialchars($members[0]['duration'] ?? '') ?> روز | 
        رفت: <?= htmlspecialchars($members[0]['flight_date'] ?? '') ?> | 
        برگشت: <?= htmlspecialchars($members[0]['return_date'] ?? '') ?>
    </div>

    <!-- SIGNATURE -->
    <div class="signature">
        <div class="sig-line">معتمر</div>
        <div class="sig-line">شرکت</div>
    </div>
</div>
</body>
</html>
