<?php
/**
 * Floating Tasks Setup Verification
 * Checks if everything is properly configured
 */

session_start();

// Set demo session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['tenant_id'] = 1;
}

require_once 'includes/db.php';

$checks = [];

// Check 1: Database table exists
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'floating_tasks'");
    $stmt->execute();
    $checks['table_exists'] = [
        'status' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'floating_tasks table found' : 'floating_tasks table NOT FOUND'
    ];
} catch (Exception $e) {
    $checks['table_exists'] = ['status' => false, 'message' => 'Error checking table: ' . $e->getMessage()];
}

// Check 2: API file exists
$api_file = 'api/floating_tasks_api.php';
$checks['api_exists'] = [
    'status' => file_exists($api_file),
    'message' => file_exists($api_file) ? 'API file found' : 'API file NOT FOUND'
];

// Check 3: Widget file exists
$widget_file = 'includes/floating_tasks.php';
$checks['widget_exists'] = [
    'status' => file_exists($widget_file),
    'message' => file_exists($widget_file) ? 'Widget file found' : 'Widget file NOT FOUND'
];

// Check 4: Test database connection
try {
    $test_query = $pdo->query("SELECT 1");
    $checks['db_connection'] = [
        'status' => true,
        'message' => 'Database connection OK'
    ];
} catch (Exception $e) {
    $checks['db_connection'] = [
        'status' => false,
        'message' => 'Database connection FAILED: ' . $e->getMessage()
    ];
}

// Check 5: Get task count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM floating_tasks WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([1, 1]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'] ?? 0;
    $checks['sample_data'] = [
        'status' => true,
        'message' => "Sample user has $count tasks"
    ];
} catch (Exception $e) {
    $checks['sample_data'] = [
        'status' => false,
        'message' => 'Could not count tasks: ' . $e->getMessage()
    ];
}

// Calculate overall status
$all_pass = array_reduce($checks, fn($carry, $item) => $carry && $item['status'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floating Tasks Setup Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 15px;
        }
        
        .status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .checks {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .check-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background 0.2s ease;
        }
        
        .check-item:last-child {
            border-bottom: none;
        }
        
        .check-item:hover {
            background: #f9f9f9;
        }
        
        .check-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .check-icon.success {
            background: #d1fae5;
            color: #10b981;
        }
        
        .check-icon.error {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .check-content {
            flex: 1;
        }
        
        .check-title {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .check-message {
            color: #666;
            font-size: 13px;
            margin-top: 4px;
        }
        
        .footer {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e9ecef;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Setup Verification</h1>
            <p style="color: #666; margin-bottom: 0;">Floating Tasks Widget</p>
            <div class="status-badge <?php echo $all_pass ? 'success' : 'error'; ?>">
                <?php echo $all_pass ? '✓ All Systems Go' : '⚠ Issues Found'; ?>
            </div>
        </div>
        
        <div class="checks">
            <?php foreach ($checks as $key => $check): ?>
            <div class="check-item">
                <div class="check-icon <?php echo $check['status'] ? 'success' : 'error'; ?>">
                    <?php echo $check['status'] ? '✓' : '✕'; ?>
                </div>
                <div class="check-content">
                    <div class="check-title"><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                    <div class="check-message"><?php echo htmlspecialchars($check['message']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer">
            <p style="color: #666; margin-bottom: 0; font-size: 13px;">
                <?php if ($all_pass): ?>
                    ✓ Your floating tasks widget is ready to use!<br>
                    The widget will appear on all pages with the header included.
                <?php else: ?>
                    ⚠ Please fix the issues above before using the widget.
                <?php endif; ?>
            </p>
            
            <div class="action-buttons">
                <a href="test_api.php" class="btn btn-primary">Test API</a>
                <a href="test_floating_tasks.php" class="btn btn-primary">Test Widget</a>
                <button class="btn btn-secondary" onclick="location.reload()">Refresh</button>
            </div>
        </div>
    </div>
</body>
</html>
