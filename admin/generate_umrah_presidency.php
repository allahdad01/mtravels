<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once '../includes/language_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

$family_id = $_GET['family_id'];
$language = $_GET['language'] ?? 'fa';
$familyHeadFatherName = $_GET['family_head_father_name'];
$familyHeadIdNumber = $_GET['family_head_id_number'];
$umrahVisaAmount = $_GET['umrah_visa_amount'];
$ticketAmount = $_GET['ticket_amount'];
$airlineName = $_GET['airline_name'];

$makkahDayNumber = $_GET['makkah_day_number'];
$makkahNightNumber = $_GET['makkah_night_number'];
$madinaDayNumber = $_GET['madina_day_number'];
$madinaNightNumber = $_GET['madina_night_number'];
$amountAirportHotel = $_GET['amount_airport_hotel'];
$amountHotelAirport = $_GET['amount_hotel_airport'];
$visitingZiaratsAmount = $_GET['visiting_ziarats_amount'];
$halaqatDarsiAmount = $_GET['halaqat_darsi_amount'];
$totalAmount = $_GET['total_amount'];
$makkahHotelName = $_GET['makkah_hotel_name'];
$makkahHotelDegree = $_GET['makkah_hotel_degree'];
$makkahHotelDistance = $_GET['makkah_hotel_distance'];

$makkahHotelAmount = $_GET['makkah_hotel_amount'];
$madinaHotelName = $_GET['madina_hotel_name'];
$madinaHotelDegree = $_GET['madina_hotel_degree'];
$madinaHotelDistance = $_GET['madina_hotel_distance'];
$madinaHotelAmount = $_GET['madina_hotel_amount'];
$commissionAmount = $_GET['commission_amount'];
$childServicesAmount = $_GET['child_services_amount'];
$childCommissionAmount = $_GET['child_commission_amount'];

if (!$family_id) {
    die('شناسه خانواده نامعتبر است.');
}

