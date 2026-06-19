<?php
// Excel Import Handler for Tenant Data Migration
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelImportHandler {
    private $pdo;
    private $tenantId;
    private $branchId;
    private $errors = [];
    private $successCount = 0;
    private $processedSheets = [];

    public function __construct($tenantId) {
        $this->pdo = $GLOBALS['pdo'];
        $this->tenantId = $tenantId;
        $this->branchId = $_SESSION['branch_id'];
    }

    public function importFromExcel($excelFilePath, $onlySheets = []) {
        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($excelFilePath);

            // Define the expected sheets and their processing methods
            $allSheets = [
                'Ticket Bookings' => 'processTicketBookings',
                'Ticket Refunds' => 'processTicketRefunds',
                'Ticket Date Changes' => 'processTicketDateChanges',
                'Ticket Weights' => 'processTicketWeights',
                'Ticket Reservations' => 'processTicketReservations',
                'Visa Applications' => 'processVisaApplications',
                'Hotel Bookings' => 'processHotelBookings',
                'Families' => 'processFamilies',
                'Umrah Bookings' => 'processUmrahBookings'
            ];

            $sheetMappings = empty($onlySheets) ? $allSheets : array_intersect_key($allSheets, array_flip($onlySheets));

            // Process each expected sheet
            foreach ($sheetMappings as $sheetName => $methodName) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                if ($worksheet) {
                    $highestRow = $worksheet->getHighestRow();
                    if ($highestRow > 1) {
                        $this->processedSheets[] = $sheetName;
                        $this->$methodName($worksheet);
                    } else {
                        $this->errors[] = "Sheet '$sheetName' is empty, skipped";
                    }
                } else {
                    $this->errors[] = "Sheet '$sheetName' not found in Excel file";
                }
            }

            return [
                'success' => empty($this->errors),
                'errors' => $this->errors,
                'success_count' => $this->successCount,
                'processed_sheets' => $this->processedSheets
            ];

        } catch (Throwable $e) {
            $this->errors[] = "Import failed: " . $e->getMessage();
            return [
                'success' => false,
                'errors' => $this->errors,
                'success_count' => 0,
                'processed_sheets' => []
            ];
        }
    }

    public function previewImport($excelFilePath, $onlySheets = []) {
        try {
            $spreadsheet = IOFactory::load($excelFilePath);

            $allEntityColumns = [
                'Ticket Bookings' => ['suppliers' => 15, 'clients' => 16, 'accounts' => 17],
                'Ticket Refunds' => ['suppliers' => 16, 'clients' => 17, 'accounts' => 18],
                'Ticket Date Changes' => ['suppliers' => 15, 'clients' => 16, 'accounts' => 17],
                'Ticket Weights' => ['suppliers' => 9, 'clients' => 10, 'accounts' => 11],
                'Ticket Reservations' => ['suppliers' => 15, 'clients' => 16, 'accounts' => 17],
                'Visa Applications' => ['suppliers' => 14, 'clients' => 15, 'accounts' => 16],
                'Hotel Bookings' => ['suppliers' => 14, 'clients' => 15, 'accounts' => 16],
                'Families' => [],
                'Umrah Bookings' => ['suppliers' => 18, 'clients' => 19, 'accounts' => 20, 'families' => 16]
            ];

            $entityColumns = empty($onlySheets) ? $allEntityColumns : array_intersect_key($allEntityColumns, array_flip($onlySheets));

            $found = ['suppliers' => [], 'clients' => [], 'accounts' => [], 'families' => []];
            $sheets = [];
            $totalRows = 0;

            foreach ($entityColumns as $sheetName => $columns) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                if (!$worksheet) continue;

                $highestRow = $worksheet->getHighestRow();
                if ($highestRow <= 1) continue;

                $rowCount = $highestRow - 1;
                $totalRows += $rowCount;
                $sheets[] = ['name' => $sheetName, 'row_count' => $rowCount];

                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $data = [];
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                        $data[] = (string) $cellValue;
                    }

                    if ($this->shouldSkipImportRow($data)) continue;

                    foreach ($columns as $type => $colIdx) {
                        $name = trim($data[$colIdx] ?? '');
                        if ($name !== '') {
                            $found[$type][] = $name;
                        }
                    }
                }
            }

            foreach ($found as $type => $list) {
                $found[$type] = array_values(array_unique($list));
            }

            $newEntities = ['suppliers' => [], 'clients' => [], 'accounts' => [], 'families' => []];
            $existingEntities = ['suppliers' => [], 'clients' => [], 'accounts' => [], 'families' => []];

            foreach ($found['suppliers'] as $name) {
                if ($this->entityExists('suppliers', $name)) {
                    $existingEntities['suppliers'][] = $name;
                } else {
                    $newEntities['suppliers'][] = $name;
                }
            }
            foreach ($found['clients'] as $name) {
                if ($this->entityExists('clients', $name)) {
                    $existingEntities['clients'][] = $name;
                } else {
                    $newEntities['clients'][] = $name;
                }
            }
            foreach ($found['accounts'] as $name) {
                if ($this->entityExists('main_account', $name)) {
                    $existingEntities['accounts'][] = $name;
                } else {
                    $newEntities['accounts'][] = $name;
                }
            }
            foreach ($found['families'] as $name) {
                if ($this->entityExists('families', $name, 'head_of_family', 'family_id')) {
                    $existingEntities['families'][] = $name;
                } else {
                    $newEntities['families'][] = $name;
                }
            }

            return [
                'success' => true,
                'sheets' => $sheets,
                'total_rows' => $totalRows,
                'new_entities' => $newEntities,
                'existing_entities' => $existingEntities
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'errors' => ['Preview failed: ' . $e->getMessage()],
                'sheets' => [],
                'total_rows' => 0,
                'new_entities' => ['suppliers' => [], 'clients' => [], 'accounts' => [], 'families' => []],
                'existing_entities' => ['suppliers' => [], 'clients' => [], 'accounts' => [], 'families' => []]
            ];
        }
    }

    private function entityExists($table, $name, $nameColumn = 'name', $idColumn = 'id') {
        $stmt = $this->pdo->prepare("SELECT $idColumn FROM $table WHERE $nameColumn = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$name, $this->tenantId, $this->branchId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function processTicketBookings($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Skip header row (assuming row 1 is headers)
        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                    $data[] = (string) $cellValue;
                }

                if ($this->shouldSkipImportRow($data)) {
                    continue;
                }

                // Map columns to database fields
                $bookingData = [
                    'pnr' => trim($data[0] ?? ''),
                    'title' => $data[1] ?? '',
                    'passenger_name' => $data[2] ?? '',
                    'phone' => empty($data[3]) ? null : $data[3],
                    'gender' => $data[4] ?? '',
                    'origin' => $data[5] ?? '',
                    'destination' => $data[6] ?? '',
                    'trip_type' => $data[7] ?? 'one_way',
                    'airline' => $data[8] ?? '',
                    'issue_date' => $this->parseDate($data[9] ?? ''),
                    'departure_date' => $this->parseDate($data[10] ?? ''),
                    'currency' => $data[11] ?? 'USD',
                    'price' => floatval($data[12] ?? 0),
                    'sold' => floatval($data[13] ?? 0),
                    'profit' => floatval($data[14] ?? 0),
                    'supplier_name' => $data[15] ?? '',
                    'sold_to_name' => $data[16] ?? '',
                    'paid_to_name' => $data[17] ?? '',
                    'status' => $data[18] ?? 'Booked',
                    'description' => $data[19] ?? ''
                ];

                $this->insertTicketBooking($bookingData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing ticket booking row $row: " . $e->getMessage();
            }
        }
    }

    private function processTicketRefunds($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = $worksheet->rangeToArray('A' . $row . ':' . Coordinate::stringFromColumnIndex($highestColumnIndex) . $row, null, true, true, false)[0];
                $data = array_map('strval', $data);

                if ($this->shouldSkipImportRow($data)) {
                    continue;
                }

                $refundData = [
                    'pnr' => trim($data[0] ?? ''),
                    'title' => $data[1] ?? '',
                    'passenger_name' => $data[2] ?? '',
                    'phone' => empty($data[3]) ? null : $data[3],
                    'gender' => $data[4] ?? '',
                    'origin' => $data[5] ?? '',
                    'destination' => $data[6] ?? '',
                    'airline' => $data[7] ?? '',
                    'issue_date' => $this->parseDate($data[8] ?? ''),
                    'departure_date' => $this->parseDate($data[9] ?? ''),
                    'currency' => $data[10] ?? 'USD',
                    'sold' => floatval($data[11] ?? 0),
                    'base_amount' => floatval($data[12] ?? 0),
                    'supplier_penalty' => floatval($data[13] ?? 0),
                    'service_penalty' => floatval($data[14] ?? 0),
                    'refund_to_passenger' => floatval($data[15] ?? 0),
                    'supplier_name' => $data[16] ?? '',
                    'sold_to_name' => $data[17] ?? '',
                    'paid_to_name' => $data[18] ?? '',
                    'status' => $data[19] ?? 'Refunded',
                    'remarks' => $data[20] ?? '',
                    'calculation_method' => 'sold'
                ];

                $this->insertTicketRefund($refundData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing ticket refund row $row: " . $e->getMessage();
            }
        }
    }

    private function processTicketDateChanges($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                    $data[] = (string) $cellValue;
                }

                if ($this->shouldSkipImportRow($data)) {
                    continue;
                }

                $dateChangeData = [
                    'pnr' => trim($data[0] ?? ''),
                    'title' => $data[1] ?? '',
                    'passenger_name' => $data[2] ?? '',
                    'phone' => empty($data[3]) ? null : $data[3],
                    'gender' => $data[4] ?? '',
                    'origin' => $data[5] ?? '',
                    'destination' => $data[6] ?? '',
                    'airline' => $data[7] ?? '',
                    'issue_date' => $this->parseDate($data[8] ?? ''),
                    'departure_date' => $this->parseDate($data[9] ?? ''),
                    'currency' => $data[10] ?? 'USD',
                    'sold' => floatval($data[11] ?? 0),
                    'base' => floatval($data[12] ?? 0),
                    'supplier_penalty' => floatval($data[13] ?? 0),
                    'service_penalty' => floatval($data[14] ?? 0),
                    'supplier_name' => $data[15] ?? '',
                    'sold_to_name' => $data[16] ?? '',
                    'paid_to_name' => $data[17] ?? '',
                    'status' => $data[18] ?? 'Date Changed',
                    'remarks' => $data[19] ?? ''
                ];

                $this->insertTicketDateChange($dateChangeData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing ticket date change row $row: " . $e->getMessage();
            }
        }
    }

    private function processTicketWeights($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                    $data[] = (string) $cellValue;
                }

                // Skip empty rows
                if (empty(array_filter($data))) continue;

                // Skip if PNR is empty
                if (empty($data[0] ?? '')) continue;

                $weightData = [
                    'pnr' => $data[0] ?? '',
                    'passenger_name' => $data[1] ?? '',
                    'weight' => floatval($data[2] ?? 0),
                    'base_price' => floatval($data[3] ?? 0),
                    'sold_price' => floatval($data[4] ?? 0),
                    'profit' => floatval($data[5] ?? 0),
                    'currency' => $data[6] ?? 'USD',
                    'date' => $this->parseDate($data[7] ?? ''),
                    'remarks' => $data[8] ?? '',
                    'supplier_name' => $data[9] ?? '',
                    'client_name' => $data[10] ?? '',
                    'account_name' => $data[11] ?? ''
                ];

                $this->insertTicketWeight($weightData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing ticket weight row $row: " . $e->getMessage();
            }
        }
    }

    private function processTicketReservations($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                    $data[] = (string) $cellValue;
                }

                if ($this->shouldSkipImportRow($data)) {
                    continue;
                }

                $reservationData = [
                    'pnr' => trim($data[0] ?? ''),
                    'title' => $data[1] ?? '',
                    'passenger_name' => $data[2] ?? '',
                    // Normalize phone so it is never NULL in strict mode
                    'phone' => empty($data[3]) ? '' : (string) $data[3],
                    'gender' => $data[4] ?? '',
                    'origin' => $data[5] ?? '',
                    'destination' => $data[6] ?? '',
                    'trip_type' => $data[7] ?? 'one_way',
                    'airline' => $data[8] ?? '',
                    'issue_date' => $this->parseDate($data[9] ?? ''),
                    'departure_date' => $this->parseDate($data[10] ?? ''),
                    'currency' => $data[11] ?? 'USD',
                    'price' => floatval($data[12] ?? 0),
                    'sold' => floatval($data[13] ?? 0),
                    'profit' => floatval($data[14] ?? 0),
                    'supplier_name' => $data[15] ?? '',
                    'sold_to_name' => $data[16] ?? '',
                    'paid_to_name' => $data[17] ?? '',
                    'status' => $data[18] ?? 'Reserved',
                    'description' => $data[19] ?? ''
                ];
                

                $this->insertTicketReservation($reservationData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing ticket reservation row $row: " . $e->getMessage();
            }
        }
    }

    private function processVisaApplications($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                    $data[] = (string) $cellValue;
                }

                $visaData = [
                    'passport_number' => $data[0] ?? '',
                    'title' => $data[1] ?? '',
                    'applicant_name' => $data[2] ?? '',
                    'gender' => $data[3] ?? '',
                    'country' => $data[4] ?? '',
                    'visa_type' => $data[5] ?? '',
                    'receive_date' => $this->parseDate($data[6] ?? ''),
                    'applied_date' => $this->parseDate($data[7] ?? ''),
                    'issued_date' => $this->parseDate($data[8] ?? ''),
                    'base' => floatval($data[9] ?? 0),
                    'sold' => floatval($data[10] ?? 0),
                    'profit' => floatval($data[11] ?? 0),
                    'currency' => $data[12] ?? 'USD',
                    'status' => $data[13] ?? 'Pending',
                    'supplier_name' => $data[14] ?? '',
                    'client_name' => $data[15] ?? '',
                    'account_name' => $data[16] ?? '',
                    'remarks' => $data[17] ?? ''
                ];

                $this->insertVisaApplication($visaData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing visa application row $row: " . $e->getMessage();
            }
        }
    }

    private function processHotelBookings($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellAddress = Coordinate::stringFromColumnIndex($col) . $row;
                    try {
                        $value = $worksheet->getCell($cellAddress)->getCalculatedValue();
                    } catch (Throwable $e) {
                        $value = '';
                    }
                    $data[] = (string) $value;
                }

                $hotelData = [
                    'order_id' => $data[0] ?? '',
                    'title' => $data[1] ?? '',
                    'first_name' => $data[2] ?? '',
                    'last_name' => $data[3] ?? '',
                    'gender' => $data[4] ?? '',
                    'contact_no' => $data[5] ?? '',
                    'issue_date' => $this->parseDate($data[6] ?? ''),
                    'check_in_date' => $this->parseDate($data[7] ?? ''),
                    'check_out_date' => $this->parseDate($data[8] ?? ''),
                    'accommodation_details' => $data[9] ?? '',
                    'currency' => $data[10] ?? 'USD',
                    'base_amount' => floatval($data[11] ?? 0),
                    'sold_amount' => floatval($data[12] ?? 0),
                    'profit' => floatval($data[13] ?? 0),
                    'supplier_name' => $data[14] ?? '',
                    'client_name' => $data[15] ?? '',
                    'account_name' => $data[16] ?? '',
                    'remarks' => $data[17] ?? ''
                ];

                $this->insertHotelBooking($hotelData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing hotel booking row $row: " . $e->getMessage();
            }
        }
    }

    private function processFamilies($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = $worksheet->rangeToArray('A' . $row . ':' . Coordinate::stringFromColumnIndex($highestColumnIndex) . $row, null, true, true, false)[0];
                $data = array_map('strval', $data);

// Skip empty rows
if (empty(array_filter($data))) continue;

                $familyData = [
                    'head_of_family' => $data[0] ?? '',
                    'contact' => $data[1] ?? '',
                    'address' => $data[2] ?? '',
                    'province' => $data[3] ?? '',
                    'district' => $data[4] ?? '',
                    'total_members' => intval($data[5] ?? 0),
                    'package_type' => $data[6] ?? '',
                    'location' => $data[7] ?? '',
                    'tazmin' => $data[8] ?? '',
                    'visa_status' => $data[9] ?? 'Applied',
                    'total_price' => floatval($data[10] ?? 0),
                    'total_paid' => floatval($data[11] ?? 0),
                    'total_paid_to_bank' => floatval($data[12] ?? 0),
                    'total_due' => floatval($data[13] ?? 0)
                ];

                $this->insertFamily($familyData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing family row $row: " . $e->getMessage();
            }
        }
    }

    private function processUmrahBookings($worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
                    $data[] = (string) $cellValue;
                }

                if ($this->shouldSkipImportRow($data)) {
                    continue;
                }

                $umrahData = [
                    'name' => $data[0] ?? '',
                    'passport_number' => $data[1] ?? '',
                    'dob' => $this->parseDate($data[2] ?? ''),
                    'entry_date' => $this->parseDate($data[3] ?? ''),
                    'flight_date' => $this->parseDate($data[4] ?? ''),
                    'return_date' => $this->parseDate($data[5] ?? ''),
                    'duration' => $data[6] ?? '',
                    'room_type' => $data[7] ?? '',
                    'price' => floatval($data[8] ?? 0),
                    'sold_price' => floatval($data[9] ?? 0),
                    'profit' => floatval($data[10] ?? 0),
                    'received_bank_payment' => floatval($data[11] ?? 0),
                    'bank_receipt_number' => $data[12] ?? '',
                    'paid' => floatval($data[13] ?? 0),
                    'due' => floatval($data[14] ?? 0),
                    'currency' => $data[15] ?? 'USD',
                    'head_of_family' => $data[16] ?? '',
                    'remarks' => $data[17] ?? '',
                    'supplier_name' => $data[18] ?? '',
                    'client_name' => $data[19] ?? '',
                    'account_name' => $data[20] ?? '',
                    // New optional column for umrah services
                    'service_type' => isset($data[21]) && $data[21] !== '' ? $data[21] : 'all'
                ];

                $this->insertUmrahBooking($umrahData);
                $this->successCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Error processing umrah booking row $row: " . $e->getMessage();
            }
        }
    }

    // Helper methods for data insertion
    private function insertTicketBooking($data) {
        // Ensure phone is not null
        $data['phone'] = $data['phone'] ?? '';

        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['sold_to_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['paid_to_name']);

        // Get user ID (assuming admin user for imports)
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO ticket_bookings (
                tenant_id, branch_id, supplier, sold_to, paid_to, pnr, title, passenger_name,
                phone, gender, origin, destination, trip_type, airline, issue_date,
                departure_date, currency, price, sold, profit, status, description,
                created_by, created_at, updated_at, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $supplierId, $clientId, $accountId, $data['pnr'],
            $data['title'], $data['passenger_name'], $data['phone'], $data['gender'],
            $data['origin'], $data['destination'], $data['trip_type'], $data['airline'],
            $data['issue_date'], $data['departure_date'], $data['currency'],
            $data['price'], $data['sold'], $data['profit'], $data['status'],
            $data['description'], $userId
        ]);
    }

    private function insertTicketRefund($data) {
        // Ensure phone is not null
        $data['phone'] = $data['phone'] ?? '';

        // Find the ticket booking by PNR
        $ticketStmt = $this->pdo->prepare("SELECT id FROM ticket_bookings WHERE pnr = ? AND tenant_id = ? AND branch_id = ?");
        $ticketStmt->execute([$data['pnr'], $this->tenantId, $this->branchId]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new Exception("Ticket booking with PNR {$data['pnr']} not found for refund");
        }

        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['sold_to_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['paid_to_name']);

        // Get user ID
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO refunded_tickets (
                tenant_id, branch_id, ticket_id, supplier, sold_to, paid_to, pnr, title, passenger_name,
                phone, gender, origin, destination, airline, issue_date, departure_date,
                currency, sold, base, supplier_penalty, service_penalty, refund_to_passenger,
                status, remarks, calculation_method, created_by, created_at, updated_at, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $ticket['id'], $supplierId, $clientId, $accountId, $data['pnr'],
            $data['title'], $data['passenger_name'], $data['phone'], $data['gender'],
            $data['origin'], $data['destination'], $data['airline'], $data['issue_date'],
            $data['departure_date'], $data['currency'], $data['sold'], $data['base_amount'],
            $data['supplier_penalty'], $data['service_penalty'], $data['refund_to_passenger'],
            $data['status'], $data['remarks'], $data['calculation_method'], $userId
        ]);
    }

    private function insertTicketDateChange($data) {
        // Find the ticket booking by PNR
        $ticketStmt = $this->pdo->prepare("SELECT id FROM ticket_bookings WHERE pnr = ? AND tenant_id = ? AND branch_id = ?");
        $ticketStmt->execute([$data['pnr'], $this->tenantId, $this->branchId]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new Exception("Ticket booking with PNR {$data['pnr']} not found for date change");
        }

        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['sold_to_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['paid_to_name']);

        // Get user ID
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO date_change_tickets (
                tenant_id, branch_id, ticket_id, supplier, sold_to, paid_to, pnr, title, passenger_name,
                phone, gender, origin, destination, airline, issue_date, departure_date,
                currency, sold, base, supplier_penalty, service_penalty, status, remarks,
                created_by, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $ticket['id'], $supplierId, $clientId, $accountId, $data['pnr'],
            $data['title'], $data['passenger_name'], $data['phone'], $data['gender'],
            $data['origin'], $data['destination'], $data['airline'], $data['issue_date'],
            $data['departure_date'], $data['currency'], $data['sold'], $data['base'],
            $data['supplier_penalty'], $data['service_penalty'], $data['status'], $data['remarks'],
            $userId
        ]);
    }

    private function insertTicketWeight($data) {
        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['client_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['account_name']);

        // Get user ID
        $userId = $this->getImportUserId();

        // First, find the ticket booking by PNR
        $ticketStmt = $this->pdo->prepare("SELECT id FROM ticket_bookings WHERE pnr = ? AND tenant_id = ? AND branch_id = ?");
        $ticketStmt->execute([$data['pnr'], $this->tenantId, $this->branchId]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new Exception("Ticket booking with PNR {$data['pnr']} not found");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ticket_weights (
                tenant_id, branch_id, ticket_id, weight, base_price, sold_price, profit,
                remarks, created_by, created_at, updated_at, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $ticket['id'], $data['weight'], $data['base_price'],
            $data['sold_price'], $data['profit'], $data['remarks'], $userId
        ]);
    }

    private function insertTicketReservation($data) {
        // Ensure phone is not null/undefined (STRICT mode safety)
        $data['phone'] = isset($data['phone']) && $data['phone'] !== null ? (string) $data['phone'] : '';

        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['sold_to_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['paid_to_name']);

        // Get user ID
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO ticket_reservations (
                tenant_id, branch_id, supplier, sold_to, paid_to, pnr, title, passenger_name,
                phone, gender, origin, destination, trip_type, airline, issue_date,
                departure_date, currency, price, sold, profit, status,
                description, created_by, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $supplierId, $clientId, $accountId, $data['pnr'],
            $data['title'], $data['passenger_name'], $data['phone'], $data['gender'],
            $data['origin'], $data['destination'], $data['trip_type'], $data['airline'],
            $data['issue_date'], $data['departure_date'], $data['currency'],
            $data['price'], $data['sold'], $data['profit'], $data['status'],
            $data['description'], $userId
        ]);
    }

    private function insertVisaApplication($data) {
        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['client_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['account_name']);

        // Get user ID
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO visa_applications (
                tenant_id, branch_id, supplier, sold_to, paid_to, passport_number, title,
                applicant_name, gender, country, visa_type, receive_date, applied_date,
                issued_date, base, sold, profit, currency, status, remarks,
                created_by, created_at, updated_at, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $supplierId, $clientId, $accountId, $data['passport_number'],
            $data['title'], $data['applicant_name'], $data['gender'], $data['country'],
            $data['visa_type'], $data['receive_date'], $data['applied_date'],
            $data['issued_date'], $data['base'], $data['sold'], $data['profit'],
            $data['currency'], $data['status'], $data['remarks'], $userId
        ]);
    }

    private function insertHotelBooking($data) {
        // Get or create supplier
        $supplierId = $this->getOrCreateSupplier($data['supplier_name']);

        // Get or create client
        $clientId = $this->getOrCreateClient($data['client_name']);

        // Get or create main account
        $accountId = $this->getOrCreateMainAccount($data['account_name']);

        // Get user ID
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO hotel_bookings (
                tenant_id, branch_id, supplier_id, sold_to, paid_to, order_id, title, first_name,
                last_name, gender, contact_no, issue_date, check_in_date, check_out_date,
                accommodation_details, currency, base_amount, sold_amount, profit, remarks,
                created_by, created_at, updated_at, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $supplierId, $clientId, $accountId, $data['order_id'],
            $data['title'], $data['first_name'], $data['last_name'], $data['gender'],
            $data['contact_no'], $data['issue_date'], $data['check_in_date'],
            $data['check_out_date'], $data['accommodation_details'], $data['currency'],
            $data['base_amount'], $data['sold_amount'], $data['profit'], $data['remarks'], $userId
        ]);
    }

    private function insertFamily($data) {
        // Get user ID
        $userId = $this->getImportUserId();

        $stmt = $this->pdo->prepare("
            INSERT INTO families (
                tenant_id, branch_id, head_of_family, contact, address, province, district,
                total_members, package_type, location, tazmin, visa_status,
                total_price, total_paid, total_paid_to_bank, total_due, created_by,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $this->tenantId, $this->branchId, $data['head_of_family'], $data['contact'], $data['address'],
            $data['province'], $data['district'], $data['total_members'], $data['package_type'],
            $data['location'], $data['tazmin'], $data['visa_status'], $data['total_price'],
            $data['total_paid'], $data['total_paid_to_bank'], $data['total_due'], $userId
        ]);
    }

    private function insertUmrahBooking($data) {
        // Map Excel data to actual `umrah_bookings` table structure.
        // Live schema (from DB) is:
        // booking_id, tenant_id, family_id, sold_to, paid_to, entry_date, name, fname, gfname,
        // relation, dob, gender, passport_number, passport_expiry, id_type, flight_date,
        // return_date, duration, room_type, price, sold_price, discount, profit,
        // received_bank_payment, bank_receipt_number, paid, due, currency, created_by,
        // created_at, updated_at, remarks, status

        // Get or create related entities
        $clientId = $this->getOrCreateClient($data['client_name']);          // sold_to
        $accountId = $this->getOrCreateMainAccount($data['account_name']);   // paid_to
        $familyId = $this->getOrCreateFamily($data['head_of_family']);       // family_id

        // Get user ID
        $userId = $this->getImportUserId();

        // Provide safe defaults for NOT NULL fields that are not present in the template
        $fname = '';        // required but not in template
        $gfname = '';       // required but not in template
        $relation = '';     // required but not in template
        $discount = 0.000;  // NOT NULL discount
        $status = 'active'; // default status
        $gender = null;     // optional
        $passportExpiry = null;
        $idType = null;

        $stmt = $this->pdo->prepare("
            INSERT INTO umrah_bookings (
                tenant_id, branch_id, family_id, sold_to, paid_to, entry_date, name, fname, gfname,
                relation, dob, gender, passport_number, passport_expiry, id_type, flight_date,
                return_date, duration, room_type, price, sold_price, discount, profit,
                received_bank_payment, bank_receipt_number, paid, due, currency, created_by,
                remarks, status, imported
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
            )
        ");

        $stmt->execute([
            $this->tenantId,
            $this->branchId,
            $familyId,
            $clientId,
            $accountId,
            $data['entry_date'],
            $data['name'],
            $fname,
            $gfname,
            $relation,
            $data['dob'],
            $gender,
            $data['passport_number'],
            $passportExpiry,
            $idType,
            $data['flight_date'],
            $data['return_date'],
            $data['duration'],
            $data['room_type'],
            $data['price'],
            $data['sold_price'],
            $discount,
            $data['profit'],
            $data['received_bank_payment'],
            $data['bank_receipt_number'],
            $data['paid'],
            $data['due'],
            $data['currency'],
            $userId,
            $data['remarks'],
            $status
        ]);

        // Also create a corresponding record in `umrah_booking_services` to store the supplier/service info.
        // Supplier is not stored directly on `umrah_bookings`, but on this separate table.
        if (!empty($data['supplier_name'])) {
            $bookingId = $this->pdo->lastInsertId();
            $supplierId = $this->getOrCreateSupplier($data['supplier_name']);
            $serviceType = !empty($data['service_type']) ? $data['service_type'] : 'all';

            $serviceStmt = $this->pdo->prepare("
                INSERT INTO umrah_booking_services (
                    tenant_id, branch_id, booking_id, service_type, supplier_id,
                    base_price, sold_price, profit, currency, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");

            $serviceStmt->execute([
                $this->tenantId, 
                $this->branchId,
                $bookingId,
                $serviceType,
                $supplierId,
                $data['price'],
                $data['sold_price'],
                $data['profit'],
                $data['currency']
            ]);
        }
    }

    // Helper methods for entity creation/lookup
    private function getOrCreateSupplier($name) {
        if (empty($name)) $name = 'Default Supplier';

        $stmt = $this->pdo->prepare("SELECT id FROM suppliers WHERE name = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$name, $this->tenantId, $this->branchId]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($supplier) {
            return $supplier['id'];
        }

        // Create new supplier
        $insertStmt = $this->pdo->prepare("
            INSERT INTO suppliers (tenant_id, branch_id, name, phone, currency, created_at, updated_at)
            VALUES (?, ?, ?, '', 'USD', NOW(), NOW())
        ");
        $insertStmt->execute([$this->tenantId, $this->branchId, $name]);
        return $this->pdo->lastInsertId();
    }

    private function getOrCreateClient($name) {
         if (empty($name)) $name = 'Default Client';

         $stmt = $this->pdo->prepare("SELECT id FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->execute([$name, $this->tenantId, $this->branchId]);
         $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            return $client['id'];
        }

        // Create new client.
        // The `clients` table has several NOT NULL fields (image, email, password_hash, address, etc.)
        // and a unique (tenant_id, email) constraint, so we provide safe placeholder values here.
        $slugName = strtolower(trim(preg_replace('/\s+/', '.', $name)));
        if ($slugName === '') {
            $slugName = 'client';
        }
        $placeholderEmail = $slugName . '+' . uniqid('', true) . '@import.local';
        $placeholderPasswordHash = password_hash(uniqid('import-client-', true), PASSWORD_DEFAULT);

        $insertStmt = $this->pdo->prepare("
            INSERT INTO clients (
                tenant_id, branch_id, image, name, email, password_hash, phone,
                usd_balance, afs_balance, address, status, client_type,
                totp_enabled, created_at, updated_at
            ) VALUES (
                ?, ?, '', ?, ?, ?, '', 0.000, 0.000, '', 'active', 'regular', 0, NOW(), NOW()
            )
        ");
        $insertStmt->execute([$this->tenantId, $this->branchId, $name, $placeholderEmail, $placeholderPasswordHash]);
        return $this->pdo->lastInsertId();
    }

    private function getOrCreateMainAccount($name) {
         if (empty($name)) $name = 'Default Account';

         $stmt = $this->pdo->prepare("SELECT id FROM main_account WHERE name = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->execute([$name, $this->tenantId, $this->branchId]);
         $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            return $account['id'];
        }

        // Create new main account
        $insertStmt = $this->pdo->prepare("
            INSERT INTO main_account (
                tenant_id, branch_id, name, account_type, usd_balance, afs_balance,
                euro_balance, darham_balance, last_updated, status
            ) VALUES (
                ?, ?, ?, 'internal', 0, 0, 0, 0, NOW(), 'active'
            )
        ");
        $insertStmt->execute([$this->tenantId, $this->branchId, $name]);
        return $this->pdo->lastInsertId();
    }

    private function getOrCreateFamily($headOfFamily) {
        if (empty($headOfFamily)) return null;

        $stmt = $this->pdo->prepare("SELECT family_id FROM families WHERE head_of_family = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$headOfFamily, $this->tenantId, $this->branchId]);
        $family = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($family) {
            return $family['family_id'];
        }

        // Create new family
        $insertStmt = $this->pdo->prepare("
            INSERT INTO families (tenant_id, branch_id, head_of_family, province, district, created_by, created_at, updated_at)
            VALUES (?, ?, ?, '', '', ?, NOW(), NOW())
        ");
        $userId = $this->getImportUserId();
        $insertStmt->execute([$this->tenantId, $this->branchId, $headOfFamily, $userId]);
        return $this->pdo->lastInsertId();
    }

    private function shouldSkipImportRow(array $data) {
        $trimmedData = array_map(static function ($value) {
            return trim((string) $value);
        }, $data);

        if (empty(array_filter($trimmedData, static function ($value) {
            return $value !== '';
        }))) {
            return true;
        }

        $firstCell = strtoupper($trimmedData[0] ?? '');
        return strpos($firstCell, 'NOTES') === 0 || strpos($firstCell, '- ') === 0;
    }

    private function getImportUserId() {
        // Get the first admin user for this tenant
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND role = 'admin' AND branch_id = ? LIMIT 1");
        $stmt->execute([$this->tenantId, $this->branchId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? $user['id'] : 1; // Fallback to user ID 1
    }

    private function parseDate($dateValue) {
        if (empty($dateValue)) {
            return null;
        }

        // Handle Excel date serial numbers
        if (is_numeric($dateValue)) {
            return Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
        }

        // Handle string dates
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }
}
