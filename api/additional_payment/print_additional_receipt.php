<?php
session_start();

// Include language helper
require_once '../../includes/language_helpers.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../../includes/db.php');
include '../../includes/conn.php';

// Get transaction ID from URL
$transaction_id = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : 0;

if (!$transaction_id) {
    die(__('invalid_transaction_id'));
}

// Fetch transaction details with additional payment information
$query = "
    SELECT
        mat.*,
        ap.payment_type,
        ap.description as payment_description,
        ap.base_amount,
        ap.profit,
        ap.sold_amount,
        ap.currency as payment_currency,
        ma.name as main_account_name,
        s.name as supplier_name,
        c.name as client_name
    FROM main_account_transactions mat
    LEFT JOIN additional_payments ap ON mat.reference_id = ap.id AND mat.transaction_of = 'additional_payment'
    LEFT JOIN main_account ma ON ap.main_account_id = ma.id
    LEFT JOIN suppliers s ON ap.supplier_id = s.id
    LEFT JOIN clients c ON ap.client_id = c.id
    WHERE mat.id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $transaction_id, $tenant_id, $branch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die(__('transaction_not_found'));
}

$transaction = $result->fetch_assoc();

// Fetch settings data (using mysqli connection)
try {
    $settingStmt = $conn->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    if (!$settingStmt) {
        throw new Exception("Prepare failed for settings query: " . $conn->error);
    }

    $settingStmt->bind_param("i", $tenant_id);
    if (!$settingStmt->execute()) {
        throw new Exception("Execute failed for settings query: " . $settingStmt->error);
    }

    $result = $settingStmt->get_result();
    $settings = $result ? $result->fetch_assoc() : null;

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
    $branchStmt = $conn->prepare("SELECT name, code FROM branches WHERE id = ? AND tenant_id = ?");
    if (!$branchStmt) {
        throw new Exception("Prepare failed for branch query: " . $conn->error);
    }

    $branchStmt->bind_param("ii", $branch_id, $tenant_id);
    if (!$branchStmt->execute()) {
        throw new Exception("Execute failed for branch query: " . $branchStmt->error);
    }

    $branchResult = $branchStmt->get_result();
    $branch       = $branchResult ? $branchResult->fetch_assoc() : null;
} catch (Exception $e) {
    error_log("Branch Error: " . $e->getMessage());
    $branch = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('receipt'); ?> - <?php echo htmlspecialchars($transaction['description']); ?></title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12px;
            }
            .receipt-container {
                max-width: 100% !important;
                margin: 0 !important;
            }
        }

        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }

        .receipt-header {
            border-bottom: 2px solid #4099ff;
            padding-bottom: 20px;
            margin-bottom: 10px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            
        }

        .agency-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            flex: 1;
        }

        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            color: #4099ff;
            text-align: center;
            flex: 2;
        }

        .company-logo {
            flex: 1;
            text-align: right;
        }

        .company-logo img {
            max-height: 80px;
            max-width: 120px;
        }

        .receipt-details {
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            position: relative;
        }

        .detail-row::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-bottom: 1px dotted #999;
            margin-top: 5px;
        }

        .detail-label {
            font-weight: bold;
            color: #333;
            background: white;
            padding-right: 10px;
            z-index: 1;
            position: relative;
        }

        .detail-value {
            color: #666;
            background: white;
            padding-left: 10px;
            z-index: 1;
            position: relative;
        }

        .amount-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            position: relative;
        }

        .amount-label {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }

        .amount-value {
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 40px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
        }

        .signature-box {
            flex: 1;
        }

        .signature-box:first-child {
            text-align: left;
        }

        .signature-box:last-child {
            text-align: right;
        }

        /* Removed middle line between signatures */

        .signature-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            width: 120px;
            height: 25px;
        }

        .signature-box:first-child .signature-line {
            margin-left: 0;
        }

        .signature-box:last-child .signature-line {
            margin-left: auto;
            margin-right: 0;
        }

        .footer-note {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px solid #eee;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="receipt-container">
            <!-- Print Button -->
            <div class="no-print print-btn">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> <?php echo __('print'); ?>
                </button>
                <button onclick="window.close()" class="btn btn-secondary ml-2">
                    <i class="fas fa-times"></i> <?php echo __('close'); ?>
                </button>
            </div>

            <!-- Receipt Header -->
            <div class="receipt-header">
                <div class="header-row">
                    <div class="agency-name">
                        <?php echo htmlspecialchars($settings['agency_name'] ?? 'Travel Agency'); ?>
                        <?php if (!empty($branch['name'])): ?>
                            <br><small><?php echo htmlspecialchars($branch['name']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="receipt-title">Payment Receipt</div>
                    <div class="company-logo">
                        <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? ''); ?>" alt="Company Logo">
                    </div>
                </div>
            </div>

            <!-- Receipt Details -->
            <div class="receipt-details">
                <div class="detail-row">
                    <span class="detail-label"><?php echo __('receipt_number'); ?>:</span>
                    <span class="detail-value">#<?php echo $transaction['id']; ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('date'); ?>:</span>
                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('payment_type'); ?>:</span>
                    <span class="detail-value"><?php echo $transaction['type'] === 'credit' ? __('received') : __('paid'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('description'); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['description']); ?></span>
                </div>

                <?php if (!empty($transaction['payment_type'])): ?>
                <hr>
                <h6>Additional Payment Information</h6>

                <div class="detail-row">
                    <span class="detail-label">Payment Type:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['payment_type']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Payment Description:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['payment_description']); ?></span>
                </div>

                <?php endif; ?>
            </div>

            <!-- Amount Section -->
            <div class="amount-section">
                <div class="amount-label"><?php echo __('amount'); ?></div>
                <div class="amount-value">
                    <?php echo htmlspecialchars($transaction['currency']); ?> <?php echo number_format($transaction['amount'], 2); ?>
                </div>

                <!-- Signature Section -->
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-label">Receiver Sign</div>
                        <div class="signature-line"></div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-label">Authorized Sign & Stamp</div>
                        <div class="signature-line"></div>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="footer-note">
                Thank you for your business<br>
                <?php if (!empty($branch)): ?>
                    <?php echo htmlspecialchars($branch['phone'] ?? ''); ?>
                    <?php if (!empty($branch['address'])): ?>
                        (<?php echo htmlspecialchars($branch['address']); ?>)
                    <?php endif; ?>
                <?php else: ?>
                    <?php echo htmlspecialchars($settings['address'] ?? ''); ?>
                    <?php if (!empty($settings['phone'])): ?>
                        | <?php echo htmlspecialchars($settings['phone']); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>