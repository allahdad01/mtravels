<?php
// Define the CSS with RTL support
$css = '
body {
    font-family: "XW Zar", Arial, sans-serif;
    line-height: 1.2;
    color: #333333;
    font-size: 9pt;
    margin: 0;
    padding: 0;
    direction: rtl;
    text-align: right;
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
    border-right: 2px solid #2c3e50;
}
.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
}
.details-table td {
    padding: 3px 6px;
    border: 1px solid #ddd;
    text-align: right;
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
    text-align: right;
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
    text-align: right;
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
    float: right;
    margin-left: 5%;
    text-align: center;
}
.signature-line:last-child {
    margin-left: 0;
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
<html dir="rtl" lang="ps">
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
                    <td width="15%" style="text-align: right; vertical-align: middle;">
                        <?php if (!empty($logoData)): ?>
                            <img src="<?= $logoBase64 ?>" alt="<?= $settings['agency_name'] ?>" class="logo">
                        <?php endif; ?>
                    </td>
                    <td width="70%" style="text-align: center; vertical-align: middle;">
                        <div class="company-name"><?= $settings['agency_name'] ?></div>
                        <div class="title">د کورنۍ عمرې د منسوخولو فورمه</div>
                    </td>
                    <td width="15%" style="text-align: left; vertical-align: middle; font-size: 7pt;">
                        نیټه: <?= date('Y/m/d') ?><br>
                        حواله FAM-CANC-<?= $family['family_id'] ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-header">د کورنۍ معلومات</div>
        <table class="details-table">
            <tr>
                <td>د کورنۍ پیژندنه</td>
                <td>FAMILY-<?= htmlspecialchars($family['family_id']) ?></td>
            </tr>
            <tr>
                <td>د کورنۍ مشر</td>
                <td><?= htmlspecialchars($family['head_of_family']) ?></td>
            </tr>
            <tr>
                <td>د اړیکې شمیره</td>
                <td><?= htmlspecialchars($family['contact']) ?></td>
            </tr>
            <tr>
                <td>د بستې ډول</td>
                <td><?= htmlspecialchars($family['package_type']) ?></td>
            </tr>
            <tr>
                <td>ټولې غړي</td>
                <td><?= htmlspecialchars($family['total_members']) ?></td>
            </tr>
        </table>

        <div class="section-header">د کورنۍ غړي</div>
        <table class="members-table">
            <thead>
                <tr>
                    <th>نوم</th>
                    <th>د پاسپورټ شمیره</th>
                    <th>بیرته راستنیدلي اسناد</th>
                    <th>یادونې</th>
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
                            
                            // Pashto labels for document types
                            $docLabels = [
                                'passport' => 'پاسپورټ',
                                'id_card' => 'د پیژندنې کارت', 
                                'photos' => 'عکسونه'
                            ];
                        ?>
                            <div class="checkbox <?= $isReturned ? 'checked' : '' ?>"></div> 
                            <?= $docLabels[$docType] . ($isReturned ? ' (بیرته ورکړل شوی)' : '') ?><br>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php 
                        // Collect notes for this member
                        $memberNotes = [];
                        $docLabels = [
                            'passport' => 'پاسپورټ',
                            'id_card' => 'د پیژندنې کارت', 
                            'photos' => 'عکسونه'
                        ];
                        
                        foreach ($docTypes as $docType) {
                            $memberPrefix = 'member_' . $memberId . '_';
                            $notesKey = $memberPrefix . $docType;
                            
                            if (isset($itemNotes[$notesKey]) && !empty($itemNotes[$notesKey])) {
                                $memberNotes[] = $docLabels[$docType] . ': ' . $itemNotes[$notesKey];
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
            <div class="section-header">د منسوخولو سبب</div>
            <div class="cancellation-reason">
                <p>
                    <?= nl2br(htmlspecialchars($cancellationDetails['additional_notes'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="terms-box">
            <div class="section-header">د منسوخولو اعلان</div>
            <p class="terms-text">دا سند د پورته ذکر شوې کورنۍ د عمرې خدماتو منسوخول تصدیقوي. ټول اصلي اسناد د کورنۍ استازي ته بیرته ورکړل شوي دي. کورنۍ د تړون سره سم د منسوخولو شرایط او فیسونه منله. هر ډول بیرته ورکولو د ادارې د بیرته ورکولو د پالیسۍ سره سم پروسس کیږي.</p>
        </div>

        <div class="signatures">
            <div class="signature-line">
                <div class="signature-box">
                    د کورنۍ د ترلاسه کوونکي لاسلیک<br>
                    نیټه: <?= date('d/m/Y') ?>
                </div>
            </div>
            <div class="signature-line">
                <div class="signature-box">
                    د ادارې استازي لاسلیک<br>
                    <?= htmlspecialchars($settings['agency_name']) ?><br>
                    نیټه: <?= date('d/m/Y') ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <?php if (!empty($settings['address'])): ?>
                <?= htmlspecialchars($settings['address']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['phone'])): ?>
                تیلیفون: <?= htmlspecialchars($settings['phone']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['email'])): ?>
                بریښنالیک: <?= htmlspecialchars($settings['email']) ?>
            <?php endif; ?>
            <br>
            د تولید نیټه <?= date('F d, Y') ?> | حواله: FAM-CANC-<?= $family['family_id'] ?>-<?= date('Ymd') ?>
        </div>
    </div>
</body>
</html>
<?php
// Return both CSS and HTML
return ['css' => $css, 'html' => ob_get_clean()];
?> 