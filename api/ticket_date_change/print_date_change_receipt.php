<?php
// Include security module
require_once '../../admin/security.php';

// Include language helper
require_once '../../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../../includes/db.php');

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transaction_id) {
    die(__('invalid_transaction_id'));
}

// Fetch transaction details with ticket reservation information
$query = "
    SELECT
        mat.*,
        tr.id as ticket_id,
        tr.passenger_name,
        tr.pnr,
        tr.origin,
        tr.destination,
        tr.airline,
        tr.departure_date,
        tr.sold,
        tr.currency as ticket_currency,
        c.name as client_name
    FROM main_account_transactions mat
    LEFT JOIN date_change_tickets tr ON mat.reference_id = tr.id AND mat.transaction_of = 'date_change'
    LEFT JOIN clients c ON tr.sold_to = c.id
    WHERE mat.id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die(__('transaction_not_found'));
}

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Travel Agency'];
}
?>
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
                    </div>
                    <div class="receipt-title">Payment Receipt</div>
                    <div class="company-logo">
                        <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']); ?>" alt="Company Logo">
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

                <?php if (!empty($transaction['ticket_id'])): ?>
                <hr>
                <h6><?php echo __('date_change_ticket_information'); ?></h6>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('passenger_name'); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['passenger_name'] ?? 'N/A'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('pnr'); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['pnr'] ?? 'N/A'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('sector'); ?>:</span>
                    <span class="detail-value">
                        <?php echo htmlspecialchars($transaction['origin'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($transaction['destination'] ?? 'N/A'); ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('airline'); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['airline'] ?? 'N/A'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('new_departure_date'); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['departure_date'] ?? 'N/A'); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php echo __('client'); ?>:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($transaction['client_name'] ?? 'N/A'); ?></span>
                </div>

                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No date change ticket information found for this transaction.
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
                <?php echo htmlspecialchars($settings['address'] ?? ''); ?> | <?php echo htmlspecialchars($settings['phone'] ?? ''); ?>
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
