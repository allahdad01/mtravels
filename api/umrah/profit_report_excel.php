<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
require_once '../../vendor/autoload.php';
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

$scope = isset($_GET['scope']) && in_array($_GET['scope'], ['group', 'family', 'member'], true) ? $_GET['scope'] : 'member';
$scopeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;

// Support multiple group_ids
$groupIds = [];
if (!empty($_GET['group_ids'])) {
    $raw = explode(',', $_GET['group_ids']);
    foreach ($raw as $gid) {
        $gid = (int)trim($gid);
        if ($gid > 0) $groupIds[] = $gid;
    }
}

if ($scope === 'group' && !empty($groupIds)) {
    $allMembers = [];
    $costTotal = 0; $soldTotal = 0; $profitTotal = 0;
    foreach ($groupIds as $gid) {
        $gData = profit_report_load($pdo, $tenant_id, $branch_id, 'group', $gid);
        if ($gData && !empty($gData['members'])) {
            $allMembers = array_merge($allMembers, $gData['members']);
            $costTotal += $gData['cost_total'];
            $soldTotal += $gData['sold_total'];
            $profitTotal += $gData['profit_total'];
        }
    }
    $data = [
        'scope'        => 'group',
        'scope_id'     => 0,
        'title_name'   => count($groupIds) > 1 ? count($groupIds) . ' Groups' : '',
        'members'      => $allMembers,
        'cost_total'   => $costTotal,
        'sold_total'   => $soldTotal,
        'profit_total' => $profitTotal,
    ];
    $scopeTitle = $data['title_name'];
} else {
    $data = profit_report_load($pdo, $tenant_id, $branch_id, $scope, $scopeId);
    if ($data === null) {
        die('Invalid request: scope not found');
    }
    $scopeTitle = translate_name($data['title_name'] ?? '', $docLanguage);
}

// Apply date filter if set
if (($dateFrom || $dateTo) && !empty($data['members'])) {
    $filtered = [];
    foreach ($data['members'] as $m) {
        $fSql = "SELECT 1 FROM umrah_fulfillments ful
                 JOIN umrah_booking_services bs ON ful.booking_service_id = bs.id AND bs.tenant_id = ful.tenant_id
                 WHERE bs.booking_id = ? AND ful.tenant_id = ? AND ful.status != 'cancelled' AND bs.is_excluded = 0";
        $fParams = [$m['booking_id'], $tenant_id];
        if ($dateFrom) { $fSql .= " AND ful.created_at >= ?"; $fParams[] = $dateFrom . ' 00:00:00'; }
        if ($dateTo)   { $fSql .= " AND ful.created_at <= ?"; $fParams[] = $dateTo . ' 23:59:59'; }
        $fSql .= " LIMIT 1";
        $fStmt = $pdo->prepare($fSql);
        $fStmt->execute($fParams);
        if ($fStmt->fetch()) {
            $filtered[] = $m;
        }
    }
    $data['members'] = $filtered;
    $data['cost_total'] = 0; $data['sold_total'] = 0; $data['profit_total'] = 0;
    foreach ($data['members'] as $m) {
        $data['cost_total'] += $m['cost_total'] ?? 0;
        $data['sold_total'] += $m['sold_total'] ?? 0;
        $data['profit_total'] += $m['profit'] ?? 0;
    }
    $data['cost_total'] = round($data['cost_total'], 2);
    $data['sold_total'] = round($data['sold_total'], 2);
    $data['profit_total'] = round($data['profit_total'], 2);
}

$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);
$scopeTitle = translate_name($data['title_name'] ?? '', $docLanguage);

foreach ($data['members'] as &$m) {
    $m['name'] = translate_name($m['name'] ?? '', $docLanguage);
    $m['fname'] = translate_name($m['fname'] ?? '', $docLanguage);
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
        'brn' => 'BRN',
        'client' => 'کلاینت',
        'family' => 'فامیل',
        'group_name' => 'گروپ',
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
        'brn' => 'BRN',
        'client' => 'کلاینت',
        'family' => 'کورنۍ',
        'group_name' => 'ګروپ',
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
        'brn' => 'BRN',
        'client' => 'Client',
        'family' => 'Family',
        'group_name' => 'Group',
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
    $currencies[strtoupper((string)($m['currency'] ?: 'USD'))] = true;
}
$currencyText = implode(', ', array_keys($currencies)) ?: 'USD';
$fmt = function ($v) {
    return number_format((float)$v, 2);
};

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft($docLanguage !== 'en');
$sheet->setTitle('Profit Report');

$headers = [$L['col_s'], $L['col_name'], $L['col_fname'], $L['col_passport'], $L['col_services'], $L['col_cost'], $L['col_sold'], $L['col_discount'], $L['col_profit']];
$lastCol = 'I';

$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->setCellValue('A1', $L['doc_title']);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->setCellValue('A2', $L['subtitle']);
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->setCellValue('A3', $agencyName);
$sheet->mergeCells('A4:' . $lastCol . '4');
$sheet->setCellValue('A4', $L['scope_label'] . ': ' . $scopeLabel . ' — ' . $scopeTitle . ' | ' . $L['members'] . ': ' . count($data['members']) . ' | ' . $today);

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A3')->getFont()->setSize(10);
$sheet->getStyle('A4')->getFont()->setSize(10);
foreach (['A1', 'A2', 'A3', 'A4'] as $c) {
    $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
}

$headerRow = 6;
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . $headerRow, $h);
}
$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5EDD6');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$row = $headerRow + 1;
$i = 0;

