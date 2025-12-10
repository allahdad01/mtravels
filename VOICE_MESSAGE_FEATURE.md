# Voice Message Feature - Complete Implementation

## Overview
Complete voice messaging feature for the chat application with recording, playback, and database integration.

## Features Implemented

### 1. Frontend Components

#### VoiceRecorder.js
- **Web Audio API Integration**: Records audio from user's microphone
- **Multi-format Support**: Supports WebM, MP4, Ogg formats
- **Audio Visualization**: Real-time frequency analysis during recording
- **Duration Tracking**: Automatic timer during recording with 5-minute max duration
- **Error Handling**: Graceful degradation with browser compatibility checks

**Key Methods:**
- `init()` - Initialize microphone access
- `startRecording(onTick)` - Start recording with callback for timer updates
- `stopRecording()` - Stop recording and return audio blob
- `cancelRecording()` - Discard current recording
- `getAudioBlob()` - Get the recorded audio as blob
- `playAudio(blob)` - Play audio blob in browser
- `formatTime(seconds)` - Format duration as MM:SS

#### VoiceMessageUI.js
- **UI State Management**: Handles recording/playback UI states
- **Event Handling**: Manages voice button clicks and recording states
- **Message Sending**: Integrates with ChatAPI for message transmission
- **Voice Message Display**: Renders voice messages in chat with play controls

**Key Methods:**
- `startRecording()` - Initiate voice recording
- `stopRecording()` - Stop and send voice message
- `sendVoiceMessage(audioBlob)` - Upload voice to server
- `playVoiceMessage(url, playBtn)` - Play voice message in UI
- `displayVoiceMessage(message, isOwn)` - Render voice message bubble
- `updateTimer(seconds)` - Update recording timer display
- `updateVisualization(event)` - Show audio levels during recording

### 2. Backend Components

#### api/voice_messages.php
- **POST Handler**: Accepts voice audio uploads
  - Validates recipient and permissions
  - Checks file format (audio only)
  - Enforces 10MB size limit
  - Stores file with tenant isolation
  - Records in database with metadata
  - Rate limiting integration

- **GET Handler**: Streams voice messages
  - Validates user access
  - Serves audio file with proper headers
  - Cache control headers for optimization

**Security Features:**
- User authentication check
- Recipient validation
- Block list verification
- Tenant isolation
- Branch permission validation
- Rate limiting

### 3. Database
Uses existing `chat_messages` table with new columns:
- `message_type` VARCHAR(20) - 'text' or 'voice'
- `duration` INT - Recording duration in seconds

### 4. Chat Integration

#### init-clean.js Updates
- VoiceRecorder and VoiceMessageUI initialization
- `voiceMessageSent` event listener for message reload
- Message type detection and proper rendering

#### ChatUIClean.js Updates
- Voice message rendering in `renderMessageContent()`
- Play button with audio element integration
- Duration display and waveform visualization

#### ChatAPI.js Updates
- `sendVoiceMessage()` method for API communication
- FormData for binary audio transmission
- Endpoint routing to voice_messages.php

### 5. UI Components

#### Voice Button
- Location: Message input area toolbar
- States:
  - Normal: Microphone icon
  - Recording: Red with pulse animation
  - Displaying: Timer countdown

#### Voice Message Bubble
- Compact player with play button
- Duration display
- Visual distinction from text messages
- Responsive design for mobile

#### Recording Timer
- MM:SS format display
- Updates every 100ms
- Warning animation at max duration

## Usage

### For Users
1. Click the microphone icon in the chat input area
2. Grant microphone permission (first time only)
3. Speak your message (max 5 minutes)
4. Click the microphone button again to stop
5. Message automatically sends after recording stops

### For Developers

#### Initialize Voice Messaging
```javascript
const voiceRecorder = new VoiceRecorder();
const voiceUI = new VoiceMessageUI(chatAPI, voiceRecorder);
voiceUI.init();
```

#### Handle Voice Message Event
```javascript
window.addEventListener('voiceMessageSent', async (e) => {
    const { message, duration } = e.detail;
    // Reload messages to display voice message
});
```

#### Manual Voice Recording
```javascript
// Start
const success = await voiceRecorder.startRecording((seconds) => {
    console.log('Recording for', seconds, 'seconds');
});

// Stop and get blob
const audioBlob = await voiceRecorder.stopRecording();
```

