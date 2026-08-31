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
        'grand_total'     => 'مجموع کلی',
        'total_cost'      => 'مجموع قیمت خرید',
        'total_sold'      => 'مجموع قیمت فروش',
        'total_profit'    => 'مجموع لابسته',
        'group_name'      => 'ګروپ',
        'family_name'     => 'فامیل',
        'head_of_family'  => 'سرفامیل',
        'client'          => 'کلاینت',
        'members'         => 'معتمر',
        'total_members'   => 'مجموع معتمرین',
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
        'grand_total'     => 'ټول مجموع',
        'total_cost'      => 'ټول د پیرود بیه',
        'total_sold'      => 'ټول د پلور بیه',
        'total_profit'    => 'ټوله ګټه',
        'group_name'      => 'ګروپ',
        'family_name'     => 'کورنۍ',
        'head_of_family'  => 'د کورنۍ سرلیک',
        'client'          => 'کلاینت',
        'members'         => 'معتمر',
        'total_members'   => 'ټول معتمر',
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
        'grand_total'     => 'Grand Total',
        'total_cost'      => 'Total Cost',
        'total_sold'      => 'Total Sold',
        'total_profit'    => 'Total Profit',
        'group_name'      => 'Group',
        'family_name'     => 'Family',
        'head_of_family'  => 'Head of Family',
        'client'          => 'Client',
        'members'         => 'members',
        'total_members'   => 'Total Members',
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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft($docLanguage !== 'en');
$sheet->setTitle('Service Report');

if ($groupBy === 'service') {
    $headers = [$L['col_s'], $L['col_service'], $L['col_members'], $L['col_cost'], $L['col_profit']];
    $lastCol = 'E';

    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->setCellValue('A1', $L['doc_title']);
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->setCellValue('A2', $agencyName);
    $sheet->mergeCells('A3:' . $lastCol . '3');
    $sheet->setCellValue('A3', $L['date_range'] . ': ' . $data['date_from'] . ' — ' . $data['date_to'] . ' | ' . $L['group_by'] . ': ' . $groupByLabel);

    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A3')->getFont()->setSize(10);
    foreach (['A1', 'A2', 'A3'] as $c) {
        $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $headerRow = 5;
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
    foreach ($data['services'] as $svc) {
        $i++;
        $profit = $data['summary']['total_sold'] > 0 ? round(($svc['total_cost'] / $data['summary']['total_sold']) * $data['summary']['total_sold'], 2) - $svc['total_cost'] : -$svc['total_cost'];
        $sheet->setCellValue('A' . $row, $i);
        $sheet->setCellValue('B' . $row, ucfirst($svc['service_name']));
        $sheet->setCellValue('C' . $row, $svc['member_count']);
        $sheet->setCellValue('D' . $row, $fmt($svc['total_cost']));
        $sheet->setCellValue('E' . $row, $fmt($profit));
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $row++;
    }

    // Grand total
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('A' . $row, $L['grand_total'] . ' (' . $data['summary']['total_members'] . ' ' . $L['members'] . ')');
    $sheet->setCellValue('C' . $row, $data['summary']['total_members']);
    $sheet->setCellValue('D' . $row, $fmt($data['summary']['total_cost']));
    $sheet->setCellValue('E' . $row, $fmt($data['summary']['total_profit']));
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $widths = [6, 24, 12, 14, 14];
    foreach ($widths as $i => $w) {
        $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
    }

} elseif ($groupBy === 'group') {
    $headers = [$L['col_s'], $L['group_name'], $L['col_service'], $L['col_members'], $L['col_cost']];
    $lastCol = 'E';

    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->setCellValue('A1', $L['doc_title']);
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->setCellValue('A2', $agencyName);
    $sheet->mergeCells('A3:' . $lastCol . '3');
    $sheet->setCellValue('A3', $L['date_range'] . ': ' . $data['date_from'] . ' — ' . $data['date_to'] . ' | ' . $L['group_by'] . ': ' . $groupByLabel);

    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A3')->getFont()->setSize(10);
    foreach (['A1', 'A2', 'A3'] as $c) {
        $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $headerRow = 5;
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
    foreach ($data['details'] as $grp) {
        $i++;
        // Group header row
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, '#' . $grp['group_number'] . ' — ' . $grp['group_name'] . ' (' . $grp['member_count'] . ' ' . $L['members'] . ')');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        foreach ($grp['services'] as $svc) {
            $sheet->setCellValue('A' . $row, '');
            $sheet->setCellValue('B' . $row, '');
            $sheet->setCellValue('C' . $row, ucfirst($svc['service_name']));
            $sheet->setCellValue('D' . $row, $svc['member_count']);
            $sheet->setCellValue('E' . $row, $fmt($svc['total_cost']));
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        // Group subtotal
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->setCellValue('A' . $row, '  Subtotal');
        $sheet->setCellValue('D' . $row, $grp['member_count']);
        $sheet->setCellValue('E' . $row, $fmt($grp['total_cost']));
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
    }

    // Grand total
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('A' . $row, $L['grand_total']);
    $sheet->setCellValue('D' . $row, $data['summary']['total_members']);
    $sheet->setCellValue('E' . $row, $fmt($data['summary']['total_cost']));
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $widths = [6, 28, 22, 12, 14];
    foreach ($widths as $i => $w) {
        $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
    }

} elseif ($groupBy === 'family') {
    $headers = [$L['col_s'], $L['family_name'], $L['group_name'], $L['col_service'], $L['col_members'], $L['col_cost']];
    $lastCol = 'F';

    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->setCellValue('A1', $L['doc_title']);
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->setCellValue('A2', $agencyName);
    $sheet->mergeCells('A3:' . $lastCol . '3');
    $sheet->setCellValue('A3', $L['date_range'] . ': ' . $data['date_from'] . ' — ' . $data['date_to'] . ' | ' . $L['group_by'] . ': ' . $groupByLabel);

    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A3')->getFont()->setSize(10);
    foreach (['A1', 'A2', 'A3'] as $c) {
        $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $headerRow = 5;
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
    foreach ($data['details'] as $fam) {
        $i++;
        // Family header row
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, $fam['head_of_family'] . ' — ' . $fam['group_name'] . ' (' . $fam['member_count'] . ' ' . $L['members'] . ')');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        foreach ($fam['services'] as $svc) {
            $sheet->setCellValue('A' . $row, '');
            $sheet->setCellValue('B' . $row, '');
            $sheet->setCellValue('C' . $row, '');
            $sheet->setCellValue('D' . $row, ucfirst($svc['service_name']));
            $sheet->setCellValue('E' . $row, $svc['member_count']);
            $sheet->setCellValue('F' . $row, $fmt($svc['total_cost']));
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        // Family subtotal
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('A' . $row, '  Subtotal');
        $sheet->setCellValue('E' . $row, $fam['member_count']);
        $sheet->setCellValue('F' . $row, $fmt($fam['total_cost']));
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
    }

    // Grand total
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->setCellValue('A' . $row, $L['grand_total']);
    $sheet->setCellValue('E' . $row, $data['summary']['total_members']);
    $sheet->setCellValue('F' . $row, $fmt($data['summary']['total_cost']));
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true)->setSize(10);
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $widths = [6, 24, 20, 22, 12, 14];
    foreach ($widths as $i => $w) {
        $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
    }
}

$sheet->getRowDimension($headerRow)->setRowHeight(30);
$sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastCol . $row)->getAlignment()->setWrapText(true);
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'service_report_' . $groupBy . '_' . preg_replace('/[^A-Za-z0-9_-]/', '', $data['date_from'] . '_' . $data['date_to']) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
