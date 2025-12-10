# Testing the New Modular Chat System

## Quick Test (5 minutes)

### Step 1: Open Chat Page
```
1. Open http://localhost/almoqadas/mtravels/chat.php
2. You should see contacts list
3. Check browser console (F12) for any red errors
```

### Step 2: Check Console
```
Open DevTools → Console tab
Look for these messages:

✅ [Chat] Initializing chat system...
✅ [Chat] Loading settings and contacts...
✅ [Chat] Initialization complete
✅ Toast: "Chat ready"

If you see errors in red, something failed.
```

### Step 3: Test Basic Functions
```javascript
// In browser console, type:
window.chatApp.log('Test 1: Manager exists?', window.chatApp.manager ? '✅' : '❌')
window.chatApp.log('Test 2: UI exists?', window.chatApp.ui ? '✅' : '❌')
window.chatApp.log('Test 3: API exists?', window.chatApp.api ? '✅' : '❌')
```

### Step 4: Test Sending Message
```
1. Click on a contact
2. Type "Hello test!"
3. Press Send or Enter
4. Message appears immediately
5. Check console for no errors
```

### Step 5: Test File Upload
```
1. Click file button (📎)
2. Select an image
3. File uploads (you'll see notice)
4. File message appears
5. Check Network tab → api/upload.php success
```

---

## Detailed Test Checklist

### Module Loading ✅
```javascript
// Test in console:
console.log('ChatManager:', typeof ChatManager);     // function
console.log('ChatUI:', typeof ChatUI);              // function
console.log('ChatAPI:', typeof ChatAPI);            // function
console.log('chatApp.manager:', typeof chatApp.manager);  // object
console.log('chatApp.ui:', typeof chatApp.ui);      // object
console.log('chatApp.api:', typeof chatApp.api);    // object
```

### ChatManager Tests
```javascript
// Test state management:
console.log('Contacts:', chatApp.manager.contacts.length > 0 ? '✅' : '❌');
console.log('Settings:', chatApp.manager.settings ? '✅' : '❌');
console.log('Preferences:', chatApp.manager.preferences ? '✅' : '❌');

// Test methods:
chatApp.manager.selectContact(1);  // Select first contact
console.log('Selected:', chatApp.manager.getCurrentContact() ? '✅' : '❌');
console.log('Current contact:', chatApp.manager.getCurrentContact());
```

### ChatUI Tests
```javascript
// Test UI rendering:
console.log('contactList:', chatApp.ui.elements.contactList ? '✅' : '❌');
console.log('messagesEl:', chatApp.ui.elements.messagesEl ? '✅' : '❌');

// Test methods:
chatApp.ui.showSuccess('Test success message');
chatApp.ui.showError('Test error message');
chatApp.ui.showNotice('Test notice');

// Check they appear on screen
```

### ChatAPI Tests
```javascript
// Test API calls:
chatApp.api.getContacts()
    .then(data => console.log('✅ getContacts works:', data))
    .catch(err => console.log('❌ getContacts failed:', err));

chatApp.api.getSettings()
    .then(data => console.log('✅ getSettings works:', data))
    .catch(err => console.log('❌ getSettings failed:', err));
```

---

## User Interaction Tests

### Contact Selection
```
Expected behavior:
1. Click contact in sidebar
2. Chat screen appears
3. Contact name shown in header
4. Avatar displays correctly
5. Previous messages load
6. Sidebar closes (mobile)

Verify:
□ No console errors
□ Contact marked active
□ Correct messages shown
□ Input field focused
```

### Message Sending
```
Expected behavior:
1. Type message
2. Click Send or press Enter
3. Message appears immediately (optimistic UI)
4. Message shows as "outgoing"
5. Time displays correctly
6. Input clears
7. Cursor focused for next message

Verify:
□ Message appears before server response
□ Message is from current user (right side)
□ Send button disables when empty
□ Network request succeeds
□ No duplicate messages
```

### File Upload
```
Expected behavior:
1. Click 📎 button
2. File dialog opens
3. Select file
4. Notice shows "Uploading..."
5. File preview appears (if image)
6. Success notice shows
7. Input clears

Verify:
□ File types accepted correctly
□ Large files rejected with message
□ Network shows api/upload.php success
□ File message shows in conversation
□ No console errors
```

