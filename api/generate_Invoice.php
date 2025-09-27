<?php
session_start();

include '../includes/db.php';
require '../vendor/autoload.php';

$username = $_SESSION['name'] ?? 'Unknown'; // User identifier from session

use Dompdf\Dompdf;
ini_set('memory_limit', '256M'); // You can adjust the value

// Fetch the JSON payload sent from the front-end
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON payload!']);
    exit;
}

$clientId = $data['client'] ?? null;
$invoiceType = $data['type'] ?? null;
$items = $data['items'] ?? null;
$mainAccountId = $data['mainAccount'] ?? null;
$invoiceDate = $data['invoiceDate'] ?? null;

if (empty($clientId) || empty($invoiceType) || empty($items) || empty($mainAccountId) || empty($invoiceDate)) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing required fields']);
    exit;
}

// Validate items array
if (!is_array($items) || empty($items)) {
    http_response_code(400);
    echo json_encode(['message' => 'Items must be a non-empty array']);
    exit;
}

foreach ($items as $item) {
    if (!isset($item['itemId']) || !isset($item['invoiceNumber'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Each item must include a valid itemId and invoiceNumber']);
        exit;
    }
    
    // Check if the invoice number already exists
    $checkInvoiceStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
    $checkInvoiceStmt->execute([$item['invoiceNumber']]);
    $invoiceExists = $checkInvoiceStmt->fetchColumn();
    
    if ($invoiceExists > 0) {
        http_response_code(400);
        echo json_encode(['message' => 'Invoice number ' . $item['invoiceNumber'] . ' already exists. Please use a different number.']);
        exit;
    }
}

$tableMap = [
    'ticket' => 'ticket_bookings',
    'refund_ticket' => 'refunded_tickets',
    'date_change_ticket' => 'date_change_tickets',
    'visa' => 'visa_applications'
];

$table = $tableMap[$invoiceType] ?? null;
if (!$table) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid invoice type']);
    exit;
}

$settingsQuery = $pdo->query("SELECT title, logo, phone, email, address FROM settings LIMIT 1");
$settings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    throw new Exception("Agency settings not found in the database.");
}

$logo = $settings['logo'];
$title = $settings['title'];
$phone = $settings['phone'];
$email = $settings['email'];
$address = $settings['address'];

$clientQuery = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
$clientQuery->execute([$clientId]);
$client = $clientQuery->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    http_response_code(400);
    echo json_encode(['message' => 'Client not found']);
    exit;
}

$mainAccountQuery = $pdo->prepare("SELECT name FROM main_account WHERE id = ?");
$mainAccountQuery->execute([$mainAccountId]);
$mainAccount = $mainAccountQuery->fetch(PDO::FETCH_ASSOC);
if (!$mainAccount) {
    http_response_code(400);
    echo json_encode(['message' => 'Main account not found']);
    exit;
}

$pdo->beginTransaction();

$logoPath = '../uploads/logo.png'; // Local path to logo
$logoData = base64_encode(file_get_contents($logoPath));
$logoSrc = 'data:image/png;base64,' . $logoData;

$totalAmountUSD = 0;
$totalAmountAFS = 0;
$receiptRows = '';
$invoiceDetailsHTML = '';

