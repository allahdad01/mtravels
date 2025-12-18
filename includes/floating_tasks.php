<?php
/**
 * Floating Tasks Widget with Database Persistence
 * Display and manage a floating to-do task reminder across all pages
 */

// Get current user session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;
?>

<!-- Floating Tasks Widget HTML -->
<div id="floatingTasksWidget" class="floating-tasks-widget minimized">
    <!-- Minimize/Expand Toggle -->
    <div class="tasks-header">
        <div class="tasks-title">
            <i class="fas fa-tasks"></i>
            <span>My Tasks</span>
        </div>
        <div class="tasks-controls">
            <button id="tasksMinimizeBtn" class="tasks-btn" title="Minimize">
                <i class="fas fa-minus"></i>
            </button>
            <button id="tasksCloseBtn" class="tasks-btn" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Tasks Content -->
    <div class="tasks-content">
        <!-- Add New Task Form -->
        <div class="add-task-form">
            <input 
                type="text" 
                id="newTaskInput" 
                class="task-input" 
                placeholder="Add a new task..." 
                maxlength="200"
            >
            <button id="addTaskBtn" class="add-task-btn" title="Add Task">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <!-- Tasks List -->
        <div class="tasks-list" id="tasksList">
            <div class="no-tasks">
                <i class="fas fa-inbox"></i>
                <p>No tasks yet. Add one to get started!</p>
            </div>
        </div>

        <!-- Tasks Footer Stats -->
        <div class="tasks-footer">
            <span class="task-count">
                <span id="completedCount">0</span>/<span id="totalCount">0</span> completed
            </span>
            <button id="clearCompletedBtn" class="clear-btn" title="Clear Completed">
                Clear
            </button>
        </div>
    </div>
</div>

<!-- Floating Tasks Toggle Button (when minimized) -->
<div id="floatingTasksToggle" class="floating-tasks-toggle" style="display: block;">
    <button id="toggleTasksBtn" class="toggle-btn" title="Open Tasks">
        <i class="fas fa-tasks"></i>
        <span id="pendingBadge" class="badge" style="display: none;">0</span>
    </button>
</div>

