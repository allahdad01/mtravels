<?php
/**
 * Payments Journal — main account balances as of end of date range.
 *
 * For each main account, finds the last transaction within the range
 * and returns its running balance per currency.
 *
 * GET params:
 *   from_date  optional (YYYY-MM-DD)
 *   to_date    optional (YYYY-MM-DD)
 *
 * Roles: admin, finance.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_permission('finance.payments');
require_once __DIR__ . '/../../includes/db.php';

$from_date = (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) ? $_GET['from_date'] : null;
$to_date   = (isset($_GET['to_date'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date']))   ? $_GET['to_date']   : null;

$accounts = [];

try {
    // Get all main accounts for this tenant/branch
    $acctStmt = $pdo->prepare("SELECT id, name FROM main_account WHERE tenant_id = ? AND branch_id = ? ORDER BY name");
    $acctStmt->execute([$tenant_id, $branch_id]);
    $allAccounts = $acctStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allAccounts as $acct) {
        $acctId   = (int) $acct['id'];
        $acctName = $acct['name'];

        // Build date filter
        $where = "WHERE mat.main_account_id = ? AND mat.tenant_id = ? AND mat.branch_id = ?";
        $params = [$acctId, $tenant_id, $branch_id];

        if (!empty($from_date) && !empty($to_date)) {
            $where .= " AND DATE(mat.created_at) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        } elseif (!empty($from_date)) {
            $where .= " AND DATE(mat.created_at) >= ?";
            $params[] = $from_date;
        } elseif (!empty($to_date)) {
            $where .= " AND DATE(mat.created_at) <= ?";
            $params[] = $to_date;
        }

        // Get the last transaction per currency for this account within the date range
        $currencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];
        $balances = [];

        foreach ($currencies as $curr) {
            $balStmt = $pdo->prepare(
                "SELECT mat.balance
                 FROM main_account_transactions mat
                 $where AND mat.currency = ?
                 ORDER BY mat.created_at DESC, mat.id DESC
                 LIMIT 1"
            );
            $balStmt->execute(array_merge($params, [$curr]));
            $row = $balStmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['balance'] !== null) {
                $balances[strtolower($curr)] = round((float) $row['balance'], 2);
            }
        }

        // Only include accounts that had transactions in the range
        if (!empty($balances)) {
            $accounts[] = [
                'id'       => $acctId,
                'name'     => $acctName,
                'balances' => $balances,
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Main account balances error: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode([
    'success'  => true,
    'accounts' => $accounts,
]);
