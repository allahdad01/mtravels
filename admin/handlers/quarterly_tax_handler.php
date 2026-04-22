<?php
session_start();

require_once('../../includes/db.php');

$tenant_id = $_SESSION['tenant_id'];

// Get action from POST/GET first, then check JSON body
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// If not found in POST/GET, try to parse from JSON body
if (!$action) {
    $json_input = json_decode(file_get_contents('php://input'), true);
    $action = $json_input['action'] ?? null;
}

header('Content-Type: application/json');

switch ($action) {
    case 'save_supplier_spec':
        saveSupplierSpecification();
        break;

    case 'get_supplier_spec':
        getSupplierSpecification();
        break;

    case 'get_supplier_data':
        getSupplierData();
        break;

    case 'get_expenses':
        getExpenses();
        break;

    case 'generate_supplier_report':
        generateSupplierReport();
        break;

    case 'get_saved_reports':
        getSavedReports();
        break;

    case 'generate_general_report':
        generateGeneralReport();
        break;

    case 'save_general_report':
        saveGeneralReportToDB();
        break;

    case 'get_all_saved_reports':
        getAllSavedReports();
        break;

    case 'get_report':
        getReportById();
        break;

    case 'delete_report':
        deleteReport();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Save supplier tax report specifications
 */
function saveSupplierSpecification() {
    global $pdo, $tenant_id;

    $supplier_id = $_POST['supplier_id'] ?? null;
    $quarter = $_POST['quarter'] ?? null;
    $year = $_POST['year'] ?? null;
    $data_type = $_POST['data_type'] ?? null;
    $profit_min = $_POST['profit_min'] ?? null;
    $profit_max = $_POST['profit_max'] ?? null;
    $item_count = $_POST['item_count'] ?? null;

    if (!$supplier_id || !$quarter || !$year || !$data_type) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    try {
        $query = "INSERT INTO tax_report_specifications (tenant_id, supplier_id, quarter, year, data_type, profit_min, profit_max, item_count, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                  ON DUPLICATE KEY UPDATE data_type=?, profit_min=?, profit_max=?, item_count=?, updated_at=NOW()";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $tenant_id, $supplier_id, $quarter, $year, $data_type,
            $profit_min, $profit_max, $item_count,
            $data_type, $profit_min, $profit_max, $item_count
        ]);

        echo json_encode(['success' => true, 'message' => 'Specification saved']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get supplier tax report specifications
 */
function getSupplierSpecification() {
    global $pdo, $tenant_id;

    $supplier_id = $_GET['supplier_id'] ?? null;
    $quarter = $_GET['quarter'] ?? null;
    $year = $_GET['year'] ?? null;

    if (!$supplier_id || !$quarter || !$year) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }

    try {
        $query = "SELECT * FROM tax_report_specifications 
                  WHERE tenant_id = ? AND supplier_id = ? AND quarter = ? AND year = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tenant_id, $supplier_id, $quarter, $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            echo json_encode(['success' => true, 'data' => null]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get actual supplier transaction data
 */
function getSupplierData() {
    global $pdo, $tenant_id;

    $supplier_id = $_GET['supplier_id'] ?? null;
    $quarter = $_GET['quarter'] ?? null;
    $year = $_GET['year'] ?? null;
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;

    if (!$supplier_id) {
        echo json_encode(['success' => false, 'message' => 'Missing supplier_id']);
        return;
    }

    try {
        if ($date_from && $date_to) {
            // Use custom date range
            $query = "SELECT SUM(amount) as total_amount, COUNT(*) as transaction_count
                      FROM supplier_transactions
                      WHERE tenant_id = ? AND supplier_id = ? 
                      AND DATE(transaction_date) BETWEEN ? AND ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$tenant_id, $supplier_id, $date_from, $date_to]);
        } else if ($quarter && $year) {
            // Use quarter-based date range
            $quarters = [
                'Q1' => ['01-01', '03-31'],
                'Q2' => ['04-01', '06-30'],
                'Q3' => ['07-01', '09-30'],
                'Q4' => ['10-01', '12-31']
            ];

            if (!isset($quarters[$quarter])) {
                echo json_encode(['success' => false, 'message' => 'Invalid quarter']);
                return;
            }

            list($start_month_day, $end_month_day) = $quarters[$quarter];
            $date_from = "$year-$start_month_day";
            $date_to = "$year-$end_month_day";

            $query = "SELECT SUM(amount) as total_amount, COUNT(*) as transaction_count
                      FROM supplier_transactions
                      WHERE tenant_id = ? AND supplier_id = ? 
                      AND DATE(transaction_date) BETWEEN ? AND ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$tenant_id, $supplier_id, $date_from, $date_to]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Must provide either quarter/year or date range']);
            return;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $result ?? ['total_amount' => 0, 'transaction_count' => 0]]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get expenses for a period
 */
function getExpenses() {
    global $pdo, $tenant_id;

    // Get data from POST JSON body or GET parameters
    $json_data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $quarter = $json_data['quarter'] ?? $_GET['quarter'] ?? null;
    $year = $json_data['year'] ?? $_GET['year'] ?? null;
    $date_from = $json_data['date_from'] ?? $_GET['date_from'] ?? null;
    $date_to = $json_data['date_to'] ?? $_GET['date_to'] ?? null;
    $categories = $json_data['categories'] ?? $_GET['categories'] ?? [];
    $branch_id = $_SESSION['branch_id'] ?? null;

    try {
        $query = "SELECT e.id, e.date, e.description, e.amount as total_amount, e.currency, ec.name as category
                  FROM expenses e
                  JOIN expense_categories ec ON e.category_id = ec.id
                  WHERE e.tenant_id = ? AND e.branch_id = ?";

        $params = [$tenant_id, $branch_id];

        if ($date_from && $date_to) {
            // Use custom date range
            $query .= " AND DATE(e.date) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        } else if ($quarter && $year) {
            // Use quarter-based date range
            $quarters = [
                'Q1' => ['01-01', '03-31'],
                'Q2' => ['04-01', '06-30'],
                'Q3' => ['07-01', '09-30'],
                'Q4' => ['10-01', '12-31']
            ];

            if (!isset($quarters[$quarter])) {
                echo json_encode(['success' => false, 'message' => 'Invalid quarter']);
                return;
            }

            list($start_month_day, $end_month_day) = $quarters[$quarter];
            $date_from = "$year-$start_month_day";
            $date_to = "$year-$end_month_day";

            $query .= " AND DATE(e.date) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        } else {
            echo json_encode(['success' => false, 'message' => 'Must provide either quarter/year or date range']);
            return;
        }

        if (!empty($categories)) {
            $placeholders = array_fill(0, count($categories), '?');
            $query .= " AND ec.name IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $categories);
        }

        $query .= " ORDER BY e.date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $results ?? []]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Generate individual supplier report
 */
function generateSupplierReport() {
    global $pdo, $tenant_id;

    $data = json_decode(file_get_contents('php://input'), true);

    // Get supplier_id - ensure it's an integer
    $supplier_id = isset($data['supplier_id']) ? (int)$data['supplier_id'] : null;
    $supplier_name = $data['supplier_name'] ?? null;
    $quarter = $data['quarter'] ?? null;
    $year = $data['year'] ?? null;
    $data_type = $data['data_type'] ?? null;
    $date_from = $data['date_from'] ?? null;
    $date_to = $data['date_to'] ?? null;
    $branch_id = isset($data['branch_id']) ? (int)$data['branch_id'] : null;
    $report_type = $data['report_type'] ?? 'ticket';  // ticket, visa, umrah, hotel, all
    $exchange_rate = isset($data['exchangeRate']) ? (float)$data['exchangeRate'] : 1;
    
    // Only use profit parameters for random data generation
    $profit_min = ($data_type === 'random') ? ($data['profit_min'] ?? 1000) : 0;
    $profit_max = ($data_type === 'random') ? ($data['profit_max'] ?? 10000) : 0;
    $item_count = ($data_type === 'random') ? ($data['item_count'] ?? 5) : 0;

    if (!$supplier_id || !$data_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: supplier_id=' . var_export($supplier_id, true) . ', data_type=' . var_export($data_type, true)]);
        return;
    }

    try {
        // Determine date range
        $from_date = null;
        $to_date = null;

        if ($date_from && $date_to) {
            $from_date = $date_from;
            $to_date = $date_to;
        } else if ($quarter && $year) {
            $quarters = [
                'Q1' => ['01-01', '03-31'],
                'Q2' => ['04-01', '06-30'],
                'Q3' => ['07-01', '09-30'],
                'Q4' => ['10-01', '12-31']
            ];
            list($start, $end) = $quarters[$quarter];
            $from_date = "$year-$start";
            $to_date = "$year-$end";
        }

        if ($data_type === 'actual') {
            // Fetch data based on report type
            $tickets = fetchTicketsByType($pdo, $tenant_id, $branch_id, $supplier_id, $report_type, $from_date, $to_date);
            
            // Sort by issue_date
            usort($tickets, function($a, $b) {
                return strtotime($b['issue_date']) - strtotime($a['issue_date']);
            });

            // Format data for display
            $formattedData = [];
            foreach ($tickets as $ticket) {
                $formattedData[] = [
                    'issue_date' => $ticket['issue_date'],
                    'full_name' => $ticket['full_name'],
                    'sector' => $ticket['sector'],
                    'details' => [
                        'status' => $ticket['status'],
                        'pnr' => $ticket['pnr'],
                        'base_price' => (float)$ticket['base_price'],
                        'sold_price' => (float)$ticket['sold_price'],
                        'profit' => (float)$ticket['profit'],
                        'ticket_type' => $ticket['ticket_type']
                    ]
                ];
            }

            if (empty($formattedData)) {
                echo json_encode(['success' => false, 'message' => 'No tickets found for this supplier in the selected period']);
            } else {
                try {
                    saveSupplierReportRecord($tenant_id, $supplier_id, $supplier_name, $quarter, $year, $from_date, $to_date, $data_type, $report_type, $exchange_rate, $formattedData, $branch_id);
                } catch (PDOException $e) {
                    // Log the error but don't fail - still return the report
                    error_log('Failed to save tax report: ' . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'data' => $formattedData]);
            }
        } else {
            // Generate random data based on profit range
            // Fetch some actual tickets to use as template
            $query = "SELECT 
                        issue_date,
                        CONCAT(title, ' ', passenger_name) as full_name,
                        CONCAT(origin, ' - ', destination, IF(trip_type='round_trip', ' - ', ''), IF(trip_type='round_trip', return_origin, '')) as sector,
                        status,
                        pnr,
                        price as base_price
                      FROM ticket_bookings
                      WHERE tenant_id = ? AND branch_id = ? AND supplier = ?";
            
            $params = [$tenant_id, $branch_id, $supplier_id];

            if ($from_date && $to_date) {
                $query .= " AND DATE(issue_date) BETWEEN ? AND ?";
                $params[] = $from_date;
                $params[] = $to_date;
            }

            $query .= " ORDER BY RAND() LIMIT ?";
            $params[] = $item_count;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If not enough actual tickets, fill with generated ones
            $randomData = [];
            $statuses = ['Booked', 'Paid', 'Date Changed'];

            for ($i = 0; $i < $item_count; $i++) {
                $template = $templates[$i] ?? null;
                
                $randomProfit = rand($profit_min, $profit_max);
                $basePrice = $template ? (float)$template['base_price'] : rand(20000, 80000);
                $soldPrice = $basePrice + $randomProfit;

                $randomData[] = [
                    'issue_date' => $template ? $template['issue_date'] : date('Y-m-d'),
                    'full_name' => $template ? $template['full_name'] : "Mr. Generated Passenger " . ($i + 1),
                    'sector' => $template ? $template['sector'] : "Random Origin - Random Destination",
                    'details' => [
                        'status' => $template ? $template['status'] : $statuses[array_rand($statuses)],
                        'pnr' => $template ? $template['pnr'] : 'GEN' . strtoupper(bin2hex(random_bytes(3))),
                        'base_price' => $basePrice,
                        'sold_price' => $soldPrice,
                        'profit' => $randomProfit
                    ]
                ];
            }

            try {
                saveSupplierReportRecord($tenant_id, $supplier_id, $supplier_name, $quarter, $year, $from_date, $to_date, $data_type, $report_type, $exchange_rate, $randomData, $branch_id);
            } catch (PDOException $e) {
                // Log the error but don't fail - still return the report
                error_log('Failed to save tax report: ' . $e->getMessage());
            }

            echo json_encode(['success' => true, 'data' => $randomData]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function saveSupplierReportRecord($tenant_id, $supplier_id, $supplier_name, $quarter, $year, $date_from, $date_to, $data_type, $report_type, $exchange_rate, $report_rows, $branch_id) {
    global $pdo;

    $reportData = json_encode([
        'supplier_id' => $supplier_id,
        'supplier_name' => $supplier_name,
        'quarter' => $quarter,
        'year' => $year,
        'quarterStart' => $date_from,
        'quarterEnd' => $date_to,
        'data_type' => $data_type,
        'service_type' => $report_type,
        'exchange_rate' => $exchange_rate,
        'data' => $report_rows,
        'generated_at' => date('Y-m-d H:i:s')
    ]);

    $saveQuery = "INSERT INTO tax_reports (tenant_id, supplier_id, quarter, year, report_type, report_data, branch_id, created_by, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                  ON DUPLICATE KEY UPDATE report_data=?, updated_at=NOW()";

    error_log("Saving report: tenant=$tenant_id, supplier=$supplier_id, quarter=$quarter, year=$year, data_type=$data_type, service_type=$report_type, branch=$branch_id, user=$_SESSION[user_id]");

    $saveStmt = $pdo->prepare($saveQuery);
    $saveStmt->execute([
        $tenant_id,
        $supplier_id,
        $quarter,
        $year,
        'supplier',
        $reportData,
        $branch_id,
        $_SESSION['user_id'],
        $reportData
    ]);

    error_log("Report saved successfully, rows affected: " . $saveStmt->rowCount());
}

/**
 * Get saved reports for a quarter (used for general report assembly)
 */
function getSavedReports() {
    global $pdo, $tenant_id;

    // Get from request (GET or JSON body)
    $quarter = $_GET['quarter'] ?? json_decode(file_get_contents('php://input'), true)['quarter'] ?? null;
    $year = $_GET['year'] ?? json_decode(file_get_contents('php://input'), true)['year'] ?? null;
    $branch_id = $_SESSION['branch_id'] ?? null;  // Use session branch_id

    error_log("getSavedReports called: quarter=$quarter, year=$year, branch_id=$branch_id, tenant_id=$tenant_id");

    if (!$year) {
        echo json_encode(['success' => false, 'message' => 'Missing year']);
        return;
    }

    if (!$branch_id) {
        echo json_encode(['success' => false, 'message' => 'Missing branch_id']);
        return;
    }

    try {
        $query = "SELECT id, supplier_id, quarter, year, report_data, created_at 
                  FROM tax_reports 
                  WHERE tenant_id = ? AND year = ? AND branch_id = ? AND report_type = 'supplier'";
        
        $params = [$tenant_id, $year, $branch_id];
        
        if ($quarter) {
            $query .= " AND quarter = ?";
            $params[] = $quarter;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        error_log("getSavedReports query: $query with params: " . json_encode($params));
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("getSavedReports found " . count($reports) . " reports");

        // Decode JSON data for each report
        foreach ($reports as &$report) {
            $report['data'] = json_decode($report['report_data'], true);
        }

        echo json_encode(['success' => true, 'data' => $reports ?? [], 'debug' => ['quarter' => $quarter, 'year' => $year, 'branch_id' => $branch_id, 'count' => count($reports)]]);
    } catch (PDOException $e) {
        error_log("getSavedReports error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Generate general tax report
 */
function generateGeneralReport() {
    global $pdo, $tenant_id;

    $data = json_decode(file_get_contents('php://input'), true);

    $quarter = $data['quarter'] ?? null;
    $year = $data['year'] ?? null;
    $categories = $data['categories'] ?? [];
    $expenses = $data['expenses'] ?? [];

    if (!$quarter || !$year) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    try {
        // Calculate summary
        $totalExpense = array_reduce($expenses, function($carry, $item) {
            return $carry + ($item['amount'] ?? 0);
        }, 0);

        $reportSummary = [
            'quarter' => $quarter,
            'year' => $year,
            'total_expenses' => $totalExpense,
            'expense_count' => count($expenses),
            'categories' => $categories,
            'expenses' => $expenses,
            'generated_at' => date('Y-m-d H:i:s')
        ];

        // Save report to database
        $reportJson = json_encode($reportSummary);
        $insertQuery = "INSERT INTO tax_reports (tenant_id, quarter, year, report_type, report_data, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([$tenant_id, $quarter, $year, 'general', $reportJson]);

        echo json_encode(['success' => true, 'data' => $reportSummary]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Fetch tickets by report type (ticket, visa, umrah, hotel, or all)
 * Visa, Umrah, Hotel types only fetch main bookings (no refunds)
 */
function fetchTicketsByType($pdo, $tenant_id, $branch_id, $supplier_id, $report_type, $from_date, $to_date) {
    $tickets = [];
    
    // Ticket type includes: bookings, refunds, date_changes
    if ($report_type === 'ticket' || $report_type === 'all') {
        // 1. Regular ticket bookings
        $query = "SELECT 
                    id,
                    issue_date,
                    CONCAT(title, ' ', passenger_name) as full_name,
                    CONCAT(origin, ' - ', destination, IF(trip_type='round_trip', ' - ', ''), IF(trip_type='round_trip', return_origin, '')) as sector,
                    status,
                    pnr,
                    price as base_price,
                    sold as sold_price,
                    profit,
                    description,
                    'ticket' as ticket_type
                  FROM ticket_bookings
                  WHERE tenant_id = ? AND branch_id = ? AND supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(issue_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 2. Refunded tickets
        $query = "SELECT 
                    rt.id,
                    rt.issue_date,
                    CONCAT(rt.title, ' ', rt.passenger_name) as full_name,
                    CONCAT(rt.origin, ' - ', rt.destination) as sector,
                    rt.status,
                    rt.pnr,
                    rt.base as base_price,
                    rt.sold as sold_price,
                    0 as profit,
                    rt.remarks as description,
                    'ticket_refund' as ticket_type
                  FROM refunded_tickets rt
                  WHERE rt.tenant_id = ? AND rt.branch_id = ? AND rt.supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(rt.created_at) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 3. Date change tickets
        $query = "SELECT 
                    dc.id,
                    dc.issue_date,
                    CONCAT(dc.title, ' ', dc.passenger_name) as full_name,
                    CONCAT(dc.origin, ' - ', dc.destination) as sector,
                    dc.status,
                    dc.pnr,
                    COALESCE(dc.supplier_penalty, 0) as base_price,
                    (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold_price,
                    COALESCE(dc.service_penalty, 0) as profit,
                    dc.remarks as description,
                    'ticket_date_change' as ticket_type
                  FROM date_change_tickets dc
                  WHERE dc.tenant_id = ? AND dc.branch_id = ? AND dc.supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(dc.created_at) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Visa type (no refunds)
    if ($report_type === 'visa' || $report_type === 'all') {
        $query = "SELECT 
                    id,
                    receive_date as issue_date,
                    applicant_name as full_name,
                    CONCAT(country, ' - ', visa_type) as sector,
                    status,
                    passport_number as pnr,
                    base as base_price,
                    sold as sold_price,
                    profit,
                    remarks as description,
                    'visa' as ticket_type
                  FROM visa_applications
                  WHERE tenant_id = ? AND branch_id = ? AND supplier = ?";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(receive_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Umrah type (no refunds)
    if ($report_type === 'umrah' || $report_type === 'all') {
        $query = "SELECT 
                    ub.booking_id as id,
                    ub.entry_date as issue_date,
                    ub.name as full_name,
                    ub.duration as sector,
                    ub.status,
                    ub.passport_number as pnr,
                    ubs.base_price,
                    ubs.sold_price,
                    ubs.profit,
                    ub.remarks as description,
                    'umrah' as ticket_type
                  FROM umrah_bookings ub
                  JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                  WHERE ub.tenant_id = ? AND ub.branch_id = ? AND ubs.supplier_id = ? AND ub.status IN ('active', 'pending')";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(ub.entry_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Hotel type (no refunds)
    if ($report_type === 'hotel' || $report_type === 'all') {
        $query = "SELECT 
                    id,
                    issue_date,
                    CONCAT(first_name, ' ', last_name) as full_name,
                    accommodation_details as sector,
                    status,
                    order_id as pnr,
                    base_amount as base_price,
                    sold_amount as sold_price,
                    profit,
                    remarks as description,
                    'hotel' as ticket_type
                  FROM hotel_bookings
                  WHERE tenant_id = ? AND branch_id = ? AND supplier_id = ? AND status = 'active'";
        
        $params = [$tenant_id, $branch_id, $supplier_id];
        if ($from_date && $to_date) {
            $query .= " AND DATE(issue_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = array_merge($tickets, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    return $tickets;
}

/**
 * Save general tax report to database
 */
function saveGeneralReportToDB() {
    global $pdo, $tenant_id;

    $data = json_decode(file_get_contents('php://input'), true);

    $quarter = $data['quarter'] ?? null;
    $year = $data['year'] ?? null;
    $branch_id = $_SESSION['branch_id'] ?? null;
    $created_by = $_SESSION['user_id'] ?? null;

    if (!$quarter || !$year) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing quarter or year']);
        return;
    }

    try {
        // Prepare report data as JSON
        $reportData = json_encode([
            'quarter' => $quarter,
            'year' => $year,
            'quarterStart' => $data['quarterStart'] ?? null,
            'quarterEnd' => $data['quarterEnd'] ?? null,
            'expenses' => $data['expenses'] ?? [],
            'suppliers' => $data['suppliers'] ?? [],
            'generatedAt' => $data['generatedAt'] ?? date('Y-m-d H:i:s')
        ]);

        // Save or update the general report
        $query = "INSERT INTO tax_reports (tenant_id, quarter, year, report_type, report_data, branch_id, created_by, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                  ON DUPLICATE KEY UPDATE report_data=?, updated_at=NOW()";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $tenant_id,
            $quarter,
            $year,
            'general',
            $reportData,
            $branch_id,
            $created_by,
            $reportData
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'General report saved successfully',
            'report_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get all saved reports for a year/quarter (both supplier and general)
 */
function getAllSavedReports() {
    global $pdo, $tenant_id;

    $year = $_GET['year'] ?? null;
    $quarter = $_GET['quarter'] ?? null;
    $branch_id = $_SESSION['branch_id'] ?? null;

    if (!$year) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing year']);
        return;
    }

    if (!$branch_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing branch_id']);
        return;
    }

    try {
        $query = "SELECT id, supplier_id, quarter, year, report_type, report_data, created_at 
                  FROM tax_reports 
                  WHERE tenant_id = ? AND year = ? AND branch_id = ?";
        
        $params = [$tenant_id, $year, $branch_id];
        
        if ($quarter) {
            $query .= " AND quarter = ?";
            $params[] = $quarter;
        }
        
        $query .= " ORDER BY report_type ASC, created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data for each report
        foreach ($reports as &$report) {
            $report['data'] = json_decode($report['report_data'], true);
        }

        echo json_encode(['success' => true, 'data' => $reports ?? []]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get a single report by ID
 */
function getReportById() {
    global $pdo, $tenant_id;

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing report ID']);
        return;
    }

    try {
        $query = "SELECT id, supplier_id, quarter, year, report_type, report_data, created_at 
                  FROM tax_reports 
                  WHERE id = ? AND tenant_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id, $tenant_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            return;
        }

        $report['data'] = json_decode($report['report_data'], true);

        echo json_encode(['success' => true, 'data' => $report]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Delete a report
 */
function deleteReport() {
    global $pdo, $tenant_id;

    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing report ID']);
        return;
    }

    try {
        // Verify report belongs to this tenant before deleting
        $checkQuery = "SELECT id FROM tax_reports WHERE id = ? AND tenant_id = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$id, $tenant_id]);
        
        if (!$checkStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $query = "DELETE FROM tax_reports WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id, $tenant_id]);

        echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
