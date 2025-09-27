<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// generate_report.php - Handles report generation in PDF, Excel, or Word formats
require_once '../includes/db.php';
include '../vendor/autoload.php'; // Load PhpSpreadsheet and Dompdf
$user_role = $_SESSION["role"];
$tenant_id = $_SESSION['tenant_id'];
$reportType = $_GET['reportType'];
$entity = $_GET['entity'];
$reportCategory = $_GET['reportCategory'];
$startDate = $_GET['startDate'];
$endDate = $_GET['endDate'];
$format = $_GET['format'];
$expenseCategory = isset($_GET['expenseCategory']) ? $_GET['expenseCategory'] : '';
$umrahFamilyType = isset($_GET['umrahFamilyType']) ? $_GET['umrahFamilyType'] : '';
$specificFamily = isset($_GET['specificFamily']) ? $_GET['specificFamily'] : '';

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
            $stmt = $pdo->prepare("SELECT name FROM $entityTable WHERE id = ?");
            $stmt->execute([$entity]);
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
        }
    } else {
        $reportTitle = "$categoryTitle Reports for " . ucwords($reportType) . ": $entityName";
    }
    
    $dateRange = "Period: " . date('d M Y', strtotime($startDate)) . " to " . date('d M Y', strtotime($endDate));

    $query = "";
    $params = [];
    $headers = [];
    
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
                AND tb.tenant_id = ?
                GROUP BY tb.id";

                $params = [$startDate, $endDate, $tenant_id];

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
                    WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
                    GROUP BY tb.id";
                $params = [$startDate, $endDate, $tenant_id];
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
                    WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
                    GROUP BY rt.id";
                $params = [$startDate, $endDate, $tenant_id];
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
                    WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                    GROUP BY dc.id";
                $params = [$startDate, $endDate, $tenant_id];
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
                    WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?
                    GROUP BY h.id";
                $params = [$startDate, $endDate, $tenant_id];
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
                    WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ?
                    GROUP BY h.id";
                $params = [$startDate, $endDate, $tenant_id];
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
                    WHERE e.date BETWEEN ? AND ? AND e.tenant_id = ?"
                    . ($expenseCategory && $expenseCategory !== 'all' ? " AND e.category_id = ?" : "") .
                    " ORDER BY e.date DESC";
                $params = [$startDate, $endDate, $tenant_id];
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
                         AND payment_date BETWEEN ? AND ?) as paid_amount,
                        (SELECT COALESCE(SUM(amount), 0) 
                         FROM creditor_transactions 
                         WHERE creditor_id = c.id 
                         AND transaction_type = 'debit' 
                         AND payment_date BETWEEN ? AND ?) as received_amount
                        FROM creditors c
                        WHERE c.tenant_id = ?
                        ORDER BY c.name ASC";
                $params = [$startDate, $endDate, $startDate, $endDate, $tenant_id];
                $headers = ['ID', 'Creditor Name', 'Phone', 'Email', 'Address', 'Balance', 'Currency', 'Status', 'Paid Amount', 'Received Amount'];
                break;
            
            case 'debtor':
                $query = "SELECT 
                        d.id, 
                        d.name as debtor_name,
                        d.phone,
                        d.email,
                        d.address,
                        d.balance,
                        d.currency,
                        d.status,
                        (SELECT COALESCE(SUM(amount), 0) 
                         FROM debtor_transactions 
                         WHERE debtor_id = d.id 
                         AND transaction_type = 'debit' 
                         AND payment_date BETWEEN ? AND ?) as paid_amount,
                        (SELECT COALESCE(SUM(amount), 0) 
                         FROM debtor_transactions 
                         WHERE debtor_id = d.id 
                         AND transaction_type = 'credit' 
                         AND payment_date BETWEEN ? AND ?) as received_amount
                        FROM debtors d
                        WHERE d.tenant_id = ?
                        ORDER BY d.name ASC";
                $params = [$startDate, $endDate, $startDate, $endDate, $tenant_id];
                $headers = ['ID', 'Debtor Name', 'Phone', 'Email', 'Address', 'Balance', 'Currency', 'Status', 'Paid Amount', 'Received Amount'];
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
                    WHERE ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ?
                    ORDER BY ap.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id];
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
                    WHERE v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?
                    ORDER BY v.receive_date DESC";
                $params = [$startDate, $endDate, $tenant_id];
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
                $query = "SELECT
                    u.booking_id,
                    u.name,
                    u.passport_number,
                    u.dob,
                    u.flight_date,
                    u.return_date,
                    u.duration,
                    u.room_type,
                    u.price,
                    u.sold_price,
                    u.profit,
                    u.received_bank_payment,
                    u.bank_receipt_number,
                    u.paid, u.due,
                    u.currency,
                    f.head_of_family,
                    f.tazmin,
                    u.remarks,
                    f.visa_status,
                    s.name as supplier_name,
                    c.name as client_name,
                    m.name as account_name,
                    u.created_at,
                    f.contact,
                    ur.refund_type as refund_status
                    FROM umrah_bookings u
                    LEFT JOIN families f ON u.family_id = f.family_id
                    LEFT JOIN suppliers s ON u.supplier = s.id
                    LEFT JOIN clients c ON u.sold_to = c.id
                    LEFT JOIN main_account m ON u.paid_to = m.id
                    LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                    WHERE u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?"
                    . ($umrahFamilyType === 'specific' && $specificFamily ? " AND u.family_id = ?" : "") .
                    " GROUP BY u.booking_id
                    ORDER BY u.entry_date DESC";
                $params = [$startDate, $endDate, $tenant_id];
                if ($umrahFamilyType === 'specific' && $specificFamily) {
                    $params[] = $specificFamily;
                }
                // Base headers (shown to everyone)
                $headers = [
                    'Head of Family',
                    'Name', 
                    'Passport Number', 
                    'Date of Birth',
                    'Phone',
                    'Created At',
                    'Flight Date',
                    'Return Date',
                    'Duration',
                    'Room Type',
                    'Sold Price',   // ✅ visible for everyone
                    'Bank Payment',
                    'Bank Receipt',
                    'Paid',
                    'Due',
                    'Currency',
                    'Tazmin',
                    'Client',
                    'Account',
                    'Visa Status',
                    'Remarks',
                    'Refund Status'
                ];

                // Add sensitive headers only if admin
                if ($user_role === 'admin') {
                    $insertIndex = array_search('Sold Price', $headers);
                if ($insertIndex !== false) {
                    // Insert Price before Sold Price
                    array_splice($headers, $insertIndex, 0, ['Price']);
                    
                    // Recalculate index of Sold Price (it shifted by +1 after insertion)
                    $soldPriceIndex = array_search('Sold Price', $headers);
                    
                    // Insert Profit right after Sold Price
                    array_splice($headers, $soldPriceIndex + 1, 0, ['Profit']);
                }


                    // Add Supplier at the end
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
                    WHERE tw.created_at BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
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
                    WHERE vr.refund_date BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
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
                    WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
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
                    ur.refund_type,
                    ur.refund_amount,
                    ur.currency,
                    ur.exchange_rate,
                    ur.created_at,
                    ur.reason,
                    s.name as supplier_name,
                    c.name as client_name,
                    m.name as account_name,
                    u.name as processed_by_name
                    FROM umrah_refunds ur
                    LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                    LEFT JOIN suppliers s ON ub.supplier = s.id
                    LEFT JOIN clients c ON ub.sold_to = c.id
                    LEFT JOIN main_account m ON ub.paid_to = m.id
                    LEFT JOIN users u ON ur.processed_by = u.id
                    WHERE ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = [
                    'Passport Number',
                    'Pilgrim Name',
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
                            WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
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
                            WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
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
                            WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id
                            
                            ORDER BY COALESCE(parent_id, id), record_type";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id];
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
                            WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
                            GROUP BY rt.id";
                        $params = [$startDate, $endDate, $tenant_id];
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
                            WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id";
                        $params = [$startDate, $endDate, $tenant_id];
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
                            WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
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
                            WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
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
                            WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id
                            
                            ORDER BY COALESCE(parent_id, id), record_type";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id];
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
                            WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
                            GROUP BY tb.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
                            GROUP BY rt.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?
                            GROUP BY v.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            u.name,
                            u.passport_number,
                            u.dob,
                            u.flight_date,
                            u.return_date,
                            u.duration,
                            u.room_type,
                            u.currency,
                            u.price, u.sold_price, u.profit,
                            u.received_bank_payment, u.bank_receipt_number,
                            u.paid, u.due, f.tazmin, u.remarks, f.visa_status,
                            f.head_of_family,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name,
                            u.created_at,
                            f.tazmin,
                            u.remarks,
                            f.contact,
                            ur.refund_type as refund_status,
                            f.visa_status
                            FROM umrah_bookings u
                            LEFT JOIN families f ON u.family_id = f.family_id
                            LEFT JOIN suppliers s ON u.supplier = s.id
                            LEFT JOIN clients c ON u.sold_to = c.id
                            LEFT JOIN main_account m ON u.paid_to = m.id
                            LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                            WHERE u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?"
                            . ($umrahFamilyType === 'specific' && $specificFamily ? " AND u.family_id = ?" : "") .
                            " ORDER BY u.entry_date DESC";
                        $params = [$startDate, $endDate, $tenant_id];
                        if ($umrahFamilyType === 'specific' && $specificFamily) {
                            $params[] = $specificFamily;
                        }
                        // Base headers (shown to everyone)
                        $headers = [
                            'Head of Family',
                            'Name', 
                            'Passport Number', 
                            'Date of Birth',
                            'Phone',
                            'Created At',
                            'Flight Date',
                            'Return Date',
                            'Duration',
                            'Room Type',
                            'Sold Price',   // ✅ visible for everyone
                            'Bank Payment',
                            'Bank Receipt',
                            'Paid',
                            'Due',
                            'Currency',
                            'Tazmin',
                            'Client',
                            'Account',
                            'Visa Status',
                            'Remarks',
                            'Refund Status'
                        ];

                        // Add sensitive headers only if admin
                        if ($user_role === 'admin') {
                            // Arrange headers as Price, Sold Price, Profit
                            $insertIndex = array_search('Sold Price', $headers);
                            if ($insertIndex !== false) {
                                array_splice($headers, $insertIndex, 0, ['Price']);  // Insert Price before Sold Price
                                $newIndex = array_search('Sold Price', $headers);
                                array_splice($headers, $newIndex + 1, 0, ['Profit']);  // Insert Profit after Sold Price
                            }
        
                            // Add Supplier at the end
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
                            WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
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
                            WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
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
                                WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ?
                                GROUP BY h.id";
                            $params = [$startDate, $endDate, $tenant_id];
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
                            ur.refund_type,
                            ur.refund_amount,
                            ur.currency,
                            ur.exchange_rate,
                            ur.created_at,
                            ur.reason,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name,
                            u.name as processed_by_name
                            FROM umrah_refunds ur
                            LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                            LEFT JOIN suppliers s ON ub.supplier = s.id
                            LEFT JOIN clients c ON ub.sold_to = c.id
                            LEFT JOIN main_account m ON ub.paid_to = m.id
                            LEFT JOIN users u ON ur.processed_by = u.id
                            WHERE ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        $headers = [
                            'Passport Number',
                            'Pilgrim Name',
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
                            WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
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
                            WHERE rt.sold_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
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
                            WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id
                            
                            ORDER BY COALESCE(parent_id, id), record_type";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id];
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
                            WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
                            GROUP BY tb.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE rt.sold_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
                            GROUP BY rt.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?
                            GROUP BY v.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            u.name, u.passport_number, u.dob,
                            u.entry_date, u.flight_date, u.return_date,
                            u.duration, u.room_type,
                            u.price, u.sold_price, u.profit,
                            u.received_bank_payment, u.bank_receipt_number,
                            u.paid, u.due, f.tazmin, u.remarks, f.visa_status,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            ur.refund_type as refund_status,
                            m.name as paid_to_name
                            FROM umrah_bookings u
                            LEFT JOIN suppliers s ON u.supplier = s.id
                            LEFT JOIN clients c ON u.sold_to = c.id
                            LEFT JOIN main_account m ON u.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON u.booking_id = mat.reference_id and mat.transaction_of = 'umrah'
                            Left join families f on u.family_id = f.family_id
                            LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                            WHERE u.sold_to = ? AND u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?"
                            . ($umrahFamilyType === 'specific' && $specificFamily ? " AND u.family_id = ?" : "") .
                            " GROUP BY u.booking_id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        if ($umrahFamilyType === 'specific' && $specificFamily) {
                            $params[] = $specificFamily;
                        }
                        // Base headers (shown to everyone)
                        $headers = [
                            'Head of Family',
                            'Name', 
                            'Passport Number', 
                            'Date of Birth',
                            'Phone',
                            'Created At',
                            'Flight Date',
                            'Return Date',
                            'Duration',
                            'Room Type',
                            'Sold Price',   // ✅ visible for everyone
                            'Bank Payment',
                            'Bank Receipt',
                            'Paid',
                            'Due',
                            'Currency',
                            'Tazmin',
                            'Client',
                            'Account',
                            'Visa Status',
                            'Remarks',
                            'Refund Status'
                        ];

                        // Add sensitive headers only if admin
                        if ($user_role === 'admin') {
                            // Insert Price + Profit right before Sold Price
                            $insertIndex = array_search('Sold Price', $headers);
                            if ($insertIndex !== false) {
                                array_splice($headers, $insertIndex, 0, ['Price', 'Profit']);
                            }

                            // Add Supplier at the end
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
                            WHERE t.sold_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE va.sold_to = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                                WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ?
                                GROUP BY h.id";
                            $params = [$startDate, $endDate, $tenant_id];
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
                            ur.refund_type,
                            ur.refund_amount,
                            ur.currency,
                            ur.exchange_rate,
                            ur.created_at,
                            ur.reason
                            FROM umrah_refunds ur
                            LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                            WHERE ub.sold_to = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = [
                            'Passport Number',
                            'Pilgrim Name',
                            'Refund Type',
                            'Refund Amount',
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
                            WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
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
                            WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
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
                            WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id
                            
                            ORDER BY COALESCE(parent_id, id), record_type";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id, $entity, $startDate, $endDate, $tenant_id];
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
                            WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?
                            GROUP BY tb.id";
                        $params = [$startDate, $endDate, $tenant_id];
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
                            WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?
                            GROUP BY rt.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?
                            GROUP BY dc.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?
                            GROUP BY v.id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            u.name, u.passport_number, u.dob,
                            u.entry_date, u.flight_date, u.return_date,
                            u.duration, u.room_type,
                            u.price, u.sold_price, u.profit,
                            u.received_bank_payment, u.bank_receipt_number,
                            u.paid, u.due, f.tazmin, u.remarks, f.visa_status,
                            s.name as supplier_name,
                            c.name as sold_to_name,
                            ur.refund_type as refund_status,
                            m.name as paid_to_name
                            FROM umrah_bookings u
                            LEFT JOIN suppliers s ON u.supplier = s.id
                            LEFT JOIN  families f on u.family_id = f.family_id
                            LEFT JOIN clients c ON u.sold_to = c.id
                            LEFT JOIN main_account m ON u.paid_to = m.id
                            LEFT JOIN main_account_transactions mat ON u.booking_id = mat.reference_id and mat.transaction_of = 'umrah'
                            LEFT JOIN umrah_refunds ur ON u.booking_id = ur.booking_id
                            WHERE u.sold_to = ? AND u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?
                            GROUP BY u.booking_id";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        // Base headers (shown to everyone)
                        $headers = [
                            'Head of Family',
                            'Name', 
                            'Passport Number', 
                            'Date of Birth',
                            'Phone',
                            'Created At',
                            'Flight Date',
                            'Return Date',
                            'Duration',
                            'Room Type',
                            'Sold Price',   // ✅ visible for everyone
                            'Bank Payment',
                            'Bank Receipt',
                            'Paid',
                            'Due',
                            'Currency',
                            'Tazmin',
                            'Client',
                            'Account',
                            'Visa Status',
                            'Remarks',
                            'Refund Status'
                        ];

                        // Add sensitive headers only if admin
                        if ($user_role === 'admin') {
                            // Insert Price + Profit right before Sold Price
                            $insertIndex = array_search('Sold Price', $headers);
                            if ($insertIndex !== false) {
                                array_splice($headers, $insertIndex, 0, ['Price', 'Profit']);
                            }

                            // Add Supplier at the end
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
                            WHERE e.main_account_id = ? AND e.date BETWEEN ? AND ?"
                            . ($expenseCategory && $expenseCategory !== 'all' ? " AND e.category_id = ?" : "") .
                            " ORDER BY e.date DESC";
                        $params = [$entity, $startDate, $endDate];
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
                            WHERE t.paid_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
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
                            WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
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
                                WHERE h.created_at BETWEEN ? AND ? AND h.tenant_id = ?
                                GROUP BY h.id";
                            $params = [$startDate, $endDate, $tenant_id];
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
                            ur.refund_type,
                            ur.refund_amount,
                            ur.currency,
                            ur.exchange_rate,
                            ur.created_at,
                            ur.reason,
                            s.name as supplier_name,
                            c.name as client_name,
                            m.name as account_name,
                            u.name as processed_by_name
                            FROM umrah_refunds ur
                            LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                            LEFT JOIN suppliers s ON ub.supplier = s.id
                            LEFT JOIN clients c ON ub.sold_to = c.id
                            LEFT JOIN main_account m ON ub.paid_to = m.id
                            LEFT JOIN users u ON ur.processed_by = u.id
                            WHERE ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        $headers = [
                            'Passport Number',
                            'Pilgrim Name',
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
                }
                break;
            
        }
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        // Use mPDF with a simpler approach similar to Word/Excel
        require_once('../vendor/autoload.php');
        
        // Simple configuration - let the system handle fonts like Word/Excel do
        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'default_font_size' => 10,
            'debug' => true
        ];
        
        try {
            // Create mPDF instance
            $pdf = new \Mpdf\Mpdf($mpdfConfig);
            
            // Create HTML content
            $html = '
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 10pt;
                    }
                    .report-title { 
                        text-align: center; 
                        font-size: 16pt; 
                        font-weight: bold; 
                        margin-bottom: 10px; 
                    }
                    .date-range { 
                        text-align: center; 
                        font-size: 12pt; 
                        margin-bottom: 20px; 
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                    }
                    th { 
                        background-color: #f2f2f2; 
                        padding: 8px; 
                        text-align: left; 
                        font-weight: bold; 
                        font-size: 10pt; 
                    }
                    td { 
                        padding: 6px; 
                        border: 1px solid #ddd; 
                        font-size: 10pt; 
                    }
                    tr.refund { 
                        background-color: #ffcccc; 
                    }
                    tr.date-change { 
                        background-color: #ffffcc; 
                    }
                    tr.umrah-refunded {
                        background-color: #ffcccc;
                    }
                    .indented { 
                        padding-left: 20px; 
                    }
                    tr.total-row { 
                        background-color: #f2f2f2; 
                        font-weight: bold; 
                    }
                    tr.total-row td { 
                        border-top: 2px solid #888; 
                    }
                </style>
            </head>
            <body>
                <div class="report-title">' . $reportTitle . '</div>
                <div class="date-range">' . $dateRange . '</div>
                <table border="1">
                    <thead>
                        <tr>';
            
            // Add headers
            foreach ($headers as $header) {
                $html .= '<th>' . $header . '</th>';
            }
            
            $html .= '</tr></thead><tbody>';
            
            // Add data rows - just like in Excel and Word exports
            foreach ($data as $rowData) {
                $rowClass = '';
                $isChild = false;
                if (isset($rowData['record_type']) && $rowData['record_type'] !== 'normal') {
                    $rowClass = $rowData['record_type'] === 'refund' ? 'refund' : 'date-change';
                    $isChild = true;
                }
                
                // Determine row class for umrah records
                if ($reportCategory === 'umrah') {
                    if (isset($rowData['refund_status']) && !empty($rowData['refund_status'])) {
                        $rowClass = 'umrah-refunded';
                    }
                }
                
                $html .= '<tr class="' . $rowClass . '">';
                
                foreach ($headers as $headerIdx => $header) {
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
                        case 'Base':
                            $value = isset($rowData['base']) ? $rowData['base'] : '';
                            break;
                        case 'Refund Amount':
                            $value = isset($rowData['refund_amount']) ? number_format($rowData['refund_amount'], 2) : 
                                    (isset($rowData['refund_to_passenger']) ? number_format($rowData['refund_to_passenger'], 2) : '0.00');
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
                                $value = isset($rowData['contact']) ? $rowData['contact'] : '';
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
                    
                    // Add indentation for child records in the first column
                    if ($isChild && $headerIdx === 0) {
                        $html .= '<td class="indented">' . $value . '</td>';
                    } else {
                        $html .= '<td>' . $value . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            
            // Add total rows for expense reports
            if ($reportCategory === 'expense' && !empty($expenseTotals)) {
                foreach ($expenseTotals as $currency => $total) {
                    $html .= '<tr class="total-row">';

                    // Add empty cells until we reach the Amount column
                    $amountColumnIndex = array_search('Amount', $headers);
                    for ($i = 0; $i < $amountColumnIndex; $i++) {
                        if ($i === 0) {
                            $html .= '<td><strong>TOTAL</strong></td>';
                        } else {
                            $html .= '<td></td>';
                        }
                    }

                    // Add the total amount
                    $html .= '<td><strong>' . number_format($total, 2) . '</strong></td>';

                    // Add the currency column
                    $html .= '<td><strong>' . $currency . '</strong></td>';

                    // Fill the remaining columns
                    for ($i = $amountColumnIndex + 2; $i < count($headers); $i++) {
                        $html .= '<td></td>';
                    }

                    $html .= '</tr>';
                }
            }

            // Add total rows for umrah reports
            if ($reportCategory === 'umrah' && !empty($umrahTotals) && $user_role === 'admin') {
                // Total Price row
                $html .= '<tr class="total-row">';
                foreach ($headers as $index => $header) {
                    if ($index === 0) {
                        $html .= '<td><strong>TOTAL PRICE</strong></td>';
                    } elseif ($header === 'Price') {
                        $html .= '<td><strong>' . number_format($umrahTotals['price'], 2) . '</strong></td>';
                    } else {
                        $html .= '<td></td>';
                    }
                }
                $html .= '</tr>';

                // Total Sold Price row
                $html .= '<tr class="total-row">';
                foreach ($headers as $index => $header) {
                    if ($index === 0) {
                        $html .= '<td><strong>TOTAL SOLD PRICE</strong></td>';
                    } elseif ($header === 'Sold Price') {
                        $html .= '<td><strong>' . number_format($umrahTotals['sold_price'], 2) . '</strong></td>';
                    } else {
                        $html .= '<td></td>';
                    }
                }
                $html .= '</tr>';

                // Total Profit row
                $html .= '<tr class="total-row">';
                foreach ($headers as $index => $header) {
                    if ($index === 0) {
                        $html .= '<td><strong>TOTAL PROFIT</strong></td>';
                    } elseif ($header === 'Profit') {
                        $html .= '<td><strong>' . number_format($umrahTotals['profit'], 2) . '</strong></td>';
                    } else {
                        $html .= '<td></td>';
                    }
                }
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></body></html>';
            
            // Write HTML to PDF
            $pdf->WriteHTML($html);
            
            // Output PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="report.pdf"');
            echo $pdf->Output('report.pdf', \Mpdf\Output\Destination::STRING_RETURN);
            
        } catch (\Exception $e) {
            // Log any errors for debugging
            error_log('PDF Generation Error: ' . $e->getMessage());
            echo 'Error generating PDF: ' . $e->getMessage();
        }
    } 
    elseif ($format === 'word') {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Set default font
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);
        
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
                            $value = isset($rowData['contact']) ? $rowData['contact'] : '';
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
                
                // Add indentation for child records in the first column
                if (isset($rowData['record_type']) && $rowData['record_type'] !== 'normal' && $header === $headers[0]) {
                    $value = '    ' . $value;
                }
                
                $cell = $table->addCell(null, $rowStyle);
                $cell->addText($value);
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
                            $value = isset($rowData['contact']) ? $rowData['contact'] : '';
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