foreach ($items as $item) {
    $itemId = $item['itemId'] ?? null;  // This can trigger a null default but no undefined issues.
if (is_null($itemId)) {
    throw new Exception("Item ID is required, but was not found for item: " . json_encode($item));
}
    $itemCurrency = $selectedItem['currency'] ?? 'N/A';
     // Add USD and AFS totals conditionally
    if ($itemCurrency == 'USD') {
        $totalAmountUSD += $itemSold;
    } else if ($itemCurrency == 'AFS') {
        $totalAmountAFS += $itemSold;
    }
    $itemId = $item['itemId'] ?? null;
    $invoiceNumber = $item['invoiceNumber'] ?? null;

    if (empty($itemId) || empty($invoiceNumber)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['message' => 'Each item must include a valid itemId and invoiceNumber']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$itemId]);
    $selectedItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedItem) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['message' => "Item with ID $itemId not found in $table"]);
        exit;
    }

    // Set values with default fallbacks
    $itemPassengerName = $selectedItem['passenger_name'] ?? 'Unknown Passenger';
    $itemPNR = $selectedItem['pnr'] ?? 'N/A';
    $itemSector = $selectedItem['origin'] . ' - ' . $selectedItem['destination'] ?? 'N/A';
    $itemIssueDate = $selectedItem['issue_date'] ?? $selectedItem['issued_date'];
    $itemIssuedDate = $selectedItem['issued_date'] ?? 'N/A';
    $itemDepartureDate = $selectedItem['departure_date'] ?? 'N/A';
    $itemSold = $selectedItem['sold'] ?? 0;
    $itemCurrency = $selectedItem['currency'] ?? 'N/A';

    // Calculate itemTax correctly
    $supplierPenalty = $selectedItem['supplier_penalty'] ?? 0;
    $servicePenalty = $selectedItem['service_penalty'] ?? 0;
    $itemTax = ($supplierPenalty + $servicePenalty);  // Correct calculation for itemTax

    // Refund amount
    $itemRefundAmount = $selectedItem['refund_to_passenger'] ?? 'N/A';

    // Applicant details
    $itemApplicantName = $selectedItem['applicant_name'] ?? 'N/A';
    $itemPassportNumber = $selectedItem['passport_number'] ?? 'N/A';
    $itemVisaType = $selectedItem['visa_type'] ?? 'N/A';
    // Ensure itemAmaount is also correctly calculated
    $itemAmaount = ($supplierPenalty + $servicePenalty); // Correct calculation for itemAmaount





    $receiptRows .= "
        <tr>
            <td>{$invoiceNumber}</td>
            <td>{$itemIssueDate}</td>
            <td>{$itemSector}</td>
            <td>{$itemSold} {$itemCurrency}</td>
        </tr>
    ";

   

    $invoiceDetailsHTML .= "

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            width: 90%;
            align-items: center;
            padding: 50px 30px;
            background-color: #f9f9f9;
            border-bottom: 2px solid #ddd;
        }
        .header .logo-container {
            float: left;
            width: 20%;
            max-height: 100px;
        }
        .header .logo {
            max-width: 100%;
            max-height: 100px;
        }
        .header .agency-details {
            text-align: right;
        }
        .header .agency-details strong {
            font-size: 22px;
            margin-bottom: 5px;
        }
        .header .agency-details small {
            font-size: 14px;
            display: block;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin: 20px 30px;
        }
        .info-section .left, .info-section .right {
            width: 100%;
        }
        .info-section .right {
            text-align: right;
        }
        .info-section p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            margin-top: 20px;
            font-size: 14px;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
    <div style='page-break-after: always;'>
        <div class='container'>
            <div class='header'>
                <div class='logo-container'>
                    <img src='{$logoSrc}' alt='Logo' class='logo'>
                </div>
                <div class='agency-details'>
                    <strong>{$title}</strong><br>
                    <small>Phone: {$phone}</small>
                    <small>Email: {$email}</small>
                    <small>Address: {$address}</small>
                </div>
            </div>
            <div class='info-section'>
                <div class='left'>
                    <p><strong>{$client['name']}</strong></p>
                </div>
                <div class='right'>
                    <p><strong>Invoice Number:</strong> {$invoiceNumber}</p>
                    <p><strong>Invoice Date:</strong> {$invoiceDate}</p>
                    <p><strong>Terms:</strong> Due on Receipt</p>
                    <p><strong>Due Date:</strong> {$invoiceDate}</p>
                </div>";

// Generate table content dynamically based on invoice type
$tableHeaders = '';
$tableRows = '';

