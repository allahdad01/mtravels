# Real-Time Online Status Implementation

## What Changed

Instead of checking activity logs (which showed any system activity), the chat now tracks **real-time online status** by monitoring who is actively viewing the chat page.

## How It Works

### 1. User Opens Chat
- Session is recorded in `user_online_sessions` table
- User appears as **online** (green dot)

### 2. Every 30 Seconds
- Frontend "pings" the server with `?action=ping`
- `last_activity` timestamp is updated
- Server returns list of currently online users

### 3. User Leaves/Closes Tab
- `beforeunload` event fires
- Sends `?action=logout` to immediately remove from online list
- User disappears from online contacts

### 4. Stale Session Cleanup
- Any session inactive for 5+ minutes is automatically deleted
- Users who lose connection are removed within 5 minutes

## Database

**Table**: `user_online_sessions` (auto-created on first request)

```
user_id    | session_id              | last_activity
-----------|-------------------------|------------------
19         | abc123def456...         | 2025-12-10 10:30:45
24         | xyz789uvw012...         | 2025-12-10 10:30:44
```

## Files Changed

### New
- `api/online_sessions.php` - Real-time session tracking API

### Updated
- `ChatManager.js` - Calls online_sessions.php instead of user_status.php
- `init.js` - Logs out session on beforeunload event

### Deprecated
- `api/user_status.php` - Still exists but marked deprecated

## Testing

1. Open chat.php in browser
2. You should see a **green dot** next to your name in the online list
3. Open another window/user and refresh
4. Both should show as online
5. Close one tab - that user should disappear within 30 seconds
6. You'll see logs like:
   ```
   [ChatManager] Online users: [19, 24, 25]
   ```

## Benefits

✅ Accurate online status (only users actively on chat)
✅ Automatic cleanup (no stale sessions)
✅ Immediate logout (when page closes)
✅ Real-time updates (every 30 seconds)
✅ Shows agency and role info
