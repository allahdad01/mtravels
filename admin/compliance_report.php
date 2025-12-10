<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ChatAudit.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];

// Get user tenant and branch info
$userStmt = secure_query($pdo, 'SELECT tenant_id, branch_id FROM users WHERE id = ?', [$currentUserId]);
$currentUser = $userStmt ? $userStmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$currentUser) {
    die('User not found');
}

$tenantId = (int)$currentUser['tenant_id'];

// Get report parameters
$reportType = isset($_GET['type']) ? $_GET['type'] : 'gdpr';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$export = isset($_GET['export']) ? $_GET['export'] : null;

// Ensure dates are in correct format
$startDate = date('Y-m-d H:i:s', strtotime($startDate));
$endDate = date('Y-m-d H:i:s', strtotime($endDate . ' 23:59:59'));

// Generate report based on type
$reportData = [];
$reportTitle = '';

switch ($reportType) {
    case 'gdpr':
        $reportTitle = 'GDPR Compliance Report';
        // Get all user activities and data access
        $sql = "SELECT 
                    user_id, 
                    action, 
                    COUNT(*) as count,
                    MIN(created_at) as first_action,
                    MAX(created_at) as last_action
                FROM chat_audit_log 
                WHERE tenant_id = ? 
                AND created_at BETWEEN ? AND ?
                GROUP BY user_id, action
                ORDER BY last_action DESC";
        
        $stmt = secure_query($pdo, $sql, [$tenantId, $startDate, $endDate]);
        $reportData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        break;

    case 'hipaa':
        $reportTitle = 'HIPAA Compliance Report';
        // Get all communication logs (send/read messages)
        $sql = "SELECT 
                    user_id, 
                    target_user_id, 
                    action, 
                    status,
                    COUNT(*) as count,
                    MIN(created_at) as first_action,
                    MAX(created_at) as last_action
                FROM chat_audit_log 
                WHERE tenant_id = ? 
                AND action IN ('send_message', 'read_message')
                AND created_at BETWEEN ? AND ?
                GROUP BY user_id, target_user_id, action, status
                ORDER BY last_action DESC";
        
        $stmt = secure_query($pdo, $sql, [$tenantId, $startDate, $endDate]);
        $reportData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        break;

    case 'sox':
        $reportTitle = 'SOX Compliance Report';
        // Get all financial communication and access logs
        $sql = "SELECT 
                    user_id, 
                    action, 
                    status,
                    COUNT(*) as count,
                    MIN(created_at) as first_action,
                    MAX(created_at) as last_action,
                    GROUP_CONCAT(DISTINCT message_id) as message_ids
                FROM chat_audit_log 
                WHERE tenant_id = ? 
                AND created_at BETWEEN ? AND ?
                GROUP BY user_id, action, status
                ORDER BY last_action DESC";
        
        $stmt = secure_query($pdo, $sql, [$tenantId, $startDate, $endDate]);
        $reportData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        break;

    case 'failed_access':
        $reportTitle = 'Failed Access Report';
        // Get all denied/failed access attempts
        $sql = "SELECT 
                    user_id, 
                    target_user_id,
                    action, 
                    status,
                    error_message,
                    COUNT(*) as count,
                    MIN(created_at) as first_action,
                    MAX(created_at) as last_action
                FROM chat_audit_log 
                WHERE tenant_id = ? 
                AND status IN ('denied', 'failed', 'error')
                AND created_at BETWEEN ? AND ?
                GROUP BY user_id, target_user_id, action, status
                ORDER BY last_action DESC";
        
        $stmt = secure_query($pdo, $sql, [$tenantId, $startDate, $endDate]);
        $reportData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        break;

    case 'activity':
        $reportTitle = 'Activity Summary Report';
        // Get activity summary
        $sql = "SELECT 
                    action, 
                    status,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(created_at) as first_action,
                    MAX(created_at) as last_action
                FROM chat_audit_log 
                WHERE tenant_id = ? 
                AND created_at BETWEEN ? AND ?
                GROUP BY action, status
                ORDER BY count DESC";
        
        $stmt = secure_query($pdo, $sql, [$tenantId, $startDate, $endDate]);
        $reportData = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        break;
}