<style>
    /* Floating Tasks Widget CSS */
    .floating-tasks-widget {
        position: fixed;
        bottom: 150px;
        right: 20px;
        width: 350px;
        max-height: 500px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        z-index: 9998;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(0, 0, 0, 0.08);
        font-family: inherit;
        animation: slideInUp 0.3s ease-out;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Header */
    .tasks-header {
        padding: 16px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border-radius: 12px 12px 0 0;
    }

    .tasks-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }

    .tasks-title i {
        font-size: 16px;
    }

    .tasks-controls {
        display: flex;
        gap: 8px;
    }

    .tasks-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        font-size: 12px;
    }

    .tasks-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }

    /* Content */
    .tasks-content {
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow: hidden;
    }

    /* Add Task Form */
    .add-task-form {
        padding: 12px;
        display: flex;
        gap: 8px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .task-input {
        flex: 1;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 13px;
        font-family: inherit;
        transition: all 0.2s ease;
    }

    .task-input:focus {
        outline: none;
        border-color: #4099ff;
        box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
    }

    .add-task-btn {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        font-size: 14px;
    }

    .add-task-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .add-task-btn:active {
        transform: scale(0.95);
    }

    .add-task-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Tasks List */
    .tasks-list {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }

    .tasks-list::-webkit-scrollbar {
        width: 6px;
    }

    .tasks-list::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.03);
        border-radius: 3px;
    }

    .tasks-list::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.15);
        border-radius: 3px;
    }

    .tasks-list::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.25);
    }

    /* Task Item */
    .task-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        margin-bottom: 6px;
        background: white;
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 8px;
        transition: all 0.2s ease;
        animation: slideInLeft 0.2s ease-out;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .task-item:hover {
        background: rgba(64, 153, 255, 0.05);
        border-color: rgba(64, 153, 255, 0.2);
    }

    .task-item.completed {
        opacity: 0.6;
    }

    .task-item.completed .task-text {
        text-decoration: line-through;
        color: rgba(0, 0, 0, 0.4);
    }

    /* Checkbox */
    .task-checkbox {
        width: 18px;
        height: 18px;
        border: 2px solid #cbd5e1;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        appearance: none;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .task-checkbox:hover {
        border-color: #4099ff;
    }

    .task-checkbox:checked {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border-color: #4099ff;
        color: white;
    }

    .task-checkbox:checked::after {
        content: '✓';
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: white;
    }

    /* Task Text */
    .task-text {
        flex: 1;
        font-size: 13px;
        color: #1e293b;
        word-break: break-word;
    }

    /* Delete Button */
    .task-delete {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        padding: 4px 6px;
        border-radius: 4px;
        transition: all 0.2s ease;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        flex-shrink: 0;
    }

    .task-item:hover .task-delete {
        opacity: 1;
    }

    .task-delete:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    /* No Tasks */
    .no-tasks {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        color: #94a3b8;
        text-align: center;
    }

    .no-tasks i {
        font-size: 32px;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    .no-tasks p {
        font-size: 13px;
        margin: 0;
    }

    /* Loading State */
    .tasks-list.loading {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100px;
    }

    .spinner {
        border: 3px solid rgba(64, 153, 255, 0.1);
        border-top: 3px solid #4099ff;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Footer */
    .tasks-footer {
        padding: 12px;
        border-top: 1px solid rgba(0, 0, 0, 0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        background: #f8fafc;
        border-radius: 0 0 12px 12px;
    }

    .task-count {
        color: #64748b;
        font-weight: 500;
    }

    .clear-btn {
        background: none;
        border: none;
        color: #4099ff;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .clear-btn:hover {
        background: rgba(64, 153, 255, 0.1);
        color: #2673cc;
    }

    .clear-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Toggle Button (minimized state) */
    .floating-tasks-toggle {
        position: fixed;
        bottom: 150px;
        right: 20px;
        z-index: 9998;
    }

    .toggle-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        box-shadow: 0 8px 24px rgba(64, 153, 255, 0.35);
        transition: all 0.3s ease;
        position: relative;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-8px); }
    }

    .toggle-btn:hover {
        transform: scale(1.1) translateY(-8px);
        box-shadow: 0 14px 35px rgba(64, 153, 255, 0.45);
    }

    .toggle-btn:active {
        transform: scale(0.95);
        animation: none;
    }

    /* Badge */
    .badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #ef4444;
        color: white;
        min-width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        box-shadow: 0 2px 10px rgba(239, 68, 68, 0.4);
        border: 2px solid white;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { box-shadow: 0 2px 10px rgba(239, 68, 68, 0.4); }
        50% { box-shadow: 0 2px 15px rgba(239, 68, 68, 0.6); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .floating-tasks-widget {
            width: calc(100% - 40px);
            max-height: 400px;
            bottom: 20px;
            right: 20px;
            left: 20px;
        }

        .toggle-btn {
            width: 48px;
            height: 48px;
            font-size: 20px;
        }
    }

    /* Minimized class */
    .floating-tasks-widget.minimized {
        display: none;
    }

    /* Drag handle cursor */
    .tasks-header {
        cursor: move;
    }
</style>

