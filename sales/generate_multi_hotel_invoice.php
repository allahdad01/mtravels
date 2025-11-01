<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/db.php';

// Validate invoiceData
$invoiceData = isset($_POST['invoiceData']) ? DbSecurity::validateInput($_POST['invoiceData'], 'string', ['maxlength' => 255]) : null;

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    die('You must be logged in to access this resource');
}

// Check if invoice data is provided
if (!isset($_POST['invoiceData'])) {
    die('No invoice data provided');
}

// Decode the JSON data
$invoiceData = json_decode($_POST['invoiceData'], true);

// Validate the data
if (!isset($invoiceData['tickets']) || !is_array($invoiceData['tickets']) || count($invoiceData['tickets']) === 0) {
    die('No tickets selected for invoice');
}



// Get company/agency information from database (PDO)
$agencyInfoQuery = "SELECT * FROM settings WHERE tenant_id = :tenant_id";
$agencyStmt = $pdo->prepare($agencyInfoQuery);
$agencyStmt->bindValue(':tenant_id', (int)$tenant_id, PDO::PARAM_INT);
$agencyStmt->execute();
$agencyInfo = $agencyStmt->fetch(PDO::FETCH_ASSOC);
if (!$agencyInfo) {
    $agencyInfo = [
        'title' => 'Travel Agency',
        'address' => '123 Travel Street, Kabul, Afghanistan',
        'phone' => '+93 XXXXXXXXX',
        'email' => 'info@travelagency.com',
        'logo' => ''
    ];
}

// Get tickets information
$placeholders = implode(',', array_fill(0, count($invoiceData['tickets']), '?'));
$ticketsQuery = "SELECT tb.id, CONCAT(tb.title, ' ', tb.first_name, ' ', tb.last_name) AS guest_name, tb.order_id, tb.check_in_date, tb.check_out_date, 
                tb.accommodation_details, tb.sold_amount, tb.currency
                FROM hotel_bookings tb
                WHERE tb.id IN ($placeholders) AND tb.tenant_id = ?
                ORDER BY tb.id";

$ticketsStmt = $pdo->prepare($ticketsQuery);
$params = array_merge($invoiceData['tickets'], [(int)$tenant_id]);
$ticketsStmt->execute($params);
$tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalAmount = 0;
foreach ($tickets as $row) {
    $totalAmount += (float)$row['sold_amount'];
}

// --- 4. Fetch paid amounts for hotel bookings ---
$paidQuery = "SELECT reference_id, currency, SUM(amount) as paid FROM main_account_transactions WHERE transaction_of = 'hotel' AND reference_id IN ($placeholders) AND type = 'credit' AND tenant_id = ? GROUP BY reference_id, currency";
$stmt = $pdo->prepare($paidQuery);
$stmt->execute(array_merge($invoiceData['tickets'], [$tenant_id]));
$paidAmounts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $paidAmounts[$row['reference_id']][$row['currency']] = $row['paid'];
}

// Calculate total paid amounts across all currencies
$totalPaid = [];
foreach ($paidAmounts as $ticketPaid) {
    foreach ($ticketPaid as $curr => $amt) {
        $totalPaid[$curr] = ($totalPaid[$curr] ?? 0) + $amt;
    }
}

// Define currency
$currency = $invoiceData['currency'];
$comments = $invoiceData['comment'];
$clientName = $invoiceData['clientName'];