### Voice Recording
```
Expected behavior:
1. Click 🎤 button
2. Record permission prompt (allow)
3. Recording UI appears
4. Mic icon shows red
5. Click again to stop
6. Audio message appears
7. Can play audio

Verify:
□ Microphone permission requested
□ Recording UI shows properly
□ Recording uploads as file
□ Audio message appears
□ No errors in console
```

### Theme Switching
```
Expected behavior:
1. Click 🎨 (palette) button
2. Theme menu appears
3. Select Dark Mode
4. Colors change to dark
5. Select color theme (Blue, Purple, etc)
6. Colors update correctly

Verify:
□ Themes apply immediately
□ Colors are visible and nice
□ Text readable in all themes
□ No styling breaks
```

---

## Browser Console Tests

### Run Full Test Suite
```javascript
// Copy and paste into console:

console.log('=== CHAT MODULE TESTS ===');

// 1. Module availability
console.log('1. Modules loaded:');
console.log('   ChatManager:', typeof ChatManager === 'function' ? '✅' : '❌');
console.log('   ChatUI:', typeof ChatUI === 'function' ? '✅' : '❌');
console.log('   ChatAPI:', typeof ChatAPI === 'function' ? '✅' : '❌');

// 2. Instance creation
console.log('2. Instances created:');
console.log('   chatApp.manager:', chatApp.manager ? '✅' : '❌');
console.log('   chatApp.ui:', chatApp.ui ? '✅' : '❌');
console.log('   chatApp.api:', chatApp.api ? '✅' : '❌');

// 3. Data loaded
console.log('3. Data loaded:');
console.log('   Contacts:', chatApp.manager.contacts.length, 'loaded');
console.log('   Settings:', chatApp.manager.settings ? 'Loaded' : 'Not loaded');
console.log('   Preferences:', chatApp.manager.preferences.blocked.size, 'blocked');

// 4. UI elements cached
console.log('4. UI elements:');
console.log('   contactList:', chatApp.ui.elements.contactList ? '✅' : '❌');
console.log('   messagesEl:', chatApp.ui.elements.messagesEl ? '✅' : '❌');
console.log('   textInput:', chatApp.ui.elements.textInput ? '✅' : '❌');
console.log('   sendBtn:', chatApp.ui.elements.sendBtn ? '✅' : '❌');

// 5. Methods exist
console.log('5. Methods:');
console.log('   manager.selectContact:', typeof chatApp.manager.selectContact === 'function' ? '✅' : '❌');
console.log('   ui.renderContacts:', typeof chatApp.ui.renderContacts === 'function' ? '✅' : '❌');
console.log('   api.sendMessage:', typeof chatApp.api.sendMessage === 'function' ? '✅' : '❌');

console.log('=== TEST COMPLETE ===');
```

---

## Performance Tests

### Load Time
```
1. Open DevTools → Network tab
2. Refresh page (Ctrl+R)
3. Look at metrics:
   ✅ Good: < 2 seconds
   ⚠️ Fair: 2-4 seconds
   ❌ Poor: > 4 seconds

Check which files are slow:
- chat.js
- chat.css
- API calls
```

### Memory Usage
```
1. Open DevTools → Memory tab
2. Take heap snapshot (initial)
3. Select contact
4. Send 10 messages
5. Upload file
6. Take another snapshot
7. Memory should not grow > 50MB
```

### Network Requests
```
Open DevTools → Network tab
Look for:
✅ api/contacts.php - should be fast
✅ api/messages.php - should be fast
✅ api/upload.php - depends on file size
❌ Any 404 or 500 errors?
❌ Any slow requests > 2 seconds?
```

---

## Mobile Testing

### Responsive Design
```
1. Open DevTools (F12)
2. Click device toolbar 📱
3. Select iPhone 12 (390x844)
4. Refresh page

Check:
□ Contact list visible
□ Back button appears
□ Input area at bottom
□ Messages readable
□ All buttons accessible
□ No horizontal scroll
```

### Touch Interactions
```
1. Open on actual mobile device
2. Touch contact → opens chat
3. Touch send button → sends message
4. Touch file button → opens picker
5. Touch back button → returns to list

Check:
□ No delay/lag
□ Touch targets > 44x44 pixels
□ Works with one hand
□ Keyboard doesn't cover input
```

---

## Error Handling Tests

### Network Error
```
1. Open DevTools → Network tab
2. Set throttling to "Offline"
3. Try to send message
4. Should see error: "Check your connection"
5. Set online again
6. Should retry/recover

Check:
□ User sees clear error message
□ No crash or freeze
□ Can recover without refresh
```

