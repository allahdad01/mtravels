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

$data = profit_report_load($pdo, $tenant_id, $branch_id, $scope, $scopeId);
if ($data === null) {
    die('Invalid request: scope not found');
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
foreach ($data['members'] as $m) {
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