<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include database connection
require_once('../../includes/db.php');

// Get maktob ID from URL
$maktob_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($maktob_id <= 0) {
    die("Invalid maktob ID");
}

// Query to get maktob details
$query = "SELECT m.*, u.name as sender_name
          FROM maktobs m
          JOIN users u ON m.sender_id = u.id
          WHERE m.id = ? AND m.tenant_id = ? AND m.branch_id = ?";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $maktob_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$maktob = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maktob) {
    die("Maktob not found");
}

// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Branch Error: " . $e->getMessage());
    $branch = null;
}

if (!$settings) {
    // Default values if settings not found
    $settings = [
        'agency_name' => 'AL MOQADAS TRAVEL & TOURS',
        'address' => 'End of Jadayi Maiwand Road [Pashtoon Tower, Kabul Afghanistan]',
        'phone' => '+93 785 555 551',
        'email' => 'Almoqadas_travel@yahoo.com',
        'logo' => 'log.png'
    ];
}

// Set language from maktob data
$lang = isset($maktob['language']) ? strtolower($maktob['language']) : 'english';

// Set headers for HTML display
header('Content-Type: text/html; charset=utf-8');

// Define the HTML content with professional A4 styling
$html = '
<!DOCTYPE html>
<html ' . (($lang == 'dari' || $lang == 'pashto') ? 'dir="rtl"' : '') . '>
<head>
    <meta charset="UTF-8">
    <title>Maktob ' . htmlspecialchars($maktob['maktob_number']) . '</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: ' . (($lang == 'dari' || $lang == 'pashto') ? 'xwzar, Tahoma, Arial, serif' : 'Arial, Helvetica, sans-serif') . ';
            font-size: 11pt;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #1a1a1a;
            background: #f5f5f5;
        }

        .letter-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            padding: 0;
        }

        /* Professional Letterhead */
        .letterhead {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 12mm 20mm 10mm 20mm;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .letterhead::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .letterhead-content {
            display: flex;
            align-items: center;
            gap: 15pt;
            position: relative;
            z-index: 1;
        }

        .company-logo {
            width: 60pt;
            height: 60pt;
            object-fit: contain;
            background: white;
            padding: 6pt;
            border-radius: 6pt;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 16pt;
            font-weight: 700;
            margin: 0 0 4pt 0;
            text-transform: uppercase;
            letter-spacing: 1pt;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .company-tagline {
            font-size: 8.5pt;
            margin: 0 0 6pt 0;
            opacity: 0.9;
            font-style: italic;
            letter-spacing: 0.3pt;
        }

        .company-details {
            font-size: 8pt;
            line-height: 1.4;
            opacity: 0.95;
        }

        .company-details-row {
            display: flex;
            gap: 20pt;
            margin-top: 4pt;
        }

        .company-details-item {
            display: flex;
            align-items: center;
            gap: 4pt;
        }

        .company-details-icon {
            width: 10pt;
            height: 10pt;
            display: inline-block;
        }

        /* Branch info badge */
        .branch-badge {
            position: absolute;
            top: 12mm;
            right: 20mm;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 6pt 12pt;
            border-radius: 15pt;
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 8pt;
            font-weight: 600;
            letter-spacing: 0.5pt;
        }

        /* Main content area */
        .letter-content {
            padding: 10mm 20mm 5mm 20mm;
        }

        /* Document metadata */
        .document-meta {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            margin-bottom: 15pt;
            gap: 15pt;
        }

        .meta-item {
            font-size: 9pt;
            flex: none;
        }

        .meta-label {
            font-weight: 600;
            color: #64748b;
            margin-bottom: 3pt;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }

        .meta-value {
            color: #1e293b;
            font-weight: 600;
            font-size: 9.5pt;
        }

        /* Letter title */
        .letter-title {
            text-align: center;
            font-size: 15pt;
            font-weight: 700;
            text-transform: uppercase;
            margin: 25pt 0;
            padding: 12pt 0;
            color: #1e3a8a;
            letter-spacing: 2pt;
            position: relative;
        }

        .letter-title::after {
            content: "";
            display: block;
            width: 80pt;
            height: 3pt;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            margin: 10pt auto 0;
            border-radius: 2pt;
        }

        /* Recipient section */
        .recipient-section {
            margin-bottom: 25pt;
            padding: 15pt;
            background: linear-gradient(to right, #f0f9ff 0%, #ffffff 100%);
            border-left: 4px solid #3b82f6;
            border-radius: 4pt;
        }

        .recipient-field {
            margin-bottom: 12pt;
        }

        .recipient-field:last-child {
            margin-bottom: 0;
        }

        .recipient-label {
            font-weight: 700;
            font-size: 10pt;
            color: #1e3a8a;
            margin-bottom: 4pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }

        .recipient-value {
            font-size: 11pt;
            color: #1e293b;
            font-weight: 500;
        }

        /* Letter body */
        .letter-body {
            margin: 30pt 0;
            text-align: justify;
            font-size: 11pt;
            line-height: 1.8;
            color: #1e293b;
        }

        .letter-body p {
            margin-bottom: 12pt;
            text-indent: 30pt;
        }

        .letter-body p:first-child {
            margin-top: 0;
        }

        /* Signature section */
        .signature-section {
            margin-top: 40pt;
            display: flex;
            justify-content: flex-end;
        }

        .signature-block {
            width: 220pt;
            text-align: center;
        }

        .complimentary-close {
            font-size: 11pt;
            font-weight: 600;
            margin-bottom: 35pt;
            color: #1e293b;
        }

        .signature-line {
            border-top: 2px solid #1e3a8a;
            padding-top: 10pt;
            margin-bottom: 8pt;
        }

        .signature-name {
            font-weight: 700;
            font-size: 12pt;
            color: #1e3a8a;
            margin-bottom: 4pt;
        }

        .signature-title {
            font-size: 10pt;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 6pt;
        }

        .signature-branch {
            font-size: 9pt;
            color: #94a3b8;
            font-style: italic;
        }

        /* Professional Footer */
        .letter-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 8mm 20mm;
        }

        .footer-content {
            font-size: 8pt;
            line-height: 1.4;
            text-align: center;
        }

        .footer-section h4 {
            font-size: 8.5pt;
            font-weight: 700;
            margin: 0 0 4pt 0;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
        }

        .footer-item {
            margin-bottom: 2pt;
            opacity: 0.95;
        }

        .footer-divider {
            height: 1px;
            background: rgba(255,255,255,0.2);
            margin: 6pt 0;
        }

        .footer-bottom {
            text-align: center;
            font-size: 7.5pt;
            margin-top: 6pt;
            opacity: 0.85;
        }

        /* Print button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        @media print {
            body {
                background: white;
            }

            .letter-container {
                box-shadow: none;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }

            .letter-footer {
                position: fixed;
                bottom: 0;
            }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print Letter</button>

    <div class="letter-container">
        <!-- Professional Letterhead -->
        <div dir="ltr">
        <div class="letterhead">
            ' . ($branch ? '<div class="branch-badge">BRANCH: ' . htmlspecialchars($branch['code'] ?? '') . '</div>' : '') . '
            <div class="letterhead-content">
                ' . ((!empty($settings['logo'])) ? '<img src="../../uploads/logo/' . htmlspecialchars($settings['logo']) . '" alt="Company Logo" class="company-logo">' : '') . '
                <div class="company-info">
                    <h1 class="company-name">' . htmlspecialchars($settings['title'] ?? 'AL MOQADAS TRAVEL & TOURS') . '</h1>
                    <p class="company-tagline">Your Trusted Travel Partner</p>
                    <div class="company-details">
                        <div>' . htmlspecialchars($settings['address'] ?? 'End of Jadayi Maiwand Road [Pashtoon Tower, Kabul Afghanistan]') . '</div>
                        <div class="company-details-row">
                            <div class="company-details-item">
                                <span>📞</span> ' . htmlspecialchars($settings['phone'] ?? '+93 785 555 551') . '
                            </div>
                            <div class="company-details-item">
                                <span>✉️</span> ' . htmlspecialchars($settings['email'] ?? 'Almoqadas_travel@yahoo.com') . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Main Content -->
        <div class="letter-content">
            <!-- Document Metadata -->
            <div class="document-meta">
                <div class="meta-item">
                    <div class="meta-label">Reference Number</div>
                    <div class="meta-value">' . htmlspecialchars($maktob['maktob_number']) . '</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Date</div>
                    <div class="meta-value">' . date('F j, Y', strtotime($maktob['maktob_date'])) . '</div>
                </div>
            </div>

            <!-- Letter Title -->
            <div class="letter-title">
                ' . (($lang == 'dari' || $lang == 'pashto') ? 'مکتوب رسمی' : 'OFFICIAL COMMUNICATION') . '
            </div>

            <!-- Recipient Section -->
            <div class="recipient-section">
                <div class="recipient-field">
                    <div class="recipient-label">' . (($lang == 'dari' || $lang == 'pashto') ? 'به' : 'To') . '</div>
                    <div class="recipient-value">' . htmlspecialchars($maktob['company_name']) . '</div>
                </div>

                <div class="recipient-field">
                    <div class="recipient-label">' . (($lang == 'dari' || $lang == 'pashto') ? 'موضوع' : 'Subject') . '</div>
                    <div class="recipient-value">' . htmlspecialchars($maktob['subject']) . '</div>
                </div>
            </div>

            <!-- Letter Body -->
            <div class="letter-body">
                ' . nl2br(htmlspecialchars($maktob['content'])) . '
            </div>

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-block">
                    <div class="complimentary-close">
                        ' . (($lang == 'dari' || $lang == 'pashto') ? 'با احترام' : 'Yours faithfully,') . '
                    </div>
                    <div class="signature-line">
                        <div class="signature-name">' . htmlspecialchars($maktob['sender_name']) . '</div>
                        <div class="signature-title">Authorized Signatory</div>
                        ' . ($branch ? '<div class="signature-branch">' . htmlspecialchars($branch['name'] ?? '') . '</div>' : '') . '
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Footer -->
        <div class="letter-footer">
            ' . ($branch ? '
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Branch Office Information</h4>
                    <div class="footer-item">' . htmlspecialchars($branch['name'] ?? '') . ' (Code: ' . htmlspecialchars($branch['code'] ?? '') . ')</div>
                    <div class="footer-item">' . htmlspecialchars($branch['address'] ?? '') . '</div>
                    <div class="footer-item">Phone: ' . htmlspecialchars($branch['phone'] ?? '') . '</div>
                </div>
            </div>
            <div class="footer-divider"></div>
            ' : '') . '
            <div class="footer-bottom">
                Document Reference: ' . htmlspecialchars($maktob['maktob_number']) . ' | Issued: ' . date('F j, Y', strtotime($maktob['maktob_date'])) . ' | Confidential Business Communication
            </div>
        </div>
    </div>
</body>
</html>';

// Output the HTML
echo $html;
exit;