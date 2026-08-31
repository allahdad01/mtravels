<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
require_once __DIR__ . '/../../includes/translate_helper.php';
require_once __DIR__ . '/profit_report_data.php';
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

$scope = isset($_GET['scope']) && in_array($_GET['scope'], ['group', 'family', 'member'], true) ? $_GET['scope'] : 'member';
$scopeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';

$data = profit_report_load($pdo, $tenant_id, $branch_id, $scope, $scopeId);
if ($data === null) {
    die('Invalid request: scope not found');
}

$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);
$scopeTitle = translate_name($data['title_name'] ?? '', $docLanguage);

foreach ($data['members'] as &$m) {
    $m['name'] = translate_name($m['name'] ?? '', $docLanguage);
    $m['fname'] = translate_name($m['fname'] ?? '', $docLanguage);
    $m['head_of_family'] = translate_name($m['head_of_family'] ?? '', $docLanguage);
    $m['client_name'] = translate_name($m['client_name'] ?? '', $docLanguage);
    foreach ($m['services'] as &$s) {
        $s['label'] = translate_name($s['label'], $docLanguage);
    }
    unset($s);
}
unset($m);

$langLabels = [
    'dari' => [
        'doc_title' => 'گزارش لابسته',
        'subtitle' => 'قیمت خدمات، قیمت فروش و لابسته معتمرین',
        'scope_label' => 'ساحه',
        'member_scope' => 'معتمر',
        'family_scope' => 'فامیل',
        'group_scope' => 'گروپ',
        'col_s' => 'شماره',
        'col_name' => 'نام',
        'col_fname' => 'ولد',
        'col_passport' => 'شماره پاسپورت',
        'col_services' => 'خدمات',
        'col_cost' => 'قیمت خرید',
        'col_sold' => 'قیمت فروش',
        'col_discount' => 'تخفیف',
        'col_profit' => 'لابسته',
        'grand_total' => 'مجموع کلی',
        'members' => 'معتمر',
        'profit' => 'لابسته',
        'cost' => 'قیمت خرید',
        'brn' => 'BRN',
        'print' => 'چاپ',
        'empty' => 'هیچ معتمری یافت نشد',
        'generated_on' => 'تاریخ ایجاد',
        'currency' => 'واحد پول',
        'client' => 'کلاینت',
        'family' => 'فامیل',
        'extra_beds' => 'بسترهای اضافی',
    ],
    'ps' => [
        'doc_title' => 'د ګټې راپور',
        'subtitle' => 'د خدمتونو بیه، د پلور بیه او د معتمرو ګټه',
        'scope_label' => 'ساحه',
        'member_scope' => 'معتمر',
        'family_scope' => 'کورنۍ',
        'group_scope' => 'ګروپ',
        'col_s' => 'شمېره',
        'col_name' => 'نوم',
        'col_fname' => 'پلار نوم',
        'col_passport' => 'د پاسپورټ شمېره',
        'col_services' => 'خدمتونه',
        'col_cost' => 'د پیرود بیه',
        'col_sold' => 'د پلور بیه',
        'col_discount' => 'رعایت',
        'col_profit' => 'ګټه',
        'grand_total' => 'ټول مجموع',
        'members' => 'معتمر',
        'profit' => 'ګټه',
        'cost' => 'د پیرود بیه',
        'brn' => 'BRN',
        'print' => 'چاپ',
        'empty' => 'هیڅ معتمر ونه موندل شو',
        'generated_on' => 'د رامنځته کېدو نیټه',
        'currency' => 'اسعارو',
        'client' => 'کلاینت',
        'family' => 'کورنۍ',
        'extra_beds' => 'اضافي بستر',
    ],
    'en' => [
        'doc_title' => 'Profit Report',
        'subtitle' => 'Service cost, sold price and profit per member',
        'scope_label' => 'Scope',
        'member_scope' => 'Member',
        'family_scope' => 'Family',
        'group_scope' => 'Group',
        'col_s' => '#',
        'col_name' => 'Name',
        'col_fname' => 'Father',
        'col_passport' => 'Passport #',
        'col_services' => 'Services',
        'col_cost' => 'Cost',
        'col_sold' => 'Sold Price',
        'col_discount' => 'Discount',
        'col_profit' => 'Profit',
        'grand_total' => 'Grand Total',
        'members' => 'members',
        'profit' => 'Profit',
        'cost' => 'Cost',
        'brn' => 'BRN',
        'print' => 'Print',
        'empty' => 'No members found',
        'generated_on' => 'Generated on',
        'currency' => 'Currency',
        'client' => 'Client',
        'family' => 'Family',
        'extra_beds' => 'Extra Beds',
    ],
];
$L = $langLabels[$docLanguage];

