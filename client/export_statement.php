<?php
// Start output buffering at the very beginning of the script
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session and check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once('../includes/db.php');
require_once('../vendor/autoload.php'); // For TCPDF


// Get report type from URL
$reportType = $_GET['reportType'] ?? 'client'; // client, supplier, or main_account

try {
    // Validate and get common parameters
    $startDate = $_GET['startDate'] ?? '';
    $endDate = $_GET['endDate'] ?? '';
    $currency = $_GET['currency'] ?? '';
    $format = strtolower($_GET['format'] ?? 'pdf');
    
    // Get entity ID and details based on report type
    $entity = $_GET['entity'] ?? '';
    
    switch($reportType) {
        case 'client':
            // Get client details
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$entity]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get detailed transactions
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN ct.transaction_of = 'ticket_reserve' THEN DATE(tr.issue_date)
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
                    ct.currency,
                    ct.exchange_rate,
                    ct.id as transaction_id,
                    COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr, ' - ', tb.airline, ' - ', ct.transaction_of, ' - ', tb.origin, ' - ', tb.destination) 
                            WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name, ' - ', tr.pnr, ' - ', tr.airline, ' - ', ct.transaction_of, ' - ', tr.origin, ' - ', tr.destination)
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr, ' - ', rt.airline, ' - ', ct.transaction_of, ' - ', rt.origin, ' - ', rt.destination) 
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr, ' - ', dc.airline, ' - ', ct.transaction_of, ' - ', dc.origin, ' - ', dc.destination) 
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name) 
                            WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.name)
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.name) 
                            WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            ELSE ''
                        END), 'N/A'
                    ) AS name,
                     COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.pnr) 
                            WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.pnr)
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.pnr) 
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.pnr) 
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.passport_number) 
                            WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.passport_number)
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.order_id)
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.role) 
                            WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hb.order_id)
                            ELSE ''
                        END), 'N/A'
                    ) AS pnr,
                     COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.airline,'-',ct.transaction_of)
                            WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.airline,'-',ct.transaction_of)
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.airline,'-',ct.transaction_of)
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.airline,'-',ct.transaction_of)
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.status,'-',ct.transaction_of)
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(ct.transaction_of)
                            WHEN ct.transaction_of = 'umrah' THEN CONCAT(ct.transaction_of)
                            WHEN ct.transaction_of = 'fund' THEN CONCAT(ct.transaction_of)
                            WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(ct.transaction_of)
                            ELSE ''
                        END), 'N/A'
                    ) AS details,
                    COALESCE(
                        (CASE 
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.departure_date) 
                            WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.departure_date)
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
                            WHEN ct.transaction_of = 'ticket_sale' THEN 
                                CASE 
                                    WHEN tb.trip_type = 'round_trip' THEN CONCAT(tb.origin,'-',tb.destination,'-',tb.return_destination)
                                    ELSE CONCAT(tb.origin,'-',tb.destination)
                                END
                            WHEN ct.transaction_of = 'ticket_reserve' THEN 
                                CASE 
                                    WHEN tr.trip_type = 'round_trip' THEN CONCAT(tr.origin,'-',tr.destination,'-',tr.return_destination)
                                    ELSE CONCAT(tr.origin,'-',tr.destination)
                                END
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
                            WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.description)
                            WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.description)
                            WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.remarks)
                            WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.remarks)
                            WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.remarks)
                           
                            WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.remarks)
                            ELSE ''
                        END), 'N/A'
                    ) AS remark
                FROM client_transactions ct
                LEFT JOIN ticket_bookings tb ON tb.id = ct.reference_id AND ct.transaction_of = 'ticket_sale'
                LEFT JOIN ticket_reservations tr ON tr.id = ct.reference_id AND ct.transaction_of = 'ticket_reserve'
                LEFT JOIN users usr ON usr.id = ct.reference_id AND ct.transaction_of = 'fund'
                LEFT JOIN refunded_tickets rt ON rt.id = ct.reference_id AND ct.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = ct.reference_id AND ct.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = ct.reference_id AND ct.transaction_of = 'visa_sale'
                LEFT JOIN umrah_bookings um ON um.booking_id = ct.reference_id AND ct.transaction_of = 'umrah'
                LEFT JOIN hotel_bookings hb ON hb.id = ct.reference_id AND ct.transaction_of = 'hotel'
                
                WHERE ct.client_id = ?
                ORDER BY ct.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entity]);
            $rawTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process transactions to handle different currencies with same receipt number
            $processedTransactions = [];
            $receiptGroups = [];
            
            // Group transactions by receipt number
            foreach ($rawTransactions as $transaction) {
                if (!empty($transaction['receipt'])) {
                    $receiptGroups[$transaction['receipt']][] = $transaction;
                } else {
                    // Transactions without a receipt go directly to processed transactions
                    if ($transaction['currency'] == $currency) {
                        $processedTransactions[] = $transaction;
                    }
                }
            }
            
            // Process each receipt group
            foreach ($receiptGroups as $receipt => $group) {
                if (count($group) == 1) {
                    // Single transaction with this receipt - just add it if it matches the currency
                    if ($group[0]['currency'] == $currency) {
                        $processedTransactions[] = $group[0];
                    }
                } else {
                    // Multiple transactions with same receipt - consolidate if different currencies
                    $primaryTransaction = null;
                    $totalConvertedAmount = 0;
                    $hasTargetCurrency = false;
                    
                    // Check if target currency exists in this group
                    foreach ($group as $transaction) {
                        if ($transaction['currency'] == $currency) {
                            $hasTargetCurrency = true;
                            $primaryTransaction = $transaction;
                            $totalConvertedAmount += $transaction['amount'];
                        }
                    }
                    
                    // Skip if no transaction in target currency
                    if (!$hasTargetCurrency) {
                        continue;
                    }
                    
                    // Add other currency amounts converted to target currency
                    foreach ($group as $transaction) {
                        if ($transaction['currency'] != $currency && !empty($transaction['exchange_rate'])) {
                            // Convert amount using exchange rate
                            $convertedAmount = $transaction['amount'] / $transaction['exchange_rate'];
                            $totalConvertedAmount += $convertedAmount;
                        }
                    }
                    
                    // Update the primary transaction with the consolidated amount
                    if ($primaryTransaction) {
                        $primaryTransaction['amount'] = $totalConvertedAmount;
                        $processedTransactions[] = $primaryTransaction;
                    }
                }
            }
            
            // Save all transactions for total calculation
            $allTransactions = $processedTransactions;
            
            // Filter by date
            $transactions = [];
            
            foreach ($processedTransactions as $transaction) {
                $transactionDate = new DateTime($transaction['transaction_date']);
                $startDateObj = new DateTime($startDate);
                $endDateObj = new DateTime($endDate);
                
                if ($transactionDate >= $startDateObj && $transactionDate <= $endDateObj) {
                    $transactions[] = $transaction;
                }
            }
            
            // Sort transactions by ID to maintain original order
            usort($transactions, function($a, $b) {
                return $a['transaction_id'] <=> $b['transaction_id'];
            });
            
            // Sort all transactions to get the current balance
            usort($allTransactions, function($a, $b) {
                return $a['transaction_id'] <=> $b['transaction_id'];
            });
            
            $title = "Client Statement of Account";
            break;
            
        case 'supplier':
            // Get supplier details
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$entity]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get detailed supplier transactions
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN st.transaction_of = 'ticket_reserve' THEN DATE(tr.issue_date)
                        WHEN st.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN st.transaction_of = 'date_change' THEN DATE(dc.created_at)
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
                    st.balance, -- Simply include the balance field from supplier_transactions
                    st.receipt,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr, ' - ', tb.airline, ' - ', st.transaction_of, ' - ', tb.origin, ' - ', tb.destination)
                            WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name, ' - ', tr.pnr, ' - ', tr.airline, ' - ', st.transaction_of, ' - ', tr.origin, ' - ', tr.destination)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr, ' - ', rt.airline, ' - ', st.transaction_of, ' - ', rt.origin, ' - ', rt.destination)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr, ' - ', dc.airline, ' - ', st.transaction_of, ' - ', dc.origin, ' - ', dc.destination)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.name)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(usr.name)
                            WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            ELSE ''
                        END), 'N/A'
                    ) AS name,
                     COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.pnr)
                            WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.pnr)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.pnr)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.pnr)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.passport_number)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.order_id)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(um.passport_number)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(usr.role)
                            WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(hb.order_id)
                            ELSE ''
                        END), 'N/A'
                    ) AS pnr,
                     COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.airline,'-',st.transaction_of)
                            WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.airline,'-',st.transaction_of)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.airline,'-',st.transaction_of)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.airline,'-',st.transaction_of)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.status,'-',st.transaction_of)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(st.transaction_of)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(st.transaction_of)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(st.transaction_of)
                            WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(st.transaction_of)
                            ELSE ''
                        END), 'N/A'
                    ) AS details,
                    COALESCE(
                        (CASE 
                            WHEN st.transaction_of = 'ticket_sale' THEN tb.departure_date
                            WHEN st.transaction_of = 'ticket_reserve' THEN tr.departure_date
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
                            WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.origin,'-',tr.destination)
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
                LEFT JOIN ticket_reservations tr ON tr.id = st.reference_id AND st.transaction_of = 'ticket_reserve'
                LEFT JOIN refunded_tickets rt ON rt.id = st.reference_id AND st.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = st.reference_id AND st.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = st.reference_id AND st.transaction_of = 'visa_sale'
                LEFT JOIN hotel_bookings hb ON hb.id = st.reference_id AND st.transaction_of = 'hotel'
                LEFT JOIN umrah_bookings um ON um.booking_id = st.reference_id AND st.transaction_of = 'umrah'
                LEFT JOIN users usr ON usr.id = st.reference_id AND st.transaction_of = 'fund'
                WHERE st.supplier_id = ?
                AND DATE(st.transaction_date) BETWEEN ? AND ?
                ORDER BY st.transaction_date ASC, st.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$entity, $startDate, $endDate]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $title = "Supplier Statement of Account";
            break;
            
        case 'main_account':
            // Get main account details
            $stmt = $pdo->prepare("SELECT * FROM main_account WHERE id = ?");
            $stmt->execute([$entity]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get main account transactions with consolidated view
            $transactionsQuery = "
                SELECT 
                    CASE 
                        WHEN mt.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                        WHEN mt.transaction_of = 'ticket_reserve' THEN DATE(tr.issue_date)
                        WHEN mt.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                        WHEN mt.transaction_of = 'date_change' THEN DATE(dc.created_at)
                        WHEN mt.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                        WHEN mt.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                        WHEN mt.transaction_of = 'umrah' THEN DATE(um.entry_date)
                        WHEN mt.transaction_of = 'fund' THEN DATE(mt.created_at)
                        WHEN mt.transaction_of = 'additional_payment' THEN DATE(ap.created_at)
                        WHEN mt.transaction_of = 'creditor' THEN DATE(ct.payment_date)
                        WHEN mt.transaction_of = 'debtor' THEN DATE(dt.payment_date)
                        WHEN mt.transaction_of = 'expense' THEN DATE(exp.date)
                        WHEN mt.transaction_of = 'transfer' THEN DATE(mt.created_at)
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
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr, ' - ', tb.airline, ' - ', mt.transaction_of, ' - ', tb.origin, ' - ', tb.destination)
                            WHEN mt.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name, ' - ', tr.pnr, ' - ', tr.airline, ' - ', mt.transaction_of, ' - ', tr.origin, ' - ', tr.destination)
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr, ' - ', rt.airline, ' - ', mt.transaction_of, ' - ', rt.origin, ' - ', rt.destination)
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr, ' - ', dc.airline, ' - ', mt.transaction_of, ' - ', dc.origin, ' - ', dc.destination)
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.name)
                            WHEN mt.transaction_of = 'hotel_refund' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(usr.name)
                            WHEN mt.transaction_of = 'additional_payment' THEN CONCAT(ap.payment_type)
                            WHEN mt.transaction_of = 'creditor' THEN CONCAT(c.name)
                            WHEN mt.transaction_of = 'debtor' THEN CONCAT(d.name)
                            WHEN mt.transaction_of = 'expense' THEN CONCAT(ec.name)
                            WHEN mt.transaction_of = 'transfer' THEN 'Account Transfer'
                            ELSE ''
                        END), 'N/A'
                    ) AS name,
                     COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.pnr)
                            WHEN mt.transaction_of = 'ticket_reserve' THEN CONCAT(tr.pnr)
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.pnr)
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.pnr)
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.passport_number)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.order_id)
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.passport_number)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(usr.role)
                            WHEN mt.transaction_of = 'additional_payment' THEN CONCAT(ap.id)
                            WHEN mt.transaction_of = 'creditor' THEN CONCAT(ct.reference_number)
                            WHEN mt.transaction_of = 'debtor' THEN CONCAT(dt.reference_number)
                            WHEN mt.transaction_of = 'expense' THEN CONCAT(exp.id)
                            WHEN mt.transaction_of = 'transfer' THEN CONCAT(mt.receipt)
                            ELSE ''
                        END), 'N/A'
                    ) AS pnr,
                     COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.airline,'-',mt.transaction_of)
                            WHEN mt.transaction_of = 'ticket_reserve' THEN CONCAT(tr.airline,'-',mt.transaction_of)
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.airline,'-',mt.transaction_of)
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.airline,'-',mt.transaction_of)
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.status,'-',mt.transaction_of)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'hotel_refund' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'additional_payment' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'creditor' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'debtor' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'expense' THEN CONCAT(mt.transaction_of)
                            WHEN mt.transaction_of = 'transfer' THEN CONCAT(mt.transaction_of)
                            ELSE ''
                        END), 'N/A'
                    ) AS details,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN tb.departure_date
                            WHEN mt.transaction_of = 'ticket_reserve' THEN tr.departure_date
                            WHEN mt.transaction_of = 'ticket_refund' THEN rt.departure_date
                            WHEN mt.transaction_of = 'date_change' THEN dc.departure_date
                            WHEN mt.transaction_of = 'visa_sale' THEN vs.applied_date
                            WHEN mt.transaction_of = 'hotel' THEN hb.check_in_date
                            WHEN mt.transaction_of = 'umrah' THEN um.flight_date
                            WHEN mt.transaction_of = 'fund' THEN ' '
                            WHEN mt.transaction_of = 'additional_payment' THEN ' '
                            WHEN mt.transaction_of = 'creditor' THEN ' '
                            WHEN mt.transaction_of = 'debtor' THEN ' '
                            WHEN mt.transaction_of = 'expense' THEN ' '
                            WHEN mt.transaction_of = 'transfer' THEN ' '
                            ELSE NULL
                        END), 'N/A'
                    ) AS departure_date,
                    COALESCE(
                        (CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.origin,'-',tb.destination)
                            WHEN mt.transaction_of = 'ticket_reserve' THEN CONCAT(tr.origin,'-',tr.destination)
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin,'-',rt.destination)
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.origin,'-',dc.destination)
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.country,'-',vs.visa_type)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(um.room_type,'-',um.duration)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(' ')
                            WHEN mt.transaction_of = 'additional_payment' THEN CONCAT('')
                            WHEN mt.transaction_of = 'creditor' THEN CONCAT('')
                            WHEN mt.transaction_of = 'debtor' THEN CONCAT('')
                            WHEN mt.transaction_of = 'expense' THEN CONCAT('')
                            WHEN mt.transaction_of = 'transfer' THEN CONCAT('')
                            ELSE ''
                        END), 'N/A'
                    ) AS sector,
                    mt.description as remark
                FROM main_account_transactions mt
                LEFT JOIN ticket_bookings tb ON tb.id = mt.reference_id AND mt.transaction_of = 'ticket_sale'
                LEFT JOIN ticket_reservations tr ON tr.id = mt.reference_id AND mt.transaction_of = 'ticket_reserve'
                LEFT JOIN refunded_tickets rt ON rt.id = mt.reference_id AND mt.transaction_of = 'ticket_refund'
                LEFT JOIN date_change_tickets dc ON dc.id = mt.reference_id AND mt.transaction_of = 'date_change'
                LEFT JOIN visa_applications vs ON vs.id = mt.reference_id AND mt.transaction_of = 'visa_sale'
                LEFT JOIN hotel_bookings hb ON hb.id = mt.reference_id AND mt.transaction_of = 'hotel'
                LEFT JOIN umrah_bookings um ON um.booking_id = mt.reference_id AND mt.transaction_of = 'umrah'
                LEFT JOIN users usr ON usr.id = mt.reference_id AND mt.transaction_of = 'fund'
                LEFT JOIN additional_payments ap ON ap.id = mt.reference_id AND mt.transaction_of = 'additional_payment'
                LEFT JOIN creditor_transactions ct ON ct.id = mt.reference_id AND mt.transaction_of = 'creditor'
                LEFT JOIN creditors c ON c.id = ct.creditor_id
                LEFT JOIN debtor_transactions dt ON dt.id = mt.reference_id AND mt.transaction_of = 'debtor'
                LEFT JOIN debtors d ON d.id = dt.debtor_id
                LEFT JOIN expenses exp ON exp.id = mt.reference_id AND mt.transaction_of = 'expense'
                LEFT JOIN expense_categories ec ON ec.id = exp.category_id
                WHERE mt.currency = ?
                AND DATE(mt.created_at) BETWEEN ? AND ?
                ORDER BY mt.created_at ASC, mt.id ASC";

            $stmt = $pdo->prepare($transactionsQuery);
            $stmt->execute([$currency, $startDate, $endDate]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            
            $title = "Main Account Statement";
            break;
            
        default:
            throw new Exception('Invalid report type specified');
    }

    // Get company settings
    $settingsQuery = "SELECT * FROM settings WHERE id = 1";
    $stmt = $pdo->prepare($settingsQuery);
    $stmt->execute();
    $companySettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate totals for transactions in date range (for display in transactions table)
    $periodDebit = 0;
    $periodCredit = 0;
    
    foreach ($transactions as $transaction) {
        if (strtolower($transaction['type']) == 'debit') {
            $periodDebit += $transaction['amount'];
        } else if (strtolower($transaction['type']) == 'credit') {
            $periodCredit += $transaction['amount'];
        }
    }
    
    // Calculate total debit and credit from all transactions
    $totalDebit = 0;
    $totalCredit = 0;
    
    if ($reportType == 'client') {
        // For client reports, use the full list of transactions
        foreach ($allTransactions as $transaction) {
            if (strtolower($transaction['type']) == 'debit') {
                $totalDebit += $transaction['amount'];
            } else if (strtolower($transaction['type']) == 'credit') {
                $totalCredit += $transaction['amount'];
            }
        }
        
        // Get the current balance directly from clients table based on currency
        $balanceField = strtolower($currency) == 'usd' ? 'usd_balance' : 
                       (strtolower($currency) == 'afs' ? 'afs_balance' : 'balance');
        
        $balanceQuery = "SELECT $balanceField AS balance FROM clients WHERE id = ?";
        $stmt = $pdo->prepare($balanceQuery);
        $stmt->execute([$entity]);
        $clientBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $finalBalance = $clientBalance['balance'] ?? (!empty($allTransactions) ? end($allTransactions)['balance'] : 0);
        
    } else if ($reportType == 'supplier') {
        // Get total debits and credits for this supplier
        $totalQuery = "
            SELECT 
                SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit
            FROM supplier_transactions
            WHERE supplier_id = ?";
            
        $stmt = $pdo->prepare($totalQuery);
        $stmt->execute([$entity]);
        $totalData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get current balance directly from suppliers table (suppliers have a balance field)
        $balanceQuery = "SELECT balance FROM suppliers WHERE id = ?";
        $stmt = $pdo->prepare($balanceQuery);
        $stmt->execute([$entity]);
        $supplierBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalDebit = $totalData['total_debit'] ?? 0;
        $totalCredit = $totalData['total_credit'] ?? 0;
        $finalBalance = $supplierBalance['balance'] ?? 0;
        
    } else if ($reportType == 'main_account') {
        // Get total debits and credits for main account in this currency
        $totalQuery = "
            SELECT 
                SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credit
            FROM main_account_transactions
            WHERE currency = ?";
            
        $stmt = $pdo->prepare($totalQuery);
        $stmt->execute([$currency]);
        $totalData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get current balance directly from main_account table based on currency
        $balanceField = strtolower($currency) == 'usd' ? 'usd_balance' : 
                       (strtolower($currency) == 'afs' ? 'afs_balance' : 
                       (strtolower($currency) == 'darham' ? 'darham_balance' :
                       (strtolower($currency) == 'euro' ? 'euro_balance' : 'balance')));
                       
        $balanceQuery = "SELECT $balanceField AS balance FROM main_account WHERE id = ?";
        $stmt = $pdo->prepare($balanceQuery);
        $stmt->execute([$entity]);
        $accountBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalDebit = $totalData['total_debit'] ?? 0;
        $totalCredit = $totalData['total_credit'] ?? 0;
        $finalBalance = $accountBalance['balance'] ?? 0;
    }

    switch($format) {
        case 'pdf':
            // Start with completely clean slate - empty the output buffer
            // This is critical to prevent "TCPDF ERROR: Some data has already been output"
            if (ob_get_length() > 0) {
                ob_end_clean();
            }
            
            // Start again with a fresh output buffer
            ob_start();
            
            try {
                // Create custom PDF document
                class MYPDF extends TCPDF {
                    // Page footer
                    public function Footer() {
                        // Position at 15 mm from bottom
                        $this->SetY(-15);
                        // Set font
                        $this->SetFont('helvetica', 'I', 8);
                        // Draw line
                        $this->Line(12, $this->GetY(), $this->getPageWidth() - 12, $this->GetY());
                        $this->Ln(1);
                        // Page number
                        $this->Cell(0, 10, 'Generated on ' . date('F d, Y') . ' | Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
                    }
                }
            
                // Create PDF with custom footer
                $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor($companySettings['agency_name']);
            $pdf->SetTitle($title);

                // Remove default header but use custom footer
            $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(true);

            // Add a page with consistent margins
            $pdf->SetMargins(12, 12, 12);
            $pdf->SetAutoPageBreak(true, 20);
            $pdf->AddPage('L', 'A4');

            // Professional styling colors
            $colors = [
                'primary' => [15, 81, 50],      // #0F5132 - Dark Green
                'secondary' => [95, 99, 104],   // #5F6368 - Google Gray
                'text' => [71, 85, 105],        // #475569 - Medium Gray
                'lightBg' => [232, 241, 238],   // #E8F1EE - Light Blue-Gray
                'border' => [226, 232, 240],    // #E2E8F0 - Border Gray
                'accent' => [31, 41, 59],       // #1E293B - Dark Blue-Gray
                'altRow' => [248, 250, 252]     // #F8FAFC - Light Gray
            ];

            // Calculate dimensions with golden ratio
            $pageWidth = $pdf->getPageWidth();
            $margins = 12;
            $columnWidth = ($pageWidth - (2 * $margins)) / 3.2;
            $logoWidth = 50;
            
            // Enhanced header background with professional gradient
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->RoundedRect($margins, 5, $pageWidth - (2 * $margins), 38, 4, '1111', 'F');
            
            // Left column - Professional company details
            $pdf->SetXY($margins + 2, 8);
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor(...$colors['primary']);
            $pdf->Cell($columnWidth, 9, strtoupper($companySettings['agency_name']), 0, 1, 'L');
            
            // Company info with professional icons and spacing
            $pdf->SetFont('helvetica', '', 9.5);
            $pdf->SetTextColor(...$colors['text']);
            
            // Address
            $pdf->SetX($margins + 2);
            $pdf->SetFont('helvetica', '', 9.5);
            $pdf->MultiCell($columnWidth - 6, 5, 'Address: ' . $companySettings['address'], 0, 'L');
            
            // Contact info with better spacing
            $contactInfo = [
                'Cell' => $companySettings['phone'],
                'Email' => $companySettings['email'],
                'CC' => $companySettings['cc_email'] ?? ''
            ];
            
            foreach ($contactInfo as $label => $value) {
                $pdf->SetX($margins + 2);
                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->Cell($columnWidth - 6, 5, $label . ': ' . $value, 0, 1, 'L');
            }

            // Centered Logo with enhanced quality
            $logoPath = '../uploads/' . $companySettings['logo'];
            if (file_exists($logoPath)) {
                $imgSize = getimagesize($logoPath);
                if ($imgSize !== false) {
                    $imgRatio = $imgSize[1] / $imgSize[0];
                    $logoHeight = $logoWidth * $imgRatio;
                    $logoY = 5 + ((38 - $logoHeight) / 2);
                    $pdf->Image($logoPath, ($pageWidth - $logoWidth) / 2, $logoY, $logoWidth, $logoHeight, '', '', '', true, 300);
                }
            }

            // Right column - Professional client card
            $rightX = $pageWidth - $margins - $columnWidth;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->RoundedRect($rightX, 8, $columnWidth, 32, 3, '1111', 'DF');
            
            // Statement title with modern accent
            $pdf->SetXY($rightX + 5, 10);
            $pdf->SetFillColor(...$colors['primary']);
            $pdf->Rect($rightX + 5, 10, 3, 6, 'F');
            $pdf->SetX($rightX + 10);
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetTextColor(...$colors['primary']);
            $pdf->Cell($columnWidth - 10, 6, 'CLIENT: ' . strtoupper($entityDetails['name'] ?? 'N/A'), 0, 1, 'R');
            
            // Client details with professional layout
            $pdf->SetTextColor(...$colors['text']);
            $clientInfo = [
                'Address' => $entityDetails['address'] ?? 'N/A',
                'Contact#' => $entityDetails['contact'] ?? $entityDetails['phone'] ?? 'N/A',
                'Email' => $entityDetails['email'] ?? 'N/A',
                'Currency' => $currency,
                'Period' => date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate))
            ];

            $yPos = 18;
            foreach ($clientInfo as $label => $value) {
                $pdf->SetXY($rightX + 8, $yPos);
                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->Cell($columnWidth - 10, 5, $label . ': ' . $value, 0, 0, 'R');
                $yPos += 5.5;
            }

            // Professional separator line
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor(...$colors['border']);
            $pdf->Line($margins, 47, $pageWidth - $margins, 47);
            
            // Reset colors
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(0, 0, 0);
            
            $pdf->Ln(12);

            // Table headers with adjusted widths
            $pdf->SetFont('helvetica', 'B', 11);
            // Align with Excel format
            $headers = ['Issue Date', 'Remarks', 'Inv.', 'Details', 'Dep Date', 'Debit', 'Credit', 'Balance'];
            
            // Calculate total available width
            $availableWidth = $pageWidth - (2 * $margins);
            
            // Adjusted column widths for 8-column layout with proportional distribution
            // The total should add up to exactly the available width
            $widths = array(
                $availableWidth * 0.09,  // Issue Date (9%)
                $availableWidth * 0.20,  // Remarks (20%)
                $availableWidth * 0.09,  // Inv. (9%)
                $availableWidth * 0.20,  // Details (20%)
                $availableWidth * 0.09,  // Dep Date (9%)
                $availableWidth * 0.11,  // Debit (11%)
                $availableWidth * 0.11,  // Credit (11%)
                $availableWidth * 0.11   // Balance (11%)
            );
            
            // Draw headers
            $startX = $margins;
            $pdf->SetFillColor(...$colors['primary']);
            foreach ($headers as $i => $header) {
                $pdf->SetX($startX);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell($widths[$i], 10, $header, 1, 0, 'C', true);
                $startX += $widths[$i];
            }
            $pdf->Ln();

            // Reset text color
            $pdf->SetTextColor(0, 0, 0);

                        // Use TCPDF's built-in table functionality for more reliable multi-page handling
            $pdf->SetFont('helvetica', '', 9);
            
            // Prepare table data for better pagination
            $tableData = [];
            foreach ($transactions as $transaction) {
                // Format data for each row
                $rowData = [
                    date('d-M-Y', strtotime($transaction['transaction_date'])),
                    (strlen($transaction['remark']) > 80) ? substr($transaction['remark'], 0, 77) . '...' : $transaction['remark'],
                    $transaction['receipt'],
                    (strlen($transaction['name']) > 80) ? substr($transaction['name'], 0, 77) . '...' : $transaction['name'],
                    $transaction['departure_date'] !== 'N/A' ? $transaction['departure_date'] : '',
                    strtolower($transaction['type']) == 'debit' ? number_format($transaction['amount'], 2) : '',
                    strtolower($transaction['type']) == 'credit' ? number_format($transaction['amount'], 2) : '',
                    number_format($transaction['balance'], 2)
                ];
                $tableData[] = $rowData;
            }
            
            // Set column alignments
            $alignments = ['C', 'L', 'C', 'L', 'C', 'R', 'R', 'R'];
            
            // Handle table pagination manually with reliable page breaks
            $rowCount = 0;
            $totalRows = count($tableData);
            
            // Define page header and table header function
            $drawTableHeader = function($pdf, $margins, $widths, $headers, $colors) {
                $startX = $margins;
                $pdf->SetFillColor(...$colors['primary']);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetTextColor(255, 255, 255);
                
                foreach ($headers as $i => $header) {
                    $pdf->SetX($startX);
                    $pdf->Cell($widths[$i], 10, $header, 1, 0, 'C', true);
                    $startX += $widths[$i];
                }
                $pdf->Ln(10);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('helvetica', '', 9);
            };
            
            // Draw first page headers
            $drawTableHeader($pdf, $margins, $widths, $headers, $colors);
            
            // Draw rows with page break handling
            foreach ($tableData as $row) {
                $rowCount++;
                $bgColor = ($rowCount % 2 == 0) ? $colors['altRow'] : [255, 255, 255];
                $pdf->SetFillColor(...$bgColor);
                
                // Check if we need a new page before drawing this row
                // Calculate approximate row height (more conservative estimate)
                $estimatedRowHeight = 8; // Default minimum height
                
                // Check for longer text that might wrap
                if (strlen($row[1]) > 30 || strlen($row[3]) > 30) {
                    $estimatedRowHeight = 12;
                }
                
                // Add extra for very long text
                if (strlen($row[1]) > 50 || strlen($row[3]) > 50) {
                    $estimatedRowHeight = 16;
                }
                
                // If this row won't fit on the current page, add a new page
                if ($pdf->GetY() + $estimatedRowHeight > $pdf->getPageHeight() - 25) {
                    $pdf->AddPage('L', 'A4');
                    
                    // Add mini header on new page
                    $pageWidth = $pdf->getPageWidth();
                    
                    // Add a mini header with page continuation info
                    $pdf->SetFillColor(...$colors['lightBg']);
                    $pdf->RoundedRect($margins, 5, $pageWidth - (2 * $margins), 20, 2, '1111', 'F');
                    
                    // Set entity name and statement title
                    $pdf->SetXY($margins + 5, 8);
                    $pdf->SetFont('helvetica', 'B', 12);
                    $pdf->SetTextColor(...$colors['primary']);
                    $pdf->Cell($pageWidth/2 - $margins, 6, strtoupper($entityDetails['name'] ?? 'N/A') . ' - ' . $title, 0, 1, 'L');
                    
                    // Add period info
                    $pdf->SetXY($margins + 5, 14);
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(...$colors['text']);
                    $pdf->Cell($pageWidth/2 - $margins, 6, 'Period: ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)), 0, 0, 'L');
                    
                    // Add page number on the right
                    $pdf->SetXY($pageWidth/2, 10);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell($pageWidth/2 - $margins - 5, 10, 'Page ' . $pdf->PageNo(), 0, 0, 'R');
                    
                    // Add a separator line
                    $pdf->SetLineWidth(0.3);
                    $pdf->SetDrawColor(...$colors['border']);
                    $pdf->Line($margins, 28, $pageWidth - $margins, 28);
                    
                    // Reset position for table continuation
                    $pdf->SetY(35);
                    
                    // Re-draw table header on new page
                    $drawTableHeader($pdf, $margins, $widths, $headers, $colors);
                }
                
                // Draw the row manually for better control
                $startX = $margins;
                $currentY = $pdf->GetY();
                $maxHeight = $estimatedRowHeight;
                
                // First pass to calculate actual row height
                $pdf->startTransaction();
                foreach ($row as $i => $text) {
                    $pdf->SetXY($startX, $currentY);
                    // Handle wrapping text columns differently
                    if ($i == 1 || $i == 3) { // Remarks and Details columns
                        $pdf->MultiCell($widths[$i], 5, $text, 0, $alignments[$i]);
                    } else {
                        $pdf->Cell($widths[$i], 5, $text, 0, 0, $alignments[$i]);
                    }
                    if ($i == 1 || $i == 3) {
                        $cellHeight = $pdf->GetY() - $currentY;
                        $maxHeight = max($maxHeight, $cellHeight);
                    }
                    $startX += $widths[$i];
                }
                $pdf->rollbackTransaction(true);
                
                // Second pass to draw the row with proper height
                $startX = $margins;
                foreach ($row as $i => $text) {
                    // Draw cell with background
                    $pdf->Rect($startX, $currentY, $widths[$i], $maxHeight, 'F', array(), $bgColor);
                    
                    // Draw cell border
                    $pdf->Rect($startX, $currentY, $widths[$i], $maxHeight);
                    
                    // Add text with proper alignment
                    if ($i == 1 || $i == 3) { // Remarks and Details columns that may need wrapping
                        $pdf->SetXY($startX, $currentY);
                        $pdf->MultiCell($widths[$i], 5, $text, 0, $alignments[$i]);
                    } else {
                        // Center text vertically for single line cells
                        $pdf->SetXY($startX, $currentY + ($maxHeight - 5) / 2);
                        $pdf->Cell($widths[$i], 5, $text, 0, 0, $alignments[$i]);
                    }
                    $startX += $widths[$i];
                }
                
                // Move to next row
                $pdf->SetY($currentY + $maxHeight);
            }
            
            // Add summary row for totals
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 11);
            
            // Summary table with new styling
            $summaryWidth = $availableWidth * 0.8; // Total width (80% of available width)
            $pdf->SetX(($pageWidth - $summaryWidth) / 2); // Center the summary table
            
            // Totals Header
            $pdf->SetFillColor(...$colors['primary']);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($summaryWidth, 10, 'STATEMENT SUMMARY', 1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            
            // Period totals
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->Cell($summaryWidth / 2, 8, 'Period Debit:', 1, 0, 'R', true);
            $pdf->Cell($summaryWidth / 2, 8, number_format($periodDebit, 2) . ' ' . $currency, 1, 1, 'R', true);
            
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->Cell($summaryWidth / 2, 8, 'Period Credit:', 1, 0, 'R', true);
            $pdf->Cell($summaryWidth / 2, 8, number_format($periodCredit, 2) . ' ' . $currency, 1, 1, 'R', true);
            
            // Total line
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->Cell($summaryWidth / 2, 8, 'Total Debit:', 1, 0, 'R', true);
            $pdf->Cell($summaryWidth / 2, 8, number_format($totalDebit, 2) . ' ' . $currency, 1, 1, 'R', true);
            
            // Credit Total
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->Cell($summaryWidth / 2, 8, 'Total Credit:', 1, 0, 'R', true);
            $pdf->Cell($summaryWidth / 2, 8, number_format($totalCredit, 2) . ' ' . $currency, 1, 1, 'R', true);
            
            // Balance
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetFillColor(...$colors['primary']);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($summaryWidth / 2, 8, 'Current Balance:', 1, 0, 'R', true);
            $pdf->Cell($summaryWidth / 2, 8, number_format($finalBalance, 2) . ' ' . $currency, 1, 1, 'R', true);
            
            // Add bank account details
            $pdf->Ln(5);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFillColor(...$colors['primary']);
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->Cell($summaryWidth / 2, 8, 'AUB Bank Account Details:', 1, 1, 'L', true);
            
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->Cell($summaryWidth / 2, 8, 'AFN Account: 125502AFS2114097', 1, 1, 'L', true);
            
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->Cell($summaryWidth / 2, 8, 'USD Account: 125502USD2112388', 1, 1, 'L', true);

                // Clean any output buffers again before output
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Directly output the PDF to the browser
                $filename = 'Statement_' . preg_replace('/[^A-Za-z0-9\-]/', '', $entityDetails['name']) . '_' . date('Y-m-d') . '.pdf';
                $pdf->Output($filename, 'D');  // D = download
                exit();
            } catch (Exception $e) {
                error_log('PDF Export Error: ' . $e->getMessage());
                throw new Exception('Error generating PDF file: ' . $e->getMessage());
            }
            break;

            case 'excel':
                try {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    
                    while (ob_get_level()) {
                        ob_end_clean();
                    }

                    // Enhanced styling configuration
                    $styles = [
                        'colors' => [
                            'primary' => '0F5132',      // Dark Green
                            'headerBg' => 'E8F1EE',     // Light Blue-Gray
                            'altRow' => 'F8FAFC',       // Light Gray
                            'border' => 'E2E8F0',       // Border Gray
                            'title' => '1E293B',        // Dark Blue-Gray
                            'text' => '475569'          // Medium Gray
                        ]
                    ];

                    // Set document properties and basic setup
                    $spreadsheet->getProperties()
                        ->setCreator($companySettings['agency_name'])
                        ->setTitle('Statement');

                    // Set column widths
                    $columnWidths = [
                        'A' => 15,  // Issue Date
                        'B' => 35,  // Remarks
                        'C' => 15,  // Invoice
                        'D' => 35,  // Name
                      
                        'E' => 15,  // Dep Date
                        
                    
                        'F' => 15,  // Debit
                        'G' => 15,  // Credit
                        'H' => 20   // Balance/Remarks
                    ];
                    foreach ($columnWidths as $col => $width) {
                        $sheet->getColumnDimension($col)->setWidth($width);
                    }

                    // Enhanced Header Section with Logo
                    if (file_exists('../uploads/' . $companySettings['logo'])) {
                        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                        $drawing->setName('Logo')
                               ->setDescription('Company Logo')
                               ->setPath('../uploads/' . $companySettings['logo'])
                               ->setCoordinates('D1')
                               ->setWidth(220)
                               ->setHeight(180)
                               ->setOffsetX(50)
                               ->setOffsetY(2)
                               ->setWorksheet($sheet);
                    }

                    // Company Details (Left Side) with enhanced styling
                    $sheet->setCellValue('A1', strtoupper($companySettings['agency_name']));
                    $sheet->mergeCells('A1:D2');
                    $sheet->getStyle('A1')->applyFromArray([
                        'font' => [
                            'bold' => true, 
                            'size' => 16, 
                            'color' => ['rgb' => $styles['colors']['primary']]
                        ],
                        'alignment' => [
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                        ]
                    ]);
                    
                    // Company contact details with enhanced styling
                    $contactInfo = [
                        'A3' => ['Address: ' . $companySettings['address']],
                        'A4' => ['Cell: ' . $companySettings['phone']],
                        'A5' => ['Email: ' . $companySettings['email']],
                        'A6' => ['CC: ' . ($companySettings['cc_email'] ?? '')]
                    ];

                    foreach ($contactInfo as $cell => $value) {
                        $sheet->setCellValue($cell, $value[0]);
                        $sheet->mergeCells($cell . ':D' . substr($cell, 1));
                        $sheet->getStyle($cell)->applyFromArray([
                            'font' => [
                                'size' => 11, 
                                'color' => ['rgb' => $styles['colors']['text']]
                            ],
                            'alignment' => [
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                            ],
                            'borders' => [
                                'bottom' => [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['rgb' => 'EEEEEE']
                                ]
                            ]
                        ]);
                        $sheet->getRowDimension(substr($cell, 1))->setRowHeight(22);
                    }

                    // Client Details (Right Side) with enhanced styling
                    $sheet->setCellValue('G1', 'CLIENT: ' . strtoupper($entityDetails['name'] ?? 'N/A'));
                    $sheet->mergeCells('G1:H2');
                    $sheet->getStyle('G1')->applyFromArray([
                        'font' => [
                            'bold' => true, 
                            'size' => 14, 
                            'color' => ['rgb' => $styles['colors']['primary']]
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                        ]
                    ]);

                    $clientInfo = [
                        'G3' => ['Address: ' . ($entityDetails['address'] ?? '')],
                        'G4' => ['Contact#: ' . ($entityDetails['phone'] ?? 'N/A')],
                        'G5' => ['Email: ' . ($entityDetails['email'] ?? '')],
                        'G6' => ['Currency: ' . $currency]
                    ];

                    foreach ($clientInfo as $cell => $value) {
                        $sheet->setCellValue($cell, $value[0]);
                        $sheet->mergeCells($cell . ':H' . substr($cell, 1));
                        $sheet->getStyle($cell)->applyFromArray([
                            'font' => [
                                'size' => 11, 
                                'color' => ['rgb' => $styles['colors']['text']]
                            ],
                            'alignment' => [
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                            ],
                            'borders' => [
                                'bottom' => [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['rgb' => 'EEEEEE']
                                ]
                            ]
                        ]);
                        $sheet->getRowDimension(substr($cell, 1))->setRowHeight(22);
                    }

                    // Add space before table headers
                    $sheet->getRowDimension(7)->setRowHeight(10);

                    // Headers with enhanced styling
                    $headers = ['Issue Date',  'Remarks', 'Inv.', 'Details', 'Dep Date', 'Debit', 'Credit', 'Balance'];
                    $sheet->fromArray([$headers], NULL, 'A8');

                    $sheet->getStyle('A8:H8')->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'FFFFFF'],
                            'size' => 11
                        ],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $styles['colors']['primary']]
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                        ],
                        'borders' => [
                            'bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
                        ]
                    ]);
                    $sheet->getRowDimension(8)->setRowHeight(25);

                    // Data rows with enhanced styling
                    $row = 9; // Start from row 9 since headers are at row 8
                    foreach ($transactions as $transaction) {
                        $data = [
                            date('d-M-y', strtotime($transaction['transaction_date'])),
                            $transaction['remark'],
                            $transaction['receipt'],
                            $transaction['name'],
                            $transaction['departure_date'],
                            
                            
                            strtolower($transaction['type']) == 'debit' ? $transaction['amount'] : '',
                            strtolower($transaction['type']) == 'credit' ? $transaction['amount'] : '',
                            $transaction['balance']
                        ];

                        $sheet->fromArray([$data], NULL, "A$row");

                        // Enhanced cell formatting for all columns
                        $rowRange = "A$row:H$row";
                        $sheet->getStyle($rowRange)->applyFromArray([
                            'font' => ['size' => 10],
                            'alignment' => [
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                            ],
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $row % 2 == 0 ? $styles['colors']['altRow'] : 'FFFFFF']
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['rgb' => 'EEEEEE']
                                ]
                            ]
                        ]);

                        // Special formatting for remarks column
                        $sheet->getStyle("B$row")->applyFromArray([
                            'alignment' => [
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                                'wrapText' => true
                            ]
                        ]);
                        // Special formatting for remarks column
                        $sheet->getStyle("D$row")->applyFromArray([
                            'alignment' => [
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                                'wrapText' => true
                            ]
                        ]);

                        // Right align numeric columns
                        $sheet->getStyle("F$row:H$row")->applyFromArray([
                            'alignment' => [
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
                            ]
                        ]);

                        // Format numbers
                        $sheet->getStyle("F$row:H$row")->getNumberFormat()
                              ->setFormatCode('#,##0.00');

                        // Auto-adjust row height based on content
                        $sheet->getRowDimension($row)->setRowHeight(-1);
                        
                        // Set minimum row height
                        $currentHeight = $sheet->getRowDimension($row)->getRowHeight();
                        if ($currentHeight < 25) {
                            $sheet->getRowDimension($row)->setRowHeight(25);
                        }

                        $row++;
                    }

                    // Freeze pane at the start of data
                    $sheet->freezePane('A9');
                    
                    // Add totals at the bottom with enhanced styling
                    $totalRow = $row + 1;
                    
                    // Add a separator row
                    $sheet->getRowDimension($row)->setRowHeight(10);
                    
                    // Add totals row
                    $sheet->setCellValue("A$totalRow", "TOTALS");
                    $sheet->setCellValue("F$totalRow", $totalDebit);
                    $sheet->setCellValue("G$totalRow", $totalCredit);
                    $sheet->setCellValue("H$totalRow", $finalBalance);
                    
                    // Style the totals row
                    $sheet->mergeCells("A$totalRow:E$totalRow");
                    $sheet->getStyle("A$totalRow:H$totalRow")->applyFromArray([
                        'font' => [
                            'bold' => true, 
                            'size' => 11,
                            'color' => ['rgb' => 'FFFFFF']
                        ],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $styles['colors']['primary']]
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC']
                            ]
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                        ]
                    ]);
                    
                    // Right align and format numbers for the total amounts
                    $sheet->getStyle("F$totalRow:H$totalRow")->getAlignment()
                         ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("F$totalRow:H$totalRow")->getNumberFormat()
                         ->setFormatCode('#,##0.00');
                    
                    // Add bank account details with proper formatting
                    $totalRow += 2; // Add some space
                    
                    // Merge cells for bank details header
                    $sheet->mergeCells("A$totalRow:C$totalRow");
                    $sheet->setCellValue("A$totalRow", "AUB Bank Account Details:");
                    $sheet->getStyle("A$totalRow:C$totalRow")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $styles['colors']['primary']]
                        ],
                        'font' => [
                            'color' => ['rgb' => 'FFFFFF']
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                        ]
                    ]);
                    
                    // AFN Account - Merge cells for better display
                    $totalRow++;
                    $sheet->mergeCells("A$totalRow:C$totalRow");
                    $sheet->setCellValue("A$totalRow", "AFN Account: 125502AFS2114097");
                    $sheet->getStyle("A$totalRow:C$totalRow")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E8F1EE']
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                        ]
                    ]);
                    
                    // USD Account - Merge cells for better display
                    $totalRow++;
                    $sheet->mergeCells("A$totalRow:C$totalRow");
                    $sheet->setCellValue("A$totalRow", "USD Account: 125502USD2112388");
                    $sheet->getStyle("A$totalRow:C$totalRow")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E8F1EE']
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
                        ]
                    ]);

                    // Set row height for bank details
                    $sheet->getRowDimension($totalRow-2)->setRowHeight(25);
                    $sheet->getRowDimension($totalRow-1)->setRowHeight(25);
                    $sheet->getRowDimension($totalRow)->setRowHeight(25);

                    // Output file
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment; filename="Statement_' . 
                           preg_replace('/[^A-Za-z0-9\-]/', '', $entityDetails['name']) . 
                           '_' . date('Y-m-d') . '.xlsx"');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');

                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $tempFile = tempnam(sys_get_temp_dir(), 'excel');
                    $writer->save($tempFile);
                    readfile($tempFile);
                    unlink($tempFile);

                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                    exit;

                } catch (Exception $e) {
                    error_log('Excel Export Error: ' . $e->getMessage());
                    throw new Exception('Error generating Excel file: ' . $e->getMessage());
                }
                break;

        case 'word':
            // For Word export, we'll use HTML format that MS Word can open
            // First, clean output buffer
            if (ob_get_length() > 0) {
                ob_end_clean();
            }
            
            // Start fresh output buffer
            ob_start();
            
            try {
                // File name for download
                $filename = 'Statement_' . preg_replace('/[^A-Za-z0-9\-]/', '', $entityDetails['name']) . '_' . date('Y-m-d') . '.doc';
                
                // Create enhanced HTML document with improved styling
                $html = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>' . $title . '</title>
                    <style>
                        body {
                            font-family: Arial, Helvetica, sans-serif;
                            margin: 20px;
                            color: #333333;
                            background-color: #ffffff;
                        }
                        
                        /* Enhanced Header Styling */
                        .header-container {
                            width: 100%;
                            background-color: #F7F9F9;
                            border-radius: 10px;
                            padding: 15px;
                            margin-bottom: 25px;
                            border: 1px solid #e0e0e0;
                            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                        }
                        
                        .header {
                            width: 100%;
                            display: table;
                        }
                        
                        .header-left {
                            display: table-cell;
                            width: 33%;
                            vertical-align: top;
                            padding-right: 15px;
                        }
                        
                        .header-center {
                            display: table-cell;
                            width: 34%;
                            text-align: center;
                            vertical-align: middle;
                        }
                        
                        .header-right {
                            display: table-cell;
                            width: 33%;
                            text-align: right;
                            vertical-align: top;
                            padding-left: 15px;
                        }
                        
                        .company-name {
                            font-size: 22px;
                            font-weight: bold;
                            color: #0F5132;
                            margin-bottom: 15px;
                            padding-bottom: 5px;
                            border-bottom: 2px solid #0F5132;
                            display: inline-block;
                        }
                        
                        .client-name {
                            font-size: 18px;
                            font-weight: bold;
                            color: #0F5132;
                            margin-bottom: 15px;
                            padding-bottom: 5px;
                            border-bottom: 2px solid #0F5132;
                            display: inline-block;
                        }
                        
                        .info-text {
                            font-size: 12px;
                            margin: 6px 0;
                            color: #555555;
                        }
                        
                        .label {
                            font-weight: bold;
                            color: #333333;
                        }
                        
                        .statement-title {
                            text-align: center;
                            font-size: 18px;
                            font-weight: bold;
                            color: #0F5132;
                            margin: 20px 0;
                            padding: 5px;
                            background-color: #f0f5f1;
                            border-radius: 5px;
                        }
                        
                        .period {
                            text-align: center;
                            margin-bottom: 15px;
                            font-style: italic;
                            font-size: 14px;
                            color: #666666;
                        }
                        
                        /* Enhanced Table Styling */
                        table.data-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 25px;
                            border: 1px solid #ccc;
                            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
                        }
                        
                        table.data-table th {
                            background-color: #0F5132;
                            color: white;
                            font-weight: bold;
                            padding: 10px;
                            text-align: center;
                            border: 1px solid #0a3e26;
                            font-size: 13px;
                        }
                        
                        table.data-table td {
                            padding: 8px;
                            border: 1px solid #ddd;
                            font-size: 12px;
                        }
                        
                        .text-center {
                            text-align: center;
                        }
                        
                        .text-right {
                            text-align: right;
                        }
                        
                        .alt-row {
                            background-color: #f2f7f4;
                        }
                        
                        .numeric-cell {
                            font-family: "Courier New", Courier, monospace;
                            font-weight: normal;
                        }
                        
                        /* Enhanced Summary Table Styling */
                        .summary-container {
                            background-color: #f7f9f9;
                            padding: 15px;
                            border-radius: 8px;
                            margin: 25px 0;
                            border: 1px solid #e0e0e0;
                        }
                        
                        .summary-table {
                            width: 60%;
                            margin: 0 auto;
                            border-collapse: collapse;
                            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
                        }
                        
                        .summary-header {
                            background-color: #0F5132;
                            color: white;
                            text-align: center;
                            font-weight: bold;
                            padding: 10px;
                            border: 1px solid #0a3e26;
                            font-size: 14px;
                        }
                        
                        .summary-label {
                            font-weight: bold;
                            text-align: right;
                            padding: 8px;
                            border: 1px solid #ddd;
                            background-color: #f9f9f9;
                        }
                        
                        .summary-value {
                            text-align: right;
                            padding: 8px;
                            border: 1px solid #ddd;
                            font-family: "Courier New", Courier, monospace;
                        }
                        
                        .balance-row .summary-label {
                            background-color: #e8f1ee;
                        }
                        
                        .balance-row .summary-value {
                            background-color: #e8f1ee;
                            font-weight: bold;
                        }
                        
                        /* Enhanced Bank Details Styling */
                        .bank-details {
                            width: 40%;
                            margin: 20px auto;
                        }
                        
                        .bank-header {
                            background-color: #0F5132;
                            color: white;
                            text-align: center;
                            font-weight: bold;
                            padding: 8px;
                            border: 1px solid #0a3e26;
                            border-radius: 5px 5px 0 0;
                        }
                        
                        .bank-content {
                            border: 1px solid #ddd;
                            border-top: none;
                            padding: 10px;
                            background-color: #f9f9f9;
                            border-radius: 0 0 5px 5px;
                        }
                        
                        .account-info {
                            padding: 5px 10px;
                            font-family: "Courier New", Courier, monospace;
                        }
                        
                        /* Page Footer */
                        .footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 11px;
                            color: #777;
                            padding-top: 5px;
                            border-top: 1px solid #ddd;
                        }
                    </style>
                </head>
                <body>
                    <div class="header-container">
                        <div class="header">
                            <div class="header-left">
                                <div class="company-name">' . strtoupper($companySettings['agency_name']) . '</div>
                                <div class="info-text"><span class="label">Address:</span> ' . $companySettings['address'] . '</div>
                                <div class="info-text"><span class="label">Phone:</span> ' . $companySettings['phone'] . '</div>
                                <div class="info-text"><span class="label">Email:</span> ' . $companySettings['email'] . '</div>';
                            
                if (!empty($companySettings['cc_email'])) {
                    $html .= '<div class="info-text"><span class="label">CC:</span> ' . $companySettings['cc_email'] . '</div>';
                }
                
                $html .= '</div>
                            <div class="header-center">
                                <!-- Logo position -->
                            </div>
                            <div class="header-right">
                                <div class="client-name">CLIENT: ' . strtoupper($entityDetails['name'] ?? 'N/A') . '</div>
                                <div class="info-text"><span class="label">Address:</span> ' . ($entityDetails['address'] ?? 'N/A') . '</div>
                                <div class="info-text"><span class="label">Contact#:</span> ' . ($entityDetails['contact'] ?? $entityDetails['phone'] ?? 'N/A') . '</div>
                                <div class="info-text"><span class="label">Email:</span> ' . ($entityDetails['email'] ?? 'N/A') . '</div>
                                <div class="info-text"><span class="label">Currency:</span> ' . $currency . '</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="statement-title">' . $title . '</div>
                    <div class="period">Period: ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)) . '</div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 9%">Date</th>
                                <th style="width: 20%">Remarks</th>
                                <th style="width: 8%">Inv.</th>
                                <th style="width: 20%">Details</th>
                                <th style="width: 9%">Dep Date</th>
                                <th style="width: 11%">Debit</th>
                                <th style="width: 11%">Credit</th>
                                <th style="width: 12%">Balance</th>
                            </tr>
                        </thead>
                        <tbody>';
                
                // Add table rows with transaction data
                $rowCount = 0;
                foreach ($transactions as $transaction) {
                    $rowCount++;
                    $rowClass = ($rowCount % 2 == 0) ? 'class="alt-row"' : '';
                    
                    // Format the date values properly
                    $transactionDate = date('d-M-Y', strtotime($transaction['transaction_date']));
                    
                    // Format departure date if it's a valid date
                    $depDate = $transaction['departure_date'];
                    if ($depDate != 'N/A' && strtotime($depDate)) {
                        $depDate = date('d-M-Y', strtotime($depDate));
                    }
                    
                    $html .= '<tr ' . $rowClass . '>';
                    $html .= '<td class="text-center">' . $transactionDate . '</td>';
                    $html .= '<td>' . htmlspecialchars(substr($transaction['remark'], 0, 100)) . '</td>';
                    $html .= '<td class="text-center">' . htmlspecialchars($transaction['receipt']) . '</td>';
                    $html .= '<td>' . htmlspecialchars(substr($transaction['name'], 0, 100)) . '</td>';
                    $html .= '<td class="text-center">' . htmlspecialchars($depDate) . '</td>';
                    $html .= '<td class="text-right numeric-cell">' . (strtolower($transaction['type']) == 'debit' ? number_format($transaction['amount'], 2) : '') . '</td>';
                    $html .= '<td class="text-right numeric-cell">' . (strtolower($transaction['type']) == 'credit' ? number_format($transaction['amount'], 2) : '') . '</td>';
                    $html .= '<td class="text-right numeric-cell">' . number_format($transaction['balance'], 2) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>
                    </table>
                    
                    <div class="summary-container">
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th colspan="2" class="summary-header">STATEMENT SUMMARY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="summary-label">Period Debit:</td>
                                    <td class="summary-value">' . number_format($periodDebit, 2) . ' ' . $currency . '</td>
                                </tr>
                                <tr>
                                    <td class="summary-label">Period Credit:</td>
                                    <td class="summary-value">' . number_format($periodCredit, 2) . ' ' . $currency . '</td>
                                </tr>
                                <tr>
                                    <td class="summary-label">Total Debit:</td>
                                    <td class="summary-value">' . number_format($totalDebit, 2) . ' ' . $currency . '</td>
                                </tr>
                                <tr>
                                    <td class="summary-label">Total Credit:</td>
                                    <td class="summary-value">' . number_format($totalCredit, 2) . ' ' . $currency . '</td>
                                </tr>
                                <tr class="balance-row">
                                    <td class="summary-label">Current Balance:</td>
                                    <td class="summary-value">' . number_format($finalBalance, 2) . ' ' . $currency . '</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="bank-details">
                        <div class="bank-header">AUB Bank Account Details</div>
                        <div class="bank-content">
                            <div class="account-info">AFN Account: 125502AFS2114097</div>
                            <div class="account-info">USD Account: 125502USD2112388</div>
                        </div>
                    </div>
                    
                    <div class="footer">
                        Generated on ' . date('d F Y') . ' | ' . $companySettings['agency_name'] . ' | This is a computer generated document.
                    </div>
                </body>
                </html>';
                
                // Output the HTML as a Word document
                header('Content-Type: application/msword');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                echo $html;
                
                exit();

            } catch (Exception $e) {
                error_log('Word Export Error: ' . $e->getMessage());
                throw new Exception('Error generating Word file: ' . $e->getMessage());
            }
            break;

        default:
            throw new Exception('Invalid format specified');
    }

} catch (Exception $e) {
    // Clean output buffer in case of error
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log('Statement Export Error: ' . $e->getMessage());
    header("Location: report.php?error=" . urlencode('Error generating statement: ' . $e->getMessage()));
    exit();
}
?> 