<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
require_once __DIR__ . '/../../includes/translate_helper.php';
require_once __DIR__ . '/service_report_data.php';
session_start();

enforce_auth();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
$groupBy = isset($_GET['group_by']) && in_array($_GET['group_by'], ['service', 'group', 'family'], true) ? $_GET['group_by'] : 'service';
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';

$data = service_report_load($pdo, $tenant_id, $branch_id, $dateFrom, $dateTo, $groupBy);

$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);

$langLabels = [
    'dari' => [
        'doc_title'       => 'راپور خدمات',
        'subtitle'        => 'قیمت، لابسته و تفصیلات هر خدمت',
        'date_range'      => 'دوره زمانی',
        'group_by'        => 'ګروپ بندی',
        'by_service'      => 'نوعیت خدمت',
        'by_group'        => 'ګروپ',
        'by_family'       => 'فامیل',
        'col_s'           => 'شماره',
        'col_service'     => 'نوعیت خدمت',
        'col_members'     => 'معتمر',
        'col_cost'        => 'قیمت خرید',
        'col_sold'        => 'قیمت فروش',
        'col_profit'      => 'لابسته',
        'col_margin'      => 'فیصدی',
        'grand_total'     => 'مجموع کلی',
        'total_cost'      => 'مجموع قیمت خرید',
        'total_sold'      => 'مجموع قیمت فروش',
        'total_profit'    => 'مجموع لابسته',
        'group_name'      => 'ګروپ',
        'family_name'     => 'فامیل',
        'head_of_family'  => 'سرفامیل',
        'client'          => 'کلاینت',
        'print'           => 'چاپ',
        'empty'           => 'هیچ معلوماتی یافت نشد',
        'generated_on'    => 'تاریخ ایجاد',
        'currency'        => 'اسعارو',
        'members'         => 'معتمر',
        'total_members'   => 'مجموع معتمرین',
        'service_breakdown' => 'تفصیلات خدمت',
    ],
    'ps' => [
        'doc_title'       => 'د خدمتونو راپور',
        'subtitle'        => 'د خدمتونو بیه، ګټه او تفصیلات',
        'date_range'      => 'د وخت محدوده',
        'group_by'        => 'د ګروپ بندی',
        'by_service'      => 'د خدمت ډول',
        'by_group'        => 'ګروپ',
        'by_family'       => 'کورنۍ',
        'col_s'           => 'شمېره',
        'col_service'     => 'د خدمت ډول',
        'col_members'     => 'معتمر',
        'col_cost'        => 'د پیرود بیه',
        'col_sold'        => 'د پلور بیه',
        'col_profit'      => 'ګټه',
        'col_margin'      => 'فیصدي',
        'grand_total'     => 'ټول مجموع',
        'total_cost'      => 'ټول د پیرود بیه',
        'total_sold'      => 'ټول د پلور بیه',
        'total_profit'    => 'ټوله ګټه',
        'group_name'      => 'ګروپ',
        'family_name'     => 'کورنۍ',
        'head_of_family'  => 'د کورنۍ سرلیک',
        'client'          => 'کلاینت',
        'print'           => 'چاپ',
        'empty'           => 'هیڅ معلومات ونه موندل شو',
        'generated_on'    => 'د رامنځته کېدو نیټه',
        'currency'        => 'اسعارو',
        'members'         => 'معتمر',
        'total_members'   => 'ټول معتمر',
        'service_breakdown' => 'د خدمتونو تفصیلات',
    ],
    'en' => [
        'doc_title'       => 'Service Report',
        'subtitle'        => 'Service cost, profit and detailed breakdown',
        'date_range'      => 'Date Range',
        'group_by'        => 'Group By',
        'by_service'      => 'Service Type',
        'by_group'        => 'Group',
        'by_family'       => 'Family',
        'col_s'           => '#',
        'col_service'     => 'Service Type',
        'col_members'     => 'Members',
        'col_cost'        => 'Cost',
        'col_sold'        => 'Sold Price',
        'col_profit'      => 'Profit',
        'col_margin'      => 'Margin',
        'grand_total'     => 'Grand Total',
        'total_cost'      => 'Total Cost',
        'total_sold'      => 'Total Sold',
        'total_profit'    => 'Total Profit',
        'group_name'      => 'Group',
        'family_name'     => 'Family',
        'head_of_family'  => 'Head of Family',
        'client'          => 'Client',
        'print'           => 'Print',
        'empty'           => 'No data found',
        'generated_on'    => 'Generated on',
        'currency'        => 'Currency',
        'members'         => 'members',
        'total_members'   => 'Total Members',
        'service_breakdown' => 'Service Breakdown',
    ],
];
$L = $langLabels[$docLanguage];

