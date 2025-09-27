<?php
require_once '../includes/db.php';

$reportType = $_POST['reportType'];
$entity = $_POST['entity'];
$reportCategory = $_POST['reportCategory'];
$startDate = $_POST['startDate'];
$endDate = $_POST['endDate'];

try {
    $query = "";
    $params = [];
    $headers = [];
    
    switch($reportType) {
        case 'ticket':
            // Handle different ticket categories
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                             FROM ticket_bookings tb 
                             WHERE tb.issue_date BETWEEN ? AND ?";
                    $params = [$startDate, $endDate];
                    break;

                case 'refund_ticket':
                    $query = "SELECT rt.pnr, rt.passenger_name, rt.created_at as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status 
                             FROM refunded_tickets rt 
                             WHERE rt.created_at BETWEEN ? AND ?";
                    $params = [$startDate, $endDate];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status 
                             FROM date_change_tickets dc 
                             WHERE dc.created_at BETWEEN ? AND ?";
                    $params = [$startDate, $endDate];
                    break;
            }
            $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status'];
            break;
            
        case 'supplier':
            // Handle supplier with different categories
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                             FROM ticket_bookings tb 
                             WHERE tb.issue_date BETWEEN ? AND ?";
                    $params = [$startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status'];
                    break;

                case 'refund_ticket':
                    $query = "SELECT rt.pnr, rt.passenger_name, rt.refund_date as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status 
                             FROM refunded_tickets rt 
                             WHERE rt.created_at BETWEEN ? AND ?";
                    $params = [$startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status'];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status 
                             FROM date_change_tickets dc 
                             WHERE dc.created_at BETWEEN ? AND ?";
                    $params = [$startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status'];
                    break;

                case 'visa':
                    $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                             v.receive_date as issue_date, v.sold as total_amount, v.status 
                             FROM visa_applications v 
                             WHERE v.supplier = ? AND v.receive_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                    break;

                case 'umrah':
                    $query = "SELECT u.passport_number as pnr, u.name as passenger_name, 
                             u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status 
                             FROM umrah_bookings u 
                             WHERE u.supplier = ? AND u.entry_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                    break;
            }
            break;

        case 'client':
            // Handle client with different categories
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                             FROM ticket_bookings tb 
                             WHERE tb.sold_to = ? AND tb.issue_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status'];
                    break;

                case 'refund_ticket':
                    $query = "SELECT rt.pnr, rt.passenger_name, rt.refund_date as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status 
                             FROM refunded_tickets rt 
                             WHERE rt.sold_to = ? AND rt.created_at BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status'];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status 
                             FROM date_change_tickets dc 
                             WHERE dc.sold_to = ? AND dc.created_at BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status'];
                    break;

                case 'visa':
                    $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                             v.receive_date as issue_date, v.sold as total_amount, v.status 
                             FROM visa_applications v 
                             WHERE v.sold_to = ? AND v.receive_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                    break;

                case 'umrah':
                    $query = "SELECT u.passport_number as pnr, u.name as passenger_name, 
                             u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status 
                             FROM umrah_bookings u 
                             WHERE u.sold_to = ? AND u.entry_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                    break;
            }
            break;

        case 'main_account':
            // Handle main account with different categories
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT tb.pnr, tb.passenger_name, tb.issue_date, tb.sold as total_amount, tb.status 
                             FROM ticket_bookings tb 
                             WHERE tb.paid_to = ? AND tb.issue_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Issue Date', 'Total Amount', 'Status'];
                    break;

                case 'refund_ticket':
                    $query = "SELECT rt.pnr, rt.passenger_name, rt.refund_date as issue_date, rt.refund_to_passenger as total_amount, 'Refunded' as status 
                             FROM refunded_tickets rt 
                             WHERE rt.paid_to = ? AND rt.created_at BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Refund Date', 'Refund Amount', 'Status'];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT dc.pnr, dc.passenger_name, dc.issue_date as issue_date, dc.service_penalty + dc.supplier_penalty as total_amount, 'Date Changed' as status 
                             FROM date_change_tickets dc 
                             WHERE dc.paid_to = ? AND dc.created_at BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['PNR', 'Passenger Name', 'Change Date', 'Penalty Amount', 'Status'];
                    break;

                case 'visa':
                    $query = "SELECT v.passport_number as pnr, v.applicant_name as passenger_name, 
                             v.receive_date as issue_date, v.sold as total_amount, v.status 
                             FROM visa_applications v 
                             WHERE v.paid_to = ? AND v.receive_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Passport Number', 'Applicant Name', 'Receive Date', 'Amount', 'Status'];
                    break;

                case 'umrah':
                    $query = "SELECT u.passport_number as pnr, u.name as passenger_name, 
                             u.entry_date as issue_date, u.sold_price as total_amount, u.duration as status 
                             FROM umrah_bookings u 
                             WHERE u.paid_to = ? AND u.entry_date BETWEEN ? AND ?";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Passport Number', 'Pilgrim Name', 'Entry Date', 'Package Price', 'Duration'];
                    break;
            }
            break;
    }

    if (empty($query)) {
        throw new Exception("Invalid report type or category combination");
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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