<?php
// Define the CSS
$css = '
body {
    font-family: ' . ($isRtl ? 'xwzar' : 'Arial, sans-serif') . ';
    line-height: 1.4;
    color: #333333;
    font-size: 10pt;
    margin: 0;
    padding: 0;
    direction: ' . ($isRtl ? 'rtl' : 'ltr') . ';
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
}
title{
    font-size: 16pt;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    margin-bottom: 3px;
    text-align: center;
}
.container {
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    padding: 15px;
}

.header {
    text-align: center;
    margin-bottom: 10px;
    border-bottom: 1px solid #2c3e50;
    padding-bottom: 8px;
}

.logo {
    width: 60px;
    margin: 0 auto 3px auto;
    display: block;
}

.company-name {
    font-size: 16pt;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    margin-bottom: 3px;
    text-align: center;
}

.title {
    font-size: 12pt;
    font-weight: bold;
    text-transform: uppercase;
    border: 1px solid #2c3e50;
    padding: 3px 15px;
    margin: 3px auto;
    display: inline-block;
}

.agreement-info {
    font-size: 8pt;
    margin-top: 3px;
    text-align: center;
}

.row {
    width: 100%;
    margin-bottom: 10px;
    display: table;
}

.column {
    width: 49%;
    display: table-cell;
    vertical-align: top;
    padding: ' . ($isRtl ? '0 0 0 1%' : '0 1% 0 0') . ';
}

.column.right {
    padding: ' . ($isRtl ? '0 1% 0 0' : '0 0 0 1%') . ';
}

.section-header {
    background-color: #f9f9f9;
    padding: 3px 8px;
    margin-bottom: 5px;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    font-size: 10pt;
    border-' . ($isRtl ? 'right' : 'left') . ': 2px solid #2c3e50;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.details-table td {
    padding: 3px 6px;
    border: 1px solid #dddddd;
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
    font-size: 9pt;
}

.details-table td:first-child {
    width: 35%;
    font-weight: bold;
    background-color: #f5f5f5;
}

.terms-container {
    margin-top: 10px;
    border: 1px solid #dddddd;
    padding: 8px;
    background-color: #f9f9f9;
}

.terms-title {
    font-weight: bold;
    margin-bottom: 5px;
    text-transform: uppercase;
    color: #2c3e50;
    font-size: 10pt;
}

.terms-list {
    margin: 0;
    padding-' . ($isRtl ? 'right' : 'left') . ': 20px;
    font-size: 8pt;
}

.terms-list li {
    margin-bottom: 2px;
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
}

.important-note {
    background-color: #fff4e5;
    border-' . ($isRtl ? 'right' : 'left') . ': 2px solid #ff9800;
    padding: 4px 8px;
    margin: 8px 0;
    font-size: 8pt;
}

.signatures {
    margin-top: 15px;
    width: 100%;
    text-align: center;
}

.signatures table {
    width: 100%;
    margin-top: 15px;
}

.signature-line {
    border-top: 1px solid #333333;
    padding-top: 3px;
    text-align: center;
    font-size: 9pt;
}

.signature-left {
    margin-' . ($isRtl ? 'left' : 'right') . ': 2%;
}

