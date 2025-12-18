<?php
/**
 * API Testing Tool
 * Test floating tasks API endpoints
 */

session_start();

// Set demo session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['tenant_id'] = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floating Tasks API Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #4099ff;
            margin-bottom: 20px;
        }
        
        .test-section {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .test-section h2 {
            color: #2ed8b6;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .test-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        button {
            background: #4099ff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        button:hover {
            background: #2673cc;
            transform: translateY(-1px);
        }
        
        button:active {
            transform: translateY(1px);
        }
        
        button.success {
            background: #10b981;
        }
        
        button.success:hover {
            background: #059669;
        }
        
        button.danger {
            background: #ef4444;
        }
        
        button.danger:hover {
            background: #dc2626;
        }
        
        input, textarea {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            color: #d4d4d4;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: inherit;
            font-size: 13px;
            flex: 1;
            min-width: 200px;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #4099ff;
            box-shadow: 0 0 0 2px rgba(64, 153, 255, 0.2);
        }
        
        .input-group {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .response-box {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 12px;
            margin-top: 12px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
        }
        
        .response-box pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .response-success {
            color: #10b981;
        }
        
        .response-error {
            color: #ef4444;
        }
        
        .response-info {
            color: #4099ff;
        }
        
        .status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .status.ok {
            background: #10b981;
        }
        
        .status.error {
            background: #ef4444;
        }
        
        .status.pending {
            background: #f59e0b;
        }
        
        .task-list {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 12px;
            margin-top: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .task-item {
            background: #252526;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 8px;
            border-left: 3px solid #4099ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .task-item.completed {
            opacity: 0.6;
            border-left-color: #10b981;
        }
        
        .task-info {
            flex: 1;
        }
        
        .task-text {
            color: #d4d4d4;
            font-size: 13px;
        }
        
        .task-meta {
            color: #858585;
            font-size: 11px;
            margin-top: 4px;
        }
        
        .task-actions {
            display: flex;
            gap: 6px;
            margin-left: 10px;
        }
        
        .task-actions button {
            padding: 4px 8px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-flask"></i> Floating Tasks API Test Suite</h1>
        
        <!-- Get Tasks -->
        <div class="test-section">
            <h2><span class="status pending"></span> GET TASKS</h2>
            <div class="test-controls">
                <button class="success" onclick="testGetTasks()">Load All Tasks</button>
                <button onclick="clearResponse('getResponse')">Clear</button>
            </div>
            <div class="response-box" id="getResponse">Ready to test...</div>
        </div>
        
        <!-- Add Task -->
        <div class="test-section">
            <h2><span class="status pending"></span> ADD TASK</h2>
            <div class="input-group">
                <input type="text" id="addTaskInput" placeholder="Enter task text..." maxlength="200">
                <button class="success" onclick="testAddTask()">Add Task</button>
            </div>
            <div class="response-box" id="addResponse">Ready to test...</div>
        </div>
        
        <!-- Update Task -->
        <div class="test-section">
            <h2><span class="status pending"></span> UPDATE TASK</h2>
            <div class="input-group">
                <input type="number" id="updateTaskId" placeholder="Task ID" min="1">
                <select id="updateCompleted" style="flex: 0; min-width: 100px;">
                    <option value="false">Mark Incomplete</option>
                    <option value="true">Mark Complete</option>
                </select>
                <button class="success" onclick="testUpdateTask()">Update</button>
            </div>
            <div class="response-box" id="updateResponse">Ready to test...</div>
        </div>
        
        <!-- Delete Task -->
        <div class="test-section">
            <h2><span class="status pending"></span> DELETE TASK</h2>
            <div class="input-group">
                <input type="number" id="deleteTaskId" placeholder="Task ID to delete" min="1">
                <button class="danger" onclick="testDeleteTask()">Delete</button>
            </div>
            <div class="response-box" id="deleteResponse">Ready to test...</div>
        </div>
        
        <!-- Clear Completed -->
        <div class="test-section">
            <h2><span class="status pending"></span> CLEAR COMPLETED TASKS</h2>
            <div class="test-controls">
                <button class="danger" onclick="testClearCompleted()">Clear All Completed</button>
            </div>
            <div class="response-box" id="clearResponse">Ready to test...</div>
        </div>
        
        <!-- Tasks Display -->
        <div class="test-section">
            <h2><span class="status pending"></span> LIVE TASKS</h2>
            <button class="success" onclick="loadAndDisplayTasks()">Refresh List</button>
            <div id="taskListContainer">No tasks loaded. Click "Refresh List" to load tasks.</div>
        </div>
    </div>
    
    <script>
        const API_URL = './api/floating_tasks_api.php';
        
        async function apiCall(action, data = {}) {
            try {
                const payload = { action, ...data };
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                return {
                    status: response.status,
                    ok: response.ok,
                    data: result
                };
            } catch (error) {
                return {
                    status: 0,
                    ok: false,
                    error: error.message
                };
            }
        }
        
        function displayResponse(elementId, status, data, isError = false) {
            const element = document.getElementById(elementId);
            const className = isError ? 'response-error' : (status === 200 || status === 0 ? 'response-success' : 'response-error');
            element.innerHTML = `<pre class="${className}">${JSON.stringify(data, null, 2)}</pre>`;
        }
        
        function clearResponse(elementId) {
            document.getElementById(elementId).innerHTML = 'Ready to test...';
        }
        
        async function testGetTasks() {
            const result = await apiCall('get');
            displayResponse('getResponse', result.status, result.data, !result.ok);
        }
        
        async function testAddTask() {
            const input = document.getElementById('addTaskInput');
            const text = input.value.trim();
            
            if (!text) {
                alert('Please enter task text');
                return;
            }
            
            const result = await apiCall('add', { text });
            displayResponse('addResponse', result.status, result.data, !result.ok);
            
            if (result.ok) {
                input.value = '';
                loadAndDisplayTasks();
            }
        }
        
        async function testUpdateTask() {
            const id = document.getElementById('updateTaskId').value;
            const completed = document.getElementById('updateCompleted').value === 'true';
            
            if (!id) {
                alert('Please enter task ID');
                return;
            }
            
            const result = await apiCall('update', { id: parseInt(id), completed });
            displayResponse('updateResponse', result.status, result.data, !result.ok);
            
            if (result.ok) {
                loadAndDisplayTasks();
            }
        }
        
        async function testDeleteTask() {
            const id = document.getElementById('deleteTaskId').value;
            
            if (!id) {
                alert('Please enter task ID');
                return;
            }
            
            if (!confirm(`Delete task #${id}?`)) return;
            
            const result = await apiCall('delete', { id: parseInt(id) });
            displayResponse('deleteResponse', result.status, result.data, !result.ok);
            
            if (result.ok) {
                document.getElementById('deleteTaskId').value = '';
                loadAndDisplayTasks();
            }
        }
        
        async function testClearCompleted() {
            if (!confirm('Delete all completed tasks?')) return;
            
            const result = await apiCall('clear_completed');
            displayResponse('clearResponse', result.status, result.data, !result.ok);
            
            if (result.ok) {
                loadAndDisplayTasks();
            }
        }
        
        async function loadAndDisplayTasks() {
            const result = await apiCall('get');
            const container = document.getElementById('taskListContainer');
            
            if (!result.ok || !result.data.tasks) {
                container.innerHTML = '<p class="response-error">Failed to load tasks</p>';
                return;
            }
            
            const tasks = result.data.tasks;
            
            if (tasks.length === 0) {
                container.innerHTML = '<p class="response-info">No tasks found. Add one to get started!</p>';
                return;
            }
            
            container.innerHTML = tasks.map(task => `
                <div class="task-item ${task.completed ? 'completed' : ''}">
                    <div class="task-info">
                        <div class="task-text">${escapeHtml(task.text)}</div>
                        <div class="task-meta">
                            ID: ${task.id} | Status: ${task.completed ? 'Complete' : 'Pending'} | Created: ${new Date(task.createdAt).toLocaleString()}
                        </div>
                    </div>
                    <div class="task-actions">
                        <button onclick="toggleTaskStatus(${task.id}, ${!task.completed})">
                            ${task.completed ? 'Uncomplete' : 'Complete'}
                        </button>
                        <button class="danger" onclick="deleteTaskQuick(${task.id})">Delete</button>
                    </div>
                </div>
            `).join('');
        }
        
        async function toggleTaskStatus(id, completed) {
            const result = await apiCall('update', { id, completed });
            if (result.ok) {
                loadAndDisplayTasks();
            }
        }
        
        async function deleteTaskQuick(id) {
            if (!confirm('Delete this task?')) return;
            const result = await apiCall('delete', { id });
            if (result.ok) {
                loadAndDisplayTasks();
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-load tasks on page load
        window.addEventListener('load', function() {
            loadAndDisplayTasks();
        });
    </script>
</body>
</html>
