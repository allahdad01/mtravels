<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once '../config.php';
require_once '../includes/db.php';

// Include security module
require_once 'security.php';

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit();
}

// Only super_admin can access this endpoint
// Tenant admins should use their own invoice generation with proper tenant isolation
if ($_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for CSRF token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid request - CSRF token validation failed');
    }
}

// Get parameters from POST (more secure than GET)
$payment_id = intval($_POST['payment_id'] ?? $_GET['payment_id'] ?? 0);
$subscription_id = intval($_POST['subscription_id'] ?? $_GET['subscription_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? $_GET['amount'] ?? 0);
$currency = $_POST['currency'] ?? $_GET['currency'] ?? 'USD';
$payment_date = $_POST['payment_date'] ?? $_GET['payment_date'] ?? date('Y-m-d');
$payment_method = $_POST['payment_method'] ?? $_GET['payment_method'] ?? '';
$transaction_id = $_POST['transaction_id'] ?? $_GET['transaction_id'] ?? '';
$receipt_number = $_POST['receipt_number'] ?? $_GET['receipt_number'] ?? '';
$notes = $_POST['notes'] ?? $_GET['notes'] ?? '';

// If payment_id is provided, fetch from database
if ($payment_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT sp.*, ts.id as subscription_id, ts.tenant_id
            FROM subscription_payments sp
            LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
            WHERE sp.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment_record) {
            die('Payment record not found');
        }
        
        // Use payment record data
        $subscription_id = $payment_record['subscription_id'];
        $amount = $payment_record['amount'];
        $currency = $payment_record['currency'];
        $payment_date = $payment_record['payment_date'];
        $payment_method = $payment_record['payment_method'];
        $transaction_id = $payment_record['transaction_id'];
        $receipt_number = $payment_record['receipt_number'];
        $notes = $payment_record['notes'];
    } catch (PDOException $e) {
        die("Error fetching payment");
    }
} elseif (!$subscription_id || !$amount) {
    die('Invalid parameters');
}

// Fetch subscription and tenant details
try {
    $stmt = $pdo->prepare("
        SELECT ts.*, t.id as tenant_id, t.name as tenant_name, t.identifier as tenant_identifier,
               t.billing_email as tenant_email,
               p.name as plan_name, p.description as plan_description
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        WHERE ts.id = ?
    ");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        die('Subscription not found');
    }
} catch (PDOException $e) {
    die("Error fetching subscription: " . $e->getMessage());
}