<script>
    // Floating Tasks Manager with Database Backend
    class FloatingTasksManager {
        constructor() {
            this.widget = document.getElementById('floatingTasksWidget');
            this.toggle = document.getElementById('floatingTasksToggle');
            this.tasksList = document.getElementById('tasksList');
            this.newTaskInput = document.getElementById('newTaskInput');
            this.addTaskBtn = document.getElementById('addTaskBtn');
            this.tasksMinimizeBtn = document.getElementById('tasksMinimizeBtn');
            this.tasksCloseBtn = document.getElementById('tasksCloseBtn');
            this.toggleTasksBtn = document.getElementById('toggleTasksBtn');
            this.clearCompletedBtn = document.getElementById('clearCompletedBtn');
            this.completedCount = document.getElementById('completedCount');
            this.totalCount = document.getElementById('totalCount');
            this.pendingBadge = document.getElementById('pendingBadge');
            
            this.apiBaseUrl = '../api/floating_tasks_api.php';
            this.tasks = [];
            this.isDragging = false;
            this.offsetX = 0;
            this.offsetY = 0;
            this.isSyncing = false;

            this.init();
        }

        async init() {
            // Start minimized by default
            this.widget.classList.add('minimized');
            this.toggle.style.display = 'block';
            
            await this.loadTasks();
            this.renderTasks();
            this.attachEventListeners();
            this.makeWidgetDraggable();
            this.updateStats();
            
            // Auto-sync every 30 seconds
            setInterval(() => this.loadTasks(), 30000);
        }

        async loadTasks() {
            try {
                const response = await fetch(`${this.apiBaseUrl}?action=get`);
                if (!response.ok) throw new Error('Failed to load tasks');
                
                const data = await response.json();
                if (data.success) {
                    this.tasks = data.tasks;
                    this.renderTasks();
                    this.updateStats();
                }
            } catch (error) {
                console.error('Error loading tasks:', error);
                this.showError('Failed to load tasks');
            }
        }

        attachEventListeners() {
            // Add task
            this.addTaskBtn.addEventListener('click', () => this.addTask());
            this.newTaskInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.addTask();
            });

            // Minimize/Maximize
            this.tasksMinimizeBtn.addEventListener('click', () => this.toggleMinimize());
            this.toggleTasksBtn.addEventListener('click', () => this.toggleMinimize());

            // Close
            this.tasksCloseBtn.addEventListener('click', () => this.closeWidget());

            // Clear completed
            this.clearCompletedBtn.addEventListener('click', () => this.clearCompleted());
        }

        makeWidgetDraggable() {
            const header = this.widget.querySelector('.tasks-header');
            let rect = this.widget.getBoundingClientRect();

            header.addEventListener('mousedown', (e) => {
                if (e.target.closest('.tasks-controls')) return;
                
                this.isDragging = true;
                this.offsetX = e.clientX - rect.left;
                this.offsetY = e.clientY - rect.top;
                this.widget.style.transition = 'none';
            });

            document.addEventListener('mousemove', (e) => {
                if (!this.isDragging) return;

                let newX = e.clientX - this.offsetX;
                let newY = e.clientY - this.offsetY;

                // Constrain to viewport
                newX = Math.max(0, Math.min(newX, window.innerWidth - this.widget.offsetWidth));
                newY = Math.max(0, Math.min(newY, window.innerHeight - this.widget.offsetHeight));

                this.widget.style.position = 'fixed';
                this.widget.style.bottom = 'auto';
                this.widget.style.right = 'auto';
                this.widget.style.left = newX + 'px';
                this.widget.style.top = newY + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (this.isDragging) {
                    this.isDragging = false;
                    this.widget.style.transition = 'all 0.3s ease';
                }
            });
        }

        async addTask() {
            const text = this.newTaskInput.value.trim();
            if (!text) return;

            if (this.isSyncing) return;
            this.isSyncing = true;
            this.addTaskBtn.disabled = true;

            try {
                const response = await fetch(this.apiBaseUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', text: text })
                });

                if (!response.ok) throw new Error('Failed to add task');
                
                const data = await response.json();
                if (data.success) {
                    this.tasks.push(data.task);
                    this.renderTasks();
                    this.newTaskInput.value = '';
                    this.newTaskInput.focus();
                    this.updateStats();
                }
            } catch (error) {
                console.error('Error adding task:', error);
                this.showError('Failed to add task');
            } finally {
                this.isSyncing = false;
                this.addTaskBtn.disabled = false;
            }
        }

        async deleteTask(id) {
            if (this.isSyncing) return;
            this.isSyncing = true;

            try {
                const response = await fetch(this.apiBaseUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                });

                if (!response.ok) throw new Error('Failed to delete task');
                
                const data = await response.json();
                if (data.success) {
                    this.tasks = this.tasks.filter(t => t.id !== id);
                    this.renderTasks();
                    this.updateStats();
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                this.showError('Failed to delete task');
            } finally {
                this.isSyncing = false;
            }
        }

        async toggleTask(id) {
            const task = this.tasks.find(t => t.id === id);
            if (!task || this.isSyncing) return;
            
            this.isSyncing = true;

            try {
                const response = await fetch(this.apiBaseUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'update', 
                        id: id, 
                        completed: !task.completed 
                    })
                });

                if (!response.ok) throw new Error('Failed to update task');
                
                const data = await response.json();
                if (data.success) {
                    task.completed = !task.completed;
                    this.renderTasks();
                    this.updateStats();
                }
            } catch (error) {
                console.error('Error updating task:', error);
                this.showError('Failed to update task');
            } finally {
                this.isSyncing = false;
            }
        }

        async clearCompleted() {
            const completed = this.tasks.filter(t => t.completed).length;
            if (completed === 0) return;
            
            if (!confirm(`Delete ${completed} completed task${completed > 1 ? 's' : ''}?`)) return;

            if (this.isSyncing) return;
            this.isSyncing = true;
            this.clearCompletedBtn.disabled = true;

            try {
                const response = await fetch(this.apiBaseUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_completed' })
                });

                if (!response.ok) throw new Error('Failed to clear completed tasks');
                
                const data = await response.json();
                if (data.success) {
                    this.tasks = this.tasks.filter(t => !t.completed);
                    this.renderTasks();
                    this.updateStats();
                }
            } catch (error) {
                console.error('Error clearing completed:', error);
                this.showError('Failed to clear completed tasks');
            } finally {
                this.isSyncing = false;
                this.clearCompletedBtn.disabled = false;
            }
        }

        toggleMinimize() {
            this.widget.classList.toggle('minimized');
            this.toggle.style.display = this.widget.classList.contains('minimized') ? 'block' : 'none';
        }

        closeWidget() {
            this.widget.style.display = 'none';
            this.toggle.style.display = 'block';
        }

        renderTasks() {
            if (this.tasks.length === 0) {
                this.tasksList.innerHTML = '<div class="no-tasks"><i class="fas fa-inbox"></i><p>No tasks yet. Add one to get started!</p></div>';
                return;
            }

            this.tasksList.innerHTML = this.tasks.map(task => `
                <div class="task-item ${task.completed ? 'completed' : ''}">
                    <input 
                        type="checkbox" 
                        class="task-checkbox" 
                        ${task.completed ? 'checked' : ''}
                        data-id="${task.id}"
                    >
                    <span class="task-text">${this.escapeHtml(task.text)}</span>
                    <button class="task-delete" data-id="${task.id}" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `).join('');

            // Attach checkbox listeners
            this.tasksList.querySelectorAll('.task-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.toggleTask(parseInt(checkbox.dataset.id));
                });
            });

            // Attach delete listeners
            this.tasksList.querySelectorAll('.task-delete').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.deleteTask(parseInt(btn.dataset.id));
                });
            });
        }

        updateStats() {
            const total = this.tasks.length;
            const completed = this.tasks.filter(t => t.completed).length;
            const pending = total - completed;

            this.totalCount.textContent = total;
            this.completedCount.textContent = completed;

            // Update badge - always visible with pending count
            if (pending > 0) {
                this.pendingBadge.textContent = pending > 99 ? '99+' : pending;
                this.pendingBadge.style.display = 'flex';
            } else {
                this.pendingBadge.textContent = '0';
                this.pendingBadge.style.display = 'flex';
            }

            // Disable clear button if no completed tasks
            this.clearCompletedBtn.disabled = completed === 0;
        }

        showError(message) {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: #ef4444;
                color: white;
                padding: 12px 16px;
                border-radius: 6px;
                font-size: 13px;
                z-index: 10000;
                animation: slideInUp 0.3s ease-out;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideInUp 0.3s ease-out reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        new FloatingTasksManager();
    });
</script>
