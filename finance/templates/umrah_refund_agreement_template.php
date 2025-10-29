<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Umrah Refund Agreement</title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.5;
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
            max-width: 120px;
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
            margin-bottom: 12px;
            padding: 10px;
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
            font-size: 12px;
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
            margin-top: 35px;
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
            display: <?= $refund['processed'] ? 'block' : 'none' ?>;
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
                <div class="title">Umrah Refund Agreement</div>
            </div>
            <div class="header-right">
                <div class="agreement-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>

    <div class="section">
        <div class="section-title">Umrah Booking Details</div>
        <table class="details-table">
            <tr>
                <td>Booking ID</td>
                <td><?= htmlspecialchars($refund['booking_id']) ?></td>
            </tr>
            <tr>
                <td>Guest Name</td>
                <td><?= htmlspecialchars($refund['name']) ?></td>
            </tr>
            <tr>
                <td>Package Type</td>
                <td><?= htmlspecialchars($refund['package_type']) ?></td>
            </tr>
            <tr>
                <td>Room Type</td>
                <td><?= htmlspecialchars($refund['room_type']) ?></td>
            </tr>
            <tr>
                <td>Duration</td>
                <td><?= htmlspecialchars($refund['duration']) ?> Days</td>
            </tr>
            <tr>
                <td>Flight Date</td>
                <td><?= date('F d, Y', strtotime($refund['flight_date'])) ?></td>
            </tr>
            <tr>
                <td>Return Date</td>
                <td><?= date('F d, Y', strtotime($refund['return_date'])) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Refund Information</div>
        <table class="details-table">
            <tr>
                <td>Refund Type</td>
                <td><?= ucfirst(htmlspecialchars($refund['refund_type'])) ?> Refund</td>
            </tr>
            <tr>
                <td>Refund Amount</td>
                <td><?= htmlspecialchars($refund['currency']) ?> <?= number_format($refund['refund_amount'], 2) ?></td>
            </tr>
            <tr>
                <td>Reason for Refund</td>
                <td><?= htmlspecialchars($refund['reason']) ?></td>
            </tr>
            <?php if ($refund['processed']): ?>
            <tr>
                <td>Processed By</td>
                <td><?= htmlspecialchars($refund['processed_by_name']) ?></td>
            </tr>
            <tr>
                <td>Processing Date</td>
                <td><?= date('F d, Y', strtotime($refund['processed_date'])) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="terms">
        <div class="section-title">Terms and Conditions</div>
        <ol>
            <li>This refund agreement is final and binding once signed by both parties.</li>
            <li>The refund amount will be processed according to the company's Umrah refund policy.</li>
            <li>Processing time for the refund may take up to 14 business days.</li>
            <li>Any bank charges or processing fees will be deducted from the refund amount.</li>
            <li>By signing this agreement, the customer acknowledges that they understand and agree to these terms.</li>
            <li>This refund may affect any related services or bookings associated with the Umrah package.</li>
        </ol>
    </div>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">
                Customer Signature<br>
                Name: <?= htmlspecialchars($refund['name']) ?>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Authorized Signature<br>
                <?= htmlspecialchars($settings['agency_name']) ?>
            </div>
        </div>
    </div>
    <div class="footer">
        This document is electronically generated and is valid without signature.
    </div>
    </div>
</body>
</html>