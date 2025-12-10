<?php
/**
 * Test Message Reactions System
 */

session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Not authenticated');
}

// Generate or retrieve CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int)$_SESSION['user_id'];
$csrfToken = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reactions Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        button { padding: 10px 15px; margin: 5px; cursor: pointer; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Reactions System Test</h1>
    <p>Current User ID: <strong><?php echo $userId; ?></strong></p>
    
    <div class="test-section">
        <h2>1. Check Database Table</h2>
        <button onclick="checkTable()">Check message_reactions Table</button>
        <div id="tableResult"></div>
    </div>
    
    <div class="test-section">
        <h2>2. List Recent Messages</h2>
        <button onclick="listMessages()">Load Messages</button>
        <div id="messagesResult"></div>
    </div>
    
    <div class="test-section">
        <h2>3. Test Adding Reaction</h2>
        <p><strong>Note:</strong> Use a message ID from section 2 above (a message where you are either the sender or receiver)</p>
        <input type="number" id="messageId" placeholder="Message ID (from section 2)" value="">
        <input type="text" id="emoji" placeholder="Emoji" value="👍" max="2">
        <button onclick="testReaction()">Test Add Reaction</button>
        <div id="reactionResult"></div>
    </div>
    
    <div class="test-section">
        <h2>4. Test Loading Reactions</h2>
        <input type="number" id="messageIdGet" placeholder="Message ID" value="">
        <button onclick="testGetReactions()">Test Get Reactions</button>
        <div id="getResult"></div>
    </div>
    
    <div class="test-section">
        <h2>5. Console Logs</h2>
        <p>Open browser DevTools (F12) and check the Console tab for detailed logs</p>
        <button onclick="runConsoleDiagnostic()">Run Diagnostic</button>
    </div>
    
    <script>
        // Make CSRF token available globally
        window.csrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
        
        function checkTable() {
            fetch('api/message_reactions.php?action=check_table', {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                const result = document.getElementById('tableResult');
                if (data.table_exists) {
                    result.innerHTML = '<div class="success">✓ Table exists with ' + data.count + ' reactions</div>';
                } else {
                    result.innerHTML = '<div class="error">✗ Table does not exist. Run create_reactions_table.php</div>';
                }
            })
            .catch(e => {
                document.getElementById('tableResult').innerHTML = '<div class="error">Error: ' + e.message + '</div>';
            });
        }
        
        function listMessages() {
            fetch('api/messages.php?peer_id=1&limit=5', {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                const result = document.getElementById('messagesResult');
                if (data.messages) {
                    let html = '<p>Recent messages:</p><pre>';
                    data.messages.slice(0, 5).forEach(m => {
                        html += 'ID: ' + m.id + ' | From: ' + m.from_user_id + '\n';
                    });
                    html += '</pre>';
                    result.innerHTML = html;
                } else {
                    result.innerHTML = '<div class="error">No messages or error loading</div>';
                }
            })
            .catch(e => {
                document.getElementById('messagesResult').innerHTML = '<div class="error">Error: ' + e.message + '</div>';
            });
        }
        
        function testReaction() {
            const messageId = document.getElementById('messageId').value;
            const emoji = document.getElementById('emoji').value;
            
            if (!messageId || !emoji) {
                alert('Please enter Message ID and Emoji');
                return;
            }
            
            console.log('[Test] Adding reaction:', messageId, emoji);
            
            fetch('api/message_reactions.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    message_id: messageId,
                    emoji: emoji,
                    csrf_token: window.csrfToken
                })
            })
            .then(r => {
                console.log('[Test] Response status:', r.status);
                return r.json();
            })
            .then(data => {
                console.log('[Test] Response data:', data);
                const result = document.getElementById('reactionResult');
                if (data.ok || data.action) {
                    result.innerHTML = '<div class="success">✓ Reaction ' + data.action + ': ' + emoji + '</div>';
                } else {
                    result.innerHTML = '<div class="error">✗ Error: ' + (data.error || 'Unknown') + '</div>';
                }
            })
            .catch(e => {
                console.error('[Test] Error:', e);
                document.getElementById('reactionResult').innerHTML = '<div class="error">Error: ' + e.message + '</div>';
            });
        }
        
        function testGetReactions() {
            const messageId = document.getElementById('messageIdGet').value;
            
            if (!messageId) {
                alert('Please enter Message ID');
                return;
            }
            
            fetch('api/message_reactions.php?message_id=' + messageId, {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                const result = document.getElementById('getResult');
                if (data.reactions && Object.keys(data.reactions).length > 0) {
                    let html = '<p>Reactions:</p><pre>';
                    for (const [emoji, reactions] of Object.entries(data.reactions)) {
                        html += emoji + ': ' + reactions.length + '\n';
                    }
                    html += '</pre>';
                    result.innerHTML = html;
                } else {
                    result.innerHTML = '<div>No reactions for this message</div>';
                }
            })
            .catch(e => {
                document.getElementById('getResult').innerHTML = '<div class="error">Error: ' + e.message + '</div>';
            });
        }
        
        function runConsoleDiagnostic() {
            console.log('=== Chat Reactions Diagnostic ===');
            console.log('User ID:', <?php echo $userId; ?>);
            console.log('Page URL:', window.location.href);
            console.log('ChatApp available:', !!window.chatApp);
            if (window.chatApp) {
                console.log('UI messageIdToElement size:', window.chatApp.ui?.messageIdToElement?.size || 0);
                console.log('Available message IDs:', Array.from(window.chatApp.ui?.messageIdToElement?.keys() || []));
            }
            console.log('=== Diagnostic Complete ===');
            alert('Diagnostic output printed to console. Check DevTools (F12)');
        }
        
        // Run initial check on load
        window.addEventListener('load', () => {
            console.log('[Test] Page loaded, running initial diagnostics...');
            checkTable();
        });
    </script>
</body>
</html>