if ($invoiceType === 'ticket') {
    $tableHeaders = "
        <tr>
            <th>Passenger Name</th>
            <th>PNR</th>
            <th>Sector</th>
            <th>Issue Date</th>
            <th>Departure Date</th>
            <th>Sold</th>
        </tr>";
    $tableRows = "
        <tr>
            <td>{$itemPassengerName}</td>
            <td>{$itemPNR}</td>
            <td>{$itemSector}</td>
            <td>{$itemIssueDate}</td>
            <td>{$itemDepartureDate}</td>
            <td>{$itemSold} {$itemCurrency}</td>
        </tr>";
} elseif ($invoiceType === 'refund_ticket') {
    $tableHeaders = "
        <tr>
            <th>Passenger Name</th>
            <th>PNR</th>
            <th>Sector</th>
            <th>Issue Date</th>
            <th>Refund Amount</th>
            <th>Charges</th>
        </tr>";
    $tableRows = "
        <tr>
            <td>{$itemPassengerName}</td>
            <td>{$itemPNR}</td>
            <td>{$itemSector}</td>
            <td>{$itemIssueDate}</td>
            <td>{$itemRefundAmount} {$itemCurrency}</td>
            <td>{$itemTax} {$itemCurrency}</td>
        </tr>";
} elseif ($invoiceType === 'date_change_ticket') {
    $tableHeaders = "
        <tr>
            <th>Passenger Name</th>
            <th>PNR</th>
            <th>Sector</th>
            <th>Issue Date</th>
            
            <th>Charges</th>
        </tr>";
    $tableRows = "
        <tr>
            <td>{$itemPassengerName}</td>
            <td>{$itemPNR}</td>
            <td>{$itemSector}</td>
            <td>{$itemIssueDate}</td>
            <td>{$itemTax} {$itemCurrency}</td>
        </tr>";
    }   elseif ($invoiceType === 'visa') {
    $tableHeaders = "
        <tr>
            <th>Applicant Name</th>
            <th>Passport Number</th>
            <th>Visa Type</th>
            <th>Issue Date</th>
            <th>Sold</th>
        </tr>";
    $tableRows = "
        <tr>
            <td>{$itemApplicantName}</td>
            <td>{$itemPassportNumber}</td>
            <td>{$itemVisaType}</td>
            <td>{$itemIssuedDate}</td>
            <td>{$itemSold} {$itemCurrency}</td>
        </tr>";
} else {
    // Default case or other types
    $tableHeaders = "<tr><th>Details</th></tr>";
    $tableRows = "<tr><td>No additional information available.</td></tr>";
}

$invoiceDetailsHTML .= "
    <table>
        {$tableHeaders}
        {$tableRows}
    </table>
    <div style='margin-top: 30px; padding: 10px; border-top: 1px solid #ddd;'>
        <div style='text-align: center; margin-bottom: 10px;'>
            <p style='color: #cc0000; font-weight: bold; font-size: 14px;'>THIS INVOICE IS UNAUTHORIZED WITHOUT SIGNATURE AND STAMP</p>
        </div>
        <table style='width: 100%; border: none;'>
            <tr>
                <td style='width: 48%; text-align: center; padding: 5px;'>
                    <div style='border: 1px dashed #999; height: 70px; margin-bottom: 5px;'></div>
                    <p style='margin: 0; font-size: 12px;'><strong>Company Stamp</strong></p>
                </td>
                <td style='width: 4%;'></td>
                <td style='width: 48%; text-align: center; padding: 5px;'>
                    <div style='border-bottom: 1px solid #000; height: 50px; margin-bottom: 5px;'></div>
                    <p style='margin: 0; font-size: 12px;'><strong>CEO Signature</strong></p>
                </td>
            </tr>
        </table>
    </div>
</div></div></div>";
}



