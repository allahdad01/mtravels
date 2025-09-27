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
            <button onclick="window.print();" class="btn">چاپ ضمانت‌نامه</button>
            <button onclick="window.history.back();" class="btn">برگشت</button>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Company Logo">
            </div>
            <div class="title-container">
                <h1>ضمانت‌نامه رسمی جهت استخدام کارمند</h1>
            </div>
            <div class="date">
                تاریخ: <?php echo date('Y/m/d'); ?>
            </div>
        </div>

        <!-- Letter Body -->
        <div class="letter-body">
            <p>به: محترم مدیریت / مسئول منابع بشری شرکت / اداره <span class="field-line"><?php echo htmlspecialchars($settings['agency_name']); ?></span></p>

            <p>اینجانب <span class="field-line"></span> فرزند <span class="field-line"></span> 
            دارای تذکره شماره <span class="field-line"></span> صادره از ناحیه / ولسوالی <span class="field-line"></span> 
            ولایت <span class="field-line"></span>، ساکن <span class="field-line"></span>، 
            به حیث ضامن قانونی آقای / خانم <span class="field-line"><?php echo htmlspecialchars($user['name']); ?></span> 
            فرزند <span class="field-line"></span> که قرار است به حیث <span class="field-line"></span> 
            در اداره محترم شما استخدام گردد، این سند را رسماً امضا می‌نمایم.</p>

            <p>متعهد می‌شوم که در صورت بروز هرگونه مشکل از قبیل ترک وظیفه بدون اطلاع، وارد آوردن خساره به اداره، سوءاستفاده، اختلاس یا هرگونه تخلف اداری از سوی شخص مذکور، مسئولیت جبران آن را به دوش گرفته و اداره حق دارد تمام خسارات وارده را از من به حیث ضامن مطالبه نماید.</p>

            <p>این ضمانت‌نامه از تاریخ امضاء تا زمان پایان قرارداد رسمی کارمند با اداره و تسویه‌حساب کامل، معتبر بوده و پس از آن فسخ می‌گردد.</p>

            <div style="margin-top: 15px;">
                <h3>مشخصات ضامن</h3>
                <p>
                نام کامل: <span class="field-line"></span> 
                نام پدر: <span class="field-line"></span>
                شماره تذکره: <span class="field-line"></span> 
                محل سکونت: <span class="field-line"></span>
                شماره تماس: <span class="field-line"></span>
                </p>
                <div class="signature-box">امضا و اثر انگشت</div>
            </div>

            <div style="margin-top: 15px;">
                <h3>مشخصات کارمند (شخص تضمین‌شونده)</h3>
                <p>
                نام: <span class="field-line"><?php echo htmlspecialchars($user['name']); ?></span>
                نام پدر: <span class="field-line"></span>
                شماره تذکره: <span class="field-line"></span>
                محل سکونت: <span class="field-line"><?php echo htmlspecialchars($user['address']); ?></span>
                شماره تماس: <span class="field-line"><?php echo htmlspecialchars($user['phone']); ?></span>
                </p>
                <div class="signature-box">امضا و اثر انگشت</div>
            </div>

           

            <div style="margin-top: 15px;">
                <h3>تأیید وکیل گذر / ملک محل</h3>
                <p>اینجانب وکیل گذر/ملک محل ساحه‌ی مربوطه، تایید می‌نمایم که شخص ضامن آقای / خانم <span class="field-line"></span> 
                باشنده اصلی ساحه ما بوده، فردی معتبر، با سابقه نیک و قابل اعتماد است و این ضمانت‌نامه را در حضور بنده امضاء نموده است.</p>
                
                <p>
                نام وکیل گذر: <span class="field-line"></span>
                شماره تماس: <span class="field-line"></span>
                </p>
                <div class="signature-box">امضا و اثر انگشت</div>
                <div class="signature-box">مهر وکیل گذر (در صورت موجودیت)</div>
            </div>

            <div style="margin-top: 15px;">
                <h3>تأیید اداره مربوطه (محل کار)</h3>
                <p>
                نام مسئول: <span class="field-line"></span>
                سمت: <span class="field-line"></span>
                </p>
                <div class="signature-box">امضا و مهر اداره</div>
            </div>
        </div>
    </div>
</body>
</html> 