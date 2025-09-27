<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
// Include config file
require_once "../includes/db.php";


// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

// Define variables
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

// Get month name
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

// Create a title
$title = $user_id ? "Individual Payroll Report" : "Group Payroll Report";
$subtitle = "For $monthName $year";

// Function to calculate total earnings
function calculateTotalEarnings($base_salary, $bonuses, $deductions, $advances) {
    $totalBonuses = 0;
    foreach ($bonuses as $bonus) {
        $totalBonuses += $bonus['amount'];
    }
    
    $totalDeductions = 0;
    foreach ($deductions as $deduction) {
        $totalDeductions += $deduction['amount'];
    }
    
    $totalAdvances = 0;
    foreach ($advances as $advance) {
        $totalAdvances += $advance['amount'];
    }
    
    return $base_salary + $totalBonuses - $totalDeductions - $totalAdvances;
}

// Prepare SQL query based on whether user_id is provided
if ($user_id) {
    // Query for individual employee
    $employeeQuery = "SELECT sm.*, u.name as employee_name, u.hire_date, u.email, u.phone 
                     FROM salary_management sm 
                     JOIN users u ON sm.user_id = u.id 
                     WHERE sm.user_id = ? AND sm.tenant_id = ?";
    $stmt = $pdo->prepare($employeeQuery);
    $stmt->execute([$user_id, $tenant_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Query for all active employees
    $employeeQuery = "SELECT sm.*, u.name as employee_name, u.hire_date, u.email, u.phone 
                     FROM salary_management sm 
                     JOIN users u ON sm.user_id = u.id 
                     WHERE sm.status = 'active' AND sm.tenant_id = ?
                     ORDER BY u.name ASC";
    $stmt = $pdo->prepare($employeeQuery);
    $stmt->execute([$tenant_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch salary bonuses for the month
$bonusesQuery = "SELECT * FROM salary_bonuses
                WHERE user_id = ? AND MONTH(bonus_date) = ? AND YEAR(bonus_date) = ? AND tenant_id = ?";
$bonusStmt = $pdo->prepare($bonusesQuery);

// Fetch salary deductions for the month
$deductionsQuery = "SELECT * FROM salary_deductions
                   WHERE user_id = ? AND MONTH(deduction_date) = ? AND YEAR(deduction_date) = ? AND tenant_id = ?";
$deductionStmt = $pdo->prepare($deductionsQuery);

// Fetch salary adjustments for the month (legacy)
$adjustmentsQuery = "SELECT sa.* 
                    FROM salary_adjustments sa 
                    WHERE sa.user_id = ? AND MONTH(sa.effective_date) = ? AND YEAR(sa.effective_date) = ? AND tenant_id = ?";
$adjustmentStmt = $pdo->prepare($adjustmentsQuery);

// Fetch salary advances for the month
$advancesQuery = "SELECT sad.* 
                 FROM salary_advances sad 
                 WHERE sad.user_id = ? AND MONTH(sad.advance_date) = ? AND YEAR(sad.advance_date) = ? AND tenant_id = ?";
$advanceStmt = $pdo->prepare($advancesQuery);

// Get employee adjustments and advances
foreach ($employees as &$employee) {
    // Get bonuses
    $bonusStmt->execute([$employee['user_id'], $month, $year, $tenant_id]);
    $employee['bonuses'] = $bonusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get deductions
    $deductionStmt->execute([$employee['user_id'], $month, $year, $tenant_id]);
    $employee['deductions'] = $deductionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get legacy adjustments
    $adjustmentStmt->execute([$employee['user_id'], $month, $year, $tenant_id]);
    $adjustments = $adjustmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get advances
    $advanceStmt->execute([$employee['user_id'], $month, $year, $tenant_id]);
    $employee['advances'] = $advanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total earnings (for display in summary only)
    $employee['total_earnings'] = calculateTotalEarnings(
        $employee['base_salary'], 
        $employee['bonuses'],
        $employee['deductions'],
        $employee['advances']
    );

    // Calculate total bonuses
    $totalBonuses = 0;
    foreach ($employee['bonuses'] as $bonus) {
        $totalBonuses += $bonus['amount'];
    }

    // Calculate total deductions
    $totalDeductions = 0;
    foreach ($employee['deductions'] as $deduction) {
        $totalDeductions += $deduction['amount'];
    }
    foreach ($employee['advances'] as $advance) {
        $totalDeductions += $advance['amount'];
    }

    // Check payment status from existing salary_payments table
    $paymentQuery = "SELECT SUM(amount) as total_paid 
                    FROM salary_payments 
                    WHERE user_id = ? 
                    AND DATE_FORMAT(payment_for_month, '%Y-%m') = ? AND tenant_id = ?";
    $paymentStmt = $pdo->prepare($paymentQuery);
    // Format month and year to match payment_for_month format (YYYY-MM)
    $paymentForMonth = sprintf('%04d-%02d', $year, $month);
    $paymentStmt->execute([$employee['user_id'], $paymentForMonth, $tenant_id]);
    $paymentStatus = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    
    // Compare total paid amount with (base salary + bonuses - deductions)
    $totalPaid = $paymentStatus['total_paid'] ?? 0;
    $requiredAmount = ($employee['base_salary'] + $totalBonuses) - $totalDeductions;
    $employee['payment_status'] = ($totalPaid >= $requiredAmount) ? 'paid' : 'pending';
    $employee['amount_paid'] = $totalPaid;
    $employee['amount_remaining'] = max(0, $requiredAmount - $totalPaid);
    $employee['required_amount'] = $requiredAmount;
    $employee['total_bonuses'] = $totalBonuses;

    // Get payment details for display
    $paymentDetailsQuery = "SELECT payment_date, payment_type, description, amount, receipt, main_account_id 
                          FROM salary_payments 
                          WHERE user_id = ? 
                          AND DATE_FORMAT(payment_for_month, '%Y-%m') = ?
                          AND tenant_id = ?
                          ORDER BY payment_date DESC";
    $paymentDetailsStmt = $pdo->prepare($paymentDetailsQuery);
    $paymentDetailsStmt->execute([$employee['user_id'], $paymentForMonth, $tenant_id]);
    $employee['payment_details'] = $paymentDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $title; ?> - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0.5cm;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                font-size: 12pt;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-after: always;
            }
            .paid-stamp {
                opacity: 0.5;
            }
        }
        
        .paid-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72px;
            color: #28a745;
            border: 10px solid #28a745;
            padding: 10px 20px;
            border-radius: 10px;
            opacity: 0.3;
            pointer-events: none;
            z-index: 1000;
        }
        
        .payment-info {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #28a745;
        }
        
        .status-pending {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .employee-section {
            position: relative;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .title {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .subtitle {
            font-size: 18px;
            margin: 5px 0;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .employee-info {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #f5f5f5;
        }
        
        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .payroll-table th, .payroll-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .payroll-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .payroll-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .earnings, .deductions {
            margin-bottom: 20px;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #eee !important;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            border-top: 1px solid #333;
            padding-top: 5px;
            width: 200px;
            text-align: center;
        }
        
        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #eee;
            border-radius: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn-print {
            background-color: #2196F3;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            margin-right: 10px;
        }
        
        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .payment-details {
            margin-top: 20px;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .bonus-row {
            background-color: #d4edda !important;
        }
        
        .deduction-row {
            background-color: #f8d7da !important;
        }
        
        .payment-details h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        @media print {
            .bonus-row {
                background-color: #efffef !important;
            }
            
            .deduction-row {
                background-color: #fff5f5 !important;
            }
            
            .text-success {
                color: #28a745 !important;
            }
            
            .text-danger {
                color: #dc3545 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Controls -->
        <div class="controls no-print">
            <form method="get" action="">
                <?php if ($user_id): ?>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="month">Month:</label>
                    <select name="month" id="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <label for="year">Year:</label>
                    <select name="year" id="year">
                        <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <button type="submit" class="btn">Apply Filters</button>
                </div>
            </form>
            
            <button onclick="window.print();" class="btn btn-print">Print Payroll</button>
            <button onclick="window.history.back();" class="btn">Back</button>
        </div>
        
        <!-- Header -->
        <div class="header">
            <div class="company-name"><?php echo htmlspecialchars($settings['agency_name']); ?></div>
            <div class="title"><?php echo $title; ?></div>
            <div class="subtitle"><?php echo $subtitle; ?></div>
        </div>
        
        <!-- For each employee -->
        <?php foreach ($employees as $index => $employee): ?>
            <?php if ($index > 0 && !$user_id): ?>
                <div class="page-break"></div>
            <?php endif; ?>
            
            <div class="employee-section">
                <?php if ($employee['payment_status'] === 'paid'): ?>
                    <div class="paid-stamp">PAID</div>
                <?php endif; ?>
                
                <!-- Employee Information -->
                <div class="employee-info">
                    <table width="100%">
                        <tr>
                            <td width="50%"><strong>Employee:</strong> <?php echo htmlspecialchars($employee['employee_name']); ?></td>
                            <td width="50%"><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['user_id']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></td>
                            <td><strong>Phone:</strong> <?php echo htmlspecialchars($employee['phone']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Joining Date:</strong> <?php echo htmlspecialchars($employee['hire_date']); ?></td>
                            <td><strong>Payment Date:</strong> <?php echo date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $employee['payment_day'] . ', ' . $year; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><strong>Payment Status:</strong> 
                                <span class="payment-status status-<?php echo strtolower($employee['payment_status']); ?>">
                                    <?php echo strtoupper($employee['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="payment-info">
                        <table width="100%">
                            <tr>
                                <td><strong>Base Salary:</strong> <?php echo number_format($employee['base_salary'], 2) . ' ' . $employee['currency']; ?></td>
                                <td><strong>Bonuses:</strong> <?php echo number_format($employee['total_bonuses'], 2) . ' ' . $employee['currency']; ?></td>
                                <td><strong>Deductions:</strong> <?php echo number_format($totalDeductions, 2) . ' ' . $employee['currency']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Required Amount:</strong> <?php echo number_format($employee['required_amount'], 2) . ' ' . $employee['currency']; ?></td>
                                <td><strong>Amount Paid:</strong> <?php echo number_format($employee['amount_paid'], 2) . ' ' . $employee['currency']; ?></td>
                                <?php if ($employee['amount_remaining'] > 0): ?>
                                <td><strong>Remaining Balance:</strong> <?php echo number_format($employee['amount_remaining'], 2) . ' ' . $employee['currency']; ?></td>
                                <?php else: ?>
                                <td><strong class="text-success">Fully Paid</strong></td>
                                <?php endif; ?>
                            </tr>
                        </table>
                        
                        <?php if (!empty($employee['payment_details']) || !empty($employee['bonuses']) || !empty($employee['deductions'])): ?>
                        <div class="payment-details">
                            <h4>Payment History</h4>
                            <table class="payroll-table" style="margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Account/Type</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Show regular salary payments
                                    foreach ($employee['payment_details'] as $payment): 
                                        // Get account name
                                        $accountQuery = "SELECT name FROM main_account WHERE id = ?";
                                        $accountStmt = $pdo->prepare($accountQuery);
                                        $accountStmt->execute([$payment['main_account_id']]);
                                        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($account['name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($payment['payment_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['receipt']); ?></td>
                                        <td class="text-success"><?php echo '+ ' . number_format($payment['amount'], 2) . ' ' . $employee['currency']; ?></td>
                                    </tr>
                                    <?php endforeach; 

                                    // Show bonuses
                                    foreach ($employee['bonuses'] as $bonus): ?>
                                    <tr class="bonus-row" style="background-color: #d4edda;">
                                        <td><?php echo date('Y-m-d', strtotime($bonus['bonus_date'])); ?></td>
                                        <td>Bonus</td>
                                        <td><?php echo htmlspecialchars(ucfirst($bonus['type'])); ?></td>
                                        <td><?php echo htmlspecialchars($bonus['description']); ?></td>
                                        <td>-</td>
                                        <td class="text-success"><?php echo '+ ' . number_format($bonus['amount'], 2) . ' ' . $employee['currency']; ?></td>
                                    </tr>
                                    <?php endforeach;

                                    // Show deductions
                                    foreach ($employee['deductions'] as $deduction): ?>
                                    <tr class="deduction-row" style="background-color: #f8d7da;">
                                        <td><?php echo date('Y-m-d', strtotime($deduction['deduction_date'])); ?></td>
                                        <td>Deduction</td>
                                        <td><?php echo htmlspecialchars(ucfirst($deduction['type'])); ?></td>
                                        <td><?php echo htmlspecialchars($deduction['description']); ?></td>
                                        <td>-</td>
                                        <td class="text-danger"><?php echo '- ' . number_format($deduction['amount'], 2) . ' ' . $employee['currency']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Earnings -->
                <div class="earnings">
                    <h3>Earnings</h3>
                    <table class="payroll-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount (<?php echo htmlspecialchars($employee['currency']); ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Base Salary</td>
                                <td><?php echo number_format($employee['base_salary'], 2); ?></td>
                            </tr>
                            
                            <?php foreach ($employee['bonuses'] as $bonus): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bonus['description']); ?></td>
                                    <td><?php echo number_format($bonus['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php 
                            $totalBonuses = 0;
                            foreach ($employee['bonuses'] as $bonus) {
                                $totalBonuses += $bonus['amount'];
                            }
                            if ($totalBonuses > 0): 
                            ?>
                            <tr class="total-row">
                                <td>Total Bonuses</td>
                                <td><?php echo number_format($totalBonuses, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Deductions -->
                <div class="deductions">
                    <h3>Deductions</h3>
                    <table class="payroll-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount (<?php echo htmlspecialchars($employee['currency']); ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalDeductions = 0;
                            foreach ($employee['deductions'] as $deduction): 
                                $totalDeductions += $deduction['amount'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($deduction['description']); ?></td>
                                    <td><?php echo number_format($deduction['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; 
                            
                            foreach ($employee['advances'] as $advance):
                                $totalDeductions += $advance['amount'];
                            ?>
                                <tr>
                                    <td>Salary Advance (<?php echo date('M d, Y', strtotime($advance['advance_date'])); ?>)</td>
                                    <td><?php echo number_format($advance['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if ($totalDeductions > 0): ?>
                            <tr class="total-row">
                                <td>Total Deductions</td>
                                <td><?php echo number_format($totalDeductions, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="summary">
                    <h3>Payment Summary</h3>
                    <table class="payroll-table">
                        <tbody>
                            <tr>
                                <th>Base Salary</th>
                                <td><?php echo number_format($employee['base_salary'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Total Bonuses</th>
                                <td><?php echo number_format($totalBonuses, 2); ?></td>
                            </tr>
                            <tr>
                                <th>Total Deductions</th>
                                <td><?php echo number_format($totalDeductions, 2); ?></td>
                            </tr>
                            <tr class="total-row">
                                <th>Net Salary</th>
                                <td><?php echo number_format($employee['total_earnings'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Signature Section -->
                <div class="signature-section">
                    <div class="signature-box">
                        Employee Signature
                    </div>
                    <div class="signature-box">
                        Manager Signature
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($employees)): ?>
            <div class="no-data">
                <p>No payroll data found for the selected criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 