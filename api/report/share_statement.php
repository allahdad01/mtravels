<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../admin/security.php';
enforce_auth();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once('../../includes/db.php');
require_once('../../vendor/autoload.php');

try {
    $reportType = $_GET['reportType'] ?? 'client';
    $entity     = $_GET['entity'] ?? '';
    $startDate  = $_GET['startDate'] ?? '';
    $endDate    = $_GET['endDate'] ?? '';
    $currency   = $_GET['currency'] ?? 'USD';

    if (!$entity || !$startDate || !$endDate) {
        throw new Exception('Missing required parameters');
    }

    switch ($reportType) {
        case 'client':
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$entity, $tenant_id, $branch_id]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            $title = "Client Statement of Account";
            break;

        case 'supplier':
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$entity, $tenant_id, $branch_id]);
            $entityDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            $title = "Supplier Statement of Account";
            break;

        default:
            throw new Exception('Invalid report type');
    }

    if (!$entityDetails) {
        throw new Exception('Entity not found');
    }

    $settingsQuery = "SELECT * FROM settings WHERE tenant_id = ?";
    $stmt = $pdo->prepare($settingsQuery);
    $stmt->execute([$tenant_id]);
    $companySettings = $stmt->fetch(PDO::FETCH_ASSOC);

    $branchQuery = "SELECT * FROM branches WHERE tenant_id = ? AND id = ?";
    $stmt = $pdo->prepare($branchQuery);
    $stmt->execute([$tenant_id, $branch_id]);
    $branchDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    $bankAccounts = [];
    try {
        $bankQuery = "SELECT name, bank_name, bank_account_number, bank_account_afs_number FROM main_account WHERE tenant_id = ? AND branch_id = ? AND status = 'active' AND account_type = 'bank' AND bank_account_number IS NOT NULL AND bank_account_number <> '' ORDER BY name";
        $stmt = $pdo->prepare($bankQuery);
        $stmt->execute([$tenant_id, $branch_id]);
        $bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $bankAccounts = [];
    }

    if ($reportType === 'client') {
        $transactionsQuery = "
            SELECT 
                CASE 
                    WHEN ct.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                    WHEN ct.transaction_of = 'weight_sale' THEN DATE(twt.issue_date)
                    WHEN ct.transaction_of = 'ticket_reserve' THEN DATE(tr.issue_date)
                    WHEN ct.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                    WHEN ct.transaction_of = 'date_change' THEN DATE(dc.created_at)
                    WHEN ct.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                    WHEN ct.transaction_of = 'visa_refund' THEN DATE(vr.refund_date)
                    WHEN ct.transaction_of = 'umrah' THEN DATE(um.entry_date)
                    WHEN ct.transaction_of = 'umrah_refund' THEN DATE(umr.entry_date)
                    WHEN ct.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                    WHEN ct.transaction_of = 'hotel_refund' THEN DATE(hr.created_at)
                    WHEN ct.transaction_of = 'fund' THEN DATE(ct.created_at)
                    ELSE DATE(ct.created_at)
                END as transaction_date,
                ct.type, ct.amount, ct.description, ct.transaction_of, ct.reference_id,
                ct.balance, ct.receipt, ct.currency, ct.exchange_rate, ct.id as transaction_id,
                COALESCE(
                    (CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr, ' - ', tb.airline)
                        WHEN ct.transaction_of = 'weight_sale' THEN CONCAT(twt.passenger_name, ' - ', twt.pnr, ' - ', twt.airline)
                        WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name, ' - ', tr.pnr, ' - ', tr.airline)
                        WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr, ' - ', rt.airline)
                        WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr, ' - ', dc.airline)
                        WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name)
                        WHEN ct.transaction_of = 'visa_refund' THEN CONCAT(vsa.applicant_name)
                        WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.name)
                        WHEN ct.transaction_of = 'umrah_refund' THEN CONCAT(umr.name)
                        WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name)
                        WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hbr.title, ' ', hbr.first_name, ' ', hbr.last_name)
                        WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.name)
                        ELSE ''
                    END), 'N/A'
                ) AS name,
                COALESCE(
                    (CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.pnr)
                        WHEN ct.transaction_of = 'weight_sale' THEN CONCAT(twt.pnr)
                        WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.pnr)
                        WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.pnr)
                        WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.pnr)
                        WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.passport_number)
                        WHEN ct.transaction_of = 'visa_refund' THEN CONCAT(vsa.passport_number)
                        WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.passport_number)
                        WHEN ct.transaction_of = 'umrah_refund' THEN CONCAT(umr.passport_number)
                        WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.order_id)
                        WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hbr.order_id)
                        WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.role)
                        ELSE ''
                    END), 'N/A'
                ) AS pnr,
                COALESCE(
                    (CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.airline, ' - ', ct.transaction_of)
                        WHEN ct.transaction_of = 'weight_sale' THEN CONCAT(twt.airline, ' - Weight: ', tw.weight, 'kg')
                        WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.airline, ' - ', ct.transaction_of)
                        WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.airline, ' - ', ct.transaction_of)
                        WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.airline, ' - ', ct.transaction_of)
                        WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.status, ' - ', ct.transaction_of)
                        WHEN ct.transaction_of = 'visa_refund' THEN CONCAT(vsa.status, ' - ', ct.transaction_of)
                        WHEN ct.transaction_of = 'umrah' THEN CONCAT(ct.transaction_of)
                        WHEN ct.transaction_of = 'umrah_refund' THEN CONCAT(ct.transaction_of)
                        WHEN ct.transaction_of = 'hotel' THEN ct.transaction_of
                        WHEN ct.transaction_of = 'fund' THEN ct.transaction_of
                        WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(ct.transaction_of)
                        ELSE ''
                    END), 'N/A'
                ) AS details,
                COALESCE(
                    (CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.departure_date)
                        WHEN ct.transaction_of = 'weight_sale' THEN CONCAT(twt.departure_date)
                        WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.departure_date)
                        WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.departure_date)
                        WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.departure_date)
                        WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applied_date)
                        WHEN ct.transaction_of = 'visa_refund' THEN CONCAT(vr.refund_date)
                        WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.entry_date)
                        WHEN ct.transaction_of = 'umrah_refund' THEN CONCAT(umr.entry_date)
                        WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.check_in_date)
                        WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hbr.check_in_date)
                        ELSE ''
                    END), 'N/A'
                ) AS departure_date,
                COALESCE(
                    (CASE 
                        WHEN ct.transaction_of = 'ticket_sale' THEN 
                            CASE WHEN tb.trip_type = 'round_trip' THEN CONCAT(tb.origin, '-', tb.destination, '-', tb.return_destination)
                            ELSE CONCAT(tb.origin, '-', tb.destination) END
                        WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin, '-', rt.destination)
                        WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.origin, '-', dc.destination)
                        WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.country, '-', vs.visa_type)
                        WHEN ct.transaction_of = 'visa_refund' THEN CONCAT(vsa.country, '-', vsa.visa_type)
                        WHEN ct.transaction_of = 'umrah' THEN CONCAT(um.room_type, '-', um.duration)
                        WHEN ct.transaction_of = 'umrah_refund' THEN CONCAT(umr.room_type, '-', umr.duration)
                        WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                        WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hbr.accommodation_details)
                        ELSE ''
                    END), 'N/A'
                ) AS sector,
                COALESCE(ct.description, 'N/A') AS remark
            FROM client_transactions ct
            LEFT JOIN ticket_bookings tb ON tb.id = ct.reference_id AND ct.transaction_of = 'ticket_sale'
            LEFT JOIN ticket_weights tw ON tw.id = ct.reference_id AND ct.transaction_of = 'weight_sale'
            LEFT JOIN ticket_bookings twt ON twt.id = tw.ticket_id
            LEFT JOIN ticket_reservations tr ON tr.id = ct.reference_id AND ct.transaction_of = 'ticket_reserve'
            LEFT JOIN users usr ON usr.id = ct.reference_id AND ct.transaction_of = 'fund'
            LEFT JOIN refunded_tickets rt ON rt.id = ct.reference_id AND ct.transaction_of = 'ticket_refund'
            LEFT JOIN date_change_tickets dc ON dc.id = ct.reference_id AND ct.transaction_of = 'date_change'
            LEFT JOIN visa_applications vs ON vs.id = ct.reference_id AND ct.transaction_of = 'visa_sale'
            LEFT JOIN visa_refunds vr ON vr.id = ct.reference_id AND ct.transaction_of = 'visa_refund'
            LEFT JOIN visa_applications vsa ON vsa.id = vr.visa_id AND ct.transaction_of = 'visa_refund'
            LEFT JOIN umrah_bookings um ON um.booking_id = ct.reference_id AND ct.transaction_of = 'umrah'
            LEFT JOIN umrah_refunds ur ON ur.id = ct.reference_id AND ct.transaction_of = 'umrah_refund'
            LEFT JOIN umrah_bookings umr ON umr.booking_id = ur.booking_id
            LEFT JOIN hotel_bookings hb ON hb.id = ct.reference_id AND ct.transaction_of = 'hotel'
            LEFT JOIN hotel_refunds hr ON hr.id = ct.reference_id AND ct.transaction_of = 'hotel_refund'
            LEFT JOIN hotel_bookings hbr ON hbr.id = hr.booking_id
            WHERE ct.client_id = ? AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.id ASC";

        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$entity, $tenant_id, $branch_id]);
        $rawTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processedTransactions = [];
        $receiptGroups = [];
        foreach ($rawTransactions as $transaction) {
            if (!empty($transaction['receipt'])) {
                $receiptGroups[$transaction['receipt']][] = $transaction;
            } else {
                if ($transaction['currency'] == $currency) {
                    $processedTransactions[] = $transaction;
                }
            }
        }
        foreach ($receiptGroups as $receipt => $group) {
            if (count($group) == 1) {
                if ($group[0]['currency'] == $currency) {
                    $processedTransactions[] = $group[0];
                }
            } else {
                $primaryTransaction = null;
                $totalConvertedAmount = 0;
                $hasTargetCurrency = false;
                foreach ($group as $transaction) {
                    if ($transaction['currency'] == $currency) {
                        $hasTargetCurrency = true;
                        $primaryTransaction = $transaction;
                        $totalConvertedAmount += $transaction['amount'];
                    }
                }
                if (!$hasTargetCurrency) continue;
                foreach ($group as $transaction) {
                    if ($transaction['currency'] != $currency && !empty($transaction['exchange_rate'])) {
                        $convertedAmount = $transaction['amount'] / $transaction['exchange_rate'];
                        $totalConvertedAmount += $convertedAmount;
                    }
                }
                if ($primaryTransaction) {
                    $primaryTransaction['amount'] = $totalConvertedAmount;
                    $processedTransactions[] = $primaryTransaction;
                }
            }
        }

        $transactions = [];
        foreach ($processedTransactions as $transaction) {
            $transactionDate = new DateTime($transaction['transaction_date']);
            $startDateObj = new DateTime($startDate);
            $endDateObj = new DateTime($endDate);
            if ($transactionDate >= $startDateObj && $transactionDate <= $endDateObj) {
                $transactions[] = $transaction;
            }
        }
        usort($transactions, function($a, $b) { return $a['transaction_id'] <=> $b['transaction_id']; });

        $balanceField = strtolower($currency) == 'usd' ? 'usd_balance' : 
                       (strtolower($currency) == 'afs' ? 'afs_balance' : 'balance');
        $balanceQuery = "SELECT $balanceField AS balance FROM clients WHERE id = ?";
        $stmt = $pdo->prepare($balanceQuery);
        $stmt->execute([$entity]);
        $clientBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        $finalBalance = $clientBalance['balance'] ?? 0;

    } else {
        $transactionsQuery = "
            SELECT 
                CASE 
                    WHEN st.transaction_of = 'ticket_sale' THEN DATE(tb.issue_date)
                    WHEN st.transaction_of = 'weight_sale' THEN DATE(twt.issue_date)
                    WHEN st.transaction_of = 'ticket_reserve' THEN DATE(tr.issue_date)
                    WHEN st.transaction_of = 'ticket_refund' THEN DATE(rt.created_at)
                    WHEN st.transaction_of = 'date_change' THEN DATE(dc.created_at)
                    WHEN st.transaction_of = 'visa_sale' THEN DATE(vs.receive_date)
                    WHEN st.transaction_of = 'visa_refund' THEN DATE(vr.refund_date)
                    WHEN st.transaction_of = 'hotel' THEN DATE(hb.issue_date)
                    WHEN st.transaction_of = 'umrah' THEN DATE(ub.entry_date)
                    WHEN st.transaction_of = 'fund' THEN DATE(st.transaction_date)
                    ELSE DATE(st.transaction_date)
                END as transaction_date,
                st.transaction_type as type, st.amount, st.transaction_of, st.reference_id,
                st.balance, st.receipt,
                COALESCE(
                    (CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name, ' - ', tb.pnr, ' - ', tb.airline)
                        WHEN st.transaction_of = 'weight_sale' THEN CONCAT(twt.passenger_name, ' - ', twt.pnr, ' - ', twt.airline)
                        WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name, ' - ', tr.pnr, ' - ', tr.airline)
                        WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name, ' - ', rt.pnr, ' - ', rt.airline)
                        WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name, ' - ', dc.pnr, ' - ', dc.airline)
                        WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name)
                        WHEN st.transaction_of = 'visa_refund' THEN CONCAT(vsa.applicant_name)
                        WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name)
                        WHEN st.transaction_of = 'umrah' THEN CONCAT(ub.name)
                        WHEN st.transaction_of = 'fund' THEN CONCAT(usr.name)
                        WHEN st.transaction_of = 'fund_withdrawal' THEN CONCAT(usrf.name)
                        WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name)
                        ELSE ''
                    END), 'N/A'
                ) AS name,
                COALESCE(
                    (CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.pnr)
                        WHEN st.transaction_of = 'weight_sale' THEN CONCAT(twt.pnr)
                        WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.pnr)
                        WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.pnr)
                        WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.pnr)
                        WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.passport_number)
                        WHEN st.transaction_of = 'visa_refund' THEN CONCAT(vsa.passport_number)
                        WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.order_id)
                        WHEN st.transaction_of = 'umrah' THEN CONCAT(ub.passport_number)
                        WHEN st.transaction_of = 'fund' THEN CONCAT(usr.role)
                        WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(hb.order_id)
                        ELSE ''
                    END), 'N/A'
                ) AS pnr,
                COALESCE(
                    (CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.airline, ' - ', st.transaction_of)
                        WHEN st.transaction_of = 'weight_sale' THEN CONCAT(twt.airline, ' - Weight: ', tw.weight, 'kg')
                        WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.airline, ' - ', st.transaction_of)
                        WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.airline, ' - ', st.transaction_of)
                        WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.airline, ' - ', st.transaction_of)
                        WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.status, ' - ', st.transaction_of)
                        WHEN st.transaction_of = 'visa_refund' THEN CONCAT(vsa.status, ' - ', st.transaction_of)
                        WHEN st.transaction_of = 'hotel' THEN st.transaction_of
                        WHEN st.transaction_of = 'umrah' THEN st.transaction_of
                        WHEN st.transaction_of = 'fund' THEN st.transaction_of
                        WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(st.transaction_of)
                        ELSE ''
                    END), 'N/A'
                ) AS details,
                COALESCE(
                    (CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN tb.departure_date
                        WHEN st.transaction_of = 'weight_sale' THEN twt.departure_date
                        WHEN st.transaction_of = 'ticket_reserve' THEN tr.departure_date
                        WHEN st.transaction_of = 'ticket_refund' THEN rt.departure_date
                        WHEN st.transaction_of = 'date_change' THEN dc.departure_date
                        WHEN st.transaction_of = 'visa_sale' THEN vs.applied_date
                        WHEN st.transaction_of = 'visa_refund' THEN vr.refund_date
                        WHEN st.transaction_of = 'hotel' THEN hb.check_in_date
                        WHEN st.transaction_of = 'umrah' THEN (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                            JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                            JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                            WHERE ubs2.booking_id = ub.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                            ORDER BY ff.id DESC LIMIT 1)
                        WHEN st.transaction_of = 'fund' THEN ' '
                        ELSE NULL
                    END), 'N/A'
                ) AS departure_date,
                COALESCE(
                    (CASE 
                        WHEN st.transaction_of = 'ticket_sale' THEN 
                            CASE WHEN tb.trip_type = 'round_trip' THEN CONCAT(tb.origin, '-', tb.destination, '-', tb.return_destination)
                            ELSE CONCAT(tb.origin, '-', tb.destination) END
                        WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.origin, '-', rt.destination)
                        WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.origin, '-', dc.destination)
                        WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.country, '-', vs.visa_type)
                        WHEN st.transaction_of = 'visa_refund' THEN CONCAT(vsa.country, '-', vsa.visa_type)
                        WHEN st.transaction_of = 'umrah' THEN CONCAT(ub.room_type, '-', ub.duration)
                        WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.accommodation_details)
                        ELSE ''
                    END), 'N/A'
                ) AS sector,
                COALESCE(st.remarks, 'N/A') AS remark
            FROM supplier_transactions st
            LEFT JOIN ticket_bookings tb ON tb.id = st.reference_id AND st.transaction_of = 'ticket_sale'
            LEFT JOIN ticket_weights tw ON tw.id = st.reference_id AND st.transaction_of = 'weight_sale'
            LEFT JOIN ticket_bookings twt ON twt.id = tw.ticket_id
            LEFT JOIN ticket_reservations tr ON tr.id = st.reference_id AND st.transaction_of = 'ticket_reserve'
            LEFT JOIN refunded_tickets rt ON rt.id = st.reference_id AND st.transaction_of = 'ticket_refund'
            LEFT JOIN date_change_tickets dc ON dc.id = st.reference_id AND st.transaction_of = 'date_change'
            LEFT JOIN visa_applications vs ON vs.id = st.reference_id AND st.transaction_of = 'visa_sale'
            LEFT JOIN visa_refunds vr ON vr.id = st.reference_id AND st.transaction_of = 'visa_refund'
            LEFT JOIN visa_applications vsa ON vsa.id = vr.visa_id AND st.transaction_of = 'visa_refund'
            LEFT JOIN hotel_bookings hb ON hb.id = st.reference_id AND st.transaction_of = 'hotel'
            LEFT JOIN umrah_bookings ub ON st.transaction_of = 'umrah' AND st.reference_id = ub.booking_id
            LEFT JOIN users usr ON usr.id = st.reference_id AND st.transaction_of = 'fund'
            LEFT JOIN users usrf ON usrf.id = st.reference_id AND st.transaction_of = 'fund_withdrawal'
            WHERE st.supplier_id = ? AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.transaction_date ASC, st.id ASC";

        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$entity, $tenant_id, $branch_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $balanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($balanceQuery);
        $stmt->execute([$entity, $tenant_id, $branch_id]);
        $supplierBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        $finalBalance = $supplierBalance['balance'] ?? 0;
    }

    $periodDebit = 0;
    $periodCredit = 0;
    foreach ($transactions as $transaction) {
        if (strtolower($transaction['type']) == 'debit') {
            $periodDebit += $transaction['amount'];
        } else if (strtolower($transaction['type']) == 'credit') {
            $periodCredit += $transaction['amount'];
        }
    }

    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($transactions as $transaction) {
        if (strtolower($transaction['type']) == 'debit') {
            $totalDebit += $transaction['amount'];
        } else if (strtolower($transaction['type']) == 'credit') {
            $totalCredit += $transaction['amount'];
        }
    }

    // Generate PDF
    while (ob_get_level()) { ob_end_clean(); }
    ob_start();

    class SharePDF extends TCPDF {
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Line(12, $this->GetY(), $this->getPageWidth() - 12, $this->GetY());
            $this->Ln(1);
            $this->Cell(0, 10, 'Generated on ' . date('F d, Y') . ' | Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    $pdf = new SharePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($branchDetails['name'] ?? $companySettings['agency_name']);
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage('L', 'A4');

    $colors = [
        'primary' => [15, 81, 50],
        'text' => [71, 85, 105],
        'lightBg' => [232, 241, 238],
        'border' => [226, 232, 240],
        'altRow' => [248, 250, 252]
    ];

    $pageWidth = $pdf->getPageWidth();
    $margins = 12;
    $columnWidth = ($pageWidth - (2 * $margins)) / 3.2;

    $pdf->SetFillColor(...$colors['lightBg']);
    $pdf->RoundedRect($margins, 5, $pageWidth - (2 * $margins), 38, 4, '1111', 'F');

    $pdf->SetXY($margins + 2, 8);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(...$colors['primary']);
    $pdf->Cell($columnWidth, 5, strtoupper($companySettings['agency_name']), 0, 1, 'L');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell($columnWidth, 4, strtoupper($branchDetails['name'] ?? ''), 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->SetTextColor(...$colors['text']);
    $pdf->SetX($margins + 2);
    $pdf->MultiCell($columnWidth - 6, 5, 'Address: ' . ($branchDetails['address'] ?? $companySettings['address']), 0, 'L');

    $contactInfo = [
        'Cell' => $branchDetails['phone'] ?? $companySettings['phone'],
        'Email' => $branchDetails['email'] ?? $companySettings['email'],
    ];
    foreach ($contactInfo as $label => $value) {
        $pdf->SetX($margins + 2);
        $pdf->Cell($columnWidth - 6, 5, $label . ': ' . $value, 0, 1, 'L');
    }

    $rightX = $pageWidth - $margins - $columnWidth;
    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($rightX, 8, $columnWidth, 32, 3, '1111', 'DF');

    $pdf->SetXY($rightX + 5, 10);
    $pdf->SetFillColor(...$colors['primary']);
    $pdf->Rect($rightX + 5, 10, 3, 6, 'F');
    $pdf->SetX($rightX + 10);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(...$colors['primary']);
    $pdf->Cell($columnWidth - 10, 6, strtoupper($reportType) . ': ' . strtoupper($entityDetails['name'] ?? 'N/A'), 0, 1, 'R');

    $pdf->SetTextColor(...$colors['text']);
    $entityInfo = [
        'Address' => $entityDetails['address'] ?? 'N/A',
        'Contact#' => $entityDetails['phone'] ?? 'N/A',
        'Email' => $entityDetails['email'] ?? 'N/A',
        'Currency' => $currency,
        'Period' => date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate))
    ];
    $yPos = 18;
    foreach ($entityInfo as $label => $value) {
        $pdf->SetXY($rightX + 8, $yPos);
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->Cell($columnWidth - 10, 5, $label . ': ' . $value, 0, 0, 'R');
        $yPos += 5.5;
    }

    $pdf->SetLineWidth(0.3);
    $pdf->SetDrawColor(...$colors['border']);
    $pdf->Line($margins, 47, $pageWidth - $margins, 47);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(12);

    $headers = ['Issue Date', 'Remarks', 'Inv.', 'Details', 'Dep Date', 'Debit', 'Credit', 'Balance'];
    $availableWidth = $pageWidth - (2 * $margins);
    $widths = array(
        $availableWidth * 0.09, $availableWidth * 0.20, $availableWidth * 0.09,
        $availableWidth * 0.20, $availableWidth * 0.09, $availableWidth * 0.11,
        $availableWidth * 0.11, $availableWidth * 0.11
    );

    $pdf->SetFont('helvetica', '', 8);
    $alignments = ['C', 'L', 'C', 'L', 'C', 'R', 'R', 'R'];

    $drawTableHeader = function($pdf, $margins, $widths, $headers, $colors) {
        $startX = $margins;
        $pdf->SetFillColor(...$colors['primary']);
        $pdf->SetFont('helvetica', 'B', 9);
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

    $drawTableHeader($pdf, $margins, $widths, $headers, $colors);

    $rowCount = 0;
    foreach ($transactions as $transaction) {
        $rowCount++;
        $bgColor = ($rowCount % 2 == 0) ? $colors['altRow'] : [255, 255, 255];
        $pdf->SetFillColor(...$bgColor);

        $row = [
            date('d-M-Y', strtotime($transaction['transaction_date'])),
            (strlen($transaction['remark']) > 80) ? substr($transaction['remark'], 0, 77) . '...' : $transaction['remark'],
            $transaction['receipt'],
            (strlen($transaction['name']) > 80) ? substr($transaction['name'], 0, 77) . '...' : $transaction['name'],
            $transaction['departure_date'] !== 'N/A' ? $transaction['departure_date'] : '',
            strtolower($transaction['type']) == 'debit' ? number_format($transaction['amount'], 2) : '',
            strtolower($transaction['type']) == 'credit' ? number_format($transaction['amount'], 2) : '',
            number_format($transaction['balance'], 2)
        ];

        $estimatedRowHeight = 6;
        if (strlen($row[1]) > 30 || strlen($row[3]) > 30) $estimatedRowHeight = 10;
        if (strlen($row[1]) > 50 || strlen($row[3]) > 50) $estimatedRowHeight = 14;

        if ($pdf->GetY() + $estimatedRowHeight > $pdf->getPageHeight() - 25) {
            $pdf->AddPage('L', 'A4');
            $pdf->SetFillColor(...$colors['lightBg']);
            $pdf->RoundedRect($margins, 5, $pageWidth - (2 * $margins), 20, 2, '1111', 'F');
            $pdf->SetXY($margins + 5, 8);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(...$colors['primary']);
            $pdf->Cell($pageWidth/2 - $margins, 6, strtoupper($entityDetails['name'] ?? 'N/A') . ' - ' . $title, 0, 1, 'L');
            $pdf->SetXY($margins + 5, 14);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(...$colors['text']);
            $pdf->Cell($pageWidth/2 - $margins, 6, 'Period: ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)), 0, 0, 'L');
            $pdf->SetY(35);
            $drawTableHeader($pdf, $margins, $widths, $headers, $colors);
        }

        $startX = $margins;
        $currentY = $pdf->GetY();
        $maxHeight = $estimatedRowHeight;

        $pdf->startTransaction();
        foreach ($row as $i => $text) {
            $pdf->SetXY($startX, $currentY);
            if ($i == 1 || $i == 3) {
                $pdf->MultiCell($widths[$i], 4, $text, 0, $alignments[$i]);
            } else {
                $pdf->Cell($widths[$i], 4, $text, 0, 0, $alignments[$i]);
            }
            if ($i == 1 || $i == 3) {
                $cellHeight = $pdf->GetY() - $currentY;
                $maxHeight = max($maxHeight, $cellHeight);
            }
            $startX += $widths[$i];
        }
        $pdf->rollbackTransaction(true);

        $startX = $margins;
        foreach ($row as $i => $text) {
            $pdf->Rect($startX, $currentY, $widths[$i], $maxHeight, 'F', array(), $bgColor);
            $pdf->Rect($startX, $currentY, $widths[$i], $maxHeight);
            if ($i == 1 || $i == 3) {
                $pdf->SetXY($startX, $currentY);
                $pdf->MultiCell($widths[$i], 4, $text, 0, $alignments[$i]);
            } else {
                $pdf->SetXY($startX, $currentY + ($maxHeight - 4) / 2);
                $pdf->Cell($widths[$i], 4, $text, 0, 0, $alignments[$i]);
            }
            $startX += $widths[$i];
        }
        $pdf->SetY($currentY + $maxHeight);
    }

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $summaryWidth = $availableWidth * 0.8;
    $pdf->SetX(($pageWidth - $summaryWidth) / 2);
    $pdf->SetFillColor(...$colors['primary']);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($summaryWidth, 10, 'STATEMENT SUMMARY', 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetX(($pageWidth - $summaryWidth) / 2);
    $pdf->SetFillColor(...$colors['lightBg']);
    $pdf->Cell($summaryWidth / 2, 8, 'Total Debit:', 1, 0, 'R', true);
    $pdf->Cell($summaryWidth / 2, 8, number_format($totalDebit, 2) . ' ' . $currency, 1, 1, 'R', true);

    $pdf->SetX(($pageWidth - $summaryWidth) / 2);
    $pdf->SetFillColor(...$colors['lightBg']);
    $pdf->Cell($summaryWidth / 2, 8, 'Total Credit:', 1, 0, 'R', true);
    $pdf->Cell($summaryWidth / 2, 8, number_format($totalCredit, 2) . ' ' . $currency, 1, 1, 'R', true);

    $pdf->SetX(($pageWidth - $summaryWidth) / 2);
    $pdf->SetFillColor(...$colors['primary']);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($summaryWidth / 2, 8, 'Current Balance:', 1, 0, 'R', true);
    $pdf->Cell($summaryWidth / 2, 8, number_format($finalBalance, 2) . ' ' . $currency, 1, 1, 'R', true);

    if (!empty($bankAccounts)) {
        $pdf->Ln(5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(...$colors['primary']);
        $pdf->SetX(($pageWidth - $summaryWidth) / 2);
        $pdf->Cell($summaryWidth, 8, 'Bank Account Details:', 1, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(...$colors['lightBg']);
        foreach ($bankAccounts as $bank) {
            $label = !empty($bank['bank_name']) ? $bank['bank_name'] : $bank['name'];
            $usd = trim((string)($bank['bank_account_number'] ?? ''));
            $afs = trim((string)($bank['bank_account_afs_number'] ?? ''));
            $pdf->SetX(($pageWidth - $summaryWidth) / 2);
            $pdf->Cell($summaryWidth, 8, $label, 1, 1, 'L', true);
            if ($usd !== '') { $pdf->SetX(($pageWidth - $summaryWidth) / 2); $pdf->Cell($summaryWidth, 8, 'USD Account: ' . $usd, 1, 1, 'L', true); }
            if ($afs !== '') { $pdf->SetX(($pageWidth - $summaryWidth) / 2); $pdf->Cell($summaryWidth, 8, 'AFS Account: ' . $afs, 1, 1, 'L', true); }
        }
    }

    while (ob_get_level()) { ob_end_clean(); }

    $token = bin2hex(random_bytes(16));
    $safeName = preg_replace('/[^A-Za-z0-9\-]/', '', $entityDetails['name']);
    $filename = $token . '_' . $safeName . '_' . date('Y-m-d') . '.pdf';
    $filepath = __DIR__ . '/../../uploads/temp_statements/' . $filename;

    $pdf->Output($filepath, 'F');

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . '/mtravels';
    $shareUrl = $baseUrl . '/uploads/temp_statements/' . $filename;

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'url' => $shareUrl,
        'filename' => $filename
    ]);

} catch (Exception $e) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
