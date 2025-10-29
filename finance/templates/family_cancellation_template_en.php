<?php
// Define the CSS
$css = '
body {
    font-family: Arial, sans-serif;
    line-height: 1.2;
    color: #333333;
    font-size: 9pt;
    margin: 0;
    padding: 0;
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
    border-left: 2px solid #2c3e50;
}
.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}
.details-table td {
    padding: 3px 6px;
    border: 1px solid #ddd;
    text-align: left;
}
.details-table td:first-child {
    width: 35%;
    font-weight: bold;
    background-color: #f5f5f5;
}
.members-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
    font-size: 8pt;
}
.members-table th,
.members-table td {
    padding: 4px 6px;
    border: 1px solid #ddd;
    text-align: left;
}
.members-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}
.members-table tr:nth-child(even) {
    background-color: #f9f9f9;
}
.documents-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}
.documents-table th,
.documents-table td {
    padding: 4px 6px;
    border: 1px solid #ddd;
    text-align: left;
}
.documents-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}
.checkbox {
    width: 12px;
    height: 12px;
    border: 1px solid #333;
    display: inline-block;
    position: relative;
    margin-right: 3px;
    vertical-align: middle;
}
.checkbox.checked:after {
    content: "✓";
    position: absolute;
    top: -3px;
    left: 1px;
}
.terms-box {
    margin: 10px 0;
    padding: 6px;
    border: 1px solid #ddd;
    background-color: #f9f9f9;
}
.terms-text {
    font-size: 8pt;
    margin-bottom: 6px;
}
.signatures {
    margin-top: 15px;
    overflow: hidden;
}
.signature-line {
    width: 45%;
    float: left;
    margin-right: 5%;
    text-align: center;
}
.signature-line:last-child {
    margin-right: 0;
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
.cancellation-reason {
    background-color: #fff4e5;
    border: 1px solid #ff9800;
    padding: 8px;
    margin: 10px 0;
    font-size: 9pt;
}';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Family Umrah Cancellation Form</title>
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
                        <div class="title">Family Umrah Cancellation Form</div>
                    </td>
                    <td width="15%" style="text-align: right; vertical-align: middle; font-size: 7pt;">
                        Date: <?= date('d/m/Y') ?><br>
                        Ref: FAM-CANC-<?= $family['family_id'] ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-header">Family Information</div>
        <table class="details-table">
            <tr>
                <td>Family ID</td>
                <td>FAMILY-<?= htmlspecialchars($family['family_id']) ?></td>
            </tr>
            <tr>
                <td>Head of Family</td>
                <td><?= htmlspecialchars($family['head_of_family']) ?></td>
            </tr>
            <tr>
                <td>Contact Number</td>
                <td><?= htmlspecialchars($family['contact']) ?></td>
            </tr>
            <tr>
                <td>Package Type</td>
                <td><?= htmlspecialchars($family['package_type']) ?></td>
            </tr>
            <tr>
                <td>Total Members</td>
                <td><?= htmlspecialchars($family['total_members']) ?></td>
            </tr>
        </table>

        <div class="section-header">Family Members</div>
        <table class="members-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Passport Number</th>
                    <th>Documents Returned</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Use $bookings instead of $members to ensure we have the correct data
                foreach ($bookings as $member): 
                    $memberId = $member['booking_id'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($member['name']) ?></td>
                    <td><?= htmlspecialchars($member['passport_number']) ?></td>
                    <td>
                        <?php 
                        $docTypes = ['passport', 'id_card', 'photos'];
                        foreach ($docTypes as $docType): 
                            $memberPrefix = 'member_' . $memberId . '_';
                            $returnKey = $memberPrefix . $docType;
                            $isReturned = isset($returnedItems[$returnKey]) && $returnedItems[$returnKey] === '1';
                        ?>
                            <div class="checkbox <?= $isReturned ? 'checked' : '' ?>"></div> 
                            <?= ucfirst($docType) . ($isReturned ? ' (Returned)' : '') ?><br>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php 
                        // Collect notes for this member
                        $memberNotes = [];
                        foreach ($docTypes as $docType) {
                            $memberPrefix = 'member_' . $memberId . '_';
                            $notesKey = $memberPrefix . $docType;
                            
                            if (isset($itemNotes[$notesKey]) && !empty($itemNotes[$notesKey])) {
                                $memberNotes[] = ucfirst($docType) . ': ' . $itemNotes[$notesKey];
                            }
                        }
                        
                        echo htmlspecialchars(implode("\n", $memberNotes));
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


        <?php
        // Unpack template variables
        extract($templateVars);

        // Modify the reason section to use the passed cancellation details
        if (!empty($cancellationDetails['additional_notes'])): ?>
            <div class="section-header">Reason for Cancellation</div>
            <div class="cancellation-reason">
                <p>
                    <?= nl2br(htmlspecialchars($cancellationDetails['additional_notes'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="terms-box">
            <div class="section-header">Cancellation Declaration</div>
            <p class="terms-text">
                This document confirms the cancellation of Umrah services for the above-mentioned family. All original documents have been returned to the family representative. The family acknowledges the cancellation terms and fees as per the agreement. Any refund will be processed according to the agency's refund policy.
            </p>
        </div>

        <div class="signatures">
            <div class="signature-line">
                <div class="signature-box">
                    Family Receiver Signature<br>
                    Date: <?= date('d/m/Y') ?>
                </div>
            </div>
            <div class="signature-line">
                <div class="signature-box">
                    Agency Representative Signature<br>
                    <?= htmlspecialchars($settings['agency_name']) ?><br>
                    Date: <?= date('d/m/Y') ?>
                </div>
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
            Generated on <?= date('F d, Y') ?> | Reference: FAM-CANC-<?= $family['family_id'] ?>-<?= date('Ymd') ?>
        </div>
    </div>
</body>
</html>
<?php
// Return both CSS and HTML
return ['css' => $css, 'html' => ob_get_clean()];
?> 