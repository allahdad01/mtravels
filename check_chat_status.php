<!DOCTYPE html>
<html>
<head>
    <title>Chat System Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .status { margin: 15px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .actions { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>💬 Chat System Status Check</h1>

        <?php
            session_start();
            
            if (!isset($_SESSION['user_id'])) {
                echo '<div class="status error">❌ Not logged in. Please <a href="login.php">login</a> first.</div>';
                exit;
            }

            require_once __DIR__ . '/includes/db.php';

            $hasIssues = false;

            // 1. Check database columns
            echo '<h2>1. Database Schema</h2>';
            
            $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'chat_messages'
                                AND COLUMN_NAME IN ('encrypted_content', 'is_encrypted', 'encryption_key_id', 'tenant_id_from')");
            $existingCols = array_column($stmt->fetchAll(), 'COLUMN_NAME');
            $requiredCols = ['encrypted_content', 'is_encrypted', 'encryption_key_id', 'tenant_id_from'];
            
            foreach ($requiredCols as $col) {
                if (in_array($col, $existingCols)) {
                    echo "<div class='status success'>✅ Column <code>$col</code> exists</div>";
                } else {
                    echo "<div class='status error'>❌ Column <code>$col</code> MISSING</div>";
                    $hasIssues = true;
                }
            }

            // 2. Check message encryption class
            echo '<h2>2. Encryption Support</h2>';
            
            try {
                require_once __DIR__ . '/includes/MessageEncryption.php';
                echo '<div class="status success">✅ MessageEncryption class loaded</div>';
                
                // Test encryption
                try {
                    $enc = new MessageEncryption($pdo);
                    $test = $enc->encrypt("test", 1);
                    echo '<div class="status success">✅ Encryption functions work</div>';
                } catch (Exception $e) {
                    echo '<div class="status warning">⚠️  Encryption test failed: ' . $e->getMessage() . '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Cannot load MessageEncryption: ' . $e->getMessage() . '</div>';
            }

            // 3. Check recent messages
            echo '<h2>3. Messages in Database</h2>';
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_messages");
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                echo "<div class='status info'>ℹ️  Total messages: $total</div>";
                
                // Check latest
                $stmt = $pdo->query("SELECT id, from_user_id, to_user_id, content, is_encrypted, created_at 
                                    FROM chat_messages 
                                    ORDER BY id DESC LIMIT 5");
                echo '<h3>Last 5 messages:</h3><pre>';
                while ($msg = $stmt->fetch()) {
                    $encrypted = $msg['is_encrypted'] ? '🔒 encrypted' : '📝 plaintext';
                    echo "ID {$msg['id']}: {$msg['from_user_id']} → {$msg['to_user_id']} ($encrypted)\n";
                    echo "  Content: " . substr($msg['content'], 0, 50) . "...\n";
                    echo "  Time: {$msg['created_at']}\n";
                }
                echo '</pre>';
            } else {
                echo '<div class="status warning">⚠️  No messages in database yet</div>';
            }

            // 4. API endpoints check
            echo '<h2>4. API Endpoints</h2>';
            
            $endpoints = [
                '/api/messages.php' => 'Message send/receive',
                '/api/contacts.php' => 'Contact list',
                '/api/chat_settings.php' => 'Chat settings'
            ];
            
            foreach ($endpoints as $endpoint => $desc) {
                if (file_exists(__DIR__ . $endpoint)) {
                    echo "<div class='status success'>✅ $endpoint exists ($desc)</div>";
                } else {
                    echo "<div class='status error'>❌ $endpoint missing</div>";
                    $hasIssues = true;
                }
            }

            // 5. Summary
            echo '<h2>5. Summary</h2>';
            
            if (!$hasIssues && !empty($existingCols) && count($existingCols) >= 4) {
                echo '<div class="status success">✅ Chat system is operational!</div>';
                echo '<p>Try sending a message and it should persist after page refresh.</p>';
            } else {
                echo '<div class="status error">⚠️  Issues detected - see above</div>';
                echo '<div class="actions">';
                echo '<h3>Next Steps:</h3>';
                echo '<ol>';
                echo '<li><strong>Add missing database columns:</strong><br>';
                echo 'Run this SQL in your database:<br>';
                echo '<pre>ALTER TABLE `chat_messages` 
ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`,
ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`,
ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`,
ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`;</pre>';
                echo '</li>';
                echo '<li>After running the SQL, refresh this page</li>';
                echo '<li>Try sending a message in the chat</li>';
                echo '</ol>';
                echo '</div>';
            }
        ?>
    </div>
</body>
</html>
