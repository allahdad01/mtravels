<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
// Validate endDate
$endDate = isset($_POST['endDate']) ? DbSecurity::validateInput($_POST['endDate'], 'date') : null;

// Validate startDate
$startDate = isset($_POST['startDate']) ? DbSecurity::validateInput($_POST['startDate'], 'date') : null;

// Validate reportCategory
$reportCategory = isset($_POST['reportCategory']) ? DbSecurity::validateInput($_POST['reportCategory'], 'string', ['maxlength' => 255]) : null;

// Validate entity
$entity = isset($_POST['entity']) ? DbSecurity::validateInput($_POST['entity'], 'string', ['maxlength' => 255]) : null;

// Validate reportType
$reportType = isset($_POST['reportType']) ? DbSecurity::validateInput($_POST['reportType'], 'string', ['maxlength' => 255]) : null;

// Validate umrahFamilyType
$umrahFamilyType = isset($_POST['umrahFamilyType']) ? DbSecurity::validateInput($_POST['umrahFamilyType'], 'string', ['maxlength' => 255]) : null;

// Validate specificFamily
$specificFamily = isset($_POST['specificFamily']) ? DbSecurity::validateInput($_POST['specificFamily'], 'string', ['maxlength' => 255]) : null;

$reportType = $_POST['reportType'];
$entity = isset($_POST['entity']) ? $_POST['entity'] : '';
$reportCategory = $_POST['reportCategory'];
$startDate = $_POST['startDate'];
$endDate = $_POST['endDate'];