// Get company details from platform_settings
try {
    $stmt = $pdo->prepare("
        SELECT `key`, `value` 
        FROM platform_settings 
        WHERE `key` IN ('platform_name', 'contact_email', 'contact_phone', 'contact_address', 'platform_logo')
    ");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $company_name = $settings['platform_name'] ?? 'MTravels';
    $company_email = $settings['contact_email'] ?? 'allahdadmuhammadi01@gmail.com';
    $company_phone = $settings['contact_phone'] ?? '0780310431';
    $company_address = $settings['contact_address'] ?? 'Kabul, Afghanistan';
    $platform_logo = $settings['platform_logo'] ?? null;
} catch (PDOException $e) {
    // Fallback values
    $company_name = 'MTravels';
    $company_email = 'allahdadmuhammadi01@gmail.com';
    $company_phone = '0780310431';
    $company_address = 'Kabul, Afghanistan';
    $platform_logo = null;
}

// Prepare logo HTML if logo exists
$logo_html = '';
if ($platform_logo) {
    $logo_path = '../uploads/logo/' . basename($platform_logo);
    if (file_exists($logo_path)) {
        $logo_html = '<img src="' . $logo_path . '" alt="Company Logo" style="max-height: 60px; margin-bottom: 10px;">';
    }
}

// Helper function for currency symbol - mPDF compatible
function getCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AFN' => 'AFN',  // Use code instead of symbol for better compatibility
        'AED' => 'AED',  // Use code instead of symbol
        'INR' => '₹',
        'PKR' => 'PKR',  // Use code instead of symbol
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

// Generate unique invoice number
$invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($subscription_id, 5, '0', STR_PAD_LEFT);

// HTML content for invoice
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
            line-height: 1.6;
            background: #f5f7fa;
        }
        .page {
            max-width: 900px;
            margin: 0;
            background: white;
            overflow: hidden;
        }
        .header-section {
            background-color: #f8f9fa;
            border-bottom: 3px solid #667eea;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        .company-info-cell {
            width: 70%;
            padding-right: 20px;
        }
        .company-logo-cell {
            width: 30%;
            text-align: right;
            vertical-align: middle;
        }
        .company-logo-cell img {
            max-height: 70px;
            max-width: 100px;
        }
        .company-info h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 3px 0;
            color: #2c3e50;
            letter-spacing: -0.3px;
        }
        .company-info .tagline {
            font-size: 11px;
            color: #667eea;
            margin-bottom: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-contact {
            font-size: 10px;
            line-height: 1.6;
            color: #666;
        }
        .company-contact p {
            margin: 1px 0;
        }
        .invoice-header {
            text-align: right;
            flex-shrink: 0;
            padding-left: 20px;
            border-left: 1px solid #ddd;
        }
        .invoice-header .invoice-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            font-weight: 600;
        }
        .invoice-header .invoice-number {
            font-size: 18px;
            font-weight: 700;
            margin: 2px 0;
            color: #667eea;
        }
        .invoice-header .invoice-date {
            font-size: 11px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            color: #666;
        }
        .content {
            padding: 20px 30px;
        }
        .invoice-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 12px;
        }
        .meta-box {
            flex: 1;
        }
        .meta-box h3 {
            font-size: 10px;
            text-transform: uppercase;
            color: #667eea;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid #667eea;
        }
        .meta-box p {
            font-size: 11px;
            margin: 3px 0;
            color: #555;
            line-height: 1.5;
        }
        .meta-box strong {
            color: #2c3e50;
            font-weight: 600;
        }
        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 10px 0;
        }
        .items-section {
            margin: 12px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 8px 0;
        }
        .items-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .items-table th {
            padding: 8px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.4px;
        }
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        .items-table tbody tr:hover {
            background-color: #f8f9ff;
        }
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .items-table td.amount {
            text-align: right;
            font-weight: 600;
            color: #667eea;
        }
        /* Summary & Payment Details Table Format */
        .summary-and-payment {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
            font-size: 11px;
        }
        
        .summary-payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        
        .summary-payment-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .summary-payment-table th {
            padding: 8px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.4px;
            border: none;
        }
        
        .summary-payment-table th:first-child {
            width: 45%;
        }
        
        .summary-payment-table th:nth-child(2) {
            width: 20%;
            text-align: center;
        }
        
        .summary-payment-table th:nth-child(3) {
            width: 20%;
            text-align: right;
        }
        
        .summary-payment-table th:last-child {
            width: 15%;
            text-align: right;
        }
        
        .summary-payment-table td {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .summary-payment-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .summary-payment-table tbody tr:nth-child(even) {
            background-color: #f8f9ff;
        }
        
        .summary-payment-table td.desc {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .summary-payment-table td.center {
            text-align: center;
        }
        
        .summary-payment-table td.right {
            text-align: right;
            font-weight: 500;
        }
        
        .summary-payment-table td.amount {
            text-align: right;
            font-weight: 700;
            color: #667eea;
            font-size: 12px;
        }
        
        .summary-payment-table tr.total-row td {
            background: #2c3e50;
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 8px;
        }
        
        .summary-payment-table tr.total-row td.desc {
            color: #ffffff;
        }
        
        .summary-payment-table tr.total-row td.amount {
            color: #ffffff;
            font-size: 13px;
        }
        
        .summary-payment-table tr.subtotal-row td {
            background-color: #f8f9ff;
            font-weight: 600;
        }
        
        .payment-status {
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            padding: 6px 10px;
            margin: 8px 0 0 0;
            text-align: center;
            font-weight: 600;
            font-size: 10px;
            border: 1px solid #c3e6cb;
            width: 100%;
            box-sizing: border-box;
            display: inline-block;
        }
        
        .payment-status::before {
            content: "✓ ";
        }
        
        /* Notes Section */
        .notes-section {
            clear: both;
            width: 100%;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: #fff9e6;
            border: 1px solid #ffd966;
            border-left: 3px solid #ffc000;
            border-radius: 3px;
            box-sizing: border-box;
        }
        
        .notes-section h4 {
            margin: 0 0 4px 0;
            color: #cc8800;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.4px;
        }
        
        .notes-section p {
            font-size: 10px;
            color: #5a5a5a;
            line-height: 1.4;
            margin: 0;
            white-space: pre-wrap;
        }
        
        .footer-section {
            background-color: #f8f9fa;
            padding: 8px 30px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 10px;
            color: #888;
        }
        
        .footer-section p {
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header Section -->
         <div class="header-section">
             <table class="header-table">
                 <tr>
                     <td class="company-info-cell">
                         <h1>' . htmlspecialchars($company_name) . '</h1>
                         <div class="tagline">INVOICE</div>
                         <div class="company-contact">
                             <p>' . htmlspecialchars($company_email) . '</p>
                             <p>' . htmlspecialchars($company_phone) . '</p>
                         </div>
                     </td>
                     <td class="company-logo-cell">
                         ' . $logo_html . '
                     </td>
                 </tr>
             </table>
             <div class="invoice-header">
                 <div class="invoice-label">Invoice #</div>
                 <div class="invoice-number">' . htmlspecialchars($invoice_number) . '</div>
                 <div class="invoice-date">
                     <strong>Date:</strong> ' . date('M d, Y', strtotime($payment_date)) . '<br>
                     <strong>Status:</strong> <span style="color: #28a745; font-weight: 600;">PAID</span>
                 </div>
             </div>
         </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Invoice Meta Information -->
            <div class="invoice-meta">
                <div class="meta-box">
                    <h3>BILL TO</h3>
                    <p><strong>' . htmlspecialchars($subscription['tenant_name']) . '</strong></p>
                    <p>Identifier: ' . htmlspecialchars($subscription['tenant_identifier']) . '</p>
                    ' . (!empty($subscription['tenant_email']) ? '<p>Email: ' . htmlspecialchars($subscription['tenant_email']) . '</p>' : '') . '
                </div>
                <div class="meta-box">
                    <h3>SUBSCRIPTION DETAILS</h3>
                    <p><strong>Plan:</strong> ' . htmlspecialchars($subscription['plan_name']) . '</p>
                    <p><strong>Billing Cycle:</strong> ' . ucfirst($subscription['billing_cycle']) . '</p>
                    <p><strong>Period:</strong> ' . date('M d, Y', strtotime($payment_date)) . '</p>
                </div>
            </div>

            <!-- Divider -->
            <div class="divider"></div>

            <!-- Payment Status Badge -->
            <div class="payment-status">
                Payment Received - Thank you for your payment
            </div>

            <!-- Items Section -->
            <div class="items-section">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Service Description</th>
                            <th style="width: 15%; text-align: center;">Qty</th>
                            <th style="width: 20%; text-align: right;">Unit Price</th>
                            <th style="width: 15%; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>' . htmlspecialchars($subscription['plan_name']) . '</strong></td>
                            <td style="text-align: center;">1</td>
                            <td class="text-right">' . number_format($amount, 2) . ' ' . getCurrencySymbol($currency) . '</td>
                            <td class="amount">' . number_format($amount, 2) . ' ' . getCurrencySymbol($currency) . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Payment Details Table -->
            <table class="summary-payment-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="desc">Subtotal</td>
                        <td class="center">-</td>
                        <td class="center">-</td>
                        <td class="amount">' . number_format($amount, 2) . ' ' . getCurrencySymbol($currency) . '</td>
                    </tr>
                    <tr>
                        <td class="desc">Tax</td>
                        <td class="center">-</td>
                        <td class="center">-</td>
                        <td class="amount">0.00 ' . getCurrencySymbol($currency) . '</td>
                    </tr>
                    <tr>
                        <td class="desc">Payment Info</td>
                        <td class="center">' . date('M d, Y', strtotime($payment_date)) . '</td>
                        <td class="center">' . (htmlspecialchars($payment_method) ?: '-') . '</td>
                        <td class="amount">' . number_format($amount, 2) . ' ' . getCurrencySymbol($currency) . '</td>
                    </tr>
                    ' . (!empty($transaction_id) ? '<tr>
                        <td class="desc">Transaction ID: ' . htmlspecialchars(substr($transaction_id, 0, 12)) . '</td>
                        <td colspan="3" class="right">Receipt #: ' . (htmlspecialchars($receipt_number) ?: 'N/A') . '</td>
                    </tr>' : '') . '
                </tbody>
            </table>
            

            <!-- Notes Section - Full Width Row -->
            ' . (!empty($notes) ? '<div class="notes-section">
                <h4>Additional Notes</h4>
                <p>' . htmlspecialchars($notes) . '</p>
            </div>' : '') . '
        </div>

        <!-- Footer Section -->
        <div class="footer-section">
            <p style="font-weight: 600; color: #2c3e50;">Thank you for your business!</p>
            <p style="margin-top: 4px;">For inquiries: ' . htmlspecialchars($company_email) . ' | ' . htmlspecialchars($company_phone) . '</p>
            <p style="font-size: 9px; color: #999; margin-top: 2px;">Generated: ' . date('M d, Y at h:i A') . '</p>
        </div>
    </div>
</body>
</html>
';

// Generate PDF using mPDF
try {
    require_once '../vendor/autoload.php';
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    
    $mpdf->WriteHTML($html);
    
    // Check if this is being called from email function to save as file
    if (isset($_GET['output']) && $_GET['output'] === 'file' && isset($_GET['output_path'])) {
        $output_path = $_GET['output_path'];
        $mpdf->Output($output_path, \Mpdf\Output\Destination::FILE);
        exit(); // Exit without redirecting to prevent execution of rest of code
    } else {
        // Normal browser download
        $filename = 'Invoice-' . $invoice_number . '-' . date('Y-m-d') . '.pdf';
        $mpdf->Output($filename, 'D');
        exit();
    }
    
} catch (Exception $e) {
    // Fallback: Output HTML if PDF generation fails
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit();
}
?>
