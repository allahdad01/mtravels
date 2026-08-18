<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// generate_report.php - Handles report generation in PDF, Excel, or Word formats
require_once '../../includes/db.php';
include '../../vendor/autoload.php'; // Load PhpSpreadsheet and Dompdf
$user_role = $_SESSION["role"];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$reportType = $_GET['reportType'];
$entity = $_GET['entity'];
$reportCategory = $_GET['reportCategory'];
$startDate = $_GET['startDate'];
$endDate = $_GET['endDate'];
$format = $_GET['format'];
$expenseCategory = isset($_GET['expenseCategory']) ? $_GET['expenseCategory'] : '';
$umrahFamilyType = isset($_GET['umrahFamilyType']) ? $_GET['umrahFamilyType'] : '';
$specificFamily = isset($_GET['specificFamily']) ? $_GET['specificFamily'] : '';
$umrahFilterType = isset($_GET['umrahFilterType']) ? $_GET['umrahFilterType'] : '';
$umrahFlightDate = isset($_GET['umrahFlightDate']) ? $_GET['umrahFlightDate'] : '';
$umrahReturnDate = isset($_GET['umrahReturnDate']) ? $_GET['umrahReturnDate'] : '';

// Build umrah filter conditions (applied to umrah_bookings alias u)
$umrahFilterSql = '';
$umrahFilterParams = [];
if (($umrahFilterType === 'family' || $umrahFamilyType === 'specific') && $specificFamily) {
    $umrahFilterSql = " AND u.family_id = ?";
    $umrahFilterParams[] = $specificFamily;
} elseif ($umrahFilterType === 'flight_date' && $umrahFlightDate) {
    $umrahFilterSql = " AND DATE(u.flight_date) = ?";
    $umrahFilterParams[] = $umrahFlightDate;
} elseif ($umrahFilterType === 'return_date' && $umrahReturnDate) {
    $umrahFilterSql = " AND DATE(u.return_date) = ?";
    $umrahFilterParams[] = $umrahReturnDate;
}

// When filtering umrah by family/flight/return date, ignore the entry date range
$umrahDateCondition = 'u.entry_date BETWEEN ? AND ?';
$umrahDateParams = [$startDate, $endDate];
if (in_array($umrahFilterType, ['family', 'flight_date', 'return_date'])) {
    $umrahDateCondition = '1=1';
    $umrahDateParams = [];
}

