<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Include database connection
include '../../includes/db.php';

// Initialize variables
$expense = null;
$transactions = [];
$errorMessage = '';

// Get expense ID from URL
if (isset($_GET['id'])) {
    $expenseId = DbSecurity::validateInput($_GET['id'], 'int');
    
    if (!$expenseId) {
        $errorMessage = "Invalid expense ID.";
    } else {
        // Get expense details
        $query = "SELECT e.*, ec.name as category_name, ma.name as account_name, mat.receipt as receipt_number
                  FROM expenses e
                  LEFT JOIN expense_categories ec ON e.category_id = ec.id
                  LEFT JOIN main_account ma ON e.main_account_id = ma.id
                  LEFT JOIN main_account_transactions mat ON e.id = mat.reference_id AND mat.transaction_of = 'expense'
                  WHERE e.id = ? AND e.tenant_id = ? AND e.branch_id = ?";
       $stmt = $pdo->prepare($query);
       $stmt->execute([$expenseId, $tenant_id, $branch_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            $errorMessage = "Expense not found.";
        } else {
            // Get transactions related to this expense
            $transactionQuery = "SELECT 
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.created_at AS transaction_date
                FROM main_account_transactions mat
                WHERE mat.reference_id = ? AND mat.transaction_of = 'expense' AND mat.tenant_id = ? AND mat.branch_id = ?";
           $stmt = $pdo->prepare($transactionQuery);
           $stmt->execute([$expenseId, $tenant_id, $branch_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $errorMessage = "Expense ID is required.";
}

// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Branch Error: " . $e->getMessage());
    $branch = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense #<?php echo isset($expense['id']) ? h($expense['id']) : 'Not Found'; ?> - Print View</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #333;
        }
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .print-header img {
            max-height: 100px;
            margin-bottom: 10px;
        }
        .print-header h1 {
            font-size: 24px;
            margin: 5px 0;
        }
        .print-header p {
            margin: 5px 0;
            color: #555;
        }
        .print-title {
            font-size: 22px;
            margin: 20px 0;
            text-align: center;
            border-bottom: 2px solid #4099ff;
            padding-bottom: 10px;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .info-table th {
            background-color: #f5f5f5;
            width: 30%;
        }
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .transaction-table th, .transaction-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .transaction-table th {
            background-color: #f5f5f5;
        }
        .transaction-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .section-title {
            font-size: 18px;
            margin: 20px 0 10px 0;
            color: #333;
            border-left: 4px solid #4099ff;
            padding-left: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .print-only {
            display: block;
        }
        .text-danger {
            color: #f44336;
        }
        .text-success {
            color: #4caf50;
        }
        @media print {
            body {
                padding: 0;
                font-size: 12pt;
            }
            .no-print {
                display: none;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
    <?php else: ?>
        <div class="print-header">
            <?php if (!empty($settings['logo'])): ?>
                <img src="../../uploads/logo/<?php echo h($settings['logo']); ?>" alt="<?php echo h($settings['agency_name']); ?> Logo">
            <?php endif; ?>
            <h1><?php echo h($settings['agency_name']); ?> - <?php echo htmlspecialchars($branch['name']); ?></h1>
            <p><?php echo h($branch['address']); ?></p>
            <p>Phone: <?php echo h($branch['phone']); ?> | Email: <?php echo h($branch['email']); ?></p>
        </div>

        <h2 class="print-title">Expense Receipt #<?php echo h($expense['id']); ?></h2>
        
        <div class="info-section">
            <h3 class="section-title">Expense Details</h3>
            <table class="info-table">
                <tr>
                    <th>ID</th>
                    <td><strong><?php echo h($expense['id']); ?></strong></td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td><?php echo h($expense['category_name']); ?></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><?php echo h($expense['description']); ?></td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td><?php echo h(date('F d, Y', strtotime($expense['date']))); ?></td>
                </tr>
                <tr>
                    <th>Amount</th>
                    <td class="text-danger">
                        <strong><?php echo h($expense['currency']) . ' ' . h(number_format($expense['amount'], 2)); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th>Account</th>
                    <td><?php echo h($expense['account_name']); ?></td>
                </tr>
                <?php if (!empty($expense['receipt_number'])): ?>
                <tr>
                    <th>Receipt Number</th>
                    <td><?php echo h($expense['receipt_number']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($expense['receipt_file'])): ?>
                <tr>
                    <th>Receipt File</th>
                    <td>
                        <img src="../../uploads/expense_receipt/<?php echo h($expense['receipt_file']); ?>" alt="Receipt" style="max-width: 200px; max-height: 200px;">
                        <br><small><?php echo h($expense['receipt_file']); ?></small>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (isset($expense['allocation_id']) && $expense['allocation_id']): ?>
                <tr>
                    <th>Budget Allocation</th>
                    <td>Allocation #<?php echo h($expense['allocation_id']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Recorded On</th>
                    <td><?php echo h(date('F d, Y H:i', strtotime($expense['created_at']))); ?></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($transactions)): ?>
            <div class="info-section">
                <h3 class="section-title">Transaction History</h3>
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo h($transaction['id']); ?></td>
                                <td class="<?php echo strtolower($transaction['type']) == 'debit' ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo h($transaction['type']); ?>
                                </td>
                                <td><?php echo h($transaction['currency']) . ' ' . h(number_format($transaction['amount'], 2)); ?></td>
                                <td><?php echo h($transaction['description']); ?></td>
                                <td><?php echo h(date('Y-m-d H:i', strtotime($transaction['transaction_date']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>This document was generated on <?php echo date('F d, Y H:i:s'); ?></p>
            <p>Thank you for your business!</p>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #4099ff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Print Receipt
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                Close
            </button>
        </div>
    <?php endif; ?>

    <script>
        // Auto print on page load
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html> 