<?php
/**
 * Payments Journal — fetch unified journal entries
 *
 * Phase 1: read-only unified register built from a UNION of:
 *   - main_account_transactions
 *   - client_transactions
 *   - supplier_transactions
 *   - jv_payments
 *
 * Scoped to tenant + branch. Roles: admin, finance.
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

// Role gate
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

/* ────────────────────────────────────────────────────────────
   Input validation
   ──────────────────────────────────────────────────────────── */
$page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 25;
$offset   = ($page - 1) * $per_page;

$from_date = (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) ? $_GET['from_date'] : null;
$to_date   = (isset($_GET['to_date'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date']))   ? $_GET['to_date']   : null;
$module    = isset($_GET['module'])  ? trim($_GET['module'])  : '';
$currency  = isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : '';
$ledger    = isset($_GET['ledger'])  ? trim($_GET['ledger'])  : '';
$entity    = isset($_GET['entity'])  ? (int) $_GET['entity']  : 0;
$search    = isset($_GET['search'])  ? trim($_GET['search'])  : '';

$allowed_currencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];
$allowed_ledgers    = ['main_account', 'client', 'supplier', 'jv'];
if ($currency !== '' && !in_array($currency, $allowed_currencies, true)) { $currency = ''; }
if ($ledger !== '' && !in_array($ledger, $allowed_ledgers, true)) { $ledger = ''; }
if (strlen($module) > 60) { $module = ''; }
if (strlen($search) > 255) { $search = substr($search, 0, 255); }

/* ────────────────────────────────────────────────────────────
   Default exchange rates (latest per pair) for base-USD calc
   ──────────────────────────────────────────────────────────── */
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

/* ────────────────────────────────────────────────────────────
   UNION subquery (positional placeholders: 6 per ledger × 4)
   ──────────────────────────────────────────────────────────── */
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
        esc.name AS sub_category_name,
        CASE
            WHEN mat.transaction_of = 'transfer' THEN mto.name
            WHEN mat.transaction_of = 'client_fund' THEN ccl.name
            WHEN mat.transaction_of IN ('supplier_fund', 'supplier_fund_withdrawal') THEN cspl.name
            WHEN mat.transaction_of = 'visa_sale' THEN vapp.applicant_name
            WHEN mat.transaction_of = 'ticket_reserve' THEN tres.passenger_name
            WHEN mat.transaction_of = 'ticket_refund' THEN tref.passenger_name
            WHEN mat.transaction_of = 'date_change' THEN dchg.passenger_name
            WHEN mat.transaction_of = 'ticket_sale' THEN tbk.passenger_name
            WHEN mat.transaction_of IN ('weight', 'ticket_weight') THEN tbk2.passenger_name
            WHEN mat.transaction_of = 'salary_payment' THEN usr.name
            WHEN mat.transaction_of = 'expense' THEN ecat.name
            WHEN mat.transaction_of = 'fund' THEN mfr.name
            WHEN mat.transaction_of = 'additional_payment' THEN CASE WHEN ap.is_from_supplier = 1 AND ap.supplier_id IS NOT NULL THEN aps.name WHEN ap.is_for_client = 1 AND ap.client_id IS NOT NULL THEN apc.name ELSE NULL END
            ELSE NULL
        END AS party_name,
        mat.main_account_id AS entity_id
    FROM main_account_transactions mat
    LEFT JOIN main_account ma ON ma.id = mat.main_account_id AND ma.tenant_id = mat.tenant_id
    LEFT JOIN main_account mto ON mat.transaction_of = 'transfer' AND mto.id = mat.reference_id AND mto.tenant_id = mat.tenant_id
    LEFT JOIN client_transactions cft ON mat.transaction_of = 'client_fund' AND cft.id = mat.reference_id AND cft.tenant_id = mat.tenant_id
    LEFT JOIN clients ccl ON ccl.id = cft.client_id AND ccl.tenant_id = mat.tenant_id
    LEFT JOIN supplier_transactions csft ON mat.transaction_of IN ('supplier_fund', 'supplier_fund_withdrawal') AND csft.id = mat.reference_id AND csft.tenant_id = mat.tenant_id
    LEFT JOIN suppliers cspl ON cspl.id = csft.supplier_id AND cspl.tenant_id = mat.tenant_id
    LEFT JOIN visa_applications vapp ON mat.transaction_of = 'visa_sale' AND vapp.id = mat.reference_id AND vapp.tenant_id = mat.tenant_id
    LEFT JOIN ticket_reservations tres ON mat.transaction_of = 'ticket_reserve' AND tres.id = mat.reference_id AND tres.tenant_id = mat.tenant_id
    LEFT JOIN refunded_tickets tref ON mat.transaction_of = 'ticket_refund' AND tref.id = mat.reference_id AND tref.tenant_id = mat.tenant_id
    LEFT JOIN date_change_tickets dchg ON mat.transaction_of = 'date_change' AND dchg.id = mat.reference_id AND dchg.tenant_id = mat.tenant_id
    LEFT JOIN ticket_bookings tbk ON mat.transaction_of = 'ticket_sale' AND tbk.id = mat.reference_id AND tbk.tenant_id = mat.tenant_id
    LEFT JOIN ticket_weights twt ON mat.transaction_of IN ('weight', 'ticket_weight') AND twt.id = mat.reference_id AND twt.tenant_id = mat.tenant_id
    LEFT JOIN ticket_bookings tbk2 ON tbk2.id = twt.ticket_id AND tbk2.tenant_id = twt.tenant_id
    LEFT JOIN salary_payments spy ON mat.transaction_of = 'salary_payment' AND spy.id = mat.reference_id AND spy.tenant_id = mat.tenant_id
    LEFT JOIN users usr ON usr.id = spy.user_id
    LEFT JOIN expenses exp ON mat.transaction_of = 'expense' AND exp.id = mat.reference_id AND exp.tenant_id = mat.tenant_id
    LEFT JOIN expense_categories ecat ON ecat.id = exp.category_id
    LEFT JOIN expense_categories esc ON mat.transaction_of = 'expense' AND esc.id = exp.sub_category_id
    LEFT JOIN main_account mfr ON mat.transaction_of = 'fund' AND mfr.id = mat.reference_id AND mfr.tenant_id = mat.tenant_id
    LEFT JOIN additional_payments ap ON mat.transaction_of = 'additional_payment' AND ap.id = mat.reference_id AND ap.tenant_id = mat.tenant_id
    LEFT JOIN suppliers aps ON ap.is_from_supplier = 1 AND aps.id = ap.supplier_id AND aps.tenant_id = ap.tenant_id
    LEFT JOIN clients apc ON ap.is_for_client = 1 AND apc.id = ap.client_id AND apc.tenant_id = ap.tenant_id
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
        NULL AS sub_category_name,
        cl.name AS party_name,
        ct.client_id AS entity_id
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
        NULL AS sub_category_name,
        sp.name AS party_name,
        st.supplier_id AS entity_id
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
        NULL AS sub_category_name,
        CONCAT(COALESCE(c.name, '—'), ' → ', COALESCE(s.name, '—')) AS party_name,
        NULL AS entity_id
    FROM jv_payments jp
    LEFT JOIN clients c ON c.id = jp.client_id AND c.tenant_id = jp.tenant_id
    LEFT JOIN suppliers s ON s.id = jp.supplier_id AND s.tenant_id = jp.tenant_id
    WHERE jp.tenant_id = ? AND jp.branch_id = ?
) t
WHERE 1 = 1
";

/* ────────────────────────────────────────────────────────────
   Filters (outer query)
   ──────────────────────────────────────────────────────────── */
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
if ($entity > 0) {
    $filterSql .= " AND t.entity_id = ?";
    $filterParams[] = $entity;
}
if ($search !== '') {
    $sp = '%' . $search . '%';
    $filterSql .= " AND (t.receipt LIKE ? OR t.description LIKE ? OR t.party_name LIKE ? OR t.account_name LIKE ? OR t.sub_category_name LIKE ?)";
    array_push($filterParams, $sp, $sp, $sp, $sp, $sp);
}

// Ledger-scoped params first (inner subquery: 4 × tenant+branch = 8), filters second
$innerParams = array_fill(0, 8, $tenant_id);
array_walk($innerParams, function (&$v, $i) use ($branch_id) {
    $v = ($i % 2 === 0) ? $v : $branch_id;
});
$allParams = array_merge($innerParams, $filterParams);

/* ────────────────────────────────────────────────────────────
   Total count
   ──────────────────────────────────────────────────────────── */
$total = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM (" . $sql . $filterSql . ") cnt");
    $countStmt->execute($allParams);
    $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("Journal count error: " . $e->getMessage());
}