// Generate invoice number
$invoiceNumber = 'INV-' . time() . '-' . rand(1000, 9999);
$invoiceDate = date('Y-m-d');
// --- 3. Fetch bank accounts from main_account table ---
try {
    $bankAccountsQuery = "SELECT name, bank_name, bank_account_number, bank_account_afs_number FROM main_account WHERE tenant_id = ? AND status = 'active' AND account_type = 'bank' AND bank_account_number IS NOT NULL AND bank_account_number <> '' ORDER BY name";
    $stmt = $pdo->prepare($bankAccountsQuery);
    $stmt->execute([$tenant_id]);
    $bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bankAccounts = [];
}
// Now generate the HTML for the invoice
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoiceNumber; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .invoice-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 33%;
        }
        .title-container {
            width: 33%;
            text-align: left;
        }
        .invoice-id {
            width: 33%;
            text-align: right;
        }
        .logo-image {
            max-height: 120px;
            max-width: 200px;
            margin-bottom: 5px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .company-info, .client-info {
            width: 48%;
        }
        .info-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background-color: #f8f8f8;
            font-weight: bold;
            color: #333;
        }
        .amount {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            font-size: 1.1em;
            background-color: #f0f0f0;
        }
        .comments {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
        }
        .print-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px 0;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
        }
        .signature-box, .stamp-box {
            border-top: 1px solid #ddd;
            width: 45%;
            text-align: center;
            padding-top: 5px;
        }
        .unauthorized-text {
            text-align: center;
            margin-top: 20px;
            font-style: italic;
            color: #555;
        }
        
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .invoice-container {
                box-shadow: none;
                padding: 0;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <button class="print-button" onclick="window.print()">Print Invoice</button>
        
        <div class="invoice-header">
            <div class="title-container">
                <div class="logo-text"><?php echo htmlspecialchars($agencyInfo['title']); ?></div>
                <div>Professional Travel Services</div>
            </div>
            
            <div class="logo-container">
                <?php if (!empty($agencyInfo['logo'])): ?>
                <img src="<?php echo htmlspecialchars('../uploads/logo/' . $agencyInfo['logo']); ?>" alt="Company Logo" class="logo-image">
                <?php endif; ?>
            </div>
            
            <div class="invoice-id">
                <div>Invoice #: <?php echo $invoiceNumber; ?></div>
                <div>Date: <?php echo date('F j, Y', strtotime($invoiceDate)); ?></div>
            </div>
        </div>
        
        <div class="invoice-info">
            <div class="company-info">
                <div class="info-title">From:</div>
                <div><?php echo htmlspecialchars($agencyInfo['title']); ?></div>
                <?php 
                // Split address into multiple lines if it contains commas
                $addressLines = explode(',', $agencyInfo['address']);
                foreach ($addressLines as $line) {
                    echo '<div>' . htmlspecialchars(trim($line)) . '</div>';
                }
                ?>
                <div>Phone: <?php echo htmlspecialchars($agencyInfo['phone']); ?></div>
                <div>Email: <?php echo htmlspecialchars($agencyInfo['email']); ?></div>
            </div>
            
            <div class="client-info">
                <div class="info-title">Invoice To:</div>
                <div><?php echo $clientName; ?></div>
                <div>&nbsp;</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Passenger Name</th>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Accommodation Details</th>
                    <th class="amount">Amount (<?php echo $currency; ?>)</th>
                    <th class="amount">Paid Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $index => $ticket): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($ticket['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['order_id']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($ticket['check_in_date'] . ' to ' . $ticket['check_out_date']); ?>


                    </td>
                    <td><?php echo htmlspecialchars($ticket['accommodation_details']); ?></td>
                    <td class="amount"><?php echo number_format($ticket['sold_amount'], 2); ?></td>
                    <td class="amount">
                        <?php
                        $paidStr = '';
                        if (isset($paidAmounts[$ticket['id']])) {
                            ksort($paidAmounts[$ticket['id']]); // Sort currencies alphabetically
                            foreach ($paidAmounts[$ticket['id']] as $curr => $amt) {
                                $paidStr .= number_format($amt, 2) . ' ' . $curr . ', ';
                            }
                            $paidStr = rtrim($paidStr, ', ');
                        }
                        echo $paidStr ?: '0';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;">Total:</td>
                    <td class="amount"><?php echo number_format($totalAmount, 2); ?></td>
                    <td class="amount">
                        <?php
                        $totalStr = '';
                        if (!empty($totalPaid)) {
                            ksort($totalPaid); // Sort currencies alphabetically
                            foreach ($totalPaid as $curr => $amt) {
                                $totalStr .= number_format($amt, 2) . ' ' . $curr . ', ';
                            }
                            $totalStr = rtrim($totalStr, ', ');
                        }
                        echo $totalStr ?: '0';
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if (!empty($comments)): ?>
        <div class="comments">
            <div class="info-title">Comments/Notes:</div>
            <div><?php echo nl2br(htmlspecialchars($comments)); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            
            <div class="signature-section">
                <div class="stamp-box">
                    <p>Company Stamp</p>
                </div>
                <div class="signature-box">
                    <p>CEO Signature</p>
                </div>
            </div>
            <div class="unauthorized-text">
                <p>This invoice is not valid without company stamp and authorized signature</p>
            </div>
            <div style="margin-top: 20px; text-align: left; border-top: 1px solid #ddd; padding-top: 10px;">
                <p style="font-weight: bold; margin-bottom: 5px;">Bank Account Details:</p>
                <?php if (!empty($bankAccounts)): ?>
                    <?php foreach ($bankAccounts as $bank): ?>
                        <?php 
                            $label = !empty($bank['bank_name']) ? $bank['bank_name'] : $bank['name'];
                            $usd = trim((string)($bank['bank_account_number'] ?? ''));
                            $afs = trim((string)($bank['bank_account_afs_number'] ?? ''));
                        ?>
                        <p style="margin: 6px 0 2px 0; font-weight: bold;"><?php echo htmlspecialchars($label); ?></p>
                        <?php if ($usd !== ''): ?>
                            <p style="margin: 2px 0 2px 12px;">USD Account: <?php echo htmlspecialchars($usd); ?></p>
                        <?php endif; ?>
                        <?php if ($afs !== ''): ?>
                            <p style="margin: 2px 0 2px 12px;">AFS Account: <?php echo htmlspecialchars($afs); ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="margin: 2px 0;">No bank accounts found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 