try {
    $query = "";
    $params = [];
    $headers = [];
    
    // Handle general report type that doesn't specify an entity
    if ($reportType === 'general') {
        switch($reportCategory) {
            case 'ticket':
                $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, 
                         tb.status, tb.currency, c.name as client_name, s.name as supplier_name 
                         FROM ticket_bookings tb 
                         LEFT JOIN clients c ON tb.sold_to = c.id
                         LEFT JOIN suppliers s ON tb.supplier = s.id
                         WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;
                
            case 'ticket_reservation':
                $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, 
                         tb.status, tb.currency, c.name as client_name, s.name as supplier_name 
                         FROM ticket_reservations tb 
                         LEFT JOIN clients c ON tb.sold_to = c.id
                         LEFT JOIN suppliers s ON tb.supplier = s.id
                         WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'refund_ticket':
                $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, 
                         rt.refund_to_passenger as total_amount, 'Refunded' as status, rt.currency,
                         c.name as client_name, s.name as supplier_name 
                         FROM refunded_tickets rt 
                         LEFT JOIN clients c ON rt.sold_to = c.id
                         LEFT JOIN suppliers s ON s.id = (SELECT supplier_id FROM supplier_transactions WHERE reference_id = rt.id AND transaction_of = 'ticket_refund' LIMIT 1)
                         WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'date_change_ticket':
                $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date, 
                         dc.service_penalty + dc.supplier_penalty as total_amount, 
                         'Date Changed' as status, dc.currency,
                         c.name as client_name, s.name as supplier_name 
                         FROM date_change_tickets dc 
                         LEFT JOIN clients c ON dc.sold_to = c.id
                         LEFT JOIN suppliers s ON s.id = (SELECT supplier_id FROM supplier_transactions WHERE reference_id = dc.id AND transaction_of = 'date_change' LIMIT 1)
                         WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'visa':
                $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                         v.receive_date as issue_date, v.sold as total_amount, v.status, v.currency,
                         c.name as client_name, s.name as supplier_name 
                         FROM visa_applications v 
                         LEFT JOIN clients c ON v.sold_to = c.id
                         LEFT JOIN suppliers s ON v.supplier = s.id
                         WHERE v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'umrah':
                $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                          u.entry_date as issue_date, u.sold_price as total_amount,
                          u.duration as status, u.currency,
                          c.name as client_name, s.name as supplier_name
                          FROM umrah_bookings u
                          LEFT JOIN clients c ON u.sold_to = c.id
                          LEFT JOIN suppliers s ON u.supplier = s.id
                          WHERE u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];

                // Add family filter if specific family is selected
                if ($umrahFamilyType === 'specific' && $specificFamily) {
                    $query .= " AND u.family_id = ?";
                    $params[] = $specificFamily;
                }

                $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration', 'Currency', 'Client', 'Supplier'];
                break;
                
            case 'hotel':
                $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name, 
                         h.issue_date, h.sold_amount as total_amount, 
                         CONCAT(h.check_in_date, ' to ', h.check_out_date) as status, h.currency,
                         c.name as client_name, s.name as supplier_name 
                         FROM hotel_bookings h 
                         LEFT JOIN clients c ON h.sold_to = c.id
                         LEFT JOIN suppliers s ON h.supplier_id = s.id
                         WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['Order ID', 'Guest Name', 'Issue Date', 'Amount', 'Stay Period', 'Currency', 'Client', 'Supplier'];
                break;
                
            case 'hotel_refund':
                $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                         hr.created_at as issue_date, hr.refund_amount as total_amount, 
                         hr.refund_type as status, hr.currency, c.name as client_name, 
                         s.name as supplier_name 
                         FROM hotel_refunds hr
                         INNER JOIN hotel_bookings hb ON hr.booking_id = hb.id
                         LEFT JOIN clients c ON hb.sold_to = c.id
                         LEFT JOIN suppliers s ON hb.supplier_id = s.id
                         WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ?
                         ORDER BY hr.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                break;
                
            case 'expense':
                $query = "SELECT e.id as pnr, e.description as passenger_name, 
                         e.date as issue_date, e.amount as total_amount, 
                         ec.name as status, e.currency,
                         m.name as account_name
                         FROM expenses e 
                         LEFT JOIN expense_categories ec ON e.category_id = ec.id
                         LEFT JOIN main_account m ON e.main_account_id = m.id
                         WHERE e.date BETWEEN ? AND ? AND e.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['ID', 'Description', 'Date', 'Amount', 'Category', 'Currency', 'Account'];
                break;
                
            case 'creditor':
                $query = "SELECT 
                        c.id as pnr, 
                        c.name as creditor_name,
                        c.phone as phone,
                        c.email as email,
                        c.address as address,
                        c.balance as balance,
                        c.currency,
                        c.status as status,
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
                        d.id as pnr, 
                        d.name as debtor_name,
                        d.phone as phone,
                        d.email as email,
                        d.address as address,
                        d.balance as balance,
                        d.currency,
                        d.status as status,
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
                         ap.id as pnr, 
                         ap.payment_type as payment_type, 
                         ap.created_at as issue_date, 
                         ap.sold_amount as total_amount,
                         ap.base_amount as base_amount,
                         ap.profit as profit,
                         ap.description as description, 
                         ap.currency,
                         m.name as account_name,
                         u.name as created_by,
                         'Paid' as paid_status
                         FROM additional_payments ap 
                         LEFT JOIN main_account m ON ap.main_account_id = m.id
                         LEFT JOIN users u ON ap.created_by = u.id
                         WHERE ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ?
                         ORDER BY ap.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['ID', 'Payment Type', 'Date', 'Amount', 'Base Amount', 'Profit', 'Description', 'Currency', 'Account', 'Created By', 'Status'];
                break;

            case 'ticket_weight':
                $query = "SELECT t.pnr, t.passenger_name, tw.created_at as issue_date, 
                         tw.sold_price as total_amount, CONCAT(tw.weight, ' kg') as status, 
                         t.currency, c.name as client_name, s.name as supplier_name 
                         FROM ticket_weights tw 
                         LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                         LEFT JOIN clients c ON t.sold_to = c.id
                         LEFT JOIN suppliers s ON t.supplier = s.id
                         WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['PNR', 'Passenger Name', 'Date', 'Amount', 'Weight', 'Currency', 'Client', 'Supplier'];
                break;

            case 'visa_refund':
                $query = "SELECT va.passport_number as pnr, va.applicant_name as passenger_name, 
                         vr.refund_date as issue_date, vr.refund_amount as total_amount, 
                         vr.refund_type as status, vr.currency, c.name as client_name, 
                         s.name as supplier_name 
                         FROM visa_refunds vr
                         LEFT JOIN visa_applications va ON vr.visa_id = va.id
                         LEFT JOIN clients c ON va.sold_to = c.id
                         LEFT JOIN suppliers s ON va.supplier = s.id
                         WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ?";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['Passport Number', 'Applicant Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                break;

            case 'hotel_refund':
                $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                         hr.created_at as issue_date, hr.refund_amount as total_amount, 
                         hr.refund_type as status, hr.currency, c.name as client_name, 
                         s.name as supplier_name 
                         FROM hotel_refunds hr
                         INNER JOIN hotel_bookings hb ON hr.booking_id = hb.id
                         LEFT JOIN clients c ON hb.sold_to = c.id
                         LEFT JOIN suppliers s ON hb.supplier_id = s.id
                         WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ?
                         ORDER BY hr.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                break;

            case 'umrah_refund':
                $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name, 
                         ur.created_at as issue_date, ur.refund_amount as total_amount, 
                         ur.refund_type as status, ur.currency, c.name as client_name, 
                         s.name as supplier_name 
                         FROM umrah_refunds ur
                         INNER JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                         LEFT JOIN clients c ON ub.sold_to = c.id
                         LEFT JOIN suppliers s ON ub.supplier = s.id
                         WHERE ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ?
                         ORDER BY ur.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id];
                $headers = ['Passport Number', 'Pilgrim Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                break;
        }
    } else {
        switch($reportType) {
            case 'ticket':
                // Handle different ticket categories
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                                 FROM ticket_bookings tb 
                                 WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                                 FROM ticket_reservations tb 
                                 WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status 
                                 FROM refunded_tickets rt 
                                 WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status 
                                 FROM date_change_tickets dc 
                                 WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name, 
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status 
                                FROM hotel_bookings h 
                                WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        break;
                        
                    case 'hotel_refund':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name, 
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Refund' as status 
                                FROM hotel_refunds h 
                                WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?";
                        $params = [$startDate, $endDate, $tenant_id];
                        break;
                }
                $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status'];
                break;
                
            case 'supplier':
                // Handle supplier with different categories
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name 
                                 FROM ticket_bookings tb 
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name 
                                 FROM ticket_reservations tb 
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM refunded_tickets rt 
                                 LEFT JOIN clients c ON rt.sold_to = c.id
                                 LEFT JOIN suppliers s ON rt.supplier = s.id
                                 WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM date_change_tickets dc 
                                 LEFT JOIN clients c ON dc.sold_to = c.id
                                 LEFT JOIN suppliers s ON dc.supplier = s.id
                                 WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'visa':
                        $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                                 v.receive_date as issue_date, v.sold as total_amount, v.status 
                                 FROM visa_applications v 
                                 WHERE v.supplier = ? AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                        break;

                    case 'umrah':
                        $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                                  u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status
                                  FROM umrah_bookings u
                                  WHERE u.supplier = ? AND u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];

                        // Add family filter if specific family is selected
                        if ($umrahFamilyType === 'specific' && $specificFamily) {
                            $query .= " AND u.family_id = ?";
                            $params[] = $specificFamily;
                        }

                        $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name, 
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status 
                                FROM hotel_bookings h 
                                WHERE h.supplier_id = ? AND h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Order ID', 'Guest Name', 'Issue Date', 'Amount', 'Status'];
                        break;
                        
                        case 'hotel_refund':
                            $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                                     hr.created_at as issue_date, hr.refund_amount as total_amount, 
                                     hr.refund_type as status, hr.currency, c.name as client_name, 
                                     s.name as supplier_name 
                                     FROM hotel_refunds hr
                                     INNER JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                     LEFT JOIN clients c ON hb.sold_to = c.id
                                     LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                     WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ?
                                     ORDER BY hr.created_at DESC";
                            $params = [$startDate, $endDate, $tenant_id];
                            $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                            break;

                    case 'ticket_weight':
                        $query = "SELECT t.pnr, t.passenger_name, tw.created_at as issue_date, 
                                 tw.sold_price as total_amount, CONCAT(tw.weight, ' kg') as status 
                                 FROM ticket_weights tw 
                                 LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                                 WHERE t.supplier = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Date', 'Amount', 'Weight'];
                        break;

                    case 'visa_refund':
                        $query = "SELECT va.passport_number as pnr, va.applicant_name as passenger_name, 
                                 vr.refund_date as issue_date, vr.refund_amount as total_amount, 
                                 vr.refund_type as status 
                                 FROM visa_refunds vr
                                 LEFT JOIN visa_applications va ON vr.visa_id = va.id
                                 WHERE va.supplier = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'hotel_refund':
                        $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                                 hr.created_at as issue_date, hr.refund_amount as total_amount, 
                                 hr.refund_type as status 
                                 FROM hotel_refunds hr
                                 LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                 WHERE hb.supplier_id = ? AND hr.created_at BETWEEN ? AND ? AND hb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'umrah_refund':
                        $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name, 
                                 ur.created_at as issue_date, ur.refund_amount as total_amount, 
                                 ur.refund_type as status 
                                 FROM umrah_refunds ur
                                 LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                                 WHERE ub.supplier = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Pilgrim Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;
                }
                break;

            case 'client':
                // Handle client with different categories
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name 
                                 FROM ticket_bookings tb 
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM ticket_reservations tb 
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM refunded_tickets rt 
                                 LEFT JOIN clients c ON rt.sold_to = c.id
                                 LEFT JOIN suppliers s ON rt.supplier = s.id
                                 WHERE rt.sold_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM date_change_tickets dc 
                                 LEFT JOIN clients c ON dc.sold_to = c.id
                                 LEFT JOIN suppliers s ON dc.supplier = s.id
                                 WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'visa':
                        $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                                 v.receive_date as issue_date, v.sold as total_amount, v.status 
                                 FROM visa_applications v 
                                 WHERE v.sold_to = ? AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                        break;

                    case 'umrah':
                        $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                                  u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status
                                  FROM umrah_bookings u
                                  WHERE u.sold_to = ? AND u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];

                        // Add family filter if specific family is selected
                        if ($umrahFamilyType === 'specific' && $specificFamily) {
                            $query .= " AND u.family_id = ?";
                            $params[] = $specificFamily;
                        }

                        $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name, 
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status 
                                FROM hotel_bookings h 
                                WHERE h.sold_to = ? AND h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Order ID', 'Guest Name', 'Issue Date', 'Amount', 'Status'];
                        break;
                        
                        case 'hotel_refund':
                            $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                                     hr.created_at as issue_date, hr.refund_amount as total_amount, 
                                     hr.refund_type as status, hr.currency, c.name as client_name, 
                                     s.name as supplier_name 
                                     FROM hotel_refunds hr
                                     INNER JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                     LEFT JOIN clients c ON hb.sold_to = c.id
                                     LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                     WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ?
                                     ORDER BY hr.created_at DESC";
                            $params = [$startDate, $endDate, $tenant_id];
                            $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                            break;

                    case 'ticket_weight':
                        $query = "SELECT t.pnr, t.passenger_name, tw.created_at as issue_date, 
                                 tw.sold_price as total_amount, CONCAT(tw.weight, ' kg') as status 
                                 FROM ticket_weights tw 
                                 LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                                 WHERE t.sold_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Date', 'Amount', 'Weight'];
                        break;

                    case 'visa_refund':
                        $query = "SELECT va.passport_number as pnr, va.applicant_name as passenger_name, 
                                 vr.refund_date as issue_date, vr.refund_amount as total_amount, 
                                 vr.refund_type as status 
                                 FROM visa_refunds vr
                                 LEFT JOIN visa_applications va ON vr.visa_id = va.id
                                 WHERE va.sold_to = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'hotel_refund':
                        $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                                 hr.created_at as issue_date, hr.refund_amount as total_amount, 
                                 hr.refund_type as status 
                                 FROM hotel_refunds hr
                                 LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                 WHERE hb.sold_to = ? AND hr.created_at BETWEEN ? AND ? AND hb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'umrah_refund':
                        $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name, 
                                 ur.created_at as issue_date, ur.refund_amount as total_amount, 
                                 ur.refund_type as status 
                                 FROM umrah_refunds ur
                                 LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                                 WHERE ub.sold_to = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Pilgrim Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;
                }
                break;

            case 'main_account':
                // Handle main account with different categories
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name 
                                 FROM ticket_bookings tb 
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 LEFT JOIN main_account m ON tb.paid_to = m.id
                                WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name 
                                 FROM ticket_reservations tb 
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 LEFT JOIN main_account m ON tb.paid_to = m.id
                                 WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name
                                 FROM refunded_tickets rt 
                                 LEFT JOIN clients c ON rt.sold_to = c.id
                                 LEFT JOIN suppliers s ON rt.supplier = s.id
                                 LEFT JOIN main_account m ON rt.paid_to = m.id
                                 WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name
                                 FROM date_change_tickets dc 
                                 LEFT JOIN clients c ON dc.sold_to = c.id
                                 LEFT JOIN suppliers s ON dc.supplier = s.id
                                 LEFT JOIN main_account m ON dc.paid_to = m.id
                                 WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'visa':
                        $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                                 v.receive_date as issue_date, v.sold as total_amount, v.status 
                                 FROM visa_applications v 
                                 WHERE v.paid_to = ? AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                        break;

                    case 'umrah':
                        $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                                  u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status
                                  FROM umrah_bookings u
                                  WHERE u.paid_to = ? AND u.entry_date BETWEEN ? AND ? AND u.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];

                        // Add family filter if specific family is selected
                        if ($umrahFamilyType === 'specific' && $specificFamily) {
                            $query .= " AND u.family_id = ?";
                            $params[] = $specificFamily;
                        }

                        $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name, 
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status 
                                FROM hotel_bookings h 
                                WHERE h.paid_to = ? AND h.issue_date BETWEEN ? AND ? AND h.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Order ID', 'Guest Name', 'Issue Date', 'Amount', 'Status'];
                        break;
                        
                        case 'hotel_refund':
                            $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                                     hr.created_at as issue_date, hr.refund_amount as total_amount, 
                                     hr.refund_type as status, hr.currency, c.name as client_name, 
                                     s.name as supplier_name 
                                     FROM hotel_refunds hr
                                     INNER JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                     LEFT JOIN clients c ON hb.sold_to = c.id
                                     LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                     WHERE hb.paid_to = ? AND hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ?
                                     ORDER BY hr.created_at DESC";
                            $params = [$startDate, $endDate, $tenant_id];
                            $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                            break;
                        
                    case 'expense':
                        $query = "SELECT e.id as pnr, e.description as passenger_name, 
                                e.date as issue_date, e.amount as total_amount, 
                                ec.name as status, e.currency
                                FROM expenses e 
                                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                                WHERE e.main_account_id = ? AND e.date BETWEEN ? AND ? AND e.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['ID', 'Description', 'Date', 'Amount', 'Category', 'Currency'];
                        break;
                        
                    case 'additional_payment':
                        $query = "SELECT 
                                ap.id as pnr, 
                                ap.payment_type as payment_type, 
                                ap.created_at as issue_date, 
                                ap.sold_amount as total_amount, 
                                ap.base_amount as base_amount,
                                ap.profit as profit,
                                ap.description as description, 
                                ap.currency,
                                m.name as account_name,
                                u.name as created_by
                                FROM additional_payments ap 
                                LEFT JOIN main_account m ON ap.main_account_id = m.id
                                LEFT JOIN users u ON ap.created_by = u.id
                                WHERE ap.main_account_id = ? AND ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ?
                                ORDER BY ap.created_at DESC";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['ID', 'Payment Type', 'Date', 'Amount', 'Base Amount', 'Profit', 'Description', 'Currency', 'Account', 'Created By'];
                        break;

                    case 'ticket_weight':
                        $query = "SELECT t.pnr, t.passenger_name, tw.created_at as issue_date, 
                                 tw.sold_price as total_amount, CONCAT(tw.weight, ' kg') as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name 
                                 FROM ticket_weights tw 
                                 LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                                 LEFT JOIN clients c ON t.sold_to = c.id
                                 LEFT JOIN suppliers s ON t.supplier = s.id
                                 LEFT JOIN main_account m ON t.paid_to = m.id
                                 WHERE t.paid_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['PNR', 'Passenger Name', 'Date', 'Amount', 'Weight', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'visa_refund':
                        $query = "SELECT va.passport_number as pnr, va.applicant_name as passenger_name, 
                                 vr.refund_date as issue_date, vr.refund_amount as total_amount, 
                                 vr.refund_type as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name 
                                 FROM visa_refunds vr
                                 LEFT JOIN visa_applications va ON vr.visa_id = va.id
                                 LEFT JOIN clients c ON va.sold_to = c.id
                                 LEFT JOIN suppliers s ON va.supplier = s.id
                                 LEFT JOIN main_account m ON va.paid_to = m.id
                                 WHERE va.paid_to = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'hotel_refund':
                        $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name, 
                                 hr.created_at as issue_date, hr.refund_amount as total_amount, 
                                 hr.refund_type as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name 
                                 FROM hotel_refunds hr
                                 LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                 LEFT JOIN clients c ON hb.sold_to = c.id
                                 LEFT JOIN suppliers s ON hb.supplier_id = s.id
                                 LEFT JOIN main_account m ON hb.paid_to = m.id
                                 WHERE hb.paid_to = ? AND hr.created_at BETWEEN ? AND ? AND hb.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'umrah_refund':
                        $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name, 
                                 ur.created_at as issue_date, ur.refund_amount as total_amount, 
                                 ur.refund_type as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name 
                                 FROM umrah_refunds ur
                                 LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                                 LEFT JOIN clients c ON ub.sold_to = c.id
                                 LEFT JOIN suppliers s ON ub.supplier = s.id
                                 LEFT JOIN main_account m ON ub.paid_to = m.id
                                WHERE ub.paid_to = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id];
                        $headers = ['Passport Number', 'Pilgrim Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Client', 'Supplier', 'Account'];
                        break;
                }
                break;
        }
    }

    if (empty($query)) {
        throw new Exception("Invalid report type or category combination");
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug - log data count for creditor and debtor reports
    if ($reportCategory == 'creditor' || $reportCategory == 'debtor') {
        error_log("Report requested: " . $reportCategory);
        error_log("Records found: " . count($data));
        
        // Check if any records exist in the respective table
        $checkTable = ($reportCategory == 'creditor') ? 'creditors' : 'debtors';
        $checkQuery = "SELECT COUNT(*) as total FROM " . $checkTable;
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute();
        $totalRecords = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Total records in " . $checkTable . ": " . $totalRecords);
    }

    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}
?> 