// Generate the final HTML content for PDF
$receiptHTML = "
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        h1, h2 {
            text-align: center;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 90%;
            padding: 20px 30px;
            background-color: #f9f9f9;
            border-bottom: 2px solid #ddd;
            margin: 0 auto;
        }
        .logo-container {
            flex: 1;
            width: 25%;
            max-height: 120px;
        }
        .logo {
            max-width: 100%;
            max-height: 120px;
        }
        .agency-details {
            flex: 2;
            text-align: right;
        }
        .agency-details strong {
            font-size: 22px;
            margin-bottom: 5px;
        }
        .agency-details small {
            font-size: 14px;
            display: block;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 20px auto;
            padding: 20px 0;
            width: 90%;
            border-bottom: 1px solid #ddd;
        }
        .info-column {
            flex: 1;
            margin-right: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ccc;
        }
        .info-label {
            font-weight: bold;
            font-size: 14px;
        }
        .info-value {
            font-size: 14px;
            text-align: right;
        }
        .total-amount-box {
            flex: 0.4;
            background-color: #eaf7ea; /* Soft green color */
            color: #2d572c; /* Darker green text */
            padding: 15px;
            font-size: 18px;
            text-align: center;
            font-weight: bold;
            border-radius: 8px;
            box-shadow: 0px 1px 5px rgba(0, 0, 0, 0.1);
        }
        .receipt-details {
            margin: 30px auto;
            width: 90%;
        }
        table {
            width: 100%;
            margin-top: 20px;
            font-size: 14px;
            border-collapse: collapse;
            border: none;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: none;
        }
        th {
            background-color: #f4f4f4;
        }
        td {
            background-color: #fff;
        }
        hr {
            border: 0;
            height: 1px;
            background: #ddd;
        }
    </style>
    <div class='header'>
        <!-- Logo Section -->
        <div class='logo-container'>
             <img src='{$logoSrc}' alt='Logo' class='logo'>
        </div>
        <!-- Agency Details -->
        <div class='agency-details'>
            <strong>{$title}</strong><br>
            <small>Phone: {$phone}</small>
            <small>Email: {$email}</small>
            <small>Address: {$address}</small>
        </div>
    </div>
    <div>
        <h1>Payment Receipt</h1>

        <p style='text-align: right;'><strong>Due Date:</strong> {$invoiceDate}</p>

        <div class='info-section'>
            <!-- Left Column with Info Rows -->
            <div class='info-column'>
                <div class='info-row'>
                    <span class='info-label'>Payment Date:</span>
                    <span class='info-value'>{$invoiceDate}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Reference:</span>
                    <span class='info-value'>Amount Processed by {$username}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Paid To:</span>
                    <span class='info-value'>{$mainAccount['name']}</span>
                </div>
            </div>

            <!-- Right Column with Total Amount -->
            <div class='total-amount-box'>
                <p>Amount Received</p>
                <p>{$itemSold} {$itemCurrency}</p>
            </div>
        </div>

        <h3 style='margin: 20px auto; text-align: left;'><strong>Received From:</strong> {$client['name']}</h3>

        <div class='receipt-details'>
            <h2 style='margin: 20px auto; text-align: left;'>Payment for</h2>
            <table>
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Invoice Date</th>
                        <th>Sector</th>
                        <th>Payment Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {$receiptRows}
                </tbody>
            </table>
        </div>
        <div style='margin-top: 30px; padding: 10px; border-top: 1px solid #ddd;'>
            <div style='text-align: center; margin-bottom: 10px;'>
                <p style='color: #cc0000; font-weight: bold; font-size: 14px;'>THIS INVOICE IS UNAUTHORIZED WITHOUT SIGNATURE AND STAMP</p>
            </div>
            <table style='width: 100%; border: none;'>
                <tr>
                    <td style='width: 48%; text-align: center; padding: 5px;'>
                        <div style='border: 1px dashed #999; height: 70px; margin-bottom: 5px;'></div>
                        <p style='margin: 0; font-size: 12px;'><strong>Company Stamp</strong></p>
                    </td>
                    <td style='width: 4%;'></td>
                    <td style='width: 48%; text-align: center; padding: 5px;'>
                        <div style='border-bottom: 1px solid #000; height: 50px; margin-bottom: 5px;'></div>
                        <p style='margin: 0; font-size: 12px;'><strong>CEO Signature</strong></p>
                    </td>
                </tr>
            </table>
        </div>
    </div>
";

$html = $receiptHTML . $invoiceDetailsHTML;

// Set options for Dompdf
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->set_option('isHtml5ParserEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

try {
    // Generate PDF Output
    $dompdf->render();
    $pdfOutput = $dompdf->output();

    if (!$pdfOutput) {
        throw new Exception("PDF content could not be generated.");
    }

    $pdo->commit(); // Commit the transaction after the invoice is processed

    // Stream the PDF directly to the browser
    $dompdf->stream("invoice_summary_{$invoiceDate}.pdf", ["Attachment" => false]);
    exit;

} catch (Exception $e) {
    // Roll back the transaction in case of error
    $pdo->rollBack();

    // Return the error message
    http_response_code(500);
    echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
}


?>