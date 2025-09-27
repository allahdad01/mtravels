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
    border-bottom: 2px solid #dc3545;
    padding-bottom: 5px;
}

.logo {
    max-width: 60px;
    margin-bottom: 3px;
}

.company-name {
    font-size: 14pt;
    font-weight: bold;
    color: #dc3545;
    text-transform: uppercase;
    margin-bottom: 2px;
}

.title {
    font-size: 12pt;
    font-weight: bold;
    text-transform: uppercase;
    border: 2px solid #dc3545;
    display: inline-block;
    padding: 3px 15px;
    margin: 3px 0;
    color: #dc3545;
}

.section-header {
    background-color: #fff5f5;
    padding: 3px 8px;
    margin: 10px 0 6px 0;
    font-weight: bold;
    color: #dc3545;
    text-transform: uppercase;
    font-size: 9pt;
    border-' . ($isRtl ? 'right' : 'left') . ': 2px solid #dc3545;
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

.full-width {
    width: 100%;
    float: none;
    margin: 0;
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
    background-color: #fff5f5;
}

.members-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    font-size: 8pt;
}

.members-table th, 
.members-table td {
    padding: 4px 6px;
    border: 1px solid #ddd;
    text-align: ' . ($isRtl ? 'right' : 'left') . ';
}

.members-table th {
    background-color: #fff5f5;
    font-weight: bold;
    color: #dc3545;
}

.members-table tr:nth-child(even) {
    background-color: #f9f9f9;
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
    background-color: #fff5f5;
    font-weight: bold;
    color: #dc3545;
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
    border: 1px solid #dc3545;
    background-color: #fff5f5;
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
    opacity: 0.05;
    color: #dc3545;
    font-weight: bold;
    text-transform: uppercase;
    white-space: nowrap;
    z-index: -1;
}

.member-section {
    margin-bottom: 15px;
    page-break-inside: avoid;
}

.member-header {
    background-color: #f0f8ff;
    padding: 5px 8px;
    margin: 8px 0 4px 0;
    font-weight: bold;
    color: #0066cc;
    font-size: 10pt;
    border-' . ($isRtl ? 'right' : 'left') . ': 3px solid #0066cc;
}

.text-center {
    text-align: center;
}';

