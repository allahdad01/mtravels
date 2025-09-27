<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once('../includes/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Get POST data
   $entityId = filter_input(INPUT_POST, 'entityId', FILTER_VALIDATE_INT);
    $startDate = htmlspecialchars(trim($_POST['startDate'] ?? ''), ENT_QUOTES, 'UTF-8');
    $endDate = htmlspecialchars(trim($_POST['endDate'] ?? ''), ENT_QUOTES, 'UTF-8');
    $currency = htmlspecialchars(trim($_POST['currency'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reportType = htmlspecialchars(trim($_POST['reportType'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!$entityId || !$startDate || !$endDate || !$currency || !$reportType) {
        throw new Exception('Missing required parameters');
    }

    switch($reportType) {
        case 'client':
            // Get client details with opening balance
            $entityQuery = "
                SELECT 
                    c.*,
                    COALESCE(
                        (SELECT SUM(
                            CASE 
                                WHEN type = 'debit' THEN amount 
                                WHEN type = 'credit' THEN -amount 
                            END
                        ) 
                        FROM client_transactions 
                        WHERE client_id = c.id 
                        AND currency = ?
                        AND DATE(created_at) < ?), 0
                    ) as opening_balance
                FROM clients c
                WHERE c.id = ?";

            $stmt = $pdo->prepare($entityQuery);
            $stmt->execute([$currency, $startDate, $entityId]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Use the existing client transactions query
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
                        ELSE DATE(ct.created_at)
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
                LEFT JOIN users usr ON usr.id = ct.reference_id AND ct.transaction_of = 'fund'
                WHERE ct.client_id = ?
                AND ct.currency = ?
                AND DATE(ct.created_at) BETWEEN ? AND ?
                ORDER BY ct.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entityId, $currency, $startDate, $endDate]);
            break;

        case 'supplier':
            // Get supplier details with opening balance
            $entityQuery = "
                SELECT 
                    s.*,
                    COALESCE(
                        (SELECT SUM(
                            CASE 
                                WHEN transaction_type = 'debit' THEN amount 
                                WHEN transaction_type = 'credit' THEN -amount 
                            END
                        ) 
                        FROM supplier_transactions 
                        WHERE supplier_id = s.id 
                        AND DATE(transaction_date) < ?), 0
                    ) as opening_balance
                FROM suppliers s
                WHERE s.id = ?";

            $stmt = $pdo->prepare($entityQuery);
            $stmt->execute([$startDate, $entityId]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Use same structure as client transactions for suppliers
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN st.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN st.transaction_of = 'date_change' THEN DATE(dc.created_at)
                        WHEN st.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                        WHEN st.transaction_of = 'umrah' THEN DATE(um.entry_date)
                        WHEN st.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                        WHEN st.transaction_of = 'fund' THEN DATE(st.transaction_date)
                        ELSE DATE(st.transaction_date)
                    END as transaction_date,
                    st.transaction_type,
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
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.name, ' - ', um.passport_number)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name, ' - ', hb.order_id)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(usr.name, ' ', usr.role)
                            ELSE ''
                        END), 'N/A'
                    ) AS reference_details,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.departure_date) 
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.departure_date) 
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.departure_date) 
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.applied_date) 
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.flight_date) 
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.check_in_date)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(' ') 
                            ELSE ''
                        END), 'N/A'
                    ) AS departure_date,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.origin,'-',tb.destination) 
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin,'-',rt.destination) 
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.origin,'-',dc.destination) 
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.country,'-',vs.visa_type) 
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.room_type,'-',um.duration) 
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(' ') 
                            ELSE ''
                        END), 'N/A'
                    ) AS sector,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'fund' THEN CONCAT(st.remarks) 
                            ELSE ''
                        END), 'N/A'
                    ) AS remark
                FROM supplier_transactions st
                LEFT JOIN ticket_bookings tb ON tb.id = st.reference_id AND st.transaction_of = 'ticket_sale'
                LEFT JOIN refunded_tickets rt ON rt.id = st.reference_id AND st.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = st.reference_id AND st.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = st.reference_id AND st.transaction_of = 'visa_sale'
                LEFT JOIN umrah_bookings um ON um.booking_id = st.reference_id AND st.transaction_of = 'umrah'
                LEFT JOIN hotel_bookings hb ON hb.id = st.reference_id AND st.transaction_of = 'hotel'
                LEFT JOIN users usr ON usr.id = st.reference_id AND st.transaction_of = 'fund'
                WHERE st.supplier_id = ?
                AND DATE(st.transaction_date) BETWEEN ? AND ?
                ORDER BY transaction_date ASC, st.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entityId, $startDate, $endDate]);
            break;

        case 'main_account':
            // Get main account details with opening balance
            $entityQuery = "
                SELECT 
                    m.*,
                    COALESCE(
                        (SELECT SUM(
                            CASE 
                                WHEN type = 'debit' THEN amount 
                                WHEN type = 'credit' THEN -amount 
                            END
                        ) 
                        FROM main_account_transactions 
                        WHERE main_account_id = m.id 
                        AND currency = ?
                        AND DATE(created_at) < ?), 0
                    ) as opening_balance
                FROM main_account m
                WHERE m.id = ?";

            $stmt = $pdo->prepare($entityQuery);
            $stmt->execute([$currency, $startDate, $entityId]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Use same structure as client transactions for main account
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN mt.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN mt.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN mt.transaction_of = 'date_change' THEN DATE(dc.created_at)
                        WHEN mt.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                        WHEN mt.transaction_of = 'umrah' THEN DATE(um.entry_date)
                        WHEN mt.transaction_of = 'hotel' THEN DATE(hb.issue_date)
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
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.name, ' - ', um.passport_number)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name, ' - ', hb.order_id)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(usr.name, ' ', usr.role)
                            ELSE ''
                        END), 'N/A'
                    ) AS reference_details,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.departure_date) 
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.departure_date) 
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.departure_date) 
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.applied_date) 
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.flight_date) 
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.check_in_date)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(' ') 
                            ELSE ''
                        END), 'N/A'
                    ) AS departure_date,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.origin,'-',tb.destination) 
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin,'-',rt.destination) 
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.origin,'-',dc.destination) 
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.country,'-',vs.visa_type) 
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.room_type,'-',um.duration) 
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(' ') 
                            ELSE ''
                        END), 'N/A'
                    ) AS sector,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(mt.description) 
                            ELSE ''
                        END), 'N/A'
                    ) AS remark
                FROM main_account_transactions mt
                LEFT JOIN ticket_bookings tb ON tb.id = mt.reference_id AND mt.transaction_of = 'ticket_sale'
                LEFT JOIN refunded_tickets rt ON rt.id = mt.reference_id AND mt.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = mt.reference_id AND mt.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = mt.reference_id AND mt.transaction_of = 'visa_sale'
                LEFT JOIN umrah_bookings um ON um.booking_id = mt.reference_id AND mt.transaction_of = 'umrah'
                LEFT JOIN hotel_bookings hb ON hb.id = mt.reference_id AND mt.transaction_of = 'hotel'
                LEFT JOIN users usr ON usr.id = mt.reference_id AND mt.transaction_of = 'fund'
                WHERE mt.main_account_id = ?
                AND mt.currency = ?
                AND DATE(mt.created_at) BETWEEN ? AND ?
                ORDER BY transaction_date ASC, mt.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entityId, $currency, $startDate, $endDate]);
            break;

        default:
            throw new Exception('Invalid report type');
    }

    // After the switch statement, add:
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Then continue with the rest of the code:
    $statementData = [];
    $totals = [
        'debit' => 0,
        'credit' => 0
    ];

    // Process transactions
    foreach ($transactions as $transaction) {
        // Add to totals based on report type
        if ($reportType === 'supplier') {
            if ($transaction['transaction_type'] === 'debit') {
                $totals['debit'] += floatval($transaction['amount']);
            } else {
                $totals['credit'] += floatval($transaction['amount']);
            }
        } else {
            if ($transaction['type'] === 'debit') {
                $totals['debit'] += floatval($transaction['amount']);
            } else {
                $totals['credit'] += floatval($transaction['amount']);
            }
        }

        // Create statement data based on report type
        $statementData[] = [
            'transaction_date' => $transaction['transaction_date'],
            'description' => $reportType === 'supplier' ? ($transaction['remarks'] ?? '') : ($transaction['description'] ?? ''),
            'type' => $reportType === 'supplier' ? $transaction['transaction_type'] : $transaction['type'],
            'amount' => $transaction['amount'],
            'balance' => $transaction['balance'],
            'transaction_of' => $transaction['transaction_of'],
            'reference_details' => $transaction['reference_details'],
            'departure_date' => $transaction['departure_date'],
            'sector' => $transaction['sector'],
            'receipt' => $transaction['receipt'],
            'remark' => $transaction['remark']
        ];
    }

    // Get company settings
    $settingsQuery = "SELECT * FROM settings WHERE id = 1";
    $stmt = $pdo->prepare($settingsQuery);
    $stmt->execute();
    $companySettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'transactions' => $statementData,
            'totals' => $totals,
            'client' => $entityDetails,
            'company' => $companySettings,
            'summary' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                
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