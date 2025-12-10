# Chat Online Status & User Info Display

## Features Added

Now the chat displays:
1. **Online Status Indicator** (green pulsing dot) - shows when a user is actively on the chat
2. **Agency Name** - displayed below user name
3. **User Role** - displayed next to agency name

## How It Works

### Backend (API)

**New Endpoint**: `api/online_sessions.php`
- Creates `user_online_sessions` table automatically on first request
- When a user loads the chat page, they are inserted into the table
- Every 30 seconds, the frontend "pings" to update `last_activity` timestamp
- Users are shown as online if their `last_activity` is within the last 5 minutes
- Automatically cleans up stale sessions (older than 5 minutes)
- On logout or page close, immediately removes the user from online list

### Frontend (JavaScript)

**ChatManager** (`assets/js/chat/ChatManager.js`):
- Calls `loadUserStatus()` during initialization
- Refreshes status every 30 seconds automatically
- Updates contact objects with `online` and `typing` properties
- Dispatches `userStatusUpdated` event when status changes

**ChatUI** (`assets/js/chat/ChatUI.js`):
- Renders online/typing indicators as colored dots
- Displays agency name and role below contact name
- Listens to `userStatusUpdated` events to refresh contact list

**Styling** (`assets/css/chat.css`):
- Green pulsing dot for online users (#10b981)
- Blue pulsing dot for typing users (#3b82f6)
- Agency/role text in smaller gray font

## Visual Design

### Contact List Item Layout
```
[Avatar with indicator] Name
                       Agency • Role
                       Last message...
```

### Status Indicators
- **Green dot** - User is online (pulsing slightly)
- **Blue dot** - User is actively typing (pulsing more)
- **No dot** - User is offline

## Data Flow

```
User loads chat.php
  ↓
ChatManager.init() calls loadUserStatus()
  ↓
api/online_sessions.php?action=ping
  ↓
Insert user into user_online_sessions table
Returns: { online: [1, 2, 3, ...] }
  ↓
Update contact.online = true/false
  ↓
Dispatch 'userStatusUpdated' event
  ↓
ChatUI listens and re-renders contacts

[Every 30 seconds, repeat]

User closes chat or logs out
  ↓
beforeunload event fires
  ↓
api/online_sessions.php?action=logout
  ↓
Delete user from user_online_sessions
```

## Database Table

**Table**: `user_online_sessions`
```sql
CREATE TABLE user_online_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255),
    last_activity TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, session_id),
    INDEX idx_last_activity (last_activity)
)
```

## Files Modified

- `api/online_sessions.php` - NEW: Real-time online session tracking
- `api/user_status.php` - DEPRECATED: Replaced by online_sessions.php
- `assets/js/chat/ChatManager.js` - Updated to use online_sessions endpoint
- `assets/js/chat/ChatUI.js` - Added online indicators and agency/role rendering
- `assets/css/chat.css` - Added styles for indicators and agency/role text
- `assets/js/chat/init.js` - Added logout handler on page unload

## Future Improvements

1. **Real-time Status**: Replace 30-second polling with WebSocket for real-time updates
2. **Typing Detection**: Add typing indicator when user is actually typing in the message input
3. **Last Seen**: Display "Last seen 5 minutes ago" instead of just online/offline
4. **Custom Presence**: Allow users to set custom status (away, busy, etc.)
5. **Persistent Status**: Store in database for historical tracking

## Testing

1. Open the chat application
2. You should see online indicators (green dots) next to users who have been active recently
3. Contacts should display their agency name and role
4. Status updates automatically every 30 seconds
5. Check the browser console for `[Chat] User status updated` logs