try {
    $settingsQuery = "SELECT * FROM settings WHERE tenant_id = ?";
    $settingsStmt = $pdo->prepare($settingsQuery);
    $settingsStmt->execute([$tenant_id]);
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    $logoPath = __DIR__ . '/../../uploads/logo/' . $settings['logo'];
    if (!empty($settings['logo']) && file_exists('../uploads/logo/' . $settings['logo'])) {
        $logoPath = '../uploads/logo/' . $settings['logo'];
    }

    $stmt = $pdo->prepare("SELECT umrah_bookings.*, families.head_of_family, families.contact, families.province, families.district FROM umrah_bookings left join families on umrah_bookings.family_id = families.family_id WHERE umrah_bookings.family_id = ? AND umrah_bookings.tenant_id = ?");
    $stmt->execute([$family_id, $tenant_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$members) {
        die('هیچ عضوی برای این خانواده یافت نشد.');
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("خطا در دریافت معلومات رسید بانکی.");
}
?>

<!DOCTYPE html>
<html lang="<?= $language ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قرارداد عمره - <?= htmlspecialchars($settings['agency_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&family=Tajawal&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', 'Amiri', serif;
            background-color: #f7f7fa;
            color: #222;
            direction: rtl;
            padding: 0 0 0 0;
            margin: 0 0 0 0;
        }
        .container {
            max-width: 950px;
            margin: 0px auto;
            background: #fff;


        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 2.55px;
            position: relative;
            padding-bottom: 2.5px;
        }
        .header img {
            position: absolute;
            left: 0;
            top: 0;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .header h1 {
            font-family: 'Tajawal', 'Amiri', serif;
            font-size: 20pt;
            margin-bottom: 2.5px;
            color: #2d3a4a;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .date {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 11pt;
            color: #888;
        }
        .contract {
            font-size: 10pt;
            line-height: 1;
            color: #222;
        }
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 10px;
            background: #fafbfc;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .table th, .table td {
            border: none;
            padding: 5px 5px;
            text-align: center;
        }
        .table th {
            background: #e9f0f7;
            color: #2d3a4a;
            font-size: 13pt;
            font-weight: 700;
            border-bottom: 2px solid #d1e0ee;
        }
        .table tr:nth-child(even) td {
            background: #f3f6fa;
        }
        .table tr:hover td {
            background: #e0f7fa;
            transition: background 0.2s;
        }
        .table td {
            font-size: 12pt;
            color: #333;
        }
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 13pt;
            color: #2d3a4a;
        }
        @media print {
            body, .container {
                background: #fff !important;
                box-shadow: none !important;
                color: #000;
            }
            .header img {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
            .container {
                border: 1px solid #ccc !important;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="لوگو">
        <h1><?= htmlspecialchars($settings['agency_name']) ?><br>قرارداد خدمات عمره</h1>
        <div class="date">تاریخ: <?= date('Y-m-d') ?></div>
    </div>

<div class="contract" style="white-space: pre-line; line-height: 1.2;">
    قرارداد عمره بین شرکت و معتمر شرکت سياحتي المقدس آدرس : آخر جاده میوند، څمکنی مارکیت، منزل اول، دفتر شماره سه، کابل <?php echo $settings['phone']; ?>
    <table class="table table-bordered" style="width: 100%;">
        <tr>
            <th>شماره</th>
            <th>اسم</th>
            <th>ولد</th>
            <th>ولدیت</th>
            <th>نوعه قرابت</th>
            <th>نمبر پاسپورت</th>
            <th>ولايت</th>  
            <th>ولسوالي</th>
        </tr>
        <?php foreach ($members as $index => $member) { ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= $member['name'] ?></td>
                <td><?= $member['fname'] ?></td>
                <td><?= $member['gfname'] ?></td>
                <td><?= $member['relation'] ?></td>
                <td><?= $member['passport_number'] ?></td>
                <td><?= $member['province'] ?></td>
                <td><?= $member['district'] ?></td>
            </tr>
        <?php } ?>
    </table>    
         این قرارداد بین شرکت (سیاحتی المقدس) واقع در (آخر جاده میوند څمکنی مارکیت) دارنده اجازه‌نامه شماره (<?php echo $settings['umrah_id']; ?>) وزارت ارشاد حج و اوقاف و شماره تماس (<?php echo $settings['phone']; ?>) که در این قرارداد بنام شرکت سیاحتی المقدس یاد می‌گردد و بین محترم (         <?= $members[0]['head_of_family'] ?>) فرزند (         <?= $familyHeadFatherName ?>) دارنده تذکره تابعیت (         <?= $familyHeadIdNumber ?>) و شماره تماس (         <?= $members[0]['contact'] ?>) اصالتاً و به نمایندگی از اعضای فامیل مندرج جدول ذیل که در این قرارداد بنام معتمر یاد می‌شود، با موارد و شرایط ذیل منعقد و واجب‌الاجرا می‌باشد.
        <strong>مبنی و ضرورت قرارداد</strong>  
        ماده اول  
        این قرارداد بر اساس اصول مربوط جهت احقاق حق معتمر، حصول توافق جانبین و جلوگیری از ضیاع حقوق بین طرفین عقد می‌گردد.
        <strong>موضوع قرارداد</strong>  
        ماده دوم  
        موضوع این قرارداد عبارت است از پذیرش، اخذ ویزه، تکت طیاره، اعزام و عرضه خدمات عمره.
        <strong>تعهدات شرکت</strong>  
        ماده سوم  
        ارایه خدمات طبق مندرجات ماده پنجم این قرارداد. پذیرش و ثبت‌نام معتمر طبق مقررات ذی‌ربط. دریافت اسناد و مدارک لازم از معتمر. صدور تعرفه جهت پرداخت مصارف عمره به حساب بانکی شرکت. اخذ ویزه و اطمینان از صحت آن. رزرو تکت طیاره دوطرفه. ارایه خدمات ارشادی و تدویر حلقات درسی. تهیه و توزیع کتابچه و بروشورهای مرتبط. تهیه و توزیع کارت شناسایی. ارایه شماره تماس نمایندگان در افغانستان و عربستان.
        <strong>تعهدات معتمر</strong>  
        ماده چهارم  
        مطالعه و پذیرش مفاد قرارداد. تحویل پاسپورت معتبر با کاپی تذکره و مدارک لازم. رعایت اصول و مقررات وضع شده. پرداخت مصارف عمره به حساب بانکی شرکت. تسلیم رسید بانکی. حضور به‌موقع در میدان هوایی.
        <strong>شرح خدمات و قیمت آن</strong>  
        ماده پنجم  
        اخذ ویزه عمره: مبلغ (<?= $umrahVisaAmount ?>) دالر. تکت طیاره دوطرفه از شرکت هوایی (<?= $airlineName ?>): مبلغ (<?= $ticketAmount ?>) دالر. در صورت اخذ تکت توسط معتمر، شرکت مسئولیتی ندارد. هوتل مکه: (<?= $makkahDayNumber ?>) شب، (<?= $makkahNightNumber ?>) روز، درجه (<?= $makkahHotelDegree ?>)، بنام (<?= $makkahHotelName ?>)، فاصله (<?= $makkahHotelDistance ?>) متر، اتاق (<?= $member['room_type'] ?>) نفره: مبلغ (<?= $makkahHotelAmount ?>) دالر. هوتل مدینه: (<?= $madinaDayNumber ?>) شب، (<?= $madinaNightNumber ?>) روز، درجه (<?= $madinaHotelDegree ?>)، بنام (<?= $madinaHotelName ?>)، فاصله (<?= $madinaHotelDistance ?>) متر، اتاق (<?= $member['room_type'] ?>) نفره: مبلغ (<?= $madinaHotelAmount ?>) دالر. ترانسپورت جده تا هوتل: مبلغ (<?= $amountAirportHotel ?>) دالر. ترانسپورت برگشت: مبلغ (<?= $amountHotelAirport ?>) دالر. بازدید از مشاعر مقدسه: مبلغ (<?= $visitingZiaratsAmount ?>) دالر. خدمات ارشادی و حلقات درسی: مبلغ (<?= $halaqatDarsiAmount ?>) دالر. سایر خدمات: به توافق طرفین. مجموع خدمات: مبلغ (<?= $totalAmount ?>) دالر. کمیشن شرکت: مبلغ (<?= $commissionAmount ?>) دالر. خدمات برای طفل: مبلغ (<?= $childServicesAmount ?>) دالر. کمیشن شرکت برای طفل: مبلغ (<?= $childCommissionAmount ?>) دالر.

        <strong>تاریخ رفت و برگشت و مدت اقامت</strong>  
        ماده ششم  
        خدمات برای مدت (<?= $members[0]['duration'] ?>) روز معتبر است  
        تاریخ رفت: <?= $members[0]['flight_date'] ?>  
        تاریخ برگشت: <?= $members[0]['return_date'] ?>

        <div class="signature">
            <p>امضا و شصت معتمر (.........................)  </p>
            <p>امضا و مهر شرکت (..........................)  </p>
        </div>
    </div>
</div>

</body>
</html>