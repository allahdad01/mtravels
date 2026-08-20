<?php

require_once '../../includes/db.php';
require_once '../../includes/language_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . '/../../includes/permissions.php';
require_permission('umrah.view');
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$family_id = filter_input(INPUT_GET, 'family_id', FILTER_VALIDATE_INT);
$member_ids_raw = $_GET['member_ids'] ?? '';
$bank_name = filter_input(INPUT_GET, 'bank_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$bank_account_number = filter_input(INPUT_GET, 'bank_account_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$account_name = filter_input(INPUT_GET, 'account_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$payment = filter_input(INPUT_GET, 'payment', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$language = $_GET['language'] ?? 'fa';

if (!$family_id) {
    die('شناسه خانواده نامعتبر است.');
}

try {
// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

    $logoPath = __DIR__ . '../../uploads/logo/' . $settings['logo'];
    if (!empty($settings['logo']) && file_exists('../../uploads/logo/' . $settings['logo'])) {
        $logoPath = '../../uploads/logo/' . $settings['logo'];
    }

    // Load Umrah bookings for the family
    $members = [];
    if (!empty($member_ids_raw)) {
        // Filter by selected member IDs
        $ids = array_filter(array_map('intval', explode(',', $member_ids_raw)));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND booking_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$family_id, $tenant_id, $branch_id], $ids));
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    if (empty($members)) {
        // Fallback to all family members if none explicitly selected
        $stmt = $pdo->prepare("SELECT * FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$family_id, $tenant_id, $branch_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Auto-translate names into the document language (MyMemory - free)
    require_once __DIR__ . '/../../includes/translate_helper.php';
    $docLang = in_array($language, ['ps', 'fa']) ? $language : 'fa';
    translate_name_fields($members, $docLang, ['name']);
    $bank_name = translate_place($bank_name, $docLang);
    $account_name = translate_name($account_name, $docLang);

    if (!$members) {
        die('هیچ عضوی برای این خانواده یافت نشد.');
    }

} catch (PDOException $e) {
    die("خطا در دریافت معلومات رسید بانکی.");
}
?>

<!DOCTYPE html>
<html lang="<?= $language ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>رسید بانکی - <?= htmlspecialchars($settings['agency_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&family=Tajawal&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Amiri', serif;
            background-color: #fff;
            color: #000;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 800px;
            margin: auto;
            border: 1px solid #ccc;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #444;
            margin-bottom: 20px;
            position: relative;
        }
        .header img {
            position: absolute;
            left: 0;
            top: -5px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .header h1 {
            font-family: 'Tajawal', sans-serif;
            font-size: 20pt;
            margin-bottom: 5px;
        }
        .date {
            position: absolute;
            right: 0;
            top: -5px;
            font-size: 10pt;
        }
        .section-title {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            border: 1px solid #aaa;
            padding: 6px;
            font-size: 10pt;
            text-align: center;
        }
        .signature {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .btn-print {
            display: none;
        }
        @media print {
            .btn-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="لوگو">
        <h1><?= $settings['agency_name'] ?> - <?php echo htmlspecialchars($branch['name']); ?><br>رسید پرداخت بانکی</h1>
        <div class="date">تاریخ: <?= date('Y-m-d') ?></div>
    </div>

    <div>
        <strong>نام بانک:</strong> <?= htmlspecialchars($bank_name) ?><br>
        <strong>شماره حساب بانکی:</strong> <?= htmlspecialchars($bank_account_number) ?><br>
        <strong>نام حساب:</strong> <?= htmlspecialchars($account_name) ?><br>
    </div>

    <div class="section-title">لیست اعضای خانواده</div>
    <table>
        <thead>
            <tr>
                <th>شماره</th>
                <th>نام کامل</th>
                <th>شماره پاسپورت</th>
                <th>پرداخت‌ به بانک</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $index => $member): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($member['name']) ?></td>
                <td><?= htmlspecialchars($member['passport_number']) ?></td>
                <td><?= $payment ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signature">
        <div class="signature-box">
            <p>امضای مسئول</p>
            <div>________________________</div>
        </div>
    </div>
</div>
<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>
