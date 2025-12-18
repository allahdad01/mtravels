# Floating Tasks Widget - Setup Guide

## Overview

The Floating Tasks Widget is a persistent to-do list that appears on all pages where the header is included. Tasks are stored in the database and synced across all user sessions.

## Features

- ✅ **Database Persistence** - All tasks stored in database per user/tenant
- ✅ **Real-time Sync** - Tasks auto-sync every 30 seconds across tabs/windows
- ✅ **User Isolation** - Each user sees only their tasks
- ✅ **Add/Delete Tasks** - Simple CRUD operations
- ✅ **Mark Complete** - Check off completed tasks
- ✅ **Clear Completed** - Bulk delete completed tasks
- ✅ **Statistics** - Shows completed/total count
- ✅ **Draggable** - Move widget around the screen
- ✅ **Minimize** - Collapse to floating button with badge
- ✅ **Responsive** - Works on mobile and desktop

## Installation

### Step 1: Create Database Table

Visit this URL in your browser:
```
http://localhost/mtravels/setup_floating_tasks.php
```

You should see a success response:
```json
{
  "success": true,
  "message": "floating_tasks table created successfully",
  "next_step": "Delete this setup file and start using floating tasks widget"
}
```

After successful creation, **delete the setup file**:
```
c:/xampp/htdocs/almoqadas/mtravels/setup_floating_tasks.php
```

### Step 2: Widget is Already Included

The widget is automatically included in all pages that use `includes/header.php` at the end of the file.

No additional changes needed!

## File Structure

```
mtravels/
├── api/
│   └── floating_tasks_api.php          # API endpoints for CRUD operations
├── includes/
│   └── floating_tasks.php              # Widget HTML, CSS, and JavaScript
├── migrations/
│   └── create_floating_tasks_table.php # Database migration script
├── setup_floating_tasks.php            # One-time setup (delete after use)
└── FLOATING_TASKS_SETUP.md             # This file
```

## Database Schema

```sql
CREATE TABLE floating_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tenant_id INT DEFAULT 1,
    task_text VARCHAR(255) NOT NULL,
    completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_user_tenant (user_id, tenant_id),
    KEY idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
```

## API Endpoints

All requests to `/api/floating_tasks_api.php`

### Get Tasks
```
GET /api/floating_tasks_api.php?action=get
Response: { success: true, tasks: [...] }
```

### Add Task
```
POST /api/floating_tasks_api.php
Body: { action: "add", text: "Task description" }
Response: { success: true, task: {...} }
```

### Update Task (Mark Complete)
```
POST /api/floating_tasks_api.php
Body: { action: "update", id: 1, completed: true }
Response: { success: true }
```

### Delete Task
```
POST /api/floating_tasks_api.php
Body: { action: "delete", id: 1 }
Response: { success: true }
```

### Clear Completed Tasks
```
POST /api/floating_tasks_api.php
Body: { action: "clear_completed" }
Response: { success: true }
```

## Security Features

- ✅ **User Authentication** - Checks session user_id
- ✅ **Tenant Isolation** - Each tenant sees only their tasks
- ✅ **Ownership Verification** - Users can only access their own tasks
- ✅ **Input Validation** - Text limited to 200 characters
- ✅ **SQL Injection Protection** - Uses prepared statements
- ✅ **CSRF Protection** - Uses session-based authentication

## JavaScript API

The widget exposes a `FloatingTasksManager` class with methods:

```javascript
// Access the manager (set on DOMContentLoaded)
// Tasks are automatically loaded and synced

// Methods available:
manager.addTask()           // Add a new task
manager.deleteTask(id)      // Delete task by ID
manager.toggleTask(id)      // Toggle completion status
manager.clearCompleted()    // Clear all completed tasks
manager.loadTasks()         // Manually reload from database
manager.toggleMinimize()    // Minimize/maximize widget
manager.closeWidget()       // Close widget
```

## Customization

### Styling

The widget uses CSS variables and gradients. Modify in `includes/floating_tasks.php`:

```css
/* Change gradient colors */
background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);

/* Change widget width */
width: 350px;

/* Change max-height */
max-height: 500px;
```

### Auto-sync Interval

Change in `floating_tasks.php`:

```javascript
// Auto-sync every 30 seconds (default)
setInterval(() => this.loadTasks(), 30000);

// Change to 10 seconds
setInterval(() => this.loadTasks(), 10000);
```

### Task Text Limit

Change in both files:

```php
// In api/floating_tasks_api.php (line ~50)
if (empty($text) || strlen($text) > 200) { // Change 200

// In includes/floating_tasks.php (maxlength)
<input maxlength="200" ... > // Change 200
```

## Troubleshooting

### Widget not appearing
- Check that header.php include is in your page
- Verify user is logged in (checks $_SESSION['user_id'])
- Check browser console for JavaScript errors

### Tasks not saving
- Verify floating_tasks table exists: `SHOW TABLES LIKE 'floating_tasks'`
- Check database connection in includes/db.php
- Check browser network tab in DevTools
- Verify user_id and tenant_id are set in session

### Database errors
- Run setup file again: http://localhost/mtravels/setup_floating_tasks.php
- Check user table exists and has users with proper IDs
- Verify database permissions

### Tasks not syncing across tabs
- Auto-sync happens every 30 seconds
- Manual refresh via DevTools or page reload
- Check browser allows localStorage (privacy mode issue)

## Browser Compatibility

- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

Requires:
- JavaScript enabled
- Fetch API support
- CSS Flexbox support

## Performance Considerations

- Tasks load asynchronously (non-blocking)
- Auto-sync interval: 30 seconds (configurable)
- Disabled during sync operations (prevents duplicates)
- Efficient database queries with indexing
- CSS animations use GPU acceleration

## Future Enhancements

- [ ] Task categories/tags
- [ ] Task priorities
- [ ] Due dates/reminders
- [ ] Task sharing between users
- [ ] Recurring tasks
- [ ] Task templates
- [ ] Mobile app sync
- [ ] Cloud backup

## Support

For issues or questions, check:
1. Browser console (F12 > Console)
2. Network tab (F12 > Network)
3. PHP error logs
4. Database logs

---

Last Updated: 2024
Version: 1.0
