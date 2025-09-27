<?php
// Define the CSS
$css = '
body {
    font-family: ' . ($isRtl ? 'xwzar' : 'Arial') . ';
    line-height: 1.2;
    color: #333333;
    font-size: 9pt;
    margin: 0;
    padding: 0;
    direction: ' . ($isRtl ? 'rtl' : 'ltr') . ';
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
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
    padding-bottom: 5px;
}

.logo {
    max-width: 60px;
    margin-bottom: 3px;
}

.company-name {
    font-size: 14pt;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    margin-bottom: 2px;
}

.title {
    font-size: 12pt;
    font-weight: bold;
    text-transform: uppercase;
    border: 1px solid #2c3e50;
    display: inline-block;
    padding: 3px 15px;
    margin: 3px 0;
}

.section-header {
    background-color: #f9f9f9;
    padding: 3px 8px;
    margin: 10px 0 6px 0;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    font-size: 9pt;
    border-' . ($isRtl ? 'right' : 'left') . ': 2px solid #2c3e50;
}

.row {
    width: 100%;
    margin-bottom: 8px;
    overflow: hidden;
}

.column {
    width: 48%;
    float: ' . ($isRtl ? 'right' : 'left') . ';
    margin-' . ($isRtl ? 'left' : 'right') . ': 2%;
}

.column:last-child {
    margin-' . ($isRtl ? 'left' : 'right') . ': 0;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}

.details-table td {
    padding: 3px 6px;
    border: 1px solid #ddd;
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
}

.details-table td:first-child {
    width: 35%;
    font-weight: bold;
    background-color: #f5f5f5;
}

.checklist-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}

.checklist-table th, 
.checklist-table td {
    padding: 4px 6px;
    border: 1px solid #ddd;
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
}

.checklist-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.checklist-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.checkbox {
    width: 12px;
    height: 12px;
    border: 1px solid #333;
    display: inline-block;
    position: relative;
    margin-' . ($isRtl ? 'left' : 'right') . ': 3px;
    vertical-align: middle;
}

.checkbox.checked:after {
    content: "✓";
    position: absolute;
    top: -3px;
    left: 1px;
}

.confirmation-box {
    margin: 10px 0;
    padding: 6px;
    border: 1px solid #ddd;
    background-color: #f9f9f9;
}

.confirmation-text {
    font-size: 8pt;
    margin-bottom: 6px;
}

.signatures {
    margin-top: 15px;
    overflow: hidden;
}

.signature-line {
    width: 45%;
    float: ' . ($isRtl ? 'right' : 'left') . ';
    margin-' . ($isRtl ? 'left' : 'right') . ': 5%;
    text-align: center;
}

.signature-line:last-child {
    margin-' . ($isRtl ? 'left' : 'right') . ': 0;
}

.signature-box {
    border-top: 1px solid #333;
    margin-top: 25px;
    padding-top: 3px;
}

.footer {
    margin-top: 15px;
    text-align: center;
    font-size: 7pt;
    color: #777;
    border-top: 1px solid #eee;
    padding-top: 5px;
    clear: both;
}

.watermark {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 80pt;
    opacity: 0.02;
    color: #2c3e50;
    font-weight: bold;
    text-transform: uppercase;
    white-space: nowrap;
    z-index: -1;
}';