try {
    // Get entity name based on report type
    $entityName = "";
    if ($entity && $reportType !== 'ticket' && $reportType !== 'general') {
        $entityTable = "";
        $entityField = "name";
        
        switch($reportType) {
            case 'supplier':
                $entityTable = "suppliers";
                break;
            case 'client':
                $entityTable = "clients";
                break;
            case 'main_account':
                $entityTable = "main_account";
                break;
        }
        
        if ($entityTable) {
            $stmt = $pdo->prepare("SELECT name FROM $entityTable WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$entity, $tenant_id, $branch_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $entityName = $result ? $result['name'] : '';
        }
    }

    // Generate report title
    $reportTitle = "";
    $categoryTitle = ucwords(str_replace('_', ' ', $reportCategory));
    
    if ($reportType === 'ticket' || $reportType === 'general') {
        $reportTitle = "$categoryTitle Reports";
        if ($reportType === 'general') {
            $reportTitle = "General Report: $categoryTitle";
            if ($reportCategory === 'general_summary') {
                $reportTitle = "General Report: Income & Expense by Source";
            }
        }
    } else {
        $reportTitle = "$categoryTitle Reports for " . ucwords($reportType) . ": $entityName";
    }
    
    $dateRange = "Period: " . date('d M Y', strtotime($startDate)) . " to " . date('d M Y', strtotime($endDate));

    $query = "";
    $params = [];
    $headers = [];
    $tables = [];
    $sections = [];
    
    // Handle general report type that doesn't specify an entity
    if ($reportType === 'general') {
        switch($reportCategory) {
            case 'ticket':
                $query = "SELECT
                     tb.id,
                     tb.pnr,
                     tb.title,
                     tb.passenger_name,
                     tb.phone,
                     tb.gender,
                     tb.origin,
                     tb.destination,
                     tb.trip_type,
                     tb.return_destination,
                     tb.airline,
                     tb.issue_date,
                     tb.departure_date,
                     tb.currency,
                     tb.price,
                     tb.sold,
                     tb.profit,
                     s.name as supplier_name,
                     c.name as sold_to_name,
                     m.name as paid_to_name,
                     tb.status,
                     GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                     tb.description,
                     'normal' as record_type,
                     NULL as parent_id
                 FROM ticket_bookings tb
                 LEFT JOIN suppliers s ON tb.supplier = s.id
                 LEFT JOIN clients c ON tb.sold_to = c.id
                 LEFT JOIN main_account m ON tb.paid_to = m.id
                 LEFT JOIN main_account_transactions mat
                     ON tb.id = mat.reference_id AND mat.transaction_of = 'ticket_sale'
                 WHERE tb.issue_date BETWEEN ? AND ?
                 AND tb.tenant_id = ? AND tb.branch_id = ?
                 GROUP BY tb.id";

                 $params = [$startDate, $endDate, $tenant_id, $branch_id];

                $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                           'Sector', 'Trip Type', 'Airline',
                           'Issue Date', 'Departure Date', 
                           'Currency', 'Price', 'Sold', 'Profit',
                           'Supplier', 'Sold To', 'Paid To',
                           'Status', 'Receipt', 'Description'];
                break;
            
            case 'ticket_reservation':
                $query = "SELECT
                     tb.pnr, tb.title, tb.passenger_name, tb.phone, tb.gender,
                     tb.origin, tb.destination, tb.trip_type, tb.return_destination,
                     tb.airline,
                     tb.issue_date, tb.departure_date,
                     tb.currency, tb.price, tb.sold, tb.profit,
                     s.name as supplier_name,
                     c.name as sold_to_name,
                     m.name as paid_to_name,
                     tb.status,
                     GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                     tb.description
                     FROM ticket_reservations tb
                     LEFT JOIN suppliers s ON tb.supplier = s.id
                     LEFT JOIN clients c ON tb.sold_to = c.id
                     LEFT JOIN main_account m ON tb.paid_to = m.id
                     LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_reserve'
                     WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                     GROUP BY tb.id";
                 $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                           'Sector', 'Trip Type', 'Airline',
                           'Issue Date', 'Departure Date', 
                           'Currency', 'Price', 'Sold', 'Profit',
                           'Supplier', 'Sold To', 'Paid To',
                           'Status', 'Receipt', 'Description'];
                break;

            case 'refund_ticket':
                $query = "SELECT
                    rt.pnr,
                    rt.title,
                    rt.passenger_name,
                    rt.phone,
                    rt.gender,
                    rt.origin,
                    rt.destination,
                    '' as trip_type,
                    '' as return_destination,
                    rt.airline,
                    rt.issue_date,
                    rt.departure_date,
                    rt.currency,
                    rt.sold,
                    rt.base as base_amount,
                    rt.supplier_penalty,
                    rt.service_penalty,
                    COALESCE(rt.supplier_penalty, 0) + COALESCE(rt.service_penalty, 0) as total_penalty,
                    rt.refund_to_passenger,
                    s.name as supplier_name,
                    c.name as sold_to_name,
                    m.name as paid_to_name,
                    rt.status,
                    GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                    rt.remarks
                    FROM refunded_tickets rt
                    LEFT JOIN suppliers s ON rt.supplier = s.id
                    LEFT JOIN clients c ON rt.sold_to = c.id
                    LEFT JOIN main_account m ON rt.paid_to = m.id
                    LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                    WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                    GROUP BY rt.id";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'PNR', 
                    'Title', 
                    'Passenger Name', 
                    'Phone', 
                    'Gender',
                    'Sector', 
                    'Trip Type', 
                    'Airline',
                    'Issue Date', 
                    'Departure Date',
                    'Currency', 
                    'Sold Amount', 
                    'Base Amount',
                    'Supplier Penalty', 
                    'Service Penalty',
                    'Total Penalty',
                    'Refund Amount',
                    'Supplier', 
                    'Sold To', 
                    'Paid To',
                    'Status', 
                    'Receipt', 
                    'Remarks'
                ];
                break;

            case 'date_change_ticket':
                $query = "SELECT
                    dc.pnr,
                    dc.title,
                    dc.passenger_name,
                    dc.phone,
                    dc.gender,
                    dc.origin,
                    dc.destination,
                    '' as trip_type,
                    '' as return_destination,
                    dc.airline,
                    dc.issue_date,
                    dc.departure_date,
                    dc.currency,
                    dc.sold,
                    dc.base,
                    dc.supplier_penalty,
                    dc.service_penalty,
                    COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0) as total_penalty,
                    s.name as supplier_name,
                    c.name as sold_to_name,
                    m.name as paid_to_name,
                    dc.status,
                    GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                    dc.remarks
                    FROM date_change_tickets dc
                    LEFT JOIN suppliers s ON dc.supplier = s.id
                    LEFT JOIN clients c ON dc.sold_to = c.id
                    LEFT JOIN main_account m ON dc.paid_to = m.id
                    LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                    WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                    GROUP BY dc.id";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'PNR', 
                    'Title', 
                    'Passenger Name', 
                    'Phone', 
                    'Gender',
                    'Sector', 
                    'Trip Type', 
                    'Airline',
                    'Issue Date', 
                    'Departure Date',
                    'Currency', 
                    'Sold Amount', 
                    'Base',
                    'Supplier Penalty', 
                    'Service Penalty',
                    'Total Penalty',
                    'Supplier', 
                    'Sold To', 
                    'Paid To',
                    'Status',
                    'Receipt',
                    'Remarks'
                ];
                break;
            
            case 'hotel':
                $query = "SELECT
                     h.order_id,
                     h.title,
                     CONCAT(h.first_name, ' ', h.last_name) as guest_name,
                     h.contact_no,
                     h.gender,
                     h.issue_date,
                     h.check_in_date,
                     h.check_out_date,
                     h.accommodation_details,
                     h.currency,
                     h.base_amount,
                     h.sold_amount,
                     h.profit,
                     s.name as supplier_name,
                     c.name as client_name,
                     m.name as account_name,
                     GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                     h.remarks
                     FROM hotel_bookings h
                     LEFT JOIN suppliers s ON h.supplier_id = s.id
                     LEFT JOIN clients c ON h.sold_to = c.id
                     LEFT JOIN main_account m ON h.paid_to = m.id
                     LEFT JOIN main_account_transactions mat ON h.id = mat.reference_id and mat.transaction_of = 'hotel'
                     WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?
                     GROUP BY h.id";
                 $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'Order ID', 
                    'Title', 
                    'Guest Name', 
                    'Contact', 
                    'Gender',
                    'Issue Date', 
                    'Check-in Date',
                    'Check-out Date',
                    'Accommodation Details',
                    'Currency', 
                    'Base Amount', 
                    'Sold Amount', 
                    'Profit',
                    'Supplier',
                    'Client',
                    'Account',
                    'Receipt',
                    'Remarks'
                ];
                break;
            
            case 'hotel_refund':
                $query = "SELECT 
                    hb.order_id, 
                    hb.title, 
                    CONCAT(hb.first_name, ' ', hb.last_name) as guest_name,
                    hb.contact_no, 
                    hb.gender,
                    h.created_at,
                    hb.currency, 
                    h.refund_amount,
                    s.name as supplier_name,
                    c.name as client_name,
                    'Refunded' as status,
                    h.reason
                    FROM hotel_refunds h
                    INNER JOIN hotel_bookings hb ON h.booking_id = hb.id
                    LEFT JOIN suppliers s ON hb.supplier_id = s.id
                    LEFT JOIN clients c ON hb.sold_to = c.id
                    WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?
                    GROUP BY h.id";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'Order ID', 
                    'Title', 
                    'Guest Name', 
                    'Contact', 
                    'Gender',
                    'Refund Date',
                    'Currency', 
                    'Refund Amount',
                    'Supplier',
                    'Client',
                    'Status',
                    'Reason'
                ];
                break;
            
            case 'expense':
                $query = "SELECT
                    e.id,
                    e.description,
                    e.date,
                    e.amount,
                    e.currency,
                    ec.name as category,
                    m.name as account_name,
                    ba.allocated_amount as budget_allocated,
                    ba.remaining_amount as budget_remaining
                    FROM expenses e
                    LEFT JOIN expense_categories ec ON e.category_id = ec.id
                    LEFT JOIN main_account m ON e.main_account_id = m.id
                    LEFT JOIN budget_allocations ba ON e.allocation_id = ba.id
                    WHERE e.date BETWEEN ? AND ? AND e.tenant_id = ? AND e.branch_id = ?"
                    . ($expenseCategory && $expenseCategory !== 'all' ? " AND e.category_id = ?" : "") .
                    " ORDER BY e.date DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                if ($expenseCategory && $expenseCategory !== 'all') {
                    $params[] = $expenseCategory;
                }
                $headers = [
                    'ID', 
                    'Description', 
                    'Date', 
                    'Amount', 
                    'Currency',
                    'Category',
                    'Account',
                    'Budget Allocated',
                    'Budget Remaining'
                ];
                break;
            
            case 'creditor':
                $query = "SELECT
                         c.id,
                         c.name as creditor_name,
                         c.phone,
                         c.email,
                         c.address,
                         c.balance,
                         c.currency,
                         c.status,
                         (SELECT COALESCE(SUM(amount), 0)
                          FROM creditor_transactions
                          WHERE creditor_id = c.id
                          AND transaction_type = 'credit'
                          AND payment_date BETWEEN ? AND ? AND tenant_id = ? AND branch_id = ?) as paid_amount,
                         (SELECT COALESCE(SUM(amount), 0)
                          FROM creditor_transactions
                          WHERE creditor_id = c.id
                          AND transaction_type = 'debit'
                          AND payment_date BETWEEN ? AND ? AND tenant_id = ? AND branch_id = ?) as received_amount
                         FROM creditors c
                         WHERE c.tenant_id = ? AND c.branch_id = ?
                         ORDER BY c.name ASC";
                 $params = [$startDate, $endDate, $tenant_id, $branch_id, $startDate, $endDate, $tenant_id, $branch_id, $tenant_id, $branch_id];
                $headers = ['ID', 'Creditor Name', 'Phone', 'Email', 'Address', 'Balance', 'Currency', 'Status', 'Paid Amount', 'Received Amount'];
                break;
            
                case 'debtor':
                    $query = "SELECT 
                            d.id, 
                            d.name as debtor_name,
                            d.phone,
                            d.email,
                            d.address,
                            d.currency,
                            d.created_at,
                            d.balance as remaining_amount,
                            (SELECT COALESCE(SUM(amount), 0) 
                             FROM debtor_transactions 
                             WHERE debtor_id = d.id 
                             AND transaction_type = 'credit' 
                             AND payment_date BETWEEN ? AND ?) as received_amount,
                            d.balance + (SELECT COALESCE(SUM(amount), 0) 
                             FROM debtor_transactions 
                             WHERE debtor_id = d.id 
                             AND transaction_type = 'credit' 
                             AND payment_date BETWEEN ? AND ?) as balance
                            FROM debtors d 
                            WHERE d.status = 'active' AND d.tenant_id = ? AND d.branch_id = ?
                            ORDER BY d.name ASC";
                    $params = [$startDate, $endDate, $startDate, $endDate, $tenant_id, $branch_id];
                    $headers = ['Date', 'Debtor Name', 'Phone', 'Email', 'Address', 'Balance', 'Received Amount', 'Remaining Amount'];
                    break;
            case 'additional_payment':
                $query = "SELECT
                    ap.id,
                    ap.payment_type,
                    ap.description,
                    ap.base_amount,
                    ap.sold_amount,
                    ap.profit,
                    ap.currency,
                    m.name as account_name,
                    u.name as created_by,
                    DATE_FORMAT(ap.created_at, '%Y-%m-%d %H:%i:%s') as payment_date,
                    'Paid' as paid_status
                    FROM additional_payments ap
                    LEFT JOIN main_account m ON ap.main_account_id = m.id
                    LEFT JOIN users u ON ap.created_by = u.id
                    WHERE ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ? AND ap.branch_id = ?
                    ORDER BY ap.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'ID', 
                    'Payment Type', 
                    'Description', 
                    'Base Amount',
                    'Sold Amount',
                    'Profit',
                    'Currency',
                    'Account',
                    'Created By',
                    'Payment Date',
                    'Status'
                ];
                break;
            
            case 'visa':
                $query = "SELECT
                    v.id,
                    v.passport_number,
                    v.title,
                    v.applicant_name,
                    v.gender,
                    v.country,
                    v.visa_type,
                    v.receive_date,
                    v.applied_date,
                    v.issued_date,
                    v.base,
                    v.sold,
                    v.profit,
                    v.currency,
                    v.status,
                    s.name as supplier_name,
                    c.name as client_name,
                    m.name as account_name,
                    v.remarks
                    FROM visa_applications v
                    LEFT JOIN suppliers s ON v.supplier = s.id
                    LEFT JOIN clients c ON v.sold_to = c.id
                    LEFT JOIN main_account m ON v.paid_to = m.id
                    WHERE v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?
                    ORDER BY v.receive_date DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'ID', 
                    'Passport Number', 
                    'Title', 
                    'Applicant Name',
                    'Gender',
                    'Country',
                    'Visa Type',
                    'Receive Date',
                    'Applied Date',
                    'Issued Date',
                    'Base Amount',
                    'Sold Amount',
                    'Profit',
                    'Currency',
                    'Status',
                    'Supplier',
                    'Client',
                    'Account',
                    'Remarks'
                ];
                break;
            
                case 'umrah':

                    // ===============================
                    // 1. QUERY
                    // ===============================
                    $query = "
                        SELECT
                            u.booking_id,
                            f.head_of_family,
                            u.name,
                            u.fname,
                            u.gender,
                            u.dob,
                            u.passport_number,
                            u.passport_expiry,
                            u.id_type,
                            f.contact,
                            f.address,
                            g.group_name,
                            u.entry_date,
                            u.created_at,
                            COALESCE(u.flight_date, (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS flight_date,
                            COALESCE(u.return_date, (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS return_date,
                            u.duration,
                            u.room_type,
                            u.price,
                            u.sold_price,
                            u.discount,
                            u.profit,
                            u.received_bank_payment,
                            u.bank_receipt_number,
                            u.paid,
                            u.due,
                            u.currency,
                            u.exchange_rate,
                            u.status,
                            f.tazmin,
                            c.name AS client_name,
                            m.name AS account_name,
                            ur.refund_type AS refund_status,
                            GROUP_CONCAT(DISTINCT ubs.service_type SEPARATOR ', ') AS services,
                            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS supplier_name,
                            u.remarks

                        FROM umrah_bookings u
                        LEFT JOIN families f ON u.family_id = f.family_id
                        LEFT JOIN umrah_groups g ON f.group_id = g.group_id
                        LEFT JOIN clients c ON u.sold_to = c.id
                        LEFT JOIN main_account m ON u.paid_to = m.id
                        LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                        LEFT JOIN umrah_booking_services ubs ON u.booking_id = ubs.booking_id
                        LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                        LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                        WHERE " . $umrahDateCondition . "
                          AND u.tenant_id = ?
                          AND u.branch_id = ?
                    ";

                    $query .= $umrahFilterSql;

                    $query .= "
                        GROUP BY u.booking_id
                        ORDER BY u.entry_date DESC
                    ";

                    $params = array_merge($umrahDateParams, [$tenant_id, $branch_id]);
                    $params = array_merge($params, $umrahFilterParams);

                    // ===============================
                    // 2. HEADERS (SINGLE SOURCE OF TRUTH)
                    // ===============================
                    $headers = [
                        'Head of Family',
                        'Name',
                        'Father Name',
                        'Gender',
                        'Date of Birth',
                        'Passport Number',
                        'Passport Expiry',
                        'ID Type',
                        'Phone',
                        'Address',
                        'Group',
                        'Entry Date',
                        'Created At',
                        'Flight Date',
                        'Return Date',
                        'Duration',
                        'Room Type',
                        'Sold Price',
                        'Bank Payment',
                        'Bank Receipt',
                        'Paid',
                        'Due',
                        'Currency',
                        'Exchange Rate',
                        'Status',
                        'Tazmin',
                        'Client',
                        'Account',
                        'Refund Status',
                        'Services',
                        'Remarks',
                    ];

                    // Sensitive columns only for admin
                    if ($user_role === 'admin') {
                        $insertIndex = array_search('Sold Price', $headers);
                        if ($insertIndex !== false) {
                            array_splice($headers, $insertIndex, 0, ['Price']);
                            $soldPriceIndex = array_search('Sold Price', $headers);
                            array_splice($headers, $soldPriceIndex + 1, 0, ['Discount', 'Profit']);
                        }
                        $headers[] = 'Supplier';
                    }

                    break;
                

            case 'ticket_weight':
                $query = "SELECT
                    t.pnr,
                    t.passenger_name,
                    tw.weight,
                    tw.base_price,
                    tw.sold_price,
                    tw.profit,
                    t.currency,
                    tw.created_at,
                    tw.remarks,
                    s.name as supplier_name,
                    c.name as client_name,
                    m.name as account_name
                    FROM ticket_weights tw
                    LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                    LEFT JOIN suppliers s ON t.supplier = s.id
                    LEFT JOIN clients c ON t.sold_to = c.id
                    LEFT JOIN main_account m ON t.paid_to = m.id
                    WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ? AND tw.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'PNR',
                    'Passenger Name',
                    'Weight (kg)',
                    'Base Price',
                    'Sold Price',
                    'Profit',
                    'Currency',
                    'Date',
                    'Remarks',
                    'Supplier',
                    'Client',
                    'Account'
                ];
                break;

            case 'visa_refund':
                $query = "SELECT
                    va.passport_number,
                    va.applicant_name,
                    vr.refund_type,
                    vr.refund_amount,
                    vr.currency,
                    vr.refund_date,
                    vr.reason,
                    s.name as supplier_name,
                    c.name as client_name,
                    m.name as account_name,
                    u.name as processed_by_name
                    FROM visa_refunds vr
                    LEFT JOIN visa_applications va ON vr.visa_id = va.id
                    LEFT JOIN suppliers s ON va.supplier = s.id
                    LEFT JOIN clients c ON va.sold_to = c.id
                    LEFT JOIN main_account m ON va.paid_to = m.id
                    LEFT JOIN users u ON vr.processed_by = u.id
                    WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ? AND vr.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'Passport Number',
                    'Applicant Name',
                    'Refund Type',
                    'Refund Amount',
                    'Currency',
                    'Exchange Rate',
                    'Refund Date',
                    'Reason',
                    'Supplier',
                    'Client',
                    'Account',
                    'Processed By'
                ];
                break;

            case 'hotel_refund':
                $query = "SELECT
                    hb.order_id,
                    CONCAT(hb.first_name, ' ', hb.last_name) as guest_name,
                    hr.refund_type,
                    hr.refund_amount,
                    hr.currency,
                    hr.exchange_rate,
                    hr.created_at,
                    hr.reason,
                    s.name as supplier_name,
                    c.name as client_name,
                    m.name as account_name,
                    u.name as processed_by_name
                    FROM hotel_refunds hr
                    LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id
                    LEFT JOIN suppliers s ON hb.supplier_id = s.id
                    LEFT JOIN clients c ON hb.sold_to = c.id
                    LEFT JOIN main_account m ON hb.paid_to = m.id
                    LEFT JOIN users u ON hr.processed_by = u.id
                    WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ? AND hr.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'Order ID',
                    'Guest Name',
                    'Refund Type',
                    'Refund Amount',
                    'Currency',
                    'Exchange Rate',
                    'Refund Date',
                    'Reason',
                    'Supplier',
                    'Client',
                    'Account',
                    'Processed By'
                ];
                break;

            case 'umrah_refund':
                $query = "SELECT
                     ub.passport_number,
                     ub.name as pilgrim_name,
                     ub.fname,
                     ur.refund_type,
                     ur.refund_amount,
                     ur.supplier_penalty,
                     ur.service_penalty,
                     ur.base,
                     ur.sold,
                     ur.currency,
                     ub.exchange_rate,
                     ur.created_at,
                     ur.reason,
                     GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as supplier_name,
                     c.name as client_name,
                     m.name as account_name,
                     u.name as processed_by_name
                     FROM umrah_refunds ur
                     LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                     LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                     LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                     LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                     LEFT JOIN clients c ON ub.sold_to = c.id
                     LEFT JOIN main_account m ON ub.paid_to = m.id
                     LEFT JOIN users u ON ur.processed_by = u.id
                     WHERE ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ? AND ur.branch_id = ?
                     GROUP BY ur.id";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = [
                    'Passport Number',
                    'Pilgrim Name',
                    'Father Name',
                    'Refund Type',
                    'Refund Amount',
                    'Supplier Penalty',
                    'Service Penalty',
                    'Base',
                    'Sold',
                    'Currency',
                    'Exchange Rate',
                    'Refund Date',
                    'Reason',
                    'Supplier',
                    'Client',
                    'Account',
                    'Processed By'
                ];
                break;

            case 'general_summary':
                $headers = ['Date', 'Reference', 'Party', 'Details', 'Profit', 'Cash In', 'Cash Out', 'Currency'];
                $data = [];
                $query = null;

                $itemQueries = [
                    'Ticket Sales' => "SELECT DATE(tb.issue_date) d, tb.pnr ref, c.name party, CONCAT(tb.airline, ' - ', tb.origin, '-', tb.destination) details, tb.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, tb.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale' AND mt.type = 'credit' LIMIT 1), tb.currency) cash_currency FROM ticket_bookings tb LEFT JOIN clients c ON tb.sold_to = c.id WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?",
                    'Date Changes' => "SELECT DATE(dc.created_at) d, dc.pnr ref, c.name party, CONCAT(dc.airline, ' - ', dc.origin, '-', dc.destination) details, dc.service_penalty profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = dc.id AND mt.transaction_of = 'date_change' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, dc.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = dc.id AND mt.transaction_of = 'date_change' AND mt.type = 'credit' LIMIT 1), dc.currency) cash_currency FROM date_change_tickets dc LEFT JOIN clients c ON dc.sold_to = c.id WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?",
                    'Ticket Reservations' => "SELECT DATE(tr.issue_date) d, tr.pnr ref, c.name party, CONCAT(tr.airline, ' - ', tr.origin, '-', tr.destination) details, tr.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, tr.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve' AND mt.type = 'credit' LIMIT 1), tr.currency) cash_currency FROM ticket_reservations tr LEFT JOIN clients c ON tr.sold_to = c.id WHERE tr.issue_date BETWEEN ? AND ? AND tr.tenant_id = ? AND tr.branch_id = ?",
                    'Ticket Weight' => "SELECT DATE(tw.created_at) d, t.pnr ref, c.name party, CONCAT(tw.weight, ' kg') details, tw.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = tw.id AND mt.transaction_of = 'weight' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, COALESCE(t.currency, 'USD') currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = tw.id AND mt.transaction_of = 'weight' AND mt.type = 'credit' LIMIT 1), COALESCE(t.currency, 'USD')) cash_currency FROM ticket_weights tw LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id LEFT JOIN clients c ON t.sold_to = c.id WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ? AND tw.branch_id = ?",
                    'Hotel' => "SELECT DATE(h.issue_date) d, h.order_id ref, c.name party, CONCAT(h.title, ' ', h.first_name, ' ', h.last_name) details, h.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = h.id AND mt.transaction_of = 'hotel' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, h.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = h.id AND mt.transaction_of = 'hotel' AND mt.type = 'credit' LIMIT 1), h.currency) cash_currency FROM hotel_bookings h LEFT JOIN clients c ON h.sold_to = c.id WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?",
                    'Visa' => "SELECT DATE(v.receive_date) d, v.passport_number ref, c.name party, CONCAT(v.country, ' - ', v.visa_type) details, v.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = v.id AND mt.transaction_of = 'visa_sale' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, v.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = v.id AND mt.transaction_of = 'visa_sale' AND mt.type = 'credit' LIMIT 1), v.currency) cash_currency FROM visa_applications v LEFT JOIN clients c ON v.sold_to = c.id WHERE v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?",
                    'Umrah Bookings' => "SELECT DATE(u.entry_date) d, u.passport_number ref, c.name party, u.duration details, u.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt LEFT JOIN umrah_transactions ut ON mt.reference_id = ut.id WHERE mt.transaction_of = 'umrah_transaction' AND mt.type = 'credit' AND ut.umrah_booking_id = u.booking_id), 0) cash_in, 0 cash_out, u.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt LEFT JOIN umrah_transactions ut ON mt.reference_id = ut.id WHERE mt.transaction_of = 'umrah_transaction' AND mt.type = 'credit' AND ut.umrah_booking_id = u.booking_id LIMIT 1), u.currency) cash_currency FROM umrah_bookings u LEFT JOIN clients c ON u.sold_to = c.id WHERE u.entry_date BETWEEN ? AND ? AND u.tenant_id = ? AND u.branch_id = ?",
                    'Additional Payments' => "SELECT DATE(ap.created_at) d, ap.id ref, m.name party, ap.payment_type details, ap.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = ap.id AND mt.transaction_of = 'additional_payment' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, ap.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = ap.id AND mt.transaction_of = 'additional_payment' AND mt.type = 'credit' LIMIT 1), ap.currency) cash_currency FROM additional_payments ap LEFT JOIN main_account m ON ap.main_account_id = m.id WHERE ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ? AND ap.branch_id = ?",
                    'Supplier Transactions' => "SELECT DATE(st.transaction_date) d, st.receipt ref, s.name party, CONCAT(COALESCE(st.remarks, ''), ' (', st.transaction_of, ')') details, 0 profit, IF(st.transaction_type = 'Debit' AND st.transaction_of = 'fund_withdrawal', st.amount, 0) cash_in, IF(st.transaction_type = 'Credit' AND st.transaction_of IN ('fund', 'supplier_bonus'), st.amount, 0) cash_out, COALESCE(s.currency, 'USD') currency, COALESCE(s.currency, 'USD') cash_currency FROM supplier_transactions st LEFT JOIN suppliers s ON st.supplier_id = s.id WHERE st.transaction_date BETWEEN ? AND ? AND st.tenant_id = ? AND st.branch_id = ?",
                    'Client Transactions' => "SELECT DATE(ct.created_at) d, ct.receipt ref, c.name party, COALESCE(ct.description, ct.transaction_of) details, 0 profit, IF(ct.type = 'credit' AND ct.transaction_of = 'fund', ct.amount, 0) cash_in, IF(ct.type = 'debit' AND ct.transaction_of = 'client_withdrawal', ct.amount, 0) cash_out, ct.currency currency, ct.currency cash_currency FROM client_transactions ct LEFT JOIN clients c ON ct.client_id = c.id WHERE ct.created_at BETWEEN ? AND ? AND ct.tenant_id = ? AND ct.branch_id = ?",
                ];
                $cashQueries = [
                    'Operating Expenses' => "SELECT DATE(mt.created_at) d, mt.receipt ref, ec.name party, e.description details, 0 profit, 0 cash_in, mt.amount cash_out, mt.currency currency FROM main_account_transactions mt LEFT JOIN expenses e ON mt.reference_id = e.id AND mt.transaction_of = 'expense' LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE mt.transaction_of = 'expense' AND mt.type = 'debit' AND mt.created_at BETWEEN ? AND ? AND mt.tenant_id = ? AND mt.branch_id = ?",
                    'Ticket Refunds Paid' => "SELECT DATE(mt.created_at) d, rt.pnr ref, rt.passenger_name party, CONCAT(rt.airline, ' - ', rt.origin, '-', rt.destination) details, 0 profit, 0 cash_in, mt.amount cash_out, mt.currency currency FROM main_account_transactions mt LEFT JOIN refunded_tickets rt ON mt.reference_id = rt.id AND mt.transaction_of = 'ticket_refund' WHERE mt.transaction_of = 'ticket_refund' AND mt.type = 'debit' AND mt.created_at BETWEEN ? AND ? AND mt.tenant_id = ? AND mt.branch_id = ?",
                    'Hotel Refunds Paid' => "SELECT DATE(mt.created_at) d, hb.order_id ref, CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) party, hb.accommodation_details details, 0 profit, 0 cash_in, mt.amount cash_out, mt.currency currency FROM main_account_transactions mt LEFT JOIN hotel_refunds hr ON mt.reference_id = hr.id AND mt.transaction_of = 'hotel_refund' LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id WHERE mt.transaction_of = 'hotel_refund' AND mt.type = 'debit' AND mt.created_at BETWEEN ? AND ? AND mt.tenant_id = ? AND mt.branch_id = ?",
                    'Visa Refunds Paid' => "SELECT DATE(mt.created_at) d, va.passport_number ref, va.applicant_name party, CONCAT(va.country, ' - ', va.visa_type) details, 0 profit, 0 cash_in, mt.amount cash_out, mt.currency currency FROM main_account_transactions mt LEFT JOIN visa_refunds vr ON mt.reference_id = vr.id AND mt.transaction_of = 'visa_refund' LEFT JOIN visa_applications va ON vr.visa_id = va.id WHERE mt.transaction_of = 'visa_refund' AND mt.type = 'debit' AND mt.created_at BETWEEN ? AND ? AND mt.tenant_id = ? AND mt.branch_id = ?",
                    'Umrah Refunds Paid' => "SELECT DATE(mt.created_at) d, ub.passport_number ref, ub.name party, ub.duration details, 0 profit, 0 cash_in, mt.amount cash_out, mt.currency currency FROM main_account_transactions mt LEFT JOIN umrah_refunds ur ON mt.reference_id = ur.id AND mt.transaction_of = 'umrah_refund' LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id WHERE mt.transaction_of = 'umrah_refund' AND mt.type = 'debit' AND mt.created_at BETWEEN ? AND ? AND mt.tenant_id = ? AND mt.branch_id = ?",
                    'Salary Payments' => "SELECT DATE(mt.created_at) d, mt.receipt ref, su.name party, mt.description details, 0 profit, 0 cash_in, mt.amount cash_out, mt.currency currency FROM main_account_transactions mt LEFT JOIN salary_payments sp ON mt.reference_id = sp.id AND mt.transaction_of = 'salary_payment' LEFT JOIN users su ON sp.user_id = su.id WHERE mt.transaction_of = 'salary_payment' AND mt.type = 'debit' AND mt.created_at BETWEEN ? AND ? AND mt.tenant_id = ? AND mt.branch_id = ?",
                ];
                $entityHeaders = ['Entity', 'Balance', 'Paid', 'Remaining', 'Currency'];
                $entityQueries = [
                    'Payments from Debtors' => "SELECT d.name entity, (d.balance + COALESCE((SELECT SUM(dt.amount) FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'credit' AND dt.payment_date BETWEEN ? AND ? AND dt.branch_id = ?), 0) - COALESCE((SELECT SUM(dt.amount) FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'debit' AND dt.payment_date BETWEEN ? AND ? AND dt.branch_id = ?), 0)) balance, COALESCE((SELECT SUM(dt.amount) FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'credit' AND dt.payment_date BETWEEN ? AND ? AND dt.branch_id = ?), 0) paid, d.balance remaining, d.currency currency FROM debtors d WHERE d.tenant_id = ? AND d.branch_id = ? AND EXISTS (SELECT 1 FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'credit' AND dt.payment_date BETWEEN ? AND ?) ORDER BY d.name",
                    'Loans to Debtors' => "SELECT d.name entity, (d.balance + COALESCE((SELECT SUM(dt.amount) FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'credit' AND dt.payment_date BETWEEN ? AND ? AND dt.branch_id = ?), 0) - COALESCE((SELECT SUM(dt.amount) FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'debit' AND dt.payment_date BETWEEN ? AND ? AND dt.branch_id = ?), 0)) balance, COALESCE((SELECT SUM(dt.amount) FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'debit' AND dt.payment_date BETWEEN ? AND ? AND dt.branch_id = ?), 0) paid, d.balance remaining, d.currency currency FROM debtors d WHERE d.tenant_id = ? AND d.branch_id = ? AND EXISTS (SELECT 1 FROM debtor_transactions dt WHERE dt.debtor_id = d.id AND dt.transaction_type = 'debit' AND dt.payment_date BETWEEN ? AND ?) ORDER BY d.name",
                    'Payments to Creditors' => "SELECT c.name entity, (c.balance + COALESCE((SELECT SUM(ct.amount) FROM creditor_transactions ct WHERE ct.creditor_id = c.id AND ct.transaction_type = 'debit' AND ct.payment_date BETWEEN ? AND ? AND ct.branch_id = ?), 0) - COALESCE((SELECT SUM(ct.amount) FROM creditor_transactions ct WHERE ct.creditor_id = c.id AND ct.transaction_type = 'credit' AND ct.payment_date BETWEEN ? AND ? AND ct.branch_id = ?), 0)) balance, COALESCE((SELECT SUM(ct.amount) FROM creditor_transactions ct WHERE ct.creditor_id = c.id AND ct.transaction_type = 'debit' AND ct.payment_date BETWEEN ? AND ? AND ct.branch_id = ?), 0) paid, c.balance remaining, c.currency currency FROM creditors c WHERE c.tenant_id = ? AND c.branch_id = ? AND EXISTS (SELECT 1 FROM creditor_transactions ct WHERE ct.creditor_id = c.id AND ct.transaction_type = 'debit' AND ct.payment_date BETWEEN ? AND ?) ORDER BY c.name",
                ];