.signature-right {
    margin-' . ($isRtl ? 'right' : 'left') . ': 2%;
}

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 7pt;
    color: #777777;
    border-top: 1px solid #eeeeee;
    padding-top: 5px;
}';
?>
<!DOCTYPE html>
<html <?= $isRtl ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <title>Umrah Agreement</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php
            // Create company logo
            $logoPath = __DIR__ . '/../../uploads/logo/' . $settings['logo'];
            $logoData = '';
            if (file_exists($logoPath)) {
                $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
            }
            ?>
            <?php if (!empty($logoData)): ?>
                <img src="<?= $logoBase64 ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <div class="company-name"><?= $settings['agency_name'] ?></div>
            <div class="title"><?= $l['umrah_agreement'] ?></div>
            <div class="agreement-info"><?= $l['date'] ?>: <?= date('M d, Y') ?> | <?= $l['agreement_no'] ?> UMRAH-<?= $booking['booking_id'] ?>-<?= date('Ymd') ?></div>
        </div>

        <div class="row">
            <div class="column">
                <div class="section-header"><?= $l['booking_details_header'] ?></div>
                <table class="details-table">
                    <tr>
                        <td><?= $l['booking_id'] ?></td>
                        <td>UMRAH-<?= htmlspecialchars($booking['booking_id']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['guest_name'] ?></td>
                        <td><?= htmlspecialchars($booking['name']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['passport_number'] ?></td>
                        <td><?= htmlspecialchars($booking['passport_number']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['package_type'] ?></td>
                        <td><?= htmlspecialchars($booking['package_type']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['room_type'] ?></td>
                        <td><?= htmlspecialchars($booking['room_type']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['duration'] ?></td>
                        <td><?= htmlspecialchars($booking['duration']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['flight_date'] ?></td>
                        <td><?= $booking['flight_date'] ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['return_date'] ?></td>
                        <td><?= $booking['return_date'] ?></td>
                    </tr>
                </table>
            </div>

            <div class="column right">
                <div class="section-header"><?= $l['financial_details_header'] ?></div>
                <table class="details-table">
                    <tr>
                        <td><?= $l['total_price'] ?></td>
                        <td><?= htmlspecialchars($booking['sold_price']) ?> <?= htmlspecialchars($booking['currency']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['amount_paid'] ?></td>
                        <td><?= htmlspecialchars($booking['paid']) ?> <?= htmlspecialchars($booking['currency']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['remaining_balance'] ?></td>
                        <td><?= htmlspecialchars($booking['due']) ?> <?= htmlspecialchars($booking['currency']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['payment_due_date'] ?></td>
                        <td><?= $booking['flight_date'] ?></td>
                    </tr>
                </table>
                
                <div class="important-note">
                    <strong><?= $l['important_note_header'] ?></strong> <?= $l['important_note_text'] ?>
                </div>
            </div>
        </div>

        <div class="terms-container">
            <div class="terms-title"><?= $l['terms_header'] ?></div>
            <ol class="terms-list">
                <li><?= $l['term_1'] ?></li>
                <li><?= $l['term_2'] ?></li>
                <li><?= $l['term_3'] ?></li>
                <li><?= $l['term_4'] ?></li>
                <li><?= $l['term_5'] ?></li>
                <li><?= $l['term_6'] ?></li>
                <li><?= $l['term_7'] ?></li>
                <li><?= $l['term_8'] ?></li>
            </ol>
        </div>

        <div class="signatures">
            <table>
                <tr>
                    <td width="45%">
                        <div class="signature-line">
                            <?= $l['client_signature'] ?><br>
                            Name: <?= htmlspecialchars($booking['name']) ?>
                        </div>
                    </td>
                    <td width="10%">&nbsp;</td>
                    <td width="45%">
                        <div class="signature-line">
                            <?= $l['authorized_signature'] ?><br>
                            <?= htmlspecialchars($settings['agency_name']) ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <?php if (!empty($settings['address'])): ?>
                <?= htmlspecialchars($settings['address']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['phone'])): ?>
                <?= $l['tel'] ?> <?= htmlspecialchars($settings['phone']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['email'])): ?>
                <?= $l['email'] ?> <?= htmlspecialchars($settings['email']) ?>
            <?php endif; ?>
            <br>
            <?= $l['generated_on'] ?> <?= date('F d, Y') ?> | <?= $l['agreement_no'] ?> UMRAH-<?= $booking['booking_id'] ?>-<?= date('Ymd') ?>
        </div>
    </div>
</body>
</html>
<?php
// Return both CSS and HTML
return ['css' => $css, 'html' => ob_get_clean()];
?> 