$scopeLabel = $L['member_scope'];
if ($scope === 'family') {
    $scopeLabel = $L['family_scope'];
} elseif ($scope === 'group') {
    $scopeLabel = $L['group_scope'];
}

$today = date('Y-m-d');
$currencies = [];
foreach ($data['members'] as $m) {
    $cur = strtoupper((string)($m['currency'] ?: 'USD'));
    $currencies[$cur] = true;
}
$currencyText = implode(', ', array_keys($currencies)) ?: 'USD';
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
            max-width: 900px;
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
        table { width: 100%; border-collapse: collapse; }
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
        .col-name { width: 14%; }
        .col-fname { width: 12%; }
        .col-passport { width: 11%; }
        .col-services { width: 24%; }
        .col-cost, .col-sold, .col-discount, .col-profit { width: 9%; text-align: center; }
        .num { direction: ltr; text-align: center; white-space: nowrap; }
        .svc-line { display: block; }
        .svc-line .svc-cost { font-weight: 600; }
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
        .client-header td { background: #dbeafe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .family-header td { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .family-total td { background: #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
            .member-total td { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .grand-total td { background: #e5e7eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="<?php echo ($docLanguage === 'en') ? 'ltr' : 'rtl'; ?>">

    <button class="print-button" onclick="window.print()">🖨️ <?php echo htmlspecialchars($L['print']); ?></button>

    <div class="doc-header">
        <div class="agency"><?php echo htmlspecialchars($agencyName); ?></div>
        <h1><?php echo htmlspecialchars($L['doc_title']); ?></h1>
        <div class="subtitle"><?php echo htmlspecialchars($L['subtitle']); ?></div>
        <div class="branch"><?php echo htmlspecialchars($branch['name'] ?? ''); ?></div>
    </div>

    <div class="meta-row">
        <span><b><?php echo htmlspecialchars($L['scope_label']); ?>:</b> <?php echo htmlspecialchars($scopeLabel . ' — ' . $scopeTitle); ?></span>
        <span><b><?php echo htmlspecialchars($L['currency']); ?>:</b> <?php echo htmlspecialchars($currencyText); ?></span>
        <span><b><?php echo htmlspecialchars($L['generated_on']); ?>:</b> <?php echo htmlspecialchars($today); ?></span>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-s"><?php echo htmlspecialchars($L['col_s']); ?></th>
                <th class="col-name"><?php echo htmlspecialchars($L['col_name']); ?></th>
                <th class="col-fname"><?php echo htmlspecialchars($L['col_fname']); ?></th>
                <th class="col-passport"><?php echo htmlspecialchars($L['col_passport']); ?></th>
                <th class="col-services"><?php echo htmlspecialchars($L['col_services']); ?></th>
                <th class="col-cost"><?php echo htmlspecialchars($L['col_cost']); ?></th>
                <th class="col-sold"><?php echo htmlspecialchars($L['col_sold']); ?></th>
                <th class="col-discount"><?php echo htmlspecialchars($L['col_discount']); ?></th>
                <th class="col-profit"><?php echo htmlspecialchars($L['col_profit']); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['members'])): ?>
            <tr class="empty-row">
                <td colspan="9"><?php echo htmlspecialchars($L['empty']); ?></td>
            </tr>
            <?php else: ?>
            <?php
            if ($scope === 'group') {
                $totalMembers = 0; $totalExtraBeds = 0;
                foreach ($data['members'] as $m) {
                    if (!empty($m['is_extra_bed']) || !empty($m['is_extra_transport'])) { $totalExtraBeds++; } else { $totalMembers++; }
                }
                $byClient = [];
                foreach ($data['members'] as $m) {
                    $clientKey = $m['client_name'] ?? '—';
                    $familyKey = $m['head_of_family'] ?? '—';
                    $byClient[$clientKey][$familyKey][] = $m;
                }
                $i = 0;
                foreach ($byClient as $clientName => $families):
            ?>
            <tr class="client-header">
                <td colspan="9" style="background:#dbeafe; font-weight:700; font-size:11px; border-top:2px solid #3b82f6;">
                    <?php echo htmlspecialchars($L['client']); ?>: <?php echo htmlspecialchars($clientName); ?>
                </td>
            </tr>
            <?php foreach ($families as $familyName => $fmembers):
                    $famCost = 0; $famSold = 0; $famProfit = 0;
                    $famMembers = 0; $famExtraBeds = 0;
                    foreach ($fmembers as $fm) {
                        $famCost += $fm['cost_total']; $famSold += $fm['sold_total']; $famProfit += $fm['profit'];
                        if (!empty($fm['is_extra_bed']) || !empty($fm['is_extra_transport'])) { $famExtraBeds++; } else { $famMembers++; }
                    }
            ?>
            <tr class="family-header">
                <td colspan="9" style="background:#f3f4f6; font-weight:600; font-size:10.5px; border-top:1px solid #9ca3af;">
                    <?php echo htmlspecialchars($L['family']); ?>: <?php echo htmlspecialchars($familyName); ?> (<?php echo $famMembers; ?> <?php echo htmlspecialchars($L['members']); ?><?php if ($famExtraBeds > 0): ?> + <?php echo $famExtraBeds; ?> <?php echo htmlspecialchars($L['extra_beds']); ?><?php endif; ?>)
                </td>
            </tr>
            <?php foreach ($fmembers as $m): $i++; $isNeg = $m['profit'] < 0; ?>
            <tr>
                <td class="col-s"><?php echo $i; ?></td>
                <td class="col-name"><?php echo htmlspecialchars($m['name'] ?? ''); ?><?php if (!empty($m['is_extra_bed']) || !empty($m['is_extra_transport'])): ?> <span style="color:#d97706; font-size:9px; font-weight:600;">(<?php echo htmlspecialchars($L['extra_beds']); ?>)</span><?php endif; ?></td>
                <td class="col-fname"><?php echo htmlspecialchars($m['fname'] ?? ''); ?></td>
                <td class="col-passport"><?php echo htmlspecialchars($m['passport_number'] ?? ''); ?></td>
                <td class="col-services">
                    <?php foreach ($m['services'] as $s): ?>
                    <span class="svc-line"><?php echo htmlspecialchars($s['label']); ?> — <span class="svc-cost num"><?php echo $fmt($s['cost']); ?></span></span>
                    <?php endforeach; ?>
                    <?php if ($m['brn_cost'] > 0): ?>
                    <span class="svc-line"><?php echo htmlspecialchars($L['brn']); ?> — <span class="svc-cost num"><?php echo $fmt($m['brn_cost']); ?></span></span>
                    <?php endif; ?>
                    <?php if (empty($m['services']) && $m['brn_cost'] <= 0): ?>
                    <span class="svc-line" style="color:#6b7280;">—</span>
                    <?php endif; ?>
                </td>
                <td class="num"><?php echo $fmt($m['cost_total']); ?></td>
                <td class="num"><?php echo $fmt($m['sold_total']); ?></td>
                <td class="num"><?php echo $fmt((float)($m['discount'] ?? 0)); ?></td>
                <td class="num <?php echo $isNeg ? 'profit-neg' : 'profit-pos'; ?>"><?php echo $fmt($m['profit']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="family-total" style="background:#fef3c7;">
                <td colspan="5" style="font-weight:600; font-size:10px; border-top:1px solid #d97706;">
                    &nbsp;&nbsp;<?php echo htmlspecialchars($familyName); ?> — <?php echo htmlspecialchars($L['grand_total']); ?>
                </td>
                <td class="num" style="font-weight:600;"><?php echo $fmt($famCost); ?></td>
                <td class="num" style="font-weight:600;"><?php echo $fmt($famSold); ?></td>
                <td class="num" style="font-weight:600;"><?php echo $fmt(0); ?></td>
                <td class="num" style="font-weight:600;" class="<?php echo $famProfit < 0 ? 'profit-neg' : 'profit-pos'; ?>"><?php echo $fmt($famProfit); ?></td>
            </tr>
            <?php endforeach; endforeach; ?>
            <tr class="grand-total">
                <td colspan="5"><?php echo htmlspecialchars($L['grand_total']); ?> (<?php echo $totalMembers; ?> <?php echo htmlspecialchars($L['members']); ?><?php if ($totalExtraBeds > 0): ?> + <?php echo $totalExtraBeds; ?> <?php echo htmlspecialchars($L['extra_beds']); ?><?php endif; ?>)</td>
                <td class="num"><?php echo $fmt($data['cost_total']); ?></td>
                <td class="num"><?php echo $fmt($data['sold_total']); ?></td>
                <td class="num"><?php echo $fmt(0); ?></td>
                <td class="num <?php echo $data['profit_total'] < 0 ? 'profit-neg' : 'profit-pos'; ?>"><?php echo $fmt($data['profit_total']); ?></td>
            </tr>
            <?php
            } else {
                $totalMembers = 0; $totalExtraBeds = 0;
                foreach ($data['members'] as $m) {
                    if (!empty($m['is_extra_bed']) || !empty($m['is_extra_transport'])) { $totalExtraBeds++; } else { $totalMembers++; }
                }
            ?>
            <?php $i = 0; foreach ($data['members'] as $m): $i++; $isNeg = $m['profit'] < 0; ?>
            <tr>
                <td class="col-s"><?php echo $i; ?></td>
                <td class="col-name"><?php echo htmlspecialchars($m['name'] ?? ''); ?><?php if (!empty($m['is_extra_bed']) || !empty($m['is_extra_transport'])): ?> <span style="color:#d97706; font-size:9px; font-weight:600;">(<?php echo htmlspecialchars($L['extra_beds']); ?>)</span><?php endif; ?></td>
                <td class="col-fname"><?php echo htmlspecialchars($m['fname'] ?? ''); ?></td>
                <td class="col-passport"><?php echo htmlspecialchars($m['passport_number'] ?? ''); ?></td>
                <td class="col-services">
                    <?php foreach ($m['services'] as $s): ?>
                    <span class="svc-line"><?php echo htmlspecialchars($s['label']); ?> — <span class="svc-cost num"><?php echo $fmt($s['cost']); ?></span></span>
                    <?php endforeach; ?>
                    <?php if ($m['brn_cost'] > 0): ?>
                    <span class="svc-line"><?php echo htmlspecialchars($L['brn']); ?> — <span class="svc-cost num"><?php echo $fmt($m['brn_cost']); ?></span></span>
                    <?php endif; ?>
                    <?php if (empty($m['services']) && $m['brn_cost'] <= 0): ?>
                    <span class="svc-line" style="color:#6b7280;">—</span>
                    <?php endif; ?>
                </td>
                <td class="num"><?php echo $fmt($m['cost_total']); ?></td>
                <td class="num"><?php echo $fmt($m['sold_total']); ?></td>
                <td class="num"><?php echo $fmt((float)($m['discount'] ?? 0)); ?></td>
                <td class="num <?php echo $isNeg ? 'profit-neg' : 'profit-pos'; ?>"><?php echo $fmt($m['profit']); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="5"><?php echo htmlspecialchars($L['grand_total']); ?> (<?php echo $totalMembers; ?> <?php echo htmlspecialchars($L['members']); ?><?php if ($totalExtraBeds > 0): ?> + <?php echo $totalExtraBeds; ?> <?php echo htmlspecialchars($L['extra_beds']); ?><?php endif; ?>)</td>
                <td class="num"><?php echo $fmt($data['cost_total']); ?></td>
                <td class="num"><?php echo $fmt($data['sold_total']); ?></td>
                <td class="num"><?php echo $fmt(0); ?></td>
                <td class="num <?php echo $data['profit_total'] < 0 ? 'profit-neg' : 'profit-pos'; ?>"><?php echo $fmt($data['profit_total']); ?></td>
            </tr>
            <?php } ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:9.5px; color:#444;">
        <span><?php echo $totalMembers; ?> <?php echo htmlspecialchars($L['members']); ?><?php if ($totalExtraBeds > 0): ?> + <?php echo $totalExtraBeds; ?> <?php echo htmlspecialchars($L['extra_beds']); ?><?php endif; ?></span>
        <span><?php echo htmlspecialchars($today); ?></span>
    </div>

<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>