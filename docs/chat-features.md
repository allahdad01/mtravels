# Chat Module — Complete Feature Documentation

## Overview

The Chat/Messaging module is a multi-tenant, multi-branch real-time messaging system with direct messaging, group chat, voice messages, file sharing, emoji reactions, AES-256-CBC encryption, rate limiting, audit logging, admin broadcast messaging, WhatsApp integration, and cross-tenant/branch peering.

---

## 1. Direct 1-on-1 Messaging

**Page:** `chat.php` | **API:** `api/messages.php`

| Feature | Details |
|---------|---------|
| **Participants** | Both `users` (staff/admins) and `clients` as conversation participants |
| **Room ID** | Deterministic format `msg-{type1}{id1}-{type2}{id2}` (sorted numerically) |
| **Authorization** | Cross-branch restricted unless `tenant_super_admin`; cross-tenant requires approved peering |
| **Pagination** | Cursor-based (`before_id`), up to 200 messages per page |
| **Message Status** | Sending → Sent → Delivered → Seen (`seen_at` timestamp) |
| **Typing Indicators** | Tracked via `user_typing_status` table |
| **Reply** | Quote original message with context preview |
| **Forward** | Forward messages to other contacts with modal contact picker |

---

## 2. Group Chat

**API:** `api/group_chats.php`, `api/group_messages.php`, `api/group_members.php`

| Feature | Details |
|---------|---------|
| **Types** | `branch` (entire branch) or `custom` (selected members) |
| **Roles** | `admin` (creator) and `member` |
| **Members** | Both users and clients; only non-clients can create groups |
| **Admin Actions** | Edit name/description, change image, add/remove members, delete group |
| **Messages** | Soft-deleted via `deleted_at` + `deleted_by_user_id` |
| **Read Tracking** | Per-message read status via `chat_group_message_reads` |

---

## 3. Voice Messages

**API:** `api/voice_messages.php` | **JS:** `js/chat/VoiceRecorder.js`

| Feature | Details |
|---------|---------|
| **Recording** | MediaRecorder API via `VoiceRecorder.js` |
| **Formats** | .webm (preferred), .mp4, .mpeg, .ogg, .wav |
| **Storage** | `uploads/voices/{tenant_id}/{user_id}/` |
| **Playback** | WaveSurfer.js integration with waveform visualization |
| **Duration** | Tracked and displayed in UI |

---

## 4. File Sharing

| Feature | Details |
|---------|---------|
| **MIME Types** | Per-branch configurable (default: image, video, audio, PDF, text) |
| **Max Size** | Per-branch configurable (default 25MB, up to 100MB) |
| **Preview** | Inline images (with lightbox), videos, audio; file cards for other types |
| **Upload Progress** | `FileUploadProgress.js` — progress bar and UI |

---

## 5. Message Reactions

**API:** `api/message_reactions.php` | **Table:** `message_reactions`

- Emoji reactions on both direct and group messages
- Unique constraint per (message_id, user_id, emoji)
- Toggle behavior — adding same emoji removes it (WhatsApp-like)

---

## 6. Encryption (AES-256-CBC)

**Class:** `includes/MessageEncryption.php` (368 lines)

| Feature | Details |
|---------|---------|
| **Algorithm** | AES-256-CBC with OpenSSL |
| **Key Management** | Per-tenant keys in `encryption_keys` table; supports rotation |
| **Storage** | Messages stored in both plaintext and encrypted form |
| **Decryption** | Decrypted using originating tenant's key on retrieval |

---

## 7. Block / Mute

**API:** `api/chat_prefs.php` | **Tables:** `user_blocks`, `user_mutes`

| Feature | Details |
|---------|---------|
| **Block** | Prevents two-way messaging; stored in `user_blocks` |
| **Mute** | Suppresses notifications; stored in `user_mutes` |
| **Actions** | block, unblock, mute, unmute via API |

---

## 8. Rate Limiting

**Class:** `includes/RateLimiter.php` (989 lines)

| Limit | Value |
|-------|-------|
| Messages per hour | 50 |
| Messages per day | 100 |
| Messages per minute per user | 10 |
| Contact discovery per hour | 20 |

Algorithms: fixed window, sliding window, token bucket

---

## 9. Admin Broadcast Messaging

**Page:** `admin/send_messages.php` | **Table:** `messages`

| Feature | Details |
|---------|---------|
| **Recipient Types** | All users, all clients, or individual (user or client) |
| **CRUD** | Create, view, edit, delete with DataTable-powered admin interface |
| **Audit** | All actions logged to `activity_log` |

---

## 10. Chat Settings (Admin)

**Page:** `admin/chat_settings.php` (1,261 lines)

| Setting | Default | Description |
|---------|---------|-------------|
| Max file size | 25MB | Per-branch configurable |
| Allowed MIME types | image/,video/,audio/,application/pdf,text/ | Comma-separated prefixes |
| Auto-download | Off | Toggle automatic file download |
| Fallback | Branch → Tenant → System defaults | |

---

## 11. Peering (Cross-Tenant / Cross-Branch)

| Page | Purpose |
|------|---------|
| `admin/tenant_peering.php` | Manage cross-tenant chat permissions |
| `admin/branch_peering.php` | Manage cross-branch chat permissions |

