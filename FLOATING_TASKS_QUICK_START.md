# Floating Tasks - Quick Start

## 3 Steps to Enable

### 1️⃣ Create Database Table
Visit: `http://localhost/mtravels/setup_floating_tasks.php`

Expected response:
```json
{ "success": true, "message": "floating_tasks table created successfully" }
```

### 2️⃣ Delete Setup File
Delete: `c:/xampp/htdocs/almoqadas/mtravels/setup_floating_tasks.php`

### 3️⃣ Done!
The widget automatically appears on all pages with header.php included.

---

## Usage

**Add Task:**
- Type in input field
- Click + button or press Enter

**Complete Task:**
- Click checkbox to toggle completion
- Strikethrough appears

**Delete Task:**
- Click trash icon on hover

**Minimize:**
- Click - button to collapse
- Click floating circle to expand
- Badge shows pending count

**Clear Completed:**
- Click "Clear" to delete all completed tasks

---

## Features

| Feature | Status |
|---------|--------|
| Database persistence | ✅ |
| User isolation | ✅ |
| Auto-sync (30s) | ✅ |
| Draggable widget | ✅ |
| Responsive design | ✅ |
| Real-time updates | ✅ |

---

## Files Created

```
api/
  └── floating_tasks_api.php        (API endpoints)

includes/
  └── floating_tasks.php            (Widget UI + JS)

migrations/
  └── create_floating_tasks_table.php (DB schema)

setup_floating_tasks.php            (One-time setup - DELETE after step 1)

FLOATING_TASKS_SETUP.md             (Full documentation)
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Widget not showing | Check header.php is included, user is logged in |
| Tasks not saving | Run setup_floating_tasks.php again |
| Network errors | Check browser DevTools > Network tab |
| Database error | Verify floating_tasks table exists in DB |

---

## Browser DevTools Check

**Console (F12):**
- No errors about floating tasks

**Network (F12 > Network):**
- Requests to `/api/floating_tasks_api.php` show 200 OK

**Application > LocalStorage:**
- No floating_tasks_* entries (all data in DB now)

---

**Ready to use!** The widget will appear in the bottom-right corner.
