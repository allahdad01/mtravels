# Voice Message Feature - Setup & Quick Start

## Setup Instructions

### Step 1: Run Database Migration
Visit the migration script to add voice message support to your database:

```
http://localhost/almoqadas/mtravels/migrations/add_voice_message_columns.php
```

This will:
- Add `message_type` column to `chat_messages` table
- Add `duration` column to `chat_messages` table
- Create `uploads/voices` directory with proper permissions

### Step 2: Verify Files Are In Place
Ensure these files exist:

**Frontend:**
- ✓ `assets/js/chat/VoiceRecorder.js` - Recording engine
- ✓ `assets/js/chat/VoiceMessageUI.js` - UI handler
- ✓ `chat-new.php` - Updated with voice button

**Backend:**
- ✓ `api/voice_messages.php` - Voice message API
- ✓ `migrations/add_voice_message_columns.php` - Database setup

### Step 3: Open Chat Application
Navigate to:
```
http://localhost/almoqadas/mtravels/chat-new.php
```

You should see the microphone icon next to the emoji button in the chat input area.

## Using Voice Messages

### Recording a Voice Message
1. Click the **microphone icon** 🎤 in the chat input toolbar
2. Grant microphone permission if prompted (first use only)
3. Speak your message
4. Click the microphone icon again to **stop and send**
5. The voice message will appear in the chat

### Playing a Voice Message
1. Look for a voice message bubble in the chat
2. Click the **play button** ▶️ to hear the message
3. The button changes to **pause** ⏸️ while playing
4. Recording duration is displayed (e.g., "0:15")

### UI Indicators
- **Recording State**: Microphone button turns red with pulse animation
- **Timer**: Shows elapsed recording time (MM:SS format)
- **Max Duration**: 5 minutes per message
- **File Size**: Max 10MB per message

## Features

### Automatic Behaviors
✓ Auto-stops recording at 5 minutes  
✓ Microphone access required (one-time permission)  
✓ Echo cancellation and noise suppression enabled  
✓ Auto-compression to WebM format  
✓ Immediate message display after sending  
✓ Audio validation before upload  

### Supported Formats
- WebM (recommended, best compression)
- MP4
- Ogg
- MP3
- WAV

### Browser Support
| Browser | Version | Status |
|---------|---------|--------|
| Chrome | 47+ | ✓ Full Support |
| Firefox | 55+ | ✓ Full Support |
| Safari | 11+ | ✓ Full Support |
| Edge | 17+ | ✓ Full Support |
| Mobile Safari | 11+ | ✓ Full Support |
| Chrome Mobile | Latest | ✓ Full Support |

## Permissions & Security

### Required Permissions
- **Microphone Access**: Required to record voice messages
  - Users grant once per browser
  - Can revoke in browser settings anytime

### Access Control
- ✓ User must be authenticated
- ✓ Recipient must exist and not be deleted
- ✓ Users cannot message each other if blocked
- ✓ Cross-branch messages require permission
- ✓ Cross-tenant messages require peering approval
- ✓ Rate limiting prevents message spam

## Troubleshooting

### Microphone Icon Not Visible
**Solution:**
- Check that chat-new.php is being used (not old chat.php)
- Clear browser cache (Ctrl+F5)
- Check browser console for JavaScript errors
- Ensure all script files loaded successfully

### Microphone Permission Denied
**Solution:**
- Click the lock icon in the address bar
- Find "Microphone" permission
- Change to "Allow" and reload page
- In Chrome: Settings → Privacy → Microphone → Allow mtravels

### Recording Fails
**Solution:**
- Check that another app isn't using microphone
- Restart your browser
- Try a different browser
- Check that microphone hardware is working

### Voice Message Doesn't Send
**Solution:**
- Check internet connection
- Verify you're logged in
- Confirm recipient exists
- Check that you're not rate-limited
- Review browser console for errors

### Can't Play Voice Message
**Solution:**
- Verify the voice file exists (check `uploads/voices/` directory)
- Try a different browser
- Check that audio format is supported
- Clear browser cache

## Server Requirements

