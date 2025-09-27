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

    $logoPath = __DIR__ . '../uploads/logo/' . $settings['logo'];
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
            font-family: 'Amiri', serif;
            background-color: #f7f7fa;
            color: #222;
            direction: rtl;
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
            font-family: 'Amiri', serif;
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
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="لوګو">
        <h1><?= htmlspecialchars($settings['agency_name']) ?><br>د عمرې خدماتو قرارداد</h1>
        <div class="date">نیټه: <?= date('Y-m-d') ?></div>
    </div>

<div class="contract" style="white-space: pre-line; line-height: 1.2;">
    د عمرې قرارداد د شرکت او معتمر ترمنځ د <?php echo $settings['agency_name']; ?> سیاحتي شرکت پته: د میوند سړک پای، څمکنی مارکیټ، لمړۍ پوړ، دریم دفتر، کابل تماس: <?php echo $settings['phone']; ?> 
    <table class="table table-bordered" style="width: 100%;">
        <tr>
            <th>شمېره</th>
            <th>نوم</th>
            <th>د پلار نوم</th>
            <th>د نیکه نوم</th>
            <th>د خپلوي ډول</th>
            <th>د پاسپورټ شمېره</th>
            <th>ولایت</th>  
            <th>ولسوالۍ</th>
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
    دا قرارداد د <?php echo $settings['agency_name']; ?> سیاحتي شرکت، چې موقعیت یې د میوند سړک په پای کې، څمکنی مارکیټ کې دی، د ارشاد، حج او اوقافو وزارت د جواز نمبر (<?php echo $settings['umrah_id']; ?>) له مخې، د تماس شمېره (<?php echo $settings['phone']; ?>) لري، چې دلته به د "<?php echo $settings['agency_name']; ?> سیاحتي شرکت" په نوم یادېږي؛ او محترم (<?= $members[0]['head_of_family'] ?>) زوی د (<?= $familyHeadFatherName ?>)، د تابعیت تذکرې نمبر (<?= $familyHeadIdNumber ?>) او د تماس شمېره (<?= $members[0]['contact'] ?>) لرونکی، د خپلې کورنۍ د غړو استازی، چې دلته به د "معتمر" په نوم یادېږي، ترمنځ د لاندې شرایطو پر بنسټ جوړ او نافذ ګڼل کېږي:
    <strong>د قرارداد اړتیا</strong>  
     لومړی ماده
    دا قرارداد د معتمر د حق د تامین، د دواړو لوریو د توافق او د حقونو د ضایع کېدو د مخنیوي لپاره تنظیم شوی دی.
    <strong>د قرارداد موضوع</strong>  
     دوهم ماده
    د قرارداد موضوع د عمرې د خدماتو وړاندې کول، ویزه اخیستل، د الوتکې ټکټ، اعزام او نور اړوند خدمات دي.
    <strong>د شرکت ژمنې</strong>  
    دریم ماده
    د قرارداد د پنځمې مادې له مخې د خدماتو وړاندې کول. د معتمر ثبت او قبولي د اړوندو اصولو مطابق. د ضروري اسنادو ترلاسه کول. د مصارفو تعرفه صادرول د بانک حساب ته د پیسو د تادیې لپاره. د ویزې ترلاسه کول او د هغې د صحت یقیني کول. د دوه طرفه ټکټ ریزرف. ارشادي خدمات او درسي حلقې. کتابچه او بروشرونو برابرول او وېشل. د پېژند کارتونو برابرول او وېشل. د افغانستان او عربستان د استازو د تماس شمیرو وړاندې کول.
    <strong>د معتمر ژمنې</strong>  
    څلورم ماده
    د قرارداد لوستل او منل. د پاسپورټ، تذکرې کاپي او نورو اسنادو سپارل. د اصولو او مقرراتو رعایت. د عمرې مصارفو بانک ته تادیه. د بانکي رسید سپارل. پر وخت هوايي میدان ته حاضرېدل.
    <strong>د خدماتو تفصیل او قیمت</strong>  
    پنځم ماده
    د عمرې ویزه: (<?= $umrahVisaAmount ?>) ډالر. د الوتکې دوه طرفه ټکټ د (<?= $airlineName ?>) شرکت څخه: (<?= $ticketAmount ?>) ډالر. که ټکټ د معتمر لخوا اخیستل شوی وي، شرکت مسؤلیت نه لري. د مکې هوټل: (<?= $makkahDayNumber ?>) شپې، (<?= $makkahNightNumber ?>) ورځې، درجه (<?= $makkahHotelDegree ?>)، نوم (<?= $makkahHotelName ?>)، واټن (<?= $makkahHotelDistance ?>) متر، (<?= $member['room_type'] ?>) نفره خونه: (<?= $makkahHotelAmount ?>) ډالر. د مدینې هوټل: (<?= $madinaDayNumber ?>) شپې، (<?= $madinaNightNumber ?>) ورځې، درجه (<?= $madinaHotelDegree ?>)، نوم (<?= $madinaHotelName ?>)، واټن (<?= $madinaHotelDistance ?>) متر، (<?= $member['room_type'] ?>) نفره خونه: (<?= $madinaHotelAmount ?>) ډالر. ترانسپورت له جدې تر هوټل: (<?= $amountAirportHotel ?>) ډالر. ترانسپورت د بیرته تګ لپاره: (<?= $amountHotelAirport ?>) ډالر. د مشاعر مقدسه لیدنه: (<?= $visitingZiaratsAmount ?>) ډالر. ارشادي خدمات او درسي حلقات: (<?= $halaqatDarsiAmount ?>) ډالر. نور خدمات: د دواړو لوریو په موافقه. د ټولو خدماتو مجموعه: (<?= $totalAmount ?>) ډالر. د شرکت کمېشن: (<?= $commissionAmount ?>) ډالر. د ماشوم لپاره خدمات: (<?= $childServicesAmount ?>) ډالر. د ماشوم لپاره د شرکت کمېشن: (<?= $childCommissionAmount ?>) ډالر.
    <strong>د تګ راتګ نېټې او د پاتې کېدو موده</strong>  
    شیږم ماده
    خدمات د (<?= $members[0]['duration'] ?>) ورځو لپاره اعتبار لري  
    د تګ نېټه: <?= $members[0]['flight_date'] ?>  
    د راتګ نېټه: <?= $members[0]['return_date'] ?>

    <div class="signature">
        <p>د معتمر لاسلیک او ګوته (.........................)</p>
        <p>د شرکت مهر او لاسلیک (.........................)</p>
    </div>
</div>
</div>


</body>
</html>