<?php
/**
 * Test Monthly Report Generator Cron Job
 * This page allows you to manually trigger and test the monthly report generation
 */

session_start();

// Check if user is logged in (optional - remove if you want public access)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

require_once dirname(__FILE__) . "/vendor/autoload.php";

// Load config.php first (it has the actual constants)
if (file_exists(dirname(__FILE__) . "/config.php")) {
    require_once dirname(__FILE__) . "/config.php";
}

require_once dirname(__FILE__) . "/cron/MonthlyReportGenerator.php";

$result = null;
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_cron'])) {
    try {
        // Validate database credentials are available
        if (!defined('DB_SERVER') || !defined('DB_NAME') || !defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
            throw new Exception("Database configuration not found. Please configure config.php file with database credentials.");
        }

        // Get database connection
        $pdo = new PDO(
            "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
            DB_USERNAME,
            DB_PASSWORD
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $generator = new MonthlyReportGenerator($pdo);

        // Get form inputs
        $tenantId = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : null;
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
        $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');

        if (!$tenantId) {
            throw new Exception("Missing required parameter: tenant_id");
        }

        $result = [
            'tenant_id' => $tenantId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'timestamp' => date('Y-m-d H:i:s'),
            'steps' => []
        ];

        // Step 1: Generate Excel report
        $result['steps'][] = ['step' => 'Generating Excel report...', 'status' => 'in-progress'];
        $excelPath = $generator->generateExcelReport($tenantId, $startDate, $endDate);

        if ($excelPath === false) {
            throw new Exception("Failed to generate Excel report");
        }
        $result['steps'][] = ['step' => 'Generating Excel report...', 'status' => 'completed', 'file' => $excelPath];

        // Create dummy report data for email
        $reportData = [
            'month' => date('F Y', strtotime($startDate)),
            'financial_summary' => ['total_usd_profit' => 0, 'total_afs_profit' => 0]
        ];
        $pdfPath = null;

        // Step 2: Get tenant super admin email and send report
        $result['steps'][] = ['step' => 'Getting tenant super admin email...', 'status' => 'in-progress'];
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE tenant_id = ? AND role IN ('super_admin', 'tenant_super_admin', 'admin') ORDER BY role DESC LIMIT 1");
        $stmt->execute([$tenantId]);
        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$adminUser || !$adminUser['email']) {
            $result['steps'][] = ['step' => 'Getting tenant super admin email...', 'status' => 'warning', 'message' => 'No super admin email found - skipping email'];
        } else {
            $result['steps'][] = ['step' => 'Getting tenant super admin email...', 'status' => 'completed', 'email' => $adminUser['email']];
            
            // Step 3: Send report via email
            $result['steps'][] = ['step' => 'Sending report to ' . htmlspecialchars($adminUser['email']) . ' (using tenant SMTP)...', 'status' => 'in-progress'];
            $emailSent = $generator->sendReportEmail(
                $adminUser['email'],
                $adminUser['name'] ?? 'Admin',
                $reportData,
                $excelPath,
                $pdfPath,
                $tenantId  // Pass tenant ID for SMTP config lookup
            );
            
            if ($emailSent) {
                $result['steps'][] = ['step' => 'Sending report to ' . htmlspecialchars($adminUser['email']) . '...', 'status' => 'completed'];
            } else {
                $result['steps'][] = ['step' => 'Sending report to ' . htmlspecialchars($adminUser['email']) . '...', 'status' => 'warning', 'message' => 'Email sending failed - check server email configuration'];
            }
        }

        // Step 4: Files generated and sent successfully
        $result['steps'][] = ['step' => 'Report generation complete', 'status' => 'success'];
        $success = true;

    } catch (Exception $e) {
        $error = $e->getMessage();
        $result = null;
    }
}

// Get list of tenants for dropdown
$tenants = [];
try {
    if (defined('DB_SERVER') && defined('DB_NAME') && defined('DB_USERNAME') && defined('DB_PASSWORD')) {
        $pdo = new PDO(
            "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
            DB_USERNAME,
            DB_PASSWORD
        );
        $stmt = $pdo->query("SELECT id, name FROM tenants LIMIT 50");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Failed to fetch tenants: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Monthly Report Cron</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-reset {
            background: #f0f0f0;
            color: #333;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .result-container {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }

        .result-header {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .step {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .step.in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .step.completed {
            background: #d4edda;
            color: #155724;
        }

        .step.success {
            background: #d4edda;
            color: #155724;
            font-weight: 600;
        }

        .step.warning {
            background: #fff3cd;
            color: #856404;
        }

        .step-status {
            font-weight: 600;
        }

        .step-message {
            margin-top: 5px;
            font-size: 12px;
            font-style: italic;
            opacity: 0.9;
        }

        .file-info {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 4px;
            font-size: 12px;
            word-break: break-all;
            color: #666;
        }

        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #333;
            line-height: 1.6;
        }

        .info-box strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Monthly Report Generator</h1>
            <p>Test the monthly report cron job</p>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> Monthly report generated successfully.
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <div class="info-box">
                    <strong>ℹ️ Instructions:</strong><br>
                    Select a tenant and date range to test the monthly report generation. This will generate an Excel report.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="tenant_id">Tenant <span style="color: red;">*</span></label>
                        <select id="tenant_id" name="tenant_id" required>
                            <option value="">-- Select Tenant --</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['id']; ?>">
                                    <?php echo htmlspecialchars($tenant['name']); ?> (ID: <?php echo $tenant['id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date <span style="color: red;">*</span></label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date <span style="color: red;">*</span></label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" name="run_cron" class="btn-primary">▶ Run Cron Job</button>
                        <button type="reset" class="btn-reset">↻ Reset</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($result && $success): ?>
                <div class="result-container">
                    <div class="result-header">✅ Execution Results</div>

                    <div style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 4px; font-size: 13px;">
                        <strong>Tenant ID:</strong> <?php echo $result['tenant_id']; ?><br>
                        <strong>Period:</strong> <?php echo $result['start_date']; ?> to <?php echo $result['end_date']; ?><br>
                        <strong>Generated:</strong> <?php echo $result['timestamp']; ?>
                    </div>

                    <?php foreach ($result['steps'] as $step): ?>
                        <div class="step <?php echo $step['status']; ?>">
                            <div>
                                <div><?php echo $step['step']; ?></div>
                                <?php if (isset($step['file'])): ?>
                                    <div class="file-info">📁 <?php echo htmlspecialchars(basename($step['file'])); ?></div>
                                <?php endif; ?>
                                <?php if (isset($step['email'])): ?>
                                    <div class="file-info">📧 <?php echo htmlspecialchars($step['email']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($step['message'])): ?>
                                    <div class="step-message">⚠️ <?php echo htmlspecialchars($step['message']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="step-status">
                                <?php 
                                if ($step['status'] === 'completed') {
                                    echo '✓';
                                } elseif ($step['status'] === 'success') {
                                    echo '✓';
                                } elseif ($step['status'] === 'warning') {
                                    echo '⚠';
                                } else {
                                    echo '⏳';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 20px;">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="run_cron" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                Run Again
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Set default dates to current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            const startInput = document.getElementById('start_date');
            const endInput = document.getElementById('end_date');

            if (startInput) {
                startInput.value = firstDay.toISOString().split('T')[0];
            }
            if (endInput) {
                endInput.value = lastDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
