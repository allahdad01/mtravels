<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Flight Date Change Agreement</title>
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
            color: rgba(52, 152, 219, 0.2);
            border: 8px solid rgba(52, 152, 219, 0.3);
            padding: 10px;
            border-radius: 8px;
            text-transform: uppercase;
            pointer-events: none;
            z-index: 100;
            font-weight: bold;
            display: <?= $ticket['status'] === 'Date Changed' ? 'block' : 'none' ?>;
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
        <div class="stamp">DATE CHANGED</div>

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
                <div class="title">Flight Date Change Agreement</div>
            </div>
            <div class="header-right">
                <div class="agreement-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Passenger Information</div>
            <table class="details-table">
                <tr>
                    <th>Passenger Name</th>
                    <td><?= htmlspecialchars($ticket['title'] . ' ' . $ticket['passenger_name']) ?></td>
                </tr>
                <tr>
                    <th>PNR</th>
                    <td><?= htmlspecialchars($ticket['pnr']) ?></td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td><?= htmlspecialchars($ticket['phone']) ?></td>
                </tr>
                <tr>
                    <th>Airline</th>
                    <td><?= htmlspecialchars($ticket['airline']) ?></td>
                </tr>
                <tr>
                    <th>Route</th>
                    <td><?= htmlspecialchars($ticket['origin']) ?> - <?= htmlspecialchars($ticket['destination']) ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Date Change Details</div>
            <table class="details-table">
                <tr>
                    <th>Original Date</th>
                    <td><?= date('F d, Y', strtotime($ticket['old_departure_date'])) ?></td>
                </tr>
                <tr>
                    <th>New Date</th>
                    <td><?= date('F d, Y', strtotime($ticket['departure_date'])) ?></td>
                </tr>
                <tr>
                    <th>Total Amount</th>
                    <td><?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'] + $ticket['service_penalty'], 2) ?></td>
                </tr>
                <?php if (!empty($ticket['exchange_rate']) && $ticket['exchange_rate'] != 1): ?>
                <tr>
                    <th>Exchange Rate</th>
                    <td><?= number_format($ticket['exchange_rate'], 4) ?></td>
                </tr>
                <tr>
                    <th>Equivalent Amount</th>
                    <td>
                        <?= $ticket['currency'] === 'USD' ? 'AFS' : 'USD' ?>
                        <?= number_format(($ticket['supplier_penalty'] + $ticket['service_penalty']) * ($ticket['currency'] === 'USD' ? $ticket['exchange_rate'] : 1/$ticket['exchange_rate']), 2) ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Description</th>
                    <td><?= htmlspecialchars($ticket['remarks']) ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Terms and Conditions</div>
            <div class="terms">
                <ol>
                    <li>This date change agreement is final and binding once signed by both parties.</li>
                    <li>The penalties and charges specified above are final and have been agreed upon by both parties.</li>
                    <li>The new flight date is subject to seat availability and airline policies.</li>
                    <li>Any additional fare difference or taxes may apply and are not included in this agreement.</li>
                    <li>By signing this agreement, the passenger acknowledges that they understand and agree to these terms.</li>
                    <li>This date change is subject to the airline's terms and conditions.</li>
                    <li>The agency is not responsible for any subsequent changes or cancellations by the airline.</li>
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
</html> 