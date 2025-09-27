<?php
// Define the CSS
$css = '
body {
    font-family: xwzar;
    line-height: 1.4;
    color: #333333;
    font-size: 10pt;
    margin: 0;
    padding: 0;
    direction: rtl;
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
.section-header {
    background-color: #f9f9f9;
    padding: 3px 8px;
    margin-bottom: 5px;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    font-size: 10pt;
    border-right: 2px solid #2c3e50;
}
.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}
.details-table td {
    padding: 3px 6px;
    border: 1px solid #dddddd;
    text-align: right;
    font-size: 9pt;
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
}
.members-table th,
.members-table td {
    padding: 4px 6px;
    border: 1px solid #dddddd;
    text-align: right;
    font-size: 8pt;
}
.members-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}
.members-table tr:nth-child(even) {
    background-color: #f9f9f9;
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
    padding-right: 20px;
    font-size: 8pt;
    list-style-position: inside;
}
.terms-list li {
    margin-bottom: 2px;
    text-align: right;
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
            <?php if (!empty($logoData)): ?>
                <img src="<?= $logoBase64 ?>" alt="<?= $settings['agency_name'] ?>" class="logo">
            <?php endif; ?>
            <div class="company-name"><?= $settings['agency_name'] ?></div>
            <div class="title"><?= $l['form_title'] ?></div>
            <div class="agreement-info"><?= $l['date'] ?>: <?= date('Y/m/d') ?> | <?= $l['agreement_no'] ?>: FAMILY-<?= $family['family_id'] ?>-<?= date('Ymd') ?></div>
        </div>

        <div class="section-header"><?= $l['family_info_header'] ?></div>
        <table class="details-table">
            <tr>
                <td><?= $l['family_id'] ?></td>
                <td>FAMILY-<?= htmlspecialchars($family['family_id']) ?></td>
            </tr>
            <tr>
                <td><?= $l['head_of_family'] ?></td>
                <td><?= htmlspecialchars($family['head_of_family']) ?></td>
            </tr>
            <tr>
                <td><?= $l['contact_number'] ?></td>
                <td><?= htmlspecialchars($family['contact']) ?></td>
            </tr>
            <tr>
                <td><?= $l['address'] ?></td>
                <td><?= htmlspecialchars($family['address']) ?></td>
            </tr>
            <tr>
                <td><?= $l['package_type'] ?></td>
                <td><?= htmlspecialchars($family['package_type']) ?></td>
            </tr>
            <tr>
                <td><?= $l['total_members'] ?></td>
                <td><?= htmlspecialchars($family['total_members']) ?></td>
            </tr>
        </table>

        <div class="section-header"><?= $l['family_members_header'] ?></div>
        <table class="members-table">
            <thead>
                <tr>
                    <th><?= $l['name'] ?></th>
                    <th><?= $l['passport_number'] ?></th>
                    <th><?= $l['room_type'] ?></th>
                    <th><?= $l['duration'] ?></th>
                    <th><?= $l['flight_date'] ?></th>
                    <th><?= $l['return_date'] ?></th>
                    <th><?= $l['price'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                <tr>
                    <td><?= htmlspecialchars($member['name']) ?> (<?= htmlspecialchars($member['id_type']) ?>)</td>
                    <td><?= htmlspecialchars($member['passport_number']) ?></td>
                    <td><?= htmlspecialchars($member['room_type']) ?></td>
                    <td><?= htmlspecialchars($member['duration']) ?></td>
                    <td><?= $member['flight_date'] ?></td>
                    <td><?= $member['return_date'] ?></td>
                    <td><?= $member['sold_price'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-header"><?= $l['financial_details_header'] ?></div>
        <table class="details-table">
            <tr>
                <td><?= $l['total_price'] ?></td>
                <td><?= htmlspecialchars($family['total_price']) ?></td>
            </tr>
            <tr>
                <td><?= $l['total_paid'] ?></td>
                <td><?= htmlspecialchars($family['total_paid']) ?></td>
            </tr>
            <tr>
                <td><?= $l['total_due'] ?></td>
                <td><?= htmlspecialchars($family['total_due']) ?></td>
            </tr>
            <tr>
                <td><?= $l['bank_payment'] ?></td>
                <td><?= htmlspecialchars($family['total_paid_to_bank']) ?></td>
            </tr>
        </table>

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
                            <?= $l['family_signature'] ?><br>
                            <?= $l['name'] ?>: <?= htmlspecialchars($family['head_of_family']) ?>
                        </div>
                    </td>
                    <td width="10%">&nbsp;</td>
                    <td width="45%">
                        <div class="signature-line">
                            <?= $l['agency_signature'] ?><br>
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
                <?= $l['tel'] ?>: <?= htmlspecialchars($settings['phone']) ?> |
            <?php endif; ?>
            <?php if (!empty($settings['email'])): ?>
                <?= $l['email'] ?>: <?= htmlspecialchars($settings['email']) ?>
            <?php endif; ?>
            <br>
            <?= $l['generated_on'] ?> <?= date('Y/m/d') ?> | <?= $l['agreement_no'] ?>: FAMILY-<?= $family['family_id'] ?>-<?= date('Ymd') ?>
        </div>
    </div>
</body>
</html>
<?php
// Return both CSS and HTML
return ['css' => $css, 'html' => ob_get_clean()];
?> 