<?php
require_once '../../includes/db.php';
require_once '../../admin/security.php';
require_once '../../includes/language_helpers.php';
require_once '../../vendor/autoload.php';
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

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
$docLanguage = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari', 'en']) ? $_GET['language'] : 'dari';
$serviceTypes = [];
if (!empty($_GET['service_types'])) {
    $raw = explode(',', $_GET['service_types']);
    $allowed = ['visa', 'hotel', 'transport', 'flight', 'meal', 'ziyarat'];
    foreach ($raw as $st) {
        $st = strtolower(trim($st));
        if (in_array($st, $allowed)) $serviceTypes[] = $st;
    }
}

$data = service_report_load($pdo, $tenant_id, $branch_id, $dateFrom, $dateTo, $serviceTypes);
if ($data === null || empty($data['members'])) {
    die('No data found');
}

$agencyName = translate_name($settings['agency_name'] ?? '', $docLanguage);

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
        'doc_title' => 'راپور خدمات',
        'date_range' => 'دوره زمانی',
        'col_s' => 'شماره',
        'col_name' => 'نام',
        'col_fname' => 'ولد',
        'col_passport' => 'شماره پاسپورت',
        'col_services' => 'خدمات',
        'col_cost' => 'قیمت خرید',
        'grand_total' => 'مجموع کلی',
        'members' => 'معتمر',
        'client' => 'کلاینت',
        'family' => 'فامیل',
        'extra_beds' => 'بسترهای اضافی',
        'generated_on' => 'تاریخ ایجاد',
    ],
    'ps' => [
        'doc_title' => 'د خدمتونو راپور',
        'date_range' => 'د وخت محدوده',
        'col_s' => 'شمېره',
        'col_name' => 'نوم',
        'col_fname' => 'پلار نوم',
        'col_passport' => 'د پاسپورټ شمېره',
        'col_services' => 'خدمتونه',
        'col_cost' => 'د پیرود بیه',
        'grand_total' => 'ټول مجموع',
        'members' => 'معتمر',
        'client' => 'کلاینت',
        'family' => 'کورنۍ',
        'extra_beds' => 'اضافي بستر',
        'generated_on' => 'د رامنځته کېدو نیټه',
    ],
    'en' => [
        'doc_title' => 'Service Report',
        'date_range' => 'Date Range',
        'col_s' => '#',
        'col_name' => 'Name',
        'col_fname' => 'Father',
        'col_passport' => 'Passport #',
        'col_services' => 'Services',
        'col_cost' => 'Cost',
        'grand_total' => 'Grand Total',
        'members' => 'members',
        'client' => 'Client',
        'family' => 'Family',
        'extra_beds' => 'Extra Beds',
        'generated_on' => 'Generated on',
    ],
];
$L = $langLabels[$docLanguage];

$today = date('Y-m-d');
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
$sheet->setTitle('Service Report');

$headers = [$L['col_s'], $L['col_name'], $L['col_fname'], $L['col_passport'], $L['col_services'], $L['col_cost']];
$lastCol = 'F';

$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->setCellValue('A1', $agencyName);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->setCellValue('A2', $L['doc_title']);
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->setCellValue('A3', $L['date_range'] . ': ' . $data['date_from'] . ' — ' . $data['date_to']);
$sheet->mergeCells('A4:' . $lastCol . '4');
$sheet->setCellValue('A4', $L['generated_on'] . ': ' . $today);

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A3')->getFont()->setSize(10);
$sheet->getStyle('A4')->getFont()->setSize(9);
foreach (['A1', 'A2', 'A3', 'A4'] as $c) {
    $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$headerRow = 6;
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . $headerRow, $h);
}
$headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5EDD6');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$row = $headerRow + 1;
$i = 0;
$totalRegular = 0;
$totalExtra = 0;

// Group by client → family
$byClient = [];
foreach ($data['members'] as $m) {
    $clientKey = $m['client_name'] ?? '—';
    $familyKey = $m['head_of_family'] ?? '—';
    $byClient[$clientKey][$familyKey][] = $m;
}

foreach ($byClient as $clientName => $families) {
    // Client header
    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
    $sheet->setCellValue('A' . $row, $L['client'] . ': ' . $clientName);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;

    foreach ($families as $familyName => $fmembers) {
        $famCost = 0;
        $famRegular = 0;
        $famExtra = 0;
        foreach ($fmembers as $fm) {
            $famCost += $fm['cost_total'];
            if (!empty($fm['is_extra_bed']) || !empty($fm['is_extra_transport'])) { $famExtra++; } else { $famRegular++; }
        }

        // Family header
        $extraLabel = $famExtra > 0 ? ' + ' . $famExtra . ' extra' : '';
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $L['family'] . ': ' . $familyName . ' (' . $famRegular . ' ' . $L['members'] . $extraLabel . ')');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        // Member rows
        foreach ($fmembers as $m) {
            $i++;
            $extraTag = (!empty($m['is_extra_bed']) || !empty($m['is_extra_transport'])) ? ' (extra)' : '';
            $sheet->setCellValue('A' . $row, $i);
            $sheet->setCellValue('B' . $row, $m['name'] . $extraTag);
            $sheet->setCellValue('C' . $row, $m['fname']);
            $sheet->setCellValue('D' . $row, $m['passport_number']);

            // Services
            $svcText = '';
            if (!empty($m['services'])) {
                $svcLines = [];
                foreach ($m['services'] as $s) {
                    $svcLines[] = $s['label'] . ' — ' . $fmt($s['cost']);
                }
                $svcText = implode("\n", $svcLines);
            } else {
                $svcText = '—';
            }
            $sheet->setCellValue('E' . $row, $svcText);
            $sheet->setCellValue('F' . $row, $fmt($m['cost_total']));

            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('E' . $row)->getAlignment()->setWrapText(true);
            $row++;
        }

        // Family subtotal
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->setCellValue('A' . $row, '  ' . $familyName . ' — ' . $L['grand_total']);
        $sheet->setCellValue('F' . $row, $fmt($famCost));
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        $totalRegular += $famRegular;
        $totalExtra += $famExtra;
    }
}

// Grand total
$memberLabel = $totalRegular . ' ' . $L['members'] . ($totalExtra > 0 ? ' + ' . $totalExtra . ' ' . $L['extra_beds'] : '');
$sheet->mergeCells('A' . $row . ':E' . $row);
$sheet->setCellValue('A' . $row, $L['grand_total'] . ' (' . $memberLabel . ')');
$sheet->setCellValue('F' . $row, $fmt($data['cost_total']));
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$widths = [6, 18, 14, 14, 30, 14];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
}

$sheet->getRowDimension($headerRow)->setRowHeight(30);
$sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastCol . $row)->getAlignment()->setWrapText(true);
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'service_report_' . preg_replace('/[^A-Za-z0-9_-]/', '', $data['date_from'] . '_' . $data['date_to']) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
