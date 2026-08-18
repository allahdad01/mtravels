<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

// Umrah filter type (all, family, flight_date, return_date)
$umrahFilterType = isset($_POST['umrahFilterType']) ? DbSecurity::validateInput($_POST['umrahFilterType'], 'string', ['maxlength' => 255]) : '';
$umrahFlightDate = isset($_POST['umrahFlightDate']) ? DbSecurity::validateInput($_POST['umrahFlightDate'], 'date') : '';
$umrahReturnDate = isset($_POST['umrahReturnDate']) ? DbSecurity::validateInput($_POST['umrahReturnDate'], 'date') : '';

$reportType = $_POST['reportType'];
$entity = isset($_POST['entity']) ? $_POST['entity'] : '';
$reportCategory = $_POST['reportCategory'];
$startDate = $_POST['startDate'];
$endDate = $_POST['endDate'];

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
    $query = "";
    $params = [];
    $headers = [];
    $tables = [];
    $sections = [];
    
    // Handle general report type that doesn't specify an entity
    if ($reportType === 'general') {
        switch($reportCategory) {
            case 'ticket':
                $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount,
                         tb.status, tb.currency, c.name as client_name, s.name as supplier_name
                         FROM ticket_bookings tb
                         LEFT JOIN clients c ON tb.sold_to = c.id
                         LEFT JOIN suppliers s ON tb.supplier = s.id
                         WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;
                
            case 'ticket_reservation':
                $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount,
                         tb.status, tb.currency, c.name as client_name, s.name as supplier_name
                         FROM ticket_reservations tb
                         LEFT JOIN clients c ON tb.sold_to = c.id
                         LEFT JOIN suppliers s ON tb.supplier = s.id
                         WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'refund_ticket':
                $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date,
                         rt.refund_to_passenger as total_amount, 'Refunded' as status, rt.currency,
                         c.name as client_name, s.name as supplier_name
                         FROM refunded_tickets rt
                         LEFT JOIN clients c ON rt.sold_to = c.id
                         LEFT JOIN suppliers s ON s.id = (SELECT supplier_id FROM supplier_transactions WHERE reference_id = rt.id AND transaction_of = 'ticket_refund' LIMIT 1)
                         WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'visa':
                $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name,
                         v.receive_date as issue_date, v.sold as total_amount, v.status, v.currency,
                         c.name as client_name, s.name as supplier_name
                         FROM visa_applications v
                         LEFT JOIN clients c ON v.sold_to = c.id
                         LEFT JOIN suppliers s ON v.supplier = s.id
                         WHERE v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status', 'Currency', 'Client', 'Supplier'];
                break;

            case 'umrah':
                $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                           u.entry_date as issue_date, u.sold_price as total_amount,
                           u.duration as status, u.currency,
                           c.name as client_name
                           FROM umrah_bookings u
                           LEFT JOIN clients c ON u.sold_to = c.id
                           WHERE " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ?";
                $params = array_merge($umrahDateParams, [$tenant_id, $branch_id]);

                // Add umrah filter (family / flight date / return date)
                if (!empty($umrahFilterSql)) {
                    $query .= $umrahFilterSql;
                    $params = array_merge($params, $umrahFilterParams);
                }

                $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration', 'Currency', 'Client'];
                break;
                
            case 'hotel':
                $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name,
                         h.issue_date, h.sold_amount as total_amount,
                         CONCAT(h.check_in_date, ' to ', h.check_out_date) as status, h.currency,
                         c.name as client_name, s.name as supplier_name
                         FROM hotel_bookings h
                         LEFT JOIN clients c ON h.sold_to = c.id
                         LEFT JOIN suppliers s ON h.supplier_id = s.id
                         WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ? AND hr.branch_id = ?
                         ORDER BY hr.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         WHERE e.date BETWEEN ? AND ? AND e.tenant_id = ? AND e.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         AND payment_date BETWEEN ? AND ? AND branch_id = ?) as paid_amount,
                        (SELECT COALESCE(SUM(amount), 0)
                         FROM creditor_transactions
                         WHERE creditor_id = c.id
                         AND transaction_type = 'debit'
                         AND payment_date BETWEEN ? AND ? AND branch_id = ?) as received_amount
                        FROM creditors c
                        WHERE c.tenant_id = ? AND c.branch_id = ?
                        ORDER BY c.name ASC";
                $params = [$startDate, $endDate, $branch_id, $startDate, $endDate, $branch_id, $tenant_id, $branch_id];
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
                         AND payment_date BETWEEN ? AND ? AND branch_id = ?) as paid_amount,
                        (SELECT COALESCE(SUM(amount), 0)
                         FROM debtor_transactions
                         WHERE debtor_id = d.id
                         AND transaction_type = 'credit'
                         AND payment_date BETWEEN ? AND ? AND branch_id = ?) as received_amount
                        FROM debtors d
                        WHERE d.tenant_id = ? AND d.branch_id = ?
                        ORDER BY d.name ASC";
                $params = [$startDate, $endDate, $branch_id, $startDate, $endDate, $branch_id, $tenant_id, $branch_id];
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
                         WHERE ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ? AND ap.branch_id = ?
                         ORDER BY ap.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ? AND tw.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         WHERE vr.refund_date BETWEEN ? AND ? AND vr.tenant_id = ? AND vr.branch_id = ?";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                         WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ? AND hr.branch_id = ?
                         ORDER BY hr.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                break;

            case 'umrah_refund':
                $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name,
                         ur.created_at as issue_date, ur.refund_amount as total_amount,
                         ur.refund_type as status, ur.currency, c.name as client_name
                         FROM umrah_refunds ur
                         INNER JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                         LEFT JOIN clients c ON ub.sold_to = c.id
                         WHERE ur.created_at BETWEEN ? AND ? AND ur.tenant_id = ? AND ur.branch_id = ?
                         ORDER BY ur.created_at DESC";
                $params = [$startDate, $endDate, $tenant_id, $branch_id];
                $headers = ['Passport Number', 'Pilgrim Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client'];
                break;

            case 'general_summary':
                $headers = ['Date', 'Reference', 'Party', 'Details', 'Profit', 'Cash In', 'Cash Out', 'Currency'];
                $data = [];

                $itemQueries = [
                    'Ticket Sales' => "SELECT DATE(tb.issue_date) d, tb.pnr ref, c.name party, CONCAT(tb.airline, ' - ', tb.origin, '-', tb.destination) details, tb.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, tb.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale' AND mt.type = 'credit' LIMIT 1), tb.currency) cash_currency FROM ticket_bookings tb LEFT JOIN clients c ON tb.sold_to = c.id WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?",
                    'Date Changes' => "SELECT DATE(dc.created_at) d, dc.pnr ref, c.name party, CONCAT(dc.airline, ' - ', dc.origin, '-', dc.destination) details, dc.service_penalty profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = dc.id AND mt.transaction_of = 'date_change' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, dc.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = dc.id AND mt.transaction_of = 'date_change' AND mt.type = 'credit' LIMIT 1), dc.currency) cash_currency FROM date_change_tickets dc LEFT JOIN clients c ON dc.sold_to = c.id WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?",
                    'Ticket Reservations' => "SELECT DATE(tr.issue_date) d, tr.pnr ref, c.name party, CONCAT(tr.airline, ' - ', tr.origin, '-', tr.destination) details, tr.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, tr.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve' AND mt.type = 'credit' LIMIT 1), tr.currency) cash_currency FROM ticket_reservations tr LEFT JOIN clients c ON tr.sold_to = c.id WHERE tr.issue_date BETWEEN ? AND ? AND tr.tenant_id = ? AND tr.branch_id = ?",
                    'Ticket Weight' => "SELECT DATE(tw.created_at) d, t.pnr ref, c.name party, CONCAT(tw.weight, ' kg') details, tw.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = tw.id AND mt.transaction_of = 'weight' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, COALESCE(t.currency, 'USD') currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = tw.id AND mt.transaction_of = 'weight' AND mt.type = 'credit' LIMIT 1), COALESCE(t.currency, 'USD')) cash_currency FROM ticket_weights tw LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id LEFT JOIN clients c ON t.sold_to = c.id WHERE tw.created_at BETWEEN ? AND ? AND tw.tenant_id = ? AND tw.branch_id = ?",
                    'Hotel' => "SELECT DATE(h.issue_date) d, h.order_id ref, c.name party, CONCAT(h.title, ' ', h.first_name, ' ', h.last_name) details, h.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = h.id AND mt.transaction_of = 'hotel' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, h.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = h.id AND mt.transaction_of = 'hotel' AND mt.type = 'credit' LIMIT 1), h.currency) cash_currency FROM hotel_bookings h LEFT JOIN clients c ON h.sold_to = c.id WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?",
                    'Visa' => "SELECT DATE(v.receive_date) d, v.passport_number ref, c.name party, CONCAT(v.country, ' - ', v.visa_type) details, v.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = v.id AND mt.transaction_of = 'visa_sale' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, v.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = v.id AND mt.transaction_of = 'visa_sale' AND mt.type = 'credit' LIMIT 1), v.currency) cash_currency FROM visa_applications v LEFT JOIN clients c ON v.sold_to = c.id WHERE v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?",
                    'Umrah Bookings' => "SELECT DATE(u.entry_date) d, u.passport_number ref, c.name party, u.duration details, u.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt LEFT JOIN umrah_transactions ut ON mt.reference_id = ut.id WHERE mt.transaction_of = 'umrah_transaction' AND mt.type = 'credit' AND ut.umrah_booking_id = u.booking_id), 0) cash_in, 0 cash_out, u.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt LEFT JOIN umrah_transactions ut ON mt.reference_id = ut.id WHERE mt.transaction_of = 'umrah_transaction' AND mt.type = 'credit' AND ut.umrah_booking_id = u.booking_id LIMIT 1), u.currency) cash_currency FROM umrah_bookings u LEFT JOIN clients c ON u.sold_to = c.id WHERE u.entry_date BETWEEN ? AND ? AND u.tenant_id = ? AND u.branch_id = ?",
                    'Additional Payments' => "SELECT DATE(ap.created_at) d, ap.id ref, m.name party, ap.payment_type details, ap.profit profit, COALESCE((SELECT SUM(mt.amount) FROM main_account_transactions mt WHERE mt.reference_id = ap.id AND mt.transaction_of = 'additional_payment' AND mt.type = 'credit'), 0) cash_in, 0 cash_out, ap.currency currency, COALESCE((SELECT mt.currency FROM main_account_transactions mt WHERE mt.reference_id = ap.id AND mt.transaction_of = 'additional_payment' AND mt.type = 'credit' LIMIT 1), ap.currency) cash_currency FROM additional_payments ap LEFT JOIN main_account m ON ap.main_account_id = m.id WHERE ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ? AND ap.branch_id = ?",
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
                        }
                        if (empty($rows)) {
                            $rows[] = ['date' => '', 'reference' => '', 'party' => 'No records in selected period', 'details' => '', 'profit' => 0, 'cash_in' => 0, 'cash_out' => 0, 'currency' => 'N/A'];
                        }
                        $mini[] = ['title' => $label, 'rows' => $rows];
                    }
                    $sections[] = ['title' => $groupTitle, 'tables' => $mini];
                }
                $summaryTables = [];
                foreach ($totals as $currency => $t) {
                    $profit = isset($t['profit']) ? $t['profit'] : 0;
                    $cashIn = isset($t['cash_in']) ? $t['cash_in'] : 0;
                    $cashOut = isset($t['cash_out']) ? $t['cash_out'] : 0;
                    $summaryTables[] = ['title' => 'Summary - ' . $currency, 'rows' => [
                        ['party' => 'INCOME (PROFIT)', 'profit' => $profit],
                        ['party' => 'CASH RECEIVED', 'cash_in' => $cashIn],
                        ['party' => 'CASH PAID (EXPENSES)', 'cash_out' => $cashOut],
                        ['party' => 'NET CASH FLOW', 'cash_in' => $cashIn - $cashOut, 'bold' => true],
                        ['party' => 'NET PROFIT', 'profit' => $profit - $cashOut, 'bold' => true],
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
                // Handle different ticket categories
                switch($reportCategory) {
                    case 'ticket':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                                 FROM ticket_bookings tb 
                                 WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                                 FROM ticket_reservations tb 
                                 WHERE tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status
                                 FROM refunded_tickets rt
                                 WHERE rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status
                                 FROM date_change_tickets dc
                                 WHERE dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name,
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status
                                FROM hotel_bookings h
                                WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
                        break;
                        
                    case 'hotel_refund':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name,
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Refund' as status
                                FROM hotel_refunds h
                                WHERE h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?";
                        $params = [$startDate, $endDate, $tenant_id, $branch_id];
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
                                 WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM ticket_reservations tb
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 WHERE tb.supplier = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM refunded_tickets rt
                                 LEFT JOIN clients c ON rt.sold_to = c.id
                                 LEFT JOIN suppliers s ON rt.supplier = s.id
                                 WHERE rt.supplier = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM date_change_tickets dc
                                 LEFT JOIN clients c ON dc.sold_to = c.id
                                 LEFT JOIN suppliers s ON dc.supplier = s.id
                                 WHERE dc.supplier = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'visa':
                        $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name,
                                 v.receive_date as issue_date, v.sold as total_amount, v.status
                                 FROM visa_applications v
                                 WHERE v.supplier = ? AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                        break;

                    case 'umrah':
                        $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                                  u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status
                          FROM umrah_bookings u
                          INNER JOIN umrah_booking_services ubs ON u.booking_id = ubs.booking_id
                          LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                          WHERE COALESCE(uf.supplier_id, ubs.supplier_id) = ? AND " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ?";
                        $params = array_merge([$entity], $umrahDateParams, [$tenant_id, $branch_id]);

                        // Add umrah filter (family / flight date / return date)
                        if (!empty($umrahFilterSql)) {
                            $query .= $umrahFilterSql;
                            $params = array_merge($params, $umrahFilterParams);
                        }

                        $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name,
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status
                                FROM hotel_bookings h
                                WHERE h.supplier_id = ? AND h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                     WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ? AND hr.branch_id = ?
                                     ORDER BY hr.created_at DESC";
                            $params = [$startDate, $endDate, $tenant_id, $branch_id];
                            $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                            break;

                    case 'ticket_weight':
                        $query = "SELECT t.pnr, t.passenger_name, tw.created_at as issue_date,
                                 tw.sold_price as total_amount, CONCAT(tw.weight, ' kg') as status
                                 FROM ticket_weights tw
                                 LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                                 WHERE t.supplier = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ? AND t.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Date', 'Amount', 'Weight'];
                        break;

                    case 'visa_refund':
                        $query = "SELECT va.passport_number as pnr, va.applicant_name as passenger_name,
                                 vr.refund_date as issue_date, vr.refund_amount as total_amount,
                                 vr.refund_type as status
                                 FROM visa_refunds vr
                                 LEFT JOIN visa_applications va ON vr.visa_id = va.id
                                 WHERE va.supplier = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ? AND va.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'hotel_refund':
                        $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name,
                                 hr.created_at as issue_date, hr.refund_amount as total_amount,
                                 hr.refund_type as status
                                 FROM hotel_refunds hr
                                 LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                 WHERE hb.supplier_id = ? AND hr.created_at BETWEEN ? AND ? AND hb.tenant_id = ? AND hb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'umrah_refund':
                        $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name,
                                 ur.created_at as issue_date, ur.refund_amount as total_amount,
                                 ur.refund_type as status
                                 FROM umrah_refunds ur
                                 LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                                 INNER JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                                 LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                                 WHERE COALESCE(uf.supplier_id, ubs.supplier_id) = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ? AND ub.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM ticket_reservations tb
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM refunded_tickets rt
                                 LEFT JOIN clients c ON rt.sold_to = c.id
                                 LEFT JOIN suppliers s ON rt.supplier = s.id
                                 WHERE rt.sold_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status,
                                 c.name as client_name, s.name as supplier_name
                                 FROM date_change_tickets dc
                                 LEFT JOIN clients c ON dc.sold_to = c.id
                                 LEFT JOIN suppliers s ON dc.supplier = s.id
                                 WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Client', 'Supplier'];
                        break;

                    case 'visa':
                        $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name,
                                 v.receive_date as issue_date, v.sold as total_amount, v.status
                                 FROM visa_applications v
                                 WHERE v.sold_to = ? AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                        break;

                    case 'umrah':
                        $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                                  u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status
                          FROM umrah_bookings u
                          WHERE u.sold_to = ? AND " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ?";
                        $params = array_merge([$entity], $umrahDateParams, [$tenant_id, $branch_id]);

                        // Add umrah filter (family / flight date / return date)
                        if (!empty($umrahFilterSql)) {
                            $query .= $umrahFilterSql;
                            $params = array_merge($params, $umrahFilterParams);
                        }

                        $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name,
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status
                                FROM hotel_bookings h
                                WHERE h.sold_to = ? AND h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                     WHERE hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ? AND hr.branch_id = ?
                                     ORDER BY hr.created_at DESC";
                            $params = [$startDate, $endDate, $tenant_id, $branch_id];
                            $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                            break;

                    case 'ticket_weight':
                        $query = "SELECT t.pnr, t.passenger_name, tw.created_at as issue_date,
                                 tw.sold_price as total_amount, CONCAT(tw.weight, ' kg') as status
                                 FROM ticket_weights tw
                                 LEFT JOIN ticket_bookings t ON tw.ticket_id = t.id
                                 WHERE t.sold_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ? AND t.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Date', 'Amount', 'Weight'];
                        break;

                    case 'visa_refund':
                        $query = "SELECT va.passport_number as pnr, va.applicant_name as passenger_name,
                                 vr.refund_date as issue_date, vr.refund_amount as total_amount,
                                 vr.refund_type as status
                                 FROM visa_refunds vr
                                 LEFT JOIN visa_applications va ON vr.visa_id = va.id
                                 WHERE va.sold_to = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ? AND va.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'hotel_refund':
                        $query = "SELECT hb.order_id as pnr, CONCAT(hb.first_name, ' ', hb.last_name) as passenger_name,
                                 hr.created_at as issue_date, hr.refund_amount as total_amount,
                                 hr.refund_type as status
                                 FROM hotel_refunds hr
                                 LEFT JOIN hotel_bookings hb ON hr.booking_id = hb.id
                                 WHERE hb.sold_to = ? AND hr.created_at BETWEEN ? AND ? AND hb.tenant_id = ? AND hb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type'];
                        break;

                    case 'umrah_refund':
                        $query = "SELECT ub.passport_number as pnr, ub.name as passenger_name,
                                 ur.created_at as issue_date, ur.refund_amount as total_amount,
                                 ur.refund_type as status
                                 FROM umrah_refunds ur
                                 LEFT JOIN umrah_bookings ub ON ur.booking_id = ub.booking_id
                                 WHERE ub.sold_to = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ? AND ub.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;
                    case 'ticket_reservation':
                        $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name
                                 FROM ticket_reservations tb
                                 LEFT JOIN clients c ON tb.sold_to = c.id
                                 LEFT JOIN suppliers s ON tb.supplier = s.id
                                 LEFT JOIN main_account m ON tb.paid_to = m.id
                                 WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ? AND tb.tenant_id = ? AND tb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'refund_ticket':
                        $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name
                                 FROM refunded_tickets rt
                                 LEFT JOIN clients c ON rt.sold_to = c.id
                                 LEFT JOIN suppliers s ON rt.supplier = s.id
                                 LEFT JOIN main_account m ON rt.paid_to = m.id
                                 WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ? AND rt.tenant_id = ? AND rt.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'date_change_ticket':
                        $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status,
                                 c.name as client_name, s.name as supplier_name, m.name as account_name
                                 FROM date_change_tickets dc
                                 LEFT JOIN clients c ON dc.sold_to = c.id
                                 LEFT JOIN suppliers s ON dc.supplier = s.id
                                 LEFT JOIN main_account m ON dc.paid_to = m.id
                                 WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ? AND dc.tenant_id = ? AND dc.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status', 'Client', 'Supplier', 'Account'];
                        break;

                    case 'visa':
                        $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name,
                                 v.receive_date as issue_date, v.sold as total_amount, v.status
                                 FROM visa_applications v
                                 WHERE v.paid_to = ? AND v.receive_date BETWEEN ? AND ? AND v.tenant_id = ? AND v.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                        break;

                    case 'umrah':
                        $query = "SELECT u.passport_number as pnr, u.name as passenger_name,
                                  u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status
                          FROM umrah_bookings u
                          WHERE u.paid_to = ? AND " . $umrahDateCondition . " AND u.tenant_id = ? AND u.branch_id = ?";
                        $params = array_merge([$entity], $umrahDateParams, [$tenant_id, $branch_id]);

                        // Add umrah filter (family / flight date / return date)
                        if (!empty($umrahFilterSql)) {
                            $query .= $umrahFilterSql;
                            $params = array_merge($params, $umrahFilterParams);
                        }

                        $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                        break;
                        
                    case 'hotel':
                        $query = "SELECT h.order_id as pnr, CONCAT(h.first_name, ' ', h.last_name) as passenger_name,
                                h.issue_date, h.sold_amount as total_amount, 'Hotel Booking' as status
                                FROM hotel_bookings h
                                WHERE h.paid_to = ? AND h.issue_date BETWEEN ? AND ? AND h.tenant_id = ? AND h.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                     WHERE hb.paid_to = ? AND hr.created_at BETWEEN ? AND ? AND hr.tenant_id = ? AND hr.branch_id = ?
                                     ORDER BY hr.created_at DESC";
                            $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                            $headers = ['Order ID', 'Guest Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Currency', 'Client', 'Supplier'];
                            break;
                        
                    case 'expense':
                        $query = "SELECT e.id as pnr, e.description as passenger_name,
                                e.date as issue_date, e.amount as total_amount,
                                ec.name as status, e.currency
                                FROM expenses e
                                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                                WHERE e.main_account_id = ? AND e.date BETWEEN ? AND ? AND e.tenant_id = ? AND e.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                WHERE ap.main_account_id = ? AND ap.created_at BETWEEN ? AND ? AND ap.tenant_id = ? AND ap.branch_id = ?
                                ORDER BY ap.created_at DESC";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                 WHERE t.paid_to = ? AND tw.created_at BETWEEN ? AND ? AND t.tenant_id = ? AND t.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                 WHERE va.paid_to = ? AND vr.refund_date BETWEEN ? AND ? AND va.tenant_id = ? AND va.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                 WHERE hb.paid_to = ? AND hr.created_at BETWEEN ? AND ? AND hb.tenant_id = ? AND hb.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
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
                                 LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
                                 LEFT JOIN umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.status <> 'cancelled'
                                 LEFT JOIN suppliers s ON s.id = COALESCE(uf.supplier_id, ubs.supplier_id)
                                 LEFT JOIN main_account m ON ub.paid_to = m.id
                                WHERE ub.paid_to = ? AND ur.created_at BETWEEN ? AND ? AND ub.tenant_id = ? AND ub.branch_id = ?";
                        $params = [$entity, $startDate, $endDate, $tenant_id, $branch_id];
                        $headers = ['Passport Number', 'Pilgrim Name', 'Refund Date', 'Refund Amount', 'Refund Type', 'Client', 'Supplier', 'Account'];
                        break;

                }
                break;
        }
    }

    if (empty($query) && $reportCategory !== 'general_summary') {
        throw new Exception("Invalid report type or category combination");
    }

    if ($query) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Debug - log data count for creditor and debtor reports
    if ($reportCategory == 'creditor' || $reportCategory == 'debtor') {

        
        // Check if any records exist in the respective table
        $checkTable = ($reportCategory == 'creditor') ? 'creditors' : 'debtors';
        $checkQuery = "SELECT COUNT(*) as total FROM " . $checkTable;
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute();
        $totalRecords = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'data' => $data,
        'tables' => $tables,
        'sections' => $sections
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}
?> 