### Directory Permissions
The `uploads/voices/` directory must be writable by PHP:
```bash
chmod 755 uploads/voices/
```

### PHP Requirements
- PHP 5.6+ (for MediaRecorder API)
- PDO with MySQL driver
- File upload handling enabled

### Storage Requirements
- Estimate 1-2 MB per 1 minute of voice recording
- Recommend at least 1GB free space for uploads

## Admin Configuration

### View Voice Messages Directory
```bash
ls -la uploads/voices/
```

### Clear Old Voice Messages
Voice files are stored indefinitely. To clear old files:
```bash
# Clear files older than 30 days
find uploads/voices/ -type f -mtime +30 -delete
```

### Monitor Voice Message Usage
Check database for voice message counts:
```sql
SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    SUM(duration) as total_duration_seconds,
    AVG(duration) as avg_duration_seconds
FROM chat_messages 
WHERE message_type = 'voice'
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

## Database Schema

### chat_messages Table Additions
```sql
-- Message type: 'text', 'voice', 'file', etc.
ALTER TABLE chat_messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text';

-- Duration in seconds for voice messages
ALTER TABLE chat_messages ADD COLUMN duration INT DEFAULT 0;
```

### Storage Format
Voice messages are stored as JSON in the `content` column:
```json
{
    "type": "voice",
    "duration": 15,
    "filename": "voice_1_45_1234567890_abc123.webm",
    "url": "uploads/voices/voice_1_45_1234567890_abc123.webm"
}
```

## Performance Tips

### For Users
- Keep voice messages under 5 minutes for best experience
- Use in environments with good microphone quality
- Check internet connection before sending
- Allow microphone access when prompted

### For Admins
- Monitor uploads/voices/ directory size
- Implement periodic cleanup of old voice files
- Set storage quotas if needed
- Monitor voice_messages.php error logs

## Limits & Quotas

### Per Message
- Max duration: 5 minutes (300 seconds)
- Max file size: 10MB
- Supported formats: WebM, MP4, Ogg, MP3, WAV

### Per User
- Subject to general message rate limits:
  - 100 messages per hour
  - 1000 messages per day
- These limits apply to both text and voice messages

### Storage
- No built-in per-user storage limit
- Recommend cleaning old files periodically
- Total space depends on server storage

## API Reference

### Client-Side (JavaScript)

**VoiceRecorder Class:**
```javascript
const recorder = new VoiceRecorder();
await recorder.init(); // Request microphone
await recorder.startRecording((seconds) => console.log(seconds));
const blob = await recorder.stopRecording();
recorder.playAudio(blob); // Play it back
```

**VoiceMessageUI Class:**
```javascript
const voiceUI = new VoiceMessageUI(chatAPI, voiceRecorder);
voiceUI.init(); // Setup event listeners
voiceUI.displayVoiceMessage(message, isOwn); // Render message
voiceUI.playVoiceMessage(url, button); // Play message
```

### Server-Side (PHP)

**Voice Upload:**
```
POST /api/voice_messages.php
- to_user_id: int
- audio: file (binary)
- duration: int (seconds)
```

**Voice Stream:**
```
GET /api/voice_messages.php?message_id=123
Response: audio/webm file stream
```

## Next Steps

1. ✓ Run the migration script
2. ✓ Verify files are in place
3. ✓ Open chat-new.php
4. ✓ Test recording a voice message
5. ✓ Test playing the voice message
6. ✓ Check uploads/voices/ directory for file

## Support & Documentation

- **Full Documentation**: See VOICE_MESSAGE_FEATURE.md
- **Code Comments**: Check VoiceRecorder.js and VoiceMessageUI.js
- **Browser DevTools**: F12 → Console for debug messages
- **Server Logs**: Check PHP error logs for upload issues

## Feedback & Issues

If you encounter issues:
1. Check the troubleshooting section above
2. Review browser console (F12)
3. Check server error logs
4. Verify all files are present
5. Run the migration script again

---

**Status**: ✓ Voice message feature is fully implemented and ready to use!

For questions or issues, refer to VOICE_MESSAGE_FEATURE.md for detailed documentation.
