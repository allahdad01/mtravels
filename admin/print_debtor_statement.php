<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

require_once '../includes/conn.php';
require_once '../includes/db.php';

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

// Validate the debtor ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid debtor ID");
}

$debtor_id = intval($_GET['id']);

// Fetch debtor details
$stmt = $conn->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $debtor_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$debtor = $result->fetch_assoc();

if (!$debtor) {
    die("Debtor not found");
}

// Fetch debtor transactions
$stmt = $conn->prepare("SELECT * FROM debtor_transactions WHERE debtor_id = ? AND tenant_id = ? ORDER BY payment_date DESC");
$stmt->bind_param("ii", $debtor_id, $tenant_id);
$stmt->execute();
$transResult = $stmt->get_result();
$transactions = $transResult->fetch_all(MYSQLI_ASSOC);

// Calculate total paid amount
$total_paid = 0;
$initial_balance = $debtor['balance'];

foreach ($transactions as $transaction) {
    if ($transaction['transaction_type'] == 'credit') {
        $total_paid += $transaction['amount'];
    } else if ($transaction['transaction_type'] == 'debit') {
        $initial_balance += $transaction['amount'];
    }
}

// Format currency symbol based on currency
function getCurrencySymbol($currency) {
    switch ($currency) {
        case 'USD':
            return '$';
        case 'EUR':
            return '€';
        case 'AFS':
            return '؋';
        case 'DARHAM':
            return 'د.إ';
        default:
            return '';
    }
}

$currency_symbol = getCurrencySymbol($debtor['currency']);
$is_fully_paid = ($debtor['balance'] == 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($settings['agency_name']) ?> - Debtor Statement</title>
    
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    
    
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .document-title {
            font-size: 20px;
            color: #e74c3c;
            margin-bottom: 15px;
        }
        
        .debtor-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .info-group {
            margin-bottom: 20px;
            flex: 1;
            min-width: 200px;
        }
        
        .info-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #7f8c8d;
        }
        
        .info-value {
            font-size: 16px;
            word-break: break-all;
            max-width: 200px;
        }
        
        .summary-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .summary-item {
            flex: 1;
            min-width: 150px;
            text-align: center;
            padding: 10px;
        }
        
        .summary-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 20px;
            font-weight: bold;
        }
        
        .summary-value.balance {
            color: #e74c3c;
        }
        
        .summary-value.paid {
            color: #27ae60;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .text-right {
            text-align: right;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
            border-top: 1px solid #f0f0f0;
            padding-top: 20px;
        }
        
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .print-button:hover {
            background-color: #c0392b;
        }
        
        .paid-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 100px;
            font-weight: bold;
            color: rgba(46, 204, 113, 0.2);
            border: 10px solid rgba(46, 204, 113, 0.2);
            padding: 10px 20px;
            border-radius: 10px;
            z-index: 1;
            pointer-events: none;
            display: <?php echo $is_fully_paid ? 'block' : 'none'; ?>;
        }
        
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                padding: 15px;
                max-width: 100%;
            }
            
            .print-button {
                display: none;
            }
            
            .paid-stamp {
                display: <?php echo $is_fully_paid ? 'block' : 'none'; ?>;
                color: rgba(46, 204, 113, 0.3);
                border-color: rgba(46, 204, 113, 0.3);
            }
            
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_fully_paid): ?>
        <div class="paid-stamp">PAID</div>
        <?php endif; ?>
        
        <div class="header">
            <div class="company-name"><?= htmlspecialchars($settings['agency_name']) ?></div>
            <div class="document-title">Debtor Statement</div>
            <div>Statement Date: <?= date('F d, Y') ?></div>
        </div>
        
        <div class="debtor-info">
            <div class="info-group">
                <div class="info-label">Debtor Name:</div>
                <div class="info-value"><?= htmlspecialchars($debtor['name']) ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Email:</div>
                <div class="info-value"><?= htmlspecialchars($debtor['email'] ?: 'N/A') ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Phone:</div>
                <div class="info-value"><?= htmlspecialchars($debtor['phone'] ?: 'N/A') ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Address:</div>
                <div class="info-value"><?= htmlspecialchars($debtor['address'] ?: 'N/A') ?></div>
            </div>
        </div>
        
        <div class="summary-box">
            <div class="summary-item">
                <div class="summary-label">Initial Debt</div>
                <div class="summary-value"><?= $currency_symbol ?> <?= number_format($initial_balance, 2) ?></div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Amount Paid</div>
                <div class="summary-value paid"><?= $currency_symbol ?> <?= number_format($total_paid, 2) ?></div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Remaining Balance</div>
                <div class="summary-value balance"><?= $currency_symbol ?> <?= number_format($debtor['balance'], 2) ?></div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Status</div>
                <div class="summary-value"><?= $is_fully_paid ? '<span style="color: #27ae60;">Fully Paid</span>' : '<span style="color: #e74c3c;">Outstanding</span>' ?></div>
            </div>
        </div>
        
        <?php if (!empty($debtor['agreement_terms'])): ?>
        <div style="margin-bottom: 20px;">
            <h3>Agreement Terms</h3>
            <div style="padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                <?= nl2br(htmlspecialchars($debtor['agreement_terms'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <h3>Payment History</h3>
        
        <?php if (count($transactions) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Receipt #</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($transaction['payment_date'])) ?></td>
                    <td><?= htmlspecialchars($transaction['description']) ?></td>
                    <td><?= htmlspecialchars($transaction['reference_number'] ?: 'N/A') ?></td>
                    <td>
                        <?php if ($transaction['transaction_type'] == 'credit'): ?>
                            <span style="color: #27ae60;">Payment</span>
                        <?php else: ?>
                            <span style="color: #e74c3c;">Debt</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?= $currency_symbol ?> <?= number_format($transaction['amount'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 20px; color: #7f8c8d;">
            <p>No transaction records found</p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>This statement was generated automatically. For any questions, please contact us.</p>
            <p><?= htmlspecialchars($settings['agency_name']) ?> - <?= htmlspecialchars($settings['phone'] ?? '') ?></p>
        </div>
    </div>
    
    <button class="print-button" onclick="window.print()">Print Statement</button>
    
    <script>
        // Auto-print when the page loads (optional - uncomment if needed)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html> 