// Start output buffering for HTML
ob_start();
?>
<!DOCTYPE html>
<html <?= $isRtl ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <title><?= $l['form_title'] ?></title>
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
            <table width="100%">
                <tr>
                    <td width="15%" style="text-align: left; vertical-align: middle;">
                        <?php if (!empty($logoData)): ?>
                            <img src="<?= $logoBase64 ?>" alt="Company Logo" class="logo">
                        <?php endif; ?>
                    </td>
                    <td width="70%" style="text-align: center; vertical-align: middle;">
                        <div class="company-name"><?= $settings['agency_name'] ?></div>
                        <div class="title"><?= $l['form_title'] ?></div>
                    </td>
                    <td width="15%" style="text-align: right; vertical-align: middle; font-size: 7pt;">
                        <?= $l['ref'] ?> UMRAH-<?= $booking['booking_id'] ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="row">
            <div class="column">
                <div class="section-header"><?= $l['guest_info_header'] ?></div>
                <table class="details-table">
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
                </table>
            </div>

            <div class="column right">
                <div class="section-header"><?= $l['travel_info_header'] ?></div>
                <table class="details-table">
                    <tr>
                        <td><?= $l['departure_date'] ?></td>
                        <td><?= date('M d, Y', strtotime($booking['flight_date'])) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['return_date'] ?></td>
                        <td><?= date('M d, Y', strtotime($booking['return_date'])) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['duration'] ?></td>
                        <td><?= htmlspecialchars($booking['duration']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section-header"><?= $l['checklist_header'] ?></div>
        <table class="checklist-table">
            <tr>
                <th width="60%"><?= $l['service_document'] ?></th>
                <th width="20%"><?= $l['provided'] ?></th>
                <th width="20%"><?= $l['date'] ?></th>
            </tr>
            <tr>
                <td><?= $l['visa_processing'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime('-30 days', strtotime($booking['flight_date']))) ?></td>
            </tr>
            <tr>
                <td><?= $l['flight_tickets'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime('-10 days', strtotime($booking['flight_date']))) ?></td>
            </tr>
            <tr>
                <td><?= $l['makkah_hotel'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime($booking['flight_date'])) ?></td>
            </tr>
            <tr>
                <td><?= $l['madinah_hotel'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime('+5 days', strtotime($booking['flight_date']))) ?></td>
            </tr>
            <tr>
                <td><?= $l['transportation'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime($booking['flight_date'])) ?></td>
            </tr>
            <tr>
                <td><?= $l['ziyarat_tours'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime('+2 days', strtotime($booking['flight_date']))) ?></td>
            </tr>
            <tr>
                <td><?= $l['document_return'] ?></td>
                <td><div class="checkbox checked"></div> <?= $l['yes'] ?></td>
                <td><?= date('m/d/Y', strtotime($booking['return_date'])) ?></td>
            </tr>
        </table>

        <!-- Add Returned Documents Section -->
        <div class="section-header"><?= $l['returned_documents_header'] ?></div>
        <table class="checklist-table">
            <tr>
                <th width="70%"><?= $l['document_item'] ?></th>
                <th width="30%"><?= $l['returned'] ?></th>
            </tr>
            <?php
            // Get returned items from the form data
            parse_str($_SERVER['QUERY_STRING'], $queryParams);
            $returnedItems = isset($queryParams['returned_items']) ? $queryParams['returned_items'] : [];

            $documentItems = [
                'passport' => 'Passport',
                'id_card' => 'ID Card',
                'other_docs' => 'Other Documents'
            ];

            foreach ($documentItems as $key => $label) {
                $isReturned = isset($returnedItems[$key]) && $returnedItems[$key] == '1';
                ?>
                <tr>
                    <td><?= htmlspecialchars($label) ?></td>
                    <td class="text-center">
                        <div class="checkbox <?= $isReturned ? 'checked' : '' ?>"></div>
                        <?= $isReturned ? 'Yes' : 'No' ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>

        <?php if (!empty($_GET['additional_notes'])): ?>
        <div class="section-header"><?= $l['additional_notes'] ?></div>
        <div class="confirmation-box">
            <p class="confirmation-text">
                <?= nl2br(htmlspecialchars($_GET['additional_notes'])) ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="confirmation-box">
            <div class="section-header"><?= $l['confirmation_header'] ?></div>
            <p class="confirmation-text">
                <?= sprintf($l['confirmation_line_1'], "<strong>" . htmlspecialchars($settings['agency_name']) . "</strong>", "<strong>" . htmlspecialchars($booking['name']) . "</strong>") ?>
            </p>
            <p class="confirmation-text">
                <?= $l['confirmation_line_2'] ?>
            </p>
        </div>

        <div class="signatures">
            <div class="signature-line signature-left">
                <?= $l['guest_signature'] ?><br>
                Name: <?= htmlspecialchars($booking['name']) ?>
            </div>
            <div class="signature-line signature-right">
                <?= $l['company_representative'] ?><br>
            </div>
        </div>
        
        <div class="footer">
            <?php if (!empty($settings['address'])): ?>
                <?= htmlspecialchars($settings['address']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['phone'])): ?>
                Tel: <?= htmlspecialchars($settings['phone']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['email'])): ?>
                Email: <?= htmlspecialchars($settings['email']) ?>
            <?php endif; ?>
            <br>
            <?= $l['generated_on'] ?> <?= date('F d, Y') ?> | Ref: UMRAH-COMP-<?= $booking['booking_id'] ?>-<?= date('Ymd') ?>
        </div>
    </div>
</body>
</html>
<?php
// Return both CSS and HTML
return ['css' => $css, 'html' => ob_get_clean()];
?> 