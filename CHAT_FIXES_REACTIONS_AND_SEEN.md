# Chat Fixes - Reactions and Mark as Seen

## Issues Fixed

### 1. Reactions Not Being Saved
**Problem**: Clicking a reaction emoji didn't save it to the database
**Solution**: 
- Updated to use correct API endpoint: `api/message_reactions.php`
- Added comprehensive error logging to debug issues
- Implemented proper response handling
- Reactions now:
  - Save to database immediately
  - Refresh from server to show accurate counts
  - Toggle on/off if clicking same emoji again

### 2. Messages Not Marked as Seen
**Problem**: Opening a contact didn't mark messages as read
**Solution**:
- Added `manager.markAsRead(contactId)` after loading messages
- This sends `mark_seen` action to `api/messages.php`
- Messages are now marked as seen when you open the conversation

## Setup Required

### 1. Create Message Reactions Table
Run this in your browser:
```
http://localhost/almoqadas/mtravels/create_reactions_table.php
```

### 2. Verify Database Table
```sql
SHOW TABLES LIKE 'message_reactions';
DESC message_reactions;
```

## How to Test

### Test Reactions:
1. Open chat and select a contact
2. Hover over any message
3. Click the menu (⋮) button
4. Click "React"
5. Select an emoji
6. Check browser console for logs
7. Reaction should appear below message with count

### Test Mark as Seen:
1. Ask another user to send you messages
2. Open the conversation
3. You should see messages marked with ✓✓ (blue checkmarks) in your sent messages
4. Their messages should show as read on their end

## Browser Console Debugging

Open DevTools (F12) and check Console tab for logs like:
```
[Chat] Loading reactions for message: 123
[Chat] Got reactions data: {reactions: {...}}
[Chat] Added reaction: 👍 1
[ChatUI] Adding reaction: 123, 👍
[ChatUI] Reaction saved, updating UI
```

## API Endpoints

### Add/Toggle Reaction
```
POST /api/message_reactions.php
Parameters:
- message_id: (int) Message ID
- emoji: (string) Emoji character
- action: (string) 'add' (default) or 'remove'

Response: {ok: true, action: "added" | "removed"}
```

### Get Reactions
```
GET /api/message_reactions.php?message_id=123

Response: {reactions: {"👍": [{user_id, user_name}, ...], ...}}
```

### Mark Messages as Seen
```
POST /api/messages.php
Parameters:
- action: 'mark_seen'
- peer_id: (int) Contact user ID

Response: {ok: true, updated: N}
```

## Files Modified

1. **ChatUIClean.js**
   - Improved `addReaction()` with proper error handling
   - Added console logging for debugging
   - Refreshes reactions from server after adding

2. **init-clean.js**
   - Added `markAsRead()` call after loading messages
   - Improved `loadMessageReactions()` with better error handling
   - Added timeout to ensure DOM is ready before loading reactions

3. **create_reactions_table.php**
   - New migration script to create the `message_reactions` table

## Key Features

✅ Reactions save to database
✅ Reactions display with count
✅ Toggle reactions on/off
✅ Messages marked as seen automatically
✅ Comprehensive error logging
✅ Real-time updates