$totals = [];
                $sections = [];
                $incomeGroups = [
                    'TICKET' => ['Ticket Sales', 'Date Changes', 'Ticket Reservations', 'Ticket Weight'],
                    'HOTEL' => ['Hotel'],
                    'VISA' => ['Visa'],
                    'UMRAH' => ['Umrah Bookings'],
                    'ADDITIONAL PAYMENTS' => ['Additional Payments'],
                    'OTHER INCOME' => ['Payments from Debtors'],
                    'SUPPLIERS' => ['Supplier Transactions'],
                    'CLIENTS' => ['Client Transactions'],
                ];
                $expenseGroups = [
                    'EXPENSES' => ['Operating Expenses', 'Ticket Refunds Paid', 'Hotel Refunds Paid', 'Visa Refunds Paid', 'Umrah Refunds Paid', 'Loans to Debtors', 'Payments to Creditors', 'Salary Payments'],
                ];
                foreach ($incomeGroups as $groupTitle => $labels) {
                    $mini = [];
                    foreach ($labels as $label) {
                        if (isset($entityQueries[$label])) {
                            $rows = [];
                            $dpr = [$startDate, $endDate, $branch_id];
                            $st = $pdo->prepare($entityQueries[$label]);
                            $st->execute(array_merge($dpr, $dpr, $dpr, [$tenant_id, $branch_id], [$startDate, $endDate]));
                            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                                $rows[] = ['entity' => $r['entity'], 'balance' => floatval($r['balance']), 'paid' => floatval($r['paid']), 'remaining' => floatval($r['remaining']), 'currency' => $r['currency']];
                                $cur = $r['currency'];
                                $totals[$cur]['cash_in'] = (isset($totals[$cur]['cash_in']) ? $totals[$cur]['cash_in'] : 0) + floatval($r['paid']);
                            }
                            if (empty($rows)) {
                                $rows[] = ['entity' => 'No records in selected period', 'balance' => 0, 'paid' => 0, 'remaining' => 0, 'currency' => 'N/A'];
                            }
                            $mini[] = ['title' => $label, 'headers' => $entityHeaders, 'rows' => $rows];
                            continue;
                        }
                        $rows = [];
                        if (isset($itemQueries[$label])) {
                            $st = $pdo->prepare($itemQueries[$label]);
                            $st->execute([$startDate, $endDate, $tenant_id, $branch_id]);
                            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                                $rows[] = ['date' => $r['d'], 'reference' => $r['ref'], 'party' => $r['party'], 'details' => $r['details'], 'profit' => floatval($r['profit']), 'cash_in' => floatval($r['cash_in']), 'cash_out' => floatval($r['cash_out']), 'currency' => $r['currency'], 'cash_currency' => $r['cash_currency']];
                                $cur = $r['currency'];
                                $cashCur = !empty($r['cash_currency']) ? $r['cash_currency'] : $cur;
                                $totals[$cur]['profit'] = (isset($totals[$cur]['profit']) ? $totals[$cur]['profit'] : 0) + floatval($r['profit']);
                                $totals[$cashCur]['cash_in'] = (isset($totals[$cashCur]['cash_in']) ? $totals[$cashCur]['cash_in'] : 0) + floatval($r['cash_in']);
                                $totals[$cashCur]['cash_out'] = (isset($totals[$cashCur]['cash_out']) ? $totals[$cashCur]['cash_out'] : 0) + floatval($r['cash_out']);
                            }
                        } elseif (isset($cashQueries[$label])) {
                            $st = $pdo->prepare($cashQueries[$label]);
                            $st->execute([$startDate, $endDate, $tenant_id, $branch_id]);
                            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                                $rows[] = ['date' => $r['d'], 'reference' => $r['ref'], 'party' => $r['party'], 'details' => $r['details'], 'profit' => floatval($r['profit']), 'cash_in' => floatval($r['cash_in']), 'cash_out' => floatval($r['cash_out']), 'currency' => $r['currency']];
                                $cur = $r['currency'];
                                $totals[$cur]['cash_in'] = (isset($totals[$cur]['cash_in']) ? $totals[$cur]['cash_in'] : 0) + floatval($r['cash_in']);
                            }
                        }
                        if (empty($rows)) {
                            $rows[] = ['date' => '', 'reference' => '', 'party' => 'No records in selected period', 'details' => '', 'profit' => 0, 'cash_in' => 0, 'cash_out' => 0, 'currency' => 'N/A'];
                        }
                        $mini[] = ['title' => $label, 'rows' => $rows];
                    }
                    $sections[] = ['title' => $groupTitle, 'tables' => $mini];
                }
                foreach ($expenseGroups as $groupTitle => $labels) {
                    $mini = [];
                    foreach ($labels as $label) {
                        if (isset($entityQueries[$label])) {
                            $rows = [];
                            $dpr = [$startDate, $endDate, $branch_id];
                            $st = $pdo->prepare($entityQueries[$label]);
                            $st->execute(array_merge($dpr, $dpr, $dpr, [$tenant_id, $branch_id], [$startDate, $endDate]));
                            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                                $rows[] = ['entity' => $r['entity'], 'balance' => floatval($r['balance']), 'paid' => floatval($r['paid']), 'remaining' => floatval($r['remaining']), 'currency' => $r['currency']];
                                $cur = $r['currency'];
                                $totals[$cur]['cash_out'] = (isset($totals[$cur]['cash_out']) ? $totals[$cur]['cash_out'] : 0) + floatval($r['paid']);
                                $totals[$cur]['expense_out'] = (isset($totals[$cur]['expense_out']) ? $totals[$cur]['expense_out'] : 0) + floatval($r['paid']);
                            }
                            if (empty($rows)) {
                                $rows[] = ['entity' => 'No records in selected period', 'balance' => 0, 'paid' => 0, 'remaining' => 0, 'currency' => 'N/A'];
                            }
                            $mini[] = ['title' => $label, 'headers' => $entityHeaders, 'rows' => $rows];
                            continue;
                        }
                        $rows = [];
                        $st = $pdo->prepare($cashQueries[$label]);
                        $st->execute([$startDate, $endDate, $tenant_id, $branch_id]);
                        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                            $rows[] = ['date' => $r['d'], 'reference' => $r['ref'], 'party' => $r['party'], 'details' => $r['details'], 'profit' => 0, 'cash_in' => 0, 'cash_out' => floatval($r['cash_out']), 'currency' => $r['currency']];
                            $cur = $r['currency'];
                            $totals[$cur]['cash_out'] = (isset($totals[$cur]['cash_out']) ? $totals[$cur]['cash_out'] : 0) + floatval($r['cash_out']);
                            $totals[$cur]['expense_out'] = (isset($totals[$cur]['expense_out']) ? $totals[$cur]['expense_out'] : 0) + floatval($r['cash_out']);
                        }
                        if (empty($rows)) {
                            $rows[] = ['date' => '', 'reference' => '', 'party' => 'No records in selected period', 'details' => '', 'profit' => 0, 'cash_in' => 0, 'cash_out' => 0, 'currency' => 'N/A'];
                        }
                        $mini[] = ['title' => $label, 'rows' => $rows];
                    }
                    $sections[] = ['title' => $groupTitle, 'tables' => $mini];
                }
                $accountLedgerSql = "SELECT mt.main_account_id account_id, mt.type, mt.transaction_of, mt.amount, DATE(mt.created_at) d, mt.receipt ref,
                    CASE
                        WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' (Ticket Sale)')
                        WHEN mt.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name, ' (Ticket Reserve)')
                        WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rft.passenger_name, ' (Ticket Refund)')
                        WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' (Date Change)')
                        WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(va_sale.applicant_name, ' (Visa Sale)')
                        WHEN mt.transaction_of = 'umrah' THEN CONCAT(ub.name, ' (Umrah)')
                        WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb_txn.title, hb_txn.first_name, ' ', hb_txn.last_name, ' (Hotel)')
                        WHEN mt.transaction_of = 'fund' THEN CONCAT(usr.name, ' (Fund)')
                        WHEN mt.transaction_of = 'withdraw_fund' THEN CONCAT(wf_usr.name, ' (Withdraw Fund)')
                        WHEN mt.transaction_of = 'hotel_refund' THEN CONCAT(hb_refund.title, hb_refund.first_name, ' ', hb_refund.last_name, ' (Hotel Refund)')
                        WHEN mt.transaction_of = 'deposit_sarafi' THEN CONCAT(sc.name, ' (Deposit Sarafi)')
                        WHEN mt.transaction_of = 'withdrawal_sarafi' THEN CONCAT(sc.name, ' (Withdrawal Sarafi)')
                        WHEN mt.transaction_of = 'hawala_sarafi' THEN CONCAT(sc.name, ' (Hawala Sarafi)')
                        WHEN mt.transaction_of = 'salary_payment' THEN CONCAT(su.name, ' (Salary Payment)')
                        WHEN mt.transaction_of = 'debtor' THEN CONCAT(d.name, ' (Debtor)')
                        WHEN mt.transaction_of = 'creditor' THEN CONCAT(COALESCE(c.name, cd.name), ' (Creditor)')
                        WHEN mt.transaction_of = 'supplier_fund' THEN CONCAT(sup.name, ' (Supplier Fund)')
                        WHEN mt.transaction_of = 'supplier_fund_withdrawal' THEN CONCAT(sup2.name, ' (Supplier Fund Withdrawal)')
                        WHEN mt.transaction_of = 'client_fund' THEN CONCAT(cl.name, ' (Client Fund)')
                        WHEN mt.transaction_of = 'client_withdraw' THEN CONCAT(cl_wd.name, ' (Client Withdraw)')
                        WHEN mt.transaction_of = 'global_budget_allocation' THEN 'Global Budget Allocation'
                        WHEN mt.transaction_of = 'budget_allocation' THEN CONCAT(ec.name, ' (Budget Allocation)')
                        WHEN mt.transaction_of = 'expense' THEN CONCAT(exp_cat.name, ' (Expense)')
                        WHEN mt.transaction_of = 'transfer' THEN CONCAT(xfr_acct.name, ' (Transfer)')
                        WHEN mt.transaction_of = 'umrah_transaction' THEN CONCAT(ub_txn.name, ' (Umrah Transaction)')
                        WHEN mt.transaction_of = 'additional_payment' THEN CONCAT(ap.description, ' (Additional Payment)')
                        WHEN mt.transaction_of = 'visa_refund' THEN CONCAT(va.applicant_name, ' (Visa Refund)')
                        WHEN mt.transaction_of = 'weight' THEN CONCAT(tb_weight.passenger_name, ' (Weight)')
                        WHEN mt.transaction_of = 'umrah_refund' THEN CONCAT(ub_refund.name, ' (Umrah Refund)')
                        ELSE CONCAT(mt.reference_id, ' (', mt.transaction_of, ')')
                    END party,
                    COALESCE(mt.description, '') details,
                    IF(mt.type = 'credit', mt.amount, 0) cash_in, IF(mt.type = 'debit', mt.amount, 0) cash_out,
                    mt.currency currency
                FROM main_account_transactions mt
                LEFT JOIN ticket_bookings tb ON mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale'
                LEFT JOIN ticket_reservations tr ON mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve'
                LEFT JOIN refunded_tickets rft ON mt.reference_id = rft.id AND mt.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON mt.reference_id = dc.id AND mt.transaction_of = 'date_change'
                LEFT JOIN umrah_bookings ub ON mt.reference_id = ub.booking_id AND mt.transaction_of = 'umrah'
                LEFT JOIN users usr ON usr.id = mt.reference_id AND mt.transaction_of = 'fund'
                LEFT JOIN users wf_usr ON wf_usr.id = mt.reference_id AND mt.transaction_of = 'withdraw_fund'
                LEFT JOIN sarafi_transactions st ON mt.reference_id = st.id AND mt.transaction_of IN ('deposit_sarafi', 'withdrawal_sarafi', 'hawala_sarafi')
                LEFT JOIN customers sc ON st.customer_id = sc.id AND mt.transaction_of IN ('deposit_sarafi', 'withdrawal_sarafi', 'hawala_sarafi')
                LEFT JOIN salary_payments sp ON mt.reference_id = sp.id AND mt.transaction_of = 'salary_payment'
                LEFT JOIN users su ON sp.user_id = su.id AND mt.transaction_of = 'salary_payment'
                LEFT JOIN debtor_transactions dt ON mt.reference_id = dt.id AND mt.transaction_of = 'debtor'
                LEFT JOIN debtors d ON dt.debtor_id = d.id AND mt.transaction_of = 'debtor'
                LEFT JOIN creditor_transactions ct ON mt.reference_id = ct.id AND mt.transaction_of = 'creditor'
                LEFT JOIN creditors c ON ct.creditor_id = c.id AND mt.transaction_of = 'creditor'
                LEFT JOIN creditors cd ON mt.reference_id = cd.id AND mt.transaction_of = 'creditor'
                LEFT JOIN supplier_transactions st2 ON mt.reference_id = st2.id AND mt.transaction_of = 'supplier_fund'
                LEFT JOIN suppliers sup ON st2.supplier_id = sup.id AND mt.transaction_of = 'supplier_fund'
                LEFT JOIN supplier_transactions st3 ON mt.reference_id = st3.id AND mt.transaction_of = 'supplier_fund_withdrawal'
                LEFT JOIN suppliers sup2 ON st3.supplier_id = sup2.id AND mt.transaction_of = 'supplier_fund_withdrawal'
                LEFT JOIN client_transactions clt ON mt.reference_id = clt.id AND mt.transaction_of = 'client_fund'
                LEFT JOIN clients cl ON clt.client_id = cl.id AND mt.transaction_of = 'client_fund'
                LEFT JOIN client_transactions clt_wd ON mt.reference_id = clt_wd.id AND mt.transaction_of = 'client_withdraw'
                LEFT JOIN clients cl_wd ON clt_wd.client_id = cl_wd.id AND mt.transaction_of = 'client_withdraw'
                LEFT JOIN budget_allocations ba ON mt.reference_id = ba.id AND mt.transaction_of = 'budget_allocation'
                LEFT JOIN expense_categories ec ON ba.category_id = ec.id AND mt.transaction_of = 'budget_allocation'
                LEFT JOIN global_budget_allocations ga ON mt.reference_id = ga.id AND mt.transaction_of = 'global_budget_allocation'
                LEFT JOIN expenses exp ON mt.reference_id = exp.id AND mt.transaction_of = 'expense'
                LEFT JOIN expense_categories exp_cat ON exp.category_id = exp_cat.id AND mt.transaction_of = 'expense'
                LEFT JOIN main_account xfr_acct ON mt.reference_id = xfr_acct.id AND mt.transaction_of = 'transfer'
                LEFT JOIN umrah_transactions ut ON mt.reference_id = ut.id AND mt.transaction_of = 'umrah_transaction'
                LEFT JOIN umrah_bookings ub_txn ON ut.umrah_booking_id = ub_txn.booking_id AND mt.transaction_of = 'umrah_transaction'
                LEFT JOIN additional_payments ap ON mt.reference_id = ap.id AND mt.transaction_of = 'additional_payment'
                LEFT JOIN visa_refunds vr ON mt.reference_id = vr.id AND mt.transaction_of = 'visa_refund'
                LEFT JOIN visa_applications va ON vr.visa_id = va.id AND mt.transaction_of = 'visa_refund'
                LEFT JOIN ticket_weights tw ON mt.reference_id = tw.id AND mt.transaction_of = 'weight'
                LEFT JOIN ticket_bookings tb_weight ON tw.ticket_id = tb_weight.id AND mt.transaction_of = 'weight'
                LEFT JOIN hotel_bookings hb_txn ON mt.reference_id = hb_txn.id AND mt.transaction_of = 'hotel'
                LEFT JOIN hotel_refunds hr ON mt.reference_id = hr.id AND mt.transaction_of = 'hotel_refund'
                LEFT JOIN hotel_bookings hb_refund ON hr.booking_id = hb_refund.id AND mt.transaction_of = 'hotel_refund'
                LEFT JOIN visa_applications va_sale ON mt.reference_id = va_sale.id AND mt.transaction_of = 'visa_sale'
                LEFT JOIN umrah_refunds ur ON mt.reference_id = ur.id AND mt.transaction_of = 'umrah_refund'
                LEFT JOIN umrah_bookings ub_refund ON ur.booking_id = ub_refund.booking_id AND mt.transaction_of = 'umrah_refund'
                WHERE mt.tenant_id = ? AND mt.branch_id = ? AND DATE(mt.created_at) BETWEEN ? AND ?
                ORDER BY mt.main_account_id, mt.created_at";
                $accSt = $pdo->prepare($accountLedgerSql);
                $accSt->execute([$tenant_id, $branch_id, $startDate, $endDate]);
                $byAccount = [];
                foreach ($accSt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $byAccount[$r['account_id']][] = $r;
                    if (in_array($r['transaction_of'], ['transfer', 'fund', 'withdraw_fund'], true)) {
                        $cur = $r['currency'];
                        $amt = floatval($r['amount']);
                        if ($r['type'] === 'credit') {
                            $totals[$cur]['cash_in'] = (isset($totals[$cur]['cash_in']) ? $totals[$cur]['cash_in'] : 0) + $amt;
                        } else {
                            $totals[$cur]['cash_out'] = (isset($totals[$cur]['cash_out']) ? $totals[$cur]['cash_out'] : 0) + $amt;
                        }
                    }
                }
                if (!empty($byAccount)) {
                    $accNames = [];
                    $accSt2 = $pdo->prepare("SELECT id, name FROM main_account WHERE tenant_id = ? AND branch_id = ?");
                    $accSt2->execute([$tenant_id, $branch_id]);
                    foreach ($accSt2 as $a) {
                        $accNames[$a['id']] = $a['name'];
                    }
                    $accTables = [];
                    foreach ($byAccount as $accId => $rows) {
                        $items = [];
                        foreach ($rows as $r) {
                            $items[] = ['date' => $r['d'], 'reference' => $r['ref'], 'party' => $r['party'], 'details' => $r['details'], 'profit' => 0, 'cash_in' => floatval($r['cash_in']), 'cash_out' => floatval($r['cash_out']), 'currency' => $r['currency'], 'cash_currency' => $r['currency']];
                        }
                        $accTables[] = ['title' => isset($accNames[$accId]) ? $accNames[$accId] : ('Account ' . $accId), 'rows' => $items];
                    }
                    $sections[] = ['title' => 'MAIN ACCOUNT', 'tables' => $accTables];
                }
                $summaryTables = [];
                foreach ($totals as $currency => $t) {
                    $profit = isset($t['profit']) ? $t['profit'] : 0;
                    $cashIn = isset($t['cash_in']) ? $t['cash_in'] : 0;
                    $cashOut = isset($t['cash_out']) ? $t['cash_out'] : 0;
                    $expenseOut = isset($t['expense_out']) ? $t['expense_out'] : 0;
                    $summaryTables[] = ['title' => 'Summary - ' . $currency, 'rows' => [
                        ['party' => 'INCOME (PROFIT)', 'profit' => $profit],
                        ['party' => 'CASH RECEIVED', 'cash_in' => $cashIn],
                        ['party' => 'CASH PAID', 'cash_out' => $cashOut],
                        ['party' => 'NET CASH FLOW', 'cash_in' => $cashIn - $cashOut, 'bold' => true],
                        ['party' => 'NET PROFIT', 'profit' => $profit - $expenseOut, 'bold' => true],
                    ]];
                }
                $sections[] = ['title' => 'SUMMARY', 'tables' => $summaryTables];
                foreach ($sections as $si => $sec) {
                    $sections[$si]['tables'] = array_values(array_filter($sec['tables'], function ($t) {
                        foreach ($t['rows'] as $row) {
                            if ((isset($row['party']) && $row['party'] === 'No records in selected period')
                                || (isset($row['entity']) && $row['entity'] === 'No records in selected period')) {
                                return false;
                            }
                        }
                        return true;
                    }));
                    if (count($sections[$si]['tables']) === 0) {
                        unset($sections[$si]);
                    }
                }
                $sections = array_values($sections);
                $tables = [];
                foreach ($sections as $sec) {
                    foreach ($sec['tables'] as $tbl) {
                        $tables[] = $tbl;
                    }
                }
                $data = [];
                foreach ($tables as $tbl) {
                    foreach ($tbl['rows'] as $r) {
                        $data[] = $r;
                    }
                }
                break;
        }
    } else {
        switch($reportType) {
            case 'ticket':
                switch($reportCategory) {
                    case 'ticket':
                        // Get all tickets with their refund and date change records
                        $query = "SELECT
                            tb.id,
                            tb.pnr,
                            tb.title,
                            tb.passenger_name,
                            tb.phone,
                            tb.gender,
                            tb.origin,
                            tb.destination,
                            tb.trip_type,
                            tb.return_destination,
                            tb.airline,
                            tb.issue_date,
                            tb.departure_date,
                            tb.currency,
                            tb.price,
                            tb.sold,
                            tb.profit,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            tb.status,
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                            tb.description,
                            'normal' as record_type,
                            NULL as parent_id
                            FROM ticket_bookings tb
                            LEFT JOIN suppliers s ON tb.supplier = s.id
                            LEFT JOIN clients c ON tb.sold_to = c.id
                            LEFT JOIN main_account m ON tb.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                            WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                            GROUP BY tb.id

                            UNION ALL

                            SELECT
                            rt.id,
                            rt.pnr,
                            rt.title,
                            rt.passenger_name,
                            rt.phone,
                            rt.gender,
                            rt.origin,
                            rt.destination,
                            '' as trip_type,
                            '' as return_destination,
                            rt.airline,
                            rt.issue_date,
                            rt.departure_date,
                            rt.currency,
                            rt.supplier_penalty as price,
                            (COALESCE(rt.supplier_penalty, 0) + COALESCE(rt.service_penalty, 0)) as sold,
                            (COALESCE(rt.service_penalty, 0)) as profit,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            CONCAT('Refunded - ', rt.status) as status,
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                            rt.remarks as description,
                            'refund' as record_type,
                            rt.ticket_id as parent_id
                            FROM refunded_tickets rt
                            LEFT JOIN suppliers s ON rt.supplier = s.id
                            LEFT JOIN clients c ON rt.sold_to = c.id
                            LEFT JOIN main_account m ON rt.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                            WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                            GROUP BY rt.id

                            UNION ALL

                            SELECT
                            dc.id,
                            dc.pnr,
                            dc.title,
                            dc.passenger_name,
                            dc.phone,
                            dc.gender,
                            dc.origin,
                            dc.destination,
                            '' as trip_type,
                            '' as return_destination,
                            dc.airline,
                            dc.issue_date,
                            dc.departure_date,
                            dc.currency,
                            dc.supplier_penalty as price,
                            (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold,
                            (COALESCE(dc.service_penalty, 0)) as profit,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            CONCAT('Date Changed - ', dc.status) as status,
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                            dc.remarks as description,
                            'date_change' as record_type,
                            dc.ticket_id as parent_id
                            FROM date_change_tickets dc
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                            WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                            GROUP BY dc.id

                            ORDER BY COALESCE(parent_id, id), record_type";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                                  'Sector', 'Trip Type', 'Airline',
                                  'Issue Date', 'Departure Date', 
                                  'Currency', 'Price', 'Sold', 'Profit',
                                  'Supplier', 'Sold To', 'Paid To',
                                  'Status', 'Receipt', 'Description'];
                        break;
                    case 'refund_ticket':
                        $query = "SELECT
                             rt.pnr,
                             rt.title,
                             rt.passenger_name,
                             rt.phone,
                             rt.gender,
                             rt.origin,
                             rt.destination,
                             '' as trip_type,
                             '' as return_destination,
                             rt.airline,
                             rt.issue_date,
                             rt.departure_date,
                             rt.currency,
                             rt.sold,
                             rt.base as base_amount,
                             rt.supplier_penalty,
                             rt.service_penalty,
                             COALESCE(rt.supplier_penalty, 0) + COALESCE(rt.service_penalty, 0) as total_penalty,
                             rt.refund_to_passenger,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             rt.status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             rt.remarks
                             FROM refunded_tickets rt
                             LEFT JOIN suppliers s ON rt.supplier = s.id
                             LEFT JOIN clients c ON rt.sold_to = c.id
                             LEFT JOIN main_account m ON rt.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                             WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                             GROUP BY rt.id";
                         $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'PNR', 
                            'Title', 
                            'Passenger Name', 
                            'Phone', 
                            'Gender',
                            'Sector', 
                            'Trip Type', 
                            'Airline',
                            'Issue Date', 
                            'Departure Date',
                            'Currency', 
                            'Sold Amount', 
                            'Base Amount',
                            'Supplier Penalty', 
                            'Service Penalty',
                            'Total Penalty',
                            'Refund Amount',
                            'Supplier', 
                            'Sold To', 
                            'Paid To',
                            'Status', 
                            'Receipt', 
                            'Remarks'
                        ];
                        break;
                    case 'date_change_ticket':
                        $query = "SELECT
                             dc.pnr,
                             dc.title,
                             dc.passenger_name,
                             dc.phone,
                             dc.gender,
                             dc.origin,
                             dc.destination,
                             '' as trip_type,
                             '' as return_destination,
                             dc.airline,
                             dc.issue_date,
                             dc.departure_date,
                             dc.currency,
                             dc.sold,
                             dc.base,
                             dc.supplier_penalty,
                             dc.service_penalty,
                             COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0) as total_penalty,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             dc.status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             dc.remarks
                             FROM date_change_tickets dc
                             LEFT JOIN suppliers s ON dc.supplier = s.id
                             LEFT JOIN clients c ON dc.sold_to = c.id
                             LEFT JOIN main_account m ON dc.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                             WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                             GROUP BY dc.id";
                         $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'PNR', 
                            'Title', 
                            'Passenger Name', 
                            'Phone', 
                            'Gender',
                            'Sector', 
                            'Trip Type', 
                            'Airline',
                            'Issue Date', 
                            'Departure Date',
                            'Currency', 
                            'Sold Amount', 
                            'Base',
                            'Supplier Penalty', 
                            'Service Penalty',
                            'Total Penalty',
                            'Supplier', 
                            'Sold To', 
                            'Paid To',
                            'Status',
                            'Receipt',
                            'Remarks'
                        ];
                        break;
                }
                break;
        
            case 'supplier':
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT
                             tb.id,
                             tb.pnr,
                             tb.title,
                             tb.passenger_name,
                             tb.phone,
                             tb.gender,
                             tb.origin,
                             tb.destination,
                             tb.trip_type,
                             tb.return_destination,
                             tb.airline,
                             tb.issue_date,
                             tb.departure_date,
                             tb.currency,
                             tb.price,
                             tb.sold,
                             tb.profit,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             tb.status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             tb.description,
                             'normal' as record_type,
                             NULL as parent_id
                             FROM ticket_bookings tb
                             LEFT JOIN suppliers s ON tb.supplier = s.id
                             LEFT JOIN clients c ON tb.sold_to = c.id
                             LEFT JOIN main_account m ON tb.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                             WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                             GROUP BY tb.id

                             UNION ALL

                             SELECT
                             rt.id,
                             rt.pnr,
                             rt.title,
                             rt.passenger_name,
                             rt.phone,
                             rt.gender,
                             rt.origin,
                             rt.destination,
                             '' as trip_type,
                             '' as return_destination,
                             rt.airline,
                             rt.issue_date,
                             rt.departure_date,
                             rt.currency,
                             rt.supplier_penalty as price,
                             (COALESCE(rt.supplier_penalty, 0) + COALESCE(rt.service_penalty, 0)) as sold,
                             (COALESCE(rt.service_penalty, 0)) as profit,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             CONCAT('Refunded - ', rt.status) as status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             rt.remarks as description,
                             'refund' as record_type,
                             rt.ticket_id as parent_id
                             FROM refunded_tickets rt
                             LEFT JOIN suppliers s ON rt.supplier = s.id
                             LEFT JOIN clients c ON rt.sold_to = c.id
                             LEFT JOIN main_account m ON rt.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                             WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                             GROUP BY rt.id

                             UNION ALL

                             SELECT
                             dc.id,
                             dc.pnr,
                             dc.title,
                             dc.passenger_name,
                             dc.phone,
                             dc.gender,
                             dc.origin,
                             dc.destination,
                             '' as trip_type,
                             '' as return_destination,
                             dc.airline,
                             dc.issue_date,
                             dc.departure_date,
                             dc.currency,
                             dc.supplier_penalty as price,
                             (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold,
                             (COALESCE(dc.service_penalty, 0)) as profit,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             CONCAT('Date Changed - ', dc.status) as status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             dc.remarks as description,
                             'date_change' as record_type,
                             dc.ticket_id as parent_id
                             FROM date_change_tickets dc
                             LEFT JOIN suppliers s ON dc.supplier = s.id
                             LEFT JOIN clients c ON dc.sold_to = c.id
                             LEFT JOIN main_account m ON dc.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                             WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                             GROUP BY dc.id

                             ORDER BY COALESCE(parent_id, id), record_type";
                         $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                                  'Sector', 'Trip Type', 'Airline',
                                  'Issue Date', 'Departure Date', 
                                  'Currency', 'Price', 'Sold', 'Profit',
                                  'Supplier', 'Sold To', 'Paid To',
                                  'Status', 'Receipt', 'Description'];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT
                             tb.pnr, tb.title, tb.passenger_name, tb.phone, tb.gender,
                             tb.origin, tb.destination, tb.trip_type, tb.return_destination,
                             tb.airline,
                             tb.issue_date, tb.departure_date,
                             tb.currency, tb.price, tb.sold, tb.profit,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             tb.status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             tb.description
                             FROM ticket_reservations tb
                             LEFT JOIN suppliers s ON tb.supplier = s.id
                             LEFT JOIN clients c ON tb.sold_to = c.id
                             LEFT JOIN main_account m ON tb.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                             WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                             GROUP BY tb.id";
                         $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date', 
                                   'Currency', 'Price', 'Sold', 'Profit',
                                   'Supplier', 'Sold To', 'Paid To',
                                   'Status', 'Receipt', 'Description'];
                        break;
                    
                    case 'refund_ticket':
                        $query = "SELECT
                             rt.pnr, rt.title, rt.passenger_name, rt.phone, rt.gender,
                             rt.origin, rt.destination, '' as trip_type,
                     '' as return_destination, rt.airline,
                             rt.issue_date, rt.departure_date,
                             rt.currency, rt.sold, rt.base,
                             rt.supplier_penalty, rt.service_penalty,
                             rt.refund_to_passenger,
                             s.name as supplier_name,
                             c.name as sold_to_name,
                             m.name as paid_to_name,
                             rt.status,
                             GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                             rt.remarks
                             FROM refunded_tickets rt
                             LEFT JOIN suppliers s ON rt.supplier = s.id
                             LEFT JOIN clients c ON rt.sold_to = c.id
                             LEFT JOIN main_account m ON rt.paid_to = m.id
                             LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                             WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                             GROUP BY rt.id";
                         $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                    'Issue Date', 'Departure Date',
                                    'Currency', 'Sold', 'Base Amount',
                                    'Supplier Penalty', 'Service Penalty',
                                    'Refund Amount',
                                    'Supplier', 'Sold To', 'Paid To',
                                    'Remarks','Receipt'];
                        break;


                    case 'date_change_ticket':
                        $query = "SELECT 
                            dc.pnr, dc.title, dc.passenger_name, dc.phone, dc.gender,
                            dc.origin, dc.destination, '' as trip_type, 
                    '' as return_destination, dc.airline,
                            dc.issue_date, dc.departure_date,
                            dc.currency, dc.sold, dc.base,
                            dc.supplier_penalty, dc.service_penalty,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            dc.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            dc.remarks
                            FROM date_change_tickets dc
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                            WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                            GROUP BY dc.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date',
                                   'Currency', 'Sold', 'Base Amount',
                                   'Supplier Penalty', 'Service Penalty',
                                   'Supplier', 'Sold To', 'Paid To',
                                    'Remarks','Receipt'];
                        break;
        
                    case 'visa':
                        $query = "SELECT 
                            v.applicant_name, v.passport_number,
                            v.phone, v.title, v.gender,
                            v.country, v.visa_type,
                            v.receive_date, v.applied_date, v.issued_date,
                            v.base, v.sold, v.profit,
                            v.currency, v.status, v.remarks,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name
                            FROM visa_applications v
                            LEFT JOIN suppliers s ON v.supplier = s.id
                            LEFT JOIN main_account_transactions mat ON v.id = mat.reference_id and mat.transaction_of = 'visa_sale'
                            WHERE v." . ($reportType === 'supplier' ? 'supplier' :
                                        ($reportType === 'client' ? 'sold_to' :
                                        ($reportType === 'main_account' ? 'paid_to' : 'supplier'))) . " = ?
                            AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?
                            GROUP BY v.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Applicant Name', 'Passport Number',
                                   'Phone', 'Title', 'Gender',
                                   'Country', 'Visa Type',
                                   'Receive Date', 'Applied Date', 'Issued Date',
                                   'Base', 'Sold', 'Profit',
                                   'Currency', 'Status', 'Remarks',
                                   'Supplier', 'Sold To', 'Paid To','Receipt'];
                                   
                        break;
        