// Start output buffering for HTML
ob_start();
?>
<!DOCTYPE html>
<html <?= $isRtl ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <title><?= $l['form_title'] ?> - <?= htmlspecialchars($family['head_of_family']) ?></title>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php
            // Create company logo
            $logoPath = __DIR__ . '../uploads/logo/' . $settings['logo'];
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
                        <div class="company-name"><?= htmlspecialchars($settings['agency_name']) ?></div>
                        <div class="title"><?= $l['form_title'] ?> - <?= $l['family'] ?? 'Family' ?></div>
                    </td>
                    <td width="15%" style="text-align: right; vertical-align: middle; font-size: 7pt;">
                        <?= $l['ref'] ?> FCANC-<?= $familyId ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="row">
            <div class="column">
                <div class="section-header"><?= $l['family_info_header'] ?? 'Family Information' ?></div>
                <table class="details-table">
                    <tr>
                        <td><?= $l['family_name'] ?? 'Family Name' ?></td>
                        <td><?= htmlspecialchars($family['head_of_family']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['package_type'] ?></td>
                        <td><?= htmlspecialchars($bookings[0]['package_type']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['total_members'] ?? 'Total Members' ?></td>
                        <td><?= count($bookings) ?></td>
                    </tr>
                </table>
            </div>

            <div class="column">
                <div class="section-header"><?= $l['booking_info_header'] ?></div>
                <table class="details-table">
                    <tr>
                        <td><?= $l['booking_date'] ?></td>
                        <td><?= date('M d, Y', strtotime($bookings[0]['entry_date'])) ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['cancellation_date'] ?></td>
                        <td><?= date('M d, Y') ?></td>
                    </tr>
                    <tr>
                        <td><?= $l['flight_date'] ?></td>
                        <td><?= date('M d, Y', strtotime($bookings[0]['flight_date'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Family Members Summary -->
        <div class="section-header"><?= $l['family_members_header'] ?? 'Family Members Details' ?></div>
        <table class="members-table">
            <tr>
                <th width="5%"><?= $l['sr_no'] ?? '#' ?></th>
                <th width="25%"><?= $l['guest_name'] ?></th>
                <th width="20%"><?= $l['passport_number'] ?></th>
                <th width="15%"><?= $l['booking_id'] ?? 'Booking ID' ?></th>
                <th width="15%"><?= $l['relation'] ?? 'Relation' ?></th>
                <th width="20%"><?= $l['contact'] ?? 'Contact' ?></th>
            </tr>
            <?php foreach ($bookings as $index => $booking): ?>
            <tr>
                <td class="text-center"><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($booking['name']) ?></td>
                <td><?= htmlspecialchars($booking['passport_number']) ?></td>
                <td class="text-center"><?= $booking['booking_id'] ?></td>
                <td><?= htmlspecialchars($booking['relation'] ?? ($index === 0 ? ($l['head'] ?? 'Head') : ($l['member'] ?? 'Member'))) ?></td>
                <td><?= htmlspecialchars($booking['contact'] ?? $booking['phone'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Document Return Section for Each Member -->
        <?php foreach ($bookings as $index => $booking): ?>
        <div class="member-section">
            <div class="member-header">
                <?= sprintf($l['member_documents'] ?? 'Member %d - %s (ID: %s)', 
                    $index + 1, 
                    htmlspecialchars($booking['name']), 
                    $booking['booking_id']) ?>
            </div>
            
            <table class="checklist-table">
                <tr>
                    <th width="30%"><?= $l['document_item'] ?></th>
                    <th width="20%"><?= $l['returned'] ?></th>
                    <th width="20%"><?= $l['condition'] ?></th>
                    <th width="30%"><?= $l['notes'] ?></th>
                </tr>
                <?php
                // Get returned items from the form data for this specific member
                parse_str($_SERVER['QUERY_STRING'], $queryParams);
                $memberPrefix = 'member_' . $booking['booking_id'] . '_';
                $returnedItems = isset($queryParams['returned_items']) ? $queryParams['returned_items'] : [];
                $itemConditions = isset($queryParams['item_condition']) ? $queryParams['item_condition'] : [];
                $itemNotes = isset($queryParams['item_notes']) ? $queryParams['item_notes'] : [];

                $documentItems = [
                    'passport' => $l['passport'],
                    'id_card' => $l['id_card'],
                    'photos' => $l['photos'],
                    'other_docs' => $l['other_documents']
                ];

                foreach ($documentItems as $key => $label) {
                    $memberItemKey = $memberPrefix . $key;
                    $isReturned = isset($returnedItems[$memberItemKey]) && $returnedItems[$memberItemKey] == '1';
                    $condition = isset($itemConditions[$memberItemKey]) ? $itemConditions[$memberItemKey] : '';
                    $note = isset($itemNotes[$memberItemKey]) ? $itemNotes[$memberItemKey] : '';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td class="text-center">
                            <div class="checkbox <?= $isReturned ? 'checked' : '' ?>"></div>
                            <?= $isReturned ? $l['yes'] : $l['no'] ?>
                        </td>
                        <td><?= htmlspecialchars($l[$condition] ?? $condition) ?></td>
                        <td><?= htmlspecialchars($note) ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($_GET['cancellation_reason'])): ?>
        <div class="section-header"><?= $l['cancellation_reason_header'] ?></div>
        <div class="confirmation-box">
            <p class="confirmation-text">
                <?= nl2br(htmlspecialchars($_GET['cancellation_reason'])) ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="confirmation-box">
            <div class="section-header"><?= $l['declaration_header'] ?></div>
            <p class="confirmation-text">
                <?= sprintf($l['family_cancellation_declaration'] ?? 'I, %s, as the head of family, hereby confirm the cancellation of Umrah booking for all %d family members with %s. All documents have been returned as indicated above.', 
                    "<strong>" . htmlspecialchars($family['head_of_family']) . "</strong>",
                    count($bookings),
                    "<strong>" . htmlspecialchars($settings['agency_name']) . "</strong>") ?>
            </p>
            <p class="confirmation-text">
                <?= sprintf($l['family_booking_ids'] ?? 'Cancelled Booking IDs: %s', 
                    "<strong>" . implode(', ', $bookingIds) . "</strong>") ?>
            </p>
            <p class="confirmation-text">
                <?= $l['document_return_confirmation'] ?>
            </p>
        </div>

        <div class="signatures">
            <div class="signature-line">
                <?= $l['family_head_signature'] ?? 'Head of Family Signature' ?><br>
                <?= $l['name'] ?>: <?= htmlspecialchars($family['head_of_family']) ?><br>
                <?= $l['date'] ?>: <?= date('d/m/Y') ?>
                <div class="signature-box"></div>
            </div>
            <div class="signature-line">
                <?= $l['company_representative'] ?><br>
                <?= $l['name'] ?>: <?= htmlspecialchars($bookings[0]['processed_by_name'] ?? '_____________________') ?><br>
                <?= $l['date'] ?>: <?= date('d/m/Y') ?>
                <div class="signature-box"></div>
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
            <?= $l['generated_on'] ?> <?= date('F d, Y') ?> | 
            Ref: FCANC-<?= $familyId ?>-<?= date('Ymd') ?> | 
            <?= $l['total_members'] ?? 'Total Members' ?>: <?= count($bookings) ?>
        </div>

        <div class="watermark"><?= $l['cancelled'] ?></div>
    </div>
</body>
</html>
<?php
// Return both CSS and HTML
return ['css' => $css, 'html' => ob_get_clean()];
?>