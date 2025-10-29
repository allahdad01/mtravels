<?php
require_once '../../includes/conn.php';
session_start();

if (!isset($_GET['pilgrim_ids']) || empty($_GET['pilgrim_ids'])) {
    echo "No pilgrims selected";
    exit;
}
$tenant_id = $_SESSION['tenant_id'];

// Fetch settings
$stmt = $conn->prepare("SELECT * FROM settings WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();

$pilgrim_ids = explode(',', $_GET['pilgrim_ids']);
$pilgrims_info = [];

foreach ($pilgrim_ids as $pilgrim_id) {
    $stmt = $conn->prepare("SELECT name, passport_number, duration FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $pilgrim_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $pilgrims_info[] = $row;
    }
}

$guarantor_name = isset($_GET['guarantor_name']) ? $_GET['guarantor_name'] : '______________________';
$date = date('Y/m/d');
$duration = '15';
if (!empty($pilgrims_info) && isset($pilgrims_info[0]['duration'])) {
    // Extract numeric value from duration string (e.g., "15 Days" -> "15")
    $duration = intval(preg_replace('/[^0-9]/', '', $pilgrims_info[0]['duration']));
}

?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>د ضمانت لیک - <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت</title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        body {
            font-family: 'Noto Naskh Arabic', Arial, sans-serif;
            line-height: 1.3;
            padding: 0;
            max-width: 21cm;
            margin: 0 auto;
            background: #fff;
            color: #000;
            font-size: 11px;
            min-height: 29.7cm;
            position: relative;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px double #000;
            padding-bottom: 5px;
        }
        .header img {
            width: 80px;
            height: auto;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 16px;
            margin: 0 0 5px 0;
            color: #000;
        }
        .header p {
            margin: 2px 0;
            font-size: 12px;
        }
        .content {
            text-align: justify;
            padding: 0 5px;
        }
        .content ol {
            margin: 5px 20px;
            padding-right: 20px;
        }
        .content ol li {
            margin-bottom: 2px;
            line-height: 1.3;
            text-align: justify;
            position: relative;
            padding-right: 5px;
        }
        .guarantor-section {
            margin: 10px 0;
            padding: 8px 12px;
            border: 1px solid #000;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .guarantor-section p:first-child {
            margin-top: 0;
            margin-bottom: 8px;
        }
        .footer {
            position: relative;
            margin-top: 10px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            padding: 0 50px;
            page-break-inside: avoid;
        }
        .signature-line {
            border-top: 1px solid black;
            width: 200px;
            text-align: center;
            padding-top: 3px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 15px;
        }
        .signature-box {
            text-align: center;
            min-height: 20px;
            margin-bottom: 5px;
        }
        @media print {
            body {
                height: 29.7cm;
            }
            .guarantor-section {
                background-color: #fff;
                break-inside: avoid;
            }
            .footer {
                position: relative;
                margin-top: 10px;
                bottom: auto;
                width: calc(100% - 100px);
                break-inside: avoid;
            }
            .content {
                break-inside: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="Al-Moqadas Logo" style="width: 100px; height: auto;">
        <h2>د <?php echo htmlspecialchars($settings['agency_name']); ?> سیاحتی او توریستی شرکت سره د محترم <?php echo htmlspecialchars($guarantor_name); ?> ضمانت لیک</h2>
        <p>د معتمرینو د لیږد په اړه لاندی مسؤلیتونو ته پاملرنه</p>
        <p>تاریخ: <?php echo $date; ?></p>
    </div>

    <div class="content">
        <ol>
            <li>د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت مکلفیت لری ترڅو د عمری ویزه په سعودی عربستان کی د استوګنی انتظام او د معتمرینو لپاره د ترانسپورت اسانتیاوی برابری کړی.</li>
            <li>د عمری د ویزی ارزښت په ټاکلی نرخ چی د حج او اوقافو وزارت له اړخه تعین شوی ده.</li>
            <li>د معتمربیرته راتګ په ټاکلی مهال د یوه ډاډمن تضمین له مخی لکه صراف او یاهم معتبرتضمین، پدی معنا چی خپل معتمر د وخت له پوره کیدو سره سم کابل ته راستون کړی.</li>
            <li>که معتمر له (<?php echo $duration; ?>) ورځو زیات په سعودی عربستان کی پاتی شی نو متخلف بلل کیږی هرډول جریمه چی د سعودی عربستان د شرکت لخوا د <?php echo htmlspecialchars($settings['agency_name']); ?> پر شرکت وضع کیږی ضامن مکلفیت لری چی د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته یی تحویل کړی.</li>
            <li>که معتمر چیرته تخلف وکړی نو ضامن اړ دی چی حداقل (۱۰۰۰۰۰) سل زره سعودی ریال د یو معتمر په سر تحویل کړی، او هرډول خساره چی له ټاکلی اندازی زیاته وی نو ضامن مسؤل دی چی داخساره هم ادا کړی.</li>
            <li>که په ویزه کی کوم ډول جعل او تذویر پیداکیږی او یاهم د معتمر لخوا تری غیر قانونی استفاده کیږی نو پدی حالت کی معتمر متخلف بلل کیږی کومه جریمه چی په <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت راځی نو ضامن د ادا کولو مسؤلیت لری.</li>
            <li>د پرواز د نیټی تغیرولو په صورت کی ضامن اړ دی چی د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته خبر ورکړی او که نه معتمر متخلف مسؤلیت یی د ضامن پر غاړه دی.</li>
            <li>که د معتمر په پاسپورت کی دخولی او خروجی لګیدلی وی، او په سیستم کی څرکند نه شو پدی حالت کی ضامن مسؤل دی چی پاسپورت د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته وسپاری او که نه متخلف دی مسؤلیت یی د ضامن پرغاړه دی.</li>
            <li>که کوم شخص په سعودی عربستان کی له شخصی ترانسپورت څخه استفاده کوی، خدای ج مه کړه کومه ستونزه، حادثه ورته پیښه شی نو مسؤلیت یی د معتمر پرغاړه دی.</li>
            <li>هرڅوک چی عمری ته د تګ نیت لری باید د سالم عقل کامل هوش څښتن وی. خو د معتمر راستنیدل په ټاکلی وخت کی لازمی امر دی که له ټاکلی مهال یی زیات وخت تیر کړ نو ضامن د هرډول نقصان مسؤلیت پر غاړه لری که معتمر له ټاکلی مهال زیات وخت تیر کړ ضامن د جبران خساری مسؤلیت لری او که معتمر ورک شو نو ضامن باید ډیر ژرد <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته خبر ورکړی ترڅو د خپل مسؤلیت له مخی د ورک شوو، مړو او زندانونو له ریاستونو معلومات حاصل کړی که معتمر به نوموړو ځایو کی وجود نه درلود نو ضامن د راتلونکو مسؤلیتونو ځواب ویونکی دی.</li>
            <li>معتمر باید د خپلی عمری د ویزی قیمت (۵۰٪) پنځوس فیصده مبلغ له پاسپورت سره سم دفتر ته ورکړی او نور مبلغ (۲۴) څلورویشت ساعته د پرواز څخه دمخه د <?php echo htmlspecialchars($settings['agency_name']); ?> دفتر ته وسپاری، او که نه د ټکټ د باطلیدو مسؤلیت به د معتمر پر غاړه وی.</li>
            <li>په سعودی عربستان کی د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت نمایندګان له معتمر سره تر راستنیدو پوری به لازمه همکاری کوی.</li>
            <li>د معتمرینو د بیرته راتګ ځخه وروسته هر معتمر مکلف دی چی خپل پاسپورت د (۱۰) ورځوو په موده کی د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته ددوخول او خروج لپاره <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته راوړی او خپل اصلی اسناد د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت څخه تسلیم شی.</li>
            <li>که کوم معتمر د پاسپورت د تسلیمیدو سره سم د کوم شخصی یا قدرتی افت یا ستونزی سره مخامخ او د پرواز څخه پاتی شی باید پاسپورت بیرته شرکت ته تسلیم کړی او که چیرته یی تسلیم نه کړی او دکومی ستونزی سره مخامخ شی مسؤلیت یی پر خپله غاړه دی.</li>
            <li>ټول معتمرین مکلف دی چی هیڅ غیر قانونی مواد لکه هیروین، چرس، پوډر او داسی نور... عربستان سعودی ته انتقال نکړی، او دعمری د سپیڅلی نوم څخه ناوړه ګټه وانخلی.</li>
            <li>ټول معتمرین مکلف دی چی د افغانستان او سعودی عربستان دولتونو وضع شوی قوانین مراعت کړی او د تخلف په صورت کی معتمر مجرم او ټول مسؤلیت یی ضمانت کونکی ته راجع کیږی.</li>
            <li>هر معتمر مکلف دی چی د ضمانت ترڅنګ د نږدی خپلوانو څخه یو کس لکه ورور، پلار، تره او داسی نور داصلی تذکره سره هم د معتمرد ضمانت په خاطر شرکت ته حاضر کړی.</li>
            <li>د پرواز د نیټی تغیرولو په صورت کی معتمرین اړدی چی د <?php echo htmlspecialchars($settings['agency_name']); ?> شرکت ته خبر ورکړی او که نه ټول مسؤلیت یی د معتمر پر غاړه دی.</li>
            
        </ol>

        <div class="guarantor-section">
            <p style="font-size: 16px; text-align: center; margin-bottom: 20px;">
                <strong>د ضمانت کوونکی ژمنه</strong>
            </p>
            <p style="text-align: justify; line-height: 2;">
                زه ښاغلی <strong><?php echo htmlspecialchars($guarantor_name); ?></strong> د
                <?php
                $pilgrim_details = [];
                foreach ($pilgrims_info as $pilgrim) {
                    $pilgrim_details[] = '<strong>' . htmlspecialchars($pilgrim['name']) . '</strong> پاسپورت نمبر (<strong>' . htmlspecialchars($pilgrim['passport_number']) . '</strong>)';
                }
                echo implode(' او ', $pilgrim_details);
                ?>
                د سعودی عربستان څخه افغانستان ته د بیرته راتګ ضامن یم او د تخلف په صورت کی هرډول جریمه او زیان مسؤل یم.
            </p>
        </div>

        <div class="footer">
            <div>
                <div class="signature-box">
                    <!-- Space for actual signature -->
                </div>
                <div class="signature-line">د ضامن لاسلیک او ګوته</div>
            </div>
            <div>
                <div class="signature-box">
                    <!-- Space for actual signature -->
                </div>
                <div class="signature-line">د شاهد لاسلیک او ګوته</div>
            </div>
        </div>
    </div>
</body>
</html> 