<?php
/**
 * Template for hotel refund agreement
 * Variables available:
 * $refund - Refund details
 * $settings - Agency settings
 * $check_in_date, $check_out_date, $refund_date - Formatted dates
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hotel Refund Agreement</title>
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
                <div class="title">Hotel Refund Agreement</div>
            </div>
            <div class="header-right">
                <div class="agreement-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>
    
        <div class="section">
            <div class="section-title">Guest Information</div>
            <table class="details-table">
                <tr>
                    <th>Guest Name</th>
                    <td><?= htmlspecialchars($refund['title'] . ' ' . $refund['first_name'] . ' ' . $refund['last_name']) ?></td>
                </tr>
                <tr>
                    <th>Booking Reference</th>
                    <td><?= htmlspecialchars($refund['order_id']) ?></td>
                </tr>
                <tr>
                    <th>Hotel</th>
                    <td><?= htmlspecialchars($refund['supplier_name']) ?></td>
                </tr>
                <tr>
                    <th>Check-in Date</th>
                    <td><?= $check_in_date ?></td>
                </tr>
                <tr>
                    <th>Check-out Date</th>
                    <td><?= $check_out_date ?></td>
                </tr>
                <tr>
                    <th>Accommodation Details</th>
                    <td><?= nl2br(htmlspecialchars($refund['accommodation_details'])) ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Refund Information</div>
            <table class="details-table">
                <tr>
                    <th>Refund Type</th>
                    <td><?= ucfirst(htmlspecialchars($refund['refund_type'])) ?> Refund</td>
                </tr>
                <tr>
                    <th>Refund Amount</th>
                    <td><?= number_format($refund['refund_amount'], 2) . ' ' . htmlspecialchars($refund['currency']) ?></td>
                </tr>
                <tr>
                    <th>Reason for Refund</th>
                    <td><?= htmlspecialchars($refund['reason']) ?></td>
                </tr>
                <?php if ($refund['processed']): ?>
                <tr>
                    <th>Processed By</th>
                    <td><?= htmlspecialchars($refund['processed_by_name']) ?></td>
                </tr>
                <tr>
                    <th>Processed Date</th>
                    <td><?= date('F d, Y', strtotime($refund['processed_at'])) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Terms and Conditions</div>
            <div class="terms">
                <ol>
                    <li>This refund agreement is binding between <?= htmlspecialchars($settings['agency_name']) ?> and the guest named above.</li>
                    <li>The refund amount specified above is final and has been agreed upon by both parties.</li>
                    <li>Processing of the refund may take up to 14 business days depending on the payment method and financial institutions involved.</li>
                    <li>This refund is subject to the hotel's cancellation and refund policies.</li>
                    <li>By signing this agreement, the guest acknowledges that they understand and agree to these terms.</li>
                </ol>
            </div>
        </div>

        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">
                    Guest Signature<br>
                    <?= htmlspecialchars($refund['title'] . ' ' . $refund['first_name'] . ' ' . $refund['last_name']) ?>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    Agency Representative<br>
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