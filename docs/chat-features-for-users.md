# 💬 M.Travels Chat & Messaging System — Features

A complete communication hub for your agency — direct messaging, group chats, voice messages, file sharing, WhatsApp integration, and admin broadcast tools. All encrypted and secure.

---

## 💬 Direct Messaging

- **1-on-1 Chat** — Message any user or client in your organization
- **Cross-Branch & Cross-Tenant** — Communicate across branches and even across different agencies (with admin-approved peering)
- **Message Status** — See when your message is sent, delivered, and read
- **Typing Indicators** — Know when someone is typing a reply
- **Reply to Messages** — Quote the original message with context preview
- **Forward Messages** — Forward any message to another contact
- **Search Messages** — Find any message with in-chat search and highlight

---

## 👥 Group Chats

- **Create Groups** — Start group conversations with any combination of users and clients
- **Two Types** — Branch-wide groups (everyone in your branch) or custom groups (selected members)
- **Admin Controls** — Group admins can edit name, description, change group image, add or remove members, or delete the group
- **Read Tracking** — See who has read each message
- **Member Roles** — Admins and members with appropriate permissions

---

## 🎤 Voice Messages

- **Record & Send** — Record voice messages directly in the browser using your microphone
- **Waveform Visualization** — See the audio waveform for every voice message
- **Playback** — Click to play with duration display
- **Supported Formats** — WebM, MP4, OGG, WAV

---

## 📎 File Sharing

- **Share Any File Type** — Images, videos, audio, PDFs, documents — whatever your branch allows
- **Generous Limits** — Share files up to 25MB by default (configurable up to 100MB)
- **Inline Preview** — See images directly in chat (with full-screen lightbox), watch videos, listen to audio, or download files
- **Upload Progress** — See real-time upload progress for large files

---

## 😊 Emoji Reactions

- **React to Any Message** — Add emoji reactions to both direct and group messages
- **Toggle On/Off** — Click the same emoji again to remove it (just like WhatsApp)
- **Multiple Reactions** — Multiple people can react to the same message

---

## 🔒 End-to-End Encryption

- **AES-256-CBC** — All messages are encrypted before being stored in the database
- **Per-Tenant Keys** — Each tenant has their own encryption keys
- **Key Rotation** — Keys can be rotated for enhanced security
- **Audit Logged** — All encryption and decryption operations are tracked

---

## 🚫 Block & Mute

- **Block Users** — Prevent two-way communication with any user
- **Mute Users** — Suppress notifications from specific users without blocking them
- **Manage Easily** — Blocked and muted users are listed in your preferences

---

## 📢 Admin Broadcast Messaging

- **Send to Everyone** — Broadcast messages to all users, all clients, or specific individuals
- **Full CRUD** — Create, view, edit, and delete broadcast messages
- **DataTable Interface** — Professional admin interface with search and pagination

---

## ⚙️ Chat Settings (Admin)

- **File Size Limit** — Set the maximum file size for your branch (default 25MB)
- **Allowed File Types** — Control which file MIME types are accepted
- **Auto-Download** — Toggle whether files download automatically

---

## 🔗 Cross-Tenant & Cross-Branch Peering

- **Tenant Peering** — Allow full communication between different agencies
- **Branch Peering** — Allow specific branch-to-branch communication across agencies
- **Approval Workflow** — Requests are pending until the other side approves
- **Block Control** — Block unwanted communication at any time

---

## 📱 WhatsApp Integration

- **Automated Notifications** — Send booking confirmations, refund notices, and other alerts via WhatsApp
- **18 Message Templates** — Pre-configured templates for visa, Umrah, hotel, refund, and more — in multiple languages
- **Delivery Tracking** — See sent, delivered, and read rates
- **Queue-Based Sending** — Messages are queued and processed via cron job
- **Analytics Dashboard** — Monitor delivery rates, read rates, and response times
- **Available as Add-On** — Requires purchasing the WhatsApp add-on

---

## 🎨 Themes & User Experience

- **6 Visual Themes** — Light (default), Dark, Blue, Purple, Green, Orange
- **Mobile-First Design** — Full responsive layout with slide-in sidebar on mobile
- **Emoji Picker** — Quick-access WhatsApp-style emoji picker
- **Contact Search** — Find anyone in your contact list instantly
- **Image Lightbox** — Full-screen image viewer for shared photos
- **Keyboard Shortcuts** — Ctrl+Enter to send, Escape to close, arrow key navigation
- **Smooth Animations** — Message slide-in, typing dots, voice recording pulse

---

## 🛡️ Security & Access Control

- **Role-Based Access** — Super admins see everything; admins see their tenant; sales see their branch; clients see limited contacts
- **Rate Limiting** — Prevents spam: 50 messages/hour, 100 messages/day, 10 messages/minute
- **CSRF Protection** — All API endpoints are protected
- **Full Audit Log** — Every message send, read, block, mute, and settings change is logged
- **File Validation** — MIME type checking and size limits on all uploads
- **Session Authentication** — All access requires valid login

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| SPA Chat Page | 1 (2,734 lines) |
| Admin Pages | 5 |
| API Endpoints | 9 |
| JavaScript Modules | 18 |
| CSS Files | 2 dedicated + inline |
| Core Library Classes | 5 |
| Database Tables | 20+ |
| Visual Themes | 6 |
| Voice Formats | 4 (webm, mp4, ogg, wav) |
| WhatsApp Templates | 18 |
| Rate Limits | 4 tiers |
| Languages | 3 (English, Dari, Pashto) |

---

*Ready to communicate? All features are available from the Chat dashboard.*