case 'umrah':
                        $query = "SELECT
                            u.booking_id,
                            f.head_of_family,
                            u.name,
                            u.fname,
                            u.gender,
                            u.dob,
                            u.passport_number,
                            u.passport_expiry,
                            u.id_type,
                            f.contact,
                            f.address,
                            g.group_name,
                            u.entry_date,
                            u.created_at,
                            COALESCE(u.flight_date, (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS flight_date,
                            COALESCE(u.return_date, (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS return_date,
                            u.duration,
                            u.room_type,
                            u.price,
                            u.sold_price,
                            u.discount,
                            u.profit,
                            u.received_bank_payment,
                            u.bank_receipt_number,
                            u.paid,
                            u.due,
                            u.currency,
                            u.exchange_rate,
                            u.status,
                            f.tazmin,
                            c.name as client_name,
                            m.name as account_name,
                            ur.refund_type as refund_status,
                            GROUP_CONCAT(DISTINCT ubs.service_type SEPARATOR ', ') as services,
                            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as supplier_name,
                            u.remarks
                            FROM umrah_bookings u
                            LEFT JOIN families f ON u.family_id = f.family_id
                            LEFT JOIN umrah_groups g ON f.group_id = g.group_id
                            LEFT JOIN clients c ON u.sold_to = c.id
                            LEFT JOIN main_account m ON u.paid_to = m.id
                            LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                            LEFT JOIN umrah_booking_services ubs ON u.booking_id = ubs.booking_id
                            LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                            LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                            WHERE u.paid_to = ? AND " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ?"
                            . $umrahFilterSql .
                            " GROUP BY u.booking_id
                            ORDER BY u.entry_date DESC";
                        $params = array_merge([$entity], $umrahDateParams, [$tenant_id, $branch_id], $umrahFilterParams);
                        $headers = [
                            'Head of Family',
                            'Name',
                            'Father Name',
                            'Gender',
                            'Date of Birth',
                            'Passport Number',
                            'Passport Expiry',
                            'ID Type',
                            'Phone',
                            'Address',
                            'Group',
                            'Entry Date',
                            'Created At',
                            'Flight Date',
                            'Return Date',
                            'Duration',
                            'Room Type',
                            'Sold Price',
                            'Bank Payment',
                            'Bank Receipt',
                            'Paid',
                            'Due',
                            'Currency',
                            'Exchange Rate',
                            'Status',
                            'Tazmin',
                            'Client',
                            'Account',
                            'Refund Status',
                            'Services',
                            'Remarks'
                        ];

                        // Add sensitive headers only if admin
                        if ($user_role === 'admin') {
                            $insertIndex = array_search('Sold Price', $headers);
                            if ($insertIndex !== false) {
                                array_splice($headers, $insertIndex, 0, ['Price']);
                                $soldPriceIndex = array_search('Sold Price', $headers);
                                array_splice($headers, $soldPriceIndex + 1, 0, ['Discount', 'Profit']);
                            }
                            $headers[] = 'Supplier';
                        }
                        break;

                    case 'ticket_weight':
                        $query = "SELECT 
                            t.pnr, 
                            t.passenger_name, 
                            tw.weight,
                            tw.base_price,
                            tw.sold_price,
                            tw.profit,
                            t.currency,
                            tw.created_at,
                            tw.remarks,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name
                            FROM ticket_weights tw
                            LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                            LEFT JOIN suppliers s ON t.supplier = s.id
                            LEFT JOIN clients c ON t.sold_to = c.id
                            LEFT JOIN main_account m ON t.paid_to = m.id
                            WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ? AND tw.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'PNR',
                            'Passenger Name',
                            'Weight (kg)',
                            'Base Price',
                            'Sold Price',
                            'Profit',
                            'Currency',
                            'Date',
                            'Remarks',
                            'Supplier',
                            'Client',
                            'Account'
                        ];
                        break;

                    case 'visa_refund':
                        $query = "SELECT 
                            va.passport_number,
                            va.applicant_name,
                            vr.refund_type,
                            vr.refund_amount,
                            vr.currency,
                            vr.exchange_rate,
                            vr.refund_date,
                            vr.reason,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name,
                            u.name as processed_by_name
                            FROM visa_refunds vr
                            LEFT JOIN visa_applications va ON vr.visa_id = va.id
                            LEFT JOIN suppliers s ON va.supplier = s.id
                            LEFT JOIN clients c ON va.sold_to = c.id
                            LEFT JOIN main_account m ON va.paid_to = m.id
                            LEFT JOIN users u ON vr.processed_by = u.id
                            WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ? AND vr.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'Passport Number',
                            'Applicant Name',
                            'Refund Type',
                            'Refund Amount',
                            'Currency',
                            'Exchange Rate',
                            'Refund Date',
                            'Reason',
                            'Supplier',
                            'Client',
                            'Account',
                            'Processed By'
                        ];
                        break;

                        case 'hotel_refund':
                            $query = "SELECT
                                hb.order_id,
                                hb.title,
                                CONCAT(hb.first_name, ' ', hb.last_name) as guest_name,
                                hb.contact_no,
                                hb.gender,
                                h.created_at,
                                hb.currency,
                                h.refund_amount,
                                s.name as supplier_name,
                                c.name as client_name,
                                'Refunded' as status,
                                h.reason
                                FROM hotel_refunds h
                                INNER JOIN hotel_bookings hb ON h.booking_id = hb.id
                                LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                LEFT JOIN clients c ON hb.sold_to = c.id
                                WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?
                                GROUP BY h.id";
                            $params = [$startDate, $endDate, $tenant_id, $branch_id];
                            $headers = [
                                'Order ID', 
                                'Title', 
                                'Guest Name', 
                                'Contact', 
                                'Gender',
                                'Refund Date',
                                'Currency', 
                                'Refund Amount',
                                'Supplier',
                                'Client',
                                'Status',
                                'Reason'
                            ];
                            break;

                    case 'umrah_refund':
                        $query = "SELECT
                             ub.passport_number,
                             ub.name as pilgrim_name,
                             ub.fname,
                             ur.refund_type,
                             ur.refund_amount,
                             ur.supplier_penalty,
                             ur.service_penalty,
                             ur.base,
                             ur.sold,
                             ur.currency,
                             ub.exchange_rate,
                             ur.created_at,
                             ur.reason,
                             GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as supplier_name,
                             c.name as client_name,
                             m.name as account_name,
                             u.name as processed_by_name
                             FROM umrah_refunds ur
                             LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                             LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                             LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                             LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                             LEFT JOIN clients c ON ub.sold_to = c.id
                             LEFT JOIN main_account m ON ub.paid_to = m.id
                             LEFT JOIN users u ON ur.processed_by = u.id
                             WHERE COALESCE(uf.supplier_id, ubs.supplier_id) = ? AND ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ? AND ur.branch_id = ?
                             GROUP BY ur.id";
                         $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'Passport Number',
                            'Pilgrim Name',
                            'Father Name',
                            'Refund Type',
                            'Refund Amount',
                            'Supplier Penalty',
                            'Service Penalty',
                            'Base',
                            'Sold',
                            'Currency',
                            'Exchange Rate',
                            'Refund Date',
                            'Reason',
                            'Supplier',
                            'Client',
                            'Account',
                            'Processed By'
                        ];
                        break;
                }
                break;
        
            case 'client':
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT 
                            tb.id,
                            tb.pnr, 
                            tb.title, 
                            tb.passenger_name, 
                            tb.phone, 
                            tb.gender,
                            tb.origin, 
                            tb.destination, 
                            tb.trip_type, 
                            tb.return_destination,
                            tb.airline,
                            tb.issue_date, 
                            tb.departure_date,
                            tb.currency, 
                            tb.price, 
                            tb.sold, 
                            tb.profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            tb.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            tb.description,
                            'normal' as record_type,
                            NULL as parent_id
                            FROM ticket_bookings tb
                            LEFT JOIN suppliers s ON tb.supplier = s.id
                            LEFT JOIN clients c ON tb.sold_to = c.id
                            LEFT JOIN main_account m ON tb.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                            WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                            GROUP BY tb.id
                            
                            UNION ALL
                            
                            SELECT 
                            dc.id,
                            dc.pnr, 
                            dc.title, 
                            dc.passenger_name, 
                            dc.phone, 
                            dc.gender,
                            dc.origin, 
                            dc.destination, 
                            '' as trip_type, 
                            '' as return_destination,
                            dc.airline,
                            dc.issue_date, 
                            dc.departure_date,
                            dc.currency, 
                            dc.supplier_penalty as price, 
                            (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold, 
                            (COALESCE(dc.service_penalty, 0)) as profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            CONCAT('Date Changed - ', dc.status) as status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                            dc.remarks as description,
                            'date_change' as record_type,
                            dc.ticket_id as parent_id
                            FROM date_change_tickets dc
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                            WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                            GROUP BY dc.id
                            
                            ORDER BY COALESCE(parent_id, id), record_type";
                            $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                                  'Sector', 'Trip Type', 'Airline',
                                  'Issue Date', 'Departure Date', 
                                  'Currency', 'Price', 'Sold', 'Profit',
                                  'Supplier', 'Sold To', 'Paid To',
                                  'Status', 'Receipt', 'Description'];
                        break;

                    case 'ticket_reservation':
                        $query = "SELECT 
                            tb.pnr, tb.title, tb.passenger_name, tb.phone, tb.gender,
                            tb.origin, tb.destination, tb.trip_type, tb.return_destination,
                            tb.airline,
                            tb.issue_date, tb.departure_date,
                            tb.currency, tb.price, tb.sold, tb.profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            tb.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            tb.description
                            FROM ticket_reservations tb
                            LEFT JOIN suppliers s ON tb.supplier = s.id
                            LEFT JOIN clients c ON tb.sold_to = c.id
                            LEFT JOIN main_account m ON tb.paid_to = m.id   
                            LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                            WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                            GROUP BY tb.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date', 
                                   'Currency', 'Price', 'Sold', 'Profit',
                                   'Supplier', 'Sold To', 'Paid To',
                                   'Status', 'Receipt', 'Description'];
                        break;
                        
        
                    case 'refund_ticket':
                        $query = "SELECT 
                            rt.pnr, rt.title, rt.passenger_name, rt.phone, rt.gender,
                            rt.origin, rt.destination, '' as trip_type,
                            '' as return_destination, rt.airline,
                            rt.issue_date, rt.departure_date,
                            rt.currency, rt.sold, rt.base,
                            rt.supplier_penalty, rt.service_penalty,
                            rt.refund_to_passenger,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            rt.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            rt.remarks
                            FROM refunded_tickets rt
                            LEFT JOIN suppliers s ON rt.supplier = s.id
                            LEFT JOIN clients c ON rt.sold_to = c.id
                            LEFT JOIN main_account m ON rt.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                            WHERE rt.sold_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? And rt.branch_id = ?
                            GROUP BY rt.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date',
                                   'Currency', 'Sold', 'Base Amount',
                                   'Supplier Penalty', 'Service Penalty',
                                   'Refund Amount',
                                   'Supplier', 'Sold To', 'Paid To',
                                   'Remarks','Receipt'];
                        break;
        

                    case 'date_change_ticket':
                        $query = "SELECT 
                            dc.pnr, dc.title, dc.passenger_name, dc.phone, dc.gender,
                            dc.origin, dc.destination, '' as trip_type,
                            '' as return_destination, dc.airline,
                            dc.issue_date, dc.departure_date,
                            dc.currency, dc.sold, dc.base,
                            dc.supplier_penalty, dc.service_penalty,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            dc.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            dc.remarks
                            FROM date_change_tickets dc
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                            WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? And dc.branch_id = ?
                            GROUP BY dc.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date',
                                   'Currency', 'Sold', 'Base Amount',
                                   'Supplier Penalty', 'Service Penalty',
                                   'Supplier', 'Sold To', 'Paid To',
                                   'Remarks','Receipt'];
                        break;

                    case 'visa':
                        $query = "SELECT 
                            v.applicant_name, v.passport_number,
                            v.phone, v.title, v.gender,
                            v.country, v.visa_type,
                            v.receive_date, v.applied_date, v.issued_date,
                            v.base, v.sold, v.profit,
                            v.currency, v.status, v.remarks,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name
                            FROM visa_applications v
                            LEFT JOIN suppliers s ON v.supplier = s.id
                            LEFT JOIN clients c ON v.sold_to = c.id
                            LEFT JOIN main_account m ON v.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON v.id = mat.reference_id and mat.transaction_of = 'visa_sale'
                            WHERE v." . ($reportType === 'supplier' ? 'supplier' : 
                                        ($reportType === 'client' ? 'sold_to' : 
                                        ($reportType === 'main_account' ? 'paid_to' : 'supplier'))) . " = ? 
                            AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? And v.branch_id = ?
                            GROUP BY v.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Applicant Name', 'Passport Number',
                                   'Phone', 'Title', 'Gender',
                                   'Country', 'Visa Type',
                                   'Receive Date', 'Applied Date', 'Issued Date',
                                   'Base', 'Sold', 'Profit',
                                   'Currency', 'Status', 'Remarks',
                                   'Supplier', 'Sold To', 'Paid To','Receipt'];
                        break;

