# Floating Tasks Widget - Visual Guide

## Default State (Minimized)

### What You See
```
┌────────────────────────────────────────────────────┐
│                                                    │
│                                                    │
│        Your Application Content Here              │
│                                                    │
│                                                    │
│                                          ╭──────╮ │
│                                          │  ≡   │ │
│                                          │   3  │ │  ← Floating Button
│                                          ╰──────╯ │    (60px circle)
│                                                    │    Badge: "3" pending
└────────────────────────────────────────────────────┘
```

### Features
- **Position**: Fixed bottom-right, 20px margin
- **Size**: 60px diameter circle
- **Color**: Blue to teal gradient
- **Animation**: Gently bobs up and down (3 sec cycle)
- **Badge**: Red circle with white border, shows pending count
- **Badge Animation**: Pulses with glow effect (2 sec cycle)
- **Shadow**: Soft shadow for depth

### Interactions
- **Hover**: Scale up 10%, shadow increases
- **Click**: Expands to full widget
- **Visual Feedback**: Smooth animations, no lag

---

## Expanded State

### What You See
```
┌────────────────────────────────────────────────────┐
│                                                    │
│        Your Content         ┌──────────────┐      │
│                             │ ≡    My Tasks │      │
│                             │ − ×          │      │
│                             ├──────────────┤      │
│                             │[Input  ] [+] │      │  ← Task Input
│                             ├──────────────┤      │
│                             │ ☐ Task 1     │      │
│                             │ ☐ Task 2     │      │  ← Task Items
│                             │ ☑ Task 3     │      │
│                             ├──────────────┤      │
│                             │ 1/3 [Clear]  │      │  ← Statistics
│                             └──────────────┘      │
│                                                    │
└────────────────────────────────────────────────────┘
```

### Dimensions
- **Width**: 350px (responsive on mobile)
- **Max Height**: 500px
- **Min Height**: Auto (shrinks with content)
- **Border Radius**: 12px corners
- **Position**: Fixed, draggable

### Header Section
```
┌─────────────────────────────────┐
│ ≡ My Tasks         − ×          │  ← Gradient header
└─────────────────────────────────┘
```
- **Background**: Blue to teal gradient
- **Text**: White, 14px, bold
- **Controls**: Minimize (−) and Close (×) buttons
- **Draggable**: Click and drag anywhere on header

### Input Section
```
┌─────────────────────────────────┐
│ [Add a new task...    ] [+]     │  ← Input area
└─────────────────────────────────┘
```
- **Input Field**: 200 char limit
- **Add Button**: + icon, gradient blue
- **Action**: Click + or press Enter to add
- **Feedback**: Input clears on success

### Task Items
```
┌─────────────────────────────────┐
│ ☐ Task 1 Text           [trash] │
│ ☐ Task 2 Text           [trash] │  ← Normal tasks
│ ☑ Task 3 Text           [trash] │  ← Completed
└─────────────────────────────────┘
```

#### Normal Task (Unchecked)
- **Checkbox**: Blue border, clickable
- **Text**: Black, full opacity
- **Delete**: Red trash icon (hidden until hover)
- **Hover**: Light blue background

#### Completed Task (Checked)
- **Checkbox**: Blue-to-teal gradient fill
- **Checkmark**: White ✓ inside
- **Text**: Strikethrough, 60% opacity
- **Delete**: Still functional
- **Visual**: Faded appearance

### Footer Section
```
┌─────────────────────────────────┐
│ 1/3 completed        [Clear]    │  ← Statistics
└─────────────────────────────────┘
```
- **Stats**: "X/Y completed"
- **Clear Button**: Removes all completed tasks
- **Background**: Light gray
- **Border Radius**: Bottom corners

---

## Badge States

### Zero Tasks
```
  ╭────╮
  │ ≡  │
  │ 0  │ ← Red badge "0"
  ╰────╯
```

### Few Pending Tasks
```
  ╭────╮
  │ ≡  │
  │ 3  │ ← Red badge "3"
  ╰────╯
```

### Many Pending Tasks
```
  ╭────╮
  │ ≡  │
  │99+ │ ← Red badge "99+" (100+ tasks)
  ╰────╯
```

