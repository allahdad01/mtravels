<?php
/**
 * Payments Journal — CSV export
 * Same filters as get_journal_entries.php, output as CSV download.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once __DIR__ . '/../../admin/security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

/* ── Input validation (same rules as get_journal_entries.php) ── */
$from_date = (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) ? $_GET['from_date'] : null;
$to_date   = (isset($_GET['to_date'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date']))   ? $_GET['to_date']   : null;
$module    = isset($_GET['module']) ? trim($_GET['module']) : '';
$currency  = isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : '';
$ledger    = isset($_GET['ledger']) ? trim($_GET['ledger']) : '';
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_currencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];
$allowed_ledgers    = ['main_account', 'client', 'supplier', 'jv'];
if ($currency !== '' && !in_array($currency, $allowed_currencies, true)) { $currency = ''; }
if ($ledger !== '' && !in_array($ledger, $allowed_ledgers, true)) { $ledger = ''; }
if (strlen($module) > 60) { $module = ''; }
if (strlen($search) > 255) { $search = substr($search, 0, 255); }

/* ── Default exchange rates ── */
$defaultRates = [];
try {
    $rateStmt = $pdo->prepare("SELECT from_currency, to_currency, rate FROM exchange_rates WHERE tenant_id = ? ORDER BY id DESC");
    $rateStmt->execute([$tenant_id]);
    while ($r = $rateStmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $r['from_currency'] . '_' . $r['to_currency'];
        if (!isset($defaultRates[$key]) && (float) $r['rate'] > 0) {
            $defaultRates[$key] = (float) $r['rate'];
        }
    }
} catch (PDOException $e) {
    $defaultRates = [];
}

/* ── UNION subquery ── */
$sql = "
SELECT * FROM (
    SELECT
        CONCAT('m', mat.id) AS row_key,
        'main_account' AS ledger,
        mat.created_at AS entry_date,
        mat.type AS type,
        mat.amount AS amount,
        mat.currency AS currency,
        mat.exchange_rate AS exchange_rate,
        mat.balance AS balance,
        mat.transaction_of AS module,
        mat.reference_id AS reference_id,
        mat.receipt AS receipt,
        mat.description AS description,
        ma.name AS account_name,
        NULL AS party_name
    FROM main_account_transactions mat
    LEFT JOIN main_account ma ON ma.id = mat.main_account_id AND ma.tenant_id = mat.tenant_id
    WHERE mat.tenant_id = ? AND mat.branch_id = ?

    UNION ALL

    SELECT
        CONCAT('c', ct.id) AS row_key,
        'client' AS ledger,
        ct.created_at AS entry_date,
        ct.type AS type,
        ct.amount AS amount,
        ct.currency AS currency,
        ct.exchange_rate AS exchange_rate,
        ct.balance AS balance,
        ct.transaction_of AS module,
        ct.reference_id AS reference_id,
        ct.receipt AS receipt,
        ct.description AS description,
        NULL AS account_name,
        cl.name AS party_name
    FROM client_transactions ct
    LEFT JOIN clients cl ON cl.id = ct.client_id AND cl.tenant_id = ct.tenant_id
    WHERE ct.tenant_id = ? AND ct.branch_id = ?

    UNION ALL

    SELECT
        CONCAT('s', st.id) AS row_key,
        'supplier' AS ledger,
        st.transaction_date AS entry_date,
        LOWER(st.transaction_type) AS type,
        st.amount AS amount,
        sp.currency AS currency,
        NULL AS exchange_rate,
        st.balance AS balance,
        st.transaction_of AS module,
        st.reference_id AS reference_id,
        st.receipt AS receipt,
        st.remarks AS description,
        NULL AS account_name,
        sp.name AS party_name
    FROM supplier_transactions st
    LEFT JOIN suppliers sp ON sp.id = st.supplier_id AND sp.tenant_id = st.tenant_id
    WHERE st.tenant_id = ? AND st.branch_id = ?

    UNION ALL

    SELECT
        CONCAT('j', jp.id) AS row_key,
        'jv' AS ledger,
        jp.created_at AS entry_date,
        'credit' AS type,
        jp.total_amount AS amount,
        jp.currency AS currency,
        jp.exchange_rate AS exchange_rate,
        NULL AS balance,
        'jv_payment' AS module,
        jp.id AS reference_id,
        jp.receipt AS receipt,
        jp.remarks AS description,
        NULL AS account_name,
        CONCAT(COALESCE(c.name, '—'), ' → ', COALESCE(s.name, '—')) AS party_name
    FROM jv_payments jp
    LEFT JOIN clients c ON c.id = jp.client_id AND c.tenant_id = jp.tenant_id
    LEFT JOIN suppliers s ON s.id = jp.supplier_id AND s.tenant_id = jp.tenant_id
    WHERE jp.tenant_id = ? AND jp.branch_id = ?
) t
WHERE 1 = 1
";

$filterSql = '';
$filterParams = [];

if (!empty($from_date) && !empty($to_date)) {
    $filterSql .= " AND DATE(t.entry_date) BETWEEN ? AND ?";
    $filterParams[] = $from_date;
    $filterParams[] = $to_date;
} elseif (!empty($from_date)) {
    $filterSql .= " AND DATE(t.entry_date) >= ?";
    $filterParams[] = $from_date;
} elseif (!empty($to_date)) {
    $filterSql .= " AND DATE(t.entry_date) <= ?";
    $filterParams[] = $to_date;
}
if ($module !== '') {
    $filterSql .= " AND t.module = ?";
    $filterParams[] = $module;
}
if ($currency !== '') {
    $filterSql .= " AND t.currency = ?";
    $filterParams[] = $currency;
}
if ($ledger !== '') {
    $filterSql .= " AND t.ledger = ?";
    $filterParams[] = $ledger;
}
if ($search !== '') {
    $sp = '%' . $search . '%';
    $filterSql .= " AND (t.receipt LIKE ? OR t.description LIKE ? OR t.party_name LIKE ? OR t.account_name LIKE ?)";
    array_push($filterParams, $sp, $sp, $sp, $sp);
}

$innerParams = array_fill(0, 8, $tenant_id);
array_walk($innerParams, function (&$v, $i) use ($branch_id) {
    $v = ($i % 2 === 0) ? $v : $branch_id;
});
$allParams = array_merge($innerParams, $filterParams);

/* ── Fetch all filtered rows ── */
$rows = [];
try {
    $dataSql = $sql . $filterSql . " ORDER BY t.entry_date DESC";
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($allParams);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
    exit;
}

$moduleLabels = getModuleLabels();
$ledgerLabels = [
    'main_account' => 'Main Account',
    'client'       => 'Client',
    'supplier'     => 'Supplier',
    'jv'           => 'JV Payment',
];

/* ── Stream CSV ── */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="payment_journal_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
fputcsv($out, ['Date', 'Type', 'Amount', 'Currency', 'Amount (USD base)', 'Ledger', 'Account', 'Party', 'Module', 'Ref ID', 'Receipt', 'Running Balance', 'Description']);

foreach ($rows as $row) {
    $rate = $row['exchange_rate'] !== null && $row['exchange_rate'] !== '' ? (float) $row['exchange_rate'] : null;
    $base = toUsdBase((float) $row['amount'], $row['currency'], $rate, $defaultRates);
    fputcsv($out, [
        $row['entry_date'],
        strtolower((string) $row['type']) === 'credit' ? 'Credit' : 'Debit',
        $row['amount'],
        $row['currency'],
        $base !== null ? $base : '',
        isset($ledgerLabels[$row['ledger']]) ? $ledgerLabels[$row['ledger']] : $row['ledger'],
        $row['account_name'] ?? '',
        $row['party_name'] ?? '',
        isset($moduleLabels[$row['module']]) ? $moduleLabels[$row['module']] : $row['module'],
        $row['reference_id'] ?? '',
        $row['receipt'] ?? '',
        $row['balance'] ?? '',
        $row['description'] ?? '',
    ]);
}
fclose($out);
exit;

/* ── Helpers (kept in sync with get_journal_entries.php) ── */

function toUsdBase($amount, $currency, $rate, $defaultRates)
{
    $currency = strtoupper((string) $currency);
    if ($currency === 'USD' || $currency === '') {
        return round((float) $amount, 2);
    }
    $r = null;
    if ($rate !== null && $rate > 0) {
        $r = $rate;
    } elseif (isset($defaultRates[$currency . '_USD']) && $defaultRates[$currency . '_USD'] > 0) {
        $r = $defaultRates[$currency . '_USD'];
    }
    if ($r === null || $r <= 0) {
        return null;
    }
    return round((float) $amount / $r, 2);
}

function getModuleLabels()
{
    return [
        'ticket_sale'                 => 'Ticket Sale',
        'ticket_refund'               => 'Ticket Refund',
        'ticket_reserve'              => 'Ticket Reserve',
        'date_change'                 => 'Date Change',
        'weight'                      => 'Ticket Weight',
        'weight_sale'                 => 'Weight Sale',
        'visa_sale'                   => 'Visa Sale',
        'visa_refund'                 => 'Visa Refund',
        'visa_cancellation'           => 'Visa Cancellation',
        'hotel'                       => 'Hotel',
        'hotel_refund'                => 'Hotel Refund',
        'umrah'                       => 'Umrah',
        'umrah_transaction'           => 'Umrah Payment',
        'umrah_refund'                => 'Umrah Refund',
        'umrah_date_change'           => 'Umrah Date Change',
        'umrah_cancellation'          => 'Umrah Cancellation',
        'additional_payment'          => 'Additional Payment',
        'jv_payment'                  => 'JV Payment',
        'fund'                        => 'Fund',
        'client_fund'                 => 'Client Fund',
        'supplier_fund'               => 'Supplier Fund',
        'supplier_fund_withdrawal'    => 'Supplier Fund Withdrawal',
        'withdraw_fund'               => 'Withdraw Fund',
        'transfer'                    => 'Transfer',
        'expense'                     => 'Expense',
        'debtor'                      => 'Debtor',
        'creditor'                    => 'Creditor',
        'salary_payment'              => 'Salary Payment',
        'deposit_sarafi'              => 'Sarafi Deposit',
        'hawala_sarafi'               => 'Sarafi Hawala',
        'withdrawal_sarafi'           => 'Sarafi Withdrawal',
        'budget_allocation'           => 'Budget Allocation',
        'global_budget_allocation'    => 'Global Budget Allocation',
    ];
}