// Detect multi-group
$distinctGroups = [];
foreach ($data['members'] as $m) {
    $gid = $m['group_id'] ?? 0;
    if ($gid && !isset($distinctGroups[$gid])) {
        $distinctGroups[$gid] = $m['group_name'] ?? $m['group_number'] ?? ('#'.$gid);
    }
}
$isMultiGroup = count($distinctGroups) > 1;

// Build group → client → family hierarchy
$byGroup = [];
foreach ($data['members'] as $m) {
    $groupKey = $isMultiGroup ? ($m['group_id'] ?? 0) : '_single';
    $clientKey = $m['client_name'] ?? '—';
    $familyKey = $m['head_of_family'] ?? '—';
    $byGroup[$groupKey][$clientKey][$familyKey][] = $m;
}

$groupHeaderColor = '374151';
$clientHeaderColor = 'DBEAFE';
$familyHeaderColor = 'F3F4F6';
$familyTotalColor = 'FEF3C7';
$grandTotalColor = 'E5E7EB';

foreach ($byGroup as $groupKey => $clients) {
    // Group header row
    if ($isMultiGroup) {
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, ($L['group_name'] ?? 'Group') . ': ' . $distinctGroups[$groupKey]);
        $sheet->getStyle('A' . $row . ':I' . $row)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $row . ':I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($groupHeaderColor);
        $sheet->getStyle('A' . $row . ':I' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row . ':I' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $row++;
    }

    foreach ($clients as $clientName => $families) {
        // Client header row
        $sheet->mergeCells('A' . $row . ':I' . $row);
        $sheet->setCellValue('A' . $row, ($L['client'] ?? 'Client') . ': ' . $clientName);
        $sheet->getStyle('A' . $row . ':I' . $row)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $row . ':I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($clientHeaderColor);
        $sheet->getStyle('A' . $row . ':I' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row . ':I' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $row++;

        foreach ($families as $familyName => $fmembers) {
            // Family header row
            $famMembers = 0; $famExtra = 0;
            foreach ($fmembers as $fm) {
                if (!empty($fm['is_extra_bed']) || !empty($fm['is_extra_transport'])) { $famExtra++; } else { $famMembers++; }
            }
            $extraLabel = $famExtra > 0 ? ' + ' . $famExtra . ' extra' : '';
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $sheet->setCellValue('A' . $row, ($L['family'] ?? 'Family') . ': ' . $familyName . ' (' . $famMembers . ' ' . $L['members'] . $extraLabel . ')');
            $sheet->getStyle('A' . $row . ':I' . $row)->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle('A' . $row . ':I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($familyHeaderColor);
            $sheet->getStyle('A' . $row . ':I' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $row . ':I' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $row++;

            // Member rows
            foreach ($fmembers as $m) {
                $i++;
                $isNeg = $m['profit'] < 0;
                $svcLines = [];
                foreach ($m['services'] as $s) {
                    $svcLines[] = $s['label'] . ' — ' . $fmt($s['cost']);
                }
                if ($m['brn_cost'] > 0) {
                    $svcLines[] = $L['brn'] . ' — ' . $fmt($m['brn_cost']);
                }
                $svcText = implode("\n", $svcLines);

                $sheet->setCellValue('A' . $row, $i);
                $sheet->setCellValue('B' . $row, $m['name'] ?? '');
                $sheet->setCellValue('C' . $row, $m['fname'] ?? '');
                $sheet->setCellValue('D' . $row, $m['passport_number'] ?? '');
                $sheet->setCellValue('E' . $row, $svcText);
                $sheet->setCellValue('F' . $row, $fmt($m['cost_total']));
                $sheet->setCellValue('G' . $row, $fmt($m['sold_total']));
                $sheet->setCellValue('H' . $row, $fmt((float)($m['discount'] ?? 0)));
                $sheet->setCellValue('I' . $row, $fmt($m['profit']));

                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle('I' . $row)->getFont()->setBold(true);
                $sheet->getStyle('I' . $row)->getFont()->getColor()->setRGB($isNeg ? 'B91C1C' : '15803D');
                $row++;
            }

            // Family subtotal
            $famCost = 0; $famSold = 0; $famProfit = 0;
            foreach ($fmembers as $fm) {
                $famCost += $fm['cost_total'] ?? 0;
                $famSold += $fm['sold_total'] ?? 0;
                $famProfit += $fm['profit'] ?? 0;
            }
            $isNegFam = $famProfit < 0;
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $sheet->setCellValue('A' . $row, '  ' . $familyName . ' — Subtotal');
            $sheet->setCellValue('F' . $row, $fmt($famCost));
            $sheet->setCellValue('G' . $row, $fmt($famSold));
            $sheet->setCellValue('H' . $row, '0.00');
            $sheet->setCellValue('I' . $row, $fmt($famProfit));
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($familyTotalColor);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('I' . $row)->getFont()->getColor()->setRGB($isNegFam ? 'B91C1C' : '15803D');
            $row++;
        }
    }
}

$isNegGrand = $data['profit_total'] < 0;
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->setCellValue('A' . $row, $L['grand_total'] . ' (' . count($data['members']) . ' ' . $L['members'] . ')');
$sheet->setCellValue('F' . $row, $fmt($data['cost_total']));
$sheet->setCellValue('G' . $row, $fmt($data['sold_total']));
$sheet->setCellValue('H' . $row, '0.00');
$sheet->setCellValue('I' . $row, $fmt($data['profit_total']));
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('I' . $row)->getFont()->getColor()->setRGB($isNegGrand ? 'B91C1C' : '15803D');

$widths = [6, 26, 22, 18, 40, 12, 12, 12, 12];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
}
$sheet->getRowDimension($headerRow)->setRowHeight(30);
$sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastCol . $row)->getAlignment()->setWrapText(true);
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'profit_report_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)($scope . '_' . $scopeId)) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');