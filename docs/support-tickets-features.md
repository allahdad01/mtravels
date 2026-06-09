# Support Ticket Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware support ticket system with threaded replies, internal notes, file attachments, SLA tracking per priority level (low/medium/high/critical), automated SLA breach detection via cron, email notifications, and super admin cross-tenant management.

---

## 1. Pages

| Page | Purpose |
|------|---------|
| `admin/support_tickets.php` | Main listing with stats strip, status/priority/category filters, DataTable |
| `admin/support_ticket_create.php` | Create new ticket form |
| `admin/support_ticket_detail.php` | View single ticket, reply, change status, SLA info |
| `super_admin/support_tickets_manage.php` | Cross-tenant ticket management dashboard |
| `super_admin/support_ticket_view.php` | Super admin view with edit controls, internal notes |

---

## 2. Database Tables (5)

### `support_tickets`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| ticket_number | varchar(20) | UNIQUE — auto-generated |
| tenant_id / branch_id | int(11) | Multi-tenant scope |
| created_by_user_id | int(11) | |
| created_by_role | enum(5) | admin, finance, sales, umrah, super_admin |
| category_id | int(11) FK | → `ticket_categories` |
| title | varchar(255) | |
| description | longtext | |
| priority | enum | low, medium, high, critical |
| status | enum | open, in_progress, resolved, closed |
| screenshot_path | varchar(500) | |
| sla_due_at | timestamp | Calculated via SLA rules |
| sla_status | enum | on_track, at_risk, breached, resolved |
| first_response_at | timestamp | |
| resolved_at / resolved_by_user_id | | Resolution tracking |
| resolution_summary | longtext | |
| is_first_response_met / is_resolution_met | tinyint(1) | SLA compliance flags |
| Fulltext index | | title + description |

### `ticket_categories`
| Column | Detail |
|--------|--------|
| id, name (unique), description, icon, color, status, sort_order | 10 active categories |

### `ticket_replies`
| Column | Detail |
|--------|--------|
| id, ticket_id (FK), replied_by_user_id, is_internal_note (boolean), reply_text, screenshot_path, created_at | Fulltext on reply_text |

### `ticket_sla_rules`
| Priority | First Response (hours) | Resolution (hours) |
|----------|----------------------|-------------------|
| Critical | 1 | Stored in DB |
| High | 4 | Stored in DB |
| Medium | 12 | Stored in DB |
| Low | 24 | Stored in DB |

### `ticket_notifications`
| Column | Detail |
|--------|--------|
| ticket_id (FK CASCADE), user_id (FK CASCADE), notification_type (created/reply/status_change/sla_breach), sent_via (email/whatsapp/in_app) | |

---

## 3. Core Classes (3)

### `includes/SupportTicketManager.php`
- `createTicket()` — Validates input, handles file upload, assigns ticket number, inserts record
- `getTicket()` — Single ticket with joins
- `getTicketsByTenant()` — Filtered list for tenant admin
- `getAllTickets()` — Cross-tenant list for super admin
- `getCategories()` — Active categories
- `addReply()` — Threaded reply with optional screenshot
- `updateStatus()` — Status transition + SLA check

### `includes/SLACalculator.php`
- `calculateSLADue()` — Computes `sla_due_at` from priority rules + current time
- `getSLAStatus()` — Determines on_track / at_risk / breached / resolved
- `getSLADisplay()` — Returns human-readable badge + percentage (e.g. "72% used")
- `isAtRisk()` / `isBreached()` — Boolean checks

### `includes/TicketNotificationService.php`
- `notifyTicketCreated()` — Email to assigned team
- `notifyTicketReply()` — Email to ticket creator
- `notifyStatusChange()` — Email on resolution/closing
- `notifySLABreach()` — Critical SLA breach alert

---

## 4. SLA Management

| SLA Status | Meaning |
|-----------|---------|
| **On Track** | Within SLA time limits |
| **At Risk** | > 75% of SLA time elapsed |
| **Breached** | SLA deadline passed |
| **Resolved** | Ticket resolved before breach |

**Cron:** `cron/update_ticket_sla.php` — runs hourly, recalculates all open/in_progress tickets' SLA status, logs breaches, creates notifications.

---

## 5. Status Workflow

```
open → in_progress → resolved → closed
```

Each transition triggers notification via `TicketNotificationService`.

---

## 6. Ticket Numbering

Auto-generated format (logic in `SupportTicketManager`): sequential per tenant, prefixed for identification.

---

## 7. File Uploads

**Class:** `includes/SecureFileUpload.php`

| Setting | Value |
|---------|-------|
| Upload path | `uploads/support_tickets/` |
| Validation | MIME type, file size, path traversal prevention |
| Used for | Screenshots on ticket creation and replies |

---

## 8. Navigation

**Admin sidebar** (nav_items.php lines 531-552):
```
Support (menu caption)
  └─ Support Tickets (dropdown)
       ├─ My Tickets → support_tickets.php
       └─ Submit New Ticket → support_ticket_create.php
```

**Super admin** (header_super_admin.php): direct link to `support_tickets_manage.php`

---

## 9. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full — create, view, reply, resolve, close |
| `finance` | Full — create, view, reply, resolve, close |
| `sales` | Full — create, view, reply, resolve, close |
| `umrah` | Full — create, view, reply, resolve, close |
| `super_admin` | Cross-tenant view + edit, sees internal notes |
| `staff` | No menu visibility |

**Feature gate:** Not feature-gated — available to all plans.

---

## 10. Stats Strip (support_tickets.php)

| Stat | Detail |
|------|--------|
| Total Tickets | Count |
| Open | status = 'open' |
| In Progress | status = 'in_progress' |
| Resolved | status = 'resolved' |
| SLA Breached | sla_status = 'breached' |
| At Risk | sla_status = 'at_risk' |

---

## 11. Filters

| Filter | Type |
|--------|------|
| Status | Dropdown (open/in_progress/resolved/closed/all) |
| Priority | Dropdown (low/medium/high/critical/all) |
| Category | Dropdown from `ticket_categories` |

---

## 12. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` |
| Dari/Farsi | `includes/languages/fa/common.php` |
| Pashto | `includes/languages/ps/common.php` |

---

## 13. File Map

```
admin/
  support_tickets.php               # Main listing page
  support_ticket_create.php         # Create ticket
  support_ticket_detail.php         # View/reply/resolve
super_admin/
  support_tickets_manage.php        # Cross-tenant dashboard
  support_ticket_view.php           # Super admin view + edit
includes/
  SupportTicketManager.php          # Core ticket CRUD class
  SLACalculator.php                 # SLA computation
  TicketNotificationService.php     # Email notification service
  SecureFileUpload.php              # File upload handler
cron/
  update_ticket_sla.php             # Hourly SLA recalculation
uploads/support_tickets/            # Screenshot files
includes/
  nav_items.php                     # Menu entry (lines 531-552)
  language_helpers.php              # __() translation
  header_super_admin.php            # Super admin nav link
```
