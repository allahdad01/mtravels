<?php
/**
 * Floating Tasks Widget Test Page
 * 
 * This page tests the floating tasks widget functionality
 * It includes a demo page with the widget visible
 */

session_start();

// For testing purposes - set a test user if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Demo user ID
    $_SESSION['tenant_id'] = 1; // Demo tenant ID
}

require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floating Tasks Widget - Test Page</title>
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
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            line-height: 1.6;
        }
        
        .status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .status-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .status-card .value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .content {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .content h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .content p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .feature {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .feature h4 {
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .feature p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            border-radius: 4px;
            margin-top: 20px;
            color: #004085;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .debug-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        
        .debug-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
            overflow-x: auto;
        }
        
        .debug-info p {
            margin: 8px 0;
        }
        
        .success {
            color: #28a745;
        }
        
        .error {
            color: #dc3545;
        }
        
        .test-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
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
            <h1>🚀 Floating Tasks Widget Test</h1>
            <p>This page demonstrates the floating tasks widget. The widget should appear in the bottom-right corner of your screen.</p>
            
            <div class="status">
                <div class="status-card">
                    <h3>Session User</h3>
                    <div class="value"><?php echo $_SESSION['user_id'] ?? 'N/A'; ?></div>
                </div>
                <div class="status-card">
                    <h3>Tenant ID</h3>
                    <div class="value"><?php echo $_SESSION['tenant_id'] ?? '1'; ?></div>
                </div>
                <div class="status-card">
                    <h3>Database</h3>
                    <div class="value" id="dbStatus">Checking...</div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <h2>Features & Testing</h2>
            
            <p>The floating tasks widget provides the following functionality:</p>
            
            <div class="features">
                <div class="feature">
                    <h4><i class="fas fa-check-square"></i> Add Tasks</h4>
                    <p>Type a task description and press Enter or click the + button.</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-database"></i> Database Persistent</h4>
                    <p>All tasks are stored in the database, not just the browser.</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-sync-alt"></i> Auto-Sync</h4>
                    <p>Tasks automatically sync every 30 seconds across tabs/windows.</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-check"></i> Mark Complete</h4>
                    <p>Check tasks off as complete. They'll appear with strikethrough.</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-trash"></i> Delete Tasks</h4>
                    <p>Hover over a task and click the trash icon to remove it.</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-compress"></i> Minimize</h4>
                    <p>Click the minus button to minimize. Pending count shows in badge.</p>
                </div>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Quick Test:</strong> Try adding a task below, then open another browser tab and visit this page again. The task will appear in both tabs due to database persistence and auto-sync.
            </div>
            
            <div class="debug-section">
                <h3>System Status</h3>
                <div class="debug-info">
                    <p><strong>User Session:</strong> <span id="userStatus" class="success">✓ Active</span></p>
                    <p><strong>Tenant Context:</strong> <span id="tenantStatus" class="success">✓ Set</span></p>
                    <p><strong>Database Table:</strong> <span id="tableStatus">Checking...</span></p>
                    <p><strong>API Endpoint:</strong> <span id="apiStatus">Checking...</span></p>
                </div>
            </div>
            
            <div class="test-actions">
                <button class="btn btn-primary" onclick="testDatabaseConnection()">Test Database</button>
                <button class="btn btn-primary" onclick="testAPIEndpoint()">Test API</button>
                <button class="btn btn-secondary" onclick="location.reload()">Refresh Page</button>
            </div>
        </div>
    </div>
    
    <!-- Include the floating tasks widget -->
    <?php include_once 'includes/floating_tasks.php'; ?>
    
    <script>
        // Test functions
        async function testDatabaseConnection() {
            const status = document.getElementById('tableStatus');
            status.textContent = 'Testing...';
            
            try {
                // Check if table exists by trying to fetch tasks
                const response = await fetch('./api/floating_tasks_api.php?action=get');
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        status.innerHTML = '<span class="success">✓ Connected</span>';
                        console.log('Tasks loaded:', data.tasks);
                    } else {
                        status.innerHTML = '<span class="error">✗ Error</span>';
                    }
                } else {
                    status.innerHTML = '<span class="error">✗ Failed</span>';
                }
            } catch (error) {
                status.innerHTML = '<span class="error">✗ Error</span>';
                console.error('Database test error:', error);
            }
        }
        
        async function testAPIEndpoint() {
            const status = document.getElementById('apiStatus');
            status.textContent = 'Testing...';
            
            try {
                const response = await fetch('./api/floating_tasks_api.php?action=get');
                
                if (response.ok && response.status === 200) {
                    status.innerHTML = '<span class="success">✓ Working</span>';
                    console.log('API is responsive');
                } else {
                    status.innerHTML = '<span class="error">✗ Error: ' + response.status + '</span>';
                }
            } catch (error) {
                status.innerHTML = '<span class="error">✗ Connection failed</span>';
                console.error('API test error:', error);
            }
        }
        
        // Run tests on page load
        document.addEventListener('DOMContentLoaded', function() {
            testDatabaseConnection();
            testAPIEndpoint();
        });
    </script>
</body>
</html>
