<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

session_start();
require_once('../includes/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get POST data
    $reportType = $_POST['entityType'] ?? 'client';
    $entity = $_POST['entityId'] ?? '';
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $currency = $_POST['currency'] ?? '';

    if (!$entity || !$startDate || !$endDate) {
        throw new Exception('Missing required parameters');

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate endDate
$endDate = isset($_POST['endDate']) ? DbSecurity::validateInput($_POST['endDate'], 'date') : null;

// Validate startDate
$startDate = isset($_POST['startDate']) ? DbSecurity::validateInput($_POST['startDate'], 'date') : null;

// Validate entityId
$entityId = isset($_POST['entityId']) ? DbSecurity::validateInput($_POST['entityId'], 'string', ['maxlength' => 255]) : null;

// Validate entityType
$entityType = isset($_POST['entityType']) ? DbSecurity::validateInput($_POST['entityType'], 'string', ['maxlength' => 255]) : null;
    }

    // Get company settings
    $settingsQuery = "SELECT * FROM settings WHERE id = 1";
    $stmt = $pdo->prepare($settingsQuery);
    $stmt->execute();
    $companySettings = $stmt->fetch(PDO::FETCH_ASSOC);

    switch($reportType) {
        case 'client':
            // Get client details
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$entity]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get client transactions
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN ct.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN ct.transaction_of = 'date_change' THEN DATE(dc.created_at)
                        WHEN ct.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                        WHEN ct.transaction_of = 'umrah' THEN DATE(um.entry_date)
                        WHEN ct.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                        WHEN ct.transaction_of = 'fund' THEN DATE(ct.created_at)
                        WHEN ct.transaction_of = 'hotel_refund' THEN DATE(hbr.created_at)
                        
                    END as transaction_date,
                    ct.type,
                    ct.amount,
                    ct.description,
                    ct.transaction_of,
                    ct.reference_id,
                    ct.balance,
                    ct.receipt,
                    COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr) 
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr) 
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr) 
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name, ' - ', vs.passport_number) 
                            WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.name, ' - ', um.passport_number)
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name, ' - ', hb.order_id)
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.name, ' ', usr.role) 
                            WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hb.title,hb.first_name, hb.last_name, ' - ', hb.order_id)
                            ELSE ''
                        END), 'N/A'
                    ) AS reference_details,
                    COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.departure_date) 
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.departure_date) 
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.departure_date) 
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applied_date) 
                            WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.flight_date) 
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.check_in_date)
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(' ') 
                            ELSE ''
                        END), 'N/A'
                    ) AS departure_date,
                    COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.origin,'-',tb.destination) 
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin,'-',rt.destination) 
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.origin,'-',dc.destination) 
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.country,'-',vs.visa_type) 
                            WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.room_type,'-',um.duration) 
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(' ') 
                            WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hbr.accommodation_details)
                            ELSE ''
                        END), 'N/A'
                    ) AS sector,
                    COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(ct.description) 
                            ELSE ''
                        END), 'N/A'
                    ) AS remark
                FROM client_transactions ct
                LEFT JOIN ticket_bookings tb ON tb.id = ct.reference_id AND ct.transaction_of = 'ticket_sale'
                LEFT JOIN refunded_tickets rt ON rt.id = ct.reference_id AND ct.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = ct.reference_id AND ct.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = ct.reference_id AND ct.transaction_of = 'visa_sale'
                LEFT JOIN umrah_bookings um ON um.booking_id = ct.reference_id AND ct.transaction_of = 'umrah'
                LEFT JOIN hotel_bookings hb ON hb.id = ct.reference_id AND ct.transaction_of = 'hotel'
                LEFT JOIN hotel_refunds hbr ON hbr.id = ct.reference_id AND ct.transaction_of = 'hotel_refund'
                LEFT JOIN users usr ON usr.id = ct.reference_id AND ct.transaction_of = 'fund'
                WHERE ct.client_id = ?
                AND ct.currency = ?
                AND DATE(ct.created_at) BETWEEN ? AND ?
                AND ct.tenant_id = ?
                ORDER BY ct.created_at ASC, ct.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entity, $currency, $startDate, $endDate, $tenant_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'supplier':
            // Get supplier details
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$entity]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get supplier transactions
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN st.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN st.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                        WHEN st.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                        WHEN st.transaction_of = 'umrah' THEN DATE(um.entry_date)
                        WHEN st.transaction_of = 'fund' THEN DATE(st.transaction_date)
                        ELSE DATE(st.transaction_date)
                    END as transaction_date,
                    st.transaction_type as type,
                    st.amount,
                    st.remarks as description,
                    st.transaction_of,
                    st.reference_id,
                    st.balance,
                    st.receipt,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name, ' - ', vs.passport_number)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name, ' - ', hb.order_id)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.name, ' - ', um.passport_number)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(usr.name, ' ', usr.role)
                            ELSE ''
                        END), 'N/A'
                    ) AS reference_details,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN tb.departure_date
                            WHEN st.transaction_of = 'ticket_refund' THEN rt.departure_date
                            WHEN st.transaction_of = 'date_change' THEN dc.departure_date
                            WHEN st.transaction_of = 'visa_sale' THEN vs.applied_date
                            WHEN st.transaction_of = 'hotel' THEN hb.check_in_date
                            WHEN st.transaction_of = 'umrah' THEN um.flight_date
                            WHEN st.transaction_of = 'fund' THEN ' '
                            ELSE NULL
                        END), 'N/A'
                    ) AS departure_date,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.origin,'-',tb.destination)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin,'-',rt.destination)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.origin,'-',dc.destination)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.country,'-',vs.visa_type)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.room_type,'-',um.duration)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(' ')
                            ELSE ''
                        END), 'N/A'
                    ) AS sector,
                    st.remarks as remark
                FROM supplier_transactions st
                LEFT JOIN ticket_bookings tb ON tb.id = st.reference_id AND st.transaction_of = 'ticket_sale'
                LEFT JOIN refunded_tickets rt ON rt.id = st.reference_id AND st.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = st.reference_id AND st.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = st.reference_id AND st.transaction_of = 'visa_sale'
                LEFT JOIN hotel_bookings hb ON hb.id = st.reference_id AND st.transaction_of = 'hotel'
                LEFT JOIN umrah_bookings um ON um.booking_id = st.reference_id AND st.transaction_of = 'umrah'
                LEFT JOIN users usr ON usr.id = st.reference_id AND st.transaction_of = 'fund'
                WHERE st.supplier_id = ?
                AND DATE(st.transaction_date) BETWEEN ? AND ?
                AND st.tenant_id = ?
                ORDER BY st.transaction_date ASC, st.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entity, $startDate, $endDate, $tenant_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'main_account':
            // Get main account details
            $stmt = $pdo->prepare("SELECT * FROM main_account WHERE id = ?");
            $stmt->execute([$entity]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get main account transactions
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN mt.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN mt.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN mt.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                        WHEN mt.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                        WHEN mt.transaction_of = 'umrah' THEN DATE(um.entry_date)
                        WHEN mt.transaction_of = 'fund' THEN DATE(mt.created_at)
                        ELSE DATE(mt.created_at)
                    END as transaction_date,
                    mt.type,
                    mt.amount,
                    mt.description,
                    mt.transaction_of,
                    mt.reference_id,
                    mt.balance,
                    mt.receipt,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr)
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr)
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr)
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name, ' - ', vs.passport_number)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name, ' - ', hb.order_id)
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.name, ' - ', um.passport_number)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(usr.name, ' ', usr.role)
                            ELSE ''
                        END), 'N/A'
                    ) AS reference_details,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN tb.departure_date
                            WHEN mt.transaction_of = 'ticket_refund' THEN rt.departure_date
                            WHEN mt.transaction_of = 'date_change' THEN dc.departure_date
                            WHEN mt.transaction_of = 'visa_sale' THEN vs.applied_date
                            WHEN mt.transaction_of = 'hotel' THEN hb.check_in_date
                            WHEN mt.transaction_of = 'umrah' THEN um.flight_date
                            WHEN mt.transaction_of = 'fund' THEN ' '
                            ELSE NULL
                        END), 'N/A'
                    ) AS departure_date,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.origin,'-',tb.destination)
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin,'-',rt.destination)
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.origin,'-',dc.destination)
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.country,'-',vs.visa_type)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.room_type,'-',um.duration)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(' ')
                            ELSE ''
                        END), 'N/A'
                    ) AS sector,
                    mt.description as remark
                FROM main_account_transactions mt
                LEFT JOIN ticket_bookings tb ON tb.id = mt.reference_id AND mt.transaction_of = 'ticket_sale'
                LEFT JOIN refunded_tickets rt ON rt.id = mt.reference_id AND mt.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = mt.reference_id AND mt.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = mt.reference_id AND mt.transaction_of = 'visa_sale'
                LEFT JOIN hotel_bookings hb ON hb.id = mt.reference_id AND mt.transaction_of = 'hotel'
                LEFT JOIN umrah_bookings um ON um.booking_id = mt.reference_id AND mt.transaction_of = 'umrah'
                LEFT JOIN users usr ON usr.id = mt.reference_id AND mt.transaction_of = 'fund'
                WHERE mt.main_account_id = ?
                AND mt.currency = ?
                AND DATE(mt.created_at) BETWEEN ? AND ?
                AND mt.tenant_id = ?
                ORDER BY mt.created_at ASC, mt.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entity, $currency, $startDate, $endDate, $tenant_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            throw new Exception('Invalid report type');
    }

    // Format dates and numbers for display
    foreach ($transactions as &$transaction) {
        $transaction['transaction_date'] = date('d M Y', strtotime($transaction['transaction_date']));
        $transaction['amount'] = number_format($transaction['amount'], 2);
        $transaction['balance'] = number_format($transaction['balance'], 2);
    }

    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'entity' => $entityDetails,
            'transactions' => $transactions,
            'company' => $companySettings,
            'summary' => [
                'startDate' => date('d M Y', strtotime($startDate)),
                'endDate' => date('d M Y', strtotime($endDate)),
                'currency' => $currency
            ]
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 