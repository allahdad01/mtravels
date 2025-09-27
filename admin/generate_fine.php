<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once '../includes/csp_headers.php';
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

    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    $logoPath = __DIR__ . '../uploads/logo/' . $settings['logo'];
    if (isset($settings['logo']) && !empty($settings['logo']) && file_exists('../uploads/logo/' . $settings['logo'])) {
        $logoPath = '../uploads/logo/' . $settings['logo'];
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("خطا در دریافت معلومات قرارداد. لطفاً بعداً دوباره تلاش نمایید.");
}
$takhaluf = filter_input(INPUT_GET, 'takhaluf', FILTER_DEFAULT);
$job_title = filter_input(INPUT_GET, 'job_title', FILTER_DEFAULT);
$fine_amount = filter_input(INPUT_GET, 'fine_amount', FILTER_DEFAULT);
$currency = filter_input(INPUT_GET, 'currency', FILTER_DEFAULT);

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>جریمه لیک - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
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
        <button onclick="window.print();" class="btn">چاپ نامه جریمه</button>
        <button onclick="window.history.back();" class="btn">برگشت</button>
    </div>

    <div class="header">
    <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="لوگو">
    <h1>شرکت <?php echo $settings['agency_name']; ?><br>نامه جریمه</h1>
    <div class="date">تاریخ: <?php echo date('Y-m-d'); ?></div>
</div>

<!-- Fine Letter Body -->
<div class="agreement-body">
    <div class="personal-info">
        به: <?php echo $user['name']; ?><br>
        وظیفه: <?php echo $job_title; ?><br>
        اداره/شرکت: <?php echo $settings['agency_name']; ?>
    </div>

    <div class="clause">
        <h3>جریمه به‌خاطر تخلف وظیفوی</h3>
        <p>
            محترمانه به اطلاع شما رسانیده می‌شود که بر اساس بررسی‌های مدیریت، شما مرتکب تخلف وظیفوی ذیل گردیده‌اید:<br><br>
            <?php echo $takhaluf; ?><br><br>

            با آن‌که قبلاً طی نامه توصیه خط به شما تذکر داده شده بود، اما اصلاح لازم صورت نگرفت. بناً مطابق پالیسی انضباطی اداره، شما به پرداخت مبلغ مشخصی جریمه محکوم می‌گردید:<br><br>

            <strong>مقدار جریمه:</strong> <?php echo $fine_amount; ?> <?php echo $currency; ?><br><br>

            این مبلغ از معاش آینده شما کسر خواهد شد. امیدواریم که در آینده از چنین تخطی‌ها خودداری نموده، وظایف خویش را به‌گونه‌ی مسئولانه و بر اساس مقررات اداره انجام دهید.

            این اقدام در راستای حفظ نظم و ارتقاء مسئولیت‌پذیری در محیط کاری اتخاذ گردیده است.
        </p>
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
                رئیس شرکت <?php echo $settings['agency_name']; ?><br>
                تاریخ: _________________________</p>
            </div>
        </div>
    </div>
</div>

</div>
</body>
</html>