// Handle export
if ($export) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . sanitize_filename($reportTitle) . '_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Output CSV headers
    if (!empty($reportData)) {
        $headers = array_keys($reportData[0]);
        echo implode(',', array_map(function($h) { return '"' . str_replace('"', '""', $h) . '"'; }, $headers)) . "\n";
        
        // Output CSV rows
        foreach ($reportData as $row) {
            $values = array_values($row);
            echo implode(',', array_map(function($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $values)) . "\n";
        }
    }
    exit;
}

function sanitize_filename($str) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $str);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($reportTitle); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .report-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }
        input, select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        button {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #45a049;
        }
        .export-btn {
            background: #008CBA;
        }
        .export-btn:hover {
            background: #007399;
        }
        .report-type {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .type-btn {
            padding: 10px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            font-size: 14px;
        }
        .type-btn.active {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        .type-btn:hover {
            border-color: #4CAF50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-denied {
            background: #f8d7da;
            color: #721c24;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f0f4f8;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #008CBA;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #666;
        }
        .stat-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .compliance-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($reportTitle); ?></h1>
        <div class="report-meta">
            Generated: <?php echo date('Y-m-d H:i:s'); ?> | 
            Period: <?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row" style="margin-bottom: 15px;">
                    <strong>Report Type:</strong>
                </div>
                <div class="report-type">
                    <button type="submit" name="type" value="gdpr" class="type-btn <?php echo $reportType === 'gdpr' ? 'active' : ''; ?>">
                        GDPR
                    </button>
                    <button type="submit" name="type" value="hipaa" class="type-btn <?php echo $reportType === 'hipaa' ? 'active' : ''; ?>">
                        HIPAA
                    </button>
                    <button type="submit" name="type" value="sox" class="type-btn <?php echo $reportType === 'sox' ? 'active' : ''; ?>">
                        SOX
                    </button>
                    <button type="submit" name="type" value="failed_access" class="type-btn <?php echo $reportType === 'failed_access' ? 'active' : ''; ?>">
                        Failed Access
                    </button>
                    <button type="submit" name="type" value="activity" class="type-btn <?php echo $reportType === 'activity' ? 'active' : ''; ?>">
                        Activity
                    </button>
                </div>

                <div class="filter-row">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime($startDate)); ?>" required>
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime($endDate)); ?>" required>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit">Generate Report</button>
                    <button type="submit" name="export" value="1" class="export-btn">Export CSV</button>
                </div>
            </form>
        </div>

        <?php if ($reportType === 'gdpr'): ?>
            <div class="compliance-note">
                <strong>GDPR Compliance:</strong> This report shows all data access and processing activities. 
                It can be used to demonstrate compliance with GDPR articles 15 (right of access) and 17 (right to be forgotten).
            </div>
        <?php elseif ($reportType === 'hipaa'): ?>
            <div class="compliance-note">
                <strong>HIPAA Compliance:</strong> This report tracks all healthcare communication access and modifications. 
                It meets HIPAA audit trail requirements.
            </div>
        <?php elseif ($reportType === 'sox'): ?>
            <div class="compliance-note">
                <strong>SOX Compliance:</strong> This report documents all financial communication and access logs. 
                It supports Sarbanes-Oxley compliance requirements for financial data.
            </div>
        <?php endif; ?>

        <?php if (!empty($reportData)): ?>
        <div class="stats">
            <div class="stat-card">
                <h3>Total Records</h3>
                <div class="value"><?php echo count($reportData); ?></div>
            </div>
            <?php if ($reportType === 'activity'): ?>
                <div class="stat-card">
                    <h3>Total Events</h3>
                    <div class="value">
                        <?php 
                        $total = 0;
                        foreach ($reportData as $row) {
                            $total += (int)$row['count'];
                        }
                        echo $total;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <?php 
                    foreach (array_keys($reportData[0]) as $header) {
                        echo '<th>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $header))) . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <?php 
                    foreach ($row as $value) {
                        if ($value === null) {
                            $display = '-';
                        } elseif (is_numeric($value) && (int)$value !== 0 && (int)$value != $value) {
                            $display = round($value, 2);
                        } else {
                            $display = htmlspecialchars((string)$value);
                        }
                        echo '<td>' . $display . '</td>';
                    }
                    ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <p>No data found for the selected period and report type.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
