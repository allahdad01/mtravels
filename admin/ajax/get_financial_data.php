<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

// Include database connection
require_once('../../includes/db.php');

// Get parameters
$period = isset($_POST['period']) ? $_POST['period'] : 'monthly';
$currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';

// Validate parameters
if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
    $period = 'monthly';
}

if (!in_array($currency, ['USD', 'AFS', 'EUR', 'AED'])) {
    $currency = 'USD';
}

// Fetch financial data
function getFinancialWealthData($pdo, $period, $currency, $tenant_id) {
    // Initialize result array
    $result = [
        'main_accounts' => 0,
        'supplier_credits' => 0,
        'client_credits' => 0,
        
        'debtor_balance' => 0,
        'creditor_balance' => 0,
        'transactions' => []
    ];
    
    try {
        // Get main accounts balance based on currency
        if ($currency == 'USD') {
            $accountsQuery = "SELECT SUM(usd_balance) as balance FROM main_account WHERE tenant_id = ?";
        } elseif ($currency == 'AFS') {
            $accountsQuery = "SELECT SUM(afs_balance) as balance FROM main_account WHERE tenant_id = ?";
        } elseif ($currency == 'EUR') {
            $accountsQuery = "SELECT SUM(euro_balance) as balance FROM main_account WHERE tenant_id = ?";
        } elseif ($currency == 'AED') {
            $accountsQuery = "SELECT SUM(darham_balance) as balance FROM main_account WHERE tenant_id = ?";
        }
        $stmt = $pdo->prepare($accountsQuery);
        $stmt->execute([$tenant_id]);
        $accounts = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['main_accounts'] = number_format(floatval($accounts['balance'] ?? 0), 2, '.', '');
        
        // Get supplier credits - ONLY include positive balances (skip negative ones)
        $supplierQuery = "SELECT SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as balance 
                         FROM suppliers WHERE currency = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($supplierQuery);
        $stmt->execute([$currency, $tenant_id]);
        $suppliers = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['supplier_credits'] = number_format(floatval($suppliers['balance'] ?? 0), 2, '.', '');
        
        // Get client credits (negative balances = money clients owe us)
        if ($currency == 'USD') {
            $clientQuery = "SELECT ABS(SUM(CASE WHEN usd_balance < 0 THEN usd_balance ELSE 0 END)) as balance FROM clients WHERE tenant_id = ?";
        } elseif ($currency == 'AFS') {
            $clientQuery = "SELECT ABS(SUM(CASE WHEN afs_balance < 0 THEN afs_balance ELSE 0 END)) as balance FROM clients WHERE tenant_id = ?";
        } elseif ($currency == 'EUR' || $currency == 'AED') {
            // For EUR and AED currencies, clients don't have these balances
            // Return 0 as default value
            $result['client_credits'] = 0;
            // Skip the query execution
            $clientQuery = null;
        }
        
        if ($clientQuery) {
            $stmt = $pdo->prepare($clientQuery);
            $stmt->execute([$tenant_id]);
            $clients = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['client_credits'] = number_format(floatval($clients['balance'] ?? 0), 2, '.', '');
        }
        
        // Get debtor balance
        $debtorQuery = "SELECT SUM(balance) as balance FROM debtors WHERE currency = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($debtorQuery);
        $stmt->execute([$currency, $tenant_id]);
        $debtors = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['debtor_balance'] = number_format(floatval($debtors['balance'] ?? 0), 2, '.', '');
        
        // Get creditor balance
        $creditorQuery = "SELECT SUM(balance) as balance FROM creditors WHERE currency = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($creditorQuery);
        $stmt->execute([$currency, $tenant_id]);
        $creditors = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['creditor_balance'] = number_format(floatval($creditors['balance'] ?? 0), 2, '.', '');
        
        
        // Get transaction flow data from main_account_transactions
        $timeConstraint = '';
        $params = [$currency];
        
        if ($period == 'daily') {
            $timeConstraint = "AND DATE(created_at) = CURDATE()";
        } elseif ($period == 'monthly') {
            // Use proper date constraints for current month
            $timeConstraint = "AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)";
        } elseif ($period == 'yearly') {
            $timeConstraint = "AND YEAR(created_at) = YEAR(CURDATE())";
        }
        
        // Map currency codes to database column names for main_account_transactions
        $transactionCurrency = $currency;
        if ($currency == 'EUR') {
            $transactionCurrency = 'EUR';
        } elseif ($currency == 'AED') {
            $transactionCurrency = 'DARHAM';
        }
        
        $transactionQuery = "SELECT 
                             DATE(created_at) as date,
                             type,
                             SUM(amount) as amount
                           FROM main_account_transactions
                           WHERE currency = ? AND tenant_id = ? $timeConstraint
                           GROUP BY DATE(created_at), type
                           ORDER BY DATE(created_at)";
                           
        $stmt = $pdo->prepare($transactionQuery);
        $stmt->execute([$transactionCurrency, $tenant_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['transactions'] = array_map(function($row) {
            $row['amount'] = number_format(floatval($row['amount']), 2, '.', '');
            return $row;
        }, $transactions);
        
        // Remove demo data generation - show actual empty data
        if (empty($result['transactions'])) {
            $result['transactions'] = [];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error getting financial wealth data: " . $e->getMessage());
        return $result;
    }
}

// Get the data
$financialData = getFinancialWealthData($pdo, $period, $currency, $tenant_id);

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => $financialData
]); 