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

$job_title_ikhtar = $_GET['job_title'];

?>

<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>رسمی اخطاریه - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
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
        <button onclick="window.print();" class="btn">چاپ رسمی اخطاریه</button>
        <button onclick="window.history.back();" class="btn">برگشت</button>
    </div>

    <div class="header">
    <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="لوګو">
    <h1>د <?php echo $settings['agency_name']; ?> شرکت<br>رسمي اخطاریه</h1>
    <div class="date">نېټه: <?php echo date('Y-m-d'); ?></div>
</div>

<!-- Agreement Body -->
<div class="agreement-body">
    <div class="personal-info">
        ته: <?php echo $user['name']; ?><br>
        دنده: <?php echo $job_title_ikhtar; ?><br>
        اداره/شرکت: <?php echo $settings['agency_name']; ?>
    </div>

    <div class="clause">
        <h3>د دندې له اصولو د سرغړونې له امله رسمي اخطار</h3>
        <p>
            درناوی، د هغه توسعې‌لیک په تعقیب چې د وظیفوي سرغړونو له امله تاسو ته صادر شوی و، له بده‌مرغه بیا هم لیدل کېږي چې دغه سرغړونه تکرار شوې یا لازمي اصلاحات نه دي شوي.
            <br><br>
            له همدې امله، دا یو رسمي اخطار دی چې که چیرې سرغړونه بیا تکرار شي یا د دندې اصول مراعات نه شي، اداره دا حق لري چې د نافذه مقرراتو او د دندې د اصولو مطابق انضباطي اقدامات ترسره کړي، چې پکې د قرارداد فسخ شاملېدای شي.
            <br><br>
            هیله ده چې دا وروستی اخطار وي او تاسو به خپل وظیفوي چلند کې لازم اصلاحات راولئ.
        </p>
    </div>
</div>




    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <p>د کارمند امضا<br>
                _________________________<br>
                نېټه: _________________________</p>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <p>د <?php echo $settings['agency_name']; ?> شرکت رئیس<br>
               <br>
                نېټه: _________________________</p>
            </div>
        </div>
    </div>
</div>

</div>
</body>
</html>