/* ────────────────────────────────────────────────────────────
   Summary (full filtered set, computed in PHP for base-USD)
   ──────────────────────────────────────────────────────────── */
$summary = [
    'count'      => $total,
    'total_in'   => 0.0,
    'total_out'  => 0.0,
    'by_currency'=> []
];
$summaryRows = [];
try {
    $sumStmt = $pdo->prepare("SELECT t.type, t.amount, t.currency, t.exchange_rate FROM (" . $sql . ") t WHERE 1 = 1" . $filterSql);
    $sumStmt->execute($allParams);
    $summaryRows = $sumStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Journal summary error: " . $e->getMessage());
}

foreach ($summaryRows as $sr) {
    $curr = $sr['currency'];
    if (!isset($summary['by_currency'][$curr])) {
        $summary['by_currency'][$curr] = ['in' => 0.0, 'out' => 0.0];
    }
    $amt = (float) $sr['amount'];
    $isCredit = strtolower((string) $sr['type']) === 'credit';
    $bucket = $isCredit ? 'in' : 'out';
    $summary['by_currency'][$curr][$bucket] += $amt;

    $base = toUsdBase($amt, $curr, $sr['exchange_rate'] !== null ? (float) $sr['exchange_rate'] : null, $defaultRates);
    if ($base !== null) {
        if ($isCredit) {
            $summary['total_in'] += $base;
        } else {
            $summary['total_out'] += $base;
        }
    }
}
$summary['total_in']  = round($summary['total_in'], 2);
$summary['total_out'] = round($summary['total_out'], 2);

