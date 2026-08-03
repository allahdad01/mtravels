<?php
require_once '../../includes/db.php';
require_once '../../includes/TemplateManager.php';
require_once 'tazmin_default_templates.php';
session_start();

if (!isset($_GET['pilgrim_ids']) || empty($_GET['pilgrim_ids'])) {
    echo "No pilgrims selected";
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$language = isset($_GET['language']) && in_array($_GET['language'], ['ps', 'dari']) ? $_GET['language'] : 'ps';

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Initialize TemplateManager
$templateManager = new TemplateManager($pdo, $tenant_id);

// Fetch branch data
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

$pilgrim_ids = explode(',', $_GET['pilgrim_ids']);
$pilgrims_info = [];

foreach ($pilgrim_ids as $pilgrim_id) {
    $stmt = $pdo->prepare("SELECT name, passport_number, duration FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ? AND status != 'cancelled'");
    $stmt->bindParam(1, $pilgrim_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $pilgrims_info[] = $result;
    }
}

$guarantor_name = isset($_GET['guarantor_name']) ? $_GET['guarantor_name'] : '______________________';
$date = date('Y/m/d');
$duration = '15';
if (!empty($pilgrims_info) && isset($pilgrims_info[0]['duration'])) {
    $duration = intval(preg_replace('/[^0-9]/', '', $pilgrims_info[0]['duration']));
}
?>
<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>د ضمانت لیک - <?php echo htmlspecialchars($settings['agency_name']); ?> - <?php echo htmlspecialchars($branch['name'] ?? ''); ?> شرکت</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── CSS Variables ─────────────────────────────────────────── */
        :root {
            --gold:        #b8960c;
            --gold-light:  #d4af37;
            --gold-pale:   #f5edd6;
            --dark:        #1a1a2e;
            --ink:         #1c1c1c;
            --muted:       #555;
            --border:      #c8a951;
            --bg:          #ffffff;
            --section-bg:  #fdfbf5;
        }

        /* ── Page / Print Setup ────────────────────────────────────── */
        @page {
            size: A4;
            margin: 0.8cm 1cm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Noto Naskh Arabic', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.65;
            color: var(--ink);
            background: var(--bg);
            max-width: 21cm;
            /* Strict A4 height so browser preview also shows one page */
            height: 29.7cm;
            overflow: hidden;
            margin: 0 auto;
            padding: 0;
            display: flex;
            flex-direction: column;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Decorative top stripe ─────────────────────────────────── */
        .top-stripe {
            height: 5px;
            flex-shrink: 0;
            background: linear-gradient(to left, var(--gold), var(--gold-light), var(--gold));
        }

        /* ── Header ────────────────────────────────────────────────── */
        .header {
            display: grid;
            grid-template-columns: 75px 1fr 105px;
            align-items: center;
            gap: 8px;
            padding: 6px 12px 6px;
            border-bottom: 1.5px solid var(--border);
            flex-shrink: 0;
        }

        .header-logo {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .header-logo img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
        }

        .header-center h1 {
            font-size: 15px;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 2px;
        }

        .header-center .subtitle {
            font-size: 11px;
            color: var(--gold);
            font-weight: 600;
            margin: 0 0 2px;
        }

        .header-contact {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 9.5px;
            color: var(--muted);
            text-align: left;
        }

        .header-contact span {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* ── Document meta bar ─────────────────────────────────────── */
        .meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gold-pale);
            border-bottom: 1px solid var(--border);
            padding: 3px 12px;
            font-size: 10px;
            color: var(--ink);
            flex-shrink: 0;
        }

        .meta-bar .doc-title {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--dark);
        }

        .meta-bar .doc-date {
            direction: ltr;
            font-size: 10px;
            color: var(--muted);
        }

        /* ── Main content ──────────────────────────────────────────── */
        .content {
            padding: 5px 12px 0;
            text-align: justify;
            flex-shrink: 1;
            overflow: hidden;
        }

        .content ol {
            margin: 3px 0;
            padding-right: 20px;
        }

        .content ol li {
            margin-bottom: 1px;
            line-height: 1.65;
            text-align: justify;
            padding-right: 3px;
        }

        /* ── Guarantor section ─────────────────────────────────────── */
        .guarantor-section {
            margin: 6px 12px 0;
            padding: 7px 12px;
            border: 1px solid var(--border);
            border-right: 4px solid var(--gold);
            border-radius: 4px;
            background: var(--section-bg);
            flex-shrink: 0;
        }

        .guarantor-title {
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            color: var(--dark);
            margin: 0 0 5px;
            padding-bottom: 4px;
            border-bottom: 1px dashed var(--border);
        }

        .guarantor-text {
            text-align: justify;
            line-height: 1.75;
            font-size: 11px;
            margin: 0;
        }

        /* ── Signature footer ──────────────────────────────────────── */
        .footer {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin: 6px 12px 0;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            flex-shrink: 0;
        }

        .sig-block {
            text-align: center;
        }

        .sig-space {
            height: 44px;
            border-bottom: 1.5px solid var(--ink);
            margin-bottom: 4px;
            position: relative;
        }

        .sig-space::after {
            content: '✦';
            position: absolute;
            bottom: 3px;
            left: 50%;
            transform: translateX(-50%);
            color: #ccc;
            font-size: 9px;
        }

        .sig-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--dark);
            margin-top: 2px;
        }

        .sig-sublabel {
            font-size: 9px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* ── Bottom stripe ─────────────────────────────────────────── */
        .bottom-stripe {
            height: 5px;
            flex-shrink: 0;
            background: linear-gradient(to left, var(--gold), var(--gold-light), var(--gold));
            margin-top: auto;
        }

        /* ── Print overrides ───────────────────────────────────────── */
        @media print {
            body {
                height: auto;
                overflow: visible;
            }

            .guarantor-section {
                background-color: var(--section-bg) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                break-inside: avoid;
            }

            .meta-bar {
                background-color: var(--gold-pale) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .footer { break-inside: avoid; }

            .top-stripe,
            .bottom-stripe {
                background: linear-gradient(to left, var(--gold), var(--gold-light), var(--gold)) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- Top gold stripe -->
    <div class="top-stripe"></div>

    <!-- ── HEADER ───────────────────────────────────────────────────── -->
    <div class="header">

        <!-- Logo (RTL: appears on the right) -->
        <div class="header-logo">
            <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" alt="Agency Logo">
        </div>

        <!-- Center: agency name + document subtitle -->
        <div class="header-center">
            <h1><?php echo htmlspecialchars($settings['agency_name']); ?></h1>
            <div class="subtitle"><?php echo htmlspecialchars($branch['name'] ?? ''); ?></div>
            <?php
                $subtitleTemplate = $templateManager->getTemplate('tazmin_agreement_subtitle', $language, $DEFAULT_TEMPLATES['tazmin_agreement_subtitle'][$language] ?? '');
                echo '<div style="font-size:11px;color:var(--muted);">' . $subtitleTemplate . '</div>';
            ?>
        </div>

        <!-- Contact info (left column in RTL layout) -->
        <div class="header-contact">
            <?php if (!empty($branch['phone'])): ?>
            <span>📞 <?= htmlspecialchars($branch['phone']) ?></span>
            <?php endif; ?>
            <?php if (!empty($branch['email'])): ?>
            <span>✉ <?= htmlspecialchars($branch['email']) ?></span>
            <?php endif; ?>
            <?php if (!empty($branch['address'])): ?>
            <span>📍 <?= htmlspecialchars($branch['address']) ?></span>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── META BAR ─────────────────────────────────────────────────── -->
    <div class="meta-bar">
        <span class="doc-title">
            <?php
                $headerTemplate = $templateManager->getTemplate('tazmin_agreement_header', $language, $DEFAULT_TEMPLATES['tazmin_agreement_header'][$language] ?? '');
                $headerTemplate = str_replace(
                    ['{{agency_name}}', '{{branch_name}}', '{{guarantor_name}}'],
                    [htmlspecialchars($settings['agency_name']), htmlspecialchars($branch['name'] ?? ''), htmlspecialchars($guarantor_name)],
                    $headerTemplate
                );
                echo $headerTemplate;
            ?>
        </span>
        <span class="doc-date">📅 تاریخ: <?php echo $date; ?></span>
    </div>

    <!-- ── MAIN CONTENT ─────────────────────────────────────────────── -->
    <div class="content">
        <?php
            $defaultTemplate = $DEFAULT_TEMPLATES['tazmin_agreement'][$language] ?? '';
            $templateContent = $templateManager->getTemplate('tazmin_agreement', $language, $defaultTemplate);
            $templateContent = str_replace(
                ['{{agency_name}}', '{{duration}}'],
                [htmlspecialchars($settings['agency_name']), $duration],
                $templateContent
            );
            echo $templateContent;
        ?>
    </div>

    <!-- ── GUARANTOR SECTION ─────────────────────────────────────────── -->
    <div class="guarantor-section">
        <p class="guarantor-title">
            <?php
                $guarantorTitleTemplate = $templateManager->getTemplate('tazmin_agreement_guarantor_title', $language, $DEFAULT_TEMPLATES['tazmin_agreement_guarantor_title'][$language] ?? '');
                echo $guarantorTitleTemplate;
            ?>
        </p>
        <p class="guarantor-text">
            <?php
                $pilgrim_details = [];
                foreach ($pilgrims_info as $pilgrim) {
                    $pilgrim_details[] = '<strong>' . htmlspecialchars($pilgrim['name']) . '</strong> پاسپورت نمبر (<strong>' . htmlspecialchars($pilgrim['passport_number']) . '</strong>)';
                }
                $pilgrim_names = implode(' او ', $pilgrim_details);

                $guarantorTextTemplate = $templateManager->getTemplate('tazmin_agreement_guarantor_text', $language, $DEFAULT_TEMPLATES['tazmin_agreement_guarantor_text'][$language] ?? '');
                $guarantorTextTemplate = str_replace(
                    ['{{guarantor_name}}', '{{pilgrim_names}}'],
                    [htmlspecialchars($guarantor_name), $pilgrim_names],
                    $guarantorTextTemplate
                );
                echo $guarantorTextTemplate;
            ?>
        </p>
    </div>

    <!-- ── SIGNATURE FOOTER ─────────────────────────────────────────── -->
    <div class="footer">

        <div class="sig-block">
            <div class="sig-space"></div>
            <div class="sig-label">د ضامن لاسلیک</div>
            <div class="sig-sublabel">ګوته / مهر</div>
        </div>

        <div class="sig-block">
            <div class="sig-space"></div>
            <div class="sig-label">د شرکت مهر او لاسلیک</div>
            <div class="sig-sublabel">Official Stamp</div>
        </div>

        <div class="sig-block">
            <div class="sig-space"></div>
            <div class="sig-label">د شاهد لاسلیک</div>
            <div class="sig-sublabel">ګوته / مهر</div>
        </div>

    </div>

    <!-- Bottom gold stripe -->
    <div class="bottom-stripe"></div>

<script src="../../js/umrah/document-editor.js"></script>
</body>
</html>