| Status | Description |
|--------|-------------|
| `approved` | Full communication allowed |
| `pending` | Awaiting approval |
| `blocked` | Communication denied |

---

## 12. WhatsApp Integration

| Feature | Details |
|---------|---------|
| **Provider** | Meta WhatsApp Business API |
| **Templates** | 18 pre-configured templates per message type per language |
| **Queue** | Cron-based processing via `cron/process_whatsapp_queue.php` (every 5-10 min) |
| **Analytics** | Delivery rates, read rates, response times |
| **Admin** | `tenant_super_admin/whatsapp_settings.php`, `tenant_super_admin/whatsapp_analytics.php` |
| **Add-on** | Requires purchasing WhatsApp add-on |

---

## 13. Notifications (In-App)

- 25+ transaction types (visa, supplier, ticket, umrah, hotel, etc.)
- Targeted to Admin, Sales, or Finance roles
- API endpoints for fetching, updating status, approving

---

## 14. UI/UX Features

| Feature | Details |
|---------|---------|
| **Themes** | Light (default), Dark, Blue, Purple, Green, Orange |
| **Mobile-First** | Slide-in/out sidebar, back button navigation, full-width on mobile |
| **Emoji Picker** | WhatsApp-style with 16 common emojis |
| **Search** | Contact search in sidebar; in-chat message search with highlight |
| **Lightbox** | Full-screen image viewer for shared images |
| **Animations** | Message slide-in, typing dots, voice recording pulse, reaction hover |
| **Keyboard Navigation** | Ctrl+Enter to send, Escape to close, arrow navigation |

---

## 15. Database Tables (20+)

| Table | Purpose |
|-------|---------|
| `chat_messages` | Core direct messages with encryption support |
| `message_reactions` | Emoji reactions per user per message |
| `messages` | Admin broadcast messages |
| `contact_messages` | Public contact form submissions |
| `chat_groups` | Group chat definitions |
| `chat_group_members` | Group membership with roles |
| `chat_group_messages` | Group messages (soft-delete) |
| `chat_group_message_reads` | Per-message read tracking |
| `branch_chat_settings` | Per-branch chat configuration |
| `tenant_peering` | Cross-tenant permissions |
| `branch_peering` | Cross-branch permissions |
| `chat_audit_log` | Full audit trail for all chat operations |
| `chat_audit_log_archive` | Archived logs (>90 days) |
| `encryption_keys` | Per-tenant AES-256 keys |
| `encryption_key_rotations` | Key rotation tracking |
| `user_blocks` | Blocked users |
| `user_mutes` | Muted users |
| `user_online_sessions` | Online presence |
| `user_typing_status` | Typing indicators |
| `whatsapp_settings` | WhatsApp API configuration |
| `whatsapp_messages` | WhatsApp message queue |
| `whatsapp_templates` | 18 message templates |
| `whatsapp_analytics` | Delivery/read/response analytics |

---

## 16. Technical Architecture

### Core Page: 1 file
`chat.php` (2,734 lines) — full SPA with embedded CSS/JS references

### Admin Pages: 5 files
`admin/chat_settings.php`, `admin/update_message.php`, `admin/delete_message.php`, `admin/tenant_peering.php`, `admin/branch_peering.php`

### API Endpoints: 9 files
| Endpoint | Purpose |
|----------|---------|
| `api/messages.php` | Core messaging CRUD + encryption + rate limiting |
| `api/chat_prefs.php` | Block/mute management |
| `api/chat_settings.php` | Branch/tenant chat settings |
| `api/contacts.php` | Available contacts with peering/role filters |
| `api/group_chats.php` | Group CRUD |
| `api/group_messages.php` | Group message send/list/delete |
| `api/group_members.php` | Group member list with roles |
| `api/message_reactions.php` | Emoji reaction add/remove/list |
| `api/voice_messages.php` | Voice message upload/stream |

### JavaScript: 18 files in `assets/js/chat/`
ChatManager, ChatAPI, ChatUIClean, UIUtilities, MessageActions, VoiceRecorder, VoiceMessageUI, VoiceMessageEnhanced, VoiceMessageAdvanced, AudioVisualization, FileUploadProgress, EmojiPickerEnhanced, KeyboardNavigation, Accessibility, EnhancementsInit, init-clean

### CSS: 2 dedicated + inline
`assets/css/chat.css` (1,558 lines), `assets/css/chat-modern.css` (695 lines), ~1,800 lines inline in chat.php

### Core Includes: 5 files
`includes/ChatAudit.php`, `includes/MessageEncryption.php`, `includes/RateLimiter.php`, `includes/SecureFileUpload.php`, `includes/CommunicationAddonManager.php`

### Access Roles
- tenant_super_admin — full access cross-branch/cross-tenant
- admin — full within tenant + peered tenants
- sales — within own branch + peered branches
- client — own branch only; cannot create groups

### Feature Gating
Gated by `hasFeature('inter_tenant_chat')` in `nav_items.php`

### Security
- CSRF on all POST/PUT/DELETE API endpoints
- AES-256-CBC encryption at rest
- Rate limiting (multiple algorithms)
- Full audit logging (`ChatAudit`)
- Input validation via `secure_query()`
- MIME validation on file uploads
- Branch/tenant/role validation on every request
