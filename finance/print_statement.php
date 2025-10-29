<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: customers.php');
    exit();
}

$customer_id = intval($_GET['id']);

// Fetch customer details
$stmt = $conn->prepare("
    SELECT * FROM customers WHERE id = ? AND status = 'active' AND tenant_id = ?
");
$stmt->bind_param('ii', $customer_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: customers.php');
    exit();
}

$customer = $result->fetch_assoc();

// Fetch customer wallet balances
$wallet_stmt = $conn->prepare("
    SELECT * FROM customer_wallets WHERE customer_id = ? AND tenant_id = ?
");
$wallet_stmt->bind_param('ii', $customer_id, $tenant_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$wallets = [];

while ($row = $wallet_result->fetch_assoc()) {
    $wallets[] = $row;
}

// Fetch recent transactions (last 30 days)
$transactions_stmt = $conn->prepare("
    SELECT 
        st.*
    FROM sarafi_transactions st
    WHERE st.customer_id = ? AND st.tenant_id = ?
    ORDER BY st.created_at ASC
");
$transactions_stmt->bind_param('ii', $customer_id, $tenant_id);
$transactions_stmt->execute();
$transactions_result = $transactions_stmt->get_result();
$transactions = [];

while ($row = $transactions_result->fetch_assoc()) {
    $transactions[] = $row;
}

// Fetch company settings
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Default Name'];
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

// Get today's date for the statement
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name']) ?> - <?= __('customer_statement') ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    
    <!-- Favicon icon --> 
    <link rel="icon" href="../Uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12pt;
                margin: 0;
                padding: 0;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .page-wrapper {
                margin-top: 0;
                padding: 0;
            }
        }
        
        .statement-header {
            border-bottom: 2px solid #ddd;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .customer-info {
            margin-bottom: 20px;
        }
        
        .balance-summary {
            margin-bottom: 20px;
        }
        
        .balance-badge {
            font-size: 1.1em;
            padding: 5px 10px;
            margin-right: 10px;
            border-radius: 5px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            display: inline-block;
        }
        
        .transaction-table th,
        .transaction-table td {
            padding: 0.75rem;
        }
        
        .print-footer {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            text-align: center;
            font-size: 0.9em;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <button class="btn btn-primary print-button no-print" onclick="window.print()">
        <i class="feather icon-printer mr-1"></i> <?= __('print') ?>
    </button>

    <div class="page-wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <!-- Statement Header -->
                            <div class="statement-header">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h3><?= htmlspecialchars($settings['agency_name']) ?></h3>
                                        <p>
                                            <?= !empty($settings['address']) ? htmlspecialchars($settings['address']) : '' ?><br>
                                            <?= !empty($settings['phone']) ? htmlspecialchars($settings['phone']) : '' ?><br>
                                            <?= !empty($settings['email']) ? htmlspecialchars($settings['email']) : '' ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <h4><?= __('customer_statement') ?></h4>
                                        <p>
                                            <?= __('statement_date') ?>: <?= $today ?><br>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Information -->
                            <div class="customer-info">
                                <h5><?= __('customer_information') ?></h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p>
                                            <strong><?= __('customer_name') ?>:</strong> <?= htmlspecialchars($customer['name']) ?><br>
                                            <strong><?= __('customer_phone') ?>:</strong> <?= htmlspecialchars($customer['phone']) ?><br>
                                            <?php if (!empty($customer['email'])): ?>
                                                <strong><?= __('customer_email') ?>:</strong> <?= htmlspecialchars($customer['email']) ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($customer['address'])): ?>
                                                <strong><?= __('customer_address') ?>:</strong> <?= htmlspecialchars($customer['address']) ?><br>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p>
                                            <strong><?= __('customer_id') ?>:</strong> #<?= $customer['id'] ?><br>
                                            <strong><?= __('customer_since') ?>:</strong> <?= date('Y-m-d', strtotime($customer['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Balance Summary -->
                            <div class="balance-summary">
                                <h5><?= __('account_balance') ?></h5>
                                <?php if (count($wallets) > 0): ?>
                                    <?php foreach ($wallets as $wallet): ?>
                                        <div class="balance-badge">
                                            <?= number_format($wallet['balance'], 2) ?> <?= htmlspecialchars($wallet['currency']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted"><?= __('no_balance') ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Recent Transactions -->
                            <div class="recent-transactions">
                                <h5><?= __('recent_transactions') ?> </h5>
                                <?php if (count($transactions) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered transaction-table">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('description') ?></th>
                                                    <th><?= __('debit') ?></th>
                                                    <th><?= __('credit') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td><?= date('Y-m-d', strtotime($transaction['created_at'])) ?></td>
                                                    <td><?= htmlspecialchars($transaction['type']) ?></td>
                                                    <td><?= htmlspecialchars($transaction['notes']) ?></td>
                                                    <td>
                                                        <?php if ($transaction['type'] == 'withdrawal' || $transaction['type'] == 'hawala_send'): ?>
                                                            <span class="text-danger"><?= number_format(abs($transaction['amount']), 2) ?> <?= htmlspecialchars($transaction['currency']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if  ($transaction['type'] == 'deposit'): ?>
                                                            <span class="text-success"><?= number_format($transaction['amount'], 2) ?> <?= htmlspecialchars($transaction['currency']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted"><?= __('no_recent_transactions') ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Print Footer -->
                            <div class="print-footer">
                                <p>
                                    <?= __('statement_disclaimer') ?><br>
                                    <?= __('generated_on') ?>: <?= date('Y-m-d H:i:s') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js for print functionality -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script>
        // Auto-print when the page loads
        window.onload = function() {
            // Wait a moment for everything to render properly
            setTimeout(function() {
                // Uncomment below line to automatically open print dialog when page loads
                // window.print();
            }, 500);
        };
    </script>
</body>
</html> 