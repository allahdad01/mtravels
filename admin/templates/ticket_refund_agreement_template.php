<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket Refund Agreement</title>
    <style>
        @page {
            size: A4;
            margin: 0.8cm;
        }
        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.3;
            color: #2c3e50;
            font-size: 10pt;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 10px;
            border: 1px solid #3498db;
            border-radius: 5px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            position: relative;
        }
        .header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #3498db, #2ecc71);
        }
        .header-left {
            flex: 0 0 auto;
        }
        .header-center {
            flex: 1;
            text-align: center;
        }
        .header-right {
            flex: 0 0 auto;
            text-align: right;
        }
        .logo {
            max-width: 80px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .agency_name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .title {
            font-size: 14px;
            font-weight: bold;
            color: #e74c3c;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #e74c3c;
            padding: 4px 8px;
            border-radius: 3px;
            display: inline-block;
            background: rgba(231, 76, 60, 0.1);
        }
        .agreement-date {
            font-size: 10px;
            color: #7f8c8d;
            font-style: italic;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-left: 3px solid #3498db;
            border-radius: 0 5px 5px 0;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #3498db;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 9px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .details-table th, .details-table td {
            padding: 6px;
            border: 1px solid #bdc3c7;
            text-align: left;
        }
        .details-table th {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
        }
        .details-table tr:nth-child(even) {
            background: #ecf0f1;
        }
        .terms {
            margin-top: 10px;
            font-size: 9px;
            padding: 8px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
        }
        .terms ol {
            margin: 5px 0;
            padding-left: 15px;
        }
        .terms li {
            margin-bottom: 3px;
            color: #2c3e50;
        }
        .signatures {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            padding-top: 10px;
            border-top: 1px solid #bdc3c7;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #2c3e50;
            margin-top: 25px;
            padding-top: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 48px;
            color: rgba(231, 76, 60, 0.2);
            border: 8px solid rgba(231, 76, 60, 0.3);
            padding: 10px;
            border-radius: 8px;
            text-transform: uppercase;
            pointer-events: none;
            z-index: 100;
            font-weight: bold;
            display: block;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #bdc3c7;
            font-size: 8px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="stamp">REFUNDED</div>

        <div class="header">
            <div class="header-left">
                <?php
                $logoPath = "../uploads/logo/" . ($settings['logo'] ?? '');
                $fullLogoPath = dirname(__DIR__, 2) . '/uploads/logo/' . ($settings['logo'] ?? '');
                if (!empty($settings['logo']) && file_exists($fullLogoPath)):
                ?>
                <img class="logo" src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="header-center">
                <div class="agency_name"><?= htmlspecialchars($settings['agency_name'] ?? 'Agency Name') ?></div>
                <div class="title">Ticket Refund Agreement</div>
            </div>
            <div class="header-right">
                <div class="agreement-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Passenger Information</div>
            <table class="details-table">
                <tr>
                    <th>Name</th>
                    <td><?= htmlspecialchars($ticket['title'] . ' ' . $ticket['passenger_name']) ?></td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td><?= htmlspecialchars($ticket['phone']) ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Flight Information</div>
            <table class="details-table">
                <tr>
                    <th>PNR</th>
                    <td><?= htmlspecialchars($ticket['pnr']) ?></td>
                </tr>
                <tr>
                    <th>Airline</th>
                    <td><?= htmlspecialchars($ticket['airline']) ?></td>
                </tr>
                <tr>
                    <th>Route</th>
                    <td><?= htmlspecialchars($ticket['origin']) ?> - <?= htmlspecialchars($ticket['destination']) ?></td>
                </tr>
                <tr>
                    <th>Flight Date</th>
                    <td><?= date('d M Y', strtotime($ticket['departure_date'])) ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Financial Information</div>
            <table class="details-table">
                <tr>
                    <th>Description</th>
                    <th>Amount (<?= htmlspecialchars($ticket['currency']) ?>)</th>
                </tr>
                <tr>
                    <td>Original Ticket Price</td>
                    <td><?= number_format($ticket['sold'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Total Refund Amount</strong></td>
                    <td><strong><?= number_format($ticket['refund_to_passenger'], 2) ?></strong></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Terms and Conditions</div>
            <div class="terms">
                <ol>
                    <li>This agreement confirms the refund of the above-mentioned ticket.</li>
                    <li>The refund amount is calculated after deducting all applicable penalties and service charges.</li>
                    <li>Once the refund is processed, it cannot be reversed.</li>
                    <li>The refund will be processed according to the original payment method.</li>
                    <li>Processing time for refunds may vary depending on the airline and payment method.</li>
                    <li>This agreement is valid only with authorized signatures and agency stamp.</li>
                </ol>
            </div>
        </div>

        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">
                    Passenger Signature<br>
                    Name: <?= htmlspecialchars($ticket['title'] . ' ' . $ticket['passenger_name']) ?>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    Authorized Signature<br>
                    <?= htmlspecialchars($settings['agency_name']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="footer">
        This document is electronically generated and is valid without signature.
    </div>
    </div>
</body>
</html>