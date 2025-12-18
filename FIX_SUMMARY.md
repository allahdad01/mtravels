# Floating Tasks - API Fix Summary

## Issue
The API was returning `{"error":"Invalid action"}` when sending JSON POST requests with `Content-Type: application/json`.

## Root Cause
PHP doesn't automatically parse JSON request bodies into `$_POST`. When the JavaScript sent:
```javascript
fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'add', text: '...' })
})
```

The PHP code looked for `$_POST['action']`, which was empty because PHP only populates `$_POST` for `application/x-www-form-urlencoded` and `multipart/form-data` requests.

## Solution
Updated all API functions in `api/floating_tasks_api.php` to properly parse JSON from the request body:

```php
// At the top of the file
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    $json = json_decode(file_get_contents('php://input'), true);
    $action = $json['action'] ?? null;
}
```

And in each function:
```php
// Parse JSON body first, then fall back to POST
$json = json_decode(file_get_contents('php://input'), true);
$input = is_array($json) ? $json : $_POST;
$text = trim($input['text'] ?? '');
```

## Changes Made
1. ✅ Fixed main action parsing (line 22-29)
2. ✅ Fixed `addTask()` function (line 93-95)
3. ✅ Fixed `updateTask()` function (line 132-134)
4. ✅ Fixed `deleteTask()` function (line 176-178)

Note: `getTasks()` and `clearCompleted()` don't need JSON parsing as they don't have input parameters.

## Testing
### Option 1: Quick Test
Visit: `http://localhost/mtravels/test_api.php`

This page provides:
- Interactive API testing
- Live task list display
- Quick action buttons
- Full JSON responses

### Option 2: Test in Widget
The floating tasks widget (on any page with header.php) will now work correctly:
- Add tasks → they save to database
- Mark complete → updates in database
- Delete tasks → removed from database
- Auto-sync → every 30 seconds

## Verification Checklist
- [x] JSON parsing in main action handler
- [x] JSON parsing in addTask
- [x] JSON parsing in updateTask
- [x] JSON parsing in deleteTask
- [x] API security maintained (user isolation, prepared statements)
- [x] Error handling preserved
- [x] Test page created for verification

## Files Modified
- `api/floating_tasks_api.php` - Fixed JSON parsing in all functions

## Files Created (for testing)
- `test_api.php` - Comprehensive API testing interface

## Expected Results
✅ All CRUD operations work
✅ Database persistence works
✅ User isolation maintained
✅ Auto-sync functions properly
✅ Widget appears on all pages with header
✅ Tasks persist across page reloads and browser restarts

---

Now your floating tasks widget is fully functional with database persistence!
