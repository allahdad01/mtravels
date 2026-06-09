# 🎫 M.Travels Support Ticket Module — Features

Submit, track, and resolve support requests with threaded conversations, file attachments, priority-based SLA monitoring, and automated email notifications.

---

## 📝 Create & Manage Tickets

- **Submit a Ticket** — Open a new support request with title, description, category, priority level, and optional screenshot attachment.
- **View Ticket Details** — See full ticket information including status, priority, SLA status, category, and all replies.
- **Reply to Tickets** — Add threaded replies to continue the conversation. Attach screenshots to replies.
- **Change Status** — Move tickets through the workflow: Open → In Progress → Resolved → Closed.
- **Resolution Summary** — Record how the issue was resolved when closing a ticket.

---

## 🏷️ Categories

Tickets are organized into categories (currently 10 active) to help route requests to the right team. Each category has its own icon and color for easy visual identification.

---

## ⏱️ SLA Monitoring

Every ticket has an automatic SLA (Service Level Agreement) based on its priority:

| Priority | First Response Target |
|----------|---------------------|
| **Critical** | Within 1 hour |
| **High** | Within 4 hours |
| **Medium** | Within 12 hours |
| **Low** | Within 24 hours |

Each ticket shows its SLA status at a glance:

| Status | Meaning |
|--------|---------|
| **On Track** | Within SLA time limits |
| **At Risk** | More than 75% of SLA time used |
| **Breached** | SLA deadline has passed |
| **Resolved** | Resolved before breach |

The system automatically recalculates SLA statuses every hour via a background cron job.

---

## 🔔 Notifications

Automatic email notifications are sent for:
- Ticket created
- New reply added
- Status changed
- SLA breach detected

---

## 🔍 Filters & Stats

The main ticket listing includes:
- **Stats Strip** — Total, Open, In Progress, Resolved, SLA Breached, At Risk counts
- **Filters** — By status, priority, and category
- **Sortable Table** — With ticket number, title, status, priority, SLA status, dates

---

## 👑 Super Admin Management

Super admins have a cross-tenant dashboard showing tickets from all branches, with the ability to:
- View and edit any ticket
- See internal notes (hidden from regular users)
- Update priority and status
- Monitor SLA compliance across the platform

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Create, view, reply, resolve, close tickets |
| **Finance** | Create, view, reply, resolve, close tickets |
| **Sales** | Create, view, reply, resolve, close tickets |
| **Umrah Staff** | Create, view, reply, resolve, close tickets |
| **Super Admin** | Cross-tenant view + edit, internal notes |
| **Staff** | No access |

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Pages | 3 |
| Super Admin Pages | 2 |
| Core Classes | 3 (Manager, SLA Calculator, Notification Service) |
| Cron Job | 1 (hourly SLA update) |
| Database Tables | 5 |
| Ticket Statuses | 4 (Open, In Progress, Resolved, Closed) |
| Priority Levels | 4 (Low, Medium, High, Critical) |
| SLA Statuses | 4 (On Track, At Risk, Breached, Resolved) |
| Ticket Categories | 10 |
| Languages | 3 |

---

*Track every support request from submission to resolution — with SLA monitoring and full audit trail.*
