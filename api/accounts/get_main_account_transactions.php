<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate account_id parameter
if (!isset($_GET['account_id']) || !is_numeric($_GET['account_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid account ID']);
    exit();
}

$accountId = intval($_GET['account_id']);

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(100000, intval($_GET['per_page']))) : 50;
$offset = ($page - 1) * $perPage;
$fetchAll = isset($_GET['per_page']);

// Database connection
require_once('../../includes/db.php');

// Get filter parameters
$currency = isset($_GET['currency']) && $_GET['currency'] !== 'all' ? $_GET['currency'] : null;
$receipt = isset($_GET['receipt']) ? trim($_GET['receipt']) : null;
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Build WHERE clause with filters
$whereConditions = ["mt.main_account_id = ? AND mt.tenant_id = ? AND mt.branch_id = ?"];
$params = [$accountId, $tenant_id, $branch_id];

if ($currency) {
    $whereConditions[] = "mt.currency = ?";
    $params[] = $currency;
}

if ($receipt) {
    $whereConditions[] = "mt.receipt LIKE ?";
    $params[] = '%' . $receipt . '%';
}

if ($startDate && $endDate) {
    $whereConditions[] = "DATE(mt.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereClause = implode(' AND ', $whereConditions);

// Count total transactions with filters
$countQuery = "SELECT COUNT(*) as total FROM main_account_transactions mt
              WHERE " . $whereClause;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalTransactions = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalTransactions / $perPage);

// Prepare and execute query with pagination and filters
$query = "SELECT mt.*, 
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
                             WHEN mt.transaction_of = 'umrah_hawala' THEN CONCAT(ca.customer_name, ' (Umrah Hawala)')
                  ELSE CONCAT(mt.reference_id, ' (', mt.transaction_of, ')')
              END AS reference_name
           FROM main_account_transactions mt
           LEFT JOIN ticket_bookings tb ON mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale'
           LEFT JOIN ticket_reservations tr ON mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve'
           LEFT JOIN refunded_tickets rt ON mt.reference_id = rt.id AND mt.transaction_of = 'ticket_refund'
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
           LEFT JOIN refunded_tickets rft ON mt.reference_id = rft.id AND mt.transaction_of = 'ticket_refund'
           LEFT JOIN hotel_bookings hb_txn ON mt.reference_id = hb_txn.id AND mt.transaction_of = 'hotel'
           LEFT JOIN hotel_refunds hr ON mt.reference_id = hr.id AND mt.transaction_of = 'hotel_refund'
           LEFT JOIN hotel_bookings hb_refund ON hr.booking_id = hb_refund.id AND mt.transaction_of = 'hotel_refund'
           LEFT JOIN visa_applications va_sale ON mt.reference_id = va_sale.id AND mt.transaction_of = 'visa_sale'
           LEFT JOIN umrah_refunds ur ON mt.reference_id = ur.id AND mt.transaction_of = 'umrah_refund'
           LEFT JOIN umrah_bookings ub_refund ON ur.booking_id = ub_refund.booking_id AND mt.transaction_of = 'umrah_refund'
           LEFT JOIN customer_advance_payments cap ON mt.reference_id = cap.id AND mt.transaction_of = 'umrah_hawala'
           LEFT JOIN customer_advances ca ON cap.advance_id = ca.id AND mt.transaction_of = 'umrah_hawala'
           WHERE " . $whereClause . "
           ORDER BY mt.id DESC" . ($fetchAll ? "" : " LIMIT ? OFFSET ?");

$stmt = $pdo->prepare($query);
if ($fetchAll) {
    $stmt->execute($params);
} else {
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($allParams);
}

// Fetch transactions for current page
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return transactions as JSON with pagination metadata
header('Content-Type: application/json');
echo json_encode([
    'data' => $transactions,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $totalTransactions,
        'total_pages' => $totalPages,
        'offset' => $offset
    ]
]);
