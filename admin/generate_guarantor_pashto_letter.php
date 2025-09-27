<?php
// Include necessary files
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once '../includes/csp_headers.php';
require_once '../includes/language_helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

// Validate and sanitize inputs - check both POST and GET
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
}

if (!$user_id) {
    die('Invalid user ID provided');
}

try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('User not found');
    }

    // Fetch company settings
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    // Check if the logo exists and set the path
    $logoPath = __DIR__ . '../uploads/logo/' . $settings['logo'];
    if (isset($settings['logo']) && !empty($settings['logo']) && file_exists('../uploads/logo/' . $settings['logo'])) {
        $logoPath = '../uploads/logo/' . $settings['logo'];
    }


} catch (PDOException $e) {
    error_log("Database error in generate_guarantor_letter.php: " . $e->getMessage());
    die("An error occurred while generating the guarantor letter. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ضمانت‌نامه رسمی - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap');
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: 'Amiri', serif;
                font-size: 14pt;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Amiri', serif;
            line-height: 2;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
            text-align: right;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .logo-container {
            position: absolute;
            right: 0;
            top: 0;
        }
        
        .logo-container img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .title-container {
            margin: 0 auto;
            max-width: 80%;
        }
        
        .title-container h1 {
            font-size: 18pt;
            color: #333;
            margin: 0 0 10px 0;
            line-height: 1.5;
        }
        
        .date {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 12pt;
            color: #333;
        }
        
        .letter-body {
            padding: 20px;
            line-height: 2;
        }
        
        .signature-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            margin-top: 30px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        
        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #eee;
            border-radius: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .field-line {
            border-bottom: 1px solid #999;
            display: inline-block;
            min-width: 200px;
            margin: 0 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Print Controls -->
    <div class="controls no-print">
        <button onclick="window.print();" class="btn">د ضمانت‌لیک چاپ</button>
        <button onclick="window.history.back();" class="btn">شاته تګ</button>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="logo-container">
            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Company Logo">
        </div>
        <div class="title-container">
            <h1>د کارکوونکي لپاره رسمي ضمانت‌لیک</h1>
        </div>
        <div class="date">
            نېټه: <?php echo date('Y/m/d'); ?>
        </div>
    </div>

    <!-- Letter Body -->
    <div class="letter-body">
        <p>درنو د شرکت / ادارې د منابع بشري مسئولینو ته: <span class="field-line"><?php echo htmlspecialchars($settings['agency_name']); ?></span></p>

        <p>زه، <span class="field-line"></span> د <span class="field-line"></span> زوی / لور،
        د تذکرې شمېره <span class="field-line"></span>، صادره له ناحیې / ولسوالۍ <span class="field-line"></span> 
        د ولایت <span class="field-line"></span> اوسېدونکی، د استوګنې ځای <span class="field-line"></span>، 
        د ښاغلي / آغلې <span class="field-line"><?php echo htmlspecialchars($user['name']); ?></span> 
        د ضمانت په توګه، چې ټاکل شوې ده د محترمې ادارې سره د <span class="field-line"></span> په توګه وګمارل شي، 
        دا سند رسماً لاسلیک کوم.</p>

        <p>زه ژمنه کوم که چیرې یاد شخص پرته له اجازې دندې ته حاضري نشي، یا ادارې ته تاوان ورسوي، 
        یا اختلاس، ناوړه استفاده یا هر ډول اداري سرغړونه وکړي، نو زه د ضامن په توګه مسوول یم او اداره حق لري چې 
        ټول تاوانونه له ما واخلي.</p>

        <p>دا ضمانت‌لیک د لاسلیک له نېټې څخه تر هغې مودې پورې اعتبار لري چې کارکوونکی له ادارې سره خپله رسمي دنده پای ته ورسوي او تصفیه وکړي، له هغې وروسته دا سند فسخ ګڼل کېږي.</p>

        <div style="margin-top: 15px;">
            <h3>د ضامن مشخصات</h3>
            <p>
            بشپړ نوم: <span class="field-line"></span> 
            د پلار نوم: <span class="field-line"></span>
            د تذکرې شمېره: <span class="field-line"></span> 
            د استوګنې ځای: <span class="field-line"></span>
            د اړیکې شمېره: <span class="field-line"></span>
            </p>
            <div class="signature-box">لاسلیک او د ګوتې نښه</div>
        </div>

        <div style="margin-top: 15px;">
            <h3>د کارکوونکي مشخصات (د ضمانت لاندې کس)</h3>
            <p>
            نوم: <span class="field-line"><?php echo htmlspecialchars($user['name']); ?></span>
            د پلار نوم: <span class="field-line"></span>
            د تذکرې شمېره: <span class="field-line"></span>
            د استوګنې ځای: <span class="field-line"><?php echo htmlspecialchars($user['address']); ?></span>
            د اړیکې شمېره: <span class="field-line"><?php echo htmlspecialchars($user['phone']); ?></span>
            </p>
            <div class="signature-box">لاسلیک او د ګوتې نښه</div>
        </div>

        <div style="margin-top: 15px;">
            <h3>د محل ملک / وکیل تصدیق</h3>
            <p>زه د ساحې وکیل / ملک، پدې تصدیق کوم چې ضامن ښاغلی / آغلې <span class="field-line"></span> 
            د دې ساحې اصلي اوسېدونکی دی، ښه سابقه لري، او د باور وړ کس دی، او دا سند زما په حضور کې لاسلیک شوی دی.</p>

            <p>
            د وکیل / ملک نوم: <span class="field-line"></span>
            د اړیکې شمېره: <span class="field-line"></span>
            </p>
            <div class="signature-box">لاسلیک او د ګوتې نښه</div>
            <div class="signature-box">د وکیل / ملک مهر (که موجود وي)</div>
        </div>

        <div style="margin-top: 15px;">
            <h3>د اړوندې ادارې تصدیق (د کار ځای)</h3>
            <p>
            د مسؤل نوم: <span class="field-line"></span>
            دنده / سمت: <span class="field-line"></span>
            </p>
            <div class="signature-box">لاسلیک او د ادارې مهر</div>
        </div>
    </div>
</div>

</body>
</html> 