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
            switch($reportCategory) {
                case 'ticket':
                    $query = "WITH combined_transactions AS (
                        SELECT 
                        'Supplier' as transaction_from,
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name,
                        tb.passenger_name,
                        c.name as client_name,
                        m.name as account_name,
                        1 as sort_order
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        LEFT JOIN ticket_bookings tb ON st.reference_id = tb.id
                        LEFT JOIN clients c ON tb.sold_to = c.id
                        LEFT JOIN main_account m ON tb.paid_to = m.id
                        WHERE st.transaction_of = 'ticket_sale'
                        AND st.transaction_date BETWEEN ? AND ?
                        UNION ALL
                        SELECT 
                        'Client' as transaction_from,
                        ct.created_at,
                        ct.reference_id,
                        ct.description,
                        ct.type,
                        ct.amount,
                        s.name as supplier_name,
                        tb.passenger_name,
                        c.name as client_name,
                        m.name as account_name,
                        2 as sort_order
                        FROM client_transactions ct
                        LEFT JOIN ticket_bookings tb ON ct.reference_id = tb.id
                        LEFT JOIN suppliers s ON tb.supplier = s.id
                        LEFT JOIN clients c ON ct.client_id = c.id
                        LEFT JOIN main_account m ON tb.paid_to = m.id
                        WHERE ct.transaction_of = 'ticket_sale'
                        AND ct.created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT 
                        'Main Account' as transaction_from,
                        mt.created_at,
                        mt.reference_id,
                        mt.description,
                        mt.type,
                        mt.amount,
                        s.name as supplier_name,
                        tb.passenger_name,
                        c.name as client_name,
                        m.name as account_name,
                        3 as sort_order
                        FROM main_account_transactions mt
                        LEFT JOIN ticket_bookings tb ON mt.reference_id = tb.id
                        LEFT JOIN suppliers s ON tb.supplier = s.id
                        LEFT JOIN clients c ON tb.sold_to = c.id
                        LEFT JOIN main_account m ON mt.main_account_id = m.id
                        WHERE mt.transaction_of = 'ticket_sale'
                        AND mt.created_at BETWEEN ? AND ?
                    )
                    SELECT * FROM combined_transactions
                    ORDER BY reference_id, sort_order";
                    $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
                    $headers = ['From', 'Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Supplier', 'Client', 'Account'];
                    break;

                    case 'refund_ticket':
                        $query = "WITH combined_transactions AS (
                            SELECT 
                            'Supplier' as transaction_from,
                            st.transaction_date,
                            st.reference_id,
                            st.remarks,
                            st.transaction_type,
                            st.amount,
                            s.name as supplier_name,
                            rt.passenger_name,
                            c.name as client_name,
                            m.name as account_name,
                            1 as sort_order
                            FROM supplier_transactions st
                            LEFT JOIN suppliers s ON st.supplier_id = s.id
                            LEFT JOIN refunded_tickets rt ON st.reference_id = rt.ticket_id
                            LEFT JOIN clients c ON rt.sold_to = c.id
                            LEFT JOIN main_account m ON rt.paid_to = m.id
                            WHERE st.transaction_of = 'refund_ticket'
                            AND st.transaction_date BETWEEN ? AND ?
                            UNION ALL
                            SELECT 
                            'Client' as transaction_from,
                            ct.created_at,
                            ct.reference_id,
                            ct.description,
                            ct.type,
                            ct.amount,
                            s.name as supplier_name,
                            rt.passenger_name,
                            c.name as client_name,
                            m.name as account_name,
                            2 as sort_order
                            FROM client_transactions ct
                            LEFT JOIN refunded_tickets rt ON ct.reference_id = rt.ticket_id
                            LEFT JOIN suppliers s ON rt.supplier = s.id
                            LEFT JOIN clients c ON ct.client_id = c.id
                            LEFT JOIN main_account m ON rt.paid_to = m.id
                            WHERE ct.transaction_of = 'refund_ticket'
                            AND ct.created_at BETWEEN ? AND ?
                            UNION ALL
                            SELECT 
                            'Main Account' as transaction_from,
                            mt.created_at,
                            mt.reference_id,
                            mt.description,
                            mt.type,
                            mt.amount,
                            s.name as supplier_name,
                            rt.passenger_name,
                            c.name as client_name,
                            m.name as account_name,
                            3 as sort_order
                            FROM main_account_transactions mt
                            LEFT JOIN refunded_tickets rt ON mt.reference_id = rt.ticket_id
                            LEFT JOIN suppliers s ON rt.supplier = s.id
                            LEFT JOIN clients c ON rt.sold_to = c.id
                            LEFT JOIN main_account m ON mt.main_account_id = m.id
                            WHERE mt.transaction_of = 'refund_ticket'
                            AND mt.created_at BETWEEN ? AND ?
                        )
                        SELECT * FROM combined_transactions
                        ORDER BY reference_id, sort_order";
                        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
                        $headers = ['From', 'Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Supplier', 'Client', 'Account'];
                        break;
            
                    case 'date_change_ticket':
                        $query = "WITH combined_transactions AS (
                            SELECT 
                            'Supplier' as transaction_from,
                            st.transaction_date,
                            st.reference_id,
                            st.remarks,
                            st.transaction_type,
                            st.amount,
                            s.name as supplier_name,
                            dc.passenger_name,
                            c.name as client_name,
                            m.name as account_name,
                            1 as sort_order
                            FROM supplier_transactions st
                            LEFT JOIN suppliers s ON st.supplier_id = s.id
                            LEFT JOIN date_change_tickets dc ON st.reference_id = dc.ticket_id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            WHERE st.transaction_of = 'date_change'
                            AND st.transaction_date BETWEEN ? AND ?
                            UNION ALL
                            SELECT 
                            'Client' as transaction_from,
                            ct.created_at,
                            ct.reference_id,
                            ct.description,
                            ct.type,
                            ct.amount,
                            s.name as supplier_name,
                            dc.passenger_name,
                            c.name as client_name,
                            m.name as account_name,
                            2 as sort_order
                            FROM client_transactions ct
                            LEFT JOIN date_change_tickets dc ON ct.reference_id = dc.ticket_id
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON ct.client_id = c.id
                            LEFT JOIN main_account m ON dc.paid_to = m.id
                            WHERE ct.transaction_of = 'date_change'
                            AND ct.created_at BETWEEN ? AND ?
                            UNION ALL
                            SELECT 
                            'Main Account' as transaction_from,
                            mt.created_at,
                            mt.reference_id,
                            mt.description,
                            mt.type,
                            mt.amount,
                            s.name as supplier_name,
                            dc.passenger_name,
                            c.name as client_name,
                            m.name as account_name,
                            3 as sort_order
                            FROM main_account_transactions mt
                            LEFT JOIN date_change_tickets dc ON mt.reference_id = dc.ticket_id
                            LEFT JOIN suppliers s ON dc.supplier = s.id
                            LEFT JOIN clients c ON dc.sold_to = c.id
                            LEFT JOIN main_account m ON mt.main_account_id = m.id
                            WHERE mt.transaction_of = 'date_change'
                            AND mt.created_at BETWEEN ? AND ?
                        )
                        SELECT * FROM combined_transactions
                        ORDER BY reference_id, sort_order";
                        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
                        $headers = ['From', 'Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Supplier', 'Client', 'Account'];
                        break;
                }
                break;

                case 'umrah':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        WHERE st.transaction_of = 'umrah'
                        AND st.transaction_date BETWEEN ? AND ?
                        AND st.tenant_id = ?";
                    $params = [$startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;

                case 'visa':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        WHERE st.transaction_of = 'visa'
                        AND st.transaction_date BETWEEN ? AND ?
                        AND st.tenant_id = ?";
                    $params = [$startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;
            

        case 'supplier':
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name,
                        tb.passenger_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        LEFT JOIN ticket_bookings tb ON st.reference_id = tb.id
                        WHERE st.supplier_id = ?
                        AND st.transaction_of = 'ticket_sale'
                        AND st.transaction_date BETWEEN ? AND ?
                        AND st.tenant_id = ?
                        ORDER BY st.transaction_date";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;

                case 'refund_ticket':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name,
                        rt.passenger_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        LEFT JOIN refunded_tickets rt ON st.reference_id = rt.ticket_id
                        WHERE st.supplier_id = ?
                        AND st.transaction_of = 'refund_ticket'
                        AND st.transaction_date BETWEEN ? AND ?
                        AND st.tenant_id = ?
                        ORDER BY st.transaction_date";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name,
                        dc.passenger_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        LEFT JOIN date_change_tickets dc ON st.reference_id = dc.ticket_id
                        WHERE st.supplier_id = ?
                        AND st.transaction_of = 'date_change'
                        AND st.transaction_date BETWEEN ? AND ?
                        AND st.tenant_id = ?
                        ORDER BY st.transaction_date";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;

                case 'visa':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        WHERE st.supplier_id = ?
                        AND st.transaction_of = 'visa'
                        AND st.transaction_date BETWEEN ? AND ?
                        AND st.tenant_id = ?
                        ORDER BY st.transaction_date";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;

                case 'umrah':
                    $query = "SELECT 
                        st.transaction_date,
                        st.reference_id,
                        st.remarks,
                        st.transaction_type,
                        st.amount,
                        s.name as supplier_name
                        FROM supplier_transactions st
                        LEFT JOIN suppliers s ON st.supplier_id = s.id
                        WHERE st.supplier_id = ?
                        AND st.transaction_of = 'umrah'
                        AND st.transaction_date BETWEEN ? AND ?
                        ORDER BY st.transaction_date";
                    $params = [$entity, $startDate, $endDate];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Supplier'];
                    break;
            }
            break;

        case 'client':
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT 
                        ct.created_at as transaction_date,
                        ct.reference_id,
                        ct.description as remarks,
                        ct.type as transaction_type,
                        ct.amount,
                        c.name as client_name,
                        tb.passenger_name
                        FROM client_transactions ct
                        LEFT JOIN clients c ON ct.client_id = c.id
                        LEFT JOIN ticket_bookings tb ON ct.reference_id = tb.id
                        WHERE ct.client_id = ?
                        AND ct.transaction_of = 'ticket_sale'
                        AND ct.created_at BETWEEN ? AND ?
                        AND ct.tenant_id = ?
                        ORDER BY ct.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Client'];
                    break;

                case 'refund_ticket':
                    $query = "SELECT 
                        ct.created_at as transaction_date,
                        ct.reference_id,
                        ct.description as remarks,
                        ct.type as transaction_type,
                        ct.amount,
                        c.name as client_name,
                        rt.passenger_name
                        FROM client_transactions ct
                        LEFT JOIN clients c ON ct.client_id = c.id
                        LEFT JOIN refunded_tickets rt ON ct.reference_id = rt.ticket_id
                        WHERE ct.client_id = ?
                        AND ct.transaction_of = 'refund_ticket'
                        AND ct.created_at BETWEEN ? AND ?
                        AND ct.tenant_id = ?
                        ORDER BY ct.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Client'];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT 
                        ct.created_at as transaction_date,
                        ct.reference_id,
                        ct.description as remarks,
                        ct.type as transaction_type,
                        ct.amount,
                        c.name as client_name,
                        dc.passenger_name
                        FROM client_transactions ct
                        LEFT JOIN clients c ON ct.client_id = c.id
                        LEFT JOIN date_change_tickets dc ON ct.reference_id = dc.ticket_id
                        WHERE ct.client_id = ?
                        AND ct.transaction_of = 'date_change'
                        AND ct.created_at BETWEEN ? AND ?
                        AND ct.tenant_id = ?
                        ORDER BY ct.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Client'];
                    break;

                case 'visa':
                    $query = "SELECT 
                        ct.created_at as transaction_date,
                        ct.reference_id,
                        ct.description as remarks,
                        ct.type as transaction_type,
                        ct.amount,
                        c.name as client_name
                        FROM client_transactions ct
                        LEFT JOIN clients c ON ct.client_id = c.id
                        WHERE ct.client_id = ?
                        AND ct.transaction_of = 'visa'
                        AND ct.created_at BETWEEN ? AND ?
                        AND ct.tenant_id = ?
                        ORDER BY ct.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Client'];
                    break;

                case 'umrah':
                    $query = "SELECT 
                        ct.created_at as transaction_date,
                        ct.reference_id,
                        ct.description as remarks,
                        ct.type as transaction_type,
                        ct.amount,
                        c.name as client_name
                        FROM client_transactions ct
                        LEFT JOIN clients c ON ct.client_id = c.id
                        WHERE ct.client_id = ?
                        AND ct.transaction_of = 'umrah'
                        AND ct.created_at BETWEEN ? AND ?
                        AND ct.tenant_id = ?
                        ORDER BY ct.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Client'];
                    break;
            }
            break;

        case 'main_account':
            switch($reportCategory) {
                case 'ticket':
                    $query = "SELECT 
                        mt.created_at as transaction_date,
                        mt.reference_id,
                        mt.description as remarks,
                        mt.type as transaction_type,
                        mt.amount,
                        m.name as account_name,
                        tb.passenger_name
                        FROM main_account_transactions mt
                        LEFT JOIN main_account m ON mt.main_account_id = m.id
                        LEFT JOIN ticket_bookings tb ON mt.reference_id = tb.id
                        WHERE mt.main_account_id = ?
                        AND mt.transaction_of = 'ticket_sale'
                        AND mt.created_at BETWEEN ? AND ?
                        AND mt.tenant_id = ?
                        ORDER BY mt.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Account'];
                    break;

                case 'refund_ticket':
                    $query = "SELECT 
                        mt.created_at as transaction_date,
                        mt.reference_id,
                        mt.description as remarks,
                        mt.type as transaction_type,
                        mt.amount,
                        m.name as account_name,
                        rt.passenger_name
                        FROM main_account_transactions mt
                        LEFT JOIN main_account m ON mt.main_account_id = m.id
                        LEFT JOIN refunded_tickets rt ON mt.reference_id = rt.ticket_id
                        WHERE mt.main_account_id = ?
                        AND mt.transaction_of = 'refund_ticket'
                        AND mt.created_at BETWEEN ? AND ?
                        AND mt.tenant_id = ?
                        ORDER BY mt.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Account'];
                    break;

                case 'date_change_ticket':
                    $query = "SELECT 
                        mt.created_at as transaction_date,
                        mt.reference_id,
                        mt.description as remarks,
                        mt.type as transaction_type,
                        mt.amount,
                        m.name as account_name,
                        dc.passenger_name
                        FROM main_account_transactions mt
                        LEFT JOIN main_account m ON mt.main_account_id = m.id
                        LEFT JOIN date_change_tickets dc ON mt.reference_id = dc.ticket_id
                        WHERE mt.main_account_id = ?
                        AND mt.transaction_of = 'date_change'
                        AND mt.created_at BETWEEN ? AND ?
                        AND mt.tenant_id = ?
                        ORDER BY mt.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Passenger Name', 'Description', 'Transaction Type', 'Amount', 'Account'];
                    break;

                case 'visa':
                    $query = "SELECT 
                        mt.created_at as transaction_date,
                        mt.reference_id,
                        mt.description as remarks,
                        mt.type as transaction_type,
                        mt.amount,
                        m.name as account_name
                        FROM main_account_transactions mt
                        LEFT JOIN main_account m ON mt.main_account_id = m.id
                        WHERE mt.main_account_id = ?
                        AND mt.transaction_of = 'visa'
                        AND mt.created_at BETWEEN ? AND ?
                        AND mt.tenant_id = ?
                        ORDER BY mt.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Account'];
                    break;

                case 'umrah':
                    $query = "SELECT 
                        mt.created_at as transaction_date,
                        mt.reference_id,
                        mt.description as remarks,
                        mt.type as transaction_type,
                        mt.amount,
                        m.name as account_name
                        FROM main_account_transactions mt
                        LEFT JOIN main_account m ON mt.main_account_id = m.id
                        WHERE mt.main_account_id = ?
                        AND mt.transaction_of = 'umrah'
                        AND mt.created_at BETWEEN ? AND ?
                        AND mt.tenant_id = ?
                        ORDER BY mt.created_at";
                    $params = [$entity, $startDate, $endDate, $tenant_id];
                    $headers = ['Date', 'Reference No', 'Description', 'Transaction Type', 'Amount', 'Account'];
                    break;
            }
            break;
    }

    if ($query) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $data,
            'headers' => $headers
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid report type or category'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 