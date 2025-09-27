<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once '../includes/language_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
}

if (!$user_id) {
    die('د کاروونکي ID ناسم دی');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('کاروونکی ونه موندل شو');
    }

    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    $logoPath = '../uploads/logo.png';
    if (isset($settings['logo']) && !empty($settings['logo']) && file_exists('../uploads/' . $settings['logo'])) {
        $logoPath = '../uploads/' . $settings['logo'];
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("د قرارداد د جوړولو پر مهال ستونزه پېښه شوه. مهرباني وکړئ وروسته بیا هڅه وکړئ.");
}

$rule = filter_input(INPUT_GET, 'rule', FILTER_DEFAULT);
?>

<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>د استخدام تړون - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;700&display=swap');

        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: 'Amiri', serif;
                font-size: 11pt;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Amiri', serif;
            background-color: #f4f4f4;
            color: #2c3e50;
            padding: 10px;
            direction: rtl;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        
        .header {
            text-align: center;
            position: relative;
            margin-bottom: 20px;
            border-bottom: 1px solid #2c3e50;
            padding-bottom: 10px;
        }
        
        .header img {
            position: absolute;
            left: 0;
            top: -5px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-family: 'Tajawal', sans-serif;
            font-size: 18pt;
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .date {
            position: absolute;
            right: 0;
            top: -5px;
            font-size: 11pt;
            color: #7f8c8d;
            font-family: 'Tajawal', sans-serif;
        }
        
        .personal-info {
            background-color: #f9f9f9;
            border-right: 3px solid #3498db;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 3px;
            font-size: 11pt;
        }
        
        .clause {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #fcfcfc;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .clause h3 {
            font-family: 'Tajawal', sans-serif;
            font-size: 14pt;
            color: #2980b9;
            margin-bottom: 5px;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 3px;
            font-weight: 600;
        }
        
        .signature-section {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #2c3e50;
            padding-top: 15px;
        }
        
        .signature-box {
            text-align: center;
            width: 40%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 15px;
            padding-top: 5px;
            font-family: 'Tajawal', sans-serif;
            font-size: 10pt;
        }
        
        .controls {
            margin-bottom: 10px;
            text-align: left;
        }
        
        .btn {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(to right, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 5px;
            font-size: 10pt;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header img {
                position: static;
                display: block;
                margin: 0 auto 10px;
            }
            
            .signature-section {
                flex-direction: column;
            }
            
            .signature-box {
                width: 100%;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="controls no-print">
        <button onclick="window.print();" class="btn">قرارداد چاپ کړئ</button>
        <button onclick="window.history.back();" class="btn">شاته ولاړ شئ</button>
    </div>

    <div class="header">
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="لوگو">
        <h1><?php echo htmlspecialchars($settings['agency_name']); ?><br>د استخدام تړون</h1>
        <div class="date">نېټه: <?php echo date('Y-m-d'); ?></div>
    </div>

   <!-- Agreement Body -->
<div class="agreement-body">
    <div class="personal-info">
        <p>دا د استخدام تړون ("تړون") د لاندې لوریو ترمنځ شوی دی:</p>
        <p><?php echo htmlspecialchars($settings['agency_name']); ?> (له دې وروسته ورته "شرکت" ویل کېږي)</p>
        <p>او</p>
        <p>زه، _________________________، د _________________________ زوی/لور، 
        د _________________________ ولایت، د _________________________ ولسوالۍ اوسېدونکی، 
        دا مهال د _________________________ ولایت، د _________________________ ولسوالۍ اوسېدونکی، 
        د تذکرې/پېژندپاڼې شمېره: _________________________</p>
        <p>په دا ډول زه د لاندې شرایطو سره د <?php echo htmlspecialchars($settings['agency_name']); ?> سره د کار کولو موافقه کوم:</p>
    </div>

    <div class="clause">
        <h3>۱. قانوني او اسلامي اصولو ته پابندي</h3>
        <p>هر کارکوونکی مکلف دی چې د افغانستان اساسي قانون، د دفتر د اصولو، او اسلامي ارزښتونو ته بشپړ درناوی وکړي. سیاسي فعالیتونه باید په کلکه سره ونه‌شي. که سرغړونه وشي، فردي مسؤلیت به د قانوني ارګانونو سره وي، او شرکت به له قانوني مسؤلیت څخه معاف وي.</p>
    </div>

    <div class="clause">
        <h3>۲. د کار ساعتونه او حاضري</h3>
        <p>۲.۱. کارکوونکي باید هره ورځ سهار ۸:۰۰ بجې دفتر ته حاضر شي.<br>
        ۲.۲. ناوخته راتګ به پرته له اجازې د ورځې غیابت حسابېږي.<br>
        ۲.۳. که څوک بې اجازې نه‌راځي، د هغه لپاره به د درې ورځو معاش کم شي.</p>
    </div>

    <div class="clause">
        <h3>۳. مسلکي چلند</h3>
        <p>۳.۱. د شخصي یا غیر رسمي کارونو ترسره کول د رسمي ساعتونو په جریان کې ممنوع دي.<br>
        ۳.۲. سرغړونه به د ۵۰۰ افغانیو جریمه ولري.<br>
        ۳.۳. تکراري سرغړونې به اداري اقداماتو ته اړ کړي.</p>
    </div>

    <div class="clause">
        <h3>۴. محرمیت</h3>
        <p>۴.۱. کارکوونکي باید د دفتر ټول معلومات (عام یا خاص) محرم وساتي.<br>
        ۴.۲. که محرمیت مات شي، کار به سمدستي ختم شي او د دوو میاشتو معاش به کم شي.</p>
    </div>

    <div class="clause">
        <h3>۵. بهرني تماسونه</h3>
        <p>له نورو شرکتونو سره د دفتر، خونه، یا بل ځای کې لیدنه پرته له اداري اجازې ممنوعه ده.</p>
    </div>

    <div class="clause">
        <h3>۶. مسلکي مسؤلیت</h3>
        <p>هر کارکوونکی د خپل مسلکي غفلت مسؤل دی. هر ډول مالي تاوان چې له دې امله رامنځته شي، د هغه مسؤلیت به شخصي وي، شرکت به معاف وي.</p>
    </div>

    <div class="clause">
        <h3>۷. د رخصتۍ پاليسي</h3>
        <p>۷.۱. هر دوه میاشتې کې درې ورځې رخصتي د مشروع دیني عذر سره ورکول کېږي.<br>
        ۷.۲. تر دې زیاته رخصتي به د معاش تناسب سره کمه شي.</p>
    </div>

    <div class="clause">
        <h3>۸. د مهمانانو اصول</h3>
        <p>کارکوونکي باید د هر مهمان د راتګ په اړه مخکې له مخکې د دفتر مدیریت خبر کړي.</p>
    </div>

    <div class="clause">
        <h3>۹. د کار ساعتونه</h3>
        <p>۹.۱. رسمي ساعتونه د سهار له ۸:۰۰ بجو تر ماښام ۶:۰۰ بجو پورې دي.<br>
        ۹.۲. که چیرې د کار بار زیات وي، اضافه ساعتونه به لازم وي.</p>
    </div>

    <div class="clause">
        <h3>۱۰. معاش</h3>
        <p>۱۰.۱. د معاش پیل ۷۰،۰۰۰ افغانۍ دی.<br>
        ۱۰.۲. که یو کارکوونکی د میاشتې ۳۰۰ ډالرو نه زیات ګټه راوړي، له هغې نه زیاتې ګټې څخه به ۱۰٪ بونس ورکړل شي.<br>
        ۱۰.۳. معاش به د هرې میاشتې په لومړۍ نېټه ورکړل شي.<br>
        ۱۰.۴. معاش به نقداً یا د بانک له لارې انتقالېږي.</p>
    </div>

    <div class="clause">
        <h3>۱۱. ضمانتونه</h3>
        <p>کارکوونکي حق نه لري چې د دفتر یا مشتریانو لپاره ضمانت ورکړي.</p>
    </div>

    <div class="clause">
        <h3>۱۲. د اړیکو وسایل</h3>
        <p>۱۲.۱. د شخصي تلیفون کارول د رسمي ساعتونو پر مهال ممنوع دي.<br>
        ۱۲.۲. د دفتر تلیفون به یوازې د اړینو کارونو لپاره کارېږي.</p>
    </div>

    <div class="clause">
        <h3>۱۳. د کار بشپړول</h3>
        <p>۱۳.۱. ورځني کارونه باید هماغه ورځ بشپړ شي.<br>
        ۱۳.۲. نیمګړی کار به د معاش د نه ورکولو سبب شي.</p>
    </div>

    <div class="clause">
        <h3>۱۴. د امنیتي تضمین</h3>
        <p>۱۴.۱. د معاش ۵۰٪ به د امنیتي تضمین په توګه وساتل شي.<br>
        ۱۴.۲. که کال پای کې هیڅ سرغړونه نه وي، تضمین به بیرته ورکړل شي.<br>
        ۱۴.۳. که یو باوري ضامن ورکړل شي، بشپړ معاش به ورکول کېږي.</p>
    </div>

    <div class="clause">
        <h3>۱۵. د فعالیت تضمین</h3>
        <p>کارکوونکی باید د خپل ضعیف فعالیت له امله رامنځته شویو زیانونو لپاره تضمین ورکړي.</p>
    </div>

    <div class="clause">
                <h3>۱۶. نور قوانین</h3>
                <p><?= $rule ?></p>
            </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <p>د کارکوونکي لاسلیک<br>
                _________________________<br>
                نېټه: _________________________</p>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <p>امضای مدیر<br>
                _________________________<br>
                نېټه: _________________________</p>
            </div>
        </div>
    </div>
</div>

</div>
</body>
</html>
