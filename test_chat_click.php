<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

// Quick diagnostic for chat issues
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat Click Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
        .pass { background: #d4edda; color: #155724; }
        .fail { background: #f8d7da; color: #721c24; }
        code { background: #f4f4f4; padding: 2px 5px; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Chat System Diagnostics</h1>
    
    <div class="test" id="test-api">
        <h3>1. Testing API Contacts Endpoint</h3>
        <p>Status: <span id="api-status">Testing...</span></p>
        <pre id="api-response"></pre>
    </div>

    <div class="test" id="test-chat-files">
        <h3>2. Checking Chat JavaScript Files</h3>
        <ul id="files-list"></ul>
    </div>

    <div class="test" id="test-dom">
        <h3>3. Testing DOM Elements</h3>
        <p id="dom-status">Checking on page load...</p>
    </div>

    <div class="test" id="test-events">
        <h3>4. Event System Test</h3>
        <button id="test-event-btn">Fire Test Event</button>
        <p id="event-result"></p>
    </div>

    <script>
        // Test 1: API Endpoint
        fetch('api/contacts.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                const status = document.getElementById('api-status');
                const response = document.getElementById('api-response');
                if (data.contacts && Array.isArray(data.contacts)) {
                    status.textContent = `✓ Success - ${data.contacts.length} contacts`;
                    status.className = 'pass';
                    response.textContent = JSON.stringify(data.contacts.slice(0, 2), null, 2);
                } else {
                    status.textContent = '✗ Failed - Invalid response';
                    status.className = 'fail';
                    response.textContent = JSON.stringify(data, null, 2);
                }
            })
            .catch(e => {
                document.getElementById('api-status').textContent = '✗ Error: ' + e.message;
                document.getElementById('api-status').className = 'fail';
            });

        // Test 2: Files
        const files = [
            'assets/js/chat/ChatManager.js',
            'assets/js/chat/ChatUI.js',
            'assets/js/chat/ChatAPI.js',
            'assets/js/chat/init.js'
        ];
        const filesList = document.getElementById('files-list');
        files.forEach(file => {
            const li = document.createElement('li');
            fetch(file, { method: 'HEAD' })
                .then(r => {
                    li.innerHTML = r.ok 
                        ? `✓ <code>${file}</code>` 
                        : `✗ <code>${file}</code> (${r.status})`;
                    li.className = r.ok ? 'pass' : 'fail';
                })
                .catch(e => {
                    li.innerHTML = `✗ <code>${file}</code> (Error: ${e.message})`;
                    li.className = 'fail';
                });
            filesList.appendChild(li);
        });

        // Test 3: DOM Elements
        window.addEventListener('load', () => {
            const elements = {
                'sidebar': document.getElementById('sidebar'),
                'contactList': document.getElementById('contactList'),
                'chatScreen': document.getElementById('chatScreen'),
                'messages': document.getElementById('messages'),
                'textInput': document.getElementById('textInput')
            };
            
            const status = document.getElementById('dom-status');
            const missing = Object.entries(elements)
                .filter(([, el]) => !el)
                .map(([name]) => name);
            
            if (missing.length === 0) {
                status.textContent = '✓ All required DOM elements found';
                status.style.color = '#155724';
            } else {
                status.textContent = '✗ Missing elements: ' + missing.join(', ');
                status.style.color = '#721c24';
            }
        });

        // Test 4: Event System
        window.addEventListener('load', () => {
            let eventFired = false;
            window.addEventListener('contactSelected', () => {
                eventFired = true;
                document.getElementById('event-result').textContent = '✓ Custom event received!';
            });
            
            document.getElementById('test-event-btn').addEventListener('click', () => {
                window.dispatchEvent(new CustomEvent('contactSelected', { detail: { contactId: 'test' } }));
                setTimeout(() => {
                    if (!eventFired) {
                        document.getElementById('event-result').textContent = '✗ Event not received';
                    }
                }, 100);
            });
        });
    </script>
</body>
</html>
