<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/conn.php';
require_once '../includes/db.php';

// Validate and sanitize debtor ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid debtor ID');
}
$debtor_id = intval($_GET['id']);

// Fetch debtor information
$stmt = $conn->prepare("SELECT d.*, m.name as main_account_name FROM debtors d 
                       LEFT JOIN main_account m ON d.main_account_id = m.id 
                       WHERE d.id = ? AND d.tenant_id = ?");
$stmt->bind_param("ii", $debtor_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$debtor = $result->fetch_assoc();

if (!$debtor) {
    die('Debtor not found');
}

// Fetch company settings
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

// Format the agreement date
$agreement_date = date('F j, Y');

// Default agreement terms if none provided
if (empty($debtor['agreement_terms'])) {
    $debtor['agreement_terms'] = "1. The debtor agrees to pay the full amount due by the agreed deadline.
2. Late payments may be subject to additional fees as per company policy.
3. Failure to make scheduled payments may result in legal action.
4. The debtor must provide advance notice for any payment delays.";
}

// Fetch the current admin user name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$_SESSION['user_id'], $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debt Agreement - <?php echo htmlspecialchars($debtor['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            position: relative;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #1a237e;
            font-size: 24px;
        }
        .header .logo {
            max-height: 80px;
        }
        .header .company-info {
            font-size: 14px;
            margin-top: 10px;
        }
        .header .document-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            color: #d32f2f;
        }
        
        /* Header Row Style */
        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .logo-section {
            flex: 0 0 20%;
            text-align: left;
        }
        .company-section {
            flex: 0 0 50%;
            text-align: center;
        }
        .date-section {
            flex: 0 0 30%;
            text-align: right;
            font-size: 14px;
            line-height: 1.8;
        }
        .title-row {
            margin: 20px 0 10px;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .agreement-section {
            margin-bottom: 25px;
        }
        .agreement-section h2 {
            font-size: 18px;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            color: #1a237e;
        }
        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }
        .detail-label {
            width: 30%;
            font-weight: bold;
        }
        .detail-value {
            width: 70%;
        }
        .terms {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #1a237e;
        }
        .terms ul {
            padding-left: 20px;
        }
        .terms p {
            white-space: pre-line;
        }
        .signature-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 20px;
            padding-top: 5px;
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 0.07;
            font-size: 100px;
            white-space: nowrap;
            font-weight: bold;
            color: #1a237e;
            z-index: -1;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        @media print {
            body {
                padding: 0;
            }
            .container {
                border: none;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Agreement</button>
    
    <div class="container">
        <div class="watermark"><?php echo htmlspecialchars($settings['agency_name']); ?></div>
        
        <div class="header">
            <div class="header-row">
                <div class="logo-section">
                    <?php if (!empty($settings['logo'])): ?>
                        <img src="../uploads/logo/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Company Logo" class="logo">
                    <?php endif; ?>
                </div>
                <div class="company-section">
                    <h1><?php echo htmlspecialchars($settings['agency_name']); ?></h1>
                    <div class="company-info">
                        <?php echo !empty($settings['address']) ? htmlspecialchars($settings['address']) : ''; ?><br>
                        <?php echo !empty($settings['phone']) ? 'Tel: ' . htmlspecialchars($settings['phone']) : ''; ?>
                        <?php echo !empty($settings['email']) ? ' | Email: ' . htmlspecialchars($settings['email']) : ''; ?>
                    </div>
                </div>
                <div class="date-section">
                    <div>Date: <?php echo $agreement_date; ?></div>
                    <div>Ref: DEBT-<?php echo $debtor['id'] . '-' . date('Ymd'); ?></div>
                </div>
            </div>
            <div class="title-row">
                <div class="document-title">Debt Agreement</div>
            </div>
        </div>
        
        <div class="agreement-section">
            <h2>Debtor Information</h2>
            <div class="detail-row">
                <div class="detail-label">Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($debtor['name']); ?></div>
            </div>
            <?php if (!empty($debtor['email'])): ?>
            <div class="detail-row">
                <div class="detail-label">Email:</div>
                <div class="detail-value"><?php echo htmlspecialchars($debtor['email']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($debtor['phone'])): ?>
            <div class="detail-row">
                <div class="detail-label">Phone:</div>
                <div class="detail-value"><?php echo htmlspecialchars($debtor['phone']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($debtor['address'])): ?>
            <div class="detail-row">
                <div class="detail-label">Address:</div>
                <div class="detail-value"><?php echo htmlspecialchars($debtor['address']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="agreement-section">
            <h2>Debt Details</h2>
            <div class="detail-row">
                <div class="detail-label">Amount:</div>
                <div class="detail-value"><?php echo number_format($debtor['balance'], 2) . ' ' . htmlspecialchars($debtor['currency']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Debited Account:</div>
                <div class="detail-value"><?php echo htmlspecialchars($debtor['main_account_name'] ?? 'Not specified'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reference:</div>
                <div class="detail-value">DEBT-<?php echo $debtor['id'] . '-' . date('Ymd'); ?></div>
            </div>
        </div>
        
        <div class="agreement-section">
            <h2>Terms and Conditions</h2>
            <div class="terms">
                <p><?php echo nl2br(htmlspecialchars($debtor['agreement_terms'])); ?></p>
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Debtor's Signature</div>
                <div style="text-align: center; margin-top: 5px;">
                </div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line">Authorized Signature</div>
                <div style="text-align: center; margin-top: 5px;">
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>This agreement is legally binding upon both parties. Any disputes shall be resolved according to applicable laws. Document generated on <?php echo $agreement_date; ?> | Ref: DEBT-<?php echo $debtor['id'] . '-' . date('Ymd'); ?></p>
        </div>
    </div>
</body>
</html> 