case 'umrah':
                        $query = "SELECT
                            u.booking_id,
                            f.head_of_family,
                            u.name,
                            u.fname,
                            u.gender,
                            u.dob,
                            u.passport_number,
                            u.passport_expiry,
                            u.id_type,
                            f.contact,
                            f.address,
                            g.group_name,
                            u.entry_date,
                            u.created_at,
                            COALESCE(u.flight_date, (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS flight_date,
                            COALESCE(u.return_date, (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS return_date,
                            u.duration,
                            u.room_type,
                            u.price,
                            u.sold_price,
                            u.discount,
                            u.profit,
                            u.received_bank_payment,
                            u.bank_receipt_number,
                            u.paid,
                            u.due,
                            u.currency,
                            u.exchange_rate,
                            u.status,
                            f.tazmin,
                            c.name as client_name,
                            m.name as account_name,
                            ur.refund_type as refund_status,
                            GROUP_CONCAT(DISTINCT ubs.service_type SEPARATOR ', ') as services,
                            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as supplier_name,
                            u.remarks
                            FROM umrah_bookings u
                            LEFT JOIN families f ON u.family_id = f.family_id
                            LEFT JOIN umrah_groups g ON f.group_id = g.group_id
                            LEFT JOIN clients c ON u.sold_to = c.id
                            LEFT JOIN main_account m ON u.paid_to = m.id
                            LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                            LEFT JOIN umrah_booking_services ubs ON u.booking_id = ubs.booking_id
                            LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                            LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                            WHERE u.sold_to = ? AND " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ?"
                            . $umrahFilterSql .
                            " GROUP BY u.booking_id
                            ORDER BY u.entry_date DESC";
                        $params = array_merge([$entity], $umrahDateParams, [$tenant_id, $branch_id], $umrahFilterParams);
                        $headers = [
                            'Head of Family',
                            'Name',
                            'Father Name',
                            'Gender',
                            'Date of Birth',
                            'Passport Number',
                            'Passport Expiry',
                            'ID Type',
                            'Phone',
                            'Address',
                            'Group',
                            'Entry Date',
                            'Created At',
                            'Flight Date',
                            'Return Date',
                            'Duration',
                            'Room Type',
                            'Sold Price',
                            'Bank Payment',
                            'Bank Receipt',
                            'Paid',
                            'Due',
                            'Currency',
                            'Exchange Rate',
                            'Status',
                            'Tazmin',
                            'Client',
                            'Account',
                            'Refund Status',
                            'Services',
                            'Remarks'
                        ];

                        // Add sensitive headers only if admin
                        if ($user_role === 'admin') {
                            $insertIndex = array_search('Sold Price', $headers);
                            if ($insertIndex !== false) {
                                array_splice($headers, $insertIndex, 0, ['Price']);
                                $soldPriceIndex = array_search('Sold Price', $headers);
                                array_splice($headers, $soldPriceIndex + 1, 0, ['Discount', 'Profit']);
                            }
                            $headers[] = 'Supplier';
                        }
                        break;

                    case 'ticket_weight':
                        $query = "SELECT 
                            t.pnr, 
                            t.passenger_name, 
                            tw.weight,
                            tw.base_price,
                            tw.sold_price,
                            tw.profit,
                            t.currency,
                            tw.created_at,
                            tw.remarks,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name
                            FROM ticket_weights tw
                            LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                            LEFT JOIN suppliers s ON t.supplier = s.id
                            LEFT JOIN clients c ON t.sold_to = c.id
                            LEFT JOIN main_account m ON t.paid_to = m.id
                            WHERE t.sold_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ? AND t.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'PNR',
                            'Passenger Name',
                            'Weight (kg)',
                            'Base Price',
                            'Sold Price',
                            'Profit',
                            'Currency',
                            'Date',
                            'Remarks',
                            'Supplier',
                            'Client',
                            'Account'
                        ];
                        break;

                    case 'visa_refund':
                        $query = "SELECT 
                            va.passport_number,
                            va.applicant_name,
                            vr.refund_type,
                            vr.refund_amount,
                            vr.currency,
                            vr.exchange_rate,
                            vr.refund_date,
                            vr.reason
                            FROM visa_refunds vr
                            LEFT JOIN visa_applications va ON vr.visa_id = va.id
                            WHERE va.sold_to = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ? AND va.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'Passport Number',
                            'Applicant Name',
                            'Refund Type',
                            'Refund Amount',
                            'Currency',
                            'Exchange Rate',
                            'Refund Date',
                            'Reason'
                        ];
                        break;

                        case 'hotel_refund':
                            $query = "SELECT
                                 hb.order_id,
                                 hb.title,
                                 CONCAT(hb.first_name, ' ', hb.last_name) as guest_name,
                                 hb.contact_no,
                                 hb.gender,
                                 h.created_at,
                                 hb.currency,
                                 h.refund_amount,
                                 s.name as supplier_name,
                                 c.name as client_name,
                                 'Refunded' as status,
                                 h.reason
                                 FROM hotel_refunds h
                                 INNER JOIN hotel_bookings hb ON h.booking_id = hb.id
                                 LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                 LEFT JOIN clients c ON hb.sold_to = c.id
                                 WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?
                                 GROUP BY h.id";
                             $params = [$startDate, $endDate, $tenant_id, $branch_id];
                            $headers = [
                                'Order ID', 
                                'Title', 
                                'Guest Name', 
                                'Contact', 
                                'Gender',
                                'Refund Date',
                                'Currency', 
                                'Refund Amount',
                                'Supplier',
                                'Client',
                                'Status',
                                'Reason'
                            ];
                            break;

                    case 'umrah_refund':
                        $query = "SELECT
                            ub.passport_number,
                            ub.name as pilgrim_name,
                            ub.fname,
                            ur.refund_type,
                            ur.refund_amount,
                            ur.supplier_penalty,
                            ur.service_penalty,
                            ur.base,
                            ur.sold,
                            ur.currency,
                            ub.exchange_rate,
                            ur.created_at,
                            ur.reason
                            FROM umrah_refunds ur
                            LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                            WHERE ub.sold_to = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ? AND ub.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'Passport Number',
                            'Pilgrim Name',
                            'Father Name',
                            'Refund Type',
                            'Refund Amount',
                            'Supplier Penalty',
                            'Service Penalty',
                            'Base',
                            'Sold',
                            'Currency',
                            'Exchange Rate',
                            'Refund Date',
                            'Reason'
                        ];
                        break;
                }
                break;

             case 'main_account':
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT 
                            tb.id,
                            tb.pnr, 
                            tb.title, 
                            tb.passenger_name, 
                            tb.phone, 
                            tb.gender,
                            tb.origin, 
                            tb.destination, 
                            tb.trip_type, 
                            tb.return_destination,
                            tb.airline,
                            tb.issue_date, 
                            tb.departure_date,
                            tb.currency, 
                            tb.price, 
                            tb.sold, 
                            tb.profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            tb.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            tb.description,
                            'normal' as record_type,
                            NULL as parent_id
                            FROM ticket_bookings tb
                            LEFT JOIN suppliers s ON tb.supplier = s.id
                            LEFT JOIN clients c ON tb.sold_to = c.id
                            LEFT JOIN main_account m ON tb.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                            WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                            GROUP BY tb.id
                            
                            UNION ALL
                            
                            SELECT 
                            rt.id,
                            rt.pnr, 
                            rt.title, 
                            rt.passenger_name, 
                            rt.phone, 
                            rt.gender,
                            rt.origin, 
                            rt.destination, 
                            '' as trip_type, 
                            '' as return_destination,
                            rt.airline,
                            rt.issue_date, 
                            rt.departure_date,
                            rt.currency, 
                            rt.supplier_penalty as price, 
                            (COALESCE(rt.supplier_penalty, 0) + COALESCE(rt.service_penalty, 0)) as sold, 
                            (COALESCE(rt.service_penalty, 0)) as profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            CONCAT('Refunded - ', rt.status) as status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                            rt.remarks as description,
                            'refund' as record_type,
                            rt.ticket_id as parent_id
                            FROM refunded_tickets rt
                            LEFT JOIN suppliers s ON rt.supplier = s.id
                            LEFT JOIN clients c ON rt.sold_to = c.id
                            LEFT JOIN main_account m ON rt.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                            WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                            GROUP BY rt.id
                            
                            UNION ALL
                            
                            SELECT 
                            dc.id,
                            dc.pnr, 
                            dc.title, 
                            dc.passenger_name, 
                            dc.phone, 
                            dc.gender,
                            dc.origin, 
                            dc.destination, 
                            '' as trip_type, 
                            '' as return_destination,
                            dc.airline,
                            dc.issue_date, 
                            dc.departure_date,
                            dc.currency, 
                            dc.supplier_penalty as price, 
                            (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold, 
                            (COALESCE(dc.service_penalty, 0)) as profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            CONCAT('Date Changed - ', dc.status) as status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt,
                            dc.remarks as description,
                            'date_change' as record_type,
                            dc.ticket_id as parent_id
                            FROM date_change_tickets dc
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                            WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                            GROUP BY dc.id
                            
                            ORDER BY COALESCE(parent_id, id), record_type";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id, $entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                                  'Sector', 'Trip Type', 'Airline',
                                  'Issue Date', 'Departure Date', 
                                  'Currency', 'Price', 'Sold', 'Profit',
                                  'Supplier', 'Sold To', 'Paid To',
                                  'Status', 'Receipt', 'Description'];
                        break;

                    case 'ticket_reservation':
                        $query = "SELECT 
                            tb.pnr, tb.title, tb.passenger_name, tb.phone, tb.gender,
                            tb.origin, tb.destination, tb.trip_type, tb.return_destination,
                            tb.airline,
                            tb.issue_date, tb.departure_date,
                            tb.currency, tb.price, tb.sold, tb.profit,
                            s.name as supplier_name, 
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            tb.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            tb.description
                            FROM ticket_reservations tb
                            LEFT JOIN suppliers s ON tb.supplier = s.id
                            LEFT JOIN clients c ON tb.sold_to = c.id
                            LEFT JOIN main_account m ON tb.paid_to = m.id   
                            LEFT JOIN main_account_transactions mat ON tb.id = mat.reference_id and mat.transaction_of = 'ticket_sale'
                            WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?
                            GROUP BY tb.id";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender', 
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date', 
                                   'Currency', 'Price', 'Sold', 'Profit',
                                   'Supplier', 'Sold To', 'Paid To',
                                   'Status', 'Receipt', 'Description'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT 
                            rt.pnr, rt.title, rt.passenger_name, rt.phone, rt.gender,
                            rt.origin, rt.destination, '' as trip_type,
                            '' as return_destination, rt.airline,
                            rt.issue_date, rt.departure_date,
                            rt.currency, rt.sold, rt.base,
                            rt.supplier_penalty, rt.service_penalty,
                            rt.refund_to_passenger,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            rt.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            rt.remarks
                            FROM refunded_tickets rt
                            LEFT JOIN suppliers s ON rt.supplier = s.id
                            LEFT JOIN clients c ON rt.sold_to = c.id
                            LEFT JOIN main_account m ON rt.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON rt.id = mat.reference_id and mat.transaction_of = 'ticket_refund'
                            WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?
                            GROUP BY rt.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                    'Issue Date', 'Departure Date',
                                    'Currency', 'Sold', 'Base Amount',
                                    'Supplier Penalty', 'Service Penalty',
                                    'Refund Amount',
                                    'Supplier', 'Sold To', 'Paid To',
                                    'Remarks','Receipt'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT 
                            dc.pnr, dc.title, dc.passenger_name, dc.phone, dc.gender,
                            dc.origin, dc.destination, '' as trip_type,
                            '' as return_destination, dc.airline,
                            dc.issue_date, dc.departure_date,
                            dc.currency, dc.sold, dc.base,
                            dc.supplier_penalty, dc.service_penalty,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name,
                            dc.status, 
                            GROUP_CONCAT(mat.receipt SEPARATOR ', ') as receipt, 
                            dc.remarks
                            FROM date_change_tickets dc
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON dc.id = mat.reference_id and mat.transaction_of = 'date_change'
                            WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?
                            GROUP BY dc.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Title', 'Passenger Name', 'Phone', 'Gender',
                                   'Sector', 'Trip Type', 'Airline',
                                   'Issue Date', 'Departure Date',
                                   'Currency', 'Sold', 'Base Amount',
                                   'Supplier Penalty', 'Service Penalty',
                                   'Supplier', 'Sold To', 'Paid To',
                                    'Remarks','Receipt'];
                        break;

                    case 'visa':
                        $query = "SELECT 
                            v.applicant_name, v.passport_number,
                            v.phone, v.title, v.gender,
                            v.country, v.visa_type,
                            v.receive_date, v.applied_date, v.issued_date,
                            v.base, v.sold, v.profit,
                            v.currency, v.status, v.remarks,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            m.name as paid_to_name
                            FROM visa_applications v
                            LEFT JOIN suppliers s ON v.supplier = s.id
                            LEFT JOIN clients c ON v.sold_to = c.id
                            LEFT JOIN main_account m ON v.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON v.id = mat.reference_id and mat.transaction_of = 'visa_sale'
                            WHERE v." . ($reportType === 'supplier' ? 'supplier' : 
                                        ($reportType === 'client' ? 'sold_to' : 
                                        ($reportType === 'main_account' ? 'paid_to' : 'supplier'))) . " = ? 
                            AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?
                            GROUP BY v.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Applicant Name', 'Passport Number',
                                   'Phone', 'Title', 'Gender',
                                   'Country', 'Visa Type',
                                   'Receive Date', 'Applied Date', 'Issued Date',
                                   'Base', 'Sold', 'Profit',
                                   'Currency', 'Status', 'Remarks',
                                   'Supplier', 'Sold To', 'Paid To','Receipt'];
                        break;