### Badge Appearance
- **Color**: Red (#ef4444)
- **Border**: 2px white
- **Shape**: Circular
- **Position**: Top-right of button
- **Size**: 24px diameter
- **Font**: Bold, 11px
- **Animation**: Pulse (glow in/out)

---

## Animations

### Button Float Animation
```
Time:  0s        1.5s       3s
       ─         ─         ─
Y:    [0px] → [-8px] → [0px]   (repeats)
```
- **Duration**: 3 seconds
- **Type**: ease-in-out
- **Loop**: Infinite
- **Distance**: 8px up and down

### Button Hover Animation
```
Before Hover:     After Hover:
  ╭────╮           ╭────╮
  │ ≡  │           │ ≡  │    (110% scale, higher position)
  │ 3  │           │ 3  │    (stronger shadow)
  ╰────╯           ╰────╯
```

### Badge Pulse Animation
```
Time:  0s        1s        2s
       ─         ─         ─
Glow: [dim] → [bright] → [dim]   (repeats)
```
- **Duration**: 2 seconds
- **Type**: ease-in-out
- **Effect**: Shadow brightens/dims
- **Loop**: Infinite

### Task Appearance
```
When added:
Opacity: 0% → 100% (200ms)
Slide:   [←10px] → [0px]
```
- **Type**: Smooth slide-in from left
- **Duration**: 200ms

---

## Responsive Behavior

### Desktop (1024px+)
```
┌──────────────────────────────────┐
│                   ╭──────────────┐
│   Content        │ My Tasks    │
│                  │ Task 1      │
│                  │ Task 2      │
│                  └──────────────┘
│                          ↑
│                  (350px width)
└──────────────────────────────────┘
```
- Widget: 350px width
- Position: Bottom-right, 20px margin
- Full functionality

### Tablet (768px - 1023px)
```
┌────────────────────────────┐
│       Content      ╭────┐  │
│                   │ ≡ 3│  │
│                   ╰────╯  │
│                 (button)   │
└────────────────────────────┘
```
- Button: 48px diameter (smaller)
- When expanded: Full width - 40px
- Adjust for screen size

### Mobile (< 768px)
```
┌──────────────────┐
│                  │
│ Content          │
│                  │
│              ╭──┐
│              │≡3│
│              ╰──┘
└──────────────────┘
```
- Button: 48px diameter
- Floating position: Adjusts for safe area
- Widget: Full width when expanded
- Touch-friendly spacing

---

## Color Scheme

### Primary Gradient
```
#4099ff  ────────────────────  #2ed8b6
(Blue)                         (Teal)
```
Used for: Header, buttons, active states

### Secondary Colors
```
Text:       #1e293b (dark gray)
Borders:    #cbd5e1 (light gray)
Background: #f8fafc (very light)
Complete:   #10b981 (green)
Delete:     #ef4444 (red)
Badge:      #ef4444 (red)
```

---

## Typography

| Element | Font | Size | Weight | Color |
|---------|------|------|--------|-------|
| Header | Inter | 14px | 600 | White |
| Task Text | Inter | 13px | 400 | #1e293b |
| Label | Inter | 12px | 500 | #64748b |
| Input | Inter | 13px | 400 | #1e293b |
| Badge | System | 11px | 700 | White |

---

## Layout Measurements

### Widget Container
```
┌─────────────────────────┐
│ [16px] Header [16px]    │  height: 60px
├─────────────────────────┤
│ [12px]                  │
│ Input Area              │  height: 48px
│ [12px]                  │
├─────────────────────────┤
│                         │
│ Tasks List (scrollable) │  height: auto (max 320px)
│                         │
├─────────────────────────┤
│ [12px] Footer [12px]    │  height: 44px
└─────────────────────────┘
  width: 350px
```

### Button Size
```
Diameter: 60px
  ├─ Icon: 26px (centered)
  └─ Badge: 24px (positioned top-right)
```

---

## Shadows

### Button Shadow
```
Idle:    0 8px 24px rgba(64, 153, 255, 0.35)
Hover:   0 14px 35px rgba(64, 153, 255, 0.45)
```

### Widget Shadow
```
0 20px 60px rgba(0, 0, 0, 0.15)
```

### Badge Shadow
```
Idle:    0 2px 10px rgba(239, 68, 68, 0.4)
Pulse:   0 2px 15px rgba(239, 68, 68, 0.6)
```

---

## State Indicators

### Task States
| State | Visual | Meaning |
|-------|--------|---------|
| ☐ Unchecked | Empty box | Not done yet |
| ☑ Checked | Filled + ✓ | Marked complete |
| [strikethrough] | Gray text | Task completed |

### Button States
| State | Visual | Action |
|-------|--------|--------|
| Idle | Normal | Floating, waiting |
| Hover | Scale up + glow | Ready to click |
| Clicked | Scale down | Opening widget |
| Active | Visible widget | Task management |

---

## Accessibility Features

- ✓ High contrast ratios (WCAG AA compliant)
- ✓ Clear button labels and titles
- ✓ Keyboard accessible (Tab, Enter)
- ✓ Clear focus indicators
- ✓ Semantic HTML
- ✓ Appropriate ARIA labels

---

This visual guide should help you understand how the widget looks and behaves!
