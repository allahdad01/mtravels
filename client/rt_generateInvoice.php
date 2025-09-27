<?php
require '../vendor/autoload.php';
use Dompdf\Dompdf;

// Database connection
$conn = new mysqli("localhost", "root", "", "travelagency");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if ticket ID is provided
if (!isset($_GET['ticketId'])) {
    die("Invalid request. Ticket ID is missing.");
}

$ticketId = intval($_GET['ticketId']);

// Fetch ticket details
$query = "
    SELECT 
        tb.*, s.name AS supplier_name, c.name AS client_name, c.phone AS client_phone
    FROM refunded_tickets tb
    LEFT JOIN suppliers s ON tb.supplier = s.id
    LEFT JOIN clients c ON tb.sold_to = c.id
    WHERE tb.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No ticket found for the provided ID.");
}
$ticket = $result->fetch_assoc();

$tax =  $ticket['supplier_penalty'] + $ticket['service_penalty'];

// Fetch agency settings
$settingsQuery = "SELECT * FROM settings LIMIT 1";
$settingsResult = $conn->query($settingsQuery);

if ($settingsResult->num_rows === 0) {
    die("No agency settings found.");
}
$settings = $settingsResult->fetch_assoc();

// Initialize Dompdf
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true);

// Generate the invoice HTML
$html = "
<html>
<head>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        color: #333;
    }

    .header {
        width: 100%;
        background-color: #f4f4f4;
        color: white;
        text-align: center;
        padding: 20px 0 20px 0;
    }

    .header h1 {
        font-size: 32px;
        margin: 0;
    }

    .header .logo-container {
        float: left;
        width: 200px;
        height: 150px;
        
       
    }
    .logo{
        padding-top: 20px;
        max-width: 300px:
        max-height: 300px;
    }

    .header .agency-details {
        float: right;
        text-align: right;
        width: calc(100% - 180px); /* To accommodate logo size */
        color: black;
    }

    .content {
        padding: 20px;
        margin-top: 20px;
        background-color: #f4f4f4;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .content h2 {
        text-align: center;
        font-size: 28px;
        margin-bottom: 20px;
    }

    .content p {
        font-size: 16px;
        margin: 5px 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        margin-bottom: 20px;
    }

    table th, table td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: left;
        font-size: 16px;
    }

    table th {
        background-color: #004b8d;
        color: white;
    }

    .total {
        text-align: right;
        font-size: 18px;
        font-weight: bold;
        margin-top: 20px;
    }

    .footer {
        text-align: center;
        margin-top: 40px;
        font-size: 12px;
        color: #777;
    }

    /* Clear floats */
    .header::after {
        content: '';
        clear: both;
        display: table;
    }
</style>
</head>
<body>
    <div class='header'>
        <div class='logo-container'>
            <img src='http://localhost/travelagency/uploads/{$settings['logo']}' alt='Logo' class='logo' style='max-width:150px; max-height: 100px;'>
        </div>
        <div class='agency-details'>
            <h1>{$settings['title']}</h1>
            <p>{$settings['address']}</p>
            <p>Phone: {$settings['phone']}</p>
            <p>Email: {$settings['email']}</p>
        </div>
    </div>

    <div class='content'>
        <h2>Refund Invoice</h2>
        
        <p><strong>Invoice Date:</strong> {$ticket['created_at']}</p>
        <p><strong>Receipt Number:</strong> {$ticket['receipt']}</p>
        <p><strong>Passenger Name:</strong> {$ticket['passenger_name']} ({$ticket['title']})</p>
        <p><strong>Supplier:</strong> {$ticket['supplier_name']}</p>
        <p><strong>PNR:</strong> {$ticket['pnr']}</p>
        <p><strong>Sector:</strong> {$ticket['origin']} - {$ticket['destination']}</p>
        <p><strong>Issue Date:</strong> {$ticket['issue_date']}</p>
        <p><strong>Departure Date:</strong> {$ticket['departure_date']}</p>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Charges</th>
                    <th>Currency</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ticket Price</td>
                    <td>{$ticket['sold']}</td>
                    <td>{$tax}</td>
                    <td>{$ticket['currency']}</td>
                </tr>
            </tbody>
        </table>

        <p class='total'>Refunded To Passenger: {$ticket['refund_to_passenger']} {$ticket['currency']}</p>
    </div>

    <div class='footer'>
        <p>{$settings['agency_name']} | {$settings['address']} | Phone: {$settings['phone']} | Email: {$settings['email']}</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the PDF
$dompdf->stream("invoice_ticket_{$ticketId}.pdf", ["Attachment" => false]);

$stmt->close();
$conn->close();
?> 