/* ────────────────────────────────────────────────────────────
   Page data
   ──────────────────────────────────────────────────────────── */
$rows = [];
$totalPages = max(1, (int) ceil($total / $per_page));
try {
    $dataSql = $sql . $filterSql . " ORDER BY t.entry_date DESC LIMIT " . $per_page . " OFFSET " . $offset;
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($allParams);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Journal fetch error: " . $e->getMessage());
}

$moduleLabels = getModuleLabels();
$drillMap = getDrillMap();

$out = [];
foreach ($rows as $row) {
    $rate = $row['exchange_rate'] !== null && $row['exchange_rate'] !== '' ? (float) $row['exchange_rate'] : null;
    $out[] = [
        'row_key'       => $row['row_key'],
        'ledger'        => $row['ledger'],
        'entry_date'    => $row['entry_date'],
        'type'          => strtolower((string) $row['type']) === 'credit' ? 'credit' : 'debit',
        'amount'        => round((float) $row['amount'], 3),
        'currency'      => $row['currency'],
        'base_amount'   => toUsdBase((float) $row['amount'], $row['currency'], $rate, $defaultRates),
        'balance'       => $row['balance'] !== null ? round((float) $row['balance'], 3) : null,
        'module'        => $row['module'],
        'module_label'  => isset($moduleLabels[$row['module']]) ? $moduleLabels[$row['module']] : $row['module'],
        'reference_id'  => $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
        'receipt'       => $row['receipt'] !== null ? $row['receipt'] : '',
        'description'   => $row['description'] !== null ? $row['description'] : '',
        'account_name'  => $row['account_name'] !== null ? $row['account_name'] : '',
        'sub_category_name' => $row['sub_category_name'] !== null ? $row['sub_category_name'] : '',
        'party_name'    => $row['party_name'] !== null ? $row['party_name'] : '',
        'drill_url'     => buildDrillUrl($row['module'], $row['reference_id'], $drillMap),
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'success'      => true,
    'rows'         => $out,
    'total'        => $total,
    'page'         => $page,
    'per_page'     => $per_page,
    'total_pages'  => $totalPages,
    'summary'      => $summary,
    'base_currency'=> 'USD',
]);

/* ────────────────────────────────────────────────────────────
   Helpers
   ──────────────────────────────────────────────────────────── */

/**
 * Convert an amount to USD base using the transaction's own rate
 * first, then falling back to the latest stored default rate.
 * Returns null when no rate is available.
 */
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

/**
 * Human-readable module labels (display only; not translated).
 */
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
        'fund_withdrawal'             => 'Fund Withdrawal',
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

/**
 * Module → drill-down page map (list or detail pages).
 */
function getDrillMap()
{
    return [
        'ticket_sale'              => 'ticket_detail.php?id=',
        'visa_sale'                => 'visa_detail.php?id=',
        'umrah'                    => 'umrah_detail.php?id=',
        'umrah_transaction'        => 'umrah_detail.php?id=',
        'hotel'                    => 'hotel_detail.php?id=',
        'hotel_refund'             => 'hotel_detail.php?id=',
        'additional_payment'       => 'payment_detail.php?id=',
        'jv_payment'               => 'jv_payments.php',
        'ticket_refund'            => 'refund_ticket.php',
        'ticket_reserve'           => 'ticket_reserve.php',
        'date_change'              => 'date_change.php',
        'weight'                   => 'ticket_weights.php',
        'weight_sale'              => 'ticket_weights.php',
        'visa_refund'              => 'visa_refunds.php',
        'umrah_refund'             => 'umrah_refunds.php',
        'umrah_date_change'        => 'umrah_date_changes.php',
        'salary_payment'           => 'salary_payments.php',
        'expense'                  => 'expense_management.php',
        'debtor'                   => 'debtors.php',
        'creditor'                 => 'creditors.php',
        'fund'                     => 'accounts.php',
        'client_fund'              => 'accounts.php',
        'supplier_fund'            => 'accounts.php',
        'supplier_fund_withdrawal' => 'accounts.php',
        'withdraw_fund'            => 'accounts.php',
        'transfer'                 => 'accounts.php',
        'deposit_sarafi'           => 'sarafi.php',
        'hawala_sarafi'            => 'sarafi.php',
        'withdrawal_sarafi'        => 'sarafi.php',
        'budget_allocation'        => 'budget_allocations.php',
        'global_budget_allocation' => 'global_budget_allocation.php',
    ];
}

/**
 * Build the drill-down URL for a row, or null when not mappable.
 */
function buildDrillUrl($module, $referenceId, $drillMap)
{
    if (!isset($drillMap[$module])) {
        return null;
    }
    $target = $drillMap[$module];
    if (substr($target, -1) === '=') {
        $ref = $referenceId !== null && $referenceId > 0 ? (int) $referenceId : 0;
        if ($ref <= 0) {
            return null;
        }
        return $target . $ref;
    }
    return $target;
}
