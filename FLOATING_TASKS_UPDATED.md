# Floating Tasks Widget - Updated Features

## Changes Made

### Default State: Collapsed ✅
- Widget now starts **minimized by default** on page load
- Only shows floating button in bottom-right corner
- Takes up minimal screen space

### Pending Task Counter Badge ✅
- **Always visible** on the floating button
- Shows number of **pending (incomplete) tasks**
- Updates in real-time as tasks are added/completed
- Displays "0" when no pending tasks
- Displays "99+" when 100+ tasks (prevents overflow)

### Visual Enhancements ✅
- **Floating animation** - button gently bobs up and down
- **Pulsing badge** - red badge pulses to catch attention
- **Larger button** - 60px diameter (was 56px) for better visibility
- **White border on badge** - for better contrast
- **Smooth transitions** - all animations are smooth

## How It Works

### Default View
```
┌─────────────────────────────────┐
│  Your Application               │
│                                 │
│                                 │
│                        ╭──────╮ │
│                        │  ≡  3│ │  ← Floating button with "3" pending tasks
│                        ╰──────╯ │
└─────────────────────────────────┘
```

### Expanded View (Click the button)
```
┌─────────────────────────────────┐
│  Your Application       ┌──────┐ │
│                         │ My T │ │
│                         │asks  │ │
│                         │──────│ │
│                         │[Add] │ │
│                         │      │ │
│                         │ Task 1│ │
│                         │ Task 2│ │
│                         │ Task 3│ │
│                         │──────│ │
│                         │ 1/3  │ │
│                         └──────┘ │
└─────────────────────────────────┘
```

## Badge Behavior

| State | Display |
|-------|---------|
| 0 pending tasks | Badge shows "0" |
| 1-99 pending tasks | Badge shows number (e.g., "3", "25") |
| 100+ pending tasks | Badge shows "99+" |

## Animation Details

### Floating Button
- **Hover**: Scales up 10% and lifts higher
- **Idle**: Gently bobs up/down (3 second cycle)
- **Click**: Scales down 5% briefly for feedback

### Badge
- **Pulse animation**: Glows and dims every 2 seconds
- **Constant visibility**: Always shows current count

## User Interactions

### Opening the Widget
- **Click** the floating button → Expands to full widget

### Closing the Widget
- **Click** the minimize button (−) → Returns to floating button
- **Click** the close button (×) → Hides widget completely

### Managing Tasks
- **Type & Press Enter** → Add new task
- **Click checkbox** → Mark task complete/incomplete
- **Hover & Click trash** → Delete task
- **Click "Clear"** → Remove all completed tasks

## Auto-Sync Features

- **Every 30 seconds**: Tasks sync with database automatically
- **In real-time**: When you add/edit/delete tasks
- **Across tabs**: Open same page in 2 tabs, changes appear instantly
- **Persistent**: Tasks survive browser refresh, restart

## Browser Compatibility

✅ Chrome/Edge 90+
✅ Firefox 88+
✅ Safari 14+
✅ Mobile browsers (iOS, Android)

## Performance

- **Fast**: Minimal DOM updates
- **Efficient**: Uses modern async/await
- **Smooth**: 60fps animations with GPU acceleration
- **Lightweight**: Only loads when needed

## Customization Options

### Change Default State to Expanded
Edit `floating_tasks.php` line ~550:
```javascript
// Comment out these lines to start expanded
// this.widget.classList.add('minimized');
// this.toggle.style.display = 'block';
```

### Hide Badge When 0 Tasks
Edit `floating_tasks.php` line ~826:
```javascript
} else {
    // this.pendingBadge.style.display = 'none'; // Uncomment this
}
```

### Adjust Badge Animation Speed
Edit CSS in `floating_tasks.php` (~487):
```css
@keyframes pulse {
    0%, 100% { box-shadow: 0 2px 10px rgba(239, 68, 68, 0.4); }
    50% { box-shadow: 0 2px 15px rgba(239, 68, 68, 0.6); }
}
```
Change `2s` to faster (e.g., `1s`) or slower (e.g., `3s`)

### Adjust Float Animation Speed
Edit CSS in `floating_tasks.php` (~442):
```css
animation: float 3s ease-in-out infinite;
```
Change `3s` to desired duration

## Mobile Experience

On mobile phones:
- Button resizes to 48px diameter (from 60px)
- Widget takes full width (100% - 40px margin)
- Touch-friendly spacing
- Badge remains visible and prominent

## Screenshot Examples

### Default (Collapsed)
- Shows floating button with red badge
- Button gently animates
- Badge pulses with task count

### Expanded
- Full widget appears
- Input field for new tasks
- List of all tasks
- Statistics at bottom
- Minimize/close options

## Tips & Tricks

1. **Quick Add**: Click button to expand, immediately start typing
2. **Quick Check**: Click checkbox to toggle completion
3. **Sync Check**: Badge updates instantly with local changes
4. **Multi-Tab**: Open in another tab - sees same tasks
5. **Keyboard**: Press Enter to add instead of clicking button

## Support

Having issues?
1. Check browser console (F12 > Console)
2. Verify database: visit `/test_api.php`
3. Check network tab (F12 > Network)
4. Ensure user is logged in

---

**Status**: ✅ Fully Functional
**Version**: 2.0 (Updated with collapse-by-default)
**Last Updated**: 2024