case 'umrah':
                        $query = "SELECT
                            u.booking_id,
                            f.head_of_family,
                            u.name,
                            u.fname,
                            u.gender,
                            u.dob,
                            u.passport_number,
                            u.passport_expiry,
                            u.id_type,
                            f.contact,
                            f.address,
                            g.group_name,
                            u.entry_date,
                            u.created_at,
                            COALESCE(u.flight_date, (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS flight_date,
                            COALESCE(u.return_date, (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                WHERE ubs2.booking_id = u.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                ORDER BY ff.id DESC LIMIT 1)) AS return_date,
                            u.duration,
                            u.room_type,
                            u.price,
                            u.sold_price,
                            u.discount,
                            u.profit,
                            u.received_bank_payment,
                            u.bank_receipt_number,
                            u.paid,
                            u.due,
                            u.currency,
                            u.exchange_rate,
                            u.status,
                            f.tazmin,
                            c.name as client_name,
                            m.name as account_name,
                            ur.refund_type as refund_status,
                            GROUP_CONCAT(DISTINCT ubs.service_type SEPARATOR ', ') as services,
                            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as supplier_name,
                            u.remarks
                            FROM umrah_bookings u
                            LEFT JOIN families f ON u.family_id = f.family_id
                            LEFT JOIN umrah_groups g ON f.group_id = g.group_id
                            LEFT JOIN clients c ON u.sold_to = c.id
                            LEFT JOIN main_account m ON u.paid_to = m.id
                            LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                            LEFT JOIN umrah_booking_services ubs ON u.booking_id = ubs.booking_id
                            LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                            LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                            WHERE " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ? AND COALESCE(uf.supplier_id, ubs.supplier_id) = ?"
                            . $umrahFilterSql .
                            " GROUP BY u.booking_id
                            ORDER BY u.entry_date DESC";
                        $params = array_merge($umrahDateParams, [$tenant_id, $branch_id, $entity], $umrahFilterParams);
                        $headers = [
                            'Head of Family',
                            'Name',
                            'Father Name',
                            'Gender',
                            'Date of Birth',
                            'Passport Number',
                            'Passport Expiry',
                            'ID Type',
                            'Phone',
                            'Address',
                            'Group',
                            'Entry Date',
                            'Created At',
                            'Flight Date',
                            'Return Date',
                            'Duration',
                            'Room Type',
                            'Sold Price',
                            'Bank Payment',
                            'Bank Receipt',
                            'Paid',
                            'Due',
                            'Currency',
                            'Exchange Rate',
                            'Status',
                            'Tazmin',
                            'Client',
                            'Account',
                            'Refund Status',
                            'Services',
                            'Remarks'
                        ];

                        // Add sensitive headers only if admin
                        if ($user_role === 'admin') {
                            $insertIndex = array_search('Sold Price', $headers);
                            if ($insertIndex !== false) {
                                array_splice($headers, $insertIndex, 0, ['Price']);
                                $newIndex = array_search('Sold Price', $headers);
                                array_splice($headers, $newIndex + 1, 0, ['Discount', 'Profit']);
                            }
                            $headers[] = 'Supplier';
                        }
                        break;

                    case 'expense':
                        $query = "SELECT 
                            e.id, 
                            e.description, 
                            e.date, 
                            e.amount, 
                            e.currency,
                            ec.name as category,
                            m.name as account_name,
                            ba.allocated_amount as budget_allocated,
                            ba.remaining_amount as budget_remaining
                            FROM expenses e
                            LEFT JOIN expense_categories ec ON e.category_id = ec.id
                            LEFT JOIN main_account m ON e.main_account_id = m.id
                            LEFT JOIN budget_allocations ba ON e.allocation_id = ba.id
                            WHERE e.main_account_id = ? AND e.date BETWEEN ? AND ? AND e.tenant_id = ? and e.branch_id = ?"
                            . ($expenseCategory && $expenseCategory !== 'all' ? " AND e.category_id = ?" : "") .
                            " ORDER BY e.date DESC";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        if ($expenseCategory && $expenseCategory !== 'all') {
                            $params[] = $expenseCategory;
                        }
                        $headers = [
                            'ID', 
                            'Description', 
                            'Date', 
                            'Amount', 
                            'Currency',
                            'Category',
                            'Account',
                            'Budget Allocated',
                            'Budget Remaining'
                        ];
                        break;

                    case 'ticket_weight':
                        $query = "SELECT 
                            t.pnr, 
                            t.passenger_name, 
                            tw.weight,
                            tw.base_price,
                            tw.sold_price,
                            tw.profit,
                            t.currency,
                            tw.created_at,
                            tw.remarks,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name
                            FROM ticket_weights tw
                            LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                            LEFT JOIN suppliers s ON t.supplier = s.id
                            LEFT JOIN clients c ON t.sold_to = c.id
                            LEFT JOIN main_account m ON t.paid_to = m.id
                            WHERE t.paid_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ? and t.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'PNR',
                            'Passenger Name',
                            'Weight (kg)',
                            'Base Price',
                            'Sold Price',
                            'Profit',
                            'Currency',
                            'Date',
                            'Remarks',
                            'Supplier',
                            'Client',
                            'Account'
                        ];
                        break;

                    case 'visa_refund':
                        $query = "SELECT 
                            va.passport_number,
                            va.applicant_name,
                            vr.refund_type,
                            vr.refund_amount,
                            vr.currency,
                            vr.exchange_rate,
                            vr.refund_date,
                            vr.reason,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name,
                            u.name as processed_by_name
                            FROM visa_refunds vr
                            LEFT JOIN visa_applications va ON vr.visa_id = va.id
                            LEFT JOIN suppliers s ON va.supplier = s.id
                            LEFT JOIN clients c ON va.sold_to = c.id
                            LEFT JOIN main_account m ON va.paid_to = m.id
                            LEFT JOIN users u ON vr.processed_by = u.id
                            WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ? And vr.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'Passport Number',
                            'Applicant Name',
                            'Refund Type',
                            'Refund Amount',
                            'Currency',
                            'Exchange Rate',
                            'Refund Date',
                            'Reason',
                            'Supplier',
                            'Client',
                            'Account',
                            'Processed By'
                        ];
                        break;

                        case 'hotel_refund':
                            $query = "SELECT 
                                hb.order_id, 
                                hb.title, 
                                CONCAT(hb.first_name, ' ', hb.last_name) as guest_name,
                                hb.contact_no, 
                                hb.gender,
                                h.created_at, 
                                hb.currency, 
                                h.refund_amount,
                                s.name as supplier_name,
                                c.name as client_name,
                                'Refunded' as status,
                                h.reason
                                FROM hotel_refunds h
                                INNER JOIN hotel_bookings hb ON h.booking_id = hb.id
                                LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                LEFT JOIN clients c ON hb.sold_to = c.id
                                WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?
                                GROUP BY h.id";
                            $params = [$startDate, $endDate, $tenant_id, $branch_id];
                            $headers = [
                                'Order ID', 
                                'Title', 
                                'Guest Name', 
                                'Contact', 
                                'Gender',
                                'Refund Date',
                                'Currency', 
                                'Refund Amount',
                                'Supplier',
                                'Client',
                                'Status',
                                'Reason'
                            ];
                            break;

                    case 'umrah_refund':
                        $query = "SELECT
                            ub.passport_number,
                            ub.name as pilgrim_name,
                            ub.fname,
                            ur.refund_type,
                            ur.refund_amount,
                            ur.supplier_penalty,
                            ur.service_penalty,
                            ur.base,
                            ur.sold,
                            ur.currency,
                            ub.exchange_rate,
                            ur.created_at,
                            ur.reason,
                            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as supplier_name,
                            c.name as client_name,
                            m.name as account_name,
                            u.name as processed_by_name
                            FROM umrah_refunds ur
                            LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                            LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                            LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                            LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                            LEFT JOIN clients c ON ub.sold_to = c.id
                            LEFT JOIN main_account m ON ub.paid_to = m.id
                            LEFT JOIN users u ON ur.processed_by = u.id
                            WHERE ub.paid_to = ? AND ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ? AND ur.branch_id = ?
                            GROUP BY ur.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = [
                            'Passport Number',
                            'Pilgrim Name',
                            'Father Name',
                            'Refund Type',
                            'Refund Amount',
                            'Supplier Penalty',
                            'Service Penalty',
                            'Base',
                            'Sold',
                            'Currency',
                            'Exchange Rate',
                            'Refund Date',
                            'Reason',
                            'Supplier',
                            'Client',
                            'Account',
                            'Processed By'
                        ];
                        break;

                }
                break;
            
        }
    }

    if ($query) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate totals for expense reports
    $expenseTotals = [];
    if ($reportCategory === 'expense') {
        foreach ($data as $row) {
            $currency = $row['currency'] ?? 'Unknown';
            if (!isset($expenseTotals[$currency])) {
                $expenseTotals[$currency] = 0;
            }
            $expenseTotals[$currency] += floatval($row['amount']);
        }
    }

    // Calculate totals for umrah reports
    $umrahTotals = [];
    if ($reportCategory === 'umrah') {
        foreach ($data as $row) {
            $umrahTotals['price'] = ($umrahTotals['price'] ?? 0) + floatval($row['price'] ?? 0);
            $umrahTotals['sold_price'] = ($umrahTotals['sold_price'] ?? 0) + floatval($row['sold_price'] ?? 0);
            $umrahTotals['profit'] = ($umrahTotals['profit'] ?? 0) + floatval($row['profit'] ?? 0);
        }
    }

    if ($format === 'pdf') {
        require_once('../../vendor/autoload.php');
    
        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'default_font_size' => 10,
        ];
    
        try {
            // Clean any accidental output before sending PDF headers
            while (ob_get_level()) {
                ob_end_clean();
            }
    
            $pdf = new \Mpdf\Mpdf($mpdfConfig);
    
            // Map headers to data keys or closures for dynamic mapping
            $headerToFieldMap = [
                'Supplier' => 'supplier_name',
                'Client' => 'client_name',
                'Sold To' => 'sold_to_name',
                'Account' => 'account_name',
                'Paid To' => 'paid_to_name',
                'Creditor' => 'creditor_name',
                'Debtor' => 'debtor_name',
                'Paid Amount' => 'paid_amount',
                'Received Amount' => 'received_amount',
                'Balance' => 'balance',
                'Status' => 'status',
                'Paid Status' => 'paid_status',
                'Date of Birth' => 'dob',
                'Father Name' => 'fname',
                'Group' => 'group_name',
                'Guest Name' => 'guest_name',
                'Reference Number' => 'reference_number',
                'Refund Amount' => 'refund_amount',
                'Sold Amount' => 'sold_amount',
                'Total Penalty' => 'total_penalty',
                'Base Amount' => 'base_amount',
                'Profit' => 'profit',
                'Bank Payment' => 'received_bank_payment',
                'Bank Receipt' => 'bank_receipt_number',
                'Phone' => function($rowData){
                    return !empty($rowData['phone']) ? $rowData['phone'] : (!empty($rowData['contact']) ? $rowData['contact'] : '');
                },
                'Sector' => ['origin','destination','return_destination','trip_type'],
                'Processed By' => 'processed_by_name',
                'Date' => 'created_at',
                'Refund Date' => 'created_at',
                'Weight (kg)' => 'weight',
            ];
    
            // Start HTML
            $html = '
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size:9pt; color:#333; line-height:1.4; }
                    .report-header { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:20px; margin:-15px -10px 20px -10px; border-radius:0 0 8px 8px; }
                    .report-title { color:#fff; text-align:center; font-size:18pt; font-weight:bold; margin:0 0 8px 0; text-transform:uppercase; letter-spacing:1px; }
                    .date-range { color:#f0f0f0; text-align:center; font-size:11pt; margin:0; }
                    .table-wrapper { margin-top:10px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
                    table { width:100%; border-collapse:collapse; background:#fff; }
                    thead tr { background:linear-gradient(180deg,#4a5568 0%,#2d3748 100%); }
                    th { color:#fff; padding:10px 8px; text-align:left; font-weight:bold; font-size:9pt; border:1px solid #2d3748; text-transform:uppercase; letter-spacing:0.5px; }
                    tbody tr:nth-child(even){background-color:#f7fafc;}
                    tbody tr:hover{background-color:#edf2f7;}
                    td{padding:8px; border:1px solid #e2e8f0; font-size:9pt; color:#2d3748;}
                    .numeric{text-align:right;font-family:"Courier New", monospace;font-weight:500;}
                    .indented{padding-left:25px;font-style:italic;color:#4a5568;}
                    .total-row{background:linear-gradient(180deg,#edf2f7 0%,#e2e8f0 100%) !important;font-weight:bold;border-top:3px solid #4a5568;border-bottom:3px solid #4a5568;}
                    .total-row td{font-size:10pt;color:#1a202c;border-color:#cbd5e0;padding:10px 8px;}
                    .status-paid{color:#22543d;background-color:#c6f6d5;padding:2px 8px;border-radius:4px;font-size:8pt;font-weight:bold;}
                    .status-pending{color:#744210;background-color:#feebc8;padding:2px 8px;border-radius:4px;font-size:8pt;font-weight:bold;}
                    .status-unpaid{color:#742a2a;background-color:#fed7d7;padding:2px 8px;border-radius:4px;font-size:8pt;font-weight:bold;}
                    .report-footer{margin-top:20px;padding-top:10px;border-top:2px solid #e2e8f0;font-size:8pt;color:#718096;text-align:center;}
                    .mini-table{margin-bottom:14px;}
                    .rowwrap{width:100%;border-collapse:collapse;margin-bottom:14px;}
                    .rowcell{padding:0;vertical-align:top;}
                    .rowwrap .mini-table{margin-bottom:0;}
                    .total-row td{font-weight:bold;background-color:#f7fafc;}
                    .mini-title{font-size:11pt;font-weight:bold;color:#4a5568;padding:6px 0 4px 0;border-bottom:2px solid #e2e8f0;margin-bottom:4px;}
                    .mini-table table{margin-bottom:6px;}
                    .section-title{font-size:13pt;font-weight:bold;color:#2d3748;background:linear-gradient(90deg,#edf2f7,#ffffff);padding:8px 10px;border-left:4px solid #4a5568;margin:18px 0 8px 0;border-radius:4px;page-break-inside:avoid;}
                </style>
            </head>
            <body>
                <div class="report-header">
                    <div class="report-title">' . htmlspecialchars($reportTitle) . '</div>
                    <div class="date-range">' . htmlspecialchars($dateRange) . '</div>
                </div>
<div class="table-wrapper">';

            if ($reportCategory === 'general_summary') {
                // Hierarchical: sections with small tables per source, several per row
                $renderMiniTable = function ($tableData) use ($headers) {
                    $tableHeaders = isset($tableData['headers']) ? $tableData['headers'] : $headers;
                    $html = '<div class="mini-table"><div class="mini-title">' . htmlspecialchars($tableData['title']) . '</div>';
                    $html .= '<table><thead><tr>';
                    foreach ($tableHeaders as $header) {
                        $html .= '<th>' . htmlspecialchars($header) . '</th>';
                    }
                    $html .= '</tr></thead><tbody>';
                    foreach ($tableData['rows'] as $rowData) {
                        $isSummaryRow = empty($rowData['date']);
                        $html .= '<tr' . (!empty($rowData['bold']) ? ' class="total-row"' : '') . '>';
                        foreach ($tableHeaders as $header) {
                            $key = strtolower(str_replace(' ', '_', $header));
                            if (in_array($header, ['Profit', 'Cash In', 'Cash Out', 'Balance', 'Paid', 'Remaining'])) {
                                $value = isset($rowData[$key]) ? number_format($rowData[$key], 2) : '0.00';
                                if (in_array($header, ['Cash In', 'Cash Out']) && !$isSummaryRow && isset($rowData[$key]) && $rowData[$key] > 0) {
                                    $cashCur = !empty($rowData['cash_currency']) ? $rowData['cash_currency'] : (isset($rowData['currency']) ? $rowData['currency'] : '');
                                    $value .= ' ' . $cashCur;
                                }
                            } else {
                                $value = isset($rowData[$key]) ? htmlspecialchars($rowData[$key]) : '';
                            }
                            $html .= '<td' . (in_array($header, ['Profit', 'Cash In', 'Cash Out', 'Balance', 'Paid', 'Remaining']) ? ' class="numeric"' : '') . '>' . $value . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table></div>';
                    return $html;
                };
                $renderRow = function ($rowTables) use ($renderMiniTable) {
                    $html = '<table class="rowwrap"><tr>';
                    $cellWidth = floor((277 - (count($rowTables) - 1) * 5) / count($rowTables));
                    foreach ($rowTables as $idx => $tableData) {
                        $padRight = ($idx === count($rowTables) - 1) ? '0' : '5mm';
                        $html .= '<td class="rowcell" style="width:' . $cellWidth . 'mm;padding-right:' . $padRight . ';">';
                        $html .= $renderMiniTable($tableData);
                        $html .= '</td>';
                    }
                    $html .= '</tr></table>';
                    return $html;
                };
                $pending = [];
                foreach ($sections as $section) {
                    if ($section['title'] === 'SUMMARY' && count($pending) > 0) {
                        $html .= $renderRow($pending);
                        $pending = [];
                    }
                    foreach ($section['tables'] as $tableData) {
                        $pending[] = $tableData;
                        if (count($pending) === 3) {
                            $html .= $renderRow($pending);
                            $pending = [];
                        }
                    }
                }
                if (count($pending) > 0) {
                    $html .= $renderRow($pending);
                }
            } else {
                $html .= '<table><thead><tr>';

                // Headers
                foreach ($headers as $header) {
                    $html .= '<th>' . htmlspecialchars($header) . '</th>';
                }
                $html .= '</tr></thead><tbody>';

                // Data rows
                foreach ($data as $rowData) {
                    $rowClass = '';
                    $isChild = false;
                    if (isset($rowData['record_type']) && $rowData['record_type'] !== 'normal') {
                        $rowClass = $rowData['record_type'] === 'refund' ? 'refund' : 'date-change';
                        $isChild = true;
                    }
                    if ($reportCategory === 'umrah' && isset($rowData['refund_status']) && !empty($rowData['refund_status'])) {
                        $rowClass = 'umrah-refunded';
                    }

                    $html .= '<tr class="' . $rowClass . '">';

                    foreach ($headers as $headerIdx => $header) {
                        $value = '';

                        if (isset($headerToFieldMap[$header])) {
                            $field = $headerToFieldMap[$header];

                            if (is_callable($field)) {
                                $value = $field($rowData);
                            } elseif (is_array($field) && $header === 'Sector') {
                                $value = isset($rowData['origin']) && isset($rowData['destination']) 
                                         ? $rowData['origin'].' → '.$rowData['destination'] : '';
                                if (isset($rowData['trip_type']) && in_array(strtolower($rowData['trip_type']), ['round_trip','round trip']) 
                                    && !empty($rowData['return_destination'])) {
                                    $value .= ' → '.$rowData['return_destination'];
                                }
                            } elseif (in_array($header, ['Date of Birth','Date','Refund Date'])) {
                                $value = !empty($rowData[$field]) ? date('Y-m-d', strtotime($rowData[$field])) : '';
                            } elseif (in_array($header, ['Paid Amount','Received Amount','Balance','Sold Amount','Refund Amount','Profit','Base Amount','Bank Payment','Weight (kg)'])) {
                                $value = isset($rowData[$field]) ? number_format($rowData[$field],2) : '0.00';
                                if ($header === 'Weight (kg)') $value .= ' kg';
                            } else {
                                $value = isset($rowData[$field]) ? htmlspecialchars($rowData[$field]) : '';
                            }
                        } else {
                            $key = strtolower(str_replace(' ', '_', $header));
                            $value = isset($rowData[$key]) ? htmlspecialchars($rowData[$key]) : '';
                        }

                        // Indent child rows
                        if ($isChild && $headerIdx === 0) $value = '    '.$value;

                        $html .= '<td class="' . (in_array($header,['Paid Amount','Received Amount','Balance','Sold Amount','Refund Amount','Profit','Base Amount','Bank Payment','Weight (kg)']) ? 'numeric':'') . '">' . $value . '</td>';
                    }
                    $html .= '</tr>';
                }
            }
    
            // Totals for Umrah (Admin)
            if ($reportCategory === 'umrah' && !empty($umrahTotals) && $user_role === 'admin') {
                $numericColumns = ['Price'=>'price','Sold Price'=>'sold_price','Profit'=>'profit'];
                foreach ($numericColumns as $colName => $keyName) {
                    $html .= '<tr class="total-row">';
                    foreach ($headers as $idx => $header) {
                        if ($idx === 0) $html .= '<td><strong>TOTAL '.$colName.'</strong></td>';
                        elseif ($header === $colName) $html .= '<td class="numeric"><strong>'.number_format($umrahTotals[$keyName],2).'</strong></td>';
                        else $html .= '<td></td>';
                    }
                    $html .= '</tr>';
                }
            }
    
            $html .= '</tbody></table></div>';
            $html .= '<div class="report-footer">Generated on ' . date('F d, Y \a\t H:i:s') . ' | Page {PAGENO} of {nbpg}</div>';
            $html .= '</body></html>';
    
            $pdf->WriteHTML($html);
    
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="report.pdf"');
            echo $pdf->Output('report.pdf', \Mpdf\Output\Destination::STRING_RETURN);
            exit;
    
        } catch (\Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error generating PDF: ' . $e->getMessage();
            exit;
        }
    }
    
    
    
    elseif ($format === 'word') {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Set default font
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(6);
        
        // Add new section with specific page settings
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginLeft' => 500,
            'marginRight' => 500,
            'marginTop' => 500,
            'marginBottom' => 500
        ]);
        
        // Add title with center alignment
        $section->addText($reportTitle, ['bold' => true, 'size' => 16], ['alignment' => 'center']);
        $section->addText($dateRange, ['size' => 12], ['alignment' => 'center']);
        $section->addTextBreak(1);
        
        // Define table style
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 50
        ];
        
        // Define first row style
        $firstRowStyle = [
            'bgColor' => 'f2f2f2'
        ];

        // Add table(s) - general summary uses small tables per source
        if ($reportCategory === 'general_summary') {
            foreach ($sections as $sectionData) {
                $section->addText($sectionData['title'], ['bold' => true, 'size' => 14]);
                foreach (array_chunk($sectionData['tables'], 3) as $chunk) {
                        $wrapper = $section->addTable();
                        $wrapper->addRow();
                        foreach ($chunk as $tableData) {
                            $cell = $wrapper->addCell(3600);
                            $tableHeaders = isset($tableData['headers']) ? $tableData['headers'] : $headers;
                            $cell->addText($tableData['title'], ['bold' => true, 'size' => 12]);
                            $miniTable = $cell->addTable($tableStyle);
                            $miniTable->addRow();
                            foreach ($tableHeaders as $header) {
                                $innerCell = $miniTable->addCell(null, $firstRowStyle);
                                $innerCell->addText(ucwords(str_replace('_', ' ', $header)), ['bold' => true]);
                            }
                            foreach ($tableData['rows'] as $rowData) {
                                $isSummaryRow = empty($rowData['date']);
                                $rowIsBold = !empty($rowData['bold']);
                                $miniTable->addRow();
                                foreach ($tableHeaders as $header) {
                                    $key = strtolower(str_replace(' ', '_', $header));
                                    if (in_array($header, ['Profit', 'Cash In', 'Cash Out', 'Balance', 'Paid', 'Remaining'])) {
                                        $value = isset($rowData[$key]) ? number_format($rowData[$key], 2) : '0.00';
                                        if (in_array($header, ['Cash In', 'Cash Out']) && !$isSummaryRow && isset($rowData[$key]) && $rowData[$key] > 0) {
                                            $cashCur = !empty($rowData['cash_currency']) ? $rowData['cash_currency'] : (isset($rowData['currency']) ? $rowData['currency'] : '');
                                            $value .= ' ' . $cashCur;
                                        }
                                    } else {
                                        $value = isset($rowData[$key]) ? $rowData[$key] : '';
                                    }
                                    $innerCell = $miniTable->addCell();
                                    $innerCell->addText($value, $rowIsBold ? ['bold' => true] : []);
                                }
                            }
}
                }
            }
        } else {
        // Add table
        $table = $section->addTable($tableStyle);
        $table->addRow();

        // Add headers
        foreach ($headers as $header) {
            // Format the header text to be more readable
            $headerText = str_replace('_', ' ', $header);
            $headerText = ucwords($headerText);
            
            // Special case formatting for specific headers
            switch($headerText) {
                case 'Pnr':
                    $headerText = 'PNR';
                    break;
                case 'Id':
                    $headerText = 'ID';
                    break;
                case 'Dob':
                    $headerText = 'DOB';
                    break;
            }

            $cell = $table->addCell(null, $firstRowStyle);
            $cell->addText($headerText, ['bold' => true]);
        }

        // Add data rows
        foreach ($data as $rowData) {
            $table->addRow();
            
            // Determine row style based on record type
            $rowStyle = [];
            if (isset($rowData['record_type'])) {
                if ($rowData['record_type'] === 'refund') {
                    $rowStyle = ['bgColor' => 'ffcccc'];
                } elseif ($rowData['record_type'] === 'date_change') {
                    $rowStyle = ['bgColor' => 'ffffcc'];
                }
            }
            
            foreach ($headers as $header) {
                $value = '';
                switch($header) {
                    case 'Supplier':
                        $value = isset($rowData['supplier_name']) ? $rowData['supplier_name'] : '';
                        break;
                    case 'Sold To':
                    case 'Client':
                        $value = isset($rowData['client_name']) ? $rowData['client_name'] : 
                                (isset($rowData['sold_to_name']) ? $rowData['sold_to_name'] : '');
                        break;
                    case 'Paid To':
                    case 'Account':
                        $value = isset($rowData['paid_to_name']) ? $rowData['paid_to_name'] : 
                                (isset($rowData['account_name']) ? $rowData['account_name'] : '');
                        break;
                    case 'Creditor':
                        $value = isset($rowData['creditor_name']) ? $rowData['creditor_name'] : '';
                        break;
                    case 'Debtor':
                        $value = isset($rowData['debtor_name']) ? $rowData['debtor_name'] : '';
                        break;
                    case 'Paid Amount':
                        $value = isset($rowData['paid_amount']) ? number_format($rowData['paid_amount'], 2) : '0.00';
                        break;
                    case 'Received Amount':
                        $value = isset($rowData['received_amount']) ? number_format($rowData['received_amount'], 2) : '0.00';
                        break;
                    case 'Balance':
                        $value = isset($rowData['balance']) ? number_format($rowData['balance'], 2) : '0.00';
                        break;
                    case 'Status':
                        $value = isset($rowData['status']) ? $rowData['status'] : '';
                        break;
                    case 'Address':
                        $value = isset($rowData['address']) ? $rowData['address'] : '';
                        break;
                    case 'Father Name':
                        $value = isset($rowData['fname']) ? $rowData['fname'] : '';
                        break;
                    case 'Group':
                        $value = isset($rowData['group_name']) ? $rowData['group_name'] : '';
                        break;
                    case 'Base':
                        $value = isset($rowData['base']) ? $rowData['base'] : '';
                        break;
                    case 'Refund Amount':
                        $value = isset($rowData['refund_to_passenger']) ? $rowData['refund_to_passenger'] : 
                                (isset($rowData['refund_amount']) ? number_format($rowData['refund_amount'], 2) : '0.00');
                        break;
                    case 'Sold Amount':
                        $value = isset($rowData['sold_amount']) ? number_format($rowData['sold_amount'], 2) : 
                                (isset($rowData['sold']) ? number_format($rowData['sold'], 2) : '0.00');
                        break;
                    case 'Total Penalty':
                        $value = isset($rowData['total_penalty']) ? $rowData['total_penalty'] : '';
                        break;
                    case 'Date of Birth':
                        $value = isset($rowData['dob']) ? $rowData['dob'] : '';
                        break;
                    case 'Guest Name':
                        $value = isset($rowData['guest_name']) ? $rowData['guest_name'] : '';
                        break;
                    case 'Reference Number':
                        $value = isset($rowData['reference_number']) ? $rowData['reference_number'] : '';
                        break;
                    case 'Paid Status':
                        $value = isset($rowData['paid_status']) ? $rowData['paid_status'] : '';
                        break;
                    case 'Base Amount':
                        $value = isset($rowData['base_amount']) ? number_format($rowData['base_amount'], 2) : '0.00';
                        break;
                    case 'Profit':
                        $value = isset($rowData['profit']) ? number_format($rowData['profit'], 2) : '0.00';
                        break;
                        case 'Bank Payment':
                            $value = isset($rowData['received_bank_payment']) ? number_format($rowData['received_bank_payment'], 2) : '0.00';
                            break;
                            case 'Bank Receipt':
                                $value = isset($rowData['bank_receipt_number']) ? $rowData['bank_receipt_number'] : '';
                                break;
                    case 'Weight (kg)':
                        $value = isset($rowData['weight']) ? number_format($rowData['weight'], 2) . ' kg' : '';
                        break;
                    case 'Date':
                        $value = isset($rowData['created_at']) ? date('Y-m-d', strtotime($rowData['created_at'])) : '';
                        break;
                    case 'Refund Date':
                        $value = isset($rowData['created_at']) ? date('Y-m-d', strtotime($rowData['created_at'])) : '';
                        break;
                        case 'Phone':
                            $value = isset($rowData['phone']) ? $rowData['phone'] : (isset($rowData['contact']) ? $rowData['contact'] : '');
                            break;
                    case 'Contact':
                        $value = isset($rowData['contact_no']) ? $rowData['contact_no'] : '';
                        break;
                    case 'Sector':
                        if (isset($rowData['origin']) && isset($rowData['destination'])) {
                            $value = $rowData['origin'] . ' - ' . $rowData['destination'];
                            // Add return destination if trip_type is 'round_trip' or 'round trip'
                            if (isset($rowData['trip_type']) && (strtolower($rowData['trip_type']) == 'round_trip' || strtolower($rowData['trip_type']) == 'round trip') && !empty($rowData['return_destination'])) {
                                $value .= ' - ' . $rowData['return_destination'];
                            }
                        } else {
                            $value = '';
                        }
                        break;
                        case 'Processed By':
                            $value = isset($rowData['processed_by_name']) ? $rowData['processed_by_name'] : '';
                            break;

                        case 'Client':
                            $value = isset($rowData['client_name']) ? $rowData['client_name'] :
                                    (isset($rowData['sold_to_name']) ? $rowData['sold_to_name'] : '');
                            break;

                        case 'Account':
                            $value = isset($rowData['paid_to_name']) ? $rowData['paid_to_name'] :
                                    (isset($rowData['account_name']) ? $rowData['account_name'] : '');
                            break;

                        case 'Supplier':
                            $value = isset($rowData['supplier_name']) ? $rowData['supplier_name'] : '';
                            break;

                    default:
                        $fieldName = strtolower(str_replace(' ', '_', $header));
                        $value = isset($rowData[$fieldName]) ? $rowData[$fieldName] : '';
                }
                
                // Add indentation for child records in the first column
                if (isset($rowData['record_type']) && $rowData['record_type'] !== 'normal' && $header === $headers[0]) {
                    $value = '    ' . $value;
                }
                
                $cell = $table->addCell(null, $rowStyle);
                $cell->addText($value, $rowIsBold ? ['bold' => true] : []);
            }
        }
        
        // Add total rows for expense reports
        if ($reportCategory === 'expense' && !empty($expenseTotals)) {
            foreach ($expenseTotals as $currency => $total) {
                $table->addRow();

                // Style for total row
                $totalRowStyle = ['bgColor' => 'f2f2f2'];

                // Find the index of the Amount column
                $amountColumnIndex = array_search('Amount', $headers);

                // Add cells for total row
                foreach ($headers as $index => $header) {
                    $cell = $table->addCell(null, $totalRowStyle);

                    if ($index === 0) {
                        // First column shows "TOTAL"
                        $cell->addText('TOTAL', ['bold' => true]);
                    }
                    elseif ($index === $amountColumnIndex) {
                        // Amount column shows the total value
                        $cell->addText(number_format($total, 2), ['bold' => true]);
                    }
                    elseif ($index === $amountColumnIndex + 1) {
                        // Currency column shows the currency
                        $cell->addText($currency, ['bold' => true]);
                    }
                    else {
                        // Other columns are empty
                        $cell->addText('');
                    }
                }
            }
        }

        // Add total rows for umrah reports
        if ($reportCategory === 'umrah' && !empty($umrahTotals) && $user_role === 'admin') {
            // Style for total row
            $totalRowStyle = ['bgColor' => 'f2f2f2'];

            // Total Price row
            $table->addRow();
            foreach ($headers as $index => $header) {
                $cell = $table->addCell(null, $totalRowStyle);
                if ($index === 0) {
                    $cell->addText('TOTAL PRICE', ['bold' => true]);
                } elseif ($header === 'Price') {
                    $cell->addText(number_format($umrahTotals['price'], 2), ['bold' => true]);
                } else {
                    $cell->addText('');
                }
            }

            // Total Sold Price row
            $table->addRow();
            foreach ($headers as $index => $header) {
                $cell = $table->addCell(null, $totalRowStyle);
                if ($index === 0) {
                    $cell->addText('TOTAL SOLD PRICE', ['bold' => true]);
                } elseif ($header === 'Sold Price') {
                    $cell->addText(number_format($umrahTotals['sold_price'], 2), ['bold' => true]);
                } else {
                    $cell->addText('');
                }
            }

            // Total Profit row
            $table->addRow();
            foreach ($headers as $index => $header) {
                $cell = $table->addCell(null, $totalRowStyle);
                if ($index === 0) {
                    $cell->addText('TOTAL PROFIT', ['bold' => true]);
                } elseif ($header === 'Profit') {
                    $cell->addText(number_format($umrahTotals['profit'], 2), ['bold' => true]);
                } else {
                    $cell->addText('');
                }
            }
        }
        }
        
        // Save file
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="report.docx"');
        header('Cache-Control: max-age=0');
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
    }
    elseif ($format === 'excel') {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Add title and date range
        $sheet->setCellValue('A1', $reportTitle);
        $sheet->setCellValue('A2', $dateRange);
        
        // Merge cells for title and date
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        
        // Style the header
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray($titleStyle);
        $sheet->getStyle("A2:{$lastColumn}2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // General summary: sections with small tables per source
        if ($reportCategory === 'general_summary') {
            $miniHeaderStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F2F2']
                ]
            ];
            $row = 4;
            $slotStartCols = [1, 9, 17];
            foreach ($sections as $sectionData) {
                $sheet->setCellValue("A{$row}", $sectionData['title']);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}")->getFont()->setSize(13);
                $row++;
                foreach (array_chunk($sectionData['tables'], 3) as $chunk) {
                    $maxAdvance = 0;
                    foreach ($chunk as $slotIdx => $tableData) {
                        $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($slotStartCols[$slotIdx]);
                        $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($slotStartCols[$slotIdx] + 7);
                        $tableHeaders = isset($tableData['headers']) ? $tableData['headers'] : $headers;
                        $sheet->setCellValue($startCol . $row, $tableData['title']);
                        $sheet->getStyle($startCol . $row)->getFont()->setBold(true);
                        $sheet->getStyle($startCol . $row)->getFont()->setSize(11);
                        $r = $row + 1;
                        $column = $startCol;
                        foreach ($tableHeaders as $header) {
                            $sheet->setCellValue($column . $r, $header);
                            $sheet->getColumnDimension($column)->setAutoSize(true);
                            $column++;
                        }
                        $sheet->getStyle("{$startCol}{$r}:{$endCol}{$r}")->applyFromArray($miniHeaderStyle);
                        $r++;
                        foreach ($tableData['rows'] as $rowData) {
                            $isSummaryRow = empty($rowData['date']);
                            $column = $startCol;
                            foreach ($tableHeaders as $header) {
                                $key = strtolower(str_replace(' ', '_', $header));
                                if (in_array($header, ['Profit', 'Cash In', 'Cash Out', 'Balance', 'Paid', 'Remaining'])) {
                                    $value = isset($rowData[$key]) ? number_format($rowData[$key], 2) : '0.00';
                                    if (in_array($header, ['Cash In', 'Cash Out']) && !$isSummaryRow && isset($rowData[$key]) && $rowData[$key] > 0) {
                                        $cashCur = !empty($rowData['cash_currency']) ? $rowData['cash_currency'] : (isset($rowData['currency']) ? $rowData['currency'] : '');
                                        $value .= ' ' . $cashCur;
                                    }
                                } else {
                                    $value = isset($rowData[$key]) ? $rowData[$key] : '';
                                }
                                $sheet->setCellValue($column . $r, $value);
                                $column++;
                            }
                            if (!empty($rowData['bold'])) {
                                $sheet->getStyle($startCol . $r . ':' . $endCol . $r)->getFont()->setBold(true);
                            }
                            $r++;
                        }
                        if ($r - $row > $maxAdvance) {
                            $maxAdvance = $r - $row;
                        }
                    }
                    $row += $maxAdvance + 1;
                }
            }
        } else {
        // Add headers at row 4
        $column = 'A';
        $headerRow = 4;
        foreach ($headers as $header) {
            $sheet->setCellValue($column . $headerRow, $header);
            $sheet->getColumnDimension($column)->setAutoSize(true);
            $column++;
        }
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2']
            ]
        ];
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray($headerStyle);
        
        // Add data starting at row 5
        $row = 5;
        foreach ($data as $rowData) {
            $column = 'A';
            $rowStyle = []; // Reset row style for each row

            // Determine row style for umrah records
            if ($reportCategory === 'umrah') {
                if (isset($rowData['refund_status']) && !empty($rowData['refund_status'])) {
                    $rowStyle = ['fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFCCCC']
                    ]];
                }
            }

            foreach ($headers as $header) {
                $value = '';
                switch($header) {
                    case 'Supplier':
                        $value = isset($rowData['supplier_name']) ? $rowData['supplier_name'] : '';
                        break;
                    case 'Sold To':
                    case 'Client':
                        $value = isset($rowData['client_name']) ? $rowData['client_name'] : 
                                (isset($rowData['sold_to_name']) ? $rowData['sold_to_name'] : '');
                        break;
                    case 'Paid To':
                    case 'Account':
                        $value = isset($rowData['paid_to_name']) ? $rowData['paid_to_name'] : 
                                (isset($rowData['account_name']) ? $rowData['account_name'] : '');
                        break;
                    case 'Creditor':
                        $value = isset($rowData['creditor_name']) ? $rowData['creditor_name'] : '';
                        break;
                    case 'Debtor':
                        $value = isset($rowData['debtor_name']) ? $rowData['debtor_name'] : '';
                        break;
                    case 'Paid Amount':
                        $value = isset($rowData['paid_amount']) ? number_format($rowData['paid_amount'], 2) : '0.00';
                        break;
                    case 'Received Amount':
                        $value = isset($rowData['received_amount']) ? number_format($rowData['received_amount'], 2) : '0.00';
                        break;
                    case 'Balance':
                        $value = isset($rowData['balance']) ? number_format($rowData['balance'], 2) : '0.00';
                        break;
                    case 'Status':
                        $value = isset($rowData['status']) ? $rowData['status'] : '';
                        break;
                    case 'Address':
                        $value = isset($rowData['address']) ? $rowData['address'] : '';
                        break;
                    case 'Father Name':
                        $value = isset($rowData['fname']) ? $rowData['fname'] : '';
                        break;
                    case 'Group':
                        $value = isset($rowData['group_name']) ? $rowData['group_name'] : '';
                        break;
                    case 'Base':
                        $value = isset($rowData['base']) ? $rowData['base'] : '';
                        break;
                    case 'Refund Amount':
                        $value = isset($rowData['refund_to_passenger']) ? $rowData['refund_to_passenger'] : 
                                (isset($rowData['refund_amount']) ? number_format($rowData['refund_amount'], 2) : '0.00');
                        break;
                    case 'Sold Amount':
                        $value = isset($rowData['sold_amount']) ? number_format($rowData['sold_amount'], 2) : 
                                (isset($rowData['sold']) ? number_format($rowData['sold'], 2) : '0.00');
                        break;
                    case 'Total Penalty':
                        $value = isset($rowData['total_penalty']) ? $rowData['total_penalty'] : '';
                        break;
                    case 'Date of Birth':
                        $value = isset($rowData['dob']) ? $rowData['dob'] : '';
                        break;
                    case 'Guest Name':
                        $value = isset($rowData['guest_name']) ? $rowData['guest_name'] : '';
                        break;
                    case 'Reference Number':
                        $value = isset($rowData['reference_number']) ? $rowData['reference_number'] : '';
                        break;
                    case 'Paid Status':
                        $value = isset($rowData['paid_status']) ? $rowData['paid_status'] : '';
                        break;
                    case 'Base Amount':
                        $value = isset($rowData['base_amount']) ? number_format($rowData['base_amount'], 2) : '0.00';
                        break;
                    case 'Profit':
                        $value = isset($rowData['profit']) ? number_format($rowData['profit'], 2) : '0.00';
                        break;
                        case 'Bank Payment':
                            $value = isset($rowData['received_bank_payment']) ? number_format($rowData['received_bank_payment'], 2) : '0.00';
                            break;
                            case 'Bank Receipt':
                                $value = isset($rowData['bank_receipt_number']) ? $rowData['bank_receipt_number'] : '';
                                break;
                    case 'Weight (kg)':
                        $value = isset($rowData['weight']) ? number_format($rowData['weight'], 2) . ' kg' : '';
                        break;
                    case 'Date':
                        $value = isset($rowData['created_at']) ? date('Y-m-d', strtotime($rowData['created_at'])) : '';
                        break;
                    case 'Refund Date':
                        $value = isset($rowData['created_at']) ? date('Y-m-d', strtotime($rowData['created_at'])) : '';
                        break;
                        case 'Phone':
                            $value = isset($rowData['phone']) ? $rowData['phone'] : (isset($rowData['contact']) ? $rowData['contact'] : '');
                            break;
                    case 'Contact':
                        $value = isset($rowData['contact_no']) ? $rowData['contact_no'] : '';
                        break;
                    case 'Sector':
                        if (isset($rowData['origin']) && isset($rowData['destination'])) {
                            $value = $rowData['origin'] . ' - ' . $rowData['destination'];
                            // Add return destination if trip_type is 'round_trip' or 'round trip'
                            if (isset($rowData['trip_type']) && (strtolower($rowData['trip_type']) == 'round_trip' || strtolower($rowData['trip_type']) == 'round trip') && !empty($rowData['return_destination'])) {
                                $value .= ' - ' . $rowData['return_destination'];
                            }
                        } else {
                            $value = '';
                        }
                        break;
                        case 'Processed By':
                            $value = isset($rowData['processed_by_name']) ? $rowData['processed_by_name'] : '';
                            break;
                    default:
                        $fieldName = strtolower(str_replace(' ', '_', $header));
                        $value = isset($rowData[$fieldName]) ? $rowData[$fieldName] : '';
                }
                
                // Add indentation for child records
                if (isset($rowData['record_type']) && $rowData['record_type'] !== 'normal' && $column === 'A') {
                    $value = '    ' . $value;
                }
                
                $sheet->setCellValue($column . $row, $value);
                $column++;
            }
            
            // Apply row style if set
            if (!empty($rowStyle)) {
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($rowStyle);
            }
            
            $row++;
        }
        
        // Add total rows for expense reports
        if ($reportCategory === 'expense' && !empty($expenseTotals)) {
            foreach ($expenseTotals as $currency => $total) {
                $column = 'A';

                // First column is "TOTAL"
                $sheet->setCellValue($column . $row, 'TOTAL');
                $column++;

                // Skip columns until Amount
                $amountColumnIndex = 0;
                foreach ($headers as $index => $header) {
                    if ($header === 'Amount') {
                        $amountColumnIndex = $index;
                        break;
                    }
                }

                for ($i = 1; $i < $amountColumnIndex; $i++) {
                    $sheet->setCellValue($column . $row, '');
                    $column++;
                }

                // Add amount
                $sheet->setCellValue($column . $row, number_format($total, 2));
                $column++;

                // Add currency
                $sheet->setCellValue($column . $row, $currency);

                // Style the total row
                $totalStyle = [
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2']
                    ]
                ];
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($totalStyle);

                $row++;
            }
        }

        // Add total rows for umrah reports
        if ($reportCategory === 'umrah' && !empty($umrahTotals) && $user_role === 'admin') {
            // Style for total rows
            $totalStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F2F2']
                ]
            ];

            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

            // Total Price row
            $sheet->setCellValue('A' . $row, 'TOTAL PRICE');
            $priceIndex = array_search('Price', $headers);
            if ($priceIndex !== false) {
                $priceColumn = chr(65 + $priceIndex); // A=0, B=1, ..., K=10
                $sheet->setCellValue($priceColumn . $row, number_format($umrahTotals['price'], 2));
            }
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($totalStyle);
            $row++;

            // Total Sold Price row
            $sheet->setCellValue('A' . $row, 'TOTAL SOLD PRICE');
            $soldPriceIndex = array_search('Sold Price', $headers);
            if ($soldPriceIndex !== false) {
                $soldPriceColumn = chr(65 + $soldPriceIndex);
                $sheet->setCellValue($soldPriceColumn . $row, number_format($umrahTotals['sold_price'], 2));
            }
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($totalStyle);
            $row++;

            // Total Profit row
            $sheet->setCellValue('A' . $row, 'TOTAL PROFIT');
            $profitIndex = array_search('Profit', $headers);
            if ($profitIndex !== false) {
                $profitColumn = chr(65 + $profitIndex);
                $sheet->setCellValue($profitColumn . $row, number_format($umrahTotals['profit'], 2));
            }
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($totalStyle);
            $row++;
        }
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="report.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