### File Upload Error
```
1. Try to upload file > 25MB
2. Should see: "File too large"
3. Try non-supported format
4. Should see: "File type not allowed"

Check:
□ Errors caught gracefully
□ User gets helpful message
□ No console errors
```

### API Timeout
```
1. Simulate slow network (DevTools)
2. Make request with > 10s timeout
3. Should show: "Request timeout"
4. Should allow retry

Check:
□ Timeout detected
□ User notified
□ Can retry operation
```

---

## Cross-Browser Testing

### Chrome/Edge
```
□ Opens without errors
□ All features work
□ Themes look good
□ No console errors
```

### Firefox
```
□ Opens without errors
□ All features work
□ File upload works
□ Voice recording works
```

### Safari
```
□ Opens without errors
□ Scrolling smooth
□ Touch interactions work
□ File upload works
```

---

## Debugging Commands

### Quick Status Check
```javascript
// Copy to console:
function checkChat() {
    const checks = {
        'Manager exists': !!window.chatApp.manager,
        'UI exists': !!window.chatApp.ui,
        'API exists': !!window.chatApp.api,
        'Contacts loaded': window.chatApp.manager.contacts.length > 0,
        'DOM elements cached': Object.keys(window.chatApp.ui.elements).length > 15,
        'No errors': console.error.calls === 0
    };
    
    Object.entries(checks).forEach(([check, result]) => {
        console.log(`${result ? '✅' : '❌'} ${check}`);
    });
}
checkChat();
```

### Check Specific Contact
```javascript
// Get contact 1:
const contact = window.chatApp.manager.contacts[0];
console.log('Contact:', contact);
console.log('Name:', contact.name);
console.log('ID:', contact.id);
console.log('Is blocked?', window.chatApp.manager.isBlocked(contact.id));
console.log('Is muted?', window.chatApp.manager.isMuted(contact.id));
```

### Test Message Sending
```javascript
// Send test message:
const testMsg = async () => {
    try {
        const contact = window.chatApp.manager.contacts[0];
        const result = await window.chatApp.api.sendMessage(contact.id, 'Test message ' + Date.now());
        console.log('✅ Sent:', result);
    } catch (error) {
        console.log('❌ Error:', error.message);
    }
};
testMsg();
```

---

## Test Report Template

Use this to document your testing:

```
DATE: 2024-01-20
TESTER: Your Name
ENVIRONMENT: Chrome on Windows

MODULES:
□ ChatManager - Status: ✅ Working
□ ChatUI - Status: ✅ Working
□ ChatAPI - Status: ✅ Working
□ init.js - Status: ✅ Working

FEATURES:
□ Contact list loads - ✅
□ Contact selection - ✅
□ Message sending - ✅
□ File upload - ✅
□ Voice recording - ⚠️ Permission issue
□ Theme switching - ✅
□ Block/Mute - ✅

ISSUES FOUND:
1. [Issue description if any]
2. [Issue description if any]

NOTES:
- All basic features working
- No console errors
- Performance is good

APPROVED FOR: Next phase
```

---

## Common Issues & Fixes

### Issue: "ChatManager is not defined"
```
Fix: Check that ChatManager.js loads before init.js
     In chat.php, verify script order:
     1. ChatManager.js
     2. ChatUI.js
     3. ChatAPI.js
     4. init.js
```

### Issue: "Cannot read property 'contactList' of undefined"
```
Fix: init.js is running before ChatUI() constructor
     Make sure ChatUI.js loads before init.js
     Check browser Network tab for 404s
```

### Issue: "Messages not sending"
```
Fix: Check Network tab → api/messages.php
     If 401: Session expired (refresh page)
     If 500: Server error (check server logs)
     If timeout: Network issue (slow connection)
```

### Issue: "File upload fails"
```
Fix: Check file size < 25MB (configurable in settings)
     Check file type is allowed (images, videos, PDFs, etc)
     Check api/upload.php exists and has permissions
```

---

## You're All Set!

Once you've verified:
- ✅ Modules load without errors
- ✅ Contacts list displays
- ✅ Can send message
- ✅ Can upload file
- ✅ No console errors

**You're ready for Phase 2!**

---

**Next Step**: Read `IMPLEMENTATION_STARTED.md`