$groupByLabel = $L['by_service'];
if ($groupBy === 'group') $groupByLabel = $L['by_group'];
elseif ($groupBy === 'family') $groupByLabel = $L['by_family'];

$today = date('Y-m-d');
$fmt = function ($v) {
    return number_format((float)$v, 2);
};
?>
<!DOCTYPE html>
<html lang="<?php echo $docLanguage; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($L['doc_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Noto Naskh Arabic', sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 24px 20px;
            color: #111827;
            font-size: 11px;
        }
        body.rtl { direction: rtl; }
        .doc-header { text-align: center; margin-bottom: 14px; }
        .doc-header .agency { font-size: 16px; font-weight: 700; color: #1f2937; }
        .doc-header h1 { font-size: 18px; margin: 6px 0 2px; color: #111827; }
        .doc-header .subtitle { font-size: 11px; color: #4b5563; }
        .doc-header .branch { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .meta-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-size: 10px;
        }
        .meta-row b { font-weight: 700; }
        .summary-cards {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }
        .summary-card {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px 10px;
            text-align: center;
        }
        .summary-card .card-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card .card-value { font-size: 14px; font-weight: 700; margin-top: 2px; }
        .summary-card .card-value.cost { color: #dc2626; }
        .summary-card .card-value.sold { color: #2563eb; }
        .summary-card .card-value.profit-pos { color: #15803d; }
        .summary-card .card-value.profit-neg { color: #b91c1c; }
        .summary-card .card-value.members { color: #7c3aed; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #9ca3af; padding: 4px 6px; vertical-align: top; }
        thead th {
            background: #e5e7eb;
            font-weight: 700;
            text-align: center;
            font-size: 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .col-s { width: 4%; text-align: center; }
        .col-name { width: 22%; }
        .col-members { width: 10%; text-align: center; }
        .col-cost, .col-sold, .col-profit, .col-margin { width: 13%; text-align: center; }
        .num { direction: ltr; text-align: center; white-space: nowrap; }
        .group-header td {
            background: #dbeafe !important;
            font-weight: 700;
            font-size: 11px;
            border-top: 2px solid #3b82f6;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .family-header td {
            background: #f3f4f6 !important;
            font-weight: 600;
            font-size: 10.5px;
            border-top: 1px solid #9ca3af;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .service-row td {
            font-size: 10.5px;
        }
        .sub-total td {
            background: #fef3c7 !important;
            font-weight: 600;
            border-top: 1px solid #d97706;
            font-size: 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .grand-total td {
            background: #e5e7eb;
            font-weight: 700;
            border-top: 2px solid #374151;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .profit-pos { color: #15803d; }
        .profit-neg { color: #b91c1c; }
        .empty-row td { padding: 18px; color: #555; text-align: center; }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12pt;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 9999;
        }
        .print-button:hover { background-color: #34495e; }
        @media print {
            body { max-width: none; }
            .print-button { display: none !important; }
            thead th { background: #e5e7eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">

    <button class="print-button" onclick="window.print()">&#128424; <?php echo htmlspecialchars($L['print']); ?></button>

    <div class="doc-header">
        <div class="agency"><?php echo htmlspecialchars($agencyName); ?></div>
        <h1><?php echo htmlspecialchars($L['doc_title']); ?></h1>
        <div class="subtitle"><?php echo htmlspecialchars($L['subtitle']); ?></div>
        <div class="branch"><?php echo htmlspecialchars($branch['name'] ?? ''); ?></div>
    </div>

    <div class="meta-row">
        <span><b><?php echo htmlspecialchars($L['date_range']); ?>:</b> <?php echo htmlspecialchars($data['date_from'] . ' — ' . $data['date_to']); ?></span>
        <span><b><?php echo htmlspecialchars($L['group_by']); ?>:</b> <?php echo htmlspecialchars($groupByLabel); ?></span>
        <span><b><?php echo htmlspecialchars($L['total_members']); ?>:</b> <?php echo $data['summary']['total_members']; ?></span>
        <span><b><?php echo htmlspecialchars($L['generated_on']); ?>:</b> <?php echo htmlspecialchars($today); ?></span>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="card-label"><?php echo htmlspecialchars($L['total_members']); ?></div>
            <div class="card-value members"><?php echo $data['summary']['total_members']; ?></div>
        </div>
        <div class="summary-card">
            <div class="card-label"><?php echo htmlspecialchars($L['total_cost']); ?></div>
            <div class="card-value cost"><?php echo $fmt($data['summary']['total_cost']); ?></div>
        </div>
        <div class="summary-card">
            <div class="card-label"><?php echo htmlspecialchars($L['total_sold']); ?></div>
            <div class="card-value sold"><?php echo $fmt($data['summary']['total_sold']); ?></div>
        </div>
        <div class="summary-card">
            <div class="card-label"><?php echo htmlspecialchars($L['total_profit']); ?></div>
            <div class="card-value <?php echo $data['summary']['total_profit'] >= 0 ? 'profit-pos' : 'profit-neg'; ?>"><?php echo $fmt($data['summary']['total_profit']); ?></div>
        </div>
    </div>

    <?php if ($groupBy === 'service'): ?>
    <!-- Service Type Summary Table -->
    <table>
        <thead>
            <tr>
                <th class="col-s"><?php echo htmlspecialchars($L['col_s']); ?></th>
                <th class="col-name"><?php echo htmlspecialchars($L['col_service']); ?></th>
                <th class="col-members"><?php echo htmlspecialchars($L['col_members']); ?></th>
                <th class="col-cost"><?php echo htmlspecialchars($L['col_cost']); ?></th>
                <th class="col-margin"><?php echo htmlspecialchars($L['col_margin']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['services'])): ?>
            <tr class="empty-row"><td colspan="5"><?php echo htmlspecialchars($L['empty']); ?></td></tr>
            <?php else: ?>
            <?php $i = 0; foreach ($data['services'] as $svc): $i++;
                $margin = $data['summary']['total_sold'] > 0 ? round(($svc['total_cost'] / $data['summary']['total_sold']) * 100, 1) : 0;
            ?>
            <tr class="service-row">
                <td class="col-s"><?php echo $i; ?></td>
                <td class="col-name" style="font-weight:600;"><?php echo htmlspecialchars(ucfirst($svc['service_name'])); ?></td>
                <td class="num"><?php echo $svc['member_count']; ?></td>
                <td class="num"><?php echo $fmt($svc['total_cost']); ?></td>
                <td class="num"><?php echo $margin; ?>%</td>
            </tr>
            <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="2"><?php echo htmlspecialchars($L['grand_total']); ?></td>
                <td class="num"><?php echo $data['summary']['total_members']; ?></td>
                <td class="num"><?php echo $fmt($data['summary']['total_cost']); ?></td>
                <td class="num">100%</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($groupBy === 'group'): ?>
    <!-- Group-wise breakdown -->
    <table>
        <thead>
            <tr>
                <th class="col-s"><?php echo htmlspecialchars($L['col_s']); ?></th>
                <th class="col-name"><?php echo htmlspecialchars($L['group_name']); ?></th>
                <th class="col-members"><?php echo htmlspecialchars($L['col_members']); ?></th>
                <th class="col-cost"><?php echo htmlspecialchars($L['col_cost']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['details'])): ?>
            <tr class="empty-row"><td colspan="4"><?php echo htmlspecialchars($L['empty']); ?></td></tr>
            <?php else: ?>
            <?php $gi = 0; foreach ($data['details'] as $grp): $gi++; ?>
            <tr class="group-header">
                <td colspan="4">
                    <?php echo htmlspecialchars($L['group_name']); ?>: #<?php echo htmlspecialchars($grp['group_number']); ?> — <?php echo htmlspecialchars($grp['group_name']); ?>
                    (<?php echo $grp['member_count']; ?> <?php echo htmlspecialchars($L['members']); ?>)
                </td>
            </tr>
            <?php foreach ($grp['services'] as $svc): ?>
            <tr class="service-row">
                <td></td>
                <td class="col-name" style="padding-left:20px;"><?php echo htmlspecialchars(ucfirst($svc['service_name'])); ?></td>
                <td class="num"><?php echo $svc['member_count']; ?></td>
                <td class="num"><?php echo $fmt($svc['total_cost']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="sub-total">
                <td colspan="2" style="padding-left:20px;">&nbsp;&nbsp;<?php echo htmlspecialchars($L['grand_total']); ?></td>
                <td class="num"><?php echo $grp['member_count']; ?></td>
                <td class="num"><?php echo $fmt($grp['total_cost']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="2"><?php echo htmlspecialchars($L['grand_total']); ?></td>
                <td class="num"><?php echo $data['summary']['total_members']; ?></td>
                <td class="num"><?php echo $fmt($data['summary']['total_cost']); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($groupBy === 'family'): ?>
    <!-- Family-wise breakdown -->
    <table>
        <thead>
            <tr>
                <th class="col-s"><?php echo htmlspecialchars($L['col_s']); ?></th>
                <th class="col-name"><?php echo htmlspecialchars($L['family_name']); ?></th>
                <th style="width:12%;"><?php echo htmlspecialchars($L['group_name']); ?></th>
                <th class="col-members"><?php echo htmlspecialchars($L['col_members']); ?></th>
                <th class="col-cost"><?php echo htmlspecialchars($L['col_cost']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['details'])): ?>
            <tr class="empty-row"><td colspan="5"><?php echo htmlspecialchars($L['empty']); ?></td></tr>
            <?php else: ?>
            <?php $fi = 0; foreach ($data['details'] as $fam): $fi++; ?>
            <tr class="family-header">
                <td colspan="5">
                    <?php echo htmlspecialchars($L['head_of_family']); ?>: <?php echo htmlspecialchars($fam['head_of_family']); ?>
                    — <?php echo htmlspecialchars($L['client']); ?>: <?php echo htmlspecialchars($fam['client_name'] ?: '—'); ?>
                    (<?php echo $fam['member_count']; ?> <?php echo htmlspecialchars($L['members']); ?>)
                </td>
            </tr>
            <?php foreach ($fam['services'] as $svc): ?>
            <tr class="service-row">
                <td></td>
                <td class="col-name" style="padding-left:20px;"><?php echo htmlspecialchars(ucfirst($svc['service_name'])); ?></td>
                <td></td>
                <td class="num"><?php echo $svc['member_count']; ?></td>
                <td class="num"><?php echo $fmt($svc['total_cost']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="sub-total">
                <td colspan="3" style="padding-left:20px;">&nbsp;&nbsp;<?php echo htmlspecialchars($L['grand_total']); ?></td>
                <td class="num"><?php echo $fam['member_count']; ?></td>
                <td class="num"><?php echo $fmt($fam['total_cost']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="3"><?php echo htmlspecialchars($L['grand_total']); ?></td>
                <td class="num"><?php echo $data['summary']['total_members']; ?></td>
                <td class="num"><?php echo $fmt($data['summary']['total_cost']); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:9.5px; color:#444;">
        <span><?php echo $data['summary']['total_members']; ?> <?php echo htmlspecialchars($L['members']); ?></span>
        <span><?php echo htmlspecialchars($today); ?></span>
    </div>

</body>
</html>
