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
    die('شناسهٔ کاربر نامعتبر است');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('کاربر یافت نشد');
    }

    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    $logoPath = __DIR__ . '/../../uploads/logo/' . $settings['logo'];
    if (isset($settings['logo']) && !empty($settings['logo']) && file_exists('../uploads/logo/' . $settings['logo'])) {
        $logoPath = '../uploads/logo/' . $settings['logo'];
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("خطا در دریافت معلومات قرارداد. لطفاً بعداً دوباره تلاش نمایید.");
}

$rule = filter_input(INPUT_GET, 'rule', FILTER_DEFAULT);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قرارداد استخدام - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
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
        <button onclick="window.print();" class="btn">چاپ قرارداد</button>
        <button onclick="window.history.back();" class="btn">برگشت</button>
    </div>

    <div class="header">
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="لوگو">
        <h1><?php echo htmlspecialchars($settings['agency_name']); ?><br>قرارداد استخدام</h1>
        <div class="date">تاریخ: <?php echo date('Y-m-d'); ?></div>
    </div>

   <!-- Agreement Body -->
<div class="agreement-body">
    <div class="personal-info">
        <p>این قرارداد استخدامی ("قرارداد") بین طرفین زیر منعقد می‌گردد:</p>
        <p><?php echo htmlspecialchars($settings['agency_name']); ?> (که از این پس "شرکت" یاد می‌شود)</p>
        <p>و</p>
        <p>من، _________________________، فرزند _________________________، 
        ساکن ولایت _________________________، ولسوالی _________________________، 
        فعلاً ساکن ولایت _________________________، ولسوالی _________________________، 
        شماره تذکره: _________________________</p>
        <p>بدین‌وسیله موافقت می‌نمایم که تحت شرایط ذیل با <?php echo htmlspecialchars($settings['agency_name']); ?> کار نمایم:</p>
    </div>

    <div class="clause">
        <h3>۱. رعایت قوانین و ارزش‌های اسلامی</h3>
        <p>هر کارمند مکلف است که به قانون اساسی افغانستان، اصول دفتر و ارزش‌های اسلامی کاملاً احترام نماید. اشتراک در امور سیاسی به طور کامل ممنوع است. در صورت تخلف، مسئولیت آن بر عهده فرد بوده و شرکت <?php echo $settings['agency_name']; ?> از هرگونه مسئولیت جزایی مبرا می‌باشد.</p>
    </div>

    <div class="clause">
        <h3>۲. ساعات کاری و حضور</h3>
        <p>۲.۱. کارمندان مکلف اند که هر روز رأس ساعت ۸:۰۰ صبح در محل کار حاضر شوند.<br>
        ۲.۲. تأخیر بدون عذر موجه یا اجازه اداری، غیابی کامل روز محسوب می‌گردد.<br>
        ۲.۳. غیبت بدون اجازه منجر به کسر سه روز معاش می‌شود.</p>
    </div>

    <div class="clause">
        <h3>۳. رفتار حرفه‌ای</h3>
        <p>۳.۱. انجام کارهای شخصی یا غیر رسمی در وقت اداری اکیداً ممنوع است.<br>
        ۳.۲. هر نوع تخلف شامل جریمه ۵۰۰ افغانی می‌گردد.<br>
        ۳.۳. تکرار تخلف شامل برخورد اداری خواهد شد.</p>
    </div>

    <div class="clause">
        <h3>۴. رازداری</h3>
        <p>۴.۱. کارمندان مکلف‌اند اطلاعات عمومی و خصوصی دفتر را محرمانه نگه دارند.<br>
        ۴.۲. افشای اطلاعات منجر به ختم فوری وظیفه و کسر دو ماه معاش خواهد شد.</p>
    </div>

    <div class="clause">
        <h3>۵. ارتباطات خارجی</h3>
        <p>برگزاری ملاقات با کارمندان یا مسئولان سایر شرکت‌ها در دفتر، اتاق یا محل دیگر بدون اجازه اداره ممنوع می‌باشد.</p>
    </div>

    <div class="clause">
        <h3>۶. مسئولیت حرفه‌ای</h3>
        <p>هر کارمند مسئول اشتباهات حرفه‌ای خود است. خسارات وارده به عهده شخص بوده و شرکت هیچ‌گونه مسئولیت نخواهد داشت.</p>
    </div>

    <div class="clause">
        <h3>۷. مرخصی</h3>
        <p>۷.۱. کارمند حق دارد هر دو ماه، سه روز مرخصی با عذر مشروع دینی داشته باشد.<br>
        ۷.۲. مرخصی بیش از این مقدار شامل کسر متناسب معاش می‌گردد.</p>
    </div>

    <div class="clause">
        <h3>۸. ورود مهمان</h3>
        <p>کارمند موظف است ورود هر نوع مهمان را از قبل به مدیریت دفتر اطلاع دهد.</p>
    </div>

    <div class="clause">
        <h3>۹. ساعات کاری رسمی</h3>
        <p>۹.۱. ساعت کاری رسمی از ۸:۰۰ صبح تا ۶:۰۰ عصر می‌باشد.<br>
        ۹.۲. در زمان‌های کاری سنگین، اضافه‌کاری الزامی خواهد بود.</p>
    </div>

    <div class="clause">
        <h3>۱۰. معاش</h3>
        <p>۱۰.۱. معاش ابتدایی ۷۰٬۰۰۰ افغانی است.<br>
        ۱۰.۲. در صورتی که کارمند در ماه بیش از ۳۰۰ دالر منفعت داشته باشد، ۱۰٪ از منفعت اضافی به عنوان بونوس دریافت خواهد کرد.<br>
        ۱۰.۳. معاش در اول هر ماه پرداخت می‌گردد.<br>
        ۱۰.۴. پرداخت معاش به صورت نقدی یا از طریق بانک صورت می‌گیرد.</p>
    </div>

    <div class="clause">
        <h3>۱۱. ضمانت‌نامه‌ها</h3>
        <p>کارمندان اجازه ندارند برای مشتریان یا به نمایندگی از دفتر، ضمانت‌نامه صادر نمایند.</p>
    </div>

    <div class="clause">
        <h3>۱۲. وسایل ارتباطی</h3>
        <p>۱۲.۱. استفاده از تلفن شخصی در وقت کاری ممنوع می‌باشد.<br>
        ۱۲.۲. استفاده از تلفن دفتر فقط در صورت نیاز کاری مجاز است.</p>
    </div>

    <div class="clause">
        <h3>۱۳. انجام وظایف</h3>
        <p>۱۳.۱. کارهای روزانه باید در همان روز تکمیل گردد.<br>
        ۱۳.۲. عدم تکمیل کار باعث تعویق معاش آن روز می‌شود.</p>
    </div>

    <div class="clause">
        <h3>۱۴. تضمین امنیتی</h3>
        <p>۱۴.۱. ۵۰٪ معاش به عنوان تضمین امنیتی حفظ می‌شود.<br>
        ۱۴.۲. این تضمین در پایان سال در صورت عدم تخلف بازگردانده می‌شود.<br>
        ۱۴.۳. در صورت ارائه ضامن معتبر، معاش به صورت کامل پرداخت می‌گردد.</p>
    </div>

    <div class="clause">
        <h3>۱۵. تضمین اجرایی</h3>
        <p>کارمند مکلف است خسارات ناشی از عملکرد ضعیف خود را جبران نماید.</p>
    </div>

    <div class="clause">
                <h3>۱۶. قوانین دیگر</h3>
                <p><?= $rule ?></p>
            </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <p>امضای کارمند<br>
                _________________________<br>
                تاریخ: _________________________</p>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <p>امضای مدیر<br>
                _________________________<br>
                تاریخ: _________________________</p>
            </div>
        </div>
    </div>
</div>

</div>
</body>
</html>
