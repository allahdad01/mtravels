<?php
// Excel Template Generator for Tenant Data Import

// Prevent any output before headers
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';
// Include security module
require_once 'security.php';
// Enforce authentication
enforce_auth();

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Fetch allowed features for this tenant
$allowed_features = [];
if ($tenant_id) {
    $query = "
        SELECT p.features
        FROM tenant_subscriptions ts
        JOIN plans p ON ts.plan_id = p.id
        WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trial')
        ORDER BY ts.start_date DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $allowed_features = json_decode($row['features'], true) ?? [];
    }
}

// Helper function to check if a feature is allowed
function hasFeature($feature, $allowed_features) {
    return in_array($feature, $allowed_features);
}

// Include PhpSpreadsheet
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Clear any output buffers
ob_clean();

try {
    // Single-sheet mode
    $onlySheet = $_GET['sheet'] ?? null;
    $includeSheet = function($name) use ($onlySheet) {
        return !$onlySheet || $name === $onlySheet;
    };

    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();

    $suffix = $onlySheet ? ' - ' . $onlySheet : '';
    $filename = $onlySheet ? str_replace(' ', '_', strtolower($onlySheet)) . '_template.xlsx' : 'tenant_data_import_template.xlsx';

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Travel Agency System')
        ->setLastModifiedBy('Travel Agency System')
        ->setTitle('Data Import Template' . $suffix)
        ->setSubject('Excel Template for Data Import' . $suffix)
        ->setDescription('Template for importing ' . ($onlySheet ?: 'all') . ' tenant data')
        ->setKeywords('import template excel data')
        ->setCategory('Templates');

    // Define styles
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
    ];

    $dataStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP]
    ];

    $noteStyle = [
        'font' => ['italic' => true, 'color' => ['rgb' => '666666'], 'size' => 10],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFF2CC']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['wrapText' => true]
    ];

    // Create Instructions sheet
    $instructionsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Instructions');
    $spreadsheet->addSheet($instructionsSheet, 0);

    $instructionsSheet->setCellValue('A1', 'DATA IMPORT TEMPLATE - INSTRUCTIONS');
    $instructionsSheet->mergeCells('A1:G1');
    $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $instructionsSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $instructions = [
        ['INSTRUCTIONS FOR USING THIS TEMPLATE'],
        [''],
        ['1. Each sheet represents a different data type that can be imported'],
        ['2. Do NOT modify the column headers in row 1 of each sheet'],
        ['3. Fill in your data starting from row 2 onwards'],
        ['4. Leave cells empty if data is not available'],
        ['5. Use the exact format specified for dates and numbers'],
        [''],
        ['IMPORTANT NOTES:'],
        ['- Dates should be in YYYY-MM-DD format (e.g., 2024-01-15)'],
        ['- Currency values should be numbers only (no currency symbols)'],
        ['- Required fields are marked with * in the column headers'],
        ['- Text fields have a maximum length limit'],
        ['- Boolean values should be 0 or 1'],
        [''],
        ['NAME vs ID HANDLING:'],
        ['- For Supplier Name, Client Name, Account Name, Family Name: Enter the actual NAMES'],
        ['- The system will automatically find or create these entities by name'],
        ['- You do NOT need to know or enter database IDs - just use the names'],
        ['- If an entity with that name doesn\'t exist, it will be created automatically'],
        [''],
        ['SHEET DESCRIPTIONS:'],
        [''],
        ['Ticket Bookings: Regular ticket sales data'],
        ['Ticket Refunds: Refunded ticket transactions'],
        ['Ticket Date Changes: Date change requests and penalties'],
        ['Ticket Weights: Extra baggage weight charges'],
        ['Ticket Reservations: Reserved tickets not yet confirmed'],
        ['Visa Applications: Visa processing applications'],
        ['Hotel Bookings: Hotel reservation data'],
        ['Families: Family/group information for Umrah'],
        ['Umrah Bookings: Umrah pilgrimage bookings'],
        [''],
        ['After filling the data, save the file and upload it through the import page.']
    ];

    $row = 3;
    foreach ($instructions as $instruction) {
        if (is_array($instruction)) {
            $instructionsSheet->setCellValue('A' . $row, $instruction[0]);
            $instructionsSheet->getStyle('A' . $row)->getFont()->setBold(true);
        } else {
            $instructionsSheet->setCellValue('A' . $row, $instruction);
        }
        $row++;
    }

    $instructionsSheet->getColumnDimension('A')->setWidth(80);
    $instructionsSheet->getStyle('A3:A' . ($row-1))->getAlignment()->setWrapText(true);

    // Create Ticket Bookings sheet (only if feature enabled)
    if (hasFeature('ticket_bookings', $allowed_features) && $includeSheet('Ticket Bookings')) {
        $ticketBookingsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ticket Bookings');
        $spreadsheet->addSheet($ticketBookingsSheet);
    } else {
        $ticketBookingsSheet = null;
    }

    if ($ticketBookingsSheet !== null) {
        $headers = [
            'PNR*', 'Title', 'Passenger Name*', 'Phone', 'Gender', 'Origin*', 'Destination*',
            'Trip Type', 'Airline*', 'Issue Date*', 'Departure Date*', 'Currency', 'Price',
            'Sold Amount*', 'Profit', 'Supplier Name*', 'Sold To Name*', 'Paid To Name*',
            'Status', 'Description'
        ];

        $ticketBookingsSheet->fromArray([$headers], NULL, 'A1');
        $ticketBookingsSheet->getStyle('A1:T1')->applyFromArray($headerStyle);

        // Add sample data and notes
        $sampleData = [
            ['ABC123', 'Mr', 'John Doe', '+1234567890', 'Male', 'New York', 'London', 'one_way', 'British Airways', '2024-01-15', '2024-01-20', 'USD', 500.00, 550.00, 50.00, 'ABC Travel', 'Client A', 'Main Account', 'Booked', 'Sample booking'],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['NOTES:', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['- PNR: Passenger Name Record number', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['- Trip Type: one_way or round_trip', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['- Status options: Booked, Paid, Date Changed, Refunded', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']
        ];

        $ticketBookingsSheet->fromArray($sampleData, NULL, 'A2');
        $ticketBookingsSheet->getStyle('A4:T7')->applyFromArray($noteStyle);

        // Set column widths
        $colWidths = [15, 10, 20, 15, 10, 15, 15, 12, 20, 12, 12, 8, 12, 12, 12, 20, 20, 20, 15, 30];
        foreach (range('A', 'T') as $index => $col) {
            $ticketBookingsSheet->getColumnDimension($col)->setWidth($colWidths[$index]);
        }
    }

    // Create Ticket Refunds sheet (only if feature enabled)
    if (hasFeature('refunded_tickets', $allowed_features) && $includeSheet('Ticket Refunds')) {
        $ticketRefundsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ticket Refunds');
        $spreadsheet->addSheet($ticketRefundsSheet);

        $refundHeaders = [
            'PNR*', 'Title', 'Passenger Name*', 'Phone', 'Gender', 'Origin*', 'Destination*',
            'Airline*', 'Issue Date*', 'Departure Date*', 'Currency', 'Sold Amount*',
            'Base Amount', 'Supplier Penalty', 'Service Penalty', 'Refund to Passenger*',
            'Supplier Name*', 'Sold To Name*', 'Paid To Name*', 'Status', 'Remarks'
        ];

        $ticketRefundsSheet->fromArray([$refundHeaders], NULL, 'A1');
        $ticketRefundsSheet->getStyle('A1:U1')->applyFromArray($headerStyle);

        $refundSample = [
            ['ABC123', 'Mr', 'John Doe', '+1234567890', 'Male', 'New York', 'London', 'British Airways', '2024-01-15', '2024-01-20', 'USD', 550.00, 500.00, 50.00, 25.00, 475.00, 'ABC Travel', 'Client A', 'Main Account', 'Refunded', 'Customer requested refund'],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['NOTES:', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['- Refund to Passenger = Sold Amount - (Supplier Penalty + Service Penalty)', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']
        ];

        $ticketRefundsSheet->fromArray($refundSample, NULL, 'A2');
        $ticketRefundsSheet->getStyle('A4:U5')->applyFromArray($noteStyle);

        $refundWidths = [15, 10, 20, 15, 10, 15, 15, 20, 12, 12, 8, 12, 12, 15, 15, 18, 20, 20, 20, 15, 30];
        foreach (range('A', 'U') as $index => $col) {
            $ticketRefundsSheet->getColumnDimension($col)->setWidth($refundWidths[$index]);
        }
    } else {
        $ticketRefundsSheet = null;
    }

    // Create Ticket Date Changes sheet (only if feature enabled)
    if (hasFeature('date_change_tickets', $allowed_features) && $includeSheet('Ticket Date Changes')) {
        $dateChangesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ticket Date Changes');
        $spreadsheet->addSheet($dateChangesSheet);

        $dateChangeHeaders = [
            'PNR*', 'Title', 'Passenger Name*', 'Phone', 'Gender', 'Origin*', 'Destination*',
            'Airline*', 'Issue Date*', 'Departure Date*', 'Currency', 'Sold Amount*',
            'Base Amount', 'Supplier Penalty', 'Service Penalty', 'Supplier Name*',
            'Sold To Name*', 'Paid To Name*', 'Status', 'Remarks'
        ];

        $dateChangesSheet->fromArray([$dateChangeHeaders], NULL, 'A1');
        $dateChangesSheet->getStyle('A1:T1')->applyFromArray($headerStyle);
    } else {
        $dateChangesSheet = null;
    }

    // Create Ticket Weights sheet (only if feature enabled)
    if (hasFeature('ticket_weights', $allowed_features) && $includeSheet('Ticket Weights')) {
        $weightsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ticket Weights');
        $spreadsheet->addSheet($weightsSheet);

        $weightHeaders = [
            'PNR*', 'Passenger Name*', 'Weight (kg)*', 'Base Price', 'Sold Price*', 'Profit',
            'Currency', 'Date*', 'Remarks', 'Supplier Name*', 'Client Name*', 'Account Name*'
        ];

        $weightsSheet->fromArray([$weightHeaders], NULL, 'A1');
        $weightsSheet->getStyle('A1:L1')->applyFromArray($headerStyle);
    } else {
        $weightsSheet = null;
    }

    // Create Ticket Reservations sheet (only if feature enabled)
    if (hasFeature('ticket_reservations', $allowed_features) && $includeSheet('Ticket Reservations')) {
        $reservationsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Ticket Reservations');
        $spreadsheet->addSheet($reservationsSheet);

        $reservationHeaders = [
            'PNR*', 'Title', 'Passenger Name*', 'Phone', 'Gender', 'Origin*', 'Destination*',
            'Trip Type', 'Airline*', 'Issue Date*', 'Departure Date*', 'Currency', 'Price',
            'Sold Amount*', 'Profit', 'Supplier Name*', 'Sold To Name*', 'Paid To Name*',
            'Status', 'Description'
        ];

        $reservationsSheet->fromArray([$reservationHeaders], NULL, 'A1');
        $reservationsSheet->getStyle('A1:T1')->applyFromArray($headerStyle);
    } else {
        $reservationsSheet = null;
    }

    // Create Visa Applications sheet (only if feature enabled)
    if (hasFeature('visa_applications', $allowed_features) && $includeSheet('Visa Applications')) {
        $visaSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Visa Applications');
        $spreadsheet->addSheet($visaSheet);

        $visaHeaders = [
            'Passport Number*', 'Title', 'Applicant Name*', 'Gender', 'Country*', 'Visa Type*',
            'Receive Date*', 'Applied Date', 'Issued Date', 'Base Amount', 'Sold Amount*',
            'Profit', 'Currency', 'Status', 'Supplier Name*', 'Client Name*', 'Account Name*', 'Remarks'
        ];

        $visaSheet->fromArray([$visaHeaders], NULL, 'A1');
        $visaSheet->getStyle('A1:R1')->applyFromArray($headerStyle);
    } else {
        $visaSheet = null;
    }

    // Create Hotel Bookings sheet (only if feature enabled)
    if (hasFeature('hotel_bookings', $allowed_features) && $includeSheet('Hotel Bookings')) {
        $hotelSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Hotel Bookings');
        $spreadsheet->addSheet($hotelSheet);

        $hotelHeaders = [
            'Order ID*', 'Title', 'First Name*', 'Last Name*', 'Gender', 'Contact No',
            'Issue Date*', 'Check-in Date*', 'Check-out Date*', 'Accommodation Details',
            'Currency', 'Base Amount', 'Sold Amount*', 'Profit', 'Supplier Name*',
            'Client Name*', 'Account Name*', 'Remarks'
        ];

        $hotelSheet->fromArray([$hotelHeaders], NULL, 'A1');
        $hotelSheet->getStyle('A1:R1')->applyFromArray($headerStyle);
    } else {
        $hotelSheet = null;
    }

    // Create Families sheet (only if feature enabled)
    if (hasFeature('umrah_bookings', $allowed_features) && $includeSheet('Families')) {
        $familiesSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Families');
        $spreadsheet->addSheet($familiesSheet);

        $familyHeaders = [
            'Head of Family*', 'Contact', 'Address', 'Province', 'District',
            'Total Members', 'Package Type', 'Location', 'Tazmin', 'Visa Status',
            'Total Price', 'Total Paid', 'Total Paid to Bank', 'Total Due'
        ];

        $familiesSheet->fromArray([$familyHeaders], NULL, 'A1');
        $familiesSheet->getStyle('A1:N1')->applyFromArray($headerStyle);
    } else {
        $familiesSheet = null;
    }

    // Create Umrah Bookings sheet (only if feature enabled)
    if (hasFeature('umrah_bookings', $allowed_features) && $includeSheet('Umrah Bookings')) {
        $umrahSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Umrah Bookings');
        $spreadsheet->addSheet($umrahSheet);

        $umrahHeaders = [
            'Name*', 'Passport Number*', 'Date of Birth', 'Entry Date*',
            'Flight Date', 'Return Date', 'Duration', 'Room Type', 'Price',
            'Sold Price*', 'Profit', 'Received Bank Payment', 'Bank Receipt Number',
            'Paid', 'Due', 'Currency', 'Head of Family*', 'Remarks',
            'Supplier Name*', 'Client Name*', 'Account Name*', 'Service Type'
        ];

        $umrahSheet->fromArray([$umrahHeaders], NULL, 'A1');
        $umrahSheet->getStyle('A1:X1')->applyFromArray($headerStyle);
    } else {
        $umrahSheet = null;
    }

    // Set column widths for all sheets (filter out null sheets)
    $sheets = array_filter([$ticketBookingsSheet, $ticketRefundsSheet, $dateChangesSheet, $weightsSheet,
               $reservationsSheet, $visaSheet, $hotelSheet, $familiesSheet, $umrahSheet], 
               function($sheet) { return $sheet !== null; });

    foreach ($sheets as $sheet) {
        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Remove the default "Worksheet" sheet that Spreadsheet creates
    $defaultSheet = $spreadsheet->getSheetByName('Worksheet');
    if ($defaultSheet !== null) {
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($defaultSheet));
    }

    // Set active sheet to Instructions
    $spreadsheet->setActiveSheetIndex(0);

    // Create Excel file
    $writer = new Xlsx($spreadsheet);

    // Clear any buffered output and set headers for download
    ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // Save to output
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo "Error generating template: " . $e->getMessage();
}
?>