## File Structure
```
├── assets/js/chat/
│   ├── VoiceRecorder.js          # Recording engine
│   ├── VoiceMessageUI.js          # UI management
│   ├── ChatAPI.js                 # Updated with sendVoiceMessage()
│   ├── ChatUIClean.js             # Updated message rendering
│   └── init-clean.js              # Updated initialization
├── api/
│   └── voice_messages.php         # Backend voice API
├── uploads/
│   └── voices/                    # Voice file storage
└── chat-new.php                   # Updated UI with voice button
```

## API Endpoints

### Upload Voice Message
**POST** `/api/voice_messages.php`

**Parameters:**
- `to_user_id` (int, required) - Recipient user ID
- `audio` (file, required) - Audio blob (webm, mp4, ogg, mpeg, wav)
- `duration` (int, optional) - Recording duration in seconds

**Response:**
```json
{
    "success": true,
    "message_id": 123,
    "url": "uploads/voices/voice_1_45_1234567890_abc123.webm",
    "duration": 15,
    "message_type": "voice"
}
```

### Stream Voice Message
**GET** `/api/voice_messages.php?message_id=123`

**Response:** Audio file stream with proper headers

## Styling Classes

### CSS Classes
- `.voice-message` - Voice message bubble
- `.voice-message.received` - Received voice message style
- `.voice-content` - Voice player wrapper
- `.voice-player` - Flex container for player
- `.voice-play-btn` - Play button styling
- `.voice-info` - Duration and waveform container
- `.voice-duration` - Duration text
- `.voice-waveform` - Waveform visualization

### Recording Button States
- `#voiceBtn.recording` - Red button with pulse animation
- `#voiceTimer` - Timer display

## Browser Support
- Chrome/Edge 47+
- Firefox 55+
- Safari 11+
- Mobile browsers (iOS Safari 11+, Chrome Mobile)

**Requires:**
- MediaRecorder API
- Web Audio API
- getUserMedia API

## Limitations & Constraints
- Max recording duration: 5 minutes
- Max file size: 10MB
- Supported formats: WebM, MP4, Ogg, MPEG, WAV
- Same tenant communication required
- Same branch communication (unless cross-branch allowed)
- Rate limiting: subject to message rate limits

## Error Handling

### Common Errors
| Error | Cause | Resolution |
|-------|-------|-----------|
| Microphone permission denied | User rejected | Request again or check browser settings |
| Unsupported format | Browser issue | Use Chrome/Firefox/Safari |
| File too large | Recording too long | Record shorter message |
| Rate limited | Too many messages | Wait before sending more |
| Cross-branch denied | Permission issue | Contact admin |

## Performance Considerations
- Audio compression via WebM/Ogg codecs
- Lazy loading of VoiceRecorder class
- Cleanup of audio streams and resources
- Database queries optimized with prepared statements
- File system isolation with directory permissions

## Security Considerations
- File type validation (audio only)
- Size limits enforced
- Tenant isolation via directory structure
- Permission validation before serving
- Rate limiting on voice uploads
- CSRF protection via session validation

## Future Enhancements
1. Voice message transcription with speech-to-text
2. Audio compression before upload
3. Voice message reactions
4. Voice note title/annotation
5. Audio filters and effects
6. Playback speed control
7. Voice message search/indexing
8. Audio fingerprinting for deduplication

## Troubleshooting

### Voice button not appearing
- Check that VoiceMessageUI is initialized
- Verify HTML element with id="voiceBtn" exists
- Check browser console for JavaScript errors

### Microphone not working
- Check browser permissions
- Verify user granted microphone access
- Check if another app is using microphone
- Try restarting browser

### Voice message not sending
- Check network connection
- Verify recipient exists and not blocked
- Check message rate limits
- Review server logs for errors

### Voice message not playing
- Verify file exists in uploads/voices/
- Check audio format is supported
- Verify browser supports HTML5 audio
- Check browser console for errors

## Testing Checklist
- [ ] Voice recording works with microphone
- [ ] Recording timer displays correctly
- [ ] Recording stops after 5 minutes
- [ ] Voice message uploads successfully
- [ ] Message appears in chat immediately after send
- [ ] Play button works and plays audio
- [ ] Works on desktop and mobile
- [ ] Proper error messages on failures
- [ ] Permissions are enforced
- [ ] Rate limiting prevents spam

## Dependencies
- MediaRecorder API (browser native)
- Web Audio API (browser native)
- ChatAPI class for message transmission
- ChatManager for contact management
- ChatUI for message rendering
